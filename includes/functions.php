<?php
// ===================================================
// Helper Functions & SVG Icon Library
// ===================================================

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

/**
 * Safe HTML escaping
 */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Set flash messages
 */
function setFlash($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

/**
 * Display flash alerts
 */
function displayFlash() {
    $output = '';
    if (isset($_SESSION['flash_success'])) {
        $output .= '<div class="alert alert-success">' . getIcon('check-circle') . ' <span>' . e($_SESSION['flash_success']) . '</span></div>';
        unset($_SESSION['flash_success']);
    }
    if (isset($_SESSION['flash_error'])) {
        $output .= '<div class="alert alert-error">' . getIcon('alert-triangle') . ' <span>' . e($_SESSION['flash_error']) . '</span></div>';
        unset($_SESSION['flash_error']);
    }
    if (isset($_SESSION['flash_info'])) {
        $output .= '<div class="alert alert-info">' . getIcon('info') . ' <span>' . e($_SESSION['flash_info']) . '</span></div>';
        unset($_SESSION['flash_info']);
    }
    return $output;
}

/**
 * Get all available services
 */
function getAllServices($pdo, $activeOnly = true) {
    $sql = "SELECT * FROM services" . ($activeOnly ? " WHERE status = 'active'" : "") . " ORDER BY id ASC";
    return $pdo->query($sql)->fetchAll();
}

/**
 * Get single service by ID
 */
function getServiceById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Standard Appointment Time Slots
 */
function getStandardTimeSlots() {
    return [
        '08:30 - 09:30',
        '09:30 - 10:30',
        '10:30 - 11:30',
        '11:30 - 12:30',
        '14:00 - 15:00',
        '15:00 - 16:00',
        '16:00 - 17:00'
    ];
}

/**
 * Calculate available slots for an appointment slot (Max 5 slots per window)
 */
function getAvailableSlotsCount($pdo, $serviceId, $date, $timeSlot, $maxSlots = 5) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM appointments 
        WHERE service_id = ? AND appointment_date = ? AND time_slot = ? AND status != 'cancelled'
    ");
    $stmt->execute([$serviceId, $date, $timeSlot]);
    $booked = (int)$stmt->fetchColumn();
    $left = $maxSlots - $booked;
    return max(0, $left);
}

/**
 * Generate Appointment Reference (e.g. APP-20261124-0001)
 */
function generateAppointmentRef($pdo) {
    $prefix = 'APP-' . date('Ymd') . '-';
    $stmt = $pdo->prepare("SELECT appointment_ref FROM appointments WHERE appointment_ref LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastRef = $stmt->fetchColumn();
    if ($lastRef) {
        $lastSeq = (int)substr($lastRef, -4);
        $nextSeq = $lastSeq + 1;
    } else {
        $nextSeq = 1;
    }
    return $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate Application Reference (e.g. IMP-20261124-1001)
 */
function generateApplicationRef($pdo) {
    $prefix = 'IMP-' . date('Ymd') . '-';
    $stmt = $pdo->prepare("SELECT application_ref FROM applications WHERE application_ref LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastRef = $stmt->fetchColumn();
    if ($lastRef) {
        $lastSeq = (int)substr($lastRef, -4);
        $nextSeq = $lastSeq + 1;
    } else {
        $nextSeq = 1001;
    }
    return $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate Next Queue Number
 * Supports Service Codes (e.g., A001, A002 for live demo, or series prefixes)
 */
function generateNextQueueNumber($pdo, $serviceType = 'General Service') {
    $prefixMap = [
        'Passport Renewal'      => 'A',
        'Passport Application'  => 'A',
        'Visa Application'      => 'B',
        'Work Permit'           => 'C',
        'Immigration Enquiry'   => 'P',
        'General Service'       => 'A',
        'Payment & Billing'     => 'B',
        'Customer Support'      => 'C',
        'Priority Service'      => 'P'
    ];
    
    $prefix = $prefixMap[$serviceType] ?? 'A';
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("SELECT queue_number FROM queues WHERE queue_number LIKE ? AND DATE(created_at) = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%', $today]);
    $lastQueue = $stmt->fetchColumn();

    if ($lastQueue) {
        $num = intval(substr($lastQueue, 1));
        $nextNum = $num + 1;
    } else {
        $nextNum = 1; // e.g. A001
    }

    return $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
}

/**
 * Calculate waiting time or human friendly time
 */
function formatDateTime($datetime) {
    if (!$datetime) return '-';
    return date('M d, Y h:i A', strtotime($datetime));
}

function formatTimeOnly($datetime) {
    if (!$datetime) return '-';
    return date('h:i A', strtotime($datetime));
}

/**
 * Return Status Badge HTML with modern status indicator dot
 */
function renderStatusBadge($status) {
    $badges = [
        'waiting'      => '<span class="badge badge-waiting"><span class="badge-dot dot-waiting"></span>' . getIcon('clock', 13) . ' Waiting</span>',
        'calling'      => '<span class="badge badge-calling"><span class="badge-dot dot-calling"></span>' . getIcon('bell', 13) . ' Calling</span>',
        'serving'      => '<span class="badge badge-serving"><span class="badge-dot dot-serving"></span>' . getIcon('play-circle', 13) . ' Serving</span>',
        'completed'    => '<span class="badge badge-completed"><span class="badge-dot dot-completed"></span>' . getIcon('check-circle', 13) . ' Completed</span>',
        'skipped'      => '<span class="badge badge-skipped"><span class="badge-dot dot-skipped"></span>' . getIcon('slash', 13) . ' Skipped</span>',
        'open'         => '<span class="badge badge-open"><span class="badge-dot dot-open"></span>' . getIcon('check', 13) . ' Open</span>',
        'closed'       => '<span class="badge badge-closed"><span class="badge-dot dot-closed"></span>' . getIcon('x', 13) . ' Closed</span>',
        'active'       => '<span class="badge badge-active"><span class="badge-dot dot-open"></span> Active</span>',
        'inactive'     => '<span class="badge badge-inactive"><span class="badge-dot dot-closed"></span> Inactive</span>',
        'confirmed'    => '<span class="badge badge-completed"><span class="badge-dot dot-completed"></span> Confirmed</span>',
        'pending'      => '<span class="badge badge-waiting"><span class="badge-dot dot-waiting"></span> Pending</span>',
        'cancelled'    => '<span class="badge badge-skipped"><span class="badge-dot dot-skipped"></span> Cancelled</span>',
        'approved'     => '<span class="badge badge-completed"><span class="badge-dot dot-completed"></span> Approved</span>',
        'submitted'    => '<span class="badge badge-calling"><span class="badge-dot dot-calling"></span> Submitted</span>',
        'under_review' => '<span class="badge badge-waiting"><span class="badge-dot dot-waiting"></span> Under Review</span>',
        'rejected'     => '<span class="badge badge-skipped"><span class="badge-dot dot-skipped"></span> Rejected</span>',
    ];
    return $badges[$status] ?? '<span class="badge">' . e(ucfirst($status)) . '</span>';
}

/**
 * Map Counter to its designated Service Department
 */
function getCounterDesignatedService($counterNumber, $counterName = '') {
    $num = trim($counterNumber);
    $name = strtolower($counterName);

    if (preg_match('/(^|\D)1($|\D)/', $num)) return 'Passport Renewal';
    if (preg_match('/(^|\D)2($|\D)/', $num)) return 'Visa Application';
    if (preg_match('/(^|\D)3($|\D)/', $num)) return 'Work Permit';
    if (preg_match('/(^|\D)4($|\D)/', $num)) return 'Immigration Enquiry';

    if (stripos($name, 'passport renewal') !== false || stripos($name, 'general') !== false) {
        return 'Passport Renewal';
    }
    if (stripos($name, 'visa') !== false || stripos($name, 'payment') !== false) {
        return 'Visa Application';
    }
    if (stripos($name, 'work permit') !== false || stripos($name, 'support') !== false) {
        return 'Work Permit';
    }
    if (stripos($name, 'enquiry') !== false || stripos($name, 'priority') !== false || stripos($name, 'express') !== false) {
        return 'Immigration Enquiry';
    }
    return null;
}

/**
 * Generate Smart Dynamic Mobile Tracker URL for QR Code
 */
function getMobileTrackerUrl($queueNumber) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    $protocol = $isHttps ? 'https://' : 'http://';
    
    $rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $hostParts = explode(':', $rawHost);
    $currentHost = $hostParts[0];

    if ($currentHost !== 'localhost' && $currentHost !== '127.0.0.1' && !filter_var($currentHost, FILTER_VALIDATE_IP)) {
        $targetHost = $rawHost;
    } else {
        $lanIp = gethostbyname(gethostname());
        if (!empty($lanIp) && $lanIp !== '127.0.0.1' && preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $lanIp)) {
            $port = (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != '80' && $_SERVER['SERVER_PORT'] != '443') ? ':' . $_SERVER['SERVER_PORT'] : '';
            $targetHost = $lanIp . $port;
        } else {
            $targetHost = $rawHost;
        }
    }

    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $basePath = ($scriptDir === '' || $scriptDir === '.') ? '' : $scriptDir;
    // ensure base path does not end with /admin, /staff or /user
    $basePath = preg_replace('/(\/admin|\/staff|\/user)$/', '', $basePath);

    return $protocol . $targetHost . $basePath . '/mobile_tracker.php?ticket=' . urlencode($queueNumber);
}

/**
 * Clean & Crisp SVG Icons for UI Elements
 */
function getIcon($name, $size = 18, $class = "icon") {
    $icons = [
        'dashboard'       => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>',
        'users'           => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'user'            => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'user-plus'       => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>',
        'desktop'         => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
        'counter'         => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="10" y1="4" x2="10" y2="10"/></svg>',
        'list'            => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
        'history'         => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>',
        'tv'              => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="15" rx="2" ry="2"/><polyline points="17 2 12 7 7 2"/></svg>',
        'ticket'          => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2z"/><path d="M3 12h2"/><path d="M19 12h2"/><path d="M9 5v14"/><line x1="14" y1="5" x2="14" y2="19"/></svg>',
        'search'          => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
        'log-in'          => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>',
        'log-out'         => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        'bell'            => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
        'plus'            => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        'play-circle'     => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>',
        'check-circle'    => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        'slash'           => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>',
        'clock'           => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        'alert-triangle'  => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        'info'            => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        'check'           => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
        'x'               => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        'printer'         => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>',
        'home'            => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'edit'            => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
        'trash'           => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
        'refresh'         => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>',
        'key'             => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-1.5 1.5L16 7l-1.5-1.5L13 7l-1.5-1.5L10 7 8.5 5.5 7 7l-2-2a5 5 0 1 0 7 7l8.5-8.5"/></svg>',
        'calendar'        => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        'file-text'       => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
        'check-square'    => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
        'beaker'          => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 3h15"/><path d="M6 3v5a6 6 0 0 0 1.5 4l4.5 5.5a2 2 0 0 0 3 0l4.5-5.5A6 6 0 0 0 21 8V3"/><line x1="6" y1="14" x2="18" y2="14"/></svg>',
        'briefcase'       => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>',
        'shield'          => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'smartphone'      => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>',
        'pause'           => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>',
        'eye'             => '<svg class="' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
    ];

    return $icons[$name] ?? '';
}
