<?php
function handle_users_list($params) {
    global $conn;
    $where = [];
    $args = [];
    $role_id = isset($params['role_id']) ? (int)$params['role_id'] : 0;
    if ($role_id > 0) {
        $where[] = 'u.role_id = ?';
        $args[] = $role_id;
    }
    $q = isset($params['q']) ? trim((string)$params['q']) : '';
    if ($q !== '') {
        $where[] = '(u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
        $like = '%' . $q . '%';
        $args[] = $like;
        $args[] = $like;
        $args[] = $like;
    }
    $active = isset($params['active']) ? (string)$params['active'] : '1';
    if ($active === '1' || $active === '0') {
        $where[] = 'u.active = ?';
        $args[] = (int)$active;
    }
    $sql = 'SELECT u.*, r.name as role_name FROM users u INNER JOIN roles r ON u.role_id = r.id';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY u.name ASC';
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $pp = isset($params['per_page']) ? (int)$params['per_page'] : 20;
    $countSql = 'SELECT COUNT(*) FROM users u INNER JOIN roles r ON u.role_id = r.id';
    if (!empty($where)) {
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $res = api_paginate($sql, $args, $page, $pp, $countSql, $args);
    foreach ($res['data'] as &$row) {
        unset($row['password_hash']);
    }
    unset($row);
    api_success($res['data'], 'Users list', 200, $res['pagination']);
}

function handle_users_detail($params) {
    global $conn;
    $id = (int)($params['id'] ?? 0);
    $stmt = $conn->prepare('SELECT u.*, r.name as role_name FROM users u INNER JOIN roles r ON u.role_id = r.id WHERE u.id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) {
        api_error('User not found', 'not_found', 404);
    }
    unset($user['password_hash']);
    $role_name = $user['role_name'];
    $perms = [];
    if ($role_name === 'Administrator') {
        $stmt = $conn->query('SELECT permission_name FROM permissions ORDER BY id ASC');
        $perms = array_column($stmt->fetchAll(), 'permission_name');
    } else {
        $stmt = $conn->prepare('SELECT p.permission_name FROM permissions p INNER JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = ? ORDER BY p.id ASC');
        $stmt->execute([(int)$user['role_id']]);
        $perms = array_column($stmt->fetchAll(), 'permission_name');
    }
    $user['permissions'] = $perms;
    api_success($user, 'User details', 200);
}

function handle_users_create($params) {
    global $conn;
    $body = api_get_json();
    $errors = [];
    api_validate_required(['name', 'username', 'email', 'password', 'role_id'], $body, $errors);
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }
    $name = trim((string)$body['name']);
    $username = trim((string)$body['username']);
    $email = trim((string)$body['email']);
    $password = (string)$body['password'];
    $role_id = (int)$body['role_id'];
    $phone = isset($body['phone']) && trim((string)$body['phone']) !== '' ? trim((string)$body['phone']) : null;
    api_validate_email($email, 'email', $errors);
    api_validate_min8_password($password, 'password', $errors);
    $stmt = $conn->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ((int)$stmt->fetchColumn() > 0) {
        $errors['username'][] = 'Username is already in use';
    }
    $stmt = $conn->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ((int)$stmt->fetchColumn() > 0) {
        $errors['email'][] = 'Email is already in use';
    }
    $stmt = $conn->prepare('SELECT id FROM roles WHERE id = ?');
    $stmt->execute([$role_id]);
    if (!$stmt->fetch()) {
        $errors['role_id'][] = 'Role does not exist';
    }
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO users (name, username, email, phone, role_id, password_hash, active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())');
    $stmt->execute([$name, $username, $email, $phone, $role_id, $password_hash]);
    $id = (int)$conn->lastInsertId();
    $stmt = $conn->prepare('SELECT u.*, r.name as role_name FROM users u INNER JOIN roles r ON u.role_id = r.id WHERE u.id = ?');
    $stmt->execute([$id]);
    $entity = $stmt->fetch();
    unset($entity['password_hash']);
    auditLog('user_created', 'User', $id, null, $entity);
    api_success($entity, 'User created', 201);
}

function handle_users_update($params) {
    global $conn;
    $id = (int)($params['id'] ?? 0);
    $stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) {
        api_error('User not found', 'not_found', 404);
    }
    $body = api_get_json();
    $errors = [];
    $name = isset($body['name']) ? trim((string)$body['name']) : $old['name'];
    $username = isset($body['username']) ? trim((string)$body['username']) : $old['username'];
    $email = isset($body['email']) ? trim((string)$body['email']) : $old['email'];
    $phone = isset($body['phone']) ? (trim((string)$body['phone']) !== '' ? trim((string)$body['phone']) : null) : $old['phone'];
    $role_id = isset($body['role_id']) ? (int)$body['role_id'] : (int)$old['role_id'];
    $active = isset($body['active']) ? (int)$body['active'] : (int)$old['active'];
    $new_password = isset($body['password']) && trim((string)$body['password']) !== '' ? (string)$body['password'] : null;
    if ($new_password !== null) {
        api_validate_min8_password($new_password, 'password', $errors);
    }
    if ($email !== null && $email !== '') {
        api_validate_email($email, 'email', $errors);
    }
    if (empty($errors)) {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM users WHERE username = ? AND id != ?');
        $stmt->execute([$username, $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors['username'][] = 'Username is already in use by another user';
        }
    }
    if (empty($errors) && $email !== null && $email !== '') {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors['email'][] = 'Email is already in use by another user';
        }
    }
    if (empty($errors)) {
        $stmt = $conn->prepare('SELECT id FROM roles WHERE id = ?');
        $stmt->execute([$role_id]);
        if (!$stmt->fetch()) {
            $errors['role_id'][] = 'Role does not exist';
        }
    }
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }
    if ($new_password !== null) {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET name = ?, username = ?, email = ?, phone = ?, role_id = ?, active = ?, password_hash = ? WHERE id = ?');
        $stmt->execute([$name, $username, $email, $phone, $role_id, $active, $password_hash, $id]);
    } else {
        $stmt = $conn->prepare('UPDATE users SET name = ?, username = ?, email = ?, phone = ?, role_id = ?, active = ? WHERE id = ?');
        $stmt->execute([$name, $username, $email, $phone, $role_id, $active, $id]);
    }
    $stmt = $conn->prepare('SELECT u.*, r.name as role_name FROM users u INNER JOIN roles r ON u.role_id = r.id WHERE u.id = ?');
    $stmt->execute([$id]);
    $updated = $stmt->fetch();
    unset($updated['password_hash']);
    $old_out = $old;
    unset($old_out['password_hash']);
    auditLog('user_updated', 'User', $id, $old_out, $updated);
    api_success($updated, 'User updated', 200);
}

function handle_users_deactivate($params) {
    global $conn;
    $id = (int)($params['id'] ?? 0);
    $stmt = $conn->prepare('SELECT u.*, r.name as role_name FROM users u INNER JOIN roles r ON u.role_id = r.id WHERE u.id = ?');
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) {
        api_error('User not found', 'not_found', 404);
    }
    $stmt = $conn->prepare('UPDATE users SET active = 0 WHERE id = ?');
    $stmt->execute([$id]);
    $old_out = $old;
    unset($old_out['password_hash']);
    auditLog('user_deactivated', 'User', $id, $old_out, ['active' => 0]);
    api_success(null, 'User deactivated', 200);
}

function handle_users_roles($params) {
    global $conn;
    $stmt = $conn->query('SELECT r.*, (SELECT JSON_ARRAYAGG(p.permission_name) FROM permissions p INNER JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = r.id) as perms_json FROM roles r ORDER BY r.id ASC');
    $rows = $stmt->fetchAll();
    $roles = [];
    foreach ($rows as $r) {
        $perms = !empty($r['perms_json']) ? json_decode($r['perms_json'], true) : [];
        if (!is_array($perms)) {
            $perms = [];
        }
        if ($r['name'] === 'Administrator') {
            $stmt2 = $conn->query('SELECT permission_name FROM permissions ORDER BY id ASC');
            $perms = array_column($stmt2->fetchAll(), 'permission_name');
        }
        unset($r['perms_json']);
        $r['perms'] = $perms;
        $roles[] = $r;
    }
    api_success($roles, 'Roles list', 200);
}

function handle_users_permissions($params) {
    global $conn;
    $stmt = $conn->query('SELECT id, permission_name FROM permissions ORDER BY id ASC');
    $rows = $stmt->fetchAll();
    api_success($rows, 'Permissions list', 200);
}

function handle_users_user_permissions($params) {
    global $conn;
    $id = (int)($params['id'] ?? 0);
    $stmt = $conn->prepare('SELECT u.*, r.name as role_name FROM users u INNER JOIN roles r ON u.role_id = r.id WHERE u.id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) {
        api_error('User not found', 'not_found', 404);
    }
    $perms = [];
    if ($user['role_name'] === 'Administrator') {
        $stmt = $conn->query('SELECT permission_name FROM permissions ORDER BY id ASC');
        $perms = array_column($stmt->fetchAll(), 'permission_name');
    } else {
        $stmt = $conn->prepare('SELECT p.permission_name FROM permissions p INNER JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = ? ORDER BY p.id ASC');
        $stmt->execute([(int)$user['role_id']]);
        $perms = array_column($stmt->fetchAll(), 'permission_name');
    }
    api_success($perms, 'User permissions', 200);
}
