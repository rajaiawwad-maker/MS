<?php
function handle_payments_list($params) {
    global $conn;
    $where = [];
    $args = [];
    $booking_id = isset($params['booking_id']) ? (int)$params['booking_id'] : 0;
    if ($booking_id > 0) {
        $where[] = 'p.booking_id = ?';
        $args[] = $booking_id;
    }
    $date_from = isset($params['date_from']) ? trim((string)$params['date_from']) : '';
    if ($date_from !== '') {
        $where[] = 'p.payment_date >= ?';
        $args[] = $date_from;
    }
    $date_to = isset($params['date_to']) ? trim((string)$params['date_to']) : '';
    if ($date_to !== '') {
        $where[] = 'p.payment_date <= ?';
        $args[] = $date_to;
    }
    $payment_method = isset($params['payment_method']) ? trim((string)$params['payment_method']) : '';
    if ($payment_method !== '') {
        $where[] = 'p.payment_method = ?';
        $args[] = $payment_method;
    }
    $q = isset($params['q']) ? trim((string)$params['q']) : '';
    if ($q !== '') {
        $where[] = '(p.notes LIKE ? OR p.reference LIKE ?)';
        $like = '%' . $q . '%';
        $args[] = $like;
        $args[] = $like;
    }
    $sql = 'SELECT p.*, b.booking_number, c.name as client_name, u.name as user_name FROM payments p INNER JOIN bookings b ON b.id = p.booking_id INNER JOIN clients c ON c.id = b.client_id LEFT JOIN users u ON u.id = p.created_by';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY p.payment_date DESC, p.id DESC';
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $pp = isset($params['per_page']) ? (int)$params['per_page'] : 20;
    $countSql = 'SELECT COUNT(*) FROM payments p INNER JOIN bookings b ON b.id = p.booking_id INNER JOIN clients c ON c.id = b.client_id LEFT JOIN users u ON u.id = p.created_by';
    if (!empty($where)) {
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $res = api_paginate($sql, $args, $page, $pp, $countSql, $args);
    api_success($res['data'], 'Payments list', 200, $res['pagination']);
}

function handle_payments_create($params) {
    global $conn;
    $body = api_get_json();
    $errors = [];
    api_validate_required(['booking_id', 'payment_date', 'amount'], $body, $errors);
    $booking_id = isset($body['booking_id']) ? (int)$body['booking_id'] : 0;
    $payment_date = isset($body['payment_date']) ? trim((string)$body['payment_date']) : '';
    $amount = isset($body['amount']) ? (float)$body['amount'] : 0;
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }
    api_validate_date($payment_date, 'payment_date', $errors);
    if ($amount <= 0) {
        $errors['amount'][] = 'Amount must be > 0';
    }
    $stmt = $conn->prepare('SELECT id, quoted_amount, booking_number FROM bookings WHERE id = ?');
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();
    if (!$booking) {
        $errors['booking_id'][] = 'Booking does not exist';
    }
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }
    $collected = getBookingCollectedAmount($booking_id) + $amount;
    $quoted = (float)$booking['quoted_amount'];
    if ($collected > $quoted && !hasPermission('override_inventory')) {
        api_error('Validation failed', 'validation_failed', 422, ['booking' => ['Total collected will exceed quoted amount; override_inventory permission required']]);
    }
    $payment_method = isset($body['payment_method']) && trim((string)$body['payment_method']) !== '' ? trim((string)$body['payment_method']) : 'Cash';
    $allowed_methods = ['Cash', 'Transfer', 'CliQ', 'Bank Transfer', 'Other'];
    if (!in_array($payment_method, $allowed_methods, true)) {
        $payment_method = 'Cash';
    }
    $notes = isset($body['notes']) && trim((string)$body['notes']) !== '' ? trim((string)$body['notes']) : null;
    $reference = isset($body['reference']) && trim((string)$body['reference']) !== '' ? trim((string)$body['reference']) : null;
    $user = currentApiUser();
    $created_by = $user ? (int)$user['id'] : null;
    $stmt = $conn->prepare('INSERT INTO payments (booking_id, payment_date, amount, payment_method, notes, reference, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$booking_id, $payment_date, $amount, $payment_method, $notes, $reference, $created_by]);
    $id = (int)$conn->lastInsertId();
    updateBookingPaymentStatus($booking_id);
    auditLog('payment_created', 'Payment', $id, null, ['id' => $id, 'booking_id' => $booking_id, 'amount' => $amount]);
    api_success(['id' => $id, 'booking_id' => $booking_id, 'amount' => $amount, 'booking_number' => $booking['booking_number']], 'Payment created', 201);
}

function handle_payments_delete($params) {
    global $conn;
    $id = (int)($params['id'] ?? 0);
    $stmt = $conn->prepare('SELECT * FROM payments WHERE id = ?');
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) {
        api_error('Payment not found', 'not_found', 404);
    }
    $booking_id = (int)$old['booking_id'];
    $stmt = $conn->prepare('DELETE FROM payments WHERE id = ?');
    $stmt->execute([$id]);
    updateBookingPaymentStatus($booking_id);
    auditLog('payment_deleted', 'Payment', $id, $old, null);
    api_success(null, 'Payment deleted', 200);
}
