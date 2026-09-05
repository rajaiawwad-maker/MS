<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('view_expenses');
$page_title = t('title.expenses_list');
$active_nav = 'expenses';

$typeFilter = (int)($_GET['expense_type_id'] ?? 0);
$df = $_GET['date_from'] ?? '';
$dt = $_GET['date_to'] ?? '';
$where = ['1=1']; $params = [];
if ($typeFilter > 0) { $where[] = "e.expense_type_id = ?"; $params[] = $typeFilter; }
if ($df !== '') { $d = DateTime::createFromFormat('d/m/Y', $df); if ($d) { $where[] = "e.date >= ?"; $params[] = $d->format('Y-m-d'); } }
if ($dt !== '') { $d = DateTime::createFromFormat('d/m/Y', $dt); if ($d) { $where[] = "e.date <= ?"; $params[] = $d->format('Y-m-d'); } }

$stmt = $conn->prepare("SELECT e.*, et.name as type_name, u.name as created_by_name, b.booking_number, b.date_from as event_date, c.name as client_name
    FROM expenses e INNER JOIN expense_types et ON e.expense_type_id = et.id
    LEFT JOIN users u ON e.created_by = u.id LEFT JOIN bookings b ON e.booking_id = b.id
    LEFT JOIN clients c ON b.client_id = c.id
    WHERE ".implode(' AND ', $where)." ORDER BY e.date DESC, e.id DESC LIMIT 500");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$total = array_sum(array_column($rows, 'amount'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('manage_expenses')) {
    $action = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    $typeId = (int)($_POST['expense_type_id'] ?? 0);
    $date = DateTime::createFromFormat('d/m/Y', $_POST['date'] ?? date('d/m/Y'));
    $amount = (float)($_POST['amount'] ?? 0);
    $method = trim($_POST['payment_method'] ?? '');
    $ref = trim($_POST['reference'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $bkId = (int)($_POST['booking_id'] ?? 0) ?: null;
    if ($typeId <= 0 || $amount <= 0) { setFlash('error', t('exp.type_amount_required')); redirect(SITE_URL.'/expenses.php'); }
    if (!$date) $date = new DateTime();
    if ($action === 'create') {
        $conn->prepare("INSERT INTO expenses (expense_type_id,date,amount,description,payment_method,reference,booking_id,created_by) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$typeId, $date->format('Y-m-d'), $amount, $desc, $method, $ref, $bkId, $_SESSION['user_id']]);
        auditLog('create', 'Expense', $conn->lastInsertId());
        setFlash('success', t('exp.recorded'));
    } elseif ($action === 'update' && $id > 0) {
        $conn->prepare("UPDATE expenses SET expense_type_id=?,date=?,amount=?,description=?,payment_method=?,reference=?,booking_id=? WHERE id=?")
            ->execute([$typeId, $date->format('Y-m-d'), $amount, $desc, $method, $ref, $bkId, $id]);
        auditLog('update', 'Expense', $id);
        setFlash('success', t('common.updated'));
    }
    redirect(SITE_URL.'/expenses.php');
}
if (isset($_GET['delete']) && hasPermission('manage_expenses')) {
    $conn->prepare("DELETE FROM expenses WHERE id = ?")->execute([(int)$_GET['delete']]);
    auditLog('delete', 'Expense', (int)$_GET['delete']);
    setFlash('success', t('common.deleted')); redirect(SITE_URL.'/expenses.php');
}

$types = $conn->query("SELECT id, name, fixed_value FROM expense_types WHERE active=1 ORDER BY name")->fetchAll();
$bookings = $conn->query("SELECT b.id, b.booking_number, b.date_from as event_date, c.name as client_name
    FROM bookings b LEFT JOIN clients c ON b.client_id = c.id
    ORDER BY b.date_from DESC LIMIT 200")->fetchAll();

include SITE_PATH . '/includes/header.php';
echo flashMessages();
?>
<div class="row mb-3 align-items-end">
    <div class="col-md-6"><h1 class="page-title"><?= te('title.expenses_list') ?></h1><p class="page-subtitle"><?= te('title.expenses_list_sub') ?></p></div>
    <div class="col-md-6 text-md-right"><?php if (hasPermission('manage_expenses')): ?>
        <button class="btn btn-primary" data-toggle="modal" data-target="#formModal" data-mode="create"><i class="fas fa-plus"></i> <?= te('exp.record_btn') ?></button>
    <?php endif; ?></div>
</div>

<form method="GET" class="card filter-row mb-3">
<div class="row align-items-end">
    <div class="col-md-3 mb-2"><label class="small font-weight-semibold"><?= te('field.expense_type') ?></label>
        <select name="expense_type_id" class="form-control select2"><option value=""><?= te('exp.all_types') ?></option>
            <?php foreach ($types as $t): ?><option value="<?= $t['id'] ?>" <?= $typeFilter === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('field.from') ?></label><input name="date_from" class="form-control datepicker" value="<?= e($df) ?>" autocomplete="off"></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('field.to') ?></label><input name="date_to" class="form-control datepicker" value="<?= e($dt) ?>" autocomplete="off"></div>
    <div class="col-md-2 mb-2"><button class="btn btn-primary btn-block"><i class="fas fa-filter"></i> <?= te('common.filter') ?></button></div>
    <div class="col-md-3 mb-2 text-md-right">
        <a href="<?= SITE_URL ?>/expenses.php" class="btn btn-outline-secondary mr-1"><?= te('common.reset') ?></a>
        <div class="btn btn-outline-dark font-weight-bold"><?= te('exp.total_label') ?> <?= formatMoney($total) ?></div>
    </div>
</div>
</form>

<div class="card"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-striped mb-0"><thead><tr><th><?= te('th.date') ?></th><th><?= te('th.type') ?></th><th><?= te('th.description') ?></th><th><?= te('th.booking') ?></th><th><?= te('th.method') ?></th><th class="text-right"><?= te('th.amount') ?></th><th><?= te('th.by') ?></th><th></th></tr></thead><tbody>
<?php if (empty($rows)): ?><tr><td colspan="8" class="text-center text-muted py-5"><?= te('exp.no_expenses') ?></td></tr>
<?php else: foreach ($rows as $r): ?>
<tr><td><?= formatDate($r['date']) ?></td>
<td class="font-weight-semibold"><?= e($r['type_name']) ?></td>
<td><?= e($r['description'] ?: '-') ?></td>
<td><?php
    if (!empty($r['booking_number'])) {
        $label = e($r['booking_number']);
        if (!empty($r['client_name'])) $label .= ' · ' . e($r['client_name']);
        if (!empty($r['event_date'])) $label .= ' · ' . e(formatDate($r['event_date']));
        echo '<a href="'.SITE_URL.'/booking_view.php?id='.$r['booking_id'].'">'.$label.'</a>';
    } else {
        echo '-';
    }
?></td>
<td><?= $r['payment_method'] ? te(t_payment_method($r['payment_method'])) : '-' ?></td>
<td class="text-right font-weight-semibold text-danger"><?= formatMoney($r['amount']) ?></td>
<td><small class="text-muted"><?= e($r['created_by_name'] ?? '') ?></small></td>
<td class="text-right">
<?php if (hasPermission('manage_expenses')): ?>
<button class="btn btn-sm btn-outline-secondary" onclick="editRow(<?= $r['id'] ?>)"><i class="fas fa-edit"></i></button>
<a href="<?= SITE_URL ?>/expenses.php?delete=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger confirm-delete"><i class="fas fa-trash"></i></a>
<?php endif; ?>
</td></tr>
<?php endforeach; endif; ?>
</tbody>
<tfoot><tr class="bg-light font-weight-bold"><td colspan="5" class="text-right"><?= te('exp.total_expenses') ?></td><td class="text-right text-danger h5 mb-0"><?= formatMoney($total) ?></td><td colspan="2"></td></tr></tfoot>
</table></div></div></div>

<?php if (hasPermission('manage_expenses')): ?>
<div class="modal fade" id="formModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><form class="modal-content" method="POST">
<div class="modal-header"><h5 class="modal-title" id="formModalTitle"><?= te('exp.record_expense_title') ?></h5><button class="close" data-dismiss="modal">&times;</button></div>
<div class="modal-body">
<input type="hidden" name="action" id="formAction" value="create"><input type="hidden" name="id" id="formId" value="">
<div class="form-row">
    <div class="form-group col-md-6"><label><?= te('field.expense_type') ?> *</label>
        <select name="expense_type_id" id="fld_expense_type_id" class="form-control select2" required onchange="var o=this.options[this.selectedIndex];var fv=o.dataset.fv||0;if(fv>0)document.getElementById('fld_amount').value=fv;">
            <option value=""><?= te('common.select_option') ?></option>
            <?php foreach ($types as $t): ?><option value="<?= $t['id'] ?>" data-fv="<?= e($t['fixed_value']) ?>"><?= e($t['name']) ?><?= $t['fixed_value'] > 0 ? ' ('.formatMoney($t['fixed_value']).')' : '' ?></option><?php endforeach; ?>
        </select></div>
    <div class="form-group col-md-2"><label><?= te('exp.date') ?> *</label><input required name="date" id="fld_date" class="form-control datepicker" value="<?= date('d/m/Y') ?>" autocomplete="off"></div>
    <div class="form-group col-md-4"><label><?= te('exp.amount') ?> *</label><input required type="number" step="0.01" name="amount" id="fld_amount" class="form-control" min="0.01"></div>
    <div class="form-group col-md-4"><label><?= te('exp.method') ?></label>
        <select name="payment_method" id="fld_payment_method" class="form-control">
            <option value=""><?= te('common.select_option') ?></option>
            <option value="Cash"><?= te('pm.cash') ?></option>
            <option value="Transfer"><?= te('pm.transfer') ?></option>
            <option value="CliQ"><?= te('pm.cliq') ?></option>
        </select></div>
    <div class="form-group col-md-4"><label><?= te('th.reference') ?></label><input name="reference" id="fld_reference" class="form-control" placeholder="<?= te('pay.reference_ph') ?>"></div>
    <div class="form-group col-md-4"><label><?= te('exp.related_booking') ?></label>
        <select name="booking_id" id="fld_booking_id" class="form-control select2">
            <option value=""><?= te('common.none') ?></option>
            <?php foreach ($bookings as $b):
                $label = e($b['booking_number']);
                if (!empty($b['client_name'])) $label .= ' · ' . e($b['client_name']);
                if (!empty($b['event_date'])) $label .= ' · ' . e(formatDate($b['event_date']));
                ?><option value="<?= $b['id'] ?>"><?= $label ?></option><?php endforeach; ?>
        </select></div>
    <div class="form-group col-md-12"><label><?= te('exp.description') ?> / <?= te('th.notes') ?></label><textarea name="description" id="fld_description" rows="2" class="form-control"></textarea></div>
</div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= te('common.cancel') ?></button>
<button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> <?= te('common.save') ?></button></div>
</form></div></div>
<script>
var rows = <?= json_encode($rows) ?>;
function ymdToDmy(s) {
    if (!s) return '';
    var m = String(s).match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
    return m ? m[3].padStart(2,'0') + '/' + m[2].padStart(2,'0') + '/' + m[1] : s;
}
function relatedTargetMode(e) {
    if (!e || !e.relatedTarget) return null;
    try { return $(e.relatedTarget).data('mode') || null; } catch (err) { return null; }
}
function editRow(id) {
    var r = rows.find(x => x.id == id); if (!r) return;
    $('#formModalTitle').text(<?= json_encode(t('exp.edit_expense')) ?>); $('#formAction').val('update'); $('#formId').val(id);
    $('#fld_expense_type_id').val(r.expense_type_id).trigger('change');
    $('#fld_date').val(ymdToDmy(r.date));
    $('#fld_amount').val(r.amount); $('#fld_payment_method').val(r.payment_method || '');
    $('#fld_reference').val(r.reference || ''); $('#fld_booking_id').val(r.booking_id || '').trigger('change');
    $('#fld_description').val(r.description || ''); $('#formModal').modal('show');
}
$('#formModal').on('show.bs.modal', function(e) {
    if (relatedTargetMode(e) === 'create' || !$('#formId').val()) {
        $('#formModalTitle').text(<?= json_encode(t('exp.record_expense_title')) ?>); $('#formAction').val('create'); $('#formId').val('');
        $('#formModal form')[0].reset();
        $('#fld_date').val('<?= date('d/m/Y') ?>');
        $('#fld_expense_type_id').val('').trigger('change');
        $('#fld_booking_id').val('').trigger('change');
    }
});
</script>
<?php endif;
include SITE_PATH . '/includes/footer.php';
?>
