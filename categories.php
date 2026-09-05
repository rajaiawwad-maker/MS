<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('manage_setup');

$page_title = t('title.categories');
$active_nav = 'inventory';
$table = 'categories'; $pk = 'id';
$fields = ['name' => ['label' => t('inv.cat_name'), 'required' => true], 'description' => ['label' => t('inv.cat_description'), 'type' => 'textarea'], 'active' => ['label' => t('u.active'), 'type' => 'checkbox', 'default' => '1']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $action = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    $vals = []; $cols = []; $ph = [];
    foreach ($fields as $f => $o) {
        if ($o['type'] ?? '' === 'checkbox') $v = isset($_POST[$f]) ? 1 : 0;
        else $v = trim($_POST[$f] ?? $o['default'] ?? '');
        if (!empty($o['required']) && $v === '') { setFlash('error', vsprintf(t('err.field_required'), [$o['label']])); redirect(SITE_URL.'/categories.php'); }
        $vals[] = $v; $cols[] = "`$f`=?"; $ph[] = "?";
    }
    if ($action === 'create') {
        $sql = "INSERT INTO $table (".implode(',', array_keys($fields)).") VALUES (".implode(',', $ph).")";
        $conn->prepare($sql)->execute($vals);
        auditLog('create', 'Category', $conn->lastInsertId());
        setFlash('success', t('common.created'));
    } elseif ($action === 'update' && $id) {
        $sql = "UPDATE $table SET ".implode(',', $cols).", updated_at=NOW() WHERE $pk=?";
        $vals[] = $id;
        $conn->prepare($sql)->execute($vals);
        auditLog('update', 'Category', $id);
        setFlash('success', t('common.updated'));
    }
    redirect(SITE_URL.'/categories.php');
}
if (isset($_GET['delete'])) {
    $conn->prepare("UPDATE $table SET active=0, updated_at=NOW() WHERE $pk=?")->execute([(int)$_GET['delete']]);
    auditLog('deactivate', 'Category', (int)$_GET['delete']);
    setFlash('success', t('common.deactivated')); redirect(SITE_URL.'/categories.php');
}

$stmt = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM item_types WHERE category_id = c.id) as item_count
    FROM $table c ORDER BY c.name");
$rows = $stmt->fetchAll();

include SITE_PATH . '/includes/header.php';
echo flashMessages();
?>
<div class="row mb-3 align-items-end">
    <div class="col-md-6"><h1 class="page-title"><?= te('title.categories') ?></h1><p class="page-subtitle"><?= te('title.categories_sub') ?></p></div>
    <div class="col-md-6 text-md-right"><button class="btn btn-primary" data-toggle="modal" data-target="#formModal" data-mode="create"><i class="fas fa-plus"></i> <?= te('inv.cat_new') ?></button></div>
</div>
<div class="card"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-striped mb-0"><thead><tr><th><?= te('inv.cat_name') ?></th><th><?= te('th.description') ?></th><th class="text-center"><?= te('th.item_types') ?></th><th><?= te('th.status') ?></th><th></th></tr></thead><tbody>
<?php if (empty($rows)): ?><tr><td colspan="5" class="text-center text-muted py-5"><?= te('common.no_records') ?></td></tr>
<?php else: foreach ($rows as $r): ?>
<tr><td class="font-weight-semibold"><?= e($r['name']) ?></td><td><?= e($r['description'] ?? '-') ?></td>
<td class="text-center"><strong><?= (int)$r['item_count'] ?></strong></td>
<td><?= $r['active'] ? '<span class="status-badge status-confirmed">'.te('inv.status_active').'</span>' : '<span class="status-badge status-canceled">'.te('inv.status_inactive').'</span>' ?></td>
<td class="text-right">
<a href="<?= SITE_URL ?>/item_types.php?category_id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary" title="<?= te('th.item_types') ?>"><i class="fas fa-list"></i></a>
<button class="btn btn-sm btn-outline-secondary" onclick="editRow(<?= $r['id'] ?>)"><i class="fas fa-edit"></i></button>
<a href="<?= SITE_URL ?>/categories.php?delete=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger confirm-delete"><i class="fas fa-times"></i></a>
</td></tr>
<?php endforeach; endif; ?>
</tbody></table></div></div></div>

<div class="modal fade" id="formModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST">
<?php csrf_field(); ?>
<div class="modal-header"><h5 class="modal-title" id="formModalTitle"><?= te('inv.cat_new') ?></h5><button class="close" data-dismiss="modal">&times;</button></div>
<div class="modal-body">
<input type="hidden" name="action" id="formAction" value="create">
<input type="hidden" name="id" id="formId" value="">
<?php foreach ($fields as $f => $o): $label = $o['label']; $type = $o['type'] ?? 'text'; ?>
<div class="form-group">
    <label><?= $label ?><?php if (!empty($o['required'])): ?> <span class="text-danger">*</span><?php endif; ?></label>
    <?php if ($type === 'textarea'): ?><textarea name="<?= $f ?>" id="fld_<?= $f ?>" rows="3" class="form-control"></textarea>
    <?php elseif ($type === 'checkbox'): ?>
    <div class="custom-control custom-switch"><input type="checkbox" name="<?= $f ?>" id="fld_<?= $f ?>" class="custom-control-input" checked>
    <label class="custom-control-label" for="fld_<?= $f ?>"><?= te('u.active') ?></label></div>
    <?php else: ?><input type="<?= $type ?>" name="<?= $f ?>" id="fld_<?= $f ?>" class="form-control"><?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= te('common.cancel') ?></button>
<button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> <?= te('common.save') ?></button></div>
</form></div></div>
<script>
var rows = <?= json_encode($rows) ?>;
function editRow(id) {
    var r = rows.find(x => x.id == id); if (!r) return;
    $('#formModalTitle').text(<?= json_encode(t('inv.cat_edit')) ?>); $('#formAction').val('update'); $('#formId').val(id);
    <?php foreach ($fields as $f => $o): $type = $o['type'] ?? 'text'; ?>
    <?php if ($type === 'checkbox'): ?>$('#fld_<?= $f ?>').prop('checked', parseInt(r.<?= $f ?>) === 1);
    <?php else: ?>$('#fld_<?= $f ?>').val(r.<?= $f ?> || '');<?php endif; ?>
    <?php endforeach; ?>
    $('#formModal').modal('show');
}
$('#formModal').on('show.bs.modal', function(e) {
    var mode = (e && e.relatedTarget) ? $(e.relatedTarget).data('mode') || null : null;
    if (mode === 'create' || !$('#formId').val()) {
        $('#formModalTitle').text(<?= json_encode(t('inv.cat_new')) ?>); $('#formAction').val('create'); $('#formId').val('');
        $('#formModal form')[0].reset(); $('#fld_active').prop('checked', true);
    }
});
</script>
<?php include SITE_PATH . '/includes/footer.php'; ?>
