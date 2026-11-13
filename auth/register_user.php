<?php
// SplitMate Authentication - User (Member) Registration

require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . base_url(current_role() === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php'));
    exit;
}

$pdo = db();
$error = '';
$fullName = '';
$email = '';
$phone = '';
$selectedAdminId = 0;

// Fetch active admins for the dropdown
$adminsStmt = $pdo->query("SELECT admin_id, full_name, email FROM admins WHERE status = 'active' ORDER BY full_name ASC");
$activeAdmins = $adminsStmt->fetchAll();

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
        $adminId  = (int)($_POST['admin_id'] ?? 0);
        $selectedAdminId = $adminId;

        // Validation
        if (empty($fullName) || empty($email) || empty($phone) || empty($password) || empty($adminId)) {
            $error = 'Please fill in all required fields including selecting an admin group.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            // Verify that the selected admin exists and is active
            $chkAdmin = $pdo->prepare("SELECT admin_id, full_name FROM admins WHERE admin_id = ? AND status = 'active'");
            $chkAdmin->execute([$adminId]);
            $adminData = $chkAdmin->fetch();

            if (!$adminData) {
                $error = 'The selected admin group is invalid or inactive.';
            } else {
                // Check if email already registered
                $checkStmt = $pdo->prepare("SELECT email, email_verified, user_id FROM users WHERE email = ?");
                $checkStmt->execute([$email]);
                $existingUser = $checkStmt->fetch();

                if ($existingUser && $existingUser['email_verified'] == 1) {
                    $error = 'This email is already registered and verified. Please log in.';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    // Generate random 6-digit OTP
                    $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
                    $expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 minutes

                    if ($existingUser && $existingUser['email_verified'] == 0) {
                        // Update existing unverified registration
                        $upUser = $pdo->prepare("UPDATE users SET admin_id = ?, full_name = ?, phone = ?, password = ?, status = 'inactive', email_verified = 0, updated_at = NOW() WHERE user_id = ?");
                        $upUser->execute([$adminId, $fullName, $phone, $hashedPassword, $existingUser['user_id']]);
                    } else {
                        // Check if email registered in admins
                        $chkAdminEmail = $pdo->prepare("SELECT email FROM admins WHERE email = ?");
                        $chkAdminEmail->execute([$email]);
                        if ($chkAdminEmail->fetch()) {
                            $error = 'This email is already used by an administrator.';
                        } else {
                            // Insert new unverified user
                            $insUser = $pdo->prepare("INSERT INTO users (admin_id, full_name, email, phone, password, status, email_verified, created_at) VALUES (?, ?, ?, ?, ?, 'inactive', 0, NOW())");
                            $insUser->execute([$adminId, $fullName, $email, $phone, $hashedPassword]);
                        }
                    }

                    if (empty($error)) {
                        // Store OTP verification entry
                        $insOtp = $pdo->prepare("INSERT INTO otp_verifications (email, otp_hash, plain_otp_dev, attempts, expires_at, created_at) VALUES (?, ?, ?, 0, ?, NOW())");
                        $insOtp->execute([$email, $otpHash, $otp, $expiresAt]);

                        // Send OTP Email
                        $mailResult = send_otp_email($email, $fullName, $otp);

                        // Set session for OTP page
                        $_SESSION['pending_user_email'] = $email;
                        $_SESSION['pending_user_name']  = $fullName;
                        $_SESSION['otp_last_sent']      = time();

                        if (isset($mailResult['dev_otp'])) {
                            $_SESSION['dev_otp_display'] = $mailResult['dev_otp'];
                        }

                        set_flash('info', 'A 6-digit verification code has been sent to ' . htmlspecialchars($email) . '.');
                        header('Location: ' . base_url('auth/verify_otp.php'));
                        exit;
                    }
                }
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
    <title>Member Registration | SplitMate</title>
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
        <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-4 mb-2" style="background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4);">
            <i class="fa-solid fa-users text-emerald fs-3" style="color: #10b981;"></i>
        </div>
        <h3 class="fw-bold mb-1">Member Registration</h3>
        <p class="text-white-50 mb-0 small">Join an expense group managed by an administrator</p>
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
                    <input type="text" class="form-control border-start-0" id="full_name" name="full_name" value="<?= e($fullName) ?>" placeholder="e.g. Priya Patel" required>
                </div>
            </div>

            <!-- Email & Phone Grid -->
            <div class="row g-2 mb-3">
                <div class="col-sm-6">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" class="form-control border-start-0" id="email" name="email" value="<?= e($email) ?>" placeholder="priya@domain.com" required>
                    </div>
                </div>
                <div class="col-sm-6">
                    <label for="phone" class="form-label">Phone Number</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-phone"></i></span>
                        <input type="tel" class="form-control border-start-0" id="phone" name="phone" value="<?= e($phone) ?>" placeholder="+91 9800000003" required>
                    </div>
                </div>
            </div>

            <!-- Select Admin Dropdown -->
            <div class="mb-3">
                <label for="admin_id" class="form-label fw-bold text-dark">
                    <i class="fa-solid fa-shield-halved text-primary me-1"></i> Select Group Administrator
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-users-gear"></i></span>
                    <select class="form-select border-start-0 fw-medium" id="admin_id" name="admin_id" required>
                        <option value="">-- Choose an Administrator to Join --</option>
                        <?php foreach ($activeAdmins as $adm): ?>
                            <option value="<?= (int)$adm['admin_id'] ?>" <?= ($selectedAdminId === (int)$adm['admin_id']) ? 'selected' : '' ?>>
                                <?= e($adm['full_name']) ?> (Group #<?= (int)$adm['admin_id'] ?> - <?= e($adm['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-text" style="font-size: 0.75rem;">You will belong exclusively to this administrator's expense group.</div>
            </div>

            <!-- Password & Confirm Password Grid -->
            <div class="row g-2 mb-4">
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

            <!-- Submit Button -->
            <button type="submit" class="btn btn-success-custom w-100 py-2 fw-bold d-flex align-items-center justify-content-center gap-2">
                <span>Continue & Verify Email OTP</span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <div class="mt-4 pt-3 border-top text-center">
            <span class="small text-muted">Already registered? </span>
            <a href="<?= base_url('auth/login.php') ?>" class="text-primary fw-semibold small text-decoration-none">
                Sign In to Account
            </a>
            <div class="mt-2">
                <a href="<?= base_url('auth/register_admin.php') ?>" class="text-muted small text-decoration-none">
                    Are you an administrator? <strong>Register here with secret code</strong>
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

