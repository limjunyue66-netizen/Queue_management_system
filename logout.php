<?php
// ===================================================
// Authentication: Logout Script
// ===================================================
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Unset all session values and destroy
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Start a fresh session to carry the flash message
session_start();
setFlash('info', 'You have been successfully logged out.');

header("Location: /Queue_management_system/login.php");
exit;
