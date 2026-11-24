<?php
// ===================================================
// Database Migration: 4 Counters = 4 Services = 4 Staff
// ===================================================
require_once __DIR__ . '/../config/database.php';
$pdo = getDBConnection();

echo "Starting migration for 4 Counters, 4 Services, and 4 Staff...\n";

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

// 1. Setup 4 Staff accounts (staff1, staff2, staff3, staff4)
$hash = password_hash('staff123', PASSWORD_DEFAULT);

$pdo->exec("
    INSERT INTO users (id, username, email, password, full_name, ic_passport, phone, role, status)
    VALUES (1, 'admin', 'admin@example.com', '$hash', 'System Administrator', 'A00000000', '+60 12-000 0000', 'admin', 'active')
    ON DUPLICATE KEY UPDATE role = 'admin', status = 'active', full_name = 'System Administrator'
");

$pdo->exec("
    INSERT INTO users (id, username, email, password, full_name, ic_passport, phone, role, status)
    VALUES (2, 'staff1', 'staff1@example.com', '$hash', 'Staff Nurul Izzati', 'S11111111', '+60 13-111 2222', 'staff', 'active')
    ON DUPLICATE KEY UPDATE role = 'staff', status = 'active', full_name = 'Staff Nurul Izzati'
");

$pdo->exec("
    INSERT INTO users (id, username, email, password, full_name, ic_passport, phone, role, status)
    VALUES (3, 'staff2', 'staff2@example.com', '$hash', 'Michael Chen', 'S22222222', '+60 14-333 4444', 'staff', 'active')
    ON DUPLICATE KEY UPDATE role = 'staff', status = 'active', full_name = 'Michael Chen'
");

// Check staff3
$s3 = $pdo->query("SELECT id FROM users WHERE username = 'staff3'")->fetchColumn();
if (!$s3) {
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, ic_passport, phone, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['staff3', 'staff3@example.com', $hash, 'Siti Sarah', 'S33333333', '+60 15-555 6666', 'staff', 'active']);
    $s3Id = (int)$pdo->lastInsertId();
} else {
    $s3Id = (int)$s3;
}

// Check staff4
$s4 = $pdo->query("SELECT id FROM users WHERE username = 'staff4'")->fetchColumn();
if (!$s4) {
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, ic_passport, phone, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['staff4', 'staff4@example.com', $hash, 'David Wong', 'S44444444', '+60 16-777 8888', 'staff', 'active']);
    $s4Id = (int)$pdo->lastInsertId();
} else {
    $s4Id = (int)$s4;
}

// 2. Rebuild Services Table to exactly 4 standard services
$pdo->exec("TRUNCATE TABLE services");

$pdo->exec("
    INSERT INTO services (id, code, name, description, est_duration, status) VALUES
    (1, 'PS', 'Passport Services', 'Comprehensive passport renewal, first-time application, and document collection.', 20, 'active'),
    (2, 'VA', 'Visa Application', 'Long-term social visit pass, student pass, and entry visa processing.', 25, 'active'),
    (3, 'WP', 'Work Permit', 'Employment pass and temporary employment visit pass renewal.', 20, 'active'),
    (4, 'IE', 'Immigration Enquiry', 'General inquiries, special passes, compound settlement, and advisory.', 15, 'active')
");

// Remap legacy service_ids in related tables
// Old 1 (PR), 2 (PA) -> 1 (PS)
// Old 3 (VA) -> 2 (VA)
// Old 4 (WP) -> 3 (WP)
// Old 5 (IE) -> 4 (IE)
$pdo->exec("UPDATE appointments SET service_id = 1 WHERE service_id IN (1, 2)");
$pdo->exec("UPDATE appointments SET service_id = 2 WHERE service_id = 3");
$pdo->exec("UPDATE appointments SET service_id = 3 WHERE service_id = 4");
$pdo->exec("UPDATE appointments SET service_id = 4 WHERE service_id >= 5");

$pdo->exec("UPDATE applications SET service_id = 1 WHERE service_id IN (1, 2)");
$pdo->exec("UPDATE applications SET service_id = 2 WHERE service_id = 3");
$pdo->exec("UPDATE applications SET service_id = 3 WHERE service_id = 4");
$pdo->exec("UPDATE applications SET service_id = 4 WHERE service_id >= 5");

$pdo->exec("UPDATE queues SET service_id = 1, service_type = 'Passport Services' WHERE service_type LIKE '%Passport%' OR service_id IN (1, 2)");
$pdo->exec("UPDATE queues SET service_id = 2, service_type = 'Visa Application' WHERE service_type LIKE '%Visa%' OR service_id = 3");
$pdo->exec("UPDATE queues SET service_id = 3, service_type = 'Work Permit' WHERE service_type LIKE '%Work Permit%' OR service_id = 4");
$pdo->exec("UPDATE queues SET service_id = 4, service_type = 'Immigration Enquiry' WHERE service_type LIKE '%Enquiry%' OR service_id >= 5");

// 3. Rebuild Counters Table: Exactly 4 counters, 1:1 with Staff and Services
$pdo->exec("TRUNCATE TABLE counters");

$pdo->exec("
    INSERT INTO counters (id, counter_number, counter_name, status, current_staff_id, designated_service_id) VALUES
    (1, 'Counter 01', 'Passport Renewal & Issuance', 'open', 2, 1),
    (2, 'Counter 02', 'Visa & Expatriate Services', 'open', 3, 2),
    (3, 'Counter 03', 'Work Permit Endorsement', 'open', $s3Id, 3),
    (4, 'Counter 04', 'Enquiry & Special Advisory', 'open', $s4Id, 4)
");

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "Migration executed successfully!\n";

// Print verification tables
$services = $pdo->query("SELECT id, code, name, est_duration, status FROM services ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$counters = $pdo->query("SELECT c.id, c.counter_number, c.counter_name, u.username as staff_user, u.full_name as staff_name, s.name as service_name FROM counters c LEFT JOIN users u ON c.current_staff_id = u.id LEFT JOIN services s ON c.designated_service_id = s.id ORDER BY c.id ASC")->fetchAll(PDO::FETCH_ASSOC);
$staffUsers = $pdo->query("SELECT id, username, full_name, role, status FROM users WHERE role IN ('admin', 'staff') ORDER BY role ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

echo "\n--- 4 SERVICES (1:1 with 4 Counters) ---\n";
print_r($services);

echo "\n--- 4 COUNTERS (1:1 with 4 Staff & 4 Services) ---\n";
print_r($counters);

echo "\n--- STAFF & ADMIN ACCOUNTS (4 Staff + 1 Admin) ---\n";
print_r($staffUsers);
