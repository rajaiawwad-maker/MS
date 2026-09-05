<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    setFlash('error', 'This action requires a POST request.');
    $idRef = (int)($_GET['id'] ?? 0);
    $to = $idRef > 0 ? (SITE_URL . '/booking_view.php?id=' . $idRef) : (SITE_URL . '/bookings.php');
    redirect($to);
}
validate_csrf();

$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);
$to = $_POST['to'] ?? '';

if (!$id) { setFlash('error', t('bk.invalid_booking')); redirect(SITE_URL . '/bookings.php'); }

$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
$stmt->execute([$id]);
$booking = $stmt->fetch();
if (!$booking) { setFlash('error', t('err.not_found')); redirect(SITE_URL . '/bookings.php'); }

if ($action === 'cancel') {
    if (!hasPermission('cancel_bookings')) {
        auditSecurity('permission_denied', ['perm' => 'cancel_bookings', 'endpoint' => 'booking_action.php']);
        setFlash('error', t('err.permission_denied'));
        redirect(SITE_URL . '/booking_view.php?id=' . $id);
    }
    if ($booking['status'] === 'Canceled') { setFlash('error', t('bk.already_canceled')); redirect(SITE_URL . '/booking_view.php?id=' . $id); }
    $old = $booking;
    $stmt = $conn->prepare("UPDATE bookings SET status = 'Canceled', payment_status = 'Canceled', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    auditLog('cancel', 'Booking', $id, $old, ['status' => 'Canceled']);
    setFlash('success', t('bk.canceled_success'));
    redirect(SITE_URL . '/booking_view.php?id=' . $id);
}

if ($action === 'status') {
    if (!hasPermission('edit_bookings')) {
        auditSecurity('permission_denied', ['perm' => 'edit_bookings', 'endpoint' => 'booking_action.php']);
        setFlash('error', t('err.permission_denied'));
        redirect(SITE_URL . '/booking_view.php?id=' . $id);
    }
    $allowed = ['Draft','Quotation','Confirmed','Change Requested','Event Completed','Closed'];
    if (!in_array($to, $allowed)) { setFlash('error', t('bk.invalid_status')); redirect(SITE_URL . '/booking_view.php?id=' . $id); }
    $old = $booking;
    $stmt = $conn->prepare("UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$to, $id]);
    auditLog('update_status', 'Booking', $id, $old, ['status' => $to]);
    setFlash('success', t('bk.status_updated', [t_booking_status($to)]));
    redirect(SITE_URL . '/booking_view.php?id=' . $id);
}

if ($action === 'regenerate_token') {
    if (!hasPermission('edit_bookings')) {
        auditSecurity('permission_denied', ['perm' => 'edit_bookings', 'endpoint' => 'booking_action.php']);
        setFlash('error', t('err.permission_denied'));
        redirect(SITE_URL . '/booking_view.php?id=' . $id);
    }
    $token = generateToken(24);
    $stmt = $conn->prepare("UPDATE bookings SET customer_confirmation_token = ?, customer_confirmed_at = NULL, customer_response = NULL WHERE id = ?");
    $stmt->execute([$token, $id]);
    setFlash('success', t('bk.token_regenerated'));
    redirect(SITE_URL . '/booking_view.php?id=' . $id);
}

redirect(SITE_URL . '/bookings.php');
