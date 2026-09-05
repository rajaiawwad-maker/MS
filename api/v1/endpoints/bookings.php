<?php

function handle_bookings_list($params) {
    global $conn;
    $where = [];
    $args = [];
    $status = $params['status'] ?? null;
    if ($status !== null && $status !== '') {
        $where[] = 'b.status = ?';
        $args[] = $status;
    }
    $client_id = $params['client_id'] ?? null;
    if ($client_id !== null && $client_id !== '') {
        $where[] = 'b.client_id = ?';
        $args[] = $client_id;
    }
    $date_from = $params['date_from'] ?? null;
    if ($date_from !== null && $date_from !== '') {
        $where[] = 'b.date_from >= ?';
        $args[] = $date_from;
    }
    $date_to = $params['date_to'] ?? null;
    if ($date_to !== null && $date_to !== '') {
        $where[] = 'b.date_to <= ?';
        $args[] = $date_to;
    }
    $q = $params['q'] ?? null;
    if ($q !== null && $q !== '') {
        $where[] = '(b.booking_number LIKE ? OR c.name LIKE ?)';
        $args[] = '%' . $q . '%';
        $args[] = '%' . $q . '%';
    }
    $sql = 'SELECT b.*, c.name as client_name, c.phone as client_phone FROM bookings b INNER JOIN clients c ON b.client_id = c.id';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY b.date_from DESC';
    $page = $params['page'] ?? 1;
    $per_page = $params['per_page'] ?? 20;
    $result = api_paginate($sql, $args, $page, $per_page);
    api_success($result['data'], 'OK', 200, $result['pagination']);
}

function handle_bookings_detail($params) {
    global $conn;
    $id = $params['id'] ?? null;
    if ($id === null) {
        api_error('Booking not found', 'not_found', 404);
    }
    $stmt = $conn->prepare('SELECT b.*, c.name as client_name, c.phone as client_phone FROM bookings b INNER JOIN clients c ON b.client_id = c.id WHERE b.id = ?');
    $stmt->execute([$id]);
    $booking = $stmt->fetch();
    if (!$booking) {
        api_error('Booking not found', 'not_found', 404);
    }
    $stmt = $conn->prepare('SELECT bi.*, it.name as item_type_name, it.category_id, cat.name as category_name FROM booking_items bi LEFT JOIN item_types it ON bi.item_type_id = it.id LEFT JOIN categories cat ON it.category_id = cat.id WHERE bi.booking_id = ?');
    $stmt->execute([$id]);
    $items = $stmt->fetchAll();
    $stmt = $conn->prepare('SELECT * FROM payments WHERE booking_id = ? ORDER BY id');
    $stmt->execute([$id]);
    $payments = $stmt->fetchAll();
    $collected = getBookingCollectedAmount($id);
    $pending = getBookingPendingAmount($id);
    $invoice_url = null;
    if (defined('SITE_URL')) {
        $invoice_url = rtrim(SITE_URL, '/') . '/invoice.php?id=' . $id;
    }
    $data = $booking;
    $data['items'] = $items;
    $data['payments'] = $payments;
    $data['invoice_url'] = $invoice_url;
    $data['totals'] = [
        'quoted_amount' => (float)$booking['quoted_amount'],
        'dj_rak_amount' => (float)$booking['dj_rak_amount'],
        'collected' => $collected,
        'pending' => $pending,
    ];
    api_success($data);
}

function handle_bookings_create($params) {
    global $conn;
    $body = api_get_json();
    $errors = [];
    api_validate_required(['client_id', 'date_from', 'date_to', 'quoted_amount'], $body, $errors);
    if (!isset($errors['quoted_amount'])) {
        if (!is_numeric($body['quoted_amount']) || (float)$body['quoted_amount'] < 0) {
            $errors['quoted_amount'][] = 'Quoted amount must be a non-negative number';
        }
    }
    $items = isset($body['items']) && is_array($body['items']) ? $body['items'] : [];
    $client_id = $body['client_id'] ?? null;
    $date_from = $body['date_from'] ?? null;
    $date_to = $body['date_to'] ?? null;
    if ($client_id !== null && $client_id !== '') {
        $stmt = $conn->prepare('SELECT id FROM clients WHERE id = ?');
        $stmt->execute([$client_id]);
        if (!$stmt->fetch()) {
            $errors['client_id'][] = 'Client does not exist';
        }
    }
    if ($date_from !== null && $date_from !== '') {
        api_validate_date($date_from, 'date_from', $errors);
    }
    if ($date_to !== null && $date_to !== '') {
        api_validate_date($date_to, 'date_to', $errors);
    }
    if ($date_from && $date_to && !isset($errors['date_from']) && !isset($errors['date_to'])) {
        if (strtotime($date_from) > strtotime($date_to)) {
            $errors['date_from'][] = 'Date from must be less than or equal to date to';
        }
    }
    $override = hasPermission('override_inventory');
    $item_errors = [];
    foreach ($items as $idx => $item) {
        if (!isset($item['item_type_id'])) {
            $item_errors[$idx]['item_type_id'][] = 'item_type_id is required';
        }
        if (!isset($item['quantity'])) {
            $item_errors[$idx]['quantity'][] = 'quantity is required';
        }
        if (!isset($item['rental_value'])) {
            $item_errors[$idx]['rental_value'][] = 'rental_value is required';
        }
        if (isset($item['quantity']) && (!is_numeric($item['quantity']) || (int)$item['quantity'] < 1)) {
            $item_errors[$idx]['quantity'][] = 'quantity must be at least 1';
        }
        if (isset($item['item_type_id'])) {
            $stmt = $conn->prepare('SELECT id FROM item_types WHERE id = ?');
            $stmt->execute([$item['item_type_id']]);
            if (!$stmt->fetch()) {
                $item_errors[$idx]['item_type_id'][] = 'Item type does not exist';
            } elseif (!$override && $date_from && $date_to && !isset($errors['date_from']) && !isset($errors['date_to'])) {
                $qty = (int)($item['quantity'] ?? 0);
                $avail = getAvailableQuantity($item['item_type_id'], $date_from, $date_to);
                if ($qty > $avail) {
                    $item_errors[$idx]['quantity'][] = 'Insufficient available quantity. Available: ' . $avail;
                }
            }
        }
    }
    if (!empty($item_errors)) {
        $errors['items'] = $item_errors;
    }
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_error', 422, $errors);
    }
    $booking_number = generateBookingNumber();
    $location = $body['location'] ?? '';
    $dj_rak_amount = isset($body['dj_rak_amount']) ? (float)$body['dj_rak_amount'] : 0;
    $event_start_time = $body['event_start_time'] ?? null;
    $event_end_time = $body['event_end_time'] ?? null;
    $status = $body['status'] ?? 'Draft';
    $internal_notes = $body['internal_notes'] ?? null;
    $user = currentApiUser();
    $created_by = $user['id'] ?? null;
    $token = generateToken(32);
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare('INSERT INTO bookings (booking_number, client_id, date_from, date_to, location, event_start_time, event_end_time, quoted_amount, dj_rak_amount, status, internal_notes, created_by, customer_confirmation_token, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
        $stmt->execute([
            $booking_number, $client_id, $date_from, $date_to, $location,
            $event_start_time, $event_end_time, (float)$body['quoted_amount'], $dj_rak_amount,
            $status, $internal_notes, $created_by, $token,
        ]);
        $bid = (int)$conn->lastInsertId();
        foreach ($items as $item) {
            $stmt = $conn->prepare('INSERT INTO booking_items (booking_id, item_type_id, quantity, rental_value) VALUES (?,?,?,?)');
            $stmt->execute([$bid, $item['item_type_id'], (int)$item['quantity'], (float)$item['rental_value']]);
        }
        $conn->commit();
        updateBookingPaymentStatus($bid);
        auditLog('booking_created', 'booking', $bid, null, ['id' => $bid, 'booking_number' => $booking_number]);
        api_success(['id' => $bid, 'booking_number' => $booking_number], 'Booking created', 201);
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        api_error('Failed to create booking: ' . $e->getMessage(), 'internal_error', 500);
    }
}

function handle_bookings_update($params) {
    global $conn;
    $id = $params['id'] ?? null;
    if ($id === null) {
        api_error('Booking not found', 'not_found', 404);
    }
    $stmt = $conn->prepare('SELECT * FROM bookings WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        api_error('Booking not found', 'not_found', 404);
    }
    $body = api_get_json();
    $errors = [];
    $fields = [];
    $upd_args = [];
    if (isset($body['client_id'])) {
        $fields[] = 'client_id = ?';
        $upd_args[] = $body['client_id'];
        $stmt = $conn->prepare('SELECT id FROM clients WHERE id = ?');
        $stmt->execute([$body['client_id']]);
        if (!$stmt->fetch()) {
            $errors['client_id'][] = 'Client does not exist';
        }
    }
    $date_from = isset($body['date_from']) ? $body['date_from'] : $existing['date_from'];
    $date_to = isset($body['date_to']) ? $body['date_to'] : $existing['date_to'];
    if (isset($body['date_from'])) {
        api_validate_date($body['date_from'], 'date_from', $errors);
        $fields[] = 'date_from = ?';
        $upd_args[] = $body['date_from'];
    }
    if (isset($body['date_to'])) {
        api_validate_date($body['date_to'], 'date_to', $errors);
        $fields[] = 'date_to = ?';
        $upd_args[] = $body['date_to'];
    }
    if (!isset($errors['date_from']) && !isset($errors['date_to'])) {
        if (strtotime($date_from) > strtotime($date_to)) {
            if (isset($body['date_from'])) {
                $errors['date_from'][] = 'Date from must be less than or equal to date to';
            } else {
                $errors['date_to'][] = 'Date to must be greater than or equal to date from';
            }
        }
    }
    if (isset($body['quoted_amount'])) {
        if (!is_numeric($body['quoted_amount']) || (float)$body['quoted_amount'] < 0) {
            $errors['quoted_amount'][] = 'Quoted amount must be a non-negative number';
        } else {
            $fields[] = 'quoted_amount = ?';
            $upd_args[] = (float)$body['quoted_amount'];
        }
    }
    if (isset($body['location'])) {
        $fields[] = 'location = ?';
        $upd_args[] = $body['location'];
    }
    if (isset($body['dj_rak_amount'])) {
        if (!is_numeric($body['dj_rak_amount']) || (float)$body['dj_rak_amount'] < 0) {
            $errors['dj_rak_amount'][] = 'DJ RAK amount must be a non-negative number';
        } else {
            $fields[] = 'dj_rak_amount = ?';
            $upd_args[] = (float)$body['dj_rak_amount'];
        }
    }
    if (isset($body['event_start_time'])) {
        $fields[] = 'event_start_time = ?';
        $upd_args[] = $body['event_start_time'];
    }
    if (isset($body['event_end_time'])) {
        $fields[] = 'event_end_time = ?';
        $upd_args[] = $body['event_end_time'];
    }
    if (isset($body['status'])) {
        $allowed = ['Draft','Quotation','Confirmed','Change Requested','Event Completed','Closed','Canceled'];
        if (!in_array($body['status'], $allowed, true)) {
            $errors['status'][] = 'Invalid status value';
        } else {
            $fields[] = 'status = ?';
            $upd_args[] = $body['status'];
        }
    }
    if (isset($body['internal_notes'])) {
        $fields[] = 'internal_notes = ?';
        $upd_args[] = $body['internal_notes'];
    }
    $items = isset($body['items']) && is_array($body['items']) ? $body['items'] : null;
    if ($items !== null) {
        $override = hasPermission('override_inventory');
        $item_errors = [];
        foreach ($items as $idx => $item) {
            if (!isset($item['item_type_id'])) {
                $item_errors[$idx]['item_type_id'][] = 'item_type_id is required';
            }
            if (!isset($item['quantity'])) {
                $item_errors[$idx]['quantity'][] = 'quantity is required';
            }
            if (!isset($item['rental_value'])) {
                $item_errors[$idx]['rental_value'][] = 'rental_value is required';
            }
            if (isset($item['quantity']) && (!is_numeric($item['quantity']) || (int)$item['quantity'] < 1)) {
                $item_errors[$idx]['quantity'][] = 'quantity must be at least 1';
            }
            if (isset($item['item_type_id'])) {
                $stmt = $conn->prepare('SELECT id FROM item_types WHERE id = ?');
                $stmt->execute([$item['item_type_id']]);
                if (!$stmt->fetch()) {
                    $item_errors[$idx]['item_type_id'][] = 'Item type does not exist';
                } elseif (!$override) {
                    $qty = (int)($item['quantity'] ?? 0);
                    $avail = getAvailableQuantity($item['item_type_id'], $date_from, $date_to, $id);
                    if ($qty > $avail) {
                        $item_errors[$idx]['quantity'][] = 'Insufficient available quantity. Available: ' . $avail;
                    }
                }
            }
        }
        if (!empty($item_errors)) {
            $errors['items'] = $item_errors;
        }
    }
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_error', 422, $errors);
    }
    try {
        $conn->beginTransaction();
        if (!empty($fields)) {
            $fields[] = 'updated_at = NOW()';
            $upd_args[] = $id;
            $sql = 'UPDATE bookings SET ' . implode(', ', $fields) . ' WHERE id = ?';
            $stmt = $conn->prepare($sql);
            $stmt->execute($upd_args);
        }
        if ($items !== null) {
            $stmt = $conn->prepare('DELETE FROM booking_items WHERE booking_id = ?');
            $stmt->execute([$id]);
            foreach ($items as $item) {
                $stmt = $conn->prepare('INSERT INTO booking_items (booking_id, item_type_id, quantity, rental_value) VALUES (?,?,?,?)');
                $stmt->execute([$id, $item['item_type_id'], (int)$item['quantity'], (float)$item['rental_value']]);
            }
        }
        $conn->commit();
        updateBookingPaymentStatus($id);
        auditLog('booking_updated', 'booking', $id, $existing, $body);
        api_success(null, 'Booking updated', 200);
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        api_error('Failed to update booking: ' . $e->getMessage(), 'internal_error', 500);
    }
}

function handle_bookings_cancel($params) {
    global $conn;
    $id = $params['id'] ?? null;
    if ($id === null) {
        api_error('Booking not found', 'not_found', 404);
    }
    $stmt = $conn->prepare('SELECT * FROM bookings WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        api_error('Booking not found', 'not_found', 404);
    }
    $stmt = $conn->prepare("UPDATE bookings SET status = 'Canceled' WHERE id = ?");
    $stmt->execute([$id]);
    updateBookingPaymentStatus($id);
    auditLog('booking_canceled', 'booking', $id, $existing, ['status' => 'Canceled']);
    api_success(null, 'Booking canceled', 200);
}

function handle_bookings_status($params) {
    global $conn;
    $id = $params['id'] ?? null;
    if ($id === null) {
        api_error('Booking not found', 'not_found', 404);
    }
    $stmt = $conn->prepare('SELECT * FROM bookings WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        api_error('Booking not found', 'not_found', 404);
    }
    $body = api_get_json();
    $to = $body['to'] ?? null;
    $errors = [];
    $allowed = ['Draft','Quotation','Confirmed','Change Requested','Event Completed','Closed'];
    if ($to === null || $to === '') {
        $errors['to'][] = 'Status is required';
    } elseif (!in_array($to, $allowed, true)) {
        $errors['to'][] = 'Invalid status value. Use the cancel endpoint to cancel a booking.';
    }
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_error', 422, $errors);
    }
    $stmt = $conn->prepare('UPDATE bookings SET status = ? WHERE id = ?');
    $stmt->execute([$to, $id]);
    updateBookingPaymentStatus($id);
    auditLog('booking_status_changed', 'booking', $id, ['status' => $existing['status']], ['status' => $to]);
    api_success(null, 'Booking status updated', 200);
}

function handle_bookings_regenerate_token($params) {
    global $conn;
    $id = $params['id'] ?? null;
    if ($id === null) {
        api_error('Booking not found', 'not_found', 404);
    }
    $stmt = $conn->prepare('SELECT id FROM bookings WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        api_error('Booking not found', 'not_found', 404);
    }
    $new = generateToken(32);
    $stmt = $conn->prepare('UPDATE bookings SET customer_confirmation_token = ? WHERE id = ?');
    $stmt->execute([$new, $id]);
    $url = (defined('SITE_URL') ? rtrim(SITE_URL, '/') : '') . '/confirm.php?token=' . $new;
    auditLog('regenerate_confirmation_token', 'booking', $id, null, ['confirmation_token' => $new]);
    api_success(['confirmation_token' => $new, 'confirmation_url' => $url], 'Confirmation token regenerated', 200);
}

function handle_bookings_invoice($params) {
    global $conn;
    $id = $params['id'] ?? null;
    if ($id === null) {
        api_error('Booking not found', 'not_found', 404);
    }
    $stmt = $conn->prepare('SELECT b.*, c.name as client_name, c.phone as client_phone, c.alt_phone as client_alt_phone, c.email as client_email, c.address as client_address FROM bookings b INNER JOIN clients c ON b.client_id = c.id WHERE b.id = ?');
    $stmt->execute([$id]);
    $booking = $stmt->fetch();
    if (!$booking) {
        api_error('Booking not found', 'not_found', 404);
    }
    $stmt = $conn->prepare('SELECT bi.*, it.name as item_type_name FROM booking_items bi LEFT JOIN item_types it ON bi.item_type_id = it.id WHERE bi.booking_id = ? ORDER BY bi.id');
    $stmt->execute([$id]);
    $items = $stmt->fetchAll();
    $stmt = $conn->prepare('SELECT * FROM payments WHERE booking_id = ? ORDER BY id');
    $stmt->execute([$id]);
    $payments = $stmt->fetchAll();
    $collected_amount = getBookingCollectedAmount($id);
    $pending_amount = getBookingPendingAmount($id);
    $items_subtotal = 0;
    foreach ($items as $it) {
        $items_subtotal += (float)$it['quantity'] * (float)$it['rental_value'];
    }
    $company = [
        'company_name' => getSetting('company_name', 'DJ RAK'),
        'address' => getSetting('company_address', ''),
        'phone' => getSetting('company_phone', ''),
        'tax_id' => getSetting('company_tax_id', ''),
        'currency' => getSetting('currency_symbol', 'JOD'),
    ];
    $client = [
        'id' => $booking['client_id'],
        'name' => $booking['client_name'],
        'phone' => $booking['client_phone'],
        'alt_phone' => $booking['client_alt_phone'],
        'email' => $booking['client_email'],
        'address' => $booking['client_address'],
    ];
    $totals = [
        'quoted_amount' => (float)$booking['quoted_amount'],
        'collected_amount' => $collected_amount,
        'pending_amount' => $pending_amount,
        'dj_rak_amount' => (float)$booking['dj_rak_amount'],
        'items_subtotal' => $items_subtotal,
    ];
    api_success([
        'company' => $company,
        'booking' => $booking,
        'client' => $client,
        'items' => $items,
        'payments' => $payments,
        'totals' => $totals,
    ]);
}
