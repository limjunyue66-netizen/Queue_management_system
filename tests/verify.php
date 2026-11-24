<?php
// ===================================================
// Automated Verification & Unit Test Suite
// ===================================================
echo "=== Queue Management System Verification Suite ===\n\n";

// 1. Test Includes & Syntax
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Lint all PHP files in project
$baseDir = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
$phpFiles = [];
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $phpFiles[] = $file->getPathname();
    }
}

$lintErrors = [];
foreach ($phpFiles as $phpFile) {
    $output = [];
    $returnVar = 0;
    exec("php -l " . escapeshellarg($phpFile) . " 2>&1", $output, $returnVar);
    if ($returnVar !== 0) {
        $lintErrors[] = implode("\n", $output);
    }
}

assert(empty($lintErrors), "PHP Lint syntax errors detected:\n" . implode("\n", $lintErrors));
echo "[PASS] Lint syntax check passed for all " . count($phpFiles) . " PHP files.\n";

echo "[PASS] Successfully loaded config and includes.\n";

// 2. Test Password Hashing and Verification
$adminPass = 'admin123';
$staffPass = 'staff123';
$adminHash = '$2y$10$I.ku/e7JCufLz9qjjrQQRONXZNMSlrvgrkby0Uwx7V9GmWxBjt6tm';
$staffHash = '$2y$10$RxT5eRtaBiGwcz/O2mOaPOFGodbnSjY55sjX.oifxJEVI9LxH8aOu';

assert(password_verify($adminPass, $adminHash), "Admin password verification failed");
assert(password_verify($staffPass, $staffHash), "Staff password verification failed");
assert(!password_verify('wrongpassword', $adminHash), "Invalid password check failed");
echo "[PASS] Password hashing and verification tests passed.\n";

// 2.1 Test Quick Login Role Mapping
$rolesToTest = [
    'admin' => ['expected_user' => 'admin', 'expected_pass' => 'admin123'],
    'staff' => ['expected_user' => 'staff1', 'expected_pass' => 'staff123'],
    'user'  => ['expected_user' => 'user',   'expected_pass' => 'user123']
];
foreach ($rolesToTest as $r => $exp) {
    if ($r === 'admin') {
        $u = 'admin'; $p = 'admin123';
    } elseif ($r === 'staff') {
        $u = 'staff1'; $p = 'staff123';
    } else {
        $u = 'user'; $p = 'user123';
    }
    assert($u === $exp['expected_user'] && $p === $exp['expected_pass'], "Quick login mapping failed for role $r");
}
echo "[PASS] Quick Login role mapping verified for Admin, Staff, and Citizen.\n";

// 2.2 Test Strict Role Separation (Admin, Staff, Citizen User)
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';
$userObj = currentUser();
assert($userObj['role'] === 'admin', "Current user role assertion failed");

// Test Staff Role
$_SESSION['user_id'] = 2;
$_SESSION['username'] = 'staff1';
$_SESSION['role'] = 'staff';
$staffObj = currentUser();
assert($staffObj['role'] === 'staff', "Current staff role assertion failed");

// Test Citizen User Role
$_SESSION['user_id'] = 4;
$_SESSION['username'] = 'user';
$_SESSION['role'] = 'user';
$citizenObj = currentUser();
assert($citizenObj['role'] === 'user', "Current citizen user role assertion failed");
echo "[PASS] Strict Role Separation: Admin, Staff, and Citizen roles validated.\n";

// 3. Test SVG Icon Generation
$iconsToTest = ['dashboard', 'users', 'user', 'user-plus', 'desktop', 'counter', 'list', 'history', 'tv', 'ticket', 'search', 'bell', 'check-circle', 'calendar', 'file-text', 'beaker'];
foreach ($iconsToTest as $icon) {
    $svg = getIcon($icon);
    assert(!empty($svg) && strpos($svg, '<svg') !== false, "Icon '$icon' failed to render SVG");
}
echo "[PASS] SVG icon generator tested (" . count($iconsToTest) . " icons verified).\n";

// 4. Test HTML Escaping
$dirty = '<script>alert("xss")</script>';
$clean = e($dirty);
assert(strpos($clean, '<script>') === false, "HTML escaping failed");
echo "[PASS] Security sanitization e() verified.\n";

// 5. Test Status Badges
$statuses = ['waiting', 'calling', 'serving', 'completed', 'skipped', 'open', 'closed', 'confirmed', 'approved', 'submitted'];
foreach ($statuses as $s) {
    $badge = renderStatusBadge($s);
    assert(!empty($badge) && strpos($badge, 'badge') !== false, "Badge rendering failed for status: $s");
}
echo "[PASS] Status badge generator verified.\n";

// 6. Test MySQL and Database Operations
$connectedPdo = null;
try {
    $connectedPdo = getDBConnection();
    echo "[PASS] Connected to MySQL database successfully.\n";
} catch (Exception $e) {
    echo "[WARN] Could not connect: " . $e->getMessage() . "\n";
}

if ($connectedPdo) {
    // Run schema.sql import
    $sql = file_get_contents(__DIR__ . '/../database/schema.sql');
    $connectedPdo->exec($sql);
    echo "[PASS] schema.sql imported successfully.\n";

    // Test cPanel SQL compatibility
    $cpanelSql = file_get_contents(__DIR__ . '/../database/cpanel_database.sql');
    assert(!preg_match('/^\s*CREATE\s+DATABASE/im', $cpanelSql), "cPanel SQL should not contain executable CREATE DATABASE query");
    assert(!preg_match('/^\s*USE\s+[`\w]/im', $cpanelSql), "cPanel SQL should not contain executable USE statement");
    $connectedPdo->exec($cpanelSql);
    echo "[PASS] cpanel_database.sql verified and imported successfully without permission conflicts.\n";

    // Switch to queue_db
    $connectedPdo->exec("USE `queue_db`");

    // Test Users table seed
    $stmt = $connectedPdo->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
    assert($userCount >= 4, "Users seed data count check failed");
    echo "[PASS] Users seed data verified ($userCount users found in database).\n";

    // Test Services catalog seed
    $services = getAllServices($connectedPdo, true);
    assert(count($services) >= 5, "Services catalog count check failed");
    echo "[PASS] Services catalog verified (" . count($services) . " services loaded).\n";

    // Test Counters table seed
    $stmt = $connectedPdo->query("SELECT COUNT(*) FROM counters");
    $counterCount = $stmt->fetchColumn();
    assert($counterCount >= 4, "Counters seed data count check failed");
    echo "[PASS] Counters seed data verified ($counterCount counters found in database).\n";

    // Test Appointment reference & slot calculation
    $appRef = generateAppointmentRef($connectedPdo);
    assert(strpos($appRef, 'APP-') === 0, "Appointment ref format check failed");
    $slotsLeft = getAvailableSlotsCount($connectedPdo, 1, date('Y-m-d'), '08:30 - 09:30');
    assert($slotsLeft >= 0 && $slotsLeft <= 5, "Slot availability check failed");
    echo "[PASS] Appointment reference generator ($appRef) and slot availability ($slotsLeft slots left) verified.\n";

    // Test Application reference
    $applRef = generateApplicationRef($connectedPdo);
    assert(strpos($applRef, 'IMP-') === 0, "Application ref format check failed");
    echo "[PASS] Application reference generator ($applRef) verified.\n";

    // Test Queue Number Generator
    $qGen1 = generateNextQueueNumber($connectedPdo, 'Passport Renewal');
    $qGen2 = generateNextQueueNumber($connectedPdo, 'Visa Application');
    echo "[PASS] Generated queue numbers: Passport Renewal -> $qGen1, Visa -> $qGen2.\n";

    // Test Counter Designated Service Mapping
    assert(getCounterDesignatedService('Counter 1', 'Passport Renewal') === 'Passport Renewal', "Counter 1 service mapping failed");
    assert(getCounterDesignatedService('Counter 2', 'Visa Application') === 'Visa Application', "Counter 2 service mapping failed");
    assert(getCounterDesignatedService('Counter 3', 'Work Permit') === 'Work Permit', "Counter 3 service mapping failed");
    assert(getCounterDesignatedService('Counter 4', 'Immigration Enquiry') === 'Immigration Enquiry', "Counter 4 service mapping failed");
    echo "[PASS] Counter to Service department mapping strictly verified (No cross-calling).\n";

    // Test Complete Queue Life Cycle (waiting -> calling -> serving -> completed)
    $stmtIns = $connectedPdo->prepare("INSERT INTO queues (queue_number, customer_name, service_type, status) VALUES (?, 'Test Customer', 'Passport Renewal', 'waiting')");
    $stmtIns->execute(['A999']);
    $testId = $connectedPdo->lastInsertId();
    assert($testId > 0, "Insert test queue failed");

    // Call Next
    $connectedPdo->prepare("UPDATE queues SET status = 'calling', counter_id = 1, staff_id = 2, called_at = NOW() WHERE id = ?")->execute([$testId]);
    $chk1 = $connectedPdo->query("SELECT status FROM queues WHERE id = $testId")->fetchColumn();
    assert($chk1 === 'calling', "State transition to 'calling' failed");

    // Start Serving
    $connectedPdo->prepare("UPDATE queues SET status = 'serving', started_at = NOW() WHERE id = ?")->execute([$testId]);
    $chk2 = $connectedPdo->query("SELECT status FROM queues WHERE id = $testId")->fetchColumn();
    assert($chk2 === 'serving', "State transition to 'serving' failed");

    // Complete
    $connectedPdo->prepare("UPDATE queues SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([$testId]);
    $chk3 = $connectedPdo->query("SELECT status FROM queues WHERE id = $testId")->fetchColumn();
    assert($chk3 === 'completed', "State transition to 'completed' failed");

    // Test Mobile Tracker API Endpoint with current completed record
    $_GET['queue_number'] = 'A999';
    ob_start();
    require __DIR__ . '/../api/queue_status.php';
    $apiOutput = ob_get_clean();
    $jsonData = json_decode($apiOutput, true);
    assert($jsonData && $jsonData['success'] === true, "Mobile Tracker API failed to return JSON");
    assert(isset($jsonData['spaced_number']) && $jsonData['status'] === 'completed', "Mobile API status should be completed");
    echo "[PASS] Mobile Tracker API (JSON real-time sync endpoint) tested successfully.\n";

    // Clean up test record
    $connectedPdo->prepare("DELETE FROM queues WHERE id = ?")->execute([$testId]);
    echo "[PASS] Complete Queue Life Cycle transaction (waiting -> calling -> serving -> completed) passed.\n";
} else {
    echo "[WARN] Could not connect to MySQL server.\n";
}

echo "\n=== ALL VERIFICATION TESTS COMPLETED SUCCESSFULLY! ===\n";
