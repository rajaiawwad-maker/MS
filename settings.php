<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('manage_settings');
$page_title = t('title.settings');
$active_nav = 'setup';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settings']) && is_array($_POST['settings'])) {
    validate_csrf();
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
    foreach ($_POST['settings'] as $key => $value) {
        $stmt->execute([$key, trim($value), '']);
    }
    auditLog('update', 'SystemSettings', null);
    setFlash('success', t('s.settings_saved'));
    redirect(SITE_URL.'/settings.php');
}

$settings = [];
try {
    $rows = $conn->query("SELECT * FROM system_settings ORDER BY setting_key")->fetchAll();
    foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];
} catch (Exception $e) {}

include SITE_PATH . '/includes/header.php';
echo flashMessages();
?>
<div class="row mb-3 align-items-end">
    <div class="col-md-6"><h1 class="page-title"><?= te('title.settings') ?></h1><p class="page-subtitle"><?= te('title.settings_sub_alt') ?></p></div>
</div>

<form method="POST">
<?php csrf_field(); ?>
<div class="row">
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header"><i class="fas fa-building mr-2"></i><?= te('s.company_info') ?></div>
            <div class="card-body">
                <div class="form-group"><label><?= te('s.company_name') ?></label><input name="settings[company_name]" class="form-control" value="<?= e($settings['company_name'] ?? '') ?>"></div>
                <div class="form-group"><label><?= te('s.company_tagline') ?></label><input name="settings[company_tagline]" class="form-control" value="<?= e($settings['company_tagline'] ?? '') ?>" placeholder="<?= te('s.company_tagline_placeholder') ?>"></div>
                <div class="form-group"><label><?= te('field.phone') ?></label><input name="settings[company_phone]" class="form-control" value="<?= e($settings['company_phone'] ?? '') ?>"></div>
                <div class="form-group"><label><?= te('field.email') ?></label><input name="settings[company_email]" type="email" class="form-control" value="<?= e($settings['company_email'] ?? '') ?>"></div>
                <div class="form-group"><label><?= te('field.address') ?></label><textarea name="settings[company_address]" rows="2" class="form-control"><?= e($settings['company_address'] ?? '') ?></textarea></div>
                <div class="form-group"><label><?= te('s.company_vat_no') ?></label><input name="settings[company_vat_no]" class="form-control" value="<?= e($settings['company_vat_no'] ?? '') ?>" placeholder="e.g. 300000000000003"></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header"><i class="fas fa-sliders-h mr-2"></i><?= te('s.regional_format') ?></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-6"><label><?= te('s.currency_code') ?></label><input name="settings[currency_code]" class="form-control" value="<?= e($settings['currency_code'] ?? 'JOD') ?>"></div>
                    <div class="form-group col-md-6"><label><?= te('s.currency_symbol') ?></label><input name="settings[currency_symbol]" class="form-control" value="<?= e($settings['currency_symbol'] ?? 'JOD') ?>"></div>
                    <div class="form-group col-md-6"><label><?= te('s.date_format') ?></label><input name="settings[date_format]" class="form-control" value="<?= e($settings['date_format'] ?? 'd/m/Y') ?>" placeholder="d/m/Y"></div>
                    <div class="form-group col-md-6"><label><?= te('s.timezone') ?></label><input name="settings[timezone]" class="form-control" value="<?= e($settings['timezone'] ?? 'Asia/Riyadh') ?>"></div>
                </div>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><i class="fab fa-whatsapp mr-2"></i><?= te('s.whatsapp_settings') ?></div>
            <div class="card-body">
                <div class="form-group"><label><?= te('s.whatsapp_country_code') ?></label>
                    <div class="input-group"><div class="input-group-prepend"><span class="input-group-text">+</span></div>
                        <input name="settings[whatsapp_country_code]" class="form-control" value="<?= e($settings['whatsapp_country_code'] ?? '966') ?>" placeholder="966"></div>
                    <small class="text-muted"><?= te('s.whatsapp_used_for') ?></small>
                </div>
                <div class="form-group"><label><?= te('s.booking_prefix') ?></label><input name="settings[booking_prefix]" class="form-control" value="<?= e($settings['booking_prefix'] ?? 'BK') ?>"></div>
            </div>
        </div>
    </div>
</div>
<div class="text-center mb-5">
    <button type="submit" class="btn btn-lg btn-primary px-5"><i class="fas fa-save mr-2"></i> <?= te('s.save_settings') ?></button>
</div>
</form>
<?php include SITE_PATH . '/includes/footer.php'; ?>
