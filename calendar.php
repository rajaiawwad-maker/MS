<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('view_calendar');
$page_title = t('title.calendar');
$active_nav = 'calendar';

$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
$view = $_GET['view'] ?? 'month';
$statusFilter = $_GET['status'] ?? '';

if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$firstDay = mktime(0,0,0,$month,1,$year);
$daysInMonth = date('t', $firstDay);
$firstDow = date('N', $firstDay);
if ($firstDow > 7) $firstDow = 7;
$monthStart = date('Y-m-01', $firstDay);
$monthEnd = date('Y-m-t', $firstDay);

$where = ["b.date_from <= ? AND b.date_to >= ?"]; $params = [$monthEnd, $monthStart];
if ($statusFilter !== '') { $where[] = "b.status = ?"; $params[] = $statusFilter; }

$stmt = $conn->prepare("SELECT b.*, c.name as client_name, c.phone as client_phone,
    (SELECT GROUP_CONCAT(CONCAT(bi.quantity, ' x ', it.name) SEPARATOR ', ')
        FROM booking_items bi INNER JOIN item_types it ON bi.item_type_id = it.id WHERE bi.booking_id = b.id) as items_summary
    FROM bookings b INNER JOIN clients c ON b.client_id = c.id
    WHERE " . implode(' AND ', $where) . " ORDER BY b.date_from, b.created_at");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$startTs = $firstDay - (($firstDow - 1) * 86400);
$endTs = $startTs + (42 * 86400) - 1;

$stmt = $conn->prepare("SELECT COUNT(*) as bk, COALESCE(SUM(CASE WHEN status != 'Canceled' THEN quoted_amount ELSE 0 END),0) as booked,
    COALESCE(SUM(dj_rak_amount),0) as rak FROM bookings WHERE date_from >= ? AND date_from <= ?");
$stmt->execute([$monthStart, $monthEnd]);
$mStats = $stmt->fetch();
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as coll FROM payments WHERE payment_date >= ? AND payment_date <= ?");
$stmt->execute([$monthStart, $monthEnd]);
$coll = (float)$stmt->fetchColumn();
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as exp FROM expenses WHERE date >= ? AND date <= ?");
$stmt->execute([$monthStart, $monthEnd]);
$exp = (float)$stmt->fetchColumn();

$prevMonth = $month - 1; $prevYear = $year;
$nextMonth = $month + 1; $nextYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

include SITE_PATH . '/includes/header.php';
echo flashMessages();
?>
<div class="row mb-3 align-items-end">
    <div class="col-md-5">
        <h1 class="page-title">
            <a href="?view=<?= e($view) ?>&month=<?= $prevMonth ?>&year=<?= $prevYear ?>&status=<?= e($statusFilter) ?>" class="btn btn-outline-secondary btn-sm mr-2"><i class="fas fa-chevron-left"></i></a>
            <?= t_month($month) . ' ' . $year ?>
            <a href="?view=<?= e($view) ?>&month=<?= $nextMonth ?>&year=<?= $nextYear ?>&status=<?= e($statusFilter) ?>" class="btn btn-outline-secondary btn-sm ml-2"><i class="fas fa-chevron-right"></i></a>
            <a href="?view=month&month=<?= date('n') ?>&year=<?= date('Y') ?>" class="btn btn-outline-info btn-sm ml-3"><?= te('cal.today') ?></a>
        </h1>
        <p class="page-subtitle"><?= te('title.calendar_sub_standalone') ?></p>
    </div>
    <div class="col-md-7 text-md-right">
        <div class="btn-group mr-2">
            <?php foreach (['month'=>'cal.view_month','week'=>'cal.view_week','day'=>'cal.view_day'] as $k=>$l): ?>
                <a href="?view=<?= $k ?>&month=<?= $month ?>&year=<?= $year ?>&status=<?= e($statusFilter) ?>" class="btn btn-outline-secondary <?= $view === $k ? 'active' : '' ?>"><?= te($l) ?></a>
            <?php endforeach; ?>
        </div>
        <form method="GET" class="form-inline d-inline">
            <input type="hidden" name="view" value="<?= e($view) ?>">
            <input type="hidden" name="month" value="<?= $month ?>">
            <input type="hidden" name="year" value="<?= $year ?>">
            <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value=""><?= te('c.all_statuses') ?></option>
                <?php foreach (['Draft','Quotation','Confirmed','Change Requested','Event Completed','Closed','Canceled'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= te(t_booking_status($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-2 mb-2"><div class="card kpi-card"><div class="card-body"><div class="kpi-label"><?= te('cal.kpi_bookings') ?></div><div class="kpi-value mt-1"><?= (int)$mStats['bk'] ?></div></div></div></div>
    <div class="col-md-2 mb-2"><div class="card kpi-card"><div class="card-body"><div class="kpi-label"><?= te('cal.kpi_quoted') ?></div><div class="kpi-value mt-1 text-primary"><?= number_format((float)$mStats['booked'],0) ?></div></div></div></div>
    <div class="col-md-2 mb-2"><div class="card kpi-card"><div class="card-body"><div class="kpi-label"><?= te('cal.kpi_collected') ?></div><div class="kpi-value mt-1 text-success"><?= number_format($coll,0) ?></div></div></div></div>
    <div class="col-md-2 mb-2"><div class="card kpi-card"><div class="card-body"><div class="kpi-label"><?= te('cal.kpi_expenses') ?></div><div class="kpi-value mt-1 text-danger"><?= number_format($exp,0) ?></div></div></div></div>
    <div class="col-md-2 mb-2"><div class="card kpi-card"><div class="card-body"><div class="kpi-label"><?= te('cal.kpi_net') ?></div><div class="kpi-value mt-1 <?= $coll - $exp >= 0 ? 'text-info' : 'text-danger' ?>"><?= number_format($coll - $exp, 0) ?></div></div></div></div>
    <div class="col-md-2 mb-2"><div class="card kpi-card"><div class="card-body"><div class="kpi-label"><?= te('cal.kpi_rak') ?></div><div class="kpi-value mt-1 text-warning"><?= number_format((float)$mStats['rak'],0) ?></div></div></div></div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <?php foreach ([
                'ce-draft'=>'cal.ce_draft','ce-quotation'=>'cal.ce_quotation','ce-confirmed'=>'cal.ce_confirmed','ce-change'=>'cal.ce_change',
                'ce-completed'=>'cal.ce_completed','ce-closed'=>'cal.ce_closed','ce-canceled'=>'cal.ce_canceled'] as $k=>$l): ?>
                <span class="calendar-event <?= $k ?> mr-2 d-inline-block px-2 py-1"><?= te($l) ?></span>
            <?php endforeach; ?>
        </div>
        <a href="<?= SITE_URL ?>/booking_form.php" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> <?= te('btn.new_booking') ?></a>
    </div>
    <div class="card-body p-2">
        <?php
        $dowNames = [1,2,3,4,5,6,7];
        $sClass = ['draft','quotation','confirmed','change','event_completed'=>'completed','closed','canceled'];
        ?>
        <div class="row no-gutters bg-light text-center py-2 small font-weight-semibold border-bottom">
            <?php foreach ($dowNames as $d): ?>
                <div class="col" style="max-width:calc(100%/7)"><?= te(t_day($d)) ?></div>
            <?php endforeach; ?>
        </div>
        <?php
        $today = date('Y-m-d');
        $ts = $startTs;
        $cell = 0;
        while ($cell < 42):
            if ($cell % 7 === 0) echo '<div class="row no-gutters">';
            $dateYmd = date('Y-m-d', $ts);
            $inMonth = (date('n', $ts) == $month && date('Y', $ts) == $year);
            $isToday = ($dateYmd === $today);
            $dayBookings = [];
            foreach ($bookings as $b) {
                if ($b['date_from'] <= $dateYmd && $b['date_to'] >= $dateYmd) $dayBookings[] = $b;
            }
            ?>
            <div class="col calendar-day <?= $inMonth ? '' : 'other-month' ?> <?= $isToday ? 'today' : '' ?>" style="max-width:calc(100%/7)">
                <div class="calendar-day-number d-flex justify-content-between">
                    <span><?= date('j', $ts) ?></span>
                    <?php if (count($dayBookings) > 0): ?>
                        <span class="badge badge-primary badge-pill small"><?= count($dayBookings) ?></span>
                    <?php endif; ?>
                </div>
                <?php foreach ($dayBookings as $b):
                    $css = str_replace(['Draft','Quotation','Confirmed','Change Requested','Event Completed','Closed','Canceled'],
                        ['ce-draft','ce-quotation','ce-confirmed','ce-change','ce-completed','ce-closed','ce-canceled'], $b['status']);
                    $isStart = ($b['date_from'] === $dateYmd);
                ?>
                    <div class="calendar-event <?= $css ?> <?= $isStart ? '' : 'rounded-left-0 ml-n2' ?>"
                        onclick="window.location='<?= SITE_URL ?>/booking_view.php?id=<?= $b['id'] ?>'"
                        title="<?= e($b['booking_number'].' | '.$b['client_name'].' | '.formatMoney($b['quoted_amount'])) ?>">
                        <strong><?= e($b['booking_number']) ?></strong> · <?= e(mb_strimwidth($b['client_name'], 0, 12, '..')) ?>
                        <div class="small opacity-90"><?= formatMoney($b['quoted_amount']) ?> · <?= e($b['location']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php
            $ts += 86400; $cell++;
            if ($cell % 7 === 0) echo '</div>';
        endwhile;
        ?>
    </div>
</div>
<?php include SITE_PATH . '/includes/footer.php'; ?>
