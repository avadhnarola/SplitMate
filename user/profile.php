<?php
// SplitMate User - Profile & Password Settings

require_once __DIR__ . '/../includes/auth.php';
require_user();

$userId  = current_user_id();
$adminId = current_admin_id();
$pdo = db();
$error = '';
$success = '';

// Handle Profile Info Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Security session expired. Please refresh.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');

        if (empty($fullName) || empty($phone)) {
            $error = 'Name and phone are required.';
        } else {
            $up = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, updated_at = NOW() WHERE user_id = ? AND admin_id = ?");
            $up->execute([$fullName, $phone, $userId, $adminId]);

            $_SESSION['auth_user']['name'] = $fullName;
            $_SESSION['auth_user']['phone'] = $phone;

            log_activity($adminId, $userId, "User updated profile information");
            $success = 'Profile details updated successfully!';
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Security session expired. Please refresh.';
    } else {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass)) {
            $error = 'Please fill in all password fields.';
        } elseif (strlen($newPass) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'New passwords do not match.';
        } else {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ? AND admin_id = ?");
            $stmt->execute([$userId, $adminId]);
            $hash = $stmt->fetchColumn();

            if (!password_verify($currentPass, $hash)) {
                $error = 'Current password is incorrect.';
            } else {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $up = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ? AND admin_id = ?");
                $up->execute([$newHash, $userId, $adminId]);

                log_activity($adminId, $userId, "User changed account password");
                $success = 'Password changed successfully!';
            }
        }
    }
}

// Fetch User Info
$stmt = $pdo->prepare("SELECT u.*, a.full_name as admin_name FROM users u JOIN admins a ON u.admin_id = a.admin_id WHERE u.user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Fetch Recent Activities
$logStmt = $pdo->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$logStmt->execute([$userId]);
$recentLogs = $logStmt->fetchAll();

$pageTitle = 'My Profile';
$currentPage = 'user_profile';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">My Profile</h3>
        <p class="text-muted mb-0 small">Manage your account profile, personal contact details, and password</p>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small d-flex align-items-center gap-2 mb-3">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span><?= e($error) ?></span>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 small d-flex align-items-center gap-2 mb-3">
        <i class="fa-solid fa-check-circle"></i>
        <span><?= e($success) ?></span>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: Profile Card & Password -->
    <div class="col-lg-6">
        <div class="sm-card p-4 p-md-5 mb-4" style="border-radius: 16px;">
            <div class="d-flex align-items-center justify-content-between pb-3 mb-4 border-bottom">
                <div class="d-flex align-items-center gap-3">
                    <div class="user-avatar-sm" style="width: 44px; height: 44px; font-size: 1.1rem; background: linear-gradient(135deg, #4f46e5, #7c3aed);">
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Personal Information</h5>
                        <small class="text-muted">Manage your contact profile and details</small>
                    </div>
                </div>
            </div>

            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-user"></i></span>
                        <input type="text" class="form-control border-start-0" name="full_name" value="<?= e($user['full_name']) ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email Address (Verified)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" class="form-control border-start-0 bg-light" value="<?= e($user['email']) ?>" readonly>
                        <span class="input-group-text bg-success-subtle text-success border-success-subtle">
                            <i class="fa-solid fa-check-circle me-1"></i> Verified
                        </span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-phone"></i></span>
                        <input type="tel" class="form-control border-start-0" name="phone" value="<?= e($user['phone']) ?>" required>
                    </div>
                </div>

                <!-- Admin Group Affiliation -->
                <div class="mb-4">
                    <label class="form-label">My Admin Group</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-shield-halved"></i></span>
                        <input type="text" class="form-control border-start-0 bg-light" value="<?= e($user['admin_name']) ?> (Group #<?= (int)$user['admin_id'] ?>)" readonly>
                    </div>
                    <div class="form-text small text-muted">You belong exclusively to this administrator's expense ledger.</div>
                </div>

                <button type="submit" class="btn btn-primary-custom d-flex align-items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Save Profile Changes</span>
                </button>
            </form>
        </div>

        <!-- Password Change Card -->
        <div class="sm-card p-4 p-md-5" style="border-radius: 16px;">
            <div class="d-flex align-items-center gap-3 pb-3 mb-4 border-bottom">
                <div class="rounded-3 d-flex align-items-center justify-content-center text-white" style="width: 40px; height: 40px; background: linear-gradient(135deg, #f59e0b, #d97706); font-size: 1.1rem;">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-dark mb-0">Change Password</h5>
                    <small class="text-muted">Update your account login credentials</small>
                </div>
            </div>

            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">

                <div class="mb-3">
                    <label class="form-label">Current Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-key"></i></span>
                        <input type="password" class="form-control border-start-0" name="current_password" required placeholder="••••••••">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" class="form-control border-start-0" name="new_password" required minlength="6" placeholder="At least 6 chars">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" class="form-control border-start-0" name="confirm_password" required placeholder="Repeat password">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-outline-primary d-flex align-items-center gap-2">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>Update Password</span>
                </button>
            </form>
        </div>
    </div>
                    <i class="fa-solid fa-key me-1"></i> Update Password
                </button>
            </form>
        </div>
    </div>

    <!-- Right Column: Personal Activity Logs -->
    <div class="col-lg-6">
        <div class="sm-card p-4 h-100">
            <h5 class="fw-bold text-dark mb-3">
                <i class="fa-solid fa-clock-rotate-left text-info me-2"></i>My Activity Log
            </h5>

            <div class="table-responsive">
                <table class="table table-sm table-custom">
                    <thead>
                        <tr>
                            <th>Activity</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentLogs)): ?>
                            <tr><td colspan="2" class="text-center py-4 text-muted">No personal activities logged yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentLogs as $log): ?>
                                <tr>
                                    <td class="small text-dark"><?= e($log['activity']) ?></td>
                                    <td class="small text-muted text-nowrap"><?= format_date($log['created_at']) ?> <?= format_time($log['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>

