<?php
require_once __DIR__ . '/config.php';

$bookingId = (int)($_GET['id'] ?? 0);
$token = trim($_GET['token'] ?? '');

if ($bookingId <= 0) { die(t('cf.invalid_request')); }

if ($token !== '') {
    $stmt = $conn->prepare("SELECT id FROM bookings WHERE id = ? AND customer_confirmation_token = ?");
    $stmt->execute([$bookingId, $token]);
    if (!$stmt->fetchColumn()) { die(t('cf.invalid_booking')); }
} else {
    if (!isLoggedIn()) { redirect(SITE_URL . '/login.php'); }
}

$stmt = $conn->prepare("SELECT b.*, c.name as client_name, c.phone as client_phone FROM bookings b INNER JOIN clients c ON b.client_id = c.id WHERE b.id = ?");
$stmt->execute([$bookingId]);
$b = $stmt->fetch();
if (!$b) { die(t('cf.not_found_short')); }

$stmt = $conn->prepare("SELECT GROUP_CONCAT(CONCAT(bi.quantity, ' x ', it.name) SEPARATOR '\\n') FROM booking_items bi INNER JOIN item_types it ON bi.item_type_id = it.id WHERE bi.booking_id = ?");
$stmt->execute([$bookingId]);
$items = $stmt->fetchColumn() ?: '';

$companyName = getSetting('company_name', 'DJ RAK Entertainment');
$companyPhone = getSetting('company_phone', '');

$startDate = $b['date_from'];
$endDate = $b['date_to'];
$startTime = $b['event_start_time'] ?: '18:00:00';
$endTime = $b['event_end_time'] ?: '23:00:00';
$dtStart = new DateTime($startDate . ' ' . $startTime, new DateTimeZone('Asia/Riyadh'));
$dtEnd = new DateTime($endDate . ' ' . $endTime, new DateTimeZone('Asia/Riyadh'));

function icsDate($dt) { return $dt->format('Ymd\THis\Z'); }

$uid = 'booking-' . $b['booking_number'] . '@' . ($_SERVER['HTTP_HOST'] ?? 'djrak.local');
$now = new DateTime('now', new DateTimeZone('UTC'));

$summary = t('cf.ics_summary_prefix') . ' - ' . $b['client_name'];
$description = t('cf.ics_booking_label') . ': ' . $b['booking_number'] . "\\n" . t('cf.ics_client_label') . ': ' . $b['client_name'] . "\\n" . t('cf.ics_phone_label') . ': ' . $b['client_phone'] . "\\n\\n" . t('cf.ics_equipment_label') . ":\\n" . $items . "\\n\\n" . t('cf.ics_total_label') . ': ' . formatMoney($b['quoted_amount']) . "\\n\\n" . $companyName;
$location = $b['location'];

header('X-Content-Type-Options: nosniff');
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="Booking_' . $b['booking_number'] . '.ics"');
echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//DJ RAK Manager//Booking ICS//EN\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";
echo "BEGIN:VEVENT\r\n";
echo "UID:" . $uid . "\r\n";
echo "DTSTAMP:" . icsDate($now) . "\r\n";
echo "DTSTART:" . icsDate($dtStart) . "\r\n";
echo "DTEND:" . icsDate($dtEnd) . "\r\n";
echo "SUMMARY:" . preg_replace('/([\,;])/','\\\\$1', $summary) . "\r\n";
echo "DESCRIPTION:" . preg_replace('/([\,;])/','\\\\$1', $description) . "\r\n";
echo "LOCATION:" . preg_replace('/([\,;])/','\\\\$1', $location) . "\r\n";
echo "STATUS:CONFIRMED\r\n";
echo "SEQUENCE:0\r\n";
echo "BEGIN:VALARM\r\n";
echo "TRIGGER:-PT1440M\r\n";
echo "ACTION:DISPLAY\r\n";
echo "DESCRIPTION:" . t('cf.ics_reminder') . "\r\n";
echo "END:VALARM\r\n";
echo "END:VEVENT\r\n";
echo "END:VCALENDAR\r\n";
