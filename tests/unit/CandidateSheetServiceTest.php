<?php

namespace Tests\Unit;

use App\Services\CandidateSheetService;
use CodeIgniter\Test\CIUnitTestCase;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use ReflectionClass;

/**
 * The five-column candidate sheet behind "Fill from Excel" on Students > New.
 *
 * Every test builds a real .xlsx and reads it back, because the failures worth
 * catching here are about how a spreadsheet actually arrives -- a blank row in
 * the middle, a column left empty, a heading someone renamed -- not about how
 * an array behaves.
 *
 * read() needs an UploadedFile, so these drive parse() and evaluate() directly:
 * the upload half is XlsxUploadGuard's, and it is shared with the admission
 * importer that already exercises it.
 *
 * @internal
 */
final class CandidateSheetServiceTest extends CIUnitTestCase
{
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->tempFiles = [];

        parent::tearDown();
    }

    /** @param list<list<string>> $rows */
    private function workbook(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sheet') . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        $this->tempFiles[] = $path;

        return $path;
    }

    /** Runs a workbook through parse() + evaluate(), as read() does. */
    private function evaluateWorkbook(array $rows): array
    {
        $service    = new CandidateSheetService();
        $reflection = new ReflectionClass($service);

        $parsed = $reflection->getMethod('parse')->invoke($service, $this->workbook($rows));

        $out = [];

        foreach ($parsed['rows'] as $entry) {
            $out[] = $reflection->getMethod('evaluate')->invoke($service, $entry['line'], $entry['cells']);
        }

        return ['rows' => $out, 'sheet' => $parsed['sheet'], 'truncated' => $parsed['truncated']];
    }

    private const HEADING = ['Name', 'Nee Name', 'Case No', 'Remarks', 'Email'];

    public function testHeadingRowIsDiscardedWhateverItSays(): void
    {
        $result = $this->evaluateWorkbook([
            ['completely', 'different', 'headings', 'entirely', 'here'],
            ['Priya Sharma', 'Priya Deshmukh', 'IDOL/2026/0142', 'Marksheet Verification', 'priya@example.com'],
        ]);

        $this->assertCount(1, $result['rows'], 'the heading row was treated as a candidate');
        $this->assertSame('Priya Sharma', $result['rows'][0]['data']['student_name']);
    }

    public function testAGoodRowIsUsable(): void
    {
        $result = $this->evaluateWorkbook([
            self::HEADING,
            ['Priya Sharma', 'Priya Deshmukh', 'IDOL/2026/0142', 'Marksheet Verification', 'priya@example.com'],
        ]);

        $row = $result['rows'][0];

        $this->assertSame('ok', $row['status']);
        $this->assertSame([], $row['messages']);
        $this->assertSame('IDOL/2026/0142', $row['data']['eligibility_case_no']);
        $this->assertSame('priya@example.com', $row['data']['email']);
    }

    /**
     * The same two defaults the manual Add Candidate button applies, so an
     * imported row and a typed row are indistinguishable once saved.
     */
    public function testBlankOptionalColumnsGetTheManualFormsDefaults(): void
    {
        $result = $this->evaluateWorkbook([
            self::HEADING,
            ['Rahul Patel', '', 'IDOL/2026/0143', '', ''],
        ]);

        $row = $result['rows'][0];

        $this->assertSame('ok', $row['status']);
        $this->assertSame('-', $row['data']['student_nee_name']);
        $this->assertSame('Marksheet Verification', $row['data']['verification_by_you']);
        $this->assertSame('', $row['data']['email']);
    }

    /**
     * @dataProvider badRows
     */
    public function testUnusableRowIsReportedNotDropped(array $cells, string $expected): void
    {
        $result = $this->evaluateWorkbook([self::HEADING, $cells]);

        $this->assertCount(1, $result['rows'], 'the bad row was dropped instead of reported');

        $row = $result['rows'][0];

        $this->assertSame('error', $row['status']);
        $this->assertStringContainsString($expected, implode(' ', $row['messages']));
    }

    public static function badRows(): array
    {
        return [
            'no name'      => [['', '', 'IDOL/2026/0144', '', ''], 'Candidate name is missing'],
            'no case no'   => [['Sneha Desai', '', '', '', ''], 'Eligibility case number is missing'],
            'bad email'    => [['Amit Kumar', '', 'IDOL/2026/0145', '', 'not-an-email'], 'Email address is not valid'],
            'name too long' => [[str_repeat('A', 130), '', 'IDOL/2026/0146', '', ''], 'Candidate name is too long'],
            'case too long' => [['Meera Joshi', '', str_repeat('C', 80), '', ''], 'Eligibility case number is too long'],
        ];
    }

    /** A row can be wrong in more than one way, and should say so. */
    public function testAllProblemsOnARowAreReportedTogether(): void
    {
        $result = $this->evaluateWorkbook([
            self::HEADING,
            ['', '', '', '', 'nope'],
        ]);

        $messages = implode(' ', $result['rows'][0]['messages']);

        $this->assertStringContainsString('Candidate name is missing', $messages);
        $this->assertStringContainsString('Eligibility case number is missing', $messages);
        $this->assertStringContainsString('Email address is not valid', $messages);
    }

    public function testBlankRowsAreSkipped(): void
    {
        $result = $this->evaluateWorkbook([
            self::HEADING,
            ['Priya Sharma', '', 'IDOL/2026/0142', '', ''],
            ['', '', '', '', ''],
            ['Meera Joshi', '', 'IDOL/2026/0147', '', ''],
        ]);

        $this->assertCount(2, $result['rows']);
    }

    /**
     * The reported line must be the one Excel shows in its own gutter,
     * including the blank row above -- otherwise "row 4" sends the operator to
     * the wrong place in their file.
     */
    public function testReportedLineMatchesTheSpreadsheetGutter(): void
    {
        $result = $this->evaluateWorkbook([
            self::HEADING,                                        // row 1
            ['Priya Sharma', '', 'IDOL/2026/0142', '', ''],       // row 2
            ['', '', '', '', ''],                                 // row 3, blank
            ['Meera Joshi', '', 'IDOL/2026/0147', '', ''],        // row 4
        ]);

        $this->assertSame(2, $result['rows'][0]['line']);
        $this->assertSame(4, $result['rows'][1]['line'], 'the blank row was not counted, so line numbers drifted');
    }

    public function testShortRowsDoNotErrorOnMissingOptionalColumns(): void
    {
        // Only two cells present -- OpenSpout returns a short row rather than
        // padding it, and the optional columns must simply come back empty.
        $result = $this->evaluateWorkbook([
            self::HEADING,
            ['Priya Sharma', '', 'IDOL/2026/0142'],
        ]);

        $this->assertSame('ok', $result['rows'][0]['status']);
        $this->assertSame('-', $result['rows'][0]['data']['student_nee_name']);
    }

    /** One sheet cannot exceed what one batch can hold. */
    public function testRowsAreCappedAtTheBatchLimit(): void
    {
        $rows = [self::HEADING];

        for ($i = 1; $i <= CandidateSheetService::MAX_ROWS + 25; $i++) {
            $rows[] = ['Candidate ' . $i, '', 'IDOL/2026/' . $i, '', ''];
        }

        $result = $this->evaluateWorkbook($rows);

        $this->assertCount(CandidateSheetService::MAX_ROWS, $result['rows']);
        $this->assertTrue($result['truncated'], 'the operator was not told rows had been left out');
    }

    public function testCapMatchesWhatABatchCanActuallyHold(): void
    {
        $this->assertSame(
            \App\Services\StudentVerificationService::MAX_BATCH_SIZE,
            CandidateSheetService::MAX_ROWS,
            'reading more rows than a batch can save would only fail later, at the point of saving'
        );
    }

    /** Whitespace-padded cells are a spreadsheet fact of life. */
    public function testValuesAreTrimmed(): void
    {
        $result = $this->evaluateWorkbook([
            self::HEADING,
            ['  Priya Sharma  ', '', '  IDOL/2026/0142  ', '', '  priya@example.com  '],
        ]);

        $row = $result['rows'][0];

        $this->assertSame('ok', $row['status']);
        $this->assertSame('Priya Sharma', $row['data']['student_name']);
        $this->assertSame('IDOL/2026/0142', $row['data']['eligibility_case_no']);
        $this->assertSame('priya@example.com', $row['data']['email']);
    }

    /**
     * The service must hand back exactly the keys the New Form's studentList
     * uses, or the rows arrive in the table with blank columns.
     */
    public function testReturnedKeysMatchTheFormsStudentList(): void
    {
        $result = $this->evaluateWorkbook([
            self::HEADING,
            ['Priya Sharma', 'Deshmukh', 'IDOL/2026/0142', 'Checked', 'priya@example.com'],
        ]);

        $this->assertSame(
            ['student_name', 'student_nee_name', 'eligibility_case_no', 'verification_by_you', 'email'],
            array_keys($result['rows'][0]['data'])
        );
    }

    /**
     * Builds a workbook whose rows are padded out to $width cells -- the shape
     * OpenSpout yields when a sheet's declared used range is wider than the
     * real data, e.g. a template reused from one that once had content typed
     * and deleted far to the right.
     *
     * @param list<list<string>> $rows
     */
    private function paddedWorkbook(array $rows, int $width): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sheet') . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($path);

        foreach ($rows as $row) {
            $cells = [];

            for ($i = 0; $i < $width; $i++) {
                $cells[] = Cell::fromValue($row[$i] ?? '');
            }

            $writer->addRow(new Row($cells));
        }

        $writer->close();
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * A genuine five-column sheet must not be rejected just because its used
     * range was inherited wide from a reused template.
     */
    public function testPhantomWideUsedRangeIsAccepted(): void
    {
        $service    = new CandidateSheetService();
        $reflection = new ReflectionClass($service);

        // Five real columns, every row padded to 40 cells.
        $path = $this->paddedWorkbook([
            self::HEADING,
            ['Priya Sharma', '', 'IDOL/2026/0142', '', ''],
            ['Rahul Patel', '', 'IDOL/2026/0143', '', 'rahul@example.com'],
        ], 40);

        $parsed = $reflection->getMethod('parse')->invoke($service, $path);

        $this->assertCount(2, $parsed['rows'], 'a padded five-column sheet was wrongly rejected or lost rows');
        $this->assertSame('Priya Sharma', $parsed['rows'][0]['cells'][0]);
    }

    /**
     * A sheet that really does carry more than the column cap of DATA is still
     * refused -- the cap protects against a hostile or wrong file, only the
     * measurement changed.
     */
    public function testSheetWithTooManyRealColumnsIsStillRejected(): void
    {
        $service    = new CandidateSheetService();
        $reflection = new ReflectionClass($service);

        $wideRow = [];
        for ($i = 0; $i < 35; $i++) {
            $wideRow[] = 'value ' . $i;
        }

        $path = $this->paddedWorkbook([$wideRow, $wideRow], 35);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('more than');

        $reflection->getMethod('parse')->invoke($service, $path);
    }
}
