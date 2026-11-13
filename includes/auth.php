<?php
// SplitMate Authentication & Authorization Middleware

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

function is_logged_in(): bool {
    return !empty($_SESSION['auth_user']);
}

function current_user(): ?array {
    return $_SESSION['auth_user'] ?? null;
}

function current_role(): ?string {
    return $_SESSION['auth_user']['role'] ?? null;
}

function current_admin_id(): int {
    if (!is_logged_in()) {
        return 0;
    }
    return (int)($_SESSION['auth_user']['admin_id'] ?? 0);
}

function current_user_id(): int {
    if (!is_logged_in()) {
        return 0;
    }
    return (int)($_SESSION['auth_user']['id'] ?? 0);
}

function require_login(): void {
    if (!is_logged_in()) {
        set_flash('danger', 'Please log in to access this page.');
        header('Location: ' . base_url('auth/login.php'));
        exit;
    }
}

function require_admin(): void {
    require_login();
    if (current_role() !== 'admin') {
        http_response_code(403);
        set_flash('danger', 'Access denied. Administrator privileges required.');
        header('Location: ' . base_url('user/dashboard.php'));
        exit;
    }
}

function require_user(): void {
    require_login();
    if (current_role() !== 'user') {
        http_response_code(403);
        set_flash('danger', 'Access restricted to standard users.');
        header('Location: ' . base_url('admin/dashboard.php'));
        exit;
    }
}

function login_user(array $account, string $role): void {
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['auth_user'] = [
        'id'       => (int)($role === 'admin' ? $account['admin_id'] : $account['user_id']),
        'name'     => $account['full_name'],
        'email'    => $account['email'],
        'phone'    => $account['phone'] ?? '',
        'role'     => $role,
        'admin_id' => (int)($role === 'admin' ? $account['admin_id'] : $account['admin_id']),
        'login_at' => time()
    ];

    log_activity(
        $_SESSION['auth_user']['admin_id'],
        $role === 'user' ? $_SESSION['auth_user']['id'] : null,
        ucfirst($role) . " logged in: " . $account['email']
    );
}

function logout_user(): void {
    if (is_logged_in()) {
        log_activity(
            current_admin_id(),
            current_role() === 'user' ? current_user_id() : null,
            ucfirst(current_role()) . " logged out"
        );
    }

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

