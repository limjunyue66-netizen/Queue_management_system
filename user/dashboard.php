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

// 2. Fetch Upcoming Appointments (Display all active/recent)
$stmt = $pdo->prepare("
    SELECT a.*, s.name as service_name 
    FROM appointments a 
    JOIN services s ON a.service_id = s.id 
    WHERE a.user_id = ? 
    ORDER BY a.appointment_date DESC, a.time_slot ASC
");
$stmt->execute([$userId]);
$recentAppointments = $stmt->fetchAll();

// 3. Fetch Recent Applications (Display all)
$stmt = $pdo->prepare("
    SELECT app.*, s.name as service_name 
    FROM applications app 
    JOIN services s ON app.service_id = s.id 
    WHERE app.user_id = ? 
    ORDER BY app.id DESC
");
$stmt->execute([$userId]);
$recentApplications = $stmt->fetchAll();

$pageTitle = 'Citizen Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Clean Top Header (Matches Image 1 layout) -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.65rem; font-weight: 800; color: #0f172a; margin-bottom: 0.25rem;">
            Welcome back, <?php echo e($currentUser['full_name']); ?>!
        </h1>
        <p style="color: var(--text-muted); font-size: 0.92rem; margin: 0;">
            Immigration Public Citizen Portal &bull; <?php echo date('l, d F Y'); ?>
        </p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <a href="book_appointment.php" class="btn btn-primary" style="font-weight: 700;">
            <?php echo getIcon('calendar', 16); ?> <span>New Appointment</span>
        </a>
        <a href="new_application.php" class="btn btn-secondary" style="font-weight: 600; background: #ffffff; border-color: #cbd5e1;">
            <?php echo getIcon('file-text', 16); ?> <span>New Application</span>
        </a>
    </div>
</div>

<!-- 4 Key Metric Cards (Row Layout with Left Colored Accents) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1.15rem; margin-bottom: 1.75rem;">
    <!-- 1. TOTAL BOOKINGS -->
    <div style="border: 1.5px solid #e2e8f0; border-left: 4.5px solid #2563eb; border-radius: 10px; background: #ffffff; padding: 1.15rem 1.25rem; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <div style="font-size: 0.76rem; font-weight: 800; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">TOTAL BOOKINGS</div>
            <div style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 0.2rem 0; line-height: 1.1;"><?php echo $totalBookings; ?></div>
            <div style="font-size: 0.8rem; color: #64748b;">All lifetime visits</div>
        </div>
        <div style="width: 38px; height: 38px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <?php echo getIcon('calendar', 20); ?>
        </div>
    </div>

    <!-- 2. UPCOMING VISITS -->
    <div style="border: 1.5px solid #e2e8f0; border-left: 4.5px solid #d97706; border-radius: 10px; background: #ffffff; padding: 1.15rem 1.25rem; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <div style="font-size: 0.76rem; font-weight: 800; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">UPCOMING VISITS</div>
            <div style="font-size: 1.85rem; font-weight: 800; color: #d97706; margin: 0.2rem 0; line-height: 1.1;"><?php echo $upcomingVisits; ?></div>
            <div style="font-size: 0.8rem; color: #64748b;">Scheduled visits</div>
        </div>
        <div style="width: 38px; height: 38px; border-radius: 8px; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <?php echo getIcon('clock', 20); ?>
        </div>
    </div>

    <!-- 3. CURRENT TICKET -->
    <div style="border: 1.5px solid #e2e8f0; border-left: 4.5px solid #059669; border-radius: 10px; background: #ffffff; padding: 1.15rem 1.25rem; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <div style="font-size: 0.76rem; font-weight: 800; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">CURRENT TICKET</div>
            <div style="font-size: 1.85rem; font-weight: 800; color: #059669; margin: 0.2rem 0; line-height: 1.1;">
                <?php echo $currentTicket ? e($currentTicket['queue_number']) : '<span style="font-size:1.35rem; color:#94a3b8; font-weight:600;">None</span>'; ?>
            </div>
            <div style="font-size: 0.8rem; color: #64748b;">
                <?php echo $currentTicket ? 'Status: ' . ucfirst(e($currentTicket['status'])) : 'No queue in hall'; ?>
            </div>
        </div>
        <div style="width: 38px; height: 38px; border-radius: 8px; background: #dcfce7; color: #059669; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <?php echo getIcon('ticket', 20); ?>
        </div>
    </div>

    <!-- 4. APPLICATIONS -->
    <div style="border: 1.5px solid #e2e8f0; border-left: 4.5px solid #0284c7; border-radius: 10px; background: #ffffff; padding: 1.15rem 1.25rem; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <div style="font-size: 0.76rem; font-weight: 800; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">APPLICATIONS</div>
            <div style="font-size: 1.85rem; font-weight: 800; color: #0284c7; margin: 0.2rem 0; line-height: 1.1;"><?php echo $totalApplications; ?></div>
            <div style="font-size: 0.8rem; color: #64748b;">Document requests</div>
        </div>
        <div style="width: 38px; height: 38px; border-radius: 8px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <?php echo getIcon('file-text', 20); ?>
        </div>
    </div>
</div>

<!-- Active Queue Banner if serving/calling/waiting -->
<?php if ($currentTicket): ?>
    <div style="margin-bottom: 1.75rem; background: #ecfdf5; border: 1.5px solid #a7f3d0; border-radius: 10px; padding: 1.15rem 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="font-size: 2.1rem; font-weight: 900; color: #065f46; letter-spacing: 1px;">
                <?php echo e($currentTicket['queue_number']); ?>
            </div>
            <div>
                <div style="font-weight: 700; color: #047857; font-size: 1.05rem;">
                    Your ticket is currently active: <?php echo ucfirst(e($currentTicket['status'])); ?>!
                </div>
                <div style="font-size: 0.85rem; color: #065f46; margin-top: 0.15rem;">
                    Service: <strong><?php echo e($currentTicket['service_type']); ?></strong> &bull; 
                    Station: <strong><?php echo $currentTicket['counter_number'] ? e($currentTicket['counter_number']) : 'Standby in Hall'; ?></strong>
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="../mobile_tracker.php?ticket=<?php echo urlencode($currentTicket['queue_number']); ?>" target="_blank" class="btn btn-primary btn-sm" style="font-weight: 700;">
                <?php echo getIcon('smartphone', 16); ?> Open Live Tracker
            </a>
            <a href="../ticket.php?id=<?php echo (int)$currentTicket['id']; ?>&num=<?php echo urlencode($currentTicket['queue_number']); ?>" target="_blank" class="btn btn-secondary btn-sm" style="background:#fff; font-weight: 600;">
                <?php echo getIcon('printer', 16); ?> Print Ticket
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Dual Layout: Upcoming Appointments (Left) & Recent Applications (Right) -->
<div style="display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(0, 0.85fr); gap: 1.5rem; align-items: start;">
    <!-- Left Column: Upcoming Appointments Card -->
    <div class="card" style="padding: 1.5rem; border: 1.5px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); overflow: hidden;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h2 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                <?php echo getIcon('calendar', 18, 'text-primary'); ?> Upcoming Appointments
            </h2>
        </div>

        <?php if (empty($recentAppointments)): ?>
            <div style="text-align: center; padding: 3rem 1rem; color: #94a3b8;">
                <?php echo getIcon('calendar', 32); ?>
                <p style="margin: 0.5rem 0 1rem 0;">No appointments booked yet.</p>
                <a href="book_appointment.php" class="btn btn-primary btn-sm">Book Your First Visit</a>
            </div>
        <?php else: ?>
            <div style="width: 100%; overflow-x: auto;">
                <table class="data-table dashboard-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="width: 26%; padding: 0.75rem 0.55rem; font-size: 0.78rem;">REFERENCE</th>
                            <th style="width: 25%; padding: 0.75rem 0.55rem; font-size: 0.78rem;">SERVICE</th>
                            <th style="width: 25%; padding: 0.75rem 0.55rem; font-size: 0.78rem;">DATE &amp; SLOT</th>
                            <th style="width: 12%; padding: 0.75rem 0.4rem; font-size: 0.78rem; text-align: center;">STATUS</th>
                            <th style="width: 12%; padding: 0.75rem 0.55rem; font-size: 0.78rem; text-align: right;">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentAppointments as $app): ?>
                            <tr>
                                <td style="padding: 0.85rem 0.55rem;">
                                    <strong style="color: #0284c7; font-size: 0.88rem; font-weight: 800; letter-spacing: 0.2px; white-space: nowrap;">
                                        <?php echo e($app['appointment_ref']); ?>
                                    </strong>
                                </td>
                                <td style="padding: 0.85rem 0.55rem;">
                                    <strong style="color: #0f172a; font-size: 0.88rem;"><?php echo e($app['service_name']); ?></strong>
                                </td>
                                <td style="padding: 0.85rem 0.55rem;">
                                    <div style="font-weight: 700; font-size: 0.86rem; color: #0f172a; white-space: nowrap;">
                                        <?php echo date('d M Y', strtotime($app['appointment_date'])); ?>
                                    </div>
                                    <div class="cell-sub" style="font-size: 0.78rem; white-space: nowrap; margin-top: 0.15rem;"><?php echo e($app['time_slot']); ?></div>
                                </td>
                                <td style="padding: 0.85rem 0.4rem; text-align: center; white-space: nowrap;">
                                    <?php echo renderStatusBadge($app['status']); ?>
                                </td>
                                <td style="padding: 0.85rem 0.55rem; text-align: right; white-space: nowrap;">
                                    <?php if ($app['appointment_date'] === date('Y-m-d') && $app['status'] === 'confirmed'): ?>
                                        <a href="checkin.php" class="btn btn-sm" style="background: #16a34a; color: #fff; padding: 0.3rem 0.65rem; font-size: 0.75rem; font-weight: 700;">
                                            Check In
                                        </a>
                                    <?php else: ?>
                                        <a href="my_appointments.php" class="btn btn-secondary btn-sm" style="padding: 0.3rem 0.65rem; font-size: 0.75rem;">
                                            Details
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Recent Applications Card -->
    <div class="card" style="padding: 1.5rem; border: 1.5px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h2 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                <?php echo getIcon('file-text', 18, 'text-primary'); ?> Recent Applications
            </h2>
        </div>

        <?php if (empty($recentApplications)): ?>
            <div style="text-align: center; padding: 3rem 1rem; color: #94a3b8;">
                <?php echo getIcon('file-text', 32); ?>
                <p style="margin: 0.5rem 0 1rem 0;">No document applications submitted.</p>
                <a href="new_application.php" class="btn btn-secondary btn-sm">Submit New Application</a>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                <?php foreach ($recentApplications as $appl): ?>
                    <div class="bordered-item-card" style="border: 1.5px solid #e2e8f0; border-radius: 10px; background: #ffffff; padding: 1.1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);">
                        <div>
                            <div style="font-size: 0.82rem; color: #0284c7; font-weight: 800;">
                                <?php echo e($appl['application_ref']); ?>
                            </div>
                            <div style="font-weight: 700; color: #1e293b; font-size: 0.98rem; margin: 0.15rem 0;">
                                <?php echo e($appl['service_name']); ?>
                            </div>
                            <div style="font-size: 0.8rem; color: #64748b;">
                                Submitted: <?php echo date('d M Y', strtotime($appl['submitted_date'])); ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div><?php echo renderStatusBadge($appl['status']); ?></div>
                            <a href="my_applications.php" style="display: inline-block; margin-top: 0.45rem; font-size: 0.82rem; color: var(--primary); font-weight: 700; text-decoration: none;">
                                View &gt;
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
