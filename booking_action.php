<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$to = $_GET['to'] ?? '';

if (!$id) { setFlash('error', t('bk.invalid_booking')); redirect(SITE_URL . '/bookings.php'); }

$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
$stmt->execute([$id]);
$booking = $stmt->fetch();
if (!$booking) { setFlash('error', t('err.not_found')); redirect(SITE_URL . '/bookings.php'); }

if ($action === 'cancel' && hasPermission('cancel_bookings')) {
    if ($booking['status'] === 'Canceled') { setFlash('error', t('bk.already_canceled')); redirect(SITE_URL . '/booking_view.php?id=' . $id); }
    $old = $booking;
    $stmt = $conn->prepare("UPDATE bookings SET status = 'Canceled', payment_status = 'Canceled', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    auditLog('cancel', 'Booking', $id, $old, ['status' => 'Canceled']);
    setFlash('success', t('bk.canceled_success'));
    redirect(SITE_URL . '/booking_view.php?id=' . $id);
}

if ($action === 'status' && hasPermission('edit_bookings')) {
    $allowed = ['Draft','Quotation','Confirmed','Change Requested','Event Completed','Closed'];
    if (!in_array($to, $allowed)) { setFlash('error', t('bk.invalid_status')); redirect(SITE_URL . '/booking_view.php?id=' . $id); }
    $old = $booking;
    $stmt = $conn->prepare("UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$to, $id]);
    auditLog('update_status', 'Booking', $id, $old, ['status' => $to]);
    setFlash('success', t('bk.status_updated', [t_booking_status($to)]));
    redirect(SITE_URL . '/booking_view.php?id=' . $id);
}

if ($action === 'regenerate_token' && hasPermission('edit_bookings')) {
    $token = generateToken(24);
    $stmt = $conn->prepare("UPDATE bookings SET customer_confirmation_token = ?, customer_confirmed_at = NULL, customer_response = NULL WHERE id = ?");
    $stmt->execute([$token, $id]);
    setFlash('success', t('bk.token_regenerated'));
    redirect(SITE_URL . '/booking_view.php?id=' . $id);
}

redirect(SITE_URL . '/bookings.php');
