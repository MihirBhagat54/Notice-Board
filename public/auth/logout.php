<?php
// public/auth/logout.php
require_once __DIR__ . '/../../app/config/config.php';
Auth::logout();
Utils::redirect('public/auth/login.php');
