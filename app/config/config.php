<?php
// =============================================================
// app/config/config.php — Central Application Configuration
// =============================================================

// ── Database ──────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Admin@123');  // Change this to your actual DB password
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
(function () {
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $docRoot  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $rootReal = rtrim(str_replace('\\', '/', realpath(ROOT_PATH) ?: ROOT_PATH), '/');

    if ($docRoot !== '' && str_starts_with($rootReal, $docRoot)) {
        $urlPath = substr($rootReal, strlen($docRoot)) . '/';
    } else {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $rootName   = basename($rootReal);
        $pattern    = '#^(/(?:.+/)?' . preg_quote($rootName, '#') . ')/#';
        if (preg_match($pattern, $scriptName, $m)) {
            $urlPath = $m[1] . '/';
        } else {
            $urlPath = '/' . $rootName . '/';
        }
    }

    define('BASE_URL', $scheme . '://' . $host . $urlPath);
})();

// ── Mail — Gmail SMTP via App Password ────────────────────────
//
// ⚠️  IMPORTANT — Gmail no longer allows plain passwords for SMTP.
//    You MUST use a 16-character App Password. Steps:
//
//    1. Go to myaccount.google.com
//    2. Security → 2-Step Verification → turn ON (required)
//    3. Security → 2-Step Verification → App passwords (scroll to bottom)
//    4. Select app: "Mail", device: "Other (custom name)" → Generate
//    5. Copy the 16-char password (e.g. "abcd efgh ijkl mnop")
//    6. Paste it as MAIL_PASS below — spaces are fine, they are ignored
//
//    The MAIL_USER should be your full Gmail address.
//    MAIL_FROM should match MAIL_USER (Gmail enforces this).
//
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);                          // STARTTLS port
define('MAIL_USER', 'boardedu508@gmail.com');      // Your Gmail address
define('MAIL_PASS', 'ulmd kzlh simy qnfs');        // 16-char App Password here
define('MAIL_FROM', 'boardedu508@gmail.com');      // Must match MAIL_USER for Gmail
define('MAIL_NAME', 'Edu Board');

// ── Security ──────────────────────────────────────────────────
define('OTP_EXPIRY_MINUTES', 10);
define('MAX_LOGIN_ATTEMPTS', 5);
define('SESSION_TIMEOUT',    1800);   // 30 minutes

// ── Session bootstrap ─────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Autoload core classes ──────────────────────────────────────
require_once APP_PATH . 'lib/Mailer.php';
require_once APP_PATH . 'core/Database.php';
require_once APP_PATH . 'core/Auth.php';
require_once APP_PATH . 'helpers/NoticeHelper.php';
require_once APP_PATH . 'helpers/Utils.php';
