<?php
// ===================================================
// Queue Ticket Receipt & Print View with Mobile QR Code
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();
$pageTitle = 'Your Queue Ticket';

$ticketId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$queueNum = strtoupper(trim($_GET['num'] ?? ($_GET['queue_number'] ?? ($_GET['ticket'] ?? ''))));

$ticket = null;
if ($ticketId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM queues WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
} elseif (!empty($queueNum)) {
    $stmt = $pdo->prepare("SELECT * FROM queues WHERE queue_number = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$queueNum]);
    $ticket = $stmt->fetch();
} else {
    // If no explicit ticket ID/num given, check if current logged in user has an active queue today
    $currentUser = currentUser();
    if ($currentUser) {
        $stmt = $pdo->prepare("
            SELECT * FROM queues 
            WHERE user_id = ? AND status IN ('waiting', 'calling', 'serving', 'completed') AND DATE(created_at) = CURDATE()
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$currentUser['id']]);
        $ticket = $stmt->fetch();
    }
}

if (!$ticket) {
    setFlash('error', 'Ticket record not found.');
    header("Location: index.php");
    exit;
}

// Calculate how many people ahead
$stmtAhead = $pdo->prepare("SELECT COUNT(*) FROM queues WHERE status = 'waiting' AND id < ? AND DATE(created_at) = DATE(?)");
$stmtAhead->execute([$ticket['id'], $ticket['created_at']]);
$aheadCount = (int)$stmtAhead->fetchColumn();

// Estimated wait time (approx 4-5 mins per person)
$estimatedMinutes = ($aheadCount + 1) * 4;

// Determine target tracker URL for QR Code (dynamic environment aware)
$mobileTrackerUrl = getMobileTrackerUrl($ticket['queue_number']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="ticket-container">
    <div style="font-size: 0.85rem; font-weight: 700; color: #64748b; letter-spacing: 1px; text-transform: uppercase;">
        QUEUE FLOW SERVICE CENTER
    </div>
    <div style="font-size: 1.5rem; font-weight: 900; color: #0f172a; margin-top: 0.2rem; letter-spacing: -0.3px;">
        Official Queue Ticket
    </div>

    <hr style="border: none; border-top: 1px dashed #cbd5e1; margin: 1.15rem 0;">

    <div style="color: var(--text-muted); font-size: 0.9rem; font-weight: 600; text-transform: uppercase;">
        Your Ticket Number
    </div>
    <div class="ticket-number"><?php echo e($ticket['queue_number']); ?></div>

    <div style="margin-bottom: 1rem;">
        <?php echo renderStatusBadge($ticket['status']); ?>
    </div>

    <!-- Ticket Summary Box -->
    <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1.15rem; margin: 1.25rem 0; text-align: left; font-size: 0.9rem;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span style="color: var(--text-muted);">Customer:</span>
            <strong><?php echo e($ticket['customer_name']); ?></strong>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span style="color: var(--text-muted);">Service:</span>
            <strong style="text-align: right; max-width: 60%;"><?php echo e($ticket['service_type']); ?></strong>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span style="color: var(--text-muted);">Date & Time:</span>
            <span><?php echo formatDateTime($ticket['created_at']); ?></span>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span style="color: var(--text-muted);">Customers Ahead:</span>
            <strong style="color: #d97706;"><?php echo $aheadCount; ?> person(s)</strong>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span style="color: var(--text-muted);">Est. Wait Time:</span>
            <strong style="color: #2563eb;">~<?php echo $estimatedMinutes; ?> mins</strong>
        </div>
    </div>

    <!-- QR Code Scan Box (Matches Real-world Smart Queue Receipt) -->
    <div class="qr-ticket-box" style="background: #ffffff; border: 2px dashed #93c5fd; border-radius: var(--radius-md); padding: 1.25rem 1rem; margin: 1.5rem 0; text-align: center;">
        <div class="qr-ticket-title" style="font-size: 0.85rem; font-weight: 800; color: #1e40af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.75rem;">
            📱 SCAN WITH PHONE CAMERA
        </div>
        
        <div id="qrcodeBox" style="display: flex; justify-content: center; align-items: center; min-height: 140px; margin: 0 auto;">
            <!-- Rendered by QRCode Generator Script -->
        </div>

        <div style="font-size: 0.82rem; color: #64748b; margin-top: 0.75rem; line-height: 1.4;">
            Scan this QR code on your mobile to track live calling screen status and receive instant completion updates.
        </div>
    </div>

    <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 1.5rem; line-height: 1.45;">
        Please watch the display screen or track your status online.<br>Thank you for your patience!
    </div>

    <!-- Action Buttons -->
    <div class="no-print" style="display: flex; flex-direction: column; gap: 0.65rem;">
        <button onclick="window.print()" class="btn btn-primary" style="width: 100%;">
            <?php echo getIcon('printer', 18); ?> Print Ticket Receipt
        </button>

        <a href="check_status.php?queue_number=<?php echo urlencode($ticket['queue_number']); ?>" class="btn btn-outline" style="width: 100%;">
            <?php echo getIcon('search', 18); ?> Check Status Online
        </a>

        <a href="index.php" class="btn btn-outline" style="width: 100%;">
            <?php echo getIcon('home', 18); ?> Back to Kiosk Home
        </a>
    </div>
</div>

<!-- Standalone Offline QR Code Generator Script -->
<script src="assets/js/qrcode.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var trackerTargetUrl = "<?php echo addslashes($mobileTrackerUrl); ?>";
    // If accessing via actual domain online, lock dynamically to current domain origin to ensure instant direct opening
    if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
        var basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
        trackerTargetUrl = window.location.origin + basePath + '/mobile_tracker.php?ticket=' + encodeURIComponent("<?php echo addslashes($ticket['queue_number']); ?>");
    }

    try {
        if (window.QRCode) {
            new QRCode("qrcodeBox", {
                text: trackerTargetUrl,
                width: 135,
                height: 135
            });
        }
    } catch(e) {
        console.log("QR Code generation fallback");
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
