<?php
// ===================================================
// Authentication: Login Page
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect to respective dashboard
if (isLoggedIn()) {
    $user = currentUser();
    if ($user['role'] === 'admin') {
        header("Location: /Queue_management_system/admin/index.php");
    } else {
        header("Location: /Queue_management_system/staff/index.php");
    }
    exit;
}

$error = '';
$usernameVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if quick login was triggered
    if (isset($_POST['quick_role'])) {
        if ($_POST['quick_role'] === 'admin') {
            $username = 'admin';
            $password = 'admin123';
        } elseif ($_POST['quick_role'] === 'staff') {
            $username = 'staff1';
            $password = 'staff123';
        }
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
    }

    $usernameVal = $username;

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
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

                setFlash('success', 'Welcome back, ' . $user['full_name'] . '!');

                if ($user['role'] === 'admin') {
                    header("Location: /Queue_management_system/admin/index.php");
                } else {
                    header("Location: /Queue_management_system/staff/index.php");
                }
                exit;
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

$pageTitle = 'Staff & Admin Login';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 440px; margin: 3rem auto;">
    <div class="card" style="box-shadow: var(--shadow-lg); padding: 2.25rem;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="width: 60px; height: 60px; background: var(--primary-light); color: var(--primary); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 0.75rem;">
                <?php echo getIcon('log-in', 28); ?>
            </div>
            <h1 style="font-size: 1.6rem; font-weight: 700; color: #0f172a;">System Login</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Sign in to access Admin or Staff portals</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo getIcon('alert-triangle', 18); ?> <span><?php echo e($error); ?></span>
            </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="login.php">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" value="<?php echo e($usernameVal); ?>" required autofocus placeholder="Enter your username">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="Enter your password">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; margin-top: 0.5rem;">
                <?php echo getIcon('log-in', 18); ?> Sign In
            </button>
        </form>

        <!-- Divider -->
        <div style="display: flex; align-items: center; text-align: center; margin: 1.75rem 0 1.25rem 0; color: #94a3b8; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
            <div style="flex: 1; height: 1px; background: #e2e8f0;"></div>
            <span style="padding: 0 0.85rem;">One-Click Quick Login</span>
            <div style="flex: 1; height: 1px; background: #e2e8f0;"></div>
        </div>

        <!-- Quick Login Buttons for each role -->
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <!-- Admin Quick Login -->
            <form method="POST" action="login.php">
                <input type="hidden" name="quick_role" value="admin">
                <button type="submit" class="btn btn-secondary" style="width: 100%; padding: 0.7rem 1rem; justify-content: space-between; border-color: #fecaca; background: #fff5f5; transition: all 0.2s ease;">
                    <div style="display: flex; align-items: center; gap: 0.65rem;">
                        <span style="display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; background: #fee2e2; color: #b91c1c;">
                            <?php echo getIcon('key', 16); ?>
                        </span>
                        <div style="text-align: left;">
                            <div style="font-weight: 700; color: #991b1b; font-size: 0.92rem;">Quick Login as Admin</div>
                            <div style="font-size: 0.78rem; color: #7f1d1d; opacity: 0.8;">Username: admin (Full system access)</div>
                        </div>
                    </div>
                    <?php echo getIcon('log-in', 16, 'icon'); ?>
                </button>
            </form>

            <!-- Staff Quick Login -->
            <form method="POST" action="login.php">
                <input type="hidden" name="quick_role" value="staff">
                <button type="submit" class="btn btn-secondary" style="width: 100%; padding: 0.7rem 1rem; justify-content: space-between; border-color: #c7d2fe; background: #f5f7ff; transition: all 0.2s ease;">
                    <div style="display: flex; align-items: center; gap: 0.65rem;">
                        <span style="display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; background: #e0e7ff; color: #4338ca;">
                            <?php echo getIcon('desktop', 16); ?>
                        </span>
                        <div style="text-align: left;">
                            <div style="font-weight: 700; color: #3730a3; font-size: 0.92rem;">Quick Login as Staff</div>
                            <div style="font-size: 0.78rem; color: #312e81; opacity: 0.8;">Username: staff1 (Counter operations)</div>
                        </div>
                    </div>
                    <?php echo getIcon('log-in', 16, 'icon'); ?>
                </button>
            </form>
        </div>

        <div style="margin-top: 1.5rem; background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 0.85rem; font-size: 0.82rem; color: #475569; text-align: center;">
            <span style="color: var(--text-muted);">Default Credentials:</span> 
            <strong>admin</strong> / <code>admin123</code> &bull; 
            <strong>staff1</strong> / <code>staff123</code>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
