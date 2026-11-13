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
$flashes = get_flashes();
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
<body style="background: #f8fafc; min-height: 100vh;">

<div class="container-fluid p-0">
    <div class="row g-0 min-vh-100">

        <!-- ========================================================
             LEFT: Brand Hero Panel (Emerald & Indigo SaaS)
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
                        <small class="text-white-50">Member Registration & Group Joining</small>
                    </div>
                </div>
                <div class="auth-brand-pill mt-3">
                    <i class="fa-solid fa-users text-emerald" style="color: #34d399;"></i>
                    <span>Connect Under Your Administrator's Workspace</span>
                </div>
            </div>

            <!-- Middle Feature Showcase -->
            <div class="my-auto py-4 position-relative" style="z-index: 2;">
                <h2 class="display-6 fw-bold text-white mb-3" style="line-height: 1.25;">
                    Track every rupee, split fairly, settle with one click.
                </h2>
                <p class="text-white-50 mb-4 fs-6">
                    Join your designated group admin to start recording personal bills, tracking member receivables, and participating in selective splits.
                </p>

                <div class="feature-bullets">
                    <div class="feature-bullet">
                        <div class="feature-bullet-icon">
                            <i class="fa-solid fa-envelope-circle-check text-emerald" style="color: #34d399;"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white">Compulsory Email OTP Verification</div>
                            <small class="text-white-50">Secure 6-digit verification ensures only verified active members join the ledger.</small>
                        </div>
                    </div>

                    <div class="feature-bullet">
                        <div class="feature-bullet-icon">
                            <i class="fa-solid fa-user-check text-warning"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white">Selective Expense Splitting</div>
                            <small class="text-white-50">Pay for dinners or cabs and split only with members who actually participated.</small>
                        </div>
                    </div>

                    <div class="feature-bullet">
                        <div class="feature-bullet-icon">
                            <i class="fa-solid fa-shield-halved text-info"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white">Strict Group Isolation</div>
                            <small class="text-white-50">Your financial entries remain 100% private and isolated to your chosen administrator.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Badge -->
            <div class="pt-4 border-top position-relative" style="z-index: 2; border-color: rgba(255,255,255,0.15) !important;">
                <div class="d-flex align-items-center justify-content-between small text-white-50">
                    <span><i class="fa-solid fa-lock me-1"></i> End-to-End Group Confidentiality</span>
                    <span>v2.0 Participant Edition</span>
                </div>
            </div>
        </div>

        <!-- ========================================================
             RIGHT: Registration Form Panel
             ======================================================== -->
        <div class="col-lg-7 col-xl-7 d-flex flex-column justify-content-center align-items-center p-4 p-md-5 auth-split-right">
            <div style="width: 100%; max-width: 520px;">

                <!-- Mobile Brand Header (Visible only on < lg screens) -->
                <div class="d-lg-none text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-4 mb-2" style="background: linear-gradient(135deg, #4f46e5, #10b981); box-shadow: 0 4px 16px rgba(16,185,129,0.3);">
                        <i class="fa-solid fa-calculator text-white fs-3"></i>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark">Split<span style="color: #10b981;">Mate</span></h3>
                    <p class="text-muted small">Join an expense group managed by an administrator</p>
                </div>

                <!-- Form Card -->
                <div class="bg-white p-4 p-md-5 rounded-4 shadow-sm border sm-card">
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <h4 class="fw-bold text-dark mb-0">Create Member Account</h4>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Step 1 of 2</span>
                        </div>
                        <p class="text-muted small mb-0">Register your details and select your group administrator</p>
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

                    <form action="" method="POST" id="registerForm" novalidate>
                        <?= csrf_field() ?>

                        <!-- Full Name -->
                        <div class="mb-3">
                            <label for="full_name" class="form-label small fw-semibold text-muted">FULL NAME <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-user"></i></span>
                                <input type="text" class="form-control border-start-0 py-2" id="full_name" name="full_name" value="<?= e($fullName) ?>" placeholder="e.g. Priya Patel" required autofocus>
                            </div>
                        </div>

                        <!-- Email & Phone Grid -->
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label for="email" class="form-label small fw-semibold text-muted">EMAIL ADDRESS <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-envelope"></i></span>
                                    <input type="email" class="form-control border-start-0 py-2" id="email" name="email" value="<?= e($email) ?>" placeholder="priya@domain.com" required>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label for="phone" class="form-label small fw-semibold text-muted">PHONE NUMBER <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-phone"></i></span>
                                    <input type="tel" class="form-control border-start-0 py-2" id="phone" name="phone" value="<?= e($phone) ?>" placeholder="+91 9800000003" required>
                                </div>
                            </div>
                        </div>

                        <!-- Select Admin Dropdown -->
                        <div class="mb-3">
                            <label for="admin_id" class="form-label small fw-semibold text-muted d-flex align-items-center justify-content-between">
                                <span><i class="fa-solid fa-shield-halved text-primary me-1"></i> SELECT GROUP ADMINISTRATOR <span class="text-danger">*</span></span>
                                <span class="badge bg-light text-muted border" style="font-size: 0.7rem;">Multi-Admin</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-users-gear"></i></span>
                                <select class="form-select border-start-0 py-2 fw-medium" id="admin_id" name="admin_id" required>
                                    <option value="">-- Choose an Administrator to Join --</option>
                                    <?php foreach ($activeAdmins as $adm): ?>
                                        <option value="<?= (int)$adm['admin_id'] ?>" <?= ($selectedAdminId === (int)$adm['admin_id']) ? 'selected' : '' ?>>
                                            <?= e($adm['full_name']) ?> (Group #<?= (int)$adm['admin_id'] ?> - <?= e($adm['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-text text-muted" style="font-size: 0.74rem;">
                                You will belong exclusively to this administrator's isolated expense group.
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
                        <button type="submit" class="btn btn-primary-custom w-100 py-2 fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm" style="background: linear-gradient(135deg, #4f46e5, #3b82f6); border: none;">
                            <span>Continue to Email OTP Verification</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </form>

                    <!-- Footer Links -->
                    <div class="mt-4 pt-3 border-top text-center">
                        <span class="small text-muted">Already registered? </span>
                        <a href="<?= base_url('auth/login.php') ?>" class="text-primary fw-semibold small text-decoration-none">
                            Sign In to Account
                        </a>
                        <div class="mt-2 pt-2 border-top border-light">
                            <a href="<?= base_url('auth/register_admin.php') ?>" class="text-muted small text-decoration-none">
                                <i class="fa-solid fa-shield-halved me-1 text-danger"></i> Are you an admin? <strong>Register group with Secret Code</strong>
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
