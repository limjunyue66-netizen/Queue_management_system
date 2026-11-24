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

// Today Appointments
$appointmentsToday = $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()")->fetchColumn();

// Pending Applications
$pendingAppsCount = $pdo->query("SELECT COUNT(*) FROM applications WHERE status IN ('submitted', 'under_review')")->fetchColumn();

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

// Recent 6 Queues
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

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.7rem; font-weight: 800; color: #0f172a; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.65rem;">
            <?php echo getIcon('dashboard', 26); ?> Administrator Dashboard
        </h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">
            Real-time management for immigration counters, citizen appointments, and queue operations
        </p>
    </div>
    <div style="display: flex; gap: 0.65rem; flex-wrap: wrap;">
        <a href="system_reset.php" class="btn btn-secondary" style="color: #0284c7; border-color: #bae6fd; background: #f0f9ff; font-weight: 700;">
            <?php echo getIcon('beaker', 16); ?> Demo Simulation Tool
        </a>
        <a href="history.php" class="btn btn-secondary">
            <?php echo getIcon('history', 16); ?> View Reports
        </a>
        <a href="../display.php" target="_blank" class="btn btn-primary">
            <?php echo getIcon('tv', 16); ?> Open Live Display
        </a>
    </div>
</div>

<!-- 6 Metrics Cards -->
<div class="grid grid-cols-4" style="margin-bottom: 2rem;">
    <div class="metric-card">
        <div class="metric-info">
            <span class="metric-title">TOTAL TICKETS TODAY</span>
            <span class="metric-number"><?php echo (int)$totalToday; ?></span>
        </div>
        <div class="metric-icon" style="background: #e0f2fe; color: #0284c7;">
            <?php echo getIcon('ticket', 24); ?>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-info">
            <span class="metric-title">WAITING IN HALL</span>
            <span class="metric-number" style="color: #d97706;"><?php echo (int)$waitingCount; ?></span>
        </div>
        <div class="metric-icon" style="background: #fef3c7; color: #d97706;">
            <?php echo getIcon('clock', 24); ?>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-info">
            <span class="metric-title">TODAY'S APPOINTMENTS</span>
            <span class="metric-number" style="color: #6366f1;"><?php echo (int)$appointmentsToday; ?></span>
        </div>
        <div class="metric-icon" style="background: #e0e7ff; color: #6366f1;">
            <?php echo getIcon('calendar', 24); ?>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-info">
            <span class="metric-title">PENDING APPLICATIONS</span>
            <span class="metric-number" style="color: #dc2626;"><?php echo (int)$pendingAppsCount; ?></span>
        </div>
        <div class="metric-icon" style="background: #fee2e2; color: #dc2626;">
            <?php echo getIcon('file-text', 24); ?>
        </div>
    </div>
</div>

<!-- Counter Live Status Grid -->
<div class="card" style="margin-bottom: 2rem; padding: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <h2 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
            <?php echo getIcon('counter', 18, 'text-primary'); ?> Live Service Counter Stations
        </h2>
        <a href="counters.php" style="font-size: 0.85rem; color: var(--primary); font-weight: 600; text-decoration: none;">Manage Counters &rarr;</a>
    </div>

    <div class="grid grid-cols-4" style="gap: 1rem;">
        <?php foreach ($counterStatuses as $c): ?>
            <div style="border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1.25rem; background: <?php echo ($c['status'] === 'open') ? '#fff' : '#f8fafc'; ?>; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <strong style="color: #0f172a; font-size: 1.05rem;"><?php echo e($c['counter_number']); ?></strong>
                        <?php echo renderStatusBadge($c['status']); ?>
                    </div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 1rem;">
                        <?php echo e($c['counter_name']); ?>
                    </div>

                    <?php if ($c['queue_number']): ?>
                        <div style="background: #eff6ff; border-radius: 8px; padding: 0.75rem; text-align: center; margin-bottom: 0.75rem;">
                            <span style="font-size: 0.75rem; color: #1e40af; font-weight: 700;">CURRENT TICKET</span>
                            <div style="font-size: 1.8rem; font-weight: 900; color: #1d4ed8; letter-spacing: 1px;">
                                <?php echo e($c['queue_number']); ?>
                            </div>
                            <small style="color: #64748b;"><?php echo e($c['customer_name']); ?></small>
                        </div>
                    <?php else: ?>
                        <div style="background: #f1f5f9; border-radius: 8px; padding: 0.75rem; text-align: center; margin-bottom: 0.75rem; color: #94a3b8; font-size: 0.85rem;">
                            Idle / No active customer
                        </div>
                    <?php endif; ?>
                </div>

                <div style="font-size: 0.8rem; color: #475569; border-top: 1px solid #f1f5f9; padding-top: 0.6rem; display: flex; align-items: center; gap: 0.4rem;">
                    <?php echo getIcon('user', 14); ?> 
                    <span>Staff: <strong><?php echo $c['staff_name'] ? e($c['staff_name']) : '<span style="color:#94a3b8;">Unassigned</span>'; ?></strong></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Recent Queue Activity -->
<div class="card" style="padding: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <h2 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
            <?php echo getIcon('list', 18, 'text-primary'); ?> Recent Queue Activity
        </h2>
        <a href="queues.php" style="font-size: 0.85rem; color: var(--primary); font-weight: 600; text-decoration: none;">View All &rarr;</a>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 14%;">TICKET NO</th>
                    <th style="width: 22%;">CUSTOMER & IC</th>
                    <th style="width: 20%;">SERVICE TYPE</th>
                    <th style="width: 12%;">TYPE</th>
                    <th style="width: 12%;">STATION</th>
                    <th style="width: 10%;">STATUS</th>
                    <th style="width: 10%; text-align: right;">ISSUED AT</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentQueues)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 3rem 1rem; color: #94a3b8;">
                            No queue tickets issued yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentQueues as $q): ?>
                        <tr>
                            <td>
                                <strong style="color: #0284c7; font-size: 1.05rem; font-weight: 800; letter-spacing: 0.3px;"><?php echo e($q['queue_number']); ?></strong>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: #0f172a; font-size: 0.93rem;"><?php echo e($q['customer_name']); ?></div>
                                <?php if (!empty($q['customer_ic'])): ?>
                                    <div class="cell-sub" style="margin-top: 0.15rem;"><?php echo e($q['customer_ic']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><strong style="color: #334155; font-size: 0.92rem;"><?php echo e($q['service_type']); ?></strong></td>
                            <td>
                                <span style="font-size: 0.78rem; font-weight: 700; padding: 0.25rem 0.6rem; border-radius: 9999px; background: <?php echo ($q['customer_type'] === 'appointment') ? '#e0e7ff; color: #4338ca;' : '#f1f5f9; color: #475569;'; ?>">
                                    <?php echo ($q['customer_type'] === 'appointment') ? 'Appointment' : 'Walk-in'; ?>
                                </span>
                            </td>
                            <td><?php echo $q['counter_number'] ? '<strong style="color:#0f172a;">' . e($q['counter_number']) . '</strong>' : '<span style="color:#94a3b8;">-</span>'; ?></td>
                            <td><?php echo renderStatusBadge($q['status']); ?></td>
                            <td style="text-align: right;">
                                <span style="font-size: 0.88rem; color: #64748b; font-weight: 600;"><?php echo date('h:i A', strtotime($q['created_at'])); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
