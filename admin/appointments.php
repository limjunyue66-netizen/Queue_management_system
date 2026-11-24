<?php
// ===================================================
// Administrator: Manage Appointments
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$pdo = getDBConnection();

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $appId = (int)($_POST['id'] ?? 0);

    if ($action === 'set_status' && $appId > 0) {
        $status = $_POST['status'] ?? 'confirmed';
        $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->execute([$status, $appId]);
        setFlash('success', "Appointment status updated to " . ucfirst($status) . ".");
        header("Location: appointments.php");
        exit;
    }
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$dateFilter = $_GET['date'] ?? '';

$sql = "
    SELECT a.*, u.full_name as user_name, u.email as user_email, u.phone as user_phone, u.ic_passport as user_ic, s.name as service_name, s.code as service_code
    FROM appointments a
    JOIN users u ON a.user_id = u.id
    JOIN services s ON a.service_id = s.id
    WHERE 1=1
";
$params = [];
if (!empty($statusFilter)) {
    $sql .= " AND a.status = ?";
    $params[] = $statusFilter;
}
if (!empty($dateFilter)) {
    $sql .= " AND a.appointment_date = ?";
    $params[] = $dateFilter;
}
$sql .= " ORDER BY a.appointment_date DESC, a.time_slot ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$pageTitle = 'Manage Citizen Appointments';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 1100px; margin: 1.5rem auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-bottom: 0.25rem;">
                Citizen Appointments (<?php echo count($appointments); ?>)
            </h1>
            <p style="color: var(--text-muted); font-size: 0.92rem; margin: 0;">
                Review upcoming scheduled visits, verify check-in records, and manage booking statuses.
            </p>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
        <form method="GET" action="appointments.php" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 180px;">
                <label class="form-label" for="date" style="font-size: 0.85rem;">Filter Date</label>
                <input type="date" id="date" name="date" class="form-control" value="<?php echo e($dateFilter); ?>">
            </div>

            <div style="flex: 1; min-width: 180px;">
                <label class="form-label" for="status" style="font-size: 0.85rem;">Filter Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="">-- All Statuses --</option>
                    <option value="confirmed" <?php echo ($statusFilter === 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="completed" <?php echo ($statusFilter === 'completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo ($statusFilter === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.25rem;">
                <?php echo getIcon('search', 14); ?> Apply Filter
            </button>
            <?php if (!empty($dateFilter) || !empty($statusFilter)): ?>
                <a href="appointments.php" class="btn btn-secondary" style="padding: 0.65rem 1rem;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Appointments Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 24%;">REF & SERVICE</th>
                        <th style="width: 22%;">CITIZEN INFO</th>
                        <th style="width: 20%;">SCHEDULED DATE & TIME</th>
                        <th style="width: 14%;">CHECK-IN TIME</th>
                        <th style="width: 10%;">STATUS</th>
                        <th style="width: 10%; text-align: right;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appointments)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 3rem 1rem; color: #94a3b8;">
                                No appointments found matching your filter.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($appointments as $a): ?>
                            <tr>
                                <td>
                                    <strong style="color: #0284c7; font-size: 0.98rem; font-weight: 800; letter-spacing: 0.3px;"><?php echo e($a['appointment_ref']); ?></strong>
                                    <div style="display: flex; align-items: center; margin-top: 0.35rem;">
                                        <span class="service-code-tag"><?php echo e($a['service_code']); ?></span>
                                        <strong style="color: #0f172a; font-size: 0.92rem;"><?php echo e($a['service_name']); ?></strong>
                                    </div>
                                    <?php if (!empty($a['remarks'])): ?>
                                        <div class="cell-sub"><?php echo e($a['remarks']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #0f172a; font-size: 0.93rem;"><?php echo e($a['user_name']); ?></div>
                                    <div class="cell-sub">
                                        IC: <?php echo e($a['user_ic']); ?> &bull; <?php echo e($a['user_phone']); ?>
                                    </div>
                                    <div style="font-size: 0.78rem; color: #94a3b8;"><?php echo e($a['user_email']); ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #0f172a; font-size: 0.93rem;"><?php echo date('D, d M Y', strtotime($a['appointment_date'])); ?></div>
                                    <div class="cell-sub"><?php echo e($a['time_slot']); ?></div>
                                </td>
                                <td>
                                    <?php if ($a['check_in_time']): ?>
                                        <span style="color: #16a34a; font-weight: 700; font-size: 0.88rem; display: inline-flex; align-items: center; gap: 0.3rem;">
                                            <?php echo getIcon('check', 14); ?> <?php echo date('h:i A', strtotime($a['check_in_time'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.85rem;">Not checked in</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo renderStatusBadge($a['status']); ?></td>
                                <td style="text-align: right;">
                                    <div class="table-actions" style="justify-content: flex-end;">
                                        <form method="POST" action="appointments.php" style="display: inline-flex; gap: 0.45rem; margin: 0;">
                                            <input type="hidden" name="action" value="set_status">
                                            <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                            <?php if ($a['status'] === 'confirmed'): ?>
                                                <button type="submit" name="status" value="completed" class="btn btn-sm" style="background:#16a34a; color:#fff;" title="Mark Completed">
                                                    Complete
                                                </button>
                                                <button type="submit" name="status" value="cancelled" class="btn btn-danger btn-sm" title="Cancel Booking">
                                                    Cancel
                                                </button>
                                            <?php elseif ($a['status'] === 'cancelled'): ?>
                                                <button type="submit" name="status" value="confirmed" class="btn btn-secondary btn-sm">
                                                    Re-confirm
                                                </button>
                                            <?php else: ?>
                                                <span style="color: #94a3b8; font-size: 0.85rem;">-</span>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
