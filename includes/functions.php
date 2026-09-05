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

