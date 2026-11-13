<?php
// SplitMate Authentication - Logout

require_once __DIR__ . '/../includes/auth.php';

logout_user();

set_flash('info', 'You have been successfully logged out.');
header('Location: ' . base_url('auth/login.php'));
exit;

