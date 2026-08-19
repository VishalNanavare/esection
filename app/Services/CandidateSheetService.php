<?php

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;
use OpenSpout\Reader\XLSX\Options as ReaderOptions;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Reads a five-column candidate sheet for the New Form screen.
 *
 * This is the typing shortcut esection_basic had, rebuilt properly. It fills
 * the batch table on screen and NOTHING ELSE -- it writes no row, touches no
 * table and creates no batch. The operator still ticks the candidates they
 * want, still chooses the university, year and course by hand, and still
 * presses Save; that submit goes through the same storeBatch() path as a
 * manually typed batch, with the same validation and the same transaction.
 *
 * Deliberately unlike StudentImportService, which is a different feature: that
 * one reads the 24-column admission export, maps boards to universities,
 * remembers those mappings, writes admission records and creates dispatch
 * batches on its own. This one reads five columns and hands them back.
 *
 * Differences from the legacy version, all of them on purpose:
 *
 *   .xlsx only          The legacy took .csv with accept=".csv" as its only
 *                       check -- a browser hint, not a check at all.
 *   Every row validated The legacy validated nothing, so a name could land in
 *                       the case-number column and nobody was told.
 *   Five columns        The legacy read three and its JavaScript then asked for
 *                       a fourth the PHP never sent, so every row rendered
 *                       "undefined" in the maiden-name column.
 *   Rows are capped     The legacy had no limit of any kind.
 */
class CandidateSheetService
{
    /** Smaller than the admission import: this sheet is a handful of columns. */
    public const MAX_SIZE_KB = 1024;

    /**
     * Most rows accepted from one sheet.
     *
     * Matches StudentVerificationService::MAX_BATCH_SIZE, because everything
     * read here is destined for exactly one batch and a larger sheet could
     * never be saved anyway. Better to say so while the file is being read
     * than after the operator has ticked 300 rows.
     */
    public const MAX_ROWS = StudentVerificationService::MAX_BATCH_SIZE;

    /** Stop reading a sheet whose content runs far below the real data. */
    private const ROW_SCAN_CAP = 5000;

    /** A five-column sheet with more than this is not the sheet we were given. */
    private const MAX_COLUMNS = 32;

    /**
     * Column order IS the contract, as it was in the legacy screen: the header
     * row is discarded rather than matched. Anyone can rename the headings, and
     * a five-column sheet has no room for the ambiguity that would justify
     * heading-matching.
     *
     * The limits mirror student_details, so a value is refused here rather than
     * by MySQL during the save.
     */
    private const COLUMNS = [
        0 => ['key' => 'student_name',        'label' => 'Candidate name',    'limit' => 100, 'required' => true],
        1 => ['key' => 'student_nee_name',    'label' => 'Nee / maiden name', 'limit' => 200, 'required' => false],
        2 => ['key' => 'eligibility_case_no', 'label' => 'Eligibility case number', 'limit' => 60, 'required' => true],
        3 => ['key' => 'verification_by_you', 'label' => 'Verification remarks', 'limit' => 60, 'required' => false],
        4 => ['key' => 'email',               'label' => 'Email address',     'limit' => 190, 'required' => false],
    ];

    protected XlsxUploadGuard $guard;

    public function __construct()
    {
        // No helper('esection') here on purpose: this service uses none of it.
        // Values are escaped where they are rendered, and the batch save runs
        // sanitize_xss() itself when the rows are finally written.
        $this->guard = new XlsxUploadGuard();
    }

    /**
     * @return array{
     *     rows: list<array{line:int, status:string, messages:list<string>, data:array<string,string>}>,
     *     ok_count: int,
     *     error_count: int,
     *     sheet: string,
     *     truncated: bool
     * }
     *
     * @throws \InvalidArgumentException with text written for the operator
     */
    public function read(?UploadedFile $file): array
    {
        $this->guard->assertAcceptable($file, self::MAX_SIZE_KB);

        $parsed = $this->parse($file->getTempName());

        if ($parsed['rows'] === []) {
            throw new \InvalidArgumentException(
                'That sheet has no candidate rows below the heading row. Add the candidates and save again.'
            );
        }

        $rows    = [];
        $okCount = 0;

        foreach ($parsed['rows'] as $entry) {
            $evaluated = $this->evaluate($entry['line'], $entry['cells']);
            $rows[]    = $evaluated;

            if ($evaluated['status'] === 'ok') {
                $okCount++;
            }
        }

        return [
            'rows'        => $rows,
            'ok_count'    => $okCount,
            'error_count' => count($rows) - $okCount,
            'sheet'       => $parsed['sheet'],
            'truncated'   => $parsed['truncated'],
        ];
    }

    /**
     * Turns one spreadsheet row into a candidate, with every reason it cannot
     * be used attached to it.
     *
     * A bad row is REPORTED, never dropped. The operator has to be able to see
     * "row 14 has no case number" against the sheet in front of them; silently
     * returning four rows from a five-row file is how the legacy screen lost
     * people's data without telling them.
     *
     * @param list<string> $cells
     *
     * @return array{line:int, status:string, messages:list<string>, data:array<string,string>}
     */
    private function evaluate(int $line, array $cells): array
    {
        $data     = [];
        $messages = [];

        foreach (self::COLUMNS as $index => $column) {
            $value = trim((string) ($cells[$index] ?? ''));

            if ($value === '' && $column['required']) {
                $messages[] = $column['label'] . ' is missing.';
            }

            if (mb_strlen($value) > $column['limit']) {
                $messages[] = $column['label'] . ' is too long (' . mb_strlen($value)
                    . ' characters; the limit is ' . $column['limit'] . ').';
            }

            $data[$column['key']] = $value;
        }

        // Optional, but if it is there it has to be usable -- the batch save
        // stores an unusable address as NULL and the candidate then silently
        // never receives a reminder.
        if ($data['email'] !== '' && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $messages[] = 'Email address is not valid.';
        }

        // The two defaults the manual form applies, applied identically here so
        // an imported row and a typed row are indistinguishable afterwards.
        if ($data['student_nee_name'] === '') {
            $data['student_nee_name'] = '-';
        }

        if ($data['verification_by_you'] === '') {
            $data['verification_by_you'] = 'Marksheet Verification';
        }

        return [
            'line'     => $line,
            'status'   => $messages === [] ? 'ok' : 'error',
            'messages' => $messages,
            'data'     => $data,
        ];
    }

    /**
     * @return array{rows: list<array{line:int, cells:list<string>}>, sheet: string, truncated: bool}
     *
     * @throws \InvalidArgumentException
     */
    private function parse(string $path): array
    {
        // SHOULD_PRESERVE_EMPTY_ROWS so the reported line number is the one
        // Excel shows in its own gutter. With the default, the iterator counts
        // only non-empty rows and "row 14" sends the operator to the wrong
        // place in their file.
        $reader = new Reader(new ReaderOptions(SHOULD_PRESERVE_EMPTY_ROWS: true));

        try {
            // \Throwable rather than \Exception: zend.assertions is enabled on
            // this server and the reader uses \assert(), so a malformed sheet
            // raises AssertionError -- an Error, not an Exception.
            $reader->open($path);

            $rows      = [];
            $sheetName = '';
            $scanned   = 0;
            $truncated = false;
            $seenSheet = false;

            foreach ($reader->getSheetIterator() as $sheet) {
                // Files often lead with an "Instructions" or hidden lookup
                // sheet, so take the first VISIBLE one rather than index 0.
                if (! $sheet->isVisible()) {
                    continue;
                }

                $seenSheet = true;
                $sheetName = $sheet->getName();

                foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                    if (++$scanned > self::ROW_SCAN_CAP) {
                        throw new \InvalidArgumentException(
                            'That sheet has more than ' . number_format(self::ROW_SCAN_CAP) . ' rows. '
                            . 'Delete any stray content below your candidates, or split the file, and try again.'
                        );
                    }

                    // The heading row, discarded by position exactly as the old
                    // screen did. Its wording is never read, so operators can
                    // keep whatever headings they already use.
                    if ($rowIndex === 1) {
                        continue;
                    }

                    if ($row->isEmpty()) {
                        continue;
                    }

                    $cells = $row->cells;

                    if (count($cells) > self::MAX_COLUMNS) {
                        throw new \InvalidArgumentException('That sheet declares an unreasonable number of columns and was not read.');
                    }

                    // continue, NOT break.
                    //
                    // Abandoning OpenSpout's row iterator part-way leaves the
                    // workbook's file handle open even after close() -- proven
                    // on this host: breaking early left the temp file locked
                    // and undeletable, while draining released it. On a web
                    // server that is a handle and a temp file leaked per
                    // oversized upload. Draining the rest costs a few thousand
                    // no-op iterations and is bounded by ROW_SCAN_CAP.
                    if (count($rows) >= self::MAX_ROWS) {
                        $truncated = true;
                        continue;
                    }

                    $rows[] = [
                        'line'  => (int) $rowIndex,
                        'cells' => array_map(
                            static fn ($cell): string => trim((string) $cell->getValue()),
                            $cells
                        ),
                    ];
                }

                break; // first visible sheet only
            }

            if (! $seenSheet) {
                throw new \InvalidArgumentException('That workbook has no visible sheet to read.');
            }

            return ['rows' => $rows, 'sheet' => $sheetName, 'truncated' => $truncated];
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            log_message('error', '[CandidateSheetService::parse] {message}', ['message' => $e::class . ': ' . $e->getMessage()]);

            throw new \InvalidArgumentException('That workbook could not be read. Re-save it from Excel as .xlsx and try again.');
        } finally {
            $reader->close();
        }
    }
}
