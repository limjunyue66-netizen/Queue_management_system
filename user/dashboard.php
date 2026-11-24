<?php
// ===================================================
// Citizen Portal: User Dashboard
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireUser();
$currentUser = currentUser();
$pdo = getDBConnection();
$userId = $currentUser['id'];

// 1. Fetch Stats
// Total Bookings
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE user_id = ?");
$stmt->execute([$userId]);
$totalBookings = (int)$stmt->fetchColumn();

// Upcoming Visits (today or future)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE user_id = ? AND appointment_date >= CURDATE() AND status = 'confirmed'");
$stmt->execute([$userId]);
$upcomingVisits = (int)$stmt->fetchColumn();

// Current Active Ticket Today
$stmt = $pdo->prepare("
    SELECT q.*, c.counter_number 
    FROM queues q 
    LEFT JOIN counters c ON q.counter_id = c.id
    WHERE q.customer_name = ? AND DATE(q.created_at) = CURDATE() AND q.status IN ('waiting', 'calling', 'serving')
    ORDER BY q.id DESC LIMIT 1
");
$stmt->execute([$currentUser['full_name']]);
$currentTicket = $stmt->fetch();

// Total Applications
$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE user_id = ?");
$stmt->execute([$userId]);
$totalApplications = (int)$stmt->fetchColumn();

// 2. Fetch Upcoming Appointments
$stmt = $pdo->prepare("
    SELECT a.*, s.name as service_name 
    FROM appointments a 
    JOIN services s ON a.service_id = s.id 
    WHERE a.user_id = ? 
    ORDER BY a.appointment_date DESC, a.time_slot ASC 
    LIMIT 5
");
$stmt->execute([$userId]);
$recentAppointments = $stmt->fetchAll();

// 3. Fetch Recent Applications
$stmt = $pdo->prepare("
    SELECT app.*, s.name as service_name 
    FROM applications app 
    JOIN services s ON app.service_id = s.id 
    WHERE app.user_id = ? 
    ORDER BY app.id DESC 
    LIMIT 5
");
$stmt->execute([$userId]);
$recentApplications = $stmt->fetchAll();

$pageTitle = 'Citizen Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 2rem;">
    <!-- Welcome Header Banner -->
    <div class="card" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; padding: 1.75rem; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); border-left: 5px solid var(--primary);">
        <div>
            <h1 style="font-size: 1.7rem; font-weight: 800; color: #0f172a; margin-bottom: 0.25rem;">
                Welcome back, <?php echo e($currentUser['full_name']); ?>!
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">
                Public Citizen Portal &bull; <?php echo date('l, d F Y'); ?>
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="book_appointment.php" class="btn btn-primary">
                <?php echo getIcon('calendar', 16); ?> <span>New Appointment</span>
            </a>
            <a href="new_application.php" class="btn btn-secondary">
                <?php echo getIcon('file-text', 16); ?> <span>New Application</span>
            </a>
        </div>
    </div>
</div>

<!-- 4 Key Metric Cards -->
<div class="grid grid-cols-4" style="margin-bottom: 2rem;">
    <div class="metric-card">
        <div class="metric-info">
            <span class="metric-title">TOTAL BOOKINGS</span>
            <span class="metric-number"><?php echo $totalBookings; ?></span>
        </div>
        <div class="metric-icon" style="background: #e0f2fe; color: #0284c7;">
            <?php echo getIcon('calendar', 24); ?>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-info">
            <span class="metric-title">UPCOMING VISITS</span>
            <span class="metric-number" style="color: #d97706;"><?php echo $upcomingVisits; ?></span>
        </div>
        <div class="metric-icon" style="background: #fef3c7; color: #d97706;">
            <?php echo getIcon('clock', 24); ?>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-info">
            <span class="metric-title">CURRENT TICKET</span>
            <span class="metric-number" style="color: #16a34a;">
                <?php echo $currentTicket ? e($currentTicket['queue_number']) : '<span style="font-size:1.2rem; color:#94a3b8;">None</span>'; ?>
            </span>
        </div>
        <div class="metric-icon" style="background: #dcfce7; color: #16a34a;">
            <?php echo getIcon('ticket', 24); ?>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-info">
            <span class="metric-title">APPLICATIONS</span>
            <span class="metric-number" style="color: #6366f1;"><?php echo $totalApplications; ?></span>
        </div>
        <div class="metric-icon" style="background: #e0e7ff; color: #6366f1;">
            <?php echo getIcon('file-text', 24); ?>
        </div>
    </div>
</div>

<!-- Active Queue Banner if serving/calling -->
<?php if ($currentTicket): ?>
    <div class="card" style="margin-bottom: 2rem; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 1.25rem 1.75rem; display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="font-size: 2.2rem; font-weight: 900; color: #065f46;">
                <?php echo e($currentTicket['queue_number']); ?>
            </div>
            <div>
                <div style="font-weight: 700; color: #047857; font-size: 1.05rem;">
                    Your ticket is currently active: <?php echo ucfirst(e($currentTicket['status'])); ?>!
                </div>
                <div style="font-size: 0.85rem; color: #065f46;">
                    Service: <strong><?php echo e($currentTicket['service_type']); ?></strong> &bull; 
                    Counter: <strong><?php echo $currentTicket['counter_number'] ? e($currentTicket['counter_number']) : 'Standby in Hall'; ?></strong>
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="../mobile_tracker.php?ticket=<?php echo urlencode($currentTicket['queue_number']); ?>" target="_blank" class="btn btn-primary btn-sm">
                <?php echo getIcon('smartphone', 16); ?> Open Live Tracker
            </a>
            <a href="../ticket.php?num=<?php echo urlencode($currentTicket['queue_number']); ?>" target="_blank" class="btn btn-secondary btn-sm">
                <?php echo getIcon('printer', 16); ?> Print Ticket
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Dual Layout: Upcoming Appointments & Recent Applications -->
<div class="grid grid-cols-2" style="gap: 1.5rem;">
    <!-- Appointments Card -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h2 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                <?php echo getIcon('calendar', 18, 'text-primary'); ?> Upcoming Appointments
            </h2>
            <a href="my_appointments.php" style="font-size: 0.85rem; color: var(--primary); font-weight: 600; text-decoration: none;">View All &rarr;</a>
        </div>

        <?php if (empty($recentAppointments)): ?>
            <div style="text-align: center; padding: 2.5rem 1rem; color: #94a3b8;">
                <p>No appointments booked yet.</p>
                <a href="book_appointment.php" class="btn btn-primary btn-sm">Book Your First Visit</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>REFERENCE</th>
                            <th>SERVICE</th>
                            <th>DATE & SLOT</th>
                            <th>STATUS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentAppointments as $app): ?>
                            <tr>
                                <td>
                                    <strong style="color: #0284c7; font-size: 0.95rem; font-weight: 800; letter-spacing: 0.3px;"><?php echo e($app['appointment_ref']); ?></strong>
                                </td>
                                <td>
                                    <strong style="color: #0f172a; font-size: 0.93rem;"><?php echo e($app['service_name']); ?></strong>
                                </td>
                                <td>
                                    <div style="font-weight: 700; font-size: 0.92rem; color: #0f172a;"><?php echo date('d M Y', strtotime($app['appointment_date'])); ?></div>
                                    <div class="cell-sub"><?php echo e($app['time_slot']); ?></div>
                                </td>
                                <td><?php echo renderStatusBadge($app['status']); ?></td>
                                <td>
                                    <div class="table-actions">
                                        <?php if ($app['appointment_date'] === date('Y-m-d') && $app['status'] === 'confirmed'): ?>
                                            <a href="checkin.php" class="btn btn-sm" style="background: #16a34a; color: #fff;">
                                                Check In
                                            </a>
                                        <?php else: ?>
                                            <a href="my_appointments.php" class="btn btn-secondary btn-sm">Details</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Applications Card -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h2 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                <?php echo getIcon('file-text', 18, 'text-primary'); ?> Recent Applications
            </h2>
            <a href="my_applications.php" style="font-size: 0.85rem; color: var(--primary); font-weight: 600; text-decoration: none;">View All &rarr;</a>
        </div>

        <?php if (empty($recentApplications)): ?>
            <div style="text-align: center; padding: 2.5rem 1rem; color: #94a3b8;">
                <p>No document applications submitted.</p>
                <a href="new_application.php" class="btn btn-secondary btn-sm">Submit New Application</a>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                <?php foreach ($recentApplications as $appl): ?>
                    <div class="bordered-item-card">
                        <div>
                            <div style="font-size: 0.82rem; color: #0284c7; font-weight: 700;">
                                <?php echo e($appl['application_ref']); ?>
                            </div>
                            <div style="font-weight: 700; color: #1e293b; font-size: 0.98rem; margin: 0.2rem 0;">
                                <?php echo e($appl['service_name']); ?>
                            </div>
                            <div style="font-size: 0.82rem; color: #64748b;">
                                Submitted: <?php echo date('d M Y', strtotime($appl['submitted_date'])); ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div><?php echo renderStatusBadge($appl['status']); ?></div>
                            <a href="my_applications.php" class="btn btn-outline btn-sm" style="margin-top: 0.45rem; padding: 0.3rem 0.75rem; font-size: 0.78rem;">
                                View Details &rarr;
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
