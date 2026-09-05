<?php
function handle_public_confirm_get($params) {
    global $conn;
    $token = isset($params['token']) ? (string)$params['token'] : '';
    if ($token === '' || strlen($token) < 10) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        auditSecurity('invalid_public_confirmation_token', [
            'token_prefix' => substr($token, 0, 4),
            'ip' => $ip,
            'ua' => $ua,
        ]);
        api_error('Not Found', 'not_found', 404);
    }
    $stmt = $conn->prepare('SELECT b.*, c.name client_name, c.phone client_phone, c.email client_email
        FROM bookings b INNER JOIN clients c ON c.id=b.client_id
        WHERE b.customer_confirmation_token=? LIMIT 1');
    $stmt->execute([$token]);
    $booking = $stmt->fetch();
    if (!$booking || $booking['status'] === 'Canceled') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        auditSecurity('invalid_public_confirmation_token', [
            'token_prefix' => substr($token, 0, 4),
            'ip' => $ip,
            'ua' => $ua,
        ]);
        if (!$booking) {
            api_error('Booking not found', 'booking_not_found', 404);
        } else {
            api_error('Booking canceled', 'booking_canceled', 404);
        }
    }
    $bid = (int)$booking['id'];
    $stmt = $conn->prepare('SELECT bi.*, it.name item_type_name, cat.name category_name
        FROM booking_items bi LEFT JOIN item_types it ON bi.item_type_id=it.id
        LEFT JOIN categories cat ON it.category_id=cat.id
        WHERE booking_id=?');
    $stmt->execute([$bid]);
    $items = $stmt->fetchAll();
    $currency_symbol = defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : 'JOD';
    $company = [
        'company_name' => getSetting('company_name', 'DJ RAK'),
        'company_address' => getSetting('company_address', ''),
        'company_phone' => getSetting('company_phone', ''),
        'company_tax_id' => getSetting('company_tax_id', ''),
        'currency' => getSetting('currency_symbol', $currency_symbol),
    ];
    $collected = getBookingCollectedAmount($bid);
    $pending = getBookingPendingAmount($bid);
    $customer_confirmed_at = isset($booking['customer_confirmed_at']) && $booking['customer_confirmed_at'] !== '' && $booking['customer_confirmed_at'] !== null ? $booking['customer_confirmed_at'] : null;
    $customer_response = $customer_confirmed_at !== null ? ($booking['customer_response'] ?? 'confirmed') : null;
    api_success([
        'booking' => $booking,
        'client' => [
            'name' => $booking['client_name'],
            'phone' => $booking['client_phone'],
            'email' => $booking['client_email'],
        ],
        'items' => $items,
        'company' => $company,
        'totals' => [
            'quoted_amount' => (float)$booking['quoted_amount'],
            'dj_rak_amount' => (float)$booking['dj_rak_amount'],
            'collected_amount' => $collected,
            'pending_amount' => $pending,
        ],
        'customer_response' => $customer_response,
        'customer_confirmed_at' => $customer_confirmed_at,
    ], 'OK', 200);
}

function handle_public_confirm_post($params) {
    global $conn;
    $token = isset($params['token']) ? (string)$params['token'] : '';
    if ($token === '' || strlen($token) < 10) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        auditSecurity('invalid_public_confirmation_token', [
            'token_prefix' => substr($token, 0, 4),
            'ip' => $ip,
            'ua' => $ua,
        ]);
        api_error('Not Found', 'not_found', 404);
    }
    $body = api_get_json();
    $action = isset($body['action']) ? (string)$body['action'] : '';
    $changeDetails = isset($body['change_details']) ? $body['change_details'] : null;
    if (!in_array($action, ['confirm','change','decline'], true)) {
        api_error('Invalid action', 'invalid_action', 400);
    }
    $stmt = $conn->prepare('SELECT b.*, c.name client_name, c.phone client_phone, c.email client_email
        FROM bookings b INNER JOIN clients c ON c.id=b.client_id
        WHERE b.customer_confirmation_token=? LIMIT 1');
    $stmt->execute([$token]);
    $booking = $stmt->fetch();
    if (!$booking || $booking['status'] === 'Canceled') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        auditSecurity('invalid_public_confirmation_token', [
            'token_prefix' => substr($token, 0, 4),
            'ip' => $ip,
            'ua' => $ua,
        ]);
        if (!$booking) {
            api_error('Booking not found', 'booking_not_found', 404);
        } else {
            api_error('Booking canceled', 'booking_canceled', 404);
        }
    }
    $bid = (int)$booking['id'];
    $responseMap = ['confirm' => 'Confirmed', 'change' => 'Change Requested', 'decline' => 'Declined'];
    $customer_response_value = $responseMap[$action];
    $already = $booking['customer_confirmed_at'] !== null && $booking['customer_confirmed_at'] !== '';
    $resp = $booking['customer_response'] ?? null;
    if ($action === 'confirm' && $already && ($resp === null || $resp === 'Confirmed' || $resp === 'confirmed')) {
        $stmt = $conn->prepare('SELECT bi.*, it.name item_type_name, cat.name category_name
            FROM booking_items bi LEFT JOIN item_types it ON bi.item_type_id=it.id
            LEFT JOIN categories cat ON it.category_id=cat.id
            WHERE booking_id=?');
        $stmt->execute([$bid]);
        $items = $stmt->fetchAll();
        $currency_symbol = defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : 'JOD';
        $company = [
            'company_name' => getSetting('company_name', 'DJ RAK'),
            'company_address' => getSetting('company_address', ''),
            'company_phone' => getSetting('company_phone', ''),
            'company_tax_id' => getSetting('company_tax_id', ''),
            'currency' => getSetting('currency_symbol', $currency_symbol),
        ];
        $collected = getBookingCollectedAmount($bid);
        $pending = getBookingPendingAmount($bid);
        $stmt = $conn->prepare('SELECT b.*, c.name client_name, c.phone client_phone, c.email client_email
            FROM bookings b INNER JOIN clients c ON c.id=b.client_id WHERE b.id=? LIMIT 1');
        $stmt->execute([$bid]);
        $finalBooking = $stmt->fetch();
        $customer_confirmed_at = isset($finalBooking['customer_confirmed_at']) && $finalBooking['customer_confirmed_at'] !== '' && $finalBooking['customer_confirmed_at'] !== null ? $finalBooking['customer_confirmed_at'] : null;
        $customer_response = $customer_confirmed_at !== null ? ($finalBooking['customer_response'] ?? 'confirmed') : null;
        api_success([
            'booking' => $finalBooking,
            'client' => [
                'name' => $finalBooking['client_name'],
                'phone' => $finalBooking['client_phone'],
                'email' => $finalBooking['client_email'],
            ],
            'items' => $items,
            'company' => $company,
            'totals' => [
                'quoted_amount' => (float)$finalBooking['quoted_amount'],
                'dj_rak_amount' => (float)$finalBooking['dj_rak_amount'],
                'collected_amount' => $collected,
                'pending_amount' => $pending,
            ],
            'customer_response' => $customer_response,
            'customer_confirmed_at' => $customer_confirmed_at,
        ], 'Already confirmed', 200);
    }
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare('UPDATE bookings SET customer_response=?, customer_confirmed_at=NOW() WHERE id=?');
        $stmt->execute([$customer_response_value, $bid]);
        if ($action === 'confirm') {
            $stmt = $conn->prepare("UPDATE bookings SET status='Confirmed'
                WHERE status NOT IN ('Event Completed','Closed','Canceled') AND id=?");
            $stmt->execute([$bid]);
        } elseif ($action === 'change') {
            $stmt = $conn->prepare("UPDATE bookings SET status='Change Requested'
                WHERE status NOT IN ('Event Completed','Closed','Canceled') AND id=?");
            $stmt->execute([$bid]);
        }
        updateBookingPaymentStatus($bid);
        $audit_detail = ['action' => $action];
        if ($changeDetails !== null) {
            $audit_detail['change_details'] = $changeDetails;
        }
        auditLog('public_confirm_action', 'Booking', $bid, null, $audit_detail);
        $conn->commit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        api_error('Failed to process confirmation: ' . $e->getMessage(), 'internal_error', 500);
    }
    $stmt = $conn->prepare('SELECT bi.*, it.name item_type_name, cat.name category_name
        FROM booking_items bi LEFT JOIN item_types it ON bi.item_type_id=it.id
        LEFT JOIN categories cat ON it.category_id=cat.id
        WHERE booking_id=?');
    $stmt->execute([$bid]);
    $items = $stmt->fetchAll();
    $currency_symbol = defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : 'JOD';
    $company = [
        'company_name' => getSetting('company_name', 'DJ RAK'),
        'company_address' => getSetting('company_address', ''),
        'company_phone' => getSetting('company_phone', ''),
        'company_tax_id' => getSetting('company_tax_id', ''),
        'currency' => getSetting('currency_symbol', $currency_symbol),
    ];
    $collected = getBookingCollectedAmount($bid);
    $pending = getBookingPendingAmount($bid);
    $stmt = $conn->prepare('SELECT b.*, c.name client_name, c.phone client_phone, c.email client_email
        FROM bookings b INNER JOIN clients c ON c.id=b.client_id WHERE b.id=? LIMIT 1');
    $stmt->execute([$bid]);
    $finalBooking = $stmt->fetch();
    $customer_confirmed_at = isset($finalBooking['customer_confirmed_at']) && $finalBooking['customer_confirmed_at'] !== '' && $finalBooking['customer_confirmed_at'] !== null ? $finalBooking['customer_confirmed_at'] : null;
    $customer_response = $customer_confirmed_at !== null ? ($finalBooking['customer_response'] ?? 'confirmed') : null;
    api_success([
        'booking' => $finalBooking,
        'client' => [
            'name' => $finalBooking['client_name'],
            'phone' => $finalBooking['client_phone'],
            'email' => $finalBooking['client_email'],
        ],
        'items' => $items,
        'company' => $company,
        'totals' => [
            'quoted_amount' => (float)$finalBooking['quoted_amount'],
            'dj_rak_amount' => (float)$finalBooking['dj_rak_amount'],
            'collected_amount' => $collected,
            'pending_amount' => $pending,
        ],
        'customer_response' => $customer_response,
        'customer_confirmed_at' => $customer_confirmed_at,
    ], 'OK', 200);
}
