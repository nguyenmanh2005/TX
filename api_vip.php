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

if ($action === 'claim_daily_bonus') {
    $today = date('Y-m-d');
    
    // Lấy thông tin VIP
    $sql = "SELECT uv.*, vl.daily_bonus 
            FROM user_vip uv
            LEFT JOIN vip_levels vl ON uv.vip_level = vl.level
            WHERE uv.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userVip = $result->fetch_assoc();
    $stmt->close();
    
    if (!$userVip) {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thông tin VIP!']);
        exit();
    }
    
    if ($userVip['daily_bonus'] <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'VIP level của bạn chưa có daily bonus!']);
        exit();
    }
    
    $lastClaimed = $userVip['daily_bonus_claimed'] ?? null;
    if ($lastClaimed == $today) {
        echo json_encode(['status' => 'error', 'message' => 'Đã nhận daily bonus hôm nay rồi!']);
        exit();
    }
    
    // Claim bonus
    $conn->begin_transaction();
    try {
        // Cập nhật daily_bonus_claimed
        $sql = "UPDATE user_vip SET daily_bonus_claimed = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $today, $userId);
        $stmt->execute();
        $stmt->close();
        
        // Cộng tiền
        $sql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $userVip['daily_bonus'], $userId);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Nhận daily bonus thành công! +' . number_format($userVip['daily_bonus']) . ' VNĐ'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ!']);
}
?>

