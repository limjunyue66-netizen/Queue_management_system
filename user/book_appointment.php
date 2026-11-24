<?php
// ===================================================
// Citizen Portal: Book Appointment
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireUser();
$currentUser = currentUser();
$pdo = getDBConnection();
$userId = $currentUser['id'];

$services = getAllServices($pdo, true);
$timeSlots = getStandardTimeSlots();

$error = '';
$selectedService = $_POST['service_id'] ?? ($services[0]['id'] ?? 1);
$selectedDate = $_POST['appointment_date'] ?? date('Y-m-d');
$selectedSlot = $_POST['time_slot'] ?? $timeSlots[0];
$remarks = trim($_POST['remarks'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    $serviceId = (int)($_POST['service_id'] ?? 0);
    $date = trim($_POST['appointment_date'] ?? '');
    $slot = trim($_POST['time_slot'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($serviceId) || empty($date) || empty($slot)) {
        $error = 'Please select a service, date, and time slot.';
    } elseif ($date < date('Y-m-d')) {
        $error = 'Appointment date cannot be in the past.';
    } else {
        // Check slot availability
        $available = getAvailableSlotsCount($pdo, $serviceId, $date, $slot);
        if ($available <= 0) {
            $error = 'The selected time slot is full. Please choose another time slot.';
        } else {
            $ref = generateAppointmentRef($pdo);
            $stmt = $pdo->prepare("
                INSERT INTO appointments (appointment_ref, user_id, service_id, appointment_date, time_slot, remarks, status)
                VALUES (?, ?, ?, ?, ?, ?, 'confirmed')
            ");
            $stmt->execute([$ref, $userId, $serviceId, $date, $slot, $remarks]);

            setFlash('success', "Appointment booked successfully! Reference: $ref. You can Check In when arriving at the office.");
            header("Location: my_appointments.php");
            exit;
        }
    }
}

$pageTitle = 'Book Service Appointment';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 800px; margin: 1.5rem auto;">
    <div class="card" style="padding: 2.25rem;">
        <div style="margin-bottom: 2rem;">
            <h1 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.65rem;">
                <span style="color: var(--primary);"><?php echo getIcon('calendar', 24); ?></span> Appointment Booking Form
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem;">
                Schedule an official service visit to avoid long waiting lines. Appointments can be made for today or future dates.
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo getIcon('alert-triangle', 18); ?> <span><?php echo e($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="book_appointment.php" id="bookingForm">
            <!-- 1. Select Service -->
            <div style="margin-bottom: 2rem;">
                <label class="form-label" style="font-weight: 700; font-size: 1rem; color: #1e293b; margin-bottom: 0.75rem; display: block;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: var(--primary); color: #fff; font-size: 0.8rem; margin-right: 0.5rem;">1</span>
                    Select Immigration Service *
                </label>
                <select name="service_id" id="service_id" class="form-control" style="font-size: 1rem; padding: 0.75rem;" required onchange="this.form.submit()">
                    <option value="">-- Choose a service --</option>
                    <?php foreach ($services as $srv): ?>
                        <option value="<?php echo $srv['id']; ?>" <?php echo ($selectedService == $srv['id']) ? 'selected' : ''; ?>>
                            [<?php echo e($srv['code']); ?>] <?php echo e($srv['name']); ?> (Est: <?php echo e($srv['est_duration']); ?> mins)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 2. Select Date -->
            <div style="margin-bottom: 2rem;">
                <label class="form-label" style="font-weight: 700; font-size: 1rem; color: #1e293b; margin-bottom: 0.75rem; display: block;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: var(--primary); color: #fff; font-size: 0.8rem; margin-right: 0.5rem;">2</span>
                    Select Preferred Date *
                </label>
                <input type="date" name="appointment_date" id="appointment_date" class="form-control" style="font-size: 1rem; padding: 0.75rem;" min="<?php echo date('Y-m-d'); ?>" value="<?php echo e($selectedDate); ?>" required onchange="this.form.submit()">
            </div>

            <!-- 3. Select Available Time Slot -->
            <div style="margin-bottom: 2rem;">
                <label class="form-label" style="font-weight: 700; font-size: 1rem; color: #1e293b; margin-bottom: 0.75rem; display: block;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: var(--primary); color: #fff; font-size: 0.8rem; margin-right: 0.5rem;">3</span>
                    Select Available Time Slot *
                </label>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.85rem;">
                    <?php foreach ($timeSlots as $slot): 
                        $left = getAvailableSlotsCount($pdo, $selectedService, $selectedDate, $slot);
                        $isFull = ($left <= 0);
                        $isChecked = ($selectedSlot === $slot && !$isFull);
                    ?>
                        <label style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1rem; border: 2px solid <?php echo $isChecked ? 'var(--primary)' : ($isFull ? '#e2e8f0' : 'var(--border-color)'); ?>; border-radius: var(--radius-sm); cursor: <?php echo $isFull ? 'not-allowed' : 'pointer'; ?>; background: <?php echo $isChecked ? '#eff6ff' : ($isFull ? '#f8fafc' : '#fff'); ?>; opacity: <?php echo $isFull ? '0.6' : '1'; ?>;">
                            <div style="display: flex; align-items: center; gap: 0.65rem;">
                                <input type="radio" name="time_slot" value="<?php echo e($slot); ?>" <?php echo $isChecked ? 'checked' : ''; ?> <?php echo $isFull ? 'disabled' : ''; ?> style="accent-color: var(--primary);">
                                <span style="font-weight: 600; font-size: 0.92rem; color: #1e293b;"><?php echo e($slot); ?></span>
                            </div>
                            <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 12px; background: <?php echo $isFull ? '#fee2e2' : '#dcfce7'; ?>; color: <?php echo $isFull ? '#b91c1c' : '#15803d'; ?>;">
                                <?php echo $isFull ? 'Full' : "$left slots left"; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 4. Remarks -->
            <div style="margin-bottom: 2rem;">
                <label class="form-label" style="font-weight: 700; font-size: 1rem; color: #1e293b; margin-bottom: 0.75rem; display: block;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: var(--primary); color: #fff; font-size: 0.8rem; margin-right: 0.5rem;">4</span>
                    Remarks / Purpose (Optional)
                </label>
                <textarea name="remarks" class="form-control" rows="3" placeholder="Provide any additional context or previous application reference numbers..."><?php echo e($remarks); ?></textarea>
            </div>

            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--radius-sm); padding: 1rem; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.88rem; color: #166534;">
                <?php echo getIcon('info', 20); ?>
                <span><strong>Check-in Reminder:</strong> On the day of your appointment, arrive at the center and click <em>Office Check-In</em> on your phone or kiosk to immediately generate your queue ticket.</span>
            </div>

            <button type="submit" name="submit_booking" value="1" class="btn btn-primary" style="padding: 0.85rem 1.75rem; font-size: 1rem; font-weight: 700;">
                <?php echo getIcon('check', 18); ?> Confirm & Book Appointment
            </button>
            <a href="dashboard.php" class="btn btn-secondary" style="padding: 0.85rem 1.5rem;">Cancel</a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
