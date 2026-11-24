<?php
// ===================================================
// Administrator: Manage Applications
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

    if ($action === 'update_status' && $appId > 0) {
        $status = $_POST['status'] ?? 'approved';
        $remarks = trim($_POST['remarks'] ?? '');
        $stmt = $pdo->prepare("UPDATE applications SET status = ?, remarks = ? WHERE id = ?");
        $stmt->execute([$status, $remarks, $appId]);
        setFlash('success', "Application updated to " . ucfirst(str_replace('_', ' ', $status)) . ".");
        header("Location: applications.php");
        exit;
    }
}

// Filters
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$sql = "
    SELECT app.*, s.name as service_name, s.code as service_code, u.email as user_email
    FROM applications app
    JOIN services s ON app.service_id = s.id
    JOIN users u ON app.user_id = u.id
    WHERE 1=1
";
$params = [];
if (!empty($search)) {
    $sql .= " AND (app.application_ref LIKE ? OR app.applicant_name LIKE ? OR app.applicant_ic LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}
if (!empty($statusFilter)) {
    $sql .= " AND app.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY app.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

$pageTitle = 'Manage Document Applications';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 1100px; margin: 1.5rem auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-bottom: 0.25rem;">
                Online Document Applications (<?php echo count($applications); ?>)
            </h1>
            <p style="color: var(--text-muted); font-size: 0.92rem; margin: 0;">
                Review submitted citizen passport and visa applications before counter verification.
            </p>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
        <form method="GET" action="applications.php" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 2; min-width: 220px;">
                <label class="form-label" for="search" style="font-size: 0.85rem;">Search Ref, Name or IC</label>
                <input type="text" id="search" name="search" class="form-control" value="<?php echo e($search); ?>" placeholder="Ref No (e.g. IMP-), Name, IC...">
            </div>

            <div style="flex: 1; min-width: 180px;">
                <label class="form-label" for="status" style="font-size: 0.85rem;">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="">-- All Statuses --</option>
                    <option value="submitted" <?php echo ($statusFilter === 'submitted') ? 'selected' : ''; ?>>Submitted</option>
                    <option value="under_review" <?php echo ($statusFilter === 'under_review') ? 'selected' : ''; ?>>Under Review</option>
                    <option value="approved" <?php echo ($statusFilter === 'approved') ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo ($statusFilter === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.25rem;">
                <?php echo getIcon('search', 14); ?> Filter
            </button>
            <?php if (!empty($search) || !empty($statusFilter)): ?>
                <a href="applications.php" class="btn btn-secondary" style="padding: 0.65rem 1rem;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Applications Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>APPLICATION REF</th>
                        <th>APPLICANT NAME & IC</th>
                        <th>SERVICE</th>
                        <th>SUBMITTED DATE</th>
                        <th>STATUS</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 3rem 1rem; color: #94a3b8;">
                                No applications found matching your criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td><strong style="color: #0284c7;"><?php echo e($app['application_ref']); ?></strong></td>
                                <td>
                                    <div style="font-weight: 700; color: #1e293b;"><?php echo e($app['applicant_name']); ?></div>
                                    <div style="font-size: 0.8rem; color: #64748b;">
                                        <?php echo e($app['applicant_ic']); ?> &bull; <?php echo e($app['user_email']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="display: inline-block; padding: 0.15rem 0.4rem; background: #f1f5f9; border-radius: 4px; font-weight: 700; font-size: 0.75rem; color: #475569; margin-right: 0.35rem;">
                                        <?php echo e($app['service_code']); ?>
                                    </span>
                                    <strong><?php echo e($app['service_name']); ?></strong>
                                </td>
                                <td><?php echo date('d M Y', strtotime($app['submitted_date'])); ?></td>
                                <td><?php echo renderStatusBadge($app['status']); ?></td>
                                <td>
                                    <button onclick="openManageModal(<?php echo $app['id']; ?>, '<?php echo e($app['application_ref']); ?>', '<?php echo e($app['status']); ?>', '<?php echo e(addslashes($app['remarks'] ?? '')); ?>')" class="btn btn-primary btn-sm" style="padding: 0.3rem 0.65rem;">
                                        <?php echo getIcon('edit', 14); ?> Manage
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Manage Modal -->
<div id="manageAppModal" style="display: none; position: fixed; z-index: 999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px);">
    <div style="max-width: 500px; margin: 90px auto; background: #fff; border-radius: var(--radius); padding: 2rem; box-shadow: var(--shadow-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 id="modalAppRef" style="margin: 0; font-size: 1.2rem; font-weight: 800; color: #0f172a;">Manage Application</h3>
            <button onclick="document.getElementById('manageAppModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8;">&times;</button>
        </div>

        <form method="POST" action="applications.php">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" id="modalAppId">

            <div class="form-group">
                <label class="form-label" for="modalStatus">Review Decision *</label>
                <select name="status" id="modalStatus" class="form-control" style="font-size: 1rem; padding: 0.7rem;">
                    <option value="approved">Approved (Ready for counter collection)</option>
                    <option value="under_review">Under Review (Pending verification)</option>
                    <option value="rejected">Rejected (Incomplete documents)</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="modalRemarks">Officer Remarks</label>
                <textarea name="remarks" id="modalRemarks" class="form-control" rows="3" placeholder="Notes visible to citizen..."></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="button" onclick="document.getElementById('manageAppModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary"><?php echo getIcon('check', 16); ?> Save Decision</button>
            </div>
        </form>
    </div>
</div>

<script>
function openManageModal(id, ref, status, remarks) {
    document.getElementById('modalAppId').value = id;
    document.getElementById('modalAppRef').innerText = 'Manage: ' + ref;
    document.getElementById('modalStatus').value = status;
    document.getElementById('modalRemarks').value = remarks;
    document.getElementById('manageAppModal').style.display = 'block';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
