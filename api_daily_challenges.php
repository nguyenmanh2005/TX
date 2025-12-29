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

if ($action === 'claim') {
    $challengeId = (int)($_POST['challenge_id'] ?? 0);
    
    if ($challengeId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID thử thách không hợp lệ!']);
        exit();
    }
    
    // Kiểm tra thử thách
    $sql = "SELECT dc.*, dcp.progress, dcp.is_completed, dcp.claimed
            FROM daily_challenges dc
            LEFT JOIN daily_challenge_progress dcp ON dc.id = dcp.challenge_id AND dcp.user_id = ?
            WHERE dc.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $challengeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $challenge = $result->fetch_assoc();
    $stmt->close();
    
    if (!$challenge) {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thử thách!']);
        exit();
    }
    
    if ($challenge['is_completed'] != 1) {
        echo json_encode(['status' => 'error', 'message' => 'Chưa hoàn thành thử thách!']);
        exit();
    }
    
    if ($challenge['claimed'] == 1) {
        echo json_encode(['status' => 'error', 'message' => 'Đã nhận phần thưởng rồi!']);
        exit();
    }
    
    // Cập nhật claimed
    $conn->begin_transaction();
    try {
        $sql = "UPDATE daily_challenge_progress 
                SET claimed = 1, claimed_at = NOW()
                WHERE user_id = ? AND challenge_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $challengeId);
        $stmt->execute();
        $stmt->close();
        
        // Cộng tiền thưởng
        if ($challenge['reward_money'] > 0) {
            $sql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $challenge['reward_money'], $userId);
            $stmt->execute();
            $stmt->close();
        }
        
        // Cộng XP thưởng
        if ($challenge['reward_xp'] > 0) {
            up_add_xp($conn, $userId, $challenge['reward_xp']);
        }
        
        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Nhận phần thưởng thành công! +' . number_format($challenge['reward_money']) . ' VNĐ, +' . number_format($challenge['reward_xp']) . ' XP'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ!']);
}
?>

