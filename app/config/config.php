<?php
// =============================================================
// app/config/config.php — Central Application Configuration
// =============================================================

// ── Database ──────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Admin@123');
define('DB_NAME', 'school_noticeboard');

// ── Site identity ─────────────────────────────────────────────
define('SITE_NAME',   'EduBoard');
define('SITE_SCHOOL', 'Springfield International School');

// ── Filesystem paths ──────────────────────────────────────────
// config.php lives at: <root>/app/config/config.php
// dirname(__DIR__, 2) → two levels up → project root
define('ROOT_PATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('APP_PATH',  ROOT_PATH . 'app'   . DIRECTORY_SEPARATOR);

// ── BASE_URL — immune to folder renames ───────────────────────
// Compares the real on-disk ROOT_PATH to DOCUMENT_ROOT to derive
// the correct URL prefix. Works on XAMPP, WAMP, Linux, macOS.
(function () {
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Normalise paths: real slashes, no trailing slash
    $docRoot  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $rootReal = rtrim(str_replace('\\', '/', realpath(ROOT_PATH) ?: ROOT_PATH), '/');

    if ($docRoot !== '' && str_starts_with($rootReal, $docRoot)) {
        // Standard case: project is inside the web root
        // e.g. docRoot=/xampp/htdocs  rootReal=/xampp/htdocs/noticeboard
        // → urlPath = /noticeboard/
        $urlPath = substr($rootReal, strlen($docRoot)) . '/';
    } else {
        // Fallback: parse SCRIPT_NAME to find the project segment
        // SCRIPT_NAME = /noticeboard/public/auth/login.php
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $rootName   = basename($rootReal);   // e.g. "noticeboard"
        $pattern    = '#^(/(?:.+/)?' . preg_quote($rootName, '#') . ')/#';
        if (preg_match($pattern, $scriptName, $m)) {
            $urlPath = $m[1] . '/';          // e.g. /noticeboard/
        } else {
            $urlPath = '/' . $rootName . '/';
        }
    }

    define('BASE_URL', $scheme . '://' . $host . $urlPath);
})();

// ── Mail (update for production SMTP) ────────────────────────
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USER', 'noreply@school.edu');
define('MAIL_PASS', 'your_app_password');
define('MAIL_FROM', 'noreply@school.edu');
define('MAIL_NAME', 'EduBoard Notices');

// ── Security ──────────────────────────────────────────────────
define('OTP_EXPIRY_MINUTES', 10);
define('MAX_LOGIN_ATTEMPTS', 5);
define('SESSION_TIMEOUT',    1800);   // 30 minutes

// ── Session bootstrap ─────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Autoload core classes ──────────────────────────────────────
require_once APP_PATH . 'core/Database.php';
require_once APP_PATH . 'core/Auth.php';
require_once APP_PATH . 'helpers/NoticeHelper.php';
require_once APP_PATH . 'helpers/Utils.php';
