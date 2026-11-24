<?php
// ===================================================
// Administrator: Manage Immigration Services
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$pdo = getDBConnection();
$error = '';

// Handle Actions (Add, Toggle, Delete, Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration = (int)($_POST['est_duration'] ?? 15);

        if (empty($code) || empty($name)) {
            $error = 'Service code and name are required.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO services (code, name, description, est_duration, status) VALUES (?, ?, ?, ?, 'active')");
            try {
                $stmt->execute([$code, $name, $description, $duration]);
                setFlash('success', "Service '$name' added successfully!");
                header("Location: services.php");
                exit;
            } catch (PDOException $e) {
                $error = 'Service code already exists or database error.';
            }
        }
    }

    if ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE services SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('info', 'Service status updated.');
        header("Location: services.php");
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
        try {
            $stmt->execute([$id]);
            setFlash('info', 'Service removed.');
            header("Location: services.php");
            exit;
        } catch (PDOException $e) {
            $error = 'Cannot delete service that is linked to existing queues or applications.';
        }
    }
}

// Fetch all services with usage statistics
$servicesStmt = $pdo->query("
    SELECT s.*, 
        (SELECT COUNT(*) FROM applications WHERE service_id = s.id) as app_count,
        (SELECT COUNT(*) FROM queues WHERE service_id = s.id) as queue_count
    FROM services s
    ORDER BY s.id ASC
");
$services = $servicesStmt->fetchAll();

$pageTitle = 'Manage Immigration Services';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 1050px; margin: 1.5rem auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-bottom: 0.25rem;">
                Immigration & Center Services (<?php echo count($services); ?>)
            </h1>
            <p style="color: var(--text-muted); font-size: 0.92rem; margin: 0;">
                Configure available service codes, estimated service durations, and counter mappings.
            </p>
        </div>
        <button onclick="document.getElementById('addServiceModal').style.display='block'" class="btn btn-primary btn-sm">
            <?php echo getIcon('plus', 14); ?> Add New Service
        </button>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            <?php echo getIcon('alert-triangle', 18); ?> <span><?php echo e($error); ?></span>
        </div>
    <?php endif; ?>

    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>CODE</th>
                        <th>SERVICE NAME</th>
                        <th>DESCRIPTION</th>
                        <th>EST. DURATION</th>
                        <th>USAGE</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $srv): ?>
                        <tr>
                            <td>
                                <span style="display: inline-block; padding: 0.25rem 0.55rem; background: #e0f2fe; color: #0284c7; border-radius: 6px; font-weight: 800; font-size: 0.82rem;">
                                    <?php echo e($srv['code']); ?>
                                </span>
                            </td>
                            <td><strong style="color: #0f172a; font-size: 0.95rem;"><?php echo e($srv['name']); ?></strong></td>
                            <td style="color: #64748b; font-size: 0.85rem; max-width: 320px;">
                                <?php echo e($srv['description']); ?>
                            </td>
                            <td>
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.85rem; font-weight: 700; color: #d97706; background: #fef3c7; padding: 0.2rem 0.5rem; border-radius: 12px;">
                                    <?php echo getIcon('clock', 12); ?> <?php echo (int)$srv['est_duration']; ?> mins
                                </span>
                            </td>
                            <td style="font-size: 0.82rem; color: #64748b;">
                                <?php echo (int)$srv['app_count']; ?> apps &bull; <?php echo (int)$srv['queue_count']; ?> queues
                            </td>
                            <td><?php echo renderStatusBadge($srv['status']); ?></td>
                            <td>
                                <div style="display: flex; gap: 0.4rem; align-items: center;">
                                    <!-- Toggle Status Button -->
                                    <form method="POST" action="services.php" style="margin: 0;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?php echo $srv['id']; ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" style="padding: 0.35rem 0.55rem;" title="Toggle Active / Inactive">
                                            <?php echo getIcon($srv['status'] === 'active' ? 'pause' : 'play-circle', 14); ?>
                                        </button>
                                    </form>

                                    <!-- Delete Button -->
                                    <form method="POST" action="services.php" onsubmit="return confirm('Delete this service?');" style="margin: 0;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $srv['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" style="padding: 0.35rem 0.55rem;" title="Delete Service">
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

<!-- Add Service Modal -->
<div id="addServiceModal" style="display: none; position: fixed; z-index: 999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px);">
    <div style="max-width: 500px; margin: 80px auto; background: #fff; border-radius: var(--radius); padding: 2rem; box-shadow: var(--shadow-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #0f172a;">Add New Service</h3>
            <button onclick="document.getElementById('addServiceModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8;">&times;</button>
        </div>

        <form method="POST" action="services.php">
            <input type="hidden" name="action" value="add">
            
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="code">Code *</label>
                    <input type="text" id="code" name="code" class="form-control" required placeholder="e.g. PR" maxlength="5">
                </div>
                <div class="form-group">
                    <label class="form-label" for="name">Service Name *</label>
                    <input type="text" id="name" name="name" class="form-control" required placeholder="e.g. Passport Renewal">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="est_duration">Est. Duration (minutes) *</label>
                <input type="number" id="est_duration" name="est_duration" class="form-control" value="15" min="5" max="120" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3" placeholder="Brief explanation of this service..."></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="button" onclick="document.getElementById('addServiceModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary"><?php echo getIcon('check', 16); ?> Save Service</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
