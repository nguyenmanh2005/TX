<?php
session_start();
require 'db_connect.php';
require_once 'user_progress_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập!']);
    exit();
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? '';

if ($action === 'redeem') {
    $rewardId = (int)($_POST['reward_id'] ?? 0);
    
    if ($rewardId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID phần thưởng không hợp lệ!']);
        exit();
    }
    
    // Lấy thông tin reward
    $sql = "SELECT * FROM reward_point_rewards WHERE id = ? AND is_active = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $rewardId);
    $stmt->execute();
    $result = $stmt->get_result();
    $reward = $result->fetch_assoc();
    $stmt->close();
    
    if (!$reward) {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy phần thưởng!']);
        exit();
    }
    
    // Lấy thông tin points của user
    $sql = "SELECT * FROM reward_points WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userPoints = $result->fetch_assoc();
    $stmt->close();
    
    if (!$userPoints) {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thông tin điểm!']);
        exit();
    }
    
    if ($userPoints['available_points'] < $reward['cost_points']) {
        echo json_encode(['status' => 'error', 'message' => 'Không đủ điểm để đổi!']);
        exit();
    }
    
    // Kiểm tra stock limit nếu có
    if ($reward['stock_limit'] !== null) {
        $sql = "SELECT COUNT(*) as count FROM reward_point_transactions 
                WHERE transaction_type = 'redeem' AND related_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $rewardId);
        $stmt->execute();
        $result = $stmt->get_result();
        $redeemedCount = $result->fetch_assoc()['count'] ?? 0;
        $stmt->close();
        
        if ($redeemedCount >= $reward['stock_limit']) {
            echo json_encode(['status' => 'error', 'message' => 'Phần thưởng đã hết!']);
            exit();
        }
    }
    
    // Đổi thưởng
    $conn->begin_transaction();
    try {
        // Trừ points
        $sql = "UPDATE reward_points 
                SET available_points = available_points - ?, 
                    total_points = total_points - ?
                WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $reward['cost_points'], $reward['cost_points'], $userId);
        $stmt->execute();
        $stmt->close();
        
        // Ghi transaction
        $sql = "INSERT INTO reward_point_transactions 
                (user_id, points, transaction_type, description, related_id)
                VALUES (?, ?, 'redeem', ?, ?)";
        $stmt = $conn->prepare($sql);
        $points = -$reward['cost_points'];
        $description = "Đổi " . $reward['name'];
        $stmt->bind_param("iisi", $userId, $points, $description, $rewardId);
        $stmt->execute();
        $stmt->close();
        
        // Cấp phần thưởng
        if ($reward['reward_type'] === 'money') {
            $sql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $reward['reward_value'], $userId);
            $stmt->execute();
            $stmt->close();
        } elseif ($reward['reward_type'] === 'xp') {
            up_add_xp($conn, $userId, $reward['reward_value']);
        }
        
        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Đổi thưởng thành công! Nhận được ' . number_format($reward['reward_value']) . 
                        ($reward['reward_type'] === 'money' ? ' VNĐ' : ' XP')
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ!']);
}
?>

