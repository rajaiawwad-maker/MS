<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
$page_title = t('title.payments');
$active_nav = 'payments';

$canEdit = hasPermission('record_payments');
if (!$canEdit) {
    requirePermission('view_bookings');
}

$bookingFilter = (int)($_GET['booking_id'] ?? 0);
$clientFilter = (int)($_GET['client_id'] ?? 0);
$methodFilter = trim($_GET['payment_method'] ?? '');
$df = $_GET['date_from'] ?? '';
$dt = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = ['1=1']; $params = [];
if ($bookingFilter > 0) { $where[] = "p.booking_id = ?"; $params[] = $bookingFilter; }
if ($clientFilter > 0) { $where[] = "b.client_id = ?"; $params[] = $clientFilter; }
if ($methodFilter !== '') { $where[] = "p.payment_method = ?"; $params[] = $methodFilter; }
if ($df !== '') { $d = DateTime::createFromFormat('d/m/Y', $df); if ($d) { $where[] = "p.payment_date >= ?"; $params[] = $d->format('Y-m-d'); } }
if ($dt !== '') { $d = DateTime::createFromFormat('d/m/Y', $dt); if ($d) { $where[] = "p.payment_date <= ?"; $params[] = $d->format('Y-m-d'); } }

$whereStr = implode(' AND ', $where);

$countSql = "SELECT COUNT(*) FROM payments p LEFT JOIN bookings b ON p.booking_id = b.id WHERE $whereStr";
$cntStmt = $conn->prepare($countSql); $cntStmt->execute($params); $totalAll = (int)$cntStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalAll / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

$sql = "SELECT p.*, b.booking_number, b.date_from as event_date, b.quoted_amount, c.name as client_name, c.id as client_id, u.name as user_name
    FROM payments p LEFT JOIN bookings b ON p.booking_id = b.id
    LEFT JOIN clients c ON b.client_id = c.id
    LEFT JOIN users u ON p.created_by = u.id
    WHERE $whereStr ORDER BY p.payment_date DESC, p.id DESC LIMIT $offset, $perPage";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$sumSql = "SELECT COALESCE(SUM(p.amount),0) FROM payments p LEFT JOIN bookings b ON p.booking_id = b.id WHERE $whereStr";
$sumStmt = $conn->prepare($sumSql); $sumStmt->execute($params); $filteredTotal = (float)$sumStmt->fetchColumn();

$todayStart = date('Y-m-d') . ' 00:00:00';
$todayEnd = date('Y-m-d') . ' 23:59:59';
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$kpiTodayStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_date = ?");
$kpiTodayStmt->execute([date('Y-m-d')]); $kpiToday = (float)$kpiTodayStmt->fetchColumn();

$kpiMonthStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_date >= ? AND payment_date <= ?");
$kpiMonthStmt->execute([$monthStart, $monthEnd]); $kpiMonth = (float)$kpiMonthStmt->fetchColumn();

$kpiAllStmt = $conn->query("SELECT COALESCE(SUM(amount),0) FROM payments");
$kpiAll = (float)$kpiAllStmt->fetchColumn();

$pendingStmt = $conn->query("SELECT COALESCE(SUM(quoted_amount),0) - (SELECT COALESCE(SUM(p.amount),0) FROM payments p INNER JOIN bookings b2 ON p.booking_id = b2.id)
    FROM bookings b WHERE b.status != 'Canceled'");
$kpiPending = max(0, (float)$pendingStmt->fetchColumn());

$bookingOpts = $conn->query("SELECT b.id, b.booking_number, b.date_from as event_date, b.quoted_amount, c.name as client_name,
    (SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = b.id) as collected
    FROM bookings b LEFT JOIN clients c ON b.client_id = c.id
    WHERE b.status != 'Canceled' ORDER BY b.date_from DESC LIMIT 300")->fetchAll();

$clientOpts = $conn->query("SELECT id, name, phone FROM clients ORDER BY name LIMIT 300")->fetchAll();

$methodOpts = ['Cash','Transfer','CliQ'];

function paginateUrl($pageNum) {
    global $bookingFilter, $clientFilter, $methodFilter, $df, $dt;
    $qs = array_filter([
        'booking_id' => $bookingFilter,
        'client_id' => $clientFilter,
        'payment_method' => $methodFilter,
        'date_from' => $df,
        'date_to' => $dt,
        'page' => $pageNum
    ], function($v){return $v !== '' && $v !== 0 && $v !== null;});
    return SITE_URL . '/payments.php' . ($qs ? '?' . http_build_query($qs) : '');
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $allSql = "SELECT p.*, b.booking_number, b.date_from as event_date, c.name as client_name, u.name as user_name
        FROM payments p LEFT JOIN bookings b ON p.booking_id = b.id
        LEFT JOIN clients c ON b.client_id = c.id
        LEFT JOIN users u ON p.created_by = u.id
        WHERE $whereStr ORDER BY p.payment_date DESC, p.id DESC";
    $allStmt = $conn->prepare($allSql); $allStmt->execute($params);
    $allRows = $allStmt->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="payments_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        t('th.datetime'), t('pay.booking_ref'), t('th.client'), t('pay.event_date'),
        t('th.amount'), t('th.method'), t('th.reference'), t('th.notes'),
        t('pay.recorded_by')
    ]);
    foreach ($allRows as $r) {
        fputcsv($out, [
            $r['payment_date'],
            $r['booking_number'] ?? '',
            $r['client_name'] ?? '',
            !empty($r['event_date']) ? formatDate($r['event_date']) : '',
            (float)$r['amount'],
            $r['payment_method'] ? t_payment_method($r['payment_method']) : '',
            $r['reference'] ?? '',
            $r['notes'] ?? '',
            $r['user_name'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

include SITE_PATH . '/includes/header.php';
echo flashMessages();
?>

<div class="row mb-3">
    <div class="col-md-6 col-xl-3 mb-3">
        <div class="kpi-card">
            <div class="kpi-icon kpi-icon-success"><i class="fas fa-wallet"></i></div>
            <div class="kpi-body">
                <div class="kpi-label"><?= te('pay.kpi_total') ?></div>
                <div class="kpi-value"><?= formatMoney($kpiAll) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3 mb-3">
        <div class="kpi-card">
            <div class="kpi-icon kpi-icon-primary"><i class="fas fa-calendar-day"></i></div>
            <div class="kpi-body">
                <div class="kpi-label"><?= te('pay.kpi_today') ?></div>
                <div class="kpi-value"><?= formatMoney($kpiToday) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3 mb-3">
        <div class="kpi-card">
            <div class="kpi-icon kpi-icon-indigo"><i class="fas fa-calendar-alt"></i></div>
            <div class="kpi-body">
                <div class="kpi-label"><?= te('pay.kpi_this_month') ?></div>
                <div class="kpi-value"><?= formatMoney($kpiMonth) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3 mb-3">
        <div class="kpi-card">
            <div class="kpi-icon kpi-icon-warning"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="kpi-body">
                <div class="kpi-label"><?= te('pay.kpi_pending_amt') ?></div>
                <div class="kpi-value text-warning"><?= formatMoney($kpiPending) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3 align-items-end">
    <div class="col-md-7">
        <h1 class="page-title mb-0"><?= te('pay.page_title') ?></h1>
        <p class="page-subtitle mb-0"><?= te('pay.subtitle') ?></p>
    </div>
    <div class="col-md-5 text-md-right mt-2 mt-md-0">
        <a class="btn btn-outline-dark mr-1" href="<?= paginateUrl($page) ?><?= strpos(paginateUrl($page),'?')===false?'?':'&' ?>export=csv"><i class="fas fa-file-csv mr-1"></i><?= te('audit.export_csv') ?></a>
        <?php if ($canEdit): ?>
        <button class="btn btn-primary" data-toggle="modal" data-target="#paymentModal"><i class="fas fa-plus"></i> <?= te('pay.add_title') ?></button>
        <?php endif; ?>
    </div>
</div>

<form method="GET" class="card filter-row mb-3">
<div class="row align-items-end">
    <div class="col-md-3 mb-2"><label class="small font-weight-semibold"><?= te('pay.filter_client') ?></label>
        <select name="client_id" class="form-control select2"><option value=""><?= te('common.all') ?></option>
            <?php foreach ($clientOpts as $co): ?><option value="<?= $co['id'] ?>" <?= $clientFilter === (int)$co['id'] ? 'selected' : '' ?>><?= e($co['name']) ?><?= !empty($co['phone']) ? ' ('.e($co['phone']).')' : '' ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-3 mb-2"><label class="small font-weight-semibold"><?= te('pay.filter_booking') ?></label>
        <select name="booking_id" class="form-control select2"><option value=""><?= te('common.all') ?></option>
            <?php foreach ($bookingOpts as $bo):
                $lbl = $bo['booking_number'];
                if (!empty($bo['client_name'])) $lbl .= ' · ' . $bo['client_name'];
                if (!empty($bo['event_date'])) $lbl .= ' · ' . formatDate($bo['event_date']);
            ?><option value="<?= $bo['id'] ?>" <?= $bookingFilter === (int)$bo['id'] ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('pay.filter_method') ?></label>
        <select name="payment_method" class="form-control select2"><option value=""><?= te('common.all') ?></option>
            <?php foreach ($methodOpts as $mo): $mLabel = t_payment_method($mo); ?>
                <option value="<?= e($mo) ?>" <?= $methodFilter === $mo ? 'selected' : '' ?>><?= e($mLabel) ?></option>
            <?php endforeach; ?>
        </select></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('field.from') ?></label><input name="date_from" class="form-control datepicker" value="<?= e($df) ?>" autocomplete="off"></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('field.to') ?></label><input name="date_to" class="form-control datepicker" value="<?= e($dt) ?>" autocomplete="off"></div>
    <div class="col-12 mb-0 pb-0">
        <div class="row">
            <div class="col-md-6 mb-2">
                <button class="btn btn-primary mr-1"><i class="fas fa-filter"></i> <?= te('common.filter') ?></button>
                <a href="<?= SITE_URL ?>/payments.php" class="btn btn-outline-secondary mr-1"><i class="fas fa-redo"></i><?= te('common.reset') ?></a>
            </div>
            <div class="col-md-6 mb-2 text-md-right">
                <?php
                $first = $totalAll === 0 ? 0 : $offset + 1;
                $last = min($offset + $perPage, $totalAll);
                ?>
                <span class="text-muted small">
                    <?= strtr(t('audit.showing'), ['{first}'=>$first, '{last}'=>$last, '{total}'=>$totalAll]) ?>
                </span>
                <span class="ml-2 btn btn-outline-dark font-weight-bold"><?= t('th.amount') ?>: <span class="text-success"><?= formatMoney($filteredTotal) ?></span></span>
            </div>
        </div>
    </div>
</div>
</form>

<div class="card"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-striped table-hover mb-0">
<thead class="thead-light">
<tr>
    <th><?= te('th.datetime') ?></th>
    <th><?= te('pay.booking_ref') ?></th>
    <th><?= te('th.client') ?></th>
    <th><?= te('pay.event_date') ?></th>
    <th class="text-right"><?= te('th.amount') ?></th>
    <th><?= te('th.method') ?></th>
    <th><?= te('th.reference') ?></th>
    <th><?= te('pay.recorded_by') ?></th>
    <th class="text-right"><?= te('th.actions') ?></th>
</tr>
</thead>
<tbody>
<?php if (empty($rows)): ?>
<tr><td colspan="9" class="text-center text-muted py-5"><i class="fas fa-box-open mr-2"></i><?= te('pay.no_payments') ?></td></tr>
<?php else: foreach ($rows as $r):
    $bkLabel = '';
    if (!empty($r['booking_number'])) {
        $bkLabel = $r['booking_number'];
        if (!empty($r['client_name'])) $bkLabel .= ' · ' . $r['client_name'];
        if (!empty($r['event_date'])) $bkLabel .= ' · ' . formatDate($r['event_date']);
    }
?>
<tr style="cursor:pointer" onclick="window.location='<?= SITE_URL ?>/booking_view.php?id=<?= (int)$r['booking_id'] ?>'">
    <td><?= formatDate($r['payment_date']) ?></td>
    <td class="font-weight-semibold">
        <?php if (!empty($r['booking_number'])): ?>
            <a href="<?= SITE_URL ?>/booking_view.php?id=<?= (int)$r['booking_id'] ?>" onclick="event.stopPropagation()"><?= e($r['booking_number']) ?></a>
        <?php else: ?>-<?php endif; ?>
    </td>
    <td><?= e($r['client_name'] ?? '-') ?></td>
    <td><?= !empty($r['event_date']) ? formatDate($r['event_date']) : '-' ?></td>
    <td class="text-right font-weight-semibold text-success"><?= formatMoney($r['amount']) ?></td>
    <td><?= !empty($r['payment_method']) ? e(t_payment_method($r['payment_method'])) : '-' ?></td>
    <td><?= e($r['reference'] ?? '-') ?></td>
    <td><small class="text-muted"><?= e($r['user_name'] ?? '-') ?></small></td>
    <td class="text-right" onclick="event.stopPropagation()">
        <?php if (!empty($r['notes'])): ?>
        <button class="btn btn-sm btn-outline-info mr-1" data-toggle="tooltip" title="<?= e($r['notes']) ?>"><i class="fas fa-sticky-note"></i></button>
        <?php endif; ?>
        <a href="<?= SITE_URL ?>/booking_view.php?id=<?= (int)$r['booking_id'] ?>" class="btn btn-sm btn-outline-primary mr-1"><i class="fas fa-eye"></i></a>
        <?php if ($canEdit): ?>
        <form method="POST" action="<?= SITE_URL ?>/payment_action.php" class="d-inline confirm-delete">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="ref" value="payments">
            <?php csrf_field(); ?>
            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm(<?= json_encode(t('pay.delete_confirm')) ?>)"><i class="fas fa-trash"></i></button>
        </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
<?php if (!empty($rows)): ?>
<tfoot><tr class="bg-light font-weight-bold"><td colspan="4" class="text-right"><?= t('th.amount') ?> (<?= t('common.filter') ?>)</td><td class="text-right text-success h5 mb-0"><?= formatMoney($filteredTotal) ?></td><td colspan="4"></td></tr></tfoot>
<?php endif; ?>
</table>
</div></div>

<?php if ($totalPages > 1): ?>
<div class="card-footer bg-white border-top d-flex justify-content-between align-items-center">
    <small class="text-muted">
        <?= strtr(t('audit.showing'), ['{first}'=>$first, '{last}'=>$last, '{total}'=>$totalAll]) ?>
    </small>
    <nav>
        <ul class="pagination pagination-sm mb-0">
            <?php if ($page > 1): ?>
                <li class="page-item"><a class="page-link" href="<?= paginateUrl($page - 1) ?>"><i class="fas fa-chevron-left"></i></a></li>
            <?php endif;
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            if ($startPage > 1): ?>
                <li class="page-item"><a class="page-link" href="<?= paginateUrl(1) ?>">1</a></li>
                <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif;
            endif;
            for ($p = $startPage; $p <= $endPage; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= paginateUrl($p) ?>"><?= $p ?></a></li>
            <?php endfor;
            if ($endPage < $totalPages):
                if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                <li class="page-item"><a class="page-link" href="<?= paginateUrl($totalPages) ?>"><?= $totalPages ?></a></li>
            <?php endif;
            if ($page < $totalPages): ?>
                <li class="page-item"><a class="page-link" href="<?= paginateUrl($page + 1) ?>"><i class="fas fa-chevron-right"></i></a></li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
<?php endif; ?>
</div>

<?php if ($canEdit): ?>
<div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <form class="modal-content" method="POST" action="<?= SITE_URL ?>/payment_action.php" id="newPaymentForm">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="ref" value="payments">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle mr-2 text-success"></i><?= t('pay.add_title') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?= t('common.close') ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group col-md-12">
                        <label><?= te('pay.booking_ref') ?> <span class="text-danger">*</span></label>
                        <select name="bk" id="pm_booking_id" class="form-control select2" required style="width:100%" onchange="updateBookingBalance()">
                            <option value=""><?= te('pay.select_booking_ph') ?></option>
                            <?php foreach ($bookingOpts as $bo):
                                $pend = max(0, (float)$bo['quoted_amount'] - (float)$bo['collected']);
                                $lbl = $bo['booking_number'];
                                if (!empty($bo['client_name'])) $lbl .= ' · ' . $bo['client_name'];
                                if (!empty($bo['event_date'])) $lbl .= ' · ' . formatDate($bo['event_date']);
                            ?><option value="<?= (int)$bo['id'] ?>"
                                data-quoted="<?= (float)$bo['quoted_amount'] ?>"
                                data-collected="<?= (float)$bo['collected'] ?>"
                                data-pending="<?= $pend ?>"><?= e($lbl) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="alert alert-info py-2 small mb-3" id="pmBalanceBox" style="display:none">
                    <div class="row">
                        <div class="col-sm-4"><?= t('pay.quoted_label') ?> <strong id="pmQuoted">-</strong></div>
                        <div class="col-sm-4"><?= t('pay.collected_label') ?> <strong id="pmCollected">-</strong></div>
                        <div class="col-sm-4"><?= t('pay.pending_label') ?> <strong class="text-danger" id="pmPending">-</strong></div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label><?= t('pay.date_label') ?> <span class="text-danger">*</span></label>
                        <input type="text" required name="payment_date" class="form-control datepicker" value="<?= date('d/m/Y') ?>" autocomplete="off">
                    </div>
                    <div class="form-group col-md-6">
                        <label><?= t('pay.amount_label') ?> <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" required name="amount" id="pm_amount" class="form-control" min="0.01">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label><?= t('pay.method_label') ?></label>
                        <select name="payment_method" class="form-control select2" style="width:100%">
                            <option value=""><?= t('common.select_option') ?></option>
                            <?php foreach ($methodOpts as $mo): $mLabel = t_payment_method($mo); ?>
                                <option value="<?= e($mo) ?>"><?= e($mLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label><?= t('pay.reference_label') ?></label>
                        <input type="text" name="reference" class="form-control" placeholder="<?= te('pay.reference_ph') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label><?= t('pay.notes_label') ?></label>
                    <textarea name="notes" rows="2" class="form-control" placeholder="<?= te('pay.notes_ph') ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= t('common.cancel') ?></button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> <?= t('bk.record_payment') ?></button>
            </div>
        </form>
    </div>
</div>
<script>
function updateBookingBalance() {
    var sel = document.getElementById('pm_booking_id');
    var opt = sel.options[sel.selectedIndex];
    var box = document.getElementById('pmBalanceBox');
    if (opt && opt.value) {
        var q = parseFloat(opt.dataset.quoted) || 0;
        var c = parseFloat(opt.dataset.collected) || 0;
        var p = parseFloat(opt.dataset.pending) || 0;
        var sym = <?= json_encode(CURRENCY_SYMBOL) ?>;
        document.getElementById('pmQuoted').textContent = sym + ' ' + q.toFixed(2);
        document.getElementById('pmCollected').textContent = sym + ' ' + c.toFixed(2);
        document.getElementById('pmPending').textContent = sym + ' ' + p.toFixed(2);
        var amtField = document.getElementById('pm_amount');
        if (parseFloat(amtField.value) <= 0 && p > 0) {
            amtField.value = p.toFixed(2);
        }
        box.style.display = '';
    } else {
        box.style.display = 'none';
    }
}
$('#paymentModal').on('shown.bs.modal', function() {
    setTimeout(function(){ updateBookingBalance(); }, 100);
});
$('#newPaymentForm').on('submit', function(e) {
    var sel = document.getElementById('pm_booking_id');
    if (!sel.value) { e.preventDefault(); alert(<?= json_encode(t('pay.select_booking_ph')) ?>); return false; }
    return true;
});
</script>
<?php endif;

include SITE_PATH . '/includes/footer.php';
