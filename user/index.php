<?php
// SplitMate Root Redirector
// IMPORTANT: No landing page. Redirect directly to Dashboard if logged in, or Login/Registration.

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    if (current_role() === 'admin') {
        header('Location: ' . base_url('admin/dashboard.php'));
        exit;
    } else {
        header('Location: ' . base_url('user/dashboard.php'));
        exit;
    }
}

header('Location: ' . base_url('auth/login.php'));
exit;

