<?php
// ===================================================
// Administrator: Presentation Demo & Simulation Toolkit
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$pdo = getDBConnection();
$today = date('Y-m-d');

// Action Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'seed_demo') {
        // 1. Clear today's queues
        $pdo->exec("DELETE FROM queues WHERE DATE(created_at) = '$today'");

        // 2. Insert standard demo scenario:
        // A001 - Serving at Counter 01 (Staff 1)
        // A002 - Calling at Counter 02 (Staff 2)
        // A003 - Waiting in hall
        // A004 - Waiting in hall
        $stmt = $pdo->prepare("
            INSERT INTO queues (queue_number, customer_name, customer_ic, customer_phone, customer_type, service_id, service_type, status, counter_id, staff_id, created_at, called_at, started_at)
            VALUES 
            ('A001', 'Ahmad bin Abdullah', '900101-14-5567', '+60 17-123 4567', 'appointment', 1, 'Passport Renewal', 'serving', 1, 2, NOW() - INTERVAL 10 MINUTE, NOW() - INTERVAL 8 MINUTE, NOW() - INTERVAL 5 MINUTE),
            ('A002', 'Walk-in Customer', '920315-10-6789', '+60 19-333 4455', 'walk_in', 1, 'Passport Renewal', 'calling', 2, 3, NOW() - INTERVAL 7 MINUTE, NOW() - INTERVAL 2 MINUTE, NULL),
            ('A003', 'Siti Nurhaliza', '981204-14-1122', '+60 18-999 8877', 'appointment', 1, 'Passport Renewal', 'waiting', NULL, NULL, NOW() - INTERVAL 4 MINUTE, NULL, NULL),
            ('A004', 'David Wilson', '850720-08-4433', '+60 12-777 6655', 'walk_in', 2, 'Passport Application', 'waiting', NULL, NULL, NOW() - INTERVAL 1 MINUTE, NULL, NULL)
        ");
        $stmt->execute();

        // Ensure Counters 1 & 2 are open and assigned
        $pdo->exec("UPDATE counters SET status = 'open', current_staff_id = 2 WHERE id = 1");
        $pdo->exec("UPDATE counters SET status = 'open', current_staff_id = 3 WHERE id = 2");

        setFlash('success', 'Standard presentation demo seeded! A001 is Serving at Counter 01, A002 is Called at Counter 02, A003 & A004 are Waiting.');
        header("Location: system_reset.php");
        exit;
    }

    if ($action === 'simulate_walkin') {
        // Pick a service randomly or default to Passport Renewal
        $services = getAllServices($pdo, true);
        $srv = $services[array_rand($services)];

        $names = ['Tan Ah Teck', 'Priya Sundram', 'Muhammad Hafiz', 'Jessica Lee', 'Chong Wei Ming', 'Nur Aisyah'];
        $randomName = $names[array_rand($names)];
        $randomIC = rand(80, 99) . sprintf('%02d', rand(1, 12)) . sprintf('%02d', rand(1, 28)) . '-10-' . rand(1000, 9999);
        $randomPhone = '+60 1' . rand(2, 9) . '-' . rand(100, 999) . ' ' . rand(1000, 9999);

        $queueNum = generateNextQueueNumber($pdo, $srv['name']);

        $stmt = $pdo->prepare("
            INSERT INTO queues (queue_number, customer_name, customer_ic, customer_phone, customer_type, service_id, service_type, status)
            VALUES (?, ?, ?, ?, 'walk_in', ?, ?, 'waiting')
        ");
        $stmt->execute([$queueNum, $randomName, $randomIC, $randomPhone, $srv['id'], $srv['name']]);

        setFlash('info', "Simulated new walk-in visitor! Generated ticket $queueNum ($randomName) for {$srv['name']}.");
        header("Location: system_reset.php");
        exit;
    }

    if ($action === 'clear_today') {
        $pdo->exec("DELETE FROM queues WHERE DATE(created_at) = '$today'");
        // Reset today's appointment check-ins
        $pdo->exec("UPDATE appointments SET check_in_time = NULL WHERE appointment_date = '$today'");

        setFlash('success', "Today's queue tickets have been cleared. All appointments for today reset to Pending.");
        header("Location: system_reset.php");
        exit;
    }
}

// Stats for current status
$stmt = $pdo->prepare("SELECT COUNT(*) FROM queues WHERE DATE(created_at) = ?");
$stmt->execute([$today]);
$todayTotal = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM queues WHERE DATE(created_at) = ? AND status = 'waiting'");
$stmt->execute([$today]);
$todayWaiting = (int)$stmt->fetchColumn();

$pageTitle = 'Demo Simulation Toolkit';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 950px; margin: 1.5rem auto;">
    <div style="margin-bottom: 2rem;">
        <h1 style="font-size: 1.7rem; font-weight: 800; color: #0f172a; margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.65rem;">
            <span style="color: #0284c7;"><?php echo getIcon('beaker', 26); ?></span> Presentation Demo & Simulation Toolkit
        </h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">
            Quickly re-seed or reset queue activity for examiner presentations, system evaluations, and live walkthroughs.
        </p>
    </div>

    <!-- Active Status Summary -->
    <div class="card" style="margin-bottom: 2rem; background: #f8fafc; display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.75rem;">
        <div style="display: flex; gap: 2.5rem;">
            <div>
                <span style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 700;">Active Today Date</span>
                <div style="font-size: 1.15rem; font-weight: 800; color: #0f172a;"><?php echo date('d M Y'); ?></div>
            </div>
            <div>
                <span style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 700;">Total Tickets Today</span>
                <div style="font-size: 1.15rem; font-weight: 800; color: #0284c7;"><?php echo $todayTotal; ?> tickets</div>
            </div>
            <div>
                <span style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 700;">Waiting in Hall</span>
                <div style="font-size: 1.15rem; font-weight: 800; color: #ea580c;"><?php echo $todayWaiting; ?> customers</div>
            </div>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="../display.php" target="_blank" class="btn btn-secondary btn-sm">
                <?php echo getIcon('tv', 14); ?> Open Display Screen
            </a>
            <a href="queues.php" class="btn btn-secondary btn-sm">
                <?php echo getIcon('list', 14); ?> View Live Queue
            </a>
        </div>
    </div>

    <!-- 3 Action Cards -->
    <div class="grid grid-cols-3" style="gap: 1.5rem;">
        <!-- Card 1: Re-Seed Demo Queue -->
        <div class="card" style="padding: 2rem; border-top: 5px solid #16a34a; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="width: 52px; height: 52px; border-radius: 12px; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">
                    <?php echo getIcon('refresh', 24); ?>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 0.5rem;">
                    Re-Seed Demo Queue
                </h3>
                <p style="color: #64748b; font-size: 0.9rem; line-height: 1.5; margin-bottom: 1.5rem;">
                    Clears today's queue and generates a standard scenario: <strong>A001</strong> (Serving at Counter 01), <strong>A002</strong> (Called at Counter 02), <strong>A003 & A004</strong> (Waiting in hall).
                </p>
            </div>
            <form method="POST" action="system_reset.php" onsubmit="return confirm('Seed standard demo queue scenario?');">
                <input type="hidden" name="action" value="seed_demo">
                <button type="submit" class="btn btn-primary" style="width: 100%; background: #16a34a; border-color: #16a34a; padding: 0.75rem; font-weight: 700;">
                    <?php echo getIcon('refresh', 16); ?> Seed Standard Demo
                </button>
            </form>
        </div>

        <!-- Card 2: Simulate Walk-in Visitor -->
        <div class="card" style="padding: 2rem; border-top: 5px solid #0284c7; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="width: 52px; height: 52px; border-radius: 12px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">
                    <?php echo getIcon('user-plus', 24); ?>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 0.5rem;">
                    Simulate Walk-in Visitor
                </h3>
                <p style="color: #64748b; font-size: 0.9rem; line-height: 1.5; margin-bottom: 1.5rem;">
                    Simulates an instant citizen arrival who takes a queue number directly from the kiosk dispenser without prior appointment.
                </p>
            </div>
            <form method="POST" action="system_reset.php">
                <input type="hidden" name="action" value="simulate_walkin">
                <button type="submit" class="btn btn-primary" style="width: 100%; background: #0284c7; border-color: #0284c7; padding: 0.75rem; font-weight: 700;">
                    <?php echo getIcon('ticket', 16); ?> Dispense Walk-In Ticket
                </button>
            </form>
        </div>

        <!-- Card 3: Reset Today's Queue -->
        <div class="card" style="padding: 2rem; border-top: 5px solid #dc2626; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="width: 52px; height: 52px; border-radius: 12px; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">
                    <?php echo getIcon('trash', 24); ?>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 0.5rem;">
                    Reset Today's Queue
                </h3>
                <p style="color: #64748b; font-size: 0.9rem; line-height: 1.5; margin-bottom: 1.5rem;">
                    Removes all queue tickets for today (<?php echo date('d M Y'); ?>) and resets all today's checked-in appointments back to Pending.
                </p>
            </div>
            <form method="POST" action="system_reset.php" onsubmit="return confirm('Clear all queue records for today?');">
                <input type="hidden" name="action" value="clear_today">
                <button type="submit" class="btn btn-danger" style="width: 100%; padding: 0.75rem; font-weight: 700;">
                    <?php echo getIcon('trash', 16); ?> Clear Today's Queue
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
