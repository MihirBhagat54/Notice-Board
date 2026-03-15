<?php
// =============================================================
// app/core/Auth.php — Authentication & Session Management
// =============================================================

class Auth
{
    // ── Password ────────────────────────────────────────────────
    public static function hashPassword(string $password, string $salt): string
    {
        return hash('sha256', hash('sha256', $password) . $salt);
    }

    public static function generateSalt(int $len = 16): string
    {
        return bin2hex(random_bytes($len));
    }

    // ── Session ─────────────────────────────────────────────────
    public static function isLoggedIn(): bool
    {
        if (!isset($_SESSION['userID'], $_SESSION['role'])) return false;

        if (isset($_SESSION['last_activity']) &&
            (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
            self::logout();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['userID']        = $user['userID'];
        $_SESSION['role']          = $user['role'];
        $_SESSION['fullName']      = $user['fullName'];
        $_SESSION['email']         = $user['email'];
        $_SESSION['last_activity'] = time();
    }

    public static function logout(): void
    {
        session_unset();
        session_destroy();
    }

    public static function currentUser(): ?array
    {
        if (!self::isLoggedIn()) return null;
        return Database::fetchOne(
            'SELECT * FROM users WHERE userID = ? AND active = 1',
            'i', $_SESSION['userID']
        );
    }

    public static function id(): int
    {
        return (int)($_SESSION['userID'] ?? 0);
    }

    public static function role(): string
    {
        return $_SESSION['role'] ?? '';
    }

    // ── Access guards ────────────────────────────────────────────
    public static function requireLogin(string $redirect = '/public/auth/login.php'): void
    {
        if (!self::isLoggedIn()) {
            header('Location: ' . BASE_URL . 'public/auth/login.php');
            exit;
        }
    }

    public static function requireRole(array $roles, string $redirect = 'dashboard.php'): void
    {
        self::requireLogin();
        if (!in_array(self::role(), $roles, true)) {
            header('Location: ' . BASE_URL . 'public/dashboard.php');
            exit;
        }
    }

    // ── OTP ─────────────────────────────────────────────────────
    public static function generateOTP(int $digits = 6): string
    {
        return str_pad((string)random_int(0, (int)(10 ** $digits) - 1), $digits, '0', STR_PAD_LEFT);
    }

    public static function saveOTP(int $userID, string $otp): bool
    {
        // Invalidate previous OTPs
        Database::query('UPDATE otp_tokens SET used = 1 WHERE userID = ? AND used = 0', 'i', $userID);
        $expiry = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
        $stmt   = Database::query(
            'INSERT INTO otp_tokens (userID, otp, expiresAt) VALUES (?, ?, ?)',
            'iss', $userID, $otp, $expiry
        );
        return $stmt !== false;
    }

    public static function verifyOTP(int $userID, string $otp): bool
    {
        $row = Database::fetchOne(
            'SELECT tokenID FROM otp_tokens
             WHERE userID = ? AND otp = ? AND used = 0 AND expiresAt > NOW()
             ORDER BY createdAt DESC LIMIT 1',
            'is', $userID, $otp
        );
        if (!$row) return false;
        Database::query('UPDATE otp_tokens SET used = 1 WHERE tokenID = ?', 'i', $row['tokenID']);
        return true;
    }

    // ── Mail ─────────────────────────────────────────────────────
    public static function sendOTPEmail(string $toEmail, string $toName, string $otp): bool
    {
        $subject = '[' . SITE_NAME . '] Your Password Reset OTP';
        $body    = "Dear {$toName},\n\n"
                 . "Your one-time password (OTP) for account recovery is:\n\n"
                 . "    {$otp}\n\n"
                 . "This OTP expires in " . OTP_EXPIRY_MINUTES . " minutes.\n"
                 . "If you did not request this, please ignore this email.\n\n"
                 . "— " . SITE_NAME . " · " . SITE_SCHOOL;
        $headers = implode("\r\n", [
            'From: '    . MAIL_NAME . ' <' . MAIL_FROM . '>',
            'Reply-To: ' . MAIL_FROM,
            'X-Mailer: PHP/' . PHP_VERSION,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ]);
        return mail($toEmail, $subject, $body, $headers);
    }
}
