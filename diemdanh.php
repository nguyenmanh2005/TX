<?php
session_start();
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

// Load theme
require_once 'load_theme.php';

// Kiểm tra kết nối database
if (!$conn || $conn->connect_error) {
    die("Lỗi kết nối database: " . ($conn ? $conn->connect_error : "Không thể kết nối"));
}

$userId = $_SESSION['Iduser'];
$today = date('Y-m-d');

// Kiểm tra bảng daily_checkin có tồn tại không
$checkTable = $conn->query("SHOW TABLES LIKE 'daily_checkin'");
$dailyCheckinExists = $checkTable && $checkTable->num_rows > 0;

if ($dailyCheckinExists) {
    // Kiểm tra đã điểm danh hôm nay chưa
    $sql = "SELECT * FROM daily_checkin WHERE user_id = ? AND checkin_date = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("is", $userId, $today);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $_SESSION['msg'] = "Bạn đã điểm danh hôm nay rồi!";
        } else {
            // Cộng tiền và ghi log
            $reward = 10000; // hoặc random 1k - 10k
            $updateStmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            if ($updateStmt) {
                $updateStmt->bind_param("di", $reward, $userId);
                $updateStmt->execute();
                $updateStmt->close();
            }

            $insertStmt = $conn->prepare("INSERT INTO daily_checkin (user_id, checkin_date) VALUES (?, ?)");
            if ($insertStmt) {
                $insertStmt->bind_param("is", $userId, $today);
                $insertStmt->execute();
                $insertStmt->close();
            }

            $_SESSION['msg'] = "Điểm danh thành công! Bạn nhận được " . number_format($reward) . " VNĐ";
        }
        $stmt->close();
    }
} else {
    $_SESSION['msg'] = "⚠️ Chức năng điểm danh chưa được kích hoạt. Vui lòng liên hệ admin.";
}

header("Location: index.php");
exit();
