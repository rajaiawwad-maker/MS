<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('manage_setup');
$page_title = t('title.item_types');
$active_nav = 'inventory';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $action = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    $cat = (int)($_POST['category_id'] ?? 0); $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? ''); $rv = (float)($_POST['default_rental_value'] ?? 0);
    $qty = (int)($_POST['quantity'] ?? 0); $active = isset($_POST['active']) ? 1 : 0;
    if ($cat <= 0 || $name === '') { setFlash('error', t('inv.cat_name_required')); redirect(SITE_URL.'/item_types.php'); }
    if ($action === 'create') {
        $sql = "INSERT INTO item_types (category_id,name,description,default_rental_value,quantity,active) VALUES (?,?,?,?,?,?)";
        $conn->prepare($sql)->execute([$cat,$name,$desc,$rv,$qty,$active]);
        auditLog('create', 'ItemType', $conn->lastInsertId());
        setFlash('success', t('common.created'));
    } elseif ($action === 'update' && $id) {
        $sql = "UPDATE item_types SET category_id=?,name=?,description=?,default_rental_value=?,quantity=?,active=?, updated_at=NOW() WHERE id=?";
        $conn->prepare($sql)->execute([$cat,$name,$desc,$rv,$qty,$active,$id]);
        auditLog('update', 'ItemType', $id);
        setFlash('success', t('common.updated'));
    }
    redirect(SITE_URL.'/item_types.php');
}
if (isset($_GET['delete'])) {
    $conn->prepare("UPDATE item_types SET active=0, updated_at=NOW() WHERE id=?")->execute([(int)$_GET['delete']]);
    auditLog('deactivate', 'ItemType', (int)$_GET['delete']);
    setFlash('success', t('common.deactivated')); redirect(SITE_URL.'/item_types.php');
}

$catFilter = (int)($_GET['category_id'] ?? 0);
$where = ['1=1']; $params = [];
if ($catFilter > 0) { $where[] = "it.category_id = ?"; $params[] = $catFilter; }
$stmt = $conn->prepare("SELECT it.*, c.name as category_name FROM item_types it INNER JOIN categories c ON it.category_id = c.id WHERE ".implode(' AND ', $where)." ORDER BY c.name, it.name");
$stmt->execute($params);
$rows = $stmt->fetchAll();
$cats = $conn->query("SELECT id, name FROM categories WHERE active=1 ORDER BY name")->fetchAll();

include SITE_PATH . '/includes/header.php';
echo flashMessages();
$today = date('Y-m-d');
?>
<div class="row mb-3 align-items-end">
    <div class="col-md-6"><h1 class="page-title"><?= te('title.item_types') ?></h1><p class="page-subtitle"><?= te('title.item_types_sub') ?></p></div>
    <div class="col-md-6 text-md-right"><button class="btn btn-primary" data-toggle="modal" data-target="#formModal" data-mode="create"><i class="fas fa-plus"></i> <?= te('inv.it_new') ?></button></div>
</div>
<form method="GET" class="card filter-row mb-3">
    <div class="row align-items-end"><div class="col-md-4 mb-2"><label class="small font-weight-semibold"><?= te('inv.filter_by_category') ?></label>
        <select name="category_id" class="form-control select2"><option value=""><?= te('common.all') ?></option>
            <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $catFilter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-2 mb-2"><button class="btn btn-primary btn-block"><i class="fas fa-filter"></i> <?= te('common.filter') ?></button></div>
        <div class="col-md-2 mb-2"><a href="<?= SITE_URL ?>/item_types.php" class="btn btn-outline-secondary btn-block"><?= te('common.reset') ?></a></div>
    </div>
</form>
<div class="card"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-striped mb-0"><thead><tr><th><?= te('th.category') ?></th><th><?= te('inv.it_name') ?></th><th class="text-right"><?= te('th.default_rate') ?></th>
    <th class="text-center"><?= te('th.total') ?></th><th class="text-center"><?= te('th.booked_today') ?></th><th class="text-center"><?= te('th.available') ?></th><th><?= te('th.status') ?></th><th></th></tr></thead><tbody>
<?php if (empty($rows)): ?><tr><td colspan="8" class="text-center text-muted py-5"><?= te('common.no_records') ?></td></tr>
<?php else: foreach ($rows as $r):
    $booked = getBookedQuantity($r['id'], $today, $today);
    $avail = max(0, (int)$r['quantity'] - $booked);
    $availClass = $avail <= 0 ? 'equipment-unavailable' : ($avail < (int)$r['quantity'] ? 'equipment-limited' : 'equipment-available');
?>
<tr><td><?= e($r['category_name']) ?></td><td class="font-weight-semibold"><?= e($r['name']) ?></td>
<td class="text-right"><?= formatMoney($r['default_rental_value']) ?></td>
<td class="text-center"><?= (int)$r['quantity'] ?></td>
<td class="text-center"><?= $booked ?></td>
<td class="text-center font-weight-bold <?= $availClass ?>"><?= $avail ?></td>
<td><?= $r['active'] ? '<span class="status-badge status-confirmed">'.te('inv.status_active').'</span>' : '<span class="status-badge status-canceled">'.te('inv.status_inactive').'</span>' ?></td>
<td class="text-right">
<button class="btn btn-sm btn-outline-secondary" onclick="editRow(<?= $r['id'] ?>)"><i class="fas fa-edit"></i></button>
<a href="<?= SITE_URL ?>/item_types.php?delete=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger confirm-delete"><i class="fas fa-times"></i></a>
</td></tr>
<?php endforeach; endif; ?>
</tbody></table></div></div></div>

<div class="modal fade" id="formModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><form class="modal-content" method="POST">
<?php csrf_field(); ?>
<div class="modal-header"><h5 class="modal-title" id="formModalTitle"><?= te('inv.it_new') ?></h5><button class="close" data-dismiss="modal">&times;</button></div>
<div class="modal-body">
<input type="hidden" name="action" id="formAction" value="create"><input type="hidden" name="id" id="formId" value="">
<div class="form-row">
    <div class="form-group col-md-6"><label><?= te('inv.it_category') ?> *</label>
        <select name="category_id" id="fld_category_id" class="form-control select2" required>
            <option value=""><?= te('common.select_option') ?></option>
            <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="form-group col-md-6"><label><?= te('inv.it_name') ?> *</label><input required name="name" id="fld_name" class="form-control"></div>
    <div class="form-group col-md-4"><label><?= te('inv.it_rental_value') ?></label><input type="number" step="0.01" name="default_rental_value" id="fld_default_rental_value" class="form-control" min="0"></div>
    <div class="form-group col-md-4"><label><?= te('inv.it_qty') ?></label><input type="number" name="quantity" id="fld_quantity" class="form-control" min="0"></div>
    <div class="form-group col-md-4 pt-2"><div class="custom-control custom-switch mt-4">
        <input type="checkbox" name="active" id="fld_active" class="custom-control-input" checked>
        <label class="custom-control-label" for="fld_active"><?= te('u.active') ?></label></div></div>
    <div class="form-group col-md-12"><label><?= te('inv.it_description') ?></label><textarea name="description" id="fld_description" rows="2" class="form-control"></textarea></div>
</div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= te('common.cancel') ?></button>
<button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> <?= te('common.save') ?></button></div>
</form></div></div>
<script>
var rows = <?= json_encode($rows) ?>;
function editRow(id) {
    var r = rows.find(x => x.id == id); if (!r) return;
    $('#formModalTitle').text(<?= json_encode(t('inv.it_edit')) ?>); $('#formAction').val('update'); $('#formId').val(id);
    $('#fld_category_id').val(r.category_id).trigger('change');
    $('#fld_name').val(r.name || '');
    $('#fld_description').val(r.description || '');
    $('#fld_default_rental_value').val(r.default_rental_value);
    $('#fld_quantity').val(r.quantity);
    $('#fld_active').prop('checked', parseInt(r.active) === 1);
    $('#formModal').modal('show');
}
$('#formModal').on('show.bs.modal', function(e) {
    var mode = (e && e.relatedTarget) ? $(e.relatedTarget).data('mode') || null : null;
    if (mode === 'create' || !$('#formId').val()) {
        $('#formModalTitle').text(<?= json_encode(t('inv.it_new')) ?>); $('#formAction').val('create'); $('#formId').val('');
        $('#formModal form')[0].reset(); $('#fld_active').prop('checked', true); $('#fld_category_id').val('').trigger('change');
    }
});
</script>
<?php include SITE_PATH . '/includes/footer.php'; ?>
