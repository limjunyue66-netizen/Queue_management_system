<?php
// ===================================================
// Citizen Portal: My Applications List
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireUser();
$currentUser = currentUser();
$pdo = getDBConnection();
$userId = $currentUser['id'];

$stmt = $pdo->prepare("
    SELECT app.*, s.name as service_name, s.code as service_code
    FROM applications app
    JOIN services s ON app.service_id = s.id
    WHERE app.user_id = ?
    ORDER BY app.id DESC
");
$stmt->execute([$userId]);
$applications = $stmt->fetchAll();

$pageTitle = 'My Document Applications';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 950px; margin: 1.5rem auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-bottom: 0.25rem;">
                My Document Applications
            </h1>
            <p style="color: var(--text-muted); font-size: 0.92rem; margin: 0;">
                Track submitted immigration and document renewal applications.
            </p>
        </div>
        <a href="new_application.php" class="btn btn-primary btn-sm">
            <?php echo getIcon('plus', 14); ?> Submit New Application
        </a>
    </div>

    <?php if (empty($applications)): ?>
        <div class="card" style="text-align: center; padding: 4rem 2rem;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                <?php echo getIcon('file-text', 32); ?>
            </div>
            <h3 style="font-size: 1.2rem; color: #334155; margin-bottom: 0.5rem;">No Applications Found</h3>
            <p style="color: #64748b; margin-bottom: 1.5rem;">You have not submitted any document applications yet.</p>
            <a href="new_application.php" class="btn btn-primary">Start New Application</a>
        </div>
    <?php else: ?>
        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>APPLICATION REF</th>
                            <th>SERVICE</th>
                            <th>APPLICANT INFO</th>
                            <th>SUBMITTED DATE</th>
                            <th>STATUS</th>
                            <th>REMARKS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <strong style="color: #0284c7;"><?php echo e($app['application_ref']); ?></strong>
                                </td>
                                <td>
                                    <span style="display: inline-block; padding: 0.15rem 0.4rem; background: #f1f5f9; border-radius: 4px; font-weight: 700; font-size: 0.75rem; color: #475569; margin-right: 0.35rem;">
                                        <?php echo e($app['service_code']); ?>
                                    </span>
                                    <strong><?php echo e($app['service_name']); ?></strong>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo e($app['applicant_name']); ?></div>
                                    <small style="color: #64748b;"><?php echo e($app['applicant_ic']); ?> &bull; <?php echo e($app['applicant_phone']); ?></small>
                                </td>
                                <td><?php echo date('d M Y', strtotime($app['submitted_date'])); ?></td>
                                <td><?php echo renderStatusBadge($app['status']); ?></td>
                                <td style="max-width: 250px; font-size: 0.85rem; color: #64748b;">
                                    <?php echo !empty($app['remarks']) ? e($app['remarks']) : '<span style="color:#cbd5e1;">-</span>'; ?>
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
