<?php
// SplitMate API - Smart Settlement Management Engine

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$adminId = current_admin_id();
$userId = current_user_id();
$role = current_role();
$pdo = db();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'matrix':
        $startDate = trim($_GET['start_date'] ?? '');
        $endDate   = trim($_GET['end_date'] ?? '');
        $matrix = calculate_settlement_matrix($adminId, !empty($startDate) ? $startDate : null, !empty($endDate) ? $endDate : null);
        echo json_encode(['success' => true, 'data' => $matrix]);
        break;

    case 'sync':
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
            exit;
        }

        $matrix = sync_settlements_to_database($adminId);
        log_activity($adminId, $userId, "Synchronized group settlements using greedy algorithm");

        echo json_encode([
            'success' => true,
            'message' => 'Settlements successfully recalculated and synchronized!',
            'data' => $matrix
        ]);
        break;

    case 'mark_paid':
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
            exit;
        }

        $settlementId = (int)($_POST['settlement_id'] ?? 0);
        $paymentMethod = trim($_POST['payment_method'] ?? 'UPI');
        $notes = trim($_POST['notes'] ?? '');

        if ($settlementId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid settlement ID.']);
            exit;
        }

        // Fetch settlement record scoped to admin_id
        $stmt = $pdo->prepare("SELECT s.*, p.full_name as payer_name, r.full_name as receiver_name 
                               FROM settlements s 
                               JOIN users p ON s.payer_id = p.user_id 
                               JOIN users r ON s.receiver_id = r.user_id 
                               WHERE s.settlement_id = ? AND s.admin_id = ?");
        $stmt->execute([$settlementId, $adminId]);
        $settlement = $stmt->fetch();

        if (!$settlement) {
            echo json_encode(['success' => false, 'message' => 'Settlement record not found.']);
            exit;
        }

        // Authorization check: Admin, or the payer, or the receiver can mark paid
        $canConfirm = ($role === 'admin' || (int)$settlement['payer_id'] === $userId || (int)$settlement['receiver_id'] === $userId);
        if (!$canConfirm) {
            echo json_encode(['success' => false, 'message' => 'You are not authorized to confirm this payment.']);
            exit;
        }

        if ($settlement['status'] === 'paid') {
            echo json_encode(['success' => false, 'message' => 'This settlement has already been marked as paid.']);
            exit;
        }

        $confirmerName = ($role === 'admin') ? "Admin ({$currentUser['name']})" : "User ({$currentUser['name']})";

        $up = $pdo->prepare("UPDATE settlements SET status = 'paid', paid_at = NOW(), confirmed_by = ?, payment_method = ?, notes = ?, updated_at = NOW() WHERE settlement_id = ? AND admin_id = ?");
        $up->execute([$confirmerName, $paymentMethod, $notes, $settlementId, $adminId]);

        log_activity(
            $adminId,
            $userId,
            "Settlement payment #{$settlementId} (" . format_currency($settlement['amount']) . " from {$settlement['payer_name']} to {$settlement['receiver_name']}) confirmed by {$confirmerName}"
        );

        echo json_encode([
            'success' => true,
            'message' => 'Payment marked as paid successfully!',
            'confirmed_by' => $confirmerName,
            'paid_at' => date('d M Y, h:i A')
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid settlement action.']);
        break;
}

