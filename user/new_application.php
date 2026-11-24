<?php
// ===================================================
// Citizen Portal: Submit New Application
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireUser();
$currentUser = currentUser();
$pdo = getDBConnection();
$userId = $currentUser['id'];

$services = getAllServices($pdo, true);
$error = '';

$nameVal = $currentUser['full_name'];
$icVal = $currentUser['ic_passport'];
$phoneVal = $currentUser['phone'];
$selectedService = $services[0]['id'] ?? 1;
$remarks = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceId = (int)($_POST['service_id'] ?? 0);
    $nameVal = trim($_POST['applicant_name'] ?? '');
    $icVal = trim($_POST['applicant_ic'] ?? '');
    $phoneVal = trim($_POST['applicant_phone'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($serviceId) || empty($nameVal) || empty($icVal) || empty($phoneVal)) {
        $error = 'Please fill in all required applicant details.';
    } else {
        $ref = generateApplicationRef($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO applications (application_ref, user_id, service_id, applicant_name, applicant_ic, applicant_phone, status, remarks, submitted_date)
            VALUES (?, ?, ?, ?, ?, ?, 'submitted', ?, CURDATE())
        ");
        $stmt->execute([$ref, $userId, $serviceId, $nameVal, $icVal, $phoneVal, $remarks]);

        setFlash('success', "Application $ref submitted successfully! You can track its status below.");
        header("Location: my_applications.php");
        exit;
    }
}

$pageTitle = 'Submit New Application';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 680px; margin: 1.5rem auto;">
    <div class="card" style="padding: 2.25rem;">
        <div style="margin-bottom: 2rem;">
            <h1 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.65rem;">
                <span style="color: var(--primary);"><?php echo getIcon('file-text', 24); ?></span> Online Document Application Form
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem;">
                Submit document details online prior to your physical counter visit.
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo getIcon('alert-triangle', 18); ?> <span><?php echo e($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="new_application.php">
            <div class="form-group">
                <label class="form-label" for="service_id">Immigration Service Category *</label>
                <select name="service_id" id="service_id" class="form-control" required style="font-size: 1rem; padding: 0.75rem;">
                    <?php foreach ($services as $srv): ?>
                        <option value="<?php echo $srv['id']; ?>" <?php echo ($selectedService == $srv['id']) ? 'selected' : ''; ?>>
                            [<?php echo e($srv['code']); ?>] <?php echo e($srv['name']); ?> &bull; <?php echo e($srv['description']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="applicant_name">Applicant Full Name *</label>
                <input type="text" id="applicant_name" name="applicant_name" class="form-control" value="<?php echo e($nameVal); ?>" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="applicant_ic">IC / Passport Number *</label>
                    <input type="text" id="applicant_ic" name="applicant_ic" class="form-control" value="<?php echo e($icVal); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="applicant_phone">Phone Contact *</label>
                    <input type="text" id="applicant_phone" name="applicant_phone" class="form-control" value="<?php echo e($phoneVal); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="remarks">Declaration / Additional Notes</label>
                <textarea id="remarks" name="remarks" class="form-control" rows="3" placeholder="State any urgency or specific requests..."><?php echo e($remarks); ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="padding: 0.85rem 1.75rem; font-weight: 700; font-size: 1rem;">
                <?php echo getIcon('check', 18); ?> Submit Application
            </button>
            <a href="dashboard.php" class="btn btn-secondary" style="padding: 0.85rem 1.5rem;">Cancel</a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
