<?php
// ===================================================
// Real-time Queue Status JSON API for Mobile Tracker
// ===================================================
if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDBConnection();
$queueNumber = strtoupper(trim($_GET['queue_number'] ?? $_GET['ticket'] ?? ''));
$cleanNum = preg_replace('/[^A-Za-z0-9]/', '', $queueNumber);

if (empty($queueNumber)) {
    echo json_encode(['success' => false, 'message' => 'Queue number required.']);
    exit;
}

// Fetch queue record (most recent for this ticket)
$stmt = $pdo->prepare("
    SELECT q.*, c.counter_number, c.counter_name, u.full_name as staff_name
    FROM queues q
    LEFT JOIN counters c ON q.counter_id = c.id
    LEFT JOIN users u ON q.staff_id = u.id
    WHERE UPPER(TRIM(q.queue_number)) = ? 
       OR REPLACE(REPLACE(UPPER(q.queue_number), ' ', ''), '-', '') = ?
       OR UPPER(TRIM(q.queue_number)) LIKE ?
    ORDER BY q.id DESC LIMIT 1
");
$stmt->execute([$queueNumber, $cleanNum, $cleanNum . '%']);
$queue = $stmt->fetch();

if (!$queue) {
    echo json_encode(['success' => false, 'message' => 'Queue ticket not found.']);
    exit;
}

// Calculate customers ahead if waiting
$customersAhead = 0;
$estimatedWaitMinutes = 0;

if ($queue['status'] === 'waiting') {
    $stmtAhead = $pdo->prepare("SELECT COUNT(*) FROM queues WHERE status = 'waiting' AND id < ?");
    $stmtAhead->execute([$queue['id']]);
    $customersAhead = (int)$stmtAhead->fetchColumn();
    $estimatedWaitMinutes = ($customersAhead + 1) * 4;
} else {
    $customersAhead = 0;
    $estimatedWaitMinutes = 0;
}

// Latest called number in hall
$stmtLatest = $pdo->query("
    SELECT q.queue_number, c.counter_number 
    FROM queues q 
    LEFT JOIN counters c ON q.counter_id = c.id 
    WHERE q.status IN ('calling', 'serving') 
    ORDER BY q.called_at DESC LIMIT 1
");
$latestCalled = $stmtLatest->fetch();

// Format display number with spaces for high-end look (e.g. C 0 0 3 or A 1 0 1)
$spacedNumber = implode(' ', str_split($queue['queue_number']));

echo json_encode([
    'success'               => true,
    'queue_number'          => $queue['queue_number'],
    'spaced_number'         => $spacedNumber,
    'customer_name'         => $queue['customer_name'],
    'service_type'          => $queue['service_type'],
    'status'                => $queue['status'],
    'counter_number'        => $queue['counter_number'] ?: 'Standby / Unassigned',
    'staff_name'            => $queue['staff_name'] ?: 'Officer on duty',
    'customers_ahead'       => $customersAhead,
    'estimated_wait_minutes'=> $estimatedWaitMinutes,
    'registered_at'         => date('h:i A', strtotime($queue['created_at'])),
    'last_updated'          => date('h:i:s A'),
    'completed_at'          => $queue['completed_at'] ? date('h:i A', strtotime($queue['completed_at'])) : null,
    'now_serving_number'    => $latestCalled['queue_number'] ?? 'None',
    'now_serving_counter'   => $latestCalled['counter_number'] ?? '-'
]);
