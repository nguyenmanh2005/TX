<?php
header('Content-Type: application/json');
session_start();
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['Iduser'] ?? 0;
    $username = $_SESSION['Name'] ?? 'Guest';
    $errorMessage = $_POST['error_message'] ?? '';
    $errorStack = $_POST['error_stack'] ?? '';
    $pageUrl = $_POST['page_url'] ?? '';

    if (empty($errorMessage)) {
        echo json_encode(['success' => false, 'message' => 'Empty error message']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO error_logs (user_id, username, error_message, error_stack, page_url) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $userId, $username, $errorMessage, $errorStack, $pageUrl);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
}
?>
