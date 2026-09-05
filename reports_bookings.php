<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('view_reports');

$report = $_GET['report'] ?? 'bookings';
$page_title = t('title.bookings_report');
$active_nav = 'reports';

$mStart = new DateTime('first day of this month');
$mEnd = new DateTime('last day of this month');
$df = isset($_GET['date_from']) && trim($_GET['date_from']) !== '' ? $_GET['date_from'] : $mStart->format('d/m/Y');
$dt = isset($_GET['date_to']) && trim($_GET['date_to']) !== '' ? $_GET['date_to'] : $mEnd->format('d/m/Y');
$clientId = (int)($_GET['client_id'] ?? 0);
$status = $_GET['status'] ?? '';
$paymentStatus = $_GET['payment_status'] ?? '';
$catId = (int)($_GET['category_id'] ?? 0);
$itemTypeId = (int)($_GET['item_type_id'] ?? 0);

$where = ['1=1']; $params = [];
if ($df !== '') { $d = DateTime::createFromFormat('d/m/Y', $df); if ($d) { $where[] = "b.date_from >= ?"; $params[] = $d->format('Y-m-d'); } }
if ($dt !== '') { $d = DateTime::createFromFormat('d/m/Y', $dt); if ($d) { $where[] = "b.date_from <= ?"; $params[] = $d->format('Y-m-d'); } }
if ($clientId > 0) { $where[] = "b.client_id = ?"; $params[] = $clientId; }
if ($status !== '') { $where[] = "b.status = ?"; $params[] = $status; }
if ($paymentStatus !== '') { $where[] = "b.payment_status = ?"; $params[] = $paymentStatus; }

$itemJoin = '';
if ($catId > 0 || $itemTypeId > 0) {
    $itemJoin = " INNER JOIN booking_items bi ON bi.booking_id = b.id INNER JOIN item_types it ON bi.item_type_id = it.id ";
    if ($catId > 0) { $where[] = "it.category_id = ?"; $params[] = $catId; }
    if ($itemTypeId > 0) { $where[] = "it.id = ?"; $params[] = $itemTypeId; }
}

$sql = "SELECT DISTINCT b.*, c.name as client_name, c.phone as client_phone,
    (SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = b.id) as collected
    FROM bookings b INNER JOIN clients c ON b.client_id = c.id $itemJoin
    WHERE " . implode(' AND ', $where) . " ORDER BY b.date_from DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$totals = ['booked' => 0, 'collected' => 0, 'pending' => 0, 'rak' => 0, 'count' => 0];
foreach ($bookings as $b) {
    if ($b['status'] !== 'Canceled') {
        $totals['booked'] += (float)$b['quoted_amount'];
        $totals['rak'] += (float)$b['dj_rak_amount'];
        $totals['count']++;
    }
    $totals['collected'] += (float)$b['collected'];
}
$totals['pending'] = max(0, $totals['booked'] - $totals['collected']);

$clients = $conn->query("SELECT id, name, phone FROM clients ORDER BY name")->fetchAll();
$categories = $conn->query("SELECT id, name FROM categories WHERE active=1 ORDER BY name")->fetchAll();
$itemTypes = $conn->query("SELECT it.id, it.name, c.name as cat_name FROM item_types it INNER JOIN categories c ON it.category_id = c.id WHERE it.active=1 ORDER BY c.name, it.name")->fetchAll();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bookings_report_'.date('Ymd').'.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, [t('th.booking_number'),t('th.client'),t('th.phone'),t('field.date_from'),t('field.date_to'),t('th.location'),t('th.equipment'),t('th.quoted'),t('th.collected'),t('th.pending'),t('th.dj_rak'),t('th.booking_status'),t('th.payment_status')]);
    foreach ($bookings as $b) {
        $stmt = $conn->prepare("SELECT GROUP_CONCAT(CONCAT(bi.quantity,'x ',it.name) SEPARATOR ', ') FROM booking_items bi INNER JOIN item_types it ON bi.item_type_id=it.id WHERE bi.booking_id=?");
        $stmt->execute([$b['id']]); $items = $stmt->fetchColumn();
        $pend = max(0, (float)$b['quoted_amount'] - (float)$b['collected']);
        fputcsv($out, [$b['booking_number'],$b['client_name'],$b['client_phone'],$b['date_from'],$b['date_to'],$b['location'],$items,
            $b['quoted_amount'],$b['collected'],$pend,$b['dj_rak_amount'],$b['status'],$b['payment_status']]);
    }
    fclose($out);
    exit;
}

include SITE_PATH . '/includes/header.php';
echo flashMessages();
?>
<div class="row mb-3 align-items-end">
    <div class="col-md-6"><h1 class="page-title"><?= te('title.bookings_report') ?></h1><p class="page-subtitle"><?= te('title.bookings_report_sub') ?></p></div>
    <div class="col-md-6 text-md-right">
        <a href="<?= e($_SERVER['REQUEST_URI']) ?><?= strpos($_SERVER['REQUEST_URI'], '?') ? '&' : '?' ?>export=csv" class="btn btn-outline-success"><i class="fas fa-file-csv mr-1"></i> <?= te('btn.export_csv') ?></a>
    </div>
</div>
<form method="GET" class="card filter-row mb-3">
<div class="row align-items-end">
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('field.from') ?></label><input name="date_from" class="form-control datepicker" value="<?= e($df) ?>" autocomplete="off"></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('field.to') ?></label><input name="date_to" class="form-control datepicker" value="<?= e($dt) ?>" autocomplete="off"></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('field.client') ?></label>
        <select name="client_id" class="form-control select2"><option value=""><?= te('common.all') ?></option>
            <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>" <?= $clientId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('th.booking_status') ?></label>
        <select name="status" class="form-control"><option value=""><?= te('common.all') ?></option>
            <?php foreach (['Draft','Quotation','Confirmed','Change Requested','Event Completed','Closed','Canceled'] as $s): ?><option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(t_booking_status($s)) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('th.payment_status') ?></label>
        <select name="payment_status" class="form-control"><option value=""><?= te('common.all') ?></option>
            <?php foreach (['Not Collected','Partially Collected','Fully Collected','Canceled'] as $s): ?><option value="<?= e($s) ?>" <?= $paymentStatus === $s ? 'selected' : '' ?>><?= e(t_payment_status($s)) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('field.category') ?></label>
        <select name="category_id" class="form-control select2"><option value=""><?= te('common.all') ?></option>
            <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-3 mb-2"><label class="small font-weight-semibold"><?= te('field.item_type') ?></label>
        <select name="item_type_id" class="form-control select2"><option value=""><?= te('common.all') ?></option>
            <?php foreach ($itemTypes as $t): ?><option value="<?= $t['id'] ?>" <?= $itemTypeId === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['cat_name'].' / '.$t['name']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-3 mb-2">
        <div class="btn-group btn-block"><button class="btn btn-primary" style="width:50%"><i class="fas fa-filter"></i> <?= te('common.filter') ?></button>
        <a href="<?= SITE_URL ?>/reports_bookings.php" class="btn btn-outline-secondary" style="width:50%"><?= te('common.reset') ?></a></div>
    </div>
    <div class="col-md-6 mb-2">
        <div class="d-flex justify-content-between bg-light rounded p-2">
            <div><small class="text-muted"><?= te('rep.bookings_kpi') ?></small><h5 class="mb-0 font-weight-bold"><?= $totals['count'] ?></h5></div>
            <div><small class="text-muted"><?= te('th.quoted') ?></small><h5 class="mb-0 text-primary font-weight-bold"><?= formatMoney($totals['booked']) ?></h5></div>
            <div><small class="text-muted"><?= te('th.collected') ?></small><h5 class="mb-0 text-success font-weight-bold"><?= formatMoney($totals['collected']) ?></h5></div>
            <div><small class="text-muted"><?= te('th.pending') ?></small><h5 class="mb-0 text-danger font-weight-bold"><?= formatMoney($totals['pending']) ?></h5></div>
            <?php if (hasPermission('view_dj_rak')): ?><div><small class="text-muted"><?= te('rep.dj_rak_kpi') ?></small><h5 class="mb-0 text-warning font-weight-bold"><?= formatMoney($totals['rak']) ?></h5></div><?php endif; ?>
        </div>
    </div>
</div>
</form>
<div class="card"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-striped mb-0"><thead><tr>
    <th><?= te('th.row_number') ?></th><th><?= te('th.client') ?></th><th><?= te('th.phone') ?></th><th><?= te('th.dates') ?></th><th><?= te('th.location') ?></th><th><?= te('th.equipment') ?></th>
    <th class="text-right"><?= te('th.quoted') ?></th><th class="text-right"><?= te('th.collected') ?></th><th class="text-right"><?= te('th.pending') ?></th>
    <?php if (hasPermission('view_dj_rak')): ?><th class="text-right"><?= te('th.dj_rak') ?></th><?php endif; ?>
    <th><?= te('th.status') ?></th></tr></thead><tbody>
<?php if (empty($bookings)): ?><tr><td colspan="11" class="text-center text-muted py-5"><?= te('rep.no_results') ?></td></tr>
<?php else: foreach ($bookings as $b):
    $stmt = $conn->prepare("SELECT GROUP_CONCAT(CONCAT(bi.quantity,'x ',it.name) SEPARATOR ', ') FROM booking_items bi INNER JOIN item_types it ON bi.item_type_id=it.id WHERE bi.booking_id=?");
    $stmt->execute([$b['id']]); $items = $stmt->fetchColumn();
    $pend = max(0, (float)$b['quoted_amount'] - (float)$b['collected']);
    $sClass = strtolower(str_replace([' ','-','/'],['_','_',''], $b['status']));
?>
<tr>
    <td><a href="<?= SITE_URL ?>/booking_view.php?id=<?= $b['id'] ?>" class="font-weight-bold"><?= e($b['booking_number']) ?></a></td>
    <td><?= e($b['client_name']) ?></td>
    <td><?= e($b['client_phone']) ?></td>
    <td><?= formatDate($b['date_from']) ?><?= $b['date_from'] !== $b['date_to'] ? ' → '.formatDate($b['date_to']) : '' ?></td>
    <td><?= e($b['location']) ?></td>
    <td><small><?= e($items ?: '-') ?></small></td>
    <td class="text-right font-weight-semibold"><?= formatMoney($b['quoted_amount']) ?></td>
    <td class="text-right text-success"><?= formatMoney($b['collected']) ?></td>
    <td class="text-right <?= $pend > 0 ? 'text-danger font-weight-semibold' : '' ?>"><?= formatMoney($pend) ?></td>
    <?php if (hasPermission('view_dj_rak')): ?><td class="text-right"><?= formatMoney($b['dj_rak_amount']) ?></td><?php endif; ?>
    <td><span class="status-badge status-<?= $sClass ?>"><?= e(t_booking_status($b['status'])) ?></span></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div></div>
<?php include SITE_PATH . '/includes/footer.php'; ?>
