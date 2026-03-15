<?php
// =============================================================
// app/helpers/Utils.php — General utility functions
// =============================================================

class Utils
{
    // ── Output sanitisation ───────────────────────────────────
    public static function sanitize(string $val): string
    {
        return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
    }

    // ── Human-readable time ago ───────────────────────────────
    public static function timeAgo(string $datetime): string
    {
        $diff = time() - strtotime($datetime);
        if ($diff < 60)     return 'Just now';
        if ($diff < 3600)   return floor($diff / 60)    . ' min ago';
        if ($diff < 86400)  return floor($diff / 3600)  . ' hr ago';
        if ($diff < 604800) return floor($diff / 86400) . ' days ago';
        return date('d M Y', strtotime($datetime));
    }

    // ── Flash messages ────────────────────────────────────────
    public static function flash(string $key, string $msg = ''): string
    {
        if ($msg !== '') {
            $_SESSION['flash'][$key] = $msg;
            return '';
        }
        $out = $_SESSION['flash'][$key] ?? '';
        unset($_SESSION['flash'][$key]);
        return $out;
    }

    // ── Avatar initials ───────────────────────────────────────
    public static function initials(string $name): string
    {
        $parts = explode(' ', trim($name));
        $ini   = strtoupper(substr($parts[0], 0, 1));
        if (count($parts) > 1) $ini .= strtoupper(substr(end($parts), 0, 1));
        return $ini;
    }

    // ── URL asset helper ──────────────────────────────────────
    public static function asset(string $path): string
    {
        return BASE_URL . 'assets/' . ltrim($path, '/');
    }

    // ── Redirect helper ───────────────────────────────────────
    // Pass either:
    //   - A full URL:            'http://...'
    //   - A root-relative path:  'public/auth/login.php'  (no leading slash)
    //
    // BASE_URL always ends with '/', e.g. 'http://localhost/noticeboard/'
    // So we just strip any accidental leading slash from $path and concatenate.
    public static function redirect(string $path): never
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $url = $path;
        } else {
            $url = BASE_URL . ltrim($path, '/');
        }
        header('Location: ' . $url);
        exit;
    }

    // ── POST / GET helpers ────────────────────────────────────
    public static function post(string $key, string $default = ''): string
    {
        return trim($_POST[$key] ?? $default);
    }

    public static function get(string $key, string $default = ''): string
    {
        return trim($_GET[$key] ?? $default);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return (int)($_GET[$key] ?? $default);
    }

    public static function postInt(string $key, int $default = 0): int
    {
        return (int)($_POST[$key] ?? $default);
    }
}
