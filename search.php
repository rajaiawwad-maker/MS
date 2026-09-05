<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
$page_title = t('title.search');

$q = trim($_GET['q'] ?? '');
$bookings = []; $clients = []; $items = [];

if ($q !== '') {
    $s = "%$q%";
    $stmt = $conn->prepare("SELECT b.id, b.booking_number, b.location, b.date_from, c.name as client_name
        FROM bookings b INNER JOIN clients c ON b.client_id = c.id
        WHERE b.booking_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR b.location LIKE ? ORDER BY b.id DESC LIMIT 20");
    $stmt->execute([$s, $s, $s, $s]);
    $bookings = $stmt->fetchAll();

    $stmt = $conn->prepare("SELECT * FROM clients WHERE name LIKE ? OR phone LIKE ? OR alt_phone LIKE ? OR email LIKE ? OR notes LIKE ? ORDER BY name LIMIT 20");
    $stmt->execute([$s, $s, $s, $s, $s]);
    $clients = $stmt->fetchAll();

    $stmt = $conn->prepare("SELECT it.*, c.name as cat_name, a.asset_code FROM item_types it
        INNER JOIN categories c ON it.category_id = c.id
        LEFT JOIN inventory_items a ON a.item_type_id = it.id
        WHERE it.name LIKE ? OR c.name LIKE ? OR a.asset_code LIKE ? OR a.serial_number LIKE ? GROUP BY it.id ORDER BY it.name LIMIT 20");
    $stmt->execute([$s, $s, $s, $s]);
    $items = $stmt->fetchAll();
}

include SITE_PATH . '/includes/header.php';
?>
<div class="row mb-3 align-items-end">
    <div class="col-md-6"><h1 class="page-title"><?= te('title.search') ?></h1>
        <p class="page-subtitle"><?= $q !== '' ? t('search.searching_for', ['q' => $q]) : te('search.enter_term') ?></p></div>
    <div class="col-md-6 text-md-right">
        <form method="GET" class="form-inline">
            <input name="q" class="form-control" value="<?= e($q) ?>" style="width:280px" autofocus>
            <button class="btn btn-primary ml-2"><i class="fas fa-search"></i> <?= te('common.search') ?></button>
        </form>
    </div>
</div>

<?php if ($q === ''): ?>
    <div class="card"><div class="card-body text-center py-5 text-muted"><i class="fas fa-search fa-3x mb-3"></i><p class="h5"><?= te('search.hint') ?></p></div></div>
<?php else: ?>
<div class="row">
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-book mr-2"></i><?= t('search.section_bookings', ['count' => count($bookings)]) ?></span>
                <?php if ($bookings): ?><a href="<?= SITE_URL ?>/bookings.php?q=<?= urlencode($q) ?>" class="btn btn-sm btn-link"><?= te('common.view_all') ?></a><?php endif; ?>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($bookings)): ?><div class="list-group-item text-muted"><?= te('search.no_bookings') ?></div>
                <?php else: foreach ($bookings as $b): ?>
                <a href="<?= SITE_URL ?>/booking_view.php?id=<?= $b['id'] ?>" class="list-group-item list-group-item-action">
                    <div class="font-weight-bold"><?= e($b['booking_number']) ?></div>
                    <div class="text-muted small"><?= e($b['client_name']) ?> · <?= formatDate($b['date_from']) ?></div>
                    <div class="text-muted small"><i class="fas fa-map-marker-alt mr-1"></i><?= e($b['location']) ?></div>
                </a>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-users mr-2"></i><?= t('search.section_clients', ['count' => count($clients)]) ?></span>
                <?php if ($clients): ?><a href="<?= SITE_URL ?>/clients.php?q=<?= urlencode($q) ?>" class="btn btn-sm btn-link"><?= te('common.view_all') ?></a><?php endif; ?>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($clients)): ?><div class="list-group-item text-muted"><?= te('search.no_clients') ?></div>
                <?php else: foreach ($clients as $c): ?>
                <a href="<?= SITE_URL ?>/clients.php?edit=<?= $c['id'] ?>" onclick="event.preventDefault();window.location='<?= SITE_URL ?>/reports_client_statement.php?client_id=<?= $c['id'] ?>'" class="list-group-item list-group-item-action">
                    <div class="font-weight-bold"><?= e($c['name']) ?></div>
                    <div class="text-muted small"><i class="fas fa-phone mr-1"></i><?= e($c['phone']) ?><?= $c['alt_phone'] ? ' / '.e($c['alt_phone']) : '' ?></div>
                </a>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-boxes mr-2"></i><?= t('search.section_inventory', ['count' => count($items)]) ?></span>
                <?php if ($items): ?><a href="<?= SITE_URL ?>/item_types.php" class="btn btn-sm btn-link"><?= te('common.view_all') ?></a><?php endif; ?>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($items)): ?><div class="list-group-item text-muted"><?= te('search.no_items') ?></div>
                <?php else: foreach ($items as $it): ?>
                <a href="<?= SITE_URL ?>/item_types.php" class="list-group-item list-group-item-action">
                    <div class="font-weight-bold"><?= e($it['name']) ?></div>
                    <div class="text-muted small"><span class="badge badge-light mr-1"><?= e($it['cat_name']) ?></span><?= t('search.qty_units', ['qty' => (int)$it['quantity']]) ?></div>
                </a>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif;
include SITE_PATH . '/includes/footer.php';
?>
