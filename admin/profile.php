<?php
// SplitMate Admin - Profile & Security Management

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$adminId = current_admin_id();
$pdo = db();
$error = '';
$success = '';

// Handle Profile Information Update
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
            $up = $pdo->prepare("UPDATE admins SET full_name = ?, phone = ?, updated_at = NOW() WHERE admin_id = ?");
            $up->execute([$fullName, $phone, $adminId]);

            $_SESSION['auth_user']['name'] = $fullName;
            $_SESSION['auth_user']['phone'] = $phone;

            log_activity($adminId, null, "Admin updated profile information");
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
            $stmt = $pdo->prepare("SELECT password FROM admins WHERE admin_id = ?");
            $stmt->execute([$adminId]);
            $hash = $stmt->fetchColumn();

            if (!password_verify($currentPass, $hash)) {
                $error = 'Current password is incorrect.';
            } else {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $up = $pdo->prepare("UPDATE admins SET password = ?, updated_at = NOW() WHERE admin_id = ?");
                $up->execute([$newHash, $adminId]);

                log_activity($adminId, null, "Admin changed account password");
                $success = 'Password changed successfully!';
            }
        }
    }
}

// Fetch Admin Details
$stmt = $pdo->prepare("SELECT * FROM admins WHERE admin_id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

// Fetch Activity Logs
$logStmt = $pdo->prepare("SELECT * FROM activity_logs WHERE admin_id = ? ORDER BY created_at DESC LIMIT 15");
$logStmt->execute([$adminId]);
$recentLogs = $logStmt->fetchAll();

$pageTitle = 'Admin Profile';
$currentPage = 'admin_profile';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">Administrator Profile</h3>
        <p class="text-muted mb-0 small">Manage your administrative credentials, group settings, and view group audit logs</p>
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
        <!-- Profile Details Card -->
        <div class="sm-card p-4 mb-4">
            <h5 class="fw-bold text-dark mb-3">
                <i class="fa-solid fa-user-shield text-primary me-2"></i>Account Details
            </h5>

            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="full_name" value="<?= e($admin['full_name']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email Address (Read-only)</label>
                    <input type="email" class="form-control bg-light" value="<?= e($admin['email']) ?>" readonly>
                    <div class="form-text small">Your login email serves as your unique admin identity.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" name="phone" value="<?= e($admin['phone']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Group ID</label>
                    <input type="text" class="form-control bg-light" value="#<?= (int)$admin['admin_id'] ?>" readonly>
                </div>

                <button type="submit" class="btn btn-primary-custom btn-sm">
                    <i class="fa-solid fa-save me-1"></i> Update Details
                </button>
            </form>
        </div>

        <!-- Change Password Card -->
        <div class="sm-card p-4">
            <h5 class="fw-bold text-dark mb-3">
                <i class="fa-solid fa-lock text-warning me-2"></i>Change Password
            </h5>

            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">

                <div class="mb-3">
                    <label class="form-label">Current Password</label>
                    <input type="password" class="form-control" name="current_password" required>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control" name="new_password" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-key me-1"></i> Update Password
                </button>
            </form>
        </div>
    </div>

    <!-- Right Column: Audit Activity Logs -->
    <div class="col-lg-6">
        <div class="sm-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0">
                    <i class="fa-solid fa-list-check text-info me-2"></i>Group Activity Log
                </h5>
                <span class="badge bg-light text-muted border">Recent 15 Events</span>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-custom">
                    <thead>
                        <tr>
                            <th>Activity Description</th>
                            <th>Time</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentLogs)): ?>
                            <tr><td colspan="3" class="text-center py-4 text-muted">No activity logged yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentLogs as $log): ?>
                                <tr>
                                    <td class="small text-dark"><?= e($log['activity']) ?></td>
                                    <td class="small text-muted text-nowrap"><?= format_date($log['created_at']) ?> <?= format_time($log['created_at']) ?></td>
                                    <td class="small text-muted font-monospace"><?= e($log['ip_address']) ?></td>
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

