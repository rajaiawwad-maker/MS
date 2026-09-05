<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('view_audit_logs');

$page_title = t('title.audit_log');
$active_nav = 'audit';

$search = trim($_GET['q'] ?? '');
$action = $_GET['action'] ?? '';
$entity = $_GET['entity'] ?? '';
$userId = (int)($_GET['user_id'] ?? 0);
$df = $_GET['date_from'] ?? '';
$dt = $_GET['date_to'] ?? '';
$view = $_GET['view'] ?? ($_SESSION['audit_view'] ?? 'table');
$view = in_array($view, ['table','timeline']) ? $view : 'table';
$_SESSION['audit_view'] = $view;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = ['1=1']; $params = [];
if ($search !== '') {
    $where[] = "(a.action LIKE ? OR a.entity_type LIKE ? OR u.name LIKE ? OR a.details LIKE ? OR a.new_value LIKE ? OR a.old_value LIKE ?)";
    $s = "%$search%"; array_push($params, $s, $s, $s, $s, $s, $s);
}
if ($action !== '') { $where[] = "a.action = ?"; $params[] = $action; }
if ($entity !== '') { $where[] = "a.entity_type = ?"; $params[] = $entity; }
if ($userId > 0) { $where[] = "a.user_id = ?"; $params[] = $userId; }
if ($df !== '') { $d = DateTime::createFromFormat('d/m/Y', $df); if ($d) { $where[] = "DATE(a.created_at) >= ?"; $params[] = $d->format('Y-m-d'); } }
if ($dt !== '') { $d = DateTime::createFromFormat('d/m/Y', $dt); if ($d) { $where[] = "DATE(a.created_at) <= ?"; $params[] = $d->format('Y-m-d'); } }

$countSql = "SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE " . implode(' AND ', $where);
$cntStmt = $conn->prepare($countSql); $cntStmt->execute($params); $totalAll = (int)$cntStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalAll / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

$sql = "SELECT a.*, u.name as user_name, r.name as user_role FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id LEFT JOIN roles r ON u.role_id = r.id
    WHERE " . implode(' AND ', $where) . " ORDER BY a.id DESC LIMIT $offset, $perPage";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$users = $conn->query("SELECT id, name FROM users ORDER BY name")->fetchAll();

$knownActions = [
    'create' => ['icon'=>'plus','color'=>'success'],
    'update' => ['icon'=>'pencil-alt','color'=>'primary'],
    'delete' => ['icon'=>'trash','color'=>'danger'],
    'login'  => ['icon'=>'sign-in-alt','color'=>'info'],
    'logout' => ['icon'=>'sign-out-alt','color'=>'secondary'],
    'cancel' => ['icon'=>'ban','color'=>'warning'],
    'deactivate' => ['icon'=>'pause-circle','color'=>'warning'],
    'retire' => ['icon'=>'snowflake','color'=>'secondary'],
    'customer_confirm' => ['icon'=>'check-circle','color'=>'success'],
    'customer_change'  => ['icon'=>'exchange-alt','color'=>'secondary'],
    'customer_decline' => ['icon'=>'times-circle','color'=>'danger'],
];
$knownEntities = [
    'booking'        => ['icon'=>'calendar','color'=>'indigo'],
    'client'         => ['icon'=>'user-friends','color'=>'teal'],
    'user'           => ['icon'=>'user-shield','color'=>'blue'],
    'category'       => ['icon'=>'tags','color'=>'orange'],
    'item_type'      => ['icon'=>'box-open','color'=>'pink'],
    'inventory_item' => ['icon'=>'box','color'=>'cyan'],
    'expense'        => ['icon'=>'money-bill-wave','color'=>'red'],
    'expense_type'   => ['icon'=>'wallet','color'=>'rose'],
    'setting'        => ['icon'=>'cog','color'=>'gray'],
    'payment'        => ['icon'=>'credit-card','color'=>'green'],
    'audit_log'      => ['icon'=>'clipboard-list','color'=>'slate'],
];
$knownColors = [
    'indigo'=>'#6610f2','teal'=>'#20c997','blue'=>'#007bff','orange'=>'#fd7e14',
    'pink'=>'#e83e8c','cyan'=>'#17a2b8','red'=>'#dc3545','rose'=>'#f06292',
    'gray'=>'#6c757d','green'=>'#28a745','slate'=>'#495057','purple'=>'#6f42c1',
];

function t_audit_action($a) {
    $m = [
        'create'=>'audit.a_create','update'=>'audit.a_update','delete'=>'audit.a_delete','login'=>'audit.a_login',
        'logout'=>'audit.a_logout','deactivate'=>'audit.a_deactivate','cancel'=>'audit.a_cancel',
        'retire'=>'audit.a_retire','customer_confirm'=>'audit.a_customer_confirm','customer_change'=>'audit.a_customer_change',
        'customer_decline'=>'audit.a_customer_decline',
    ];
    return isset($m[$a]) ? t($m[$a]) : ucwords(str_replace('_', ' ', $a));
}
function t_entity($e) {
    $k = "ent." . $e;
    $x = t($k);
    return $x === $k ? ucwords(str_replace('_',' ',$e)) : $x;
}
function relativeTime($ts) {
    $now = time(); $diff = max(1, $now - $ts);
    if ($diff < 60) return t('audit.just_now');
    if ($diff < 3600) { $n = (int)floor($diff/60); return str_replace('{n}', $n, t($n===1?'audit.min_ago':'audit.mins_ago')); }
    if ($diff < 86400) { $n = (int)floor($diff/3600); return str_replace('{n}', $n, t($n===1?'audit.hour_ago':'audit.hours_ago')); }
    $n = (int)floor($diff/86400); return str_replace('{n}', $n, t($n===1?'audit.day_ago':'audit.days_ago'));
}
function targetLink($entityType, $entityId, $newArr) {
    $label = ''; $url = null;
    if ($entityType === 'booking') {
        $bn = $newArr['booking_number'] ?? null;
        $label = $bn ? "#$bn" : ("#" . (int)$entityId);
        $url = SITE_URL . '/booking_view.php?id=' . (int)$entityId;
    } elseif ($entityType === 'client') {
        $cn = $newArr['name'] ?? null;
        $label = $cn ? $cn : ("#" . (int)$entityId);
        $url = SITE_URL . '/clients.php';
    } elseif ($entityType === 'user') {
        $un = $newArr['name'] ?? $newArr['username'] ?? null;
        $label = $un ? $un : ("#" . (int)$entityId);
        $url = SITE_URL . '/users.php';
    } elseif ($entityType === 'category') {
        $label = $newArr['name'] ?? ("#" . (int)$entityId);
        $url = SITE_URL . '/categories.php';
    } elseif ($entityType === 'item_type') {
        $label = $newArr['name'] ?? ("#" . (int)$entityId);
        $url = SITE_URL . '/item_types.php';
    } elseif ($entityType === 'inventory_item') {
        $label = ($newArr['serial_number'] ?? $newArr['barcode'] ?? null) ?: ("#" . (int)$entityId);
        $url = SITE_URL . '/inventory.php';
    } elseif ($entityType === 'expense') {
        $label = t('ent.expense') . " #" . (int)$entityId;
        $url = SITE_URL . '/expenses.php';
    } elseif ($entityType === 'expense_type') {
        $label = $newArr['name'] ?? ("#" . (int)$entityId);
        $url = SITE_URL . '/expense_types.php';
    } elseif ($entityType === 'payment') {
        $am = $newArr['amount'] ?? null; $label = $am ? formatMoney($am) : ("#" . (int)$entityId);
        $bid = $newArr['booking_id'] ?? null; if ($bid) $url = SITE_URL . '/booking_view.php?id=' . (int)$bid;
    } elseif ($entityType === 'setting') {
        $label = $newArr['setting_key'] ?? ("#" . (int)$entityId);
    }
    if (!$label) $label = t('audit.no_target') . " #" . (int)$entityId;
    return ['label'=>$label, 'url'=>$url];
}
function renderSentence($r, $userName, $entityName, $targetLabel, $actionLabel) {
    $action = $r['action'];
    $u = '<b class="text-dark">' . e($userName ?: t('audit.unknown')) . '</b>';
    $e = '<span class="font-weight-semibold">' . e($entityName) . '</span>';
    $t = '<span class="text-body">' . e($targetLabel) . '</span>';
    if ($action === 'login') $tpl = t('audit.sentence_login');
    elseif ($action === 'logout') $tpl = t('audit.sentence_logout');
    elseif ($action === 'create') $tpl = t('audit.sentence_create');
    elseif ($action === 'update') $tpl = t('audit.sentence_update');
    elseif ($action === 'delete') $tpl = t('audit.sentence_delete');
    else $tpl = t('audit.sentence_default');
    $a = '<span class="font-weight-semibold">' . e($actionLabel) . '</span>';
    return strtr($tpl, ['{user}'=>$u, '{entity}'=>$e, '{target}'=>$t, '{action}'=>$a]);
}
function computeDiff($old, $new) {
    $diff = []; $allKeys = array_unique(array_merge(is_array($old)?array_keys($old):[], is_array($new)?array_keys($new):[]));
    $skipKeys = ['password','password_hash','pin'];
    foreach ($allKeys as $k) {
        if (in_array(strtolower($k), $skipKeys, true)) continue;
        $o = is_array($old) && array_key_exists($k, $old) ? $old[$k] : null;
        $n = is_array($new) && array_key_exists($k, $new) ? $new[$k] : null;
        $os = is_array($o) ? json_encode($o, JSON_UNESCAPED_UNICODE) : (is_null($o) ? null : (string)$o);
        $ns = is_array($n) ? json_encode($n, JSON_UNESCAPED_UNICODE) : (is_null($n) ? null : (string)$n);
        if ((string)$os !== (string)$ns) $diff[$k] = ['old'=>$os, 'new'=>$ns];
    }
    return $diff;
}
function initialsBadge($name) {
    $n = trim((string)$name); if ($n === '') $n = '?';
    $parts = preg_split('/\s+/', $n, 2);
    $ini = mb_strtoupper(mb_substr($parts[0], 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));
    $colors = ['#007bff','#28a745','#ffc107','#17a2b8','#6f42c1','#e83e8c','#fd7e14','#20c997'];
    $c = $colors[abs(crc32($n)) % count($colors)];
    return $ini . '|' . $c;
}

// ====== KPI COMPUTATIONS ======
$totalRows = count($rows);
$uniqueUsers = []; $actionCounts = []; $latest = null;
$kpiSql = "SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE " . implode(' AND ', $where);
$kpiStmt = $conn->prepare($kpiSql); $kpiStmt->execute($params); $kpiAllCount = (int)$kpiStmt->fetchColumn();
$kpiRowsSql = "SELECT a.user_id, a.action, a.created_at, u.name as user_name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE " . implode(' AND ', $where) . " ORDER BY a.id DESC LIMIT 500";
$kpiStmt2 = $conn->prepare($kpiRowsSql); $kpiStmt2->execute($params);
$kpiRows = $kpiStmt2->fetchAll();
foreach ($kpiRows as $r) {
    if (!empty($r['user_name'])) $uniqueUsers[$r['user_id'] ?: $r['user_name']] = true;
    $actionCounts[$r['action']] = ($actionCounts[$r['action']] ?? 0) + 1;
    if ($latest === null || $r['created_at'] > $latest) $latest = $r['created_at'];
}
arsort($actionCounts);
$topAction = $actionCounts ? array_key_first($actionCounts) : null;
$topActionCount = $topAction ? $actionCounts[$topAction] : 0;
$uniqueUserCount = count($uniqueUsers);
unset($kpiRows, $kpiStmt, $kpiStmt2);

// ====== CSV EXPORT (before header, w/ UTF-8 BOM) ======
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $allParams = $params;
    $allSql = "SELECT a.*, u.name as user_name, r.name as user_role FROM audit_logs a 
        LEFT JOIN users u ON a.user_id = u.id LEFT JOIN roles r ON u.role_id = r.id
        WHERE " . implode(' AND ', $where) . " ORDER BY a.id DESC";
    $allStmt = $conn->prepare($allSql); $allStmt->execute($allParams);
    $allRows = $allStmt->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="audit_log_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    $headers = [
        t('audit.csv_id'),
        t('audit.csv_timestamp'),
        t('audit.csv_relative'),
        t('audit.csv_user'),
        t('audit.csv_role'),
        t('audit.csv_action'),
        t('audit.csv_entity'),
        t('audit.csv_entity_id'),
        t('audit.csv_target'),
        t('audit.csv_summary'),
        t('audit.csv_changed_fields'),
        t('audit.csv_old_value'),
        t('audit.csv_new_value'),
        t('audit.csv_ip'),
        t('audit.csv_session'),
    ];
    fputcsv($out, $headers);

    foreach ($allRows as $r) {
        $old = $r['old_value'] ? @json_decode($r['old_value'], true) : null;
        $new = $r['new_value'] ? @json_decode($r['new_value'], true) : null;
        $newForTarget = is_array($new) ? $new : (is_array($old) ? $old : []);
        $target = targetLink($r['entity_type'], $r['entity_id'], $newForTarget);
        $diff = $r['action'] === 'update' ? computeDiff($old, $new) : [];
        $changedFields = !empty($diff) ? implode(', ', array_keys($diff)) : '';
        $ts = @strtotime($r['created_at']) ?: time();
        $summary = trim((string)($r['details'] ?? ''));
        if ($summary === '') {
            $aLabel = t_audit_action($r['action']);
            $eLabel = t_entity($r['entity_type']);
            $uLabel = $r['user_name'] ?? t('audit.unknown');
            $summary = $uLabel . ' - ' . $aLabel . ' ' . $eLabel . ' ' . $target['label'];
        }

        fputcsv($out, [
            (int)$r['id'],
            $r['created_at'],
            relativeTime($ts),
            $r['user_name'] ?? '',
            $r['user_role'] ?? '',
            t_audit_action($r['action']),
            t_entity($r['entity_type']),
            (int)$r['entity_id'],
            $target['label'],
            $summary,
            $changedFields,
            $r['old_value'] ?? '',
            $r['new_value'] ?? '',
            $r['ip_address'] ?? '',
            $r['session_id'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

include SITE_PATH . '/includes/header.php';
echo flashMessages();

$today = (new DateTime('now'))->format('Y-m-d');
$yesterday = (new DateTime('-1 day'))->format('Y-m-d');

$activeChips = [];
if ($search !== '') $activeChips[] = ['label' => t('audit.search') . ': ' . $search, 'param'=>'q'];
if ($action !== '') $activeChips[] = ['label' => t_audit_action($action), 'param'=>'action'];
if ($entity !== '') $activeChips[] = ['label' => t_entity($entity), 'param'=>'entity'];
if ($userId > 0) { foreach ($users as $u) if ($u['id']===$userId) $activeChips[] = ['label'=>$u['name'],'param'=>'user_id']; }
if ($df !== '') $activeChips[] = ['label'=>t('field.from').': '.$df,'param'=>'date_from'];
if ($dt !== '') $activeChips[] = ['label'=>t('field.to').': '.$dt,'param'=>'date_to'];

function buildClearFilterUrl($paramToClear) {
    $params = $_GET;
    unset($params[$paramToClear]);
    unset($params['page']);
    return SITE_URL . '/audit_logs.php' . ($params ? ('?' . http_build_query($params)) : '');
}
function quickFilterUrl($overrides) {
    $params = array_merge($_GET, $overrides);
    unset($params['page']);
    return SITE_URL . '/audit_logs.php?' . http_build_query($params);
}
function viewSwitchUrl($targetView) {
    $params = $_GET;
    $params['view'] = $targetView;
    unset($params['page']);
    return SITE_URL . '/audit_logs.php?' . http_build_query($params);
}
function paginateUrl($targetPage) {
    $params = $_GET;
    $params['page'] = $targetPage;
    return SITE_URL . '/audit_logs.php?' . http_build_query($params);
}
function paginationHtml($current, $total) {
    if ($total <= 1) return '';
    $html = '<nav aria-label="Pagination"><ul class="pagination pagination-sm mb-0">';
    $prev = max(1, $current - 1);
    $disabled = $current == 1 ? ' disabled' : '';
    $html .= '<li class="page-item'.$disabled.'"><a class="page-link" href="'.paginateUrl($prev).'"><i class="fas fa-chevron-left"></i></a></li>';
    $start = max(1, $current - 2);
    $end = min($total, $start + 4);
    if ($end - $start < 4) $start = max(1, $end - 4);
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="'.paginateUrl(1).'">1</a></li>';
        if ($start > 2) $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
    }
    for ($i = $start; $i <= $end; $i++) {
        $active = $i == $current ? ' active' : '';
        $html .= '<li class="page-item'.$active.'"><a class="page-link" href="'.paginateUrl($i).'">'.$i.'</a></li>';
    }
    if ($end < $total) {
        if ($end < $total - 1) $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        $html .= '<li class="page-item"><a class="page-link" href="'.paginateUrl($total).'">'.$total.'</a></li>';
    }
    $next = min($total, $current + 1);
    $disabled2 = $current == $total ? ' disabled' : '';
    $html .= '<li class="page-item'.$disabled2.'"><a class="page-link" href="'.paginateUrl($next).'"><i class="fas fa-chevron-right"></i></a></li>';
    $html .= '</ul></nav>';
    return $html;
}
?>
<div class="row mb-3 align-items-end">
    <div class="col-md-8">
        <h1 class="page-title mb-1"><i class="fas fa-clipboard-list text-indigo mr-2"></i><?= te('title.audit_log') ?></h1>
        <p class="page-subtitle mb-0"><?= te('audit.subtitle') ?></p>
    </div>
    <div class="col-md-4 text-right">
        <div class="btn-group">
            <a href="<?= viewSwitchUrl('table') ?>" class="btn btn-outline-secondary <?= $view==='table'?'active':'' ?>" title="<?= te('audit.view_table') ?>">
                <i class="fas fa-table mr-1"></i><?= te('audit.view_table') ?>
            </a>
            <a href="<?= viewSwitchUrl('timeline') ?>" class="btn btn-outline-secondary <?= $view==='timeline'?'active':'' ?>" title="<?= te('audit.view_timeline') ?>">
                <i class="fas fa-stream mr-1"></i><?= te('audit.view_timeline') ?>
            </a>
        </div>
        <a href="<?= SITE_URL ?>/audit_logs.php?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>" class="btn btn-outline-success ml-1">
            <i class="fas fa-file-csv mr-1"></i><?= te('audit.export_csv') ?>
        </a>
    </div>
</div>

<!-- KPI ROW -->
<div class="row mb-3">
    <div class="col-sm-6 col-xl-3 mb-2">
        <div class="card kpi-card h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center">
                    <div class="kpi-icon mr-3 rounded" style="background:linear-gradient(135deg,#6610f2,#7041d6);color:#fff;padding:10px 12px;">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div>
                        <div class="small text-muted"><?= te('audit.kpi_total') ?></div>
                        <div class="h4 mb-0"><?= number_format($kpiAllCount) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3 mb-2">
        <div class="card kpi-card h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center">
                    <div class="kpi-icon mr-3 rounded" style="background:linear-gradient(135deg,#007bff,#3fa4ff);color:#fff;padding:10px 12px;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="small text-muted"><?= te('audit.kpi_users') ?></div>
                        <div class="h4 mb-0"><?= number_format($uniqueUserCount) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3 mb-2">
        <div class="card kpi-card h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center">
                    <div class="kpi-icon mr-3 rounded" style="background:linear-gradient(135deg,#20c997,#5ee7c0);color:#fff;padding:10px 12px;">
                        <i class="fas fa-fire"></i>
                    </div>
                    <div>
                        <div class="small text-muted"><?= te('audit.kpi_top_action') ?></div>
                        <div class="h5 mb-0">
                            <?php if ($topAction): ?>
                                <?php $aInfo = $knownActions[$topAction] ?? ['color'=>'secondary','icon'=>'question-circle']; ?>
                                <span class="badge badge-<?= $aInfo['color'] ?> badge-pill py-1 px-2">
                                    <i class="fas fa-<?= $aInfo['icon'] ?> mr-1"></i><?= te(t_audit_action($topAction)) ?>
                                </span>
                                <small class="text-muted ml-1">(<?= $topActionCount ?>)</small>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3 mb-2">
        <div class="card kpi-card h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center">
                    <div class="kpi-icon mr-3 rounded" style="background:linear-gradient(135deg,#fd7e14,#ffb36b);color:#fff;padding:10px 12px;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div class="small text-muted"><?= te('audit.kpi_latest') ?></div>
                        <div class="h6 mb-0 font-weight-semibold">
                            <?= $latest ? relativeTime(strtotime($latest)) : '—' ?>
                        </div>
                        <div class="small text-muted"><?= $latest ? formatDateTime($latest) : '' ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ACTIVE FILTERS CHIPS -->
<?php if (!empty($activeChips)): ?>
<div class="mb-3">
    <div class="d-flex align-items-center flex-wrap">
        <span class="small text-muted mr-2 font-weight-semibold"><?= te('audit.filters_active') ?>:</span>
        <?php foreach ($activeChips as $chip): ?>
            <a href="<?= buildClearFilterUrl($chip['param']) ?>"
               class="badge badge-outline-primary badge-pill mr-1 mb-1 px-2 py-1 text-decoration-none"
               title="<?= te('audit.clear_filter') ?>">
                <?= e($chip['label']) ?> <span class="ml-1">&times;</span>
            </a>
        <?php endforeach; ?>
        <a href="<?= SITE_URL ?>/audit_logs.php" class="badge badge-outline-secondary badge-pill px-2 py-1 text-decoration-none mb-1">
            <i class="fas fa-times-circle mr-1"></i><?= te('common.reset') ?>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- FILTER CARD -->
<form method="GET" class="card mb-3">
<div class="card-header bg-white border-bottom py-2">
    <div class="d-flex align-items-center justify-content-between flex-wrap">
        <h6 class="mb-0 font-weight-semibold"><i class="fas fa-filter mr-1 text-muted"></i><?= te('common.filters') ?></h6>
        <span class="small text-muted"><?= te('audit.quick') ?>:
            <?php
                $today2 = (new DateTime('now')); $yd = (new DateTime('-1 day')); $svda = (new DateTime('-6 day'));
                $todayStr = $today2->format('d/m/Y'); $ydStr = $yd->format('d/m/Y'); $svdaStr = $svda->format('d/m/Y');
            ?>
            <a href="<?= quickFilterUrl(['date_from'=>$todayStr,'date_to'=>$todayStr,'q'=>'','action'=>'','entity'=>'','user_id'=>'']) ?>" class="badge badge-light mr-1"><?= te('audit.q_today') ?></a>
            <a href="<?= quickFilterUrl(['date_from'=>$ydStr,'date_to'=>$ydStr,'q'=>'','action'=>'','entity'=>'','user_id'=>'']) ?>" class="badge badge-light mr-1"><?= te('audit.q_yesterday') ?></a>
            <a href="<?= quickFilterUrl(['date_from'=>$svdaStr,'date_to'=>$todayStr,'q'=>'','action'=>'','entity'=>'','user_id'=>'']) ?>" class="badge badge-light mr-1"><?= te('audit.q_7days') ?></a>
            <a href="<?= quickFilterUrl(['action'=>'create','q'=>'','entity'=>'','date_from'=>'','date_to'=>'','user_id'=>'']) ?>" class="badge badge-light mr-1"><?= te('audit.q_creates') ?></a>
            <a href="<?= quickFilterUrl(['action'=>'update','q'=>'','entity'=>'','date_from'=>'','date_to'=>'','user_id'=>'']) ?>" class="badge badge-light mr-1"><?= te('audit.q_updates') ?></a>
            <a href="<?= quickFilterUrl(['action'=>'delete','q'=>'','entity'=>'','date_from'=>'','date_to'=>'','user_id'=>'']) ?>" class="badge badge-light mr-1"><?= te('audit.q_deletes') ?></a>
            <a href="<?= quickFilterUrl(['action'=>'login','q'=>'','entity'=>'','date_from'=>'','date_to'=>'','user_id'=>'']) ?>" class="badge badge-light"><?= te('audit.q_logins') ?></a>
        </span>
    </div>
</div>
<div class="card-body pb-1">
<div class="row align-items-end">
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('audit.search') ?></label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
            <input name="q" class="form-control" value="<?= e($search) ?>" placeholder="<?= te('audit.search_placeholder') ?>">
        </div>
    </div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('audit.user') ?></label>
        <select name="user_id" class="form-control select2"><option value=""><?= te('common.all') ?></option>
            <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= $userId === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('audit.action') ?></label>
        <select name="action" class="form-control select2"><option value=""><?= te('common.all') ?></option>
            <?php foreach ($knownActions as $aKey => $aInfo): ?>
                <option value="<?= e($aKey) ?>" <?= $action === $aKey ? 'selected' : '' ?>>
                    <?= te(t_audit_action($aKey)) ?>
                </option>
            <?php endforeach; ?>
        </select></div>
    <div class="col-md-2 mb-2"><label class="small font-weight-semibold"><?= te('audit.entity') ?></label>
        <select name="entity" class="form-control select2"><option value=""><?= te('common.all') ?></option>
            <?php
                $entStmt = $conn->query("SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type");
                foreach ($entStmt->fetchAll() as $a) { $raw = $a['entity_type']; $friendly = t_entity($raw);
                    echo '<option value="'.e($raw).'"'.($entity===$raw?' selected':'').'>'.e($friendly).($friendly!==ucwords(str_replace('_',' ',$raw))? ' ('.e($raw).')' :'').'</option>';
                }
            ?>
        </select></div>
    <div class="col-md-1 mb-2"><label class="small font-weight-semibold"><?= te('field.from') ?></label><input name="date_from" class="form-control datepicker" value="<?= e($df) ?>" autocomplete="off"></div>
    <div class="col-md-1 mb-2"><label class="small font-weight-semibold"><?= te('field.to') ?></label><input name="date_to" class="form-control datepicker" value="<?= e($dt) ?>" autocomplete="off"></div>
    <div class="col-md-2 mb-2"><div class="btn-group btn-block">
        <button class="btn btn-primary" style="width:50%"><i class="fas fa-filter"></i> <?= te('common.filter') ?></button>
        <a href="<?= SITE_URL ?>/audit_logs.php" class="btn btn-outline-secondary" style="width:50%"><i class="fas fa-redo"></i><?= te('common.reset') ?></a>
    </div></div>
</div>
</div>
</form>

<?php if ($view === 'table'): ?>
<!-- TABLE VIEW -->
<div class="card">
<div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center flex-wrap">
    <h6 class="mb-0 font-weight-semibold">
        <i class="fas fa-table mr-1 text-indigo"></i><?= te('title.audit_log') ?>
        <small class="text-muted ml-2">
            <?= strtr(t('audit.showing'), ['{first}'=>$totalAll===0?0:($offset+1), '{last}'=>min($offset+$perPage,$totalAll), '{total}'=>number_format($totalAll)]) ?>
        </small>
    </h6>
    <div class="d-flex align-items-center">
        <div class="btn-group btn-group-sm mr-2">
            <button type="button" class="btn btn-outline-secondary audit-expand-all"><i class="fas fa-plus-square mr-1"></i><?= te('audit.expand_all') ?></button>
            <button type="button" class="btn btn-outline-secondary audit-collapse-all"><i class="fas fa-minus-square mr-1"></i><?= te('audit.collapse_all') ?></button>
        </div>
        <?= paginationHtml($page, $totalPages) ?>
        <a href="<?= SITE_URL ?>/audit_logs.php?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>" class="btn btn-sm btn-outline-success ml-2">
            <i class="fas fa-file-csv mr-1"></i><?= te('audit.export_csv') ?>
        </a>
    </div>
</div>
<div class="card-body p-0">
<?php if (empty($rows)): ?>
    <div class="text-center py-7">
        <div style="font-size:4rem" class="mb-2 text-muted"><i class="far fa-clipboard"></i></div>
        <h5 class="text-muted mb-1"><?= te('audit.no_records') ?></h5>
        <p class="text-muted small mb-0"><i class="fas fa-filter mr-1"></i><?= te('common.reset') ?>
            <a href="<?= SITE_URL ?>/audit_logs.php" class="ml-1 text-decoration-none"><?= te('audit.q_creates') ?>?</a>
        </p>
    </div>
<?php else: ?>
<div class="table-responsive audit-table-wrap" style="max-height:70vh;overflow:auto;">
<table class="table table-sm table-hover mb-0 audit-table sticky-first-col">
    <thead class="thead-light sticky-top">
    <tr>
        <th style="width:50px" class="text-center"><?= te('audit.th_id') ?></th>
        <th style="width:160px"><?= te('audit.th_time') ?></th>
        <th style="width:160px"><?= te('audit.th_user') ?></th>
        <th style="width:110px"><?= te('audit.th_action') ?></th>
        <th style="width:130px"><?= te('audit.th_entity') ?></th>
        <th style="width:150px"><?= te('audit.th_target') ?></th>
        <th><?= te('audit.th_summary') ?></th>
        <th style="width:110px" class="text-center"><?= te('audit.th_ip') ?></th>
        <th style="width:70px" class="text-center"><?= te('audit.th_actions') ?></th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
        $aInfo = $knownActions[$r['action']] ?? ['icon'=>'question-circle','color'=>'secondary'];
        $eInfo = $knownEntities[$r['entity_type']] ?? ['icon'=>'file','color'=>'gray'];
        $eColor = isset($knownColors[$eInfo['color']]) ? $knownColors[$eInfo['color']] : '#6c757d';
        $aColor = $aInfo['color'];
        $old = $r['old_value'] ? @json_decode($r['old_value'], true) : null;
        $new = $r['new_value'] ? @json_decode($r['new_value'], true) : null;
        $newForTarget = is_array($new) ? $new : (is_array($old) ? $old : []);
        $target = targetLink($r['entity_type'], $r['entity_id'], $newForTarget);
        $targetHtml = $target['url'] !== null
            ? '<a class="text-indigo font-weight-semibold" href="'.e($target['url']).'" target="_blank" title="'.te('audit.view_record').'">'.e($target['label']).' <i class="fas fa-external-link-alt ml-1" style="font-size:.7em"></i></a>'
            : '<span class="font-weight-semibold">'.e($target['label']).'</span>';
        $diff = $r['action'] === 'update' ? computeDiff($old, $new) : [];
        $dumpArr = ($r['action'] !== 'update') ? (is_array($new) ? $new : (is_array($old) ? $old : [])) : [];
        $hasExpand = (!empty($r['details'] ?? '')) || (!empty($diff)) || (!empty($dumpArr));
        $ts = @strtotime($r['created_at']) ?: time();
        $userBadge = initialsBadge($r['user_name'] ?? '?');
        list($ini, $iniColor) = explode('|', $userBadge);
        $summary = trim((string)($r['details'] ?? ''));
        if ($summary === '') {
            $summary = strip_tags(renderSentence($r, $r['user_name'] ?? t('audit.unknown'), t_entity($r['entity_type']), $target['label'], t_audit_action($r['action'])));
        }
    ?>
    <tr class="audit-row align-middle" data-id="<?= (int)$r['id'] ?>">
        <td class="text-center text-muted font-weight-mono small">#<?= (int)$r['id'] ?></td>
        <td>
            <div class="d-flex flex-column">
                <span class="font-weight-semibold small"><?= date('d/m/Y H:i:s', $ts) ?></span>
                <span class="text-muted small" title="<?= e(formatDateTime($r['created_at'])) ?>"><i class="far fa-clock mr-1"></i><?= relativeTime($ts) ?></span>
            </div>
        </td>
        <td>
            <div class="d-flex align-items-center">
                <div class="rounded-circle d-flex align-items-center justify-content-center text-white font-weight-bold mr-2" style="width:30px;height:30px;background:<?= $iniColor ?>;font-size:11px;flex-shrink:0;">
                    <?= e($ini) ?>
                </div>
                <div class="min-w-0">
                    <div class="font-weight-semibold small text-truncate" style="max-width:120px" title="<?= e($r['user_name'] ?? t('audit.unknown')) ?>">
                        <?= e($r['user_name'] ?? t('audit.unknown')) ?>
                    </div>
                    <?php if (!empty($r['user_role'])): ?>
                        <span class="badge badge-light" style="font-size:.7em"><?= e(ucfirst($r['user_role'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </td>
        <td>
            <span class="badge badge-<?= $aColor ?> badge-pill px-2 py-1 d-inline-flex align-items-center">
                <i class="fas fa-<?= $aInfo['icon'] ?> mr-1" style="font-size:.8em"></i>
                <span class="small"><?= te(t_audit_action($r['action'])) ?></span>
            </span>
        </td>
        <td>
            <span class="d-inline-flex align-items-center small">
                <i class="fas fa-<?= $eInfo['icon'] ?> mr-1" style="color:<?= $eColor ?>"></i>
                <span class="font-weight-semibold" style="color:<?= $eColor ?>"><?= te(t_entity($r['entity_type'])) ?></span>
            </span>
            <div class="text-muted small mt-1">ID: <?= (int)$r['entity_id'] ?></div>
        </td>
        <td class="small"><?= $targetHtml ?></td>
        <td class="small">
            <div class="text-truncate-wrap" style="max-width:320px" title="<?= e($summary) ?>">
                <?= e(mb_strlen($summary) > 140 ? mb_substr($summary, 0, 140) . '…' : $summary) ?>
            </div>
        </td>
        <td class="text-center">
            <?php if (!empty($r['ip_address'])): ?>
                <button type="button" class="btn btn-xs btn-outline-secondary copy-ip font-weight-mono" data-ip="<?= e($r['ip_address']) ?>" title="<?= te('audit.copy_ip') ?>">
                    <i class="far fa-copy mr-1"></i><?= e($r['ip_address']) ?>
                </button>
            <?php else: ?>
                <span class="text-muted small">—</span>
            <?php endif; ?>
        </td>
        <td class="text-center">
            <div class="d-flex justify-content-center">
                <?php if ($target['url'] !== null): ?>
                    <a href="<?= e($target['url']) ?>" target="_blank" class="btn btn-xs btn-outline-secondary mr-1" title="<?= te('audit.view_record') ?>">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                <?php endif; ?>
                <?php if ($hasExpand): ?>
                    <button type="button" class="btn btn-xs btn-outline-secondary audit-toggle-row" data-target="rowdiff-<?= (int)$r['id'] ?>" title="<?= te('audit.expand_diff') ?>">
                        <i class="fas fa-chevron-down audit-row-chevron"></i>
                    </button>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    <?php if ($hasExpand): ?>
    <tr id="rowdiff-<?= (int)$r['id'] ?>" class="audit-row-detail d-none">
        <td colspan="9" class="p-0 border-0">
            <div class="bg-light p-3 m-0 border-left-0 border-right-0" style="box-shadow:inset 0 4px 10px -6px rgba(0,0,0,.1), inset 0 -4px 10px -6px rgba(0,0,0,.1);">
                <?php if (!empty($r['details'] ?? '')): ?>
                    <div class="mb-2 p-2 bg-white border rounded" style="font-size:.85rem">
                        <i class="fas fa-info-circle mr-1 text-info"></i><b><?= te('audit.th_summary') ?>:</b>
                        <?= e($r['details'] ?? '') ?>
                    </div>
                <?php endif; ?>
                <?php if ($r['action'] === 'update' && !empty($diff)): ?>
                    <h6 class="small font-weight-semibold mb-2"><i class="fas fa-exchange-alt mr-1 text-primary"></i><?= te('audit.before_vs_after') ?></h6>
                    <div class="table-responsive border rounded bg-white">
                        <table class="table table-sm mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width:28%"><?= te('audit.field') ?></th>
                                    <th style="width:36%"><?= te('audit.before') ?></th>
                                    <th style="width:36%"><?= te('audit.after') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($diff as $dk => $dv): ?>
                                    <tr>
                                        <td class="font-weight-mono font-weight-semibold small"><?= e($dk) ?></td>
                                        <td class="bg-danger-light">
                                            <?php if ($dv['old'] === null): ?><span class="text-muted"><i>—</i></span><?php else: ?><samp class="small"><?= e(mb_strlen($dv['old']) > 300 ? mb_substr($dv['old'],0,300).'…' : $dv['old']) ?></samp><?php endif; ?>
                                        </td>
                                        <td class="bg-success-light">
                                            <?php if ($dv['new'] === null): ?><span class="text-muted"><i>—</i></span><?php else: ?><samp class="small"><?= e(mb_strlen($dv['new']) > 300 ? mb_substr($dv['new'],0,300).'…' : $dv['new']) ?></samp><?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif (!empty($dumpArr)): ?>
                    <h6 class="small font-weight-semibold mb-2"><i class="fas fa-database mr-1 text-<?= $r['action']==='create'?'success':'danger' ?>"></i><?= te('audit.record_state') ?></h6>
                    <div class="table-responsive border rounded bg-white">
                        <table class="table table-sm mb-0">
                            <thead class="thead-light"><tr><th style="width:28%"><?= te('audit.field') ?></th><th>Value</th></tr></thead>
                            <tbody>
                                <?php foreach (array_slice($dumpArr, 0, 25, true) as $dk => $dv):
                                    if (is_array($dv)) $dv = json_encode($dv, JSON_UNESCAPED_UNICODE);
                                ?>
                                    <tr>
                                        <td class="font-weight-mono font-weight-semibold small"><?= e($dk) ?></td>
                                        <td class="small"><samp><?= e($dv !== null && !is_array($dv) ? (mb_strlen((string)$dv) > 500 ? mb_substr((string)$dv,0,500).'…' : (string)$dv) : json_encode($dv, JSON_UNESCAPED_UNICODE)) ?></samp></td>
                                    </tr>
                                <?php endforeach;
                                    if (count($dumpArr) > 25): ?><tr><td colspan="2" class="small text-muted text-center">… <?= count($dumpArr) - 25 ?> <?= te('audit.csv_export_hint') ?></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($r['action'] === 'update'): ?>
                    <div class="small text-muted p-2 bg-white border rounded">
                        <i class="far fa-meh mr-1"></i><?= te('audit.no_changes_shown') ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($r['session_id'])): ?>
                    <div class="mt-2 small text-muted">
                        <i class="fas fa-fingerprint mr-1"></i><?= te('audit.csv_session') ?>: <code class="font-weight-mono"><?= e($r['session_id']) ?></code>
                    </div>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
</div>
<div class="card-footer bg-white border-top d-flex justify-content-between align-items-center small text-muted flex-wrap">
    <div><?= strtr(t('audit.showing'), ['{first}'=>$totalAll===0?0:($offset+1), '{last}'=>min($offset+$perPage,$totalAll), '{total}'=>number_format($totalAll)]) ?></div>
    <div class="d-flex align-items-center">
        <?= paginationHtml($page, $totalPages) ?>
        <a href="<?= SITE_URL ?>/audit_logs.php?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>" class="btn btn-sm btn-outline-success ml-2">
            <i class="fas fa-file-csv mr-1"></i><?= te('audit.export_csv') ?>
        </a>
    </div>
</div>
</div>

<?php else: ?>

<!-- TIMELINE VIEW (existing enhanced) -->
<div class="card">
<div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center flex-wrap">
    <h6 class="mb-0 font-weight-semibold">
        <i class="fas fa-history mr-1 text-indigo"></i><?= te('title.audit_log') ?>
        <small class="text-muted ml-2">
            <?= strtr(t('audit.showing'), ['{first}'=>$totalAll===0?0:($offset+1), '{last}'=>min($offset+$perPage,$totalAll), '{total}'=>number_format($totalAll)]) ?>
        </small>
    </h6>
    <div class="d-flex align-items-center">
        <div class="btn-group btn-group-sm mr-2">
            <button type="button" class="btn btn-outline-secondary audit-expand-all"><i class="fas fa-plus-square mr-1"></i><?= te('audit.expand_all') ?></button>
            <button type="button" class="btn btn-outline-secondary audit-collapse-all"><i class="fas fa-minus-square mr-1"></i><?= te('audit.collapse_all') ?></button>
        </div>
        <?= paginationHtml($page, $totalPages) ?>
        <a href="<?= SITE_URL ?>/audit_logs.php?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>" class="btn btn-sm btn-outline-success ml-2">
            <i class="fas fa-file-csv mr-1"></i><?= te('audit.export_csv') ?>
        </a>
    </div>
</div>
<div class="card-body p-0">
<?php if (empty($rows)): ?>
    <div class="text-center py-7">
        <div style="font-size:4rem" class="mb-2 text-muted"><i class="far fa-clipboard"></i></div>
        <h5 class="text-muted mb-1"><?= te('audit.no_records') ?></h5>
        <p class="text-muted small mb-0"><i class="fas fa-filter mr-1"></i><?= te('common.reset') ?>
            <a href="<?= SITE_URL ?>/audit_logs.php" class="ml-1 text-decoration-none"><?= te('audit.q_creates') ?>?</a>
        </p>
    </div>
<?php else:
    $lastDate = null; $rowIdx = 0;
    foreach ($rows as $r): $rowIdx++;
        $rowDate = (new DateTime($r['created_at']))->format('Y-m-d');
        if ($rowDate !== $lastDate):
            $lastDate = $rowDate;
            if ($rowDate === $today) $dateLabel = '<span class="badge badge-success">'.te('audit.today').'</span>';
            elseif ($rowDate === $yesterday) $dateLabel = '<span class="badge badge-info">'.te('audit.yesterday').'</span>';
            else $dateLabel = date('d/m/Y', strtotime($rowDate));
?>
<div class="px-3 py-2 bg-light border-top border-bottom d-flex align-items-center">
    <span class="font-weight-semibold mr-2"><?= $dateLabel ?></span>
    <div class="flex-grow-1" style="height:1px;background:linear-gradient(90deg, #dee2e6, transparent);"></div>
</div>
<?php endif;
    $aInfo = $knownActions[$r['action']] ?? ['icon'=>'question-circle','color'=>'secondary'];
    $eInfo = $knownEntities[$r['entity_type']] ?? ['icon'=>'file','color'=>'gray'];
    $eColor = isset($knownColors[$eInfo['color']]) ? $knownColors[$eInfo['color']] : '#6c757d';
    $aColor = $aInfo['color'];
    $old = $r['old_value'] ? @json_decode($r['old_value'], true) : null;
    $new = $r['new_value'] ? @json_decode($r['new_value'], true) : null;
    $newForTarget = is_array($new) ? $new : (is_array($old) ? $old : []);
    $target = targetLink($r['entity_type'], $r['entity_id'], $newForTarget);
    $targetHtml = $target['url'] !== null
        ? '<a class="font-weight-semibold text-indigo" href="'.e($target['url']).'" target="_blank" title="'.te('audit.view_record').'">'.e($target['label']).' <i class="fas fa-external-link-alt ml-1" style="font-size:.7em"></i></a>'
        : '<span class="font-weight-semibold">'.e($target['label']).'</span>';
    $sentence = renderSentence($r, $r['user_name'] ?? t('audit.unknown'), t_entity($r['entity_type']), $target['label'], t_audit_action($r['action']));
    $userBadge = initialsBadge($r['user_name'] ?? '?');
    list($ini, $iniColor) = explode('|', $userBadge);
    $diff = $r['action'] === 'update' ? computeDiff($old, $new) : [];
    $hasDetails = (!empty($r['details'] ?? '')) || (!empty($diff)) || is_array($old) || is_array($new);
    $ts = @strtotime($r['created_at']) ?: time();
    $timeOnlyLabel = date('h:i:s A', $ts);
?>
<div class="audit-row p-3 border-bottom hover-grey" data-id="<?= (int)$r['id'] ?>">
    <div class="d-flex align-items-start">
        <div class="mr-3 d-flex flex-column align-items-center" style="min-width:56px">
            <div class="rounded-circle d-flex align-items-center justify-content-center text-white font-weight-bold mb-1" style="width:40px;height:40px;background:<?= $iniColor ?>;font-size:14px;">
                <?= e($ini) ?>
            </div>
            <span class="badge badge-<?= $aColor ?> badge-pill d-flex align-items-center px-2 py-1" title="<?= e(t_audit_action($r['action'])) ?>">
                <i class="fas fa-<?= $aInfo['icon'] ?> mr-1"></i><?= te(t_audit_action($r['action'])) ?>
            </span>
        </div>
        <div class="flex-grow-1 min-w-0">
            <div class="d-flex align-items-center mb-1 flex-wrap">
                <?= $sentence ?>
            </div>
            <div class="d-flex align-items-center flex-wrap small text-muted">
                <span class="mr-3 mb-1">
                    <i class="far fa-clock mr-1"></i>
                    <span class="font-weight-semibold text-body"><?= relativeTime($ts) ?></span>
                    <span class="mx-1">·</span>
                    <span title="<?= e(formatDateTime($r['created_at'])) ?>"><?= $timeOnlyLabel ?></span>
                </span>
                <span class="mr-3 mb-1">
                    <i class="fas fa-user-tag mr-1"></i>
                    <span><?= e($r['user_name'] ?? t('audit.unknown')) ?></span>
                    <?php if (!empty($r['user_role'])): ?>
                        <span class="badge badge-light ml-1"><?= e(ucfirst($r['user_role'])) ?></span>
                    <?php endif; ?>
                </span>
                <span class="mr-3 mb-1">
                    <i class="fas fa-<?= $eInfo['icon'] ?> mr-1" style="color:<?= $eColor ?>"></i>
                    <span class="font-weight-semibold" style="color:<?= $eColor ?>"><?= te(t_entity($r['entity_type'])) ?></span>
                    <?php if ($r['entity_id'] !== null): ?>
                        <span class="badge badge-light ml-1">#<?= e($r['entity_id']) ?></span>
                    <?php endif; ?>
                </span>
                <span class="mr-3 mb-1"><?= $targetHtml ?></span>
                <?php if (!empty($r['ip_address'])): ?>
                    <span class="mb-1 d-inline-flex align-items-center">
                        <i class="fas fa-network-wired mr-1"></i>
                        <button type="button" class="btn btn-xs btn-link p-0 text-muted text-decoration-none copy-ip font-weight-mono" data-ip="<?= e($r['ip_address']) ?>" title="<?= te('audit.copy_ip') ?>">
                            <?= e($r['ip_address']) ?> <i class="far fa-copy ml-1"></i>
                        </button>
                    </span>
                <?php endif; ?>
            </div>
            <?php if (!empty($r['details'] ?? '')): ?>
                <div class="mt-2 p-2 bg-light border rounded" style="font-size:.85rem">
                    <i class="fas fa-info-circle mr-1 text-info"></i>
                    <?= e($r['details'] ?? '') ?>
                </div>
            <?php endif; ?>
            <?php if ($hasDetails): ?>
                <div class="mt-2">
                    <button class="btn btn-xs btn-link p-0 small text-decoration-none audit-toggle" data-target="diff-<?= (int)$r['id'] ?>">
                        <i class="fas fa-chevron-right mr-1 audit-chevron"></i><?= te('audit.expand_diff') ?>
                    </button>
                    <div id="diff-<?= (int)$r['id'] ?>" class="audit-diff mt-2 d-none">
                        <?php if ($r['action'] === 'update' && !empty($diff)): ?>
                            <div class="table-responsive border rounded">
                            <table class="table table-sm mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width:30%"><?= te('audit.field') ?></th>
                                    <th style="width:35%"><?= te('audit.before') ?></th>
                                    <th style="width:35%"><?= te('audit.after') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($diff as $dk => $dv): ?>
                                    <tr>
                                        <td class="font-weight-mono font-weight-semibold"><?= e($dk) ?></td>
                                        <td class="text-danger bg-danger-light">
                                            <?php if ($dv['old'] === null): ?><span class="text-muted"><i>—</i></span><?php else: ?><samp class="small"><?= e(mb_strlen($dv['old']) > 180 ? mb_substr($dv['old'],0,180).'…' : $dv['old']) ?></samp><?php endif; ?>
                                        </td>
                                        <td class="text-success bg-success-light">
                                            <?php if ($dv['new'] === null): ?><span class="text-muted"><i>—</i></span><?php else: ?><samp class="small"><?= e(mb_strlen($dv['new']) > 180 ? mb_substr($dv['new'],0,180).'…' : $dv['new']) ?></samp><?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            </table>
                            </div>
                        <?php elseif (is_array($new) || is_array($old)): ?>
                            <div class="table-responsive border rounded">
                                <table class="table table-sm mb-0">
                                <thead class="thead-light"><tr><th style="width:30%"><?= te('audit.field') ?></th><th>Value</th></tr></thead>
                                <tbody>
                                    <?php $dumpArr2 = is_array($new) ? $new : (is_array($old) ? $old : []);
                                          foreach (array_slice($dumpArr2, 0, 15, true) as $dk => $dv):
                                            if (is_array($dv)) $dv = json_encode($dv, JSON_UNESCAPED_UNICODE);
                                    ?>
                                        <tr>
                                            <td class="font-weight-mono font-weight-semibold"><?= e($dk) ?></td>
                                            <td class="small"><samp><?= e($dv !== null && !is_array($dv) ? (mb_strlen((string)$dv) > 250 ? mb_substr((string)$dv,0,250).'…' : (string)$dv) : json_encode($dv, JSON_UNESCAPED_UNICODE)) ?></samp></td>
                                        </tr>
                                    <?php endforeach;
                                          if (count($dumpArr2) > 15): ?><tr><td colspan="2" class="small text-muted text-center">… <?= count($dumpArr2) - 15 ?> <?= te('audit.csv_export_hint') ?></td></tr><?php endif; ?>
                                </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="small text-muted p-2 bg-light border rounded">
                                <i class="far fa-meh mr-1"></i><?= te('audit.no_changes_shown') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
<div class="card-footer bg-white border-top d-flex justify-content-between align-items-center small text-muted flex-wrap">
    <div><?= strtr(t('audit.showing'), ['{first}'=>$totalAll===0?0:($offset+1), '{last}'=>min($offset+$perPage,$totalAll), '{total}'=>number_format($totalAll)]) ?></div>
    <div class="d-flex align-items-center">
        <?= paginationHtml($page, $totalPages) ?>
        <a href="<?= SITE_URL ?>/audit_logs.php?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>" class="btn btn-sm btn-outline-success ml-2">
            <i class="fas fa-file-csv mr-1"></i><?= te('audit.export_csv') ?>
        </a>
    </div>
</div>
</div>
<?php endif; ?>

<style>
.text-indigo { color:#6610f2 !important; }
.bg-indigo { background-color:#6610f2; }
.hover-grey:hover { background-color:#f8f9fa; }
.font-weight-mono { font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size:.85rem; }
.font-weight-semibold { font-weight:600 !important; }
.page-title { font-size:1.6rem; font-weight:700; }
.kpi-card { border:1px solid #eef0f3; border-radius:12px; }
.badge-outline-primary { border:1px solid #b3d7ff; color:#0056b3; background:#f8fbff; }
.badge-outline-secondary { border:1px solid #d6d8db; color:#383d41; background:#f8f9fa; }
.badge-outline-primary:hover { background:#e6f0ff; }
.badge-outline-secondary:hover { background:#e2e6ea; }
.kpi-icon i { font-size:1.15rem; }
.audit-row:hover .btn-link { color:#0056b3; }
.bg-danger-light { background-color:#fff5f5; }
.bg-success-light { background-color:#f0fff4; }
.px-7 { padding-left:4rem !important; padding-right:4rem !important; }
.py-7 { padding-top:3.5rem !important; padding-bottom:3.5rem !important; }
.audit-table-wrap thead.thead-light th { background:#f8f9fa; z-index:2; }
.sticky-top { position:sticky; top:0; z-index:2; }
.audit-table .copy-ip { padding: .1rem .35rem; font-size: .7rem; }
.text-truncate-wrap { word-break: break-word; }
.audit-table tbody tr.audit-row:hover { background:#f8fbff; cursor:pointer; }
</style>

<script>
$(function() {
    $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
    $('.datepicker').flatpickr({ dateFormat: 'd/m/Y', allowInput: true, locale: LANG_CODE === 'ar' ? 'ar' : 'default' });

    $(document).on('click', '.copy-ip', function(e) {
        e.preventDefault();
        var ip = $(this).attr('data-ip') || '';
        if (!ip) return;
        var $this = $(this);
        var origHtml = $this.html();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(ip).then(function(){
                $this.addClass('btn-success text-white').removeClass('btn-outline-secondary btn-link text-muted').html('<i class="fas fa-check mr-1"></i>' + <?= json_encode(t('audit.copied')) ?>);
                setTimeout(function(){ $this.removeClass('btn-success text-white').addClass(origHtml.indexOf('copy-ip')!==-1 ? 'btn-outline-secondary' : 'btn-outline-secondary').html(origHtml); }, 1500);
            });
        } else {
            var ta = document.createElement('textarea'); ta.value = ip; ta.style.position='fixed'; ta.style.top='-9999px'; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); } catch(e){}
            document.body.removeChild(ta);
            $this.addClass('btn-success text-white').removeClass('btn-outline-secondary btn-link text-muted').html('<i class="fas fa-check mr-1"></i>' + <?= json_encode(t('audit.copied')) ?>);
            setTimeout(function(){ $this.removeClass('btn-success text-white').html(origHtml); }, 1500);
        }
    });

    $(document).on('click', '.audit-toggle', function(e){
        e.preventDefault();
        var tgt = document.getElementById($(this).attr('data-target'));
        if (!tgt) return;
        $(tgt).toggleClass('d-none');
        var chev = $(this).find('.audit-chevron');
        if (chev.length) chev.toggleClass('fa-chevron-right fa-chevron-down');
        var labels = { open: <?= json_encode(t('audit.expand_diff')) ?>, close: <?= json_encode(t('audit.collapse_diff')) ?> };
        var html = $(this).html();
        if ($(tgt).hasClass('d-none')) { $(this).html(html.replace(labels.close, labels.open)); }
        else { $(this).html(html.replace(labels.open, labels.close)); }
    });

    $(document).on('click', '.audit-toggle-row', function(e){
        e.stopPropagation();
        var tgt = document.getElementById($(this).attr('data-target'));
        if (!tgt) return;
        var $tgt = $(tgt);
        var $chev = $(this).find('.audit-row-chevron');
        $tgt.toggleClass('d-none');
        if ($chev.length) $chev.toggleClass('fa-chevron-down fa-chevron-up');
    });

    $(document).on('click', '.audit-expand-all', function(){
        if ($('.audit-toggle').length) {
            $('.audit-toggle').each(function(){
                var tgt = document.getElementById($(this).attr('data-target'));
                if (tgt && $(tgt).hasClass('d-none')) $(this).trigger('click');
            });
        }
        if ($('.audit-toggle-row').length) {
            $('.audit-toggle-row').each(function(){
                var tgt = document.getElementById($(this).attr('data-target'));
                if (tgt && $(tgt).hasClass('d-none')) $(this).trigger('click');
            });
        }
    });

    $(document).on('click', '.audit-collapse-all', function(){
        $('.audit-diff, .audit-row-detail').addClass('d-none');
        $('.audit-chevron').removeClass('fa-chevron-down').addClass('fa-chevron-right');
        $('.audit-row-chevron').removeClass('fa-chevron-up').addClass('fa-chevron-down');
        var labels = { open: <?= json_encode(t('audit.expand_diff')) ?>, close: <?= json_encode(t('audit.collapse_diff')) ?> };
        $('.audit-toggle').each(function(){
            var html = $(this).html();
            if (html.indexOf(labels.close) !== -1) $(this).html(html.replace(labels.close, labels.open));
        });
    });
});
</script>
<?php include SITE_PATH . '/includes/footer.php'; ?>
