<?php
function ensure_api_tokens_table() {
    global $conn;
    static $ensured = false;
    if ($ensured) return;
    $sql = "CREATE TABLE IF NOT EXISTS api_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        device_name VARCHAR(255) NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(500) NULL,
        last_used_at DATETIME NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_api_tokens_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->exec($sql);
    $ensured = true;
}

function issue_token($user_id, $device_name = null, $expSecs = 604800) {
    global $conn;
    ensure_api_tokens_table();
    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $expires = date('Y-m-d H:i:s', time() + $expSecs);
    $stmt = $conn->prepare("INSERT INTO api_tokens (user_id, token_hash, device_name, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $hash, $device_name, $ip, $ua, $expires]);
    return $raw;
}

function authenticate_by_token() {
    global $conn;
    ensure_api_tokens_table();
    $headers = null;
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }
    if ($headers === false || $headers === null) {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$headerName] = $value;
            }
        }
    }
    $authHeader = null;
    foreach ($headers as $name => $value) {
        if (strcasecmp($name, 'Authorization') === 0) {
            $authHeader = $value;
            break;
        }
    }
    if ($authHeader === null && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }
    if ($authHeader === null) {
        $GLOBALS['CURRENT_API_USER'] = null;
        return null;
    }
    $token = null;
    if (stripos($authHeader, 'Bearer ') === 0) {
        $token = trim(substr($authHeader, 7));
    }
    if (!$token) {
        $GLOBALS['CURRENT_API_USER'] = null;
        return null;
    }
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare("SELECT t.*, u.*, r.name AS role_name FROM api_tokens t INNER JOIN users u ON t.user_id = u.id LEFT JOIN roles r ON u.role_id = r.id WHERE t.token_hash = ? AND t.expires_at >= NOW() AND u.active = 1 LIMIT 1");
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    if (!$row) {
        $GLOBALS['CURRENT_API_USER'] = null;
        return null;
    }
    $GLOBALS['CURRENT_API_USER'] = $row;
    $GLOBALS['CURRENT_API_TOKEN_HASH'] = $hash;
    $_SESSION['user_id'] = $row['id'];
    $updateStmt = $conn->prepare("UPDATE api_tokens SET last_used_at = NOW() WHERE token_hash = ?");
    $updateStmt->execute([$hash]);
    return $row;
}

function currentApiUser() {
    return $GLOBALS['CURRENT_API_USER'] ?? null;
}

function revoke_current_token() {
    global $conn;
    $hash = $GLOBALS['CURRENT_API_TOKEN_HASH'] ?? null;
    if (!$hash) return false;
    $stmt = $conn->prepare("DELETE FROM api_tokens WHERE token_hash = ?");
    $stmt->execute([$hash]);
    return true;
}

function api_require_permission($permName) {
    if (!hasPermission($permName)) {
        auditSecurity('permission_denied', ['perm' => $permName, 'path' => $_SERVER['PATH_INFO'] ?? '']);
        api_error('Forbidden', 'forbidden', 403);
    }
}

function api_get_json() {
    return $GLOBALS['API_JSON'] ?? [];
}
