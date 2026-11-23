<?php
// ===================================================
// Queue History & Advanced Search/Filtering (Admin)
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$pdo = getDBConnection();
$pageTitle = 'Queue History & Reports';

// Handle clear all queue data action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_all_queues') {
    $pdo->exec("DELETE FROM queues");
    setFlash('success', 'All queue history records have been cleared. System is now fresh.');
    header("Location: history.php");
    exit;
}

// Filters
$keyword   = trim($_GET['keyword'] ?? '');
$status    = trim($_GET['status'] ?? '');
$counterId = !empty($_GET['counter_id']) ? intval($_GET['counter_id']) : 0;
$service   = trim($_GET['service'] ?? '');
$startDate = trim($_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days')));
$endDate   = trim($_GET['end_date'] ?? date('Y-m-d'));

// Build SQL query safely with prepared statements
$where = ["1=1"];
$params = [];

if (!empty($keyword)) {
    $where[] = "(q.queue_number LIKE ? OR q.customer_name LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}

if (!empty($status)) {
    $where[] = "q.status = ?";
    $params[] = $status;
}

if ($counterId > 0) {
    $where[] = "q.counter_id = ?";
    $params[] = $counterId;
}

if (!empty($service)) {
    $where[] = "q.service_type = ?";
    $params[] = $service;
}

if (!empty($startDate)) {
    $where[] = "DATE(q.created_at) >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $where[] = "DATE(q.created_at) <= ?";
    $params[] = $endDate;
}

$whereSql = implode(" AND ", $where);

// Summary metrics under current filter
$summarySql = "SELECT 
    COUNT(*) as total_count,
    SUM(CASE WHEN q.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
    SUM(CASE WHEN q.status = 'skipped' THEN 1 ELSE 0 END) as skipped_count,
    SUM(CASE WHEN q.status = 'waiting' THEN 1 ELSE 0 END) as waiting_count
    FROM queues q WHERE $whereSql";
$sumStmt = $pdo->prepare($summarySql);
$sumStmt->execute($params);
$summary = $sumStmt->fetch();

// Main records query
$sql = "
    SELECT q.*, c.counter_number, u.full_name AS staff_name
    FROM queues q
    LEFT JOIN counters c ON q.counter_id = c.id
    LEFT JOIN users u ON q.staff_id = u.id
    WHERE $whereSql
    ORDER BY q.id DESC
    LIMIT 200
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$historyRecords = $stmt->fetchAll();

// Dropdown data
$allCounters = $pdo->query("SELECT id, counter_number FROM counters ORDER BY counter_number ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo getIcon('history', 26); ?> Queue History & Reports</h1>
        <p class="page-desc">Search, filter, and audit all historical queue customer transactions</p>
    </div>
    <div>
        <form method="POST" action="history.php" onsubmit="return confirm('Are you sure you want to delete ALL historical queue records? This will clear all data and start completely fresh.');" style="display:inline;">
            <input type="hidden" name="action" value="clear_all_queues">
            <button type="submit" class="btn btn-danger btn-sm">
                <?php echo getIcon('trash', 14); ?> Clear All History Records
            </button>
        </form>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card" style="margin-bottom: 1.5rem;">
    <form method="GET" action="history.php">
        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label" for="keyword">Search Keyword</label>
                <input type="text" id="keyword" name="keyword" class="form-control" placeholder="Queue Number or Customer..." value="<?php echo e($keyword); ?>">
            </div>

            <div class="form-col form-group">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="">-- All Statuses --</option>
                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="skipped" <?php echo $status === 'skipped' ? 'selected' : ''; ?>>Skipped</option>
                    <option value="serving" <?php echo $status === 'serving' ? 'selected' : ''; ?>>Serving</option>
                    <option value="calling" <?php echo $status === 'calling' ? 'selected' : ''; ?>>Calling</option>
                    <option value="waiting" <?php echo $status === 'waiting' ? 'selected' : ''; ?>>Waiting</option>
                </select>
            </div>

            <div class="form-col form-group">
                <label class="form-label" for="counter_id">Counter</label>
                <select id="counter_id" name="counter_id" class="form-control">
                    <option value="0">-- All Counters --</option>
                    <?php foreach ($allCounters as $ac): ?>
                        <option value="<?php echo $ac['id']; ?>" <?php echo $counterId === (int)$ac['id'] ? 'selected' : ''; ?>>
                            <?php echo e($ac['counter_number']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-col form-group">
                <label class="form-label" for="start_date">From Date</label>
                <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo e($startDate); ?>">
            </div>

            <div class="form-col form-group">
                <label class="form-label" for="end_date">To Date</label>
                <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo e($endDate); ?>">
            </div>
        </div>

        <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 0.5rem;">
            <a href="history.php" class="btn btn-secondary">
                <?php echo getIcon('refresh', 16); ?> Reset Filters
            </a>
            <button type="submit" class="btn btn-primary">
                <?php echo getIcon('search', 16); ?> Apply Filters
            </button>
        </div>
    </form>
</div>

<!-- Summary Mini Stats -->
<div class="grid grid-4" style="margin-bottom: 1.5rem;">
    <div class="stat-card">
        <div class="stat-icon primary"><?php echo getIcon('ticket', 20); ?></div>
        <div class="stat-content">
            <div class="stat-label">Total Found</div>
            <div class="stat-value"><?php echo (int)($summary['total_count'] ?? 0); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><?php echo getIcon('check-circle', 20); ?></div>
        <div class="stat-content">
            <div class="stat-label">Completed</div>
            <div class="stat-value" style="color:#059669;"><?php echo (int)($summary['completed_count'] ?? 0); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><?php echo getIcon('slash', 20); ?></div>
        <div class="stat-content">
            <div class="stat-label">Skipped / Missed</div>
            <div class="stat-value" style="color:#dc2626;"><?php echo (int)($summary['skipped_count'] ?? 0); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><?php echo getIcon('clock', 20); ?></div>
        <div class="stat-content">
            <div class="stat-label">Still In Line</div>
            <div class="stat-value" style="color:#7c3aed;"><?php echo (int)($summary['waiting_count'] ?? 0); ?></div>
        </div>
    </div>
</div>

<!-- History Results Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><?php echo getIcon('list', 18); ?> Filtered Queue Records</h2>
        <span class="badge badge-serving"><?php echo count($historyRecords); ?> Results</span>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 10%;">Queue No</th>
                    <th style="width: 15%;">Customer</th>
                    <th style="width: 15%;">Service Type</th>
                    <th style="width: 12%;">Status</th>
                    <th style="width: 11%;">Counter</th>
                    <th style="width: 13%;">Served By</th>
                    <th style="width: 12%;">Issued Time</th>
                    <th style="width: 12%;">Finished</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($historyRecords)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                            No records found matching your filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($historyRecords as $row): ?>
                        <tr>
                            <td><strong style="color: #1d4ed8; font-size: 1.05rem;"><?php echo e($row['queue_number']); ?></strong></td>
                            <td><strong><?php echo e($row['customer_name']); ?></strong></td>
                            <td><span style="font-size: 0.85rem; color: #475569;"><?php echo e($row['service_type']); ?></span></td>
                            <td><?php echo renderStatusBadge($row['status']); ?></td>
                            <td><?php echo $row['counter_number'] ? e($row['counter_number']) : '<span style="color:#94a3b8;">-</span>'; ?></td>
                            <td><?php echo $row['staff_name'] ? e($row['staff_name']) : '<span style="color:#94a3b8;">-</span>'; ?></td>
                            <td><span style="color: #64748b; font-size: 0.85rem;"><?php echo formatTimeOnly($row['created_at']); ?></span></td>
                            <td><span style="color: #64748b; font-size: 0.85rem;"><?php echo $row['completed_at'] ? formatTimeOnly($row['completed_at']) : '-'; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
