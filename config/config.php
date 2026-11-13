
<?php
// SplitMate Main Configuration File

// Prevent direct execution outside app
if (!defined('SPLITMATE_APP')) {
    define('SPLITMATE_APP', true);
}

// Set default timezone
date_default_timezone_set('Asia/Kolkata');

// Simple .env Loader
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);

            $key = trim($key);
            $val = trim($val);

            // Remove matching enclosing quotes
            if (
                (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))
            ) {
                $val = substr($val, 1, -1);
            }

            if (
                !array_key_exists($key, $_SERVER) &&
                !array_key_exists($key, $_ENV)
            ) {
                putenv("$key=$val");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// Load .env from project root
loadEnv(__DIR__ . '/../.env');

// Environment variable helper
function env($key, $default = null) {
    $val = getenv($key);

    if ($val === false) {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    if ($val === 'true' || $val === 'TRUE') {
        return true;
    }

    if ($val === 'false' || $val === 'FALSE') {
        return false;
    }

    return $val;
}

// Session Configuration
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @ini_set('session.cookie_httponly', 1);
    @ini_set('session.use_only_cookies', 1);
    @ini_set('session.use_strict_mode', 1);

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        @ini_set('session.cookie_secure', 1);
    }

    session_start();
}

// Application Constants
define('APP_NAME', env('APP_NAME', 'SplitMate'));
define('ADMIN_SECRET_CODE', env('ADMIN_SECRET_CODE', '250506'));
define('DEV_MODE', (bool) env('DEV_MODE', false));

// Base URL for InfinityFree root installation
function base_url($path = '') {
    $protocol = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        ($_SERVER['SERVER_PORT'] ?? 80) == 443
    ) ? 'https://' : 'http://';

    $host = $_SERVER['HTTP_HOST'] ?? 'splitmate.infinityfree.me';

    return $protocol . $host . '/' . ltrim($path, '/');
}

// CSRF Protection
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field() {
    $token = htmlspecialchars(
        get_csrf_token(),
        ENT_QUOTES,
        'UTF-8'
    );

    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

// Flash Messaging
function set_flash($type, $message) {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }

    $_SESSION['flash'][] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flashes() {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $flashes;
}

// PHPMailer / Composer Autoloader
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
