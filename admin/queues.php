<?php
// ===================================================
// Queue Records Management & Monitor (Admin Only)
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$pdo = getDBConnection();
$pageTitle = 'Manage Queue Records';

// Handle Administrative status overrides or deletions
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'update_status') {
        $queueId   = intval($_POST['queue_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $validStatuses = ['waiting', 'calling', 'serving', 'completed', 'skipped'];

        if ($queueId > 0 && in_array($newStatus, $validStatuses)) {
            $stmt = $pdo->prepare("UPDATE queues SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $queueId]);
            setFlash('success', 'Queue status updated to ' . ucfirst($newStatus) . '.');
        }
        header("Location: queues.php");
        exit;
    } elseif ($action === 'delete_queue') {
        $queueId = intval($_POST['queue_id'] ?? 0);
        if ($queueId > 0) {
            $stmt = $pdo->prepare("DELETE FROM queues WHERE id = ?");
            $stmt->execute([$queueId]);
            setFlash('success', 'Queue record deleted.');
        }
        header("Location: queues.php");
        exit;
    } elseif ($action === 'clear_today_completed') {
        $stmt = $pdo->prepare("DELETE FROM queues WHERE status IN ('completed', 'skipped') AND DATE(created_at) = CURDATE()");
        $stmt->execute();
        setFlash('info', 'Cleaned up completed & skipped queues for today.');
        header("Location: queues.php");
        exit;
    } elseif ($action === 'clear_all_queues') {
        $pdo->exec("DELETE FROM queues");
        setFlash('success', 'All queue data has been cleared. The queue system is now completely fresh.');
        header("Location: queues.php");
        exit;
    }
}

// Fetch all queue records (today or active)
$todayQueues = $pdo->query("
    SELECT q.*, c.counter_number, u.full_name AS staff_name
    FROM queues q
    LEFT JOIN counters c ON q.counter_id = c.id
    LEFT JOIN users u ON q.staff_id = u.id
    ORDER BY q.id DESC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo getIcon('list', 26); ?> Queue Management & Monitor</h1>
        <p class="page-desc">Live monitor, override queue statuses, or clean records (<?php echo date('M d, Y'); ?>)</p>
    </div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <form method="POST" action="queues.php" onsubmit="return confirm('Clear all completed and skipped queues?');" style="display:inline;">
            <input type="hidden" name="action" value="clear_today_completed">
            <button type="submit" class="btn btn-secondary btn-sm">
                <?php echo getIcon('trash', 14); ?> Clean Finished
            </button>
        </form>
        <form method="POST" action="queues.php" onsubmit="return confirm('Are you sure you want to delete ALL queue records? This will clear all test tickets and reset the system to completely fresh.');" style="display:inline;">
            <input type="hidden" name="action" value="clear_all_queues">
            <button type="submit" class="btn btn-danger btn-sm">
                <?php echo getIcon('trash', 14); ?> Clear All Queue Records
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><?php echo getIcon('clock', 18); ?> All Queue Tickets Today</h2>
        <span class="badge badge-serving"><?php echo count($todayQueues); ?> Records</span>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 12%;">QUEUE NO</th>
                    <th style="width: 18%;">CUSTOMER</th>
                    <th style="width: 18%;">SERVICE</th>
                    <th style="width: 12%;">COUNTER</th>
                    <th style="width: 12%;">STAFF</th>
                    <th style="width: 10%;">TIME</th>
                    <th style="width: 10%;">STATUS</th>
                    <th style="width: 8%; text-align: right;">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($todayQueues)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 3rem 1rem;">
                            No queue tickets generated today.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($todayQueues as $q): ?>
                        <tr>
                            <td><strong style="color: #1d4ed8; font-size: 1.05rem; font-weight: 800; letter-spacing: 0.3px;"><?php echo e($q['queue_number']); ?></strong></td>
                            <td><strong style="color: #0f172a; font-size: 0.95rem;"><?php echo e($q['customer_name']); ?></strong></td>
                            <td><span style="font-size: 0.9rem; color: #334155; font-weight: 600;"><?php echo e($q['service_type']); ?></span></td>
                            <td><?php echo $q['counter_number'] ? '<strong style="color:#0f172a;">' . e($q['counter_number']) . '</strong>' : '<span style="color:#94a3b8;">-</span>'; ?></td>
                            <td><?php echo $q['staff_name'] ? '<span style="color:#475569;">' . e($q['staff_name']) . '</span>' : '<span style="color:#94a3b8;">-</span>'; ?></td>
                            <td><span style="color: #64748b; font-size: 0.88rem;"><?php echo formatTimeOnly($q['created_at']); ?></span></td>
                            <td><?php echo renderStatusBadge($q['status']); ?></td>
                            <td style="text-align: right;">
                                <div class="table-actions" style="justify-content: flex-end;">
                                    <form method="POST" action="queues.php" style="margin: 0;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="queue_id" value="<?php echo $q['id']; ?>">
                                        <select name="status" class="table-select-sm" onchange="this.form.submit()" title="Change queue status">
                                            <option value="waiting" <?php echo $q['status'] === 'waiting' ? 'selected' : ''; ?>>Waiting</option>
                                            <option value="calling" <?php echo $q['status'] === 'calling' ? 'selected' : ''; ?>>Calling</option>
                                            <option value="serving" <?php echo $q['status'] === 'serving' ? 'selected' : ''; ?>>Serving</option>
                                            <option value="completed" <?php echo $q['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="skipped" <?php echo $q['status'] === 'skipped' ? 'selected' : ''; ?>>Skipped</option>
                                        </select>
                                    </form>
                                    <form method="POST" action="queues.php" onsubmit="return confirm('Delete queue <?php echo e($q['queue_number']); ?>?');" style="margin: 0;">
                                        <input type="hidden" name="action" value="delete_queue">
                                        <input type="hidden" name="queue_id" value="<?php echo $q['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" style="padding: 0.22rem 0.45rem;" title="Delete record">
                                            <?php echo getIcon('trash', 13); ?>
                                        </button>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
