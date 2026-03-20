<?php
// =============================================================
// app/core/Auth.php — Authentication & Session Management
// =============================================================

class Auth
{
    // ── Password helpers ─────────────────────────────────────────
    public static function hashPassword(string $password, string $salt): string
    {
        return hash('sha256', hash('sha256', $password) . $salt);
    }

    public static function generateSalt(int $len = 16): string
    {
        return bin2hex(random_bytes($len));
    }

    /**
     * Generate a readable random password:
     * 3 uppercase + 3 lowercase + 2 digits + 2 symbols = 10 chars, shuffled.
     */
    public static function generateRandomPassword(): string
    {
        $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower   = 'abcdefghjkmnpqrstuvwxyz';
        $digits  = '23456789';
        $symbols = '@#$!%';

        $pwd  = '';
        for ($i = 0; $i < 3; $i++) $pwd .= $upper[random_int(0, strlen($upper) - 1)];
        for ($i = 0; $i < 3; $i++) $pwd .= $lower[random_int(0, strlen($lower) - 1)];
        for ($i = 0; $i < 2; $i++) $pwd .= $digits[random_int(0, strlen($digits) - 1)];
        for ($i = 0; $i < 2; $i++) $pwd .= $symbols[random_int(0, strlen($symbols) - 1)];

        // Shuffle
        $arr = str_split($pwd);
        shuffle($arr);
        return implode('', $arr);
    }

    // ── Session management ───────────────────────────────────────
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
    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: ' . BASE_URL . 'public/auth/login.php');
            exit;
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        if (!in_array(self::role(), $roles, true)) {
            header('Location: ' . BASE_URL . 'public/dashboard.php');
            exit;
        }
    }

    // ── OTP helpers ──────────────────────────────────────────────
    public static function generateOTP(int $digits = 6): string
    {
        return str_pad((string)random_int(0, (int)(10 ** $digits) - 1), $digits, '0', STR_PAD_LEFT);
    }

    public static function saveOTP(int $userID, string $otp): bool
    {
        Database::query('UPDATE otp_tokens SET used = 1 WHERE userID = ? AND used = 0', 'i', $userID);
        $expiry = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
        return Database::query(
            'INSERT INTO otp_tokens (userID, otp, expiresAt) VALUES (?, ?, ?)',
            'iss', $userID, $otp, $expiry
        ) !== false;
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

    // ── Email helpers ────────────────────────────────────────────
    public static function sendOTPEmail(string $toEmail, string $toName, string $otp): bool
    {
        $subject = '[' . SITE_NAME . '] Your Password Reset OTP';
        $body    = "Dear {$toName},\n\n"
                 . "Your one-time password (OTP) for account recovery is:\n\n"
                 . "    {$otp}\n\n"
                 . "This OTP expires in " . OTP_EXPIRY_MINUTES . " minutes.\n"
                 . "If you did not request this, please ignore this email.\n\n"
                 . "— " . SITE_NAME . " · " . SITE_SCHOOL;

        return self::sendMail($toEmail, $toName, $subject, $body);
    }

    public static function sendWelcomeEmail(
        string $toEmail,
        string $toName,
        string $role,
        string $plainPassword,
        ?string $grade = null
    ): bool {
        $gradeInfo = ($role === 'Student' && $grade)
            ? "\nGrade      : {$grade}"
            : '';

        $subject = '[' . SITE_NAME . '] Your Account Has Been Created';
        $body    = "Dear {$toName},\n\n"
                 . "Welcome to " . SITE_NAME . " — " . SITE_SCHOOL . "!\n\n"
                 . "An account has been created for you. Your login credentials are:\n\n"
                 . "    Login URL  : " . BASE_URL . "public/auth/login.php\n"
                 . "    Email      : {$toEmail}\n"
                 . "    Password   : {$plainPassword}\n"
                 . "    Role       : {$role}{$gradeInfo}\n\n"
                 . "For security, please change your password after your first login.\n\n"
                 . "— " . SITE_NAME . " Team";

        return self::sendMail($toEmail, $toName, $subject, $body);
    }

    private static function sendMail(
        string $toEmail,
        string $toName,
        string $subject,
        string $body
    ): bool {
        $headers = implode("\r\n", [
            'From: '         . MAIL_NAME . ' <' . MAIL_FROM . '>',
            'Reply-To: '     . MAIL_FROM,
            'X-Mailer: PHP/' . PHP_VERSION,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ]);
        return mail($toEmail, $subject, $body, $headers);
    }
}
