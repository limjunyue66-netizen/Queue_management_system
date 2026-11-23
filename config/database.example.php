<?php
// =============================================================================
// Database Configuration and Connection Template (Example)
// Copy this file to config/database.php and adjust your database credentials.
// =============================================================================

// Set standard timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// -----------------------------------------------------------------------------
// [OPTION 1: Local Development Setup (XAMPP / WAMP)]
// -----------------------------------------------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'queue_db');
define('DB_PORT', 3307); // Default XAMPP port (auto fallback to 3306)

// -----------------------------------------------------------------------------
// [OPTION 2: Production / cPanel Web Hosting Setup]
// When deploying to cPanel or VPS, adjust with your hosting database credentials:
// -----------------------------------------------------------------------------
/*
define('DB_HOST', 'localhost');
define('DB_USER', 'yourcpanel_dbuser');
define('DB_PASS', 'your_secure_password');
define('DB_NAME', 'yourcpanel_dbname');
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

        die("<div style='font-family: Arial, sans-serif; max-width: 650px; margin: 50px auto; padding: 25px; border-radius: 8px; background: #fff3f3; border: 1px solid #ffc9c9; color: #b71c1c;'>
            <h2 style='margin-top: 0;'>Database Connection Error</h2>
            <p>Could not connect to database <strong>" . htmlspecialchars(DB_NAME) . "</strong>.</p>
            <p>Please check your credentials in <code>config/database.php</code>.</p>
        </div>");
    }
    return $pdo;
}
