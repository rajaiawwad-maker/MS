<?php
function handle_misc_audit_logs($params) {
    global $conn;
    $where = [];
    $args = [];
    $user_id = $params['user_id'] ?? null;
    if ($user_id !== null && $user_id !== '') {
        $where[] = 'al.user_id = ?';
        $args[] = $user_id;
    }
    $entity_type = $params['entity_type'] ?? null;
    if ($entity_type !== null && $entity_type !== '') {
        $where[] = 'al.entity_type = ?';
        $args[] = $entity_type;
    }
    $action = $params['action'] ?? null;
    if ($action !== null && $action !== '') {
        $where[] = 'al.action = ?';
        $args[] = $action;
    }
    $date_from = $params['date_from'] ?? null;
    if ($date_from !== null && $date_from !== '') {
        $where[] = 'DATE(al.created_at) >= ?';
        $args[] = $date_from;
    }
    $date_to = $params['date_to'] ?? null;
    if ($date_to !== null && $date_to !== '') {
        $where[] = 'DATE(al.created_at) <= ?';
        $args[] = $date_to;
    }
    $q = $params['q'] ?? null;
    if ($q !== null && $q !== '') {
        $where[] = '(al.action LIKE ? OR al.entity_type LIKE ? OR u.name LIKE ? OR u.username LIKE ?)';
        $args[] = '%' . $q . '%';
        $args[] = '%' . $q . '%';
        $args[] = '%' . $q . '%';
        $args[] = '%' . $q . '%';
    }
    $sql = 'SELECT al.*, u.name as user_name, u.username as user_username FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY al.created_at DESC, al.id DESC';
    $page = $params['page'] ?? 1;
    $per_page = $params['per_page'] ?? 20;
    $result = api_paginate($sql, $args, $page, $per_page);
    api_success($result['data'], 'OK', 200, $result['pagination']);
}

function handle_misc_search($params) {
    global $conn;
    $q = trim($params['q'] ?? $_GET['q'] ?? '');
    if (mb_strlen($q) < 2) {
        api_error('Search query must be at least 2 characters', 'search_query_short', 422);
    }
    $like = '%' . $q . '%';
    $results = [];

    $stmt = $conn->prepare("SELECT 'booking' as type, b.id, b.booking_number as title, b.date_from as date_1, b.status, c.name as subtitle FROM bookings b JOIN clients c ON c.id = b.client_id WHERE b.booking_number LIKE ? OR c.name LIKE ? LIMIT 10");
    $stmt->execute([$like, $like]);
    $bookings = $stmt->fetchAll();
    foreach ($bookings as $b) {
        $results[] = [
            'type' => 'booking',
            'id' => (int)$b['id'],
            'title' => $b['title'],
            'date_1' => $b['date_1'],
            'status' => $b['status'],
            'subtitle' => $b['subtitle'],
        ];
    }

    $stmt = $conn->prepare("SELECT 'client' as type, c.id, c.name as title, c.phone as subtitle, c.active as status FROM clients c WHERE c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? LIMIT 10");
    $stmt->execute([$like, $like, $like]);
    $clients = $stmt->fetchAll();
    foreach ($clients as $c) {
        $results[] = [
            'type' => 'client',
            'id' => (int)$c['id'],
            'title' => $c['title'],
            'subtitle' => $c['subtitle'],
            'status' => $c['status'],
        ];
    }

    $stmt = $conn->prepare("SELECT 'item_type' as type, it.id, it.name as title, cat.name as subtitle FROM item_types it LEFT JOIN categories cat ON cat.id = it.category_id WHERE it.name LIKE ? LIMIT 10");
    $stmt->execute([$like]);
    $item_types = $stmt->fetchAll();
    foreach ($item_types as $it) {
        $results[] = [
            'type' => 'item_type',
            'id' => (int)$it['id'],
            'title' => $it['title'],
            'subtitle' => $it['subtitle'],
        ];
    }

    $stmt = $conn->prepare("SELECT 'inventory_item' as type, i.id, CONCAT_WS(' - ', i.serial_number, it.name) as title, i.asset_tag as subtitle, i.status as status FROM inventory_items i JOIN item_types it ON it.id = i.item_type_id WHERE i.serial_number LIKE ? OR i.asset_tag LIKE ? LIMIT 10");
    $stmt->execute([$like, $like]);
    $inventory_items = $stmt->fetchAll();
    foreach ($inventory_items as $ii) {
        $results[] = [
            'type' => 'inventory_item',
            'id' => (int)$ii['id'],
            'title' => $ii['title'],
            'subtitle' => $ii['subtitle'],
            'status' => $ii['status'],
        ];
    }

    $data = [
        'query' => $q,
        'total' => count($results),
        'results' => $results,
    ];
    api_success($data);
}

function handle_misc_i18n_dict($params) {
    $code = $params['lang'] ?? 'en';
    $allowed = ['en', 'ar'];
    if (!in_array($code, $allowed, true)) {
        $code = 'en';
    }
    $dict = loadLangDictionary($code);
    if ($code !== 'en') {
        $en_dict = loadLangDictionary('en');
        $dict = array_merge($en_dict, $dict);
    }
    $data = [
        'lang' => $code,
        'count' => count($dict),
        'dictionary' => $dict,
    ];
    api_success($data);
}

function handle_misc_i18n_set($params) {
    $body = api_get_json();
    $code = $body['lang'] ?? $body['code'] ?? null;
    $allowed = ['en', 'ar'];
    if (!in_array($code, $allowed, true)) {
        api_error('Invalid language code', 'invalid_lang_code', 422);
    }
    setActiveLang($code);
    $data = [
        'current_lang' => getActiveLang(),
    ];
    api_success($data);
}
