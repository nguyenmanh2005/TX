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

if ($action === 'buy') {
    $passTypeId = (int)($_POST['id'] ?? 0);
    
    // Lấy thông tin gói
    $stmt = $conn->prepare("SELECT * FROM monthly_pass_types WHERE id = ?");
    $stmt->bind_param("i", $passTypeId);
    $stmt->execute();
    $passType = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$passType) {
        echo json_encode(['status' => 'error', 'message' => 'Gói không tồn tại!']);
        exit();
    }

    // Lấy tiền user
    $user = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc();
    if ($user['Money'] < $passType['price']) {
        echo json_encode(['status' => 'error', 'message' => 'Bạn không đủ GTLM!']);
        exit();
    }

    $conn->begin_transaction();
    try {
        // Trừ tiền
        $conn->query("UPDATE users SET Money = Money - {$passType['price']} WHERE Iduser = $userId");

        // Kiểm tra xem đã có gói chưa
        $stmt = $conn->prepare("SELECT id, expiry_date FROM user_monthly_pass WHERE user_id = ? AND pass_type_id = ? AND expiry_date > NOW()");
        $stmt->bind_param("ii", $userId, $passTypeId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            // Gia hạn
            $newExpiry = date('Y-m-d H:i:s', strtotime($existing['expiry_date'] . " + {$passType['duration_days']} days"));
            $stmt = $conn->prepare("UPDATE user_monthly_pass SET expiry_date = ? WHERE id = ?");
            $stmt->bind_param("si", $newExpiry, $existing['id']);
            $stmt->execute();
            $stmt->close();
        } else {
            // Mua mới (Xóa các gói cũ đã hết hạn nếu có của cùng user để sạch DB - tùy chọn)
            $newExpiry = date('Y-m-d H:i:s', strtotime("+{$passType['duration_days']} days"));
            $stmt = $conn->prepare("INSERT INTO user_monthly_pass (user_id, pass_type_id, expiry_date) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $userId, $passTypeId, $newExpiry);
            $stmt->execute();
            $stmt->close();
        }

        // Cộng instant bonus
        if ($passType['instant_bonus'] > 0) {
            $conn->query("UPDATE users SET Money = Money + {$passType['instant_bonus']} WHERE Iduser = $userId");
        }

        // Ghi log
        require_once 'game_history_helper.php';
        logGameHistory($conn, $userId, 'MONTHLY PASS', $passType['price'], (float)$passType['instant_bonus'], true);

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => "Bạn đã sở hữu {$passType['name']}!"]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
    }

} elseif ($action === 'claim') {
    $today = date('Y-m-d');
    
    // Lấy gói đang hoạt động
    $sql = "SELECT ump.*, mpt.daily_bonus, mpt.name 
            FROM user_monthly_pass ump
            JOIN monthly_pass_types mpt ON ump.pass_type_id = mpt.id
            WHERE ump.user_id = ? AND ump.expiry_date > NOW()
            ORDER BY ump.expiry_date DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $activePass = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$activePass) {
        echo json_encode(['status' => 'error', 'message' => 'Bạn không có gói Monthly Pass nào đang hoạt động!']);
        exit();
    }

    if ($activePass['last_claimed_date'] === $today) {
        echo json_encode(['status' => 'error', 'message' => 'Bạn đã nhận thưởng hôm nay rồi!']);
        exit();
    }

    $conn->begin_transaction();
    try {
        // Cập nhật ngày nhận
        $stmt = $conn->prepare("UPDATE user_monthly_pass SET last_claimed_date = ? WHERE id = ?");
        $stmt->bind_param("si", $today, $activePass['id']);
        $stmt->execute();
        $stmt->close();

        // Cộng tiền
        $conn->query("UPDATE users SET Money = Money + {$activePass['daily_bonus']} WHERE Iduser = $userId");

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => "Đã nhận " . number_format($activePass['daily_bonus']) . " GTLM từ {$activePass['name']}!"]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ!']);
}
?>
