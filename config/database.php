<?php
// =============================================================================
// Database Configuration and Connection
// Works out-of-the-box on local XAMPP and cPanel Cloud Hosting
// =============================================================================

// Set standard timezone (Malaysia GMT+8)
date_default_timezone_set('Asia/Kuala_Lumpur');

// -----------------------------------------------------------------------------
// [OPTION 1: Local XAMPP Setup]
// -----------------------------------------------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'queue_db');
define('DB_PORT', 3307); // Local XAMPP configured port (with automatic 3306 fallback)

// -----------------------------------------------------------------------------
// [OPTION 2: cPanel Production Setup Example]
// When uploading to cPanel, simply comment out OPTION 1 above and uncomment below:
// -----------------------------------------------------------------------------
/*
define('DB_HOST', 'localhost');
define('DB_USER', 'yourcpanel_dbuser');
define('DB_PASS', 'your_strong_password');
define('DB_NAME', 'yourcpanel_queuedb');
define('DB_PORT', 3306);
*/

function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $portsToTry = [DB_PORT, 3306, 3307];
        $lastException = null;

        // Try primary configured credentials
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

        // Automatic local XAMPP fallback for local development if cPanel credentials are set
        if (DB_USER !== 'root') {
            foreach ([3307, 3306] as $port) {
                try {
                    $dsn = "mysql:host=127.0.0.1;port=" . $port . ";dbname=queue_db;charset=utf8mb4";
                    $options = [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ];
                    $pdo = new PDO($dsn, 'root', '', $options);
                    return $pdo;
                } catch (PDOException $e) {
                    // keep trying
                }
            }
        }

        // Friendly error guide if database connection fails
        die("<div style='font-family: Arial, sans-serif; max-width: 650px; margin: 50px auto; padding: 25px; border-radius: 8px; background: #fff3f3; border: 1px solid #ffc9c9; color: #b71c1c;'>
            <h2 style='margin-top: 0;'>Database Connection Error</h2>
            <p>Could not connect to database <strong>" . htmlspecialchars(DB_NAME) . "</strong> on host <strong>" . htmlspecialchars(DB_HOST) . "</strong>.</p>
            <p><strong>Troubleshooting:</strong></p>
            <ul>
                <li><strong>On Localhost (XAMPP):</strong> Ensure MySQL is started in XAMPP Control Panel.</li>
                <li><strong>On cPanel:</strong> Ensure you created database in <em>cPanel -> MySQL Databases</em> and assigned user with All Privileges.</li>
                <li>Check your database credentials in <code>config/database.php</code>.</li>
            </ul>
            <p><small style='color: #666;'>Error Details: " . htmlspecialchars($lastException ? $lastException->getMessage() : 'Unknown') . "</small></p>
        </div>");
    }
    return $pdo;
}
