<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('view_financials');

$inReportsParent = isset($tab) && $tab === 'expenses';
if (!$inReportsParent) {
    $page_title = t('title.expenses_report');
    $active_nav = 'reports';
    $tab = null;
}

$mStart = new DateTime('first day of this month');
$mEnd = new DateTime('last day of this month');
$df = isset($_GET['date_from']) && trim($_GET['date_from']) !== '' ? $_GET['date_from'] : $mStart->format('d/m/Y');
$dt = isset($_GET['date_to']) && trim($_GET['date_to']) !== '' ? $_GET['date_to'] : $mEnd->format('d/m/Y');
$typeId = (int)($_GET['expense_type_id'] ?? 0);

$where = ['1=1']; $params = [];
if ($df !== '') { $d = DateTime::createFromFormat('d/m/Y', $df); if ($d) { $where[] = "e.date >= ?"; $params[] = $d->format('Y-m-d'); } }
if ($dt !== '') { $d = DateTime::createFromFormat('d/m/Y', $dt); if ($d) { $where[] = "e.date <= ?"; $params[] = $d->format('Y-m-d'); } }
if ($typeId > 0) { $where[] = "e.expense_type_id = ?"; $params[] = $typeId; }

$sql = "SELECT e.*, et.name as type_name, u.name as user_name FROM expenses e
    INNER JOIN expense_types et ON e.expense_type_id = et.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE " . implode(' AND ', $where) . " ORDER BY e.date DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$stmt = $conn->prepare("SELECT et.id, et.name, COALESCE(SUM(e2.amount),0) as total FROM expense_types et
    LEFT JOIN expenses e2 ON e2.expense_type_id = et.id".
    (count($where) ? " AND " . implode(" AND ", array_map(function($w){ return preg_replace('/\be\./','e2.', $w); }, $where)) : "")."
    GROUP BY et.id, et.name ORDER BY total DESC LIMIT 10");
$stmt->execute($params);
$byType = $stmt->fetchAll();

$total = array_sum(array_column($rows, 'amount'));
$types = $conn->query("SELECT id, name FROM expense_types WHERE active=1 ORDER BY name")->fetchAll();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="expenses_report_'.date('Ymd').'.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, [t('th.date'),t('th.type'),t('th.amount'),t('th.description'),t('th.method'),t('th.reference'),t('th.created_by')]);
    foreach ($rows as $r) fputcsv($out, [$r['date'],$r['type_name'],$r['amount'],$r['description'],$r['payment_method'],$r['reference'],$r['user_name']]);
    fputcsv($out, [t('rep.totals'),'',$total]);
    fclose($out);
    exit;
}

if (!$inReportsParent) {
    include SITE_PATH . '/includes/header.php';
    echo flashMessages();
?>
<div class="row mb-3 align-items-end">
    <div class="col-md-6"><h1 class="page-title"><?= te('title.expenses_report') ?></h1><p class="page-subtitle"><?= te('title.expenses_report_sub') ?></p></div>
    <div class="col-md-6 text-md-right">
        <div class="btn-group mr-2 mb-2">
            <a href="<?= SITE_URL ?>/reports_financial.php?tab=financial" class="btn btn-outline-secondary"><?= te('tab.financial') ?></a>
            <a href="<?= SITE_URL ?>/reports_financial.php?tab=expenses" class="btn btn-outline-secondary active"><?= te('tab.expenses') ?></a>
            <a href="<?= SITE_URL ?>/reports_financial.php?tab=inventory" class="btn btn-outline-secondary"><?= te('tab.inventory') ?></a>
            <a href="<?= SITE_URL ?>/reports_financial.php?tab=client" class="btn btn-outline-secondary"><?= te('tab.client_statement') ?></a>
        </div>
        <a href="<?= e($_SERVER['REQUEST_URI']) ?><?= strpos($_SERVER['REQUEST_URI'], '?') ? '&' : '?' ?>export=csv" class="btn btn-outline-success mb-2"><i class="fas fa-file-csv mr-1"></i> <?= te('common.export_csv') ?></a>
    </div>
</div>
<?php } ?>
<form method="GET" class="card filter-row mb-3">
<?php if (!$inReportsParent): ?><input type="hidden" name="tab" value="expenses"><?php endif; ?>
<div class="row align-items-end">
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('field.from') ?></label><input name="date_from" class="form-control datepicker" value="<?= e($df) ?>" autocomplete="off"></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('field.to') ?></label><input name="date_to" class="form-control datepicker" value="<?= e($dt) ?>" autocomplete="off"></div>
    <div class="col-md-3 mb-2"><label class="small font-weight-semibold"><?= te('field.expense_type') ?></label>
        <select name="expense_type_id" class="form-control select2"><option value=""><?= te('common.all') ?></option>
            <?php foreach ($types as $t): ?><option value="<?= $t['id'] ?>" <?= $typeId === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-2 mb-2">
        <div class="btn-group btn-block"><button class="btn btn-primary" style="width:50%"><i class="fas fa-filter"></i> <?= te('common.filter') ?></button>
        <a href="<?= $inReportsParent ? SITE_URL.'/reports_financial.php?tab=expenses' : SITE_URL.'/reports_expenses.php' ?>" class="btn btn-outline-secondary" style="width:50%"><?= te('common.reset') ?></a></div>
    </div>
    <div class="col-md-3 mb-2"><div class="bg-light rounded p-2 text-center">
        <small class="text-muted"><?= te('exp.total_expenses') ?></small><h4 class="mb-0 text-danger font-weight-bold"><?= formatMoney($total) ?></h4>
    </div></div>
</div>
</form>

<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card"><div class="card-body p-0"><div class="table-responsive">
        <table class="table table-striped mb-0"><thead><tr>
            <th><?= te('th.date') ?></th><th><?= te('th.type') ?></th><th><?= te('th.description') ?></th><th><?= te('th.method') ?></th><th class="text-right"><?= te('th.amount') ?></th><th><?= te('th.by') ?></th>
        </tr></thead><tbody>
        <?php if (empty($rows)): ?><tr><td colspan="6" class="text-center text-muted py-5"><?= te('common.no_records') ?></td></tr>
        <?php else: foreach ($rows as $r): ?>
            <tr>
                <td><?= formatDate($r['date']) ?></td>
                <td class="font-weight-semibold"><?= e($r['type_name']) ?></td>
                <td><?= e($r['description'] ?? '-') ?></td>
                <td><?= e(t_payment_method($r['payment_method'] ?? '-')) ?></td>
                <td class="text-right font-weight-semibold text-danger"><?= formatMoney($r['amount']) ?></td>
                <td><small class="text-muted"><?= e($r['user_name'] ?? '') ?></small></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody><tfoot><tr class="bg-light font-weight-bold"><td colspan="4" class="text-right"><?= te('common.total') ?></td><td class="text-right text-danger h5 mb-0"><?= formatMoney($total) ?></td><td></td></tr></tfoot></table>
        </div></div></div>
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-header"><i class="fas fa-chart-pie mr-2"></i><?= te('exp.by_type') ?></div>
            <div class="card-body">
                <div class="chart-container" style="height:280px"><canvas id="expChart"></canvas></div>
                <table class="table table-sm mt-3 mb-0">
                    <tbody><?php foreach ($byType as $b): ?>
                        <tr><td class="font-weight-semibold"><?= e($b['name']) ?></td><td class="text-right"><?= formatMoney($b['total']) ?></td></tr>
                    <?php endforeach; ?></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
$(function() {
    var isRTL = document.documentElement.dir === 'rtl';
    var d = <?= json_encode($byType) ?>;
    if (!d.length) return;
    var c = document.getElementById('expChart');
    if (c) new Chart(c, {
        type: 'doughnut',
        data: {
            labels: d.map(x => x.name),
            datasets: [{
                data: d.map(x => parseFloat(x.total)),
                backgroundColor: ['#667eea','#11998e','#f5576c','#4facfe','#fa709a','#a18cd1','#f5af19','#2ECC71','#e74c3c','#34495e']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { position: 'bottom', labels: { rtl: isRTL, textDirection: isRTL ? 'rtl' : 'ltr' } }
        }
    });
});
</script>
<?php
if (!$inReportsParent) {
    include SITE_PATH . '/includes/footer.php';
}
