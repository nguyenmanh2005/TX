<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập!']);
    exit();
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? '';

if ($action === 'mark_read') {
    $notificationId = (int)($_POST['notification_id'] ?? 0);
    
    if ($notificationId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID thông báo không hợp lệ!']);
        exit();
    }
    
    // Kiểm tra notification thuộc về user
    $sql = "SELECT id FROM achievement_notifications WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notificationId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        $stmt->close();
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thông báo!']);
        exit();
    }
    $stmt->close();
    
    // Đánh dấu đã đọc
    $sql = "UPDATE achievement_notifications SET is_read = 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $notificationId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['status' => 'success', 'message' => 'Đã đánh dấu đã đọc!']);
    
} elseif ($action === 'mark_all_read') {
    // Đánh dấu tất cả đã đọc
    $sql = "UPDATE achievement_notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    echo json_encode([
        'status' => 'success', 
        'message' => "Đã đánh dấu $affected thông báo đã đọc!"
    ]);
    
} else {
    echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ!']);
}
?>

