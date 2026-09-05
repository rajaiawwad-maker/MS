<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$out = ['ok' => false, 'total' => 0, 'booked' => 0, 'available' => 0];
if (!isLoggedIn()) { echo json_encode($out); exit; }

$itemTypeId = (int)($_GET['item_type_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$exclude = isset($_GET['exclude_booking_id']) ? (int)$_GET['exclude_booking_id'] : null;

if ($itemTypeId > 0 && $dateFrom && $dateTo) {
    try {
        $stmt = $conn->prepare("SELECT quantity FROM item_types WHERE id = ?");
        $stmt->execute([$itemTypeId]);
        $total = (int)$stmt->fetchColumn();
        $booked = getBookedQuantity($itemTypeId, $dateFrom, $dateTo, $exclude);
        $out = ['ok' => true, 'total' => $total, 'booked' => $booked, 'available' => max(0, $total - $booked)];
    } catch (Exception $e) { $out['error'] = $e->getMessage(); }
}
echo json_encode($out);
