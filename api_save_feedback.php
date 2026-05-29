<?php
/**
 * API Save Feedback
 * LÆ°u feedback tá»« ngÆ°á»i dÃ¹ng
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

