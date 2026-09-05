<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('record_payments');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    setFlash('error', 'This action requires a POST request.');
    $idRef = (int)($_GET['id'] ?? 0);
    $bkRef = (int)($_GET['bk'] ?? 0);
    $ref = $_GET['ref'] ?? 'booking';
    if ($ref === 'payments') {
        $to = SITE_URL . '/payments.php';
    } elseif ($bkRef > 0) {
        $to = SITE_URL . '/booking_view.php?id=' . $bkRef;
    } else {
        $to = SITE_URL . '/bookings.php';
    }
    redirect($to);
}
validate_csrf();

$action = $_POST['action'] ?? '';
$bk = (int)($_POST['bk'] ?? 0);
$id = (int)($_POST['id'] ?? 0);
$ref = $_POST['ref'] ?? 'booking';

function payRedirect($bk, $ref) {
    if ($ref === 'payments') {
        redirect(SITE_URL . '/payments.php');
    }
    redirect(SITE_URL . '/booking_view.php?id=' . $bk);
}

if ($action === 'create' && $bk) {
    if (!hasPermission('record_payments')) {
        auditSecurity('permission_denied', ['perm' => 'record_payments', 'endpoint' => 'payment_action.php', 'action' => 'create']);
        setFlash('error', t('err.permission_denied'));
        payRedirect($bk, $ref);
    }
    $date = DateTime::createFromFormat('d/m/Y', $_POST['payment_date'] ?? date('d/m/Y'));
    if (!$date) $date = new DateTime();
    $amount = (float)($_POST['amount'] ?? 0);
    $method = trim($_POST['payment_method'] ?? '');
    $reference = trim($_POST['reference'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    if ($amount <= 0) { setFlash('error', t('pay.amount_gt_zero')); payRedirect($bk, $ref); }
    $stmt = $conn->prepare("SELECT quoted_amount FROM bookings WHERE id = ?");
    $stmt->execute([$bk]);
    $b = $stmt->fetch();
    $collected = getBookingCollectedAmount($bk);
    if ($collected + $amount > (float)$b['quoted_amount'] + 0.001 && !hasPermission('override_inventory')) {
        setFlash('error', t('pay.exceeds_quoted'));
        payRedirect($bk, $ref);
    }
    $stmt = $conn->prepare("INSERT INTO payments (booking_id, payment_date, amount, payment_method, reference, notes, created_by) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$bk, $date->format('Y-m-d'), $amount, $method, $reference, $notes, $_SESSION['user_id']]);
    $pid = $conn->lastInsertId();
    updateBookingPaymentStatus($bk);
    auditLog('create', 'Payment', $pid, null, ['booking_id' => $bk, 'amount' => $amount, 'date' => $date->format('Y-m-d')]);
    setFlash('success', t('pay.add_success'));
    payRedirect($bk, $ref);
}

if ($action === 'delete' && $id) {
    if (!requirePermission('record_payments', false)) {
        auditSecurity('permission_denied', ['perm' => 'record_payments', 'endpoint' => 'payment_action.php', 'action' => 'delete']);
        setFlash('error', t('err.permission_denied'));
        redirect(SITE_URL . '/bookings.php');
    }
    $stmt = $conn->prepare("SELECT * FROM payments WHERE id = ?");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if (!$p) { setFlash('error', t('err.not_found')); redirect(SITE_URL . '/bookings.php'); }
    $bk = $p['booking_id'];
    $stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
    $stmt->execute([$id]);
    updateBookingPaymentStatus($bk);
    auditLog('delete', 'Payment', $id, $p, null);
    setFlash('success', t('pay.delete_success'));
    payRedirect($bk, $ref);
}

redirect(SITE_URL . '/bookings.php');
