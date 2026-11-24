<?php
// ===================================================
// Staff Account Management (Admin Only)
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$pdo = getDBConnection();
$pageTitle = 'Staff Account Management';

// Handle Actions: Add, Edit, Delete, Toggle Status
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create_staff') {
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role     = trim($_POST['role'] ?? 'staff');

        if (empty($username) || empty($fullName) || empty($password)) {
            setFlash('error', 'Please fill in all required fields.');
        } else {
            // Check username uniqueness
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $checkStmt->execute([$username]);
            if ($checkStmt->fetch()) {
                setFlash('error', 'Username "' . e($username) . '" is already taken. Choose another.');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->execute([$username, $hash, $fullName, $role]);
                setFlash('success', 'User account for ' . e($fullName) . ' created successfully.');
                header("Location: staff.php");
                exit;
            }
        }
    } elseif ($action === 'edit_staff') {
        $userId   = intval($_POST['user_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role     = trim($_POST['role'] ?? 'staff');
        $status   = trim($_POST['status'] ?? 'active');

        if ($userId <= 0 || empty($fullName)) {
            setFlash('error', 'Invalid user details.');
        } else {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, password = ?, role = ?, status = ? WHERE id = ?");
                $stmt->execute([$fullName, $hash, $role, $status, $userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, role = ?, status = ? WHERE id = ?");
                $stmt->execute([$fullName, $role, $status, $userId]);
            }
            setFlash('success', 'Staff account updated successfully.');
            header("Location: staff.php");
            exit;
        }
    } elseif ($action === 'delete_staff') {
        $userId = intval($_POST['user_id'] ?? 0);
        $curr = currentUser();
        if ($userId === (int)$curr['id']) {
            setFlash('error', 'You cannot delete your own account.');
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            setFlash('success', 'User account deleted successfully.');
        }
        header("Location: staff.php");
        exit;
    }
}

// Fetch all staff accounts only (strictly exclude admin and citizen users)
$users = $pdo->query("SELECT * FROM users WHERE role = 'staff' ORDER BY id ASC")->fetchAll();

// Check if editing a specific user
$editUser = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo getIcon('users', 26); ?> Staff Account Management</h1>
        <p class="page-desc">Configure official service staff accounts (1 Staff assigned per Counter Station)</p>
    </div>
</div>

<div class="staff-layout" style="display: grid; grid-template-columns: 360px minmax(0, 1fr); gap: 1.5rem; align-items: start;">
    <!-- Add or Edit Form -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <?php echo $editUser ? getIcon('edit', 18) . ' Edit User Account' : getIcon('plus', 18) . ' Create New Staff Account'; ?>
            </h2>
            <?php if ($editUser): ?>
                <a href="staff.php" class="btn btn-sm btn-secondary"><?php echo getIcon('x', 14); ?> Cancel Edit</a>
            <?php endif; ?>
        </div>

        <form method="POST" action="staff.php">
            <input type="hidden" name="action" value="<?php echo $editUser ? 'edit_staff' : 'create_staff'; ?>">
            <?php if ($editUser): ?>
                <input type="hidden" name="user_id" value="<?php echo (int)$editUser['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" 
                       value="<?php echo e($editUser['username'] ?? ''); ?>" 
                       <?php echo $editUser ? 'readonly style="background:#f1f5f9;"' : 'required'; ?> 
                       placeholder="e.g. staff_john">
                <?php if ($editUser): ?>
                    <small style="color: var(--text-muted); font-size: 0.8rem;">Username cannot be changed once created.</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" class="form-control" 
                       value="<?php echo e($editUser['full_name'] ?? ''); ?>" required placeholder="e.g. John Doe">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">
                    <?php echo $editUser ? 'New Password (Leave blank to keep unchanged)' : 'Password'; ?>
                </label>
                <input type="password" id="password" name="password" class="form-control" 
                       <?php echo $editUser ? '' : 'required'; ?> placeholder="Minimum 6 characters">
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label" for="role">User Role</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="staff" selected>Staff Member</option>
                    </select>
                </div>

                <?php if ($editUser): ?>
                    <div class="form-col form-group">
                        <label class="form-label" for="status">Account Status</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="active" <?php echo ($editUser['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($editUser['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                <?php echo $editUser ? getIcon('check', 16) . ' Update Account' : getIcon('plus', 16) . ' Create Account'; ?>
            </button>
        </form>
    </div>

    <!-- Staff List Table -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?php echo getIcon('users', 18); ?> Existing Service Staff</h2>
            <span class="badge badge-open"><?php echo count($users); ?> Staff Members</span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <strong><?php echo e($u['full_name']); ?></strong><br>
                                <small style="color: var(--text-muted);">@<?php echo e($u['username']); ?></small>
                            </td>
                            <td>
                                <span class="user-badge <?php echo e($u['role']); ?>">
                                    <?php echo ucfirst(e($u['role'])); ?>
                                </span>
                            </td>
                            <td><?php echo renderStatusBadge($u['status']); ?></td>
                            <td>
                                <div style="display: flex; gap: 0.35rem;">
                                    <a href="staff.php?edit=<?php echo $u['id']; ?>" class="btn btn-sm btn-secondary" title="Edit">
                                        <?php echo getIcon('edit', 14); ?> Edit
                                    </a>
                                    <?php if ($u['id'] !== currentUser()['id']): ?>
                                        <form method="POST" action="staff.php" onsubmit="return confirm('Are you sure you want to delete user <?php echo e($u['username']); ?>?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_staff">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                <?php echo getIcon('trash', 14); ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
