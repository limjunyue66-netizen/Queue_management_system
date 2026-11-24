<?php
// ===================================================
// Administrator Dashboard
// Clean & Streamlined Operations Center
// 6 Core Operational Metric Cards + Full Queue Data Table
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$pdo = getDBConnection();
$pageTitle = 'Admin Dashboard';

// Aggregate Core Statistics for Today
$today = date('Y-m-d');
$todayFormatted = date('l, d F Y');

// 1. Registered Citizens
$registeredUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();

// 2. Scheduled Bookings Today
$bookingsToday = $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()")->fetchColumn();

// 3. Checked-In Today (Arrived at center)
$checkedInToday = $pdo->query("SELECT COUNT(*) FROM queues WHERE DATE(created_at) = CURDATE() AND appointment_id IS NOT NULL")->fetchColumn();

// 4. Waiting in Queue
$waitingCount = $pdo->query("SELECT COUNT(*) FROM queues WHERE status = 'waiting' AND DATE(created_at) = CURDATE()")->fetchColumn();

// 5. Completed / Served Today
$completedCount = $pdo->query("SELECT COUNT(*) FROM queues WHERE status = 'completed' AND DATE(created_at) = CURDATE()")->fetchColumn();

// 6. Pending Document Applications to Review
$pendingAppsCount = $pdo->query("SELECT COUNT(*) FROM applications WHERE status IN ('submitted', 'under_review')")->fetchColumn();

// Recent Queues (Fetch 10 recent transactions for rich data table)
$recentQueues = $pdo->query("
    SELECT q.*, c.counter_number, u.full_name AS staff_name
    FROM queues q
    LEFT JOIN counters c ON q.counter_id = c.id
    LEFT JOIN users u ON q.staff_id = u.id
    ORDER BY q.id DESC
    LIMIT 10
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Dashboard Top Header Bar -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.65rem; font-weight: 800; color: #0f172a; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.65rem;">
            <?php echo getIcon('dashboard', 26); ?> Administrator Dashboard
        </h1>
        <p style="color: var(--text-muted); font-size: 0.92rem; margin: 0;">
            System Overview &amp; Live Operations Monitor &bull; <?php echo e($todayFormatted); ?>
        </p>
    </div>
    <div style="display: flex; gap: 0.65rem; flex-wrap: wrap;">
        <a href="../display.php" target="_blank" class="btn btn-outline" style="font-weight: 700; border-color: #bae6fd; color: #0284c7; background: #f0f9ff;">
            <?php echo getIcon('tv', 16); ?> Public TV Screen
        </a>
        <a href="history.php" class="btn btn-primary" style="font-weight: 700;">
            <?php echo getIcon('history', 16); ?> Daily Reports
        </a>
        <a href="system_reset.php" class="btn btn-secondary" style="font-weight: 600;">
            <?php echo getIcon('beaker', 16); ?> Simulator
        </a>
    </div>
</div>

<!-- 6 Core Metric Cards (Streamlined Modern Layout) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <!-- 1. USERS -->
    <div style="border: 1.5px solid #e2e8f0; border-left: 4.5px solid #2563eb; border-radius: 10px; background: #ffffff; padding: 1.15rem 1.25rem; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <div style="font-size: 0.76rem; font-weight: 800; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">USERS</div>
            <div style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 0.2rem 0; line-height: 1.1;"><?php echo (int)$registeredUsers; ?></div>
            <div style="font-size: 0.8rem; color: #64748b;">Registered citizens</div>
        </div>
        <div style="width: 38px; height: 38px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <?php echo getIcon('users', 20); ?>
        </div>
    </div>

    <!-- 2. BOOKINGS -->
    <div style="border: 1.5px solid #e2e8f0; border-left: 4.5px solid #0284c7; border-radius: 10px; background: #ffffff; padding: 1.15rem 1.25rem; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <div style="font-size: 0.76rem; font-weight: 800; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">BOOKINGS</div>
            <div style="font-size: 1.85rem; font-weight: 800; color: #0284c7; margin: 0.2rem 0; line-height: 1.1;"><?php echo (int)$bookingsToday; ?></div>
            <div style="font-size: 0.8rem; color: #64748b;">Scheduled today</div>
        </div>
        <div style="width: 38px; height: 38px; border-radius: 8px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <?php echo getIcon('calendar', 20); ?>
        </div>
    </div>

    <!-- 3. CHECKED-IN -->
    <div style="border: 1.5px solid #e2e8f0; border-left: 4.5px solid #0d9488; border-radius: 10px; background: #ffffff; padding: 1.15rem 1.25rem; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <div style="font-size: 0.76rem; font-weight: 800; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">CHECKED-IN</div>
            <div style="font-size: 1.85rem; font-weight: 800; color: #0d9488; margin: 0.2rem 0; line-height: 1.1;"><?php echo (int)$checkedInToday; ?></div>
            <div style="font-size: 0.8rem; color: #64748b;">Arrived at center</div>
        </div>
        <div style="width: 38px; height: 38px; border-radius: 8px; background: #ccfbf1; color: #0d9488; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <?php echo getIcon('check-circle', 20); ?>
        </div>
    </div>

    <!-- 4. WAITING -->
    <div style="border: 1.5px solid #e2e8f0; border-left: 4.5px solid #d97706; border-radius: 10px; background: #ffffff; padding: 1.15rem 1.25rem; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <div style="font-size: 0.76rem; font-weight: 800; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">WAITING</div>
            <div style="font-size: 1.85rem; font-weight: 800; color: #d97706; margin: 0.2rem 0; line-height: 1.1;"><?php echo (int)$waitingCount; ?></div>
            <div style="font-size: 0.8rem; color: #64748b;">In current queue</div>
        </div>
        <div style="width: 38px; height: 38px; border-radius: 8px; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <?php echo getIcon('clock', 20); ?>
        </div>
    </div>

    <!-- 5. COMPLETED -->
    <div style="border: 1.5px solid #e2e8f0; border-left: 4.5px solid #16a34a; border-radius: 10px; background: #ffffff; padding: 1.15rem 1.25rem; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <div style="font-size: 0.76rem; font-weight: 800; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">COMPLETED</div>
            <div style="font-size: 1.85rem; font-weight: 800; color: #16a34a; margin: 0.2rem 0; line-height: 1.1;"><?php echo (int)$completedCount; ?></div>
            <div style="font-size: 0.8rem; color: #64748b;">Served today</div>
        </div>
        <div style="width: 38px; height: 38px; border-radius: 8px; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <?php echo getIcon('check', 20); ?>
        </div>
    </div>

    <!-- 6. PENDING -->
    <div style="border: 1.5px solid #e2e8f0; border-left: 4.5px solid #dc2626; border-radius: 10px; background: #ffffff; padding: 1.15rem 1.25rem; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <div style="font-size: 0.76rem; font-weight: 800; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">PENDING</div>
            <div style="font-size: 1.85rem; font-weight: 800; color: #dc2626; margin: 0.2rem 0; line-height: 1.1;"><?php echo (int)$pendingAppsCount; ?></div>
            <div style="font-size: 0.8rem; color: #64748b;">Applications to review</div>
        </div>
        <div style="width: 38px; height: 38px; border-radius: 8px; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <?php echo getIcon('file-text', 20); ?>
        </div>
    </div>
</div>

<!-- Main Section: Clean Queue Operations Data Table Only -->
<div class="card" style="padding: 1.5rem; border: 1.5px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.35rem; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <h2 style="font-size: 1.18rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                <?php echo getIcon('list', 18, 'text-primary'); ?> Live Queue Operations Activity
            </h2>
            <p style="font-size: 0.85rem; color: #64748b; margin: 0.2rem 0 0 0;">
                Real-time transaction flow across service counters and citizen channels
            </p>
        </div>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <a href="queues.php" class="btn btn-outline btn-sm" style="font-weight: 700;">
                View All Queue Monitor &rarr;
            </a>
        </div>
    </div>

    <div class="table-responsive" style="overflow-x: auto;">
        <table class="data-table" style="width: 100%;">
            <thead>
                <tr>
                    <th style="width: 13%;">TICKET NO</th>
                    <th style="width: 22%;">CUSTOMER &amp; IC</th>
                    <th style="width: 20%;">SERVICE TYPE</th>
                    <th style="width: 12%;">TYPE</th>
                    <th style="width: 13%;">ASSIGNED STATION</th>
                    <th style="width: 10%;">STATUS</th>
                    <th style="width: 10%; text-align: right;">ISSUED AT</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentQueues)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 3.5rem 1rem; color: #94a3b8;">
                            <?php echo getIcon('inbox', 32); ?>
                            <div style="margin-top: 0.5rem; font-weight: 600;">No queue activity recorded today yet.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentQueues as $q): ?>
                        <tr>
                            <td>
                                <strong style="color: #0284c7; font-size: 1.05rem; font-weight: 800; letter-spacing: 0.3px;">
                                    <?php echo e($q['queue_number']); ?>
                                </strong>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: #0f172a; font-size: 0.93rem;"><?php echo e($q['customer_name']); ?></div>
                                <?php if (!empty($q['customer_ic'])): ?>
                                    <div class="cell-sub" style="margin-top: 0.15rem;"><?php echo e($q['customer_ic']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: #334155; font-size: 0.92rem;"><?php echo e($q['service_type']); ?></strong>
                            </td>
                            <td>
                                <span style="font-size: 0.78rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: 9999px; background: <?php echo ($q['customer_type'] === 'appointment') ? '#e0e7ff; color: #4338ca; border: 1px solid #c7d2fe;' : '#f1f5f9; color: #475569; border: 1px solid #e2e8f0;'; ?>">
                                    <?php echo ($q['customer_type'] === 'appointment') ? 'Appointment' : 'Walk-in'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($q['counter_number'])): ?>
                                    <div style="display: flex; align-items: center; gap: 0.35rem;">
                                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
                                        <strong style="color: #0f172a; font-size: 0.92rem;"><?php echo e($q['counter_number']); ?></strong>
                                    </div>
                                    <?php if (!empty($q['staff_name'])): ?>
                                        <div class="cell-sub" style="font-size: 0.78rem;"><?php echo e($q['staff_name']); ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 0.88rem;">Standby / Queue</span>
                                <?php endif; ?>
                            </td>
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
