<?php
// ===================================================
// Shared Page Header & Navigation
// Two-Tier Clean & Modern Layout (Zero Overflow & Overlap)
// Supports Admin, Staff, and Citizen Portals
// ===================================================
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
$inAdmin = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;
$inStaff = strpos($_SERVER['REQUEST_URI'], '/staff/') !== false;
$inUser  = strpos($_SERVER['REQUEST_URI'], '/user/') !== false;

// Base url helper
$baseUrl = '/Queue_management_system';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? e($pageTitle) . ' - QueueFlow' : 'QueueFlow - Smart Queue & Appointment System'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/style.css?v=2.2">
</head>
<body>
<div class="app-container">
    <header class="header-wrapper">
        <!-- Top Main Bar: Brand on Left, User & Actions on Right -->
        <div class="top-navbar">
            <div class="top-nav-container">
<?php
$brandHomeUrl = $baseUrl . '/index.php';
if ($user) {
    if ($user['role'] === 'admin') $brandHomeUrl = $baseUrl . '/admin/index.php';
    elseif ($user['role'] === 'staff') $brandHomeUrl = $baseUrl . '/staff/index.php';
    elseif ($user['role'] === 'user') $brandHomeUrl = $baseUrl . '/user/dashboard.php';
}
?>
                <!-- Brand Logo -->
                <a href="<?php echo $brandHomeUrl; ?>" class="nav-brand">
                    <span class="brand-icon">
                        <?php echo getIcon('ticket', 20); ?>
                    </span>
                    <span class="brand-text">
                        QueueFlow
                        <?php if ($user && $user['role'] === 'admin'): ?>
                            <span class="brand-role-tag admin">Admin Portal</span>
                        <?php elseif ($user && $user['role'] === 'staff'): ?>
                            <span class="brand-role-tag staff">Staff Station</span>
                        <?php elseif ($user && $user['role'] === 'user'): ?>
                            <span class="brand-role-tag user" style="background:#e0f2fe; color:#0369a1; border-color:#bae6fd;">Citizen Portal</span>
                        <?php endif; ?>
                    </span>
                </a>

                <!-- User & Quick Action Area -->
                <div class="top-nav-actions">
                    <?php if ($user): ?>
                        <!-- User Profile Badge -->
                        <div class="user-profile-badge">
                            <span class="user-avatar-circle" style="<?php echo ($user['role'] === 'user') ? 'background:#0284c7; color:#fff;' : ''; ?>">
                                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                            </span>
                            <span class="user-name"><?php echo e($user['full_name']); ?></span>
                            <span class="user-role-tag <?php echo e($user['role']); ?>" style="<?php echo ($user['role'] === 'user') ? 'background:#e0f2fe; color:#0369a1;' : ''; ?>">
                                <?php echo strtoupper(e($user['role'])); ?>
                            </span>
                        </div>

                        <!-- Logout Button -->
                        <a href="<?php echo $baseUrl; ?>/logout.php" class="btn btn-logout btn-sm" title="Sign out of system">
                            <?php echo getIcon('log-out', 14); ?> <span>Logout</span>
                        </a>
                    <?php else: ?>
                        <!-- Guest Actions: Register & Sign In always visible -->
                        <a href="<?php echo $baseUrl; ?>/register.php" class="btn btn-secondary btn-sm <?php echo ($currentPage === 'register.php') ? 'active' : ''; ?>" style="margin-right: 0.5rem; <?php echo ($currentPage === 'register.php') ? 'background:#e2e8f0; font-weight:700;' : ''; ?>">
                            <?php echo getIcon('user-plus', 14); ?> <span>Register</span>
                        </a>
                        <a href="<?php echo $baseUrl; ?>/login.php" class="btn btn-primary btn-sm <?php echo ($currentPage === 'login.php') ? 'active' : ''; ?>" style="<?php echo ($currentPage === 'login.php') ? 'background:#1d4ed8; font-weight:700; box-shadow: 0 0 0 2px #bfdbfe;' : ''; ?>">
                            <?php echo getIcon('log-in', 14); ?> <span>Sign In</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Subnav Tab Bar (Fixed & Always Present for seamless switching) -->
        <div class="subnav-bar">
            <div class="subnav-container">
                <ul class="subnav-tabs">
                    <?php if ($user && $user['role'] === 'admin'): ?>
                        <!-- Admin Subnav Tabs -->
                        <li>
                            <a href="<?php echo $baseUrl; ?>/admin/index.php" class="subnav-tab <?php echo ($currentPage === 'index.php' && $inAdmin) ? 'active' : ''; ?>">
                                <?php echo getIcon('dashboard', 16); ?> <span>Dashboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/admin/services.php" class="subnav-tab <?php echo ($currentPage === 'services.php') ? 'active' : ''; ?>">
                                <?php echo getIcon('briefcase', 16); ?> <span>Services</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/admin/appointments.php" class="subnav-tab <?php echo ($currentPage === 'appointments.php') ? 'active' : ''; ?>">
                                <?php echo getIcon('calendar', 16); ?> <span>Appointments</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/admin/applications.php" class="subnav-tab <?php echo ($currentPage === 'applications.php') ? 'active' : ''; ?>">
                                <?php echo getIcon('file-text', 16); ?> <span>Applications</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/admin/counters.php" class="subnav-tab <?php echo ($currentPage === 'counters.php') ? 'active' : ''; ?>">
                                <?php echo getIcon('counter', 16); ?> <span>Counters</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/admin/queues.php" class="subnav-tab <?php echo ($currentPage === 'queues.php') ? 'active' : ''; ?>">
                                <?php echo getIcon('list', 16); ?> <span>Queues</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/admin/staff.php" class="subnav-tab <?php echo ($currentPage === 'staff.php') ? 'active' : ''; ?>">
                                <?php echo getIcon('users', 16); ?> <span>Staff</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/admin/user_management.php" class="subnav-tab <?php echo ($currentPage === 'user_management.php') ? 'active' : ''; ?>">
                                <?php echo getIcon('user', 16); ?> <span>Citizens</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/admin/history.php" class="subnav-tab <?php echo ($currentPage === 'history.php' && $inAdmin) ? 'active' : ''; ?>">
                                <?php echo getIcon('history', 16); ?> <span>Reports</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/admin/system_reset.php" class="subnav-tab <?php echo ($currentPage === 'system_reset.php') ? 'active' : ''; ?>" style="color: #0284c7; font-weight: 600;">
                                <?php echo getIcon('beaker', 16); ?> <span>Simulator</span>
                            </a>
                        </li>

                    <?php elseif ($user && $user['role'] === 'user'): ?>
                        <!-- Citizen User Subnav Tabs -->
                        <li>
                            <a href="<?php echo $baseUrl; ?>/user/dashboard.php" class="subnav-tab <?php echo ($currentPage === 'dashboard.php' && $inUser) ? 'active' : ''; ?>">
                                <?php echo getIcon('dashboard', 16); ?> <span>My Dashboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/user/book_appointment.php" class="subnav-tab <?php echo ($currentPage === 'book_appointment.php') ? 'active' : ''; ?>">
                                <?php echo getIcon('calendar', 16); ?> <span>Book Appointment</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/user/checkin.php" class="subnav-tab <?php echo ($currentPage === 'checkin.php') ? 'active' : ''; ?>" style="color: #16a34a; font-weight: 600;">
                                <?php echo getIcon('check-square', 16); ?> <span>Office Check-In</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/user/my_appointments.php" class="subnav-tab <?php echo ($currentPage === 'my_appointments.php') ? 'active' : ''; ?>">
                                <?php echo getIcon('clock', 16); ?> <span>My Appointments</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/user/my_applications.php" class="subnav-tab <?php echo ($currentPage === 'my_applications.php' || $currentPage === 'new_application.php') ? 'active' : ''; ?>">
                                <?php echo getIcon('file-text', 16); ?> <span>My Applications</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/display.php" target="_blank" class="subnav-tab">
                                <?php echo getIcon('tv', 16); ?> <span>Live Display</span>
                            </a>
                        </li>

                    <?php elseif ($user && $user['role'] === 'staff'): ?>
                        <!-- Staff Subnav Tabs -->
                        <li>
                            <a href="<?php echo $baseUrl; ?>/staff/index.php" class="subnav-tab <?php echo ($currentPage === 'index.php' && $inStaff) ? 'active' : ''; ?>">
                                <?php echo getIcon('desktop', 16); ?> <span>My Service Station</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/display.php" target="_blank" class="subnav-tab">
                                <?php echo getIcon('tv', 16); ?> <span>Live Display Screen</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/index.php" target="_blank" class="subnav-tab">
                                <?php echo getIcon('ticket', 16); ?> <span>Customer Ticket Kiosk</span>
                            </a>
                        </li>

                    <?php else: ?>
                        <!-- Customer Public Tabs: Always present on Kiosk, Display, Status, Register & Login -->
                        <li>
                            <a href="<?php echo $baseUrl; ?>/index.php" class="subnav-tab <?php echo ($currentPage === 'index.php' || $currentPage === 'kiosk.php') ? 'active' : ''; ?>">
                                <?php echo getIcon('ticket', 16); ?> <span>Walk-In Kiosk</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/display.php" class="subnav-tab <?php echo ($currentPage === 'display.php') ? 'active' : ''; ?>">
                                <?php echo getIcon('tv', 16); ?> <span>Live Calling Display</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/check_status.php" class="subnav-tab <?php echo ($currentPage === 'check_status.php') ? 'active' : ''; ?>">
                                <?php echo getIcon('search', 16); ?> <span>Check Waiting Status</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/register.php" class="subnav-tab <?php echo ($currentPage === 'register.php') ? 'active' : ''; ?>">
                                <?php echo getIcon('user-plus', 16); ?> <span>Citizen Registration</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </header>

    <main class="main-content">
        <?php echo displayFlash(); ?>
