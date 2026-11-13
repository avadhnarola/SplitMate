<?php
// SplitMate User - Who Owes Whom (Settlements & Balance Flow)

require_once __DIR__ . '/../includes/auth.php';
require_user();

$userId  = current_user_id();
$adminId = current_admin_id();
$pdo = db();

// Auto-sync settlement instructions into DB so Mark Paid works correctly
sync_settlements_to_database($adminId);

// Calculate live greedy settlement matrix
$matrix = calculate_settlement_matrix($adminId);
$myBalance = $matrix['user_balances'][$userId] ?? null;

// Fetch all settlements for this admin group
$stmt = $pdo->prepare("
    SELECT s.*, 
           p.full_name as payer_name, p.email as payer_email,
           r.full_name as receiver_name, r.email as receiver_email
    FROM settlements s 
    JOIN users p ON s.payer_id = p.user_id 
    JOIN users r ON s.receiver_id = r.user_id 
    WHERE s.admin_id = ? 
    ORDER BY (s.status = 'pending') DESC, s.created_at DESC
");
$stmt->execute([$adminId]);
$settlements = $stmt->fetchAll();

$pageTitle = 'Who Owes Whom';
$currentPage = 'user_settlements';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">Who Owes Whom</h3>
        <p class="text-muted mb-0 small">
            Transparent group settlement matrix showing how debts are reconciled using the greedy algorithm
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-outline-success btn-sm d-flex align-items-center gap-2" onclick="exportTableToCSV('userSettlementsTable', 'group_settlement_status.csv')">
            <i class="fa-solid fa-file-csv"></i>
            <span>Export CSV</span>
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="printReport()">
            <i class="fa-solid fa-print"></i>
        </button>
    </div>
</div>

<!-- ========================================================
     My Personal Standing Highlight Card & Stat Cards
     ======================================================== -->
<?php if ($myBalance): ?>
    <?php
    $totalPaid = (float)($myBalance['total_paid'] ?? $myBalance['total_spent'] ?? 0);
    $totalOwed = (float)($myBalance['total_owed'] ?? 0);
    $bal = (float)$myBalance['net_balance'];
    $isReceiving = ($bal > 0);
    $isOwing = ($bal < 0);
    ?>
    <div class="p-4 mb-4 rounded-3 border shadow-sm sm-card" style="background: <?= $isReceiving ? '#ecfdf5' : ($isOwing ? '#fef2f2' : '#f8fafc') ?>; border-color: <?= $isReceiving ? '#a7f3d0' : ($isOwing ? '#fecaca' : '#e2e8f0') ?> !important;">
        <div class="row align-items-center g-3">
            <div class="col-md-7">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded-circle fs-3 <?= $isReceiving ? 'bg-success text-white' : ($isOwing ? 'bg-danger text-white' : 'bg-secondary text-white') ?>">
                        <i class="fa-solid <?= $isReceiving ? 'fa-arrow-down-left' : ($isOwing ? 'fa-arrow-up-right' : 'fa-check') ?>"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-1 <?= $isReceiving ? 'text-success' : ($isOwing ? 'text-danger' : 'text-dark') ?>">
                            <?php if ($isReceiving): ?>
                                You are owed <?= format_currency($bal) ?>
                            <?php elseif ($isOwing): ?>
                                You owe <?= format_currency(abs($bal)) ?>
                            <?php else: ?>
                                You are all settled up! (₹0.00)
                            <?php endif; ?>
                        </h4>
                        <div class="text-muted small">
                            <i class="fa-solid fa-circle-info me-1 text-primary"></i>Your balance is based on what you paid vs. your assigned share of each expense.
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-5 text-md-end">
                <div class="small text-muted mb-1">Group Total Expenses:</div>
                <div class="fs-4 fw-bold text-dark"><?= format_currency($matrix['total_group_expense']) ?></div>
                <small class="text-muted">Across <?= $matrix['active_members_count'] ?> active members</small>
            </div>
        </div>
    </div>

    <!-- Metric Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="sm-stat-card">
                <div class="stat-title">Total Paid</div>
                <div class="stat-value text-primary"><?= format_currency($totalPaid) ?></div>
                <div class="stat-subtext">Direct out-of-pocket expenses</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="sm-stat-card">
                <div class="stat-title">My Share of Expenses</div>
                <div class="stat-value text-warning"><?= format_currency($totalOwed) ?></div>
                <div class="stat-subtext">Total owed across your participated expenses</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="sm-stat-card">
                <div class="stat-title">Net Balance</div>
                <div class="stat-value <?= $isReceiving ? 'text-success' : ($isOwing ? 'text-danger' : 'text-dark') ?>">
                    <?= ($bal > 0 ? '+' : '') . format_currency($bal) ?>
                </div>
                <div class="stat-subtext"><?= $isReceiving ? 'Receivable from group' : ($isOwing ? 'Payable to group' : 'All balanced') ?></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="sm-stat-card">
                <div class="stat-title">Group Total</div>
                <div class="stat-value text-dark"><?= format_currency($matrix['total_group_expense']) ?></div>
                <div class="stat-subtext">Active group total expenditure</div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ========================================================
     Algorithmic Transfer Instructions
     ======================================================== -->
<div class="sm-card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold text-dark mb-0">
                <i class="fa-solid fa-route text-primary me-2"></i>Optimized Group Settlement Flow
            </h5>
            <small class="text-muted">Greedy algorithm solution minimizing total number of peer-to-peer transfers</small>
        </div>
    </div>

    <?php if (empty($matrix['settlement_instructions'])): ?>
        <div class="text-center py-4 text-muted">
            <i class="fa-solid fa-circle-check fs-2 text-success mb-2 d-block"></i>
            All accounts are fully settled. No pending debt transfers required!
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($matrix['settlement_instructions'] as $idx => $inst): ?>
                <?php
                $isMePayer = ((int)$inst['payer_id'] === $userId);
                $isMeReceiver = ((int)$inst['receiver_id'] === $userId);
                $involvesMe = ($isMePayer || $isMeReceiver);
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="settle-card h-100 flex-column align-items-stretch <?= $involvesMe ? 'border-primary shadow-sm' : '' ?>" style="<?= $involvesMe ? 'background: #fdfefe; border-width: 2px;' : '' ?>">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-secondary-subtle text-secondary small">Step #<?= $idx + 1 ?></span>
                            <span class="fw-bold fs-5 text-dark"><?= format_currency($inst['amount']) ?></span>
                        </div>

                        <div class="d-flex align-items-center justify-content-between my-2 p-2 bg-light rounded border">
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-avatar-sm" style="background: #ef4444; width: 26px; height: 26px; font-size: 0.7rem;">
                                    <?= strtoupper(substr($inst['payer_name'], 0, 1)) ?>
                                </div>
                                <div class="fw-bold <?= $isMePayer ? 'text-danger' : 'text-dark' ?> text-truncate" style="max-width: 90px;">
                                    <?= e($inst['payer_name']) ?> <?= $isMePayer ? '(You)' : '' ?>
                                </div>
                            </div>
                            <div class="text-muted"><i class="fa-solid fa-arrow-right settlement-arrow"></i></div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-avatar-sm" style="background: #10b981; width: 26px; height: 26px; font-size: 0.7rem;">
                                    <?= strtoupper(substr($inst['receiver_name'], 0, 1)) ?>
                                </div>
                                <div class="fw-bold <?= $isMeReceiver ? 'text-success' : 'text-dark' ?> text-truncate" style="max-width: 90px;">
                                    <?= e($inst['receiver_name']) ?> <?= $isMeReceiver ? '(You)' : '' ?>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-2 small text-muted">
                            <?php if ($isMePayer): ?>
                                <strong class="text-danger">Action required:</strong> Send <?= format_currency($inst['amount']) ?> to <?= e($inst['receiver_name']) ?>
                            <?php elseif ($isMeReceiver): ?>
                                <strong class="text-success">Receivable:</strong> You will receive <?= format_currency($inst['amount']) ?> from <?= e($inst['payer_name']) ?>
                            <?php else: ?>
                                <?= e($inst['payer_name']) ?> pays <?= e($inst['receiver_name']) ?> <?= format_currency($inst['amount']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ========================================================
     Settlement Status Table
     ======================================================== -->
<div class="sm-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold text-dark mb-0">Settlement Ledger & Payment Status</h5>
            <small class="text-muted">Recorded group transfers and confirmation log</small>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-custom" id="userSettlementsTable">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Payer</th>
                    <th>Receiver</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Confirmed By</th>
                    <th>Payment Time</th>
                    <th class="text-end no-export" style="width: 140px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($settlements)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            No settlement entries recorded yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($settlements as $i => $row): ?>
                        <?php 
                        $isMePayer = ((int)$row['payer_id'] === $userId);
                        $isMeReceiver = ((int)$row['receiver_id'] === $userId);
                        $canConfirm = ($isMePayer || $isMeReceiver);
                        $isPaid = ($row['status'] === 'paid');
                        ?>
                        <tr class="<?= ($canConfirm && !$isPaid) ? 'table-warning-subtle' : '' ?>">
                            <td class="text-muted small"><?= $i + 1 ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar-sm" style="background: #ef4444; width: 24px; height: 24px; font-size: 0.7rem;">
                                        <?= strtoupper(substr($row['payer_name'], 0, 1)) ?>
                                    </div>
                                    <span class="<?= $isMePayer ? 'fw-bold text-danger' : 'text-dark' ?>">
                                        <?= e($row['payer_name']) ?> <?= $isMePayer ? '(You)' : '' ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar-sm" style="background: #10b981; width: 24px; height: 24px; font-size: 0.7rem;">
                                        <?= strtoupper(substr($row['receiver_name'], 0, 1)) ?>
                                    </div>
                                    <span class="<?= $isMeReceiver ? 'fw-bold text-success' : 'text-dark' ?>">
                                        <?= e($row['receiver_name']) ?> <?= $isMeReceiver ? '(You)' : '' ?>
                                    </span>
                                </div>
                            </td>
                            <td class="fw-bold text-dark fs-6"><?= format_currency($row['amount']) ?></td>
                            <td>
                                <?php if ($isPaid): ?>
                                    <span class="badge badge-custom badge-paid"><i class="fa-solid fa-check"></i> Paid</span>
                                <?php else: ?>
                                    <span class="badge badge-custom badge-pending"><i class="fa-solid fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= !empty($row['confirmed_by']) ? '<small class="fw-semibold text-dark">' . e($row['confirmed_by']) . '</small>' : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td>
                                <?= !empty($row['paid_at']) ? format_date($row['paid_at']) . ' <small class="text-muted">' . format_time($row['paid_at']) . '</small>' : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td class="text-end no-export">
                                <?php if (!$isPaid): ?>
                                    <?php if ($canConfirm): ?>
                                        <button type="button" class="btn btn-sm btn-success-custom" onclick="openMarkPaidModal(<?= (int)$row['settlement_id'] ?>, '<?= e(addslashes($row['payer_name'])) ?>', '<?= e(addslashes($row['receiver_name'])) ?>', '<?= format_currency($row['amount']) ?>')">
                                            <i class="fa-solid fa-check me-1"></i> Mark Paid
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Pending</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-light text-success border border-success-subtle">
                                        <i class="fa-solid fa-lock me-1"></i> Completed
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal to Confirm Payment -->
<div class="modal fade" id="markPaidModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-hand-holding-dollar me-2"></i>Confirm Settlement Payment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="markPaidForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_paid">
                <input type="hidden" name="settlement_id" id="paid_settlement_id">

                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded border text-center mb-3">
                        <div class="small text-muted mb-1">Transfer Details</div>
                        <div class="fs-5 fw-bold text-dark">
                            <span id="modalPayerName" class="text-danger"></span> &rarr; <span id="modalReceiverName" class="text-success"></span>
                        </div>
                        <div class="fs-3 fw-bold text-primary mt-1" id="modalAmount"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Method Used</label>
                        <select class="form-select" name="payment_method">
                            <option value="UPI" selected>UPI (GPay / PhonePe / Paytm)</option>
                            <option value="Cash">Cash Handover</option>
                            <option value="Bank Transfer">Bank Transfer (IMPS / NEFT)</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Transaction Reference / Note (Optional)</label>
                        <input type="text" class="form-control" name="notes" placeholder="e.g. UPI Ref # or Handed in person">
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success-custom btn-sm" id="confirmPaymentBtn">
                        <i class="fa-solid fa-check me-1"></i> Confirm Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = "
<script>
function openMarkPaidModal(id, payer, receiver, amount) {
    document.getElementById('paid_settlement_id').value = id;
    document.getElementById('modalPayerName').innerText = payer;
    document.getElementById('modalReceiverName').innerText = receiver;
    document.getElementById('modalAmount').innerText = amount;
    const modal = new bootstrap.Modal(document.getElementById('markPaidModal'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('markPaidForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = document.getElementById('confirmPaymentBtn');
        btn.disabled = true;

        const formData = new FormData(this);
        fetch('" . base_url('api/settlements.php') . "', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('markPaidModal'));
                if (modal) modal.hide();
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast(data.message, 'danger');
                btn.disabled = false;
            }
        })
        .catch(() => {
            showToast('Failed to record payment.', 'danger');
            btn.disabled = false;
        });
    });
});
</script>
";

require_once __DIR__ . '/../includes/footer.php';
?>

