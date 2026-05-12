<?php
session_start();
require_once 'db_connect.php';

$userId = $_SESSION['Iduser'] ?? 0;
if (!$userId) exit(json_encode(['success' => false]));

// 1. Khởi tạo Database nếu chưa có
$setupSql = "
CREATE TABLE IF NOT EXISTS bp_stats (
    user_id INT PRIMARY KEY,
    level INT DEFAULT 1,
    xp INT DEFAULT 0,
    claimed_rewards TEXT -- Lưu JSON các level đã nhận quà
);

CREATE TABLE IF NOT EXISTS bp_missions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    type ENUM('daily', 'weekly', 'lifetime'),
    action VARCHAR(50), -- e.g., 'play_game', 'win_money', 'pvp_win'
    goal INT,
    reward_xp INT
);

CREATE TABLE IF NOT EXISTS bp_user_missions (
    user_id INT,
    mission_id INT,
    progress INT DEFAULT 0,
    status ENUM('active', 'completed', 'claimed') DEFAULT 'active',
    last_updated DATE,
    PRIMARY KEY (user_id, mission_id)
);
";
// Note: Chạy tay SQL này hoặc dùng script tự động
// $conn->multi_query($setupSql); 

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_status':
        // Lấy thông tin level & XP
        $stats = $conn->query("SELECT * FROM bp_stats WHERE user_id = $userId")->fetch_assoc();
        if (!$stats) {
            $conn->query("INSERT INTO bp_stats (user_id) VALUES ($userId)");
            $stats = ['level' => 1, 'xp' => 0, 'claimed_rewards' => '[]', 'has_premium' => 0, 'premium_claimed_rewards' => '[]'];
        }
        
        // Lấy danh sách nhiệm vụ
        $missions = $conn->query("
            SELECT m.*, um.progress, um.status 
            FROM bp_missions m
            LEFT JOIN bp_user_missions um ON m.id = um.mission_id AND um.user_id = $userId
            WHERE m.type = 'daily'
        ");
        
        $missionList = [];
        while ($row = $missions->fetch_assoc()) {
            $missionList[] = $row;
        }

        // Lấy danh sách phần thưởng
        $rewards = $conn->query("SELECT * FROM bp_rewards ORDER BY level ASC, type ASC")->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'level' => $stats['level'], 
            'xp' => $stats['xp'],
            'xp_max' => $stats['level'] * 1000,
            'has_premium' => (bool)$stats['has_premium'],
            'missions' => $missionList,
            'rewards' => $rewards,
            'claimed' => json_decode($stats['claimed_rewards'] ?? '[]'),
            'premium_claimed' => json_decode($stats['premium_claimed_rewards'] ?? '[]')
        ]);
        break;

    case 'buy_premium':
        $price = 200000; // Giá Premium Pass: 200k GTLM
        $user = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc();
        if ($user['Money'] < $price) exit(json_encode(['success' => false, 'message' => 'Không đủ GTLM!']));

        $conn->begin_transaction();
        try {
            $conn->query("UPDATE users SET Money = Money - $price WHERE Iduser = $userId");
            $conn->query("UPDATE bp_stats SET has_premium = 1 WHERE user_id = $userId");
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Kích hoạt Premium Track thành công!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'claim_reward':
        $level = (int) $_POST['level'];
        $track = $_POST['track'] ?? 'free'; // 'free' hoặc 'premium'
        
        $stats = $conn->query("SELECT * FROM bp_stats WHERE user_id = $userId")->fetch_assoc();
        if ($level > $stats['level']) exit(json_encode(['success' => false, 'message' => 'Chưa đạt level!']));
        
        if ($track === 'premium' && !$stats['has_premium']) {
            exit(json_encode(['success' => false, 'message' => 'Cần mua Premium Pass!']));
        }

        $col = ($track === 'premium') ? 'premium_claimed_rewards' : 'claimed_rewards';
        $claimed = json_decode($stats[$col] ?? '[]', true);
        if (in_array($level, $claimed)) exit(json_encode(['success' => false, 'message' => 'Đã nhận rồi!']));
        
        // Lấy thông tin phần thưởng từ DB
        $stmt = $conn->prepare("SELECT * FROM bp_rewards WHERE level = ? AND type = ?");
        $stmt->bind_param("is", $level, $track);
        $stmt->execute();
        $reward = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$reward) exit(json_encode(['success' => false, 'message' => 'Không có phần thưởng cho level này!']));

        $conn->begin_transaction();
        try {
            if ($reward['reward_type'] === 'money') {
                $conn->query("UPDATE users SET Money = Money + {$reward['reward_value']} WHERE Iduser = $userId");
            }
            // (Thêm logic tặng item/skin ở đây nếu cần)

            $claimed[] = $level;
            $claimedJson = json_encode($claimed);
            $conn->query("UPDATE bp_stats SET $col = '$claimedJson' WHERE user_id = $userId");
            $conn->commit();
            echo json_encode(['success' => true, 'message' => "Bạn đã nhận: {$reward['reward_name']}"]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
}

// Hàm helper để cập nhật tiến độ (sẽ được gọi từ các file game)
function updateBPMission(mysqli $conn, int $userId, string $actionType, int $amount = 1) {
    // Lấy nhiệm vụ active của action này
    $missions = $conn->query("SELECT id, goal, reward_xp FROM bp_missions WHERE action = '$actionType'");
    while ($m = $missions->fetch_assoc()) {
        $mid = $m['id'];
        $res = $conn->query("SELECT * FROM bp_user_missions WHERE user_id = $userId AND mission_id = $mid");
        $um = $res->fetch_assoc();
        
        if (!$um) {
            $conn->query("INSERT INTO bp_user_missions (user_id, mission_id, progress) VALUES ($userId, $mid, $amount)");
        } else if ($um['status'] == 'active') {
            $newProgress = $um['progress'] + $amount;
            $status = ($newProgress >= $m['goal']) ? 'completed' : 'active';
            
            if ($status == 'completed') {
                // Cộng XP Battle Pass
                addBPXP($conn, $userId, $m['reward_xp']);
            }
            
            $conn->query("UPDATE bp_user_missions SET progress = $newProgress, status = '$status' WHERE user_id = $userId AND mission_id = $mid");
        }
    }
}

function addBPXP(mysqli $conn, int $userId, int $amount) {
    $stats = $conn->query("SELECT * FROM bp_stats WHERE user_id = $userId")->fetch_assoc();
    if (!$stats) {
        $conn->query("INSERT INTO bp_stats (user_id, xp) VALUES ($userId, $amount)");
        return;
    }
    
    $newXP = $stats['xp'] + $amount;
    $level = $stats['level'];
    $xpToNext = $level * 1000;
    
    while ($newXP >= $xpToNext) {
        $newXP -= $xpToNext;
        $level++;
        $xpToNext = $level * 1000;
    }
    
    $conn->query("UPDATE bp_stats SET level = $level, xp = $newXP WHERE user_id = $userId");
}
