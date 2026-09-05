<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('view_clients');

$page_title = t('title.clients_list');
$active_nav = 'clients';

$search = trim($_GET['q'] ?? '');
$where = ['1=1']; $params = [];
if ($search !== '') {
    $where[] = "(name LIKE ? OR phone LIKE ? OR alt_phone LIKE ? OR email LIKE ? OR notes LIKE ?)";
    $s = "%$search%"; $params = [$s,$s,$s,$s,$s];
}
if (isset($_GET['active']) && $_GET['active'] !== '') {
    $where[] = "active = ?"; $params[] = (int)$_GET['active'];
}

$stmt = $conn->prepare("SELECT c.*, (SELECT COUNT(*) FROM bookings WHERE client_id = c.id) as booking_count,
    (SELECT COALESCE(SUM(quoted_amount),0) FROM bookings WHERE client_id = c.id AND status != 'Canceled') as total_value,
    (SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id IN (SELECT id FROM bookings WHERE client_id = c.id)) as total_paid
    FROM clients c WHERE " . implode(' AND ', $where) . " ORDER BY name ASC LIMIT 500");
$stmt->execute($params);
$clients = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('manage_clients')) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if ($name === '' || $phone === '') { setFlash('error', t('c.name_phone_required')); redirect(SITE_URL . '/clients.php'); }
    $alt = trim($_POST['alt_phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;
    if ($action === 'create') {
        $stmt = $conn->prepare("INSERT INTO clients (name, phone, alt_phone, email, address, notes, active) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$name, $phone, $alt, $email, $address, $notes, $active]);
        auditLog('create', 'Client', $conn->lastInsertId(), null, $_POST);
        setFlash('success', t('c.create_success'));
    } elseif ($action === 'update' && $id > 0) {
        $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?"); $stmt->execute([$id]); $old = $stmt->fetch();
        $stmt = $conn->prepare("UPDATE clients SET name=?, phone=?, alt_phone=?, email=?, address=?, notes=?, active=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$name, $phone, $alt, $email, $address, $notes, $active, $id]);
        auditLog('update', 'Client', $id, $old, $_POST);
        setFlash('success', t('c.update_success'));
    }
    redirect(SITE_URL . '/clients.php');
}

if (isset($_GET['delete']) && hasPermission('manage_clients')) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?"); $stmt->execute([$id]); $old = $stmt->fetch();
    $stmt = $conn->prepare("UPDATE clients SET active = 0, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    auditLog('deactivate', 'Client', $id, $old, ['active' => 0]);
    setFlash('success', t('c.deactivated'));
    redirect(SITE_URL . '/clients.php');
}

$editClient = null;
if (isset($_GET['edit']) && hasPermission('manage_clients')) {
    $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editClient = $stmt->fetch();
}

include SITE_PATH . '/includes/header.php';
echo flashMessages();
?>
<div class="row mb-3 align-items-end">
    <div class="col-md-6"><h1 class="page-title"><?= te('title.clients_list') ?></h1><p class="page-subtitle"><?= te('title.clients_list_sub') ?></p></div>
    <div class="col-md-6 text-md-right"><?php if (hasPermission('manage_clients')): ?>
        <button class="btn btn-primary" data-toggle="modal" data-target="#clientModal" data-mode="create"><i class="fas fa-plus"></i> <?= te('c.new_client') ?></button>
    <?php endif; ?></div>
</div>

<form method="GET" class="card filter-row mb-3">
    <div class="row align-items-end">
        <div class="col-md-5 mb-2"><input name="q" class="form-control" value="<?= e($search) ?>" placeholder="<?= te('c.search_placeholder') ?>"></div>
        <div class="col-md-3 mb-2"><select name="active" class="form-control">
            <option value=""><?= te('c.all_statuses') ?></option>
            <option value="1" <?= (isset($_GET['active']) && $_GET['active'] === '1') ? 'selected' : '' ?>><?= te('inv.status_active') ?></option>
            <option value="0" <?= (isset($_GET['active']) && $_GET['active'] === '0') ? 'selected' : '' ?>><?= te('inv.status_inactive') ?></option>
        </select></div>
        <div class="col-md-2 mb-2"><button class="btn btn-primary btn-block"><i class="fas fa-filter"></i> <?= te('common.filter') ?></button></div>
        <div class="col-md-2 mb-2"><a href="<?= SITE_URL ?>/clients.php" class="btn btn-outline-secondary btn-block"><?= te('common.reset') ?></a></div>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead><tr><th><?= te('th.user_name') ?></th><th><?= te('th.phone') ?></th><th><?= te('c.email') ?></th><th class="text-center"><?= te('th.bookings') ?></th>
                    <th class="text-right"><?= te('th.total_value') ?></th><th class="text-right"><?= te('th.total_paid') ?></th><th class="text-right"><?= te('th.balance') ?></th>
                    <th><?= te('th.status') ?></th><th></th></tr></thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-5"><?= te('c.no_clients') ?></td></tr>
                    <?php else: foreach ($clients as $c):
                        $balance = max(0, (float)$c['total_value'] - (float)$c['total_paid']); ?>
                        <tr>
                            <td class="font-weight-semibold"><?= e($c['name']) ?></td>
                            <td><a href="tel:<?= e($c['phone']) ?>"><?= e($c['phone']) ?></a><?= $c['alt_phone'] ? '<br><small class="text-muted">'.e($c['alt_phone']).'</small>' : '' ?></td>
                            <td><?= $c['email'] ? '<a href="mailto:'.e($c['email']).'">'.e($c['email']).'</a>' : '-' ?></td>
                            <td class="text-center font-weight-bold"><?= (int)$c['booking_count'] ?></td>
                            <td class="text-right"><?= formatMoney($c['total_value']) ?></td>
                            <td class="text-right text-success"><?= formatMoney($c['total_paid']) ?></td>
                            <td class="text-right <?= $balance > 0 ? 'text-danger font-weight-semibold' : '' ?>"><?= formatMoney($balance) ?></td>
                            <td><?= $c['active'] ? '<span class="status-badge status-confirmed">'.te('inv.status_active').'</span>' : '<span class="status-badge status-canceled">'.te('inv.status_inactive').'</span>' ?></td>
                            <td class="text-right">
                                <a href="<?= SITE_URL ?>/reports_client_statement.php?client_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-info" title="<?= te('btn.view_statement') ?>"><i class="fas fa-file-invoice-dollar"></i></a>
                                <a href="<?= SITE_URL ?>/bookings.php?client_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="<?= te('nav.bookings') ?>"><i class="fas fa-book"></i></a>
                                <?php if (hasPermission('manage_clients')): ?>
                                    <a href="<?= SITE_URL ?>/clients.php?edit=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary" title="<?= te('common.edit') ?>" onclick="event.preventDefault();$('#clientModal').data('mode','edit').data('id',<?= $c['id'] ?>).modal('show');loadClient(<?= $c['id'] ?>)"><i class="fas fa-edit"></i></a>
                                    <a href="<?= SITE_URL ?>/clients.php?delete=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger confirm-delete" title="<?= t('c.delete_confirm') ?>"><i class="fas fa-times"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (hasPermission('manage_clients')): ?>
<div class="modal fade" id="clientModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered">
<form class="modal-content" method="POST">
    <div class="modal-header"><h5 class="modal-title" id="clientModalTitle"><?= te('c.new_client') ?></h5><button class="close" data-dismiss="modal">&times;</button></div>
    <div class="modal-body">
        <input type="hidden" name="action" id="clientAction" value="create">
        <input type="hidden" name="id" id="clientId" value="">
        <div class="form-row">
            <div class="form-group col-md-8"><label><?= te('c.name') ?> *</label><input required name="name" id="clientName" class="form-control"></div>
            <div class="form-group col-md-4"><label><?= te('c.primary_phone') ?> *</label><input required name="phone" id="clientPhone" class="form-control"></div>
            <div class="form-group col-md-4"><label><?= te('c.alt_phone') ?></label><input name="alt_phone" id="clientAltPhone" class="form-control"></div>
            <div class="form-group col-md-4"><label><?= te('c.email') ?></label><input type="email" name="email" id="clientEmail" class="form-control"></div>
            <div class="form-group col-md-12"><label><?= te('c.address') ?></label><input name="address" id="clientAddress" class="form-control"></div>
            <div class="form-group col-md-12"><label><?= te('c.notes') ?></label><textarea name="notes" id="clientNotes" rows="3" class="form-control"></textarea></div>
            <div class="form-group col-md-12"><div class="custom-control custom-checkbox">
                <input type="checkbox" name="active" id="clientActive" class="custom-control-input" checked>
                <label for="clientActive" class="custom-control-label"><?= te('u.active') ?></label>
            </div></div>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= te('common.cancel') ?></button>
    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> <?= te('common.save') ?></button></div>
</form>
</div></div>
<script>
var clients = <?= json_encode($clients) ?>;
function loadClient(id) {
    var c = clients.find(x => x.id === id);
    if (!c) return;
    $('#clientModalTitle').text(<?= json_encode(t('c.edit_client')) ?>);
    $('#clientAction').val('update');
    $('#clientId').val(c.id);
    $('#clientName').val(c.name); $('#clientPhone').val(c.phone); $('#clientAltPhone').val(c.alt_phone || '');
    $('#clientEmail').val(c.email || ''); $('#clientAddress').val(c.address || ''); $('#clientNotes').val(c.notes || '');
    $('#clientActive').prop('checked', parseInt(c.active) === 1);
}
$('#clientModal').on('show.bs.modal', function(e) {
    if ($(e.relatedTarget).data('mode') === 'create' || !$('#clientId').val()) {
        $('#clientModalTitle').text(<?= json_encode(t('c.new_client')) ?>);
        $('#clientAction').val('create'); $('#clientId').val('');
        $('#clientModal form')[0].reset(); $('#clientActive').prop('checked', true);
    }
});
</script>
<?php endif;
include SITE_PATH . '/includes/footer.php';
?>
