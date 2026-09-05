<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$page = $_GET['step'] ?? 'check';
$root = __DIR__;
$configPath = $root . '/config.php';
$schemaPath = $root . '/database/schema.sql';

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'dj_rak_system';
if (file_exists($configPath)) {
    require_once $configPath;
    $dbHost = DB_HOST; $dbUser = DB_USER; $dbPass = DB_PASS; $dbName = DB_NAME;
}

$errors = [];
$messages = [];
$phpOk = PHP_VERSION_ID >= 70400;
$pdoOk = extension_loaded('pdo_mysql');
$jsonOk = extension_loaded('json');
$sessionOk = extension_loaded('session');
$writable = is_writable($root) || is_writable(__DIR__ . '/uploads');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($page === 'config') {
        $dbHost = trim($_POST['db_host'] ?? 'localhost');
        $dbUser = trim($_POST['db_user'] ?? 'root');
        $dbPass = $_POST['db_pass'] ?? '';
        $dbName = trim($_POST['db_name'] ?? 'dj_rak_system');

        try {
            $tmp = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            if (!empty($_POST['create_db'])) {
                $tmp->exec("CREATE DATABASE IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $messages[] = "Database `$dbName` created/verified.";
            }
            $tmp = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            $configStr = '<?php
date_default_timezone_set(\'Asia/Riyadh\');
ini_set(\'display_errors\', 1);
ini_set(\'display_startup_errors\', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

define(\'DB_HOST\', \'' . addslashes($dbHost) . '\');
define(\'DB_USER\', \'' . addslashes($dbUser) . '\');
define(\'DB_PASS\', \'' . addslashes($dbPass) . '\');
define(\'DB_NAME\', \'' . addslashes($dbName) . '\');

define(\'SITE_URL\', \'http://\' . $_SERVER[\'HTTP_HOST\'] . str_replace(\'\\\\\', \'/\', dirname($_SERVER[\'SCRIPT_NAME\'])));
define(\'SITE_PATH\', realpath(dirname(__FILE__)));

define(\'CURRENCY_SYMBOL\', \'JOD\');
define(\'DATE_FORMAT\', \'d/m/Y\');
define(\'DATETIME_FORMAT\', \'d/m/Y H:i\');

define(\'SESSION_TIMEOUT\', 3600);
define(\'BOOKING_PREFIX\', \'BK\');

define(\'UPLOAD_DIR\', SITE_PATH . DIRECTORY_SEPARATOR . \'uploads\');
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

require_once SITE_PATH . \'/includes/functions.php\';
require_once SITE_PATH . \'/includes/db.php\';

session_start();

$db = new Database();
$conn = $db->getConnection();

loadSystemSettings();
';
            file_put_contents($configPath, $configStr);
            $messages[] = "Configuration file written.";
            header('Location: install.php?step=schema');
            exit;
        } catch (Exception $e) {
            $errors[] = "Database connection failed: " . $e->getMessage();
        }
    } elseif ($page === 'schema') {
        require_once $configPath;
        try {
            $sql = file_get_contents($schemaPath);
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            $executed = 0;
            foreach ($statements as $s) {
                if (!empty($s) && stripos($s, 'CREATE DATABASE') !== false) {
                    $conn->exec($s);
                    $conn->exec("USE `$dbName`");
                    continue;
                }
                if (!empty($s)) {
                    try { $conn->exec($s); $executed++; } catch (Exception $e) { }
                }
            }
            $messages[] = "Database schema installed successfully ($executed statements executed).";
            header('Location: install.php?step=done');
            exit;
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DJ RAK Manager - Installer</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .install-card { border-radius: 1rem; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .step-badge { width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center; border-radius:50%; font-weight:bold; color:white; background:#6c757d; }
        .step-badge.done { background:#28a745; }
        .step-badge.active { background:#667eea; }
        .step-label { margin-left:10px; vertical-align:middle; font-weight:600; }
    </style>
</head>
<body class="py-5">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="text-center mb-4">
                <h1 class="text-white mb-0"><i class="fas fa-compact-disc mr-2"></i>DJ RAK Manager</h1>
                <p class="text-white-50">System Installation Wizard</p>
            </div>
            <div class="card install-card">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex justify-content-between mb-4 pb-3 border-bottom">
                        <div><span class="step-badge <?= $page === 'check' ? 'active' : (in_array($page, ['config','schema','done']) ? 'done' : '') ?>">1</span><span class="step-label">Requirements</span></div>
                        <div><span class="step-badge <?= $page === 'config' ? 'active' : (in_array($page, ['schema','done']) ? 'done' : '') ?>">2</span><span class="step-label">Database</span></div>
                        <div><span class="step-badge <?= $page === 'schema' ? 'active' : ($page === 'done' ? 'done' : '') ?>">3</span><span class="step-label">Schema</span></div>
                        <div><span class="step-badge <?= $page === 'done' ? 'done' : '' ?>">4</span><span class="step-label">Complete</span></div>
                    </div>

                    <?php if (!empty($errors)): foreach ($errors as $e): ?>
                        <div class="alert alert-danger"><i class="fas fa-times-circle mr-2"></i><?= $e ?></div>
                    <?php endforeach; endif; ?>
                    <?php if (!empty($messages)): foreach ($messages as $m): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle mr-2"></i><?= $m ?></div>
                    <?php endforeach; endif; ?>

                    <?php if ($page === 'check'): ?>
                        <h3 class="mb-4"><i class="fas fa-check-circle text-info mr-2"></i>System Requirements</h3>
                        <div class="table-responsive">
                        <table class="table">
                            <tr><td>PHP 7.4+</td><td class="text-right">Version: <?= phpversion() ?></td><td class="text-right"><?= $phpOk ? '<span class="text-success"><i class="fas fa-check-circle"></i> Pass</span>' : '<span class="text-danger"><i class="fas fa-times-circle"></i> Fail</span>' ?></td></tr>
                            <tr><td>PDO MySQL Extension</td><td class="text-right"><?= $pdoOk ? 'Loaded' : 'Not loaded' ?></td><td class="text-right"><?= $pdoOk ? '<span class="text-success"><i class="fas fa-check-circle"></i> Pass</span>' : '<span class="text-danger"><i class="fas fa-times-circle"></i> Fail</span>' ?></td></tr>
                            <tr><td>JSON Extension</td><td class="text-right"><?= $jsonOk ? 'Loaded' : 'Not loaded' ?></td><td class="text-right"><?= $jsonOk ? '<span class="text-success"><i class="fas fa-check-circle"></i> Pass</span>' : '<span class="text-danger"><i class="fas fa-times-circle"></i> Fail</span>' ?></td></tr>
                            <tr><td>Session Extension</td><td class="text-right"><?= $sessionOk ? 'Loaded' : 'Not loaded' ?></td><td class="text-right"><?= $sessionOk ? '<span class="text-success"><i class="fas fa-check-circle"></i> Pass</span>' : '<span class="text-danger"><i class="fas fa-times-circle"></i> Fail</span>' ?></td></tr>
                            <tr><td>Uploads Writable</td><td class="text-right"><?= $writable ? 'Yes' : 'No' ?></td><td class="text-right"><?= $writable ? '<span class="text-success"><i class="fas fa-check-circle"></i> Pass</span>' : '<span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Warning</span>' ?></td></tr>
                        </table>
                        </div>
                        <?php if (!file_exists($schemaPath)): ?><div class="alert alert-danger">Missing schema file at: <?= e($schemaPath) ?></div><?php endif; ?>
                        <hr>
                        <div class="text-right">
                            <a href="install.php?step=config" class="btn btn-primary btn-lg px-5 <?= ($phpOk && $pdoOk && $jsonOk && $sessionOk) ? '' : 'disabled' ?>">
                                Continue <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($page === 'config'): ?>
                        <h3 class="mb-4"><i class="fas fa-database text-info mr-2"></i>Database Configuration</h3>
                        <form method="POST">
                            <div class="form-row">
                                <div class="form-group col-md-6"><label>Database Host</label><input name="db_host" class="form-control form-control-lg" value="<?= e($dbHost) ?>"></div>
                                <div class="form-group col-md-6"><label>Database Name</label><input name="db_name" class="form-control form-control-lg" value="<?= e($dbName) ?>"></div>
                                <div class="form-group col-md-6"><label>Database User</label><input name="db_user" class="form-control form-control-lg" value="<?= e($dbUser) ?>"></div>
                                <div class="form-group col-md-6"><label>Database Password</label><input name="db_pass" type="password" class="form-control form-control-lg" value="<?= e($dbPass) ?>" placeholder="Leave blank for default XAMPP"></div>
                            </div>
                            <div class="custom-control custom-checkbox mb-4">
                                <input type="checkbox" class="custom-control-input" id="createDb" name="create_db" checked>
                                <label class="custom-control-label" for="createDb">Create database if it does not exist</label>
                            </div>
                            <div class="text-right">
                                <a href="install.php?step=check" class="btn btn-outline-secondary mr-2">Back</a>
                                <button type="submit" class="btn btn-primary btn-lg px-5">Test &amp; Save <i class="fas fa-save ml-2"></i></button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ($page === 'schema'):
                        $tables = [];
                        try {
                            $stmt = $conn->query("SHOW TABLES");
                            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        } catch (Exception $e) {}
                        $tablesNeeded = ['roles','permissions','role_permissions','users','categories','item_types','inventory_items','clients','expense_types','bookings','booking_items','payments','expenses','audit_logs','system_settings'];
                        $missing = array_diff($tablesNeeded, $tables);
                    ?>
                        <h3 class="mb-4"><i class="fas fa-table text-info mr-2"></i>Install Database Schema</h3>
                        <?php if (empty($missing)): ?>
                            <div class="alert alert-info">Schema tables already installed. You can re-run installation to add missing data.</div>
                        <?php elseif (!empty($missing)): ?>
                            <div class="alert alert-warning">
                                Missing tables: <?= implode(', ', $missing) ?>. Click below to install.
                            </div>
                        <?php endif; ?>
                        <div class="text-muted mb-4">Schema file: <code><?= e($schemaPath) ?></code></div>
                        <form method="POST">
                            <div class="text-right">
                                <a href="install.php?step=config" class="btn btn-outline-secondary mr-2">Back</a>
                                <button type="submit" class="btn btn-primary btn-lg px-5">
                                    <i class="fas fa-download mr-2"></i><?= empty($tables) ? 'Install Schema' : 'Reinstall / Run Schema' ?>
                                </button>
                                <a href="install.php?step=done" class="btn btn-outline-success btn-lg px-4 ml-2">Skip</a>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ($page === 'done'): ?>
                        <div class="text-center py-4">
                            <div class="mb-4"><i class="fas fa-check-circle fa-5x text-success"></i></div>
                            <h2 class="mb-2">Installation Complete!</h2>
                            <p class="text-muted mb-4">The DJ RAK Manager system has been successfully installed.</p>
                            <div class="alert alert-warning text-left">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <strong>Important:</strong> Delete the <code>install.php</code> file from production immediately after login. Change your admin password after first login.
                            </div>
                            <a href="login.php" class="btn btn-primary btn-lg px-5 mt-2">
                                <i class="fas fa-sign-in-alt mr-2"></i>Proceed to Login
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-center text-white-50 small mt-3">DJ RAK Inventory &amp; Rental Management System</div>
        </div>
    </div>
</div>
</body>
</html>
