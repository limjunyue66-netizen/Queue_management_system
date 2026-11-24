<?php
// ===================================================
// Citizen Portal: Office Arrival Check-In
// Generates official daily queue number from appointment
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireUser();
$currentUser = currentUser();
$pdo = getDBConnection();
$userId = $currentUser['id'];
$today = date('Y-m-d');

$error = '';
$success = '';

// Handle Check-In Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkin_appointment_id'])) {
    $appId = (int)$_POST['checkin_appointment_id'];

    // Verify appointment belongs to user and is today
    $stmt = $pdo->prepare("
        SELECT a.*, s.name as service_name, s.code as service_code, s.est_duration
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.id = ? AND a.user_id = ? AND a.appointment_date = ? AND a.status = 'confirmed'
        LIMIT 1
    ");
    $stmt->execute([$appId, $userId, $today]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        $error = 'No eligible confirmed appointment found for check-in today.';
    } else {
        // Check if ticket already generated for this appointment
        $checkQueue = $pdo->prepare("SELECT * FROM queues WHERE appointment_id = ? AND DATE(created_at) = ? LIMIT 1");
        $checkQueue->execute([$appId, $today]);
        $existingQueue = $checkQueue->fetch();

        if ($existingQueue) {
            $queueNumber = $existingQueue['queue_number'];
        } else {
            // Generate queue number based on service
            $queueNumber = generateNextQueueNumber($pdo, $appointment['service_name']);

            // Insert into queues
            $insertQueue = $pdo->prepare("
                INSERT INTO queues (queue_number, customer_name, customer_ic, customer_phone, customer_type, service_id, service_type, appointment_id, status)
                VALUES (?, ?, ?, ?, 'appointment', ?, ?, ?, 'waiting')
            ");
            $insertQueue->execute([
                $queueNumber,
                $currentUser['full_name'],
                $currentUser['ic_passport'],
                $currentUser['phone'],
                $appointment['service_id'],
                $appointment['service_name'],
                $appId
            ]);

            // Update appointment check in time
            $updApp = $pdo->prepare("UPDATE appointments SET check_in_time = NOW() WHERE id = ?");
            $updApp->execute([$appId]);
        }

        setFlash('success', "Check-in successful! Your daily queue number is $queueNumber.");
        header("Location: checkin.php?ticket=" . urlencode($queueNumber));
        exit;
    }
}

// Check if user already checked in today
$checkedInTicket = $_GET['ticket'] ?? '';
$activeQueue = null;

if (!empty($checkedInTicket)) {
    $stmt = $pdo->prepare("
        SELECT q.*, a.appointment_ref, c.counter_number 
        FROM queues q
        LEFT JOIN appointments a ON q.appointment_id = a.id
        LEFT JOIN counters c ON q.counter_id = c.id
        WHERE q.queue_number = ? AND DATE(q.created_at) = ?
        LIMIT 1
    ");
    $stmt->execute([$checkedInTicket, $today]);
    $activeQueue = $stmt->fetch();
}

if (!$activeQueue) {
    // Check if user has an existing queue ticket created today
    $stmt = $pdo->prepare("
        SELECT q.*, a.appointment_ref, c.counter_number 
        FROM queues q
        LEFT JOIN appointments a ON q.appointment_id = a.id
        LEFT JOIN counters c ON q.counter_id = c.id
        WHERE (q.customer_name = ? OR q.customer_ic = ?) AND DATE(q.created_at) = ?
        ORDER BY q.id DESC LIMIT 1
    ");
    $stmt->execute([$currentUser['full_name'], $currentUser['ic_passport'], $today]);
    $activeQueue = $stmt->fetch();
}

// Fetch any pending appointments for today
$stmt = $pdo->prepare("
    SELECT a.*, s.name as service_name, s.code as service_code, s.est_duration
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    WHERE a.user_id = ? AND a.appointment_date = ? AND a.status = 'confirmed' AND a.check_in_time IS NULL
    ORDER BY a.time_slot ASC
");
$stmt->execute([$userId, $today]);
$todayEligibleAppointments = $stmt->fetchAll();

// Calculate waiting position and estimated time
$aheadCount = 0;
$currentlyServing = null;
if ($activeQueue) {
    $aheadStmt = $pdo->prepare("
        SELECT COUNT(*) FROM queues 
        WHERE status = 'waiting' AND service_type = ? AND DATE(created_at) = ? AND id < ?
    ");
    $aheadStmt->execute([$activeQueue['service_type'], $today, $activeQueue['id']]);
    $aheadCount = (int)$aheadStmt->fetchColumn();

    $servingStmt = $pdo->prepare("
        SELECT queue_number, counter_id FROM queues 
        WHERE status IN ('calling', 'serving') AND DATE(created_at) = ? 
        ORDER BY id DESC LIMIT 1
    ");
    $servingStmt->execute([$today]);
    $currentlyServing = $servingStmt->fetch();
}

$pageTitle = 'Immigration Office Arrival Check-In';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 780px; margin: 1.5rem auto;">
    <div style="text-align: center; margin-bottom: 2rem;">
        <div style="width: 58px; height: 58px; background: #dcfce7; color: #16a34a; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 0.75rem;">
            <?php echo getIcon('check-square', 26); ?>
        </div>
        <h1 style="font-size: 1.7rem; font-weight: 800; color: #0f172a; margin-bottom: 0.35rem;">
            Immigration Office Arrival Check-In
        </h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">
            Confirm your presence at the service center to generate your daily queue number.
        </p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            <?php echo getIcon('alert-triangle', 18); ?> <span><?php echo e($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($activeQueue): ?>
        <!-- Already Checked In: Verified Ticket Card -->
        <div class="card" style="padding: 2.5rem; text-align: center; border-top: 6px solid #16a34a; box-shadow: var(--shadow-lg);">
            <div style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.85rem; border-radius: 20px; background: #dcfce7; color: #15803d; font-weight: 700; font-size: 0.85rem; margin-bottom: 1.25rem;">
                <?php echo getIcon('check-circle', 16); ?> CHECK-IN VERIFIED
            </div>

            <div style="font-size: 0.88rem; color: #64748b; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;">
                YOUR DAILY QUEUE NUMBER
            </div>

            <div style="font-size: clamp(3.5rem, 10vw, 4.5rem); font-weight: 900; color: #0284c7; letter-spacing: 2px; margin: 0.25rem 0 1rem 0;">
                <?php echo e($activeQueue['queue_number']); ?>
            </div>

            <div style="margin-bottom: 1.75rem;">
                <?php echo renderStatusBadge($activeQueue['status']); ?>
            </div>

            <!-- Details Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; text-align: left; margin-bottom: 2rem;">
                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1rem;">
                    <div style="font-size: 0.78rem; color: #64748b; text-transform: uppercase;">Service Type</div>
                    <div style="font-weight: 700; color: #1e293b; font-size: 1rem; margin-top: 0.2rem;">
                        <?php echo e($activeQueue['service_type']); ?>
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1rem;">
                    <div style="font-size: 0.78rem; color: #64748b; text-transform: uppercase;">Appointment Reference</div>
                    <div style="font-weight: 700; color: #0284c7; font-size: 1rem; margin-top: 0.2rem;">
                        <?php echo !empty($activeQueue['appointment_ref']) ? e($activeQueue['appointment_ref']) : 'Walk-in Ticket'; ?>
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1rem;">
                    <div style="font-size: 0.78rem; color: #64748b; text-transform: uppercase;">Check-in Timestamp</div>
                    <div style="font-weight: 700; color: #1e293b; font-size: 0.95rem; margin-top: 0.2rem;">
                        <?php echo date('d M Y, h:i A', strtotime($activeQueue['created_at'])); ?>
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1rem;">
                    <div style="font-size: 0.78rem; color: #64748b; text-transform: uppercase;">Assigned Counter</div>
                    <div style="font-weight: 700; color: #16a34a; font-size: 0.95rem; margin-top: 0.2rem;">
                        <?php echo $activeQueue['counter_number'] ? e($activeQueue['counter_number']) : 'Standby (Assigned when called)'; ?>
                    </div>
                </div>
            </div>

            <!-- Position Banner -->
            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: var(--radius-sm); padding: 1.25rem; display: flex; justify-content: space-around; align-items: center; margin-bottom: 2rem;">
                <div>
                    <div style="font-size: 0.75rem; color: #1e40af; font-weight: 700; text-transform: uppercase;">PEOPLE AHEAD IN LINE</div>
                    <div style="font-size: 1.8rem; font-weight: 900; color: #1e3a8a; margin-top: 0.2rem;"><?php echo $aheadCount; ?></div>
                    <small style="color: #64748b;">Estimated wait: ~<?php echo $aheadCount * 10; ?> mins</small>
                </div>
                <div style="height: 45px; width: 1px; background: #cbd5e1;"></div>
                <div>
                    <div style="font-size: 0.75rem; color: #1e40af; font-weight: 700; text-transform: uppercase;">CURRENTLY SERVING IN HALL</div>
                    <div style="font-size: 1.8rem; font-weight: 900; color: #0284c7; margin-top: 0.2rem;">
                        <?php echo $currentlyServing ? e($currentlyServing['queue_number']) : 'None'; ?>
                    </div>
                    <small style="color: #64748b;"><?php echo $currentlyServing ? 'At Counter ' . e($currentlyServing['counter_id']) : 'No counters calling'; ?></small>
                </div>
            </div>

            <!-- Two Action Buttons -->
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="../ticket.php?num=<?php echo urlencode($activeQueue['queue_number']); ?>" target="_blank" class="btn btn-secondary" style="padding: 0.85rem 1.75rem; font-weight: 700;">
                    <?php echo getIcon('printer', 18); ?> Print Ticket Slip
                </a>
                <a href="../mobile_tracker.php?ticket=<?php echo urlencode($activeQueue['queue_number']); ?>" target="_blank" class="btn btn-primary" style="padding: 0.85rem 1.75rem; font-weight: 700;">
                    <?php echo getIcon('smartphone', 18); ?> Open Live Mobile Tracker
                </a>
            </div>
        </div>

    <?php elseif (!empty($todayEligibleAppointments)): ?>
        <!-- Eligible Appointment Ready for Check-in -->
        <div class="card" style="padding: 2.25rem;">
            <div style="background: #2563eb; color: #fff; padding: 1rem 1.5rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                <?php echo getIcon('calendar', 24); ?>
                <span style="font-weight: 700; font-size: 1.05rem;">Confirm Arrival for Today (<?php echo date('d M Y'); ?>)</span>
            </div>

            <p style="color: #475569; margin-bottom: 1.5rem; font-size: 0.95rem;">
                You have <strong><?php echo count($todayEligibleAppointments); ?></strong> eligible appointment(s) scheduled for today. Select one to generate your ticket:
            </p>

            <?php foreach ($todayEligibleAppointments as $app): ?>
                <div style="border: 2px solid #e2e8f0; border-radius: var(--radius-sm); padding: 1.5rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <div style="font-size: 0.85rem; color: #0284c7; font-weight: 700;">
                            <?php echo e($app['appointment_ref']); ?>
                        </div>
                        <div style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0.2rem 0;">
                            <?php echo e($app['service_name']); ?>
                        </div>
                        <div style="font-size: 0.88rem; color: #64748b;">
                            <?php echo getIcon('clock', 14); ?> <?php echo e($app['time_slot']); ?> &bull; Est. <?php echo (int)$app['est_duration']; ?> mins
                        </div>
                    </div>

                    <form method="POST" action="checkin.php" style="margin: 0;">
                        <input type="hidden" name="checkin_appointment_id" value="<?php echo $app['id']; ?>">
                        <button type="submit" class="btn btn-primary" style="background: #16a34a; border-color: #16a34a; padding: 0.85rem 1.75rem; font-weight: 700; font-size: 1rem;">
                            <?php echo getIcon('check-square', 18); ?> Check In Now
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <!-- No Appointments Today -->
        <div class="card" style="text-align: center; padding: 3.5rem 2rem;">
            <div style="width: 60px; height: 60px; border-radius: 50%; background: #fef2f2; color: #dc2626; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                <?php echo getIcon('alert-triangle', 28); ?>
            </div>
            <h3 style="font-size: 1.25rem; color: #1e293b; margin-bottom: 0.5rem;">No Appointments Scheduled for Today</h3>
            <p style="color: #64748b; max-width: 480px; margin: 0 auto 1.5rem auto;">
                You do not have a confirmed appointment for today (<?php echo date('d M Y'); ?>). You can book an appointment in advance or take an instant ticket at the walk-in kiosk.
            </p>
            <div style="display: flex; gap: 0.75rem; justify-content: center;">
                <a href="book_appointment.php" class="btn btn-primary">Book New Appointment</a>
                <a href="../index.php" class="btn btn-secondary">Take Walk-In Ticket</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
