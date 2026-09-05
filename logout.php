<?php
require_once __DIR__ . '/config.php';
setFlash('success', t('auth.logout_success'));
session_unset();
session_destroy();
redirect(SITE_URL . '/login.php');
