<?php
// SplitMate User - Personal Dashboard

require_once __DIR__ . '/../includes/auth.php';
require_user();

$userId  = current_user_id();
$adminId = current_admin_id();

$stats = get_user_dashboard_stats($userId, $adminId);

$pageTitle = 'My Dashboard';
$currentPage = 'user_dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">My Dashboard</h3>
        <p class="text-muted mb-0 small">
            Welcome back, <strong class="text-dark"><?= e($_SESSION['auth_user']['name'] ?? 'Member') ?></strong>! Here is your personal financial standing.
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= base_url('user/add_expense.php') ?>" class="btn btn-primary-custom btn-sm d-flex align-items-center gap-2">
            <i class="fa-solid fa-plus-circle"></i>
            <span>Add New Expense</span>
        </a>
        <a href="<?= base_url('user/settlements.php') ?>" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2">
            <i class="fa-solid fa-scale-balanced"></i>
            <span>Who Owes Whom</span>
        </a>
    </div>
</div>

<!-- ========================================================
     User Dashboard Metric Cards (Prompt Requirement)
     ======================================================== -->
<div class="row g-3 mb-4">
    <!-- My Total Spending -->
    <div class="col-sm-6 col-xl-3">
        <div class="sm-stat-card">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="stat-title mb-0">My Total Spending</div>
                <div class="stat-icon-wrapper stat-icon-primary" style="width: 36px; height: 36px; font-size: 1rem;">
                    <i class="fa-solid fa-wallet"></i>
                </div>
            </div>
            <div class="stat-value text-dark"><?= format_currency($stats['my_total']) ?></div>
            <div class="stat-subtext"><?= $stats['my_count'] ?> expenses recorded by me</div>
        </div>
    </div>

    <!-- Group Total Spending -->
    <div class="col-sm-6 col-xl-3">
        <div class="sm-stat-card">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="stat-title mb-0">Group Total Spending</div>
                <div class="stat-icon-wrapper stat-icon-amber" style="width: 36px; height: 36px; font-size: 1rem;">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
            </div>
            <div class="stat-value text-dark"><?= format_currency($stats['group_total']) ?></div>
            <div class="stat-subtext">Cumulative group expenditures</div>
        </div>
    </div>

    <!-- My Fair Share -->
    <div class="col-sm-6 col-xl-3">
        <div class="sm-stat-card">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="stat-title mb-0">My Fair Share</div>
                <div class="stat-icon-wrapper stat-icon-primary" style="width: 36px; height: 36px; font-size: 1rem;">
                    <i class="fa-solid fa-divide"></i>
                </div>
            </div>
            <div class="stat-value text-primary"><?= format_currency($stats['my_fair_share']) ?></div>
            <div class="stat-subtext">Equal split target for you</div>
        </div>
    </div>

    <!-- My Current Balance -->
    <div class="col-sm-6 col-xl-3">
        <?php
        $myBal = (float)$stats['my_balance'];
        $isReceiving = ($myBal > 0);
        $isOwing = ($myBal < 0);
        ?>
        <div class="sm-stat-card" style="border-left: 4px solid <?= $isReceiving ? '#10b981' : ($isOwing ? '#ef4444' : '#64748b') ?>;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="stat-title mb-0">My Current Balance</div>
                <div class="stat-icon-wrapper <?= $isReceiving ? 'stat-icon-green' : ($isOwing ? 'stat-icon-red' : 'bg-light text-muted') ?>" style="width: 36px; height: 36px; font-size: 1rem;">
                    <i class="fa-solid <?= $isReceiving ? 'fa-arrow-down-left' : ($isOwing ? 'fa-arrow-up-right' : 'fa-check') ?>"></i>
                </div>
            </div>
            <div class="stat-value <?= $isReceiving ? 'text-success' : ($isOwing ? 'text-danger' : 'text-dark') ?>">
                <?= $isReceiving ? '+' : '' ?><?= format_currency($myBal) ?>
            </div>
            <div class="stat-subtext">
                <?php if ($isReceiving): ?>
                    <strong class="text-success">You will get back <?= format_currency($myBal) ?></strong>
                <?php elseif ($isOwing): ?>
                    <strong class="text-danger">You owe <?= format_currency(abs($myBal)) ?> to members</strong>
                <?php else: ?>
                    <span class="text-muted">You are all squared up!</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
     Actionable Settlements Banner (If you owe or are owed)
     ======================================================== -->
<?php if (!empty($stats['settlements'])): ?>
    <div class="sm-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="fa-solid fa-handshake text-primary me-2"></i>My Pending Settlements
                </h5>
                <small class="text-muted">Direct payment links where you are either the payer or the recipient</small>
            </div>
            <a href="<?= base_url('user/settlements.php') ?>" class="btn btn-outline-primary btn-sm">
                View Full Group Board <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th>Payer</th>
                        <th>Receiver</th>
                        <th>Amount</th>
                        <th>Your Role</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['settlements'] as $st): ?>
                        <?php 
                        $iAmPayer = ((int)$st['payer_id'] === $userId);
                        $isPaid = ($st['status'] === 'paid');
                        ?>
                        <tr>
                            <td class="<?= $iAmPayer ? 'fw-bold text-danger' : 'text-dark' ?>">
                                <?= e($st['payer_name']) ?> <?= $iAmPayer ? '(You)' : '' ?>
                            </td>
                            <td class="<?= !$iAmPayer ? 'fw-bold text-success' : 'text-dark' ?>">
                                <?= e($st['receiver_name']) ?> <?= !$iAmPayer ? '(You)' : '' ?>
                            </td>
                            <td class="fw-bold text-dark fs-6"><?= format_currency($st['amount']) ?></td>
                            <td>
                                <?php if ($iAmPayer): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                        <i class="fa-solid fa-arrow-up-right me-1"></i>You Pay
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="fa-solid fa-arrow-down-left me-1"></i>You Receive
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isPaid): ?>
                                    <span class="badge badge-custom badge-paid"><i class="fa-solid fa-check"></i> Paid</span>
                                <?php else: ?>
                                    <span class="badge badge-custom badge-pending"><i class="fa-solid fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if (!$isPaid): ?>
                                    <button type="button" class="btn btn-success-custom btn-sm" onclick="quickMarkPaid(<?= (int)$st['settlement_id'] ?>)">
                                        <i class="fa-solid fa-check me-1"></i> Mark Paid
                                    </button>
                                <?php else: ?>
                                    <small class="text-muted"><i class="fa-solid fa-lock"></i> Settled</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ========================================================
     Recent Personal Expenses
     ======================================================== -->
<div class="sm-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold text-dark mb-0">My Recent Expenses</h5>
            <small class="text-muted">Latest expense entries paid by you</small>
        </div>
        <a href="<?= base_url('user/my_expenses.php') ?>" class="btn btn-outline-secondary btn-sm">
            View All My Expenses <i class="fa-solid fa-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-custom">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Expense Title</th>
                    <th>Category</th>
                    <th>Amount</th>
                    <th>Date & Time</th>
                    <th>Method</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($stats['recent_expenses'])): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-receipt fs-2 mb-2 d-block text-secondary"></i>
                            You haven't added any expenses yet.
                            <div class="mt-2">
                                <a href="<?= base_url('user/add_expense.php') ?>" class="btn btn-primary-custom btn-sm">
                                    <i class="fa-solid fa-plus me-1"></i> Add Your First Expense
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($stats['recent_expenses'] as $idx => $exp): ?>
                        <tr>
                            <td class="text-muted small"><?= $idx + 1 ?></td>
                            <td class="fw-bold text-dark"><?= e($exp['title']) ?></td>
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
                                <span class="text-muted small text-truncate d-inline-block" style="max-width: 180px;" title="<?= e($exp['description']) ?>">
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

<?= csrf_field() ?>

<?php
$extraScripts = "
<script>
function quickMarkPaid(settlementId) {
    if (!confirm('Are you sure you want to confirm this payment?')) return;
    const token = document.querySelector('input[name=\"csrf_token\"]').value;
    const formData = new FormData();
    formData.append('action', 'mark_paid');
    formData.append('settlement_id', settlementId);
    formData.append('csrf_token', token);

    fetch('" . base_url('api/settlements.php') . "', {
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
        }
    })
    .catch(() => showToast('Failed to confirm payment.', 'danger'));
}
</script>
";

require_once __DIR__ . '/../includes/footer.php';
?>

