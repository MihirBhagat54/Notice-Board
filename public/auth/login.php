<?php
// public/auth/login.php
require_once __DIR__ . '/../../app/config/config.php';

if (Auth::isLoggedIn()) { Utils::redirect('public/dashboard.php'); }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = Utils::post('email');
    $password = Utils::post('password');

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $user = Database::fetchOne(
            'SELECT * FROM users WHERE email = ? AND active = 1', 's', $email
        );

        if (!$user) {
            $error = 'Invalid credentials. Please try again.';
        } elseif ($user['loginAttempts'] >= MAX_LOGIN_ATTEMPTS) {
            $error = 'Account locked after too many failed attempts. Contact your administrator.';
        } else {
            $hash = Auth::hashPassword($password, $user['salt']);
            if ($hash !== $user['password']) {
                Database::query(
                    'UPDATE users SET loginAttempts = loginAttempts + 1 WHERE userID = ?',
                    'i', $user['userID']
                );
                $remaining = MAX_LOGIN_ATTEMPTS - $user['loginAttempts'] - 1;
                $error = "Incorrect password. {$remaining} attempt(s) remaining before lockout.";
            } else {
                Database::query(
                    'UPDATE users SET loginAttempts = 0, lastLoginAt = NOW() WHERE userID = ?',
                    'i', $user['userID']
                );
                Auth::login($user);
                Utils::redirect('public/dashboard.php');
            }
        }
    }
}

$successFlash = Utils::flash('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — <?= SITE_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<div class="auth-wrapper">

  <!-- Brand Panel -->
  <div class="auth-brand">
    <div class="auth-decoration"></div>
    <div class="auth-brand-logo">
      <div class="logo-mark"><i class="fa-solid fa-bell-school"></i></div>
      <div><div class="logo-text"><?= SITE_NAME ?></div></div>
    </div>
    <h1>The Official<br><span>Digital Notice</span><br>Board</h1>
    <p>A unified platform for publishing, managing, and discovering school notices — keeping every member of our community informed and connected.</p>
    <div class="auth-brand-footer"><?= SITE_SCHOOL ?> &nbsp;·&nbsp; Academic Year 2024–25</div>
  </div>

  <!-- Form Panel -->
  <div class="auth-form-side">
    <div class="auth-form-inner fade-up">
      <h2>Welcome back</h2>
      <p class="auth-subtitle">Sign in with your school credentials to continue.</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><?= Utils::sanitize($error) ?></div>
      <?php endif; ?>
      <?php if ($successFlash): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i><?= Utils::sanitize($successFlash) ?></div>
      <?php endif; ?>

      <form method="POST" autocomplete="off" novalidate>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <div class="input-group">
            <i class="fa-solid fa-envelope input-icon"></i>
            <input type="email" name="email" class="form-control"
                   placeholder="you@school.edu"
                   value="<?= Utils::sanitize($_POST['email'] ?? '') ?>" required autofocus>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-group">
            <i class="fa-solid fa-lock input-icon"></i>
            <input type="password" name="password" id="pwdField" class="form-control"
                   placeholder="••••••••" required>
            <button type="button" class="input-eye" data-toggle-pw="pwdField">
              <i class="fa-regular fa-eye"></i>
            </button>
          </div>
        </div>

        <div style="display:flex; justify-content:flex-end; margin-bottom:22px;">
          <a href="<?= BASE_URL ?>public/auth/forgot-password.php"
             style="font-size:13px; color:var(--navy); font-weight:500;">
            Forgot password?
          </a>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">
          <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In
        </button>
      </form>

      <p style="font-size:12px; color:var(--text-light); text-align:center; margin-top:28px;">
        Demo: <strong>admin@school.edu</strong> / <strong>Admin@123</strong>
      </p>
    </div>
  </div>

</div>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
