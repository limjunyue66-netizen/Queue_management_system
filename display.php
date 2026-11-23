<?php
// ===================================================
// Real-time Queue Display Screen (Public TV / Monitor)
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();
$pageTitle = 'Live Calling Display Screen';

// Fetch the most recently called queue
$stmtCurrent = $pdo->query("
    SELECT q.*, c.counter_number, c.counter_name 
    FROM queues q 
    LEFT JOIN counters c ON q.counter_id = c.id 
    WHERE q.status IN ('calling', 'serving') 
    ORDER BY q.called_at DESC 
    LIMIT 1
");
$currentCall = $stmtCurrent->fetch();

// Fetch status of all counters (Open counters and what they are currently serving)
$counters = $pdo->query("
    SELECT c.*, 
           q.queue_number AS active_queue, 
           q.status AS queue_status,
           u.full_name AS staff_name
    FROM counters c
    LEFT JOIN queues q ON q.counter_id = c.id AND q.status IN ('calling', 'serving')
    LEFT JOIN users u ON c.current_staff_id = u.id
    ORDER BY c.counter_number ASC
")->fetchAll();

// Fetch upcoming waiting tickets
$stmtWaiting = $pdo->query("
    SELECT queue_number, customer_name, service_type, created_at 
    FROM queues 
    WHERE status = 'waiting' AND DATE(created_at) = CURDATE() 
    ORDER BY id ASC 
    LIMIT 8
");
$waitingTickets = $stmtWaiting->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="display-header">
    <h1 style="font-size: 2.3rem; font-weight: 900; margin-bottom: 0.25rem; letter-spacing: -0.5px;">
        <?php echo getIcon('tv', 32); ?> NOW SERVING DISPLAY
    </h1>
    <p style="opacity: 0.85; font-size: 1rem;">
        <?php echo date('l, d F Y'); ?> &bull; Real-time Calling Screen & Counter Status (Auto updates every 5 seconds)
    </p>
</div>

<!-- Main Spotlight / Calling Hero -->
<?php if ($currentCall): ?>
    <div class="serving-hero <?php echo $currentCall['status'] === 'calling' ? 'active' : ''; ?>">
        <div style="font-size: 1.3rem; font-weight: 800; color: #1e3a8a; text-transform: uppercase; letter-spacing: 2px;">
            <?php echo $currentCall['status'] === 'calling' ? '🔔 NOW CALLING TICKET' : 'CURRENTLY SERVING'; ?>
        </div>
        <div class="hero-number"><?php echo e($currentCall['queue_number']); ?></div>
        <div>
            <span class="hero-counter">
                <?php echo getIcon('counter', 24); ?> Please proceed to: <?php echo e($currentCall['counter_number'] ?? 'Assigned Counter'); ?>
            </span>
        </div>
        <div style="margin-top: 1.25rem; color: #475569; font-size: 1.05rem;">
            Department / Service: <strong><?php echo e($currentCall['service_type']); ?></strong> &bull; Customer: <strong><?php echo e($currentCall['customer_name']); ?></strong>
        </div>
    </div>
<?php else: ?>
    <div class="card" style="text-align: center; padding: 3rem; margin-bottom: 2rem;">
        <div style="color: var(--text-muted); font-size: 1.3rem;">
            <?php echo getIcon('clock', 36); ?><br>
            <strong>No active customer being called right now.</strong>
            <p style="font-size: 0.95rem; margin-top: 0.5rem;">Please take a ticket at the kiosk and wait for your number.</p>
        </div>
    </div>
<?php endif; ?>

<!-- Counters Status Grid -->
<div class="page-header" style="margin-bottom: 1rem;">
    <h2 class="page-title" style="font-size: 1.3rem;"><?php echo getIcon('counter', 20); ?> Counter Overview</h2>
</div>

<div class="grid grid-4" style="margin-bottom: 2.5rem;">
    <?php foreach ($counters as $cnt): ?>
        <div class="counter-display-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-weight: 700; font-size: 1.1rem; color: #1e293b;"><?php echo e($cnt['counter_number']); ?></span>
                <?php echo renderStatusBadge($cnt['status']); ?>
            </div>
            <div style="font-size: 0.82rem; color: var(--text-muted); min-height: 20px;">
                <?php echo e($cnt['counter_name']); ?>
            </div>

            <div class="counter-display-num">
                <?php if ($cnt['status'] === 'open' && $cnt['active_queue']): ?>
                    <span style="color: #2563eb;"><?php echo e($cnt['active_queue']); ?></span>
                <?php elseif ($cnt['status'] === 'open'): ?>
                    <span style="color: #94a3b8; font-size: 1.3rem; font-weight: 500;">Idle</span>
                <?php else: ?>
                    <span style="color: #cbd5e1; font-size: 1.3rem; font-weight: 500;">Closed</span>
                <?php endif; ?>
            </div>

            <div style="font-size: 0.8rem; color: #64748b; border-top: 1px solid var(--border-color); padding-top: 0.5rem;">
                Staff: <?php echo !empty($cnt['staff_name']) ? e($cnt['staff_name']) : 'Unassigned'; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Upcoming Waiting Tickets Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?php echo getIcon('clock', 20); ?> Next in Line (Waiting Queue)</h3>
        <span class="badge badge-waiting"><?php echo count($waitingTickets); ?> Up Next</span>
    </div>

    <?php if (empty($waitingTickets)): ?>
        <p style="text-align: center; color: var(--text-muted); padding: 1.5rem;">No customers currently waiting in line.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Queue Number</th>
                        <th>Customer Name</th>
                        <th>Service Type</th>
                        <th>Waiting Since</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($waitingTickets as $wait): ?>
                        <tr>
                            <td><strong style="color: #2563eb; font-size: 1.1rem;"><?php echo e($wait['queue_number']); ?></strong></td>
                            <td><?php echo e($wait['customer_name']); ?></td>
                            <td><?php echo e($wait['service_type']); ?></td>
                            <td><?php echo formatTimeOnly($wait['created_at']); ?></td>
                            <td><?php echo renderStatusBadge('waiting'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Auto-refresh script: simple, zero-framework, beginner-friendly polling -->
<script>
    // Refresh page every 5 seconds to show live updates smoothly
    setTimeout(function() {
        window.location.reload();
    }, 5000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
