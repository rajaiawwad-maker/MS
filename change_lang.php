<?php
require_once __DIR__ . '/config.php';

$lang = $_GET['lang'] ?? 'en';
setActiveLang($lang);

$back = $_SERVER['HTTP_REFERER'] ?? (SITE_URL . '/index.php');
if (strpos($back, 'change_lang.php') !== false) {
    $back = SITE_URL . '/index.php';
}

header('Location: ' . $back);
exit;
