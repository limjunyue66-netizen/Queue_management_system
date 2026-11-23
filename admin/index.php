<?php
// ===================================================
// Administrator Dashboard
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$pdo = getDBConnection();
$pageTitle = 'Admin Dashboard';

// Aggregate Statistics for Today
$today = date('Y-m-d');

// Total queues today
$totalToday = $pdo->query("SELECT COUNT(*) FROM queues WHERE DATE(created_at) = CURDATE()")->fetchColumn();

// Waiting queues today
$waitingCount = $pdo->query("SELECT COUNT(*) FROM queues WHERE status = 'waiting' AND DATE(created_at) = CURDATE()")->fetchColumn();

// Currently serving/calling
$servingCount = $pdo->query("SELECT COUNT(*) FROM queues WHERE status IN ('calling', 'serving') AND DATE(created_at) = CURDATE()")->fetchColumn();

// Completed today
$completedCount = $pdo->query("SELECT COUNT(*) FROM queues WHERE status = 'completed' AND DATE(created_at) = CURDATE()")->fetchColumn();

// Skipped today
$skippedCount = $pdo->query("SELECT COUNT(*) FROM queues WHERE status = 'skipped' AND DATE(created_at) = CURDATE()")->fetchColumn();

// Active counters
$activeCountersCount = $pdo->query("SELECT COUNT(*) FROM counters WHERE status = 'open'")->fetchColumn();

// Active staff count
$activeStaffCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staff' AND status = 'active'")->fetchColumn();

// Active Counters with their serving queues
$counterStatuses = $pdo->query("
    SELECT c.*, 
           q.queue_number, 
           q.customer_name, 
           q.service_type, 
           q.status AS queue_status,
           u.full_name AS staff_name
    FROM counters c
    LEFT JOIN queues q ON q.counter_id = c.id AND q.status IN ('calling', 'serving')
    LEFT JOIN users u ON c.current_staff_id = u.id
    ORDER BY c.counter_number ASC
")->fetchAll();

// Recent 5 Queues
$recentQueues = $pdo->query("
    SELECT q.*, c.counter_number, u.full_name AS staff_name
    FROM queues q
    LEFT JOIN counters c ON q.counter_id = c.id
    LEFT JOIN users u ON q.staff_id = u.id
    ORDER BY q.id DESC
    LIMIT 6
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo getIcon('dashboard', 26); ?> Administrator Dashboard</h1>
        <p class="page-desc">Comprehensive real-time overview of counters, staff, and customer queues</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="history.php" class="btn btn-secondary">
            <?php echo getIcon('history', 16); ?> View Reports & History
        </a>
        <a href="../display.php" target="_blank" class="btn btn-outline">
            <?php echo getIcon('tv', 16); ?> Open Live Display
        </a>
    </div>
</div>

<!-- Metrics Cards -->
<div class="grid grid-4" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon primary">
            <?php echo getIcon('ticket', 24); ?>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total Today</div>
            <div class="stat-value"><?php echo (int)$totalToday; ?></div>
            <small style="color: var(--text-muted); font-size: 0.8rem;">Issued tickets</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon warning">
            <?php echo getIcon('clock', 24); ?>
        </div>
        <div class="stat-content">
            <div class="stat-label">In Waiting Line</div>
            <div class="stat-value" style="color: #d97706;"><?php echo (int)$waitingCount; ?></div>
            <small style="color: var(--text-muted); font-size: 0.8rem;">Awaiting call</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon purple">
            <?php echo getIcon('play-circle', 24); ?>
        </div>
        <div class="stat-content">
            <div class="stat-label">Currently Serving</div>
            <div class="stat-value" style="color: #7c3aed;"><?php echo (int)$servingCount; ?></div>
            <small style="color: var(--text-muted); font-size: 0.8rem;">At service counters</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon success">
            <?php echo getIcon('check-circle', 24); ?>
        </div>
        <div class="stat-content">
            <div class="stat-label">Completed Today</div>
            <div class="stat-value" style="color: #059669;"><?php echo (int)$completedCount; ?></div>
            <small style="color: var(--text-muted); font-size: 0.8rem;"><?php echo (int)$skippedCount; ?> skipped</small>
        </div>
    </div>
</div>

<!-- Counter Live Status & Quick Action -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><?php echo getIcon('counter', 20); ?> Live Counter Activity Status</h2>
        <a href="counters.php" class="btn btn-sm btn-outline"><?php echo getIcon('edit', 14); ?> Manage Counters</a>
    </div>

    <div class="grid grid-4">
        <?php foreach ($counterStatuses as $cnt): ?>
            <div style="background: #ffffff; border: 1px solid var(--slate-200); border-radius: var(--radius-md); padding: 1.25rem; box-shadow: var(--shadow-xs); transition: all 0.2s ease;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <strong style="font-size: 1.05rem; color: var(--slate-900);"><?php echo e($cnt['counter_number']); ?></strong>
                    <?php echo renderStatusBadge($cnt['status']); ?>
                </div>
                <div style="font-size: 0.85rem; color: var(--slate-500); min-height: 38px; line-height: 1.35;"><?php echo e($cnt['counter_name']); ?></div>

                <div style="margin: 0.85rem 0; padding: 0.75rem; background: var(--slate-50); border-radius: var(--radius-sm); border: 1px solid var(--slate-200); text-align: center;">
                    <?php if ($cnt['status'] === 'open' && $cnt['queue_number']): ?>
                        <div style="font-size: 1.5rem; font-weight: 800; color: #2563eb; letter-spacing: 0.5px;"><?php echo e($cnt['queue_number']); ?></div>
                        <div style="font-size: 0.78rem; color: #64748b; margin-top: 2px;"><?php echo renderStatusBadge($cnt['queue_status']); ?></div>
                    <?php elseif ($cnt['status'] === 'open'): ?>
                        <div style="color: #94a3b8; font-weight: 600; font-size: 1.05rem;">Idle (Ready)</div>
                        <small style="color: #cbd5e1; font-size: 0.75rem;">Waiting for call</small>
                    <?php else: ?>
                        <div style="color: #cbd5e1; font-weight: 600; font-size: 1.05rem;">Offline</div>
                        <small style="color: #e2e8f0; font-size: 0.75rem;">Closed</small>
                    <?php endif; ?>
                </div>

                <div style="font-size: 0.82rem; color: var(--slate-600); border-top: 1px dashed var(--slate-200); padding-top: 0.65rem;">
                    Assigned: <strong><?php echo !empty($cnt['staff_name']) ? e($cnt['staff_name']) : '<span style="color:#94a3b8;">Unassigned</span>'; ?></strong>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Recent Queue Activity -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><?php echo getIcon('list', 20); ?> Recent Queue Activity</h2>
        <a href="queues.php" class="btn btn-sm btn-primary"><?php echo getIcon('list', 14); ?> View All Queues</a>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Queue No</th>
                    <th>Customer Name</th>
                    <th>Service Type</th>
                    <th>Status</th>
                    <th>Counter</th>
                    <th>Staff</th>
                    <th>Time Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentQueues)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">No queues recorded yet today.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentQueues as $q): ?>
                        <tr>
                            <td><strong style="color: #1d4ed8;"><?php echo e($q['queue_number']); ?></strong></td>
                            <td><?php echo e($q['customer_name']); ?></td>
                            <td><?php echo e($q['service_type']); ?></td>
                            <td><?php echo renderStatusBadge($q['status']); ?></td>
                            <td><?php echo e($q['counter_number'] ?: '-'); ?></td>
                            <td><?php echo e($q['staff_name'] ?: '-'); ?></td>
                            <td><?php echo formatTimeOnly($q['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
