<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('view_bookings');

$page_title = t('title.bookings_list');
$active_nav = 'bookings';

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$paymentStatus = $_GET['payment_status'] ?? '';
$clientId = $_GET['client_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$where = ['1=1']; $params = [];
if ($search !== '') {
    $where[] = "(b.booking_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR b.location LIKE ?)";
    $s = "%$search%";
    array_push($params, $s, $s, $s, $s);
}
if ($status !== '') { $where[] = "b.status = ?"; $params[] = $status; }
if ($paymentStatus !== '') { $where[] = "b.payment_status = ?"; $params[] = $paymentStatus; }
if ($clientId !== '') { $where[] = "b.client_id = ?"; $params[] = $clientId; }
if ($dateFrom !== '') { $df = DateTime::createFromFormat('d/m/Y', $dateFrom); if ($df) { $where[] = "b.date_from >= ?"; $params[] = $df->format('Y-m-d'); } }
if ($dateTo !== '') { $dt = DateTime::createFromFormat('d/m/Y', $dateTo); if ($dt) { $where[] = "b.date_from <= ?"; $params[] = $dt->format('Y-m-d'); } }

$sql = "SELECT b.*, c.name as client_name, c.phone as client_phone,
    (SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = b.id) as collected
    FROM bookings b INNER JOIN clients c ON b.client_id = c.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY b.created_at DESC LIMIT 500";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

include SITE_PATH . '/includes/header.php';
echo flashMessages();
?>
<div class="row mb-3 align-items-end">
    <div class="col-md-6">
        <h1 class="page-title"><?= t('title.bookings_list') ?></h1>
        <p class="page-subtitle"><?= t('title.bookings_list_sub') ?></p>
    </div>
    <div class="col-md-6 text-md-right">
        <?php if (hasPermission('create_bookings')): ?>
            <a href="<?= SITE_URL ?>/booking_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> <?= t('btn.new_booking') ?></a>
        <?php endif; ?>
    </div>
</div>

<form method="GET" class="card filter-row mb-3">
    <div class="row align-items-end">
        <div class="col-md-2 mb-2"><label class="font-weight-semibold small"><?= t('common.search') ?></label><input name="q" class="form-control" value="<?= e($search) ?>" placeholder="<?= te('bk.search_ph') ?>"></div>
        <div class="col-md-2 mb-2"><label class="font-weight-semibold small"><?= t('bk.status') ?></label>
            <select name="status" class="form-control"><option value=""><?= t('common.all') ?></option>
            <?php foreach (['Draft','Quotation','Confirmed','Change Requested','Event Completed','Closed','Canceled'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= t_booking_status($s) ?></option>
            <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 mb-2"><label class="font-weight-semibold small"><?= t('th.payment') ?></label>
            <select name="payment_status" class="form-control"><option value=""><?= t('common.all') ?></option>
            <?php foreach (['Not Collected','Partially Collected','Fully Collected','Canceled'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $paymentStatus === $s ? 'selected' : '' ?>><?= t_payment_status($s) ?></option>
            <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 mb-2"><label class="font-weight-semibold small"><?= t('field.client') ?></label>
            <select name="client_id" class="form-control select2"><option value=""><?= t('th.all_clients') ?></option>
            <?php $cs = $conn->query("SELECT id, name FROM clients WHERE active=1 ORDER BY name")->fetchAll();
            foreach ($cs as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $clientId == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1 mb-2"><label class="font-weight-semibold small"><?= t('field.from') ?></label><input name="date_from" class="form-control datepicker" value="<?= e($dateFrom) ?>" autocomplete="off"></div>
        <div class="col-md-1 mb-2"><label class="font-weight-semibold small"><?= t('field.to') ?></label><input name="date_to" class="form-control datepicker" value="<?= e($dateTo) ?>" autocomplete="off"></div>
        <div class="col-md-2 mb-2"><button class="btn btn-primary btn-block"><i class="fas fa-filter"></i> <?= te('btn.retrieve') ?></button></div>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0" id="bookingsTable">
                <thead><tr>
                    <th><?= t('th.hash') ?></th><th><?= t('th.client') ?></th><th><?= t('th.dates') ?></th><th><?= t('th.location') ?></th>
                    <th class="text-right"><?= t('th.quoted') ?></th><th class="text-right"><?= t('th.collected') ?></th><th class="text-right"><?= t('th.pending') ?></th>
                    <th><?= t('th.status') ?></th><th><?= t('th.payment') ?></th><th><?= t('th.actions') ?></th>
                </tr></thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-5"><?= t('bk.no_bookings') ?></td></tr>
                    <?php else: foreach ($bookings as $b):
                        $pend = max(0, (float)$b['quoted_amount'] - (float)$b['collected']);
                        $sClass = strtolower(str_replace([' ','-','/'],['_','_',''], $b['status']));
                        $psClass = strtolower(str_replace([' ','-','/'],['_','_',''], $b['payment_status']));
                    ?>
                    <tr>
                        <td class="font-weight-bold"><a href="<?= SITE_URL ?>/booking_view.php?id=<?= $b['id'] ?>"><?= e($b['booking_number']) ?></a></td>
                        <td><?= e($b['client_name']) ?><br><small class="text-muted"><?= e($b['client_phone']) ?></small></td>
                        <td><?= formatDate($b['date_from']) ?><?= $b['date_from'] != $b['date_to'] ? ' → '.formatDate($b['date_to']) : '' ?></td>
                        <td><?= e($b['location']) ?></td>
                        <td class="text-right font-weight-semibold"><?= formatMoney($b['quoted_amount']) ?></td>
                        <td class="text-right text-success"><?= formatMoney($b['collected']) ?></td>
                        <td class="text-right <?= $pend > 0 ? 'text-danger font-weight-semibold' : '' ?>"><?= formatMoney($pend) ?></td>
                        <td><span class="status-badge status-<?= $sClass ?>"><?= t_booking_status($b['status']) ?></span></td>
                        <td><span class="status-badge status-<?= $psClass ?>"><?= t_payment_status($b['payment_status']) ?></span></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="<?= SITE_URL ?>/booking_view.php?id=<?= $b['id'] ?>" class="btn btn-outline-secondary" title="<?= te('common.view') ?>"><i class="fas fa-eye"></i></a>
                                <?php if (hasPermission('edit_bookings')): ?>
                                    <a href="<?= SITE_URL ?>/booking_form.php?id=<?= $b['id'] ?>" class="btn btn-outline-primary" title="<?= te('common.edit') ?>"><i class="fas fa-edit"></i></a>
                                <?php endif; ?>
                                <?php if (hasPermission('cancel_bookings') && $b['status'] !== 'Canceled'): ?>
                                    <form method="POST" action="<?= SITE_URL ?>/booking_action.php" class="d-inline confirm-action" data-confirm="<?= te('bk.cancel_confirm') ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <?php csrf_field(); ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= te('common.cancel') ?>"><i class="fas fa-times"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include SITE_PATH . '/includes/footer.php'; ?>
