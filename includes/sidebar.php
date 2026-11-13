<?php
// SplitMate Sidebar Component

$role = current_role();
$user = current_user();
$adminId = current_admin_id();

// Fetch Admin Group Name
$adminName = 'Admin Group';
if ($adminId > 0) {
    $stmt = db()->prepare("SELECT full_name FROM admins WHERE admin_id = ?");
    $stmt->execute([$adminId]);
    $adminRow = $stmt->fetch();
    if ($adminRow) {
        $adminName = $adminRow['full_name'];
    }
}

$currPage = $currentPage ?? '';
?>

<aside class="app-sidebar" id="appSidebar">
    <!-- Brand Header -->
    <div class="sidebar-header">
        <a href="<?= base_url($role === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php') ?>" class="sidebar-brand">
            <div class="brand-logo-icon">
                <i class="fa-solid fa-calculator"></i>
            </div>
            <span>Split<span class="brand-accent">Mate</span></span>
        </a>
    </div>

    <!-- Group Information Pill -->
    <div class="sidebar-admin-badge">
        <div class="d-flex align-items-center justify-content-between text-muted mb-1" style="font-size: 0.72rem;">
            <span><i class="fa-solid fa-users-gear me-1"></i> ACTIVE GROUP</span>
            <span class="badge bg-secondary" style="font-size: 0.65rem;">ID: #<?= $adminId ?></span>
        </div>
        <div class="fw-bold text-white text-truncate" title="<?= e($adminName) ?>">
            <?= e($adminName) ?>
        </div>
    </div>

    <!-- Navigation Links -->
    <div class="sidebar-nav">
        <?php if ($role === 'admin'): ?>
            <!-- ADMIN MENU -->
            <div class="nav-section-title">Admin Management</div>
            <a href="<?= base_url('admin/dashboard.php') ?>" class="nav-link <?= ($currPage === 'admin_dashboard') ? 'active' : '' ?>">
                <i class="fa-solid fa-gauge-high"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?= base_url('admin/expenses.php') ?>" class="nav-link <?= ($currPage === 'admin_expenses') ? 'active' : '' ?>">
                <i class="fa-solid fa-receipt"></i>
                <span>All Expenses</span>
            </a>
            <a href="<?= base_url('admin/users.php') ?>" class="nav-link <?= ($currPage === 'admin_users') ? 'active' : '' ?>">
                <i class="fa-solid fa-users"></i>
                <span>Manage Users</span>
            </a>
            <a href="<?= base_url('admin/user_expenses.php') ?>" class="nav-link <?= ($currPage === 'admin_user_expenses') ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie"></i>
                <span>User-wise Expenses</span>
            </a>
            <a href="<?= base_url('admin/settlements.php') ?>" class="nav-link <?= ($currPage === 'admin_settlements') ? 'active' : '' ?>">
                <i class="fa-solid fa-handshake"></i>
                <span>Settlement Management</span>
            </a>
            <a href="<?= base_url('admin/reports.php') ?>" class="nav-link <?= ($currPage === 'admin_reports') ? 'active' : '' ?>">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <span>Reports & Analytics</span>
            </a>
            <div class="nav-section-title mt-2">Account</div>
            <a href="<?= base_url('admin/profile.php') ?>" class="nav-link <?= ($currPage === 'admin_profile') ? 'active' : '' ?>">
                <i class="fa-solid fa-user-shield"></i>
                <span>Profile</span>
            </a>
            <a href="<?= base_url('auth/logout.php') ?>" class="nav-link text-danger">
                <i class="fa-solid fa-right-from-bracket text-danger"></i>
                <span>Logout</span>
            </a>

        <?php else: ?>
            <!-- USER MENU -->
            <div class="nav-section-title">Personal Space</div>
            <a href="<?= base_url('user/dashboard.php') ?>" class="nav-link <?= ($currPage === 'user_dashboard') ? 'active' : '' ?>">
                <i class="fa-solid fa-gauge-high"></i>
                <span>My Dashboard</span>
            </a>
            <a href="<?= base_url('user/add_expense.php') ?>" class="nav-link <?= ($currPage === 'user_add_expense') ? 'active' : '' ?>">
                <i class="fa-solid fa-plus-circle"></i>
                <span>Add Expense</span>
            </a>
            <a href="<?= base_url('user/my_expenses.php') ?>" class="nav-link <?= ($currPage === 'user_my_expenses') ? 'active' : '' ?>">
                <i class="fa-solid fa-wallet"></i>
                <span>My Expenses</span>
            </a>
            <div class="nav-section-title mt-2">Group & Settlements</div>
            <a href="<?= base_url('user/group_expenses.php') ?>" class="nav-link <?= ($currPage === 'user_group_expenses') ? 'active' : '' ?>">
                <i class="fa-solid fa-layer-group"></i>
                <span>Group Expenses</span>
            </a>
            <a href="<?= base_url('user/settlements.php') ?>" class="nav-link <?= ($currPage === 'user_settlements') ? 'active' : '' ?>">
                <i class="fa-solid fa-scale-balanced"></i>
                <span>Who Owes Whom</span>
            </a>
            <div class="nav-section-title mt-2">Account</div>
            <a href="<?= base_url('user/profile.php') ?>" class="nav-link <?= ($currPage === 'user_profile') ? 'active' : '' ?>">
                <i class="fa-solid fa-user"></i>
                <span>My Profile</span>
            </a>
            <a href="<?= base_url('auth/logout.php') ?>" class="nav-link text-danger">
                <i class="fa-solid fa-right-from-bracket text-danger"></i>
                <span>Logout</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- Sidebar User Footer -->
    <div class="sidebar-footer">
        <div class="user-profile-badge">
            <div class="user-avatar-sm">
                <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="overflow-hidden">
                <div class="fw-bold text-white text-truncate" style="font-size: 0.85rem;"><?= e($user['name'] ?? 'User') ?></div>
                <div class="text-muted text-capitalize" style="font-size: 0.75rem;"><?= e($role) ?></div>
            </div>
        </div>
    </div>
</aside>

