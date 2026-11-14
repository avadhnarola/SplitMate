<?php
// SplitMate Admin - Smart Settlement Management Engine

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$adminId = current_admin_id();
$pdo = db();

// Period filter
$periodStart = trim($_GET['period_start'] ?? '');
$periodEnd   = trim($_GET['period_end'] ?? '');

// Auto-sync settlements into DB on every page load so the table is always current
sync_settlements_to_database(
    $adminId,
    !empty($periodStart) ? $periodStart : null,
    !empty($periodEnd)   ? $periodEnd   : null
);

// Calculate live greedy settlement matrix
$matrix = calculate_settlement_matrix($adminId, !empty($periodStart) ? $periodStart : null, !empty($periodEnd) ? $periodEnd : null);

// Fetch settlements from database table (both pending & paid)
$settleSql = "SELECT s.*, 
                     p.full_name as payer_name, p.email as payer_email,
                     r.full_name as receiver_name, r.email as receiver_email
              FROM settlements s 
              JOIN users p ON s.payer_id = p.user_id 
              JOIN users r ON s.receiver_id = r.user_id 
              WHERE s.admin_id = ?";
$params = [$adminId];

if (!empty($periodStart) && !empty($periodEnd)) {
    $settleSql .= " AND s.created_at BETWEEN ? AND ?";
    $params[] = $periodStart . ' 00:00:00';
    $params[] = $periodEnd . ' 23:59:59';
}

$settleSql .= " ORDER BY (s.status = 'pending') DESC, s.created_at DESC";
$stmt = $pdo->prepare($settleSql);
$stmt->execute($params);
$settlementRecords = $stmt->fetchAll();

// Separate pending and paid
$pendingSettlements = [];
$paidSettlements = [];
foreach ($settlementRecords as $row) {
    if ($row['status'] === 'pending') {
        $pendingSettlements[] = $row;
    } else {
        $paidSettlements[] = $row;
    }
}

$pageTitle = 'Settlement Management';
$currentPage = 'admin_settlements';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">Smart Settlement System</h3>
        <p class="text-muted mb-0 small">
            Greedy Debt Minimization Algorithm automatically balancing equal shares across active members
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-primary-custom btn-sm d-flex align-items-center gap-2" onclick="recalculateAndSync()">
            <i class="fa-solid fa-arrows-rotate"></i>
            <span>Recalculate & Sync</span>
        </button>
        <button type="button" class="btn btn-outline-success btn-sm d-flex align-items-center gap-2" onclick="exportTableToCSV('settlementsTable', 'settlements_ledger.csv')">
            <i class="fa-solid fa-file-csv"></i>
            <span>Export CSV</span>
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="printReport()">
            <i class="fa-solid fa-print"></i>
        </button>
    </div>
</div>

<!-- ========================================================
     Settlement Metric Cards (Prompt Requirement)
     ======================================================== -->
<div class="row g-3 mb-4">
    <!-- Total Group Expense -->
    <div class="col-sm-6 col-xl">
        <div class="sm-stat-card">
            <div class="stat-title">Total Group Expense</div>
            <div class="stat-value text-dark"><?= format_currency($matrix['total_group_expense']) ?></div>
            <div class="stat-subtext">Sum of active expenses</div>
        </div>
    </div>

    <!-- Per Person Fair Share / Average Share -->
    <div class="col-sm-6 col-xl">
        <div class="sm-stat-card">
            <div class="stat-title">Avg Share Per Person</div>
            <div class="stat-value text-primary"><?= format_currency($matrix['fair_share']) ?></div>
            <div class="stat-subtext">Across <?= $matrix['active_members_count'] ?> members (participant splits apply)</div>
        </div>
    </div>

    <!-- Total Amount Receivable -->
    <div class="col-sm-6 col-xl">
        <div class="sm-stat-card">
            <div class="stat-title">Total Receivable</div>
            <div class="stat-value text-success"><?= format_currency($matrix['total_receivable']) ?></div>
            <div class="stat-subtext">Due to creditors</div>
        </div>
    </div>

    <!-- Total Amount Payable -->
    <div class="col-sm-6 col-xl">
        <div class="sm-stat-card">
            <div class="stat-title">Total Payable</div>
            <div class="stat-value text-danger"><?= format_currency($matrix['total_payable']) ?></div>
            <div class="stat-subtext">Owed by debtors</div>
        </div>
    </div>

    <!-- Number of Pending Payments -->
    <div class="col-sm-6 col-xl">
        <div class="sm-stat-card">
            <div class="stat-title">Pending Payments</div>
            <div class="stat-value text-warning"><?= count($pendingSettlements) ?></div>
            <div class="stat-subtext">Transactions pending</div>
        </div>
    </div>
</div>

<!-- ========================================================
     Member Balances (Creditors vs Debtors)
     ======================================================== -->
<div class="row g-3 mb-4">
    <!-- Creditors (To Receive) -->
    <div class="col-md-6">
        <div class="sm-card p-3 h-100 border-success-subtle">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold mb-0 text-success">
                    <i class="fa-solid fa-circle-arrow-down me-2"></i>Creditors (Should Receive Money)
                </h6>
                <span class="badge bg-success-subtle text-success">Positive Balance</span>
            </div>
            <div class="list-group list-group-flush">
                <?php
                $hasCreditors = false;
                foreach ($matrix['user_balances'] as $b) {
                    if ($b['net_balance'] > 0) {
                        $hasCreditors = true;
                        ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-avatar-sm" style="background: #10b981; width: 30px; height: 30px; font-size: 0.75rem;">
                                    <?= strtoupper(substr($b['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?= e($b['full_name']) ?></div>
                                    <small class="text-muted">
                                        Paid: <?= format_currency($b['total_paid'] ?? $b['total_spent']) ?> | Share: <?= format_currency($b['total_owed'] ?? 0) ?>
                                        <?php if (!empty($b['settled_received'])): ?>
                                            <span class="text-success ms-1">• <?= format_currency($b['settled_received']) ?> received</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success">+<?= format_currency($b['net_balance']) ?></div>
                                <small class="text-muted">Receivable</small>
                            </div>
                        </div>
                        <?php
                    }
                }
                if (!$hasCreditors): ?>
                    <div class="text-muted small py-3 text-center">No creditors at this time. Group is balanced.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Debtors (To Pay) -->
    <div class="col-md-6">
        <div class="sm-card p-3 h-100 border-danger-subtle">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold mb-0 text-danger">
                    <i class="fa-solid fa-circle-arrow-up me-2"></i>Debtors (Need to Pay Money)
                </h6>
                <span class="badge bg-danger-subtle text-danger">Negative Balance</span>
            </div>
            <div class="list-group list-group-flush">
                <?php
                $hasDebtors = false;
                foreach ($matrix['user_balances'] as $b) {
                    if ($b['net_balance'] < 0) {
                        $hasDebtors = true;
                        ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-avatar-sm" style="background: #ef4444; width: 30px; height: 30px; font-size: 0.75rem;">
                                    <?= strtoupper(substr($b['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?= e($b['full_name']) ?></div>
                                    <small class="text-muted">
                                        Paid: <?= format_currency($b['total_paid'] ?? $b['total_spent']) ?> | Share: <?= format_currency($b['total_owed'] ?? 0) ?>
                                        <?php if (!empty($b['settled_paid'])): ?>
                                            <span class="text-info ms-1">• <?= format_currency($b['settled_paid']) ?> settled</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-danger">-<?= format_currency(abs($b['net_balance'])) ?></div>
                                <small class="text-muted">Payable</small>
                            </div>
                        </div>
                        <?php
                    }
                }
                if (!$hasDebtors): ?>
                    <div class="text-muted small py-3 text-center">No outstanding debts in this group!</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
     User Balances Breakdown Table (Participant-Based)
     ======================================================== -->
<div class="sm-card p-4 mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h5 class="fw-bold text-dark mb-0">
                <i class="fa-solid fa-scale-balanced text-primary me-2"></i>Member Balances Breakdown
            </h5>
            <small class="text-muted">
                Transparent participant accounting showing what each member paid vs. their assigned shares
            </small>
        </div>
        <span class="badge bg-light text-muted border py-2 px-3" data-bs-toggle="tooltip" title="Balance = Total Paid − Total Share Assigned Across All Participated Expenses">
            <i class="fa-solid fa-circle-info text-primary me-1"></i> Balance = Total Paid − Total Share Assigned Across All Participated Expenses
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-custom mb-0" id="userBalancesTable">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Member</th>
                    <th>Expenses Paid</th>
                    <th>Assigned Share</th>
                    <th>Settled Transferred</th>
                    <th>
                        <span data-bs-toggle="tooltip" data-bs-placement="top" title="Net Balance = (Expenses Paid − Assigned Share) + Settled Paid − Settled Received" style="cursor: help; border-bottom: 1px dashed #64748b;">
                            Net Balance <i class="fa-solid fa-circle-question small text-muted"></i>
                        </span>
                    </th>
                    <th class="text-end">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($matrix['user_balances'])): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No active members found.</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $uIdx = 1;
                    foreach ($matrix['user_balances'] as $u): 
                        $net = (float)$u['net_balance'];
                        $isCred = ($net > 0.005);
                        $isDebt = ($net < -0.005);
                        $isZero = (!$isCred && !$isDebt);
                        $paid = (float)($u['total_paid'] ?? $u['total_spent'] ?? 0);
                        $owed = (float)($u['total_owed'] ?? 0);
                        $settledPaid = (float)($u['settled_paid'] ?? 0);
                        $settledRecv = (float)($u['settled_received'] ?? 0);
                    ?>
                        <tr>
                            <td class="text-muted small"><?= $uIdx++ ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar-sm" style="background: <?= $isCred ? '#10b981' : ($isDebt ? '#ef4444' : '#64748b') ?>; width: 28px; height: 28px; font-size: 0.72rem;">
                                        <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?= e($u['full_name']) ?></div>
                                        <small class="text-muted"><?= e($u['email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td class="fw-semibold text-dark"><?= format_currency($paid) ?></td>
                            <td class="fw-semibold text-muted"><?= format_currency($owed) ?></td>
                            <td>
                                <?php if ($settledPaid > 0): ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1" data-bs-toggle="tooltip" title="Money paid to settle debts">
                                        <i class="fa-solid fa-arrow-up-right me-1"></i><?= format_currency($settledPaid) ?> Paid
                                    </span>
                                <?php elseif ($settledRecv > 0): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" data-bs-toggle="tooltip" title="Money received from debtors">
                                        <i class="fa-solid fa-arrow-down-left me-1"></i><?= format_currency($settledRecv) ?> Recv
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="fw-bold <?= $isCred ? 'text-success' : ($isDebt ? 'text-danger' : 'text-secondary') ?>" data-bs-toggle="tooltip" title="Remaining unsettled balance">
                                    <?= ($isCred ? '+' : '') . format_currency($net) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php if ($isCred): ?>
                                    <span class="badge bg-success-subtle text-success">
                                        <i class="fa-solid fa-arrow-down-left me-1"></i> Creditor (Receives)
                                    </span>
                                <?php elseif ($isDebt): ?>
                                    <span class="badge bg-danger-subtle text-danger">
                                        <i class="fa-solid fa-arrow-up-right me-1"></i> Debtor (Pays)
                                    </span>
                                <?php else: ?>
                                    <?php if ($settledPaid > 0 || $settledRecv > 0): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="fa-solid fa-check-double me-1"></i> Settled Up
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary">
                                            <i class="fa-solid fa-check me-1"></i> Balanced
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================================
     Algorithmic Settlement Instructions Cards
     ======================================================== -->
<div class="sm-card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold text-dark mb-0">
                <i class="fa-solid fa-route text-primary me-2"></i>Optimized Settlement Flow
            </h5>
            <small class="text-muted">Generated by Greedy Matching Algorithm (Minimizing Total Inter-member Transactions)</small>
        </div>
        <span class="badge bg-primary px-3 py-2">
            <?= count($matrix['settlement_instructions']) ?> Optimal Transfers
        </span>
    </div>

    <?php if (empty($matrix['settlement_instructions'])): ?>
        <div class="text-center py-4 text-muted">
            <i class="fa-solid fa-handshake-simple fs-2 text-success mb-2 d-block"></i>
            All accounts are fully settled! No pending debt transfers required.
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($matrix['settlement_instructions'] as $idx => $inst): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="settle-card h-100 flex-column align-items-stretch">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-secondary-subtle text-secondary small">Step #<?= $idx + 1 ?></span>
                            <span class="fw-bold fs-5 text-dark"><?= format_currency($inst['amount']) ?></span>
                        </div>

                        <div class="d-flex align-items-center justify-content-between my-2 p-2 bg-light rounded border">
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-avatar-sm" style="background: #ef4444; width: 26px; height: 26px; font-size: 0.7rem;">
                                    <?= strtoupper(substr($inst['payer_name'], 0, 1)) ?>
                                </div>
                                <div class="fw-bold text-dark text-truncate" style="max-width: 90px;"><?= e($inst['payer_name']) ?></div>
                            </div>
                            <div class="text-muted"><i class="fa-solid fa-arrow-right settlement-arrow"></i></div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-avatar-sm" style="background: #10b981; width: 26px; height: 26px; font-size: 0.7rem;">
                                    <?= strtoupper(substr($inst['receiver_name'], 0, 1)) ?>
                                </div>
                                <div class="fw-bold text-dark text-truncate" style="max-width: 90px;"><?= e($inst['receiver_name']) ?></div>
                            </div>
                        </div>

                        <div class="text-center mt-2 small text-muted">
                            <strong><?= e($inst['payer_name']) ?></strong> pays <strong><?= e($inst['receiver_name']) ?></strong> <?= format_currency($inst['amount']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ========================================================
     Database Settlement Records & Payment Status Table
     ======================================================== -->
<div class="sm-card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold text-dark mb-0">Recorded Settlement Transactions</h5>
            <small class="text-muted">Track payments, verification timestamps, and confirmer details</small>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-custom" id="settlementsTable">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Payer (Who Pays)</th>
                    <th>Receiver (Who Receives)</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Confirmed By</th>
                    <th>Paid At</th>
                    <th class="text-end no-export" style="width: 140px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($settlementRecords)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            No settlement entries recorded yet. Click "Recalculate & Sync" to generate from current expenses.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($settlementRecords as $i => $row): ?>
                        <tr>
                            <td class="text-muted small"><?= $i + 1 ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar-sm" style="background: #ef4444; width: 26px; height: 26px; font-size: 0.7rem;">
                                        <?= strtoupper(substr($row['payer_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?= e($row['payer_name']) ?></div>
                                        <small class="text-muted"><?= e($row['payer_email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar-sm" style="background: #10b981; width: 26px; height: 26px; font-size: 0.7rem;">
                                        <?= strtoupper(substr($row['receiver_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?= e($row['receiver_name']) ?></div>
                                        <small class="text-muted"><?= e($row['receiver_email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td class="fw-bold text-dark fs-6"><?= format_currency($row['amount']) ?></td>
                            <td>
                                <?php if ($row['status'] === 'paid'): ?>
                                    <span class="badge badge-custom badge-paid">
                                        <i class="fa-solid fa-check-circle"></i> Paid
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-custom badge-pending">
                                        <i class="fa-solid fa-clock"></i> Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= !empty($row['confirmed_by']) ? '<span class="small fw-semibold text-dark"><i class="fa-solid fa-user-check me-1 text-success"></i>' . e($row['confirmed_by']) . '</span>' : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td>
                                <?= !empty($row['paid_at']) ? format_date($row['paid_at']) . '<br><small class="text-muted">' . format_time($row['paid_at']) . '</small>' : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td class="text-end no-export">
                                <?php if ($row['status'] === 'pending'): ?>
                                    <button type="button" class="btn btn-sm btn-success-custom d-inline-flex align-items-center gap-1" onclick="openMarkPaidModal(<?= (int)$row['settlement_id'] ?>, '<?= e(addslashes($row['payer_name'])) ?>', '<?= e(addslashes($row['receiver_name'])) ?>', '<?= format_currency($row['amount']) ?>')">
                                        <i class="fa-solid fa-check"></i>
                                        <span>Mark Paid</span>
                                    </button>
                                <?php else: ?>
                                    <span class="badge bg-light text-success border border-success-subtle py-2 px-2">
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

<!-- ========================================================
     Mark Payment Paid Modal
     ======================================================== -->
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
                            <span id="modalPayerName" class="text-danger"></span> pays <span id="modalReceiverName" class="text-success"></span>
                        </div>
                        <div class="fs-3 fw-bold text-primary mt-1" id="modalAmount"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Method Used</label>
                        <select class="form-select" name="payment_method" id="paid_payment_method">
                            <option value="UPI" selected>UPI (GPay / PhonePe / Paytm)</option>
                            <option value="Cash">Cash Handover</option>
                            <option value="Bank Transfer">Bank Transfer (IMPS / NEFT)</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Transaction Reference / Note (Optional)</label>
                        <input type="text" class="form-control" name="notes" placeholder="e.g. UTR #123456 or Paid in person">
                    </div>

                    <div class="form-text small text-muted">
                        <i class="fa-solid fa-shield-halved me-1 text-primary"></i>
                        Confirming will record your identity as the confirmer, log the payment date and time, and preserve original expense ledgers.
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success-custom btn-sm d-flex align-items-center gap-2" id="submitMarkPaidBtn">
                        <i class="fa-solid fa-check-double"></i>
                        <span>Confirm Payment Receipt</span>
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

function recalculateAndSync() {
    const token = document.querySelector('input[name=\"csrf_token\"]').value;
    const formData = new FormData();
    formData.append('action', 'sync');
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
    .catch(() => showToast('Failed to sync settlements.', 'danger'));
}

document.addEventListener('DOMContentLoaded', function () {
    // Initialize Bootstrap tooltips
    document.querySelectorAll('[data-bs-toggle=\\'tooltip\\']').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    document.getElementById('markPaidForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = document.getElementById('submitMarkPaidBtn');
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

