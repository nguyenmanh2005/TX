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

if ($action === 'claim_reward') {
    // Tính tuần hiện tại
    $today = new DateTime();
    $dayOfWeek = $today->format('w');
    if ($dayOfWeek == 0) {
        $dayOfWeek = 7;
    }
    $daysToMonday = $dayOfWeek - 1;
    $weekStart = clone $today;
    $weekStart->modify("-$daysToMonday days");
    $weekStartStr = $weekStart->format('Y-m-d');
    
    // Lấy rank và reward
    $sql = "SELECT rank_position, reward_claimed FROM weekly_leaderboard 
            WHERE week_start = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $weekStartStr, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    $stmt->close();
    
    if (!$userData) {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy dữ liệu của bạn!']);
        exit();
    }
    
    if ($userData['reward_claimed'] == 1) {
        echo json_encode(['status' => 'error', 'message' => 'Đã nhận phần thưởng rồi!']);
        exit();
    }
    
    $rank = $userData['rank_position'];
    
    // Xác định reward
    $rankRewards = [
        1 => ['money' => 5000000, 'xp' => 5000],
        2 => ['money' => 3000000, 'xp' => 3000],
        3 => ['money' => 2000000, 'xp' => 2000],
        4 => ['money' => 1000000, 'xp' => 1000],
        10 => ['money' => 500000, 'xp' => 500],
        50 => ['money' => 200000, 'xp' => 200]
    ];
    
    $reward = null;
    if ($rank == 1) {
        $reward = $rankRewards[1];
    } elseif ($rank == 2) {
        $reward = $rankRewards[2];
    } elseif ($rank == 3) {
        $reward = $rankRewards[3];
    } elseif ($rank <= 10) {
        $reward = $rankRewards[4];
    } elseif ($rank <= 50) {
        $reward = $rankRewards[10];
    } elseif ($rank <= 100) {
        $reward = $rankRewards[50];
    }
    
    if (!$reward) {
        echo json_encode(['status' => 'error', 'message' => 'Bạn không đủ điều kiện nhận phần thưởng!']);
        exit();
    }
    
    // Claim reward
    $conn->begin_transaction();
    try {
        // Đánh dấu đã claim
        $sql = "UPDATE weekly_leaderboard SET reward_claimed = 1 WHERE week_start = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $weekStartStr, $userId);
        $stmt->execute();
        $stmt->close();
        
        // Cộng tiền
        $sql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $reward['money'], $userId);
        $stmt->execute();
        $stmt->close();
        
        // Cộng XP
        up_add_xp($conn, $userId, $reward['xp']);
        
        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Nhận phần thưởng thành công! +' . number_format($reward['money']) . ' VNĐ, +' . number_format($reward['xp']) . ' XP'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ!']);
}
?>

