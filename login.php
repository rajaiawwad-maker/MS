<?php
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    redirect(SITE_URL . '/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if ($conn && $username !== '' && $password !== '') {
        $stmt = $conn->prepare('SELECT * FROM users WHERE (username = ? OR email = ?) AND active = 1 LIMIT 1');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login_time'] = time();
            $stmt = $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
            $stmt->execute([$user['id']]);
            auditLog('login', 'User', $user['id']);
            if ($remember) {
                $token = generateToken(40);
                $expire = time() + (86400 * 30);
            }
            redirect(SITE_URL . '/index.php');
        } else {
            $error = t('auth.invalid');
        }
    } else {
        $error = t('msg.login_fill_fields');
    }
}
?><!DOCTYPE html>
<html lang="<?= LANG_CODE ?>" dir="<?= IS_RTL ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('auth.login_title') ?> - <?= e(trim((string)getSetting('company_name')) ?: t('brand.name')) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <?php if (IS_RTL): ?>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/rtl.css">
    <style> html[dir="rtl"] body { font-family: 'Tahoma', 'Segoe UI', 'Arial', sans-serif; } </style>
    <?php endif; ?>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .login-card {
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            border: none;
        }
        .login-left {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 3rem 2rem;
        }
        .login-left h2 { font-weight: 700; }
        .login-right { padding: 3rem 2.5rem; }
        .brand-icon {
            font-size: 4rem;
            opacity: 0.9;
        }
        .form-control-lg {
            border-radius: 0.5rem;
            padding: 0.875rem 1.25rem;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }
        .form-control-lg:focus { border-color: #667eea; box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25); }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 0.5rem;
            padding: 0.875rem;
            font-weight: 600;
        }
        .btn-primary:hover { opacity: 0.95; transform: translateY(-1px); }
        .custom-control-label { cursor: pointer; }
        .lang-switcher {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1050;
        }
        html[dir="rtl"] .lang-switcher {
            right: auto;
            left: 1rem;
        }
    </style>
</head>
<body>
<div class="lang-switcher">
    <div class="btn-group btn-group-sm bg-white rounded shadow-sm p-1" role="group" title="<?= t('lang.switch_tooltip') ?>">
        <a href="<?= SITE_URL ?>/change_lang.php?lang=en" class="btn btn-sm <?= LANG_CODE === 'en' ? 'btn-primary' : 'btn-link text-dark' ?>">EN</a>
        <a href="<?= SITE_URL ?>/change_lang.php?lang=ar" class="btn btn-sm <?= LANG_CODE === 'ar' ? 'btn-primary' : 'btn-link text-dark' ?>">ع</a>
    </div>
</div>
<div class="container">
    <div class="row justify-content-center align-items-center">
        <div class="col-lg-10 col-xl-9">
            <div class="card login-card">
                <div class="row no-gutters">
                    <div class="col-md-5 d-none d-md-block login-left text-center">
                        <div class="brand-icon mb-3"><i class="fas fa-compact-disc"></i></div>
                        <h2 class="mb-3"><?= e(trim((string)getSetting('company_name')) ?: t('brand.name')) ?></h2>
                        <p class="opacity-90 mb-5"><?= e(trim((string)getSetting('company_tagline', '')) ?: t('brand.tagline')) ?></p>
                        <hr class="bg-white opacity-30">
                        <div class="mt-4 text-left opacity-90 small" style="<?= IS_RTL ? 'text-align:right; direction:rtl;' : 'text-align:left;' ?>">
                            <p><i class="fas fa-check-circle mr-2 ml-2"></i> <?= t('login.f1_availability') ?></p>
                            <p><i class="fas fa-check-circle mr-2 ml-2"></i> <?= t('login.f2_booking_payment') ?></p>
                            <p><i class="fas fa-check-circle mr-2 ml-2"></i> <?= t('login.f3_calendar_reports') ?></p>
                            <p><i class="fas fa-check-circle mr-2 ml-2"></i> <?= t('login.f4_whatsapp') ?></p>
                        </div>
                    </div>
                    <div class="col-md-7 login-right">
                        <h3 class="mb-1 font-weight-bold"><?= t('login.welcome_back') ?></h3>
                        <p class="text-muted mb-4"><?= t('auth.login_subtitle') ?></p>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= e($error) ?></div>
                        <?php endif; ?>
                        <form method="POST" action="<?= e($_SERVER['PHP_SELF']) ?>">
                            <div class="form-group">
                                <label class="font-weight-semibold"><?= t('login.username_or_email') ?></label>
                                <div class="input-group input-group-lg">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
                                    <input type="text" name="username" class="form-control form-control-lg" placeholder="<?= t('login.enter_username') ?>" required autofocus>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="font-weight-semibold"><?= t('auth.password') ?></label>
                                <div class="input-group input-group-lg">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-lock"></i></span></div>
                                    <input type="password" name="password" class="form-control form-control-lg" placeholder="<?= t('login.enter_password') ?>" required>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" name="remember" class="custom-control-input" id="remember">
                                    <label class="custom-control-label" for="remember"><?= t('login.remember_me') ?></label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg btn-block mb-3"><i class="fas fa-sign-in-alt mr-2 ml-2"></i><?= t('auth.login_btn') ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
