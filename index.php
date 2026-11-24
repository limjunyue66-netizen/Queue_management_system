<?php
// ===================================================
// Customer Ticket Kiosk / Home Page
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();
$pageTitle = 'Take a Queue Number - Walk-In Kiosk';

// Fetch active services
$services = getAllServices($pdo, true);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_ticket') {
    $customerName = trim($_POST['customer_name'] ?? '');
    $serviceId = (int)($_POST['service_id'] ?? 0);

    if (empty($customerName)) {
        $customerName = 'Walk-in Customer';
    }

    $selectedService = null;
    foreach ($services as $s) {
        if ($s['id'] == $serviceId) {
            $selectedService = $s;
            break;
        }
    }

    if (!$selectedService && !empty($services)) {
        $selectedService = $services[0];
    }

    $serviceName = $selectedService ? $selectedService['name'] : 'General Service';
    $serviceIdVal = $selectedService ? $selectedService['id'] : 1;

    try {
        $pdo->beginTransaction();
        $queueNumber = generateNextQueueNumber($pdo, $serviceName);

        $stmt = $pdo->prepare("
            INSERT INTO queues (queue_number, customer_name, customer_type, service_id, service_type, status, created_at) 
            VALUES (?, ?, 'walk_in', ?, ?, 'waiting', NOW())
        ");
        $stmt->execute([$queueNumber, $customerName, $serviceIdVal, $serviceName]);
        $newQueueId = $pdo->lastInsertId();

        $pdo->commit();

        setFlash('success', 'Your queue number ' . $queueNumber . ' has been generated!');
        header("Location: ticket.php?id=" . $newQueueId . "&num=" . urlencode($queueNumber));
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('error', 'Failed to generate ticket. Please try again.');
    }
}

// Fetch general queue overview for customers
$waitingCount = $pdo->query("SELECT COUNT(*) FROM queues WHERE status = 'waiting' AND DATE(created_at) = CURDATE()")->fetchColumn();
$servingCount = $pdo->query("SELECT COUNT(*) FROM queues WHERE status IN ('calling', 'serving') AND DATE(created_at) = CURDATE()")->fetchColumn();

// Latest called number
$stmtLatest = $pdo->query("SELECT q.queue_number, c.counter_number FROM queues q LEFT JOIN counters c ON q.counter_id = c.id WHERE q.status IN ('calling', 'serving') ORDER BY q.called_at DESC LIMIT 1");
$latestCalled = $stmtLatest->fetch();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="text-align: center; display: block; margin-bottom: 2rem;">
    <h1 class="page-title" style="justify-content: center; font-size: 2.2rem; font-weight: 900;">
        <?php echo getIcon('ticket', 32); ?> Self-Service Walk-In Kiosk
    </h1>
    <p class="page-desc" style="font-size: 1.05rem;">Touch a service card below to take your queue number or track waiting status</p>
</div>

<!-- Quick Stat Banners -->
<div class="grid grid-3" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon primary">
            <?php echo getIcon('bell', 24); ?>
        </div>
        <div class="stat-content">
            <div class="stat-label">Currently Serving</div>
            <div class="stat-value" style="color: #1d4ed8;">
                <?php echo $latestCalled ? e($latestCalled['queue_number']) : 'None'; ?>
            </div>
            <small style="color: var(--text-muted); font-size: 0.8rem;">
                <?php echo $latestCalled && $latestCalled['counter_number'] ? 'at ' . e($latestCalled['counter_number']) : 'Waiting for next call'; ?>
            </small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon warning">
            <?php echo getIcon('clock', 24); ?>
        </div>
        <div class="stat-content">
            <div class="stat-label">Customers Waiting</div>
            <div class="stat-value" style="color: #d97706;"><?php echo (int)$waitingCount; ?></div>
            <small style="color: var(--text-muted); font-size: 0.8rem;">In waiting queue today</small>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon success">
            <?php echo getIcon('desktop', 24); ?>
        </div>
        <div class="stat-content">
            <div class="stat-label">Active Counters</div>
            <div class="stat-value" style="color: #059669;"><?php echo (int)$servingCount; ?></div>
            <small style="color: var(--text-muted); font-size: 0.8rem;">Currently serving</small>
        </div>
    </div>
</div>

<div class="grid grid-2" style="gap: 1.5rem;">
    <!-- Service Selection Kiosk Form -->
    <div class="card" style="box-shadow: var(--shadow-md); padding: 2rem;">
        <div style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.3rem; font-weight: 800; color: #0f172a; margin: 0 0 0.25rem 0;">
                Select an Immigration Service
            </h2>
            <p style="color: #64748b; font-size: 0.9rem; margin: 0;">
                Touch one of the options below to get your ticket
            </p>
        </div>

        <form method="POST" action="index.php">
            <input type="hidden" name="action" value="generate_ticket">

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" for="customer_name">Customer / Visitor Name (Optional)</label>
                <input type="text" id="customer_name" name="customer_name" class="form-control" placeholder="e.g. John Doe / Walk-in Visitor">
                <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 4px; display: block;">Display screen calling announcement reference.</small>
            </div>

            <div class="form-group">
                <label class="form-label" style="font-weight: 700; color: #1e293b; margin-bottom: 0.75rem; display: block;">
                    Available Service Options
                </label>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach ($services as $idx => $srv): ?>
                        <label style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 1rem 1.25rem; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: all 0.2s ease; background: #fff;" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="if(!this.querySelector('input').checked) this.style.borderColor='#e2e8f0'">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <input type="radio" name="service_id" value="<?php echo $srv['id']; ?>" <?php echo ($idx === 0) ? 'checked' : ''; ?> style="accent-color: var(--primary); transform: scale(1.2);">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span style="font-weight: 800; font-size: 0.85rem; background: #e0f2fe; color: #0284c7; padding: 0.15rem 0.5rem; border-radius: 4px;">
                                            <?php echo e($srv['code']); ?>
                                        </span>
                                        <strong style="color: #0f172a; font-size: 1.05rem;"><?php echo e($srv['name']); ?></strong>
                                    </div>
                                    <div style="font-size: 0.82rem; color: #64748b; margin-top: 0.2rem;">
                                        <?php echo e($srv['description']); ?>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: right; min-width: 75px;">
                                <span style="font-size: 0.75rem; font-weight: 700; background: #fef3c7; color: #b45309; padding: 0.2rem 0.55rem; border-radius: 12px; white-space: nowrap;">
                                    ~<?php echo (int)$srv['est_duration']; ?>m
                                </span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.95rem; font-size: 1.1rem; font-weight: 800; margin-top: 1rem; box-shadow: var(--shadow);">
                <?php echo getIcon('ticket', 20); ?> Dispense Queue Number Now
            </button>
        </form>
    </div>

    <!-- Right Box: Online Appointments & Public Self-Service -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Online Portal Callout -->
        <div class="card" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-left: 5px solid #2563eb; padding: 1.75rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                <div style="width: 44px; height: 44px; border-radius: 50%; background: #2563eb; color: #fff; display: flex; align-items: center; justify-content: center;">
                    <?php echo getIcon('calendar', 22); ?>
                </div>
                <div>
                    <h3 style="font-size: 1.15rem; font-weight: 800; color: #1e3a8a; margin: 0;">Have an Online Appointment?</h3>
                    <small style="color: #3b82f6; font-weight: 600;">Fast-Track Arrival Check-in</small>
                </div>
            </div>
            <p style="color: #1e3a8a; font-size: 0.92rem; line-height: 1.5; margin-bottom: 1.25rem;">
                If you already booked an appointment online, please sign in to your citizen portal to instantly check in and generate your assigned ticket.
            </p>
            <div style="display: flex; gap: 0.65rem;">
                <a href="user/checkin.php" class="btn btn-primary btn-sm" style="background: #16a34a; border-color: #16a34a; font-weight: 700;">
                    <?php echo getIcon('check-square', 14); ?> Office Check-In
                </a>
                <a href="register.php" class="btn btn-secondary btn-sm" style="font-weight: 700;">
                    <?php echo getIcon('user-plus', 14); ?> Register Account
                </a>
            </div>
        </div>

        <!-- Quick Public Links -->
        <div class="card" style="padding: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin-bottom: 1rem;">
                Public Screen & Tracking
            </h3>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <a href="display.php" target="_blank" class="btn btn-secondary" style="justify-content: flex-start; padding: 0.85rem 1rem;">
                    <?php echo getIcon('tv', 20); ?>
                    <div style="text-align: left; margin-left: 0.5rem;">
                        <div style="font-weight: 700; font-size: 0.95rem;">Open Live Calling Display Screen</div>
                        <small style="color: var(--text-muted);">Real-time public TV monitor with audio chime</small>
                    </div>
                </a>

                <a href="check_status.php" class="btn btn-secondary" style="justify-content: flex-start; padding: 0.85rem 1rem;">
                    <?php echo getIcon('search', 20); ?>
                    <div style="text-align: left; margin-left: 0.5rem;">
                        <div style="font-weight: 700; font-size: 0.95rem;">Check My Queue Position</div>
                        <small style="color: var(--text-muted);">Look up your ticket number and estimated wait time</small>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
