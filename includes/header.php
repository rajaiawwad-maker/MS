<?php
require_once dirname(__DIR__) . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
$user = currentUser();
$page_title = $page_title ?? t('title.dashboard');
?><!DOCTYPE html>
<html lang="<?= LANG_CODE ?>" dir="<?= IS_RTL ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - <?= e(trim((string)getSetting('company_name')) ?: t('brand.name')) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css"><!-- SRI TODO -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css"><!-- SRI TODO -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css"><!-- SRI TODO -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=<?= md5_file(__DIR__ . '/../assets/css/style.css') ?>">
    <?php if (IS_RTL): ?>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/rtl.css?v=<?= md5_file(__DIR__ . '/../assets/css/rtl.css') ?>">
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script><!-- SRI TODO -->
</head>
<body class="bg-light">
<div class="wrapper">
    <nav id="sidebar">
        <div class="sidebar-header">
            <div class="brand-logo">
                <div class="brand-icon"><i class="fas fa-compact-disc fa-spin-slow"></i></div>
                <div class="brand-text">
                    <h5 class="brand-title"><?= e(trim((string)getSetting('company_name')) ?: t('brand.name')) ?></h5>
                    <small class="brand-subtitle"><?= e(trim((string)getSetting('company_tagline', '')) ?: t('brand.tagline')) ?></small>
                </div>
            </div>
        </div>
        <div class="sidebar-menu">
        <ul class="list-unstyled components">
            <?php $nav = $active_nav ?? ''; ?>
            <li class="nav-item <?= $nav === 'dashboard' ? 'active' : '' ?>" data-nav="dashboard">
                <a href="<?= SITE_URL ?>/index.php" class="nav-link"><span class="nav-icon nav-icon-dashboard"><i class="fas fa-tachometer-alt"></i></span><span class="nav-text"><?= t('nav.dashboard') ?></span></a>
            </li>
            <li class="nav-item <?= $nav === 'calendar' ? 'active' : '' ?>" data-nav="calendar">
                <a href="<?= SITE_URL ?>/calendar.php" class="nav-link"><span class="nav-icon nav-icon-calendar"><i class="fas fa-calendar-alt"></i></span><span class="nav-text"><?= t('nav.calendar') ?></span></a>
            </li>
            <li class="nav-item <?= $nav === 'bookings' ? 'active' : '' ?>" data-nav="bookings">
                <a href="<?= SITE_URL ?>/bookings.php" class="nav-link"><span class="nav-icon nav-icon-bookings"><i class="fas fa-clipboard-list"></i></span><span class="nav-text"><?= t('nav.bookings') ?></span></a>
            </li>
            <li class="nav-item <?= $nav === 'payments' ? 'active' : '' ?>" data-nav="payments">
                <a href="<?= SITE_URL ?>/payments.php" class="nav-link"><span class="nav-icon nav-icon-payments"><i class="fas fa-money-check-alt"></i></span><span class="nav-text"><?= t('nav.payments') ?></span></a>
            </li>
            <li class="nav-item <?= $nav === 'inventory' ? 'active show' : '' ?>" data-nav="inventory">
                <a href="#inventorySubmenu" data-toggle="collapse" aria-expanded="<?= $nav === 'inventory' ? 'true' : 'false' ?>" class="nav-link dropdown-toggle"><span class="nav-icon nav-icon-inventory"><i class="fas fa-boxes"></i></span><span class="nav-text"><?= t('nav.inventory') ?></span><span class="nav-caret"><i class="fas fa-chevron-down"></i></span></a>
                <ul class="collapse <?= $nav === 'inventory' ? 'show' : '' ?> list-unstyled" id="inventorySubmenu">
                    <li><a href="<?= SITE_URL ?>/categories.php"><i class="fas fa-angle-right"></i><?= t('nav.categories') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/item_types.php"><i class="fas fa-angle-right"></i><?= t('nav.item_types') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/inventory_items.php"><i class="fas fa-angle-right"></i><?= t('nav.inventory_items') ?></a></li>
                </ul>
            </li>
            <li class="nav-item <?= $nav === 'clients' ? 'active' : '' ?>" data-nav="clients">
                <a href="<?= SITE_URL ?>/clients.php" class="nav-link"><span class="nav-icon nav-icon-clients"><i class="fas fa-users"></i></span><span class="nav-text"><?= t('nav.clients') ?></span></a>
            </li>
            <li class="nav-item <?= $nav === 'expenses' ? 'active show' : '' ?>" data-nav="expenses">
                <a href="#expenseSubmenu" data-toggle="collapse" aria-expanded="<?= $nav === 'expenses' ? 'true' : 'false' ?>" class="nav-link dropdown-toggle"><span class="nav-icon nav-icon-expenses"><i class="fas fa-money-bill-wave"></i></span><span class="nav-text"><?= t('nav.expenses') ?></span><span class="nav-caret"><i class="fas fa-chevron-down"></i></span></a>
                <ul class="collapse <?= $nav === 'expenses' ? 'show' : '' ?> list-unstyled" id="expenseSubmenu">
                    <li><a href="<?= SITE_URL ?>/expense_types.php"><i class="fas fa-angle-right"></i><?= t('nav.expense_types') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/expenses.php"><i class="fas fa-angle-right"></i><?= t('nav.expenses') ?></a></li>
                </ul>
            </li>
            <li class="nav-item <?= $nav === 'reports' ? 'active show' : '' ?>" data-nav="reports">
                <a href="#reportSubmenu" data-toggle="collapse" aria-expanded="<?= $nav === 'reports' ? 'true' : 'false' ?>" class="nav-link dropdown-toggle"><span class="nav-icon nav-icon-reports"><i class="fas fa-chart-bar"></i></span><span class="nav-text"><?= t('nav.reports') ?></span><span class="nav-caret"><i class="fas fa-chevron-down"></i></span></a>
                <ul class="collapse <?= $nav === 'reports' ? 'show' : '' ?> list-unstyled" id="reportSubmenu">
                    <li><a href="<?= SITE_URL ?>/reports_bookings.php"><i class="fas fa-angle-right"></i><?= t('nav.bookings_report') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/reports_expenses.php"><i class="fas fa-angle-right"></i><?= t('nav.expenses_report') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/reports_financial.php"><i class="fas fa-angle-right"></i><?= t('nav.financial_report') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/reports_inventory.php"><i class="fas fa-angle-right"></i><?= t('nav.inventory_report') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/reports_client_statement.php"><i class="fas fa-angle-right"></i><?= t('nav.client_statement') ?></a></li>
                </ul>
            </li>
            <?php if (hasPermission('manage_setup')): ?>
            <li class="nav-item <?= $nav === 'setup' ? 'active show' : '' ?>" data-nav="setup">
                <a href="#setupSubmenu" data-toggle="collapse" aria-expanded="<?= $nav === 'setup' ? 'true' : 'false' ?>" class="nav-link dropdown-toggle"><span class="nav-icon nav-icon-setup"><i class="fas fa-cogs"></i></span><span class="nav-text"><?= t('nav.setup') ?></span><span class="nav-caret"><i class="fas fa-chevron-down"></i></span></a>
                <ul class="collapse <?= $nav === 'setup' ? 'show' : '' ?> list-unstyled" id="setupSubmenu">
                    <li><a href="<?= SITE_URL ?>/categories.php"><i class="fas fa-angle-right"></i><?= t('nav.categories') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/item_types.php"><i class="fas fa-angle-right"></i><?= t('nav.item_types') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/expense_types.php"><i class="fas fa-angle-right"></i><?= t('nav.expense_types') ?></a></li>
                    <li><a href="<?= SITE_URL ?>/settings.php"><i class="fas fa-angle-right"></i><?= t('nav.system_settings') ?></a></li>
                </ul>
            </li>
            <?php endif; ?>
            <?php if (hasPermission('manage_users')): ?>
            <li class="nav-item <?= $nav === 'users' ? 'active' : '' ?>" data-nav="users">
                <a href="<?= SITE_URL ?>/users.php" class="nav-link"><span class="nav-icon nav-icon-users"><i class="fas fa-user-shield"></i></span><span class="nav-text"><?= t('nav.users_permissions') ?></span></a>
            </li>
            <?php endif; ?>
            <?php if (hasPermission('view_audit_logs')): ?>
            <li class="nav-item <?= $nav === 'audit' ? 'active' : '' ?>" data-nav="audit">
                <a href="<?= SITE_URL ?>/audit_logs.php" class="nav-link"><span class="nav-icon nav-icon-audit"><i class="fas fa-history"></i></span><span class="nav-text"><?= t('nav.audit_log') ?></span></a>
            </li>
            <?php endif; ?>
        </ul>
        </div>
        <div class="sidebar-footer d-none d-md-block">
            <div class="user-card">
                <div class="user-avatar"><i class="fas fa-user"></i></div>
                <div class="user-info">
                    <div class="user-name"><?= e($user['name'] ?? 'User') ?></div>
                    <small class="user-role"><?= e($user['role_name'] ?? 'Staff') ?></small>
                </div>
                <a href="<?= SITE_URL ?>/logout.php" class="user-logout" title="<?= t('auth.logout') ?>"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </nav>
    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <div id="content">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
            <div class="container-fluid">
                <button type="button" id="sidebarCollapse" class="btn btn-sidebar-toggle mr-2" title="<?= t('common.toggle_sidebar') ?>">
                    <i class="fas fa-bars"></i>
                </button>
                <form class="form-inline d-none d-md-inline-block mr-auto ml-md-3" method="GET" action="<?= SITE_URL ?>/search.php">
                    <div class="input-group input-group-sm">
                        <input class="form-control" type="search" name="q" placeholder="<?= t('common.search_placeholder') ?>" aria-label="Search" style="width:320px">
                        <div class="input-group-append"><button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button></div>
                    </div>
                </form>
                <ul class="nav navbar-nav ml-auto flex-row align-items-center">
                    <li class="nav-item">
                        <div class="btn-group btn-group-sm mr-2" role="group" title="<?= t('lang.switch_tooltip') ?>">
                            <a href="<?= SITE_URL ?>/change_lang.php?lang=en" class="btn <?= LANG_CODE === 'en' ? 'btn-primary' : 'btn-outline-secondary' ?>">EN</a>
                            <a href="<?= SITE_URL ?>/change_lang.php?lang=ar" class="btn <?= LANG_CODE === 'ar' ? 'btn-primary' : 'btn-outline-secondary' ?>">ع</a>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-user-circle mr-1"></i>
                            <span class="d-none d-sm-inline"><?= e($user['name'] ?? '') ?></span>
                            <span class="badge badge-info ml-1"><?= e($user['role_name'] ?? '') ?></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                            <a class="dropdown-item" href="<?= SITE_URL ?>/profile.php"><i class="fas fa-user mr-2"></i><?= t('auth.my_profile') ?></a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="<?= SITE_URL ?>/logout.php"><i class="fas fa-sign-out-alt mr-2"></i><?= t('auth.logout') ?></a>
                        </div>
                    </li>
                </ul>
            </div>
        </nav>
        <div class="container-fluid p-4">
            <?php echo flashMessages(); ?>
