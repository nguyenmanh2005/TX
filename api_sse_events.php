<?php
// api_sse_events.php - Xử lý Polling thay thế SSE (Giảm tải server, tránh block PHP-FPM)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

require_once 'db_connect.php';
require_once 'api_event_helper.php'; // getActiveSeasonalEvent()

session_start();
if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false]);
    exit;
}

// Lấy sự kiện mùa giải
$eventData = getActiveSeasonalEvent($conn, false, 'id, name, theme_emoji');

// Lấy sự kiện đột xuất
$randomEvent = $conn->query("SELECT id, event_name, event_type FROM random_events WHERE is_active = 1 LIMIT 1")->fetch_assoc();

echo json_encode([
    'success' => true,
    'seasonal_event' => $eventData ? [
        'id' => (int)$eventData['id'],
        'name' => $eventData['name'],
        'emoji' => $eventData['theme_emoji'] ?? '🏆'
    ] : null,
    'random_event' => $randomEvent ? [
        'id' => (int)$randomEvent['id'],
        'name' => $randomEvent['event_name'],
        'type' => $randomEvent['event_type']
    ] : null
]);
exit;
?>
