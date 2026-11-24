<?php
// =============================================================================
// Database Configuration Template (Copy to config/database.php)
// Works out-of-the-box on local XAMPP and production cPanel Cloud Hosting
// =============================================================================

// Set standard timezone (e.g. Asia/Kuala_Lumpur GMT+8)
date_default_timezone_set('Asia/Kuala_Lumpur');

// -----------------------------------------------------------------------------
// [OPTION 1: Local Development / XAMPP]
// -----------------------------------------------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'queue_db');
define('DB_PORT', 3306); // Default MySQL port (or 3307 if configured)

// -----------------------------------------------------------------------------
// [OPTION 2: Production Hosting / cPanel Setup]
// For production, comment out Option 1 and fill in your database credentials:
// -----------------------------------------------------------------------------
/*
define('DB_HOST', 'localhost');
define('DB_USER', 'your_cpanel_db_username');
define('DB_PASS', 'your_secure_password');
define('DB_NAME', 'your_cpanel_db_name');
define('DB_PORT', 3306);
*/

function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $portsToTry = [DB_PORT, 3306, 3307];
        $lastException = null;

        foreach (array_unique($portsToTry) as $port) {
            try {
                if (DB_HOST === 'localhost') {
                    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                } else {
                    $dsn = "mysql:host=" . DB_HOST . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                }

                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                return $pdo;
            } catch (PDOException $e) {
                $lastException = $e;
            }
        }

        die("Database connection failed. Please check your credentials in config/database.php. Error: " . htmlspecialchars($lastException ? $lastException->getMessage() : 'Unknown'));
    }
    return $pdo;
}
