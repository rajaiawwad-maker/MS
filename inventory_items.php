<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('manage_inventory');
$page_title = t('title.inventory_items');
$active_nav = 'inventory';

$statuses = ['Available','Booked','Out for Event','Maintenance','Damaged','Lost','Retired'];

function t_ii_status($s) {
    $map = [
        'Available'=>'inv.ii_status_available',
        'Booked'=>'inv.ii_status_booked',
        'Out for Event'=>'inv.ii_status_out_event',
        'Maintenance'=>'inv.ii_status_maintenance',
        'Damaged'=>'inv.ii_status_damaged',
        'Lost'=>'inv.ii_status_lost',
        'Retired'=>'inv.ii_status_retired'
    ];
    return $map[$s] ?? $s;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $action = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    $it = (int)($_POST['item_type_id'] ?? 0); $sn = trim($_POST['serial_number'] ?? '');
    $ac = trim($_POST['asset_code'] ?? ''); $pd = trim($_POST['purchase_date'] ?? '');
    $st = $_POST['status'] ?? 'Available'; $loc = trim($_POST['location'] ?? ''); $notes = trim($_POST['notes'] ?? '');
    if ($it <= 0) { setFlash('error', t('inv.item_type_required')); redirect(SITE_URL.'/inventory_items.php'); }
    $pdSql = $pd ? DateTime::createFromFormat('d/m/Y', $pd)?->format('Y-m-d') : null;
    if ($action === 'create') {
        $sql = "INSERT INTO inventory_items (item_type_id,serial_number,asset_code,purchase_date,status,location,notes) VALUES (?,?,?,?,?,?,?)";
        $conn->prepare($sql)->execute([$it,$sn,$ac,$pdSql,$st,$loc,$notes]);
        auditLog('create', 'InventoryItem', $conn->lastInsertId());
        setFlash('success', t('common.created'));
    } elseif ($action === 'update' && $id) {
        $sql = "UPDATE inventory_items SET item_type_id=?,serial_number=?,asset_code=?,purchase_date=?,status=?,location=?,notes=?, updated_at=NOW() WHERE id=?";
        $conn->prepare($sql)->execute([$it,$sn,$ac,$pdSql,$st,$loc,$notes,$id]);
        auditLog('update', 'InventoryItem', $id);
        setFlash('success', t('common.updated'));
    }
    redirect(SITE_URL.'/inventory_items.php');
}
if (isset($_GET['delete'])) {
    $conn->prepare("UPDATE inventory_items SET status='Retired', updated_at=NOW() WHERE id=?")->execute([(int)$_GET['delete']]);
    auditLog('retire', 'InventoryItem', (int)$_GET['delete']);
    setFlash('success', t('inv.ii_retired')); redirect(SITE_URL.'/inventory_items.php');
}

$filterType = (int)($_GET['item_type_id'] ?? 0);
$filterStatus = $_GET['status'] ?? '';
$where = ['1=1']; $params = [];
if ($filterType > 0) { $where[] = "i.item_type_id = ?"; $params[] = $filterType; }
if ($filterStatus !== '') { $where[] = "i.status = ?"; $params[] = $filterStatus; }
$stmt = $conn->prepare("SELECT i.*, it.name as item_name, c.name as category_name FROM inventory_items i
    INNER JOIN item_types it ON i.item_type_id = it.id INNER JOIN categories c ON it.category_id = c.id
    WHERE ".implode(' AND ', $where)." ORDER BY c.name, it.name, i.asset_code");
$stmt->execute($params);
$rows = $stmt->fetchAll();
$itemTypes = $conn->query("SELECT it.id, it.name, c.name as category_name FROM item_types it INNER JOIN categories c ON it.category_id = c.id WHERE it.active=1 ORDER BY c.name, it.name")->fetchAll();

include SITE_PATH . '/includes/header.php';
echo flashMessages();
?>
<div class="row mb-3 align-items-end">
    <div class="col-md-6"><h1 class="page-title"><?= te('title.inventory_items') ?></h1><p class="page-subtitle"><?= te('title.inventory_items_sub') ?></p></div>
    <div class="col-md-6 text-md-right"><button class="btn btn-primary" data-toggle="modal" data-target="#formModal" data-mode="create"><i class="fas fa-plus"></i> <?= te('inv.ii_new_asset') ?></button></div>
</div>
<form method="GET" class="card filter-row mb-3">
<div class="row align-items-end">
    <div class="col-md-5 mb-2"><label class="small font-weight-semibold"><?= te('inv.ii_item_type') ?></label>
        <select name="item_type_id" class="form-control select2"><option value=""><?= te('common.all') ?></option>
        <?php foreach ($itemTypes as $t): ?><option value="<?= $t['id'] ?>" <?= $filterType === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['category_name'].' / '.$t['name']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-3 mb-2"><label class="small font-weight-semibold"><?= te('inv.ii_status') ?></label>
        <select name="status" class="form-control"><option value=""><?= te('common.all') ?></option>
            <?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= te(t_ii_status($s)) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-2 mb-2"><button class="btn btn-primary btn-block"><i class="fas fa-filter"></i> <?= te('common.filter') ?></button></div>
    <div class="col-md-2 mb-2"><a href="<?= SITE_URL ?>/inventory_items.php" class="btn btn-outline-secondary btn-block"><?= te('common.reset') ?></a></div>
</div>
</form>
<div class="card"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-striped mb-0"><thead><tr><th><?= te('th.asset_code') ?></th><th><?= te('th.category_item') ?></th><th><?= te('inv.ii_serial') ?></th><th><?= te('inv.ii_purchase_date') ?></th><th><?= te('inv.ii_location') ?></th><th><?= te('th.status') ?></th><th></th></tr></thead><tbody>
<?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted py-5"><?= te('common.no_records') ?></td></tr>
<?php else: foreach ($rows as $r):
    $sClass = strtolower(str_replace([' ','-'], '_', $r['status']));
?>
<tr><td class="font-weight-bold"><?= e($r['asset_code'] ?: '-') ?></td>
<td><?= e($r['category_name']) ?> / <strong><?= e($r['item_name']) ?></strong></td>
<td><?= e($r['serial_number'] ?: '-') ?></td>
<td><?= formatDate($r['purchase_date']) ?></td>
<td><?= e($r['location'] ?: '-') ?></td>
<td><span class="status-badge status-<?= $sClass ?>"><?= te(t_ii_status($r['status'])) ?></span></td>
<td class="text-right">
<button class="btn btn-sm btn-outline-secondary" onclick="editRow(<?= $r['id'] ?>)"><i class="fas fa-edit"></i></button>
<a href="<?= SITE_URL ?>/inventory_items.php?delete=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger confirm-action" data-confirm="<?= t('inv.ii_retire_confirm') ?>"><i class="fas fa-times"></i></a>
</td></tr>
<?php endforeach; endif; ?>
</tbody></table></div></div></div>

<div class="modal fade" id="formModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><form class="modal-content" method="POST">
<?php csrf_field(); ?>
<div class="modal-header"><h5 class="modal-title" id="formModalTitle"><?= te('inv.ii_add') ?></h5><button class="close" data-dismiss="modal">&times;</button></div>
<div class="modal-body">
<input type="hidden" name="action" id="formAction" value="create"><input type="hidden" name="id" id="formId" value="">
<div class="form-row">
    <div class="form-group col-md-8"><label><?= te('inv.ii_item_type') ?> *</label>
        <select name="item_type_id" id="fld_item_type_id" class="form-control select2" required>
            <option value=""><?= te('common.select_option') ?></option>
            <?php foreach ($itemTypes as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['category_name'].' / '.$t['name']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="form-group col-md-4"><label><?= te('inv.ii_status') ?></label>
        <select name="status" id="fld_status" class="form-control">
            <?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>"><?= te(t_ii_status($s)) ?></option><?php endforeach; ?>
        </select></div>
    <div class="form-group col-md-4"><label><?= te('inv.ii_asset_code') ?></label><input name="asset_code" id="fld_asset_code" class="form-control"></div>
    <div class="form-group col-md-4"><label><?= te('inv.ii_serial') ?></label><input name="serial_number" id="fld_serial_number" class="form-control"></div>
    <div class="form-group col-md-4"><label><?= te('inv.ii_purchase_date') ?></label><input type="text" name="purchase_date" id="fld_purchase_date" class="form-control datepicker" autocomplete="off"></div>
    <div class="form-group col-md-6"><label><?= te('inv.ii_location') ?></label><input name="location" id="fld_location" class="form-control"></div>
    <div class="form-group col-md-12"><label><?= te('inv.ii_notes') ?></label><textarea name="notes" id="fld_notes" rows="2" class="form-control"></textarea></div>
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
    $('#formModalTitle').text(<?= json_encode(t('inv.ii_edit')) ?>); $('#formAction').val('update'); $('#formId').val(id);
    $('#fld_item_type_id').val(r.item_type_id).trigger('change');
    $('#fld_status').val(r.status);
    $('#fld_asset_code').val(r.asset_code || '');
    $('#fld_serial_number').val(r.serial_number || '');
    $('#fld_purchase_date').val(ymdToDmy(r.purchase_date));
    $('#fld_location').val(r.location || '');
    $('#fld_notes').val(r.notes || '');
    $('#formModal').modal('show');
}
$('#formModal').on('show.bs.modal', function(e) {
    if (relatedTargetMode(e) === 'create' || !$('#formId').val()) {
        $('#formModalTitle').text(<?= json_encode(t('inv.ii_add')) ?>); $('#formAction').val('create'); $('#formId').val('');
        $('#formModal form')[0].reset(); $('#fld_item_type_id').val('').trigger('change'); $('#fld_status').val('Available');
    }
});
</script>
<?php include SITE_PATH . '/includes/footer.php'; ?>
