<?php
function handle_auth_login($params) {
    global $conn;
    $body = api_get_json();
    $errors = [];
    api_validate_required(['username', 'password'], $body, $errors);
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }
    $username = trim($body['username']);
    $password = $body['password'];
    $deviceName = isset($body['device_name']) ? trim($body['device_name']) : null;

    enforce_login_throttle($username);

    $stmt = $conn->prepare("SELECT id, username, email, phone, name, role_id, password_hash, active FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || (int)$user['active'] !== 1 || !password_verify($password, $user['password_hash'])) {
        record_failed_login($username);
        $throttleFile = $GLOBALS['API_LOGIN_THROTTLE_FILE'] ?? null;
        $records = $GLOBALS['API_LOGIN_THROTTLE_RECORDS'] ?? [];
        if ($throttleFile !== null) {
            $records[] = time();
            @file_put_contents($throttleFile, json_encode($records));
        }
        api_error('Invalid credentials', 'invalid_credentials', 401);
    }

    reset_login_attempts($username);
    $throttleFile = $GLOBALS['API_LOGIN_THROTTLE_FILE'] ?? null;
    if ($throttleFile !== null && file_exists($throttleFile)) {
        @unlink($throttleFile);
    }

    $rawToken = issue_token($user['id'], $deviceName);

    $stmt = $conn->prepare("SELECT name FROM roles WHERE id = ?");
    $stmt->execute([$user['role_id']]);
    $roleName = $stmt->fetchColumn();

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

    auditLog('logged_in', 'User', (int)$user['id'], null, $responseUser);

    api_success([
        'token_type' => 'Bearer',
        'access_token' => $rawToken,
        'expires_at' => date('Y-m-d H:i:s', time() + 604800),
        'user' => $responseUser,
    ], 'Login successful', 200);
}

function handle_auth_logout($params) {
    $user = currentApiUser();
    revoke_current_token();
    if ($user) {
        auditLog('logged_out', 'User', (int)$user['id'], null, null);
    }
    api_success(null, 'Logged out', 200);
}

function handle_auth_me($params) {
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

    api_success($responseUser, 'Current user', 200);
}
