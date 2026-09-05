<?php
function handle_profile_get($params) {
    global $conn;
    $user = currentApiUser();
    if (!$user) {
        api_error('Unauthenticated', 'unauthenticated', 401);
    }

    $roleName = $user['role_name'] ?? null;
    if ($roleName === null) {
        $stmt = $conn->prepare("SELECT name FROM roles WHERE id = ?");
        $stmt->execute([$user['role_id']]);
        $roleName = $stmt->fetchColumn();
    }

    $perms = [];
    if ($roleName === 'Administrator') {
        $stmt = $conn->query("SELECT permission_name FROM permissions ORDER BY id");
        $perms = array_column($stmt->fetchAll(), 'permission_name');
    } else {
        $stmt = $conn->prepare("SELECT p.permission_name FROM permissions p
            LEFT JOIN role_permissions rp ON rp.permission_id = p.id
            WHERE rp.role_id = ?");
        $stmt->execute([$user['role_id']]);
        $perms = array_column($stmt->fetchAll(), 'permission_name');
    }

    $langCode = defined('LANG_CODE') ? LANG_CODE : 'en';

    $responseUser = [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'username' => $user['username'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'role_id' => (int)$user['role_id'],
        'role_name' => $roleName,
        'active' => (bool)$user['active'],
        'permissions' => $perms,
        'lang' => $langCode,
    ];

    api_success($responseUser, 'Profile retrieved', 200);
}

function handle_profile_put($params) {
    global $conn;
    $user = currentApiUser();
    if (!$user) {
        api_error('Unauthenticated', 'unauthenticated', 401);
    }

    $body = api_get_json();
    $errors = [];

    $name = isset($body['name']) ? trim($body['name']) : $user['name'];
    $email = isset($body['email']) ? trim($body['email']) : $user['email'];
    $phone = isset($body['phone']) ? trim($body['phone']) : $user['phone'];

    if ($name === '') {
        $errors['name'][] = 'Name is required';
    }

    if ($email !== '' && $email !== null) {
        api_validate_email($email, 'email', $errors);
    }

    if ($email !== '' && $email !== null) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, (int)$user['id']]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors['email'][] = 'Email is already in use by another user';
        }
    }

    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }

    $old = [
        'name' => $user['name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
    ];

    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
    $stmt->execute([
        $name === '' ? null : $name,
        $email === '' ? null : $email,
        $phone === '' ? null : $phone,
        (int)$user['id'],
    ]);

    $stmt = $conn->prepare("SELECT id, name, username, email, phone, role_id, active FROM users WHERE id = ?");
    $stmt->execute([(int)$user['id']]);
    $updated = $stmt->fetch();

    $new = [
        'name' => $updated['name'],
        'email' => $updated['email'],
        'phone' => $updated['phone'],
    ];

    auditLog('profile_updated', 'User', (int)$user['id'], $old, $new);

    api_success([
        'id' => (int)$updated['id'],
        'name' => $updated['name'],
        'username' => $updated['username'],
        'email' => $updated['email'],
        'phone' => $updated['phone'],
        'role_id' => (int)$updated['role_id'],
        'active' => (bool)$updated['active'],
    ], 'Profile updated successfully', 200);
}

function handle_profile_password($params) {
    global $conn;
    $user = currentApiUser();
    if (!$user) {
        api_error('Unauthenticated', 'unauthenticated', 401);
    }

    $body = api_get_json();
    $errors = [];

    api_validate_required(['old_password', 'new_password', 'confirm_password'], $body, $errors);

    if (empty($errors)) {
        api_validate_min8_password($body['new_password'], 'new_password', $errors);
        if ($body['new_password'] !== $body['confirm_password']) {
            if (!isset($errors['confirm_password'])) {
                $errors['confirm_password'] = [];
            }
            $errors['confirm_password'][] = 'Passwords do not match';
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([(int)$user['id']]);
        $hash = $stmt->fetchColumn();
        if (!password_verify($body['old_password'], $hash)) {
            if (!isset($errors['old_password'])) {
                $errors['old_password'] = [];
            }
            $errors['old_password'][] = 'Current password is incorrect';
        }
    }

    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }

    $newHash = password_hash($body['new_password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$newHash, (int)$user['id']]);

    auditSecurity('password_changed', ['user_id' => (int)$user['id']]);

    api_success(null, 'Password updated successfully', 200);
}
