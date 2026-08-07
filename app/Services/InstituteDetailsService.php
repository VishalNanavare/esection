<?php

namespace App\Services;

class InstituteDetailsService
{
    private const KEYS = [
        'institute_name',
        'institute_university_title',
        'institute_address',
        'institute_contact',
        'institute_signatory_name',
        'institute_signatory_designation',
        'institute_logo_path',
        'institute_letterhead_path',
        'institute_signature_space_lines',
    ];

    private const ALLOWED_MIMES = ['image/png', 'image/jpeg'];

    // Public: the view/JS reads this to keep the client-side check and the
    // "up to Xmb" copy in sync with the actual backend limit, rather than a
    // second hardcoded number that could silently drift from this one.
    public const MAX_SIZE_KB = 2048;

    // Exact pixel dimensions required -- not a range. The letterhead
    // requirement matches the real image already uploaded (esection_header
    // .png, 1486x368); the logo requirement is a common square size for a
    // seal/crest-style logo. Public for the same reason as MAX_SIZE_KB.
    public const LOGO_WIDTH = 300;
    public const LOGO_HEIGHT = 300;
    public const LETTERHEAD_WIDTH = 1486;
    public const LETTERHEAD_HEIGHT = 368;

    // The blank gap above the signatory name in every letter, reserved for a
    // physical wet-ink signature. A floor of 1 means it can never be
    // configured down to "no space at all"; leaving the field blank instead
    // keeps each letter template's own current default (see the 4 pdf/*.php
    // templates), it does not mean zero.
    public const MIN_SIGNATURE_SPACE_LINES = 1;
    public const MAX_SIGNATURE_SPACE_LINES = 5;

    protected SettingService $settingService;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        helper('esection');
        $this->settingService     = new SettingService();
        $this->activityLogService = new ActivityLogService();
    }

    public function getAll(): array
    {
        return $this->settingService->getMany(self::KEYS);
    }

    /**
     * @param array $postData Raw POST-shaped array of text fields.
     * @param array $files    Expected keys 'logo' and 'letterhead', each an
     *                        \CodeIgniter\HTTP\Files\UploadedFile|null.
     *
     * @throws \InvalidArgumentException on invalid input or a bad upload
     */
    public function save(array $postData, array $files): void
    {
        $name = trim(sanitize_xss($postData['institute_name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Institute name is required.');
        }

        $this->settingService->set('institute_name', $name, 'institute');

        // A field ABSENT from $postData (array_key_exists false) means "not
        // part of this submission" and keeps its existing value -- matching
        // the same "leave blank to keep current" principle already used for
        // file uploads below. A field present but explicitly '' still means
        // "clear it": the real HTML form always posts every input, so a
        // deliberate clear must still take effect.
        $optionalFields = [
            'institute_university_title',
            'institute_address',
            'institute_contact',
            'institute_signatory_name',
            'institute_signatory_designation',
        ];

        foreach ($optionalFields as $key) {
            if (! array_key_exists($key, $postData)) {
                continue;
            }
            $this->settingService->set($key, sanitize_xss($postData[$key]), 'institute');
        }

        // Same absent-vs-blank distinction as above, but this one is a
        // constrained integer, not free text -- an explicit out-of-range
        // value is rejected outright rather than silently clamped, matching
        // this file's existing pixel-dimension validation below.
        if (array_key_exists('institute_signature_space_lines', $postData)) {
            $raw = trim((string) $postData['institute_signature_space_lines']);

            if ($raw === '') {
                $this->settingService->set('institute_signature_space_lines', '', 'institute');
            } else {
                $lines = (int) $raw;

                if ((string) $lines !== $raw || $lines < self::MIN_SIGNATURE_SPACE_LINES || $lines > self::MAX_SIGNATURE_SPACE_LINES) {
                    throw new \InvalidArgumentException(
                        'Signature space must be a whole number between ' . self::MIN_SIGNATURE_SPACE_LINES
                        . ' and ' . self::MAX_SIGNATURE_SPACE_LINES . '.'
                    );
                }

                $this->settingService->set('institute_signature_space_lines', (string) $lines, 'institute');
            }
        }

        $this->storeUploadedFile($files['logo'] ?? null, 'institute_logo_path', self::LOGO_WIDTH, self::LOGO_HEIGHT);
        $this->storeUploadedFile($files['letterhead'] ?? null, 'institute_letterhead_path', self::LETTERHEAD_WIDTH, self::LETTERHEAD_HEIGHT);

        $this->activityLogService->record('settings.institute.update', 'settings', null, 'Updated institute details');
    }

    /**
     * @throws \InvalidArgumentException when a file was submitted but is invalid
     */
    private function storeUploadedFile($file, string $settingKey, int $requiredWidth, int $requiredHeight): void
    {
        // A blank file input on a resubmitted form must not wipe out the
        // previously-uploaded logo/letterhead.
        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return;
        }

        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException('Only PNG or JPEG images are accepted.');
        }

        if ($file->getSizeByUnit('kb') > self::MAX_SIZE_KB) {
            throw new \InvalidArgumentException('The uploaded file must be 2MB or smaller.');
        }

        // getimagesize() reads only the image header, not the full pixel
        // data -- cheap enough to run on every upload. An exact match is
        // required, not a range: these dimensions are laid out into the
        // letter templates, not just displayed as a thumbnail.
        $dimensions = getimagesize($file->getTempName());
        if ($dimensions === false) {
            throw new \InvalidArgumentException('The uploaded file could not be read as an image.');
        }

        [$actualWidth, $actualHeight] = $dimensions;
        if ($actualWidth !== $requiredWidth || $actualHeight !== $requiredHeight) {
            throw new \InvalidArgumentException(
                "The image must be exactly {$requiredWidth}x{$requiredHeight} pixels "
                . "(uploaded file is {$actualWidth}x{$actualHeight})."
            );
        }

        $uploadDir = FCPATH . 'uploads/institute/';
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $oldPath = $this->settingService->get($settingKey);

        $newName = $file->getRandomName();
        $file->move($uploadDir, $newName);

        // Only point the setting at the new file -- and only delete the old
        // one -- after move() succeeded (it throws on failure), so a failed
        // upload never leaves the setting referencing a missing file.
        $this->settingService->set($settingKey, 'uploads/institute/' . $newName, 'institute');

        if ($oldPath && is_file(FCPATH . $oldPath)) {
            unlink(FCPATH . $oldPath);
        }
    }
}
