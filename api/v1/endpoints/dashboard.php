<?php
function handle_dashboard_stats($params) {
    global $conn;
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');

    $dateFrom = isset($params['date_from']) && trim($params['date_from']) !== '' ? trim($params['date_from']) : $monthStart;
    $dateTo = isset($params['date_to']) && trim($params['date_to']) !== '' ? trim($params['date_to']) : $today;

    $errors = [];
    api_validate_date($dateFrom, 'date_from', $errors);
    api_validate_date($dateTo, 'date_to', $errors);
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }

    $bookingSql = "SELECT COUNT(*) as cnt,
        COALESCE(SUM(CASE WHEN status != 'Canceled' THEN quoted_amount ELSE 0 END),0) as booked,
        COALESCE(SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END),0) as confirmed,
        COALESCE(SUM(CASE WHEN status IN ('Draft','Quotation','Change Requested') THEN 1 ELSE 0 END),0) as pending_events,
        COALESCE(SUM(CASE WHEN status = 'Canceled' THEN 1 ELSE 0 END),0) as canceled,
        COALESCE(SUM(CASE WHEN status != 'Canceled' THEN dj_rak_amount ELSE 0 END),0) as dj_rak
        FROM bookings WHERE date_from >= ? AND date_from <= ?";
    $stmt = $conn->prepare($bookingSql);
    $stmt->execute([$dateFrom, $dateTo]);
    $b = $stmt->fetch();
    $totalBookings = (int)$b['cnt'];
    $totalQuoted = (float)$b['booked'];
    $confirmedCount = (int)$b['confirmed'];
    $pendingCount = (int)$b['pending_events'];
    $canceledCount = (int)$b['canceled'];
    $djRakAmount = (float)$b['dj_rak'];

    $paymentSql = "SELECT COALESCE(SUM(p.amount),0) as collected FROM payments p
        INNER JOIN bookings b ON p.booking_id = b.id
        WHERE p.payment_date >= ? AND p.payment_date <= ? AND b.status != 'Canceled'";
    $stmt = $conn->prepare($paymentSql);
    $stmt->execute([$dateFrom, $dateTo]);
    $collected = (float)$stmt->fetchColumn();

    $pendingSql = "SELECT COALESCE(SUM(
        CASE WHEN b.status != 'Canceled' THEN GREATEST(0, b.quoted_amount -
            (SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = b.id))
        ELSE 0 END), 0) as pending FROM bookings b
        WHERE b.date_from >= ? AND b.date_from <= ?";
    $stmt = $conn->prepare($pendingSql);
    $stmt->execute([$dateFrom, $dateTo]);
    $pendingBalance = (float)$stmt->fetchColumn();

    $expenseSql = "SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE date >= ? AND date <= ?";
    $stmt = $conn->prepare($expenseSql);
    $stmt->execute([$dateFrom, $dateTo]);
    $expenses = (float)$stmt->fetchColumn();

    $collectionPct = $totalQuoted > 0 ? min(100, round(($collected / $totalQuoted) * 100)) : 0;
    $djRakPct = $totalQuoted > 0 ? min(100, round(($djRakAmount / $totalQuoted) * 100)) : 0;

    $itemTypeSql = "SELECT COUNT(*) as cnt, COALESCE(SUM(quantity),0) as total_units FROM item_types WHERE active = 1";
    $itemTypeStats = $conn->query($itemTypeSql)->fetch();
    $totalUnits = (int)$itemTypeStats['total_units'];
    $totalItemTypes = (int)$itemTypeStats['cnt'];

    $todayBooked = 0;
    $reserveStatuses = ['Quotation','Confirmed','Change Requested','Event Completed','Closed'];
    $placeholders = implode(',', array_fill(0, count($reserveStatuses), '?'));
    $biSql = "SELECT COALESCE(SUM(bi.quantity),0) as qty FROM booking_items bi
        INNER JOIN bookings b ON bi.booking_id = b.id
        WHERE b.status IN ($placeholders)
        AND b.date_from <= ? AND b.date_to >= ? AND b.status != 'Canceled'";
    $stmt = $conn->prepare($biSql);
    $biParams = array_merge($reserveStatuses, [$today, $today]);
    $stmt->execute($biParams);
    $todayBooked = (int)$stmt->fetchColumn();

    $upcomingBookings = [];
    $ubStmt = $conn->prepare("SELECT b.*, c.name as client_name, c.phone as client_phone
        FROM bookings b INNER JOIN clients c ON b.client_id = c.id
        WHERE b.status != 'Canceled' AND b.date_from >= ?
        ORDER BY b.date_from ASC LIMIT 8");
    $ubStmt->execute([$today]);
    $upcomingBookings = $ubStmt->fetchAll();

    $pendingPayments = [];
    $ppStmt = $conn->prepare("SELECT b.id, b.booking_number, b.quoted_amount, b.date_from, c.name as client_name,
        (SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = b.id) as collected
        FROM bookings b INNER JOIN clients c ON b.client_id = c.id
        WHERE b.status != 'Canceled' AND b.payment_status IN ('Not Collected','Partially Collected')
        ORDER BY b.date_from ASC LIMIT 8");
    $ppStmt->execute();
    $pendingPayments = $ppStmt->fetchAll();

    $topClients = [];
    $tcStmt = $conn->prepare("SELECT c.id, c.name,
        COUNT(b.id) as booking_count,
        COALESCE(SUM(CASE WHEN b.status != 'Canceled' THEN b.quoted_amount ELSE 0 END),0) as total_value
        FROM clients c LEFT JOIN bookings b ON b.client_id = c.id AND b.date_from >= ? AND b.date_from <= ?
        GROUP BY c.id ORDER BY total_value DESC LIMIT 5");
    $tcStmt->execute([$dateFrom, $dateTo]);
    $topClients = $tcStmt->fetchAll();

    $data = [
        'total_bookings' => $totalBookings,
        'total_quoted' => $totalQuoted,
        'confirmed_count' => $confirmedCount,
        'pending_count' => $pendingCount,
        'canceled_count' => $canceledCount,
        'dj_rak_amount' => $djRakAmount,
        'collected' => $collected,
        'pending_balance' => $pendingBalance,
        'expenses' => $expenses,
        'collection_pct' => $collectionPct,
        'dj_rak_pct' => $djRakPct,
        'item_types' => [
            'total_types' => $totalItemTypes,
            'total_units' => $totalUnits,
            'booked_units' => $todayBooked,
        ],
        'recent' => [
            'upcoming_bookings' => $upcomingBookings,
            'pending_payments' => $pendingPayments,
            'top_clients' => $topClients,
        ],
    ];

    api_success($data, 'Dashboard stats', 200);
}

function handle_dashboard_activity($params) {
    global $conn;
    $page = $params['page'] ?? 1;
    $perPage = $params['per_page'] ?? 20;

    $sql = "SELECT audit_logs.*, users.name as user_name
        FROM audit_logs
        INNER JOIN users ON audit_logs.user_id = users.id
        ORDER BY audit_logs.created_at DESC";

    $result = api_paginate($sql, [], $page, $perPage);

    api_success($result['data'], 'Recent activity', 200, $result['pagination']);
}
