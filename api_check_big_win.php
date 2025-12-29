<?php
session_start();
header('Content-Type: application/json');

require 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $userId = $_SESSION['Iduser'] ?? null;
    $winAmount = isset($_POST['win_amount']) ? (float)$_POST['win_amount'] : 0;
    
    if (!$userId || $winAmount < 10000000) { // 10 triệu
        echo json_encode(["status" => "skip"]);
        exit();
    }
    
    // Lấy thông tin người dùng
    $userSql = "SELECT Name FROM users WHERE Iduser = ?";
    $userStmt = $conn->prepare($userSql);
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $user = $userResult->fetch_assoc();
    $userStmt->close();
    
    if (!$user) {
        echo json_encode(["status" => "error", "message" => "User not found"]);
        exit();
    }
    
    // Tạo thông báo toàn server
    $message = "🎉 " . htmlspecialchars($user['Name']) . " vừa thắng lớn " . number_format($winAmount, 0, ',', '.') . " VNĐ! Chúc mừng! 🎊";
    $expiresAt = date('Y-m-d H:i:s', time() + 30); // 30 giây
    
    $insertSql = "INSERT INTO server_notifications (user_id, user_name, message, amount, notification_type, expires_at) 
                  VALUES (?, ?, ?, ?, 'big_win', ?)";
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param("issds", $userId, $user['Name'], $message, $winAmount, $expiresAt);
    
    if ($insertStmt->execute()) {
        echo json_encode([
            "status" => "success",
            "notification_id" => $conn->insert_id,
            "message" => $message
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to create notification"]);
    }
    
    $insertStmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
}

$conn->close();
?>

