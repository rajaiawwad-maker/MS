<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
$page_title = t('title.profile');

$user = currentUser();
$userId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '') ?: null;
        $phone = trim($_POST['phone'] ?? '') ?: null;
        $username = trim($_POST['username'] ?? '');
        if ($name === '' || $username === '') { setFlash('error', t('profile.update_required')); redirect(SITE_URL.'/profile.php'); }
        try {
            $stmt = $conn->prepare("UPDATE users SET name=?, username=?, email=?, phone=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$name, $username, $email, $phone, $userId]);
            setFlash('success', t('profile.profile_updated'));
        } catch (Exception $e) { setFlash('error', t('u.username_email_exists')); }
    } elseif ($action === 'password') {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?"); $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();
        if (!password_verify($cur, $hash)) { setFlash('error', t('profile.current_password_incorrect')); }
        elseif (strlen($new) < 6) { setFlash('error', t('profile.new_password_min')); }
        elseif ($new !== $conf) { setFlash('error', t('profile.passwords_mismatch')); }
        else {
            $conn->prepare("UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
            setFlash('success', t('profile.password_changed'));
        }
    }
    redirect(SITE_URL.'/profile.php');
}

$stmt = $conn->prepare("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->execute([$userId]);
$u = $stmt->fetch();

include SITE_PATH . '/includes/header.php';
echo flashMessages();
?>
<div class="row mb-3 align-items-end">
    <div class="col-md-6"><h1 class="page-title"><?= te('title.profile') ?></h1><p class="page-subtitle"><?= te('profile.manage_account') ?></p></div>
</div>
<div class="row">
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header"><i class="fas fa-user mr-2"></i><?= te('profile.profile_information') ?></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="profile">
                    <div class="form-row">
                        <div class="form-group col-md-6"><label><?= te('u.full_name') ?> *</label><input required name="name" class="form-control" value="<?= e($u['name']) ?>"></div>
                        <div class="form-group col-md-6"><label><?= te('u.username') ?> *</label><input required name="username" class="form-control" value="<?= e($u['username']) ?>"></div>
                        <div class="form-group col-md-6"><label><?= te('u.email') ?></label><input type="email" name="email" class="form-control" value="<?= e($u['email'] ?? '') ?>"></div>
                        <div class="form-group col-md-6"><label><?= te('u.phone') ?></label><input name="phone" class="form-control" value="<?= e($u['phone'] ?? '') ?>"></div>
                        <div class="form-group col-md-6"><label><?= te('u.role') ?></label><input class="form-control" value="<?= e($u['role_name']) ?>" disabled></div>
                        <div class="form-group col-md-6"><label><?= te('profile.last_login') ?></label><input class="form-control" value="<?= $u['last_login'] ? formatDateTime($u['last_login']) : te('common.never') ?>" disabled></div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> <?= te('profile.update_profile') ?></button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header"><i class="fas fa-lock mr-2"></i><?= te('profile.change_password') ?></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="password">
                    <div class="form-group"><label><?= te('profile.current_password') ?></label><input type="password" required name="current_password" class="form-control"></div>
                    <div class="form-group"><label><?= te('profile.new_password') ?></label><input type="password" required name="new_password" class="form-control" minlength="6"></div>
                    <div class="form-group"><label><?= te('profile.confirm_new_password') ?></label><input type="password" required name="confirm_password" class="form-control" minlength="6"></div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-key mr-1"></i> <?= te('profile.change_password_btn') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include SITE_PATH . '/includes/footer.php'; ?>
