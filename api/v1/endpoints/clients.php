<?php
function handle_clients_list($params) {
    global $conn;
    $page = $params['page'] ?? 1;
    $perPage = $params['per_page'] ?? 20;
    $active = $params['active'] ?? '1';
    $q = isset($params['q']) ? trim($params['q']) : '';

    $where = [];
    $queryParams = [];

    if ($active === '1' || $active === '0') {
        $where[] = 'c.active = ?';
        $queryParams[] = (int)$active;
    }

    if ($q !== '') {
        $where[] = '(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
        $like = '%' . $q . '%';
        $queryParams[] = $like;
        $queryParams[] = $like;
        $queryParams[] = $like;
    }

    $whereSql = '';
    if (!empty($where)) {
        $whereSql = 'WHERE ' . implode(' AND ', $where);
    }

    $sql = "SELECT c.*,
        (SELECT COUNT(*) FROM bookings WHERE client_id = c.id) as bookings_count
        FROM clients c
        $whereSql
        ORDER BY c.id DESC";

    $countSql = "SELECT COUNT(*) FROM clients c $whereSql";

    $result = api_paginate($sql, $queryParams, $page, $perPage, $countSql, $queryParams);

    api_success($result['data'], 'Clients list', 200, $result['pagination']);
}

function handle_clients_detail($params) {
    global $conn;
    $id = (int)($params['id'] ?? 0);

    $stmt = $conn->prepare("SELECT c.*,
        (SELECT COUNT(*) FROM bookings WHERE client_id = c.id) as bookings_count
        FROM clients c WHERE c.id = ?");
    $stmt->execute([$id]);
    $client = $stmt->fetch();

    if (!$client) {
        api_error('Client not found', 'client_not_found', 404);
    }

    api_success($client, 'Client details', 200);
}

function handle_clients_create($params) {
    global $conn;
    $body = api_get_json();
    $errors = [];

    api_validate_required(['name', 'phone'], $body, $errors);

    if (empty($errors)) {
        if (strlen(trim($body['name'])) < 2) {
            $errors['name'][] = 'Name must be at least 2 characters';
        }
        if (strlen(trim($body['phone'])) < 5) {
            $errors['phone'][] = 'Phone must be at least 5 characters';
        }
    }

    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }

    $name = trim($body['name']);
    $phone = trim($body['phone']);
    $altPhone = isset($body['alt_phone']) && trim($body['alt_phone']) !== '' ? trim($body['alt_phone']) : null;
    $email = isset($body['email']) && trim($body['email']) !== '' ? trim($body['email']) : null;
    $address = isset($body['address']) && trim($body['address']) !== '' ? trim($body['address']) : null;
    $notes = isset($body['notes']) && trim($body['notes']) !== '' ? trim($body['notes']) : null;

    if ($email !== null) {
        $emailErrors = [];
        api_validate_email($email, 'email', $emailErrors);
        if (!empty($emailErrors)) {
            api_error('Validation failed', 'validation_failed', 422, $emailErrors);
        }
        $stmt = $conn->prepare("SELECT COUNT(*) FROM clients WHERE email = ?");
        $stmt->execute([$email]);
        if ((int)$stmt->fetchColumn() > 0) {
            api_error('Validation failed', 'validation_failed', 422, ['email' => ['Email is already in use']]);
        }
    }

    $stmt = $conn->prepare("INSERT INTO clients (name, phone, alt_phone, email, address, notes, active, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
    $stmt->execute([$name, $phone, $altPhone, $email, $address, $notes]);
    $newId = (int)$conn->lastInsertId();

    $stmt = $conn->prepare("SELECT c.*,
        (SELECT COUNT(*) FROM bookings WHERE client_id = c.id) as bookings_count
        FROM clients c WHERE c.id = ?");
    $stmt->execute([$newId]);
    $entity = $stmt->fetch();

    auditLog('client_created', 'Client', $newId, null, $entity);

    api_success([
        'id' => $newId,
        'entity' => $entity,
    ], 'Client created successfully', 201);
}

function handle_clients_update($params) {
    global $conn;
    $id = (int)($params['id'] ?? 0);

    $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();

    if (!$old) {
        api_error('Client not found', 'client_not_found', 404);
    }

    $body = api_get_json();
    $errors = [];

    $name = isset($body['name']) ? trim($body['name']) : $old['name'];
    $phone = isset($body['phone']) ? trim($body['phone']) : $old['phone'];
    $altPhone = isset($body['alt_phone']) ? (trim($body['alt_phone']) !== '' ? trim($body['alt_phone']) : null) : $old['alt_phone'];
    $email = isset($body['email']) ? (trim($body['email']) !== '' ? trim($body['email']) : null) : $old['email'];
    $address = isset($body['address']) ? (trim($body['address']) !== '' ? trim($body['address']) : null) : $old['address'];
    $notes = isset($body['notes']) ? (trim($body['notes']) !== '' ? trim($body['notes']) : null) : $old['notes'];
    $active = isset($body['active']) ? (int)$body['active'] : (int)$old['active'];

    if ($name === '') {
        $errors['name'][] = 'Name is required';
    }
    if ($phone === '') {
        $errors['phone'][] = 'Phone is required';
    }

    if ($name !== '' && strlen($name) < 2) {
        $errors['name'][] = 'Name must be at least 2 characters';
    }
    if ($phone !== '' && strlen($phone) < 5) {
        $errors['phone'][] = 'Phone must be at least 5 characters';
    }

    if ($email !== null) {
        api_validate_email($email, 'email', $errors);
    }

    if ($email !== null && empty($errors)) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM clients WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors['email'][] = 'Email is already in use by another client';
        }
    }

    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }

    $stmt = $conn->prepare("UPDATE clients SET name = ?, phone = ?, alt_phone = ?, email = ?, address = ?, notes = ?, active = ? WHERE id = ?");
    $stmt->execute([$name, $phone, $altPhone, $email, $address, $notes, $active, $id]);

    $stmt = $conn->prepare("SELECT c.*,
        (SELECT COUNT(*) FROM bookings WHERE client_id = c.id) as bookings_count
        FROM clients c WHERE c.id = ?");
    $stmt->execute([$id]);
    $updated = $stmt->fetch();

    auditLog('client_updated', 'Client', $id, $old, $updated);

    api_success($updated, 'Client updated successfully', 200);
}

function handle_clients_delete($params) {
    global $conn;
    $id = (int)($params['id'] ?? 0);

    $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();

    if (!$old) {
        api_error('Client not found', 'client_not_found', 404);
    }

    $stmt = $conn->prepare("UPDATE clients SET active = 0 WHERE id = ?");
    $stmt->execute([$id]);

    auditLog('client_deactivated', 'Client', $id, $old, ['active' => 0]);

    api_success(null, 'Client deactivated successfully', 200);
}

function handle_clients_statement($params) {
    global $conn;
    $id = (int)($params['id'] ?? 0);

    $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    $client = $stmt->fetch();

    if (!$client) {
        api_error('Client not found', 'client_not_found', 404);
    }

    $stmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN status != 'Canceled' THEN quoted_amount END),0) as booked FROM bookings WHERE client_id = ?");
    $stmt->execute([$id]);
    $booked = (float)$stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p
        INNER JOIN bookings b ON p.booking_id = b.id WHERE b.client_id = ?");
    $stmt->execute([$id]);
    $collected = (float)$stmt->fetchColumn();

    $pending = $booked - $collected;

    $summary = [
        'booked' => $booked,
        'collected' => $collected,
        'pending' => $pending,
    ];

    $stmt = $conn->prepare("SELECT id, booking_number, date_from, quoted_amount, status
        FROM bookings WHERE client_id = ? ORDER BY id DESC LIMIT 10");
    $stmt->execute([$id]);
    $recentBookings = $stmt->fetchAll();

    $stmt = $conn->prepare("SELECT p.* FROM payments p
        INNER JOIN bookings b ON p.booking_id = b.id
        WHERE b.client_id = ? ORDER BY p.id DESC LIMIT 10");
    $stmt->execute([$id]);
    $recentPayments = $stmt->fetchAll();

    api_success([
        'summary' => $summary,
        'recent_bookings' => $recentBookings,
        'recent_payments' => $recentPayments,
    ], 'Client statement', 200);
}
