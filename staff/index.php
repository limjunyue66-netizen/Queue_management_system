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

// Fallback: Default to first active/serving counter (e.g. Counter 1) so it never displays empty None
if (!$activeCounterId) {
    $firstOpen = $pdo->query("SELECT id FROM counters WHERE status = 'open' ORDER BY counter_number ASC LIMIT 1")->fetchColumn();
    $activeCounterId = $firstOpen ? (int)$firstOpen : 1;
    $_SESSION['active_counter_id'] = $activeCounterId;
}

// Fetch counter info if selected
$currentCounter = null;
if ($activeCounterId) {
    $cStmt = $pdo->prepare("SELECT * FROM counters WHERE id = ?");
    $cStmt->execute([$activeCounterId]);
    $currentCounter = $cStmt->fetch();
}

// Determine designated services & ticket prefix for this counter
$targetServices = $currentCounter ? getCounterDesignatedServices($currentCounter['counter_number'], $currentCounter['counter_name']) : ['General Service'];
$counterPrefix = $currentCounter ? getCounterPrefix($currentCounter['counter_number'], $currentCounter['counter_name']) : 'A';
$designatedService = $targetServices[0] ?? 'General Service';

// Handle Queue State Transitions: call_next, recall, start_serving, complete, skip
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if (!$activeCounterId) {
        setFlash('error', 'Please select and activate a counter before handling queues.');
        header("Location: index.php");
        exit;
    }

    // 1. Call Next Customer (STRICTLY for this counter's designated service)
    if ($action === 'call_next') {
        $stmtCheck = $pdo->prepare("SELECT id, queue_number, status FROM queues WHERE counter_id = ? AND status IN ('calling', 'serving') LIMIT 1");
        $stmtCheck->execute([$activeCounterId]);
        $activeTicket = $stmtCheck->fetch();

        if ($activeTicket) {
            setFlash('error', 'Please complete or skip ticket ' . e($activeTicket['queue_number']) . ' before calling the next customer.');
        } else {
            $placeholders = implode(',', array_fill(0, count($targetServices), '?'));
            $params = $targetServices;
            $params[] = $counterPrefix . '%';

            $stmtNext = $pdo->prepare("
                SELECT id, queue_number, service_type 
                FROM queues 
                WHERE status = 'waiting' 
                  AND (service_type IN ($placeholders) OR queue_number LIKE ?)
                ORDER BY id ASC 
                LIMIT 1
            ");
            $stmtNext->execute($params);
            $nextCustomer = $stmtNext->fetch();

            if ($nextCustomer) {
                $stmtUpdate = $pdo->prepare("UPDATE queues SET status = 'calling', counter_id = ?, staff_id = ?, called_at = NOW() WHERE id = ?");
                $stmtUpdate->execute([$activeCounterId, $staffId, $nextCustomer['id']]);
                setFlash('success', 'Called customer ticket ' . $nextCustomer['queue_number'] . ' (' . $nextCustomer['service_type'] . ')!');
            } else {
                setFlash('info', 'There are no waiting customers for ' . $currentCounter['counter_number'] . ' (' . $designatedService . ') right now.');
            }
        }
        header("Location: index.php");
        exit;
    }

    // 2. Recall Current Customer
    if ($action === 'recall') {
        $queueId = intval($_POST['queue_id'] ?? 0);
        if ($queueId > 0) {
            $stmt = $pdo->prepare("UPDATE queues SET called_at = NOW(), status = 'calling' WHERE id = ?");
            $stmt->execute([$queueId]);
            setFlash('info', 'Customer recalled on display screen.');
        }
        header("Location: index.php");
        exit;
    }

    // 3. Mark as Serving
    if ($action === 'start_serving') {
        $queueId = intval($_POST['queue_id'] ?? 0);
        if ($queueId > 0) {
            $stmt = $pdo->prepare("UPDATE queues SET status = 'serving', started_at = IFNULL(started_at, NOW()) WHERE id = ?");
            $stmt->execute([$queueId]);
            setFlash('success', 'Customer marked as currently serving.');
        }
        header("Location: index.php");
        exit;
    }

    // 4. Mark as Completed
    if ($action === 'complete') {
        $queueId = intval($_POST['queue_id'] ?? 0);
        if ($queueId > 0) {
            $stmt = $pdo->prepare("UPDATE queues SET status = 'completed', completed_at = NOW() WHERE id = ?");
            $stmt->execute([$queueId]);
            setFlash('success', 'Transaction completed successfully.');
        }
        header("Location: index.php");
        exit;
    }

    // 5. Skip Customer
    if ($action === 'skip') {
        $queueId = intval($_POST['queue_id'] ?? 0);
        if ($queueId > 0) {
            $stmt = $pdo->prepare("UPDATE queues SET status = 'skipped', completed_at = NOW() WHERE id = ?");
            $stmt->execute([$queueId]);
            setFlash('warning', 'Customer was marked as skipped.');
        }
        header("Location: index.php");
        exit;
    }
}

// Fetch currently active customer at this counter (calling or serving)
$currentServing = null;
if ($activeCounterId) {
    $stmtAct = $pdo->prepare("
        SELECT q.*, a.appointment_ref 
        FROM queues q
        LEFT JOIN appointments a ON q.appointment_id = a.id
        WHERE q.counter_id = ? AND q.status IN ('calling', 'serving') 
        LIMIT 1
    ");
    $stmtAct->execute([$activeCounterId]);
    $currentServing = $stmtAct->fetch();
}

// Fetch waiting customers STRICTLY for this counter's designated service
$placeholders = implode(',', array_fill(0, count($targetServices), '?'));
$waitParams = $targetServices;
$waitParams[] = $counterPrefix . '%';

$stmtWait = $pdo->prepare("
    SELECT * FROM queues 
    WHERE status = 'waiting' 
      AND (service_type IN ($placeholders) OR queue_number LIKE ?)
    ORDER BY id ASC
");
$stmtWait->execute($waitParams);
$waitingList = $stmtWait->fetchAll();

// Fetch today's count served by this staff
$myCompletedCount = $pdo->prepare("SELECT COUNT(*) FROM queues WHERE staff_id = ? AND status = 'completed' AND DATE(created_at) = CURDATE()");
$myCompletedCount->execute([$staffId]);
$servedCount = (int)$myCompletedCount->fetchColumn();

// Fetch all available counters with their live serving status for dropdown
$availableCounters = $pdo->query("
    SELECT c.*, 
           q.queue_number, 
           q.status AS queue_status
    FROM counters c
    LEFT JOIN queues q ON q.counter_id = c.id AND q.status IN ('calling', 'serving')
    ORDER BY c.counter_number ASC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo getIcon('desktop', 26); ?> Staff Service Station</h1>
        <p class="page-desc">Manage counter assignments, call waiting customers, and process transactions</p>
    </div>
    <!-- Counter Switcher Dropdown -->
    <div style="background: white; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 0.5rem 0.85rem; display: flex; align-items: center; gap: 0.65rem;">
        <span style="font-weight: 600; font-size: 0.88rem; color: #475569;">Assigned Counter:</span>
        <form method="POST" action="index.php" style="display: flex; gap: 0.35rem; align-items: center;">
            <input type="hidden" name="set_counter" value="1">
            <select name="counter_id" class="form-control" style="padding: 0.3rem 0.6rem; font-size: 0.88rem; font-weight: 600;" onchange="this.form.submit()">
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

<!-- Alert if no counter is selected -->
<?php if (!$currentCounter): ?>
    <div class="alert alert-info" style="margin-bottom: 2rem;">
        <?php echo getIcon('info', 20); ?>
        <div>
            <strong>No counter selected!</strong> Please select your counter from the dropdown above to begin calling customers.
        </div>
    </div>
<?php endif; ?>

<!-- Top Metrics -->
<div class="grid grid-3" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon primary"><?php echo getIcon('counter', 24); ?></div>
        <div class="stat-content">
            <div class="stat-label">My Counter</div>
            <div class="stat-value" style="font-size: 1.5rem; color: #1e3a8a;">
                <?php echo $currentCounter ? e($currentCounter['counter_number']) : 'None'; ?>
            </div>
            <small style="color: var(--text-muted); font-size: 0.8rem;">
                Status: <?php echo $currentCounter ? renderStatusBadge($currentCounter['status']) : '-'; ?>
            </small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon warning"><?php echo getIcon('clock', 24); ?></div>
        <div class="stat-content">
            <div class="stat-label">Waiting in Queue</div>
            <div class="stat-value" style="color: #d97706;"><?php echo count($waitingList); ?></div>
            <small style="color: var(--text-muted); font-size: 0.8rem;">
                Customers in waiting line
            </small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon success"><?php echo getIcon('check-circle', 24); ?></div>
        <div class="stat-content">
            <div class="stat-label">Completed</div>
            <div class="stat-value" style="color: #059669;"><?php echo $servedCount; ?></div>
            <small style="color: var(--text-muted); font-size: 0.8rem;">Completed transactions</small>
        </div>
    </div>
</div>

<!-- Current Serving Customer Panel -->
<div class="card" style="border: 2px solid <?php echo $currentServing ? '#3b82f6' : '#e2e8f0'; ?>; box-shadow: var(--shadow-md); margin-bottom: 2rem;">
    <div class="card-header">
        <h2 class="card-title">
            <?php echo getIcon('bell', 20); ?> Current Customer at Your Counter
        </h2>
        <div>
            <?php if ($currentServing): ?>
                <?php echo renderStatusBadge($currentServing['status']); ?>
            <?php else: ?>
                <span class="badge badge-closed">Counter Idle</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($currentServing): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; padding: 1rem 0;">
            <div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Ticket Number</div>
                <div style="font-size: 3.5rem; font-weight: 900; color: #1d4ed8; line-height: 1.1;">
                    <?php echo e($currentServing['queue_number']); ?>
                </div>
                <div style="margin-top: 0.5rem; font-size: 1.25rem; font-weight: 700; color: #1e293b;">
                    <?php echo e($currentServing['customer_name']); ?>
                </div>
                <div style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
                    Service: <strong><?php echo e($currentServing['service_type']); ?></strong> &bull; Called at: <?php echo formatTimeOnly($currentServing['called_at']); ?>
                    <?php if (!empty($currentServing['customer_ic']) || !empty($currentServing['customer_phone'])): ?>
                        <br>IC: <strong><?php echo e($currentServing['customer_ic'] ?: 'N/A'); ?></strong> &bull; Tel: <strong><?php echo e($currentServing['customer_phone'] ?: 'N/A'); ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($currentServing['appointment_ref'])): ?>
                        &bull; Appt: <strong style="color: #2563eb;"><?php echo e($currentServing['appointment_ref']); ?></strong>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons for Current Customer -->
            <div style="display: flex; flex-direction: column; gap: 0.65rem; min-width: 220px;">
                <!-- Recall Button -->
                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="recall">
                    <input type="hidden" name="queue_id" value="<?php echo $currentServing['id']; ?>">
                    <button type="submit" class="btn btn-secondary" style="width: 100%;">
                        <?php echo getIcon('bell', 16); ?> Recall Customer
                    </button>
                </form>

                <!-- Start Serving Button (if status is calling) -->
                <?php if ($currentServing['status'] === 'calling'): ?>
                    <form method="POST" action="index.php">
                        <input type="hidden" name="action" value="start_serving">
                        <input type="hidden" name="queue_id" value="<?php echo $currentServing['id']; ?>">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <?php echo getIcon('play-circle', 16); ?> Start Serving
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Complete Button -->
                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="complete">
                    <input type="hidden" name="queue_id" value="<?php echo $currentServing['id']; ?>">
                    <button type="submit" class="btn btn-success" style="width: 100%;">
                        <?php echo getIcon('check-circle', 16); ?> Mark as Completed
                    </button>
                </form>

                <!-- Skip Button -->
                <form method="POST" action="index.php" onsubmit="return confirm('Skip ticket <?php echo e($currentServing['queue_number']); ?>?');">
                    <input type="hidden" name="action" value="skip">
                    <input type="hidden" name="queue_id" value="<?php echo $currentServing['id']; ?>">
                    <button type="submit" class="btn btn-danger" style="width: 100%;">
                        <?php echo getIcon('slash', 16); ?> Skip / No Show
                    </button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 2.5rem 1rem;">
            <div style="color: var(--text-muted); font-size: 1.1rem; margin-bottom: 1.5rem;">
                <?php echo getIcon('desktop', 36); ?><br>
                <strong>No customer is currently called at this counter.</strong>
            </div>

            <form method="POST" action="index.php" style="display: inline-block;">
                <input type="hidden" name="action" value="call_next">
                <button type="submit" class="btn btn-primary btn-lg" <?php echo (!$currentCounter || empty($waitingList)) ? 'disabled' : ''; ?>>
                    <?php echo getIcon('bell', 20); ?> Call Next <?php echo $designatedService ? '(' . e($designatedService) . ')' : 'Customer'; ?>
                </button>
            </form>

            <?php if (empty($waitingList)): ?>
                <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.5rem;">
                    (No waiting customers for <?php echo e($currentCounter['counter_number'] ?? 'this counter'); ?> right now)
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Waiting Customers Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?php echo getIcon('clock', 18); ?> Waiting Queue for <?php echo e($currentCounter['counter_number'] ?? 'This Counter'); ?> (<?php echo e($designatedService); ?>)</h3>
        <span class="badge badge-waiting"><?php echo count($waitingList); ?> In Queue</span>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Position</th>
                    <th>Queue No</th>
                    <th>Customer Name</th>
                    <th>Service Category</th>
                    <th>Wait Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($waitingList)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                            No customers are waiting in line for this counter's service at the moment.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($waitingList as $index => $w): ?>
                        <tr>
                            <td><strong>#<?php echo $index + 1; ?></strong></td>
                            <td><strong style="color: #2563eb; font-size: 1.05rem;"><?php echo e($w['queue_number']); ?></strong></td>
                            <td><?php echo e($w['customer_name']); ?></td>
                            <td><?php echo e($w['service_type']); ?></td>
                            <td><?php echo formatTimeOnly($w['created_at']); ?></td>
                            <td><?php echo renderStatusBadge('waiting'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
