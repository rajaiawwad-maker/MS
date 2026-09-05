<?php
function api_success($data = null, $message = 'OK', $code = 200, $pagination = null) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization,Content-Type,X-Requested-With');
        header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
    }
    http_response_code($code);
    $payload = [
        'success' => true,
        'message' => $message,
        'data' => $data,
    ];
    if ($pagination !== null) {
        $payload['pagination'] = $pagination;
    } else {
        $payload['pagination'] = null;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_error($message, $error_code = 'error', $code = 400, $errors = null) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization,Content-Type,X-Requested-With');
        header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
    }
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'error_code' => $error_code,
        'errors' => $errors,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
