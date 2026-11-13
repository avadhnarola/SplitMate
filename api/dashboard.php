<?php
// SplitMate API - Dashboard Statistics & Chart Data Provider

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$adminId = current_admin_id();
$stats = get_admin_dashboard_stats($adminId);

echo json_encode([
    'success' => true,
    'data' => $stats
]);

