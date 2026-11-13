<?php
require_once __DIR__ . '/config/database.php';
$pdo = db();
$stmt = $pdo->prepare("INSERT INTO users (admin_id, full_name, email, phone, password, status, email_verified, created_at) VALUES (?, ?, ?, ?, ?, 'inactive', 0, NOW())");
$testEmail = 'test_reg_' . time() . '@example.com';
$res = $stmt->execute([1, 'Test User', $testEmail, '9999999999', 'dummy_hash']);
echo "Insert Success: " . ($res ? "YES" : "NO") . "\n";
$del = $pdo->prepare("DELETE FROM users WHERE email = ?");
$del->execute([$testEmail]);
echo "Cleanup done.\n";

