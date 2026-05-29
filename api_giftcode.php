<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'claim_random') {
    // Để tránh bot spam vô hạn làm lạm phát tiền tệ, cho bot nhận một khoản tiền nhỏ ngẫu nhiên từ 50,000 đến 150,000 GTLM
    $amount = rand(50000, 150000);
    
    $conn->begin_transaction();
    try {
        // Khóa hàng của user trước để cập nhật an toàn
        $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmtLock->bind_param("i", $userId);
        $stmtLock->execute();
        $user = $stmtLock->get_result()->fetch_assoc();
        $stmtLock->close();

        if (!$user) {
            throw new Exception("Không tìm thấy người dùng!");
        }

        // Cộng tiền cho user
        $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
        $stmt->bind_param("di", $amount, $userId);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Nhận giftcode thành công! +' . number_format($amount) . ' GTLM',
            'amount' => $amount
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Lỗi: ' . $e->getMessage()
        ]);
    }
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!']);
}
?>
