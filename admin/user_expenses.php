<?php
// SplitMate Admin - User-wise Expenses Breakdown

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$adminId = current_admin_id();
$pdo = db();

// Fetch active users in this group
$uStmt = $pdo->prepare("SELECT user_id, full_name, email FROM users WHERE admin_id = ? AND status = 'active' ORDER BY full_name ASC");
$uStmt->execute([$adminId]);
$users = $uStmt->fetchAll();

// Selected user filter (0 = all users breakdown)
$selectedUserId = (int)($_GET['user_id'] ?? 0);

// Get total group expenses for percentage calculation
$grpTotalStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE admin_id = ?");
$grpTotalStmt->execute([$adminId]);
$groupTotalAmount = (float)$grpTotalStmt->fetchColumn();

// Fetch users with aggregate stats
$aggStmt = $pdo->prepare("
    SELECT u.user_id, u.full_name, u.email, 
           COALESCE(SUM(e.amount), 0) as total_spent, 
           COUNT(e.expense_id) as expense_count,
           MAX(e.expense_date) as last_expense_date
    FROM users u 
    LEFT JOIN expenses e ON u.user_id = e.user_id AND e.admin_id = ? 
    WHERE u.admin_id = ? AND u.status = 'active'
    GROUP BY u.user_id 
    ORDER BY total_spent DESC
");
$aggStmt->execute([$adminId, $adminId]);
$userSummaries = $aggStmt->fetchAll();

// Fetch detailed expenses for the table
$expSql = "SELECT e.*, u.full_name as user_name 
           FROM expenses e 
           JOIN users u ON e.user_id = u.user_id 
           WHERE e.admin_id = ?";
$params = [$adminId];
if ($selectedUserId > 0) {
    $expSql .= " AND e.user_id = ?";
    $params[] = $selectedUserId;
}
$expSql .= " ORDER BY e.expense_date DESC, e.expense_time DESC";
$expStmt = $pdo->prepare($expSql);
$expStmt->execute($params);
$detailedExpenses = $expStmt->fetchAll();

$pageTitle = 'User-wise Expenses';
$currentPage = 'admin_user_expenses';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">User-wise Expense Analysis</h3>
        <p class="text-muted mb-0 small">Examine individual spending habits and contribution breakdowns across members</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <form method="GET" class="d-flex align-items-center gap-2">
            <label class="form-label mb-0 small text-muted text-nowrap">Filter User:</label>
            <select name="user_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="0">All Members Combined</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= (int)$u['user_id'] ?>" <?= ($selectedUserId === (int)$u['user_id']) ? 'selected' : '' ?>>
                        <?= e($u['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<!-- ========================================================
     Member Contribution Cards Grid
     ======================================================== -->
<div class="row g-3 mb-4">
    <?php foreach ($userSummaries as $uSum): ?>
        <?php
        $spent = (float)$uSum['total_spent'];
        $percentage = ($groupTotalAmount > 0) ? round(($spent / $groupTotalAmount) * 100, 1) : 0;
        $isCurrentSelected = ($selectedUserId === (int)$uSum['user_id']);
        ?>
        <div class="col-sm-6 col-lg-4 col-xl-3">
            <div class="sm-card p-3 h-100 <?= $isCurrentSelected ? 'border-primary shadow-sm' : '' ?>" style="border-left: 4px solid #4f46e5;">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="user-avatar-sm" style="width: 32px; height: 32px;">
                            <?= strtoupper(substr($uSum['full_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="fw-bold text-dark text-truncate" style="max-width: 130px;"><?= e($uSum['full_name']) ?></div>
                            <small class="text-muted"><?= (int)$uSum['expense_count'] ?> expenses</small>
                        </div>
                    </div>
                    <span class="badge bg-light text-primary border fw-bold"><?= $percentage ?>%</span>
                </div>

                <div class="fs-4 fw-bold text-dark mb-1"><?= format_currency($spent) ?></div>
                <div class="progress mb-2" style="height: 6px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= min(100, $percentage) ?>%"></div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-2 small text-muted">
                    <span>Last: <?= format_date($uSum['last_expense_date']) ?></span>
                    <a href="?user_id=<?= (int)$uSum['user_id'] ?>" class="text-decoration-none fw-semibold <?= $isCurrentSelected ? 'text-primary' : 'text-muted' ?>">
                        <?= $isCurrentSelected ? 'Active Filter' : 'Filter &rarr;' ?>
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ========================================================
     Detailed Expense List Table
     ======================================================== -->
<div class="sm-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold text-dark mb-0">Expense Ledger</h5>
            <small class="text-muted">
                <?= $selectedUserId > 0 ? 'Showing expenses entered by selected member' : 'Showing all group entries' ?>
            </small>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('userExpensesTable', 'user_expenses_ledger.csv')">
            <i class="fa-solid fa-file-csv me-1"></i> Export Ledger
        </button>
    </div>

    <div class="table-responsive">
        <table class="table table-custom" id="userExpensesTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Expense Title</th>
                    <th>Paid By</th>
                    <th>Category</th>
                    <th>Amount</th>
                    <th>Date & Time</th>
                    <th>Payment Method</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($detailedExpenses)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            No expenses found for this selection.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($detailedExpenses as $idx => $exp): ?>
                        <tr>
                            <td class="text-muted small"><?= $idx + 1 ?></td>
                            <td class="fw-bold text-dark"><?= e($exp['title']) ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar-sm" style="width: 24px; height: 24px; font-size: 0.7rem;">
                                        <?= strtoupper(substr($exp['user_name'], 0, 1)) ?>
                                    </div>
                                    <span class="fw-medium"><?= e($exp['user_name']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= e($exp['category']) ?>
                                </span>
                            </td>
                            <td class="fw-bold text-dark"><?= format_currency($exp['amount']) ?></td>
                            <td>
                                <div><?= format_date($exp['expense_date']) ?></div>
                                <small class="text-muted" style="font-size: 0.72rem;"><?= format_time($exp['expense_time']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary">
                                    <?= e($exp['payment_method']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-muted small text-truncate d-inline-block" style="max-width: 200px;" title="<?= e($exp['description']) ?>">
                                    <?= !empty($exp['description']) ? e($exp['description']) : '-' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>

