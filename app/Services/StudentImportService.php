<?php

namespace App\Services;

use App\Models\CollegeModel;
use App\Models\EStudentDataModel;
use App\Models\ImportMappingModel;
use App\Models\StudentModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Reader\XLSX\Options as ReaderOptions;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Reads the IDOL admission system's Excel export and turns it into two things:
 * the complete admission record in e_student_data, and dispatch-ready
 * verification batches in student_details.
 *
 * The file is parsed but never written to disk anywhere -- see
 * assertAcceptableUpload() -- and this class NEVER inserts into
 * student_details itself. Batch creation is delegated to
 * StudentVerificationService::storeCandidateBatch(), the same method the New
 * Entry form uses, so there is exactly one write path and the per-batch header
 * fields are applied identically to every row (which is what the dispatch PDFs
 * depend on).
 */
class StudentImportService
{
    /**
     * Public so the view, the client-side guard and the error text all read
     * one number. Matches InstituteDetailsService::MAX_SIZE_KB's precedent and
     * its reasoning. A 200-row export is roughly 15 KB, so this is ~100x
     * headroom.
     */
    public const MAX_SIZE_KB = 2048;

    /**
     * Hard stop on rows walked, distinct from the batch-size cap.
     *
     * SHOULD_PRESERVE_EMPTY_ROWS is on (it has to be, so error messages carry
     * real Excel row numbers), which means a sheet with a stray value in row
     * 500,000 emits half a million synthetic empty rows. This bounds that.
     */
    private const PARSE_ROW_CAP = 20000;

    /**
     * A crafted <dimension ref="A1:XFD1"/> makes the reader allocate 16,384
     * cells per row and copy the whole array per cell. No legitimate export is
     * anywhere near this.
     */
    private const MAX_COLUMNS = 64;

    /** Zip-audit bounds -- see assertAcceptableUpload(). */
    private const MAX_ZIP_ENTRIES        = 512;
    private const MAX_UNCOMPRESSED_BYTES = 50 * 1024 * 1024;
    private const MAX_COMPRESSION_RATIO  = 200;
    private const MAX_SHARED_STRINGS     = 20 * 1024 * 1024;

    /**
     * Header synonyms, normalised key => canonical column.
     *
     * Matching is by NAME, never by position: the single most common operator
     * edit is inserting a "Sr No" column, which under positional mapping would
     * shift every field one place and import a case number into the name field
     * -- silently, onto a letter that gets posted. A renamed header fails
     * loudly instead, naming the column it could not find.
     */
    private const HEADER_SYNONYMS = [
        'admissionyear'                   => 'AdmissionYear',
        'academicyear'                    => 'AdmissionYear',
        'applicationid'                   => 'ApplicationID',
        'applicationno'                   => 'ApplicationID',
        'firstname'                       => 'FirstName',
        'middlename'                      => 'MiddleName',
        'lastname'                        => 'LastName',
        'nameonmarksheet'                 => 'NameOnMarkSheet',
        'nameasonmarksheet'               => 'NameOnMarkSheet',
        'gender'                          => 'Gender',
        'correspondenceaddress'           => 'CorrespondenceAddress',
        'permanentaddress'                => 'PermanentAddress',
        'primarymobileno'                 => 'PrimaryMobileNo',
        'alternatemobileno'               => 'AlternateMobileNo',
        'emailid'                         => 'EmailID',
        'email'                           => 'EmailID',
        'coursename'                      => 'CourseName',
        'lastqual'                        => 'LastQual',
        'qualificationname'               => 'QualificationName',
        'certifyingbodytype'              => 'CertifyingBodyType',
        'certifyingboardname'             => 'CertifyingBoardName',
        'certifyingstatename'             => 'CertifyingStateName',
        'admissionfeeamount'              => 'AdmissionFeeAmount',
        'admissionfeetransactiondatetime' => 'AdmissionFeeTransactionDateTime',
        'applicationstatus'               => 'ApplicationStatus',
        'sceligibilitystatus'             => 'SCEligibilityStatus',
        'eligibilitycaseno'               => 'EligibilityCaseNo',
        'eligibilitycasenumber'           => 'EligibilityCaseNo',
        'eligibilityapprovaldate'         => 'EligibilityApprovalDate',
    ];

    /** Without these the file cannot be processed at all. */
    private const REQUIRED_HEADERS = [
        'ApplicationID', 'NameOnMarkSheet', 'CertifyingBoardName', 'CourseName',
        'AdmissionYear', 'SCEligibilityStatus', 'EligibilityCaseNo',
    ];

    /**
     * Destination column widths, checked in preview.
     *
     * sql_mode carries STRICT_TRANS_TABLES, so one over-long value throws and
     * rolls back its whole batch with a generic message and no indication of
     * which row. sanitize_xss() also EXPANDS strings ("'" becomes "&#039;"),
     * so the check has to run on the encoded form, not the raw one.
     */
    private const DEST_LIMITS = [
        'student_name'                          => 150,
        'student_nee_name'                      => 150,
        'eligibility_case_no'                   => 100,
        'verification_of_marksheet_done_by_you' => 150,
        'email'                                 => 190,
        'admission_taken_in'                    => 60,
        'admission_taken_year'                  => 25,
    ];

    protected EStudentDataModel $eStudentDataModel;
    protected ImportMappingModel $importMappingModel;
    protected StudentModel $studentModel;
    protected CollegeModel $collegeModel;
    protected StudentVerificationService $studentVerificationService;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        helper('esection');
        $this->eStudentDataModel         = new EStudentDataModel();
        $this->importMappingModel        = new ImportMappingModel();
        $this->studentModel              = new StudentModel();
        $this->collegeModel              = new CollegeModel();
        $this->studentVerificationService = new StudentVerificationService();
        $this->activityLogService        = new ActivityLogService();
    }

    // ---------------------------------------------------------------------
    // Upload acceptance
    // ---------------------------------------------------------------------

    /**
     * @throws \InvalidArgumentException with operator-safe text
     */
    private function assertAcceptableUpload(?UploadedFile $file): void
    {
        if (! feature_enabled('feature_import_enabled')) {
            throw new \InvalidArgumentException('Excel import is currently disabled. Ask an administrator to enable it in Settings > Feature Toggles.');
        }

        if ($file === null || ! $file->isValid() || $file->hasMoved()) {
            throw new \InvalidArgumentException(
                $file === null ? 'Choose a file to import.' : 'The file could not be uploaded: ' . $file->getErrorString()
            );
        }

        if ($file->getSizeByUnit('kb') > self::MAX_SIZE_KB) {
            throw new \InvalidArgumentException('The file must be ' . (self::MAX_SIZE_KB / 1024) . 'MB or smaller.');
        }

        // Extension is the gate, NOT the MIME type. Config\Mimes maps xlsx to
        // application/zip and application/msword among others, and finfo
        // returns application/zip for perfectly valid xlsx on some builds -- so
        // a MIME allowlist both accepts any renamed zip and rejects legitimate
        // files. It is a check that produces false positives and false
        // negatives at the same time.
        $extension = strtolower($file->getClientExtension());

        if ($extension === 'xls' || $extension === 'xlsm') {
            throw new \InvalidArgumentException(
                'This is an .' . $extension . ' workbook. Open it in Excel and use File > Save As > Excel Workbook (.xlsx), then upload again.'
            );
        }

        if ($extension !== 'xlsx') {
            throw new \InvalidArgumentException('Only .xlsx files can be imported.');
        }

        // Magic bytes: every xlsx is a zip. This is the real content check --
        // it costs one fread and catches a renamed .txt/.pdf immediately.
        $handle = @fopen($file->getTempName(), 'rb');
        $magic  = $handle ? (string) fread($handle, 4) : '';
        if ($handle) {
            fclose($handle);
        }

        if ($magic !== "PK\x03\x04") {
            throw new \InvalidArgumentException('That file is not a readable Excel workbook.');
        }

        $this->auditZipArchive($file->getTempName());
    }

    /**
     * Reject decompression bombs before the reader touches the file.
     *
     * This matters more than it looks: Reader::open() extracts the shared
     * strings table EAGERLY, before a single row is iterated, and this server
     * has memory_limit=4000M with max_execution_time=0 -- so there is no
     * platform-level backstop at all. The application-level cap IS the control.
     * Reading the zip central directory costs microseconds.
     */
    private function auditZipArchive(string $path): void
    {
        if (! class_exists(\ZipArchive::class)) {
            return;
        }

        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            throw new \InvalidArgumentException('That file is not a readable Excel workbook.');
        }

        try {
            if ($zip->numFiles > self::MAX_ZIP_ENTRIES) {
                throw new \InvalidArgumentException('That workbook has an unexpected internal structure and was not opened.');
            }

            if ($zip->locateName('[Content_Types].xml') === false) {
                throw new \InvalidArgumentException('That file is not a readable Excel workbook.');
            }

            $totalUncompressed = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);

                if ($stat === false) {
                    continue;
                }

                $size       = (int) ($stat['size'] ?? 0);
                $compressed = (int) ($stat['comp_size'] ?? 0);
                $totalUncompressed += $size;

                if ($totalUncompressed > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new \InvalidArgumentException('That workbook expands to an unreasonable size and was not opened.');
                }

                if ($compressed > 0 && ($size / $compressed) > self::MAX_COMPRESSION_RATIO) {
                    throw new \InvalidArgumentException('That workbook expands to an unreasonable size and was not opened.');
                }

                if (str_ends_with((string) ($stat['name'] ?? ''), 'sharedStrings.xml') && $size > self::MAX_SHARED_STRINGS) {
                    throw new \InvalidArgumentException('That workbook has an unexpectedly large text table and was not opened.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    // ---------------------------------------------------------------------
    // Cell reading
    // ---------------------------------------------------------------------

    /**
     * Read one cell to a string, with a note when the value is not usable.
     *
     * Deliberately NOT Row::toArray(): that calls Cell::getValue(), and
     * FormulaCell::getValue() returns the FORMULA TEXT rather than its result
     * -- so a name built with =PROPER(B2) would import literally as "=PROPER(B2)"
     * onto a posted letter -- while ErrorCell::getValue() returns null, making
     * a broken VLOOKUP indistinguishable from an empty cell.
     *
     * @return array{0: string, 1: ?string} [value, note]
     */
    private function readCell(?Cell $cell): array
    {
        if ($cell === null) {
            return ['', null];
        }

        if ($cell instanceof Cell\ErrorCell) {
            return ['', 'contains a spreadsheet error (' . $cell->getRawValue() . ')'];
        }

        if ($cell instanceof Cell\FormulaCell) {
            $computed = $cell->getComputedValue();

            if ($computed === null) {
                return ['', 'contains a formula with no saved result -- select the column in Excel, then Copy and Paste Special > Values'];
            }

            return [$this->scalarToString($computed), null];
        }

        return [$this->scalarToString($cell->getValue()), null];
    }

    /** Typed cell value -> the string the database column expects. */
    private function scalarToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        if ($value instanceof \DateInterval) {
            return $value->format('%H:%I:%S');
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            // (string)(float) produces "1.0E+25"; %F never uses exponent form.
            if (is_finite($value) && floor($value) === $value && abs($value) < 1e15) {
                return number_format($value, 0, '.', '');
            }

            return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }

        if (is_array($value)) {
            // Rich text arrives as an array of runs.
            return implode('', array_map(static fn ($run): string => (string) ($run->text ?? ''), $value));
        }

        return (string) $value;
    }

    /**
     * Excel and copy-paste routinely inject a BOM or a non-breaking space,
     * which makes an otherwise identical header silently fail to match.
     */
    private function cleanText(string $raw): string
    {
        $value = str_replace(["\xEF\xBB\xBF", "\xC2\xA0"], ['', ' '], $raw);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return trim($value);
    }

    /** Header text -> lookup key. */
    private function normalizeHeaderKey(string $raw): string
    {
        $key = strtolower($this->cleanText($raw));
        $key = preg_replace('/\((?:optional|required)\)/u', '', $key) ?? $key;
        $key = str_replace(['*', ':', '.', '_', '-', ' ', '/'], '', $key);

        return trim($key);
    }

    // ---------------------------------------------------------------------
    // Parsing
    // ---------------------------------------------------------------------

    /**
     * Read the sheet into raw associative rows keyed by canonical column name.
     *
     * @return array{rows: list<array{line:int, data: array<string,string>, notes: list<string>}>, mapping: array<string,int>, sheet: string}
     * @throws \InvalidArgumentException with operator-safe text
     */
    private function parseSheet(string $path): array
    {
        // SHOULD_PRESERVE_EMPTY_ROWS is on deliberately. With the default the
        // iterator key is a running count of non-empty rows, so an error would
        // read "row 14" when Excel shows row 19 and the operator cannot find
        // it. Blank rows are skipped below instead.
        $reader = new Reader(new ReaderOptions(SHOULD_PRESERVE_EMPTY_ROWS: true));

        try {
            // catch \Throwable, not \Exception: zend.assertions is enabled on
            // this server and the reader uses \assert(), so a malformed sheet
            // raises AssertionError -- an Error, not an Exception.
            $reader->open($path);

            $sheetName   = '';
            $headerMap   = [];
            $rows        = [];
            $iterated    = 0;
            $foundSheet  = false;

            foreach ($reader->getSheetIterator() as $sheet) {
                // Files often lead with an "Instructions" or hidden lookup
                // sheet, so take the first VISIBLE one rather than index 0.
                if (! $sheet->isVisible()) {
                    continue;
                }

                $foundSheet = true;
                $sheetName  = $sheet->getName();

                foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                    if (++$iterated > self::PARSE_ROW_CAP) {
                        throw new \InvalidArgumentException(
                            'The sheet has content far below the last candidate row. Delete the empty rows below your data and save again.'
                        );
                    }

                    if ($row->isEmpty()) {
                        continue;
                    }

                    $cells = $row->cells;

                    if (count($cells) > self::MAX_COLUMNS) {
                        throw new \InvalidArgumentException('The sheet declares an unreasonable number of columns and was not read.');
                    }

                    if ($headerMap === []) {
                        $candidate = $this->resolveHeaderRow($cells);

                        if ($candidate !== []) {
                            $headerMap = $candidate;
                        }

                        continue;
                    }

                    [$data, $notes] = $this->readDataRow($cells, $headerMap);

                    // A row where every mapped cell is blank is padding, not data.
                    if (implode('', $data) === '') {
                        continue;
                    }

                    $rows[] = ['line' => (int) $rowIndex, 'data' => $data, 'notes' => $notes];
                }

                break;
            }

            if (! $foundSheet) {
                throw new \InvalidArgumentException('That workbook has no visible sheet.');
            }

            if ($headerMap === []) {
                throw new \InvalidArgumentException(
                    'Could not find the column headings. The first rows must include: ' . implode(', ', self::REQUIRED_HEADERS) . '.'
                );
            }

            return ['rows' => $rows, 'mapping' => $headerMap, 'sheet' => $sheetName];
        } finally {
            // Without this a mid-parse throw leaves a sharedstrings<uniqid>
            // folder in the temp directory for every failed upload.
            $reader->close();
        }
    }

    /**
     * Is this row the header? Returns canonical column => cell index, or [].
     *
     * Scanned rather than assumed to be row 1, because this app's own Excel
     * exports put a summary line in row 1 and the headings in row 2 -- so an
     * export/edit/re-import round trip has to work.
     *
     * @param  array<int, Cell> $cells
     * @return array<string, int>
     */
    private function resolveHeaderRow(array $cells): array
    {
        $map = [];

        foreach ($cells as $index => $cell) {
            [$text] = $this->readCell($cell);
            $key    = $this->normalizeHeaderKey($text);

            if ($key === '' || ! isset(self::HEADER_SYNONYMS[$key])) {
                continue;
            }

            $column = self::HEADER_SYNONYMS[$key];

            // First occurrence wins; a duplicated heading must not silently
            // shadow the one the operator meant.
            if (! isset($map[$column])) {
                $map[$column] = (int) $index;
            }
        }

        foreach (self::REQUIRED_HEADERS as $required) {
            if (! isset($map[$required])) {
                return [];
            }
        }

        return $map;
    }

    /**
     * @param  array<int, Cell>   $cells
     * @param  array<string, int> $headerMap
     * @return array{0: array<string,string>, 1: list<string>}
     */
    private function readDataRow(array $cells, array $headerMap): array
    {
        $data  = [];
        $notes = [];

        foreach (EStudentDataModel::SOURCE_COLUMNS as $column) {
            $index = $headerMap[$column] ?? null;

            if ($index === null) {
                $data[$column] = '';
                continue;
            }

            // toArray() can return a non-contiguous array -- a cell beyond the
            // declared <dimension> leaves a hole -- so index defensively.
            [$value, $note] = $this->readCell($cells[$index] ?? null);

            $data[$column] = $this->cleanText($value);

            if ($note !== null) {
                $notes[] = $column . ' ' . $note;
            }
        }

        return [$data, $notes];
    }

    // ---------------------------------------------------------------------
    // Preview
    // ---------------------------------------------------------------------

    /**
     * Parse, resolve mappings and validate -- WITHOUT writing anything.
     *
     * @param  array<string,string> $chosenBoards  source board  => college id, as chosen on screen
     * @param  array<string,string> $chosenCourses source course => stream name, as chosen on screen
     * @throws \InvalidArgumentException with operator-safe text
     */
    public function previewUpload(?UploadedFile $file, array $chosenBoards = [], array $chosenCourses = []): array
    {
        $this->assertAcceptableUpload($file);

        try {
            $parsed = $this->parseSheet($file->getTempName());
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            log_message('error', '[StudentImportService::previewUpload] {message}', ['message' => (string) $e]);

            throw new \InvalidArgumentException('That workbook could not be read. Re-save it from Excel as .xlsx and try again.');
        }

        return $this->buildPreview($parsed, $chosenBoards, $chosenCourses);
    }

    /**
     * @param array{rows: array, mapping: array, sheet: string} $parsed
     */
    private function buildPreview(array $parsed, array $chosenBoards, array $chosenCourses): array
    {
        $rows = $parsed['rows'];

        if ($rows === []) {
            throw new \InvalidArgumentException('That sheet has column headings but no candidate rows.');
        }

        // Remembered answers first, then anything chosen on screen this time.
        $boardMap  = $this->importMappingModel->getMap(ImportMappingModel::TYPE_BOARD) + [];
        $courseMap = $this->importMappingModel->getMap(ImportMappingModel::TYPE_COURSE) + [];

        foreach ($chosenBoards as $source => $target) {
            if (trim((string) $target) !== '') {
                $boardMap[$this->cleanText((string) $source)] = trim((string) $target);
            }
        }
        foreach ($chosenCourses as $source => $target) {
            if (trim((string) $target) !== '') {
                $courseMap[$this->cleanText((string) $source)] = trim((string) $target);
            }
        }

        $boards  = $this->summariseBoards($rows, $boardMap);
        $courses = $this->summariseCourses($rows, $courseMap);

        // One query for the whole file rather than one per candidate.
        $existingCaseNos = $this->studentModel->findExistingCaseNos(
            array_column(array_column($rows, 'data'), 'EligibilityCaseNo')
        );

        $seenCaseNos = [];
        $previewRows = [];
        $counts      = ['ready' => 0, 'warning' => 0, 'error' => 0, 'record_only' => 0];

        foreach ($rows as $row) {
            $evaluated = $this->evaluateRow($row, $boards, $courses, $existingCaseNos, $seenCaseNos);

            $counts[$evaluated['status']] = ($counts[$evaluated['status']] ?? 0) + 1;
            $previewRows[]                = $evaluated;
        }

        // A board group larger than one batch is refused rather than split:
        // each batch is a separate physical dispatch letter, so auto-splitting
        // would silently change how many letters the office has to post.
        $oversized = [];
        foreach ($boards as $board) {
            if ($board['dispatchable'] > StudentVerificationService::MAX_BATCH_SIZE) {
                $oversized[] = $board['source'] . ' (' . $board['dispatchable'] . ' candidates)';
            }
        }

        return [
            'sheet'      => $parsed['sheet'],
            'columns'    => array_keys($parsed['mapping']),
            'boards'     => array_values($boards),
            'courses'    => array_values($courses),
            'rows'       => $previewRows,
            'counts'     => $counts + ['total' => count($rows)],
            'oversized'  => $oversized,
            'max_batch'  => StudentVerificationService::MAX_BATCH_SIZE,
        ];
    }

    /**
     * Distinct certifying bodies, each with its resolved university.
     *
     * Auto-resolution is an EXACT name match only. A fuzzy guess here would be
     * silently addressing an official letter to the wrong institution, so a
     * near-match is offered as a suggestion and left for the operator.
     */
    private function summariseBoards(array $rows, array $boardMap): array
    {
        $boards = [];

        foreach ($rows as $row) {
            $source = $row['data']['CertifyingBoardName'] ?? '';
            $key    = $source;

            if (! isset($boards[$key])) {
                $target = $boardMap[$source] ?? null;
                $auto   = false;

                if ($target === null && $source !== '' && $source !== '-') {
                    $exact = $this->collegeModel->findByExactName($source);

                    if ($exact !== null) {
                        $target = (string) $exact['id'];
                        $auto   = true;
                    }
                }

                $boards[$key] = [
                    'source'       => $source,
                    'type'         => $row['data']['CertifyingBodyType'] ?? '',
                    'rows'         => 0,
                    'dispatchable' => 0,
                    'target'       => $target,
                    'auto'         => $auto,
                    'target_label' => $target !== null ? $this->collegeLabel((int) $target) : '',
                ];
            }

            $boards[$key]['rows']++;

            if ($this->isDispatchable($row['data'])) {
                $boards[$key]['dispatchable']++;
            }
        }

        return $boards;
    }

    /** Distinct course names, each with its resolved stream. */
    private function summariseCourses(array $rows, array $courseMap): array
    {
        $courses = [];

        foreach ($rows as $row) {
            $source = $row['data']['CourseName'] ?? '';

            if (! isset($courses[$source])) {
                $courses[$source] = [
                    'source' => $source,
                    'rows'   => 0,
                    'target' => $courseMap[$source] ?? null,
                    'auto'   => false,
                ];
            }

            $courses[$source]['rows']++;
        }

        return $courses;
    }

    private function collegeLabel(int $collegeId): string
    {
        $college = $this->collegeModel->find($collegeId);

        return $college ? (string) ($college['Name'] ?? '') : '';
    }

    /** Is this row eligible to become a dispatch record at all? */
    private function isDispatchable(array $data): bool
    {
        return strcasecmp(trim($data['SCEligibilityStatus'] ?? ''), 'Approved') === 0
            && trim($data['EligibilityCaseNo'] ?? '') !== '';
    }

    /**
     * Classify one row and collect its messages.
     *
     * Statuses:
     *   error       -> cannot be stored at all
     *   record_only -> stored in e_student_data, not dispatched (not approved,
     *                  no case number, or its board/course is unmapped)
     *   warning     -> will dispatch, but something is worth seeing first
     *   ready       -> will dispatch cleanly
     */
    private function evaluateRow(array $row, array $boards, array $courses, array $existingCaseNos, array &$seenCaseNos): array
    {
        $data     = $row['data'];
        $messages = $row['notes'];
        $status   = 'ready';

        $applicationId = trim($data['ApplicationID'] ?? '');
        $name          = trim($data['NameOnMarkSheet'] ?? '');
        $caseNo        = trim($data['EligibilityCaseNo'] ?? '');
        $email         = trim($data['EmailID'] ?? '');
        $board         = $data['CertifyingBoardName'] ?? '';
        $course        = $data['CourseName'] ?? '';

        if ($applicationId === '') {
            $messages[] = 'Application ID is blank -- this row cannot be identified or updated later.';
            $status     = 'error';
        }

        if ($name === '') {
            $messages[] = 'Name On Mark Sheet is blank.';
            $status     = 'error';
        }

        // Length checks run on the ENCODED form, because sanitize_xss()
        // expands strings and STRICT_TRANS_TABLES turns an overflow into a
        // whole-batch rollback with no indication of which row caused it.
        foreach ([
            'student_name'                          => $name,
            'eligibility_case_no'                   => $caseNo,
            'verification_of_marksheet_done_by_you' => trim($data['QualificationName'] ?? ''),
            'admission_taken_year'                  => trim($data['AdmissionYear'] ?? ''),
        ] as $column => $value) {
            if ($value !== '' && mb_strlen(sanitize_xss($value)) > self::DEST_LIMITS[$column]) {
                $messages[] = 'Value for ' . $column . ' is too long (limit ' . self::DEST_LIMITS[$column] . ' characters).';
                $status     = 'error';
            }
        }

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $messages[] = 'Email address is not valid and will be left blank.';
            $status     = $status === 'error' ? 'error' : 'warning';
        }

        if ($status !== 'error') {
            if (! $this->isDispatchable($data)) {
                $messages[] = strcasecmp(trim($data['SCEligibilityStatus'] ?? ''), 'Approved') !== 0
                    ? 'Eligibility is not Approved -- recorded, but not dispatched.'
                    : 'No eligibility case number -- recorded, but not dispatched.';
                $status = 'record_only';
            } else {
                $boardTarget  = $boards[$board]['target'] ?? null;
                $courseTarget = $courses[$course]['target'] ?? null;

                if ($boardTarget === null) {
                    $messages[] = 'Certifying body is not mapped to a university yet.';
                    $status     = 'record_only';
                }

                if ($courseTarget === null) {
                    $messages[] = 'Course is not mapped to a stream yet.';
                    $status     = 'record_only';
                }
            }
        }

        if ($caseNo !== '') {
            if (isset($seenCaseNos[$caseNo])) {
                $messages[] = 'This case number also appears on row ' . $seenCaseNos[$caseNo] . ' of this file.';
                $status     = $status === 'error' ? 'error' : ($status === 'record_only' ? 'record_only' : 'warning');
            } else {
                $seenCaseNos[$caseNo] = $row['line'];
            }

            if (isset($existingCaseNos[$caseNo])) {
                $messages[] = 'This case number already exists in the system.';
                $status     = $status === 'error' ? 'error' : ($status === 'record_only' ? 'record_only' : 'warning');
            }
        }

        return [
            'line'           => $row['line'],
            'status'         => $status,
            'messages'       => $messages,
            'application_id' => $applicationId,
            'name'           => $name,
            'case_no'        => $caseNo,
            'email'          => $email,
            'board'          => $board,
            'course'         => $course,
            'year'           => trim($data['AdmissionYear'] ?? ''),
            'qualification'  => trim($data['QualificationName'] ?? ''),
        ];
    }

    // ---------------------------------------------------------------------
    // Commit
    // ---------------------------------------------------------------------

    /**
     * Write the import: admission records first, then one dispatch batch per
     * mapped university.
     *
     * The FILE is re-sent and re-parsed rather than the browser posting back
     * the rows it was shown. That keeps the server the only source of the data
     * being written -- the operator's approval of a preview stays meaningful,
     * because the same code produced both -- and avoids shipping a few hundred
     * 24-column rows through the browser and back.
     *
     * @param  array<string,string> $chosenBoards  source board  => college id
     * @param  array<string,string> $chosenCourses source course => stream name
     * @throws \InvalidArgumentException with operator-safe text
     */
    public function commitImport(?UploadedFile $file, array $chosenBoards, array $chosenCourses, string $username): array
    {
        $preview = $this->previewUpload($file, $chosenBoards, $chosenCourses);

        if ($preview['oversized'] !== []) {
            throw new \InvalidArgumentException(
                'One dispatch batch cannot hold more than ' . $preview['max_batch'] . ' candidates. Split the file for: '
                . implode('; ', $preview['oversized']) . '.'
            );
        }

        // Re-parse for the full 24 columns -- the preview carries only what the
        // screen needs to show.
        $parsed = $this->parseSheet($file->getTempName());

        $boards  = $this->indexBySource($preview['boards']);
        $courses = $this->indexBySource($preview['courses']);

        $statusByLine = [];
        foreach ($preview['rows'] as $previewRow) {
            $statusByLine[$previewRow['line']] = $previewRow['status'];
        }

        $importRef = 'IMP-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);

        // --- 1. Admission records -------------------------------------------
        // Committed before any batch, so the full record survives even if a
        // mapping problem stops a dispatch.
        $storable = [];
        foreach ($parsed['rows'] as $row) {
            if (($statusByLine[$row['line']] ?? 'error') === 'error') {
                continue;
            }

            $storable[] = $row;
        }

        if ($storable === []) {
            throw new \InvalidArgumentException('No importable rows were found -- every row has an error. Fix the file and try again.');
        }

        $existingIds = $this->eStudentDataModel->findIdsByApplicationIds(
            array_column(array_column($storable, 'data'), 'ApplicationID')
        );

        $db = $this->eStudentDataModel->db;
        $db->transStart();

        $recordIdByLine = [];
        foreach ($storable as $row) {
            $applicationId = trim($row['data']['ApplicationID'] ?? '');

            $recordIdByLine[$row['line']] = $this->eStudentDataModel->upsertRecord(
                $row['data'],
                $existingIds[$applicationId] ?? null,
                $importRef,
                $username
            );
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new \RuntimeException('The admission records could not be saved. Nothing was written -- please try again.');
        }

        $recordedCount = count($storable);
        $updatedCount  = count(array_intersect_key($existingIds, array_flip(
            array_map(static fn (array $r): string => trim($r['data']['ApplicationID'] ?? ''), $storable)
        )));

        // --- 2. Dispatch batches, one per mapped university ------------------
        $groups = [];
        foreach ($storable as $row) {
            if (($statusByLine[$row['line']] ?? '') === 'record_only') {
                continue;
            }

            $boardSource = $row['data']['CertifyingBoardName'] ?? '';
            $groups[$boardSource][] = $row;
        }

        $batches = [];
        foreach ($groups as $boardSource => $groupRows) {
            $collegeId = $boards[$boardSource]['target'] ?? null;

            if ($collegeId === null) {
                continue;
            }

            $batches[] = $this->createBatchForBoard(
                (int) $collegeId,
                $boardSource,
                $groupRows,
                $courses,
                $recordIdByLine,
                $username
            );
        }

        // --- 3. Remember the mappings ----------------------------------------
        foreach ($chosenBoards as $source => $target) {
            $this->importMappingModel->remember(ImportMappingModel::TYPE_BOARD, (string) $source, (string) $target, $username);
        }
        foreach ($chosenCourses as $source => $target) {
            $this->importMappingModel->remember(ImportMappingModel::TYPE_COURSE, (string) $source, (string) $target, $username);
        }

        $this->activityLogService->record(
            'student.import',
            'import',
            null,
            'Imported ' . $recordedCount . ' admission record(s) (' . $updatedCount . ' updated) and created '
            . count($batches) . ' dispatch batch(es) [' . $importRef . ']'
        );

        return [
            'import_ref'  => $importRef,
            'recorded'    => $recordedCount,
            'updated'     => $updatedCount,
            'batches'     => $batches,
            'not_dispatched' => $preview['counts']['record_only'] ?? 0,
            'skipped'     => $preview['counts']['error'] ?? 0,
        ];
    }

    /**
     * One dispatch batch, delegated to the same service the New Entry form
     * uses so there is a single write path into student_details.
     */
    private function createBatchForBoard(
        int $collegeId,
        string $boardSource,
        array $groupRows,
        array $courses,
        array $recordIdByLine,
        string $username
    ): array {
        $college = $this->collegeModel->find($collegeId);

        if ($college === null) {
            throw new \InvalidArgumentException('The university chosen for "' . $boardSource . '" no longer exists.');
        }

        $first = $groupRows[0]['data'];

        $students = [];
        foreach ($groupRows as $row) {
            $students[] = [
                'student_name'        => $row['data']['NameOnMarkSheet'] ?? '',
                // The manual form defaults a blank maiden name to '-', and the
                // service's own ?? only fires on a MISSING key, not an empty
                // string -- so an imported blank has to be defaulted here or
                // imported rows would differ from manually entered ones.
                'student_nee_name'    => '-',
                'eligibility_case_no' => $row['data']['EligibilityCaseNo'] ?? '',
                'verification_by_you' => ($row['data']['QualificationName'] ?? '') !== ''
                    ? $row['data']['QualificationName']
                    : 'Marksheet Verification',
                'email'               => $row['data']['EmailID'] ?? '',
            ];
        }

        $result = $this->studentVerificationService->storeCandidateBatch([
            // common_no omitted on purpose: the service derives it fresh inside
            // its own transaction. An import can sit on screen for minutes, and
            // a stale batch number would collide with another tab's batch and
            // silently merge the two onto one dispatch letter.
            'to_name'              => $college['head_name'] ?? 'The Controller of Examinations',
            'clg_add'              => $college['Address'] ?? '',
            'admission_taken_year' => $first['AdmissionYear'] ?? '',
            'admission_taken_in'   => $courses[$first['CourseName'] ?? '']['target'] ?? '',
            'in_favour_of'         => $college['in_favour_of'] ?? '',
            'students'             => $students,
        ], $username, 'import');

        // Link the admission records to the batch that dispatched them.
        $ids = [];
        foreach ($groupRows as $row) {
            if (isset($recordIdByLine[$row['line']])) {
                $ids[] = $recordIdByLine[$row['line']];
            }
        }

        $this->eStudentDataModel->markDispatched($ids, $result['array_space']);

        return [
            'board'       => $boardSource,
            'university'  => $college['Name'] ?? '',
            'array_space' => $result['array_space'],
            'count'       => $result['count'],
        ];
    }

    /** @return array<string, array> */
    private function indexBySource(array $entries): array
    {
        $indexed = [];

        foreach ($entries as $entry) {
            $indexed[$entry['source']] = $entry;
        }

        return $indexed;
    }

    // ---------------------------------------------------------------------
    // Template
    // ---------------------------------------------------------------------

    /**
     * The blank import workbook, with the export's own 24 headings and three
     * example rows.
     *
     * Written with a direct Writer rather than ExcelExportService::exportToXlsx()
     * on purpose: that method throws when feature_export_enabled is off -- which
     * has nothing to do with importing -- and writes an "Exported 1 row(s)"
     * audit entry every time someone downloads a blank template.
     */
    public function buildTemplate(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'esimp_');

        $writer = new \OpenSpout\Writer\XLSX\Writer(
            new \OpenSpout\Writer\XLSX\Options(DEFAULT_COLUMN_WIDTH: 22.0)
        );
        $writer->openToFile($path);

        // withFontBold(true) + fromValuesWithStyle -- the same idiom
        // ExcelExportService::writeSheetContent() uses for its header row.
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValuesWithStyle(
            EStudentDataModel::SOURCE_COLUMNS,
            (new \OpenSpout\Common\Entity\Style\Style())->withFontBold(true)
        ));

        // EligibilityCaseNo is forced to Text. Excel stores "0012345" in a
        // General cell as the NUMBER 12345, and those leading zeros are gone
        // before any reader sees the file -- unrecoverable. A Text-formatted
        // column stops it happening in the first place.
        $caseNoIndex = array_search('EligibilityCaseNo', EStudentDataModel::SOURCE_COLUMNS, true);
        $textStyle   = (new \OpenSpout\Common\Entity\Style\Style())->withFormat('@');

        foreach ($this->templateSampleRows() as $sample) {
            $values = [];
            foreach (EStudentDataModel::SOURCE_COLUMNS as $column) {
                $values[] = $sample[$column] ?? '';
            }

            $writer->addRow($caseNoIndex === false
                ? \OpenSpout\Common\Entity\Row::fromValues($values)
                : \OpenSpout\Common\Entity\Row::fromValuesWithStyles($values, [$caseNoIndex => $textStyle]));
        }

        $writer->close();

        return $path;
    }

    /**
     * Sample values drawn from the real vocabularies already in the data, so
     * the expected format is self-evident.
     *
     * @return list<array<string,string>>
     */
    private function templateSampleRows(): array
    {
        return [
            [
                'AdmissionYear' => '2025-2026', 'ApplicationID' => '2921',
                'FirstName' => 'PRANJAL', 'MiddleName' => 'PANCHAL', 'LastName' => 'ASHOK',
                'NameOnMarkSheet' => 'PANCHAL PRANJAL ASHOK YAMINI', 'Gender' => 'Female',
                'CorrespondenceAddress' => 'Flat 12, Sunrise Apartments, Mumbai',
                'PermanentAddress' => 'Flat 12, Sunrise Apartments, Mumbai',
                'PrimaryMobileNo' => '9820000001', 'AlternateMobileNo' => '',
                'EmailID' => 'pranjal.panchal@example.com',
                'CourseName' => 'BA-2017 Pattern-TY BA-TY BA',
                'LastQual' => 'HSC[Arts]', 'QualificationName' => 'HSC',
                'CertifyingBodyType' => 'Board',
                'CertifyingBoardName' => 'MAHARASHTRA STATE BOARD OF SECONDARY AND HIGHER SECONDARY EDUCATION',
                'CertifyingStateName' => 'Maharashtra',
                'AdmissionFeeAmount' => '5000', 'AdmissionFeeTransactionDateTime' => '01-July-2025 11:20',
                'ApplicationStatus' => 'Approved', 'SCEligibilityStatus' => 'Approved',
                'EligibilityCaseNo' => 'IDOL20252921', 'EligibilityApprovalDate' => '09-September-2025',
            ],
            [
                'AdmissionYear' => '2025-2026', 'ApplicationID' => '4176',
                'FirstName' => 'VATSAL', 'MiddleName' => 'PANDYA', 'LastName' => 'BHAVESH',
                'NameOnMarkSheet' => 'PANDYA VATSAL BHAVESH KAVITA', 'Gender' => 'Male',
                'CorrespondenceAddress' => '', 'PermanentAddress' => '',
                'PrimaryMobileNo' => '9820000002', 'AlternateMobileNo' => '',
                'EmailID' => '',
                'CourseName' => 'B.Com-2017 Pattern-T.Y.B.Com-T.Y.B.Com',
                'LastQual' => 'HSC[Commerce]', 'QualificationName' => 'HSC',
                'CertifyingBodyType' => 'Board',
                'CertifyingBoardName' => 'CENTRAL BOARD OF SECONDARY EDUCATION',
                'CertifyingStateName' => 'Uttarakhand',
                'AdmissionFeeAmount' => '5000', 'AdmissionFeeTransactionDateTime' => '',
                'ApplicationStatus' => 'Applied', 'SCEligibilityStatus' => 'Approved',
                'EligibilityCaseNo' => 'IDOL20254176', 'EligibilityApprovalDate' => '15-September-2025',
            ],
            [
                'AdmissionYear' => '2025-2026', 'ApplicationID' => '7216',
                'FirstName' => 'VIVEK', 'MiddleName' => 'SAWANT', 'LastName' => 'GANGARAM',
                'NameOnMarkSheet' => 'SAWANT VIVEK GANGARAM SUNITA', 'Gender' => 'Male',
                'CorrespondenceAddress' => '', 'PermanentAddress' => '',
                'PrimaryMobileNo' => '', 'AlternateMobileNo' => '',
                'EmailID' => 'vivek.sawant@example.com',
                'CourseName' => 'B.Sc. (IT)-80-20 Pattern-S.Y.B.Sc(IT)-S.Y.B.Sc.IT Sem III',
                'LastQual' => 'Diploma In Chemical', 'QualificationName' => 'Diploma In Chemical',
                'CertifyingBodyType' => 'Board',
                'CertifyingBoardName' => 'Maharashtra State Board of Technical Education',
                'CertifyingStateName' => 'Maharashtra',
                'AdmissionFeeAmount' => '', 'AdmissionFeeTransactionDateTime' => '',
                // Deliberately Pending, to show what a not-yet-dispatchable row
                // looks like in the preview.
                'ApplicationStatus' => 'Applied', 'SCEligibilityStatus' => 'Pending',
                'EligibilityCaseNo' => '', 'EligibilityApprovalDate' => '',
            ],
        ];
    }
}
