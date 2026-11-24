<?php
// ===================================================
// Customer Queue Status Tracker
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();
$pageTitle = 'Check Queue Status';

$searchQuery = strtoupper(trim($_GET['queue_number'] ?? ''));
$queueRecord = null;
$aheadCount = 0;
$estimatedWait = 0;
$searched = false;

if (!empty($searchQuery)) {
    $searched = true;
    $cleanSearch = preg_replace('/[^A-Za-z0-9]/', '', $searchQuery);
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
    $stmt->execute([$searchQuery, $cleanSearch, $cleanSearch . '%']);
    $queueRecord = $stmt->fetch();

    if ($queueRecord && $queueRecord['status'] === 'waiting') {
        $stmtAhead = $pdo->prepare("SELECT COUNT(*) FROM queues WHERE status = 'waiting' AND id < ?");
        $stmtAhead->execute([$queueRecord['id']]);
        $aheadCount = (int)$stmtAhead->fetchColumn();
        $estimatedWait = ($aheadCount + 1) * 4;
    }
}

// Smart dynamic mobile tracker URL for QR Code
$mobileTrackerScanUrl = $queueRecord ? getMobileTrackerUrl($queueRecord['queue_number']) : '';

// Current serving for comparison
$stmtNow = $pdo->query("SELECT queue_number, counter_number FROM queues q LEFT JOIN counters c ON q.counter_id = c.id WHERE q.status IN ('calling', 'serving') ORDER BY q.called_at DESC LIMIT 1");
$nowServing = $stmtNow->fetch();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="text-align: center; display: block;">
    <h1 class="page-title" style="justify-content: center;">
        <?php echo getIcon('search', 28); ?> Track Your Queue Status
    </h1>
    <p class="page-desc">Enter your queue ticket number to view your real-time waiting position</p>
</div>

<!-- Search Form Card -->
<div class="card" style="max-width: 550px; margin: 0 auto 2rem auto;">
    <form method="GET" action="check_status.php" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 220px;">
            <input type="text" name="queue_number" class="form-control" placeholder="Enter Queue Number (e.g. A101)" value="<?php echo e($searchQuery); ?>" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary">
            <?php echo getIcon('search', 16); ?> Check Status
        </button>
    </form>
</div>

<?php if ($searched): ?>
    <?php if ($queueRecord): ?>
        <div class="card" style="max-width: 650px; margin: 0 auto; box-shadow: var(--shadow-md);">
            <div class="card-header" style="justify-content: space-between;">
                <div>
                    <h2 class="card-title" style="font-size: 1.4rem;">
                        Ticket: <span style="color: #2563eb;"><?php echo e($queueRecord['queue_number']); ?></span>
                    </h2>
                    <small style="color: var(--text-muted);"><?php echo e($queueRecord['service_type']); ?> • Created at <?php echo formatDateTime($queueRecord['created_at']); ?></small>
                </div>
                <div>
                    <?php echo renderStatusBadge($queueRecord['status']); ?>
                </div>
            </div>

            <!-- Dynamic Status Banner -->
            <?php if ($queueRecord['status'] === 'waiting'): ?>
                <div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: var(--radius-md); padding: 1.5rem; text-align: center; margin-bottom: 1.5rem;">
                    <div style="font-size: 0.95rem; color: #92400e; font-weight: 600;">YOU ARE CURRENTLY IN LINE</div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: #b45309; margin: 0.5rem 0;">
                        <?php echo $aheadCount; ?> Customer(s) Ahead
                    </div>
                    <div style="color: #78350f; font-size: 0.9rem;">
                        Estimated wait time: <strong>~<?php echo $estimatedWait; ?> minutes</strong>
                    </div>
                </div>
            <?php elseif ($queueRecord['status'] === 'calling'): ?>
                <div style="background: #eff6ff; border: 2px solid #3b82f6; border-radius: var(--radius-md); padding: 1.5rem; text-align: center; margin-bottom: 1.5rem; animation: pulse 1.5s infinite;">
                    <div style="font-size: 1.1rem; color: #1d4ed8; font-weight: 800;">🔔 IT'S YOUR TURN! NOW CALLING</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #1e3a8a; margin: 0.5rem 0;">
                        Please go to <?php echo e($queueRecord['counter_number'] ?? 'Assigned Counter'); ?>
                    </div>
                    <div style="color: #1e40af; font-size: 0.9rem;">
                        Please proceed immediately to the counter.
                    </div>
                </div>
            <?php elseif ($queueRecord['status'] === 'serving'): ?>
                <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: var(--radius-md); padding: 1.5rem; text-align: center; margin-bottom: 1.5rem;">
                    <div style="font-size: 1rem; color: #065f46; font-weight: 700;">IN SERVICE</div>
                    <div style="font-size: 1.6rem; font-weight: 800; color: #047857; margin: 0.5rem 0;">
                        Currently being served at <?php echo e($queueRecord['counter_number'] ?? 'Counter'); ?>
                    </div>
                    <div style="color: #065f46; font-size: 0.88rem;">Staff: <?php echo e($queueRecord['staff_name'] ?? 'Staff Representative'); ?></div>
                </div>
            <?php elseif ($queueRecord['status'] === 'completed'): ?>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: var(--radius-md); padding: 1.5rem; text-align: center; margin-bottom: 1.5rem;">
                    <div style="font-size: 1.5rem; font-weight: 700; color: #10b981;"><?php echo getIcon('check-circle', 28); ?> Completed</div>
                    <p style="color: var(--text-muted); margin-top: 0.5rem;">This ticket service was completed at <?php echo formatTimeOnly($queueRecord['completed_at']); ?>.</p>
                </div>
            <?php elseif ($queueRecord['status'] === 'skipped'): ?>
                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: var(--radius-md); padding: 1.5rem; text-align: center; margin-bottom: 1.5rem;">
                    <div style="font-size: 1.5rem; font-weight: 700; color: #ef4444;"><?php echo getIcon('slash', 28); ?> Ticket Skipped / Missed</div>
                    <p style="color: #991b1b; margin-top: 0.5rem;">This number was skipped because the customer was not present when called. Please contact customer support or obtain a new ticket.</p>
                </div>
            <?php endif; ?>

            <!-- Ticket details info table -->
            <div class="table-responsive">
                <table class="table">
                    <tr>
                        <th style="width: 35%;">Customer Name</th>
                        <td><?php echo e($queueRecord['customer_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Service Requested</th>
                        <td><?php echo e($queueRecord['service_type']); ?></td>
                    </tr>
                    <tr>
                        <th>Counter Assigned</th>
                        <td><?php echo e($queueRecord['counter_number'] ?: 'Not assigned yet'); ?></td>
                    </tr>
                    <tr>
                        <th>Currently Serving in Hall</th>
                        <td>
                            <?php if ($nowServing): ?>
                                <strong style="color: #2563eb;"><?php echo e($nowServing['queue_number']); ?></strong> (<?php echo e($nowServing['counter_number']); ?>)
                            <?php else: ?>
                                <span style="color: var(--text-muted);">None</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Mobile Phone QR Code Scan Box -->
            <div style="background: #f8fafc; border: 2px dashed #93c5fd; border-radius: var(--radius-md); padding: 1.25rem 1rem; margin: 1.5rem 0 1rem 0; text-align: center;">
                <div style="font-size: 0.85rem; font-weight: 800; color: #1e40af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.75rem;">
                    📱 SCAN WITH PHONE CAMERA TO OPEN LIVE TRACKER
                </div>
                
                <div id="checkQrcodeBox" style="display: flex; justify-content: center; align-items: center; min-height: 135px; margin: 0 auto;">
                    <!-- Rendered by QRCode Generator Script -->
                </div>

                <div style="font-size: 0.82rem; color: #64748b; margin-top: 0.75rem; line-height: 1.4;">
                    Point your smartphone camera at this QR code to monitor your queue in real time without refreshing.
                </div>
            </div>

            <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
                <button onclick="window.location.reload();" class="btn btn-primary">
                    <?php echo getIcon('refresh', 16); ?> Refresh Status
                </button>
                <a href="display.php" class="btn btn-outline">
                    <?php echo getIcon('tv', 16); ?> Open Live Display
                </a>
            </div>
        </div>

        <script src="assets/js/qrcode.min.js"></script>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            var scanUrl = "<?php echo addslashes($mobileTrackerScanUrl); ?>";
            // If accessing via actual domain online, lock dynamically to current domain origin
            if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                var basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
                scanUrl = window.location.origin + basePath + '/mobile_tracker.php?ticket=' + encodeURIComponent("<?php echo addslashes($queueRecord['queue_number']); ?>");
            }

            try {
                if (window.QRCode && scanUrl) {
                    new QRCode("checkQrcodeBox", {
                        text: scanUrl,
                        width: 130,
                        height: 130
                    });
                }
            } catch(e) {
                console.log("QR Code generation fallback");
            }
        });
        </script>
    <?php else: ?>
        <div class="alert alert-error" style="max-width: 550px; margin: 0 auto; text-align: center; justify-content: center;">
            <?php echo getIcon('alert-triangle', 18); ?> No active queue ticket found for <strong><?php echo e($searchQuery); ?></strong> today. Please check the ticket number.
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
