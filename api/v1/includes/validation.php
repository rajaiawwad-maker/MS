<?php
function api_validate_required(array $fields, $source, &$errors) {
    foreach ($fields as $field) {
        if (!isset($source[$field]) || trim((string)$source[$field]) === '') {
            if (!isset($errors[$field])) {
                $errors[$field] = [];
            }
            $errors[$field][] = 'Field is required';
        }
    }
}

function api_validate_min8_password($pw, $field, &$errors) {
    if (strlen(trim($pw)) < 8) {
        if (!isset($errors[$field])) {
            $errors[$field] = [];
        }
        $errors[$field][] = 'Password must be at least 8 characters';
    }
}

function api_validate_email($email, $field, &$errors) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if (!isset($errors[$field])) {
            $errors[$field] = [];
        }
        $errors[$field][] = 'Invalid email format';
    }
}

function api_validate_date($d, $field, &$errors, $format = 'Y-m-d') {
    $dt = DateTime::createFromFormat($format, $d);
    if (!$dt || $dt->format($format) !== $d) {
        if (!isset($errors[$field])) {
            $errors[$field] = [];
        }
        $errors[$field][] = 'Invalid date format, expected ' . $format;
    }
}
