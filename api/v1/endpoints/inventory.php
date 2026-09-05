<?php
function handle_inventory_categories_list($params) {
    global $conn;
    $sql = "SELECT * FROM categories WHERE active=1";
    $p = [];
    $q = isset($params['q']) ? trim((string)$params['q']) : '';
    if ($q !== '') {
        $sql .= " AND name LIKE ?";
        $p[] = '%' . $q . '%';
    }
    $sql .= " ORDER BY name ASC";
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $pp = isset($params['per_page']) ? (int)$params['per_page'] : 20;
    $res = api_paginate($sql, $p, $page, $pp);
    api_success($res['data'], 'OK', 200, $res['pagination']);
}

function handle_inventory_categories_create($params) {
    global $conn;
    $body = api_get_json();
    $errors = [];
    api_validate_required(['name'], $body, $errors);
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }
    $name = trim((string)$body['name']);
    $description = isset($body['description']) ? trim((string)$body['description']) : null;
    $stmt = $conn->prepare("INSERT categories (name,description,active) VALUES (?,?,1)");
    $stmt->execute([$name, $description]);
    $id = (int)$conn->lastInsertId();
    $stmt = $conn->prepare("SELECT id, name, description, active FROM categories WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    auditLog('category_created', 'Category', $id, null, $row);
    api_success($row, 'Category created', 201);
}

function handle_inventory_categories_update($params) {
    global $conn;
    $id = (int)($params['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id=?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) {
        api_error('Category not found', 'not_found', 404);
    }
    $body = api_get_json();
    $name = isset($body['name']) ? trim((string)$body['name']) : $old['name'];
    $description = isset($body['description']) ? trim((string)$body['description']) : $old['description'];
    $active = isset($body['active']) ? (int)$body['active'] : (int)$old['active'];
    $stmt = $conn->prepare("UPDATE categories SET name=?, description=?, active=? WHERE id=?");
    $stmt->execute([$name, $description, $active, $id]);
    $stmt = $conn->prepare("SELECT id, name, description, active FROM categories WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    auditLog('category_updated', 'Category', $id, $old, $row);
    api_success($row, 'Category updated', 200);
}

function handle_inventory_categories_delete($params) {
    global $conn;
    $id = (int)($params['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id=?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) {
        api_error('Category not found', 'not_found', 404);
    }
    $stmt = $conn->prepare("UPDATE categories SET active=0 WHERE id=?");
    $stmt->execute([$id]);
    auditLog('category_deactivated', 'Category', $id, $old, null);
    api_success(null, 'Category deactivated', 200);
}

function handle_inventory_item_types_list($params) {
    global $conn;
    $sql = "SELECT it.*, c.name as category_name FROM item_types it LEFT JOIN categories c ON it.category_id=c.id WHERE 1=1";
    $p = [];
    $category_id = isset($params['category_id']) ? (int)$params['category_id'] : 0;
    if ($category_id > 0) {
        $sql .= " AND it.category_id = ?";
        $p[] = $category_id;
    }
    $q = isset($params['q']) ? trim((string)$params['q']) : '';
    if ($q !== '') {
        $sql .= " AND it.name LIKE ?";
        $p[] = '%' . $q . '%';
    }
    $activeFilter = isset($params['active']) ? (string)$params['active'] : '1';
    if ($activeFilter === '1' || $activeFilter === '0') {
        $sql .= " AND it.active = ?";
        $p[] = (int)$activeFilter;
    }
    $sql .= " ORDER BY it.name ASC";
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $pp = isset($params['per_page']) ? (int)$params['per_page'] : 20;
    $res = api_paginate($sql, $p, $page, $pp);
    api_success($res['data'], 'OK', 200, $res['pagination']);
}

function handle_inventory_item_types_create($params) {
    global $conn;
    $body = api_get_json();
    $errors = [];
    api_validate_required(['name', 'quantity', 'category_id'], $body, $errors);
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }
    $name = trim((string)$body['name']);
    $category_id = (int)$body['category_id'];
    $quantity = (int)$body['quantity'];
    if ($quantity < 0) {
        if (!isset($errors['quantity'])) $errors['quantity'] = [];
        $errors['quantity'][] = 'Quantity must be >= 0';
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }
    $daily_rate = isset($body['daily_rate']) ? (float)$body['daily_rate'] : 0;
    $hourly_rate = isset($body['hourly_rate']) ? (float)$body['hourly_rate'] : 0;
    $description = isset($body['description']) ? trim((string)$body['description']) : null;
    $stmt = $conn->prepare("INSERT item_types (name,category_id,quantity,daily_rate,hourly_rate,description,status,active) VALUES (?,?,?,?,?,?,'Available',1)");
    $stmt->execute([$name, $category_id, $quantity, $daily_rate, $hourly_rate, $description]);
    $id = (int)$conn->lastInsertId();
    $stmt = $conn->prepare("SELECT it.*, c.name as category_name FROM item_types it LEFT JOIN categories c ON it.category_id=c.id WHERE it.id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    auditLog('item_type_created', 'ItemType', $id, null, $row);
    api_success($row, 'Item type created', 201);
}

function handle_inventory_item_types_update($params) {
    global $conn;
    $id = (int)($params['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM item_types WHERE id=?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) {
        api_error('Item type not found', 'not_found', 404);
    }
    $body = api_get_json();
    $name = isset($body['name']) ? trim((string)$body['name']) : $old['name'];
    $category_id = isset($body['category_id']) ? (int)$body['category_id'] : (int)$old['category_id'];
    $quantity = isset($body['quantity']) ? (int)$body['quantity'] : (int)$old['quantity'];
    if ($quantity < 0) {
        $errors = [];
        $errors['quantity'][] = 'Quantity must be >= 0';
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }
    $daily_rate = isset($body['daily_rate']) ? (float)$body['daily_rate'] : (float)$old['daily_rate'];
    $hourly_rate = isset($body['hourly_rate']) ? (float)$body['hourly_rate'] : (float)$old['hourly_rate'];
    $description = isset($body['description']) ? trim((string)$body['description']) : $old['description'];
    $active = isset($body['active']) ? (int)$body['active'] : (int)$old['active'];
    $stmt = $conn->prepare("UPDATE item_types SET name=?, category_id=?, quantity=?, daily_rate=?, hourly_rate=?, description=?, active=? WHERE id=?");
    $stmt->execute([$name, $category_id, $quantity, $daily_rate, $hourly_rate, $description, $active, $id]);
    $stmt = $conn->prepare("SELECT it.*, c.name as category_name FROM item_types it LEFT JOIN categories c ON it.category_id=c.id WHERE it.id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    auditLog('item_type_updated', 'ItemType', $id, $old, $row);
    api_success($row, 'Item type updated', 200);
}

function handle_inventory_item_types_delete($params) {
    global $conn;
    $id = (int)($params['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM item_types WHERE id=?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) {
        api_error('Item type not found', 'not_found', 404);
    }
    $stmt = $conn->prepare("UPDATE item_types SET active=0 WHERE id=?");
    $stmt->execute([$id]);
    auditLog('item_type_deactivated', 'ItemType', $id, $old, null);
    api_success(null, 'Item type deactivated', 200);
}

function handle_inventory_item_type_availability($params) {
    global $conn;
    $itid = (int)($params['id'] ?? 0);
    $from = isset($params['date_from']) ? trim((string)$params['date_from']) : date('Y-m-d');
    $to = isset($params['date_to']) ? trim((string)$params['date_to']) : date('Y-m-d');
    $errors = [];
    api_validate_date($from, 'date_from', $errors);
    api_validate_date($to, 'date_to', $errors);
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }
    $stmt = $conn->prepare("SELECT quantity FROM item_types WHERE id=?");
    $stmt->execute([$itid]);
    $row = $stmt->fetch();
    if (!$row) {
        api_error('Item type not found', 'not_found', 404);
    }
    $total = (int)$row['quantity'];
    $booked = getBookedQuantity($itid, $from, $to);
    $available = max(0, $total - $booked);
    api_success([
        'item_type_id' => $itid,
        'date_from' => $from,
        'date_to' => $to,
        'total_quantity' => $total,
        'booked_quantity' => $booked,
        'available_quantity' => $available,
        'booked_statuses_considered' => ['Quotation', 'Confirmed', 'Change Requested', 'Event Completed', 'Closed'],
    ], 'OK', 200);
}

function handle_inventory_item_types_availability_bulk($params) {
    global $conn;
    $from = isset($params['date_from']) ? trim((string)$params['date_from']) : '';
    $to = isset($params['date_to']) ? trim((string)$params['date_to']) : '';
    $errors = [];
    if ($from === '') {
        $errors['date_from'][] = 'Field is required';
    } else {
        api_validate_date($from, 'date_from', $errors);
    }
    if ($to === '') {
        $errors['date_to'][] = 'Field is required';
    } else {
        api_validate_date($to, 'date_to', $errors);
    }
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }
    $stmt = $conn->prepare("SELECT id,name,quantity,category_id,active FROM item_types WHERE active=1");
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $itid = (int)$r['id'];
        $booked = getBookedQuantity($itid, $from, $to);
        $available = max(0, (int)$r['quantity'] - $booked);
        $out[] = [
            'id' => $itid,
            'name' => $r['name'],
            'quantity' => (int)$r['quantity'],
            'category_id' => (int)$r['category_id'],
            'active' => (int)$r['active'],
            'booked_quantity' => $booked,
            'available_quantity' => $available,
        ];
    }
    api_success($out, 'OK', 200);
}

function handle_inventory_items_list($params) {
    global $conn;
    $sql = "SELECT inv.*, it.name AS item_type_name, it.category_id AS item_type_category_id, c.name AS category_name FROM inventory_items inv LEFT JOIN item_types it ON inv.item_type_id = it.id LEFT JOIN categories c ON it.category_id=c.id WHERE 1=1";
    $p = [];
    $item_type_id = isset($params['item_type_id']) ? (int)$params['item_type_id'] : 0;
    if ($item_type_id > 0) {
        $sql .= " AND inv.item_type_id = ?";
        $p[] = $item_type_id;
    }
    $status = isset($params['status']) ? trim((string)$params['status']) : '';
    if ($status !== '') {
        $sql .= " AND inv.status = ?";
        $p[] = $status;
    }
    $q = isset($params['q']) ? trim((string)$params['q']) : '';
    if ($q !== '') {
        $sql .= " AND (inv.serial_number LIKE ? OR inv.asset_tag LIKE ?)";
        $p[] = '%' . $q . '%';
        $p[] = '%' . $q . '%';
    }
    $sql .= " ORDER BY inv.serial_number ASC";
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $pp = isset($params['per_page']) ? (int)$params['per_page'] : 20;
    $res = api_paginate($sql, $p, $page, $pp);
    api_success($res['data'], 'OK', 200, $res['pagination']);
}

function handle_inventory_items_create($params) {
    global $conn;
    $body = api_get_json();
    $errors = [];
    api_validate_required(['item_type_id', 'serial_number'], $body, $errors);
    if (!empty($errors)) {
        api_error('Validation failed', 'validation_failed', 422, $errors);
    }
    $item_type_id = (int)$body['item_type_id'];
    $serial_number = trim((string)$body['serial_number']);
    $asset_tag = isset($body['asset_tag']) ? trim((string)$body['asset_tag']) : null;
    $manufacturer = isset($body['manufacturer']) ? trim((string)$body['manufacturer']) : null;
    $model = isset($body['model']) ? trim((string)$body['model']) : null;
    $purchase_date = isset($body['purchase_date']) ? trim((string)$body['purchase_date']) : null;
    $purchase_cost = isset($body['purchase_cost']) ? (float)$body['purchase_cost'] : null;
    $notes = isset($body['notes']) ? trim((string)$body['notes']) : null;
    $stmt = $conn->prepare("INSERT inventory_items (item_type_id,serial_number,asset_tag,manufacturer,model,status,purchase_date,purchase_cost,notes,condition,active,created_at) VALUES (?,?,?,?,?,?,'Available',?,?,?,'Good',1,NOW())");
    $stmt->execute([$item_type_id, $serial_number, $asset_tag, $manufacturer, $model, $purchase_date, $purchase_cost, $notes]);
    $id = (int)$conn->lastInsertId();
    $stmt = $conn->prepare("SELECT inv.*, it.name AS item_type_name, it.category_id AS item_type_category_id, c.name AS category_name FROM inventory_items inv LEFT JOIN item_types it ON inv.item_type_id = it.id LEFT JOIN categories c ON it.category_id=c.id WHERE inv.id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    auditLog('inventory_item_created', 'InventoryItem', $id, null, $row);
    api_success($row, 'Inventory item created', 201);
}

function handle_inventory_items_update($params) {
    global $conn;
    $id = (int)($params['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM inventory_items WHERE id=?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) {
        api_error('Inventory item not found', 'not_found', 404);
    }
    $body = api_get_json();
    $item_type_id = isset($body['item_type_id']) ? (int)$body['item_type_id'] : (int)$old['item_type_id'];
    $serial_number = isset($body['serial_number']) ? trim((string)$body['serial_number']) : $old['serial_number'];
    $asset_tag = isset($body['asset_tag']) ? trim((string)$body['asset_tag']) : $old['asset_tag'];
    $manufacturer = isset($body['manufacturer']) ? trim((string)$body['manufacturer']) : $old['manufacturer'];
    $model = isset($body['model']) ? trim((string)$body['model']) : $old['model'];
    $status = isset($body['status']) ? trim((string)$body['status']) : $old['status'];
    $purchase_date = isset($body['purchase_date']) ? trim((string)$body['purchase_date']) : $old['purchase_date'];
    $purchase_cost = isset($body['purchase_cost']) ? (float)$body['purchase_cost'] : (float)$old['purchase_cost'];
    $notes = isset($body['notes']) ? trim((string)$body['notes']) : $old['notes'];
    $condition = isset($body['condition']) ? trim((string)$body['condition']) : $old['condition'];
    $active = isset($body['active']) ? (int)$body['active'] : (int)$old['active'];
    $stmt = $conn->prepare("UPDATE inventory_items SET item_type_id=?, serial_number=?, asset_tag=?, manufacturer=?, model=?, status=?, purchase_date=?, purchase_cost=?, notes=?, condition=?, active=? WHERE id=?");
    $stmt->execute([$item_type_id, $serial_number, $asset_tag, $manufacturer, $model, $status, $purchase_date, $purchase_cost, $notes, $condition, $active, $id]);
    $stmt = $conn->prepare("SELECT inv.*, it.name AS item_type_name, it.category_id AS item_type_category_id, c.name AS category_name FROM inventory_items inv LEFT JOIN item_types it ON inv.item_type_id = it.id LEFT JOIN categories c ON it.category_id=c.id WHERE inv.id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    auditLog('inventory_item_updated', 'InventoryItem', $id, $old, $row);
    api_success($row, 'Inventory item updated', 200);
}

function handle_inventory_items_delete($params) {
    global $conn;
    $id = (int)($params['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM inventory_items WHERE id=?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) {
        api_error('Inventory item not found', 'not_found', 404);
    }
    $stmt = $conn->prepare("UPDATE inventory_items SET active=0 WHERE id=?");
    $stmt->execute([$id]);
    auditLog('inventory_item_deactivated', 'InventoryItem', $id, $old, null);
    api_success(null, 'Inventory item deactivated', 200);
}
