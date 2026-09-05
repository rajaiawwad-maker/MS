<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('view_financials');

$page_title = t('title.financial_report');
$active_nav = 'reports';
$tab = $_GET['tab'] ?? 'financial';

$tabTitle = [
    'financial' => [t('title.financial_report'), t('title.financial_report_sub')],
    'expenses'  => [t('title.expenses_report'), t('title.expenses_report_sub')],
    'inventory' => [t('title.inventory_report'), t('title.inventory_report_sub')],
    'client'    => [t('title.client_statement_report'), t('title.client_statement_report_sub')],
];
$tabLabel = [
    'financial' => t('tab.financial'),
    'expenses'  => t('tab.expenses'),
    'inventory' => t('tab.inventory'),
    'client'    => t('tab.client_statement'),
];
$currentTitle = $tabTitle[$tab][0] ?? t('title.financial_report');
$currentSubtitle = $tabTitle[$tab][1] ?? '';

$subPageToLoad = null;
if ($tab === 'expenses')  $subPageToLoad = __DIR__ . '/reports_expenses.php';
if ($tab === 'inventory') $subPageToLoad = __DIR__ . '/reports_inventory.php';
if ($tab === 'client')    $subPageToLoad = __DIR__ . '/reports_client_statement.php';

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if ($tab === 'financial') {
        $mStart = new DateTime('first day of this month');
        $mEnd = new DateTime('last day of this month');
        $df = isset($_GET['date_from']) && trim($_GET['date_from']) !== '' ? $_GET['date_from'] : $mStart->format('d/m/Y');
        $dt = isset($_GET['date_to']) && trim($_GET['date_to']) !== '' ? $_GET['date_to'] : $mEnd->format('d/m/Y');
        $clientId = (int)($_GET['client_id'] ?? 0);
        $paymentStatus = $_GET['payment_status'] ?? '';
        $show = $_GET['show'] ?? 'all';

        $where = ['1=1']; $params = [];
        if ($df !== '') { $d = DateTime::createFromFormat('d/m/Y', $df); if ($d) { $where[] = "b.date_from >= ?"; $params[] = $d->format('Y-m-d'); } }
        if ($dt !== '') { $d = DateTime::createFromFormat('d/m/Y', $dt); if ($d) { $where[] = "b.date_from <= ?"; $params[] = $d->format('Y-m-d'); } }
        if ($clientId > 0) { $where[] = "b.client_id = ?"; $params[] = $clientId; }
        if ($paymentStatus !== '') { $where[] = "b.payment_status = ?"; $params[] = $paymentStatus; }
        if ($show === 'booked') { $where[] = "b.status != 'Canceled'"; }
        elseif ($show === 'collected') { $where[] = "b.payment_status IN ('Partially Collected','Fully Collected')"; }
        elseif ($show === 'pending') { $where[] = "b.payment_status IN ('Not Collected','Partially Collected') AND b.status != 'Canceled'"; }

        $sql = "SELECT b.*, c.name as client_name,
            (SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = b.id) as collected
            FROM bookings b INNER JOIN clients c ON b.client_id = c.id
            WHERE " . implode(' AND ', $where) . " ORDER BY b.date_from DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $totalBooked = 0; $totalCollected = 0; $totalPending = 0;
        foreach ($rows as $r) {
            if ($r['status'] !== 'Canceled') $totalBooked += (float)$r['quoted_amount'];
            $totalCollected += (float)$r['collected'];
        }
        $totalPending = max(0, $totalBooked - $totalCollected);

        $expTotal = 0;
        $whereExp = []; $pExp = [];
        if ($df !== '') { $d = DateTime::createFromFormat('d/m/Y', $df); if ($d) { $whereExp[] = "date >= ?"; $pExp[] = $d->format('Y-m-d'); } }
        if ($dt !== '') { $d = DateTime::createFromFormat('d/m/Y', $dt); if ($d) { $whereExp[] = "date <= ?"; $pExp[] = $d->format('Y-m-d'); } }
        $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses ".(!empty($whereExp) ? "WHERE ".implode(' AND ', $whereExp) : ""));
        $stmt->execute($pExp); $expTotal = (float)$stmt->fetchColumn();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="financial_report_'.date('Ymd').'.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, [t('th.booking'),t('th.client'),t('th.event_date'),t('th.quoted'),t('th.collected'),t('th.pending'),t('th.payment_status'),t('th.booking_status')]);
        foreach ($rows as $r) {
            $pend = max(0, (float)$r['quoted_amount'] - (float)$r['collected']);
            fputcsv($out, [$r['booking_number'],$r['client_name'],$r['date_from'],$r['quoted_amount'],$r['collected'],$pend,$r['payment_status'],$r['status']]);
        }
        fputcsv($out, []);
        fputcsv($out, [t('rep.totals'),'',$totalBooked,$totalCollected,$totalPending]);
        fputcsv($out, [t('rep.expenses'),'','',$expTotal]);
        fputcsv($out, [t('rep.net_collected_label'),'','',$totalCollected - $expTotal]);
        exit;
    } elseif ($subPageToLoad) {
        require $subPageToLoad;
        exit;
    }
}

if ($tab !== 'financial') {
    $inReportsParent = true;
}

$df = $_GET['date_from'] ?? '';
$dt = $_GET['date_to'] ?? '';
if ($tab === 'financial') {
    $mStart = new DateTime('first day of this month');
    $mEnd = new DateTime('last day of this month');
    if ($df === '') $df = $mStart->format('d/m/Y');
    if ($dt === '') $dt = $mEnd->format('d/m/Y');
}
$clientId = (int)($_GET['client_id'] ?? 0);
$paymentStatus = $_GET['payment_status'] ?? '';
$show = $_GET['show'] ?? 'all';

$where = ['1=1']; $params = [];
if ($df !== '') { $d = DateTime::createFromFormat('d/m/Y', $df); if ($d) { $where[] = "b.date_from >= ?"; $params[] = $d->format('Y-m-d'); } }
if ($dt !== '') { $d = DateTime::createFromFormat('d/m/Y', $dt); if ($d) { $where[] = "b.date_from <= ?"; $params[] = $d->format('Y-m-d'); } }
if ($clientId > 0) { $where[] = "b.client_id = ?"; $params[] = $clientId; }
if ($paymentStatus !== '') { $where[] = "b.payment_status = ?"; $params[] = $paymentStatus; }
if ($show === 'booked') { $where[] = "b.status != 'Canceled'"; }
elseif ($show === 'collected') { $where[] = "b.payment_status IN ('Partially Collected','Fully Collected')"; }
elseif ($show === 'pending') { $where[] = "b.payment_status IN ('Not Collected','Partially Collected') AND b.status != 'Canceled'"; }

$sql = "SELECT b.*, c.name as client_name,
    (SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = b.id) as collected
    FROM bookings b INNER JOIN clients c ON b.client_id = c.id
    WHERE " . implode(' AND ', $where) . " ORDER BY b.date_from DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$totalBooked = 0; $totalCollected = 0; $totalPending = 0;
foreach ($rows as $r) {
    if ($r['status'] !== 'Canceled') $totalBooked += (float)$r['quoted_amount'];
    $totalCollected += (float)$r['collected'];
}
$totalPending = max(0, $totalBooked - $totalCollected);

$expTotal = 0;
$whereExp = []; $pExp = [];
if ($df !== '') { $d = DateTime::createFromFormat('d/m/Y', $df); if ($d) { $whereExp[] = "date >= ?"; $pExp[] = $d->format('Y-m-d'); } }
if ($dt !== '') { $d = DateTime::createFromFormat('d/m/Y', $dt); if ($d) { $whereExp[] = "date <= ?"; $pExp[] = $d->format('Y-m-d'); } }
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses ".(!empty($whereExp) ? "WHERE ".implode(' AND ', $whereExp) : ""));
$stmt->execute($pExp); $expTotal = (float)$stmt->fetchColumn();

$trendStart = $df !== '' && ($d = DateTime::createFromFormat('d/m/Y', $df)) ? $d : (new DateTime())->modify('-5 months');
$trendEnd   = $dt !== '' && ($d = DateTime::createFromFormat('d/m/Y', $dt)) ? $d : new DateTime();
$trendData = [];
$cal_months_fin = t('cal.months');
$cursor = (clone $trendStart)->modify('first day of this month');
$endCursor = (clone $trendEnd)->modify('first day of this month');
while ($cursor <= $endCursor) {
    $mStart = $cursor->format('Y-m-01');
    $mEnd = $cursor->format('Y-m-t');
    $bWhere = $where; $bParams = $params;
    $bWhere[] = "b.date_from >= ?"; $bParams[] = $mStart;
    $bWhere[] = "b.date_from <= ?"; $bParams[] = $mEnd;
    $bSql = "SELECT
        COALESCE(SUM(CASE WHEN b.status != 'Canceled' THEN b.quoted_amount ELSE 0 END),0) as booked,
        COALESCE(SUM((SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = b.id)),0) as collected
        FROM bookings b INNER JOIN clients c ON b.client_id = c.id
        WHERE ".implode(' AND ', $bWhere);
    $stmt = $conn->prepare($bSql); $stmt->execute($bParams); $bt = $stmt->fetch();

    $eWhere = []; $eParams = [];
    foreach ($whereExp as $i => $w) { $eWhere[] = $w; $eParams[] = $pExp[$i]; }
    $eWhere[] = "date >= ?"; $eParams[] = $mStart;
    $eWhere[] = "date <= ?"; $eParams[] = $mEnd;
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE ".implode(' AND ', $eWhere));
    $stmt->execute($eParams); $expM = (float)$stmt->fetchColumn();

    $mIdx = (int)$cursor->format('n') - 1;
    $trendData[] = [
        'label' => ($cal_months_fin[$mIdx] ?? $cursor->format('M')) . ' ' . $cursor->format('y'),
        'booked' => (float)($bt['booked'] ?? 0),
        'collected' => (float)($bt['collected'] ?? 0),
        'expenses' => $expM
    ];
    $cursor->modify('+1 month');
    if (count($trendData) > 24) break;
}

$clients = $conn->query("SELECT id, name FROM clients ORDER BY name")->fetchAll();

include SITE_PATH . '/includes/header.php';
echo flashMessages();
?>
<div class="row mb-3 align-items-end">
    <div class="col-md-6">
        <h1 class="page-title"><?= e($currentTitle) ?></h1>
        <p class="page-subtitle"><?= e($currentSubtitle) ?></p>
    </div>
    <div class="col-md-6 text-md-right">
        <div class="btn-group mr-2 mb-2">
            <?php foreach ($tabLabel as $k => $l): ?>
                <a href="?tab=<?= $k ?>" class="btn btn-outline-secondary <?= $tab === $k ? 'active' : '' ?>"><?= e($l) ?></a>
            <?php endforeach; ?>
        </div>
        <?php if ($tab !== 'inventory'): ?>
        <a href="<?= e($_SERVER['REQUEST_URI']) ?><?= strpos($_SERVER['REQUEST_URI'], '?') ? '&' : '?' ?>export=csv" class="btn btn-outline-success mb-2"><i class="fas fa-file-csv mr-1"></i> <?= te('common.export_csv') ?></a>
        <?php endif; ?>
    </div>
</div>
<?php if ($tab === 'financial'): ?>
<form method="GET" class="card filter-row mb-3">
<input type="hidden" name="tab" value="financial">
<div class="row align-items-end">
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('field.from') ?></label><input name="date_from" class="form-control datepicker" value="<?= e($df) ?>" autocomplete="off"></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('field.to') ?></label><input name="date_to" class="form-control datepicker" value="<?= e($dt) ?>" autocomplete="off"></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('field.client') ?></label>
        <select name="client_id" class="form-control select2"><option value=""><?= te('common.all') ?></option>
            <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>" <?= $clientId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('field.payment_status') ?></label>
        <select name="payment_status" class="form-control"><option value=""><?= te('common.all') ?></option>
            <?php foreach (['Not Collected','Partially Collected','Fully Collected','Canceled'] as $s): ?><option value="<?= e($s) ?>" <?= $paymentStatus === $s ? 'selected' : '' ?>><?= e(t_payment_status($s)) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('field.show') ?></label>
        <select name="show" class="form-control">
            <option value="all" <?= $show === 'all' ? 'selected' : '' ?>><?= te('common.all') ?></option>
            <option value="booked" <?= $show === 'booked' ? 'selected' : '' ?>><?= te('pay.booked_active') ?></option>
            <option value="collected" <?= $show === 'collected' ? 'selected' : '' ?>><?= te('pay.collected') ?></option>
            <option value="pending" <?= $show === 'pending' ? 'selected' : '' ?>><?= te('pay.pending') ?></option>
        </select></div>
    <div class="col-md-2 mb-2">
        <div class="btn-group btn-block"><button class="btn btn-primary" style="width:50%"><i class="fas fa-filter"></i> <?= te('common.filter') ?></button>
        <a href="<?= SITE_URL ?>/reports_financial.php" class="btn btn-outline-secondary" style="width:50%"><?= te('common.reset') ?></a></div>
    </div>
</div>
</form>

<div class="row mb-4">
    <div class="col-6 col-md-4 col-lg mb-3"><div class="card kpi-card"><div class="card-body"><div class="kpi-label"><?= te('rep.total_booked') ?></div><div class="kpi-value mt-1 text-primary"><?= formatMoney($totalBooked) ?></div></div></div></div>
    <div class="col-6 col-md-4 col-lg mb-3"><div class="card kpi-card"><div class="card-body"><div class="kpi-label"><?= te('rep.total_collected') ?></div><div class="kpi-value mt-1 text-success"><?= formatMoney($totalCollected) ?></div></div></div></div>
    <div class="col-6 col-md-4 col-lg mb-3"><div class="card kpi-card"><div class="card-body"><div class="kpi-label"><?= te('rep.total_expenses') ?></div><div class="kpi-value mt-1 text-danger"><?= formatMoney($expTotal) ?></div></div></div></div>
    <div class="col-6 col-md-4 col-lg mb-3"><div class="card kpi-card"><div class="card-body"><div class="kpi-label"><?= te('rep.total_pending') ?></div><div class="kpi-value mt-1 text-warning"><?= formatMoney($totalPending) ?></div></div></div></div>
    <div class="col-12 col-md-4 col-lg mb-3"><div class="card kpi-card"><div class="card-body"><div class="kpi-label"><?= te('rep.net_collected') ?></div><div class="kpi-value mt-1 <?= $totalCollected - $expTotal >= 0 ? 'text-info' : 'text-danger' ?>"><?= formatMoney($totalCollected - $expTotal) ?></div></div></div></div>
</div>

<div class="row mb-4">
    <div class="col-lg-8 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-line mr-2"></i><?= te('dash.revenue_trend') ?></span>
                <small class="text-muted"><i class="fas fa-info-circle mr-1"></i><?= te('rep.trend_includes_general') ?></small>
            </div>
            <div class="card-body"><div class="chart-container"><canvas id="revTrendChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="card mb-3"><div class="card-header"><i class="fas fa-balance-scale mr-2"></i><?= te('rep.revenue_vs_expenses') ?></div>
            <div class="card-body text-center"><div class="chart-container" style="height:260px"><canvas id="revExp"></canvas></div></div>
        </div>
        <div class="card mb-3"><div class="card-header"><i class="fas fa-chart-pie mr-2"></i><?= te('rep.collected_vs_pending') ?></div>
            <div class="card-body text-center"><div class="chart-container" style="height:220px"><canvas id="colPen"></canvas></div></div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-12">
        <div class="card"><div class="card-body p-0"><div class="table-responsive">
        <table class="table table-striped mb-0"><thead><tr>
            <th><?= te('th.booking') ?></th><th><?= te('th.client') ?></th><th><?= te('th.date') ?></th><th class="text-right"><?= te('th.quoted') ?></th><th class="text-right"><?= te('th.collected') ?></th><th class="text-right"><?= te('th.pending') ?></th><th><?= te('th.status') ?></th>
        </tr></thead><tbody>
        <?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted py-5"><?= te('rep.no_results') ?></td></tr>
        <?php else: foreach ($rows as $r):
            $pend = max(0, (float)$r['quoted_amount'] - (float)$r['collected']);
        ?>
            <tr>
                <td><a href="<?= SITE_URL ?>/booking_view.php?id=<?= $r['id'] ?>" class="font-weight-bold"><?= e($r['booking_number']) ?></a></td>
                <td><?= e($r['client_name']) ?></td>
                <td><?= formatDate($r['date_from']) ?><?= $r['date_from'] !== $r['date_to'] ? ' → '.formatDate($r['date_to']) : '' ?></td>
                <td class="text-right font-weight-semibold"><?= formatMoney($r['quoted_amount']) ?></td>
                <td class="text-right text-success"><?= formatMoney($r['collected']) ?></td>
                <td class="text-right <?= $pend > 0 ? 'text-danger font-weight-semibold' : '' ?>"><?= formatMoney($pend) ?></td>
                <td><span class="status-badge status-<?= strtolower(str_replace([' ','-','/'],['_','_',''], $r['payment_status'])) ?>"><?= te(t_payment_status($r['payment_status'])) ?></span></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody><tfoot><tr class="bg-light font-weight-bold">
            <td colspan="3" class="text-right"><?= te('rep.totals') ?></td>
            <td class="text-right text-primary"><?= formatMoney($totalBooked) ?></td>
            <td class="text-right text-success"><?= formatMoney($totalCollected) ?></td>
            <td class="text-right text-danger"><?= formatMoney($totalPending) ?></td><td></td>
        </tr></tfoot></table></div></div></div>
    </div>
</div>
<script>
$(function() {
    var isRTL = document.documentElement.dir === 'rtl';
    var rev = <?= $totalCollected ?>, exp = <?= $expTotal ?>, net = Math.max(0, <?= $totalCollected - $expTotal ?>);
    var r1 = document.getElementById('revExp');
    if (r1) new Chart(r1, { type: 'bar', data: { labels: ['<?= t('pay.collected') ?>','<?= t('dash.kpi_expenses') ?>','<?= t('dash.kpi_net') ?>'],
        datasets: [{ data: [rev, exp, net], backgroundColor: ['#28a745','#dc3545','#17a2b8'] }] },
        options: { responsive: true, maintainAspectRatio: false,
            legend: { display: false, labels: { rtl: isRTL, textDirection: isRTL ? 'rtl' : 'ltr' } },
            scales: { yAxes: [{ ticks: { beginAtZero: true, callback: v => v.toLocaleString() } }] } } });
    var c = document.getElementById('colPen');
    if (c) new Chart(c, { type: 'doughnut', data: { labels: ['<?= t('pay.collected') ?>','<?= t('pay.pending') ?>'],
        datasets: [{ data: [<?= $totalCollected ?>, <?= $totalPending ?>], backgroundColor: ['#28a745','#ffc107'] }] },
        options: { responsive: true, maintainAspectRatio: false,
            legend: { position: 'bottom', labels: { rtl: isRTL, textDirection: isRTL ? 'rtl' : 'ltr' } } } });

    var trend = <?= json_encode($trendData) ?>;
    var rt = document.getElementById('revTrendChart');
    if (rt) new Chart(rt, {
        type: 'line',
        data: {
            labels: trend.map(d => d.label),
            datasets: [
                { label: '<?= t('dash.chart_booked') ?>', data: trend.map(d => d.booked), borderColor: '#667eea', backgroundColor: 'rgba(102,126,234,0.1)', tension: 0.3, fill: true },
                { label: '<?= t('dash.chart_collected') ?>', data: trend.map(d => d.collected), borderColor: '#11998e', backgroundColor: 'rgba(17,153,142,0.1)', tension: 0.3, fill: true },
                { label: '<?= t('dash.kpi_expenses') ?>', data: trend.map(d => d.expenses), borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.08)', tension: 0.3, fill: true, borderDash: [6, 4], borderWidth: 2 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { position: 'top', labels: { rtl: isRTL, textDirection: isRTL ? 'rtl' : 'ltr' } },
            scales: { yAxes: [{ ticks: { beginAtZero: true, callback: v => v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',') } }] }
        }
    });
});
</script>
<?php else: ?>
<?php require $subPageToLoad; ?>
<?php endif; ?>
<?php include SITE_PATH . '/includes/footer.php';
