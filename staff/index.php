<?php
// ===================================================
// Staff Service Station Dashboard & Operations
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireStaff();
$pdo = getDBConnection();
$currentUser = currentUser();
$staffId = $currentUser['id'];
$pageTitle = 'Staff Service Station';
$today = date('Y-m-d');

// Check if specific counter requested via GET
if (isset($_GET['counter_id']) && intval($_GET['counter_id']) > 0) {
    $_SESSION['active_counter_id'] = intval($_GET['counter_id']);
}

// Get or set current active counter for this staff session
if (isset($_POST['set_counter'])) {
    $selectedCounterId = intval($_POST['counter_id'] ?? 0);
    if ($selectedCounterId > 0) {
        $_SESSION['active_counter_id'] = $selectedCounterId;
        $stmt = $pdo->prepare("UPDATE counters SET current_staff_id = ?, status = 'open' WHERE id = ?");
        $stmt->execute([$staffId, $selectedCounterId]);
        setFlash('success', 'You are now operating at this counter.');
    }
    header("Location: index.php");
    exit;
}

// Check session or database for staff assigned counter
$activeCounterId = $_SESSION['active_counter_id'] ?? null;
if (!$activeCounterId) {
    $cntStmt = $pdo->prepare("SELECT id FROM counters WHERE current_staff_id = ? LIMIT 1");
    $cntStmt->execute([$staffId]);
    $activeCounterId = $cntStmt->fetchColumn() ?: null;
}

// Fallback to Counter 1 if none assigned
if (!$activeCounterId) {
    $firstOpen = $pdo->query("SELECT id FROM counters WHERE status = 'open' ORDER BY counter_number ASC LIMIT 1")->fetchColumn();
    $activeCounterId = $firstOpen ? (int)$firstOpen : 1;
    $_SESSION['active_counter_id'] = $activeCounterId;
}

// Fetch counter info
$currentCounter = null;
if ($activeCounterId) {
    $cStmt = $pdo->prepare("
        SELECT c.*, s.name as designated_service_name, s.est_duration 
        FROM counters c
        LEFT JOIN services s ON c.designated_service_id = s.id
        WHERE c.id = ?
    ");
    $cStmt->execute([$activeCounterId]);
    $currentCounter = $cStmt->fetch();
}

// Handle Queue State Transitions: call_next, recall, start_serving, complete, skip
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if (!$activeCounterId) {
        setFlash('error', 'Please select and activate a counter before handling queues.');
        header("Location: index.php");
        exit;
    }

    // 1. Call Next Customer
    if ($action === 'call_next') {
        $stmtCheck = $pdo->prepare("SELECT id, queue_number, status FROM queues WHERE counter_id = ? AND status IN ('calling', 'serving') LIMIT 1");
        $stmtCheck->execute([$activeCounterId]);
        $activeTicket = $stmtCheck->fetch();

        if ($activeTicket) {
            setFlash('error', 'Please complete or skip ticket ' . e($activeTicket['queue_number']) . ' before calling the next customer.');
        } else {
            $targetService = $currentCounter['designated_service_name'] ?? getCounterDesignatedService($currentCounter['counter_number'], $currentCounter['counter_name']);

            if ($targetService) {
                $stmtNext = $pdo->prepare("SELECT id, queue_number, service_type FROM queues WHERE status = 'waiting' AND service_type = ? ORDER BY id ASC LIMIT 1");
                $stmtNext->execute([$targetService]);
            } else {
                $stmtNext = $pdo->prepare("SELECT id, queue_number, service_type FROM queues WHERE status = 'waiting' ORDER BY id ASC LIMIT 1");
                $stmtNext->execute();
            }
            $nextCustomer = $stmtNext->fetch();

            if ($nextCustomer) {
                $stmtUpdate = $pdo->prepare("UPDATE queues SET status = 'calling', counter_id = ?, staff_id = ?, called_at = NOW() WHERE id = ?");
                $stmtUpdate->execute([$activeCounterId, $staffId, $nextCustomer['id']]);
                setFlash('success', 'Called customer ticket ' . $nextCustomer['queue_number'] . ' (' . $nextCustomer['service_type'] . ')!');
            } else {
                $serviceName = $targetService ? $targetService : 'your department';
                setFlash('info', 'There are no waiting customers for ' . $serviceName . ' right now.');
            }
        }
        header("Location: index.php");
        exit;
    }

    // 2. Recall Current Customer
    if ($action === 'recall') {
        $queueId = intval($_POST['queue_id'] ?? 0);
        if ($queueId > 0) {
            $stmt = $pdo->prepare("UPDATE queues SET called_at = NOW(), status = 'calling' WHERE id = ? AND counter_id = ?");
            $stmt->execute([$queueId, $activeCounterId]);
            setFlash('info', 'Customer recalled on display screen.');
        }
        header("Location: index.php");
        exit;
    }

    // 3. Mark as Serving
    if ($action === 'start_serving') {
        $queueId = intval($_POST['queue_id'] ?? 0);
        if ($queueId > 0) {
            $stmt = $pdo->prepare("UPDATE queues SET status = 'serving', started_at = IFNULL(started_at, NOW()) WHERE id = ? AND counter_id = ?");
            $stmt->execute([$queueId, $activeCounterId]);
            setFlash('success', 'Customer marked as currently serving.');
        }
        header("Location: index.php");
        exit;
    }

    // 4. Mark as Completed
    if ($action === 'complete') {
        $queueId = intval($_POST['queue_id'] ?? 0);
        if ($queueId > 0) {
            $stmt = $pdo->prepare("UPDATE queues SET status = 'completed', completed_at = NOW() WHERE id = ? AND counter_id = ?");
            $stmt->execute([$queueId, $activeCounterId]);
            setFlash('success', 'Transaction completed successfully.');
        }
        header("Location: index.php");
        exit;
    }

    // 5. Skip Customer
    if ($action === 'skip') {
        $queueId = intval($_POST['queue_id'] ?? 0);
        if ($queueId > 0) {
            $stmt = $pdo->prepare("UPDATE queues SET status = 'skipped', completed_at = NOW() WHERE id = ? AND counter_id = ?");
            $stmt->execute([$queueId, $activeCounterId]);
            setFlash('warning', 'Customer was marked as skipped.');
        }
        header("Location: index.php");
        exit;
    }
}

// Fetch currently active customer at this counter (calling or serving) with appointment ref
$currentServing = null;
if ($activeCounterId) {
    $stmtAct = $pdo->prepare("
        SELECT q.*, a.appointment_ref, s.est_duration 
        FROM queues q
        LEFT JOIN appointments a ON q.appointment_id = a.id
        LEFT JOIN services s ON q.service_id = s.id
        WHERE q.counter_id = ? AND q.status IN ('calling', 'serving') 
        LIMIT 1
    ");
    $stmtAct->execute([$activeCounterId]);
    $currentServing = $stmtAct->fetch();
}

// Determine designated service for this counter
$designatedService = $currentCounter['designated_service_name'] ?? getCounterDesignatedService($currentCounter['counter_number'], $currentCounter['counter_name']);

// Fetch waiting customers for this counter's designated service
if ($designatedService) {
    $stmtWait = $pdo->prepare("SELECT * FROM queues WHERE status = 'waiting' AND service_type = ? ORDER BY id ASC");
    $stmtWait->execute([$designatedService]);
    $waitingList = $stmtWait->fetchAll();
} else {
    $waitingList = $pdo->query("SELECT * FROM queues WHERE status = 'waiting' ORDER BY id ASC")->fetchAll();
}

// Stats for this counter today
$stmtStats = $pdo->prepare("
    SELECT 
        SUM(status = 'completed') as completed_count,
        SUM(status = 'skipped') as skipped_count
    FROM queues 
    WHERE counter_id = ? AND DATE(created_at) = CURDATE()
");
$stmtStats->execute([$activeCounterId]);
$counterStats = $stmtStats->fetch();
$counterCompleted = (int)($counterStats['completed_count'] ?? 0);
$counterSkipped = (int)($counterStats['skipped_count'] ?? 0);

// Fetch all available counters for switch dropdown
$availableCounters = $pdo->query("
    SELECT c.*, q.queue_number, q.status AS queue_status
    FROM counters c
    LEFT JOIN queues q ON q.counter_id = c.id AND q.status IN ('calling', 'serving')
    ORDER BY c.counter_number ASC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.65rem;">
            <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #16a34a; box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.2);"></span>
            <h1 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin: 0;">
                <?php echo e($currentCounter['counter_number']); ?> &mdash; <?php echo e($currentCounter['counter_name']); ?>
            </h1>
        </div>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.25rem 0 0 0;">
            Officer in Charge: <strong><?php echo e($currentUser['full_name']); ?></strong> &bull; <?php echo date('l, d M Y'); ?>
        </p>
    </div>

    <!-- Assigned Counter Selector -->
    <div style="background: white; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 0.5rem 0.85rem; display: flex; align-items: center; gap: 0.65rem;">
        <span style="font-weight: 600; font-size: 0.88rem; color: #475569;">Switch Station:</span>
        <form method="POST" action="index.php" style="display: flex; gap: 0.35rem; align-items: center; margin: 0;">
            <input type="hidden" name="set_counter" value="1">
            <select name="counter_id" class="form-control" style="padding: 0.35rem 0.65rem; font-size: 0.88rem; font-weight: 600;" onchange="this.form.submit()">
                <?php foreach ($availableCounters as $ac): ?>
                    <option value="<?php echo $ac['id']; ?>" <?php echo ($activeCounterId == $ac['id']) ? 'selected' : ''; ?>>
                        <?php echo e($ac['counter_number']); ?> (<?php echo e($ac['counter_name']); ?>)
                        <?php if (!empty($ac['queue_number'])): ?>
                            - [Serving <?php echo e($ac['queue_number']); ?>]
                        <?php elseif ($ac['status'] === 'open'): ?>
                            - [Open / Idle]
                        <?php else: ?>
                            - [Closed]
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<div class="grid grid-cols-3" style="gap: 1.5rem; align-items: start;">
    <!-- Left Main Column: Current Customer at Counter (Span 2) -->
    <div style="grid-column: span 2;">
        <div class="card" style="padding: 2.25rem; box-shadow: var(--shadow-md);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem;">
                <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 0.65rem; margin: 0;">
                    <?php echo getIcon('user', 20, 'text-primary'); ?> Current Customer at Counter
                </h2>
                <?php if ($currentServing): ?>
                    <?php echo renderStatusBadge($currentServing['status']); ?>
                <?php else: ?>
                    <span class="badge" style="background:#f1f5f9; color:#64748b;">Idle Station</span>
                <?php endif; ?>
            </div>

            <?php if ($currentServing): ?>
                <div style="text-align: center; padding: 1.5rem 0 2rem 0; border-bottom: 1px solid var(--border-color); margin-bottom: 2rem;">
                    <span style="font-size: 0.82rem; font-weight: 700; color: #64748b; letter-spacing: 1px; text-transform: uppercase;">
                        SERVING TICKET
                    </span>
                    <div style="font-size: clamp(3.5rem, 8vw, 5rem); font-weight: 900; color: #0284c7; letter-spacing: 2px; margin: 0.25rem 0;">
                        <?php echo e($currentServing['queue_number']); ?>
                    </div>
                    <div style="font-size: 1.35rem; font-weight: 800; color: #0f172a;">
                        <?php echo e($currentServing['customer_name']); ?>
                    </div>
                    <?php if (!empty($currentServing['customer_ic']) || !empty($currentServing['customer_phone'])): ?>
                        <div style="color: #64748b; font-size: 0.9rem; margin-top: 0.25rem;">
                            IC/Passport: <strong><?php echo e($currentServing['customer_ic'] ?: 'N/A'); ?></strong> &bull; 
                            Tel: <strong><?php echo e($currentServing['customer_phone'] ?: 'N/A'); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Customer Metadata Sub-Card -->
                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1.25rem; margin-bottom: 2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <span style="font-size: 0.78rem; color: #64748b; text-transform: uppercase; font-weight: 600;">Service:</span>
                        <div style="font-weight: 700; color: #1e293b;"><?php echo e($currentServing['service_type']); ?></div>
                    </div>
                    <div>
                        <span style="font-size: 0.78rem; color: #64748b; text-transform: uppercase; font-weight: 600;">Appointment Ref:</span>
                        <div style="font-weight: 700; color: #0284c7;">
                            <?php echo !empty($currentServing['appointment_ref']) ? e($currentServing['appointment_ref']) : '<span style="color:#64748b;">Walk-in</span>'; ?>
                        </div>
                    </div>
                    <div>
                        <span style="font-size: 0.78rem; color: #64748b; text-transform: uppercase; font-weight: 600;">Called At:</span>
                        <div style="font-weight: 600; color: #1e293b;">
                            <?php echo $currentServing['called_at'] ? date('h:i A', strtotime($currentServing['called_at'])) : '-'; ?>
                        </div>
                    </div>
                    <div>
                        <span style="font-size: 0.78rem; color: #64748b; text-transform: uppercase; font-weight: 600;">Est. Duration:</span>
                        <div style="font-weight: 600; color: #1e293b;">
                            ~<?php echo (int)($currentServing['est_duration'] ?: 15); ?> mins
                        </div>
                    </div>
                </div>

                <!-- Operational Action Buttons -->
                <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
                    <?php if ($currentServing['status'] === 'calling'): ?>
                        <form method="POST" action="index.php" style="margin: 0;">
                            <input type="hidden" name="action" value="start_serving">
                            <input type="hidden" name="queue_id" value="<?php echo $currentServing['id']; ?>">
                            <button type="submit" class="btn btn-primary" style="background: #16a34a; border-color: #16a34a; padding: 0.8rem 1.5rem; font-weight: 700;">
                                <?php echo getIcon('play-circle', 18); ?> Start Serving
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Recall -->
                    <form method="POST" action="index.php" style="margin: 0;">
                        <input type="hidden" name="action" value="recall">
                        <input type="hidden" name="queue_id" value="<?php echo $currentServing['id']; ?>">
                        <button type="submit" class="btn btn-secondary" style="background: #fef3c7; border-color: #fde68a; color: #b45309; padding: 0.8rem 1.4rem; font-weight: 700;">
                            <?php echo getIcon('bell', 18); ?> Recall Number
                        </button>
                    </form>

                    <!-- Mark Completed -->
                    <form method="POST" action="index.php" style="margin: 0;">
                        <input type="hidden" name="action" value="complete">
                        <input type="hidden" name="queue_id" value="<?php echo $currentServing['id']; ?>">
                        <button type="submit" class="btn btn-primary" style="padding: 0.8rem 1.5rem; font-weight: 700;">
                            <?php echo getIcon('check', 18); ?> Mark Completed
                        </button>
                    </form>

                    <!-- Skip / No Show -->
                    <form method="POST" action="index.php" onsubmit="return confirm('Skip this customer as No Show?');" style="margin: 0;">
                        <input type="hidden" name="action" value="skip">
                        <input type="hidden" name="queue_id" value="<?php echo $currentServing['id']; ?>">
                        <button type="submit" class="btn btn-danger" style="padding: 0.8rem 1.4rem; font-weight: 700;">
                            <?php echo getIcon('slash', 18); ?> Skip Number
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <!-- Idle State: Ready to call next -->
                <div style="text-align: center; padding: 3.5rem 1rem;">
                    <div style="width: 72px; height: 72px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">
                        <?php echo getIcon('counter', 36); ?>
                    </div>
                    <h3 style="font-size: 1.3rem; font-weight: 800; color: #1e293b; margin-bottom: 0.5rem;">
                        Ready for Next Customer
                    </h3>
                    <p style="color: #64748b; max-width: 440px; margin: 0 auto 2rem auto; font-size: 0.95rem;">
                        There are <strong><?php echo count($waitingList); ?></strong> waiting customer(s) in queue for <strong><?php echo e($designatedService ?: 'all services'); ?></strong>.
                    </p>

                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="call_next">
                        <button type="submit" class="btn btn-primary" style="padding: 0.95rem 2.25rem; font-size: 1.1rem; font-weight: 800; box-shadow: var(--shadow);">
                            <?php echo getIcon('bell', 20); ?> Call Next Customer
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Today Stats & Next Waiting (Span 1) -->
    <div>
        <!-- Today's Counter Stats -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin-bottom: 1rem;">
                Today's Counter Stats
            </h3>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.65rem; text-align: center;">
                <div style="background: #fef3c7; border-radius: 8px; padding: 0.85rem 0.5rem;">
                    <div style="font-size: 1.6rem; font-weight: 900; color: #d97706;"><?php echo count($waitingList); ?></div>
                    <div style="font-size: 0.72rem; font-weight: 700; color: #b45309; text-transform: uppercase;">Waiting</div>
                </div>
                <div style="background: #dcfce7; border-radius: 8px; padding: 0.85rem 0.5rem;">
                    <div style="font-size: 1.6rem; font-weight: 900; color: #16a34a;"><?php echo $counterCompleted; ?></div>
                    <div style="font-size: 0.72rem; font-weight: 700; color: #15803d; text-transform: uppercase;">Completed</div>
                </div>
                <div style="background: #f1f5f9; border-radius: 8px; padding: 0.85rem 0.5rem;">
                    <div style="font-size: 1.6rem; font-weight: 900; color: #64748b;"><?php echo $counterSkipped; ?></div>
                    <div style="font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase;">Skipped</div>
                </div>
            </div>
        </div>

        <!-- Next in Waiting Queue -->
        <div class="card" style="padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0;">
                    Next in Waiting Queue
                </h3>
                <span style="font-size: 0.78rem; font-weight: 700; background: #e0f2fe; color: #0369a1; padding: 0.2rem 0.5rem; border-radius: 12px;">
                    <?php echo count($waitingList); ?> in line
                </span>
            </div>

            <?php if (empty($waitingList)): ?>
                <div style="text-align: center; padding: 2rem 1rem; color: #94a3b8; font-size: 0.9rem;">
                    No customers currently waiting in queue.
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.65rem; max-height: 380px; overflow-y: auto;">
                    <?php foreach ($waitingList as $idx => $wq): ?>
                        <div style="border: 1px solid var(--border-color); border-radius: 8px; padding: 0.75rem 1rem; display: flex; justify-content: space-between; align-items: center; background: <?php echo ($idx === 0) ? '#f0fdf4' : '#fff'; ?>;">
                            <div>
                                <strong style="font-size: 1.1rem; color: #0284c7;"><?php echo e($wq['queue_number']); ?></strong>
                                <div style="font-weight: 600; font-size: 0.85rem; color: #1e293b;"><?php echo e($wq['customer_name']); ?></div>
                                <small style="color: #64748b;"><?php echo e($wq['service_type']); ?></small>
                            </div>
                            <div style="text-align: right;">
                                <span style="font-size: 0.75rem; color: #94a3b8;"><?php echo date('h:i A', strtotime($wq['created_at'])); ?></span>
                                <div><?php echo renderStatusBadge($wq['status']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
