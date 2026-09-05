<?php
function e($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function formatMoney($amount) {
    $symbol = (function_exists('getSetting') ? getSetting('currency_symbol', '') : '');
    if ($symbol === '' && defined('CURRENCY_SYMBOL')) $symbol = CURRENCY_SYMBOL;
    if ($symbol === '') $symbol = 'JOD';
    return $symbol . ' ' . number_format((float)$amount, 2);
}

function formatDate($date, $format = null) {
    if (!$date || $date === '0000-00-00') return '';
    $f = $format ?? (defined('DATE_FORMAT') ? DATE_FORMAT : 'd/m/Y');
    $ts = is_numeric($date) ? $date : strtotime($date);
    return date($f, $ts);
}

function formatDateTime($datetime, $format = null) {
    if (!$datetime || $datetime === '0000-00-00 00:00:00') return '';
    $f = $format ?? (defined('DATETIME_FORMAT') ? DATETIME_FORMAT : 'd/m/Y H:i');
    $ts = is_numeric($datetime) ? $datetime : strtotime($datetime);
    return date($f, $ts);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function currentUser() {
    global $conn;
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null && $conn) {
        $stmt = $conn->prepare('SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    return $user;
}

function hasPermission($permName) {
    global $conn;
    if (!isLoggedIn()) return false;
    $user = currentUser();
    if (!$user) return false;
    if ($user['role_name'] === 'Administrator') return true;
    static $perms = null;
    if ($perms === null && $conn) {
        $stmt = $conn->prepare('SELECT p.permission_name FROM permissions p
            INNER JOIN role_permissions rp ON rp.permission_id = p.id
            WHERE rp.role_id = ?');
        $stmt->execute([$user['role_id']]);
        $perms = array_column($stmt->fetchAll(), 'permission_name');
    }
    return is_array($perms) && in_array($permName, $perms);
}

function requirePermission($permName, $redirect = true) {
    if (!isLoggedIn()) {
        redirect(SITE_URL . '/login.php');
    }
    if (!hasPermission($permName)) {
        if ($redirect) {
            $_SESSION['error'] = 'You do not have permission to access this page.';
            redirect(SITE_URL . '/index.php');
        }
        return false;
    }
    return true;
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function flashMessages() {
    $flash = getFlash();
    if (!$flash) return '';
    $class = $flash['type'] === 'success' ? 'alert-success' : ($flash['type'] === 'error' ? 'alert-danger' : 'alert-info');
    return '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        ' . e($flash['message']) . '
    </div>';
}

function sanitizePhone($phone, $countryCode = '966') {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strpos($phone, '00') === 0) $phone = substr($phone, 2);
    elseif (strpos($phone, '+') === 0) $phone = substr($phone, 1);
    if (strlen($phone) <= 10 && strpos($phone, '0') === 0) $phone = $countryCode . substr($phone, 1);
    if (strlen($phone) <= 9) $phone = $countryCode . $phone;
    return $phone;
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

function generateBookingNumber() {
    global $conn;
    $prefix = defined('BOOKING_PREFIX') ? BOOKING_PREFIX : 'BK';
    $year = date('Y');
    $month = date('m');
    $sql = "SELECT MAX(CAST(SUBSTRING(booking_number, LENGTH(?) + 7) AS UNSIGNED)) as max_num
            FROM bookings WHERE booking_number LIKE ?";
    $stmt = $conn->prepare($sql);
    $like = $prefix . $year . $month . '%';
    $stmt->execute([$prefix, $like]);
    $row = $stmt->fetch();
    $num = intval($row['max_num']) + 1;
    return $prefix . $year . $month . str_pad($num, 4, '0', STR_PAD_LEFT);
}

function loadSystemSettings() {
    global $conn;
    if (!$conn) return;
    static $loaded = false;
    if ($loaded) return;
    try {
        $stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = $stmt->fetchAll();
        foreach ($settings as $s) {
            if (!defined('SYS_' . strtoupper($s['setting_key']))) {
                define('SYS_' . strtoupper($s['setting_key']), $s['setting_value']);
            }
        }
        $loaded = true;
    } catch (Exception $e) {}
}

function getSetting($key, $default = '') {
    $c = 'SYS_' . strtoupper($key);
    return defined($c) ? constant($c) : $default;
}

function auditLog($action, $entityType, $entityId = null, $oldValue = null, $newValue = null) {
    global $conn;
    if (!isLoggedIn()) return;
    $userId = $_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_value, new_value, ip_address, user_agent) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $userId, $action, $entityType, $entityId,
        $oldValue ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : null,
        $newValue ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null,
        $ip, $ua
    ]);
}

function getBookedQuantity($itemTypeId, $dateFrom, $dateTo, $excludeBookingId = null) {
    global $conn;
    $reserveStatuses = ['Quotation', 'Confirmed', 'Change Requested', 'Event Completed', 'Closed'];
    $placeholders = implode(',', array_fill(0, count($reserveStatuses), '?'));
    $sql = "SELECT COALESCE(SUM(bi.quantity), 0) as booked
            FROM booking_items bi
            INNER JOIN bookings b ON bi.booking_id = b.id
            WHERE bi.item_type_id = ?
            AND b.status IN ($placeholders)
            AND b.date_from <= ? AND b.date_to >= ?
            AND b.status != 'Canceled'";
    $params = array_merge([$itemTypeId], $reserveStatuses, [$dateTo, $dateFrom]);
    if ($excludeBookingId) {
        $sql .= " AND b.id != ?";
        $params[] = $excludeBookingId;
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return (int)($row['booked'] ?? 0);
}

function getAvailableQuantity($itemTypeId, $dateFrom, $dateTo, $excludeBookingId = null) {
    global $conn;
    $stmt = $conn->prepare("SELECT quantity FROM item_types WHERE id = ?");
    $stmt->execute([$itemTypeId]);
    $row = $stmt->fetch();
    $total = (int)($row['quantity'] ?? 0);
    $booked = getBookedQuantity($itemTypeId, $dateFrom, $dateTo, $excludeBookingId);
    return max(0, $total - $booked);
}

function getTotalQuantity($itemTypeId) {
    global $conn;
    $stmt = $conn->prepare("SELECT quantity FROM item_types WHERE id = ?");
    $stmt->execute([$itemTypeId]);
    $row = $stmt->fetch();
    return (int)($row['quantity'] ?? 0);
}

function getBookingCollected($bookingId) { return getBookingCollectedAmount($bookingId); }
function getBookingPending($bookingId)   { return getBookingPendingAmount($bookingId); }

function getBookingCollectedAmount($bookingId) {
    global $conn;
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    $row = $stmt->fetch();
    return (float)($row['total'] ?? 0);
}

function getBookingPendingAmount($bookingId) {
    global $conn;
    $stmt = $conn->prepare("SELECT quoted_amount FROM bookings WHERE id = ?");
    $stmt->execute([$bookingId]);
    $row = $stmt->fetch();
    $quoted = (float)($row['quoted_amount'] ?? 0);
    $collected = getBookingCollectedAmount($bookingId);
    return max(0, $quoted - $collected);
}

function updateBookingPaymentStatus($bookingId) {
    global $conn;
    $stmt = $conn->prepare("SELECT quoted_amount FROM bookings WHERE id = ?");
    $stmt->execute([$bookingId]);
    $b = $stmt->fetch();
    if (!$b) return;
    $collected = getBookingCollectedAmount($bookingId);
    $quoted = (float)$b['quoted_amount'];
    if ($quoted <= 0) $status = 'Not Collected';
    elseif ($collected <= 0) $status = 'Not Collected';
    elseif ($collected >= $quoted) $status = 'Fully Collected';
    else $status = 'Partially Collected';
    $stmt = $conn->prepare("UPDATE bookings SET payment_status = ? WHERE id = ?");
    $stmt->execute([$status, $bookingId]);
}

function datesOverlap($startA, $endA, $startB, $endB) {
    return (strtotime($startA) <= strtotime($endB)) && (strtotime($endA) >= strtotime($startB));
}

function calcPaymentStatus($quoted, $collected, $bookingStatus = null) {
    if ($bookingStatus === 'Canceled') return 'Canceled';
    if ($quoted <= 0 || $collected <= 0) return 'Not Collected';
    if ($collected >= $quoted) return 'Fully Collected';
    return 'Partially Collected';
}

function buildWhatsAppMessage($booking, $clientName = '', $clientPhone = '') {
    $companyName = $booking['company_name'] ?? getSetting('company_name', 'DJ RAK');
    $currency = $booking['currency'] ?? getSetting('currency_symbol', 'SAR');
    $lines = [];
    if (!empty($clientName)) $lines[] = "Hello $clientName,";
    $lines[] = "";
    $lines[] = "Here is your DJ equipment rental quotation from $companyName:";
    $lines[] = "";
    if (!empty($booking['booking_number'])) $lines[] = "Booking #{$booking['booking_number']}";
    if (!empty($booking['event_date_display'])) $lines[] = "Date: {$booking['event_date_display']}";
    if (!empty($booking['location'])) $lines[] = "Location: {$booking['location']}";
    $lines[] = "";
    $lines[] = "Equipment:";
    $items = $booking['items'] ?? [];
    foreach ($items as $it) {
        $qty = $it['quantity'] ?? 1;
        $name = $it['item_type_name'] ?? $it['item_name'] ?? 'Item';
        // IMPORTANT: never show per-item / per-unit rate to customer (even qty=1 line total
        // looks like a default catalog rate, and mismatches w/ custom "Total Quoted" manual overrides).
        // Only the single "Total Quoted" figure is shown below.
        $lines[] = "- {$qty} × {$name}";
    }
    $lines[] = "";
    if (!empty($booking['quoted_amount'])) $lines[] = "*Total Quoted: {$currency} " . number_format((float)$booking['quoted_amount'], 2) . "*";
    if (!empty($booking['customer_confirm_url'])) {
        $lines[] = "";
        $lines[] = "Please confirm your booking by clicking the link below:";
        $lines[] = $booking['customer_confirm_url'];
    }
    $lines[] = "";
    $lines[] = "Thank you!";
    return implode("\n", $lines);
}

function buildWhatsAppReminder($booking, $clientName, $collected, $pending, $currency = 'SAR') {
    $companyName = getSetting('company_name', 'DJ RAK');
    $lines = ["Hello $clientName,", "", "This is a friendly reminder from $companyName about your booking {$booking['booking_number']}", ""];
    if (!empty($booking['event_date_display'])) $lines[] = "Event Date: {$booking['event_date_display']}";
    $lines[] = "Quoted: {$currency} " . number_format($booking['quoted_amount'] ?? 0, 2);
    $lines[] = "Collected: {$currency} " . number_format($collected, 2);
    $lines[] = "*Pending: {$currency} " . number_format($pending, 2) . "*";
    $lines[] = "";
    $lines[] = "Please arrange payment at your earliest convenience.\nThank you!";
    return implode("\n", $lines);
}

/* ======= I18N HELPERS ======= */
function getDefaultLang() {
    return 'en';
}
function getActiveLang() {
    $default = getDefaultLang();
    if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], ['en', 'ar'], true)) {
        return $_SESSION['lang'];
    }
    $allowed = ['en', 'ar'];
    $browser = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if ($browser) {
        $primary = strtolower(substr($browser, 0, 2));
        if (in_array($primary, $allowed, true)) return $primary;
    }
    return $default;
}
function setActiveLang($code) {
    if (in_array($code, ['en', 'ar'], true)) {
        $_SESSION['lang'] = $code;
    }
}
function isRtlLang($lang = null) {
    $l = $lang ?? getActiveLang();
    return $l === 'ar';
}

$GLOBALS['__LANG'] = null;
function loadLangDictionary($code) {
    $allowed = ['en', 'ar'];
    if (!in_array($code, $allowed, true)) $code = 'en';
    $path = SITE_PATH . '/lang/' . $code . '.php';
    if (!file_exists($path)) return [];
    $dict = include $path;
    return is_array($dict) ? $dict : [];
}
function initI18n() {
    $active = getActiveLang();
    $GLOBALS['__LANG_CODE'] = $active;
    $GLOBALS['__LANG_DICT'] = array_merge(loadLangDictionary('en'), loadLangDictionary($active));
    if ($active === 'ar') {
        setlocale(LC_TIME, ['ar_SA.UTF-8', 'ar_SA', 'Arabic_Saudi Arabia.1256']);
    } else {
        setlocale(LC_TIME, ['en_US.UTF-8', 'en_US', 'English_United States.1252']);
    }
}
function t($key, $params = []) {
    $dict = $GLOBALS['__LANG_DICT'] ?? [];
    $value = $dict[$key] ?? $key;
    if (is_array($value)) return $value;
    if (!empty($params)) {
        $replaced = [];
        foreach ($params as $i => $p) {
            if (is_int($i)) {
                $replaced['%' . ($i + 1) . '$s'] = $p;
                $replaced['%s'] = $p;
            } else {
                $replaced['{' . $i . '}'] = $p;
            }
        }
        $value = strtr($value, $replaced);
        if (strpos($value, '%s') !== false || strpos($value, '%1$s') !== false) {
            $value = vsprintf($value, array_values($params));
        }
    }
    return $value;
}
function te($key, $params = []) {
    return e(t($key, $params));
}
function t_month($idx0) {
    $months = t('cal.months');
    if (is_array($months)) {
        $i = intval($idx0);
        if ($i < 1) $i += 12;
        if ($i > 12) $i -= 12;
        return $months[$i - 1] ?? '';
    }
    return '';
}
function t_day($idx0) {
    $days = t('cal.days_short');
    if (is_array($days)) return $days[intval($idx0) % 7] ?? '';
    return '';
}
function t_booking_status($status) {
    $map = [
        'Draft' => 'status.draft', 'Quotation' => 'status.quotation',
        'Confirmed' => 'status.confirmed', 'Change Requested' => 'status.change_requested',
        'Event Completed' => 'status.event_completed', 'Closed' => 'status.closed',
        'Canceled' => 'status.canceled',
    ];
    $key = $map[$status] ?? null;
    return $key ? t($key) : $status;
}
function t_payment_status($status) {
    $map = [
        'Not Collected' => 'pay.not_collected',
        'Partially Collected' => 'pay.partially_collected',
        'Fully Collected' => 'pay.fully_collected',
        'Canceled' => 'status.canceled',
    ];
    $key = $map[$status] ?? null;
    return $key ? t($key) : $status;
}
function t_payment_method($m) {
    $map = ['Cash'=>'pm.cash','Transfer'=>'pm.transfer','CliQ'=>'pm.cliq','Bank Transfer'=>'pm.transfer','Other'=>'pm.other'];
    $key = $map[$m] ?? null;
    return $key ? t($key) : $m;
}
function t_inv_status($s) {
    $map = ['Active'=>'inv.status_active','Inactive'=>'inv.status_inactive','Rented'=>'inv.status_rented','Maintenance'=>'inv.status_maintenance'];
    $key = $map[$s] ?? null;
    return $key ? t($key) : $s;
}
function t_inv_condition($c) {
    $map = ['New'=>'inv.condition_new','Good'=>'inv.condition_good','Fair'=>'inv.condition_fair','Poor'=>'inv.condition_poor'];
    $key = $map[$c] ?? null;
    return $key ? t($key) : $c;
}
function buildWhatsAppMessageI18n($booking, $clientName = '', $clientPhone = '') {
    $companyName = $booking['company_name'] ?? getSetting('company_name', 'DJ RAK');
    $currency = $booking['currency'] ?? getSetting('currency_symbol', t('system.currency'));
    $lines = [];
    if (!empty($clientName)) $lines[] = t('wa.hello') . " $clientName,";
    $lines[] = "";
    $lines[] = t('wa.quotes_intro') . " $companyName:";
    $lines[] = "";
    if (!empty($booking['booking_number'])) $lines[] = t('cf.booking_number') . " #{$booking['booking_number']}";
    if (!empty($booking['event_date_display'])) $lines[] = t('wa.date_label') . ": {$booking['event_date_display']}";
    if (!empty($booking['location'])) $lines[] = t('wa.location_label') . ": {$booking['location']}";
    $lines[] = "";
    $lines[] = t('wa.equipment_label') . ":";
    $items = $booking['items'] ?? [];
    foreach ($items as $it) {
        $qty = $it['quantity'] ?? 1;
        $name = $it['item_type_name'] ?? $it['item_name'] ?? 'Item';
        // IMPORTANT: never show per-item / per-unit rate to customer (even qty=1 line totals
        // can look like default catalog rates, and cause confusion w/ manual "Total Quoted" overrides).
        // Only the single Total Quoted figure below carries the price.
        $lines[] = "- {$qty} × {$name}";
    }
    $lines[] = "";
    if (!empty($booking['quoted_amount'])) {
        $lines[] = "*" . t('wa.total_quoted_label') . ": {$currency} " . number_format((float)$booking['quoted_amount'], 2) . "*";
    }
    if (!empty($booking['customer_confirm_url'])) {
        $lines[] = "";
        $lines[] = t('wa.confirm_prompt');
        $lines[] = $booking['customer_confirm_url'];
    }
    $lines[] = "";
    $lines[] = t('wa.thanks');
    return implode("\n", $lines);
}
function buildWhatsAppReminderI18n($booking, $clientName, $collected, $pending, $currency = null) {
    if (!$currency) $currency = getSetting('currency_symbol', t('system.currency'));
    $companyName = getSetting('company_name', 'DJ RAK');
    $intro = vsprintf(t('wa.reminder_intro'), [$companyName, $booking['booking_number'] ?? '']);
    $lines = [t('wa.hello') . " $clientName,", "", $intro, ""];
    if (!empty($booking['event_date_display'])) $lines[] = t('wa.event_date') . ": {$booking['event_date_display']}";
    $lines[] = t('wa.quoted') . ": {$currency} " . number_format($booking['quoted_amount'] ?? 0, 2);
    $lines[] = t('wa.collected') . ": {$currency} " . number_format($collected, 2);
    $lines[] = "*" . t('wa.pending_bold') . ": {$currency} " . number_format($pending, 2) . "*";
    $lines[] = "";
    $lines[] = t('wa.arrange_payment') . "\n" . t('wa.thanks');
    return implode("\n", $lines);
}
function buildPendingBalanceWhatsAppI18n($client, $pendingAmount) {
    $intro = vsprintf(t('wa.pending_msg_intro'), [$client['name'] ?? 'Client']);
    return $intro . "\n*" . formatMoney($pendingAmount) . "*\n" . t('wa.pending_msg_thanks');
}

/* ========================================================================
 * OWASP Top 10 — Core Security Helpers
 * ====================================================================== */

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken(32);
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function validate_csrf($redirect = true) {
    $sent = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if ($expected === '' || !is_string($sent) || $sent === '' || !hash_equals($expected, $sent)) {
        auditSecurity('invalid_csrf', [
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'uri'    => $_SERVER['REQUEST_URI'] ?? '',
        ]);
        if ($redirect) {
            if (!headers_sent()) {
                http_response_code(403);
            }
            setFlash('error', t('err.csrf_invalid') ?: 'Security check failed. Please refresh and try again.');
            $back = $_SERVER['HTTP_REFERER'] ?? (SITE_URL . '/index.php');
            $backHost = parse_url($back, PHP_URL_HOST);
            $siteHost = parse_url(SITE_URL, PHP_URL_HOST);
            if ($backHost !== null && $siteHost !== null && strcasecmp($backHost, $siteHost) !== 0) {
                $back = SITE_URL . '/index.php';
            }
            redirect($back);
        }
        return false;
    }
    return true;
}

function emit_security_headers() {
    if (php_sapi_name() === 'cli' || headers_sent()) return;

    $nonce = bin2hex(random_bytes(16));
    $GLOBALS['CSP_NONCE'] = $nonce;

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    $isHttps =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (strpos(SITE_URL, 'https://') === 0);
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    $csp = [
        "default-src 'self'",
        "script-src 'self' 'nonce-{$nonce}' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com cdnjs.cloudflare.com",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com cdnjs.cloudflare.com",
        "img-src 'self' data: https:",
        "font-src 'self' https://cdnjs.cloudflare.com cdnjs.cloudflare.com data:",
        "connect-src 'self'",
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "object-src 'none'",
    ];
    header('Content-Security-Policy: ' . implode('; ', $csp));
}

function csp_nonce() {
    if (empty($GLOBALS['CSP_NONCE'])) {
        $GLOBALS['CSP_NONCE'] = bin2hex(random_bytes(16));
    }
    return $GLOBALS['CSP_NONCE'];
}

function auditSecurity($action, $detail = []) {
    global $conn;
    if (!$conn) return;
    $userId = (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) ? $_SESSION['user_id'] : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $newVal = !empty($detail) ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null;
    try {
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_value, ip_address, user_agent, created_at) VALUES (?, ?, 'SecurityEvent', NULL, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $newVal, $ip, $ua]);
    } catch (Exception $e) {
    }
}

function ensure_login_attempts_table() {
    global $conn;
    if (!$conn) return;
    static $ensured = false;
    if ($ensured) return;
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            username VARCHAR(150) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempts INT(11) NOT NULL DEFAULT 1,
            last_attempt_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (username, ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ensured = true;
    } catch (Exception $e) {}
}

function record_failed_login($username) {
    global $conn;
    ensure_login_attempts_table();
    if (!$conn || $username === '') return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    try {
        $stmt = $conn->prepare("INSERT INTO login_attempts (username, ip_address, attempts, last_attempt_at) VALUES (?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt_at = NOW()");
        $stmt->execute([$username, $ip]);
    } catch (Exception $e) {}
    auditSecurity('failed_login', ['username' => $username]);
}

function reset_login_attempts($username) {
    global $conn;
    ensure_login_attempts_table();
    if (!$conn || $username === '') return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    try {
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE username = ? AND ip_address = ?");
        $stmt->execute([$username, $ip]);
    } catch (Exception $e) {}
}

function enforce_login_throttle($username) {
    global $conn;
    ensure_login_attempts_table();
    if (!$conn || $username === '') return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $attempts = 0;
    try {
        $stmt = $conn->prepare("SELECT attempts FROM login_attempts WHERE username = ? AND ip_address = ?");
        $stmt->execute([$username, $ip]);
        $row = $stmt->fetch();
        $attempts = (int)($row['attempts'] ?? 0);
    } catch (Exception $e) {}
    if ($attempts > 3) {
        $exponent = max(0, $attempts - 3);
        $delay = min(15, pow(2, $exponent));
        sleep((int)$delay);
    }
}

function destroy_session() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    @session_start();
    session_regenerate_id(true);
}

function enforce_session_timeout() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) return;
    $timeout = defined('SESSION_TIMEOUT') ? (int)SESSION_TIMEOUT : 3600;
    $now = time();
    $loginAge = isset($_SESSION['login_time']) ? ($now - (int)$_SESSION['login_time']) : 0;
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = $now;
    }
    $idleAge = $now - (int)$_SESSION['last_activity'];
    if ($loginAge > $timeout || $idleAge > $timeout) {
        auditSecurity('session_timeout');
        destroy_session();
        setFlash('error', t('err.session_timeout') ?: 'Your session has timed out. Please log in again.');
        redirect(SITE_URL . '/login.php');
    }
    $_SESSION['last_activity'] = $now;
}

