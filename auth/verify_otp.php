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
    $latestStmt = $pdo->prepare("SELECT plain_otp_dev FROM otp_verifications WHERE email = ? ORDER BY otp_id DESC LIMIT 1");
    $latestStmt->execute([$email]);
    $row = $latestStmt->fetch();
    if ($row && !empty($row['plain_otp_dev'])) {
        $devOtp = $row['plain_otp_dev'];
    }
}
$flashes = get_flashes();
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
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css?v=' . (file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time())) ?>">
</head>
<body style="background: #f8fafc; min-height: 100vh;">

<div class="container-fluid p-0">
    <div class="row g-0 min-vh-100">

        <!-- ========================================================
             LEFT: Brand Hero Panel (Emerald Verification Theme)
             ======================================================== -->
        <div class="col-lg-5 col-xl-5 d-none d-lg-flex flex-column justify-content-between p-5 auth-split-left auth-split-left-otp position-relative">
            <!-- Brand Top -->
            <div class="position-relative" style="z-index: 2;">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="brand-logo-icon rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: rgba(255,255,255,0.2); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.3);">
                        <i class="fa-solid fa-envelope-circle-check text-white fs-4"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold text-white mb-0" style="letter-spacing: -0.5px;">Split<span style="color: #34d399;">Mate</span></h3>
                        <small class="text-white-50">Two-Factor Email Verification</small>
                    </div>
                </div>
                <div class="auth-brand-pill mt-3">
                    <i class="fa-solid fa-shield-check text-emerald" style="color: #34d399;"></i>
                    <span>Verifying Identity For <?= e($user['full_name']) ?></span>
                </div>
            </div>

            <!-- Middle Feature Showcase -->
            <div class="my-auto py-4 position-relative" style="z-index: 2;">
                <h2 class="display-6 fw-bold text-white mb-3" style="line-height: 1.25;">
                    Almost there! Confirm your email address.
                </h2>
                <p class="text-white-50 mb-4 fs-6">
                    A secure 6-digit One-Time Password has been dispatched to your mailbox to confirm ownership before activating your member workspace.
                </p>

                <div class="feature-bullets">
                    <div class="feature-bullet">
                        <div class="feature-bullet-icon">
                            <i class="fa-regular fa-clock text-warning"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white">5-Minute Security Window</div>
                            <small class="text-white-50">Each verification code is time-stamped with strict expiration to preserve cryptographic safety.</small>
                        </div>
                    </div>

                    <div class="feature-bullet">
                        <div class="feature-bullet-icon">
                            <i class="fa-solid fa-lock text-info"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white">Hashed Server-Side Storage</div>
                            <small class="text-white-50">Verification tokens are hashed in the database and never kept in plain-text.</small>
                        </div>
                    </div>

                    <div class="feature-bullet">
                        <div class="feature-bullet-icon">
                            <i class="fa-solid fa-bolt text-emerald" style="color: #34d399;"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white">Instant Account Activation</div>
                            <small class="text-white-50">Once verified, your account immediately activates and redirects into your live personal dashboard.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Badge -->
            <div class="pt-4 border-top position-relative" style="z-index: 2; border-color: rgba(255,255,255,0.15) !important;">
                <div class="d-flex align-items-center justify-content-between small text-white-50">
                    <span><i class="fa-solid fa-envelope me-1"></i> Sent to: <?= e($email) ?></span>
                    <span>Step 2 of 2</span>
                </div>
            </div>
        </div>

        <!-- ========================================================
             RIGHT: OTP Entry Form Panel
             ======================================================== -->
        <div class="col-lg-7 col-xl-7 d-flex flex-column justify-content-center align-items-center p-4 p-md-5 auth-split-right">
            <div style="width: 100%; max-width: 480px;">

                <!-- Mobile Brand Header -->
                <div class="d-lg-none text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-4 mb-2" style="background: linear-gradient(135deg, #059669, #4f46e5); box-shadow: 0 4px 16px rgba(5,150,105,0.3);">
                        <i class="fa-solid fa-envelope-circle-check text-white fs-3"></i>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark">Split<span style="color: #10b981;">Mate</span></h3>
                    <p class="text-muted small">Enter the 6-digit code sent to your email</p>
                </div>

                <!-- Form Card -->
                <div class="auth-form-card text-center">
                    <div class="mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-circle mb-3" style="background: #ecfdf5; border: 1px solid #a7f3d0; width: 64px; height: 64px;">
                            <i class="fa-solid fa-envelope-open-text text-success fs-3"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-1">Check Your Inbox</h4>
                        <p class="text-muted small mb-2">We sent a 6-digit verification code to:</p>
                        <span class="badge bg-light text-dark border px-3 py-2 fs-6 fw-semibold user-select-all"><?= e($email) ?></span>
                    </div>

                    <!-- Flash Messages -->
                    <?php if (!empty($flashes)): ?>
                        <?php foreach ($flashes as $f): ?>
                            <div class="alert alert-<?= e($f['type']) ?> py-2 small d-flex align-items-center gap-2 mb-3 text-start">
                                <i class="fa-solid fa-circle-info"></i>
                                <span><?= e($f['message']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger py-2 small d-flex align-items-center gap-2 mb-3 text-start">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span><?= e($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success py-2 small d-flex align-items-center gap-2 mb-3 text-start">
                            <i class="fa-solid fa-check-circle"></i>
                            <span><?= e($success) ?></span>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST" id="otpForm" novalidate>
                        <?= csrf_field() ?>

                        <!-- Unified Hidden OTP Input -->
                        <input type="hidden" id="otp" name="otp" value="">

                        <!-- Interactive 6-Digit Grid -->
                        <div class="mb-4">
                            <label class="form-label small fw-semibold text-muted mb-3 d-block">ENTER 6-DIGIT CODE</label>
                            <div class="otp-inputs-grid mb-2">
                                <input type="text" maxlength="1" class="otp-digit-input" data-index="0" inputmode="numeric" autofocus pattern="[0-9]*">
                                <input type="text" maxlength="1" class="otp-digit-input" data-index="1" inputmode="numeric" pattern="[0-9]*">
                                <input type="text" maxlength="1" class="otp-digit-input" data-index="2" inputmode="numeric" pattern="[0-9]*">
                                <input type="text" maxlength="1" class="otp-digit-input" data-index="3" inputmode="numeric" pattern="[0-9]*">
                                <input type="text" maxlength="1" class="otp-digit-input" data-index="4" inputmode="numeric" pattern="[0-9]*">
                                <input type="text" maxlength="1" class="otp-digit-input" data-index="5" inputmode="numeric" pattern="[0-9]*">
                            </div>
                            <div class="text-muted small" style="font-size: 0.78rem;">
                                <i class="fa-regular fa-clock me-1 text-danger"></i> Code expires in 5 minutes.
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" id="submitBtn" class="btn btn-primary-custom w-100 py-2 fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm" style="background: linear-gradient(135deg, #10b981, #059669); border: none;">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>Verify & Activate Account</span>
                        </button>
                    </form>

                    <!-- Resend Section -->
                    <div class="mt-4 pt-3 border-top d-flex flex-column align-items-center gap-2">
                        <span class="small text-muted">Didn't receive the email code?</span>
                        <a href="<?= base_url('auth/verify_otp.php?action=resend') ?>" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill" id="resendBtn">
                            <i class="fa-solid fa-rotate-right"></i>
                            <span>Resend Code</span>
                        </a>
                        <a href="<?= base_url('auth/register_user.php') ?>" class="small text-decoration-none text-muted mt-2">
                            <i class="fa-solid fa-arrow-left me-1"></i> Wrong email? Back to Registration
                        </a>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputs = document.querySelectorAll('.otp-digit-input');
    const hiddenOtp = document.getElementById('otp');
    const form = document.getElementById('otpForm');

    function updateHiddenValue() {
        let val = '';
        inputs.forEach(inp => val += inp.value.trim());
        hiddenOtp.value = val;
    }

    inputs.forEach((input, idx) => {
        input.addEventListener('input', function (e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 1 && idx < inputs.length - 1) {
                inputs[idx + 1].focus();
            }
            updateHiddenValue();
            if (hiddenOtp.value.length === 6) {
                // Auto submit when all 6 digits entered
                form.submit();
            }
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !this.value && idx > 0) {
                inputs[idx - 1].focus();
            }
        });

        input.addEventListener('paste', function (e) {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
            if (pasted.length >= 6) {
                for (let i = 0; i < 6; i++) {
                    inputs[i].value = pasted[i];
                }
                updateHiddenValue();
                form.submit();
            }
        });
    });

    // Auto-fill dev mode OTP if available on single click
    const devBadge = document.querySelector('.user-select-all');
    if (devBadge && devBadge.textContent.trim().length === 6 && /^\d+$/.test(devBadge.textContent.trim())) {
        devBadge.style.cursor = 'pointer';
        devBadge.title = 'Click to auto-fill';
        devBadge.addEventListener('click', function () {
            const code = this.textContent.trim();
            for (let i = 0; i < 6; i++) {
                if (inputs[i]) inputs[i].value = code[i];
            }
            updateHiddenValue();
        });
    }
});
</script>
</body>
</html>
