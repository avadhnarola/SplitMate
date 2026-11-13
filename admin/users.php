<?php
// SplitMate Admin - User Management

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$adminId = current_admin_id();
$pdo = db();

// Handle User Status Toggle (Activate / Deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $token = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($token)) {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $newStatus    = ($_POST['status'] === 'active') ? 'active' : 'inactive';

        // Check if user belongs to this admin
        $chk = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ? AND admin_id = ?");
        $chk->execute([$targetUserId, $adminId]);
        $targetUser = $chk->fetch();

        if ($targetUser) {
            $up = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE user_id = ? AND admin_id = ?");
            $up->execute([$newStatus, $targetUserId, $adminId]);

            log_activity($adminId, $targetUserId, "User status for {$targetUser['full_name']} changed to " . ucfirst($newStatus));
            
            // Recalculate settlements since active membership changed
            sync_settlements_to_database($adminId);

            set_flash('success', "User '{$targetUser['full_name']}' is now {$newStatus}. Settlements updated.");
        }
    }
    header('Location: ' . base_url('admin/users.php'));
    exit;
}

// Fetch live settlement matrix with calculated balances for this admin's group
$matrix = calculate_settlement_matrix($adminId);
$userBalances = $matrix['user_balances'];

// Also fetch all users (including inactive ones) registered under this admin
$stmt = $pdo->prepare("
    SELECT u.*, 
           COALESCE(SUM(e.amount), 0) as actual_spent, 
           COUNT(e.expense_id) as actual_count 
    FROM users u 
    LEFT JOIN expenses e ON u.user_id = e.user_id AND e.admin_id = ? 
    WHERE u.admin_id = ? 
    GROUP BY u.user_id 
    ORDER BY u.created_at ASC
");
$stmt->execute([$adminId, $adminId]);
$allUsers = $stmt->fetchAll();

$pageTitle = 'Manage Users';
$currentPage = 'admin_users';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header Row -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">Group Member Management</h3>
        <p class="text-muted mb-0 small">Review members, individual spending contributions, active settlement balances, and account status</p>
    </div>
    <div>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fs-6">
            <i class="fa-solid fa-users me-1"></i> Total Members: <?= count($allUsers) ?> (<?= $matrix['active_members_count'] ?> Active)
        </span>
    </div>
</div>

<!-- ========================================================
     User Summary Metric Cards
     ======================================================== -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="sm-stat-card">
            <div class="stat-title">Group Fair Share</div>
            <div class="stat-value text-primary"><?= format_currency($matrix['fair_share']) ?></div>
            <div class="stat-subtext">Calculated equal share per active member</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="sm-stat-card">
            <div class="stat-title">Total Active Group Spending</div>
            <div class="stat-value text-dark"><?= format_currency($matrix['total_group_expense']) ?></div>
            <div class="stat-subtext">Sum of active member contributions</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="sm-stat-card">
            <div class="stat-title">Settlement Circulation</div>
            <div class="stat-value text-success"><?= format_currency($matrix['total_receivable']) ?></div>
            <div class="stat-subtext">Required debt realignment amount</div>
        </div>
    </div>
</div>

<!-- ========================================================
     User Summary Table (Prompt Requirement)
     ======================================================== -->
<div class="sm-card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold text-dark mb-0">Member Contribution & Settlement Balances</h5>
            <small class="text-muted">Dynamic calculated balances based on actual MySQL transactions</small>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('usersTable', 'group_members_summary.csv')">
            <i class="fa-solid fa-download me-1"></i> Export Summary
        </button>
    </div>

    <div class="table-responsive">
        <table class="table table-custom" id="usersTable">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email & Contact</th>
                    <th>Status</th>
                    <th>Total Spent</th>
                    <th>Expenses</th>
                    <th>Fair Share</th>
                    <th>Calculated Balance</th>
                    <th class="text-end no-export">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allUsers)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            No members registered under your group yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($allUsers as $u): ?>
                        <?php
                        $uid = (int)$u['user_id'];
                        $isActive = ($u['status'] === 'active');
                        $balanceInfo = $userBalances[$uid] ?? null;

                        $spent = (float)$u['actual_spent'];
                        $expCount = (int)$u['actual_count'];
                        $netBalance = $balanceInfo ? (float)$balanceInfo['net_balance'] : 0.00;
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar-sm" style="background-color: <?= $isActive ? '#4f46e5' : '#94a3b8' ?>;">
                                        <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?= e($u['full_name']) ?></div>
                                        <small class="text-muted">ID: #<?= $uid ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><i class="fa-regular fa-envelope me-1 text-muted"></i><?= e($u['email']) ?></div>
                                <small class="text-muted"><i class="fa-solid fa-phone me-1"></i><?= e($u['phone']) ?></small>
                            </td>
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="fa-solid fa-circle-check me-1"></i>Active
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                        <i class="fa-solid fa-circle-xmark me-1"></i>Inactive
                                    </span>
                                <?php endif; ?>

                                <?php if ($u['email_verified'] == 1): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" title="Email Verified">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold text-dark">
                                <?= format_currency($spent) ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= $expCount ?> <?= $expCount === 1 ? 'entry' : 'entries' ?>
                                </span>
                            </td>
                            <td>
                                <?= $isActive ? format_currency($matrix['fair_share']) : '<span class="text-muted">Excluded</span>' ?>
                            </td>
                            <td>
                                <?php if (!$isActive): ?>
                                    <span class="text-muted small">Inactive (Not Split)</span>
                                <?php elseif ($netBalance > 0): ?>
                                    <span class="badge badge-custom badge-receive">
                                        <i class="fa-solid fa-arrow-down-left"></i> +<?= format_currency($netBalance) ?> (Gets Back)
                                    </span>
                                <?php elseif ($netBalance < 0): ?>
                                    <span class="badge badge-custom badge-pay">
                                        <i class="fa-solid fa-arrow-up-right"></i> -<?= format_currency(abs($netBalance)) ?> (Owes Group)
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-custom badge-settled">
                                        <i class="fa-solid fa-check"></i> Settled (₹0.00)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end no-export">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary" onclick="viewUserProfile(<?= $uid ?>)" title="View Member Profile & History">
                                        <i class="fa-solid fa-eye text-primary"></i>
                                    </button>

                                    <!-- Status Toggle Form -->
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Change status of <?= e(addslashes($u['full_name'])) ?> to <?= $isActive ? 'Inactive' : 'Active' ?>?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                                        <input type="hidden" name="status" value="<?= $isActive ? 'inactive' : 'active' ?>">
                                        <button type="submit" class="btn btn-outline-secondary" title="<?= $isActive ? 'Deactivate Member' : 'Activate Member' ?>">
                                            <i class="fa-solid <?= $isActive ? 'fa-user-slash text-danger' : 'fa-user-check text-success' ?>"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================================
     User Profile & Expense History Modal
     ======================================================== -->
<div class="modal fade" id="userProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="userProfileModalTitle">
                    <i class="fa-solid fa-user me-2 text-primary"></i>Member Profile & History
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="userProfileModalBody">
                <div class="text-center py-4 text-muted">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2">Loading member profile and expense history...</div>
                </div>
            </div>
            <div class="modal-footer bg-light px-4 py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = "
<script>
function viewUserProfile(userId) {
    const modal = new bootstrap.Modal(document.getElementById('userProfileModal'));
    modal.show();

    fetch('" . base_url('api/filters.php?scope=admin&user_id=') . "' + userId)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                let html = `
                    <div class=\"row g-3 mb-4\">
                        <div class=\"col-sm-4\">
                            <div class=\"p-3 bg-light rounded border text-center\">
                                <div class=\"small text-muted\">Expenses Entered</div>
                                <div class=\"fs-4 fw-bold text-dark\">` + data.count + `</div>
                            </div>
                        </div>
                        <div class=\"col-sm-4\">
                            <div class=\"p-3 bg-light rounded border text-center\">
                                <div class=\"small text-muted\">Total Amount Spent</div>
                                <div class=\"fs-4 fw-bold text-primary\">` + data.formatted_total + `</div>
                            </div>
                        </div>
                        <div class=\"col-sm-4\">
                            <div class=\"p-3 bg-light rounded border text-center\">
                                <div class=\"small text-muted\">Group Status</div>
                                <div class=\"fs-6 fw-bold text-success mt-1\"><i class=\"fa-solid fa-circle-check me-1\"></i>Registered Member</div>
                            </div>
                        </div>
                    </div>

                    <h6 class=\"fw-bold mb-3 text-dark\"><i class=\"fa-solid fa-clock-rotate-left text-primary me-2\"></i>Expense History</h6>
                    <div class=\"table-responsive\">
                        <table class=\"table table-sm table-bordered table-striped\">
                            <thead class=\"table-light\">
                                <tr>
                                    <th>#</th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Payment Method</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                if (data.raw_data.length === 0) {
                    html += `<tr><td colspan=\"6\" class=\"text-center text-muted py-3\">No expenses recorded by this member.</td></tr>`;
                } else {
                    data.raw_data.forEach((exp, idx) => {
                        html += `
                            <tr>
                                <td>` + (idx + 1) + `</td>
                                <td class=\"fw-bold\">` + exp.title + `</td>
                                <td><span class=\"badge bg-light text-dark border\">` + exp.category + `</span></td>
                                <td class=\"fw-bold\">` + exp.formatted_amount + `</td>
                                <td>` + exp.formatted_date + `</td>
                                <td><span class=\"badge bg-secondary-subtle text-secondary\">` + exp.payment_method + `</span></td>
                            </tr>
                        `;
                    });
                }

                html += `
                            </tbody>
                        </table>
                    </div>
                `;

                document.getElementById('userProfileModalBody').innerHTML = html;
            }
        })
        .catch(() => {
            document.getElementById('userProfileModalBody').innerHTML = '<div class=\"alert alert-danger\">Failed to load user profile.</div>';
        });
}
</script>
";

require_once __DIR__ . '/../includes/footer.php';
?>

