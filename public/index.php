<?php
// public/index.php — Root entry point
// Redirects to login or dashboard based on session state
require_once __DIR__ . '/../app/config/config.php';

if (Auth::isLoggedIn()) {
    Utils::redirect('public/dashboard.php');
} else {
    Utils::redirect('public/auth/login.php');
}
