<?php
// =============================================================
// app/lib/Mailer.php — Self-contained SMTP Mailer
//
// No Composer, no PHPMailer dependency. Pure PHP using
// stream_socket_client() for STARTTLS (Gmail port 587).
// Works on any XAMPP / WAMP installation out of the box.
//
// Gmail setup required:
//   1. Enable 2-Step Verification on the Gmail account
//   2. Create an App Password (myaccount.google.com → Security
//      → 2-Step Verification → App passwords)
//   3. Use that 16-char App Password as MAIL_PASS in config.php
//   ⚠️  Plain password (2FA OFF) no longer works — Google
//       requires App Passwords or OAuth for SMTP AUTH.
// =============================================================

class Mailer
{
    // Connection & stream handle
    private mixed   $socket   = null;
    private array   $log      = [];
    private string  $lastError = '';

    // Config (read from constants set in config.php)
    private string  $host;
    private int     $port;
    private string  $user;
    private string  $pass;
    private string  $fromEmail;
    private string  $fromName;
    private int     $timeout  = 10;

    public function __construct()
    {
        $this->host      = defined('MAIL_HOST') ? MAIL_HOST : 'smtp.gmail.com';
        $this->port      = defined('MAIL_PORT') ? (int)MAIL_PORT : 587;
        $this->user      = defined('MAIL_USER') ? MAIL_USER : '';
        $this->pass      = defined('MAIL_PASS') ? MAIL_PASS : '';
        $this->fromEmail = defined('MAIL_FROM') ? MAIL_FROM : '';
        $this->fromName  = defined('MAIL_NAME') ? MAIL_NAME : '';
    }

    // ── Public API ────────────────────────────────────────────────

    /**
     * Send an email. Returns true on success, false on failure.
     * Call getLastError() to retrieve the error message.
     */
    public function send(
        string  $toEmail,
        string  $toName,
        string  $subject,
        string  $body,
        bool    $isHtml = false
    ): bool {
        $this->log       = [];
        $this->lastError = '';

        try {
            $this->connect();
            $this->ehlo();
            $this->startTLS();
            $this->ehlo();          // re-send EHLO after STARTTLS
            $this->authenticate();
            $this->sendMail($this->fromEmail, $toEmail, $toName, $subject, $body, $isHtml);
            $this->quit();
            return true;
        } catch (RuntimeException $e) {
            $this->lastError = $e->getMessage();
            error_log('[Mailer] SMTP error: ' . $e->getMessage());
            error_log('[Mailer] Trace: ' . implode(' | ', $this->log));
            $this->closeSocket();
            return false;
        }
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getLog(): array
    {
        return $this->log;
    }

    // ── SMTP protocol steps ───────────────────────────────────────

    private function connect(): void
    {
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);

        $errno  = 0;
        $errstr = '';

        // Plain TCP connection first (we STARTTLS later)
        $this->socket = stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if (!$this->socket) {
            throw new RuntimeException(
                "Cannot connect to {$this->host}:{$this->port} — {$errstr} (error {$errno}). "
              . "Check MAIL_HOST / MAIL_PORT and that outbound port 587 is not blocked by your firewall."
            );
        }

        stream_set_timeout($this->socket, $this->timeout);
        $banner = $this->readResponse(220, 'CONNECT');
        $this->logLine('S (banner)', $banner);
    }

    private function ehlo(): void
    {
        $host = gethostname() ?: 'localhost';
        $resp = $this->cmd("EHLO {$host}", 250);
        $this->logLine('EHLO', $resp);
    }

    private function startTLS(): void
    {
        $resp = $this->cmd('STARTTLS', 220);
        $this->logLine('STARTTLS', $resp);

        // Upgrade the plain socket to TLS
        $ok = stream_socket_enable_crypto(
            $this->socket,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        );

        if (!$ok) {
            throw new RuntimeException(
                'TLS handshake failed. Ensure openssl extension is enabled in php.ini '
              . '(extension=openssl) and that MAIL_HOST supports STARTTLS.'
            );
        }
    }

    private function authenticate(): void
    {
        $this->cmd('AUTH LOGIN', 334);
        $this->cmd(base64_encode($this->user), 334);
        $resp = $this->cmd(base64_encode($this->pass), 235);
        $this->logLine('AUTH LOGIN', '*** credentials sent ***');

        // 535 = auth failed
        if (str_starts_with(trim($resp), '535')) {
            throw new RuntimeException(
                'SMTP authentication failed (535). '
              . 'For Gmail, you MUST use a 16-character App Password '
              . '(not your regular Gmail password). '
              . 'Go to myaccount.google.com → Security → 2-Step Verification → App passwords.'
            );
        }
    }

    private function sendMail(
        string $fromEmail,
        string $toEmail,
        string $toName,
        string $subject,
        string $body,
        bool   $isHtml
    ): void {
        $this->cmd("MAIL FROM:<{$fromEmail}>",  250);
        $this->cmd("RCPT TO:<{$toEmail}>",      250);
        $this->cmd('DATA',                       354);

        $contentType = $isHtml
            ? 'text/html; charset=UTF-8'
            : 'text/plain; charset=UTF-8';

        $toDisplay = $toName ? "\"{$toName}\" <{$toEmail}>" : $toEmail;
        $msgID     = '<' . uniqid('edu', true) . '@' . ($this->host) . '>';
        $date      = date('r');   // RFC 2822 date

        $headers = implode("\r\n", [
            "Date: {$date}",
            "From: \"{$this->fromName}\" <{$this->fromEmail}>",
            "To: {$toDisplay}",
            "Message-ID: {$msgID}",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "MIME-Version: 1.0",
            "Content-Type: {$contentType}",
            "Content-Transfer-Encoding: base64",
            "X-Mailer: EduBoard-Mailer/1.0",
        ]);

        // Body: base64-encode and wrap at 76 chars per RFC 2045
        $encodedBody = chunk_split(base64_encode($body), 76, "\r\n");

        $message = $headers . "\r\n\r\n" . $encodedBody . "\r\n.";
        $this->cmd($message, 250);
        $this->logLine('DATA sent', "Subject: {$subject}, To: {$toEmail}");
    }

    private function quit(): void
    {
        fwrite($this->socket, "QUIT\r\n");
        $this->closeSocket();
    }

    // ── Socket I/O helpers ────────────────────────────────────────

    /**
     * Send a command and read the response, asserting the expected code.
     */
    private function cmd(string $command, int $expectedCode): string
    {
        // Don't log the actual base64-encoded password line
        $logCmd = (str_starts_with($command, 'DATA') || strlen($command) > 200)
            ? substr($command, 0, 80) . '…'
            : $command;
        $this->logLine('C', $logCmd);

        fwrite($this->socket, $command . "\r\n");
        return $this->readResponse($expectedCode, $command);
    }

    /**
     * Read lines from the socket until a final response line (no dash after code).
     */
    private function readResponse(int $expectedCode, string $context): string
    {
        $response = '';
        $deadline = time() + $this->timeout;

        while (!feof($this->socket) && time() < $deadline) {
            $line = fgets($this->socket, 512);
            if ($line === false) break;

            $response .= $line;
            $this->logLine('S', rtrim($line));

            // A line starting with "NNN " (space, not dash) is the last line
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        $code = (int)substr(trim($response), 0, 3);

        if ($code !== $expectedCode) {
            throw new RuntimeException(
                "SMTP error during [{$context}]: expected {$expectedCode}, got {$code}. "
              . "Server said: " . trim($response)
            );
        }

        return $response;
    }

    private function closeSocket(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function logLine(string $dir, string $line): void
    {
        $this->log[] = "[{$dir}] " . rtrim($line);
    }
}
