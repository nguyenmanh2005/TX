<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập']);
    exit();
}

$userId = $_SESSION['Iduser'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Kiểm tra bảng tồn tại
$checkRewardsTable = $conn->query("SHOW TABLES LIKE 'lucky_wheel_rewards'");
$checkLogsTable = $conn->query("SHOW TABLES LIKE 'lucky_wheel_logs'");

if (!$checkRewardsTable || $checkRewardsTable->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Hệ thống Lucky Wheel chưa được kích hoạt. Vui lòng chạy file create_lucky_wheel_tables.sql']);
    exit();
}

// Kiểm tra đã quay hôm nay chưa
if ($action === 'check_spin') {
    $today = date('Y-m-d');
    
    $sql = "SELECT * FROM lucky_wheel_logs WHERE user_id = ? AND spin_date = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $userId, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $hasSpun = $result->num_rows > 0;
    $lastSpin = null;
    
    if ($hasSpun) {
        $lastSpin = $result->fetch_assoc();
    }
    
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'has_spun' => $hasSpun,
        'last_spin' => $lastSpin
    ]);
}

// Lấy danh sách phần thưởng
elseif ($action === 'get_rewards') {
    $sql = "SELECT * FROM lucky_wheel_rewards WHERE is_active = 1 ORDER BY probability DESC, id ASC";
    $result = $conn->query($sql);
    
    $rewards = [];
    while ($row = $result->fetch_assoc()) {
        $rewards[] = $row;
    }
    
    echo json_encode([
        'status' => 'success',
        'rewards' => $rewards
    ]);
}

// Quay wheel
elseif ($action === 'spin') {
    $today = date('Y-m-d');
    
    // Kiểm tra đã quay hôm nay chưa
    $checkSql = "SELECT * FROM lucky_wheel_logs WHERE user_id = ? AND spin_date = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("is", $userId, $today);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $checkStmt->close();
        echo json_encode([
            'status' => 'error',
            'message' => 'Bạn đã quay wheel hôm nay rồi! Quay lại vào ngày mai nhé.'
        ]);
        exit();
    }
    $checkStmt->close();
    
    // Lấy tất cả rewards active
    $sql = "SELECT * FROM lucky_wheel_rewards WHERE is_active = 1";
    $result = $conn->query($sql);
    
    $rewards = [];
    while ($row = $result->fetch_assoc()) {
        // Thêm reward vào mảng theo probability
        for ($i = 0; $i < $row['probability']; $i++) {
            $rewards[] = $row;
        }
    }
    
    // Chọn ngẫu nhiên một reward
    if (count($rewards) == 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Không có phần thưởng nào!'
        ]);
        exit();
    }
    
    $selectedReward = $rewards[array_rand($rewards)];
    
    // Tính góc quay (360 độ chia cho số lượng rewards)
    $totalRewards = $conn->query("SELECT COUNT(*) as total FROM lucky_wheel_rewards WHERE is_active = 1")->fetch_assoc()['total'];
    
    // Lấy danh sách rewards theo thứ tự để tính index
    $rewardsListSql = "SELECT id FROM lucky_wheel_rewards WHERE is_active = 1 ORDER BY id ASC";
    $rewardsListResult = $conn->query($rewardsListSql);
    $rewardsList = [];
    $rewardIndex = 0;
    $index = 0;
    while ($row = $rewardsListResult->fetch_assoc()) {
        if ($row['id'] == $selectedReward['id']) {
            $rewardIndex = $index;
        }
        $rewardsList[] = $row['id'];
        $index++;
    }
    
    // Góc của phần thưởng được chọn (tính từ trên cùng, theo chiều kim đồng hồ)
    $sectorAngle = 360 / $totalRewards;
    // Góc bắt đầu của sector (từ -90 độ để bắt đầu từ trên)
    $startAngle = -90 + ($rewardIndex * $sectorAngle);
    // Góc giữa của sector
    $targetAngle = $startAngle + ($sectorAngle / 2);
    
    // Thêm số vòng quay ngẫu nhiên (5-10 vòng)
    $spinRotations = rand(5, 10);
    // Tính góc quay cuối cùng (quay ngược lại để pointer trỏ đúng phần thưởng)
    $finalAngle = ($spinRotations * 360) + (360 - $targetAngle);
    
    // Cấp phần thưởng
    $rewardGiven = false;
    
    if ($selectedReward['reward_type'] === 'money' && $selectedReward['reward_value'] > 0) {
        // Cấp tiền
        $updateMoneySql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
        $updateMoneyStmt = $conn->prepare($updateMoneySql);
        $updateMoneyStmt->bind_param("di", $selectedReward['reward_value'], $userId);
        $updateMoneyStmt->execute();
        $updateMoneyStmt->close();
        $rewardGiven = true;
    } elseif ($selectedReward['reward_type'] === 'theme' && $selectedReward['reward_value'] > 0) {
        // Cấp theme - kiểm tra và thêm vào user_themes
        $themeId = (int)$selectedReward['reward_value'];
        
        // Kiểm tra bảng user_themes có tồn tại không
        $checkTable = $conn->query("SHOW TABLES LIKE 'user_themes'");
        if ($checkTable && $checkTable->num_rows > 0) {
            // Kiểm tra user đã có theme này chưa
            $checkSql = "SELECT * FROM user_themes WHERE user_id = ? AND theme_id = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("ii", $userId, $themeId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $checkStmt->close();
            
            if ($result->num_rows == 0) {
                // Thêm theme vào user_themes
                $insertSql = "INSERT INTO user_themes (user_id, theme_id, is_active) VALUES (?, ?, 0)";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->bind_param("ii", $userId, $themeId);
                $insertStmt->execute();
                $insertStmt->close();
                $rewardGiven = true;
            } else {
                // User đã có theme này rồi
                $rewardGiven = true; // Vẫn coi như đã cấp (tránh lỗi)
            }
        } else {
            $rewardGiven = true; // Bảng chưa tồn tại, bỏ qua
        }
    } elseif ($selectedReward['reward_type'] === 'cursor' && $selectedReward['reward_value'] > 0) {
        // Cấp cursor - kiểm tra và thêm vào user_cursors
        $cursorId = (int)$selectedReward['reward_value'];
        
        // Kiểm tra bảng user_cursors có tồn tại không
        $checkTable = $conn->query("SHOW TABLES LIKE 'user_cursors'");
        if ($checkTable && $checkTable->num_rows > 0) {
            // Kiểm tra user đã có cursor này chưa
            $checkSql = "SELECT * FROM user_cursors WHERE user_id = ? AND cursor_id = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("ii", $userId, $cursorId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $checkStmt->close();
            
            if ($result->num_rows == 0) {
                // Thêm cursor vào user_cursors
                $insertSql = "INSERT INTO user_cursors (user_id, cursor_id, is_active) VALUES (?, ?, 0)";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->bind_param("ii", $userId, $cursorId);
                $insertStmt->execute();
                $insertStmt->close();
                $rewardGiven = true;
            } else {
                // User đã có cursor này rồi
                $rewardGiven = true; // Vẫn coi như đã cấp (tránh lỗi)
            }
        } else {
            $rewardGiven = true; // Bảng chưa tồn tại, bỏ qua
        }
    }
    // Nếu reward_value = 0 hoặc reward_type không hợp lệ, không cấp gì (Chúc may mắn lần sau)
    
    // Lưu lịch sử quay
    $insertSql = "INSERT INTO lucky_wheel_logs (user_id, reward_id, reward_type, reward_value, reward_name, spin_date) 
                  VALUES (?, ?, ?, ?, ?, ?)";
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param("iisdss", 
        $userId, 
        $selectedReward['id'], 
        $selectedReward['reward_type'], 
        $selectedReward['reward_value'], 
        $selectedReward['reward_name'],
        $today
    );
    $insertStmt->execute();
    $insertStmt->close();
    
    $message = '';
    if ($selectedReward['reward_type'] === 'money' && $selectedReward['reward_value'] > 0) {
        $message = '🎉 Chúc mừng! Bạn nhận được ' . number_format($selectedReward['reward_value'], 0, ',', '.') . ' VNĐ!';
    } else {
        $message = '😢 ' . $selectedReward['reward_name'];
    }
    
    echo json_encode([
        'status' => 'success',
        'reward' => $selectedReward,
        'angle' => $finalAngle,
        'reward_given' => $rewardGiven,
        'message' => $message
    ]);
}

// Lấy lịch sử quay (10 lần gần nhất)
elseif ($action === 'get_history') {
    $sql = "SELECT lwl.*, lwr.icon, lwr.color 
            FROM lucky_wheel_logs lwl
            LEFT JOIN lucky_wheel_rewards lwr ON lwl.reward_id = lwr.id
            WHERE lwl.user_id = ?
            ORDER BY lwl.spun_at DESC
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'history' => $history
    ]);
}

else {
    echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ']);
}

?>

