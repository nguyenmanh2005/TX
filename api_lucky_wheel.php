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
    $totalRewardStmt = $conn->query("SELECT COUNT(*) as total FROM lucky_wheel_rewards WHERE is_active = 1");
    $totalRewardRow = $totalRewardStmt ? $totalRewardStmt->fetch_assoc() : null;
    $totalRewards = $totalRewardRow ? $totalRewardRow['total'] : 0;

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

    // --- MONTHLY PASS INTEGRATION: x2 Rewards ---
    $hasMonthlyPass = false;
    $checkPass = $conn->query("SELECT COUNT(*) as total FROM user_monthly_pass WHERE user_id = $userId AND expiry_date > NOW()");
    if ($checkPass && $checkPass->fetch_assoc()['total'] > 0) {
        $hasMonthlyPass = true;
    }

    $finalRewardValue = $selectedReward['reward_value'];
    if ($hasMonthlyPass && $selectedReward['reward_type'] === 'money') {
        $finalRewardValue *= 2;
    }

    // Cấp phần thưởng
    $rewardGiven = false;

    if ($selectedReward['reward_type'] === 'money' && $finalRewardValue > 0) {
        // Cấp gtlm
        $updateMoneySql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
        $updateMoneyStmt = $conn->prepare($updateMoneySql);
        $updateMoneyStmt->bind_param("di", $finalRewardValue, $userId);
        $updateMoneyStmt->execute();
        $updateMoneyStmt->close();
        $rewardGiven = true;
    } elseif ($selectedReward['reward_type'] === 'theme') {
        // Cấp theme - kiểm tra và thêm vào user_themes
        if ($selectedReward['reward_value'] > 0) {
            $checkThemeStmt = $conn->prepare("SELECT * FROM user_themes WHERE user_id = ? AND theme_id = ?");
            $checkThemeStmt->bind_param("ii", $userId, $selectedReward['reward_value']);
            $checkThemeStmt->execute();
            $checkThemeResult = $checkThemeStmt->get_result();
            if ($checkThemeResult->num_rows === 0) {
                // Người dùng chưa có theme này, thêm vào
                $addThemeStmt = $conn->prepare("INSERT INTO user_themes (user_id, theme_id) VALUES (?, ?)");
                $addThemeStmt->bind_param("ii", $userId, $selectedReward['reward_value']);
                $addThemeStmt->execute();
                $addThemeStmt->close();
            }
            $checkThemeStmt->close();
            $rewardGiven = true;
        }
    } elseif ($selectedReward['reward_type'] === 'cursor') {
        // Cấp cursor - kiểm tra và thêm vào user_cursors
        if ($selectedReward['reward_value'] > 0) {
            $checkCursorStmt = $conn->prepare("SELECT * FROM user_cursors WHERE user_id = ? AND cursor_id = ?");
            $checkCursorStmt->bind_param("ii", $userId, $selectedReward['reward_value']);
            $checkCursorStmt->execute();
            $checkCursorResult = $checkCursorStmt->get_result();
            if ($checkCursorResult->num_rows === 0) {
                // Người dùng chưa có cursor này, thêm vào
                $addCursorStmt = $conn->prepare("INSERT INTO user_cursors (user_id, cursor_id) VALUES (?, ?)");
                $addCursorStmt->bind_param("ii", $userId, $selectedReward['reward_value']);
                $addCursorStmt->execute();
                $addCursorStmt->close();
            }
            $checkCursorStmt->close();
            $rewardGiven = true;
        }
    }
    // Nếu reward_value = 0 hoặc reward_type không hợp lệ, không cấp gì (Chúc may mắn lần sau)

    // Lưu lịch sử quay
    $insertSql = "INSERT INTO lucky_wheel_logs (user_id, reward_id, reward_type, reward_value, reward_name, spin_date) 
                  VALUES (?, ?, ?, ?, ?, ?)";
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param(
        "iisdss",
        $userId,
        $selectedReward['id'],
        $selectedReward['reward_type'],
        $finalRewardValue,
        $selectedReward['reward_name'],
        $today
    );
    $insertStmt->execute();
    $insertStmt->close();

    $message = '';
    if ($selectedReward['reward_type'] === 'money' && $finalRewardValue > 0) {
        $vipText = $hasMonthlyPass ? " (X2 VIP BONUS!)" : "";
        $message = '🎉 Chúc mừng! Bạn nhận được ' . number_format($finalRewardValue, 0, ',', '.') . ' gtlm!' . $vipText;
    } else {
        $message = '😢 ' . $selectedReward['reward_name'];
    }

    echo json_encode([
        'status' => 'success',
        'reward' => $selectedReward,
        'final_value' => $finalRewardValue,
        'has_vip' => $hasMonthlyPass,
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
} else {
    echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ']);
}

?>