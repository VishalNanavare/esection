<?php

if (! function_exists('asset_url')) {
    /**
     * base_url() for a static asset, with a filemtime cache-buster appended.
     *
     * The nginx vhost sets `expires 7d` on css|js, so replacing a file in
     * place is invisible to any browser that already downloaded the old one.
     * Keying the query string on the file's mtime means the URL changes
     * exactly when the bytes do -- no build step, no manual version bump.
     *
     * @param string $path Path under public/, e.g. 'assets/css/app.css'
     */
    function asset_url(string $path): string
    {
        $path     = ltrim($path, '/');
        $absolute = FCPATH . $path;
        $version  = is_file($absolute) ? filemtime($absolute) : null;

        return base_url($path) . ($version !== null ? '?v=' . $version : '');
    }
}

if (!function_exists('number_to_words_indian')) {
    /**
     * Rupee amount, in words, Indian numbering (hundred/thousand/lakh/crore),
     * e.g. 1500 -> "one thousand five hundred and ". Ported from
     * esection_basic/config/ac_view_pdf.php's inline algorithm (used there to
     * spell out a DD amount) -- same output, just extracted into a reusable
     * helper instead of duplicated inline PHP.
     */
    function number_to_words_indian(float $amount): string
    {
        $words = [
            '0' => '', '1' => 'one', '2' => 'two', '3' => 'three', '4' => 'four',
            '5' => 'five', '6' => 'six', '7' => 'seven', '8' => 'eight', '9' => 'nine',
            '10' => 'ten', '11' => 'eleven', '12' => 'twelve', '13' => 'thirteen',
            '14' => 'fourteen', '15' => 'fifteen', '16' => 'sixteen', '17' => 'seventeen',
            '18' => 'eighteen', '19' => 'nineteen', '20' => 'twenty', '30' => 'thirty',
            '40' => 'forty', '50' => 'fifty', '60' => 'sixty', '70' => 'seventy',
            '80' => 'eighty', '90' => 'ninety',
        ];
        $digits = ['', 'hundred', 'thousand', 'lakh', 'crore'];

        $no     = floor($amount);
        $point  = round($amount - $no, 2) * 100;
        $digitsCount = strlen((string) $no);
        $i      = 0;
        $str    = [];

        while ($i < $digitsCount) {
            $divider = ($i === 2) ? 10 : 100;
            $number  = floor($no % $divider);
            $no      = floor($no / $divider);
            $i      += ($divider === 10) ? 1 : 2;

            if ($number) {
                $counter = count($str);
                $plural  = ($counter && $number > 9) ? 's' : null;
                $hundred = ($counter === 1 && $str[0]) ? ' and ' : null;
                $str[] = ($number < 21)
                    ? $words[(string) $number] . ' ' . $digits[$counter] . $plural . ' ' . $hundred
                    : $words[(string) (floor($number / 10) * 10)] . ' ' . $words[(string) ($number % 10)] . ' ' . $digits[$counter] . $plural . ' ' . $hundred;
            } else {
                $str[] = null;
            }
        }

        $result = implode('', array_reverse($str));

        if ($point) {
            $result .= '.' . $words[(string) ($point / 10)] . ' ' . $words[(string) ($point % 10)];
        }

        return trim(preg_replace('/\s+/', ' ', $result));
    }
}

if (!function_exists('format_currency')) {
    function format_currency($amount)
    {
        $val = floatval($amount);
        return '₹' . number_format($val, 2);
    }
}

if (!function_exists('render_status_badge')) {
    function render_status_badge($status)
    {
        $statusLower = strtolower(trim($status));
        if ($statusLower === 'confirmed' || $statusLower === 'complete' || $statusLower === 'active') {
            return '<span class="badge badge-glass-emerald"><i class="fa fa-check-circle me-1"></i> ' . esc(ucfirst($status)) . '</span>';
        }
        if ($statusLower === 'pending' || $statusLower === 'awaiting') {
            return '<span class="badge badge-glass-amber"><i class="fa fa-clock-o me-1"></i> ' . esc(ucfirst($status)) . '</span>';
        }
        if ($statusLower === 'inactive' || $statusLower === 'disabled') {
            return '<span class="badge badge-glass-rose"><i class="fa fa-ban me-1"></i> ' . esc(ucfirst($status)) . '</span>';
        }
        return '<span class="badge badge-glass-indigo">' . esc(ucfirst($status)) . '</span>';
    }
}

if (!function_exists('generate_case_no')) {
    /**
     * Default of null (not 'CASE') so a caller that passes no argument at
     * all reads the configured prefix from Settings > Document Numbering,
     * while a caller that explicitly passes a prefix still wins outright.
     */
    function generate_case_no($prefix = null)
    {
        if ($prefix === null) {
            $prefix = setting('case_no_prefix', 'CASE');
        }

        return strtoupper($prefix) . '-' . date('Y') . '/' . sprintf('%04d', rand(1, 9999));
    }
}

if (!function_exists('sanitize_xss')) {
    function sanitize_xss($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = sanitize_xss($value);
            }
            return $data;
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_address')) {
    /**
     * Render a postal address that may carry legacy <br> markup.
     *
     * Addresses imported from esection_basic embed a literal "<br>" as their
     * line-break marker, because the legacy app echoed these fields
     * unescaped. 2,303 of 6,345 student_details.clg_add rows and 162 of 469
     * college_details.Address rows still contain one. The CI4 rewrite
     * correctly escapes output, so that marker now prints as the visible
     * text "<br>" -- including inside the "To," block of the dispatch letter
     * that is physically posted to universities:
     *
     *     ... Mumbai-400 049. <br>phone- 022-26612877
     *
     * Converting the legacy token to a real newline BEFORE escaping restores
     * the line break the legacy app produced, without weakening anything:
     * esc() still runs over the entire value, so any other markup in the
     * data is still neutralised. Only this one known token is translated,
     * and it is translated into a newline -- never re-emitted as live HTML.
     *
     * nl2br() is applied after esc(), matching how the PDF views already
     * render multi-line values.
     */
    function esc_address($value): string
    {
        $normalised = preg_replace('/<br\s*\/?>/i', "\n", (string) $value);

        return nl2br(esc($normalised));
    }
}

if (!function_exists('setting')) {
    /**
     * Read a value from the Settings module's key/value store.
     *
     * @param mixed $default Returned when the key has never been saved.
     */
    function setting(string $key, $default = null)
    {
        static $service = null;
        $service = $service ?? new \App\Services\SettingService();

        return $service->get($key, $default);
    }
}

if (!function_exists('feature_enabled')) {
    /**
     * Whether a named feature toggle (Settings > Feature Toggles) is on.
     * Cast through (int) first so any non-canonical stored value (blank,
     * 'off', 'false') normalises to 0/1 before the bool cast, rather than
     * relying on each string's own PHP truthiness.
     */
    function feature_enabled(string $key): bool
    {
        return (bool) (int) setting($key, 0);
    }
}

if (!function_exists('page_access_granted')) {
    /**
     * Whether the logged-in user can reach a given Access Rights page_key.
     * Display-only (hides a sidebar link the user can't use) -- NOT a
     * security boundary, that's AccessFilter's job. Reads the session cache
     * populated at login, never a DB hit per call.
     */
    function page_access_granted(string $pageKey): bool
    {
        if (session()->get('role') === 'admin') {
            return true;
        }

        $granted = session()->get('page_access');

        return is_array($granted) && in_array($pageKey, $granted, true);
    }
}
