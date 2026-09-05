<?php
require_once __DIR__ . '/config.php';

$lang = $_GET['lang'] ?? 'en';
setActiveLang($lang);

$back = $_SERVER['HTTP_REFERER'] ?? '';
$default = SITE_URL . '/index.php';
if ($back === '') {
    $back = $default;
} else {
    $backHost = parse_url($back, PHP_URL_HOST);
    $siteHost = parse_url(SITE_URL, PHP_URL_HOST);
    $backScheme = parse_url($back, PHP_URL_SCHEME);
    if ($backHost === null) {
        // relative path, safe
    } elseif ($siteHost !== null && strcasecmp($backHost, $siteHost) !== 0) {
        // cross-origin redirect blocked
        $back = $default;
    } elseif ($backScheme !== null && !in_array(strtolower($backScheme), ['http','https'], true)) {
        $back = $default;
    }
    if (strpos($back, 'change_lang.php') !== false) {
        $back = $default;
    }
}
header('Location: ' . $back);
exit;
