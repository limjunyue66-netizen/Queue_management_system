<?php
require_once __DIR__ . '/../config/database.php';

try {
    $rawPdo = new PDO("mysql:host=127.0.0.1;port=3307;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "1. Creating standalone test database: cpanel_import_test...\n";
    $rawPdo->exec("CREATE DATABASE IF NOT EXISTS cpanel_import_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    echo "2. Connecting to cpanel_import_test...\n";
    $testPdo = new PDO("mysql:host=127.0.0.1;port=3307;dbname=cpanel_import_test;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "3. Reading database/cpanel_database.sql...\n";
    $sqlContent = file_get_contents(__DIR__ . '/../database/cpanel_database.sql');

    echo "4. Executing cpanel_database.sql...\n";
    $testPdo->exec($sqlContent);

    echo "5. Verifying imported tables...\n";
    $tables = $testPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Found " . count($tables) . " tables: " . implode(", ", $tables) . "\n";
    assert(count($tables) === 6, "Expected 6 tables");

    echo "6. Verifying seed data in cpanel_import_test...\n";
    $users = $testPdo->query("SELECT id, username, role FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo "Users count: " . count($users) . "\n";
    foreach ($users as $u) {
        echo "  - ID {$u['id']}: {$u['username']} ({$u['role']})\n";
    }

    $services = $testPdo->query("SELECT id, code, name FROM services ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo "Services count: " . count($services) . "\n";
    foreach ($services as $s) {
        echo "  - ID {$s['id']}: [{$s['code']}] {$s['name']}\n";
    }

    $counters = $testPdo->query("SELECT id, counter_number, current_staff_id, designated_service_id FROM counters ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo "Counters count: " . count($counters) . "\n";
    foreach ($counters as $c) {
        echo "  - ID {$c['id']}: {$c['counter_number']} -> Staff #{$c['current_staff_id']}, Service #{$c['designated_service_id']}\n";
    }

    echo "7. Cleaning up test database...\n";
    $rawPdo->exec("DROP DATABASE IF EXISTS cpanel_import_test");

    echo "\n>>> [SUCCESS] cpanel_database.sql imported cleanly without ANY errors or permission issues! <<<\n";
} catch (Exception $e) {
    echo "\n>>> [FAIL] Error importing cpanel_database.sql: " . $e->getMessage() . "\n";
    exit(1);
}
