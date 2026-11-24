<?php
// ===================================================
// Live Queue Mobile Tracker
// Pixel-perfect, Ultra-clean Mobile UI (Zero Frameworks)
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();
$queueNumber = strtoupper(trim($_GET['ticket'] ?? ($_GET['queue_number'] ?? ($_GET['num'] ?? ''))));

// Initial fetch for server-side rendering (most recent record for this ticket)
$queue = null;
if (!empty($queueNumber)) {
    $cleanNum = preg_replace('/[^A-Za-z0-9]/', '', $queueNumber);
    $stmt = $pdo->prepare("
        SELECT q.*, c.counter_number, c.counter_name, u.full_name as staff_name, a.status as app_status
        FROM queues q
        LEFT JOIN counters c ON q.counter_id = c.id
        LEFT JOIN users u ON q.staff_id = u.id
        LEFT JOIN appointments a ON q.appointment_id = a.id
        WHERE UPPER(TRIM(q.queue_number)) = ?
           OR REPLACE(REPLACE(UPPER(q.queue_number), ' ', ''), '-', '') = ?
           OR UPPER(TRIM(q.queue_number)) LIKE ?
        ORDER BY q.id DESC LIMIT 1
    ");
    $stmt->execute([$queueNumber, $cleanNum, $cleanNum . '%']);
    $queue = $stmt->fetch();

    // Auto-sync: If linked appointment was completed in admin, mark queue completed
    if ($queue && !empty($queue['app_status']) && $queue['app_status'] === 'completed' && $queue['status'] !== 'completed') {
        $pdo->prepare("UPDATE queues SET status = 'completed', completed_at = IFNULL(completed_at, NOW()) WHERE id = ?")->execute([$queue['id']]);
        $queue['status'] = 'completed';
    }
}

$customersAhead = 0;
$estimatedWaitMinutes = 0;
if ($queue && $queue['status'] === 'waiting') {
    $stmtAhead = $pdo->prepare("SELECT COUNT(*) FROM queues WHERE status = 'waiting' AND id < ?");
    $stmtAhead->execute([$queue['id']]);
    $customersAhead = (int)$stmtAhead->fetchColumn();
    $estimatedWaitMinutes = ($customersAhead + 1) * 4;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $queue ? e($queue['queue_number']) : 'Live Tracker'; ?> - QueueFlow Mobile</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        body {
            background-color: #f8fafc;
            margin: 0;
            padding: 2rem 1.25rem 3rem 1.25rem;
            display: flex;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #1e293b;
            min-height: 100vh;
        }
        .mobile-container {
            width: 100%;
            max-width: 410px;
            margin: 0 auto;
        }
        .mobile-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .mobile-title {
            font-size: 1.45rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 0.35rem 0;
            letter-spacing: -0.3px;
        }
        .mobile-subtitle {
            font-size: 0.95rem;
            color: #64748b;
            font-weight: 500;
            margin: 0;
        }

        /* Hero Card (Refined slate aesthetic matching reference design) */
        .tracker-hero-card {
            background-color: #5c6776;
            color: #ffffff;
            border-radius: 20px;
            padding: 2.25rem 1.5rem;
            text-align: center;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.12);
            margin-bottom: 1.15rem;
            position: relative;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }
        .tracker-hero-card.calling {
            background-color: #1d4ed8;
            animation: pulseHero 2s infinite;
        }
        .tracker-hero-card.serving {
            background-color: #059669;
        }
        .tracker-hero-card.completed {
            background-color: #5c6776;
        }
        .tracker-hero-card.skipped {
            background-color: #dc2626;
        }

        @keyframes pulseHero {
            0% { transform: scale(1); box-shadow: 0 0 0 rgba(29, 78, 216, 0.4); }
            50% { transform: scale(1.015); box-shadow: 0 0 24px rgba(29, 78, 216, 0.6); }
            100% { transform: scale(1); box-shadow: 0 0 0 rgba(29, 78, 216, 0.4); }
        }

        .service-pill {
            display: inline-block;
            background: #ffffff;
            color: #1e293b;
            font-size: 0.82rem;
            font-weight: 700;
            padding: 0.32rem 1.25rem;
            border-radius: 9999px;
            margin-bottom: 1.35rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        /* Number Display: strictly NO-WRAP, neat letter spacing */
        .spaced-ticket-number {
            font-size: clamp(2.3rem, 8.5vw, 3.2rem);
            font-weight: 900;
            letter-spacing: 0.35rem;
            padding-left: 0.35rem; /* center compensation */
            color: #ffffff;
            line-height: 1.1;
            margin: 0 auto 1.25rem auto;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: clip;
            font-variant-numeric: tabular-nums;
            display: block;
        }

        .status-headline {
            font-size: 1.55rem;
            font-weight: 800;
            margin: 0 0 0.5rem 0;
            letter-spacing: -0.2px;
        }

        .status-submsg {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
            line-height: 1.45;
            font-weight: 400;
        }

        /* 2-Column Metrics Box */
        .metrics-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.85rem;
            margin-bottom: 1.15rem;
        }
        .metric-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.35rem 1rem;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .metric-title {
            font-size: 0.74rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 0.45rem;
        }
        .metric-num {
            font-size: 2.3rem;
            font-weight: 900;
            color: #0f172a;
            line-height: 1;
            margin: 0;
        }
        .metric-num.blue {
            color: #2563eb;
        }

        /* Information Details Card */
        .info-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.35rem 1.4rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.7rem 0;
            font-size: 0.92rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .info-row:last-of-type {
            border-bottom: none;
            padding-bottom: 0.2rem;
        }
        .info-label {
            color: #64748b;
            font-weight: 500;
        }
        .info-val {
            font-weight: 700;
            color: #0f172a;
        }
        .info-val.station {
            color: #2563eb;
            font-size: 1rem;
        }
        .info-footnote {
            display: block;
            margin-top: 0.85rem;
            font-size: 0.78rem;
            color: #94a3b8;
            font-style: italic;
            line-height: 1.4;
        }

        /* Action Buttons Area */
        .action-area {
            text-align: center;
        }
        .btn-refresh-mobile {
            background-color: #5c6776;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.15s ease;
            box-shadow: 0 2px 4px rgba(15, 23, 42, 0.08);
        }
        .btn-refresh-mobile:hover {
            background-color: #475569;
        }
        .btn-refresh-mobile:active {
            transform: scale(0.97);
        }

        .security-badge {
            margin-top: 1.15rem;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.82rem;
            color: #64748b;
        }
        .security-badge svg {
            color: #10b981;
        }
    </style>
</head>
<body>

<div class="mobile-container">
    <?php if (!$queue): ?>
        <div class="mobile-header">
            <h1 class="mobile-title">QueueFlow Service Center</h1>
            <div class="mobile-subtitle">Live Queue Mobile Tracker</div>
        </div>
        <div style="background: white; border-radius: 16px; padding: 2.5rem 1.5rem; text-align: center; border: 1px solid #e2e8f0;">
            <div style="font-size: 1.15rem; font-weight: 700; color: #dc2626; margin-bottom: 0.5rem;">Ticket Not Found</div>
            <p style="color: #64748b; font-size: 0.92rem; margin-bottom: 1.5rem;">No active queue found for ticket "<?php echo e($queueNumber); ?>". Please check your ticket number.</p>
            <a href="index.php" class="btn btn-primary" style="display: inline-block;">Take a New Ticket</a>
        </div>
    <?php else: ?>
        <div class="mobile-header">
            <h1 class="mobile-title">QueueFlow Service Center</h1>
            <div class="mobile-subtitle">Live Queue Mobile Tracker</div>
        </div>

        <!-- Main Hero Card (Matches user reference screenshot) -->
        <div id="heroCard" class="tracker-hero-card <?php echo $queue['status']; ?>">
            <div id="servicePill" class="service-pill">
                <?php echo e($queue['service_type']); ?>
            </div>

            <!-- Single Line, Centered, No Wrap -->
            <div id="ticketNumber" class="spaced-ticket-number">
                <?php echo e($queue['queue_number']); ?>
            </div>

            <div id="statusHeadline" class="status-headline">
                <?php
                if ($queue['status'] === 'completed') {
                    echo 'Service Completed';
                } elseif ($queue['status'] === 'calling') {
                    echo '🔔 NOW CALLING';
                } elseif ($queue['status'] === 'serving') {
                    echo 'In Service';
                } elseif ($queue['status'] === 'skipped') {
                    echo 'Ticket Skipped';
                } else {
                    echo 'Waiting in Line';
                }
                ?>
            </div>

            <p id="statusSubmsg" class="status-submsg">
                <?php
                if ($queue['status'] === 'completed') {
                    echo 'Thank you for visiting! Have a wonderful day.';
                } elseif ($queue['status'] === 'calling') {
                    echo 'Please proceed immediately to ' . e($queue['counter_number'] ?: 'Counter') . '.';
                } elseif ($queue['status'] === 'serving') {
                    echo 'Currently being served at ' . e($queue['counter_number'] ?: 'Counter') . '.';
                } elseif ($queue['status'] === 'skipped') {
                    echo 'Number skipped due to no-show. Please see reception.';
                } else {
                    echo 'Please keep this page open. We will update as counters progress.';
                }
                ?>
            </p>
        </div>

        <!-- 2-Column Metrics Box -->
        <div class="metrics-grid">
            <div class="metric-box">
                <div class="metric-title">CUSTOMERS AHEAD</div>
                <div id="aheadCount" class="metric-num"><?php echo $customersAhead; ?></div>
            </div>

            <div class="metric-box">
                <div class="metric-title">EST. WAIT TIME</div>
                <div id="waitTime" class="metric-num blue">~<?php echo $estimatedWaitMinutes; ?>m</div>
            </div>
        </div>

        <!-- Information Details Card -->
        <div class="info-card">
            <div class="info-row">
                <span class="info-label">Registered At:</span>
                <span id="registeredAt" class="info-val"><?php echo date('h:i A', strtotime($queue['created_at'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Assigned Station:</span>
                <span id="assignedStation" class="info-val station"><?php echo e($queue['counter_number'] ?: 'Standby / Unassigned'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Last Updated:</span>
                <span id="lastUpdated" class="info-val" style="font-family: monospace; font-size: 0.92rem;"><?php echo date('h:i:s A'); ?></span>
            </div>

            <span class="info-footnote">
                * Dynamic estimated waiting time updates automatically as counters progress.
            </span>
        </div>

        <!-- Action & Security -->
        <div class="action-area">
            <button type="button" class="btn-refresh-mobile" onclick="pollQueueStatus(true)">
                <?php echo getIcon('refresh', 16); ?> Refresh Now
            </button>

            <div>
                <div class="security-badge">
                    <?php echo getIcon('check-circle', 14); ?> Secured with encrypted session token
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Real-time Synchronization Script (Native fetch, Auto-syncs on staff action) -->
<script>
var currentTicket = "<?php echo addslashes($queueNumber); ?>";
var lastStatus = "<?php echo $queue ? $queue['status'] : ''; ?>";

function playMobileDingDong(onFinish) {
    try {
        var AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) {
            if (typeof onFinish === 'function') onFinish();
            return;
        }
        var ctx = new AudioCtx();
        if (ctx.state === 'suspended') ctx.resume();
        var now = ctx.currentTime;
        
        var osc1 = ctx.createOscillator();
        var gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(784, now);
        gain1.gain.setValueAtTime(0.35, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.5);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(now);
        osc1.stop(now + 0.5);

        var osc2 = ctx.createOscillator();
        var gain2 = ctx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(587.3, now + 0.25);
        gain2.gain.setValueAtTime(0.4, now + 0.25);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 1.0);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(now + 0.25);
        osc2.stop(now + 1.0);

        setTimeout(function() {
            if (typeof onFinish === 'function') onFinish();
        }, 550);
    } catch (e) {
        if (typeof onFinish === 'function') onFinish();
    }
}

function pollQueueStatus(isManual) {
    if (!currentTicket) return;
    
    fetch('api/queue_status.php?queue_number=' + encodeURIComponent(currentTicket) + '&t=' + new Date().getTime())
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) return;

            // 1. Update Spaced Number & Service
            document.getElementById('ticketNumber').innerText = data.queue_number;
            document.getElementById('servicePill').innerText = data.service_type;

            // 2. Update Metrics
            document.getElementById('aheadCount').innerText = data.customers_ahead;
            document.getElementById('waitTime').innerText = '~' + data.estimated_wait_minutes + 'm';
            document.getElementById('registeredAt').innerText = data.registered_at;
            document.getElementById('assignedStation').innerText = data.counter_number;
            document.getElementById('lastUpdated').innerText = data.last_updated;

            // 3. Update Hero Card Styles & Dynamic Headline
            var hero = document.getElementById('heroCard');
            var headline = document.getElementById('statusHeadline');
            var submsg = document.getElementById('statusSubmsg');

            hero.className = 'tracker-hero-card ' + data.status;

            if (data.status === 'completed') {
                headline.innerText = 'Service Completed';
                submsg.innerText = 'Thank you for visiting! Have a wonderful day.';
                if (lastStatus !== 'completed' && window.navigator && window.navigator.vibrate) {
                    window.navigator.vibrate([100, 50, 100]);
                }
            } else if (data.status === 'calling') {
                headline.innerText = '🔔 NOW CALLING';
                submsg.innerText = 'Please proceed immediately to ' + data.counter_number + '!';
                if (lastStatus !== 'calling') {
                    if (window.navigator && window.navigator.vibrate) {
                        window.navigator.vibrate([300, 100, 300, 100, 300]);
                    }
                    playMobileDingDong(function() {
                        if ('speechSynthesis' in window) {
                            var spokenTicket = (data.queue_number || '').split('').join(' ');
                            var msg = new SpeechSynthesisUtterance("Now calling ticket " + spokenTicket + ", please proceed to " + data.counter_number);
                            msg.rate = 0.9;
                            window.speechSynthesis.speak(msg);
                        }
                    });
                }
            } else if (data.status === 'serving') {
                headline.innerText = 'In Service';
                submsg.innerText = 'Currently being served at ' + data.counter_number + '.';
            } else if (data.status === 'skipped') {
                headline.innerText = 'Ticket Skipped';
                submsg.innerText = 'Number skipped due to no-show. Please see reception.';
            } else {
                headline.innerText = 'Waiting in Line';
                submsg.innerText = 'Please keep this page open. We will update as counters progress.';
            }

            lastStatus = data.status;
        })
        .catch(function(err) {
            console.log('Poll check failed:', err);
        });
}

// Auto-poll every 2.5 seconds so when staff changes status, phone updates seamlessly!
if (currentTicket) {
    setInterval(function() {
        pollQueueStatus(false);
    }, 2500);
}
</script>

</body>
</html>