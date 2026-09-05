<?php
function handle_expenses_types_list($params) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM expense_types WHERE active=1 ORDER BY name ASC");
    $stmt->execute();
    $rows = $stmt->fetchAll();
    api_success($rows, 'OK', 200);
}

function handle_expenses_types_create($params) {
    global $conn;
    $body = api_get_json();
    $errors = [];
    api_validate_required(['name'], $body, $errors);
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }
    $name = trim((string)$body['name']);
    $description = isset($body['description']) ? trim((string)$body['description']) : null;
    $stmt = $conn->prepare("INSERT expense_types (name,description,active) VALUES (?,?,1)");
    $stmt->execute([$name, $description]);
    $id = (int)$conn->lastInsertId();
    $stmt = $conn->prepare("SELECT * FROM expense_types WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    auditLog('expense_type_created', 'ExpenseType', $id, null, $row);
    api_success($row, 'Expense type created', 201);
}

function handle_expenses_types_update($params) {
    global $conn;
    $id = (int)($params['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM expense_types WHERE id=?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) {
        api_error('Expense type not found', 'not_found', 404);
    }
    $body = api_get_json();
    $name = isset($body['name']) ? trim((string)$body['name']) : $old['name'];
    $description = isset($body['description']) ? trim((string)$body['description']) : $old['description'];
    $active = isset($body['active']) ? (int)$body['active'] : (int)$old['active'];
    $stmt = $conn->prepare("UPDATE expense_types SET name=?, description=?, active=? WHERE id=?");
    $stmt->execute([$name, $description, $active, $id]);
    $stmt = $conn->prepare("SELECT * FROM expense_types WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    auditLog('expense_type_updated', 'ExpenseType', $id, $old, $row);
    api_success($row, 'Expense type updated', 200);
}

function handle_expenses_types_delete($params) {
    global $conn;
    $id = (int)($params['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM expense_types WHERE id=?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) {
        api_error('Expense type not found', 'not_found', 404);
    }
    $stmt = $conn->prepare("UPDATE expense_types SET active=0 WHERE id=?");
    $stmt->execute([$id]);
    auditLog('expense_type_deactivated', 'ExpenseType', $id, $old, null);
    api_success(null, 'Expense type deactivated', 200);
}

function handle_expenses_list($params) {
    global $conn;
    $sql = "SELECT e.*, et.name type_name, b.booking_number, u.name user_name FROM expenses e INNER JOIN expense_types et ON e.type_id = et.id LEFT JOIN bookings b ON e.booking_id=b.id LEFT JOIN users u ON e.created_by=u.id WHERE 1=1";
    $p = [];
    $date_from = isset($params['date_from']) ? trim((string)$params['date_from']) : '';
    if ($date_from !== '') {
        $sql .= " AND e.date >= ?";
        $p[] = $date_from;
    }
    $date_to = isset($params['date_to']) ? trim((string)$params['date_to']) : '';
    if ($date_to !== '') {
        $sql .= " AND e.date <= ?";
        $p[] = $date_to;
    }
    $type_id = isset($params['type_id']) ? (int)$params['type_id'] : 0;
    if ($type_id > 0) {
        $sql .= " AND e.type_id = ?";
        $p[] = $type_id;
    }
    $booking_id = isset($params['booking_id']) ? (int)$params['booking_id'] : 0;
    if ($booking_id > 0) {
        $sql .= " AND e.booking_id = ?";
        $p[] = $booking_id;
    }
    $q = isset($params['q']) ? trim((string)$params['q']) : '';
    if ($q !== '') {
        $sql .= " AND e.description LIKE ?";
        $p[] = '%' . $q . '%';
    }
    $sql .= " ORDER BY e.date DESC, e.id DESC";
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $pp = isset($params['per_page']) ? (int)$params['per_page'] : 20;
    $res = api_paginate($sql, $p, $page, $pp);
    api_success($res['data'], 'OK', 200, $res['pagination']);
}

function handle_expenses_create($params) {
    global $conn;
    $body = api_get_json();
    $errors = [];
    api_validate_required(['type_id', 'date', 'amount'], $body, $errors);
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }
    $type_id = (int)$body['type_id'];
    $date = trim((string)$body['date']);
    $amount = (float)$body['amount'];
    api_validate_date($date, 'date', $errors);
    $stmt = $conn->prepare("SELECT id FROM expense_types WHERE id=? AND active=1");
    $stmt->execute([$type_id]);
    if (!$stmt->fetch()) {
        $errors['type_id'][] = 'Expense type does not exist or is inactive';
    }
    if ($amount <= 0) {
        $errors['amount'][] = 'Amount must be > 0';
    }
    $booking_id = isset($body['booking_id']) ? (int)$body['booking_id'] : null;
    if ($booking_id !== null && $booking_id > 0) {
        $stmt = $conn->prepare("SELECT id FROM bookings WHERE id=?");
        $stmt->execute([$booking_id]);
        if (!$stmt->fetch()) {
            $errors['booking_id'][] = 'Booking does not exist';
        }
    } else {
        $booking_id = null;
    }
    $payment_method = isset($body['payment_method']) ? trim((string)$body['payment_method']) : null;
    $allowed_methods = ['Cash', 'Transfer', 'CliQ', 'Bank Transfer', 'Other'];
    if ($payment_method !== null && $payment_method !== '' && !in_array($payment_method, $allowed_methods, true)) {
        $errors['payment_method'][] = 'Invalid payment method, allowed: Cash, Transfer, CliQ, Bank Transfer, Other';
    }
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }
    $description = isset($body['description']) ? trim((string)$body['description']) : null;
    $reference = isset($body['reference']) ? trim((string)$body['reference']) : null;
    $notes = isset($body['notes']) ? trim((string)$body['notes']) : null;
    $user = currentApiUser();
    $uid = $user ? (int)$user['id'] : null;
    $stmt = $conn->prepare("INSERT INTO expenses (type_id,date,amount,description,payment_method,reference,booking_id,notes,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?, NOW())");
    $stmt->execute([$type_id, $date, $amount, $description, $payment_method, $reference, $booking_id, $notes, $uid]);
    $id = (int)$conn->lastInsertId();
    $stmt = $conn->prepare("SELECT e.*, et.name type_name, b.booking_number, u.name user_name FROM expenses e INNER JOIN expense_types et ON e.type_id = et.id LEFT JOIN bookings b ON e.booking_id=b.id LEFT JOIN users u ON e.created_by=u.id WHERE e.id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    auditLog('expense_created', 'Expense', $id, null, $row);
    api_success($row, 'Expense created', 201);
}

function handle_expenses_update($params) {
    global $conn;
    $id = (int)($params['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM expenses WHERE id=?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) {
        api_error('Expense not found', 'not_found', 404);
    }
    $body = api_get_json();
    $errors = [];
    $type_id = isset($body['type_id']) ? (int)$body['type_id'] : (int)$old['type_id'];
    $date = isset($body['date']) ? trim((string)$body['date']) : $old['date'];
    $amount = isset($body['amount']) ? (float)$body['amount'] : (float)$old['amount'];
    if (isset($body['date'])) {
        api_validate_date($date, 'date', $errors);
    }
    if (isset($body['type_id'])) {
        $stmt = $conn->prepare("SELECT id FROM expense_types WHERE id=? AND active=1");
        $stmt->execute([$type_id]);
        if (!$stmt->fetch()) {
            $errors['type_id'][] = 'Expense type does not exist or is inactive';
        }
    }
    if (isset($body['amount']) && $amount <= 0) {
        $errors['amount'][] = 'Amount must be > 0';
    }
    $booking_id = isset($body['booking_id']) ? (int)$body['booking_id'] : ($old['booking_id'] ?: null);
    if (isset($body['booking_id'])) {
        if ($booking_id > 0) {
            $stmt = $conn->prepare("SELECT id FROM bookings WHERE id=?");
            $stmt->execute([$booking_id]);
            if (!$stmt->fetch()) {
                $errors['booking_id'][] = 'Booking does not exist';
            }
        } else {
            $booking_id = null;
        }
    }
    $payment_method = isset($body['payment_method']) ? trim((string)$body['payment_method']) : $old['payment_method'];
    $allowed_methods = ['Cash', 'Transfer', 'CliQ', 'Bank Transfer', 'Other'];
    if (isset($body['payment_method']) && $payment_method !== null && $payment_method !== '' && !in_array($payment_method, $allowed_methods, true)) {
        $errors['payment_method'][] = 'Invalid payment method, allowed: Cash, Transfer, CliQ, Bank Transfer, Other';
    }
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }
    $description = isset($body['description']) ? trim((string)$body['description']) : $old['description'];
    $reference = isset($body['reference']) ? trim((string)$body['reference']) : $old['reference'];
    $notes = isset($body['notes']) ? trim((string)$body['notes']) : $old['notes'];
    $stmt = $conn->prepare("UPDATE expenses SET type_id=?, date=?, amount=?, description=?, payment_method=?, reference=?, booking_id=?, notes=? WHERE id=?");
    $stmt->execute([$type_id, $date, $amount, $description, $payment_method, $reference, $booking_id, $notes, $id]);
    $stmt = $conn->prepare("SELECT e.*, et.name type_name, b.booking_number, u.name user_name FROM expenses e INNER JOIN expense_types et ON e.type_id = et.id LEFT JOIN bookings b ON e.booking_id=b.id LEFT JOIN users u ON e.created_by=u.id WHERE e.id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    auditLog('expense_updated', 'Expense', $id, $old, $row);
    api_success($row, 'Expense updated', 200);
}

function handle_expenses_delete($params) {
    global $conn;
    $id = (int)($params['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM expenses WHERE id=?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) {
        api_error('Expense not found', 'not_found', 404);
    }
    $stmt = $conn->prepare("DELETE FROM expenses WHERE id=?");
    $stmt->execute([$id]);
    auditLog('expense_deleted', 'Expense', $id, $old, null);
    api_success(null, 'Expense deleted', 200);
}
