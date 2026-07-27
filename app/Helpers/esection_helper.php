<?php

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
        return '<span class="badge badge-glass-indigo">' . esc(ucfirst($status)) . '</span>';
    }
}

if (!function_exists('generate_case_no')) {
    function generate_case_no($prefix = 'CASE')
    {
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
