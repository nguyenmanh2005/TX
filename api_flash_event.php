<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['active' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$res = $conn->query("SELECT * FROM flash_events WHERE status = 'active' AND NOW() BETWEEN start_time AND end_time LIMIT 1");
$event = $res->fetch_assoc();

if ($event) {
    echo json_encode([
        'active' => true,
        'multiplier' => (float)$event['multiplier'],
        'start_time' => $event['start_time'],
        'end_time' => $event['end_time'],
        'current_time' => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode(['active' => false]);
}
