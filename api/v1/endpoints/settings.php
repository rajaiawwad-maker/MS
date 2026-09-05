<?php
function handle_settings_get($params) {
    global $conn;
    $stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings ORDER BY setting_key");
    $rows = $stmt->fetchAll();
    $data = [];
    foreach ($rows as $row) {
        $data[$row['setting_key']] = $row['setting_value'];
    }
    $defaults = [
        'currency_symbol' => getSetting('currency_symbol', defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : ''),
        'company_name' => getSetting('company_name', 'DJ RAK'),
        'company_address' => getSetting('company_address', ''),
        'company_phone' => getSetting('company_phone', ''),
        'company_tax_id' => getSetting('company_tax_id', ''),
        'default_lang' => getSetting('default_lang', 'en'),
        'booking_prefix' => getSetting('booking_prefix', defined('BOOKING_PREFIX') ? BOOKING_PREFIX : 'BK'),
        'date_format' => getSetting('date_format', defined('DATE_FORMAT') ? DATE_FORMAT : 'd/m/Y'),
        'datetime_format' => getSetting('datetime_format', defined('DATETIME_FORMAT') ? DATETIME_FORMAT : 'd/m/Y H:i'),
    ];
    $merged = array_merge($defaults, $data);
    api_success($merged);
}

function handle_settings_put($params) {
    global $conn;
    $body = api_get_json();
    if (empty($body) || !is_array($body)) {
        $errors = ['body' => ['Request body is required and must be a non-empty object']];
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }
    $errors = [];
    foreach ($body as $key => $value) {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_.]{0,99}$/', $key)) {
            if (!isset($errors[$key])) {
                $errors[$key] = [];
            }
            $errors[$key][] = 'Invalid setting key format';
        }
        if (strlen((string)$value) > 65535) {
            if (!isset($errors[$key])) {
                $errors[$key] = [];
            }
            $errors[$key][] = 'Setting value exceeds maximum length of 65535 characters';
        }
    }
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
    foreach ($body as $key => $value) {
        $stmt->execute([$key, (string)$value]);
    }
    auditLog('settings_updated', 'SystemSetting', null, null, $body);
    $stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings ORDER BY setting_key");
    $rows = $stmt->fetchAll();
    $data = [];
    foreach ($rows as $row) {
        $data[$row['setting_key']] = $row['setting_value'];
    }
    $defaults = [
        'currency_symbol' => getSetting('currency_symbol', defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : ''),
        'company_name' => getSetting('company_name', 'DJ RAK'),
        'company_address' => getSetting('company_address', ''),
        'company_phone' => getSetting('company_phone', ''),
        'company_tax_id' => getSetting('company_tax_id', ''),
        'default_lang' => getSetting('default_lang', 'en'),
        'booking_prefix' => getSetting('booking_prefix', defined('BOOKING_PREFIX') ? BOOKING_PREFIX : 'BK'),
        'date_format' => getSetting('date_format', defined('DATE_FORMAT') ? DATE_FORMAT : 'd/m/Y'),
        'datetime_format' => getSetting('datetime_format', defined('DATETIME_FORMAT') ? DATETIME_FORMAT : 'd/m/Y H:i'),
    ];
    $merged = array_merge($defaults, $data);
    api_success($merged, 'Settings updated successfully', 200);
}
