<?php
// ===================================================
// Administrator: Manage Public Citizen Accounts
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$pdo = getDBConnection();

// Handle status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    $id = (int)$_POST['toggle_id'];
    $stmt = $pdo->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ? AND role = 'user'");
    $stmt->execute([$id]);
    setFlash('info', 'Citizen account status toggled.');
    header("Location: user_management.php");
    exit;
}

$search = trim($_GET['search'] ?? '');
$sql = "
    SELECT u.*,
        (SELECT COUNT(*) FROM appointments WHERE user_id = u.id) as booking_count,
        (SELECT COUNT(*) FROM applications WHERE user_id = u.id) as app_count
    FROM users u
    WHERE u.role = 'user'
";
$params = [];
if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.ic_passport LIKE ? OR u.phone LIKE ?)";
    $term = "%$search%";
    $params = [$term, $term, $term, $term];
}
$sql .= " ORDER BY u.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$citizens = $stmt->fetchAll();

$pageTitle = 'Manage Public Citizens';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 1100px; margin: 1.5rem auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-bottom: 0.25rem;">
                Public Citizen Accounts (<?php echo count($citizens); ?>)
            </h1>
            <p style="color: var(--text-muted); font-size: 0.92rem; margin: 0;">
                Registered users who book appointments and submit online document applications.
            </p>
        </div>
    </div>

    <!-- Search Form -->
    <div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
        <form method="GET" action="user_management.php" style="display: flex; gap: 1rem; align-items: flex-end;">
            <div style="flex: 1;">
                <label class="form-label" for="search" style="font-size: 0.85rem;">Search Citizen by Name, Email, IC or Phone</label>
                <input type="text" id="search" name="search" class="form-control" value="<?php echo e($search); ?>" placeholder="Type search term...">
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.25rem;">
                <?php echo getIcon('search', 14); ?> Search
            </button>
            <?php if (!empty($search)): ?>
                <a href="user_management.php" class="btn btn-secondary" style="padding: 0.65rem 1rem;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Citizens Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 22%;">CITIZEN NAME</th>
                        <th style="width: 18%;">IDENTIFICATION</th>
                        <th style="width: 20%;">CONTACT DETAILS</th>
                        <th style="width: 15%;">ACTIVITY</th>
                        <th style="width: 12%;">REGISTERED DATE</th>
                        <th style="width: 8%;">STATUS</th>
                        <th style="width: 5%; text-align: right;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($citizens)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 3rem 1rem; color: #94a3b8;">
                                No citizen accounts found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($citizens as $c): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="width: 38px; height: 38px; border-radius: 50%; background: #e0f2fe; color: #0284c7; font-weight: 800; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0;">
                                            <?php echo strtoupper(substr($c['full_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong style="color: #0f172a; font-size: 0.95rem;"><?php echo e($c['full_name']); ?></strong>
                                            <div class="cell-sub" style="margin-top: 0.15rem;">@<?php echo e($c['username']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong style="color: #334155; font-size: 0.92rem;"><?php echo e($c['ic_passport'] ?: 'N/A'); ?></strong>
                                </td>
                                <td>
                                    <div style="font-size: 0.9rem; font-weight: 600; color: #1e293b;"><?php echo e($c['phone'] ?: 'N/A'); ?></div>
                                    <div class="cell-sub" style="margin-top: 0.15rem;"><?php echo e($c['email'] ?: 'No email'); ?></div>
                                </td>
                                <td>
                                    <span style="font-size: 0.85rem; color: #475569;">
                                        <strong><?php echo (int)$c['booking_count']; ?></strong> bookings &bull; 
                                        <strong><?php echo (int)$c['app_count']; ?></strong> apps
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: #334155; font-size: 0.9rem;"><?php echo date('d M Y', strtotime($c['created_at'])); ?></span>
                                </td>
                                <td><?php echo renderStatusBadge($c['status']); ?></td>
                                <td style="text-align: right;">
                                    <div class="table-actions" style="justify-content: flex-end;">
                                        <form method="POST" action="user_management.php" style="margin: 0;">
                                            <input type="hidden" name="toggle_id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm">
                                                <?php echo ($c['status'] === 'active') ? 'Deactivate' : 'Activate'; ?>
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
