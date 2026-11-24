<?php
// ===================================================
// Citizen Registration Page
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header("Location: /Queue_management_system/user/dashboard.php");
    exit;
}

$error = '';
$fullName = '';
$icPassport = '';
$phone = '';
$email = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName   = trim($_POST['full_name'] ?? '');
    $icPassport = trim($_POST['ic_passport'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    if (empty($fullName) || empty($icPassport) || empty($phone) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        $pdo = getDBConnection();

        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$username ?: $email, $email]);
        if ($stmt->fetch()) {
            $error = 'An account with this username or email already exists. Please sign in.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $userLogin = $username ?: explode('@', $email)[0] . rand(100, 999);

            $insertStmt = $pdo->prepare("
                INSERT INTO users (username, email, password, full_name, ic_passport, phone, role, status)
                VALUES (?, ?, ?, ?, ?, ?, 'user', 'active')
            ");
            $insertStmt->execute([$userLogin, $email, $hash, $fullName, $icPassport, $phone]);
            $newId = $pdo->lastInsertId();

            // Auto sign-in
            $_SESSION['user_id'] = $newId;
            $_SESSION['username'] = $userLogin;
            $_SESSION['full_name'] = $fullName;
            $_SESSION['role'] = 'user';
            $_SESSION['ic_passport'] = $icPassport;
            $_SESSION['phone'] = $phone;
            $_SESSION['email'] = $email;

            setFlash('success', 'Registration successful! Welcome to the Public Citizen Portal.');
            header("Location: /Queue_management_system/user/dashboard.php");
            exit;
        }
    }
}

$pageTitle = 'Citizen Account Registration';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 580px; margin: 2.5rem auto;">
    <div class="card" style="box-shadow: var(--shadow-lg); padding: 2.5rem;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="width: 60px; height: 60px; background: #e0f2fe; color: #0284c7; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 0.75rem;">
                <?php echo getIcon('user-plus', 28); ?>
            </div>
            <h1 style="font-size: 1.6rem; font-weight: 700; color: #0f172a;">Citizen Registration</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Create your account to book service appointments and fast-track arrival</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo getIcon('alert-triangle', 18); ?> <span><?php echo e($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="form-group">
                <label class="form-label" for="full_name">Full Name (as per IC / Passport) *</label>
                <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo e($fullName); ?>" required placeholder="e.g. Ahmad bin Abdullah">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="ic_passport">IC / Passport Number *</label>
                    <input type="text" id="ic_passport" name="ic_passport" class="form-control" value="<?php echo e($icPassport); ?>" required placeholder="e.g. 900101-14-5567">
                </div>
                <div class="form-group">
                    <label class="form-label" for="phone">Contact Phone Number *</label>
                    <input type="text" id="phone" name="phone" class="form-control" value="<?php echo e($phone); ?>" required placeholder="e.g. +60 17-123 4567">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address *</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo e($email); ?>" required placeholder="user@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label" for="username">Username (Optional)</label>
                    <input type="text" id="username" name="username" class="form-control" value="<?php echo e($username); ?>" placeholder="Desired username">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="password">Password *</label>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="Min. 6 characters">
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="Repeat password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.8rem; margin-top: 1rem;">
                <?php echo getIcon('check', 18); ?> Complete Registration
            </button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.88rem; color: #64748b;">
            Already have an account? 
            <a href="login.php" style="color: var(--primary); font-weight: 600; text-decoration: none;">Sign In Here</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
