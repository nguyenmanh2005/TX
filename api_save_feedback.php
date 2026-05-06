<?php
/**
 * API Save Feedback
 * Lưu feedback từ người dùng
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

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$type = isset($data['type']) ? $conn->real_escape_string($data['type']) : 'other';
$message = isset($data['message']) ? $conn->real_escape_string($data['message']) : '';
$email = isset($data['email']) ? $conn->real_escape_string($data['email']) : '';
$url = isset($data['url']) ? $conn->real_escape_string($data['url']) : '';
$userAgent = isset($data['userAgent']) ? $conn->real_escape_string($data['userAgent']) : '';

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message is required']);
    exit;
}

// Create feedback table if not exists
$createTableSql = "CREATE TABLE IF NOT EXISTS user_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT 0,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    email VARCHAR(255),
    url VARCHAR(500),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$conn->query($createTableSql);

// Insert feedback
$sql = "INSERT INTO user_feedback (user_id, type, message, email, url, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("isssss", $userId, $type, $message, $email, $url, $userAgent);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Feedback saved successfully',
            'id' => $stmt->insert_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save feedback: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $conn->error
    ]);
}

$conn->close();
?>

