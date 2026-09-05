<?php
function handle_reports_bookings($params) {
    global $conn;
    $where = [];
    $args = [];
    $date_from = isset($params['date_from']) ? trim((string)$params['date_from']) : '';
    if ($date_from !== '') {
        $where[] = 'b.date_from >= ?';
        $args[] = $date_from;
    }
    $date_to = isset($params['date_to']) ? trim((string)$params['date_to']) : '';
    if ($date_to !== '') {
        $where[] = 'b.date_from <= ?';
        $args[] = $date_to;
    }
    $status = isset($params['status']) ? trim((string)$params['status']) : '';
    if ($status !== '') {
        $where[] = 'b.status = ?';
        $args[] = $status;
    }
    $client_id = isset($params['client_id']) ? (int)$params['client_id'] : 0;
    if ($client_id > 0) {
        $where[] = 'b.client_id = ?';
        $args[] = $client_id;
    }
    $sql = 'SELECT b.*, c.name as client_name, c.phone as client_phone,
        (SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id=b.id) as collected
        FROM bookings b INNER JOIN clients c ON c.id=b.client_id';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY b.date_from DESC';
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $per_page = isset($params['per_page']) ? (int)$params['per_page'] : 20;
    $result = api_paginate($sql, $args, $page, $per_page);
    $rows = $result['data'];
    $total_count = count($rows);
    $total_booked_amount = 0.0;
    $total_collected = 0.0;
    $total_dj_rak = 0.0;
    $nc_count = 0;
    foreach ($rows as $r) {
        $is_canceled = ($r['status'] === 'Canceled');
        if (!$is_canceled) {
            $nc_count++;
            $total_booked_amount += (float)$r['quoted_amount'];
            $total_dj_rak += (float)$r['dj_rak_amount'];
        }
        $total_collected += (float)($r['collected'] ?? 0);
    }
    $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') x';
    $stmt = $conn->prepare($countSql);
    $stmt->execute($args);
    $all_count = (int)$stmt->fetchColumn();
    $sumSql = "SELECT
        COALESCE(SUM(CASE WHEN b.status != 'Canceled' THEN b.quoted_amount ELSE 0 END),0) as s_booked,
        COALESCE(SUM((SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id=b.id)),0) as s_collected,
        COALESCE(SUM(CASE WHEN b.status != 'Canceled' THEN b.dj_rak_amount ELSE 0 END),0) as s_dj,
        COALESCE(SUM(CASE WHEN b.status != 'Canceled' THEN 1 ELSE 0 END),0) as s_cnt
        FROM bookings b INNER JOIN clients c ON c.id=b.client_id";
    if (!empty($where)) {
        $sumSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $stmt = $conn->prepare($sumSql);
    $stmt->execute($args);
    $sums = $stmt->fetch();
    $total_booked_amount = (float)($sums['s_booked'] ?? 0);
    $total_collected = (float)($sums['s_collected'] ?? 0);
    $total_dj_rak = (float)($sums['s_dj'] ?? 0);
    $nc_count = (int)($sums['s_cnt'] ?? 0);
    $total_pending = $total_booked_amount - $total_collected;
    $avg_value = $nc_count > 0 ? ($total_booked_amount / $nc_count) : 0;
    $collection_pct = $total_booked_amount > 0 ? (($total_collected / $total_booked_amount) * 100) : 0;
    $summary = [
        'total_count' => $all_count,
        'total_booked_amount' => round($total_booked_amount, 2),
        'total_collected' => round($total_collected, 2),
        'total_pending' => round($total_pending, 2),
        'total_dj_rak' => round($total_dj_rak, 2),
        'avg_value' => round($avg_value, 2),
        'collection_pct' => round($collection_pct, 2),
    ];
    $data = [
        'rows' => $rows,
        'summary' => $summary,
    ];
    api_success($data, 'OK', 200, $result['pagination']);
}

function handle_reports_bookings_csv($params) {
    global $conn;
    $where = [];
    $args = [];
    $date_from = isset($params['date_from']) ? trim((string)$params['date_from']) : '';
    if ($date_from !== '') {
        $where[] = 'b.date_from >= ?';
        $args[] = $date_from;
    }
    $date_to = isset($params['date_to']) ? trim((string)$params['date_to']) : '';
    if ($date_to !== '') {
        $where[] = 'b.date_from <= ?';
        $args[] = $date_to;
    }
    $status = isset($params['status']) ? trim((string)$params['status']) : '';
    if ($status !== '') {
        $where[] = 'b.status = ?';
        $args[] = $status;
    }
    $client_id = isset($params['client_id']) ? (int)$params['client_id'] : 0;
    if ($client_id > 0) {
        $where[] = 'b.client_id = ?';
        $args[] = $client_id;
    }
    $sql = 'SELECT b.*, c.name as client_name, c.phone as client_phone,
        (SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id=b.id) as collected
        FROM bookings b INNER JOIN clients c ON c.id=b.client_id';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY b.date_from DESC';
    $stmt = $conn->prepare($sql);
    $stmt->execute($args);
    $rows = $stmt->fetchAll();
    $buf = fopen('php://memory', 'r+');
    fputcsv($buf, [
        'Booking Number','Client','Date From','Date To','Status','Payment Status',
        'Quoted Amount','Collected Amount','Pending Amount','DJ RAK Amount','Created At'
    ]);
    foreach ($rows as $r) {
        $quoted = (float)$r['quoted_amount'];
        $coll = (float)($r['collected'] ?? 0);
        $pend = max(0, $quoted - $coll);
        fputcsv($buf, [
            (string)($r['booking_number'] ?? ''),
            (string)($r['client_name'] ?? ''),
            (string)($r['date_from'] ?? ''),
            (string)($r['date_to'] ?? ''),
            (string)($r['status'] ?? ''),
            (string)($r['payment_status'] ?? ''),
            number_format($quoted, 2, '.', ''),
            number_format($coll, 2, '.', ''),
            number_format($pend, 2, '.', ''),
            number_format((float)$r['dj_rak_amount'], 2, '.', ''),
            (string)($r['created_at'] ?? ''),
        ]);
    }
    rewind($buf);
    $csv = stream_get_contents($buf);
    fclose($buf);
    $b64 = base64_encode($csv);
    $filename = 'bookings_report_' . date('Ymd_His') . '.csv';
    api_success([
        'mime' => 'text/csv',
        'filename' => $filename,
        'base64_content' => $b64,
        'total_rows' => count($rows),
    ], 'OK', 200);
}

function handle_reports_financial($params) {
    global $conn;
    $first_day = date('Y-m-01');
    $today = date('Y-m-d');
    $date_from = isset($params['date_from']) && trim((string)$params['date_from']) !== '' ? trim((string)$params['date_from']) : $first_day;
    $date_to = isset($params['date_to']) && trim((string)$params['date_to']) !== '' ? trim((string)$params['date_to']) : $today;
    $stmt = $conn->prepare("SELECT
        COUNT(*) as cnt,
        COALESCE(SUM(CASE WHEN status != 'Canceled' THEN quoted_amount ELSE 0 END),0) as booked,
        COALESCE(SUM(CASE WHEN status != 'Canceled' THEN dj_rak_amount ELSE 0 END),0) as dj_rak
        FROM bookings WHERE date_from >= ? AND date_from <= ?");
    $stmt->execute([$date_from, $date_to]);
    $b = $stmt->fetch();
    $total_bookings_count = (int)$b['cnt'];
    $total_booked_amount = (float)$b['booked'];
    $total_dj_rak_amount = (float)$b['dj_rak'];
    $stmt = $conn->prepare("SELECT COALESCE(SUM(p.amount),0) as coll FROM payments p
        INNER JOIN bookings b ON p.booking_id = b.id
        WHERE p.payment_date >= ? AND p.payment_date <= ? AND b.status != 'Canceled'");
    $stmt->execute([$date_from, $date_to]);
    $total_collected = (float)$stmt->fetchColumn();
    $total_pending = $total_booked_amount - $total_collected;
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE date >= ? AND date <= ?");
    $stmt->execute([$date_from, $date_to]);
    $total_expenses = (float)$stmt->fetchColumn();
    $net_income = $total_collected - $total_expenses;
    $collection_pct = $total_booked_amount > 0 ? (($total_collected / $total_booked_amount) * 100) : 0;
    $avg_booking_value = $total_bookings_count > 0 ? ($total_booked_amount / $total_bookings_count) : 0;
    api_success([
        'total_bookings_count' => $total_bookings_count,
        'total_booked_amount' => round($total_booked_amount, 2),
        'total_collected' => round($total_collected, 2),
        'total_pending' => round($total_pending, 2),
        'total_expenses' => round($total_expenses, 2),
        'net_income' => round($net_income, 2),
        'collection_pct' => round($collection_pct, 2),
        'total_dj_rak_amount' => round($total_dj_rak_amount, 2),
        'avg_booking_value' => round($avg_booking_value, 2),
    ], 'OK', 200);
}

function handle_reports_expenses($params) {
    global $conn;
    $where = ['1=1'];
    $p = [];
    $date_from = isset($params['date_from']) ? trim((string)$params['date_from']) : '';
    if ($date_from !== '') {
        $where[] = 'e.date >= ?';
        $p[] = $date_from;
    }
    $date_to = isset($params['date_to']) ? trim((string)$params['date_to']) : '';
    if ($date_to !== '') {
        $where[] = 'e.date <= ?';
        $p[] = $date_to;
    }
    $type_id = isset($params['type_id']) ? (int)$params['type_id'] : 0;
    if ($type_id > 0) {
        $where[] = 'e.type_id = ?';
        $p[] = $type_id;
    }
    $sql = 'SELECT e.*, et.name as type_name, b.booking_number, u.name as user_name
        FROM expenses e INNER JOIN expense_types et ON et.id=e.type_id
        LEFT JOIN bookings b ON b.id=e.booking_id
        LEFT JOIN users u ON u.id=e.created_by
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY e.date DESC, e.id DESC';
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $pp = isset($params['per_page']) ? (int)$params['per_page'] : 20;
    $res = api_paginate($sql, $p, $page, $pp);
    $sumSql = 'SELECT COUNT(*) as cnt, COALESCE(SUM(e.amount),0) as total
        FROM expenses e INNER JOIN expense_types et ON et.id=e.type_id
        WHERE ' . implode(' AND ', $where);
    $stmt = $conn->prepare($sumSql);
    $stmt->execute($p);
    $sr = $stmt->fetch();
    $summary = [
        'total_expense_count' => (int)($sr['cnt'] ?? 0),
        'total_expense_amount' => round((float)($sr['total'] ?? 0), 2),
    ];
    $data = [
        'rows' => $res['data'],
        'summary' => $summary,
    ];
    api_success($data, 'OK', 200, $res['pagination']);
}

function handle_reports_expenses_csv($params) {
    global $conn;
    $where = ['1=1'];
    $p = [];
    $date_from = isset($params['date_from']) ? trim((string)$params['date_from']) : '';
    if ($date_from !== '') {
        $where[] = 'e.date >= ?';
        $p[] = $date_from;
    }
    $date_to = isset($params['date_to']) ? trim((string)$params['date_to']) : '';
    if ($date_to !== '') {
        $where[] = 'e.date <= ?';
        $p[] = $date_to;
    }
    $type_id = isset($params['type_id']) ? (int)$params['type_id'] : 0;
    if ($type_id > 0) {
        $where[] = 'e.type_id = ?';
        $p[] = $type_id;
    }
    $sql = 'SELECT e.*, et.name as type_name, b.booking_number, u.name as user_name
        FROM expenses e INNER JOIN expense_types et ON et.id=e.type_id
        LEFT JOIN bookings b ON b.id=e.booking_id
        LEFT JOIN users u ON u.id=e.created_by
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY e.date DESC, e.id DESC';
    $stmt = $conn->prepare($sql);
    $stmt->execute($p);
    $rows = $stmt->fetchAll();
    $buf = fopen('php://memory', 'r+');
    fputcsv($buf, [
        'Date','Type','Amount','Payment Method','Reference','Booking Number','Created By','Description','Notes'
    ]);
    foreach ($rows as $r) {
        fputcsv($buf, [
            (string)($r['date'] ?? ''),
            (string)($r['type_name'] ?? ''),
            number_format((float)($r['amount'] ?? 0), 2, '.', ''),
            (string)($r['payment_method'] ?? ''),
            (string)($r['reference'] ?? ''),
            (string)($r['booking_number'] ?? ''),
            (string)($r['user_name'] ?? ''),
            (string)($r['description'] ?? ''),
            (string)($r['notes'] ?? ''),
        ]);
    }
    rewind($buf);
    $csv = stream_get_contents($buf);
    fclose($buf);
    $b64 = base64_encode($csv);
    $filename = 'expenses_report_' . date('Ymd_His') . '.csv';
    api_success([
        'mime' => 'text/csv',
        'filename' => $filename,
        'base64_content' => $b64,
        'total_rows' => count($rows),
    ], 'OK', 200);
}

function handle_reports_inventory($params) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM item_types WHERE active=1");
    $stmt->execute();
    $total_types = (int)$stmt->fetchColumn();
    $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity),0) FROM item_types WHERE active=1");
    $stmt->execute();
    $total_units = (int)$stmt->fetchColumn();
    $stmt = $conn->prepare("SELECT COUNT(*) FROM inventory_items WHERE active=1");
    $stmt->execute();
    $total_inventory_items = (int)$stmt->fetchColumn();
    $by_category = [];
    $stmt = $conn->prepare("SELECT c.id, c.name,
        COUNT(DISTINCT it.id) as types,
        COALESCE(SUM(it.quantity),0) as units
        FROM categories c LEFT JOIN item_types it ON it.category_id=c.id AND it.active=1
        WHERE c.active=1
        GROUP BY c.id, c.name
        ORDER BY c.name");
    $stmt->execute();
    $catRows = $stmt->fetchAll();
    foreach ($catRows as $cr) {
        $by_category[(string)$cr['name']] = [
            'types' => (int)$cr['types'],
            'units' => (int)$cr['units'],
        ];
    }
    $by_status = [];
    $statuses = ['Available','Booked','Out for Event','Maintenance','Damaged','Lost','Retired'];
    foreach ($statuses as $st) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM inventory_items WHERE status=? AND active=1");
        $stmt->execute([$st]);
        $by_status[$st] = (int)$stmt->fetchColumn();
    }
    $sql = "SELECT it.*, c.name category_name,
        (SELECT COUNT(*) FROM inventory_items WHERE item_type_id=it.id AND active=1) as total_items
        FROM item_types it LEFT JOIN categories c ON c.id=it.category_id
        WHERE it.active=1 ORDER BY it.name";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $summary = [
        'total_types' => $total_types,
        'total_units' => $total_units,
        'total_inventory_items' => $total_inventory_items,
        'by_category' => $by_category,
        'by_status' => $by_status,
    ];
    api_success([
        'summary' => $summary,
        'rows' => $rows,
    ], 'OK', 200);
}

function handle_reports_client_statement($params) {
    global $conn;
    $id = isset($params['id']) ? (int)$params['id'] : 0;
    if ($id <= 0) {
        api_error('Client not found', 'not_found', 404);
    }
    $stmt = $conn->prepare("SELECT * FROM clients WHERE id=?");
    $stmt->execute([$id]);
    $client = $stmt->fetch();
    if (!$client) {
        api_error('Client not found', 'not_found', 404);
    }
    $stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE client_id=? AND status != 'Canceled'");
    $stmt->execute([$id]);
    $total_bookings = (int)$stmt->fetchColumn();
    $stmt = $conn->prepare("SELECT COALESCE(SUM(quoted_amount),0) FROM bookings WHERE client_id=? AND status != 'Canceled'");
    $stmt->execute([$id]);
    $total_booked = (float)$stmt->fetchColumn();
    $stmt = $conn->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p
        INNER JOIN bookings b ON p.booking_id=b.id
        WHERE b.client_id=? AND b.status != 'Canceled'");
    $stmt->execute([$id]);
    $total_collected = (float)$stmt->fetchColumn();
    $total_pending = $total_booked - $total_collected;
    $summary = [
        'total_bookings' => $total_bookings,
        'total_booked' => round($total_booked, 2),
        'total_collected' => round($total_collected, 2),
        'total_pending' => round($total_pending, 2),
    ];
    $stmt = $conn->prepare("SELECT b.id, b.booking_number, b.date_from, b.date_to, b.status, b.quoted_amount,
        (SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id=b.id) as collected
        FROM bookings b WHERE client_id=? ORDER BY b.date_from DESC");
    $stmt->execute([$id]);
    $bookings = $stmt->fetchAll();
    $stmt = $conn->prepare("SELECT p.*, b.booking_number FROM payments p
        INNER JOIN bookings b ON p.booking_id=b.id
        WHERE b.client_id=? ORDER BY p.payment_date DESC, p.id DESC");
    $stmt->execute([$id]);
    $payments = $stmt->fetchAll();
    api_success([
        'client' => $client,
        'summary' => $summary,
        'bookings' => $bookings,
        'payments' => $payments,
    ], 'OK', 200);
}
