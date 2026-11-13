<?php
// SplitMate User - Add Expense Form

require_once __DIR__ . '/../includes/auth.php';
require_user();

$userId  = current_user_id();
$adminId = current_admin_id();
$categories = get_categories();
$pdo = db();

$error = '';
$success = '';

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

        if (empty($title)) {
            $error = 'Please enter an expense title.';
        } elseif ($amount <= 0) {
            $error = 'Expense amount must be greater than zero.';
        } elseif (empty($category)) {
            $error = 'Please select an expense category.';
        } else {
            $ins = $pdo->prepare("INSERT INTO expenses (admin_id, user_id, title, description, amount, category, expense_date, expense_time, payment_method, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $ins->execute([
                $adminId,
                $userId,
                $title,
                $description,
                $amount,
                $category,
                $date,
                $time,
                $paymentMethod
            ]);

            log_activity($adminId, $userId, "User added expense '{$title}' for " . format_currency($amount));

            // Sync settlements automatically
            sync_settlements_to_database($adminId);

            set_flash('success', "Expense '{$title}' of " . format_currency($amount) . " successfully added and settled across group!");
            header('Location: ' . base_url('user/my_expenses.php'));
            exit;
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

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">Add Group Expense</h3>
        <p class="text-muted mb-0 small">Record a new payment to automatically split equally among active group members</p>
    </div>
    <div>
        <a href="<?= base_url('user/my_expenses.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-list me-1"></i> View My Expenses
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small d-flex align-items-center gap-2 mb-3">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span><?= e($error) ?></span>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Expense Entry Form Card -->
    <div class="col-lg-7">
        <div class="sm-card p-4">
            <h5 class="fw-bold text-dark mb-3">
                <i class="fa-solid fa-receipt text-primary me-2"></i>Expense Details
            </h5>

            <form action="" method="POST" id="expenseForm" novalidate>
                <?= csrf_field() ?>

                <!-- Expense Title -->
                <div class="mb-3">
                    <label for="title" class="form-label">Expense Title <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-heading"></i></span>
                        <input type="text" class="form-control" id="title" name="title" placeholder="e.g. Dinner, Weekend Groceries, Taxi Fare" required autofocus>
                    </div>
                </div>

                <!-- Amount & Category Grid -->
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label for="amount" class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold text-dark">₹</span>
                            <input type="number" step="0.01" min="0.01" class="form-control fw-bold" id="amount" name="amount" placeholder="1500.00" required>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-tags"></i></span>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">-- Choose Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= e($cat['name']) ?>"><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Date, Time & Payment Method Grid -->
                <div class="row g-3 mb-3">
                    <div class="col-sm-4">
                        <label for="expense_date" class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="expense_date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-sm-4">
                        <label for="expense_time" class="form-label">Time <span class="text-danger">*</span></label>
                        <input type="time" class="form-control" id="expense_time" name="expense_time" value="<?= date('H:i') ?>" required>
                    </div>
                    <div class="col-sm-4">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select class="form-select" id="payment_method" name="payment_method">
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
                    <textarea class="form-control" id="description" name="description" rows="3" placeholder="e.g. Dinner with friends at spice grill..."></textarea>
                    <div class="form-text small text-muted">Provide any specifics for group clarity.</div>
                </div>

                <!-- Submit Button -->
                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-primary-custom px-4 py-2 d-flex align-items-center gap-2">
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
                <i class="fa-solid fa-calculator me-1"></i> Automatic Split Logic
            </h6>
            <p class="small text-muted mb-2">
                When you record this payment, you are credited as the payer. The smart settlement algorithm automatically recalculates fair shares across all active members in your admin group.
            </p>
            <ul class="small text-muted ps-3 mb-0">
                <li>Your spending increases.</li>
                <li>Your receivable balance increases accordingly.</li>
                <li>Settlement instructions update dynamically.</li>
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
require_once __DIR__ . '/../includes/footer.php';
?>

