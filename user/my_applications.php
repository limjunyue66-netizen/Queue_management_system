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

<div style="max-width: 1160px; margin: 2rem auto; padding: 0 1rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.7rem; font-weight: 800; color: #0f172a; margin-bottom: 0.35rem; letter-spacing: -0.3px;">
                My Document Applications
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">
                Track submitted immigration and document renewal applications.
            </p>
        </div>
        <a href="new_application.php" class="btn btn-primary" style="padding: 0.55rem 1.15rem; font-weight: 600;">
            <?php echo getIcon('plus', 16); ?> Submit New Application
        </a>
    </div>

    <?php if (empty($applications)): ?>
        <div class="card" style="text-align: center; padding: 4rem 2rem;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                <?php echo getIcon('file-text', 32); ?>
            </div>
            <h3 style="font-size: 1.25rem; font-weight: 700; color: #334155; margin-bottom: 0.5rem;">No Applications Found</h3>
            <p style="color: #64748b; margin-bottom: 1.5rem;">You have not submitted any document applications yet.</p>
            <a href="new_application.php" class="btn btn-primary">Start New Application</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 22%;">APPLICATION REF</th>
                        <th style="width: 24%;">SERVICE</th>
                        <th style="width: 22%;">APPLICANT INFO</th>
                        <th style="width: 14%;">SUBMITTED DATE</th>
                        <th style="width: 10%;">STATUS</th>
                        <th style="width: 8%;">REMARKS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                            <td>
                                <strong style="color: #0284c7; font-size: 0.98rem; font-weight: 800; letter-spacing: 0.3px;"><?php echo e($app['application_ref']); ?></strong>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center;">
                                    <span class="service-code-tag">
                                        <?php echo e($app['service_code']); ?>
                                    </span>
                                    <strong style="color: #0f172a; font-size: 0.95rem;"><?php echo e($app['service_name']); ?></strong>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: #0f172a; font-size: 0.93rem;"><?php echo e($app['applicant_name']); ?></div>
                                <div class="cell-sub"><?php echo e($app['applicant_ic']); ?> &bull; <?php echo e($app['applicant_phone']); ?></div>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: #334155;"><?php echo date('d M Y', strtotime($app['submitted_date'])); ?></span>
                            </td>
                            <td><?php echo renderStatusBadge($app['status']); ?></td>
                            <td>
                                <span style="font-size: 0.85rem; color: #64748b; line-height: 1.4;">
                                    <?php echo !empty($app['remarks']) ? e($app['remarks']) : '<span style="color:#cbd5e1;">-</span>'; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
