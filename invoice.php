<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
requirePermission('view_bookings');

$bookingId = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT b.*, c.name as client_name, c.phone as client_phone, c.alt_phone, c.email as client_email, c.address as client_address,
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

$subtotal = 0;
foreach ($bookingItems as $bi) {
    $subtotal += (float)$bi['rental_value'] * (int)$bi['quantity'];
}
$djRak = (float)$booking['dj_rak_amount'];
$tax = 0;
$discount = 0;
$itemsTotal = $subtotal + $djRak;
$quoted = (float)$booking['quoted_amount'];
if ($quoted <= 0) $quoted = $itemsTotal;
$grandTotal = $quoted;
$pending = max(0, $quoted - $collected);

$invoiceDate = new DateTime($booking['created_at'] ?: 'now');
$dueDate = (clone $invoiceDate)->modify('+7 days');
$days = (strtotime($booking['date_to']) - strtotime($booking['date_from'])) / 86400 + 1;

$companyName = getSetting('company_name', t('inv.company_name'));
$companyTagline = getSetting('company_tagline', t('inv.company_tagline'));
$companyPhone = getSetting('company_phone', '');
$companyEmail = getSetting('company_email', '');
$companyAddress = getSetting('company_address', '');
$companyVat = getSetting('company_vat_no', '');

$page_title = t('title.invoice') . ' #' . $booking['booking_number'];
$active_nav = 'bookings';
?><!DOCTYPE html>
<html lang="<?= LANG_CODE ?>" dir="<?= IS_RTL ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=<?= time() ?>">
<?php if (IS_RTL): ?>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/rtl.css?v=<?= time() ?>">
<?php endif; ?>
<style>
html, body { background: #f0f2f5; }
.invoice-toolbar {
    padding: 14px 18px;
    background: #fff;
    border-bottom: 1px solid #e3e6f0;
    position: sticky;
    top: 0;
    z-index: 20;
    box-shadow: 0 1px 6px rgba(0,0,0,0.06);
}
.invoice-shell {
    max-width: 900px;
    margin: 28px auto;
    background: #fff;
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    padding: 56px 64px;
}
.invoice-brand h1 {
    font-size: 28px;
    font-weight: 800;
    color: #1e293b;
    margin: 0;
    letter-spacing: -0.3px;
}
.invoice-brand .tagline {
    color: #64748b;
    font-size: 13px;
    margin-top: 4px;
}
.invoice-brand .logo-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    box-shadow: 0 6px 14px rgba(37, 99, 235, 0.25);
}
.invoice-badge {
    text-align: right;
}
html[dir="rtl"] .invoice-badge { text-align: left; }
.invoice-badge .label {
    font-size: 11px;
    letter-spacing: 2.5px;
    color: #94a3b8;
    font-weight: 700;
    text-transform: uppercase;
    margin-bottom: 4px;
}
.invoice-badge h2 {
    font-size: 34px;
    font-weight: 900;
    color: #1e293b;
    margin: 0 0 6px;
    letter-spacing: -0.5px;
}
.invoice-badge .meta {
    color: #475569;
    font-size: 13px;
    margin: 2px 0;
}
.invoice-badge .meta b {
    color: #0f172a;
    font-weight: 700;
}
.invoice-party {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px 22px;
    margin-top: 30px;
}
.invoice-party h4 {
    font-size: 12px;
    letter-spacing: 1.8px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    margin: 0 0 8px;
}
.invoice-party .name {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 2px;
}
.invoice-party .line {
    color: #475569;
    font-size: 13px;
    margin: 2px 0;
}
.invoice-party .line i {
    width: 14px;
    color: #94a3b8;
    margin-right: 6px;
    text-align: center;
}
html[dir="rtl"] .invoice-party .line i { margin-right: 0; margin-left: 6px; }
.invoice-event {
    margin-top: 22px;
    padding: 14px 18px;
    border-left: 3px solid #2563eb;
    background: #f1f5f9;
    border-radius: 6px;
}
html[dir="rtl"] .invoice-event { border-left: none; border-right: 3px solid #2563eb; }
.invoice-event .lbl {
    font-size: 11px;
    letter-spacing: 1.5px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
}
.invoice-event .val {
    font-weight: 700;
    color: #0f172a;
    font-size: 14px;
    margin-top: 2px;
}
.invoice-table {
    margin-top: 30px;
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #cbd5e1;
    background: #fff;
}
.invoice-table thead th {
    background: #0f172a;
    color: #f8fafc;
    padding: 12px 14px;
    font-size: 12px;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    font-weight: 700;
    border: 1px solid #1e293b;
    border-bottom: 2px solid #334155;
}
.invoice-table tbody td {
    padding: 14px;
    border: 1px solid #e2e8f0;
    color: #1e293b;
    vertical-align: top;
    font-size: 14px;
    background: #fff;
}
.invoice-table tbody tr:hover { background: #fafbfc; }
.invoice-table tbody tr:hover td { background: #fafbfc; }
.invoice-table .cat {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1px;
    padding: 2px 8px;
    background: #ede9fe;
    color: #6d28d9;
    border-radius: 999px;
    text-transform: uppercase;
    margin-bottom: 4px;
}
.invoice-table .desc { font-weight: 700; color: #0f172a; }
.invoice-table .num { text-align: right; font-variant-numeric: tabular-nums; }
.invoice-table .cntr { text-align: center; }
.invoice-table tfoot td {
    padding: 11px 14px;
    font-size: 14px;
    color: #334155;
    border: 1px solid #e2e8f0;
    background: #fff;
}
.invoice-table tfoot .lbl {
    text-align: right;
    font-weight: 600;
    letter-spacing: 0.3px;
}
.invoice-table tfoot tr.subtotal td { border-top: 2px solid #cbd5e1; }
.invoice-table tfoot tr.grand {
    background: linear-gradient(135deg, #0f172a, #1e293b);
    color: #fff;
    font-size: 18px;
}
.invoice-table tfoot tr.grand td {
    padding: 16px 14px;
    color: #fff !important;
    border: 1px solid #0f172a;
    border-top: 2px solid #334155;
}
.invoice-table tfoot tr.grand .lbl { color: #cbd5e1; }
.invoice-table tfoot tr.grand .num { color: #fff; font-weight: 900; font-size: 22px; }
.invoice-foot {
    margin-top: 40px;
    display: grid;
    grid-template-columns: 1.1fr 1fr;
    gap: 32px;
}
html[dir="rtl"] .invoice-foot { direction: rtl; }
.payments-box h5, .terms-box h5, .sigs-box h5 {
    font-size: 12px;
    letter-spacing: 1.8px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    margin: 0 0 12px;
}
.payments-box .pay-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px dashed #e2e8f0;
    font-size: 13px;
    color: #334155;
}
.payments-box .pay-row:last-child { border: none; }
.payments-box .pay-row .amt { color: #16a34a; font-weight: 700; }
.payments-box .empty { color: #94a3b8; font-size: 13px; padding: 6px 0; }
.balance-strip {
    margin-top: 12px;
    padding: 14px 16px;
    border-radius: 10px;
    background: linear-gradient(135deg, #ecfeff, #ecfccb);
    font-weight: 800;
    color: #065f46;
    display: flex;
    justify-content: space-between;
}
.balance-strip.due { background: linear-gradient(135deg, #fff7ed, #fef2f2); color: #991b1b; }
.terms-box .term-item {
    font-size: 13px;
    color: #475569;
    padding: 4px 0;
    line-height: 1.6;
}
.sigs-box {
    margin-top: 38px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 28px;
}
.sig {
    border-top: 1px solid #94a3b8;
    padding-top: 10px;
    text-align: center;
    font-size: 13px;
    color: #64748b;
    font-weight: 600;
}
.invoice-footnote {
    margin-top: 24px;
    text-align: center;
    color: #94a3b8;
    font-size: 12px;
    border-top: 1px solid #f1f5f9;
    padding-top: 18px;
}
.invoice-footnote .thanks {
    font-size: 14px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 4px;
}
@media print {
    @page {
        size: A4 portrait !important;
        margin: 9mm 10.5mm 10mm 10.5mm !important;
    }
    html {
        width: 210mm !important;
        max-width: 210mm !important;
        min-width: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        direction: ltr !important;
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
    }
    body {
        background: #fff !important;
        margin: 0 !important;
        padding: 0 !important;
        font-size: 9pt;
        line-height: 1.25 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
        -webkit-font-smoothing: antialiased;
        overflow: visible !important;
        direction: ltr !important;
        unicode-bidi: plaintext;
        orphans: 2;
        widows: 2;
        width: 210mm !important;
        max-width: 210mm !important;
        min-width: 0 !important;
        height: auto !important;
        min-height: 0 !important;
    }
    .container, .container-fluid, .container-sm, .container-md,
    .container-lg, .container-xl, .row, .col, .col-1, .col-2,
    .col-3, .col-4, .col-5, .col-6, .col-7, .col-8, .col-9,
    .col-10, .col-11, .col-12, .col-sm, .col-sm-1, .col-sm-2,
    .col-sm-3, .col-sm-4, .col-sm-5, .col-sm-6, .col-sm-7,
    .col-sm-8, .col-sm-9, .col-sm-10, .col-sm-11, .col-sm-12,
    .col-md, .col-md-1, .col-md-2, .col-md-3, .col-md-4,
    .col-md-5, .col-md-6, .col-md-7, .col-md-8, .col-md-9,
    .col-md-10, .col-md-11, .col-md-12, .col-lg, .col-lg-1,
    .col-lg-2, .col-lg-3, .col-lg-4, .col-lg-5, .col-lg-6,
    .col-lg-7, .col-lg-8, .col-lg-9, .col-lg-10, .col-lg-11,
    .col-lg-12, .col-xl, .col-xl-1, .col-xl-2, .col-xl-3,
    .col-xl-4, .col-xl-5, .col-xl-6, .col-xl-7, .col-xl-8,
    .col-xl-9, .col-xl-10, .col-xl-11, .col-xl-12 {
        padding: 0 !important;
        margin: 0 !important;
        width: auto !important;
        max-width: none !important;
        flex: 0 0 auto !important;
        flex-basis: auto !important;
        flex-grow: 0 !important;
        flex-shrink: 0 !important;
        display: block !important;
    }
    .invoice-toolbar { display: none !important; visibility: hidden !important; height: 0 !important; }
    .d-none { display: none !important; }
    .d-sm-none { display: none !important; }
    .d-none\.d-sm-inline { display: none !important; }
    .invoice-shell {
        box-shadow: none !important;
        text-shadow: none !important;
        border: none !important;
        margin: 0 auto !important;
        padding: 0 !important;
        max-width: 189mm !important;
        width: 189mm !important;
        min-width: 189mm !important;
        max-height: 278mm !important;
        background: #fff !important;
        color: #000 !important;
        display: block !important;
        float: none !important;
        clear: both !important;
        overflow: visible !important;
        page-break-after: auto;
        page-break-inside: auto;
        direction: ltr !important;
    }
    html[dir="rtl"] .invoice-shell { direction: rtl !important; }
    .invoice-shell > .row,
    .invoice-party.row,
    .invoice-event.row {
        display: block !important;
        float: none !important;
        width: 189mm !important;
        max-width: 189mm !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
        direction: inherit !important;
    }
    .invoice-shell > .row:after,
    .invoice-party.row:after,
    .invoice-event.row:after {
        content: "" !important;
        display: block !important;
        clear: both !important;
        height: 0 !important;
        visibility: hidden !important;
    }
    .invoice-shell .row > [class*="col-"] {
        padding: 0 !important;
        margin: 0 !important;
        display: block !important;
        float: left !important;
        position: static !important;
        overflow: hidden !important;
        direction: inherit !important;
    }
    html[dir="rtl"] .invoice-shell .row > [class*="col-"] { float: right !important; }
    .invoice-shell .row > .col-sm-6,
    .invoice-shell .row > .col-md-6 {
        width: 48% !important;
        max-width: 48% !important;
    }
    .invoice-shell > .row > .col-sm-6:first-child,
    .invoice-party.row > .col-md-6:first-child { margin-right: 4% !important; }
    html[dir="rtl"] .invoice-shell > .row > .col-sm-6:first-child,
    html[dir="rtl"] .invoice-party.row > .col-md-6:first-child {
        margin-right: 0 !important;
        margin-left: 4% !important;
    }
    .invoice-event.row > .col-md-4 { width: 30% !important; max-width: 30% !important; }
    .invoice-event.row > .col-md-5 { width: 40% !important; max-width: 40% !important; }
    .invoice-event.row > .col-md-3 { width: 24% !important; max-width: 24% !important; }
    .invoice-event.row > .col-md-4 { margin-right: 3% !important; }
    .invoice-event.row > .col-md-5 { margin-right: 3% !important; }
    html[dir="rtl"] .invoice-event.row > .col-md-4 { margin-right: 0 !important; margin-left: 3% !important; }
    html[dir="rtl"] .invoice-event.row > .col-md-5 { margin-right: 0 !important; margin-left: 3% !important; }
    [class*="badge"][data-toggle="tooltip"] { pointer-events: none !important; }
    .badge {
        padding: 1pt 2.5pt !important;
        font-size: 7pt !important;
        border: 0.3pt solid currentColor !important;
        line-height: 1 !important;
        display: inline-block !important;
    }
    .invoice-shell .mb-3 { margin-bottom: 2mm !important; }
    .invoice-shell .mb-md-0 { margin-bottom: 0 !important; }
    .invoice-brand { display: block !important; float: left !important; width: auto !important; max-width: 92% !important; }
    html[dir="rtl"] .invoice-brand { float: right !important; }
    .invoice-brand h1 { font-size: 14.5pt; line-height: 1; margin: 0 !important; }
    .invoice-brand .tagline {
        font-size: 7.5pt;
        line-height: 1.2;
        white-space: normal;
        overflow: hidden;
    }
    .invoice-brand .logo-icon {
        width: 30pt;
        height: 30pt;
        font-size: 14pt;
        border-radius: 6pt;
        margin-right: 7pt !important;
        float: left !important;
    }
    html[dir="rtl"] .invoice-brand .logo-icon {
        margin-right: 0 !important;
        margin-left: 7pt !important;
        float: right !important;
    }
    .invoice-badge { text-align: right !important; }
    html[dir="rtl"] .invoice-badge { text-align: left !important; }
    .invoice-badge h2 {
        font-size: 17pt;
        line-height: 1;
        margin: 0 0 0.6mm !important;
        white-space: nowrap !important;
    }
    .invoice-badge .meta {
        font-size: 8pt;
        line-height: 1.3;
        margin: 0 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis;
        max-width: 100% !important;
        display: block !important;
    }
    .invoice-party {
        padding: 1.6mm 2mm !important;
        margin-top: 2.3mm !important;
        page-break-inside: avoid !important;
        page-break-after: avoid !important;
        border-radius: 0 !important;
    }
    .invoice-party h4 {
        font-size: 7.1pt;
        margin: 0 0 0.8mm !important;
        white-space: nowrap !important;
    }
    .invoice-party .name {
        font-size: 10.2pt;
        margin-bottom: 0 !important;
        line-height: 1.12;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100% !important;
    }
    .invoice-party .line {
        font-size: 8pt;
        line-height: 1.28;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis;
        max-width: 100% !important;
        display: block !important;
    }
    .invoice-party .line i { font-size: 6.4pt; }
    .invoice-event {
        margin-top: 1.9mm !important;
        padding: 0.9mm 1.8mm !important;
        page-break-inside: avoid !important;
        page-break-after: avoid !important;
        border-radius: 0 !important;
    }
    .invoice-event .lbl {
        font-size: 6.6pt;
        letter-spacing: 0.4pt;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis;
    }
    .invoice-event .val {
        font-size: 8.3pt;
        line-height: 1.16;
        margin-top: 0.1mm !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis;
        max-width: 100% !important;
    }
    .invoice-event .small { font-size: 6.6pt; }
    .invoice-table {
        margin-top: 2.3mm !important;
        width: 189mm !important;
        min-width: 189mm !important;
        max-width: 189mm !important;
        table-layout: fixed !important;
        border-collapse: collapse !important;
        border: 0.5pt solid #94a3b8 !important;
        page-break-inside: auto !important;
        float: none !important;
        clear: both !important;
        direction: ltr !important;
    }
    html[dir="rtl"] .invoice-table { direction: rtl !important; }
    .invoice-table colgroup col { display: table-column !important; width: auto !important; }
    .invoice-table thead th {
        padding: 1.1mm 1.3mm !important;
        font-size: 7.5pt;
        border: 0.5pt solid #1e293b !important;
        border-bottom: 0.8pt solid #334155 !important;
        background: #0f172a !important;
        color: #f8fafc !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-align: center !important;
        line-height: 1.12;
    }
    .invoice-table thead th:first-child { text-align: left !important; }
    html[dir="rtl"] .invoice-table thead th:first-child { text-align: right !important; }
    .invoice-table thead th.num,
    .invoice-table thead th.cntr { text-align: center !important; }
    .invoice-table tbody td {
        padding: 0.8mm 1.1mm !important;
        font-size: 8.3pt;
        border: 0.5pt solid #cbd5e1 !important;
        line-height: 1.12 !important;
        background: #fff !important;
        color: #000 !important;
        overflow: hidden !important;
    }
    .invoice-table tbody td .small { font-size: 6.6pt; }
    .invoice-table tbody td .cat {
        font-size: 5.8pt;
        padding: 0.1pt 2pt !important;
        letter-spacing: 0.1pt !important;
        border-radius: 0 !important;
        margin-bottom: 0.4mm !important;
        white-space: nowrap !important;
    }
    .invoice-table tbody td .desc {
        font-size: 8.3pt;
        line-height: 1.1;
        white-space: normal;
        overflow: visible;
        max-width: 100% !important;
        word-wrap: break-word;
        word-break: break-word;
    }
    .invoice-table .num {
        text-align: right !important;
        font-variant-numeric: tabular-nums !important;
        direction: ltr !important;
        unicode-bidi: embed;
        white-space: nowrap !important;
    }
    html[dir="rtl"] .invoice-table .num {
        text-align: left !important;
        direction: ltr !important;
        unicode-bidi: embed;
    }
    .invoice-table .cntr { text-align: center !important; }
    .invoice-table tfoot td {
        padding: 0.9mm 1.1mm !important;
        font-size: 8.3pt;
        border: 0.5pt solid #cbd5e1 !important;
        background: #fff !important;
        color: #000 !important;
        white-space: nowrap !important;
        line-height: 1.12;
    }
    .invoice-table tfoot tr.subtotal td { border-top: 0.8pt solid #94a3b8 !important; }
    .invoice-table tfoot tr.grand td {
        padding: 1.4mm 1.1mm !important;
        border: 0.5pt solid #0f172a !important;
        border-top: 0.8pt solid #334155 !important;
        background: #0f172a !important;
        color: #fff !important;
    }
    .invoice-table tfoot tr.grand .lbl {
        font-size: 9.1pt;
        color: #cbd5e1 !important;
    }
    .invoice-table tfoot tr.grand .num {
        font-size: 11pt !important;
        color: #fff !important;
        direction: ltr !important;
    }
    .invoice-foot {
        margin-top: 3.1mm !important;
        gap: 3.3mm !important;
        page-break-inside: avoid !important;
        page-break-after: avoid !important;
        display: grid !important;
        grid-template-columns: 1.1fr 1fr !important;
    }
    html[dir="rtl"] .invoice-foot { direction: rtl !important; }
    .payments-box, .terms-box {
        page-break-inside: avoid !important;
        display: block !important;
        float: none !important;
        min-width: 0 !important;
    }
    .payments-box h5, .terms-box h5, .sigs-box h5 {
        font-size: 7.2pt;
        margin: 0 0 0.8mm !important;
        letter-spacing: 0.6pt;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis;
    }
    .payments-box .pay-row {
        font-size: 7.8pt;
        padding: 0.35mm 0 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis;
        line-height: 1.22;
    }
    .balance-strip {
        margin-top: 0.8mm !important;
        padding: 0.9mm 1.6mm !important;
        font-size: 8.3pt;
        page-break-inside: avoid !important;
        border-radius: 0 !important;
        white-space: nowrap !important;
    }
    .balance-strip.due { background: #fff7ed !important; color: #991b1b !important; }
    .terms-box .term-item {
        font-size: 7.4pt;
        padding: 0.1mm 0 !important;
        line-height: 1.22;
        overflow: hidden !important;
    }
    .sigs-box {
        margin-top: 3.4mm !important;
        gap: 3.3mm !important;
        page-break-inside: avoid !important;
        page-break-after: avoid !important;
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
    }
    .sig {
        font-size: 7.8pt;
        padding-top: 1mm !important;
        border-top: 0.3pt solid #94a3b8 !important;
        white-space: nowrap !important;
    }
    .invoice-footnote {
        margin-top: 2.5mm !important;
        padding-top: 1.1mm !important;
        font-size: 6.7pt;
        page-break-inside: avoid !important;
        page-break-after: avoid !important;
        border-top: 0.3pt solid #f1f5f9 !important;
        text-align: center !important;
        line-height: 1.2;
    }
    .invoice-footnote .thanks {
        font-size: 8.3pt;
        margin-bottom: 0.2mm !important;
    }
    html[dir="rtl"] .invoice-party {
        padding: 1.9mm 2.3mm !important;
        margin-top: 2.3mm !important;
    }
    html[dir="rtl"] .invoice-party h4 {
        margin: 0 0 0.8mm !important;
    }
    html[dir="rtl"] .invoice-party .name {
        line-height: 1.14 !important;
    }
    html[dir="rtl"] .invoice-party .line {
        line-height: 1.3 !important;
    }
    html[dir="rtl"] .invoice-event {
        padding: 1.1mm 2.1mm !important;
        margin-top: 1.9mm !important;
    }
    html[dir="rtl"] .invoice-event .val {
        line-height: 1.18 !important;
    }
    html[dir="rtl"] .invoice-table thead th {
        padding: 1.25mm 1.45mm !important;
    }
    html[dir="rtl"] .invoice-table tbody td {
        padding: 0.95mm 1.25mm !important;
        line-height: 1.14 !important;
    }
    html[dir="rtl"] .invoice-table tbody td .desc {
        line-height: 1.12 !important;
    }
    html[dir="rtl"] .invoice-table tfoot td {
        padding: 1mm 1.25mm !important;
    }
    html[dir="rtl"] .invoice-table tfoot tr.grand td {
        padding: 1.55mm 1.25mm !important;
    }
    html[dir="rtl"] .invoice-foot {
        gap: 3.5mm !important;
        margin-top: 3.1mm !important;
    }
    html[dir="rtl"] .payments-box .pay-row,
    html[dir="rtl"] .terms-box .term-item {
        line-height: 1.24 !important;
    }
    html[dir="rtl"] .sigs-box {
        gap: 3.5mm !important;
        margin-top: 3.4mm !important;
    }
    html[dir="rtl"] .invoice-footnote {
        margin-top: 2.5mm !important;
    }
    html[dir="rtl"] .invoice-badge h2 {
        margin: 0 0 0.6mm !important;
    }
    html[dir="rtl"] .invoice-badge .meta {
        line-height: 1.32 !important;
    }
    html[dir="rtl"] .invoice-brand .tagline {
        line-height: 1.22 !important;
    }
    .invoice-table tr { page-break-inside: avoid !important; page-break-after: auto !important; }
    .invoice-table thead { display: table-header-group !important; }
    .invoice-table tfoot { display: table-row-group !important; }
    a, a * {
        color: inherit !important;
        text-decoration: none !important;
        pointer-events: none !important;
        cursor: default !important;
    }
    img, svg, canvas { max-width: 100% !important; page-break-inside: avoid !important; }
    * { box-shadow: none !important; text-shadow: none !important; box-sizing: border-box !important; }
    .mr-1, .ml-1 { margin-right: 1pt !important; margin-left: 1pt !important; }
    .mr-2, .ml-2 { margin-right: 2pt !important; margin-left: 2pt !important; }
    .mr-3, .ml-3 { margin-right: 3.5pt !important; margin-left: 3.5pt !important; }
    .text-muted { color: #64748b !important; -webkit-print-color-adjust: exact !important; }
    .text-dark { color: #0f172a !important; }
    .text-success { color: #15803d !important; -webkit-print-color-adjust: exact !important; }
    .text-info { color: #0369a1 !important; -webkit-print-color-adjust: exact !important; }
    .text-danger { color: #b91c1c !important; -webkit-print-color-adjust: exact !important; }
    .font-weight-bold, .font-weight-semibold, .font-weight-800 { font-weight: 700 !important; }
    br { page-break-after: auto !important; }
    .d-flex { display: block !important; float: none !important; }
}
@media (max-width: 768px) {
    .invoice-shell { padding: 28px 22px; margin: 12px 0; }
    .invoice-foot { grid-template-columns: 1fr; gap: 20px; }
    .sigs-box { grid-template-columns: 1fr; }
    .invoice-badge { text-align: left; margin-top: 18px; }
    html[dir="rtl"] .invoice-badge { text-align: right; }
}
</style>
</head>
<body>
<div class="invoice-toolbar d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center">
        <a href="<?= SITE_URL ?>/booking_view.php?id=<?= $bookingId ?>" class="btn btn-outline-secondary mr-2"><i class="fas fa-arrow-left"></i> <span class="d-none d-sm-inline"><?= t('btn.back') ?></span></a>
        <strong class="mr-3"><?= t('common.invoice') ?> #<?= e($booking['booking_number']) ?></strong>
        <span class="badge badge-pill badge-<?= $booking['payment_status'] === 'Fully Collected' ? 'success' : ($pending > 0 ? 'warning' : 'secondary') ?> mr-2">
            <?= t_payment_status($booking['payment_status']) ?>
        </span>
        <span class="badge badge-pill badge-light border text-muted"><?= t_booking_status($booking['status']) ?></span>
    </div>
    <div>
        <a href="<?= SITE_URL ?>/change_lang.php?lang=<?= IS_RTL ? 'en' : 'ar' ?>" class="btn btn-sm btn-outline-secondary mr-2">
            <i class="fas fa-globe"></i> <?= IS_RTL ? 'EN' : 'ع' ?>
        </a>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print mr-1"></i> <?= t('btn.print_invoice') ?>
        </button>
    </div>
</div>

<div class="invoice-shell">
    <div class="row mb-3">
        <div class="col-12 col-sm-6 d-flex align-items-start">
            <div class="logo-icon mr-3 invoice-brand logo-icon"><i class="fas fa-record-vinyl"></i></div>
            <div class="invoice-brand">
                <h1><?= e($companyName) ?></h1>
                <div class="tagline"><?= e($companyTagline) ?></div>
                <div class="tagline">
                    <?php if ($companyAddress): ?><i class="fas fa-map-marker-alt mr-1"></i><?= e($companyAddress) ?><?php endif; ?>
                    <?php if ($companyAddress && $companyPhone): ?> &bull; <?php endif; ?>
                    <?php if ($companyPhone): ?><i class="fas fa-phone mr-1"></i><?= e($companyPhone) ?><?php endif; ?>
                    <?php if ($companyPhone && $companyEmail): ?> &bull; <?php endif; ?>
                    <?php if ($companyEmail): ?><i class="fas fa-envelope mr-1"></i><?= e($companyEmail) ?><?php endif; ?>
                    <?php if ($companyVat): ?><br><i class="fas fa-file-invoice mr-1"></i><?= t('inv.tax') ?>: <?= e($companyVat) ?><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 invoice-badge">
            <h2 id="invoiceTop"><?= t('inv.title') ?></h2>
            <div class="meta"><b><?= t('inv.invoice_no') ?>:</b> INV-<?= str_pad($bookingId, 6, '0', STR_PAD_LEFT) ?></div>
            <div class="meta"><b><?= t('inv.booking_ref') ?>:</b> <?= e($booking['booking_number']) ?></div>
            <div class="meta"><b><?= t('inv.invoice_date') ?>:</b> <?= formatDateTime($invoiceDate->format('Y-m-d H:i:s')) ?></div>
            <div class="meta"><b><?= t('inv.due_date') ?>:</b> <?= formatDate($dueDate->format('Y-m-d')) ?></div>
        </div>
    </div>

    <div class="row invoice-party">
        <div class="col-md-6 mb-3 mb-md-0">
            <h4><i class="fas fa-user mr-1"></i><?= t('inv.bill_to') ?></h4>
            <div class="name"><?= e($booking['client_name']) ?></div>
            <?php if ($booking['client_phone']): ?>
            <div class="line"><i class="fas fa-phone"></i><a href="tel:<?= e($booking['client_phone']) ?>"><?= e($booking['client_phone']) ?></a><?php if ($booking['alt_phone']): ?> / <a href="tel:<?= e($booking['alt_phone']) ?>"><?= e($booking['alt_phone']) ?></a><?php endif; ?></div>
            <?php endif; ?>
            <?php if ($booking['client_email']): ?>
            <div class="line"><i class="fas fa-envelope"></i><a href="mailto:<?= e($booking['client_email']) ?>"><?= e($booking['client_email']) ?></a></div>
            <?php endif; ?>
            <?php if (!empty($booking['client_address'])): ?>
            <div class="line"><i class="fas fa-map-marker-alt"></i><?= e($booking['client_address']) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <h4><i class="fas fa-building mr-1"></i><?= t('inv.from') ?></h4>
            <div class="name"><?= e($companyName) ?></div>
            <?php if ($companyAddress): ?>
            <div class="line"><i class="fas fa-map-marker-alt"></i><?= e($companyAddress) ?></div>
            <?php endif; ?>
            <?php if ($companyPhone): ?>
            <div class="line"><i class="fas fa-phone"></i><a href="tel:<?= e($companyPhone) ?>"><?= e($companyPhone) ?></a></div>
            <?php endif; ?>
            <?php if ($companyEmail): ?>
            <div class="line"><i class="fas fa-envelope"></i><a href="mailto:<?= e($companyEmail) ?>"><?= e($companyEmail) ?></a></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row invoice-event">
        <div class="col-md-4">
            <div class="lbl"><i class="fas fa-map-pin mr-1"></i><?= t('inv.event_location') ?></div>
            <div class="val"><?= e($booking['location']) ?></div>
        </div>
        <div class="col-md-5">
            <div class="lbl"><i class="far fa-calendar mr-1"></i><?= t('inv.event_period') ?></div>
            <div class="val">
                <?= formatDate($booking['date_from']) ?>
                <?php if ($booking['event_start_time']): ?> <span class="text-muted">(<?= date('g:i A', strtotime($booking['event_start_time'])) ?>)</span><?php endif; ?>
                <?php if ($booking['date_from'] !== $booking['date_to']): ?>
                 &rarr; <?= formatDate($booking['date_to']) ?>
                 <?php if ($booking['event_end_time']): ?> <span class="text-muted">(<?= date('g:i A', strtotime($booking['event_end_time'])) ?>)</span><?php endif; ?>
                <?php else: ?>
                    <?php if ($booking['event_end_time']): ?> &rarr; <span class="text-muted"><?= date('g:i A', strtotime($booking['event_end_time'])) ?></span><?php endif; ?>
                <?php endif; ?>
                <span class="text-muted small ml-2"> (<?= (int)$days ?> <?= $days === 1 ? t('bk.day') : t('bk.days') ?>)</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="lbl"><i class="fas fa-clipboard-check mr-1"></i><?= t('bk.status') ?></div>
            <div class="val"><span class="badge badge-pill badge-<?= strtolower(str_replace([' ','-','/'],['_','_','_'], $booking['status'])) ?>"><?= t_booking_status($booking['status']) ?></span></div>
        </div>
    </div>

    <table class="invoice-table">
        <colgroup>
            <col style="width: 80%">
            <col style="width: 8%">
            <col style="width: 12%">
        </colgroup>
        <thead>
            <tr>
                <th><?= t('inv.item_desc') ?></th>
                <th class="cntr"><?= t('th.qty') ?></th>
                <th class="cntr">&nbsp;</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($bookingItems)): ?>
            <tr><td colspan="3" class="text-center py-5 text-muted"><i class="fas fa-box-open mr-2"></i><?= t('bk.no_payments') ?></td></tr>
            <?php else: ?>
            <?php foreach ($bookingItems as $bi):
                $qty = (int)$bi['quantity'];
            ?>
            <tr>
                <td>
                    <span class="cat"><?= e($bi['category_name']) ?></span>
                    <div class="desc"><b><?= $qty ?>&times;</b> <?= e($bi['item_name']) ?></div>
                    <div class="small text-muted"><?= t('bk.duration') ?>: <?= (int)$days ?> <?= $days === 1 ? t('bk.day') : t('bk.days') ?></div>
                </td>
                <td class="cntr font-weight-bold"><?= $qty ?></td>
                <td>&nbsp;</td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr class="grand">
                <td class="lbl"><b><?= t('inv.total_due') ?></b> <span class="badge badge-pill badge-dark border ml-2"><?= $days ?> <?= $days === 1 ? t('bk.day') : t('bk.days') ?></span></td>
                <td class="cntr">&nbsp;</td>
                <td class="num"><?= formatMoney($grandTotal) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="invoice-foot">
        <div class="payments-box">
            <h5><i class="fas fa-history mr-1"></i><?= t('inv.payment_history') ?></h5>
            <?php if (empty($payments)): ?>
                <div class="empty"><i class="fas fa-circle-notch mr-1"></i><?= t('bk.no_payments') ?></div>
            <?php else: ?>
                <?php foreach ($payments as $p): ?>
                <div class="pay-row">
                    <span>
                        <i class="far fa-calendar text-muted mr-1"></i><?= formatDate($p['payment_date']) ?>
                        <span class="text-muted ml-1">(<?= t_payment_method($p['payment_method'] ?? '-') ?>)</span>
                    </span>
                    <span class="amt">+ <?= formatMoney($p['amount']) ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($collected > 0): ?>
            <div class="pay-row font-weight-bold text-success" style="border-top:2px solid #16a34a; margin-top: 6px;">
                <span><?= t('th.total_collected') ?></span>
                <span class="amt"><?= formatMoney($collected) ?></span>
            </div>
            <?php endif; ?>
            <div class="balance-strip <?= $pending > 0 ? 'due' : '' ?>">
                <span><i class="fas fa-<?= $pending > 0 ? 'exclamation-triangle' : 'check-circle' ?> mr-1"></i><?= $pending > 0 ? t('inv.balance_due') : t('inv.amount_due') ?></span>
                <span style="font-size:16px;"><?= formatMoney($pending) ?></span>
            </div>
        </div>
        <div class="terms-box">
            <h5><i class="fas fa-file-contract mr-1"></i><?= t('inv.terms_heading') ?></h5>
            <div class="term-item"><span class="font-weight-bold mr-1">1.</span><?= t('inv.term_1') ?></div>
            <div class="term-item"><span class="font-weight-bold mr-1">2.</span><?= t('inv.term_2') ?></div>
            <div class="term-item"><span class="font-weight-bold mr-1">3.</span><?= t('inv.term_3') ?></div>
            <div class="term-item"><span class="font-weight-bold mr-1">4.</span><?= t('inv.term_4') ?></div>
        </div>
    </div>

    <div class="sigs-box">
        <div class="sig">
            <i class="fas fa-pen-alt text-muted mr-1"></i> <?= t('inv.signature_1') ?>
        </div>
        <div class="sig">
            <i class="fas fa-signature text-muted mr-1"></i> <?= t('inv.signature_2') ?>
        </div>
    </div>

    <div class="invoice-footnote">
        <div class="thanks"><i class="fas fa-heart text-danger mr-1"></i><?= t('inv.thanks') ?></div>
        <div><i class="fas fa-info-circle mr-1"></i><?= t('inv.print_note') ?></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(function () {
    $('[data-toggle="tooltip"]').tooltip();
    <?php if (!isset($_GET['noprint'])): ?>
    setTimeout(function() { window.print(); }, 500);
    <?php endif; ?>
});
</script>
</body>
</html>
