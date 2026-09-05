<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('view_dashboard');

$page_title = t('title.dashboard');
$active_nav = 'dashboard';

$today = date('Y-m-d');
$monthStart = date('Y-m-01');

$dateFrom = $_GET['date_from'] ?? date('d/m/Y', strtotime($monthStart));
$dateTo = $_GET['date_to'] ?? date('d/m/Y', strtotime($today));
$df = DateTime::createFromFormat('d/m/Y', $dateFrom)->format('Y-m-d');
$dt = DateTime::createFromFormat('d/m/Y', $dateTo)->format('Y-m-d');

$stats = new stdClass();
$stats->bookings = 0;
$stats->booked = 0;
$stats->collected = 0;
$stats->pending = 0;
$stats->dj_rak = 0;
$stats->expenses = 0;
$stats->confirmed = 0;
$stats->pending_events = 0;
$stats->canceled = 0;

if ($conn) {
    $bookingSql = "SELECT COUNT(*) as cnt,
        COALESCE(SUM(CASE WHEN status != 'Canceled' THEN quoted_amount ELSE 0 END),0) as booked,
        COALESCE(SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END),0) as confirmed,
        COALESCE(SUM(CASE WHEN status IN ('Draft','Quotation','Change Requested') THEN 1 ELSE 0 END),0) as pending_events,
        COALESCE(SUM(CASE WHEN status = 'Canceled' THEN 1 ELSE 0 END),0) as canceled,
        COALESCE(SUM(CASE WHEN status != 'Canceled' THEN dj_rak_amount ELSE 0 END),0) as dj_rak
        FROM bookings WHERE date_from >= ? AND date_from <= ?";
    $stmt = $conn->prepare($bookingSql);
    $stmt->execute([$df, $dt]);
    $b = $stmt->fetch();
    $stats->bookings = (int)$b['cnt'];
    $stats->booked = (float)$b['booked'];
    $stats->confirmed = (int)$b['confirmed'];
    $stats->pending_events = (int)$b['pending_events'];
    $stats->canceled = (int)$b['canceled'];
    $stats->dj_rak = (float)$b['dj_rak'];

    $paymentSql = "SELECT COALESCE(SUM(p.amount),0) as collected FROM payments p
        INNER JOIN bookings b ON p.booking_id = b.id
        WHERE p.payment_date >= ? AND p.payment_date <= ? AND b.status != 'Canceled'";
    $stmt = $conn->prepare($paymentSql);
    $stmt->execute([$df, $dt]);
    $stats->collected = (float)$stmt->fetchColumn();

    $pendingSql = "SELECT COALESCE(SUM(
        CASE WHEN b.status != 'Canceled' THEN GREATEST(0, b.quoted_amount -
            (SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = b.id))
        ELSE 0 END), 0) as pending FROM bookings b
        WHERE b.date_from >= ? AND b.date_from <= ?";
    $stmt = $conn->prepare($pendingSql);
    $stmt->execute([$df, $dt]);
    $stats->pending = (float)$stmt->fetchColumn();

    $expenseSql = "SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE date >= ? AND date <= ?";
    $stmt = $conn->prepare($expenseSql);
    $stmt->execute([$df, $dt]);
    $stats->expenses = (float)$stmt->fetchColumn();

    $netCollected = $stats->collected - $stats->expenses;
    $collectionPct = $stats->booked > 0 ? min(100, round(($stats->collected / $stats->booked) * 100)) : 0;
    $djRakPct = $stats->booked > 0 ? min(100, round(($stats->dj_rak / $stats->booked) * 100)) : 0;

    $itemTypeSql = "SELECT COUNT(*) as cnt, COALESCE(SUM(quantity),0) as total_units FROM item_types WHERE active = 1";
    $itemTypeStats = $conn->query($itemTypeSql)->fetch();
    $totalUnits = (int)$itemTypeStats['total_units'];
    $totalItemTypes = (int)$itemTypeStats['cnt'];

    $todayBooked = 0;
    $reserveStatuses = ['Quotation','Confirmed','Change Requested','Event Completed','Closed'];
    $biSql = "SELECT COALESCE(SUM(bi.quantity),0) as qty FROM booking_items bi
        INNER JOIN bookings b ON bi.booking_id = b.id
        WHERE b.status IN ('" . implode("','", $reserveStatuses) . "')
        AND b.date_from <= ? AND b.date_to >= ? AND b.status != 'Canceled'";
    $stmt = $conn->prepare($biSql);
    $stmt->execute([$today, $today]);
    $todayBooked = (int)$stmt->fetchColumn();
    $availableUnits = max(0, $totalUnits - $todayBooked);

    $upcomingBookings = [];
    $ubStmt = $conn->prepare("SELECT b.*, c.name as client_name, c.phone as client_phone
        FROM bookings b INNER JOIN clients c ON b.client_id = c.id
        WHERE b.status != 'Canceled' AND b.date_from >= ?
        ORDER BY b.date_from ASC LIMIT 8");
    $ubStmt->execute([$today]);
    $upcomingBookings = $ubStmt->fetchAll();

    $pendingPayments = [];
    $ppStmt = $conn->prepare("SELECT b.id, b.booking_number, b.quoted_amount, b.date_from, c.name as client_name,
        (SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = b.id) as collected
        FROM bookings b INNER JOIN clients c ON b.client_id = c.id
        WHERE b.status != 'Canceled' AND b.payment_status IN ('Not Collected','Partially Collected')
        ORDER BY b.date_from ASC LIMIT 8");
    $ppStmt->execute();
    $pendingPayments = $ppStmt->fetchAll();

    $revenueChartData = [];
    $cal_months = t('cal.months');
    for ($i = 11; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("-$i months"));
        $mStart = $m . '-01';
        $mEnd = date('Y-m-t', strtotime($mStart));
        $rs = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN status != 'Canceled' THEN quoted_amount ELSE 0 END),0) as booked FROM bookings WHERE date_from >= ? AND date_from <= ?");
        $rs->execute([$mStart, $mEnd]);
        $bookedM = (float)$rs->fetchColumn();
        $rs = $conn->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p INNER JOIN bookings b ON p.booking_id = b.id WHERE p.payment_date >= ? AND p.payment_date <= ? AND b.status != 'Canceled'");
        $rs->execute([$mStart, $mEnd]);
        $collectedM = (float)$rs->fetchColumn();
        $rs = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE date >= ? AND date <= ?");
        $rs->execute([$mStart, $mEnd]);
        $expensesM = (float)$rs->fetchColumn();
        $mIdx = (int)date('n', strtotime($mStart)) - 1;
        $revenueChartData[] = [
            'label' => ($cal_months[$mIdx] ?? date('M', strtotime($mStart))) . ' ' . date('y', strtotime($mStart)),
            'booked' => $bookedM,
            'collected' => $collectedM,
            'expenses' => $expensesM
        ];
    }

    $expenseChartData = [];
    $ecStmt = $conn->prepare("SELECT et.name, COALESCE(SUM(e.amount),0) as total FROM expenses e
        RIGHT JOIN expense_types et ON e.expense_type_id = et.id AND e.date >= ? AND e.date <= ?
        GROUP BY et.id ORDER BY total DESC LIMIT 6");
    $ecStmt->execute([$df, $dt]);
    $expenseChartData = $ecStmt->fetchAll();

    $topClients = [];
    $tcStmt = $conn->prepare("SELECT c.id, c.name,
        COUNT(b.id) as booking_count,
        COALESCE(SUM(CASE WHEN b.status != 'Canceled' THEN b.quoted_amount ELSE 0 END),0) as total_value
        FROM clients c LEFT JOIN bookings b ON b.client_id = c.id AND b.date_from >= ? AND b.date_from <= ?
        GROUP BY c.id ORDER BY total_value DESC LIMIT 5");
    $tcStmt->execute([$df, $dt]);
    $topClients = $tcStmt->fetchAll();
}

$curUser = currentUser();
$curHour = (int)date('H');
$greetingKey = 'dash.good_morning';
if ($curHour >= 12 && $curHour < 17) $greetingKey = 'dash.good_afternoon';
elseif ($curHour >= 17) $greetingKey = 'dash.good_evening';
$greetingUser = $curUser['name'] ?? t('common.unknown');

include SITE_PATH . '/includes/header.php';
echo flashMessages();
?>

<style>
.greeting-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 14px;
    padding: 1.5rem 1.75rem;
    color: #fff;
    position: relative;
    overflow: hidden;
    margin-bottom: 1.5rem;
    border: none;
}
.greeting-card::after {
    content: '';
    position: absolute;
    right: -40px; top: -50px;
    width: 200px; height: 200px;
    background: rgba(255,255,255,0.08);
    border-radius: 50%;
}
.greeting-card::before {
    content: '';
    position: absolute;
    right: 60px; bottom: -80px;
    width: 180px; height: 180px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
}
.greeting-card h2 { margin: 0 0 0.25rem; font-weight: 700; z-index: 2; position: relative; }
.greeting-card p  { margin: 0; opacity: 0.92; z-index: 2; position: relative; }
.greeting-card .greeting-meta {
    display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1rem;
    z-index: 2; position: relative;
}
.greeting-meta .chip {
    background: rgba(255,255,255,0.16);
    border: 1px solid rgba(255,255,255,0.22);
    padding: 0.35rem 0.8rem;
    border-radius: 999px;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.qa-card {
    border-radius: 12px;
    border: 1px solid #e9ecef;
    padding: 1rem 1.1rem;
    display: flex;
    gap: 0.9rem;
    align-items: center;
    color: inherit !important;
    transition: all 0.2s ease;
    background: #fff;
    text-decoration: none !important;
    margin-bottom: 1rem;
}
.qa-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(102,126,234,0.15);
    border-color: #c7d2fe;
}
.qa-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    flex-shrink: 0;
    font-size: 1.15rem;
}
.qa-title { font-weight: 700; font-size: 1rem; color: #1a202c; margin: 0 0 0.15rem; }
.qa-sub   { font-size: 0.8rem; color: #64748b; margin: 0; }

.kpi-card-elevated {
    border-radius: 12px;
    transition: all 0.2s ease;
    border: 1px solid #eef2f7;
}
.kpi-card-elevated:hover {
    box-shadow: 0 6px 18px rgba(15,23,42,0.08);
    transform: translateY(-1px);
}
.kpi-card-link { display: block; color: inherit; text-decoration: none !important; padding: 0; }
.kpi-value-collection {
    font-size: 0.85rem; color: #475569; margin-top: 0.35rem;
}
.kpi-progress {
    height: 6px; border-radius: 999px; background: #edf2f7;
    overflow: hidden; margin-top: 0.5rem;
}
.kpi-progress-bar { height: 100%; border-radius: 999px; }

.table-clickable tbody tr { cursor: pointer; }
.table-clickable tbody tr:hover { background: #f8fafc; }
.table-clickable tbody tr:hover td:first-child { border-left: 3px solid #667eea; padding-left: 9px; }

.collection-pct-badge {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.3rem 0.7rem; border-radius: 999px;
    font-size: 0.8rem; font-weight: 600;
}
.status-mini-chip {
    display: inline-block;
    width: 6px; height: 6px; border-radius: 50%;
    margin-right: 6px;
}
@media (min-width: 992px) {
    .col-lg-five {
        flex: 0 0 20%;
        max-width: 20%;
    }
}
</style>

<div class="greeting-card">
    <h2><?= t($greetingKey) ?>, <?= e($greetingUser) ?>! 👋</h2>
    <p><?= t('dash.greeting_sub') ?></p>
    <div class="greeting-meta">
        <span class="chip"><i class="fas fa-calendar-day"></i> <?= (new DateTime())->format('l, d F Y') ?></span>
        <span class="chip"><i class="fas fa-calendar-alt"></i> <?= t('field.from') ?> <?= e($dateFrom) ?> → <?= t('field.to') ?> <?= e($dateTo) ?></span>
        <span class="chip"><i class="fas fa-chart-pie"></i> <?= t('dash.collection_ratio') ?> <strong><?= $collectionPct ?>%</strong></span>
        <?php if ($stats->dj_rak > 0): ?>
            <span class="chip"><i class="fas fa-compact-disc"></i> <?= t('dash.dj_rak_share') ?> <strong><?= $djRakPct ?>%</strong></span>
        <?php endif; ?>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12 mb-3">
        <h5 class="mb-2 mt-1"><i class="fas fa-bolt text-warning mr-2"></i><?= t('dash.quick_actions') ?></h5>
    </div>
    <div class="col-6 col-sm-4 col-md-4 col-lg-five col-xl-2">
        <a class="qa-card" href="<?= SITE_URL ?>/booking_form.php">
            <div class="qa-icon" style="background: linear-gradient(135deg,#667eea,#764ba2)"><i class="fas fa-calendar-plus"></i></div>
            <div>
                <div class="qa-title"><?= t('dash.qa_new_booking') ?></div>
                <div class="qa-sub"><?= t('dash.qa_new_booking_sub') ?></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-sm-4 col-md-4 col-lg-five col-xl-2">
        <a class="qa-card" href="<?= SITE_URL ?>/clients.php">
            <div class="qa-icon" style="background: linear-gradient(135deg,#11998e,#38ef7d)"><i class="fas fa-user-plus"></i></div>
            <div>
                <div class="qa-title"><?= t('dash.qa_new_client') ?></div>
                <div class="qa-sub"><?= t('dash.qa_new_client_sub') ?></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-sm-4 col-md-4 col-lg-five col-xl-2">
        <a class="qa-card" href="<?= SITE_URL ?>/payments.php">
            <div class="qa-icon" style="background: linear-gradient(135deg,#11998e,#4facfe)"><i class="fas fa-hand-holding-usd"></i></div>
            <div>
                <div class="qa-title"><?= t('dash.qa_new_payment') ?></div>
                <div class="qa-sub"><?= t('dash.qa_new_payment_sub') ?></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-sm-4 col-md-4 col-lg-five col-xl-2">
        <a class="qa-card" href="<?= SITE_URL ?>/expenses.php">
            <div class="qa-icon" style="background: linear-gradient(135deg,#f5576c,#F093FB)"><i class="fas fa-receipt"></i></div>
            <div>
                <div class="qa-title"><?= t('dash.qa_new_expense') ?></div>
                <div class="qa-sub"><?= t('dash.qa_new_expense_sub') ?></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-sm-4 col-md-4 col-lg-five col-xl-2">
        <a class="qa-card" href="<?= SITE_URL ?>/inventory.php">
            <div class="qa-icon" style="background: linear-gradient(135deg,#fa709a,#fee140)"><i class="fas fa-boxes"></i></div>
            <div>
                <div class="qa-title"><?= t('dash.qa_inventory') ?></div>
                <div class="qa-sub"><?= t('dash.qa_inventory_sub') ?></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-sm-4 col-md-4 col-lg-five col-xl-2">
        <a class="qa-card" href="<?= SITE_URL ?>/calendar.php">
            <div class="qa-icon" style="background: linear-gradient(135deg,#4facfe,#00f2fe)"><i class="fas fa-calendar-alt"></i></div>
            <div>
                <div class="qa-title"><?= t('nav.calendar') ?></div>
                <div class="qa-sub"><?= t('dash.qa_inventory_sub') ?></div>
            </div>
        </a>
    </div>
</div>

<div class="row mb-3 align-items-end">
    <div class="col-md-6">
        <h1 class="page-title"><?= t('title.dashboard') ?></h1>
        <p class="page-subtitle"><?= t('title.dashboard_sub') ?></p>
    </div>
    <div class="col-md-6">
        <form method="GET" class="form-inline justify-content-md-end">
            <div class="input-group input-group-sm mr-2">
                <div class="input-group-prepend"><span class="input-group-text"><?= t('field.from') ?></span></div>
                <input type="text" name="date_from" class="form-control datepicker" value="<?= e($dateFrom) ?>" autocomplete="off">
            </div>
            <div class="input-group input-group-sm mr-2">
                <div class="input-group-prepend"><span class="input-group-text"><?= t('field.to') ?></span></div>
                <input type="text" name="date_to" class="form-control datepicker" value="<?= e($dateTo) ?>" autocomplete="off">
            </div>
            <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-filter"></i> <?= t('common.apply') ?></button>
        </form>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4 col-lg-2 mb-3">
        <a class="kpi-card-link" href="<?= SITE_URL ?>/bookings.php">
            <div class="card kpi-card kpi-card-elevated h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label"><?= t('dash.kpi_bookings') ?></div>
                            <div class="kpi-value mt-1"><?= $stats->bookings ?></div>
                            <div class="kpi-value-collection"><i class="fas fa-arrow-right mr-1"></i><?= t('dash.kpi_click_hint') ?></div>
                        </div>
                        <div class="kpi-icon bg-gradient-primary"><i class="fas fa-book"></i></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-lg-2 mb-3">
        <a class="kpi-card-link" href="<?= SITE_URL ?>/reports_financial.php">
            <div class="card kpi-card kpi-card-elevated h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label"><?= t('dash.kpi_collected') ?></div>
                            <div class="kpi-value mt-1"><?= number_format($stats->collected, 0) ?></div>
                            <div class="kpi-progress"><div class="kpi-progress-bar" style="width:<?= $collectionPct ?>%;background:linear-gradient(90deg,#11998e,#38ef7d)"></div></div>
                            <div class="kpi-value-collection"><span class="collection-pct-badge" style="background:<?= $collectionPct >= 80 ? '#ecfdf5;color:#065f46' : ($collectionPct >= 50 ? '#fffbeb;color:#92400e' : '#fef2f2;color:#991b1b') ?>"><?= $collectionPct ?>%</span> <?= str_replace(['{c}','{b}'], [number_format($stats->collected,0), number_format($stats->booked,0)], t('dash.collection_of')) ?></div>
                        </div>
                        <div class="kpi-icon bg-gradient-success"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-lg-2 mb-3">
        <a class="kpi-card-link" href="<?= SITE_URL ?>/reports_financial.php#pending">
            <div class="card kpi-card kpi-card-elevated h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label"><?= t('dash.kpi_pending') ?></div>
                            <div class="kpi-value mt-1"><?= number_format($stats->pending, 0) ?></div>
                            <div class="kpi-progress"><div class="kpi-progress-bar" style="width:<?= 100 - $collectionPct ?>%;background:linear-gradient(90deg,#f59e0b,#fbbf24)"></div></div>
                            <div class="kpi-value-collection"><i class="fas fa-arrow-right mr-1"></i><?= t('dash.kpi_click_hint') ?></div>
                        </div>
                        <div class="kpi-icon bg-gradient-warning"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-lg-2 mb-3">
        <a class="kpi-card-link" href="<?= SITE_URL ?>/expenses.php">
            <div class="card kpi-card kpi-card-elevated h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label"><?= t('dash.kpi_expenses') ?></div>
                            <div class="kpi-value mt-1"><?= number_format($stats->expenses, 0) ?></div>
                            <div class="kpi-value-collection"><i class="fas fa-arrow-right mr-1"></i><?= t('dash.kpi_click_hint') ?></div>
                        </div>
                        <div class="kpi-icon bg-gradient-danger"><i class="fas fa-money-bill-wave"></i></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-lg-2 mb-3">
        <div class="card kpi-card kpi-card-elevated h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-label"><?= t('dash.kpi_net') ?></div>
                        <div class="kpi-value mt-1 <?= $netCollected >= 0 ? '' : 'text-danger' ?>"><?= number_format($netCollected, 0) ?></div>
                        <div class="kpi-value-collection">
                            <span class="status-mini-chip" style="background:<?= $netCollected >= 0 ? '#10b981' : '#ef4444' ?>"></span>
                            <?= $netCollected >= 0 ? t('dash.chart_collected') . ' - ' . t('dash.kpi_expenses') : t('dash.chart_booked') ?>
                        </div>
                    </div>
                    <div class="kpi-icon bg-gradient-info"><i class="fas fa-chart-line"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2 mb-3">
        <div class="card kpi-card kpi-card-elevated h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-label"><?= t('dash.kpi_expected') ?></div>
                        <div class="kpi-value mt-1"><?= number_format($stats->booked, 0) ?></div>
                        <?php if ($stats->dj_rak > 0): ?>
                            <div class="kpi-progress"><div class="kpi-progress-bar" style="width:<?= $djRakPct ?>%;background:linear-gradient(90deg,#667eea,#a855f7)"></div></div>
                            <div class="kpi-value-collection"><?= t('dash.dj_rak_share') ?>: <?= number_format($stats->dj_rak,0) ?> (<?= $djRakPct ?>%)</div>
                        <?php endif; ?>
                    </div>
                    <div class="kpi-icon bg-gradient-secondary"><i class="fas fa-coins"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <a class="kpi-card-link" href="<?= SITE_URL ?>/bookings.php?status=Confirmed">
            <div class="card h-100 kpi-card-elevated">
                <div class="card-body text-center py-4">
                    <i class="fas fa-calendar-check fa-2x text-success mb-2"></i>
                    <div class="h3 font-weight-bold mb-0"><?= $stats->confirmed ?></div>
                    <small class="text-muted text-uppercase"><?= t('dash.confirmed_events') ?></small>
                    <div class="mt-2 small text-muted"><i class="fas fa-arrow-right"></i> <?= t('dash.kpi_click_hint') ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 mb-3">
        <a class="kpi-card-link" href="<?= SITE_URL ?>/bookings.php?status=Quotation">
            <div class="card h-100 kpi-card-elevated">
                <div class="card-body text-center py-4">
                    <i class="fas fa-clipboard-list fa-2x text-warning mb-2"></i>
                    <div class="h3 font-weight-bold mb-0"><?= $stats->pending_events ?></div>
                    <small class="text-muted text-uppercase"><?= t('dash.pending_events') ?></small>
                    <div class="mt-2 small text-muted"><i class="fas fa-arrow-right"></i> <?= t('dash.kpi_click_hint') ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 mb-3">
        <a class="kpi-card-link" href="<?= SITE_URL ?>/bookings.php?status=Canceled">
            <div class="card h-100 kpi-card-elevated">
                <div class="card-body text-center py-4">
                    <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                    <div class="h3 font-weight-bold mb-0"><?= $stats->canceled ?></div>
                    <small class="text-muted text-uppercase"><?= t('dash.canceled_events') ?></small>
                    <div class="mt-2 small text-muted"><i class="fas fa-arrow-right"></i> <?= t('dash.kpi_click_hint') ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 mb-3">
        <a class="kpi-card-link" href="<?= SITE_URL ?>/inventory.php">
            <div class="card h-100 kpi-card-elevated">
                <div class="card-body text-center py-4">
                    <i class="fas fa-headphones-alt fa-2x text-info mb-2"></i>
                    <div class="h3 font-weight-bold mb-0"><?= $availableUnits ?> / <?= $totalUnits ?></div>
                    <small class="text-muted text-uppercase"><?= t('dash.units_available') ?></small>
                    <div class="mt-2 small text-muted"><i class="fas fa-arrow-right"></i> <?= t('dash.kpi_click_hint') ?></div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-8 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-bar mr-2"></i><?= t('dash.revenue_trend') ?></span>
            </div>
            <div class="card-body"><div class="chart-container"><canvas id="revenueChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-pie mr-2"></i><?= t('dash.expenses_by_type') ?></div>
            <div class="card-body"><div class="chart-container"><canvas id="expenseChart"></canvas></div></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-calendar-day mr-2"></i><?= t('dash.upcoming_bookings') ?></span>
                <a href="<?= SITE_URL ?>/bookings.php" class="btn btn-sm btn-link"><?= t('common.view_all') ?> <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0 table-clickable" id="upcomingTable">
                        <thead><tr><th>#</th><th><?= t('dash.th_client') ?></th><th><?= t('dash.th_date') ?></th><th><?= t('dash.th_amount') ?></th><th><?= t('dash.th_status') ?></th></tr></thead>
                        <tbody>
                            <?php if (empty($upcomingBookings)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4" style="cursor:default;pointer-events:none"><?= t('dash.no_upcoming') ?></td></tr>
                            <?php else: foreach ($upcomingBookings as $ub): ?>
                                <tr data-href="<?= SITE_URL ?>/booking_view.php?id=<?= (int)$ub['id'] ?>">
                                    <td class="font-weight-bold"><?= e($ub['booking_number']) ?></td>
                                    <td><?= e($ub['client_name']) ?><br><small class="text-muted"><?= e($ub['client_phone']) ?></small></td>
                                    <td><?= formatDate($ub['date_from']) ?></td>
                                    <td><?= formatMoney($ub['quoted_amount']) ?></td>
                                    <td><span class="status-badge status-<?= strtolower(str_replace([' ','-','/'],['_','_',''], $ub['status'])) ?>"><?= t_booking_status($ub['status']) ?></span></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-money-check-alt mr-2"></i><?= t('dash.pending_payments') ?></span>
                <a href="<?= SITE_URL ?>/reports_financial.php" class="btn btn-sm btn-link"><?= t('common.view_report') ?> <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0 table-clickable" id="pendingTable">
                        <thead><tr><th><?= t('dash.th_booking') ?></th><th><?= t('dash.th_client') ?></th><th><?= t('dash.th_quoted') ?></th><th><?= t('dash.th_collected') ?></th><th><?= t('dash.th_pending') ?></th></tr></thead>
                        <tbody>
                            <?php if (empty($pendingPayments)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4" style="cursor:default;pointer-events:none"><?= t('dash.no_pending_payments') ?></td></tr>
                            <?php else: foreach ($pendingPayments as $pp):
                                $pending = max(0, (float)$pp['quoted_amount'] - (float)$pp['collected']); ?>
                                <tr data-href="<?= SITE_URL ?>/booking_view.php?id=<?= (int)$pp['id'] ?>">
                                    <td class="font-weight-bold"><?= e($pp['booking_number']) ?></td>
                                    <td><?= e($pp['client_name']) ?></td>
                                    <td><?= formatMoney($pp['quoted_amount']) ?></td>
                                    <td class="text-success"><?= formatMoney($pp['collected']) ?></td>
                                    <td class="text-danger font-weight-bold"><?= formatMoney($pending) ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header"><i class="fas fa-trophy mr-2"></i><?= t('dash.top_clients') ?></div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr><th><?= t('dash.th_client') ?></th><th><?= t('dash.th_bookings') ?></th><th><?= t('dash.th_total_value') ?></th></tr></thead>
                    <tbody>
                        <?php if (empty($topClients)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4"><?= t('common.no_records') ?></td></tr>
                        <?php else: foreach ($topClients as $tc): ?>
                            <tr>
                                <td class="font-weight-bold"><?= e($tc['name']) ?></td>
                                <td><?= (int)$tc['booking_count'] ?></td>
                                <td><?= formatMoney($tc['total_value']) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header"><i class="fas fa-music mr-2"></i><?= t('dash.inventory_summary') ?></div>
            <div class="card-body">
                <?php if ($conn):
                    $stmt = $conn->query("SELECT c.name as category,
                        COUNT(it.id) as types,
                        COALESCE(SUM(it.quantity),0) as units
                        FROM categories c LEFT JOIN item_types it ON it.category_id = c.id AND it.active = 1
                        WHERE c.active = 1 GROUP BY c.id ORDER BY units DESC LIMIT 8");
                    $rows = $stmt->fetchAll();
                ?>
                <div class="progress mb-3" style="height:20px">
                    <?php $total = array_sum(array_column($rows, 'units')); ?>
                    <?php $colors = ['#667eea','#11998e','#f5576c','#4facfe','#fa709a','#a18cd1','#f5af19','#2ECC71']; ?>
                    <?php $offset = 0; $i = 0; foreach ($rows as $r):
                        if ($total <= 0) break;
                        $pct = ($r['units'] / $total) * 100;
                        if ($pct < 1) continue; ?>
                        <div class="progress-bar" role="progressbar" style="width:<?= $pct ?>%; background:<?= $colors[$i % count($colors)] ?>"
                            title="<?= e($r['category']) ?>: <?= $r['units'] ?> units (<?= round($pct, 1) ?>%)"></div>
                    <?php $i++; endforeach; ?>
                </div>
                <table class="table table-sm mb-0">
                    <tbody>
                        <?php $idx = 0; foreach ($rows as $r): ?>
                            <tr><td><i class="fas fa-circle" style="color:<?= $colors[$idx % count($colors)] ?>; font-size:8px"></i> <?= e($r['category']) ?></td>
                                <td class="text-right"><?= (int)$r['types'] ?> <?= t('dash.types') ?></td>
                                <td class="text-right font-weight-bold"><?= (int)$r['units'] ?> <?= t('dash.units') ?></td>
                            </tr>
                        <?php $idx++; endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    document.querySelectorAll('#upcomingTable tbody tr[data-href], #pendingTable tbody tr[data-href]').forEach(function(row){
        row.addEventListener('click', function(){
            var href = this.getAttribute('data-href');
            if (href) window.location.href = href;
        });
    });

    var revenueData = <?= json_encode($revenueChartData) ?>;
    var ctx = document.getElementById('revenueChart');
    var isRTL = document.documentElement.dir === 'rtl';
    if (ctx) new Chart(ctx, {
        type: 'line',
        data: {
            labels: revenueData.map(d => d.label),
            datasets: [
                { label: '<?= t('dash.chart_booked') ?>', data: revenueData.map(d => d.booked), borderColor: '#667eea', backgroundColor: 'rgba(102,126,234,0.1)', tension: 0.3, fill: true },
                { label: '<?= t('dash.chart_collected') ?>', data: revenueData.map(d => d.collected), borderColor: '#11998e', backgroundColor: 'rgba(17,153,142,0.1)', tension: 0.3, fill: true },
                { label: '<?= t('dash.kpi_expenses') ?>', data: revenueData.map(d => d.expenses), borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.08)', tension: 0.3, fill: true, borderDash: [6, 4], borderWidth: 2 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { position: 'top', labels: { rtl: isRTL, textDirection: isRTL ? 'rtl' : 'ltr' } },
            scales: { yAxes: [{ ticks: { beginAtZero: true, callback: v => v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',') } }] }
        }
    });

    var expenseData = <?= json_encode($expenseChartData) ?>;
    var ex = document.getElementById('expenseChart');
    if (ex && expenseData.length) new Chart(ex, {
        type: 'doughnut',
        data: {
            labels: expenseData.map(d => d.name),
            datasets: [{ data: expenseData.map(d => d.total),
                backgroundColor: ['#667eea','#11998e','#f5576c','#4facfe','#fa709a','#a18cd1','#f5af19','#2ECC71'] }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 }, rtl: isRTL, textDirection: isRTL ? 'rtl' : 'ltr' } }
        }
    });
});
</script>
<?php include SITE_PATH . '/includes/footer.php'; ?>
