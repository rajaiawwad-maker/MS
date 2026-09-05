<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('manage_setup');
$page_title = t('title.expense_types');
$active_nav = 'expenses';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? ''); $fv = (float)($_POST['fixed_value'] ?? 0);
    $desc = trim($_POST['description'] ?? ''); $active = isset($_POST['active']) ? 1 : 0;
    if ($name === '') { setFlash('error', t('exp.name_required')); redirect(SITE_URL.'/expense_types.php'); }
    if ($action === 'create') {
        $conn->prepare("INSERT INTO expense_types (name,fixed_value,description,active) VALUES (?,?,?,?)")->execute([$name,$fv,$desc,$active]);
        auditLog('create', 'ExpenseType', $conn->lastInsertId());
        setFlash('success', t('common.created'));
    } elseif ($action === 'update' && $id) {
        $conn->prepare("UPDATE expense_types SET name=?,fixed_value=?,description=?,active=?, updated_at=NOW() WHERE id=?")->execute([$name,$fv,$desc,$active,$id]);
        auditLog('update', 'ExpenseType', $id);
        setFlash('success', t('common.updated'));
    }
    redirect(SITE_URL.'/expense_types.php');
}
if (isset($_GET['delete'])) {
    $conn->prepare("UPDATE expense_types SET active=0, updated_at=NOW() WHERE id=?")->execute([(int)$_GET['delete']]);
    auditLog('deactivate', 'ExpenseType', (int)$_GET['delete']);
    setFlash('success', t('common.deactivated')); redirect(SITE_URL.'/expense_types.php');
}
$rows = $conn->query("SELECT et.*, (SELECT COUNT(*) FROM expenses WHERE expense_type_id = et.id) as usage_count FROM expense_types et ORDER BY name")->fetchAll();
include SITE_PATH . '/includes/header.php';
echo flashMessages();
?>
<div class="row mb-3 align-items-end">
    <div class="col-md-6"><h1 class="page-title"><?= te('title.expense_types') ?></h1><p class="page-subtitle"><?= te('title.expense_types_sub') ?></p></div>
    <div class="col-md-6 text-md-right"><button class="btn btn-primary" data-toggle="modal" data-target="#formModal" data-mode="create"><i class="fas fa-plus"></i> <?= te('exp.type_new') ?></button></div>
</div>
<div class="card"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-striped mb-0"><thead><tr><th><?= te('exp.type_name') ?></th><th class="text-right"><?= te('th.fixed_value') ?></th><th><?= te('th.description') ?></th><th class="text-center"><?= te('th.usages') ?></th><th><?= te('th.status') ?></th><th></th></tr></thead><tbody>
<?php if (empty($rows)): ?><tr><td colspan="6" class="text-center text-muted py-5"><?= te('common.no_records') ?></td></tr>
<?php else: foreach ($rows as $r): ?>
<tr><td class="font-weight-semibold"><?= e($r['name']) ?></td>
<td class="text-right"><?= $r['fixed_value'] > 0 ? formatMoney($r['fixed_value']) : '<span class="text-muted">-</span>' ?></td>
<td><?= e($r['description'] ?? '-') ?></td>
<td class="text-center"><strong><?= (int)$r['usage_count'] ?></strong></td>
<td><?= $r['active'] ? '<span class="status-badge status-confirmed">'.te('inv.status_active').'</span>' : '<span class="status-badge status-canceled">'.te('inv.status_inactive').'</span>' ?></td>
<td class="text-right">
<button class="btn btn-sm btn-outline-secondary" onclick="editRow(<?= $r['id'] ?>)"><i class="fas fa-edit"></i></button>
<a href="<?= SITE_URL ?>/expense_types.php?delete=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger confirm-delete"><i class="fas fa-times"></i></a>
</td></tr>
<?php endforeach; endif; ?>
</tbody></table></div></div></div>

<div class="modal fade" id="formModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST">
<div class="modal-header"><h5 class="modal-title" id="formModalTitle"><?= te('exp.type_add') ?></h5><button class="close" data-dismiss="modal">&times;</button></div>
<div class="modal-body">
<input type="hidden" name="action" id="formAction" value="create"><input type="hidden" name="id" id="formId" value="">
<div class="form-row">
    <div class="form-group col-md-8"><label><?= te('exp.type_name') ?> *</label><input required name="name" id="fld_name" class="form-control"></div>
    <div class="form-group col-md-4"><label><?= te('exp.fixed_default_value') ?></label><input type="number" step="0.01" name="fixed_value" id="fld_fixed_value" class="form-control" min="0"></div>
    <div class="form-group col-md-9"><label><?= te('exp.description') ?></label><textarea name="description" id="fld_description" rows="2" class="form-control"></textarea></div>
    <div class="form-group col-md-3 pt-2"><div class="custom-control custom-switch mt-4">
        <input type="checkbox" name="active" id="fld_active" class="custom-control-input" checked>
        <label class="custom-control-label" for="fld_active"><?= te('u.active') ?></label></div></div>
</div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= te('common.cancel') ?></button>
<button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> <?= te('common.save') ?></button></div>
</form></div></div>
<script>
var rows = <?= json_encode($rows) ?>;
function editRow(id) {
    var r = rows.find(x => x.id == id); if (!r) return;
    $('#formModalTitle').text(<?= json_encode(t('exp.type_edit')) ?>); $('#formAction').val('update'); $('#formId').val(id);
    $('#fld_name').val(r.name); $('#fld_fixed_value').val(r.fixed_value);
    $('#fld_description').val(r.description || ''); $('#fld_active').prop('checked', parseInt(r.active) === 1);
    $('#formModal').modal('show');
}
$('#formModal').on('show.bs.modal', function(e) {
    var mode = (e && e.relatedTarget) ? $(e.relatedTarget).data('mode') || null : null;
    if (mode === 'create' || !$('#formId').val()) {
        $('#formModalTitle').text(<?= json_encode(t('exp.type_new')) ?>); $('#formAction').val('create'); $('#formId').val('');
        $('#formModal form')[0].reset(); $('#fld_active').prop('checked', true);
    }
});
</script>
<?php include SITE_PATH . '/includes/footer.php'; ?>
