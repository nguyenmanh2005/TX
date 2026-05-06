<?php
/**
 * API Track Analytics
 * Lưu analytics events
 */

header('Content-Type: application/json');
session_start();

require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$userId = isset($_SESSION['Iduser']) ? (int)$_SESSION['Iduser'] : 0;
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['events'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

// Create analytics table if not exists
$createTableSql = "CREATE TABLE IF NOT EXISTS analytics_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT 0,
    session_id VARCHAR(100),
    event_name VARCHAR(100) NOT NULL,
    event_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id),
    INDEX idx_event_name (event_name),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$conn->query($createTableSql);

$success = true;
$inserted = 0;

foreach ($data['events'] as $event) {
    $sessionId = isset($event['sessionId']) ? $conn->real_escape_string($event['sessionId']) : '';
    $eventName = isset($event['name']) ? $conn->real_escape_string($event['name']) : '';
    $eventData = isset($event['data']) ? json_encode($event['data']) : '{}';
    
    $sql = "INSERT INTO analytics_events (user_id, session_id, event_name, event_data) 
            VALUES (?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("isss", $userId, $sessionId, $eventName, $eventData);
        if ($stmt->execute()) {
            $inserted++;
        } else {
            $success = false;
        }
        $stmt->close();
    } else {
        $success = false;
    }
}

echo json_encode([
    'success' => $success,
    'inserted' => $inserted,
    'total' => count($data['events'])
]);

$conn->close();
?>

