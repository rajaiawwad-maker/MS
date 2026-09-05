<?php
function api_paginate($sql, $params = [], $page = 1, $perPage = 20, $countSql = null, $countParams = []) {
    global $conn;
    $page = max(1, (int)$page);
    $perPage = min(100, max(1, (int)$perPage));
    $offset = ($page - 1) * $perPage;

    if ($countSql === null) {
        $trimmed = ltrim($sql);
        if (stripos($trimmed, 'SELECT') === 0) {
            $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') x';
            $countParams = $params;
        } else {
            $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') x';
            $countParams = $params;
        }
    }

    $stmt = $conn->prepare($countSql);
    $stmt->execute($countParams);
    $total = (int)$stmt->fetchColumn();
    $total_pages = $total > 0 ? (int)ceil($total / $perPage) : 0;

    $mainSql = rtrim($sql, "; \t\n\r\0\x0B") . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;
    $stmt = $conn->prepare($mainSql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    return [
        'data' => $rows,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $total_pages,
        ],
    ];
}
