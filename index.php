<?php
// ===================================================
// Customer Ticket Kiosk / Home Page
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();
$pageTitle = 'Take a Queue Number';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_ticket') {
    $customerName = trim($_POST['customer_name'] ?? '');
    $serviceType = trim($_POST['service_type'] ?? 'General Service');

    if (empty($customerName)) {
        $customerName = 'Guest Customer';
    }

    $validServices = ['General Service', 'Payment & Billing', 'Customer Support', 'Priority Service'];
    if (!in_array($serviceType, $validServices)) {
        $serviceType = 'General Service';
    }

    try {
        $pdo->beginTransaction();
        $queueNumber = generateNextQueueNumber($pdo, $serviceType);

        $stmt = $pdo->prepare("INSERT INTO queues (queue_number, customer_name, customer_type, service_type, status, created_at) VALUES (?, ?, 'walk_in', ?, 'waiting', NOW())");
        $stmt->execute([$queueNumber, $customerName, $serviceType]);
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
    <h1 class="page-title" style="justify-content: center; font-size: 2.2rem;">
        <?php echo getIcon('ticket', 32); ?> Welcome to QueueFlow
    </h1>
    <p class="page-desc" style="font-size: 1.1rem;">Take a queue ticket in seconds or check your existing queue status</p>
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
            <small style="color: var(--text-muted); font-size: 0.8rem;">Currently in service</small>
        </div>
    </div>
</div>

<div class="grid grid-2">
    <!-- Ticket Generation Box -->
    <div class="card" style="box-shadow: var(--shadow-md);">
        <div class="card-header">
            <h2 class="card-title"><?php echo getIcon('plus', 20); ?> Get New Queue Ticket</h2>
        </div>
        <form method="POST" action="index.php">
            <input type="hidden" name="action" value="generate_ticket">

            <div class="form-group">
                <label class="form-label" for="customer_name">Customer Name (Optional)</label>
                <input type="text" id="customer_name" name="customer_name" class="form-control" placeholder="e.g. John Doe / Alice Smith (or leave blank for Guest)">
                <small style="color: var(--text-muted); font-size: 0.82rem; margin-top: 4px; display: block;">Used for calling announcements on the display screen.</small>
            </div>

            <div class="form-group">
                <label class="form-label">Select Service Department</label>
                <div class="service-cards-grid">
                    <label class="service-radio-card">
                        <input type="radio" name="service_type" value="General Service" checked>
                        <div class="service-radio-content">
                            <strong style="display: block; font-size: 0.95rem; color: #1e3a8a;">General Service</strong>
                            <small style="color: var(--text-muted);">Series <strong>A</strong> &bull; Registration & Inquiries</small>
                        </div>
                    </label>

                    <label class="service-radio-card">
                        <input type="radio" name="service_type" value="Payment & Billing">
                        <div class="service-radio-content">
                            <strong style="display: block; font-size: 0.95rem; color: #1e3a8a;">Payment & Billing</strong>
                            <small style="color: var(--text-muted);">Series <strong>B</strong> &bull; Cashier & Invoice Payments</small>
                        </div>
                    </label>

                    <label class="service-radio-card">
                        <input type="radio" name="service_type" value="Customer Support">
                        <div class="service-radio-content">
                            <strong style="display: block; font-size: 0.95rem; color: #1e3a8a;">Customer Support</strong>
                            <small style="color: var(--text-muted);">Series <strong>C</strong> &bull; Account Help & Consultations</small>
                        </div>
                    </label>

                    <label class="service-radio-card">
                        <input type="radio" name="service_type" value="Priority Service">
                        <div class="service-radio-content">
                            <strong style="display: block; font-size: 0.95rem; color: #7c3aed;">Priority Service</strong>
                            <small style="color: var(--text-muted);">Series <strong>P</strong> &bull; Senior Citizens & Express Lane</small>
                        </div>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 1rem;">
                <?php echo getIcon('ticket', 20); ?> Take Queue Number Now
            </button>
        </form>
    </div>

    <!-- Quick Navigation & Helpful Info -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?php echo getIcon('info', 20); ?> Customer Self-Service</h2>
        </div>
        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
            Already have a ticket? You can monitor live calls on the big screen or check your estimated wait time on your phone.
        </p>

        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <a href="display.php" class="btn btn-secondary" style="justify-content: flex-start; padding: 1rem;">
                <?php echo getIcon('tv', 24); ?>
                <div style="text-align: left; margin-left: 0.5rem;">
                    <div style="font-weight: 700;">Open Live Calling Display Screen</div>
                    <small style="color: var(--text-muted);">View the real-time public screen with current serving tickets</small>
                </div>
            </a>

            <a href="check_status.php" class="btn btn-secondary" style="justify-content: flex-start; padding: 1rem;">
                <?php echo getIcon('search', 24); ?>
                <div style="text-align: left; margin-left: 0.5rem;">
                    <div style="font-weight: 700;">Check My Queue Position</div>
                    <small style="color: var(--text-muted);">Enter your queue number to see how many people are ahead of you</small>
                </div>
            </a>

            <div style="background: #f8fafc; padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); font-size: 0.88rem; color: #475569;">
                <strong>💡 Tip for Customers:</strong> Keep your ticket receipt handy. Watch the display screen or check online to ensure you do not miss your turn.
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
