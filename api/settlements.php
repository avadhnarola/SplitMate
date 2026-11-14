<?php
// SplitMate API - Smart Settlement Management Engine

// Buffer all output so stray PHP warnings never corrupt JSON
ob_start();

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

// Suppress PHP notices/warnings from polluting JSON response
error_reporting(0);

if (!is_logged_in()) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$adminId = current_admin_id();
$userId  = current_user_id();
$role    = current_role();
$pdo     = db();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'matrix':
        $startDate = trim($_GET['start_date'] ?? '');
        $endDate   = trim($_GET['end_date']   ?? '');
        $matrix = calculate_settlement_matrix(
            $adminId,
            !empty($startDate) ? $startDate : null,
            !empty($endDate)   ? $endDate   : null
        );
        ob_clean();
        echo json_encode(['success' => true, 'data' => $matrix]);
        break;

    case 'sync':
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
            exit;
        }

        $matrix = sync_settlements_to_database($adminId);
        log_activity($adminId, $userId, "Synchronized group settlements using greedy algorithm");

        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Settlements successfully recalculated and synchronized!',
            'data'    => $matrix
        ]);
        break;

    case 'mark_paid':
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
            exit;
        }

        $settlementId  = (int)($_POST['settlement_id']  ?? 0);
        $paymentMethod = trim($_POST['payment_method']  ?? 'UPI');
        $notes         = trim($_POST['notes']           ?? '');

        if ($settlementId <= 0) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid settlement ID.']);
            exit;
        }

        // Fetch settlement record scoped to admin_id
        $stmt = $pdo->prepare(
            "SELECT s.*, p.full_name AS payer_name, r.full_name AS receiver_name
             FROM   settlements s
             JOIN   users p ON s.payer_id    = p.user_id
             JOIN   users r ON s.receiver_id = r.user_id
             WHERE  s.settlement_id = ? AND s.admin_id = ?"
        );
        $stmt->execute([$settlementId, $adminId]);
        $settlement = $stmt->fetch();

        // ----------------------------------------------------------------
        // If the row is missing it means the settlements table was never
        // synced.  Auto-sync now and retry the lookup once.
        // ----------------------------------------------------------------
        if (!$settlement) {
            sync_settlements_to_database($adminId);

            $stmt->execute([$settlementId, $adminId]);
            $settlement = $stmt->fetch();

            if (!$settlement) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Settlement record not found. Please refresh the page and try again.']);
                exit;
            }
        }

        // Authorization: admin, payer, or receiver may mark paid
        $canConfirm = (
            $role === 'admin' ||
            (int)$settlement['payer_id']    === $userId ||
            (int)$settlement['receiver_id'] === $userId
        );
        if (!$canConfirm) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'You are not authorized to confirm this payment.']);
            exit;
        }

        if ($settlement['status'] === 'paid') {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'This settlement has already been marked as paid.']);
            exit;
        }

        // Safe retrieval of confirmer name
        $authUser     = current_user();
        $authUserName = $authUser['name'] ?? 'Unknown';
        $confirmerName = ($role === 'admin')
            ? "Admin ({$authUserName})"
            : "User ({$authUserName})";

        $up = $pdo->prepare(
            "UPDATE settlements
             SET    status = 'paid', paid_at = NOW(),
                    confirmed_by = ?, payment_method = ?, notes = ?,
                    updated_at = NOW()
             WHERE  settlement_id = ? AND admin_id = ?"
        );
        $up->execute([$confirmerName, $paymentMethod, $notes, $settlementId, $adminId]);

        log_activity(
            $adminId,
            $userId,
            "Settlement payment #{$settlementId} (" .
            format_currency($settlement['amount']) .
            " from {$settlement['payer_name']} to {$settlement['receiver_name']}) confirmed by {$confirmerName}"
        );

        // Immediately resynchronize settlements so balances and instructions reflect the paid transaction
        sync_settlements_to_database($adminId);

        ob_clean();
        echo json_encode([
            'success'      => true,
            'message'      => 'Payment marked as paid successfully!',
            'confirmed_by' => $confirmerName,
            'paid_at'      => date('d M Y, h:i A')
        ]);
        break;

    default:
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid settlement action.']);
        break;
}
