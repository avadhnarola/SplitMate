<?php
define('SPLITMATE_APP', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = db();

echo "=== TEST: Sync settlements for Admin #1 ===\n";
$matrix = sync_settlements_to_database(1);

echo "Group Total: " . format_currency($matrix['total_group_expense']) . "\n";
echo "Fair Share:  " . format_currency($matrix['fair_share']) . "\n";
echo "Members:     " . $matrix['active_members_count'] . "\n\n";

echo "Settlement Instructions:\n";
foreach ($matrix['settlement_instructions'] as $i => $inst) {
    echo "  " . ($i+1) . ". {$inst['payer_name']} → {$inst['receiver_name']}: " . format_currency($inst['amount']) . "\n";
}

echo "\n=== DB Rows in settlements table (admin_id=1) ===\n";
$rows = $pdo->query("SELECT s.settlement_id, p.full_name as payer, r.full_name as receiver, s.amount, s.status FROM settlements s JOIN users p ON s.payer_id=p.user_id JOIN users r ON s.receiver_id=r.user_id WHERE s.admin_id=1")->fetchAll();
foreach ($rows as $row) {
    echo "  ID#{$row['settlement_id']}: {$row['payer']} → {$row['receiver']} ₹{$row['amount']} [{$row['status']}]\n";
}

if (empty($rows)) {
    echo "  (no rows — likely no expenses yet for admin 1)\n";
}

echo "\nDone.\n";

