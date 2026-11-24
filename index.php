<?php
// ===================================================
// QueueFlow Main Landing Page: Authentication & Portal Sign In
// Supports Admin, Staff, and Citizen Portals
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect immediately to respective dashboard
if (isLoggedIn()) {
    $user = currentUser();
    if ($user['role'] === 'admin') {
        header("Location: /Queue_management_system/admin/index.php");
    } elseif ($user['role'] === 'staff') {
        header("Location: /Queue_management_system/staff/index.php");
    } else {
        header("Location: /Queue_management_system/user/dashboard.php");
    }
    exit;
}

$error = '';
$usernameVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if quick 1-click login was triggered
    if (isset($_POST['quick_role'])) {
        if ($_POST['quick_role'] === 'admin') {
            $username = 'admin';
            $password = 'admin123';
        } elseif ($_POST['quick_role'] === 'staff') {
            $username = 'staff1';
            $password = 'staff123';
        } elseif ($_POST['quick_role'] === 'user') {
            $username = 'user';
            $password = 'user123';
        }
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
    }

    $usernameVal = $username;

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        $pdo = getDBConnection();
        // Support login via either username or email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = 'Your account has been deactivated. Please contact an administrator.';
            } else {
                // Successful login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['ic_passport'] = $user['ic_passport'] ?? '';
                $_SESSION['phone'] = $user['phone'] ?? '';
                $_SESSION['email'] = $user['email'] ?? '';

                setFlash('success', 'Welcome back, ' . $user['full_name'] . '!');

                if ($user['role'] === 'admin') {
                    header("Location: /Queue_management_system/admin/index.php");
                } elseif ($user['role'] === 'staff') {
                    header("Location: /Queue_management_system/staff/index.php");
                } else {
                    header("Location: /Queue_management_system/user/dashboard.php");
                }
                exit;
            }
        } else {
            $error = 'Invalid username/email or password.';
        }
    }
}

$pageTitle = 'Sign In - System Portal';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 480px; margin: 3rem auto;">
    <div class="card" style="box-shadow: var(--shadow-lg); padding: 2.25rem; border: 1.5px solid #e2e8f0; border-radius: 12px;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="width: 60px; height: 60px; background: var(--primary-light); color: var(--primary); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 0.75rem;">
                <?php echo getIcon('log-in', 28); ?>
            </div>
            <h1 style="font-size: 1.6rem; font-weight: 800; color: #0f172a;">Sign In to QueueFlow</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Sign in to access Citizen Portal, Staff Station, or Admin</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo getIcon('alert-triangle', 18); ?> <span><?php echo e($error); ?></span>
            </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="index.php">
            <div class="form-group">
                <label class="form-label" for="username">Username or Email Address</label>
                <input type="text" id="username" name="username" class="form-control" value="<?php echo e($usernameVal); ?>" required autofocus placeholder="name@example.com or username">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="Enter your password">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; margin-top: 0.5rem; font-weight: 700;">
                <?php echo getIcon('log-in', 18); ?> Sign In
            </button>
        </form>

        <!-- Divider -->
        <div style="display: flex; align-items: center; text-align: center; margin: 1.75rem 0 1.25rem 0; color: #94a3b8; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
            <div style="flex: 1; height: 1px; background: #e2e8f0;"></div>
            <span style="padding: 0 0.85rem;">⚡ Quick 1-Click Demo Fill</span>
            <div style="flex: 1; height: 1px; background: #e2e8f0;"></div>
        </div>

        <!-- Quick 3 Role Buttons -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.65rem;">
            <!-- Admin -->
            <form method="POST" action="index.php">
                <input type="hidden" name="quick_role" value="admin">
                <button type="submit" class="btn btn-secondary" style="width: 100%; padding: 0.6rem 0.4rem; flex-direction: column; gap: 0.35rem; border-color: #fecaca; background: #fff5f5; color: #b91c1c;">
                    <?php echo getIcon('key', 18); ?>
                    <span style="font-weight: 700; font-size: 0.82rem;">Admin</span>
                </button>
            </form>

            <!-- Staff -->
            <form method="POST" action="index.php">
                <input type="hidden" name="quick_role" value="staff">
                <button type="submit" class="btn btn-secondary" style="width: 100%; padding: 0.6rem 0.4rem; flex-direction: column; gap: 0.35rem; border-color: #c7d2fe; background: #f5f7ff; color: #4338ca;">
                    <?php echo getIcon('desktop', 18); ?>
                    <span style="font-weight: 700; font-size: 0.82rem;">Staff 01</span>
                </button>
            </form>

            <!-- Public User -->
            <form method="POST" action="index.php">
                <input type="hidden" name="quick_role" value="user">
                <button type="submit" class="btn btn-secondary" style="width: 100%; padding: 0.6rem 0.4rem; flex-direction: column; gap: 0.35rem; border-color: #bbf7d0; background: #f0fdf4; color: #15803d;">
                    <?php echo getIcon('user', 18); ?>
                    <span style="font-weight: 700; font-size: 0.82rem;">Public User</span>
                </button>
            </form>
        </div>

        <!-- Default Demo Credentials Box -->
        <div style="margin-top: 1.5rem; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 1.15rem 1.25rem; font-size: 0.88rem; line-height: 1.6; text-align: left; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);">
            <div style="font-weight: 700; color: #1e293b; margin-bottom: 0.35rem;">Default Demo Credentials:</div>
            <div style="color: #475569; margin-bottom: 0.2rem;">
                <strong>Admin:</strong> <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-family: monospace; border: 1px solid #cbd5e1;">admin</code> / <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-family: monospace; border: 1px solid #cbd5e1;">admin123</code>
            </div>
            <div style="color: #475569; margin-bottom: 0.2rem;">
                <strong>Staff (Counters 1-4):</strong> <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-family: monospace; border: 1px solid #cbd5e1;">staff1</code> to <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-family: monospace; border: 1px solid #cbd5e1;">staff4</code> / <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-family: monospace; border: 1px solid #cbd5e1;">staff123</code>
            </div>
            <div style="color: #475569;">
                <strong>Public User:</strong> <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-family: monospace; border: 1px solid #cbd5e1;">user</code> / <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-family: monospace; border: 1px solid #cbd5e1;">user123</code>
            </div>
        </div>

        <div style="margin-top: 1.25rem; text-align: center; font-size: 0.88rem; color: #64748b;">
            Don't have an account yet? 
            <a href="register.php" style="color: var(--primary); font-weight: 600; text-decoration: none;">Register Here</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
