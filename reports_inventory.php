<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('manage_inventory');

$inReportsParent = isset($tab) && $tab === 'inventory';
if (!$inReportsParent) {
    $page_title = t('title.inventory_report');
    $active_nav = 'reports';
    $tab = null;
}

$today = date('Y-m-d');
$mStart = new DateTime('first day of this month');
$mEnd = new DateTime('last day of this month');
$df = isset($_GET['date_from']) && trim($_GET['date_from']) !== '' ? $_GET['date_from'] : $mStart->format('d/m/Y');
$dt = isset($_GET['date_to']) && trim($_GET['date_to']) !== '' ? $_GET['date_to'] : $mEnd->format('d/m/Y');
$catId = (int)($_GET['category_id'] ?? 0);
$itId = (int)($_GET['item_type_id'] ?? 0);

$catFilter = $catId > 0 ? " AND c.id = $catId" : "";
$itFilter = $itId > 0 ? " AND it.id = $itId" : "";

$rows = $conn->query("SELECT it.id, it.name, it.quantity as total, c.name as category_name
    FROM item_types it INNER JOIN categories c ON it.category_id = c.id
    WHERE it.active = 1 $catFilter $itFilter ORDER BY c.name, it.name")->fetchAll();

foreach ($rows as &$r) {
    $r['booked'] = getBookedQuantity($r['id'], $today, $today);
    $r['available'] = max(0, (int)$r['total'] - (int)$r['booked']);
    $stmt = $conn->prepare("SELECT COUNT(*) FROM booking_items bi INNER JOIN bookings b ON bi.booking_id = b.id WHERE bi.item_type_id = ? AND b.status != 'Canceled' AND b.date_from >= ? AND b.date_from <= ?");
    $stmt->execute([$r['id'], date('Y-m-01'), date('Y-m-t')]);
    $r['month_count'] = (int)$stmt->fetchColumn();
    $stmt = $conn->prepare("SELECT COALESCE(SUM(b.quoted_amount),0) FROM bookings b INNER JOIN booking_items bi ON bi.booking_id = b.id WHERE bi.item_type_id = ? AND b.status != 'Canceled' AND b.date_from >= ? AND b.date_from <= ?");
    $stmt->execute([$r['id'], date('Y-01-01'), date('Y-12-31')]);
    $r['year_rev'] = (float)$stmt->fetchColumn();
}
unset($r);

$totalUnits = 0; $totalAvailable = 0; $totalBooked = 0;
foreach ($rows as $r) { $totalUnits += (int)$r['total']; $totalAvailable += (int)$r['available']; $totalBooked += (int)$r['booked']; }

$categories = $conn->query("SELECT id, name FROM categories WHERE active=1 ORDER BY name")->fetchAll();
$itemTypes = $conn->query("SELECT it.id, it.name, c.name as cat_name FROM item_types it INNER JOIN categories c ON it.category_id = c.id WHERE it.active=1 ORDER BY c.name, it.name")->fetchAll();

if (!$inReportsParent) {
    include SITE_PATH . '/includes/header.php';
    echo flashMessages();
?>
<div class="row mb-3 align-items-end">
    <div class="col-md-6"><h1 class="page-title"><?= te('title.inventory_report') ?></h1><p class="page-subtitle"><?= te('title.inventory_report_sub_standalone') ?></p></div>
    <div class="col-md-6 text-md-right">
        <div class="btn-group mr-2 mb-2">
            <a href="<?= SITE_URL ?>/reports_financial.php?tab=financial" class="btn btn-outline-secondary"><?= te('tab.financial') ?></a>
            <a href="<?= SITE_URL ?>/reports_financial.php?tab=expenses" class="btn btn-outline-secondary"><?= te('tab.expenses') ?></a>
            <a href="<?= SITE_URL ?>/reports_financial.php?tab=inventory" class="btn btn-outline-secondary active"><?= te('tab.inventory') ?></a>
            <a href="<?= SITE_URL ?>/reports_financial.php?tab=client" class="btn btn-outline-secondary"><?= te('tab.client_statement') ?></a>
        </div>
    </div>
</div>
<?php } ?>
<form method="GET" class="card filter-row mb-3">
<?php if ($inReportsParent): ?><input type="hidden" name="tab" value="inventory"><?php endif; ?>
<div class="row align-items-end">
    <div class="col-md-3 mb-2"><label class="small font-weight-semibold"><?= te('field.category') ?></label>
        <select name="category_id" id="catSel" class="form-control select2"><option value=""><?= te('common.all') ?></option>
            <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-3 mb-2"><label class="small font-weight-semibold"><?= te('field.item_type') ?></label>
        <select name="item_type_id" class="form-control select2"><option value=""><?= te('common.all') ?></option>
            <?php foreach ($itemTypes as $t): ?><option value="<?= $t['id'] ?>" <?= $itId === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['cat_name'].' / '.$t['name']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('field.check_date_from') ?></label><input name="date_from" class="form-control datepicker" value="<?= e($df) ?>" autocomplete="off"></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('field.check_date_to') ?></label><input name="date_to" class="form-control datepicker" value="<?= e($dt) ?>" autocomplete="off"></div>
    <div class="col-md-2 mb-2">
        <div class="btn-group btn-block"><button class="btn btn-primary" style="width:50%"><i class="fas fa-filter"></i> <?= te('common.apply') ?></button>
        <a href="<?= $inReportsParent ? SITE_URL.'/reports_financial.php?tab=inventory' : SITE_URL.'/reports_inventory.php' ?>" class="btn btn-outline-secondary" style="width:50%"><?= te('common.reset') ?></a></div>
    </div>
</div>
</form>

<div class="row mb-4">
    <div class="col-md-3 mb-3"><div class="card kpi-card"><div class="card-body"><div class="kpi-label"><?= te('inv.total_item_types') ?></div><div class="kpi-value mt-1"><?= count($rows) ?></div></div></div></div>
    <div class="col-md-3 mb-3"><div class="card kpi-card"><div class="card-body"><div class="kpi-label"><?= te('inv.total_units') ?></div><div class="kpi-value mt-1"><?= $totalUnits ?></div></div></div></div>
    <div class="col-md-3 mb-3"><div class="card kpi-card"><div class="card-body"><div class="kpi-label"><?= te('inv.units_available') ?></div><div class="kpi-value mt-1 text-success"><?= $totalAvailable ?></div></div></div></div>
    <div class="col-md-3 mb-3"><div class="card kpi-card"><div class="card-body"><div class="kpi-label"><?= te('inv.booked_today') ?></div><div class="kpi-value mt-1 text-danger"><?= $totalBooked ?></div></div></div></div>
</div>

<div class="card"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-striped mb-0"><thead><tr>
    <th><?= te('th.category') ?></th><th><?= te('th.item_type') ?></th><th class="text-center"><?= te('th.total') ?></th><th class="text-center"><?= te('th.booked') ?></th>
    <th class="text-center"><?= te('th.available') ?></th><th class="text-center"><?= te('th.usage_month') ?></th><th class="text-right"><?= te('th.revenue_year') ?></th><th><?= te('th.availability') ?></th>
</tr></thead><tbody>
<?php if (empty($rows)): ?><tr><td colspan="8" class="text-center text-muted py-5"><?= te('common.no_records') ?></td></tr>
<?php else: foreach ($rows as $r):
    $pct = (int)$r['total'] > 0 ? round(((int)$r['available'] / (int)$r['total']) * 100) : 0;
    $cls = $r['available'] <= 0 ? 'equipment-unavailable' : ($r['available'] < (int)$r['total'] ? 'equipment-limited' : 'equipment-available');
?>
    <tr>
        <td><?= e($r['category_name']) ?></td>
        <td class="font-weight-semibold"><?= e($r['name']) ?></td>
        <td class="text-center font-weight-bold"><?= (int)$r['total'] ?></td>
        <td class="text-center"><?= (int)$r['booked'] ?></td>
        <td class="text-center font-weight-bold <?= $cls ?>"><?= (int)$r['available'] ?></td>
        <td class="text-center"><?= (int)$r['month_count'] ?></td>
        <td class="text-right font-weight-semibold"><?= formatMoney($r['year_rev']) ?></td>
        <td style="min-width:160px"><div class="progress" style="height:20px">
            <div class="progress-bar <?= $pct < 25 ? 'bg-danger' : ($pct < 60 ? 'bg-warning' : 'bg-success') ?>" style="width:<?= $pct ?>%"><?= $pct ?>%</div>
        </div></td>
    </tr>
<?php endforeach; endif; ?>
</tbody></table></div></div></div>
<?php
if (!$inReportsParent) {
    include SITE_PATH . '/includes/footer.php';
}
