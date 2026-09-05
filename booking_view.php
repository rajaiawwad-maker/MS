<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('view_bookings');

$page_title = t('title.booking_view');
$active_nav = 'bookings';

$bookingId = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT b.*, c.name as client_name, c.phone as client_phone, c.alt_phone, c.email as client_email,
    u.name as created_by_name
    FROM bookings b
    INNER JOIN clients c ON b.client_id = c.id
    LEFT JOIN users u ON b.created_by = u.id
    WHERE b.id = ?");
$stmt->execute([$bookingId]);
$booking = $stmt->fetch();
if (!$booking) { setFlash('error', t('err.not_found')); redirect(SITE_URL . '/bookings.php'); }

$stmt = $conn->prepare("SELECT bi.*, it.name as item_name, it.quantity as total_qty, cat.name as category_name
    FROM booking_items bi
    INNER JOIN item_types it ON bi.item_type_id = it.id
    INNER JOIN categories cat ON it.category_id = cat.id
    WHERE bi.booking_id = ? ORDER BY cat.name, it.name");
$stmt->execute([$bookingId]);
$bookingItems = $stmt->fetchAll();

$stmt = $conn->prepare("SELECT p.*, u.name as created_by_name
    FROM payments p LEFT JOIN users u ON p.created_by = u.id
    WHERE p.booking_id = ? ORDER BY p.payment_date DESC, p.id DESC");
$stmt->execute([$bookingId]);
$payments = $stmt->fetchAll();

$collected = getBookingCollectedAmount($bookingId);
$pending = max(0, (float)$booking['quoted_amount'] - $collected);

$stmt = $conn->prepare("SELECT e.*, et.name as type_name, u.name as created_by_name
    FROM expenses e
    INNER JOIN expense_types et ON e.expense_type_id = et.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.booking_id = ? ORDER BY e.date DESC");
$stmt->execute([$bookingId]);
$bookingExpenses = $stmt->fetchAll();

$waLink = ''; $waReminderLink = '';
if (hasPermission('send_whatsapp')) {
    $countryCode = getSetting('whatsapp_country_code', '966');
    $phone = sanitizePhone($booking['client_phone'], $countryCode);
    $confirmUrl = SITE_URL . '/confirm.php?token=' . $booking['customer_confirmation_token'];
    $dateText = $booking['date_from'] === $booking['date_to']
        ? formatDate($booking['date_from'])
        : formatDate($booking['date_from']) . ' ' . t('common.to') . ' ' . formatDate($booking['date_to']);

    $waBookingData = [
        'booking_number' => $booking['booking_number'],
        'event_date_display' => $dateText,
        'location' => $booking['location'],
        'quoted_amount' => $booking['quoted_amount'],
        'customer_confirm_url' => $confirmUrl,
        'items' => array_map(function($bi) {
            return [
                'quantity' => $bi['quantity'],
                'item_type_name' => $bi['item_name'],
                'rental_value' => $bi['rental_value'],
            ];
        }, $bookingItems),
    ];

    $waMsg = buildWhatsAppMessageI18n($waBookingData, $booking['client_name'], $booking['client_phone']);
    $waLink = 'https://wa.me/' . $phone . '?text=' . urlencode($waMsg);

    if ($pending > 0) {
        $reminderMsg = buildWhatsAppReminderI18n(
            array_merge($waBookingData, ['quoted_amount' => $booking['quoted_amount']]),
            $booking['client_name'],
            $collected,
            $pending
        );
        $waReminderLink = 'https://wa.me/' . $phone . '?text=' . urlencode($reminderMsg);
    }
}

include SITE_PATH . '/includes/header.php';
echo flashMessages();
?>
<div class="row mb-3 align-items-end">
    <div class="col-md-6">
        <h1 class="page-title">
            <?= t('bk.booking_label_prefix') ?> <span class="text-primary"><?= e($booking['booking_number']) ?></span>
            <span class="status-badge status-<?= strtolower(str_replace([' ','-','/'],['_','_',''], $booking['status'])) ?> ml-2 align-middle"><?= t_booking_status($booking['status']) ?></span>
            <span class="status-badge status-<?= strtolower(str_replace([' ','-','/'],['_','_',''], $booking['payment_status'])) ?> ml-1 align-middle"><?= t_payment_status($booking['payment_status']) ?></span>
        </h1>
        <p class="page-subtitle"><?= t('bk.created_by_prefix') ?> <?= e($booking['created_by_name'] ?? '') ?> <?= t('bk.on_prefix') ?> <?= formatDateTime($booking['created_at']) ?></p>
    </div>
    <div class="col-md-6 text-md-right">
        <a href="<?= SITE_URL ?>/bookings.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> <?= t('btn.back') ?></a>
        <?php if (hasPermission('edit_bookings')): ?>
            <a href="<?= SITE_URL ?>/booking_form.php?id=<?= $bookingId ?>" class="btn btn-primary"><i class="fas fa-edit"></i> <?= t('common.edit') ?></a>
        <?php endif; ?>
        <a href="<?= SITE_URL ?>/invoice.php?id=<?= $bookingId ?>" target="_blank" class="btn btn-outline-dark"><i class="fas fa-file-invoice mr-1"></i> <?= t('btn.print_invoice') ?></a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-user mr-2"></i><?= t('bk.client_event_info') ?></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-uppercase small text-muted"><?= t('bk.client_label') ?></label>
                        <div class="h5 mb-0 font-weight-bold"><?= e($booking['client_name']) ?></div>
                        <div>
                            <i class="fas fa-phone text-muted mr-1"></i><a href="tel:<?= e($booking['client_phone']) ?>"><?= e($booking['client_phone']) ?></a>
                            <?php if ($booking['alt_phone']): ?> / <a href="tel:<?= e($booking['alt_phone']) ?>"><?= e($booking['alt_phone']) ?></a><?php endif; ?>
                            <?php if ($booking['client_email']): ?><br><i class="fas fa-envelope text-muted mr-1"></i><a href="mailto:<?= e($booking['client_email']) ?>"><?= e($booking['client_email']) ?></a><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-uppercase small text-muted"><?= t('bk.location') ?></label>
                        <div class="h5 mb-0 font-weight-bold"><?= e($booking['location']) ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-uppercase small text-muted"><?= t('bk.date_from') ?></label>
                        <div class="font-weight-semibold"><?= formatDate($booking['date_from']) ?></div>
                        <?php if ($booking['event_start_time']): ?><small class="text-muted"><i class="far fa-clock mr-1"></i><?= date('g:i A', strtotime($booking['event_start_time'])) ?></small><?php endif; ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-uppercase small text-muted"><?= t('bk.date_to') ?></label>
                        <div class="font-weight-semibold"><?= formatDate($booking['date_to']) ?></div>
                        <?php if ($booking['event_end_time']): ?><small class="text-muted"><i class="far fa-clock mr-1"></i><?= date('g:i A', strtotime($booking['event_end_time'])) ?></small><?php endif; ?>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-uppercase small text-muted"><?= t('bk.duration') ?></label>
                        <div class="font-weight-semibold">
                            <?php $days = (strtotime($booking['date_to']) - strtotime($booking['date_from'])) / 86400 + 1;
                            echo $days . ' ' . ($days !== 1 ? t('bk.days') : t('bk.day')); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-boxes mr-2"></i><?= t('bk.equipment_label', [count($bookingItems)]) ?></span>
                <small class="text-muted"><?= t('bk.avail_checked_note') ?></small>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr><th><?= t('th.category') ?></th><th><?= t('th.item_type') ?></th><th class="text-center"><?= t('th.qty') ?></th><th class="text-right"><?= t('th.rate') ?></th><th class="text-right"><?= t('th.subtotal') ?></th><th class="text-right"><?= t('th.availability') ?></th></tr></thead>
                    <tbody>
                        <?php $subtotal = 0; foreach ($bookingItems as $bi):
                            $st = (float)$bi['rental_value'] * (int)$bi['quantity'];
                            $subtotal += $st;
                            $avail = getAvailableQuantity($bi['item_type_id'], $booking['date_from'], $booking['date_to'], $bookingId);
                            $needed = (int)$bi['quantity'];
                            $availClass = $needed <= $avail ? 'equipment-available' : 'equipment-unavailable';
                            $availText = $avail . ' / ' . $bi['total_qty'];
                        ?>
                            <tr>
                                <td><?= e($bi['category_name']) ?></td>
                                <td class="font-weight-semibold"><?= e($bi['item_name']) ?></td>
                                <td class="text-center font-weight-bold"><?= $bi['quantity'] ?></td>
                                <td class="text-right"><?= formatMoney($bi['rental_value']) ?></td>
                                <td class="text-right font-weight-semibold"><?= formatMoney($st) ?></td>
                                <td class="text-right <?= $availClass ?>"><?= $availText ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-light font-weight-bold"><td colspan="4" class="text-right"><?= t('bk.eq_subtotal') ?></td><td class="text-right"><?= formatMoney($subtotal) ?></td><td></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-money-bill mr-2"></i><?= t('bk.payments_heading') ?></span>
                <?php if (hasPermission('record_payments') && $booking['status'] !== 'Canceled'): ?>
                    <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#paymentModal"><i class="fas fa-plus"></i> <?= t('bk.record_payment') ?></button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($payments)): ?>
                    <div class="text-center text-muted py-5"><?= t('bk.no_payments') ?></div>
                <?php else: ?>
                    <table class="table mb-0">
                        <thead><tr><th><?= t('th.date') ?></th><th><?= t('th.method') ?></th><th><?= t('th.reference') ?></th><th class="text-right"><?= t('th.amount') ?></th><th><?= t('th.notes') ?></th><th><?= t('th.by') ?></th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><?= formatDate($p['payment_date']) ?></td>
                                    <td><?= t_payment_method($p['payment_method'] ?? '-') ?></td>
                                    <td><?= e($p['reference'] ?? '-') ?></td>
                                    <td class="text-right font-weight-semibold text-success"><?= formatMoney($p['amount']) ?></td>
                                    <td><small class="text-muted"><?= e($p['notes'] ?? '-') ?></small></td>
                                    <td><small class="text-muted"><?= e($p['created_by_name'] ?? '') ?></small></td>
                                    <td>
                                        <?php if (hasPermission('record_payments')): ?>
                                            <form method="POST" action="<?= SITE_URL ?>/payment_action.php" class="d-inline confirm-delete">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                <input type="hidden" name="bk" value="<?= $bookingId ?>">
                                                <input type="hidden" name="ref" value="booking">
                                                <?php csrf_field(); ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="bg-light font-weight-bold"><td colspan="3" class="text-right"><?= t('th.total_collected') ?></td><td class="text-right text-success"><?= formatMoney($collected) ?></td><td colspan="3"></td></tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <?php if (hasPermission('view_dj_rak') && !empty($booking['internal_notes'])): ?>
            <div class="card mb-3 border-warning">
                <div class="card-header bg-warning text-dark"><i class="fas fa-user-secret mr-2"></i><?= t('bk.internal_notes') ?> <small class="ml-2"><?= t('bk.never_shared_note') ?></small></div>
                <div class="card-body"><p class="mb-0"><?= nl2br(e($booking['internal_notes'])) ?></p></div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3 summary-box">
            <div class="card-body">
                <h5 class="mb-3"><i class="fas fa-calculator mr-2"></i><?= t('bk.financial_summary') ?></h5>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <div class="text-uppercase small text-muted"><?= t('bk.quoted_amount') ?></div>
                    </div>
                    <div class="h5 mb-0 font-weight-bold"><?= formatMoney($booking['quoted_amount']) ?></div>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <div class="text-uppercase small text-muted"><?= t('pay.collected') ?></div>
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-success"><?= formatMoney($collected) ?></div>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <div class="text-uppercase small text-muted"><?= t('pay.pending') ?></div>
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-danger"><?= formatMoney($pending) ?></div>
                </div>
                <?php if (hasPermission('view_dj_rak')): ?>
                <div class="d-flex justify-content-between align-items-center py-2">
                    <div>
                        <div class="text-uppercase small text-muted"><?= t('th.dj_rak') ?> <span class="text-info" data-toggle="tooltip" title="<?= te('bk.djrak_help') ?>"><i class="fas fa-info-circle"></i></span></div>
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-info"><?= formatMoney($booking['dj_rak_amount']) ?></div>
                </div>
                <?php endif; ?>
                <hr>
                <div class="progress mb-2" style="height: 10px;">
                    <?php $pct = $booking['quoted_amount'] > 0 ? min(100, ($collected / $booking['quoted_amount']) * 100) : 0; ?>
                    <div class="progress-bar bg-success" style="width: <?= $pct ?>%"><?= round($pct) ?>%</div>
                </div>
                <small class="text-muted"><?= round($pct) ?><?= t('bk.pct_of_quoted') ?></small>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-share-alt mr-2"></i><?= t('bk.share_actions') ?></div>
            <div class="card-body">
                <?php if (hasPermission('send_whatsapp') && isset($_GET['wa'])): ?>
                    <div class="alert alert-success py-2 small"><i class="fab fa-whatsapp mr-1"></i> <?= t('bk.wa_click_prompt') ?></div>
                <?php endif; ?>
                <?php if (hasPermission('send_whatsapp')): ?>
                    <a href="<?= e($waLink) ?>" target="_blank" class="btn btn-whatsapp btn-block mb-2"><i class="fab fa-whatsapp mr-1"></i> <?= t('bk.send_wa_btn') ?></a>
                    <?php if ($waReminderLink): ?>
                        <a href="<?= e($waReminderLink) ?>" target="_blank" class="btn btn-outline-success btn-block mb-2"><i class="fas fa-bell mr-1"></i> <?= t('bk.wa_payment_reminder') ?></a>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($booking['customer_confirmation_token']): ?>
                    <div class="form-group mb-2">
                        <label class="small font-weight-semibold"><?= t('bk.confirm_link_label') ?></label>
                        <div class="input-group input-group-sm">
                            <input type="text" readonly class="form-control" value="<?= SITE_URL ?>/confirm.php?token=<?= e($booking['customer_confirmation_token']) ?>">
                            <div class="input-group-append"><button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard?.writeText(this.previousElementSibling.value);this.innerHTML='<?= t('common.copied') ?>'"><i class="fas fa-copy"></i></button></div>
                        </div>
                    </div>
                    <?php if ($booking['customer_confirmed_at']): ?>
                        <div class="alert alert-success py-2 small mb-2">
                            <i class="fas fa-check-circle mr-1"></i> <?= t('bk.confirmed_by_customer_on') ?> <?= formatDateTime($booking['customer_confirmed_at']) ?>
                            (<?= e($booking['customer_response'] ?? '') ?>)
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <hr>
                <a href="<?= SITE_URL ?>/calendar_download.php?id=<?= $bookingId ?>" class="btn btn-outline-dark btn-block mb-2"><i class="fas fa-calendar-plus mr-1"></i> <?= t('bk.add_to_calendar') ?></a>
                <a href="<?= SITE_URL ?>/reports_client_statement.php?client_id=<?= $booking['client_id'] ?>" class="btn btn-outline-secondary btn-block"><i class="fas fa-file-alt mr-1"></i> <?= t('bk.client_statement') ?></a>
                <hr>
                <?php if ($booking['status'] !== 'Canceled' && hasPermission('cancel_bookings')): ?>
                    <form method="POST" action="<?= SITE_URL ?>/booking_action.php" class="d-block mb-2 confirm-action" data-confirm="<?= te('bk.cancel_release_confirm') ?>">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="id" value="<?= $bookingId ?>">
                        <?php csrf_field(); ?>
                        <button type="submit" class="btn btn-outline-danger btn-block"><i class="fas fa-times mr-1"></i> <?= t('common.cancel') ?> <?= t('bk.info_title') ?></button>
                    </form>
                <?php endif; ?>
                <?php if (hasPermission('edit_bookings') && $booking['status'] !== 'Closed' && $booking['status'] !== 'Canceled'):
                    $next = [
                        'Draft' => ['Quotation', t('bk.mark_as_quotation')],
                        'Quotation' => ['Confirmed', t('bk.mark_as_confirmed')],
                        'Confirmed' => ['Event Completed', t('bk.mark_as_event_completed')],
                        'Event Completed' => ['Closed', t('bk.mark_as_closed')],
                        'Change Requested' => ['Confirmed', t('bk.mark_as_confirmed')],
                    ];
                    if (isset($next[$booking['status']])):
                        [$ns, $lbl] = $next[$booking['status']];
                ?>
                    <form method="POST" action="<?= SITE_URL ?>/booking_action.php" class="d-block confirm-action" data-confirm="<?= te('msg.change_status_to', [$ns]) ?>">
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="id" value="<?= $bookingId ?>">
                        <input type="hidden" name="to" value="<?= $ns ?>">
                        <?php csrf_field(); ?>
                        <button type="submit" class="btn btn-success btn-block"><i class="fas fa-forward mr-1"></i> <?= $lbl ?></button>
                    </form>
                <?php endif; endif; ?>
            </div>
        </div>

        <?php if (!empty($bookingExpenses)): ?>
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-money-check mr-2"></i><?= t('bk.related_expenses') ?></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th><?= t('th.date') ?></th><th><?= t('th.type') ?></th><th class="text-right"><?= t('th.amount') ?></th></tr></thead>
                        <tbody>
                            <?php $expTotal = 0; foreach ($bookingExpenses as $e): $expTotal += (float)$e['amount']; ?>
                                <tr><td><?= formatDate($e['date']) ?></td><td><?= e($e['type_name']) ?></td><td class="text-right"><?= formatMoney($e['amount']) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr class="bg-light font-weight-bold"><td colspan="2" class="text-right"><?= t('exp.total_expenses') ?></td><td class="text-right text-danger"><?= formatMoney($expTotal) ?></td></tr></tfoot>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (hasPermission('record_payments') && $booking['status'] !== 'Canceled'): ?>
<div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form class="modal-content" method="POST" action="<?= SITE_URL ?>/payment_action.php">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="bk" value="<?= $bookingId ?>">
            <input type="hidden" name="ref" value="booking">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle mr-2"></i><?= t('pay.add_title') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?= t('common.close') ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3">
                    <?= t('pay.quoted_label') ?> <strong><?= formatMoney($booking['quoted_amount']) ?></strong> | <?= t('pay.collected_label') ?> <strong><?= formatMoney($collected) ?></strong> | <?= t('pay.pending_label') ?> <strong class="text-danger"><?= formatMoney($pending) ?></strong>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label><?= t('pay.date_label') ?> <span class="text-danger">*</span></label>
                        <input type="text" required name="payment_date" class="form-control datepicker" value="<?= date('d/m/Y') ?>" autocomplete="off">
                    </div>
                    <div class="form-group col-md-6">
                        <label><?= t('pay.amount_label') ?> <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" required name="amount" class="form-control" value="<?= round($pending, 2) ?>" min="0.01">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label><?= t('pay.method_label') ?></label>
                        <select name="payment_method" class="form-control">
                            <option value=""><?= t('common.select_option') ?></option>
                            <option value="Cash"><?= t('pm.cash') ?></option>
                            <option value="Transfer"><?= t('pm.transfer') ?></option>
                            <option value="CliQ"><?= t('pm.cliq') ?></option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label><?= t('pay.reference_label') ?></label>
                        <input type="text" name="reference" class="form-control" placeholder="<?= te('pay.reference_ph') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label><?= t('pay.notes_label') ?></label>
                    <textarea name="notes" rows="2" class="form-control" placeholder="<?= te('pay.notes_ph') ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= t('common.cancel') ?></button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> <?= t('bk.record_payment') ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include SITE_PATH . '/includes/footer.php'; ?>
