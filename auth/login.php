<?php
// SplitMate Authentication - Login

require_once __DIR__ . '/../includes/auth.php';

// If already logged in, redirect
if (is_logged_in()) {
    header('Location: ' . base_url(current_role() === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php'));
    exit;
}

$error = '';
$emailVal = '';
$roleVal = 'user';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = trim($_POST['role'] ?? 'user');
        $emailVal = $email;
        $roleVal = $role;

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } elseif (!check_rate_limit('login_' . md5($email), 8, 300)) {
            $error = 'Too many login attempts. Please wait 5 minutes before trying again.';
        } else {
            $pdo = db();

            if ($role === 'admin') {
                $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $account = $stmt->fetch();

                if ($account && password_verify($password, $account['password'])) {
                    if ($account['status'] !== 'active') {
                        $error = 'Your admin account has been deactivated. Please contact support.';
                    } else {
                        reset_rate_limit('login_' . md5($email));
                        login_user($account, 'admin');
                        set_flash('success', "Welcome back, Admin {$account['full_name']}!");
                        header('Location: ' . base_url('admin/dashboard.php'));
                        exit;
                    }
                } else {
                    $error = 'Invalid admin credentials. Please verify your email and password.';
                }
            } else {
                // User login
                $stmt = $pdo->prepare("SELECT u.*, a.status as admin_status FROM users u JOIN admins a ON u.admin_id = a.admin_id WHERE u.email = ? LIMIT 1");
                $stmt->execute([$email]);
                $account = $stmt->fetch();

                if ($account && password_verify($password, $account['password'])) {
                    if ($account['email_verified'] != 1) {
                        // Unverified user, redirect to OTP verification
                        $_SESSION['pending_user_email'] = $account['email'];
                        set_flash('warning', 'Please verify your email address to activate your account.');
                        header('Location: ' . base_url('auth/verify_otp.php'));
                        exit;
                    } elseif ($account['status'] !== 'active') {
                        $error = 'Your user account has been deactivated by your administrator.';
                    } elseif ($account['admin_status'] !== 'active') {
                        $error = 'Your admin group is currently inactive. Please contact your admin.';
                    } else {
                        reset_rate_limit('login_' . md5($email));
                        login_user($account, 'user');
                        set_flash('success', "Welcome back, {$account['full_name']}!");
                        header('Location: ' . base_url('user/dashboard.php'));
                        exit;
                    }
                } else {
                    $error = 'Invalid user credentials. Please verify your email and password.';
                }
            }
        }
    }
}
$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | SplitMate</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Main Style -->
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body class="auth-wrapper">

<div class="auth-card">
    <div class="auth-header">
        <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-4 mb-2" style="background: rgba(99, 102, 241, 0.2); border: 1px solid rgba(99, 102, 241, 0.4);">
            <i class="fa-solid fa-calculator text-white fs-3"></i>
        </div>
        <h3 class="fw-bold mb-1">Split<span class="text-emerald" style="color: #10b981;">Mate</span></h3>
        <p class="text-white-50 mb-0 small">Multi-Admin Expense & Smart Settlement Platform</p>
    </div>

    <div class="auth-body">
        <!-- Flash Messages -->
        <?php if (!empty($flashes)): ?>
            <?php foreach ($flashes as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?> py-2 small d-flex align-items-center gap-2">
                    <i class="fa-solid fa-circle-info"></i>
                    <span><?= e($f['message']) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small d-flex align-items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="loginForm" novalidate>
            <?= csrf_field() ?>

            <!-- Role Selector -->
            <div class="mb-3">
                <label class="form-label">I am logging in as:</label>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="role" id="roleUser" value="user" <?= ($roleVal === 'user') ? 'checked' : '' ?> onchange="updateRoleUi('user')">
                    <label class="btn btn-outline-primary py-2 fw-semibold" for="roleUser">
                        <i class="fa-solid fa-user me-1"></i> Group Member
                    </label>

                    <input type="radio" class="btn-check" name="role" id="roleAdmin" value="admin" <?= ($roleVal === 'admin') ? 'checked' : '' ?> onchange="updateRoleUi('admin')">
                    <label class="btn btn-outline-primary py-2 fw-semibold" for="roleAdmin">
                        <i class="fa-solid fa-shield-halved me-1"></i> Administrator
                    </label>
                </div>
            </div>

            <!-- Email Field -->
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" class="form-control border-start-0" id="email" name="email" value="<?= e($emailVal) ?>" placeholder="name@domain.com" required autocomplete="email">
                </div>
            </div>

            <!-- Password Field -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <label for="password" class="form-label mb-0">Password</label>
                    <button type="button" class="btn btn-link p-0 text-decoration-none small text-muted" onclick="togglePasswordVisibility('password', this)">
                        <i class="fa-solid fa-eye"></i> Show
                    </button>
                </div>
                <div class="input-group mt-1">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary-custom w-100 py-2 fw-bold d-flex align-items-center justify-content-center gap-2">
                <span>Sign In</span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <!-- Registration Navigation -->
        <div class="mt-4 pt-3 border-top text-center">
            <div class="small text-muted mb-2">Don't have an account yet?</div>
            <div class="d-flex justify-content-center gap-3">
                <a href="<?= base_url('auth/register_user.php') ?>" class="text-primary fw-semibold small text-decoration-none">
                    <i class="fa-solid fa-user-plus me-1"></i> Register as Member
                </a>
                <span class="text-muted small">&bull;</span>
                <a href="<?= base_url('auth/register_admin.php') ?>" class="text-dark fw-semibold small text-decoration-none">
                    <i class="fa-solid fa-user-shield me-1"></i> Register as Admin
                </a>
            </div>
        </div>

        <!-- Demo Accounts Accordion (Great for testing) -->
        <div class="mt-4 pt-3 border-top">
            <button class="btn btn-sm btn-outline-secondary w-100 collapsed text-muted d-flex align-items-center justify-content-between" type="button" data-bs-toggle="collapse" data-bs-target="#demoAccountsCollapse" aria-expanded="false">
                <span><i class="fa-solid fa-key me-1"></i> Quick Test Accounts (Click to Fill)</span>
                <i class="fa-solid fa-chevron-down small"></i>
            </button>
            <div class="collapse mt-2" id="demoAccountsCollapse">
                <div class="p-2 bg-light rounded border small">
                    <div class="fw-bold mb-1 text-primary">Admin Accounts:</div>
                    <div class="d-flex gap-1 mb-2 flex-wrap">
                        <button type="button" class="btn btn-xs btn-outline-primary" style="font-size: 0.75rem;" onclick="fillLogin('admin@splitmate.com', 'Password@123', 'admin')">
                            Admin 1 (Avadh Narola)
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-primary" style="font-size: 0.75rem;" onclick="fillLogin('vikram@splitmate.com', 'Password@123', 'admin')">
                            Admin 2 (Vikram)
                        </button>
                    </div>

                    <div class="fw-bold mb-1 text-success">User Accounts (Admin 1 Group):</div>
                    <div class="d-flex gap-1 flex-wrap">
                        <button type="button" class="btn btn-xs btn-outline-success" style="font-size: 0.75rem;" onclick="fillLogin('avadh@gmail.com', 'Password@123', 'user')">
                            Avadh (Spent ₹1,000)
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-success" style="font-size: 0.75rem;" onclick="fillLogin('user2@gmail.com', 'Password@123', 'user')">
                            User 2 (Spent ₹1,500)
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-success" style="font-size: 0.75rem;" onclick="fillLogin('user3@gmail.com', 'Password@123', 'user')">
                            User 3 (Priya)
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateRoleUi(role) {
    // UI reactive tweaks if needed
}

function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Hide';
    } else {
        input.type = 'password';
        btn.innerHTML = '<i class="fa-solid fa-eye"></i> Show';
    }
}

function fillLogin(email, pass, role) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = pass;
    if (role === 'admin') {
        document.getElementById('roleAdmin').checked = true;
    } else {
        document.getElementById('roleUser').checked = true;
    }
}
</script>
</body>
</html>

