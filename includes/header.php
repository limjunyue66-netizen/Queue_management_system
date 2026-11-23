<?php
// ===================================================
// Shared Page Header & Navigation
// Two-Tier Clean & Modern Layout (Zero Overflow & Overlap)
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

// Base url helper
$baseUrl = '/Queue_management_system';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? e($pageTitle) . ' - QueueFlow' : 'QueueFlow - Queue Management System'; ?></title>
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/style.css">
</head>
<body>
<div class="app-container">
    <header class="header-wrapper">
        <!-- Top Main Bar: Brand on Left, User & Actions on Right -->
        <div class="top-navbar">
            <div class="top-nav-container">
                <!-- Brand Logo -->
                <a href="<?php echo $baseUrl; ?>/index.php" class="nav-brand">
                    <span class="brand-icon">
                        <?php echo getIcon('ticket', 20); ?>
                    </span>
                    <span class="brand-text">
                        QueueFlow
                        <?php if ($user && $user['role'] === 'admin'): ?>
                            <span class="brand-role-tag admin">Admin Portal</span>
                        <?php elseif ($user && $user['role'] === 'staff'): ?>
                            <span class="brand-role-tag staff">Staff Station</span>
                        <?php endif; ?>
                    </span>
                </a>

                <!-- User & Quick Action Area (Always neat and aligned on right) -->
                <div class="top-nav-actions">
                    <?php if ($user): ?>
                        <!-- User Profile Badge -->
                        <div class="user-profile-badge">
                            <span class="user-avatar-circle">
                                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                            </span>
                            <span class="user-name"><?php echo e($user['full_name']); ?></span>
                            <span class="user-role-tag <?php echo e($user['role']); ?>"><?php echo ucfirst(e($user['role'])); ?></span>
                        </div>

                        <!-- Logout Button -->
                        <a href="<?php echo $baseUrl; ?>/logout.php" class="btn btn-logout btn-sm" title="Sign out of system">
                            <?php echo getIcon('log-out', 14); ?> <span>Logout</span>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo $baseUrl; ?>/login.php" class="btn btn-primary btn-sm">
                            <?php echo getIcon('log-in', 14); ?> Staff / Admin Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Subnav Tab Bar: Dedicated navigation items with ample breathing room -->
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
                            <a href="<?php echo $baseUrl; ?>/admin/staff.php" class="subnav-tab <?php echo ($currentPage === 'staff.php') ? 'active' : ''; ?>">
                                <?php echo getIcon('users', 16); ?> <span>Staff Accounts</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/admin/counters.php" class="subnav-tab <?php echo ($currentPage === 'counters.php') ? 'active' : ''; ?>">
                                <?php echo getIcon('counter', 16); ?> <span>Service Counters</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/admin/queues.php" class="subnav-tab <?php echo ($currentPage === 'queues.php') ? 'active' : ''; ?>">
                                <?php echo getIcon('list', 16); ?> <span>Queue Monitor</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $baseUrl; ?>/admin/history.php" class="subnav-tab <?php echo ($currentPage === 'history.php' && $inAdmin) ? 'active' : ''; ?>">
                                <?php echo getIcon('history', 16); ?> <span>History & Reports</span>
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
                        <!-- Customer Public Tabs -->
                        <li>
                            <a href="<?php echo $baseUrl; ?>/index.php" class="subnav-tab <?php echo ($currentPage === 'index.php') ? 'active' : ''; ?>">
                                <?php echo getIcon('ticket', 16); ?> <span>Take a Ticket</span>
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
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </header>

    <main class="main-content">
        <?php echo displayFlash(); ?>
