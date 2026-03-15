<?php
// index.php — Project root entry point
// All traffic enters here; redirect appropriately
require_once __DIR__ . '/app/config/config.php';

if (Auth::isLoggedIn()) {
    Utils::redirect('public/dashboard.php');
} else {
    Utils::redirect('public/auth/login.php');
}
