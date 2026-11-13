<?php
// SplitMate API - Advanced Expense Filtering Engine

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$adminId = current_admin_id();
$currentUserId = current_user_id();
$role = current_role();
$pdo = db();

// Filter parameters
$search        = trim($_GET['search'] ?? '');
$filterUserId  = (int)($_GET['user_id'] ?? 0);
$category      = trim($_GET['category'] ?? '');
$minAmount     = isset($_GET['min_amount']) && $_GET['min_amount'] !== '' ? (float)$_GET['min_amount'] : null;
$maxAmount     = isset($_GET['max_amount']) && $_GET['max_amount'] !== '' ? (float)$_GET['max_amount'] : null;
$startDate     = trim($_GET['start_date'] ?? '');
$endDate       = trim($_GET['end_date'] ?? '');
$month         = trim($_GET['month'] ?? '');
$paymentMethod = trim($_GET['payment_method'] ?? '');
$sortBy        = trim($_GET['sort_by'] ?? 'newest');
$scope         = trim($_GET['scope'] ?? ($role === 'admin' ? 'admin' : 'user'));

// Base Query - Strictly Scoped to authenticated admin_id
$sql = "SELECT e.*, u.full_name as user_name, u.email as user_email 
        FROM expenses e 
        JOIN users u ON e.user_id = u.user_id 
        WHERE e.admin_id = ?";
$params = [$adminId];

// Scope constraint
if ($scope === 'user') {
    $sql .= " AND e.user_id = ?";
    $params[] = $currentUserId;
} elseif ($filterUserId > 0) {
    $sql .= " AND e.user_id = ?";
    $params[] = $filterUserId;
}

// Search by title or user name
if (!empty($search)) {
    $sql .= " AND (e.title LIKE ? OR u.full_name LIKE ? OR e.description LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Category
if (!empty($category)) {
    $sql .= " AND e.category = ?";
    $params[] = $category;
}

// Min Amount
if ($minAmount !== null) {
    $sql .= " AND e.amount >= ?";
    $params[] = $minAmount;
}

// Max Amount
if ($maxAmount !== null) {
    $sql .= " AND e.amount <= ?";
    $params[] = $maxAmount;
}

// Month filter (YYYY-MM)
if (!empty($month)) {
    $sql .= " AND DATE_FORMAT(e.expense_date, '%Y-%m') = ?";
    $params[] = $month;
}

// Date Range
if (!empty($startDate)) {
    $sql .= " AND e.expense_date >= ?";
    $params[] = $startDate;
}
if (!empty($endDate)) {
    $sql .= " AND e.expense_date <= ?";
    $params[] = $endDate;
}

// Payment Method
if (!empty($paymentMethod)) {
    $sql .= " AND e.payment_method = ?";
    $params[] = $paymentMethod;
}

// Sorting logic
switch ($sortBy) {
    case 'oldest':
        $sql .= " ORDER BY e.expense_date ASC, e.expense_time ASC, e.expense_id ASC";
        break;
    case 'amount_high':
        $sql .= " ORDER BY e.amount DESC, e.expense_date DESC";
        break;
    case 'amount_low':
        $sql .= " ORDER BY e.amount ASC, e.expense_date DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY e.expense_date DESC, e.expense_time DESC, e.expense_id DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$expenseIds = array_column($rows, 'expense_id');
$participantsMap = [];
if (!empty($expenseIds)) {
    $placeholders = implode(',', array_fill(0, count($expenseIds), '?'));
    $epStmt = $pdo->prepare("SELECT ep.expense_id, ep.user_id, ep.share_amount, u.full_name 
                             FROM expense_participants ep 
                             JOIN users u ON ep.user_id = u.user_id 
                             WHERE ep.expense_id IN ($placeholders) AND ep.admin_id = ?
                             ORDER BY u.full_name ASC");
    $epParams = array_merge($expenseIds, [$adminId]);
    $epStmt->execute($epParams);
    while ($epRow = $epStmt->fetch()) {
        $participantsMap[$epRow['expense_id']][] = $epRow;
    }
}

$avatarColors = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#3b82f6'];

$totalAmount = 0.00;
foreach ($rows as &$r) {
    $eid = $r['expense_id'];
    $parts = $participantsMap[$eid] ?? [];
    $r['participants'] = $parts;
    $r['participant_count'] = count($parts);

    // Calculate this user's share
    $myShare = 0.00;
    foreach ($parts as $p) {
        if ((int)$p['user_id'] === (int)$currentUserId) {
            $myShare = (float)$p['share_amount'];
            break;
        }
    }
    $r['my_share'] = $myShare;
    $r['formatted_my_share'] = format_currency($myShare);

    $totalAmount += (float)$r['amount'];
    $r['formatted_amount'] = format_currency($r['amount']);
    $r['formatted_date']   = format_date($r['expense_date']);
    $r['formatted_time']   = format_time($r['expense_time']);
    $r['can_manage']       = ($role === 'admin' || (int)$r['user_id'] === $currentUserId);
}
unset($r);

// Generate pre-rendered HTML table rows for dynamic AJAX table replacement
ob_start();
if (empty($rows)) {
    $colSpan = ($scope === 'user') ? 10 : (($scope === 'group' || $role !== 'admin') ? 10 : 9);
    ?>
    <tr>
        <td colspan="11" class="text-center py-4 text-muted">
            <i class="fa-solid fa-receipt fs-2 mb-2 d-block text-secondary"></i>
            No expenses found matching the selected criteria.
        </td>
    </tr>
    <?php
} else {
    foreach ($rows as $index => $exp) {
        $parts = $exp['participants'] ?? [];
        $splitType = $exp['split_type'] ?? 'everyone';
        $pCount = count($parts);
        ?>
        <tr id="expense-row-<?= (int)$exp['expense_id'] ?>">
            <td class="text-muted small"><?= $index + 1 ?></td>
            <td>
                <div class="fw-bold text-dark"><?= e($exp['title']) ?></div>
                <?php if (!empty($exp['description'])): ?>
                    <small class="text-muted text-truncate d-inline-block" style="max-width: 200px;" title="<?= e($exp['description']) ?>">
                        <?= e($exp['description']) ?>
                    </small>
                <?php endif; ?>
            </td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <div class="user-avatar-sm" style="width: 26px; height: 26px; font-size: 0.72rem;">
                        <?= strtoupper(substr($exp['user_name'], 0, 1)) ?>
                    </div>
                    <span class="fw-medium"><?= e($exp['user_name']) ?></span>
                </div>
            </td>
            <td>
                <span class="badge bg-light text-dark border">
                    <?= e($exp['category']) ?>
                </span>
            </td>
            <td class="fw-bold text-dark">
                <?= e($exp['formatted_amount']) ?>
            </td>
            <!-- Split Badge Column -->
            <td>
                <?php if ($splitType === 'everyone'): ?>
                    <span class="badge-everyone"><i class="fa-solid fa-globe me-1"></i>Everyone</span>
                <?php else: ?>
                    <span class="badge-selected"><i class="fa-solid fa-user-check me-1"></i><?= $pCount ?> members</span>
                <?php endif; ?>
            </td>
            <!-- Participants Avatars Column -->
            <td>
                <div class="participant-avatars" title="<?= e(implode(', ', array_column($parts, 'full_name'))) ?>">
                    <?php 
                    $shown = array_slice($parts, 0, 4);
                    foreach ($shown as $pIdx => $p): 
                        $color = $avatarColors[$p['user_id'] % count($avatarColors)];
                        $initial = strtoupper(substr($p['full_name'], 0, 1));
                    ?>
                        <span class="participant-avatar" style="background-color: <?= $color ?>;" title="<?= e($p['full_name']) ?> (<?= format_currency($p['share_amount']) ?>)">
                            <?= $initial ?>
                        </span>
                    <?php endforeach; ?>
                    <?php if ($pCount > 4): ?>
                        <span class="participant-avatar bg-secondary" title="<?= ($pCount - 4) ?> more participants">
                            +<?= $pCount - 4 ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="small text-muted text-truncate" style="max-width: 140px; font-size: 0.72rem;">
                    <?php
                    $names = [];
                    foreach ($parts as $p) {
                        $names[] = ((int)$p['user_id'] === $currentUserId) ? 'You' : e(explode(' ', trim($p['full_name']))[0]);
                    }
                    echo implode(', ', array_slice($names, 0, 3)) . (count($names) > 3 ? '...' : '');
                    ?>
                </div>
            </td>
            <!-- Your Share Column (shown for user scope or user viewing) -->
            <?php if ($role !== 'admin' || $scope === 'user'): ?>
            <td>
                <?php if ($exp['my_share'] > 0): ?>
                    <span class="fw-bold text-primary"><?= e($exp['formatted_my_share']) ?></span>
                <?php else: ?>
                    <span class="text-muted small">₹0.00</span>
                <?php endif; ?>
            </td>
            <?php endif; ?>
            <td>
                <div><?= e($exp['formatted_date']) ?></div>
                <small class="text-muted" style="font-size: 0.75rem;"><?= e($exp['formatted_time']) ?></small>
            </td>
            <td>
                <span class="badge bg-secondary-subtle text-secondary border">
                    <?= e($exp['payment_method']) ?>
                </span>
            </td>
            <td class="text-end no-export">
                <?php if ($exp['can_manage']): ?>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openEditExpenseModal(<?= (int)$exp['expense_id'] ?>)" title="Edit Expense">
                            <i class="fa-solid fa-pen-to-square text-primary"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="confirmDeleteExpense(<?= (int)$exp['expense_id'] ?>, '<?= e(addslashes($exp['title'])) ?>')" title="Delete Expense">
                            <i class="fa-solid fa-trash text-danger"></i>
                        </button>
                    </div>
                <?php else: ?>
                    <span class="text-muted small"><i class="fa-solid fa-lock"></i> View Only</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }
}
$htmlOutput = ob_get_clean();

echo json_encode([
    'success'      => true,
    'count'        => count($rows),
    'total_amount' => $totalAmount,
    'formatted_total' => format_currency($totalAmount),
    'html'         => $htmlOutput,
    'raw_data'     => $rows
]);

