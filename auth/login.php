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
    <title>Login | SplitMate - Smart Expense Sharing</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Main Style -->
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css?v=' . (file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time())) ?>">
</head>
<body style="background: #f8fafc; min-height: 100vh;">

<div class="container-fluid p-0">
    <div class="row g-0 min-vh-100">

        <!-- ========================================================
             LEFT: Brand Hero Panel (Split-Screen Modern)
             ======================================================== -->
        <div class="col-lg-5 col-xl-5 d-none d-lg-flex flex-column justify-content-between p-5 auth-split-left position-relative">
            <!-- Brand Top -->
            <div class="position-relative" style="z-index: 2;">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="brand-logo-icon rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: rgba(255,255,255,0.2); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.3);">
                        <i class="fa-solid fa-calculator text-white fs-4"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold text-white mb-0" style="letter-spacing: -0.5px;">Split<span style="color: #34d399;">Mate</span></h3>
                        <small class="text-white-50">Expense Management & Smart Settlements</small>
                    </div>
                </div>
                <div class="auth-brand-pill mt-3">
                    <i class="fa-solid fa-sparkles text-warning"></i>
                    <span>Participant Splitting & Minimal Debt Routing</span>
                </div>
            </div>

            <!-- Middle Feature Showcase -->
            <div class="my-auto py-4 position-relative" style="z-index: 2;">
                <h2 class="display-6 fw-bold text-white mb-3" style="line-height: 1.25;">
                    Fair expenses, zero headaches, instant settlements.
                </h2>
                <p class="text-white-50 mb-4 fs-6">
                    Effortlessly split shared bills among room-mates, group trips, and teams with automated greedy debt reconciliation.
                </p>

                <div class="feature-bullets">
                    <div class="feature-bullet">
                        <div class="feature-bullet-icon">
                            <i class="fa-solid fa-user-group"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white">Selective Participant Splitting</div>
                            <small class="text-white-50">Split with everyone or choose only specific active members per expense.</small>
                        </div>
                    </div>

                    <div class="feature-bullet">
                        <div class="feature-bullet-icon">
                            <i class="fa-solid fa-route"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white">Optimized Greedy Debt Flow</div>
                            <small class="text-white-50">Minimizes total payment transfers needed so everyone gets settled rapidly.</small>
                        </div>
                    </div>

                    <div class="feature-bullet">
                        <div class="feature-bullet-icon">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white">Isolated Multi-Tenant Security</div>
                            <small class="text-white-50">Separate admin workspaces with authenticated audit trails and OTP verification.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Badge -->
            <div class="pt-4 border-top border-white-10 position-relative" style="z-index: 2; border-color: rgba(255,255,255,0.15) !important;">
                <div class="d-flex align-items-center justify-content-between small text-white-50">
                    <span><i class="fa-solid fa-lock me-1"></i> 256-bit Secure TLS Data Routing</span>
                    <span>v2.0 Participant Edition</span>
                </div>
            </div>
        </div>

        <!-- ========================================================
             RIGHT: Login Form Panel
             ======================================================== -->
        <div class="col-lg-7 col-xl-7 d-flex flex-column justify-content-center align-items-center p-4 p-md-5 auth-split-right">
            <div style="width: 100%; max-width: 480px;">

                <!-- Mobile Brand Header (Visible only on < lg screens) -->
                <div class="d-lg-none text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-4 mb-2" style="background: linear-gradient(135deg, #4f46e5, #7c3aed); box-shadow: 0 4px 16px rgba(79,70,229,0.3);">
                        <i class="fa-solid fa-calculator text-white fs-3"></i>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark">Split<span style="color: #10b981;">Mate</span></h3>
                    <p class="text-muted small">Multi-Admin Expense & Smart Settlement Platform</p>
                </div>

                <!-- Form Card -->
                <div class="auth-form-card">
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <h4 class="fw-bold text-dark mb-0">Welcome back</h4>
                            <span class="auth-step-pill" id="rolePill">
                                <i class="fa-solid fa-user text-primary"></i> Member Space
                            </span>
                        </div>
                        <p class="text-muted small mb-0">Sign in to manage shared expenses and view your standing</p>
                    </div>

                    <!-- Flash Messages -->
                    <?php if (!empty($flashes)): ?>
                        <?php foreach ($flashes as $f): ?>
                            <div class="alert alert-<?= e($f['type']) ?> py-2 small d-flex align-items-center gap-2 mb-3">
                                <i class="fa-solid fa-circle-info"></i>
                                <span><?= e($f['message']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger py-2 small d-flex align-items-center gap-2 mb-3">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span><?= e($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST" id="loginForm" novalidate>
                        <?= csrf_field() ?>

                        <!-- Sleek Segmented Role Switcher -->
                        <div class="mb-4">
                            <label class="form-label d-flex align-items-center justify-content-between mb-2">
                                <span>SELECT ACCOUNT ROLE</span>
                                <span class="text-muted small" style="font-size: 0.72rem;">Multi-Admin Architecture</span>
                            </label>
                            <div class="auth-role-switcher" role="radiogroup">
                                <button type="button" class="auth-role-option <?= ($roleVal === 'user') ? 'active-role' : '' ?>" id="roleBtnUser" onclick="setLoginRole('user')">
                                    <i class="fa-solid fa-user"></i>
                                    <span>Member</span>
                                </button>
                                <button type="button" class="auth-role-option <?= ($roleVal === 'admin') ? 'active-role-admin' : '' ?>" id="roleBtnAdmin" onclick="setLoginRole('admin')">
                                    <i class="fa-solid fa-shield-halved"></i>
                                    <span>Administrator</span>
                                </button>
                            </div>
                            <input type="hidden" name="role" id="loginRoleInput" value="<?= e($roleVal) ?>">
                        </div>

                        <!-- Email Field -->
                        <div class="mb-3">
                            <label for="email" class="form-label d-flex align-items-center justify-content-between">
                                <span>EMAIL ADDRESS</span>
                                <span class="badge bg-light text-muted border" style="font-size: 0.7rem;">Required</span>
                            </label>
                            <div class="input-group auth-input-group">
                                <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?= e($emailVal) ?>" placeholder="name@domain.com" required autocomplete="email">
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="password" class="form-label mb-0">PASSWORD</label>
                                <span class="text-muted small" style="font-size: 0.72rem;">Encrypted</span>
                            </div>
                            <div class="input-group auth-input-group">
                                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" class="form-control has-toggle" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                                <button type="button" class="auth-password-toggle" onclick="togglePasswordVisibility('password', this)" title="Show/Hide Password" aria-label="Toggle password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" id="submitLoginBtn" class="btn btn-auth-submit shadow-sm <?= ($roleVal === 'admin') ? 'btn-auth-submit-admin' : '' ?>">
                            <span id="submitBtnText">Sign In as Member</span>
                            <i class="fa-solid fa-arrow-right ms-1"></i>
                        </button>
                    </form>

                    <!-- Registration Navigation -->
                    <div class="mt-4 pt-3 border-top text-center">
                        <div class="small text-muted mb-2">Don't have an account yet?</div>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="<?= base_url('auth/register_user.php') ?>" class="text-primary fw-semibold small text-decoration-none d-flex align-items-center gap-1">
                                <i class="fa-solid fa-user-plus"></i> Register as Member
                            </a>
                            <span class="text-muted small">&bull;</span>
                            <a href="<?= base_url('auth/register_admin.php') ?>" class="text-dark fw-semibold small text-decoration-none d-flex align-items-center gap-1">
                                <i class="fa-solid fa-user-shield text-danger"></i> Register as Admin
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function setLoginRole(role) {
    const input = document.getElementById('loginRoleInput');
    input.value = role;

    const btnUser = document.getElementById('roleBtnUser');
    const btnAdmin = document.getElementById('roleBtnAdmin');
    const rolePill = document.getElementById('rolePill');
    const submitBtn = document.getElementById('submitLoginBtn');
    const submitText = document.getElementById('submitBtnText');

    if (role === 'admin') {
        btnUser.classList.remove('active-role');
        btnAdmin.classList.add('active-role-admin');
        rolePill.innerHTML = '<i class=\"fa-solid fa-shield-halved text-danger\"></i> Admin Console';
        submitBtn.classList.add('btn-auth-submit-admin');
        submitText.innerText = 'Sign In as Administrator';
    } else {
        btnAdmin.classList.remove('active-role-admin');
        btnUser.classList.add('active-role');
        rolePill.innerHTML = '<i class=\"fa-solid fa-user text-primary\"></i> Member Space';
        submitBtn.classList.remove('btn-auth-submit-admin');
        submitText.innerText = 'Sign In as Member';
    }
}

function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class=\"fa-solid fa-eye-slash\"></i>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<i class=\"fa-solid fa-eye\"></i>';
    }
}
</script>
</body>
</html>
