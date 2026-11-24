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

<div style="max-width: 950px; margin: 1.5rem auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-bottom: 0.25rem;">
                My Scheduled Appointments
            </h1>
            <p style="color: var(--text-muted); font-size: 0.92rem; margin: 0;">
                View your confirmed visits, check in upon arrival, or cancel if your schedule changes.
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="checkin.php" class="btn btn-sm" style="background: #16a34a; color: #fff;">
                <?php echo getIcon('check-square', 14); ?> Office Check-In
            </a>
            <a href="book_appointment.php" class="btn btn-primary btn-sm">
                <?php echo getIcon('plus', 14); ?> Book New Appointment
            </a>
        </div>
    </div>

    <?php if (empty($appointments)): ?>
        <div class="card" style="text-align: center; padding: 4rem 2rem;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                <?php echo getIcon('calendar', 32); ?>
            </div>
            <h3 style="font-size: 1.2rem; color: #334155; margin-bottom: 0.5rem;">No Appointments Found</h3>
            <p style="color: #64748b; margin-bottom: 1.5rem;">You have not scheduled any service visits yet.</p>
            <a href="book_appointment.php" class="btn btn-primary">Book Your First Appointment</a>
        </div>
    <?php else: ?>
        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>REF NUMBER</th>
                            <th>SERVICE</th>
                            <th>DATE & TIME</th>
                            <th>EST DURATION</th>
                            <th>STATUS</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $a): 
                            $isToday = ($a['appointment_date'] === date('Y-m-d'));
                            $isConfirmed = ($a['status'] === 'confirmed');
                        ?>
                            <tr>
                                <td>
                                    <strong style="color: #0284c7;"><?php echo e($a['appointment_ref']); ?></strong>
                                    <?php if (!empty($a['remarks'])): ?>
                                        <div style="font-size: 0.75rem; color: #64748b;"><?php echo e($a['remarks']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="display: inline-block; padding: 0.15rem 0.4rem; background: #f1f5f9; border-radius: 4px; font-weight: 700; font-size: 0.75rem; color: #475569; margin-right: 0.35rem;">
                                        <?php echo e($a['service_code']); ?>
                                    </span>
                                    <strong><?php echo e($a['service_name']); ?></strong>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #1e293b;"><?php echo date('D, d M Y', strtotime($a['appointment_date'])); ?></div>
                                    <div style="font-size: 0.8rem; color: #64748b;"><?php echo e($a['time_slot']); ?></div>
                                </td>
                                <td>~<?php echo (int)$a['est_duration']; ?> mins</td>
                                <td><?php echo renderStatusBadge($a['status']); ?></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <?php if ($isToday && $isConfirmed): ?>
                                            <a href="checkin.php" class="btn btn-sm" style="background: #16a34a; color: #fff; padding: 0.3rem 0.65rem;">
                                                <?php echo getIcon('check', 14); ?> Check In
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($isConfirmed): ?>
                                            <form method="POST" action="my_appointments.php" onsubmit="return confirm('Are you sure you want to cancel this appointment?');" style="margin: 0;">
                                                <input type="hidden" name="cancel_id" value="<?php echo $a['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" style="padding: 0.3rem 0.5rem;" title="Cancel Booking">
                                                    Cancel
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 0.8rem;">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
