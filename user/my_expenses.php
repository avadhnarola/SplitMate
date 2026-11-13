<?php
// SplitMate User - My Expenses with Dynamic AJAX Filters

require_once __DIR__ . '/../includes/auth.php';
require_user();

$userId  = current_user_id();
$adminId = current_admin_id();
$categories = get_categories();

$pageTitle = 'My Expenses';
$currentPage = 'user_my_expenses';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header & Action Row -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">My Personal Expenses</h3>
        <p class="text-muted mb-0 small">Review and manage all expense contributions paid by you</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-outline-success btn-sm d-flex align-items-center gap-2" onclick="exportTableToCSV('myExpensesTable', 'my_expenses.csv')">
            <i class="fa-solid fa-file-csv"></i>
            <span>Export CSV</span>
        </button>
        <a href="<?= base_url('user/add_expense.php') ?>" class="btn btn-primary-custom btn-sm d-flex align-items-center gap-2">
            <i class="fa-solid fa-plus-circle"></i>
            <span>Add Expense</span>
        </a>
    </div>
</div>

<!-- ========================================================
     Filter Controls
     ======================================================== -->
<div class="sm-card p-3 mb-4 filter-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-filter text-primary me-2"></i>Filter My Expenses
        </h6>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetFilters()">
            <i class="fa-solid fa-rotate-left me-1"></i> Reset
        </button>
    </div>

    <form id="filterForm" onsubmit="event.preventDefault(); applyFilters();">
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label">Search Title</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control" id="filter_search" placeholder="Type title..." onkeyup="debounceFilter()">
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label">Category</label>
                <select class="form-select form-select-sm" id="filter_category" onchange="applyFilters()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat['name']) ?>"><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Payment Method</label>
                <select class="form-select form-select-sm" id="filter_payment_method" onchange="applyFilters()">
                    <option value="">All Methods</option>
                    <option value="UPI">UPI</option>
                    <option value="Cash">Cash</option>
                    <option value="Card">Card</option>
                    <option value="Net Banking">Net Banking</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Month</label>
                <input type="month" class="form-control form-control-sm" id="filter_month" onchange="applyFilters()">
            </div>

            <div class="col-md-4">
                <label class="form-label">Date Range</label>
                <div class="input-group input-group-sm">
                    <input type="date" class="form-control" id="filter_start_date" onchange="applyFilters()">
                    <span class="input-group-text bg-light">to</span>
                    <input type="date" class="form-control" id="filter_end_date" onchange="applyFilters()">
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label">Amount Range (₹)</label>
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control" id="filter_min_amount" placeholder="Min ₹" onchange="applyFilters()">
                    <span class="input-group-text bg-light">-</span>
                    <input type="number" class="form-control" id="filter_max_amount" placeholder="Max ₹" onchange="applyFilters()">
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label">Sort By</label>
                <select class="form-select form-select-sm" id="filter_sort_by" onchange="applyFilters()">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="amount_high">Highest Amount</option>
                    <option value="amount_low">Lowest Amount</option>
                </select>
            </div>
        </div>
    </form>
</div>

<!-- Results Counter -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="small text-muted">
        Showing <strong id="resultCount" class="text-dark">0</strong> expenses
    </div>
    <div class="small">
        My Filtered Spending: <strong id="resultTotal" class="text-primary fs-6">₹0.00</strong>
    </div>
</div>

<!-- ========================================================
     Expenses Table
     ======================================================== -->
<div class="table-responsive-wrapper mb-4">
    <table class="table table-custom" id="myExpensesTable">
        <thead>
            <tr>
                <th style="width: 50px;">#</th>
                <th>Expense Title</th>
                <th>Paid By</th>
                <th>Category</th>
                <th>Amount</th>
                <th>Date & Time</th>
                <th>Method</th>
                <th class="text-end no-export" style="width: 100px;">Actions</th>
            </tr>
        </thead>
        <tbody id="expensesTableBody">
            <tr>
                <td colspan="8" class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Loading your expenses...
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- ========================================================
     Edit Expense Modal
     ======================================================== -->
<div class="modal fade" id="editExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-pen-to-square me-2 text-warning"></i>Edit My Expense
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editExpenseForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="expense_id" id="edit_expense_id">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Expense Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_title" name="title" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">₹</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="edit_amount" name="amount" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_category" name="category" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= e($cat['name']) ?>"><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-sm-4">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="edit_date" name="expense_date" required>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label">Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="edit_time" name="expense_time" required>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label">Method</label>
                            <select class="form-select" id="edit_payment_method" name="payment_method">
                                <option value="UPI">UPI</option>
                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="Net Banking">Net Banking</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Description / Notes</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom btn-sm" id="updateExpenseBtn">
                        <i class="fa-solid fa-save me-1"></i> Update Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================
     Delete Confirmation Modal
     ======================================================== -->
<div class="modal fade" id="deleteExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-trash me-2"></i>Delete Expense</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 text-center">
                <p class="mb-1">Are you sure you want to delete this expense?</p>
                <strong id="deleteExpenseTitle" class="text-dark d-block mb-2"></strong>
                <small class="text-danger">This will recalculate group balances.</small>
            </div>
            <div class="modal-footer p-2 justify-content-center">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = "
<script>
let debounceTimer;
let deleteTargetId = 0;

function debounceFilter() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 300);
}

function resetFilters() {
    document.getElementById('filterForm').reset();
    applyFilters();
}

function applyFilters() {
    const params = new URLSearchParams({
        scope: 'user',
        search: document.getElementById('filter_search').value,
        category: document.getElementById('filter_category').value,
        payment_method: document.getElementById('filter_payment_method').value,
        min_amount: document.getElementById('filter_min_amount').value,
        max_amount: document.getElementById('filter_max_amount').value,
        start_date: document.getElementById('filter_start_date').value,
        end_date: document.getElementById('filter_end_date').value,
        month: document.getElementById('filter_month').value,
        sort_by: document.getElementById('filter_sort_by').value
    });

    fetch('" . base_url('api/filters.php?') . "' + params.toString())
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('expensesTableBody').innerHTML = data.html;
                document.getElementById('resultCount').innerText = data.count;
                document.getElementById('resultTotal').innerText = data.formatted_total;
            }
        });
}

function openEditExpenseModal(id) {
    fetch('" . base_url('api/expenses.php?action=get&id=') . "' + id)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const exp = data.data;
                document.getElementById('edit_expense_id').value = exp.expense_id;
                document.getElementById('edit_title').value = exp.title;
                document.getElementById('edit_amount').value = exp.amount;
                document.getElementById('edit_category').value = exp.category;
                document.getElementById('edit_date').value = exp.expense_date;
                document.getElementById('edit_time').value = exp.expense_time;
                document.getElementById('edit_payment_method').value = exp.payment_method;
                document.getElementById('edit_description').value = exp.description || '';

                const modal = new bootstrap.Modal(document.getElementById('editExpenseModal'));
                modal.show();
            } else {
                showToast(data.message, 'danger');
            }
        });
}

function confirmDeleteExpense(id, title) {
    deleteTargetId = id;
    document.getElementById('deleteExpenseTitle').innerText = title;
    const modal = new bootstrap.Modal(document.getElementById('deleteExpenseModal'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function () {
    applyFilters();

    // Edit Expense Submission
    document.getElementById('editExpenseForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = document.getElementById('updateExpenseBtn');
        btn.disabled = true;

        const formData = new FormData(this);
        fetch('" . base_url('api/expenses.php') . "', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('editExpenseModal'));
                if (modal) modal.hide();
                applyFilters();
            } else {
                showToast(data.message, 'danger');
            }
            btn.disabled = false;
        })
        .catch(() => {
            showToast('Failed to update expense.', 'danger');
            btn.disabled = false;
        });
    });

    // Delete Confirmation
    document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
        if (!deleteTargetId) return;
        const token = document.querySelector('input[name=\"csrf_token\"]').value;
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('expense_id', deleteTargetId);
        formData.append('csrf_token', token);

        fetch('" . base_url('api/expenses.php') . "', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('deleteExpenseModal'));
                if (modal) modal.hide();
                applyFilters();
            } else {
                showToast(data.message, 'danger');
            }
        })
        .catch(() => showToast('Failed to delete expense.', 'danger'));
    });
});
</script>
";

require_once __DIR__ . '/../includes/footer.php';
?>

