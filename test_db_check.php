<?php
define('SPLITMATE_APP', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$pdo = db();

echo "=== ADMINS ===\n";
$rows = $pdo->query("SELECT admin_id, full_name, email, status FROM admins")->fetchAll();
foreach ($rows as $r) echo "  Admin #{$r['admin_id']}: {$r['full_name']} <{$r['email']}> [{$r['status']}]\n";
if (empty($rows)) echo "  (none)\n";

echo "\n=== USERS ===\n";
$rows = $pdo->query("SELECT user_id, admin_id, full_name, email, status, email_verified FROM users")->fetchAll();
foreach ($rows as $r) echo "  User #{$r['user_id']} (admin={$r['admin_id']}): {$r['full_name']} <{$r['email']}> status={$r['status']} verified={$r['email_verified']}\n";
if (empty($rows)) echo "  (none)\n";

echo "\n=== EXPENSES ===\n";
$rows = $pdo->query("SELECT expense_id, admin_id, user_id, title, amount FROM expenses LIMIT 10")->fetchAll();
foreach ($rows as $r) echo "  Exp #{$r['expense_id']} (admin={$r['admin_id']}, user={$r['user_id']}): {$r['title']} ₹{$r['amount']}\n";
if (empty($rows)) echo "  (none)\n";

