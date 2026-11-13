<?php
// SplitMate Admin - Comprehensive Reports & Analytics

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$adminId = current_admin_id();
$pdo = db();

// Fetch filter choices
$categories = get_categories();
$uStmt = $pdo->prepare("SELECT user_id, full_name FROM users WHERE admin_id = ? ORDER BY full_name ASC");
$uStmt->execute([$adminId]);
$adminUsers = $uStmt->fetchAll();

// Parameters
$reportType  = trim($_GET['report_type'] ?? 'overall');
$startDate   = trim($_GET['start_date'] ?? '');
$endDate     = trim($_GET['end_date'] ?? '');
$filterUser  = (int)($_GET['user_id'] ?? 0);
$filterCat   = trim($_GET['category'] ?? '');

// Base filtering SQL conditions for expenses
$where = "WHERE e.admin_id = ?";
$params = [$adminId];

if (!empty($startDate)) {
    $where .= " AND e.expense_date >= ?";
    $params[] = $startDate;
}
if (!empty($endDate)) {
    $where .= " AND e.expense_date <= ?";
    $params[] = $endDate;
}
if ($filterUser > 0) {
    $where .= " AND e.user_id = ?";
    $params[] = $filterUser;
}
if (!empty($filterCat)) {
    $where .= " AND e.category = ?";
    $params[] = $filterCat;
}

$reportData = [];
$reportTitle = 'Overall Group Expense Report';

switch ($reportType) {
    case 'individual':
        $reportTitle = 'Individual User Expense Report';
        $sql = "SELECT u.full_name as member_name, u.email,
                       COUNT(e.expense_id) as entry_count,
                       COALESCE(SUM(e.amount), 0) as total_spent,
                       MIN(e.expense_date) as first_expense,
                       MAX(e.expense_date) as latest_expense
                FROM users u
                LEFT JOIN expenses e ON u.user_id = e.user_id AND e.admin_id = ? " . 
                (!empty($startDate) ? " AND e.expense_date >= '$startDate'" : "") .
                (!empty($endDate) ? " AND e.expense_date <= '$endDate'" : "") .
                (!empty($filterCat) ? " AND e.category = " . $pdo->quote($filterCat) : "") . "
                WHERE u.admin_id = ? " . ($filterUser > 0 ? " AND u.user_id = $filterUser" : "") . "
                GROUP BY u.user_id 
                ORDER BY total_spent DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$adminId, $adminId]);
        $reportData = $stmt->fetchAll();
        break;

    case 'monthly':
        $reportTitle = 'Monthly Expense Report';
        $sql = "SELECT DATE_FORMAT(e.expense_date, '%M %Y') as month_name,
                       DATE_FORMAT(e.expense_date, '%Y-%m') as year_month,
                       COUNT(e.expense_id) as transaction_count,
                       COALESCE(SUM(e.amount), 0) as monthly_total,
                       AVG(e.amount) as avg_expense
                FROM expenses e
                $where
                GROUP BY year_month, month_name
                ORDER BY year_month DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();
        break;

    case 'category':
        $reportTitle = 'Category-wise Expense Report';
        $sql = "SELECT e.category,
                       COUNT(e.expense_id) as transaction_count,
                       COALESCE(SUM(e.amount), 0) as category_total,
                       AVG(e.amount) as avg_amount
                FROM expenses e
                $where
                GROUP BY e.category
                ORDER BY category_total DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();
        break;

    case 'settlement':
        $reportTitle = 'Settlement & Payment Reconciliation Report';
        $sWhere = "WHERE s.admin_id = ?";
        $sParams = [$adminId];
        if (!empty($startDate)) {
            $sWhere .= " AND s.created_at >= ?";
            $sParams[] = $startDate . ' 00:00:00';
        }
        if (!empty($endDate)) {
            $sWhere .= " AND s.created_at <= ?";
            $sParams[] = $endDate . ' 23:59:59';
        }
        $sql = "SELECT s.*, p.full_name as payer_name, r.full_name as receiver_name 
                FROM settlements s 
                JOIN users p ON s.payer_id = p.user_id 
                JOIN users r ON s.receiver_id = r.user_id 
                $sWhere
                ORDER BY s.status ASC, s.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($sParams);
        $reportData = $stmt->fetchAll();
        break;

    case 'pending':
        $reportTitle = 'Pending Payment Report';
        $sql = "SELECT s.*, p.full_name as payer_name, p.phone as payer_phone, r.full_name as receiver_name, r.phone as receiver_phone 
                FROM settlements s 
                JOIN users p ON s.payer_id = p.user_id 
                JOIN users r ON s.receiver_id = r.user_id 
                WHERE s.admin_id = ? AND s.status = 'pending'
                ORDER BY s.amount DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$adminId]);
        $reportData = $stmt->fetchAll();
        break;

    case 'overall':
    default:
        $reportTitle = 'Overall Group Expense Report';
        $sql = "SELECT e.*, u.full_name as user_name 
                FROM expenses e 
                JOIN users u ON e.user_id = u.user_id 
                $where 
                ORDER BY e.expense_date DESC, e.expense_time DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();
        break;
}

$pageTitle = 'Reports & Analytics';
$currentPage = 'admin_reports';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 no-print">
    <div>
        <h3 class="fw-bold text-dark mb-1">Reports & Analytics</h3>
        <p class="text-muted mb-0 small">Generate, inspect, print, and export structured group financial reports</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-outline-success btn-sm d-flex align-items-center gap-2" onclick="exportTableToCSV('reportDataTable', '<?= e($reportType) ?>_report.csv')">
            <i class="fa-solid fa-file-csv"></i>
            <span>Export CSV</span>
        </button>
        <button type="button" class="btn btn-primary-custom btn-sm d-flex align-items-center gap-2" onclick="printReport()">
            <i class="fa-solid fa-print"></i>
            <span>Print / PDF</span>
        </button>
    </div>
</div>

<!-- ========================================================
     Report Configuration Filter Bar
     ======================================================== -->
<div class="sm-card p-3 mb-4 filter-card no-print">
    <form method="GET" action="">
        <div class="row g-2 align-items-end">
            <!-- Report Type -->
            <div class="col-md-3">
                <label class="form-label">Report Type</label>
                <select name="report_type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="overall" <?= ($reportType === 'overall') ? 'selected' : '' ?>>Overall Group Expenses</option>
                    <option value="individual" <?= ($reportType === 'individual') ? 'selected' : '' ?>>Individual User Expenses</option>
                    <option value="monthly" <?= ($reportType === 'monthly') ? 'selected' : '' ?>>Monthly Expense Report</option>
                    <option value="category" <?= ($reportType === 'category') ? 'selected' : '' ?>>Category-wise Report</option>
                    <option value="settlement" <?= ($reportType === 'settlement') ? 'selected' : '' ?>>Settlement & Reconciliation</option>
                    <option value="pending" <?= ($reportType === 'pending') ? 'selected' : '' ?>>Pending Payments Only</option>
                </select>
            </div>

            <!-- Member Filter -->
            <div class="col-md-2">
                <label class="form-label">Member</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="0">All Members</option>
                    <?php foreach ($adminUsers as $u): ?>
                        <option value="<?= (int)$u['user_id'] ?>" <?= ($filterUser === (int)$u['user_id']) ? 'selected' : '' ?>>
                            <?= e($u['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Category Filter -->
            <div class="col-md-2">
                <label class="form-label">Category</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat['name']) ?>" <?= ($filterCat === $cat['name']) ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date Range -->
            <div class="col-md-3">
                <label class="form-label">Date Range</label>
                <div class="input-group input-group-sm">
                    <input type="date" name="start_date" class="form-control" value="<?= e($startDate) ?>">
                    <span class="input-group-text bg-light">to</span>
                    <input type="date" name="end_date" class="form-control" value="<?= e($endDate) ?>">
                </div>
            </div>

            <!-- Actions -->
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary-custom w-100">
                    <i class="fa-solid fa-filter me-1"></i> Apply
                </button>
                <a href="<?= base_url('admin/reports.php') ?>" class="btn btn-sm btn-outline-secondary" title="Reset">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </div>
    </form>
</div>

<!-- ========================================================
     Printable Report Document Canvas
     ======================================================== -->
<div class="sm-card p-4">
    <!-- Formal Document Header -->
    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1"><?= e($reportTitle) ?></h4>
            <div class="text-muted small">
                Admin Group: <strong><?= e($_SESSION['auth_user']['name'] ?? 'Admin') ?> (ID #<?= $adminId ?>)</strong>
            </div>
            <div class="text-muted small">
                Period: <?= !empty($startDate) ? format_date($startDate) : 'Start' ?> &mdash; <?= !empty($endDate) ? format_date($endDate) : 'Current Date' ?>
                <?= !empty($filterCat) ? " | Category: <strong>" . e($filterCat) . "</strong>" : "" ?>
            </div>
        </div>
        <div class="text-end">
            <h5 class="fw-bold mb-0 text-primary">Split<span class="text-success">Mate</span></h5>
            <small class="text-muted">Generated: <?= date('d M Y, h:i A') ?></small>
        </div>
    </div>

    <!-- Report Table Renderers based on Type -->
    <div class="table-responsive">
        <table class="table table-custom table-bordered" id="reportDataTable">
            <?php if ($reportType === 'overall'): ?>
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
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
                    <?php 
                    $sum = 0;
                    if (empty($reportData)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No records found matching filters.</td></tr>
                    <?php else: ?>
                        <?php foreach ($reportData as $i => $row): $sum += (float)$row['amount']; ?>
                            <tr>
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td class="fw-bold text-dark"><?= e($row['title']) ?></td>
                                <td><?= e($row['user_name']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($row['category']) ?></span></td>
                                <td class="fw-bold text-dark"><?= format_currency($row['amount']) ?></td>
                                <td><?= format_date($row['expense_date']) ?> <small class="text-muted"><?= format_time($row['expense_time']) ?></small></td>
                                <td><?= e($row['payment_method']) ?></td>
                                <td><?= e($row['description'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold">
                            <td colspan="4" class="text-end">Total Amount:</td>
                            <td colspan="4" class="text-primary fs-6"><?= format_currency($sum) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>

            <?php elseif ($reportType === 'individual'): ?>
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Member Name</th>
                        <th>Email</th>
                        <th>Total Expenses</th>
                        <th>Total Amount Spent</th>
                        <th>First Entry</th>
                        <th>Latest Entry</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sum = 0;
                    if (empty($reportData)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No member records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($reportData as $i => $row): $sum += (float)$row['total_spent']; ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="fw-bold text-dark"><?= e($row['member_name']) ?></td>
                                <td><?= e($row['email']) ?></td>
                                <td><?= (int)$row['entry_count'] ?></td>
                                <td class="fw-bold text-dark"><?= format_currency($row['total_spent']) ?></td>
                                <td><?= format_date($row['first_expense']) ?></td>
                                <td><?= format_date($row['latest_expense']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold">
                            <td colspan="4" class="text-end">Group Cumulative:</td>
                            <td colspan="3" class="text-primary fs-6"><?= format_currency($sum) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>

            <?php elseif ($reportType === 'monthly'): ?>
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Month</th>
                        <th>Transaction Count</th>
                        <th>Monthly Total</th>
                        <th>Average / Expense</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sum = 0;
                    if (empty($reportData)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No monthly records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($reportData as $i => $row): $sum += (float)$row['monthly_total']; ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="fw-bold text-dark"><?= e($row['month_name']) ?></td>
                                <td><?= (int)$row['transaction_count'] ?></td>
                                <td class="fw-bold text-dark"><?= format_currency($row['monthly_total']) ?></td>
                                <td><?= format_currency($row['avg_expense']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold">
                            <td colspan="3" class="text-end">Overall Total:</td>
                            <td colspan="2" class="text-primary fs-6"><?= format_currency($sum) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>

            <?php elseif ($reportType === 'category'): ?>
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Category</th>
                        <th>Transactions</th>
                        <th>Total Amount</th>
                        <th>Average / Transaction</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sum = 0;
                    if (empty($reportData)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No category data found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($reportData as $i => $row): $sum += (float)$row['category_total']; ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="fw-bold text-dark"><?= e($row['category']) ?></td>
                                <td><?= (int)$row['transaction_count'] ?></td>
                                <td class="fw-bold text-dark"><?= format_currency($row['category_total']) ?></td>
                                <td><?= format_currency($row['avg_amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold">
                            <td colspan="3" class="text-end">Overall Total:</td>
                            <td colspan="2" class="text-primary fs-6"><?= format_currency($sum) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>

            <?php elseif ($reportType === 'settlement' || $reportType === 'pending'): ?>
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Payer (Debtor)</th>
                        <th>Receiver (Creditor)</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Confirmed By</th>
                        <th>Payment Timestamp</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sum = 0;
                    if (empty($reportData)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No settlement records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($reportData as $i => $row): $sum += (float)$row['amount']; ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="fw-bold text-danger"><?= e($row['payer_name']) ?></td>
                                <td class="fw-bold text-success"><?= e($row['receiver_name']) ?></td>
                                <td class="fw-bold text-dark"><?= format_currency($row['amount']) ?></td>
                                <td>
                                    <?php if ($row['status'] === 'paid'): ?>
                                        <span class="badge bg-success-subtle text-success">Paid</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($row['confirmed_by'] ?: '-') ?></td>
                                <td><?= !empty($row['paid_at']) ? format_date($row['paid_at']) . ' ' . format_time($row['paid_at']) : '-' ?></td>
                                <td><?= e($row['notes'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold">
                            <td colspan="3" class="text-end">Sum of Transactions:</td>
                            <td colspan="5" class="text-primary fs-6"><?= format_currency($sum) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>

