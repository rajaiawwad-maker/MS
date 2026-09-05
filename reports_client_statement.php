<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('view_financials');

$inReportsParent = isset($tab) && $tab === 'client';
if (!$inReportsParent) {
    $page_title = t('title.client_account_statement');
    $active_nav = 'reports';
    $tab = null;
}

$mStart = new DateTime('first day of this month');
$mEnd = new DateTime('last day of this month');
$clientId = (int)($_GET['client_id'] ?? 0);
$df = isset($_GET['date_from']) && trim($_GET['date_from']) !== '' ? $_GET['date_from'] : $mStart->format('d/m/Y');
$dt = isset($_GET['date_to']) && trim($_GET['date_to']) !== '' ? $_GET['date_to'] : $mEnd->format('d/m/Y');

$client = null;
$bookings = [];
$totals = ['quoted' => 0, 'collected' => 0, 'pending' => 0];

if ($clientId > 0) {
    $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch();

    if ($client) {
        $where = ["client_id = ?"]; $params = [$clientId];
        if ($df !== '') { $d = DateTime::createFromFormat('d/m/Y', $df); if ($d) { $where[] = "date_from >= ?"; $params[] = $d->format('Y-m-d'); } }
        if ($dt !== '') { $d = DateTime::createFromFormat('d/m/Y', $dt); if ($d) { $where[] = "date_from <= ?"; $params[] = $d->format('Y-m-d'); } }

        $sql = "SELECT b.*,
            (SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = b.id) as collected
            FROM bookings b WHERE " . implode(' AND ', $where) . " ORDER BY b.date_from DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $bookings = $stmt->fetchAll();

        foreach ($bookings as $b) {
            if ($b['status'] !== 'Canceled') $totals['quoted'] += (float)$b['quoted_amount'];
            $totals['collected'] += (float)$b['collected'];
        }
        $totals['pending'] = max(0, $totals['quoted'] - $totals['collected']);
    }
}

$clients = $conn->query("SELECT id, name, phone FROM clients ORDER BY name")->fetchAll();

if (isset($_GET['export']) && $_GET['export'] === 'csv' && $client) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="client_statement_'.preg_replace('/[^A-Za-z0-9]/','_',$client['name']).'_'.date('Ymd').'.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, [t('rep.client_statement_for').': '.$client['name'], t('rep.phone_label').': '.$client['phone']]);
    fputcsv($out, [t('th.date'),t('th.booking_number'),t('th.quoted'),t('th.collected'),t('th.pending'),t('th.status')]);
    foreach ($bookings as $b) {
        $pend = $b['status'] === 'Canceled' ? 0 : max(0, (float)$b['quoted_amount'] - (float)$b['collected']);
        fputcsv($out, [$b['date_from'],$b['booking_number'],$b['quoted_amount'],$b['collected'],$pend,$b['status']]);
    }
    fputcsv($out, []);
    fputcsv($out, [t('rep.totals'),'',$totals['quoted'],$totals['collected'],$totals['pending']]);
    exit;
}

if (!$inReportsParent) {
    include SITE_PATH . '/includes/header.php';
    echo flashMessages();
?>
<div class="row mb-3 align-items-end">
    <div class="col-md-6"><h1 class="page-title"><?= te('title.client_account_statement') ?></h1><p class="page-subtitle"><?= te('title.client_account_statement_sub') ?></p></div>
    <div class="col-md-6 text-md-right">
        <div class="btn-group mr-2 mb-2">
            <a href="<?= SITE_URL ?>/reports_financial.php?tab=financial" class="btn btn-outline-secondary"><?= te('tab.financial') ?></a>
            <a href="<?= SITE_URL ?>/reports_financial.php?tab=expenses" class="btn btn-outline-secondary"><?= te('tab.expenses') ?></a>
            <a href="<?= SITE_URL ?>/reports_financial.php?tab=inventory" class="btn btn-outline-secondary"><?= te('tab.inventory') ?></a>
            <a href="<?= SITE_URL ?>/reports_financial.php?tab=client" class="btn btn-outline-secondary active"><?= te('tab.client_statement') ?></a>
        </div>
        <?php if ($client): ?>
            <a href="?<?= http_build_query($_GET) ?>&export=csv" class="btn btn-outline-success mb-2"><i class="fas fa-file-csv mr-1"></i> <?= te('common.export_csv') ?></a>
        <?php endif; ?>
    </div>
</div>
<?php } ?>
<form method="GET" class="card filter-row mb-3">
<?php if ($inReportsParent): ?><input type="hidden" name="tab" value="client"><?php endif; ?>
<div class="row align-items-end">
    <div class="col-md-5 mb-2"><label class="small font-weight-semibold"><?= te('field.client') ?> *</label>
        <select name="client_id" class="form-control select2" required>
            <option value=""><?= te('field.client_select') ?></option>
            <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>" <?= $clientId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name'].' ('.$c['phone'].')') ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('field.from') ?></label><input name="date_from" class="form-control datepicker" value="<?= e($df) ?>" autocomplete="off"></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('field.to') ?></label><input name="date_to" class="form-control datepicker" value="<?= e($dt) ?>" autocomplete="off"></div>
    <div class="col-md-3 mb-2">
        <div class="btn-group btn-block"><button class="btn btn-primary" style="width:50%"><i class="fas fa-search"></i> <?= te('btn.view_statement') ?></button>
        <a href="<?= $inReportsParent ? SITE_URL.'/reports_financial.php?tab=client' : SITE_URL.'/reports_client_statement.php' ?>" class="btn btn-outline-secondary" style="width:50%"><?= te('common.reset') ?></a></div>
    </div>
</div>
</form>

<?php if ($client): ?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="fas fa-user-tie mr-2"></i><strong><?= e($client['name']) ?></strong>
            <span class="ml-3 text-muted"><i class="fas fa-phone mr-1"></i><?= e($client['phone']) ?></span>
            <?php if ($client['email']): ?><span class="ml-3 text-muted"><i class="fas fa-envelope mr-1"></i><?= e($client['email']) ?></span><?php endif; ?>
        </div>
        <?php if (hasPermission('send_whatsapp') && $totals['pending'] > 0):
            $countryCode = getSetting('whatsapp_country_code', '966');
            $phone = sanitizePhone($client['phone'], $countryCode);
            $companyName = getSetting('company_name', 'DJ RAK');
            $msg = buildPendingBalanceWhatsAppI18n($client, $totals['pending']);
        ?>
            <a href="https://wa.me/<?= $phone ?>?text=<?= urlencode($msg) ?>" target="_blank" class="btn btn-sm btn-whatsapp"><i class="fab fa-whatsapp mr-1"></i> <?= te('rep.send_payment_reminder') ?></a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4"><div class="h6 text-muted mb-1"><?= te('c.total_booked') ?></div><div class="h3 font-weight-bold text-primary mb-0"><?= formatMoney($totals['quoted']) ?></div></div>
            <div class="col-md-4"><div class="h6 text-muted mb-1"><?= te('c.total_collected') ?></div><div class="h3 font-weight-bold text-success mb-0"><?= formatMoney($totals['collected']) ?></div></div>
            <div class="col-md-4"><div class="h6 text-muted mb-1"><?= te('c.balance_due') ?></div><div class="h3 font-weight-bold text-danger mb-0"><?= formatMoney($totals['pending']) ?></div></div>
        </div>
    </div>
</div>

<div class="card"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-striped mb-0"><thead><tr>
    <th><?= te('th.date') ?></th><th><?= te('th.booking_number') ?></th><th><?= te('th.location') ?></th><th class="text-right"><?= te('th.quoted') ?></th><th class="text-right"><?= te('th.collected') ?></th><th class="text-right"><?= te('th.pending') ?></th><th><?= te('th.status') ?></th><th></th>
</tr></thead><tbody>
<?php if (empty($bookings)): ?><tr><td colspan="8" class="text-center text-muted py-5"><?= te('rep.no_bookings_client') ?></td></tr>
<?php else: foreach ($bookings as $b):
    $pend = $b['status'] === 'Canceled' ? 0 : max(0, (float)$b['quoted_amount'] - (float)$b['collected']);
    $sClass = strtolower(str_replace([' ','-','/'],['_','_',''], $b['status']));
?>
<tr>
    <td><?= formatDate($b['date_from']) ?><?= $b['date_from'] !== $b['date_to'] ? ' → '.formatDate($b['date_to']) : '' ?></td>
    <td class="font-weight-bold"><?= e($b['booking_number']) ?></td>
    <td><?= e($b['location']) ?></td>
    <td class="text-right <?= $b['status'] === 'Canceled' ? 'text-muted line-through' : 'font-weight-semibold' ?>"><?= formatMoney($b['quoted_amount']) ?></td>
    <td class="text-right text-success"><?= formatMoney($b['collected']) ?></td>
    <td class="text-right <?= $pend > 0 ? 'text-danger font-weight-semibold' : '' ?>"><?= formatMoney($pend) ?></td>
    <td><span class="status-badge status-<?= $sClass ?>"><?= e(t_booking_status($b['status'])) ?></span></td>
    <td class="text-right"><a href="<?= SITE_URL ?>/booking_view.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a></td>
</tr>
<?php endforeach; endif; ?>
</tbody><tfoot><tr class="bg-light font-weight-bold">
    <td colspan="3" class="text-right"><?= te('common.balance_summary') ?></td>
    <td class="text-right text-primary"><?= formatMoney($totals['quoted']) ?></td>
    <td class="text-right text-success"><?= formatMoney($totals['collected']) ?></td>
    <td class="text-right text-danger h5 mb-0"><?= formatMoney($totals['pending']) ?></td>
    <td colspan="2"></td>
</tr></tfoot></table></div></div></div>
<?php else: ?>
<div class="card"><div class="card-body text-center py-5 text-muted"><i class="fas fa-user mr-2"></i><?= te('c.select_client_prompt') ?></div></div>
<?php endif;
if (!$inReportsParent) {
    include SITE_PATH . '/includes/footer.php';
}
