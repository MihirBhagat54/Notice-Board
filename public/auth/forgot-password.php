<?php
// public/auth/forgot-password.php
require_once __DIR__ . '/../../app/config/config.php';
if (Auth::isLoggedIn()) { Utils::redirect('public/dashboard.php'); }

// If the user arrives via GET with a ?reset=1 query param (from "Try a different email"
// or from coming back from the login page), clear the forgot-password session state
// so they always land on the email entry step, not a stale OTP/reset step.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['reset'])) {
    unset($_SESSION['fp_step'], $_SESSION['fp_userID'], $_SESSION['fp_email'], $_SESSION['fp_mail_error']);
}

$step  = $_SESSION['fp_step'] ?? 'email';
$error = '';
$info  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = Utils::post('action');

    // Step 1 — Send OTP
    if ($action === 'send_otp') {
        $email = Utils::post('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $user = Database::fetchOne('SELECT * FROM users WHERE email = ? AND active = 1', 's', $email);
            if (!$user) {
                $error = 'No active account found with that email address. Please check and try again.';
            } else {
                $otp = Auth::generateOTP(6);
                Auth::saveOTP($user['userID'], $otp);
                $mailResult = Auth::sendOTPEmail($user['email'], $user['fullName'], $otp);
                if (!$mailResult['ok']) {
                    $_SESSION['fp_mail_error'] = $mailResult['error'];
                }
                $_SESSION['fp_userID'] = $user['userID'];
                $_SESSION['fp_email']  = $user['email'];
                $_SESSION['fp_step']   = 'otp';
                $step = 'otp';
                $info = 'An OTP has been sent to ' . htmlspecialchars($user['email'], ENT_QUOTES) . '.';
            }
        }
    }

    // Step 2 — Verify OTP
    elseif ($action === 'verify_otp') {
        $otp = implode('', array_map('trim', (array)($_POST['otp'] ?? [])));
        if (strlen($otp) !== 6) {
            $error = 'Please enter the complete 6-digit OTP.'; $step = 'otp';
        } elseif (empty($_SESSION['fp_userID'])) {
            $error = 'Session expired. Please start again.'; $step = 'email';
        } elseif (!Auth::verifyOTP((int)$_SESSION['fp_userID'], $otp)) {
            $error = 'Invalid or expired OTP. Try again.'; $step = 'otp';
        } else {
            $_SESSION['fp_step'] = 'reset'; $step = 'reset';
        }
    }

    // Step 3 — Reset password
    elseif ($action === 'reset_password') {
        $pass    = Utils::post('password');
        $confirm = Utils::post('confirm_password');
        if (strlen($pass) < 8)    { $error = 'Password must be at least 8 characters.'; $step = 'reset'; }
        elseif ($pass !== $confirm) { $error = 'Passwords do not match.'; $step = 'reset'; }
        elseif (empty($_SESSION['fp_userID'])) { $error = 'Session expired.'; $step = 'email'; }
        else {
            $salt = Auth::generateSalt();
            $hash = Auth::hashPassword($pass, $salt);
            Database::query(
                'UPDATE users SET password = ?, salt = ?, lastPasswordChangedAt = NOW(), loginAttempts = 0 WHERE userID = ?',
                'ssi', $hash, $salt, (int)$_SESSION['fp_userID']
            );
            unset($_SESSION['fp_step'], $_SESSION['fp_userID'], $_SESSION['fp_email']);
            Utils::flash('success', 'Password reset successfully. Please sign in.');
            Utils::redirect('public/auth/login.php');
        }
    }
}

$step = $_SESSION['fp_step'] ?? $step;
$steps = ['email' => 1, 'otp' => 2, 'reset' => 3];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password — <?= SITE_NAME ?></title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<div class="auth-wrapper">

  <div class="auth-brand">
    <div class="auth-decoration"></div>
    <div class="auth-brand-logo">
      <div class="logo-mark"><i class="fa-solid fa-bell-school"></i></div>
      <div><div class="logo-text"><?= SITE_NAME ?></div></div>
    </div>
    <h1>Account<br><span>Recovery</span></h1>
    <p>We'll send a one-time password to your registered email address to verify your identity before letting you set a new password.</p>
    <div class="auth-brand-footer"><?= SITE_SCHOOL ?></div>
  </div>

  <div class="auth-form-side">
    <div class="auth-form-inner fade-up">

      <!-- Step indicator -->
      <div style="display:flex; gap:8px; margin-bottom:32px; align-items:center;">
        <?php foreach (['email' => 'Email', 'otp' => 'Verify OTP', 'reset' => 'New Password'] as $k => $label): ?>
          <div style="display:flex;align-items:center;gap:6px;opacity:<?= $step===$k?'1':'.38' ?>">
            <div style="width:24px;height:24px;border-radius:50%;background:<?= $step===$k?'var(--navy)':'var(--border)' ?>;color:white;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">
              <?= $steps[$k] ?>
            </div>
            <span style="font-size:12px;font-weight:500;color:var(--text-muted)"><?= $label ?></span>
          </div>
          <?php if ($k !== 'reset'): ?><div style="height:1px;flex:1;background:var(--border-lt)"></div><?php endif; ?>
        <?php endforeach; ?>
      </div>

      <?php if ($error): ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><?= Utils::sanitize($error) ?></div><?php endif; ?>
      <?php if ($info):  ?><div class="alert alert-info"><i class="fa-solid fa-circle-info"></i><?= Utils::sanitize($info) ?></div><?php endif; ?>
      <?php
        $__mailErr = $_SESSION['fp_mail_error'] ?? '';
        unset($_SESSION['fp_mail_error']);
      ?>
      <?php if ($__mailErr && $step === 'otp'): ?>
      <div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation"></i>
        <div><strong>Email delivery failed — OTP could not be sent.</strong><br>
        SMTP error: <code><?= Utils::sanitize($__mailErr) ?></code><br>
        <small>Gmail requires a 16-character <strong>App Password</strong>, not your regular password.<br>
        Go to: myaccount.google.com → Security → 2-Step Verification → App passwords</small></div>
      </div>
      <?php endif; ?>

      <?php if ($step === 'email'): ?>
        <h2>Forgot Password?</h2>
        <p class="auth-subtitle">Enter your registered school email address.</p>
        <form method="POST">
          <input type="hidden" name="action" value="send_otp">
          <div class="form-group">
            <label class="form-label">Registered Email</label>
            <div class="input-group">
              <i class="fa-solid fa-envelope input-icon"></i>
              <input type="email" name="email" class="form-control" placeholder="you@school.edu" required autofocus>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-block btn-lg">
            <i class="fa-solid fa-paper-plane"></i> Send OTP
          </button>
        </form>

      <?php elseif ($step === 'otp'): ?>
        <h2>Enter OTP</h2>
        <p class="auth-subtitle">Check <strong><?= Utils::sanitize($_SESSION['fp_email'] ?? 'your inbox') ?></strong> for the 6-digit code.</p>
        <form method="POST" id="otpForm">
          <input type="hidden" name="action" value="verify_otp">
          <div class="otp-inputs" style="margin:28px 0;">
            <?php for ($i = 0; $i < 6; $i++): ?>
              <input type="text" name="otp[]" class="otp-box" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
            <?php endfor; ?>
          </div>
          <button type="submit" class="btn btn-primary btn-block btn-lg">
            <i class="fa-solid fa-check"></i> Verify OTP
          </button>
        </form>
        <p style="text-align:center;margin-top:16px;font-size:13px;">
          <a href="<?= BASE_URL ?>public/auth/forgot-password.php?reset=1" style="color:var(--navy);font-weight:500;">
            <i class="fa-solid fa-arrow-left"></i> Try a different email
          </a>
        </p>

      <?php elseif ($step === 'reset'): ?>
        <h2>Set New Password</h2>
        <p class="auth-subtitle">Choose a strong new password for your account.</p>
        <form method="POST">
          <input type="hidden" name="action" value="reset_password">
          <div class="form-group">
            <label class="form-label">New Password</label>
            <div class="input-group">
              <i class="fa-solid fa-lock input-icon"></i>
              <input type="password" name="password" id="np" class="form-control" placeholder="Min 8 characters" required minlength="8">
              <button type="button" class="input-eye" data-toggle-pw="np"><i class="fa-regular fa-eye"></i></button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <div class="input-group">
              <i class="fa-solid fa-lock input-icon"></i>
              <input type="password" name="confirm_password" id="cp" class="form-control" placeholder="Repeat password" required minlength="8">
              <button type="button" class="input-eye" data-toggle-pw="cp"><i class="fa-regular fa-eye"></i></button>
            </div>
          </div>
          <button type="submit" class="btn btn-amber btn-block btn-lg">
            <i class="fa-solid fa-key"></i> Reset Password
          </button>
        </form>
      <?php endif; ?>

      <p style="text-align:center;margin-top:28px;font-size:13px;color:var(--text-muted);">
        Remember your password?
        <a href="<?= BASE_URL ?>public/auth/login.php" style="color:var(--navy);font-weight:500;">Sign in</a>
      </p>
    </div>
  </div>
</div>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
