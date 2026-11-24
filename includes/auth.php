<?php
// ===================================================
// Authentication & Session Guard
// Supports Admin, Staff, and Citizen User roles
// ===================================================

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

/**
 * Check if a user is currently logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current logged in user information
 */
function currentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'          => $_SESSION['user_id'],
        'username'    => $_SESSION['username'] ?? '',
        'full_name'   => $_SESSION['full_name'] ?? '',
        'role'        => $_SESSION['role'] ?? 'user',
        'ic_passport' => $_SESSION['ic_passport'] ?? '',
        'phone'       => $_SESSION['phone'] ?? '',
        'email'       => $_SESSION['email'] ?? '',
    ];
}

/**
 * Require user to be logged in
 */
function requireLogin($redirectPath = '/Queue_management_system/login.php') {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = 'Please log in to access this page.';
        header("Location: $redirectPath");
        exit;
    }
}

/**
 * Require user to have Admin role
 */
function requireAdmin($redirectPath = '/Queue_management_system/login.php') {
    requireLogin($redirectPath);
    if ($_SESSION['role'] !== 'admin') {
        $_SESSION['flash_error'] = 'Access denied. Administrator privileges required.';
        if ($_SESSION['role'] === 'staff') {
            header("Location: /Queue_management_system/staff/index.php");
        } else {
            header("Location: /Queue_management_system/user/dashboard.php");
        }
        exit;
    }
}

/**
 * Require user to have Staff role only (strict separation)
 */
function requireStaff($redirectPath = '/Queue_management_system/login.php') {
    requireLogin($redirectPath);
    if ($_SESSION['role'] === 'admin') {
        $_SESSION['flash_error'] = 'Access restricted: Staff Service Station is for counter staff only.';
        header("Location: /Queue_management_system/admin/index.php");
        exit;
    }
    if ($_SESSION['role'] === 'user') {
        $_SESSION['flash_error'] = 'Access restricted to official staff members only.';
        header("Location: /Queue_management_system/user/dashboard.php");
        exit;
    }
    if ($_SESSION['role'] !== 'staff') {
        $_SESSION['flash_error'] = 'Access denied. Staff privileges required.';
        header("Location: $redirectPath");
        exit;
    }
}

/**
 * Require user to have Citizen User role
 */
function requireUser($redirectPath = '/Queue_management_system/login.php') {
    requireLogin($redirectPath);
    if ($_SESSION['role'] === 'admin') {
        header("Location: /Queue_management_system/admin/index.php");
        exit;
    }
    if ($_SESSION['role'] === 'staff') {
        header("Location: /Queue_management_system/staff/index.php");
        exit;
    }
}
