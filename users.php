<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('manage_users');

$page_title = t('title.users');
$active_nav = 'users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '') ?: null;
    $phone = trim($_POST['phone'] ?? '') ?: null;
    $roleId = (int)($_POST['role_id'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;
    $password = $_POST['password'] ?? '';

    if ($action === 'create') {
        if ($name === '' || $username === '' || $roleId <= 0 || $password === '') {
            setFlash('error', t('u.create_required'));
            redirect(SITE_URL.'/users.php');
        }
        if (strlen($password) < 8) { setFlash('error', t('u.password_min')); redirect(SITE_URL.'/users.php'); }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $conn->prepare("INSERT INTO users (name, username, email, password_hash, role_id, phone, active) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$name, $username, $email, $hash, $roleId, $phone, $active]);
            auditLog('create', 'User', $conn->lastInsertId());
            setFlash('success', t('u.create_success'));
        } catch (Exception $e) { setFlash('error', t('u.username_email_exists')); }
    } elseif ($action === 'update' && $id > 0) {
        if ($name === '' || $username === '' || $roleId <= 0) {
            setFlash('error', t('u.update_required'));
            redirect(SITE_URL.'/users.php');
        }
        $updates = "name=?, username=?, email=?, role_id=?, phone=?, active=?";
        $params = [$name, $username, $email, $roleId, $phone, $active];
        if ($password !== '') {
            if (strlen($password) < 8) { setFlash('error', t('u.password_min')); redirect(SITE_URL.'/users.php'); }
            $updates .= ", password_hash=?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        $params[] = $id;
        try {
            $stmt = $conn->prepare("UPDATE users SET $updates, updated_at=NOW() WHERE id=?");
            $stmt->execute($params);
            auditLog('update', 'User', $id);
            setFlash('success', t('u.update_success'));
        } catch (Exception $e) { setFlash('error', t('u.username_email_exists')); }
    }
    redirect(SITE_URL.'/users.php');
}
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id !== (int)$_SESSION['user_id']) {
        $conn->prepare("UPDATE users SET active = 0, updated_at=NOW() WHERE id = ?")->execute([$id]);
        auditLog('deactivate', 'User', $id);
        setFlash('success', t('u.deactivated'));
    } else {
        setFlash('error', t('u.cannot_deactivate_self'));
    }
    redirect(SITE_URL.'/users.php');
}

$users = $conn->query("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY u.name")->fetchAll();
$roles = $conn->query("SELECT * FROM roles ORDER BY id")->fetchAll();

include SITE_PATH . '/includes/header.php';
echo flashMessages();
?>
<div class="row mb-3 align-items-end">
    <div class="col-md-6"><h1 class="page-title"><?= te('title.users') ?></h1><p class="page-subtitle"><?= te('title.users_sub') ?></p></div>
    <div class="col-md-6 text-md-right">
        <button class="btn btn-primary" data-toggle="modal" data-target="#userModal" data-mode="create"><i class="fas fa-user-plus"></i> <?= te('u.new_user') ?></button>
    </div>
</div>
<div class="card"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-striped mb-0"><thead><tr><th><?= te('th.user_name') ?></th><th><?= te('u.username') ?></th><th><?= te('u.email') ?></th><th><?= te('u.phone') ?></th><th><?= te('u.role') ?></th><th><?= te('th.last_login') ?></th><th><?= te('th.status') ?></th><th></th></tr></thead><tbody>
<?php if (empty($users)): ?><tr><td colspan="8" class="text-center text-muted py-5"><?= te('u.no_users') ?></td></tr>
<?php else: foreach ($users as $u): ?>
<tr>
    <td class="font-weight-semibold"><?= e($u['name']) ?></td>
    <td><?= e($u['username']) ?></td>
    <td><?= $u['email'] ? e($u['email']) : '-' ?></td>
    <td><?= $u['phone'] ? e($u['phone']) : '-' ?></td>
    <td><span class="badge badge-info"><?= e($u['role_name']) ?></span></td>
    <td><?= $u['last_login'] ? formatDateTime($u['last_login']) : '<span class="text-muted">'.te('common.never').'</span>' ?></td>
    <td><?= $u['active'] ? '<span class="status-badge status-confirmed">'.te('u.active').'</span>' : '<span class="status-badge status-canceled">'.te('u.inactive').'</span>' ?></td>
    <td class="text-right">
        <button class="btn btn-sm btn-outline-secondary" onclick='editUser(<?= json_encode($u, JSON_HEX_TAG) ?>)'><i class="fas fa-edit"></i></button>
        <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
            <a href="<?= SITE_URL ?>/users.php?delete=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger confirm-action" data-confirm="<?= t('u.deactivate_confirm') ?>"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div></div>

<div class="card mt-3 mb-3">
    <div class="card-header"><i class="fas fa-user-shield mr-2"></i><?= te('u.roles_overview') ?></div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead><tr><th><?= te('u.role') ?></th><th><?= te('u.role_description') ?></th>
                <th><?= te('th.users_count') ?></th></tr></thead>
            <tbody><?php foreach ($roles as $r):
                $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?"); $stmt->execute([$r['id']]); $c = $stmt->fetchColumn();
                ?><tr>
                <td class="font-weight-bold"><?= e($r['name']) ?></td>
                <td class="text-muted"><?= e($r['description'] ?? '') ?></td>
                <td><span class="badge badge-pill badge-primary"><?= $c ?></span></td>
            </tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><form class="modal-content" method="POST">
<div class="modal-header"><h5 class="modal-title" id="userModalTitle"><?= te('u.new_user') ?></h5><button class="close" data-dismiss="modal">&times;</button></div>
<div class="modal-body">
<input type="hidden" name="action" id="userAction" value="create"><input type="hidden" name="id" id="userId" value="">
<div class="form-row">
    <div class="form-group col-md-6"><label><?= te('u.full_name') ?> *</label><input required name="name" id="userName" class="form-control"></div>
    <div class="form-group col-md-6"><label><?= te('u.username') ?> *</label><input required name="username" id="userUsername" class="form-control"></div>
    <div class="form-group col-md-6"><label><?= te('u.email') ?></label><input type="email" name="email" id="userEmail" class="form-control"></div>
    <div class="form-group col-md-6"><label><?= te('u.phone') ?></label><input name="phone" id="userPhone" class="form-control"></div>
    <div class="form-group col-md-6"><label><?= te('u.role') ?> *</label>
        <select name="role_id" id="userRole" class="form-control" required>
            <option value=""><?= te('common.select_option') ?></option>
            <?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group col-md-6"><label id="passwordLabel"><?= te('u.password') ?> *</label>
        <input name="password" id="userPassword" class="form-control" placeholder="<?= te('u.password_keep_hint') ?>">
    </div>
    <div class="form-group col-md-12"><div class="custom-control custom-switch">
        <input type="checkbox" name="active" id="userActive" class="custom-control-input" checked>
        <label class="custom-control-label" for="userActive"><?= te('u.active') ?></label>
    </div></div>
</div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= te('common.cancel') ?></button>
<button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> <?= te('common.save') ?></button></div>
</form></div></div>
<script>
function editUser(u) {
    $('#userModalTitle').text(<?= json_encode(t('u.edit_user')) ?>);
    $('#userAction').val('update'); $('#userId').val(u.id);
    $('#userName').val(u.name); $('#userUsername').val(u.username);
    $('#userEmail').val(u.email || ''); $('#userPhone').val(u.phone || '');
    $('#userRole').val(u.role_id); $('#userPassword').val('');
    $('#passwordLabel').text(<?= json_encode(t('u.password_optional')) ?>);
    $('#userActive').prop('checked', parseInt(u.active) === 1);
    $('#userModal').modal('show');
}
$('#userModal').on('show.bs.modal', function(e) {
    var mode = (e && e.relatedTarget) ? $(e.relatedTarget).data('mode') || null : null;
    if (mode === 'create' || !$('#userId').val()) {
        $('#userModalTitle').text(<?= json_encode(t('u.new_user')) ?>); $('#userAction').val('create'); $('#userId').val('');
        $('#userModal form')[0].reset(); $('#userActive').prop('checked', true);
        $('#userRole').val(''); $('#passwordLabel').text(<?= json_encode(t('u.password')) ?> + ' *');
    }
});
</script>
<?php include SITE_PATH . '/includes/footer.php'; ?>
