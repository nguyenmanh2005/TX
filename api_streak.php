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

if ($action === 'claim_milestone') {
    $milestoneDays = (int)($_POST['milestone_days'] ?? 0);
    
    if ($milestoneDays <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Milestone không hợp lệ!']);
        exit();
    }
    
    $milestones = [
        3 => ['reward' => 50000, 'xp' => 50],
        7 => ['reward' => 150000, 'xp' => 150],
        14 => ['reward' => 350000, 'xp' => 350],
        30 => ['reward' => 1000000, 'xp' => 1000],
        60 => ['reward' => 2500000, 'xp' => 2500],
        100 => ['reward' => 5000000, 'xp' => 5000]
    ];
    
    if (!isset($milestones[$milestoneDays])) {
        echo json_encode(['status' => 'error', 'message' => 'Milestone không tồn tại!']);
        exit();
    }
    
    // Kiểm tra streak hiện tại
    $sql = "SELECT current_streak FROM user_streaks WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $streakData = $result->fetch_assoc();
    $stmt->close();
    
    if (!$streakData || $streakData['current_streak'] < $milestoneDays) {
        echo json_encode(['status' => 'error', 'message' => 'Chưa đạt streak cần thiết!']);
        exit();
    }
    
    // Kiểm tra đã claim chưa
    $sql = "SELECT id FROM streak_milestone_rewards WHERE user_id = ? AND milestone_days = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $milestoneDays);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $stmt->close();
        echo json_encode(['status' => 'error', 'message' => 'Đã nhận phần thưởng rồi!']);
        exit();
    }
    $stmt->close();
    
    // Claim reward
    $conn->begin_transaction();
    try {
        $reward = $milestones[$milestoneDays];
        
        // Ghi lại đã claim
        $sql = "INSERT INTO streak_milestone_rewards (user_id, milestone_days, reward_money, reward_xp)
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiii", $userId, $milestoneDays, $reward['reward'], $reward['xp']);
        $stmt->execute();
        $stmt->close();
        
        // Cộng tiền
        $sql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $reward['reward'], $userId);
        $stmt->execute();
        $stmt->close();
        
        // Cộng XP
        up_add_xp($conn, $userId, $reward['xp']);
        
        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Nhận phần thưởng thành công! +' . number_format($reward['reward']) . ' VNĐ, +' . number_format($reward['xp']) . ' XP'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ!']);
}
?>

