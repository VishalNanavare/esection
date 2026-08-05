<?php

namespace App\Services;

use CodeIgniter\HTTP\ResponseInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The one shared "export engine" every listing page's Export to Excel button
 * calls into -- mirrors PdfGenerationService's shape (single method, returns
 * a ResponseInterface the controller just returns, no per-page duplicated
 * wiring) and the same single-purpose Service pattern used everywhere else
 * in this app.
 */
class ExcelExportService
{
    /**
     * @param array<int, array{header: string, format?: string, width?: float}> $columns
     *        Ordered column specs. `format` is an Excel number-format code
     *        (e.g. 'dd/mm/yyyy hh:mm', '#,##0.00') applied to every cell in
     *        that column -- mandatory for date/timestamp columns, since
     *        OpenSpout's DateTimeCell carries no default format and would
     *        otherwise render as a bare serial-date integer, not a date.
     * @param array<int, array<int, scalar|\DateTimeInterface|null>> $rows
     *        Row values already in $columns order and already correctly
     *        typed by the CALLER (int/float casts, DateTimeImmutable for
     *        date columns) -- this service does no domain-aware conversion.
     * @param string $filenameBase Descriptive, filter-aware, WITHOUT date or
     *        extension (e.g. 'confirmations_history_year-2024'); the current
     *        date and '.xlsx' are appended here.
     * @param string $summaryLine Placed in row 1 above the header row, e.g.
     *        "Filters: Year=2024, Stream=Commerce | 187 row(s) | Generated 03/08/2026 14:32".
     *
     * @throws \InvalidArgumentException when the "Excel Export" feature
     *         toggle (Settings > Feature Toggles) is off -- reuses this
     *         app's own established "safe, user-facing validation text"
     *         signal, so every caller just wraps this call in the same
     *         catch (\InvalidArgumentException $e) block already used
     *         throughout this codebase. Checked first, before any row is
     *         read or written, so a direct URL hit is blocked exactly like
     *         the button disappearing -- not just a cosmetic UI hide.
     */
    public function exportToXlsx(array $columns, array $rows, string $filenameBase, string $summaryLine): ResponseInterface
    {
        if (! feature_enabled('feature_export_enabled')) {
            throw new \InvalidArgumentException('Excel export is currently disabled. Ask an administrator to enable it in Settings > Feature Toggles.');
        }

        // OpenSpout's own openToBrowser() writes straight to php://output via
        // raw header() calls, bypassing CI4's Response object entirely --
        // incompatible with this app's "Service returns a Response" pattern.
        // Writing to a real temp file and reading it back once keeps that
        // pattern intact at the cost of one disk round-trip, cleaned up
        // immediately below.
        $tmpPath = tempnam(sys_get_temp_dir(), 'esxl_');

        try {
            $writer = new Writer(new Options(DEFAULT_COLUMN_WIDTH: 18.0));
            $writer->openToFile($tmpPath);

            $this->writeSheetContent($writer, $writer->getCurrentSheet(), $columns, $rows, $summaryLine);

            $writer->close();
            $binary = file_get_contents($tmpPath);
        } finally {
            if (is_file($tmpPath)) {
                unlink($tmpPath);
            }
        }

        $filename = $filenameBase . '_' . date('Y-m-d') . '.xlsx';

        return service('response')
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($binary);
    }

    /**
     * Multi-sheet workbook written straight to a path on disk -- the backup
     * workbook (one sheet per table). Separate from exportToXlsx() because
     * that one streams a one-shot download and this one produces a file that
     * is KEPT, listed and re-downloaded later.
     *
     * @param array<int, array{
     *     name: string,
     *     columns: array<int, array{header: string, format?: string, width?: float}>,
     *     rows: iterable<int, array<int, scalar|\DateTimeInterface|null>>,
     *     summary: string
     * }> $sheets
     *
     * `rows` is `iterable`, not `array`, on purpose: callers pass a Generator
     * so a table is never materialised in memory. OpenSpout appends each row
     * to the open file handle as addRow() is called, so memory stays flat
     * regardless of table size.
     *
     * Deliberately does NOT check feature_enabled('feature_export_enabled').
     * That toggle governs the user-facing "Export to Excel" report buttons; a
     * disaster-recovery backup must not silently stop running because an
     * admin switched off a reporting feature.
     */
    public function writeWorkbookToFile(array $sheets, string $destinationPath): void
    {
        try {
            $writer = new Writer(new Options(DEFAULT_COLUMN_WIDTH: 18.0));
            $writer->openToFile($destinationPath);

            $usedNames = [];

            foreach (array_values($sheets) as $i => $spec) {
                // openToFile() already created sheet 1, so asking for a new
                // sheet on the first iteration would leave a blank leading
                // "Sheet1" in every workbook.
                $sheet = $i === 0
                    ? $writer->getCurrentSheet()
                    : $writer->addNewSheetAndMakeItCurrent();

                $sheet->setName($this->safeSheetName((string) $spec['name'], $i, $usedNames));

                $this->writeSheetContent($writer, $sheet, $spec['columns'], $spec['rows'], (string) $spec['summary']);
            }

            $writer->close();
        } catch (\Throwable $e) {
            // Never leave a half-written workbook behind for a history row to
            // point at.
            if (is_file($destinationPath)) {
                @unlink($destinationPath);
            }

            throw $e;
        }
    }

    /**
     * The shared body of both public methods: freeze pane, per-column widths,
     * summary row, bold header row, per-column number formats, then the data.
     *
     * @param array<int, array{header: string, format?: string, width?: float}> $columns
     * @param iterable<int, array<int, scalar|\DateTimeInterface|null>>         $rows
     */
    private function writeSheetContent(Writer $writer, Sheet $sheet, array $columns, iterable $rows, string $summaryLine): void
    {
        // Freezes rows 1 (summary) + 2 (header) so both stay visible while
        // scrolling -- "Set to 2 to fix the first row" per SheetView's own
        // docblock, so 3 fixes the first two.
        $sheet->setSheetView((new SheetView())->withFreezeRow(3));

        foreach (array_values($columns) as $i => $col) {
            if (isset($col['width'])) {
                $sheet->setColumnWidth((float) $col['width'], $i + 1);
            }
        }

        $writer->addRow(Row::fromValuesWithStyle([$summaryLine], new Style()));

        $headerStyle = (new Style())->withFontBold(true);
        $writer->addRow(Row::fromValuesWithStyle(array_column($columns, 'header'), $headerStyle));

        $columnStyles = [];
        foreach (array_values($columns) as $i => $col) {
            if (isset($col['format'])) {
                $columnStyles[$i] = (new Style())->withFormat($col['format']);
            }
        }

        foreach ($rows as $row) {
            $writer->addRow(
                $columnStyles === []
                    ? Row::fromValues($row)
                    : Row::fromValuesWithStyles($row, $columnStyles)
            );
        }
    }

    /**
     * Sheet::setName() throws on a blank name, one over 31 characters, one
     * containing \ / ? * : [ ], or a duplicate. Table names are well within
     * that today, but a future migration could break it, so normalise rather
     * than trust.
     *
     * @param array<string, true> $usedNames carried across the workbook, by reference
     */
    private function safeSheetName(string $raw, int $index, array &$usedNames): string
    {
        $name = preg_replace('#[\\\\/?*:\[\]]#', '', $raw) ?? '';
        $name = trim($name);
        $name = mb_substr($name, 0, 31);

        if ($name === '' || isset($usedNames[mb_strtolower($name)])) {
            $suffix = '_' . $index;
            $name   = mb_substr($name === '' ? 'Sheet' : $name, 0, 31 - mb_strlen($suffix)) . $suffix;
        }

        $usedNames[mb_strtolower($name)] = true;

        return $name;
    }
}
