<?php
require_once __DIR__ . '/includes/functions.php';

$res = calculate_settlement_matrix(1);
echo "=== SETTLEMENT ALGORITHM TEST ===\n";
echo "Total Group Expenses: " . $res['total_group_expense'] . "\n";
echo "Active Members: " . $res['active_members_count'] . "\n";
echo "Fair Share: " . $res['fair_share'] . "\n";
echo "--- User Balances ---\n";
foreach ($res['user_balances'] as $b) {
    echo "{$b['full_name']}: Spent: {$b['total_spent']}, Balance: {$b['net_balance']} ({$b['status']})\n";
}
echo "--- Settlement Instructions ---\n";
foreach ($res['settlement_instructions'] as $inst) {
    echo "{$inst['payer_name']} pays {$inst['receiver_name']} ₹{$inst['amount']}\n";
}

