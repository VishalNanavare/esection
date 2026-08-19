<?php

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Everything that has to be true before an .xlsx upload is opened.
 *
 * Lifted out of StudentImportService so the quick candidate sheet on
 * Students > New Form gets the identical protection instead of a second,
 * thinner copy. Upload hardening is exactly the code that must not be
 * reimplemented per feature: the version that gets forgotten is the one that
 * lets a zip bomb through.
 *
 * The feature toggle is deliberately NOT part of this. It gates one particular
 * import, not the act of reading a workbook, and the two features are switched
 * independently.
 */
class XlsxUploadGuard
{
    /** Ceiling on entries inside the archive -- an .xlsx has tens, not thousands. */
    private const MAX_ZIP_ENTRIES = 512;

    /** Total expanded size. A zip bomb is small on disk and enormous open. */
    private const MAX_UNCOMPRESSED_BYTES = 50 * 1024 * 1024;

    /** Per-entry expansion ratio. Ordinary spreadsheet XML is nowhere near this. */
    private const MAX_COMPRESSION_RATIO = 200;

    /** sharedStrings.xml holds every distinct string; enormous means hostile. */
    private const MAX_SHARED_STRINGS = 20 * 1024 * 1024;

    /**
     * @param int $maxKb caller's size ceiling, so each feature can differ
     *
     * @throws \InvalidArgumentException with text written for the operator
     */
    public function assertAcceptable(?UploadedFile $file, int $maxKb): void
    {
        if ($file === null || ! $file->isValid() || $file->hasMoved()) {
            throw new \InvalidArgumentException(
                $file === null
                    ? 'Choose a file to import.'
                    : 'The file could not be uploaded: ' . $file->getErrorString()
            );
        }

        if ($file->getSizeByUnit('kb') > $maxKb) {
            throw new \InvalidArgumentException('The file must be ' . round($maxKb / 1024, 1) . 'MB or smaller.');
        }

        // Extension is the gate, NOT the MIME type. Config\Mimes maps xlsx to
        // application/zip and application/msword among others, and finfo
        // returns application/zip for perfectly valid xlsx on some builds -- so
        // a MIME allowlist both accepts any renamed zip and rejects legitimate
        // files. It produces false positives and false negatives at once.
        $extension = strtolower($file->getClientExtension());

        if ($extension === 'xls' || $extension === 'xlsm') {
            throw new \InvalidArgumentException(
                'This is an .' . $extension . ' workbook. Open it in Excel and use File > Save As > Excel Workbook (.xlsx), then upload again.'
            );
        }

        if ($extension !== 'xlsx') {
            throw new \InvalidArgumentException('Only .xlsx files can be imported.');
        }

        // Magic bytes: every xlsx is a zip. One fread, and it catches a
        // renamed .txt or .pdf immediately.
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
     * Reads the archive's central directory WITHOUT extracting anything, so a
     * hostile workbook is rejected on its declared sizes rather than after it
     * has already filled the disk.
     *
     * @throws \InvalidArgumentException
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
}
