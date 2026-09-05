<?php
require_once __DIR__ . '/config.php';

$token = trim($_GET['token'] ?? '');
if ($token === '') {
    http_response_code(404);
    die(t('cf.invalid_link'));
}

$stmt = $conn->prepare("SELECT b.*, c.name as client_name, c.phone as client_phone FROM bookings b
    INNER JOIN clients c ON b.client_id = c.id
    WHERE b.customer_confirmation_token = ? LIMIT 1");
$stmt->execute([$token]);
$booking = $stmt->fetch();

if (!$booking) {
    http_response_code(404);
    die(t('cf.not_found'));
}

$bookingId = $booking['id'];

$message = ''; $msgClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['confirm','change','decline'])) {
        $responseMap = ['confirm' => 'Confirmed', 'change' => 'Change Requested', 'decline' => 'Declined'];
        $response = $responseMap[$action];
        $now = date('Y-m-d H:i:s');

        $updates = ["customer_confirmed_at = ?", "customer_response = ?"];
        $params = [$now, $response];

        if ($action === 'confirm') {
            $updates[] = "status = 'Confirmed'";
            $message = t('cf.success_msg');
            $msgClass = 'success';
        } elseif ($action === 'change') {
            $updates[] = "status = 'Change Requested'";
            $message = t('cf.change_received');
            $msgClass = 'info';
        } else {
            $message = t('cf.decline_noted');
            $msgClass = 'warning';
        }
        $params[] = $bookingId;

        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare("UPDATE bookings SET " . implode(', ', $updates) . " WHERE id = ?");
            $stmt->execute($params);
            auditLog('customer_'.$action, 'Booking', $bookingId, null, ['response' => $response, 'at' => $now]);
            $conn->commit();
            $booking['customer_confirmed_at'] = $now;
            $booking['customer_response'] = $response;
            if ($action === 'confirm') $booking['status'] = 'Confirmed';
            if ($action === 'change') $booking['status'] = 'Change Requested';
        } catch (Exception $e) {
            $message = t('err.error_prefix') . ': '.$e->getMessage();
            $msgClass = 'danger';
        }
    }
}

$stmt = $conn->prepare("SELECT bi.*, it.name as item_name FROM booking_items bi INNER JOIN item_types it ON bi.item_type_id = it.id WHERE bi.booking_id = ? ORDER BY it.name");
$stmt->execute([$bookingId]);
$items = $stmt->fetchAll();

$companyName = getSetting('company_name', 'DJ RAK Entertainment');
$companyPhone = getSetting('company_phone', '');
$dateText = $booking['date_from'] === $booking['date_to']
    ? t_day(date('N', strtotime($booking['date_from']))) . ', ' . t_month(date('n', strtotime($booking['date_from']))) . ' ' . date('j, Y', strtotime($booking['date_from']))
    : t_month(date('n', strtotime($booking['date_from']))) . ' ' . date('j, Y', strtotime($booking['date_from'])) . ' — ' . t_month(date('n', strtotime($booking['date_to']))) . ' ' . date('j, Y', strtotime($booking['date_to']));

$icsUrl = SITE_URL . '/calendar_download.php?id=' . $bookingId . '&token=' . $token;
$alreadyResponded = !empty($booking['customer_response']);
?>
<!DOCTYPE html>
<html lang="<?= LANG_CODE ?>" dir="<?= IS_RTL ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= te('cf.title') ?> - <?= e($companyName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <?php if (IS_RTL): ?>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/rtl.css">
    <style>html[dir="rtl"] body { font-family: 'Tahoma', 'Segoe UI', 'Arial', sans-serif; }</style>
    <?php endif; ?>
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
        .booking-card { border-radius: 1rem; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.2); overflow: hidden; }
        .card-header-brand { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 2rem; }
        .section-title { border-left: 4px solid #667eea; padding-left: 12px; font-weight: 700; }
        html[dir="rtl"] .section-title { border-left: none; border-right: 4px solid #667eea; padding-left: 0; padding-right: 12px; }
        .item-row { padding: 10px 0; border-bottom: 1px dashed #eee; }
        .amount-box { background: linear-gradient(135deg, #f5f7fa 0%, #e8edf5 100%); border-radius: 10px; padding: 16px; }
        .btn-action { padding: 14px; font-weight: 600; border-radius: 10px; transition: all .2s; }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
        .confirmed-banner { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; border-radius: 10px; padding: 20px; }
        .lang-switcher { position: fixed; top: 1rem; right: 1rem; z-index: 9999; }
        html[dir="rtl"] .lang-switcher { right: auto; left: 1rem; }
        .lang-switcher .btn-group { box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
    </style>
</head>
<body class="py-4 py-md-5">
<div class="lang-switcher" title="<?= te('lang.switch_tooltip') ?>">
    <div class="btn-group btn-group-sm">
        <a href="<?= SITE_URL ?>/change_lang.php?lang=en" class="btn <?= LANG_CODE === 'en' ? 'btn-primary' : 'btn-light text-dark' ?>">EN</a>
        <a href="<?= SITE_URL ?>/change_lang.php?lang=ar" class="btn <?= LANG_CODE === 'ar' ? 'btn-primary' : 'btn-light text-dark' ?>">ع</a>
    </div>
</div>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card booking-card">
                <div class="card-header-brand text-center">
                    <i class="fas fa-compact-disc fa-3x mb-2"></i>
                    <h2 class="mb-1 font-weight-bold"><?= e($companyName) ?></h2>
                    <p class="opacity-90 mb-0"><?= te('cf.title') ?></p>
                </div>
                <div class="card-body p-4 p-md-5">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $msgClass ?> alert-important py-3 mb-4">
                            <?= $message ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($alreadyResponded && $booking['status'] === 'Confirmed'): ?>
                        <div class="confirmed-banner mb-4 text-center">
                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                            <h4 class="mb-1 font-weight-bold"><?= te('cf.booking_confirmed_title') ?></h4>
                            <p class="mb-0 opacity-90"><?= t('cf.confirmed_on', ['date' => t_month(date('n', strtotime($booking['customer_confirmed_at']))) . ' ' . date('j, Y g:i A', strtotime($booking['customer_confirmed_at']))]) ?></p>
                        </div>
                    <?php endif; ?>

                    <h4 class="section-title mb-3 mt-2"><?= te('cf.event_info') ?></h4>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3"><div class="text-uppercase small text-muted mb-1"><?= te('cf.booking_number') ?></div><div class="font-weight-bold h5 mb-0"><?= e($booking['booking_number']) ?></div></div>
                        <div class="col-md-6 mb-3"><div class="text-uppercase small text-muted mb-1"><?= te('cf.client') ?></div><div class="font-weight-bold h5 mb-0"><?= e($booking['client_name']) ?></div></div>
                        <div class="col-md-6 mb-3"><div class="text-uppercase small text-muted mb-1"><?= te('cf.event_dates') ?></div><div class="font-weight-semibold"><?= e($dateText) ?></div>
                            <?php if ($booking['event_start_time']): ?>
                                <small class="text-muted d-block mt-1"><i class="far fa-clock mr-1"></i>
                                    <?= date('g:i A', strtotime($booking['event_start_time'])) ?>
                                    <?= $booking['event_end_time'] ? ' — '.date('g:i A', strtotime($booking['event_end_time'])) : '' ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3"><div class="text-uppercase small text-muted mb-1"><?= te('cf.event_location') ?></div><div class="font-weight-semibold"><?= e($booking['location']) ?></div></div>
                    </div>

                    <h4 class="section-title mb-3"><?= te('cf.equipment_list') ?></h4>
                    <div class="mb-4">
                        <?php foreach ($items as $it): ?>
                            <div class="item-row d-flex justify-content-between align-items-center">
                                <span class="font-weight-semibold"><?= (int)$it['quantity'] ?> × <?= e($it['item_name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="amount-box mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-uppercase small text-muted"><?= te('cf.quoted_amount_label') ?></span>
                            <span class="h3 mb-0 font-weight-bold text-primary"><?= formatMoney($booking['quoted_amount']) ?></span>
                        </div>
                        <?php if (!empty($booking['customer_response']) && $booking['status'] === 'Confirmed'): ?>
                            <small class="text-success"><i class="fas fa-check-circle mr-1"></i> <?= te('cf.status_confirmed') ?></small>
                        <?php else: ?>
                            <small class="text-muted"><?= te('cf.status_prefix') ?>: <?= te(t_booking_status($booking['status'])) ?></small>
                        <?php endif; ?>
                    </div>

                    <?php if (!$alreadyResponded && $booking['status'] !== 'Canceled'): ?>
                        <form method="POST" class="mb-3">
                            <div class="text-center mb-4">
                                <h5 class="font-weight-bold mb-3"><?= te('cf.please_confirm') ?></h5>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <button type="submit" name="action" value="confirm" class="btn btn-success btn-block btn-action">
                                        <i class="fas fa-check-circle mr-2"></i><?= te('cf.confirm_btn') ?>
                                    </button>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <button type="submit" name="action" value="change" class="btn btn-info btn-block btn-action">
                                        <i class="fas fa-edit mr-2"></i><?= te('cf.request_changes') ?>
                                    </button>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <button type="submit" name="action" value="decline" class="btn btn-outline-secondary btn-block btn-action">
                                        <i class="fas fa-times mr-2"></i><?= te('cf.decline') ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ($booking['status'] === 'Confirmed' || $booking['customer_response'] === 'Confirmed'): ?>
                        <div class="text-center mb-3">
                            <a href="<?= e($icsUrl) ?>" class="btn btn-outline-dark btn-lg">
                                <i class="fas fa-calendar-plus mr-2"></i><?= te('cf.add_to_calendar') ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <hr class="my-4">
                    <div class="text-center text-muted small">
                        <?php if ($companyPhone): ?><i class="fas fa-phone mr-1"></i><?= e($companyPhone) ?><?php endif; ?>
                        &nbsp;·&nbsp;
                        <?php if (getSetting('company_email')): ?><i class="fas fa-envelope mr-1"></i><?= e(getSetting('company_email')) ?><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="text-center text-white-50 small mt-3">
                <?= t('cf.thank_you_footer', ['company' => $companyName]) ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
