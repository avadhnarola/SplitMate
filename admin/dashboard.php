<?php
// SplitMate Admin Dashboard

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$adminId = current_admin_id();
$stats = get_admin_dashboard_stats($adminId);
$categories = get_categories();

$pageTitle = 'Admin Dashboard';
$currentPage = 'admin_dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header Title & Actions -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">Group Overview</h3>
        <p class="text-muted mb-0 small">
            Monitoring expenses and automated settlements for <strong class="text-dark"><?= e($_SESSION['auth_user']['name'] ?? 'Admin') ?>'s Group</strong>
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2" onclick="triggerSyncSettlements()">
            <i class="fa-solid fa-arrows-rotate"></i>
            <span>Recalculate Settlements</span>
        </button>
        <button type="button" class="btn btn-primary-custom btn-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="fa-solid fa-plus-circle"></i>
            <span>Record Expense</span>
        </button>
    </div>
</div>

<!-- ========================================================
     1. Dashboard Summary Cards
     ======================================================== -->
<div class="row g-3 mb-4">
    <!-- Total Group Expenses -->
    <div class="col-sm-6 col-xl-4">
        <div class="sm-stat-card d-flex align-items-center gap-3">
            <div class="stat-icon-wrapper stat-icon-primary">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <div>
                <div class="stat-title">Total Group Expenses</div>
                <div class="stat-value"><?= format_currency($stats['total_expenses']) ?></div>
                <div class="stat-subtext">Cumulative spending across group</div>
            </div>
        </div>
    </div>

    <!-- Total Number of Users -->
    <div class="col-sm-6 col-xl-2">
        <div class="sm-stat-card">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="stat-title mb-0">Total Users</div>
                <i class="fa-solid fa-users text-primary"></i>
            </div>
            <div class="stat-value"><?= (int)$stats['total_users'] ?></div>
            <div class="stat-subtext">Active members</div>
        </div>
    </div>

    <!-- Total Expense Entries -->
    <div class="col-sm-6 col-xl-2">
        <div class="sm-stat-card">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="stat-title mb-0">Total Entries</div>
                <i class="fa-solid fa-receipt text-warning"></i>
            </div>
            <div class="stat-value"><?= (int)$stats['total_entries'] ?></div>
            <div class="stat-subtext">Recorded transactions</div>
        </div>
    </div>

    <!-- Average Expense Per User -->
    <div class="col-sm-6 col-xl-2">
        <div class="sm-stat-card">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="stat-title mb-0">Avg / User</div>
                <i class="fa-solid fa-scale-balanced text-info"></i>
            </div>
            <div class="stat-value" style="font-size: 1.35rem;"><?= format_currency($stats['avg_per_user']) ?></div>
            <div class="stat-subtext">Fair share baseline</div>
        </div>
    </div>

    <!-- Pending Settlements -->
    <div class="col-sm-6 col-xl-2">
        <div class="sm-stat-card">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="stat-title mb-0">Pending Settles</div>
                <i class="fa-solid fa-clock text-danger"></i>
            </div>
            <div class="stat-value text-danger"><?= (int)$stats['pending_settlements_count'] ?></div>
            <div class="stat-subtext"><?= format_currency($stats['pending_settlements_amount']) ?> awaiting</div>
        </div>
    </div>
</div>

<!-- ========================================================
     Highest Spender Banner
     ======================================================== -->
<div class="p-3 mb-4 rounded-3 d-flex align-items-center justify-content-between border" style="background: linear-gradient(135deg, #f8fafc, #edf2f7);">
    <div class="d-flex align-items-center gap-3">
        <div class="p-2 rounded-circle bg-warning text-dark fs-5">
            <i class="fa-solid fa-crown"></i>
        </div>
        <div>
            <div class="fw-bold text-dark">Top Contributor</div>
            <div class="small text-muted">User with the highest contribution in your group:</div>
        </div>
    </div>
    <div class="text-end">
        <span class="fs-5 fw-bold text-primary"><?= e($stats['highest_user']['full_name']) ?></span>
        <div class="badge bg-primary-subtle text-primary fw-bold ms-2 fs-6">
            <?= format_currency($stats['highest_user']['user_total']) ?>
        </div>
    </div>
</div>

<!-- ========================================================
     2. Dashboard Analytics (Chart.js)
     ======================================================== -->
<div class="row g-4 mb-4">
    <!-- Monthly Expense Overview (Bar Chart) -->
    <div class="col-lg-6">
        <div class="sm-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-chart-column text-primary me-2"></i>Monthly Expense Overview
                </h6>
                <span class="badge bg-light text-muted border">Last 6 Months</span>
            </div>
            <div style="height: 260px; position: relative;">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Category-wise Expenses (Doughnut Chart) -->
    <div class="col-lg-6">
        <div class="sm-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-chart-pie text-success me-2"></i>Category-wise Distribution
                </h6>
                <span class="badge bg-light text-muted border">Breakdown</span>
            </div>
            <div style="height: 260px; position: relative;">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>

    <!-- User-wise Spending Comparison (Bar Chart) -->
    <div class="col-lg-6">
        <div class="sm-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-users-viewfinder text-info me-2"></i>User-wise Spending Comparison
                </h6>
                <span class="badge bg-light text-muted border">Active Members</span>
            </div>
            <div style="height: 260px; position: relative;">
                <canvas id="userComparisonChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Daily Expense Trends (Line Chart) -->
    <div class="col-lg-6">
        <div class="sm-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-chart-line text-warning me-2"></i>Daily Expense Trends
                </h6>
                <span class="badge bg-light text-muted border">Recent 14 Days</span>
            </div>
            <div style="height: 260px; position: relative;">
                <canvas id="dailyTrendsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
     3. Recent Expenses Table
     ======================================================== -->
<div class="sm-card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold text-dark mb-0">Recent Expenses</h5>
            <small class="text-muted">Latest entries submitted by members of this group</small>
        </div>
        <a href="<?= base_url('admin/expenses.php') ?>" class="btn btn-outline-primary btn-sm">
            View All Expenses <i class="fa-solid fa-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-custom">
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
                <?php if (empty($stats['recent_expenses'])): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-receipt fs-2 mb-2 d-block text-secondary"></i>
                            No expenses recorded yet in this group. Click "Record Expense" to add the first one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($stats['recent_expenses'] as $i => $exp): ?>
                        <tr>
                            <td class="text-muted small"><?= $i + 1 ?></td>
                            <td class="fw-bold text-dark"><?= e($exp['title']) ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar-sm" style="width: 26px; height: 26px; font-size: 0.75rem;">
                                        <?= strtoupper(substr($exp['user_name'], 0, 1)) ?>
                                    </div>
                                    <span><?= e($exp['user_name']) ?></span>
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
                                <span class="badge bg-secondary-subtle text-secondary border">
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

<!-- ========================================================
     Add Expense Modal (Admin Mode)
     ======================================================== -->
<?php
// Fetch active users under this admin for select dropdown
$uStmt = db()->prepare("SELECT user_id, full_name FROM users WHERE admin_id = ? AND status = 'active' ORDER BY full_name ASC");
$uStmt->execute([$adminId]);
$adminGroupUsers = $uStmt->fetchAll();
?>
<div class="modal fade" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="addExpenseModalLabel">
                    <i class="fa-solid fa-receipt me-2 text-primary"></i>Record Group Expense
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addExpenseForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">

                <div class="modal-body p-4">
                    <!-- Title -->
                    <div class="mb-3">
                        <label for="exp_title" class="form-label">Expense Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="exp_title" name="title" placeholder="e.g. Dinner, Apartment Rent, Groceries" required>
                    </div>

                    <!-- Paid By (Member Selector) -->
                    <div class="mb-3">
                        <label for="exp_user_id" class="form-label">Paid By (Member) <span class="text-danger">*</span></label>
                        <select class="form-select" id="exp_user_id" name="user_id" required>
                            <?php foreach ($adminGroupUsers as $agu): ?>
                                <option value="<?= (int)$agu['user_id'] ?>"><?= e($agu['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Amount & Category Grid -->
                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <label for="exp_amount" class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">₹</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="exp_amount" name="amount" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label for="exp_category" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="exp_category" name="category" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= e($cat['name']) ?>"><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Date, Time & Payment Method Grid -->
                    <div class="row g-2 mb-3">
                        <div class="col-sm-4">
                            <label for="exp_date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="exp_date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-sm-4">
                            <label for="exp_time" class="form-label">Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="exp_time" name="expense_time" value="<?= date('H:i') ?>" required>
                        </div>
                        <div class="col-sm-4">
                            <label for="exp_payment_method" class="form-label">Payment Method</label>
                            <select class="form-select" id="exp_payment_method" name="payment_method">
                                <option value="UPI" selected>UPI</option>
                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="Net Banking">Net Banking</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-2">
                        <label for="exp_description" class="form-label">Description / Notes</label>
                        <textarea class="form-control" id="exp_description" name="description" rows="2" placeholder="Optional details about this expense"></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom btn-sm d-flex align-items-center gap-2" id="saveExpenseBtn">
                        <i class="fa-solid fa-check"></i>
                        <span>Save Expense</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Prepare Chart.js datasets from PHP calculated data
$monthlyLabels = array_column($stats['monthly_expenses'], 'month_label');
$monthlyValues = array_column($stats['monthly_expenses'], 'monthly_total');

$categoryLabels = array_column($stats['category_expenses'], 'category');
$categoryValues = array_column($stats['category_expenses'], 'category_total');

$userLabels = array_column($stats['user_comparison'], 'full_name');
$userValues = array_column($stats['user_comparison'], 'total_spent');

$dailyLabels = array_column($stats['daily_trends'], 'day_label');
$dailyValues = array_column($stats['daily_trends'], 'daily_total');

$extraScripts = "
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Monthly Chart (Bar)
    const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
    new Chart(ctxMonthly, {
        type: 'bar',
        data: {
            labels: " . json_encode($monthlyLabels) . ",
            datasets: [{
                label: 'Monthly Total (₹)',
                data: " . json_encode($monthlyValues) . ",
                backgroundColor: 'rgba(79, 70, 229, 0.75)',
                borderColor: '#4f46e5',
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => '₹' + v } }
            }
        }
    });

    // 2. Category Chart (Doughnut)
    const ctxCat = document.getElementById('categoryChart').getContext('2d');
    new Chart(ctxCat, {
        type: 'doughnut',
        data: {
            labels: " . json_encode($categoryLabels) . ",
            datasets: [{
                data: " . json_encode($categoryValues) . ",
                backgroundColor: [
                    '#4f46e5', '#10b981', '#f59e0b', '#ef4444', 
                    '#3b82f6', '#8b5cf6', '#ec4899', '#06b6d4', '#64748b'
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } }
            }
        }
    });

    // 3. User Spending Comparison Chart (Bar)
    const ctxUser = document.getElementById('userComparisonChart').getContext('2d');
    new Chart(ctxUser, {
        type: 'bar',
        data: {
            labels: " . json_encode($userLabels) . ",
            datasets: [{
                label: 'Total Spent (₹)',
                data: " . json_encode($userValues) . ",
                backgroundColor: 'rgba(16, 185, 129, 0.75)',
                borderColor: '#10b981',
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => '₹' + v } }
            }
        }
    });

    // 4. Daily Trends Chart (Line)
    const ctxDaily = document.getElementById('dailyTrendsChart').getContext('2d');
    new Chart(ctxDaily, {
        type: 'line',
        data: {
            labels: " . json_encode($dailyLabels) . ",
            datasets: [{
                label: 'Daily Spending (₹)',
                data: " . json_encode($dailyValues) . ",
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                tension: 0.35,
                fill: true,
                pointRadius: 4,
                pointBackgroundColor: '#f59e0b'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => '₹' + v } }
            }
        }
    });

    // Handle Add Expense Form via AJAX
    document.getElementById('addExpenseForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = document.getElementById('saveExpenseBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class=\"spinner-border spinner-border-sm me-2\"></span>Saving...';

        const formData = new FormData(this);
        fetch('" . base_url('api/expenses.php') . "', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast(data.message, 'danger');
                btn.disabled = false;
                btn.innerHTML = '<i class=\"fa-solid fa-check me-2\"></i>Save Expense';
            }
        })
        .catch(err => {
            showToast('Error saving expense.', 'danger');
            btn.disabled = false;
            btn.innerHTML = '<i class=\"fa-solid fa-check me-2\"></i>Save Expense';
        });
    });
});

function triggerSyncSettlements() {
    const token = document.querySelector('input[name=\"csrf_token\"]').value;
    const form = new FormData();
    form.append('action', 'sync');
    form.append('csrf_token', token);

    fetch('" . base_url('api/settlements.php') . "', {
        method: 'POST',
        body: form
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 1200);
        } else {
            showToast(data.message, 'danger');
        }
    })
    .catch(() => showToast('Failed to sync settlements.', 'danger'));
}
</script>
";

require_once __DIR__ . '/../includes/footer.php';
?>

