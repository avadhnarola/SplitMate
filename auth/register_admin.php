<?php
// SplitMate Authentication - Admin Registration

require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . base_url(current_role() === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php'));
    exit;
}

$error = '';
$fullName = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $secret   = trim($_POST['secret_code'] ?? '');

        // Server-side validation
        if (empty($fullName) || empty($email) || empty($phone) || empty($password) || empty($secret)) {
            $error = 'All fields are strictly required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters in length.';
        } elseif ($password !== $confirm) {
            $error = 'Password confirmation does not match.';
        } elseif ($secret !== ADMIN_SECRET_CODE) {
            // Strict server-side verification of admin secret code
            $error = 'Invalid Administrator Secret Code. Access denied.';
        } else {
            $pdo = db();

            // Check if email already registered in admins or users
            $checkStmt = $pdo->prepare("SELECT email FROM admins WHERE email = ? UNION SELECT email FROM users WHERE email = ?");
            $checkStmt->execute([$email, $email]);
            if ($checkStmt->fetch()) {
                $error = 'This email address is already registered in SplitMate.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $ins = $pdo->prepare("INSERT INTO admins (full_name, email, phone, password, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
                $ins->execute([$fullName, $email, $phone, $hashed]);

                $newAdminId = (int)$pdo->lastInsertId();

                // Auto login new admin
                $newAdmin = [
                    'admin_id'  => $newAdminId,
                    'full_name' => $fullName,
                    'email'     => $email,
                    'phone'     => $phone,
                    'role'      => 'admin'
                ];
                login_user($newAdmin, 'admin');

                log_activity($newAdminId, null, "New administrator registered: {$fullName} ({$email})");

                set_flash('success', "Administrator account created successfully! Welcome, {$fullName}.");
                header('Location: ' . base_url('admin/dashboard.php'));
                exit;
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
    <title>Admin Registration | SplitMate</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Main Style -->
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body style="background: #f8fafc; min-height: 100vh;">

<div class="container-fluid p-0">
    <div class="row g-0 min-vh-100">

        <!-- ========================================================
             LEFT: Brand Hero Panel (Crimson & Violet Admin Command)
             ======================================================== -->
        <div class="col-lg-5 col-xl-5 d-none d-lg-flex flex-column justify-content-between p-5 auth-split-left auth-split-left-admin position-relative">
            <!-- Brand Top -->
            <div class="position-relative" style="z-index: 2;">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="brand-logo-icon rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: rgba(255,255,255,0.2); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.3);">
                        <i class="fa-solid fa-shield-halved text-white fs-4"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold text-white mb-0" style="letter-spacing: -0.5px;">Split<span style="color: #f87171;">Mate</span></h3>
                        <small class="text-white-50">Administrator Command & Group Creation</small>
                    </div>
                </div>
                <div class="auth-brand-pill mt-3">
                    <i class="fa-solid fa-key text-warning"></i>
                    <span>Restricted to Authorized Group Administrators</span>
                </div>
            </div>

            <!-- Middle Feature Showcase -->
            <div class="my-auto py-4 position-relative" style="z-index: 2;">
                <h2 class="display-6 fw-bold text-white mb-3" style="line-height: 1.25;">
                    Empower your group with automated fairness.
                </h2>
                <p class="text-white-50 mb-4 fs-6">
                    Establish an independent expense environment. You will have sovereign control over your members, participant splits, settlement verifications, and audit ledgers.
                </p>

                <div class="feature-bullets">
                    <div class="feature-bullet">
                        <div class="feature-bullet-icon">
                            <i class="fa-solid fa-lock text-warning"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white">Secret Code Verification Required</div>
                            <small class="text-white-50">Registration requires the verified server-side passkey to prevent unauthorized admin creation.</small>
                        </div>
                    </div>

                    <div class="feature-bullet">
                        <div class="feature-bullet-icon">
                            <i class="fa-solid fa-chart-pie text-info"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white">Full Analytics & Multi-Format Reports</div>
                            <small class="text-white-50">Access category breakdowns, monthly trends, participant ledgers, and CSV exports.</small>
                        </div>
                    </div>

                    <div class="feature-bullet">
                        <div class="feature-bullet-icon">
                            <i class="fa-solid fa-scale-balanced text-emerald" style="color: #34d399;"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white">Greedy Debt Settlement Matrix</div>
                            <small class="text-white-50">Recalculate balances dynamically and mark payments settled with timestamped audit logs.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Badge -->
            <div class="pt-4 border-top position-relative" style="z-index: 2; border-color: rgba(255,255,255,0.15) !important;">
                <div class="d-flex align-items-center justify-content-between small text-white-50">
                    <span><i class="fa-solid fa-shield-cat me-1"></i> Sovereign Multi-Admin Isolation</span>
                    <span>Admin Passkey Gate</span>
                </div>
            </div>
        </div>

        <!-- ========================================================
             RIGHT: Admin Registration Form Panel
             ======================================================== -->
        <div class="col-lg-7 col-xl-7 d-flex flex-column justify-content-center align-items-center p-4 p-md-5 auth-split-right">
            <div style="width: 100%; max-width: 520px;">

                <!-- Mobile Brand Header -->
                <div class="d-lg-none text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-4 mb-2" style="background: linear-gradient(135deg, #ef4444, #7c3aed); box-shadow: 0 4px 16px rgba(239,68,68,0.3);">
                        <i class="fa-solid fa-shield-halved text-white fs-3"></i>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark">Split<span style="color: #ef4444;">Mate</span> Admin</h3>
                    <p class="text-muted small">Create a new independent expense group</p>
                </div>

                <!-- Form Card -->
                <div class="bg-white p-4 p-md-5 rounded-4 shadow-sm border sm-card">
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <h4 class="fw-bold text-dark mb-0">Admin Registration</h4>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="fa-solid fa-shield me-1"></i>Secret Key</span>
                        </div>
                        <p class="text-muted small mb-0">Create an administrator account and launch your expense group</p>
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

                    <form action="" method="POST" id="adminRegisterForm" novalidate>
                        <?= csrf_field() ?>

                        <!-- Full Name -->
                        <div class="mb-3">
                            <label for="full_name" class="form-label small fw-semibold text-muted">ADMIN FULL NAME <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-user-shield"></i></span>
                                <input type="text" class="form-control border-start-0 py-2" id="full_name" name="full_name" value="<?= e($fullName) ?>" placeholder="e.g. Vikram Joshi" required autofocus>
                            </div>
                        </div>

                        <!-- Email & Phone Grid -->
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label for="email" class="form-label small fw-semibold text-muted">EMAIL ADDRESS <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-envelope"></i></span>
                                    <input type="email" class="form-control border-start-0 py-2" id="email" name="email" value="<?= e($email) ?>" placeholder="admin@domain.com" required>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label for="phone" class="form-label small fw-semibold text-muted">PHONE NUMBER <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-phone"></i></span>
                                    <input type="tel" class="form-control border-start-0 py-2" id="phone" name="phone" value="<?= e($phone) ?>" placeholder="+91 9800000001" required>
                                </div>
                            </div>
                        </div>

                        <!-- Administrator Secret Code -->
                        <div class="mb-3">
                            <label for="secret_code" class="form-label small fw-semibold text-muted d-flex align-items-center justify-content-between">
                                <span><i class="fa-solid fa-key text-warning me-1"></i> ADMIN SECRET REGISTRATION CODE <span class="text-danger">*</span></span>
                                <span class="text-muted" style="font-size: 0.72rem;">Server-Validated</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-warning-subtle text-dark border-end-0"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" class="form-control border-start-0 py-2 fw-bold" id="secret_code" name="secret_code" placeholder="Enter the 6-digit Secret Passcode" required autocomplete="off">
                            </div>
                            <div class="form-text text-muted" style="font-size: 0.74rem;">
                                Only authorized individuals with the secret passkey can register admin accounts.
                            </div>
                        </div>

                        <!-- Password & Confirm Password Grid -->
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label for="password" class="form-label small fw-semibold text-muted">PASSWORD <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-lock"></i></span>
                                    <input type="password" class="form-control border-start-0 py-2" id="password" name="password" placeholder="At least 6 chars" required minlength="6">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label for="confirm_password" class="form-label small fw-semibold text-muted">CONFIRM PASSWORD <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-lock"></i></span>
                                    <input type="password" class="form-control border-start-0 py-2" id="confirm_password" name="confirm_password" placeholder="Repeat password" required>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-danger w-100 py-2 fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm" style="background: linear-gradient(135deg, #ef4444, #dc2626); border: none;">
                            <i class="fa-solid fa-shield-halved"></i>
                            <span>Register & Launch Admin Workspace</span>
                        </button>
                    </form>

                    <!-- Footer Links -->
                    <div class="mt-4 pt-3 border-top text-center">
                        <span class="small text-muted">Already registered as Admin? </span>
                        <a href="<?= base_url('auth/login.php') ?>" class="text-primary fw-semibold small text-decoration-none">
                            Sign In to Account
                        </a>
                        <div class="mt-2 pt-2 border-top border-light">
                            <a href="<?= base_url('auth/register_user.php') ?>" class="text-muted small text-decoration-none">
                                <i class="fa-solid fa-user me-1 text-primary"></i> Looking for member registration? <strong>Join an admin group here</strong>
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
