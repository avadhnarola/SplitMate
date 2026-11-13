<?php
// SplitMate User - Add Expense Form with Selective Participant Splitting

require_once __DIR__ . '/../includes/auth.php';
require_user();

$userId  = current_user_id();
$adminId = current_admin_id();
$categories = get_categories();
$pdo = db();

$error = '';
$success = '';

// Fetch all active users in this admin group
$stmtUsers = $pdo->prepare("SELECT user_id, full_name, email FROM users WHERE admin_id = ? AND status = 'active' ORDER BY full_name ASC");
$stmtUsers->execute([$adminId]);
$activeUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// Server-side fallback submission handler (if JS is disabled or bypassed)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Security session expired. Please refresh.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $date = trim($_POST['expense_date'] ?? date('Y-m-d'));
        $time = trim($_POST['expense_time'] ?? date('H:i:s'));
        $paymentMethod = trim($_POST['payment_method'] ?? 'UPI');
        $splitType = trim($_POST['split_type'] ?? 'everyone');
        if (!in_array($splitType, ['everyone', 'selected'], true)) {
            $splitType = 'everyone';
        }

        $participantIds = [];
        if ($splitType === 'everyone') {
            $participantIds = array_column($activeUsers, 'user_id');
        } else {
            $rawIds = $_POST['participant_ids'] ?? [];
            if (is_array($rawIds)) {
                $participantIds = array_values(array_filter(array_map('intval', $rawIds)));
            }
        }

        if (empty($title)) {
            $error = 'Please enter an expense title.';
        } elseif ($amount <= 0) {
            $error = 'Expense amount must be greater than zero.';
        } elseif (empty($category)) {
            $error = 'Please select an expense category.';
        } elseif ($splitType === 'selected' && empty($participantIds)) {
            $error = 'Please select at least one participant.';
        } else {
            try {
                $pdo->beginTransaction();
                $ins = $pdo->prepare("INSERT INTO expenses (admin_id, user_id, title, description, amount, category, expense_date, expense_time, payment_method, split_type, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $ins->execute([
                    $adminId,
                    $userId,
                    $title,
                    $description,
                    $amount,
                    $category,
                    $date,
                    $time,
                    $paymentMethod,
                    $splitType
                ]);
                $newExpenseId = (int)$pdo->lastInsertId();

                if (function_exists('save_expense_participants')) {
                    save_expense_participants($newExpenseId, $adminId, $participantIds, $amount);
                }
                $pdo->commit();

                log_activity($adminId, $userId, "User added expense '{$title}' for " . format_currency($amount));

                // Sync settlements automatically
                sync_settlements_to_database($adminId);

                set_flash('success', "Expense '{$title}' of " . format_currency($amount) . " successfully added and settled!");
                header('Location: ' . base_url('user/my_expenses.php'));
                exit;
            } catch (\Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Failed to save expense: ' . $e->getMessage();
            }
        }
    }
}

// Fetch recent 5 expenses of this user
$recStmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = ? AND admin_id = ? ORDER BY expense_date DESC, expense_time DESC LIMIT 5");
$recStmt->execute([$userId, $adminId]);
$recentEntries = $recStmt->fetchAll();

$pageTitle = 'Add Expense';
$currentPage = 'user_add_expense';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.cursor-pointer { cursor: pointer; }
.split-radio-card {
    transition: all 0.2s ease;
    border: 1px solid var(--sm-border);
    border-radius: var(--sm-radius-sm);
}
.split-radio-card:hover {
    border-color: #cbd5e1;
    background-color: #f8fafc;
}
.split-radio-card.active-split {
    border-color: var(--sm-primary) !important;
    background-color: var(--sm-primary-light) !important;
    box-shadow: 0 0 0 1px var(--sm-primary);
}
.participant-option-label {
    transition: all 0.15s ease;
    background-color: #ffffff;
}
.participant-option-label:hover {
    background-color: #f8fafc;
    border-color: #cbd5e1 !important;
}
.participant-option-label.participant-active {
    background-color: #f0f4ff;
    border-color: #a5b4fc !important;
}
.participant-checkbox {
    width: 1.15em;
    height: 1.15em;
    cursor: pointer;
}
.split-preview-card {
    background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    padding: 1rem 1.25rem;
}
.split-preview-amount {
    font-size: 1.5rem;
    font-weight: 800;
    color: #059669;
}
</style>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                <i class="fa-solid fa-receipt me-1"></i>New Entry
            </span>
            <span class="text-muted small">Group #<?= (int)$adminId ?></span>
        </div>
        <h3 class="fw-bold text-dark mb-0">Add Group Expense</h3>
        <p class="text-muted mb-0 small">Record a new payment and choose whether to split across everyone or specific members</p>
    </div>
    <div>
        <a href="<?= base_url('user/my_expenses.php') ?>" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2 px-3 py-2 rounded-3">
            <i class="fa-solid fa-list text-primary"></i>
            <span>View My Expenses</span>
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small d-flex align-items-center gap-2 mb-3 rounded-3 shadow-sm">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span><?= e($error) ?></span>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Expense Entry Form Card -->
    <div class="col-lg-7">
        <div class="sm-card p-4 p-md-5" style="border-radius: 16px;">
            <div class="d-flex align-items-center justify-content-between pb-3 mb-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-3 d-flex align-items-center justify-content-center text-white" style="width: 36px; height: 36px; background: linear-gradient(135deg, #4f46e5, #6366f1); font-size: 1rem;">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Expense Details</h5>
                        <small class="text-muted">Enter bill details and participant distribution</small>
                    </div>
                </div>
                <span class="badge bg-light text-muted border px-2 py-1" style="font-size: 0.75rem;">Required *</span>
            </div>

            <form action="" method="POST" id="expenseForm" novalidate>
                <?= csrf_field() ?>

                <!-- Expense Title -->
                <div class="mb-4">
                    <label for="title" class="form-label">Expense Title <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-heading"></i></span>
                        <input type="text" class="form-control border-start-0 py-2 fs-6 fw-semibold" id="title" name="title" placeholder="e.g. Dinner, Weekend Groceries, Taxi Fare" required autofocus>
                    </div>
                </div>

                <!-- Amount & Category Grid -->
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <label for="amount" class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold text-primary border-end-0 fs-5">₹</span>
                            <input type="number" step="0.01" min="0.01" class="form-control border-start-0 input-amount-highlight" id="amount" name="amount" placeholder="1500.00" required>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-tags"></i></span>
                            <select class="form-select border-start-0 py-2 fw-medium" id="category" name="category" required>
                                <option value="">-- Choose Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= e($cat['name']) ?>"><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Date, Time & Payment Method Grid -->
                <div class="row g-3 mb-4">
                    <div class="col-sm-4">
                        <label for="expense_date" class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control py-2" id="expense_date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-sm-4">
                        <label for="expense_time" class="form-label">Time <span class="text-danger">*</span></label>
                        <input type="time" class="form-control py-2" id="expense_time" name="expense_time" value="<?= date('H:i') ?>" required>
                    </div>
                    <div class="col-sm-4">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select class="form-select py-2 fw-medium" id="payment_method" name="payment_method">
                            <option value="UPI" selected>UPI (GPay / PhonePe)</option>
                            <option value="Cash">Cash</option>
                            <option value="Card">Card</option>
                            <option value="Net Banking">Net Banking</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <!-- Description / Notes -->
                <div class="mb-4">
                    <label for="description" class="form-label">Description / Notes</label>
                    <textarea class="form-control" id="description" name="description" rows="2" placeholder="e.g. Dinner with friends at spice grill..."></textarea>
                    <div class="form-text small text-muted">Provide any specifics for group clarity.</div>
                </div>

                <!-- NEW SECTION: Split Expense Between -->
                <div class="mb-4">
                    <label class="form-label d-flex align-items-center justify-content-between mb-2">
                        <span><i class="fa-solid fa-users text-primary me-2"></i><strong>Split Expense Between</strong></span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1" id="splitModeBadge">Everyone</span>
                    </label>

                    <!-- Two Radio Options -->
                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <div class="p-3 bg-white split-radio-card active-split" id="cardEveryone">
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="radio" name="split_type" id="split_type_everyone" value="everyone" checked>
                                    <label class="form-check-label w-100 cursor-pointer ps-1" for="split_type_everyone">
                                        <div class="fw-bold text-dark d-flex align-items-center gap-2">
                                            <i class="fa-solid fa-globe text-primary"></i> Everyone
                                        </div>
                                        <div class="text-muted mt-1" style="font-size: 0.78rem;">
                                            Splits equally among all active members under this admin
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-white split-radio-card" id="cardSelected">
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="radio" name="split_type" id="split_type_selected" value="selected">
                                    <label class="form-check-label w-100 cursor-pointer ps-1" for="split_type_selected">
                                        <div class="fw-bold text-dark d-flex align-items-center gap-2">
                                            <i class="fa-solid fa-user-check" style="color: #7c3aed;"></i> Selected Members
                                        </div>
                                        <div class="text-muted mt-1" style="font-size: 0.78rem;">
                                            Choose specific group members who participate
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Selected Members Checkboxes Card (Visible when 'selected' is chosen) -->
                    <div id="selectedMembersCard" class="card border rounded-3 p-3 mb-3 bg-light-subtle" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom">
                            <span class="fw-bold small text-dark">
                                <i class="fa-solid fa-user-group me-1 text-primary"></i> Select Participants
                            </span>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary btn-sm py-1 px-2" id="btnSelectAll">
                                    <i class="fa-solid fa-check-double me-1"></i>Select All
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2" id="btnClearAll">
                                    <i class="fa-solid fa-xmark me-1"></i>Clear
                                </button>
                            </div>
                        </div>

                        <!-- Checkbox list of users -->
                        <div id="participantsList" class="row g-2" style="max-height: 260px; overflow-y: auto;">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>

                    <!-- Live Share Preview Box -->
                    <div id="splitLivePreview" class="split-preview-card d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrapper stat-icon-green" style="width: 44px; height: 44px; font-size: 1.2rem;">
                                <i class="fa-solid fa-calculator"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark" id="previewParticipantsText">Everyone (<?= count($activeUsers) ?> active members)</div>
                                <div class="text-muted small" id="previewSubtitleText">Splits equally across entire group</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="text-muted small fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.05em;">EACH PAYS</div>
                            <div class="split-preview-amount" id="previewShareAmount">₹0.00</div>
                        </div>
                    </div>

                    <!-- Warning Alert when 0 participants selected -->
                    <div id="splitWarningMessage" class="alert alert-warning py-2 px-3 small mt-2 d-none align-items-center gap-2 mb-0">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span>Select at least one participant to split this expense.</span>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="d-flex align-items-center gap-2">
                    <button type="submit" id="submitBtn" class="btn btn-primary-custom px-4 py-2 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-check"></i>
                        <span>Save & Split Expense</span>
                    </button>
                    <a href="<?= base_url('user/dashboard.php') ?>" class="btn btn-light border px-3 py-2">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Side Information & Preview Card -->
    <div class="col-lg-5">
        <!-- How Split Works Info Box -->
        <div class="p-3 mb-4 rounded-3 bg-primary-subtle border border-primary-subtle">
            <h6 class="fw-bold text-primary mb-1">
                <i class="fa-solid fa-calculator me-1"></i> Smart Settlement Engine
            </h6>
            <p class="small text-muted mb-2">
                When you record this payment, you are credited as the payer. Choose <strong>Everyone</strong> to distribute the cost equally among all active members, or choose <strong>Selected Members</strong> to split only among specific participants.
            </p>
            <ul class="small text-muted ps-3 mb-0">
                <li>Your total paid expenditure increases.</li>
                <li>Each participant is assigned their exact share.</li>
                <li>Settlement instructions recalculate automatically.</li>
            </ul>
        </div>

        <!-- Recent Personal Expenses Summary -->
        <div class="sm-card p-3">
            <h6 class="fw-bold text-dark mb-3">
                <i class="fa-solid fa-clock-rotate-left text-muted me-2"></i>My Recent Entries
            </h6>
            <?php if (empty($recentEntries)): ?>
                <div class="text-center text-muted py-3 small">
                    No prior expenses logged. Your first entry will show here.
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentEntries as $rec): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                            <div>
                                <div class="fw-bold text-dark small"><?= e($rec['title']) ?></div>
                                <div class="text-muted" style="font-size: 0.72rem;">
                                    <?= format_date($rec['expense_date']) ?> &bull; <?= e($rec['category']) ?>
                                    <?php if (!empty($rec['split_type']) && $rec['split_type'] === 'selected'): ?>
                                        <span class="badge bg-secondary-subtle text-secondary ms-1">Selected</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="fw-bold text-dark small"><?= format_currency($rec['amount']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$extraScripts = "
<script>
document.addEventListener('DOMContentLoaded', function () {
    const activeUsersFallback = " . json_encode($activeUsers) . ";
    const currentUserId = " . (int)$userId . ";
    let usersList = Array.isArray(activeUsersFallback) ? activeUsersFallback : [];

    const amountInput = document.getElementById('amount');
    const radioEveryone = document.getElementById('split_type_everyone');
    const radioSelected = document.getElementById('split_type_selected');
    const cardEveryone = document.getElementById('cardEveryone');
    const cardSelected = document.getElementById('cardSelected');
    const selectedMembersCard = document.getElementById('selectedMembersCard');
    const participantsList = document.getElementById('participantsList');
    const splitLivePreview = document.getElementById('splitLivePreview');
    const previewParticipantsText = document.getElementById('previewParticipantsText');
    const previewSubtitleText = document.getElementById('previewSubtitleText');
    const previewShareAmount = document.getElementById('previewShareAmount');
    const splitWarningMessage = document.getElementById('splitWarningMessage');
    const splitModeBadge = document.getElementById('splitModeBadge');
    const submitBtn = document.getElementById('submitBtn');
    const btnSelectAll = document.getElementById('btnSelectAll');
    const btnClearAll = document.getElementById('btnClearAll');
    const expenseForm = document.getElementById('expenseForm');

    const avatarColors = ['#4f46e5', '#059669', '#d97706', '#dc2626', '#7c3aed', '#0891b2', '#db2777', '#2563eb'];

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;');
    }

    function getInitials(name) {
        if (!name) return 'U';
        const parts = name.trim().split(/\s+/);
        if (parts.length >= 2) {
            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        }
        return name.substring(0, 2).toUpperCase();
    }

    function formatINR(amount) {
        const val = parseFloat(amount) || 0;
        return '₹' + val.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function renderParticipants() {
        if (!participantsList) return;

        const currentlyChecked = new Set();
        document.querySelectorAll('.participant-checkbox:checked').forEach(cb => {
            currentlyChecked.add(cb.value);
        });

        const isFirstRender = document.querySelectorAll('.participant-checkbox').length === 0;

        let html = '';
        usersList.forEach((user, idx) => {
            const uid = String(user.user_id);
            const isChecked = isFirstRender ? true : currentlyChecked.has(uid);
            const isMe = (parseInt(user.user_id) === currentUserId);
            const color = avatarColors[idx % avatarColors.length];
            const initials = getInitials(user.full_name);

            html += `
            <div class=\"col-md-6 col-12\">
                <label class=\"participant-option-label d-flex align-items-center gap-2 p-2 border rounded-3 cursor-pointer user-select-none mb-0 w-100 \${isChecked ? 'participant-active' : ''}\" for=\"part_\${uid}\">
                    <input type=\"checkbox\" class=\"form-check-input participant-checkbox m-0\" id=\"part_\${uid}\" name=\"participant_ids[]\" value=\"\${uid}\" data-name=\"\${escapeHtml(user.full_name)}\" \${isChecked ? 'checked' : ''}>
                    <div class=\"user-avatar-sm\" style=\"background-color: \${color}; width: 32px; height: 32px; font-size: 0.75rem; min-width: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700;\">
                        \${initials}
                    </div>
                    <div class=\"flex-grow-1 text-truncate\">
                        <div class=\"fw-semibold text-dark small text-truncate\">
                            \${escapeHtml(user.full_name)}
                            \${isMe ? '<span class=\"badge bg-primary-subtle text-primary border border-primary-subtle ms-1\" style=\"font-size: 0.65rem;\">You</span>' : ''}
                        </div>
                        <div class=\"text-muted text-truncate\" style=\"font-size: 0.7rem;\">\${escapeHtml(user.email || '')}</div>
                    </div>
                </label>
            </div>`;
        });

        participantsList.innerHTML = html;

        // Attach event listeners to newly created checkboxes
        document.querySelectorAll('.participant-checkbox').forEach(cb => {
            cb.addEventListener('change', function () {
                const label = this.closest('.participant-option-label');
                if (label) {
                    if (this.checked) {
                        label.classList.add('participant-active');
                    } else {
                        label.classList.remove('participant-active');
                    }
                }
                recalculateShare();
            });
        });
    }

    function recalculateShare() {
        const totalAmount = parseFloat(amountInput.value) || 0;
        const splitType = document.querySelector('input[name=\"split_type\"]:checked')?.value || 'everyone';

        if (splitType === 'everyone') {
            selectedMembersCard.style.display = 'none';
            cardEveryone.classList.add('active-split');
            cardSelected.classList.remove('active-split');
            splitModeBadge.textContent = 'Everyone';
            splitModeBadge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1';

            splitWarningMessage.classList.add('d-none');
            splitWarningMessage.classList.remove('d-flex');
            submitBtn.disabled = false;

            const memberCount = usersList.length > 0 ? usersList.length : 1;
            const perPerson = totalAmount > 0 ? (totalAmount / memberCount) : 0;

            previewParticipantsText.innerHTML = `<i class=\"fa-solid fa-users text-primary me-1\"></i> Everyone (\${memberCount} active members)`;
            previewSubtitleText.textContent = 'Splits equally across entire group';
            previewShareAmount.textContent = formatINR(perPerson);
        } else {
            selectedMembersCard.style.display = 'block';
            cardSelected.classList.add('active-split');
            cardEveryone.classList.remove('active-split');
            splitModeBadge.textContent = 'Selected Members';
            splitModeBadge.className = 'badge bg-purple-subtle text-purple border px-2 py-1';
            splitModeBadge.style.color = '#7c3aed';
            splitModeBadge.style.backgroundColor = '#f5f3ff';
            splitModeBadge.style.borderColor = '#ddd6fe';

            const checkedBoxes = document.querySelectorAll('.participant-checkbox:checked');
            const checkedCount = checkedBoxes.length;

            if (checkedCount === 0) {
                splitWarningMessage.classList.remove('d-none');
                splitWarningMessage.classList.add('d-flex');
                submitBtn.disabled = true;

                previewParticipantsText.innerHTML = `<i class=\"fa-solid fa-triangle-exclamation text-warning me-1\"></i> 0 members selected`;
                previewSubtitleText.textContent = 'Select at least one participant';
                previewShareAmount.textContent = '₹0.00';
            } else if (checkedCount === 1) {
                splitWarningMessage.classList.add('d-none');
                splitWarningMessage.classList.remove('d-flex');
                submitBtn.disabled = false;

                const singleName = checkedBoxes[0].getAttribute('data-name') || 'This person';
                previewParticipantsText.innerHTML = `<i class=\"fa-solid fa-user-check text-success me-1\"></i> Selected: 1 member`;
                previewSubtitleText.textContent = `\${singleName} covers the full amount`;
                previewShareAmount.textContent = formatINR(totalAmount);
            } else {
                splitWarningMessage.classList.add('d-none');
                splitWarningMessage.classList.remove('d-flex');
                submitBtn.disabled = false;

                const perPerson = totalAmount > 0 ? (totalAmount / checkedCount) : 0;
                previewParticipantsText.innerHTML = `<i class=\"fa-solid fa-user-group text-primary me-1\"></i> Selected: \${checkedCount} members`;
                previewSubtitleText.textContent = 'Each pays an equal share';
                previewShareAmount.textContent = formatINR(perPerson);
            }
        }
    }

    // Fetch active users from API
    function fetchGroupUsers() {
        fetch('" . base_url('api/expenses.php?action=get_users') . "')
            .then(res => res.json())
            .then(data => {
                if (data.success && Array.isArray(data.data) && data.data.length > 0) {
                    usersList = data.data;
                    renderParticipants();
                    recalculateShare();
                }
            })
            .catch(err => {
                console.warn('API get_users fetch fallback to initial list:', err);
            });
    }

    // Initial render and setup
    renderParticipants();
    recalculateShare();
    fetchGroupUsers();

    // Event listeners
    amountInput.addEventListener('input', recalculateShare);
    radioEveryone.addEventListener('change', recalculateShare);
    radioSelected.addEventListener('change', recalculateShare);

    cardEveryone.addEventListener('click', function (e) {
        if (e.target !== radioEveryone) {
            radioEveryone.checked = true;
        }
        recalculateShare();
    });

    cardSelected.addEventListener('click', function (e) {
        if (e.target !== radioSelected) {
            radioSelected.checked = true;
        }
        recalculateShare();
    });

    btnSelectAll.addEventListener('click', function () {
        document.querySelectorAll('.participant-checkbox').forEach(cb => {
            cb.checked = true;
            const label = cb.closest('.participant-option-label');
            if (label) label.classList.add('participant-active');
        });
        recalculateShare();
    });

    btnClearAll.addEventListener('click', function () {
        document.querySelectorAll('.participant-checkbox').forEach(cb => {
            cb.checked = false;
            const label = cb.closest('.participant-option-label');
            if (label) label.classList.remove('participant-active');
        });
        recalculateShare();
    });

    // Form submission via AJAX fetch
    expenseForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const titleInput = document.getElementById('title');
        const titleVal = titleInput.value.trim();
        const amountVal = parseFloat(amountInput.value);
        const categoryVal = document.getElementById('category').value;
        const splitType = document.querySelector('input[name=\"split_type\"]:checked')?.value || 'everyone';

        if (!titleVal) {
            showToast('Please enter an expense title.', 'danger');
            titleInput.focus();
            return;
        }
        if (isNaN(amountVal) || amountVal <= 0) {
            showToast('Amount must be greater than zero.', 'danger');
            amountInput.focus();
            return;
        }
        if (!categoryVal) {
            showToast('Please select a category.', 'danger');
            document.getElementById('category').focus();
            return;
        }

        let participantIds = [];
        if (splitType === 'everyone') {
            participantIds = usersList.map(u => u.user_id);
        } else {
            const checkedBoxes = document.querySelectorAll('.participant-checkbox:checked');
            checkedBoxes.forEach(cb => participantIds.push(cb.value));
            if (participantIds.length === 0) {
                showToast('Please select at least one participant.', 'danger');
                return;
            }
        }

        const originalBtnHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class=\"fa-solid fa-spinner fa-spin me-2\"></i> Saving...';

        const formData = new FormData(expenseForm);
        formData.set('action', 'add');
        formData.set('split_type', splitType);

        // Delete any existing participant_ids[] and append fresh
        formData.delete('participant_ids[]');
        participantIds.forEach(id => {
            formData.append('participant_ids[]', id);
        });

        fetch('" . base_url('api/expenses.php?action=add') . "', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Expense added successfully!', 'success');
                setTimeout(() => {
                    window.location.href = '" . base_url('user/my_expenses.php') . "';
                }, 800);
            } else {
                showToast(data.message || 'Failed to add expense.', 'danger');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        })
        .catch(err => {
            console.error(err);
            showToast('An error occurred while saving the expense.', 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;
        });
    });
});
</script>
";

require_once __DIR__ . '/../includes/footer.php';
?>
