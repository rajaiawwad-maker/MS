<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization,Content-Type,X-Requested-With');
header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/includes/bootstrap.php';

$rawInput = file_get_contents('php://input');
if ($rawInput === false) $rawInput = '';
$json = json_decode($rawInput, true);
if ($json === null && trim($rawInput) !== '') {
    $json = [];
}
if ($json === null) $json = [];
$GLOBALS['API_JSON'] = $json;

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri = $_SERVER['PATH_INFO'] ?? '';
if ($requestUri === '' || $requestUri === '/') {
    if (isset($_SERVER['SCRIPT_NAME'])) {
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $pos = strpos($requestUri, '?');
        if ($pos !== false) {
            $requestUri = substr($requestUri, 0, $pos);
        }
        if (strpos($requestUri, $scriptName) === 0) {
            $requestUri = substr($requestUri, strlen($scriptName));
        }
    }
}
if ($requestUri === '' || $requestUri === false) {
    $requestUri = '/';
}
$requestUri = '/' . ltrim($requestUri, '/');

$routes = $GLOBALS['API_ROUTES'] ?? [];
$matchedRoute = null;
$matchedParams = [];

foreach ($routes as $routeKey => $route) {
    list($method, $pattern) = explode(' ', $routeKey, 2);
    if ($method !== $requestMethod) {
        continue;
    }
    $pattern = '/' . ltrim($pattern, '/');
    $regexParts = [];
    $segments = explode('/', trim($pattern, '/'));
    foreach ($segments as $seg) {
        if (strpos($seg, ':') === 0) {
            $paramName = substr($seg, 1);
            $regexParts[] = '(?P<' . $paramName . '>[^/]+)';
        } else {
            $regexParts[] = preg_quote($seg, '#');
        }
    }
    $regex = '#^/?' . implode('/', $regexParts) . '/?$#';
    if (preg_match($regex, $requestUri, $matches)) {
        $matchedRoute = $route;
        $matchedParams = [];
        foreach ($matches as $k => $v) {
            if (is_string($k)) {
                $matchedParams[$k] = $v;
            }
        }
        if (isset($_GET) && is_array($_GET)) {
            foreach ($_GET as $gk => $gv) {
                if (!isset($matchedParams[$gk])) {
                    $matchedParams[$gk] = $gv;
                }
            }
        }
        break;
    }
}

if ($matchedRoute === null) {
    api_error('Not Found', 'not_found', 404);
}

$handlerStr = $matchedRoute['handler'];
$isPublic = !empty($matchedRoute['public']);
$perm = $matchedRoute['perm'] ?? null;

if ($handlerStr === 'auth.login') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    enforce_login_throttle('api_login_' . $ip);
    $throttleFile = sys_get_temp_dir() . '/api_login_throttle_' . md5($ip) . '.json';
    $window = 60;
    $maxAttempts = 5;
    $now = time();
    $records = [];
    if (file_exists($throttleFile)) {
        $data = @file_get_contents($throttleFile);
        if ($data !== false) {
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                $records = $decoded;
            }
        }
    }
    $records = array_filter($records, function ($t) use ($now, $window) {
        return ($now - (int)$t) < $window;
    });
    if (count($records) >= $maxAttempts) {
        api_error('Too many failed login attempts. Please try again later.', 'too_many_attempts', 429);
    }
    $GLOBALS['API_LOGIN_THROTTLE_FILE'] = $throttleFile;
    $GLOBALS['API_LOGIN_THROTTLE_RECORDS'] = $records;
}

if (!$isPublic) {
    $user = authenticate_by_token();
    if ($user === null) {
        api_error('Unauthenticated', 'unauthenticated', 401);
    }
}

if ($perm !== null && !$isPublic) {
    api_require_permission($perm);
}

list($filePart, $funcPart) = explode('.', $handlerStr, 2);
$handlerFile = __DIR__ . '/endpoints/' . $filePart . '.php';
$handlerFunc = 'handle_' . $filePart . '_' . $funcPart;

if (!file_exists($handlerFile)) {
    api_error('Handler file not implemented', 'not_implemented', 501);
}

require_once $handlerFile;

if (!function_exists($handlerFunc)) {
    api_error('Handler function not implemented', 'not_implemented', 501);
}

call_user_func($handlerFunc, $matchedParams);
exit;
