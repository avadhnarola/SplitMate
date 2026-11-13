<?php
// SplitMate Authentication - Email OTP Verification

require_once __DIR__ . '/../includes/auth.php';

$email = $_SESSION['pending_user_email'] ?? '';
if (empty($email)) {
    set_flash('danger', 'No pending registration found. Please register first.');
    header('Location: ' . base_url('auth/register_user.php'));
    exit;
}

$pdo = db();
$error = '';
$success = '';

// Check if user is already verified
$uStmt = $pdo->prepare("SELECT user_id, admin_id, full_name, email, phone, email_verified, status FROM users WHERE email = ?");
$uStmt->execute([$email]);
$user = $uStmt->fetch();

if (!$user) {
    unset($_SESSION['pending_user_email']);
    set_flash('danger', 'Registration record not found.');
    header('Location: ' . base_url('auth/register_user.php'));
    exit;
}

if ($user['email_verified'] == 1) {
    unset($_SESSION['pending_user_email']);
    set_flash('success', 'Your account is already verified. You may now log in.');
    header('Location: ' . base_url('auth/login.php'));
    exit;
}

// Handle Resend OTP Request
if (isset($_GET['action']) && $_GET['action'] === 'resend') {
    $now = time();
    $lastSent = $_SESSION['otp_last_sent'] ?? 0;
    $cooldown = 60; // 60 seconds

    if ($now - $lastSent < $cooldown) {
        $remaining = $cooldown - ($now - $lastSent);
        $error = "Please wait {$remaining} seconds before requesting a new OTP.";
    } else {
        $newOtp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $newHash = password_hash($newOtp, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 min

        $insOtp = $pdo->prepare("INSERT INTO otp_verifications (email, otp_hash, plain_otp_dev, attempts, expires_at, created_at) VALUES (?, ?, ?, 0, ?, NOW())");
        $insOtp->execute([$email, $newHash, $newOtp, $expiresAt]);

        $_SESSION['otp_last_sent'] = time();

        $mailResult = send_otp_email($email, $user['full_name'], $newOtp);
        if (isset($mailResult['dev_otp'])) {
            $_SESSION['dev_otp_display'] = $mailResult['dev_otp'];
        }

        $success = 'A fresh 6-digit OTP code has been sent to your email.';
    }
}

// Handle OTP Form Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Session expired. Please try again.';
    } else {
        $enteredOtp = trim($_POST['otp'] ?? '');

        if (empty($enteredOtp) || strlen($enteredOtp) !== 6) {
            $error = 'Please enter the complete 6-digit OTP code.';
        } else {
            // Find most recent OTP record for this email
            $otpStmt = $pdo->prepare("SELECT * FROM otp_verifications WHERE email = ? ORDER BY otp_id DESC LIMIT 1");
            $otpStmt->execute([$email]);
            $otpRecord = $otpStmt->fetch();

            if (!$otpRecord) {
                $error = 'No OTP verification request found. Please request a new OTP.';
            } elseif ($otpRecord['attempts'] >= 5) {
                $error = 'Maximum verification attempts exceeded. Please request a new OTP code.';
            } elseif (strtotime($otpRecord['expires_at']) < time()) {
                $error = 'This OTP has expired (5-minute limit). Please request a new one.';
            } else {
                // Increment attempts
                $upAttempt = $pdo->prepare("UPDATE otp_verifications SET attempts = attempts + 1 WHERE otp_id = ?");
                $upAttempt->execute([$otpRecord['otp_id']]);

                // Verify OTP hash
                if (password_verify($enteredOtp, $otpRecord['otp_hash'])) {
                    // Successful verification! Activate user
                    $actStmt = $pdo->prepare("UPDATE users SET email_verified = 1, status = 'active', updated_at = NOW() WHERE user_id = ?");
                    $actStmt->execute([$user['user_id']]);

                    // Clean up session
                    unset($_SESSION['pending_user_email']);
                    unset($_SESSION['pending_user_name']);
                    unset($_SESSION['dev_otp_display']);

                    // Log activity
                    log_activity($user['admin_id'], $user['user_id'], "User verified email & activated account: {$user['full_name']} ({$user['email']})");

                    // Auto login verified user
                    $user['role'] = 'user';
                    login_user($user, 'user');

                    set_flash('success', "Email successfully verified! Welcome to SplitMate, {$user['full_name']}.");
                    header('Location: ' . base_url('user/dashboard.php'));
                    exit;
                } else {
                    $remainingAttempts = 4 - $otpRecord['attempts'];
                    $error = "Incorrect OTP code. Please check and try again. ({$remainingAttempts} attempt(s) remaining)";
                }
            }
        }
    }
}

// Dev Mode OTP Display
$devOtp = $_SESSION['dev_otp_display'] ?? null;
if (!$devOtp && DEV_MODE) {
    // Lookup latest plain otp from database for local convenience
    $latestStmt = $pdo->prepare("SELECT plain_otp_dev FROM otp_verifications WHERE email = ? ORDER BY otp_id DESC LIMIT 1");
    $latestStmt->execute([$email]);
    $row = $latestStmt->fetch();
    if ($row && !empty($row['plain_otp_dev'])) {
        $devOtp = $row['plain_otp_dev'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email OTP | SplitMate</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Main Style -->
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body class="auth-wrapper">

<div class="auth-card" style="max-width: 460px;">
    <div class="auth-header">
        <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-4 mb-2" style="background: rgba(99, 102, 241, 0.2); border: 1px solid rgba(99, 102, 241, 0.4);">
            <i class="fa-solid fa-envelope-circle-check text-white fs-3"></i>
        </div>
        <h3 class="fw-bold mb-1">Verify Email</h3>
        <p class="text-white-50 mb-0 small">Enter the 6-digit code sent to your email</p>
    </div>

    <div class="auth-body text-center">
        <!-- Target Email Indicator -->
        <div class="p-2 mb-3 bg-light rounded border text-muted small">
            Sent to: <strong class="text-dark"><?= e($email) ?></strong>
        </div>

        <!-- Dev Mode Helper Banner for Easy Testing -->
        <?php if (DEV_MODE && !empty($devOtp)): ?>
            <div class="alert alert-warning py-2 mb-3 text-start small border-warning">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="fa-solid fa-flask text-warning fs-5"></i>
                    <strong>Local Dev Testing Helper:</strong>
                </div>
                <div>Your generated OTP is: <span class="badge bg-dark fs-6 px-2 py-1 user-select-all"><?= e($devOtp) ?></span></div>
                <div class="text-muted" style="font-size: 0.72rem; margin-top: 3px;">(Provided automatically because DEV_MODE is enabled in .env)</div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small d-flex align-items-center gap-2 text-start">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success py-2 small d-flex align-items-center gap-2 text-start">
                <i class="fa-solid fa-check-circle"></i>
                <span><?= e($success) ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST" novalidate>
            <?= csrf_field() ?>

            <!-- OTP Input -->
            <div class="mb-4">
                <label for="otp" class="form-label fw-bold">6-Digit Verification Code</label>
                <div class="d-flex justify-content-center">
                    <input type="text" class="form-control text-center fw-bold fs-3 tracking-wide" id="otp" name="otp" maxlength="6" pattern="[0-9]{6}" placeholder="------" style="letter-spacing: 10px; max-width: 260px;" required autofocus autocomplete="one-time-code">
                </div>
                <div class="form-text mt-2 small text-muted">
                    <i class="fa-regular fa-clock me-1"></i> Code expires in 5 minutes.
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary-custom w-100 py-2 fw-bold d-flex align-items-center justify-content-center gap-2">
                <i class="fa-solid fa-circle-check"></i>
                <span>Verify & Activate Account</span>
            </button>
        </form>

        <!-- Resend OTP with cooldown -->
        <div class="mt-4 pt-3 border-top d-flex flex-column align-items-center gap-2">
            <span class="small text-muted">Didn't receive the email?</span>
            <a href="<?= base_url('auth/verify_otp.php?action=resend') ?>" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-2" id="resendBtn">
                <i class="fa-solid fa-rotate-right"></i>
                <span>Resend OTP Code</span>
            </a>
            <a href="<?= base_url('auth/register_user.php') ?>" class="small text-decoration-none text-muted mt-1">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Registration
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto focus and digit filtering
document.getElementById('otp').addEventListener('input', function (e) {
    this.value = this.value.replace(/[^0-9]/g, '');
});
</script>
</body>
</html>

