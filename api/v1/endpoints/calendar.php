<?php

function handle_calendar_list($params) {
    global $conn;
    $start = $_GET['start'] ?? date('Y-m-01');
    $end = $_GET['end'] ?? date('Y-m-t');
    $stmt = $conn->prepare('SELECT b.id, b.booking_number, b.date_from, b.date_to, b.event_start_time, b.event_end_time, b.status, b.client_id, b.quoted_amount, c.name as client_name FROM bookings b INNER JOIN clients c ON b.client_id = c.id WHERE b.status != ? AND (b.date_from <= ? AND b.date_to >= ?)');
    $stmt->execute(['Canceled', $end, $start]);
    $rows = $stmt->fetchAll();
    $colorMap = [
        'Draft' => '#94a3b8',
        'Quotation' => '#f59e0b',
        'Confirmed' => '#10b981',
        'Change Requested' => '#8b5cf6',
        'Event Completed' => '#0ea5e9',
        'Closed' => '#64748b',
        'Canceled' => '#ef4444',
    ];
    $events = [];
    foreach ($rows as $r) {
        $st = !empty($r['event_start_time']) ? ' ' . $r['event_start_time'] : ' 00:00:00';
        $et = !empty($r['event_end_time']) ? ' ' . $r['event_end_time'] : ' 23:59:59';
        $start_dt = $r['date_from'] . $st;
        $end_dt = $r['date_to'] . $et;
        $title = $r['booking_number'] . ' | ' . $r['client_name'];
        $status = $r['status'];
        $color = $colorMap[$status] ?? '#94a3b8';
        $events[] = [
            'id' => $r['id'],
            'title' => $title,
            'start' => $start_dt,
            'end' => $end_dt,
            'status' => $status,
            'color' => $color,
            'extended_props' => [
                'client_id' => $r['client_id'],
                'client_name' => $r['client_name'],
                'booking_number' => $r['booking_number'],
                'quoted_amount' => (float)$r['quoted_amount'],
            ],
        ];
    }
    api_success($events);
}

function handle_calendar_download($params) {
    global $conn;
    $id = $params['id'] ?? null;
    if ($id === null) {
        api_error('Booking not found', 'not_found', 404);
    }
    $stmt = $conn->prepare('SELECT b.*, c.name as client_name FROM bookings b INNER JOIN clients c ON b.client_id = c.id WHERE b.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        api_error('Booking not found', 'not_found', 404);
    }
    $booking = $row;
    $client = ['id' => $booking['client_id'], 'name' => $booking['client_name']];
    $st = !empty($booking['event_start_time']) ? $booking['event_start_time'] : '00:00:00';
    $et = !empty($booking['event_end_time']) ? $booking['event_end_time'] : '23:59:59';
    $dtstart = date('Ymd\THis\Z', strtotime($booking['date_from'] . ' ' . $st));
    $dtend = date('Ymd\THis\Z', strtotime($booking['date_to'] . ' ' . $et));
    $location = isset($booking['location']) ? $booking['location'] : '';
    $site_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
    $description = 'Event at client ' . $booking['client_name'];
    $ics = "BEGIN:VCALENDAR\r\n";
    $ics .= "VERSION:2.0\r\n";
    $ics .= "PRODID:-//DJ RAK//EN\r\n";
    $ics .= "BEGIN:VEVENT\r\n";
    $ics .= "UID:bk-" . $booking['id'] . "@djrak\r\n";
    $ics .= "SUMMARY:" . $booking['booking_number'] . " - " . $booking['client_name'] . "\r\n";
    $ics .= "DTSTART:" . $dtstart . "\r\n";
    $ics .= "DTEND:" . $dtend . "\r\n";
    $ics .= "LOCATION:" . $location . "\r\n";
    $ics .= "DESCRIPTION:" . $description . "\r\n";
    $ics .= "URL:" . $site_url . "\r\n";
    $ics .= "END:VEVENT\r\n";
    $ics .= "END:VCALENDAR\r\n";
    api_success([
        'mime' => 'text/calendar',
        'filename' => 'booking-' . $booking['booking_number'] . '.ics',
        'ical_data' => $ics,
    ]);
}
