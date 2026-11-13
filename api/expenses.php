<?php
// SplitMate API - Expense Operations (Add, Edit, Delete, Get, Get Users)

ob_start();
error_reporting(0);
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
    exit;
}

$adminId = current_admin_id();
$userId = current_user_id();
$role = current_role();
$pdo = db();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'get':
        $expenseId = (int)($_GET['id'] ?? 0);
        if ($expenseId <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid expense ID.']);
            exit;
        }

        // Admin can view any expense in group; user can view any expense in group or personal
        $stmt = $pdo->prepare("SELECT e.*, u.full_name as user_name FROM expenses e JOIN users u ON e.user_id = u.user_id WHERE e.expense_id = ? AND e.admin_id = ?");
        $stmt->execute([$expenseId, $adminId]);
        $expense = $stmt->fetch();

        if (!$expense) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Expense not found.']);
            exit;
        }

        // Fetch participants for this expense
        $expense['participants'] = get_expense_participants($expenseId, $adminId);

        ob_clean();
        echo json_encode(['success' => true, 'data' => $expense]);
        break;

    case 'get_users':
        $stmt = $pdo->prepare("SELECT user_id, full_name FROM users WHERE admin_id = ? AND status = 'active' ORDER BY full_name ASC");
        $stmt->execute([$adminId]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_clean();
        echo json_encode(['success' => true, 'users' => $users]);
        break;

    case 'add':
        // CSRF check
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
            exit;
        }

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

        // Target user: if admin is adding on behalf of a user, validate user_id belongs to this admin. Otherwise target is logged-in user.
        $targetUserId = $userId;
        if ($role === 'admin' && !empty($_POST['user_id'])) {
            $chkUser = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND admin_id = ? AND status = 'active'");
            $chkUser->execute([(int)$_POST['user_id'], $adminId]);
            if ($chkUser->fetch()) {
                $targetUserId = (int)$_POST['user_id'];
            }
        }

        if (empty($title)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Expense title is required.']);
            exit;
        }
        if ($amount <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Amount must be greater than zero.']);
            exit;
        }
        if (empty($category)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Category is required.']);
            exit;
        }

        // Determine participant user IDs
        $participantIds = [];
        if ($splitType === 'everyone') {
            $uStmt = $pdo->prepare("SELECT user_id FROM users WHERE admin_id = ? AND status = 'active'");
            $uStmt->execute([$adminId]);
            $participantIds = array_map('intval', $uStmt->fetchAll(PDO::FETCH_COLUMN));
        } else {
            $rawParticipantIds = $_POST['participant_ids'] ?? [];
            if (!is_array($rawParticipantIds)) {
                $rawParticipantIds = [];
            }
            $participantIds = array_values(array_filter(array_map('intval', $rawParticipantIds), fn($id) => $id > 0));
        }

        if (empty($participantIds)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'At least one participant must be selected.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Insert into expenses
            $ins = $pdo->prepare("INSERT INTO expenses (admin_id, user_id, title, description, amount, category, expense_date, expense_time, payment_method, split_type, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $ins->execute([
                $adminId,
                $targetUserId,
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

            // Save participants with paise-exact shares
            save_expense_participants($newExpenseId, $adminId, $participantIds, $amount);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to save expense: ' . $e->getMessage()]);
            exit;
        }

        // Get user name for logging
        $uNameStmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
        $uNameStmt->execute([$targetUserId]);
        $uName = $uNameStmt->fetchColumn() ?: 'User';

        log_activity($adminId, $targetUserId, "Added expense '{$title}' for " . format_currency($amount) . " by {$uName}");

        // Auto sync settlements
        sync_settlements_to_database($adminId);

        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Expense added successfully!',
            'expense_id' => $newExpenseId
        ]);
        break;

    case 'edit':
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
            exit;
        }

        $expenseId = (int)($_POST['expense_id'] ?? 0);
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

        if ($expenseId <= 0 || empty($title) || $amount <= 0 || empty($category)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Please provide all valid required fields.']);
            exit;
        }

        // Authorization check: Admin can edit any in group, user can only edit their own
        if ($role === 'admin') {
            $chk = $pdo->prepare("SELECT expense_id, user_id FROM expenses WHERE expense_id = ? AND admin_id = ?");
            $chk->execute([$expenseId, $adminId]);
        } else {
            $chk = $pdo->prepare("SELECT expense_id, user_id FROM expenses WHERE expense_id = ? AND admin_id = ? AND user_id = ?");
            $chk->execute([$expenseId, $adminId, $userId]);
        }
        $existing = $chk->fetch();

        if (!$existing) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Expense not found or unauthorized to edit.']);
            exit;
        }

        // Determine participant user IDs
        $participantIds = [];
        if ($splitType === 'everyone') {
            $uStmt = $pdo->prepare("SELECT user_id FROM users WHERE admin_id = ? AND status = 'active'");
            $uStmt->execute([$adminId]);
            $participantIds = array_map('intval', $uStmt->fetchAll(PDO::FETCH_COLUMN));
        } else {
            $rawParticipantIds = $_POST['participant_ids'] ?? [];
            if (!is_array($rawParticipantIds)) {
                $rawParticipantIds = [];
            }
            $participantIds = array_values(array_filter(array_map('intval', $rawParticipantIds), fn($id) => $id > 0));
        }

        if (empty($participantIds)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'At least one participant must be selected.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $up = $pdo->prepare("UPDATE expenses SET title = ?, description = ?, amount = ?, category = ?, expense_date = ?, expense_time = ?, payment_method = ?, split_type = ?, updated_at = NOW() WHERE expense_id = ? AND admin_id = ?");
            $up->execute([$title, $description, $amount, $category, $date, $time, $paymentMethod, $splitType, $expenseId, $adminId]);

            // Save participants with paise-exact shares
            save_expense_participants($expenseId, $adminId, $participantIds, $amount);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to update expense: ' . $e->getMessage()]);
            exit;
        }

        log_activity($adminId, $userId, "Updated expense '{$title}' (#{$expenseId})");

        // Sync settlements
        sync_settlements_to_database($adminId);

        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Expense updated successfully!']);
        break;

    case 'delete':
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
            exit;
        }

        $expenseId = (int)($_POST['expense_id'] ?? 0);
        if ($expenseId <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid expense ID.']);
            exit;
        }

        // Authorization check
        if ($role === 'admin') {
            $chk = $pdo->prepare("SELECT title FROM expenses WHERE expense_id = ? AND admin_id = ?");
            $chk->execute([$expenseId, $adminId]);
        } else {
            $chk = $pdo->prepare("SELECT title FROM expenses WHERE expense_id = ? AND admin_id = ? AND user_id = ?");
            $chk->execute([$expenseId, $adminId, $userId]);
        }
        $existing = $chk->fetch();

        if (!$existing) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Expense not found or unauthorized to delete.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $delPart = $pdo->prepare("DELETE FROM expense_participants WHERE expense_id = ? AND admin_id = ?");
            $delPart->execute([$expenseId, $adminId]);

            $del = $pdo->prepare("DELETE FROM expenses WHERE expense_id = ? AND admin_id = ?");
            $del->execute([$expenseId, $adminId]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to delete expense: ' . $e->getMessage()]);
            exit;
        }

        log_activity($adminId, $userId, "Deleted expense '{$existing['title']}' (#{$expenseId})");

        // Sync settlements
        sync_settlements_to_database($adminId);

        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Expense deleted successfully!']);
        break;

    default:
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
        break;
}
