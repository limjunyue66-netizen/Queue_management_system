<?php
// ===================================================
// Citizen Portal: My Appointments List
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireUser();
$currentUser = currentUser();
$pdo = getDBConnection();
$userId = $currentUser['id'];

// Handle Cancel Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $cancelId = (int)$_POST['cancel_id'];
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'confirmed'");
    $stmt->execute([$cancelId, $userId]);
    setFlash('info', 'Your appointment has been cancelled.');
    header("Location: my_appointments.php");
    exit;
}

// Fetch all appointments
$stmt = $pdo->prepare("
    SELECT a.*, s.name as service_name, s.code as service_code, s.est_duration
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    WHERE a.user_id = ?
    ORDER BY a.appointment_date DESC, a.time_slot ASC
");
$stmt->execute([$userId]);
$appointments = $stmt->fetchAll();

$pageTitle = 'My Appointments';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 1160px; margin: 2rem auto; padding: 0 1rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.7rem; font-weight: 800; color: #0f172a; margin-bottom: 0.35rem; letter-spacing: -0.3px;">
                My Scheduled Appointments
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">
                View your confirmed visits, check in upon arrival, or cancel if your schedule changes.
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="checkin.php" class="btn" style="background: #16a34a; color: #fff; padding: 0.55rem 1.15rem; font-weight: 600;">
                <?php echo getIcon('check', 16); ?> Office Check-In
            </a>
            <a href="book_appointment.php" class="btn btn-primary" style="padding: 0.55rem 1.15rem; font-weight: 600;">
                <?php echo getIcon('plus', 16); ?> Book New Appointment
            </a>
        </div>
    </div>

    <?php if (empty($appointments)): ?>
        <div class="card" style="text-align: center; padding: 4rem 2rem;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                <?php echo getIcon('clock', 32); ?>
            </div>
            <h3 style="font-size: 1.25rem; font-weight: 700; color: #334155; margin-bottom: 0.5rem;">No Appointments Found</h3>
            <p style="color: #64748b; margin-bottom: 1.5rem;">You have not scheduled any service visits yet.</p>
            <a href="book_appointment.php" class="btn btn-primary">Book Your First Appointment</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 22%;">REF NUMBER</th>
                        <th style="width: 24%;">SERVICE</th>
                        <th style="width: 20%;">DATE & TIME</th>
                        <th style="width: 14%;">EST DURATION</th>
                        <th style="width: 10%;">STATUS</th>
                        <th style="width: 10%; text-align: right;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $a): 
                        $isToday = ($a['appointment_date'] === date('Y-m-d'));
                        $isConfirmed = ($a['status'] === 'confirmed');
                    ?>
                        <tr>
                            <td>
                                <strong style="color: #0284c7; font-size: 0.98rem; font-weight: 800; letter-spacing: 0.3px;"><?php echo e($a['appointment_ref']); ?></strong>
                                <?php if (!empty($a['remarks'])): ?>
                                    <div class="cell-sub"><?php echo e($a['remarks']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center;">
                                    <span class="service-code-tag">
                                        <?php echo e($a['service_code']); ?>
                                    </span>
                                    <strong style="color: #0f172a; font-size: 0.95rem;"><?php echo e($a['service_name']); ?></strong>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: #0f172a; font-size: 0.93rem;"><?php echo date('D, d M Y', strtotime($a['appointment_date'])); ?></div>
                                <div class="cell-sub"><?php echo e($a['time_slot']); ?></div>
                            </td>
                            <td>
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; color: #475569; font-weight: 600; font-size: 0.9rem;">
                                    <?php echo getIcon('clock', 14); ?> ~<?php echo (int)$a['est_duration']; ?> mins
                                </span>
                            </td>
                            <td><?php echo renderStatusBadge($a['status']); ?></td>
                            <td style="text-align: right;">
                                <div class="table-actions" style="justify-content: flex-end;">
                                    <?php if ($isToday && $isConfirmed): ?>
                                        <form method="POST" action="checkin.php" style="margin: 0; display: inline;">
                                            <input type="hidden" name="checkin_appointment_id" value="<?php echo $a['id']; ?>">
                                            <button type="submit" class="btn btn-sm" style="background: #16a34a; color: #fff; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.3rem;">
                                                <?php echo getIcon('check', 14); ?> Check In
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($isConfirmed): ?>
                                        <form method="POST" action="my_appointments.php" onsubmit="return confirm('Are you sure you want to cancel this appointment?');" style="margin: 0;">
                                            <input type="hidden" name="cancel_id" value="<?php echo $a['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Cancel Booking">
                                                Cancel
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.85rem;">-</span>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
