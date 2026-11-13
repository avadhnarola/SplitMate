<?php
// SplitMate User - Personal Dashboard

require_once __DIR__ . '/../includes/auth.php';
require_user();

$userId  = current_user_id();
$adminId = current_admin_id();

// Auto-sync settlement instructions into DB so Mark Paid works immediately.
// This is lightweight: runs the greedy algorithm and upserts only changed rows.
sync_settlements_to_database($adminId);

$stats = get_user_dashboard_stats($userId, $adminId);
$matrix = calculate_settlement_matrix($adminId);
$userBal = $matrix['user_balances'][$userId] ?? null;
$totalPaid = $userBal['total_paid'] ?? $stats['my_total'];
$totalOwed = $userBal['total_owed'] ?? $stats['my_fair_share'];
$myBal = (float)($userBal['net_balance'] ?? $stats['my_balance']);
$isReceiving = ($myBal > 0);
$isOwing = ($myBal < 0);

$pageTitle = 'My Dashboard';
$currentPage = 'user_dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ========================================================
     Modern Page Hero Banner
     ======================================================== -->
<div class="page-hero-banner">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 position-relative" style="z-index: 2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="hero-glass-pill">
                    <i class="fa-solid fa-sparkles text-warning"></i>
                    <span>Personal Expense Hub</span>
                </span>
                <?php if ($isReceiving): ?>
                    <span class="badge bg-success-subtle text-success border border-success px-2 py-1">
                        <i class="fa-solid fa-arrow-down-left me-1"></i>Receivable +<?= format_currency($myBal) ?>
                    </span>
                <?php elseif ($isOwing): ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1">
                        <i class="fa-solid fa-arrow-up-right me-1"></i>Payable <?= format_currency(abs($myBal)) ?>
                    </span>
                <?php else: ?>
                    <span class="badge bg-light text-dark px-2 py-1">
                        <i class="fa-solid fa-check me-1"></i>All Squared Up
                    </span>
                <?php endif; ?>
            </div>
            <h3 class="display-6 fw-bold mb-1">
                Welcome, <?= e($_SESSION['auth_user']['name'] ?? 'Member') ?>! 👋
            </h3>
            <p class="mb-0 small text-white-50">
                Track personal expenditures, participate in selective group splits, and settle balances instantly with greedy routing.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?= base_url('user/add_expense.php') ?>" class="btn btn-hero-white btn-sm px-3 py-2 d-flex align-items-center gap-2">
                <i class="fa-solid fa-plus-circle text-primary"></i>
                <span>Add New Expense</span>
            </a>
            <a href="<?= base_url('user/settlements.php') ?>" class="btn btn-hero-glass btn-sm px-3 py-2 d-flex align-items-center gap-2">
                <i class="fa-solid fa-scale-balanced"></i>
                <span>Who Owes Whom</span>
            </a>
        </div>
    </div>
</div>

<!-- ========================================================
     User Dashboard Metric Cards (Selective Splitting Model)
     ======================================================== -->
<div class="row g-3 mb-4">
    <!-- Total You Paid -->
    <div class="col-sm-6 col-xl-3">
        <div class="sm-stat-card" style="border-top: 3px solid #4f46e5;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="stat-title mb-0">Total You Paid</div>
                <div class="stat-icon-wrapper stat-icon-primary" style="width: 38px; height: 38px; font-size: 1rem;">
                    <i class="fa-solid fa-wallet"></i>
                </div>
            </div>
            <div class="stat-value text-dark"><?= format_currency($totalPaid) ?></div>
            <div class="stat-subtext text-muted small">
                <i class="fa-solid fa-receipt text-primary me-1"></i><?= $stats['my_count'] ?> expenses paid by you
            </div>
        </div>
    </div>

    <!-- Total You Owe (Assigned Shares) -->
    <div class="col-sm-6 col-xl-3">
        <div class="sm-stat-card" style="border-top: 3px solid #f59e0b;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="stat-title mb-0">Total You Owe</div>
                <div class="stat-icon-wrapper stat-icon-amber" style="width: 38px; height: 38px; font-size: 1rem;">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
            </div>
            <div class="stat-value text-warning"><?= format_currency($totalOwed) ?></div>
            <div class="stat-subtext text-muted small">
                <i class="fa-solid fa-users text-warning me-1"></i>Your share across all expenses
            </div>
        </div>
    </div>

    <!-- Group Total Spending -->
    <div class="col-sm-6 col-xl-3">
        <div class="sm-stat-card" style="border-top: 3px solid #06b6d4;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="stat-title mb-0">Group Total Spending</div>
                <div class="stat-icon-wrapper" style="width: 38px; height: 38px; font-size: 1rem; background: #ecfeff; color: #0891b2;">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
            </div>
            <div class="stat-value text-dark"><?= format_currency($stats['group_total']) ?></div>
            <div class="stat-subtext text-muted small">
                <i class="fa-solid fa-building-columns text-info me-1"></i>Cumulative group ledger
            </div>
        </div>
    </div>

    <!-- My Current Balance -->
    <div class="col-sm-6 col-xl-3">
        <div class="sm-stat-card" style="border-top: 3px solid <?= $isReceiving ? '#10b981' : ($isOwing ? '#ef4444' : '#64748b') ?>;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="stat-title mb-0">Net Settlement Balance</div>
                <div class="stat-icon-wrapper <?= $isReceiving ? 'stat-icon-green' : ($isOwing ? 'stat-icon-red' : 'bg-light text-muted') ?>" style="width: 38px; height: 38px; font-size: 1rem;">
                    <i class="fa-solid <?= $isReceiving ? 'fa-arrow-down-left' : ($isOwing ? 'fa-arrow-up-right' : 'fa-check') ?>"></i>
                </div>
            </div>
            <div class="stat-value <?= $isReceiving ? 'text-success' : ($isOwing ? 'text-danger' : 'text-dark') ?>">
                <?= $isReceiving ? '+' : '' ?><?= format_currency($myBal) ?>
            </div>
            <div class="stat-subtext">
                <?php if ($isReceiving): ?>
                    <strong class="text-success small"><i class="fa-solid fa-circle-check me-1"></i>You will get back <?= format_currency($myBal) ?></strong>
                <?php elseif ($isOwing): ?>
                    <strong class="text-danger small"><i class="fa-solid fa-circle-exclamation me-1"></i>You owe <?= format_currency(abs($myBal)) ?></strong>
                <?php else: ?>
                    <span class="text-muted small"><i class="fa-solid fa-check me-1"></i>All squared up</span>
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
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th>Payer</th>
                        <th style="width: 30px;"></th>
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
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width: 32px; height: 32px; background: <?= $iAmPayer ? '#ef4444' : '#64748b' ?>; font-size: 0.75rem;">
                                        <?= strtoupper(substr($st['payer_name'], 0, 1)) ?>
                                    </div>
                                    <span class="<?= $iAmPayer ? 'fw-bold text-danger' : 'text-dark' ?>">
                                        <?= e($st['payer_name']) ?> <?= $iAmPayer ? '<span class="badge bg-danger-subtle text-danger ms-1">You</span>' : '' ?>
                                    </span>
                                </div>
                            </td>
                            <td class="text-center text-muted">
                                <i class="fa-solid fa-arrow-right-long text-secondary"></i>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width: 32px; height: 32px; background: <?= !$iAmPayer ? '#10b981' : '#64748b' ?>; font-size: 0.75rem;">
                                        <?= strtoupper(substr($st['receiver_name'], 0, 1)) ?>
                                    </div>
                                    <span class="<?= !$iAmPayer ? 'fw-bold text-success' : 'text-dark' ?>">
                                        <?= e($st['receiver_name']) ?> <?= !$iAmPayer ? '<span class="badge bg-success-subtle text-success ms-1">You</span>' : '' ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="fw-bold text-dark fs-6"><?= format_currency($st['amount']) ?></span>
                            </td>
                            <td>
                                <?php if ($iAmPayer): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                        <i class="fa-solid fa-arrow-up-right me-1"></i>You Pay
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
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
                                    <button type="button" class="btn btn-success-custom btn-sm px-3 shadow-sm" onclick="quickMarkPaid(<?= (int)$st['settlement_id'] ?>)">
                                        <i class="fa-solid fa-check me-1"></i> Mark Paid
                                    </button>
                                <?php else: ?>
                                    <small class="text-muted"><i class="fa-solid fa-lock me-1"></i>Settled</small>
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
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Expense Title</th>
                    <th>Category</th>
                    <th>Split Mode</th>
                    <th>Total Amount</th>
                    <th>Date & Time</th>
                    <th>Method</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($stats['recent_expenses'])): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-receipt fs-1 mb-2 d-block text-secondary opacity-50"></i>
                            <div class="fw-semibold">You haven't added any expenses yet.</div>
                            <div class="mt-2">
                                <a href="<?= base_url('user/add_expense.php') ?>" class="btn btn-primary-custom btn-sm px-3">
                                    <i class="fa-solid fa-plus me-1"></i> Add Your First Expense
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($stats['recent_expenses'] as $idx => $exp): ?>
                        <tr>
                            <td class="text-muted small"><?= $idx + 1 ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($exp['title']) ?></div>
                                <?php if (!empty($exp['description'])): ?>
                                    <small class="text-muted text-truncate d-inline-block" style="max-width: 200px;"><?= e($exp['description']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1">
                                    <i class="fa-solid fa-tag me-1 text-primary"></i><?= e($exp['category']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (($exp['split_type'] ?? 'everyone') === 'selected'): ?>
                                    <span class="badge-selected">
                                        <i class="fa-solid fa-user-check me-1"></i>Selected
                                    </span>
                                <?php else: ?>
                                    <span class="badge-everyone">
                                        <i class="fa-solid fa-globe me-1"></i>Everyone
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold text-dark fs-6"><?= format_currency($exp['amount']) ?></td>
                            <td>
                                <div><?= format_date($exp['expense_date']) ?></div>
                                <small class="text-muted" style="font-size: 0.72rem;"><?= format_time($exp['expense_time']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary px-2 py-1">
                                    <?= e($exp['payment_method']) ?>
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
