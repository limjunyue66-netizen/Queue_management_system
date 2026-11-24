<?php
// ===================================================
// Counter Management (Admin Only)
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$pdo = getDBConnection();
$pageTitle = 'Counter Management';

// Handle Actions
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create_counter') {
        $counterNumber = trim($_POST['counter_number'] ?? '');
        $counterName   = trim($_POST['counter_name'] ?? '');
        $status        = trim($_POST['status'] ?? 'open');
        $staffId       = !empty($_POST['staff_id']) ? intval($_POST['staff_id']) : null;

        if (empty($counterNumber) || empty($counterName)) {
            setFlash('error', 'Counter Number and Name are required.');
        } else {
            // Check uniqueness
            $chk = $pdo->prepare("SELECT id FROM counters WHERE counter_number = ?");
            $chk->execute([$counterNumber]);
            if ($chk->fetch()) {
                setFlash('error', 'Counter identifier "' . e($counterNumber) . '" already exists.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO counters (counter_number, counter_name, status, current_staff_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$counterNumber, $counterName, $status, $staffId]);
                setFlash('success', 'Counter ' . e($counterNumber) . ' added successfully.');
                header("Location: counters.php");
                exit;
            }
        }
    } elseif ($action === 'toggle_status') {
        $counterId = intval($_POST['counter_id'] ?? 0);
        $newStatus = $_POST['new_status'] === 'open' ? 'open' : 'closed';
        $stmt = $pdo->prepare("UPDATE counters SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $counterId]);
        setFlash('success', 'Counter status updated to ' . ucfirst($newStatus) . '.');
        header("Location: counters.php");
        exit;
    } elseif ($action === 'edit_counter') {
        $counterId     = intval($_POST['counter_id'] ?? 0);
        $counterNumber = trim($_POST['counter_number'] ?? '');
        $counterName   = trim($_POST['counter_name'] ?? '');
        $status        = trim($_POST['status'] ?? 'open');
        $staffId       = !empty($_POST['staff_id']) ? intval($_POST['staff_id']) : null;

        $stmt = $pdo->prepare("UPDATE counters SET counter_number = ?, counter_name = ?, status = ?, current_staff_id = ? WHERE id = ?");
        $stmt->execute([$counterNumber, $counterName, $status, $staffId, $counterId]);
        setFlash('success', 'Counter updated successfully.');
        header("Location: counters.php");
        exit;
    } elseif ($action === 'delete_counter') {
        $counterId = intval($_POST['counter_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM counters WHERE id = ?");
        $stmt->execute([$counterId]);
        setFlash('success', 'Counter deleted successfully.');
        header("Location: counters.php");
        exit;
    }
}

// Fetch active staff for assigning
$staffList = $pdo->query("SELECT id, full_name, username FROM users WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();

// Fetch counters
$counters = $pdo->query("
    SELECT c.*, u.full_name AS staff_name 
    FROM counters c 
    LEFT JOIN users u ON c.current_staff_id = u.id 
    ORDER BY c.id ASC
")->fetchAll();

// Check if editing
$editCounter = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM counters WHERE id = ?");
    $stmt->execute([$editId]);
    $editCounter = $stmt->fetch();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo getIcon('counter', 26); ?> Counter Management</h1>
        <p class="page-desc">Configure service stations, open/close counters, and assign staff members</p>
    </div>
</div>

<div class="counters-layout" style="display: grid; grid-template-columns: 360px minmax(0, 1fr); gap: 1.5rem; align-items: start;">
    <!-- Add or Edit Form -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <?php echo $editCounter ? getIcon('edit', 18) . ' Edit Counter' : getIcon('plus', 18) . ' Add New Counter'; ?>
            </h2>
            <?php if ($editCounter): ?>
                <a href="counters.php" class="btn btn-sm btn-secondary"><?php echo getIcon('x', 14); ?> Cancel</a>
            <?php endif; ?>
        </div>

        <form method="POST" action="counters.php">
            <input type="hidden" name="action" value="<?php echo $editCounter ? 'edit_counter' : 'create_counter'; ?>">
            <?php if ($editCounter): ?>
                <input type="hidden" name="counter_id" value="<?php echo (int)$editCounter['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label" for="counter_number">Counter Display Identifier</label>
                <input type="text" id="counter_number" name="counter_number" class="form-control" 
                       value="<?php echo e($editCounter['counter_number'] ?? ''); ?>" required placeholder="e.g. Counter 1, Window A, Desk 3">
            </div>

            <div class="form-group">
                <label class="form-label" for="counter_name">Counter Service Description</label>
                <input type="text" id="counter_name" name="counter_name" class="form-control" 
                       value="<?php echo e($editCounter['counter_name'] ?? ''); ?>" required placeholder="e.g. Registration & General Enquiry">
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label" for="status">Initial Status</label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="open" <?php echo ($editCounter && $editCounter['status'] === 'open') ? 'selected' : ''; ?>>Open (Active)</option>
                        <option value="closed" <?php echo ($editCounter && $editCounter['status'] === 'closed') ? 'selected' : ''; ?>>Closed (Offline)</option>
                    </select>
                </div>

                <div class="form-col form-group">
                    <label class="form-label" for="staff_id">Assigned Staff (Optional)</label>
                    <select id="staff_id" name="staff_id" class="form-control">
                        <option value="">-- None / Standby --</option>
                        <?php foreach ($staffList as $stf): ?>
                            <option value="<?php echo $stf['id']; ?>" <?php echo ($editCounter && (int)$editCounter['current_staff_id'] === (int)$stf['id']) ? 'selected' : ''; ?>>
                                <?php echo e($stf['full_name']); ?> (@<?php echo e($stf['username']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                <?php echo $editCounter ? getIcon('check', 16) . ' Update Counter' : getIcon('plus', 16) . ' Save Counter'; ?>
            </button>
        </form>
    </div>

    <!-- Counter List -->
    <div class="card" style="overflow: hidden;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2 class="card-title"><?php echo getIcon('desktop', 18); ?> Service Counters</h2>
            <span class="badge badge-open"><?php echo count($counters); ?> Configured</span>
        </div>

        <div style="overflow-x: auto; width: 100%;">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="width: 32%; padding: 0.9rem 1rem;">Counter</th>
                        <th style="width: 20%; padding: 0.9rem 0.75rem; text-align: center;">Status</th>
                        <th style="width: 28%; padding: 0.9rem 0.85rem;">Assigned Staff</th>
                        <th style="width: 20%; padding: 0.9rem 1rem; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($counters as $c): ?>
                        <tr>
                            <td style="padding: 1rem;">
                                <strong style="font-size: 0.95rem; color: #0f172a;"><?php echo e($c['counter_number']); ?></strong><br>
                                <small style="color: var(--text-muted); font-size: 0.82rem; line-height: 1.35; display: inline-block; margin-top: 0.15rem;"><?php echo e($c['counter_name']); ?></small>
                            </td>
                            <td style="padding: 1rem 0.75rem; text-align: center;">
                                <form method="POST" action="counters.php" style="display:inline-block; margin: 0;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="counter_id" value="<?php echo $c['id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $c['status'] === 'open' ? 'closed' : 'open'; ?>">
                                    <button type="submit" style="background: none; border: none; padding: 0; cursor: pointer; display: inline-flex;" title="Click to toggle status">
                                        <?php echo renderStatusBadge($c['status']); ?>
                                    </button>
                                </form>
                            </td>
                            <td style="padding: 1rem 0.85rem;">
                                <?php if (!empty($c['staff_name'])): ?>
                                    <span style="font-weight: 600; color: #1e293b; font-size: 0.88rem;"><?php echo e($c['staff_name']); ?></span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.88rem;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem; text-align: right;">
                                <div style="display: inline-flex; gap: 0.35rem; align-items: center; justify-content: flex-end;">
                                    <a href="counters.php?edit=<?php echo $c['id']; ?>" class="btn btn-sm btn-secondary" title="Edit" style="padding: 0.4rem 0.65rem;">
                                        <?php echo getIcon('edit', 14); ?>
                                    </a>
                                    <form method="POST" action="counters.php" onsubmit="return confirm('Are you sure you want to delete <?php echo e($c['counter_number']); ?>?');" style="display:inline-block; margin: 0;">
                                        <input type="hidden" name="action" value="delete_counter">
                                        <input type="hidden" name="counter_id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete" style="padding: 0.4rem 0.65rem;">
                                            <?php echo getIcon('trash', 14); ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
