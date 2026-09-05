<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');

$editing = isset($_GET['id']) && !empty($_GET['id']);
if ($editing) {
    requirePermission('edit_bookings');
    $bookingId = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();
    if (!$booking) { setFlash('error', t('err.not_found')); redirect(SITE_URL . '/bookings.php'); }
    $stmt = $conn->prepare("SELECT bi.*, it.name as item_name, it.category_id, it.quantity as total_quantity, c.name as category_name
        FROM booking_items bi INNER JOIN item_types it ON bi.item_type_id = it.id
        INNER JOIN categories c ON it.category_id = c.id WHERE bi.booking_id = ?");
    $stmt->execute([$bookingId]);
    $bookingItems = $stmt->fetchAll();
} else {
    requirePermission('create_bookings');
    $booking = null;
    $bookingItems = [];
}

$page_title = $editing ? t('title.booking_edit') : t('title.booking_new');
$active_nav = 'bookings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = (int)($_POST['client_id'] ?? 0);
    $dateFrom = DateTime::createFromFormat('d/m/Y', $_POST['date_from'] ?? date('d/m/Y'));
    $dateTo = DateTime::createFromFormat('d/m/Y', $_POST['date_to'] ?? date('d/m/Y'));
    if (!$dateFrom) $dateFrom = new DateTime();
    if (!$dateTo) $dateTo = new DateTime();
    if ($dateTo < $dateFrom) $dateTo = $dateFrom;
    $location = trim($_POST['location'] ?? '');
    $quotedAmount = (float)($_POST['quoted_amount'] ?? 0);
    $djRakAmount = (float)($_POST['dj_rak_amount'] ?? 0);
    $status = $_POST['status'] ?? 'Quotation';
    $internalNotes = trim($_POST['internal_notes'] ?? '');
    $startTime = $_POST['event_start_time'] ?? null;
    $endTime = $_POST['event_end_time'] ?? null;

    $newClientName = trim($_POST['new_client_name'] ?? '');
    $newClientPhone = trim($_POST['new_client_phone'] ?? '');
    if ($clientId === 0 && $newClientName !== '' && $newClientPhone !== '') {
        $stmt = $conn->prepare("INSERT INTO clients (name, phone, created_at) VALUES (?,?,NOW())");
        $stmt->execute([$newClientName, $newClientPhone]);
        $clientId = $conn->lastInsertId();
        auditLog('create', 'Client', $clientId, null, ['name' => $newClientName, 'phone' => $newClientPhone]);
    }

    if ($clientId === 0 || $location === '' || !$dateFrom) {
        setFlash('error', t('msg.fill_required_fields'));
        $refresh = true;
    }

    $items = [];
    $itemErrors = [];
    $rawPostRows = [];
    if (isset($_POST['item_type_id']) && is_array($_POST['item_type_id'])) {
        foreach ($_POST['item_type_id'] as $i => $rawItId) {
            $itId = (int)$rawItId;
            $qty = (int)($_POST['quantity'][$i] ?? 0);
            $rv = (float)($_POST['rental_value'][$i] ?? 0);
            $rawPostRows[] = ['item_type_id' => $itId, 'quantity' => $qty, 'rental_value' => $rv];
            if ($itId > 0 && $qty > 0) {
                $avail = getAvailableQuantity($itId, $dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d'), $editing ? $bookingId : null);
                if ($qty > $avail && !hasPermission('override_inventory')) {
                    $stmt = $conn->prepare("SELECT name FROM item_types WHERE id = ?"); $stmt->execute([$itId]);
                    $nm = $stmt->fetchColumn();
                    $itemErrors[] = t('msg.requested_unavailable', [$nm, $qty, $avail]);
                }
                $items[] = ['item_type_id' => $itId, 'quantity' => $qty, 'rental_value' => $rv];
            }
        }
    }

    if (!empty($itemErrors)) {
        setFlash('error', t('msg.availability_issues') . implode(' | ', $itemErrors));
        $refresh = true;
    }

    if (empty($refresh)) {
        try {
            $conn->beginTransaction();
            if ($editing) {
                $old = $booking;
                $stmt = $conn->prepare("UPDATE bookings SET client_id=?, date_from=?, date_to=?, event_start_time=?, event_end_time=?, location=?, quoted_amount=?, dj_rak_amount=?, status=?, internal_notes=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$clientId, $dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d'), $startTime, $endTime, $location, $quotedAmount, $djRakAmount, $status, $internalNotes, $bookingId]);
                auditLog('update', 'Booking', $bookingId, $old, ['client_id'=>$clientId,'date_from'=>$dateFrom->format('Y-m-d'),'date_to'=>$dateTo->format('Y-m-d'),'location'=>$location,'quoted_amount'=>$quotedAmount,'dj_rak_amount'=>$djRakAmount,'status'=>$status]);
                $conn->prepare("DELETE FROM booking_items WHERE booking_id = ?")->execute([$bookingId]);
            } else {
                $bkNum = generateBookingNumber();
                $token = generateToken(24);
                $stmt = $conn->prepare("INSERT INTO bookings (booking_number, client_id, date_from, date_to, event_start_time, event_end_time, location, quoted_amount, dj_rak_amount, status, internal_notes, customer_confirmation_token, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$bkNum, $clientId, $dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d'), $startTime, $endTime, $location, $quotedAmount, $djRakAmount, $status, $internalNotes, $token, $_SESSION['user_id']]);
                $bookingId = $conn->lastInsertId();
                auditLog('create', 'Booking', $bookingId, null, ['booking_number' => $bkNum]);
            }
            $biStmt = $conn->prepare("INSERT INTO booking_items (booking_id, item_type_id, quantity, rental_value) VALUES (?,?,?,?)");
            foreach ($items as $it) {
                $biStmt->execute([$bookingId, $it['item_type_id'], $it['quantity'], $it['rental_value']]);
            }
            $conn->commit();
            updateBookingPaymentStatus($bookingId);
            setFlash('success', $editing ? t('bk.update_success') : t('bk.create_success'));
            if (isset($_POST['save_and_whatsapp'])) {
                redirect(SITE_URL . '/booking_view.php?id=' . $bookingId . '&wa=1');
            }
            redirect(SITE_URL . '/booking_view.php?id=' . $bookingId);
        } catch (Exception $e) {
            $conn->rollBack();
            setFlash('error', t('bk.error_saving', [$e->getMessage()]));
        }
    }
    if (!empty($refresh) && !empty($rawPostRows)) {
        $renderItems = [];
        foreach ($rawPostRows as $rr) {
            $catId = null;
            $itemName = null;
            if ($rr['item_type_id'] > 0) {
                $stmt = $conn->prepare("SELECT it.category_id, it.name FROM item_types it WHERE it.id = ?");
                $stmt->execute([$rr['item_type_id']]);
                $row = $stmt->fetch();
                if ($row) { $catId = (int)$row['category_id']; $itemName = $row['name']; }
            }
            $renderItems[] = [
                'category_id' => $catId,
                'item_type_id' => $rr['item_type_id'],
                'item_name' => $itemName,
                'quantity' => $rr['quantity'],
                'rental_value' => $rr['rental_value'],
            ];
        }
    }
}

$categories = $conn->query("SELECT * FROM categories WHERE active=1 ORDER BY name")->fetchAll();
$clients = $conn->query("SELECT id, name, phone FROM clients WHERE active=1 ORDER BY name")->fetchAll();

include SITE_PATH . '/includes/header.php';
echo flashMessages();
?>
<div class="row mb-3">
    <div class="col-md-6">
        <h1 class="page-title"><?= $editing ? t('title.booking_edit') : t('title.booking_new') ?></h1>
        <p class="page-subtitle"><?= $editing ? t('title.booking_edit_sub') : t('title.booking_new_sub') ?></p>
    </div>
    <div class="col-md-6 text-md-right">
        <a href="<?= SITE_URL ?>/bookings.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> <?= t('btn.back_to_bookings') ?></a>
    </div>
</div>

<form method="POST" id="bookingForm">
<div class="row">
    <div class="col-xl-8 col-lg-8 mb-3 mb-lg-0">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-info-circle mr-2"></i><?= t('bk.info_title') ?></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="font-weight-semibold"><?= t('bk.client_label') ?> <span class="text-danger">*</span></label>
                        <div class="row">
                            <div class="col-md-8 col-sm-12 mb-2">
                                <select name="client_id" id="clientId" class="form-control select2">
                                    <option value=""><?= t('field.client_select') ?></option>
                                    <?php foreach ($clients as $c): ?>
                                        <option value="<?= $c['id'] ?>" data-phone="<?= e($c['phone']) ?>"
                                            <?= ($booking && $booking['client_id'] == $c['id']) ? 'selected' : '' ?>>
                                            <?= e($c['name']) ?> (<?= e($c['phone']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 col-sm-12 mb-2"><button type="button" class="btn btn-outline-secondary btn-block" data-toggle="collapse" data-target="#newClientCollapse"><i class="fas fa-user-plus"></i> <?= t('bk.client_new') ?></button></div>
                        </div>
                        <div class="collapse mt-3" id="newClientCollapse">
                            <div class="card card-body bg-light">
                                <h6 class="font-weight-bold"><?= t('c.add_client') ?></h6>
                                <div class="row">
                                    <div class="col-md-6 col-sm-12 mb-2"><input class="form-control" name="new_client_name" id="newClientName" placeholder="<?= te('bk.new_client_name_ph') ?>" oninput="document.getElementById('clientId').value = this.value ? '' : document.getElementById('clientId').value;"></div>
                                    <div class="col-md-6 col-sm-12 mb-2"><input class="form-control" name="new_client_phone" id="newClientPhone" placeholder="<?= te('bk.new_client_phone_ph') ?>"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-sm-6 mb-3">
                        <label class="font-weight-semibold"><?= t('bk.date_from') ?> <span class="text-danger">*</span></label>
                        <input type="text" name="date_from" id="dateFrom" class="form-control datepicker" required
                            value="<?= $booking ? formatDate($booking['date_from']) : date('d/m/Y') ?>" autocomplete="off">
                    </div>
                    <div class="col-md-6 col-sm-6 mb-3">
                        <label class="font-weight-semibold"><?= t('bk.date_to') ?> <span class="text-danger">*</span></label>
                        <input type="text" name="date_to" id="dateTo" class="form-control datepicker" required
                            value="<?= $booking ? formatDate($booking['date_to']) : date('d/m/Y') ?>" autocomplete="off">
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <label class="font-weight-semibold"><?= t('bk.time_start') ?></label>
                        <input type="time" name="event_start_time" class="form-control" value="<?= e($booking['event_start_time'] ?? '18:00') ?>">
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <label class="font-weight-semibold"><?= t('bk.time_end') ?></label>
                        <input type="time" name="event_end_time" class="form-control" value="<?= e($booking['event_end_time'] ?? '23:00') ?>">
                    </div>
                    <div class="col-md-6 col-sm-12 mb-3">
                        <label class="font-weight-semibold"><?= t('bk.status') ?></label>
                        <select name="status" class="form-control">
                            <?php foreach (['Draft','Quotation','Confirmed','Change Requested','Event Completed','Closed'] as $s):
                                $defaultStatus = $booking ? $booking['status'] : 'Quotation';
                                $sel = $defaultStatus === $s ? 'selected' : '';
                            ?>
                                <option value="<?= e($s) ?>" <?= $sel ?>><?= t_booking_status($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted"><?= t('bk.status_help') ?></small>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="font-weight-semibold"><?= t('bk.location') ?> <span class="text-danger">*</span></label>
                        <input type="text" name="location" class="form-control" required placeholder="<?= te('bk.location_ph') ?>" value="<?= e($booking['location'] ?? '') ?>">
                    </div>
                    <div class="col-md-12 mb-0">
                        <label class="font-weight-semibold"><?= t('bk.internal_notes') ?> <span class="text-info" data-toggle="tooltip" title="<?= te('bk.never_shared_note') ?>"><i class="fas fa-info-circle"></i></span></label>
                        <textarea name="internal_notes" rows="3" class="form-control" placeholder="<?= te('bk.internal_notes_ph') ?>"><?= e($booking['internal_notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3 eq-selection-box">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <span class="d-inline-flex align-items-center">
                    <i class="fas fa-box mr-2"></i>
                    <strong style="font-size: 1.02rem;"><?= t('bk.equipment_heading') ?></strong>
                    <span class="ml-2 small text-muted font-weight-normal eq-select-hint">
                        <i class="fas fa-check-square mr-1"></i><?= t('bk.select_items_hint') ?? 'Tick items to add them to this booking' ?>
                    </span>
                </span>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2 mt-sm-0 eq-toolbar-btns">
                    <div class="input-group input-group-sm mr-0 mr-sm-2 eq-search-wrap">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" id="equipmentSearch" class="form-control form-control-sm"
                            placeholder="<?= te('bk.search_items_ph') ?? 'Search items...' ?>"
                            aria-label="<?= te('bk.search_items_ph') ?? 'Search items' ?>">
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mr-1 eq-select-all-visible" title="<?= te('bk.select_all_visible') ?? 'Select all visible' ?>">
                        <i class="fas fa-check-double mr-1"></i><?= t('bk.select_all_short') ?? 'All' ?>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary eq-clear-all" title="<?= te('bk.clear_all_hint') ?? 'Clear all' ?>">
                        <i class="fas fa-eraser mr-1"></i><?= t('bk.clear_all_short') ?? 'Clear' ?>
                    </button>
                </div>
            </div>
            <div class="card-body p-0 p-sm-2 p-md-3">
                <div id="equipmentContainer">
                    <?php
                    $rowsToRender = $renderItems ?? $bookingItems ?? [];
                    $selMap = [];
                    if (!empty($rowsToRender)) {
                        foreach ($rowsToRender as $bi) {
                            $selMap[(int)$bi['item_type_id']] = [
                                'quantity' => (int)$bi['quantity'],
                                'rental_value' => (float)$bi['rental_value']
                            ];
                        }
                    }
                    $itemStmt = $conn->query("SELECT id, category_id, name, default_rental_value, quantity FROM item_types WHERE active=1 ORDER BY category_id, name");
                    $itemsByCat = [];
                    foreach ($itemStmt->fetchAll() as $it) {
                        $cid = (int)$it['category_id'];
                        if (!isset($itemsByCat[$cid])) $itemsByCat[$cid] = [];
                        $itemsByCat[$cid][] = $it;
                    }
                    $catIdx = 0;
                    foreach ($categories as $c):
                        $cid = (int)$c['id'];
                        $items = $itemsByCat[$cid] ?? [];
                        if (empty($items)) continue;
                        $catIdx++;
                        ?>
                        <div class="eq-category-block eq-inner" data-cat-block="<?= $cid ?>">
                            <div class="eq-category-header d-flex align-items-center justify-content-between">
                                <label class="mb-0 d-flex align-items-center py-2 flex-grow-1 eq-cat-label">
                                    <input type="checkbox" class="eq-cat-toggle mr-2" data-cat="<?= $cid ?>" aria-label="<?= e($c['name']) ?>">
                                    <span class="eq-category-name text-dark" style="font-weight: 700; font-size: 0.98rem;">
                                        <?= e($c['name']) ?>
                                        <span class="text-muted ml-1 font-weight-normal" style="font-size: 0.82rem;">
                                            (<span class="eq-cat-visible-total"><?= count($items) ?></span>)
                                        </span>
                                    </span>
                                </label>
                                <button type="button" class="btn btn-sm btn-link eq-cat-collapse ml-auto eq-cat-collapse-btn"
                                    data-cat-toggle="<?= $cid ?>" title="<?= te('bk.toggle_cat') ?? 'Collapse / Expand' ?>"
                                    aria-expanded="true">
                                    <i class="fas fa-chevron-up text-muted small"></i>
                                </button>
                            </div>
                            <div class="eq-items-list" data-cat-body="<?= $cid ?>">
                                <div class="row no-gutters eq-items-grid">
                                <?php foreach ($items as $it):
                                    $itId = (int)$it['id'];
                                    $checked = isset($selMap[$itId]);
                                    $qty = $checked ? $selMap[$itId]['quantity'] : 1;
                                    $rv = $checked ? $selMap[$itId]['rental_value'] : (float)$it['default_rental_value'];
                                    $avail = (int)$it['quantity'];
                                    ?>
                                    <div class="col-xl-3 col-lg-4 col-md-4 col-sm-6 col-12 eq-item-wrap px-1 py-1" data-item-wrap="<?= $itId ?>">
                                        <div class="eq-item-card p-2 border rounded h-100 d-flex flex-column clickable-card"
                                            data-item="<?= $itId ?>"
                                            data-name="<?= e(mb_strtolower($it['name'])) ?>"
                                            data-cat-name="<?= e(mb_strtolower($c['name'])) ?>">
                                            <label class="eq-item-label mb-2 d-flex align-items-start">
                                                <input type="checkbox" class="eq-item-check mr-2 mt-1"
                                                    data-item="<?= $itId ?>"
                                                    data-cat="<?= $cid ?>"
                                                    data-rv="<?= e($it['default_rental_value']) ?>"
                                                    <?= $checked ? 'checked' : '' ?>>
                                                <span class="eq-item-name">
                                                    <strong style="font-size: 0.93rem; line-height: 1.25; display:block;"><?= e($it['name']) ?></strong>
                                                    <span class="d-block text-muted eq-item-meta mt-1">
                                                        <span class="badge badge-pill badge-light text-muted" style="font-weight: 500;">
                                                            <?= formatMoney($it['default_rental_value']) ?>
                                                        </span>
                                                        <span class="mx-1 text-muted">&middot;</span>
                                                        <span class="text-muted small">
                                                            <i class="fas fa-cubes mr-1 text-muted"></i><?= t('bk.avail_available') ?>: <?= $avail ?>
                                                        </span>
                                                    </span>
                                                </span>
                                            </label>
                                            <div class="eq-item-controls mt-auto d-flex align-items-center gap-2 row mx-0 <?= $checked ? '' : 'eq-disabled' ?>"
                                                 data-item-controls="<?= $itId ?>">
                                                <div class="col-6 px-1 mb-0 flex-grow-1">
                                                    <label class="small mb-0 d-block text-muted" for="eq_qty_<?= $itId ?>"><?= t('bk.item_qty') ?></label>
                                                    <input type="number" min="1" class="form-control form-control-sm eq-qty-input"
                                                        id="eq_qty_<?= $itId ?>" data-item="<?= $itId ?>"
                                                        value="<?= $qty ?>" <?= $checked ? '' : 'disabled' ?>>
                                                </div>
                                                <div class="col-6 px-1 mb-0 flex-grow-1">
                                                    <label class="small mb-0 d-block text-muted" for="eq_rv_<?= $itId ?>"><?= t('bk.item_rate') ?></label>
                                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm eq-rv-input"
                                                        id="eq_rv_<?= $itId ?>" data-item="<?= $itId ?>"
                                                        value="<?= number_format($rv, 2, '.', '') ?>" <?= $checked ? '' : 'disabled' ?>>
                                                </div>
                                            </div>
                                            <div class="small avail-info mt-1 px-0" data-avail-info="<?= $itId ?>">
                                                <span class="avail-status text-muted small"><?= t('bk.check_avail_hint') ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="equipmentHiddenRows" style="display:none;"></div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-lg-4">
        <div class="card mb-3 summary-box sticky-top">
            <div class="card-body pt-3">
                <h5 class="mb-3"><i class="fas fa-calculator mr-2"></i><?= t('bk.summary_heading') ?></h5>
                <div class="form-group">
                    <label class="font-weight-semibold"><?= t('bk.quoted_amount') ?></label>
                    <input type="number" step="0.01" name="quoted_amount" id="quotedAmount" class="form-control form-control-lg font-weight-bold"
                        value="<?= $booking ? e($booking['quoted_amount']) : '0.00' ?>" min="0">
                    <small class="text-muted"><?= t('bk.quoted_help') ?></small>
                </div>
                <div class="form-group">
                    <label class="font-weight-semibold"><?= t('bk.djrak_amount') ?> <span class="text-info" data-toggle="tooltip" title="<?= te('bk.djrak_help') ?>"><i class="fas fa-info-circle"></i></span></label>
                    <input type="number" step="0.01" name="dj_rak_amount" id="djRakAmount" class="form-control"
                        value="<?= $booking ? e($booking['dj_rak_amount']) : '0.00' ?>" min="0">
                    <small class="text-muted"><?= t('bk.djrak_help') ?></small>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span><?= t('bk.eq_count') ?>:</span><strong id="equipmentCount">0</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span><?= t('bk.eq_subtotal') ?>:</span><strong id="equipmentSubtotal">0.00</strong>
                </div>
                <div class="alert alert-info py-2 small mb-0" id="availabilitySummary">
                    <i class="fas fa-info-circle"></i> <?= t('bk.select_dates_avail') ?>
                </div>
            </div>
        </div>

        <div class="form-group mb-0">
            <button type="submit" name="save" class="btn btn-lg btn-primary btn-block mb-2">
                <i class="fas fa-save mr-1"></i> <?= $editing ? t('bk.update_btn') : t('common.save') ?>
            </button>
            <?php if (hasPermission('send_whatsapp')): ?>
            <button type="submit" name="save_and_whatsapp" class="btn btn-lg btn-block btn-whatsapp mb-2">
                <i class="fab fa-whatsapp mr-1"></i> <?= t('bk.save_send_whatsapp') ?>
            </button>
            <?php endif; ?>
            <a href="<?= SITE_URL ?>/bookings.php" class="btn btn-lg btn-outline-secondary btn-block"><?= t('common.cancel') ?></a>
        </div>
    </div>
</div>
</form>

<?php
function renderEquipmentRow($idx, $bi, $categories, $conn, $booking) {
    $hidden = ($idx === 0 && $bi === null && isset($_GET['preload_empty'])) ? 'style="display:none"' : '';
    ?>
    <div class="equipment-item-row card card-body bg-light p-2 p-md-3 mb-2" data-row="<?= $idx ?>" <?= $hidden ?>>
        <div class="row align-items-end">
            <div class="col-md-4 col-sm-6 col-12 mb-2">
                <label class="small font-weight-semibold"><?= t('bk.item_category') ?></label>
                <select class="form-control eq-category" data-row="<?= $idx ?>">
                    <option value=""><?= t('inv.select_category') ?></option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($bi && $bi['category_id'] == $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-sm-6 col-12 mb-2">
                <label class="small font-weight-semibold"><?= t('bk.item_type_label') ?> <span class="text-danger">*</span></label>
                <select name="item_type_id[]" class="form-control eq-item-type select2" data-row="<?= $idx ?>" required>
                    <option value=""><?= t('inv.select_item') ?></option>
                    <?php
                    $catId = $bi ? $bi['category_id'] : ($categories[0]['id'] ?? null);
                    $stmt = $conn->prepare("SELECT * FROM item_types WHERE active=1 " . ($catId ? "AND category_id=?" : "") . " ORDER BY name");
                    if ($catId) $stmt->execute([$catId]); else $stmt->execute();
                    foreach ($stmt->fetchAll() as $it): ?>
                        <option value="<?= $it['id'] ?>" data-rv="<?= e($it['default_rental_value']) ?>"
                            <?= ($bi && $bi['item_type_id'] == $it['id']) ? 'selected' : '' ?>><?= e($it['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-sm-4 col-6 mb-2">
                <label class="small font-weight-semibold"><?= t('bk.item_qty') ?> <span class="text-danger">*</span></label>
                <input type="number" name="quantity[]" class="form-control eq-qty" data-row="<?= $idx ?>"
                    value="<?= $bi ? e($bi['quantity']) : 1 ?>" min="1">
            </div>
            <div class="col-md-2 col-sm-4 col-6 mb-2">
                <label class="small font-weight-semibold"><?= t('bk.item_rate') ?></label>
                <input type="number" step="0.01" name="rental_value[]" class="form-control eq-rate" data-row="<?= $idx ?>"
                    value="<?= $bi ? e($bi['rental_value']) : 0 ?>" min="0">
            </div>
            <div class="col-sm-4 col-12 mb-2 text-sm-right">
                <button type="button" class="btn btn-outline-danger btn-block removeRow" data-row="<?= $idx ?>">
                    <i class="fas fa-trash mr-1"></i><?= t('common.remove') ?>
                </button>
            </div>
        </div>
        <div class="row">
            <div class="col-12 small avail-info mt-1">
                <span class="avail-status text-muted"><?= t('bk.check_avail_hint') ?></span>
            </div>
        </div>
    </div>
<?php
}
?>

<script>
var editingId = <?= $editing ? $bookingId : 'null' ?>;
var allItems = <?= json_encode(
    $conn->query("SELECT id, category_id, name, default_rental_value, quantity FROM item_types WHERE active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) + []
) ?>;
var itemsById = {};
allItems.forEach(function(it) { itemsById[it.id] = it; });

var SITE_URL = '<?= SITE_URL ?>';

var I18N = {
    checkAvail: <?= json_encode(t('bk.check_avail_hint')) ?>,
    dateBefore: <?= json_encode(t('msg.date_to_before_from')) ?>,
    checking: <?= json_encode(t('bk.checking_avail')) ?>,
    availOk: <?= json_encode(t('bk.avail_ok')) ?>,
    availUnavailable: <?= json_encode(t('bk.avail_unavailable')) ?>,
    availTotal: <?= json_encode(t('bk.avail_total')) ?>,
    availBooked: <?= json_encode(t('bk.avail_booked')) ?>,
    availAvailable: <?= json_encode(t('bk.avail_available')) ?>,
    shortage: <?= json_encode(t('bk.shortage_units')) ?>,
    errorCheck: <?= json_encode(t('bk.avail_error')) ?>,
    clientRequired: <?= json_encode(t('msg.client_required')) ?>
};

function parseDmY(s) { var p = (s||'').split('/'); if (p.length !== 3) return null; return new Date(+p[2], +p[1]-1, +p[0]); }
function dfISO(d)  { return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }
function dtISO(d)  { return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }

function syncHiddenRows() {
    // Rebuild #equipmentHiddenRows inputs: item_type_id[] / quantity[] / rental_value[]
    // Order of these parallel arrays must match — iterate checkboxes in document order.
    var $container = $('#equipmentHiddenRows');
    $container.empty();
    var html = '';
    $('#equipmentContainer .eq-item-check').each(function() {
        var $check = $(this);
        if (!$check.is(':checked')) return;
        var itId = $check.data('item');
        var qty = parseInt($('#eq_qty_'+itId).val()) || 0;
        var rv = parseFloat($('#eq_rv_'+itId).val()) || 0;
        html += '<input type="hidden" name="item_type_id[]" value="'+itId+'">';
        html += '<input type="hidden" name="quantity[]" value="'+qty+'">';
        html += '<input type="hidden" name="rental_value[]" value="'+rv.toFixed(2)+'">';
    });
    $container.html(html);
}

function recalc() {
    var count = 0, subtotal = 0;
    $('#equipmentContainer .eq-item-check').each(function() {
        var $check = $(this);
        if (!$check.is(':checked')) return;
        var itId = $check.data('item');
        var q = parseInt($('#eq_qty_'+itId).val()) || 0;
        var r = parseFloat($('#eq_rv_'+itId).val()) || 0;
        count += q;
        subtotal += q * r;
    });
    var djRak = parseFloat($('#djRakAmount').val()) || 0;
    var grandTotal = subtotal + djRak;
    $('#equipmentCount').text(count);
    $('#equipmentSubtotal').text(subtotal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}));

    var $qa = $('#quotedAmount');
    var qaVal = parseFloat($qa.val());
    var lastComputed = parseFloat($qa.data('lastComputed') || '0');

    // Always keep the quoted editable; auto-populate when:
    //  (a) it was left at 0.00 (fresh booking / staff hasn't typed yet), OR
    //  (b) it matches our previously-computed value (so user hasn't overridden manually).
    // If neither, user typed a custom figure — keep it untouched.
    if (!qaVal || qaVal === 0 || (lastComputed > 0 && Math.abs(qaVal - lastComputed) < 0.005)) {
        $qa.val(grandTotal.toFixed(2));
        $qa.data('lastComputed', grandTotal.toFixed(2));
    } else {
        // Remember the NEW computed would-be value for the NEXT edit
        $qa.data('lastComputed', grandTotal.toFixed(2));
    }
    syncHiddenRows();
}

async function checkItemAvail(itId) {
    var $check = $('#equipmentContainer .eq-item-check[data-item="'+itId+'"]');
    if ($check.length === 0) return;
    var checked = $check.is(':checked');
    var $info = $('[data-avail-info="'+itId+'"] .avail-status');
    if (!checked) { $info.removeClass().addClass('avail-status text-muted').text(I18N.checkAvail); return; }
    var qty = parseInt($('#eq_qty_'+itId).val()) || 0;
    var df = parseDmY($('#dateFrom').val());
    var dt = parseDmY($('#dateTo').val());
    if (!qty || !df || !dt) { $info.removeClass().addClass('avail-status text-muted').text(I18N.checkAvail); return; }
    if (dt < df) { $info.removeClass().addClass('avail-status text-danger').text(I18N.dateBefore); return; }
    $info.removeClass().addClass('avail-status text-info').text(I18N.checking);
    try {
        var data = { item_type_id: itId, date_from: dfISO(df), date_to: dtISO(dt) };
        if (editingId) data.exclude_booking_id = editingId;
        var r = await $.get(SITE_URL + '/ajax_availability.php', data);
        var total = parseInt(r.total) || 0;
        var booked = parseInt(r.booked) || 0;
        var avail = Math.max(0, total - booked);
        var line = I18N.availTotal + ': '+total+' | '+I18N.availBooked+': '+booked+' | '+I18N.availAvailable+': '+avail;
        if (qty <= avail) {
            $info.removeClass().addClass('avail-status equipment-available').html('<i class="fas fa-check-circle"></i> '+I18N.availOk+' &mdash; '+line);
        } else {
            var shortage = qty - avail;
            $info.removeClass().addClass('avail-status equipment-unavailable').html('<i class="fas fa-exclamation-triangle"></i> '+I18N.shortage.replace('%s', shortage)+' '+line);
        }
    } catch(e) { $info.text(I18N.errorCheck); }
}

function checkAllAvail() {
    $('#equipmentContainer .eq-item-check').each(function() {
        var itId = $(this).data('item');
        checkItemAvail(itId);
    });
}

function applyItemCheckState(itId) {
    var $check = $('#equipmentContainer .eq-item-check[data-item="'+itId+'"]');
    var checked = $check.is(':checked');
    var $card = $check.closest('.eq-item-card');
    var $controls = $card.find('[data-item-controls="'+itId+'"]');
    var $qty = $('#eq_qty_'+itId);
    var $rv = $('#eq_rv_'+itId);
    if (checked) {
        $card.addClass('border-primary bg-primary-light');
        $controls.removeClass('eq-disabled');
        $qty.prop('disabled', false);
        $rv.prop('disabled', false);
        if (parseFloat($rv.val() || '0') === 0) {
            var defaultRv = parseFloat($check.attr('data-rv')) || 0;
            $rv.val(defaultRv.toFixed(2));
        }
    } else {
        $card.removeClass('border-primary bg-primary-light');
        $controls.addClass('eq-disabled');
        $qty.prop('disabled', true);
        $rv.prop('disabled', true);
    }
    checkItemAvail(itId);
    recalc();
}

function refreshCatToggle(catId) {
    var $catBlock = $('.eq-cat-toggle[data-cat="'+catId+'"]').closest('.eq-category-block');
    var $allInCat = $catBlock.find('.eq-item-check').not(function(){ return $(this).closest('.eq-item-wrap').hasClass('eq-hidden'); });
    var total = $allInCat.length;
    var checked = $allInCat.filter(':checked').length;
    var $toggle = $('.eq-cat-toggle[data-cat="'+catId+'"]');
    $toggle.prop('indeterminate', checked > 0 && checked < total);
    $toggle.prop('checked', checked === total && total > 0);
    var $totalSpan = $catBlock.find('.eq-cat-visible-total').first();
    if ($totalSpan.length) $totalSpan.text(total);
}
function refreshAllCatToggles() {
    $('.eq-cat-toggle').each(function() {
        var catId = $(this).data('cat');
        refreshCatToggle(catId);
    });
}

function applyEquipmentFilter(query) {
    var q = (query || '').trim().toLowerCase();
    var $container = $('#equipmentContainer');
    var $noResults = $container.find('.eq-no-results-message');
    var totalVisible = 0;
    $('#equipmentContainer .eq-item-wrap').each(function() {
        var $wrap = $(this);
        var $card = $wrap.find('.eq-item-card').first();
        var name = ($card.attr('data-name') || '').toLowerCase();
        var cat  = ($card.attr('data-cat-name') || '').toLowerCase();
        var visible = !q || name.indexOf(q) !== -1 || cat.indexOf(q) !== -1;
        $wrap.toggleClass('eq-hidden', !visible);
        if (visible) totalVisible++;
    });
    $('#equipmentContainer .eq-category-block').each(function() {
        var $block = $(this);
        var anyVisible = $block.find('.eq-item-wrap').not('.eq-hidden').length > 0;
        $block.toggleClass('eq-hidden', !anyVisible);
    });
    if (!q || totalVisible > 0) {
        if ($noResults.length) $noResults.remove();
    } else {
        if (!$noResults.length) {
            $container.append('<div class="eq-no-results-message eq-no-results"><i class="fas fa-search mr-2"></i>' +
                (window.EQ_I18N && window.EQ_I18N.noResults ? window.EQ_I18N.noResults : 'No items match your search.') +
                '</div>');
        }
    }
    refreshAllCatToggles();
}

$(function() {
    window.EQ_I18N = {
        noResults: <?= json_encode(t('bk.search_no_results') ?? 'No items match your search.') ?>
    };

    // 1) On load — set initial states (cards highlighted / controls enabled) for checked items
    $('#equipmentContainer .eq-item-check').each(function() {
        var itId = $(this).data('item');
        applyItemCheckState(itId);
    });
    refreshAllCatToggles();
    // Seed lastComputed so we don't overwrite a previously-edited quoted total on load
    (function () {
        var sub = 0;
        $('#equipmentContainer .eq-item-check:checked').each(function () {
            var itId = $(this).data('item');
            var q = parseInt($('#eq_qty_'+itId).val()) || 0;
            var r = parseFloat($('#eq_rv_'+itId).val()) || 0;
            sub += q * r;
        });
        var dj = parseFloat($('#djRakAmount').val()) || 0;
        $('#quotedAmount').data('lastComputed', (sub + dj).toFixed(2));
    })();
    recalc();

    // 1b) Recompute totals when DJ RAK amount or Quoted Amount changes.
    // If Quoted Amount is edited manually, recalc won't overwrite it (see logic inside recalc()).
    $('#djRakAmount').on('input change', recalc);

    // 2) Item checkbox click handler
    $('#equipmentContainer').on('change', '.eq-item-check', function() {
        var itId = $(this).data('item');
        var catId = $(this).data('cat');
        applyItemCheckState(itId);
        refreshCatToggle(catId);
    });

    // 2b) Entire card click toggles checkbox (except when clicking inputs/buttons/links inside it)
    $('#equipmentContainer').on('click', '.eq-item-card', function(e) {
        var $target = $(e.target);
        if ($target.closest('input, button, select, textarea, a').length) return;
        var $check = $(this).find('.eq-item-check').first();
        if (!$check.length) return;
        if ($target.hasClass('eq-item-check')) return;
        // Native <label> click already toggles; if target is within label and NOT the input — don't double-toggle
        if ($target.closest('label.eq-item-label').length) return;
        e.preventDefault();
        $check.prop('checked', !$check.prop('checked')).trigger('change');
    });

    // 2c) Category collapse/expand
    $('#equipmentContainer').on('click', '.eq-cat-collapse-btn', function(e) {
        e.preventDefault();
        var catId = $(this).data('cat-toggle');
        var $block = $('.eq-category-block[data-cat-block="'+catId+'"]');
        $block.toggleClass('collapsed-category');
        var expanded = !$block.hasClass('collapsed-category');
        $(this).attr('aria-expanded', expanded ? 'true' : 'false');
    });

    // 3) Category toggle (select/deselect all in category — only visible items)
    $('#equipmentContainer').on('change', '.eq-cat-toggle', function() {
        var catId = $(this).data('cat');
        var on = $(this).prop('checked');
        var $block = $('.eq-category-block[data-cat-block="'+catId+'"]');
        $block.find('.eq-item-wrap').not('.eq-hidden').each(function() {
            var $chk = $(this).find('.eq-item-check');
            if ($chk.length && $chk.is(':checked') !== on) {
                $chk.prop('checked', on).trigger('change');
            }
        });
        refreshCatToggle(catId);
    });

    // 3b) Equipment search
    var $search = $('#equipmentSearch');
    if ($search.length) {
        var lastVal = '';
        $search.on('input', function() {
            var v = $(this).val();
            if (v === lastVal) return;
            lastVal = v;
            applyEquipmentFilter(v);
        });
    }

    // 3c) Select all VISIBLE (after search filter, non-collapsed)
    $(document).on('click', '.eq-select-all-visible', function() {
        $('#equipmentContainer .eq-item-wrap').not('.eq-hidden').each(function() {
            var $block = $(this).closest('.eq-category-block');
            if ($block.hasClass('collapsed-category')) return;
            var $chk = $(this).find('.eq-item-check');
            if ($chk.length && !$chk.is(':checked')) {
                $chk.prop('checked', true).trigger('change');
            }
        });
    });

    // 3d) Clear all item selection
    $(document).on('click', '.eq-clear-all', function() {
        $('#equipmentContainer .eq-item-check:checked').each(function() {
            $(this).prop('checked', false).trigger('change');
        });
    });

    // 4) Qty / Rate input changes → recalc + re-check availability
    $('#equipmentContainer').on('input change', '.eq-qty-input, .eq-rv-input', function() {
        var itId = $(this).data('item');
        recalc();
        checkItemAvail(itId);
    });

    // 5) Date changes → re-check availability of all checked items
    $('#dateFrom, #dateTo').on('change', function() {
        var df = parseDmY($('#dateFrom').val());
        var dt = parseDmY($('#dateTo').val());
        if (df && dt && dt < df) { $('#dateTo').val($('#dateFrom').val()); }
        checkAllAvail();
    });

    // 6) Form submit → client OR-rule validation from earlier
    $('#bookingForm').on('submit', function(e) {
        var clientId = parseInt($('#clientId').val()) || 0;
        var newName = ($('#newClientName').val() || '').trim();
        var newPhone = ($('#newClientPhone').val() || '').trim();
        if (clientId === 0 && (newName === '' || newPhone === '')) {
            alert(I18N.clientRequired);
            e.preventDefault();
            if (newName === '' && newPhone === '') {
                $('#clientId').select2('open');
            } else if (newName === '') {
                $('#newClientName').focus();
            } else {
                $('#newClientPhone').focus();
            }
            return false;
        }
    });

    setTimeout(checkAllAvail, 350);
});
</script>
<?php include SITE_PATH . '/includes/footer.php'; ?>
