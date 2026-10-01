<?php
/**
 * Formatting helpers (rupiah, date id, etc.).
 */

if (!function_exists('rupiah')) {
    function rupiah(?float $value): string {
        if ($value === null) return '—';
        return 'Rp ' . number_format($value, 0, ',', '.');
    }
}

if (!function_exists('tanggal_id')) {
    function tanggal_id(?string $datetime, bool $withTime = true): string {
        if (!$datetime) return '—';
        $ts = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
        if (!$ts) return e($datetime);
        $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                   'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $d = date('d', $ts);
        $m = $months[(int)date('n', $ts)];
        $y = date('Y', $ts);
        $base = "{$d} {$m} {$y}";
        return $withTime ? $base . ' ' . date('H:i', $ts) : $base;
    }
}

if (!function_exists('short_number')) {
    function short_number(int $n): string {
        if ($n >= 1000000) return round($n / 1000000, 1) . 'jt';
        if ($n >= 1000) return round($n / 1000, 1) . 'rb';
        return (string)$n;
    }
}

if (!function_exists('initials')) {
    function initials(string $name): string {
        $parts = preg_split('/\s+/', trim($name));
        $first = mb_substr($parts[0] ?? '?', 0, 1);
        $last  = mb_substr($parts[count($parts) - 1] ?? '', 0, 1);
        return strtoupper($first . $last);
    }
}