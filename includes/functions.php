<?php
// SplitMate Helper Functions and Business Logic

require_once __DIR__ . '/../config/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Sanitize and escape HTML string
 */
function e($str): string {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency with Indian Rupee symbol
 */
function format_currency($amount, $showDecimals = true): string {
    $amount = (float)$amount;
    if ($showDecimals) {
        return '₹' . number_format($amount, 2, '.', ',');
    }
    return '₹' . number_format($amount, 0, '.', ',');
}

/**
 * Format date for display
 */
function format_date($dateStr): string {
    if (empty($dateStr)) return '-';
    $time = strtotime($dateStr);
    return date('d M Y', $time);
}

/**
 * Format time for display
 */
function format_time($timeStr): string {
    if (empty($timeStr)) return '-';
    $time = strtotime($timeStr);
    return date('h:i A', $time);
}

/**
 * Log activity in database
 */
function log_activity($admin_id, $user_id, $activity): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = db()->prepare("INSERT INTO activity_logs (admin_id, user_id, activity, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$admin_id, $user_id, $activity, $ip]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Simple session/IP rate limiter for sensitive actions (e.g. login, OTP)
 */
function check_rate_limit(string $key, int $maxAttempts = 5, int $decaySeconds = 300): bool {
    $sessionKey = 'rate_limit_' . $key;
    $now = time();
    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = ['attempts' => 1, 'first_attempt' => $now];
        return true;
    }

    $entry = &$_SESSION[$sessionKey];
    if ($now - $entry['first_attempt'] > $decaySeconds) {
        $entry['attempts'] = 1;
        $entry['first_attempt'] = $now;
        return true;
    }

    if ($entry['attempts'] >= $maxAttempts) {
        return false;
    }

    $entry['attempts']++;
    return true;
}

/**
 * Clear rate limit on successful action
 */
function reset_rate_limit(string $key): void {
    unset($_SESSION['rate_limit_' . $key]);
}

/**
 * Send OTP via PHPMailer with fallback for local development
 */
function send_otp_email(string $recipientEmail, string $recipientName, string $otp): array {
    $mail = new PHPMailer(true);
    $smtpHost = env('SMTP_HOST', 'smtp.gmail.com');
    $smtpPort = (int)env('SMTP_PORT', 587);
    $smtpUser = env('SMTP_USER', '');
    $smtpPass = env('SMTP_PASS', '');
    $smtpEnc  = env('SMTP_ENCRYPTION', 'tls');
    $fromEmail = env('SMTP_FROM_EMAIL', 'no-reply@splitmate.com');
    $fromName = env('SMTP_FROM_NAME', 'SplitMate System');

    $htmlBody = '
    <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;">
        <div style="text-align: center; padding-bottom: 20px; border-bottom: 1px solid #edf2f7;">
            <h1 style="color: #4338ca; margin: 0; font-size: 26px; font-weight: 800; letter-spacing: -0.5px;">Split<span style="color: #10b981;">Mate</span></h1>
            <p style="color: #64748b; font-size: 14px; margin-top: 4px;">Smart Multi-Admin Expense Management</p>
        </div>
        <div style="padding: 24px 0;">
            <p style="font-size: 16px; color: #1e293b; margin-top: 0;">Hello <strong>' . e($recipientName) . '</strong>,</p>
            <p style="font-size: 15px; color: #475569; line-height: 1.6;">Thank you for registering on SplitMate. Please use the following 6-digit One-Time Password (OTP) to verify your email address and activate your account:</p>
            <div style="margin: 32px 0; text-align: center;">
                <span style="display: inline-block; font-size: 34px; font-weight: 800; letter-spacing: 8px; color: #4338ca; background-color: #eef2ff; padding: 14px 28px; border-radius: 10px; border: 1px dashed #6366f1;">' . e($otp) . '</span>
            </div>
            <p style="font-size: 14px; color: #ef4444; font-weight: 600; text-align: center;">⏰ This OTP will expire in 5 minutes.</p>
            <p style="font-size: 14px; color: #64748b; line-height: 1.5;">If you did not initiate this registration, please disregard this email. Never share your OTP with anyone.</p>
        </div>
        <div style="padding-top: 20px; border-top: 1px solid #edf2f7; text-align: center; color: #94a3b8; font-size: 12px;">
            <p style="margin: 0;">&copy; ' . date('Y') . ' SplitMate. All rights reserved.</p>
        </div>
    </div>';

    // If SMTP credentials are provided, attempt real sending
    if (!empty($smtpUser) && !empty($smtpPass)) {
        try {
            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->SMTPSecure = ($smtpEnc === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtpPort;

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($recipientEmail, $recipientName);

            $mail->isHTML(true);
            $mail->Subject = 'Your SplitMate Verification Code: ' . $otp;
            $mail->Body    = $htmlBody;
            $mail->AltBody = "Hello $recipientName,\n\nYour SplitMate verification OTP is: $otp. It will expire in 5 minutes.\n\nThank you.";

            $mail->send();
            return ['success' => true, 'message' => 'OTP sent to your email address.'];
        } catch (Exception $e) {
            error_log("PHPMailer SMTP Error: " . $mail->ErrorInfo);
            if (DEV_MODE) {
                return ['success' => true, 'message' => "OTP generated (Dev Mode): SMTP failed ({$mail->ErrorInfo}), but OTP recorded.", 'dev_otp' => $otp];
            }
            return ['success' => false, 'message' => 'Failed to send OTP email: ' . $mail->ErrorInfo];
        }
    }

    // Dev mode fallback when credentials are not configured yet
    if (DEV_MODE) {
        return ['success' => true, 'message' => 'OTP generated successfully (Local Dev Mode).', 'dev_otp' => $otp];
    }

    return ['success' => false, 'message' => 'SMTP server is not configured in .env'];
}

/**
 * =========================================================================
 * CORE SETTLEMENT ALGORITHM: GREEDY DEBT SETTLEMENT
 * =========================================================================
 * Calculates exact equal split across active group members of an admin.
 * Uses integer paise to completely eliminate floating-point rounding errors.
 */
function calculate_settlement_matrix(int $admin_id, ?string $period_start = null, ?string $period_end = null): array {
    $pdo = db();

    // 1. Fetch all active users in this admin's group
    $userStmt = $pdo->prepare("SELECT user_id, full_name, email, phone FROM users WHERE admin_id = ? AND status = 'active' ORDER BY user_id ASC");
    $userStmt->execute([$admin_id]);
    $activeUsers = $userStmt->fetchAll();

    $memberCount = count($activeUsers);
    if ($memberCount === 0) {
        return [
            'total_group_expense' => 0.00,
            'active_members_count' => 0,
            'fair_share' => 0.00,
            'user_balances' => [],
            'settlement_instructions' => [],
            'total_receivable' => 0.00,
            'total_payable' => 0.00
        ];
    }

    // 2. Total Group Expense & Expense Count per user
    $paidSql = "SELECT user_id, COALESCE(SUM(amount), 0) as total_paid, COUNT(expense_id) as expense_count 
                FROM expenses 
                WHERE admin_id = ?";
    $paidParams = [$admin_id];

    if (!empty($period_start) && !empty($period_end)) {
        $paidSql .= " AND expense_date BETWEEN ? AND ?";
        $paidParams[] = $period_start;
        $paidParams[] = $period_end;
    }
    $paidSql .= " GROUP BY user_id";

    $paidStmt = $pdo->prepare($paidSql);
    $paidStmt->execute($paidParams);
    $paidMap = [];

    while ($row = $paidStmt->fetch()) {
        $paidPaise = (int)round((float)$row['total_paid'] * 100);
        $paidMap[$row['user_id']] = [
            'total_paid' => (float)$row['total_paid'],
            'total_paid_paise' => $paidPaise,
            'expense_count' => (int)$row['expense_count']
        ];
    }

    // Total Group Expense across all active users / expenses for this admin
    $totalExpSql = "SELECT COALESCE(SUM(amount), 0) as total_amount FROM expenses WHERE admin_id = ?";
    $totalExpParams = [$admin_id];
    if (!empty($period_start) && !empty($period_end)) {
        $totalExpSql .= " AND expense_date BETWEEN ? AND ?";
        $totalExpParams[] = $period_start;
        $totalExpParams[] = $period_end;
    }
    $totStmt = $pdo->prepare($totalExpSql);
    $totStmt->execute($totalExpParams);
    $totalGroupExpense = (float)($totStmt->fetchColumn() ?: 0.00);

    // Fair share (for display) = Total Group Expenses / Total Active Members
    $fairShare = ($memberCount > 0) ? round($totalGroupExpense / $memberCount, 2) : 0.00;

    // 3. Total Owed per user from expense_participants
    $owedSql = "SELECT ep.user_id, COALESCE(SUM(ep.share_amount), 0) as total_owed 
                FROM expense_participants ep 
                JOIN expenses e ON ep.expense_id = e.expense_id 
                WHERE ep.admin_id = ?";
    $owedParams = [$admin_id];

    if (!empty($period_start) && !empty($period_end)) {
        $owedSql .= " AND e.expense_date BETWEEN ? AND ?";
        $owedParams[] = $period_start;
        $owedParams[] = $period_end;
    }
    $owedSql .= " GROUP BY ep.user_id";

    $owedStmt = $pdo->prepare($owedSql);
    $owedStmt->execute($owedParams);
    $owedMap = [];

    while ($row = $owedStmt->fetch()) {
        $owedPaise = (int)round((float)$row['total_owed'] * 100);
        $owedMap[$row['user_id']] = [
            'total_owed' => (float)$row['total_owed'],
            'total_owed_paise' => $owedPaise
        ];
    }

    // 3b. Factor in completed settlements (status = 'paid')
    // Settlements already transferred reduce debts and receivables
    $settledPaidSql = "SELECT payer_id, COALESCE(SUM(amount), 0) as total_settled_paid 
                       FROM settlements 
                       WHERE admin_id = ? AND status = 'paid'";
    $settledPaidParams = [$admin_id];
    if (!empty($period_start) && !empty($period_end)) {
        $settledPaidSql .= " AND DATE(paid_at) BETWEEN ? AND ?";
        $settledPaidParams[] = $period_start;
        $settledPaidParams[] = $period_end;
    }
    $settledPaidSql .= " GROUP BY payer_id";

    $spStmt = $pdo->prepare($settledPaidSql);
    $spStmt->execute($settledPaidParams);
    $settledPaidMap = [];
    while ($row = $spStmt->fetch()) {
        $paise = (int)round((float)$row['total_settled_paid'] * 100);
        $settledPaidMap[$row['payer_id']] = [
            'total_settled_paid' => (float)$row['total_settled_paid'],
            'total_settled_paid_paise' => $paise
        ];
    }

    $settledRecvSql = "SELECT receiver_id, COALESCE(SUM(amount), 0) as total_settled_received 
                       FROM settlements 
                       WHERE admin_id = ? AND status = 'paid'";
    $settledRecvParams = [$admin_id];
    if (!empty($period_start) && !empty($period_end)) {
        $settledRecvSql .= " AND DATE(paid_at) BETWEEN ? AND ?";
        $settledRecvParams[] = $period_start;
        $settledRecvParams[] = $period_end;
    }
    $settledRecvSql .= " GROUP BY receiver_id";

    $srStmt = $pdo->prepare($settledRecvSql);
    $srStmt->execute($settledRecvParams);
    $settledRecvMap = [];
    while ($row = $srStmt->fetch()) {
        $paise = (int)round((float)$row['total_settled_received'] * 100);
        $settledRecvMap[$row['receiver_id']] = [
            'total_settled_received' => (float)$row['total_settled_received'],
            'total_settled_received_paise' => $paise
        ];
    }

    // 4. Calculate individual balances in paise
    // Net balance = (Total Expense Paid - Total Expense Owed) + Settled Paid - Settled Received
    $userBalances = [];
    $creditors = [];
    $debtors = [];
    $sumOfBalancesPaise = 0;

    foreach ($activeUsers as $u) {
        $uid = (int)$u['user_id'];
        $paid = $paidMap[$uid]['total_paid'] ?? 0.00;
        $paidPaise = $paidMap[$uid]['total_paid_paise'] ?? 0;
        $count = $paidMap[$uid]['expense_count'] ?? 0;

        $owed = $owedMap[$uid]['total_owed'] ?? 0.00;
        $owedPaise = $owedMap[$uid]['total_owed_paise'] ?? 0;

        $settledPaid = $settledPaidMap[$uid]['total_settled_paid'] ?? 0.00;
        $settledPaidPaise = $settledPaidMap[$uid]['total_settled_paid_paise'] ?? 0;

        $settledRecv = $settledRecvMap[$uid]['total_settled_received'] ?? 0.00;
        $settledRecvPaise = $settledRecvMap[$uid]['total_settled_received_paise'] ?? 0;

        // Raw initial debt balance based solely on recorded expenses
        $initialBalancePaise = $paidPaise - $owedPaise;

        // Net remaining balance: paying a settlement reduces debt (+), receiving money satisfies credit (-)
        $netBalancePaise = $initialBalancePaise + $settledPaidPaise - $settledRecvPaise;
        $sumOfBalancesPaise += $netBalancePaise;

        $userBalances[$uid] = [
            'user_id' => $uid,
            'full_name' => $u['full_name'],
            'email' => $u['email'],
            'phone' => $u['phone'],
            'total_spent' => $paid,
            'total_spent_paise' => $paidPaise,
            'total_paid' => $paid,
            'total_paid_paise' => $paidPaise,
            'total_owed' => $owed,
            'total_owed_paise' => $owedPaise,
            'settled_paid' => $settledPaid,
            'settled_paid_paise' => $settledPaidPaise,
            'settled_received' => $settledRecv,
            'settled_received_paise' => $settledRecvPaise,
            'initial_balance' => (float)($initialBalancePaise / 100),
            'initial_balance_paise' => $initialBalancePaise,
            'expense_count' => $count,
            'fair_share' => $fairShare,
            'net_balance' => (float)($netBalancePaise / 100),
            'net_balance_paise' => $netBalancePaise,
            'status' => ($netBalancePaise > 0) ? 'receive' : (($netBalancePaise < 0) ? 'pay' : 'settled')
        ];
    }

    // Rounding correction logic: sum must = 0
    if ($sumOfBalancesPaise !== 0) {
        $highestSpenderId = null;
        $maxSpent = -1;
        foreach ($userBalances as $uid => $b) {
            if ($b['total_spent_paise'] > $maxSpent) {
                $maxSpent = $b['total_spent_paise'];
                $highestSpenderId = $uid;
            }
        }
        if ($highestSpenderId !== null) {
            $userBalances[$highestSpenderId]['net_balance_paise'] -= $sumOfBalancesPaise;
            $userBalances[$highestSpenderId]['net_balance'] = (float)($userBalances[$highestSpenderId]['net_balance_paise'] / 100);
            if ($userBalances[$highestSpenderId]['net_balance_paise'] > 0) {
                $userBalances[$highestSpenderId]['status'] = 'receive';
            } elseif ($userBalances[$highestSpenderId]['net_balance_paise'] < 0) {
                $userBalances[$highestSpenderId]['status'] = 'pay';
            } else {
                $userBalances[$highestSpenderId]['status'] = 'settled';
            }
        }
    }

    // Separate Creditors and Debtors
    $totalReceivable = 0.00;
    $totalPayable = 0.00;

    foreach ($userBalances as $b) {
        if ($b['net_balance_paise'] > 0) {
            $creditors[] = [
                'user_id' => $b['user_id'],
                'full_name' => $b['full_name'],
                'balance_paise' => $b['net_balance_paise']
            ];
            $totalReceivable += $b['net_balance'];
        } elseif ($b['net_balance_paise'] < 0) {
            $debtors[] = [
                'user_id' => $b['user_id'],
                'full_name' => $b['full_name'],
                'balance_paise' => $b['net_balance_paise'] // negative
            ];
            $totalPayable += abs($b['net_balance']);
        }
    }

    // Sort creditors descending (largest credit first)
    usort($creditors, fn($a, $b) => $b['balance_paise'] <=> $a['balance_paise']);
    // Sort debtors ascending (largest debt first, most negative)
    usort($debtors, fn($a, $b) => $a['balance_paise'] <=> $b['balance_paise']);

    // 5. Greedy matching to minimize transactions
    $settlementInstructions = [];
    $cIdx = 0;
    $dIdx = 0;
    $cCount = count($creditors);
    $dCount = count($debtors);

    while ($cIdx < $cCount && $dIdx < $dCount) {
        $debtor = &$debtors[$dIdx];
        $creditor = &$creditors[$cIdx];

        $debtPaise = abs($debtor['balance_paise']);
        $creditPaise = $creditor['balance_paise'];

        $settlePaise = min($debtPaise, $creditPaise);
        if ($settlePaise > 0) {
            $settlementInstructions[] = [
                'payer_id' => $debtor['user_id'],
                'payer_name' => $debtor['full_name'],
                'receiver_id' => $creditor['user_id'],
                'receiver_name' => $creditor['full_name'],
                'amount' => (float)($settlePaise / 100),
                'amount_paise' => $settlePaise
            ];

            $debtor['balance_paise'] += $settlePaise;
            $creditor['balance_paise'] -= $settlePaise;
        }

        if ($debtor['balance_paise'] === 0) {
            $dIdx++;
        }
        if ($creditor['balance_paise'] === 0) {
            $cIdx++;
        }
    }

    return [
        'total_group_expense' => $totalGroupExpense,
        'active_members_count' => $memberCount,
        'fair_share' => $fairShare,
        'user_balances' => $userBalances,
        'settlement_instructions' => $settlementInstructions,
        'total_receivable' => $totalReceivable,
        'total_payable' => $totalPayable
    ];
}

/**
 * Sync calculated settlements into the database settlements table.
 * Preserves completed payments and updates/creates pending ones.
 */
function sync_settlements_to_database(int $admin_id, ?string $period_start = null, ?string $period_end = null): array {
    $matrix = calculate_settlement_matrix($admin_id, $period_start, $period_end);
    $instructions = $matrix['settlement_instructions'];
    $pdo = db();

    // Fetch existing settlements for this admin & period
    $sql = "SELECT * FROM settlements WHERE admin_id = ?";
    $params = [$admin_id];
    if ($period_start && $period_end) {
        $sql .= " AND period_start = ? AND period_end = ?";
        $params[] = $period_start;
        $params[] = $period_end;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $existing = $stmt->fetchAll();

    // Map existing pending records by "payer_id:receiver_id"
    $existingPendingMap = [];
    foreach ($existing as $row) {
        if ($row['status'] === 'pending') {
            $pairKey = $row['payer_id'] . ':' . $row['receiver_id'];
            $existingPendingMap[$pairKey] = $row;
        }
    }

    $matchedKeys = [];

    foreach ($instructions as $inst) {
        $pairKey = $inst['payer_id'] . ':' . $inst['receiver_id'];
        $matchedKeys[$pairKey] = true;

        if (isset($existingPendingMap[$pairKey])) {
            // Update amount if changed
            $existingId = $existingPendingMap[$pairKey]['settlement_id'];
            if ((float)$existingPendingMap[$pairKey]['amount'] !== (float)$inst['amount']) {
                $upStmt = $pdo->prepare("UPDATE settlements SET amount = ?, updated_at = NOW() WHERE settlement_id = ?");
                $upStmt->execute([$inst['amount'], $existingId]);
            }
        } else {
            // Insert new pending settlement
            $inStmt = $pdo->prepare("INSERT INTO settlements (admin_id, payer_id, receiver_id, amount, period_start, period_end, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $inStmt->execute([
                $admin_id,
                $inst['payer_id'],
                $inst['receiver_id'],
                $inst['amount'],
                $period_start,
                $period_end
            ]);
        }
    }

    // Clean up stale pending settlements that are no longer in instructions
    foreach ($existingPendingMap as $pairKey => $row) {
        if (!isset($matchedKeys[$pairKey])) {
            $delStmt = $pdo->prepare("DELETE FROM settlements WHERE settlement_id = ?");
            $delStmt->execute([$row['settlement_id']]);
        }
    }

    return $matrix;
}

/**
 * Fetch all categories
 */
function get_categories(): array {
    $stmt = db()->query("SELECT * FROM categories ORDER BY name ASC");
    return $stmt->fetchAll();
}

/**
 * Get Admin Dashboard Statistics
 */
function get_admin_dashboard_stats(int $admin_id): array {
    $pdo = db();

    // 1. Total Group Expenses & Total Entries
    $expStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_amount, COUNT(expense_id) as total_count FROM expenses WHERE admin_id = ?");
    $expStmt->execute([$admin_id]);
    $expStats = $expStmt->fetch();
    $totalExpenses = (float)$expStats['total_amount'];
    $totalEntries = (int)$expStats['total_count'];

    // 2. Total Number of Users
    $userStmt = $pdo->prepare("SELECT COUNT(user_id) as total_users FROM users WHERE admin_id = ? AND status = 'active'");
    $userStmt->execute([$admin_id]);
    $totalUsers = (int)$userStmt->fetch()['total_users'];

    // 3. Average Expense Per User
    $avgPerUser = ($totalUsers > 0) ? ($totalExpenses / $totalUsers) : 0.00;

    // 4. Highest Spending User
    $highStmt = $pdo->prepare("SELECT u.full_name, COALESCE(SUM(e.amount), 0) as user_total 
                               FROM users u 
                               JOIN expenses e ON u.user_id = e.user_id 
                               WHERE e.admin_id = ? 
                               GROUP BY u.user_id, u.full_name 
                               ORDER BY user_total DESC 
                               LIMIT 1");
    $highStmt->execute([$admin_id]);
    $highestUser = $highStmt->fetch() ?: ['full_name' => 'None', 'user_total' => 0.00];

    // 5. Pending Settlements Count & Sum
    $settleStmt = $pdo->prepare("SELECT COUNT(settlement_id) as pending_count, COALESCE(SUM(amount), 0) as pending_amount FROM settlements WHERE admin_id = ? AND status = 'pending'");
    $settleStmt->execute([$admin_id]);
    $settleStats = $settleStmt->fetch();

    // 6. Recent Expenses
    $recentStmt = $pdo->prepare("SELECT e.*, u.full_name as user_name 
                                 FROM expenses e 
                                 JOIN users u ON e.user_id = u.user_id 
                                 WHERE e.admin_id = ? 
                                 ORDER BY e.expense_date DESC, e.expense_time DESC, e.expense_id DESC 
                                 LIMIT 7");
    $recentStmt->execute([$admin_id]);
    $recentExpenses = $recentStmt->fetchAll();

    // 7. Monthly Expenses (Last 6 Months)
    $monthStmt = $pdo->prepare("SELECT DATE_FORMAT(expense_date, '%b %Y') as month_label, 
                                       COALESCE(SUM(amount), 0) as monthly_total,
                                       DATE_FORMAT(expense_date, '%Y-%m') as sort_key
                                FROM expenses 
                                WHERE admin_id = ? AND expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                                GROUP BY sort_key, month_label 
                                ORDER BY sort_key ASC");
    $monthStmt->execute([$admin_id]);
    $monthlyExpenses = $monthStmt->fetchAll();

    // 8. Category-wise Expenses
    $catStmt = $pdo->prepare("SELECT category, COALESCE(SUM(amount), 0) as category_total, COUNT(expense_id) as count 
                              FROM expenses 
                              WHERE admin_id = ? 
                              GROUP BY category 
                              ORDER BY category_total DESC");
    $catStmt->execute([$admin_id]);
    $categoryExpenses = $catStmt->fetchAll();

    // 9. User-wise Spending Comparison
    $userCompStmt = $pdo->prepare("SELECT u.full_name, COALESCE(SUM(e.amount), 0) as total_spent 
                                   FROM users u 
                                   LEFT JOIN expenses e ON u.user_id = e.user_id AND e.admin_id = ? 
                                   WHERE u.admin_id = ? AND u.status = 'active'
                                   GROUP BY u.user_id, u.full_name 
                                   ORDER BY total_spent DESC");
    $userCompStmt->execute([$admin_id, $admin_id]);
    $userComparison = $userCompStmt->fetchAll();

    // 10. Daily Expense Trends (Last 14 days)
    $dailyStmt = $pdo->prepare("SELECT DATE_FORMAT(expense_date, '%d %b') as day_label, 
                                       COALESCE(SUM(amount), 0) as daily_total,
                                       expense_date
                                FROM expenses 
                                WHERE admin_id = ? AND expense_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                                GROUP BY expense_date, day_label 
                                ORDER BY expense_date ASC");
    $dailyStmt->execute([$admin_id]);
    $dailyTrends = $dailyStmt->fetchAll();

    return [
        'total_expenses' => $totalExpenses,
        'total_users' => $totalUsers,
        'total_entries' => $totalEntries,
        'avg_per_user' => $avgPerUser,
        'highest_user' => $highestUser,
        'pending_settlements_count' => (int)$settleStats['pending_count'],
        'pending_settlements_amount' => (float)$settleStats['pending_amount'],
        'recent_expenses' => $recentExpenses,
        'monthly_expenses' => $monthlyExpenses,
        'category_expenses' => $categoryExpenses,
        'user_comparison' => $userComparison,
        'daily_trends' => $dailyTrends
    ];
}

/**
 * Get User Dashboard Statistics
 */
function get_user_dashboard_stats(int $user_id, int $admin_id): array {
    $pdo = db();

    // User Total Spending
    $uStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as my_total, COUNT(expense_id) as my_count FROM expenses WHERE user_id = ? AND admin_id = ?");
    $uStmt->execute([$user_id, $admin_id]);
    $uData = $uStmt->fetch();
    $myTotal = (float)$uData['my_total'];
    $myCount = (int)$uData['my_count'];

    // Group Settlement Matrix (calculates group total, fair share, net balance)
    $matrix = calculate_settlement_matrix($admin_id);
    $groupTotal = $matrix['total_group_expense'];
    $myFairShare = $matrix['fair_share'];
    $myBalanceData = $matrix['user_balances'][$user_id] ?? null;
    $myBalance = $myBalanceData ? $myBalanceData['net_balance'] : 0.00;

    // Recent Personal Expenses
    $recentStmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = ? AND admin_id = ? ORDER BY expense_date DESC, expense_time DESC LIMIT 6");
    $recentStmt->execute([$user_id, $admin_id]);
    $recentExpenses = $recentStmt->fetchAll();

    // Pending settlements involving this user
    $settleStmt = $pdo->prepare("SELECT s.*, p.full_name as payer_name, r.full_name as receiver_name 
                                 FROM settlements s 
                                 JOIN users p ON s.payer_id = p.user_id 
                                 JOIN users r ON s.receiver_id = r.user_id 
                                 WHERE s.admin_id = ? AND (s.payer_id = ? OR s.receiver_id = ?) 
                                 ORDER BY s.status ASC, s.created_at DESC");
    $settleStmt->execute([$admin_id, $user_id, $user_id]);
    $mySettlements = $settleStmt->fetchAll();

    return [
        'my_total' => $myTotal,
        'my_count' => $myCount,
        'group_total' => $groupTotal,
        'my_fair_share' => $myFairShare,
        'my_balance' => $myBalance,
        'recent_expenses' => $recentExpenses,
        'settlements' => $mySettlements
    ];
}

/**
 * Get participants for a specific expense (scoped to admin_id for security)
 */
function get_expense_participants(int $expense_id, int $admin_id): array {
    $stmt = db()->prepare(
        "SELECT ep.user_id, u.full_name, ep.share_amount
         FROM expense_participants ep
         JOIN users u ON ep.user_id = u.user_id
         WHERE ep.expense_id = ? AND ep.admin_id = ?
         ORDER BY u.full_name ASC"
    );
    $stmt->execute([$expense_id, $admin_id]);
    return $stmt->fetchAll();
}

/**
 * Save participants for an expense. Validates user ownership, recalculates shares.
 * Must be called inside a transaction.
 */
function save_expense_participants(int $expense_id, int $admin_id, array $participant_user_ids, float $total_amount): void {
    $pdo = db();

    // Validate: all participant IDs must belong to this admin
    if (empty($participant_user_ids)) {
        throw new \InvalidArgumentException('At least one participant is required.');
    }
    $placeholders = implode(',', array_fill(0, count($participant_user_ids), '?'));
    $valid = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id IN ($placeholders) AND admin_id = ?");
    $valid->execute(array_merge($participant_user_ids, [$admin_id]));
    $validCount = (int)$valid->fetchColumn();
    if ($validCount !== count($participant_user_ids)) {
        throw new \InvalidArgumentException('One or more participants do not belong to this admin group.');
    }

    // Delete existing participant rows for this expense
    $del = $pdo->prepare("DELETE FROM expense_participants WHERE expense_id = ?");
    $del->execute([$expense_id]);

    // Calculate shares using integer paise for exactness
    $count = count($participant_user_ids);
    $totalPaise = (int)round($total_amount * 100);
    $basePaise = intdiv($totalPaise, $count);
    $remainder = $totalPaise - ($basePaise * $count);

    $ins = $pdo->prepare("INSERT INTO expense_participants (expense_id, user_id, admin_id, share_amount) VALUES (?, ?, ?, ?)");
    foreach (array_values($participant_user_ids) as $idx => $uid) {
        $sharePaise = $basePaise + ($idx < $remainder ? 1 : 0);
        $ins->execute([$expense_id, (int)$uid, $admin_id, round($sharePaise / 100, 2)]);
    }
}


