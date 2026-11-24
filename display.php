<?php
// ===================================================
// Real-time Public Queue Calling Display (TV Monitor)
// Supports Audio Announcements, Clock & Fullscreen
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();
$today = date('Y-m-d');

// Fetch all currently serving or calling queues across all counters
$stmtActive = $pdo->query("
    SELECT q.*, c.counter_number, c.counter_name 
    FROM queues q 
    JOIN counters c ON q.counter_id = c.id 
    WHERE q.status IN ('calling', 'serving') AND DATE(q.created_at) = CURDATE()
    ORDER BY q.status ASC, q.called_at DESC
");
$activeServingList = $stmtActive->fetchAll();

// Fetch upcoming waiting tickets (Next Queue list)
$stmtWaiting = $pdo->query("
    SELECT q.*, s.code as service_code
    FROM queues q
    LEFT JOIN services s ON q.service_id = s.id
    WHERE q.status = 'waiting' AND DATE(q.created_at) = CURDATE() 
    ORDER BY q.id ASC 
    LIMIT 10
");
$waitingList = $stmtWaiting->fetchAll();

// Metrics for footer
$totalWaiting = count($waitingList);
$completedCount = (int)$pdo->query("SELECT COUNT(*) FROM queues WHERE status = 'completed' AND DATE(created_at) = CURDATE()")->fetchColumn();
$activeCountersCount = (int)$pdo->query("SELECT COUNT(*) FROM counters WHERE status = 'open'")->fetchColumn();

// Most recently called ticket for voice announcement
$recentCalled = $pdo->query("
    SELECT q.queue_number, c.counter_number 
    FROM queues q 
    JOIN counters c ON q.counter_id = c.id 
    WHERE q.status = 'calling' AND DATE(q.created_at) = CURDATE() 
    ORDER BY q.called_at DESC LIMIT 1
")->fetch();

$recentTicketJson = $recentCalled ? json_encode($recentCalled) : 'null';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Calling Display - QueueFlow</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background-color: #0b1329;
            color: #f8fafc;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            overflow-x: hidden;
        }
        .tv-topbar {
            background: #0f1e42;
            border-bottom: 2px solid #2563eb;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .tv-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .tv-logo {
            width: 44px;
            height: 44px;
            background: #2563eb;
            color: #fff;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .tv-title {
            font-size: 1.5rem;
            font-weight: 900;
            letter-spacing: -0.5px;
            color: #ffffff;
            margin: 0;
        }
        .tv-subtitle {
            font-size: 0.78rem;
            color: #94a3b8;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-weight: 700;
        }
        .tv-clock-box {
            text-align: right;
        }
        .tv-clock {
            font-size: 1.6rem;
            font-weight: 900;
            color: #f59e0b;
            font-variant-numeric: tabular-nums;
            letter-spacing: 1px;
        }
        .tv-date {
            font-size: 0.85rem;
            color: #cbd5e1;
        }
        .tv-content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.75rem;
            padding: 2rem;
            min-height: calc(100vh - 160px);
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1.25rem;
            background: #14244d;
            border-left: 4px solid #2563eb;
            border-radius: 8px 8px 0 0;
            margin-bottom: 1rem;
        }
        .section-title {
            font-weight: 800;
            font-size: 1.1rem;
            color: #f59e0b;
            letter-spacing: 1px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .pill-badge {
            background: #f59e0b;
            color: #0f172a;
            font-size: 0.75rem;
            font-weight: 800;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
        }
        .active-card {
            background: #132247;
            border: 2px solid #1e3a8a;
            border-radius: 12px;
            padding: 1.75rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5);
            transition: all 0.3s ease;
        }
        .active-card.is-calling {
            border-color: #f59e0b;
            background: #192b59;
            animation: pulse-border 1.5s infinite;
        }
        @keyframes pulse-border {
            0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(245, 158, 11, 0); }
            100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
        }
        .big-ticket {
            font-size: 3.8rem;
            font-weight: 900;
            color: #38bdf8;
            letter-spacing: 2px;
            line-height: 1;
        }
        .waiting-list-item {
            background: #132247;
            border: 1px solid #1e3a8a;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .tv-footer {
            background: #080d1a;
            border-top: 1px solid #1e293b;
            padding: 0.75rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: #94a3b8;
        }
        .pulse-dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 8px #10b981;
            margin-right: 6px;
        }
    </style>
</head>
<body>

    <!-- Top Display Header -->
    <div class="tv-topbar">
        <div class="tv-brand">
            <div class="tv-logo">
                <?php echo getIcon('ticket', 24); ?>
            </div>
            <div>
                <h1 class="tv-title">QueueFlow Live Display</h1>
                <div class="tv-subtitle">Official Public Calling Display Screen</div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 1.5rem;">
            <!-- Sound Toggle Button -->
            <button id="soundToggleBtn" onclick="toggleSound()" class="btn btn-secondary btn-sm" style="background:#1e293b; color:#fff; border-color:#334155; padding: 0.4rem 0.85rem;">
                🔊 Sound: Active
            </button>

            <!-- Fullscreen Button -->
            <button onclick="toggleFullscreen()" class="btn btn-secondary btn-sm" style="background:#1e293b; color:#fff; border-color:#334155; padding: 0.4rem 0.85rem;">
                ⛶ Fullscreen
            </button>

            <!-- Clock & Date -->
            <div class="tv-clock-box">
                <div id="liveClock" class="tv-clock"><?php echo date('h:i:s A'); ?></div>
                <div class="tv-date"><?php echo date('l, d F Y'); ?></div>
            </div>
        </div>
    </div>

    <!-- Main Live Screen Area -->
    <div class="tv-content-grid">
        <!-- Left Column: Currently Serving -->
        <div>
            <div class="section-header">
                <div class="section-title">
                    📢 CURRENTLY SERVING
                </div>
                <span class="pill-badge"><?php echo count($activeServingList); ?> ACTIVE</span>
            </div>

            <?php if (empty($activeServingList)): ?>
                <div style="background: #132247; border: 1px dashed #1e3a8a; border-radius: 12px; padding: 5rem 2rem; text-align: center; color: #64748b;">
                    <div style="font-size: 1.6rem; font-weight: 700; color: #94a3b8; margin-bottom: 0.5rem;">
                        No Numbers Currently Being Served
                    </div>
                    <p style="font-size: 1rem; margin: 0;">Counters will call upcoming ticket numbers shortly.</p>
                </div>
            <?php else: ?>
                <?php foreach ($activeServingList as $act): ?>
                    <div class="active-card <?php echo ($act['status'] === 'calling') ? 'is-calling' : ''; ?>">
                        <div>
                            <div style="font-size: 0.85rem; font-weight: 800; color: #f59e0b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.35rem;">
                                <?php echo ($act['status'] === 'calling') ? '🔔 NOW CALLING' : 'IN SERVICE'; ?>
                            </div>
                            <div class="big-ticket"><?php echo e($act['queue_number']); ?></div>
                            <div style="color: #94a3b8; font-size: 1.1rem; margin-top: 0.5rem;">
                                Customer: <strong style="color: #fff;"><?php echo e($act['customer_name']); ?></strong> &bull; 
                                <?php echo e($act['service_type']); ?>
                            </div>
                        </div>

                        <div style="text-align: right; background: #0b1329; border: 1px solid #1e3a8a; border-radius: 12px; padding: 1.25rem 2rem;">
                            <div style="font-size: 0.85rem; color: #94a3b8; text-transform: uppercase; font-weight: 700;">PLEASE PROCEED TO</div>
                            <div style="font-size: 2.2rem; font-weight: 900; color: #10b981; margin-top: 0.2rem;">
                                <?php echo e($act['counter_number']); ?>
                            </div>
                            <div style="font-size: 0.85rem; color: #cbd5e1;"><?php echo e($act['counter_name']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Right Column: Next in Queue -->
        <div>
            <div class="section-header" style="border-left-color: #38bdf8;">
                <div class="section-title" style="color: #38bdf8;">
                    👥 NEXT QUEUE
                </div>
                <span class="pill-badge" style="background: #38bdf8;"><?php echo count($waitingList); ?> WAITING</span>
            </div>

            <?php if (empty($waitingList)): ?>
                <div style="background: #132247; border: 1px dashed #1e3a8a; border-radius: 12px; padding: 3rem 1.5rem; text-align: center; color: #64748b;">
                    No customers currently waiting.
                </div>
            <?php else: ?>
                <?php foreach ($waitingList as $wq): ?>
                    <div class="waiting-list-item">
                        <div>
                            <span style="font-size: 1.6rem; font-weight: 900; color: #38bdf8; font-family: monospace;">
                                <?php echo e($wq['queue_number']); ?>
                            </span>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 700; color: #f8fafc; font-size: 0.95rem;"><?php echo e($wq['service_type']); ?></div>
                            <small style="color: #64748b;">Ticket issued: <?php echo date('h:i A', strtotime($wq['created_at'])); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- TV Footer Status Bar -->
    <div class="tv-footer">
        <div>
            <span class="pulse-dot"></span> Live Connection Active &bull;
            Counters Open: <strong><?php echo $activeCountersCount; ?></strong>
        </div>
        <div>
            Total Waiting: <strong><?php echo $totalWaiting; ?></strong> &bull; 
            Completed Today: <strong><?php echo $completedCount; ?></strong>
        </div>
        <div>
            Press <strong>F11</strong> for Fullscreen Display &bull; Auto-updates every 5s
        </div>
    </div>

    <script>
        // Real-time Clock
        function updateClock() {
            var now = new Date();
            var hours = now.getHours();
            var minutes = now.getMinutes();
            var seconds = now.getSeconds();
            var ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            var strTime = (hours < 10 ? '0' + hours : hours) + ':' + minutes + ':' + seconds + ' ' + ampm;
            var clockEl = document.getElementById('liveClock');
            if (clockEl) clockEl.innerText = strTime;
        }
        setInterval(updateClock, 1000);

        // Sound Notification Handling
        var soundEnabled = true;
        function toggleSound() {
            soundEnabled = !soundEnabled;
            var btn = document.getElementById('soundToggleBtn');
            btn.innerText = soundEnabled ? '🔊 Sound: Active' : '🔇 Sound: Muted';
        }

        // Voice Announcer using Web Speech API
        var recentCall = <?php echo $recentTicketJson; ?>;
        var lastAnnounced = sessionStorage.getItem('last_announced_ticket');

        if (recentCall && recentCall.queue_number && recentCall.queue_number !== lastAnnounced) {
            sessionStorage.setItem('last_announced_ticket', recentCall.queue_number);
            if ('speechSynthesis' in window) {
                // Short chime and announcement
                var msg = new SpeechSynthesisUtterance("Ticket number " + recentCall.queue_number.split('').join(' ') + ", please proceed to " + recentCall.counter_number);
                msg.rate = 0.9;
                window.speechSynthesis.speak(msg);
            }
        }

        // Fullscreen Toggle
        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(function(err) {});
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        }

        // Auto Refresh every 5 seconds
        setTimeout(function() {
            window.location.reload();
        }, 5000);
    </script>
</body>
</html>
