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
<body class="auth-wrapper">

<div class="auth-card" style="max-width: 520px;">
    <div class="auth-header">
        <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-4 mb-2" style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.4);">
            <i class="fa-solid fa-shield-halved text-danger fs-3"></i>
        </div>
        <h3 class="fw-bold mb-1">Admin Registration</h3>
        <p class="text-white-50 mb-0 small">Create a new independent expense group</p>
    </div>

    <div class="auth-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small d-flex align-items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST" novalidate>
            <?= csrf_field() ?>

            <!-- Full Name -->
            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-user"></i></span>
                    <input type="text" class="form-control border-start-0" id="full_name" name="full_name" value="<?= e($fullName) ?>" placeholder="e.g. Avadh Narola" required>
                </div>
            </div>

            <!-- Email & Phone Grid -->
            <div class="row g-2 mb-3">
                <div class="col-sm-6">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" class="form-control border-start-0" id="email" name="email" value="<?= e($email) ?>" placeholder="admin@domain.com" required>
                    </div>
                </div>
                <div class="col-sm-6">
                    <label for="phone" class="form-label">Phone Number</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-phone"></i></span>
                        <input type="tel" class="form-control border-start-0" id="phone" name="phone" value="<?= e($phone) ?>" placeholder="+91 9876543210" required>
                    </div>
                </div>
            </div>

            <!-- Password & Confirm Password Grid -->
            <div class="row g-2 mb-3">
                <div class="col-sm-6">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>
                <div class="col-sm-6">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" class="form-control border-start-0" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
                    </div>
                </div>
            </div>

            <!-- Secret Registration Code -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <label for="secret_code" class="form-label mb-0 text-danger fw-bold">
                        <i class="fa-solid fa-key me-1"></i> Admin Secret Code
                    </label>
                    <small class="text-muted" style="font-size: 0.75rem;">Authorization Required</small>
                </div>
                <div class="input-group mt-1">
                    <span class="input-group-text bg-danger-subtle border-end-0 text-danger"><i class="fa-solid fa-unlock-keyhole"></i></span>
                    <input type="password" class="form-control border-start-0 border-danger-subtle" id="secret_code" name="secret_code" placeholder="Enter confidential admin code" required>
                </div>
                <div class="form-text" style="font-size: 0.75rem;">Only authorized organizers with the master secret code can register as group admins.</div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary-custom w-100 py-2 fw-bold d-flex align-items-center justify-content-center gap-2">
                <i class="fa-solid fa-user-shield"></i>
                <span>Register as Administrator</span>
            </button>
        </form>

        <div class="mt-4 pt-3 border-top text-center">
            <span class="small text-muted">Already registered? </span>
            <a href="<?= base_url('auth/login.php') ?>" class="text-primary fw-semibold small text-decoration-none">
                Sign In to Account
            </a>
            <div class="mt-2">
                <a href="<?= base_url('auth/register_user.php') ?>" class="text-muted small text-decoration-none">
                    Looking to join an existing group as a member? <strong>Click here</strong>
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

