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
            $stats = ['level' => 1, 'xp' => 0, 'claimed_rewards' => '[]'];
        }
        
        // Lấy danh sách nhiệm vụ
        $today = date('Y-m-d');
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
        
        echo json_encode([
            'success' => true, 
            'level' => $stats['level'], 
            'xp' => $stats['xp'],
            'xp_max' => $stats['level'] * 1000, // Mỗi level cần level * 1000 XP
            'missions' => $missionList,
            'claimed' => json_decode($stats['claimed_rewards'] ?? '[]')
        ]);
        break;

    case 'claim_reward':
        $level = (int) $_POST['level'];
        $stats = $conn->query("SELECT * FROM bp_stats WHERE user_id = $userId")->fetch_assoc();
        if ($level > $stats['level']) exit(json_encode(['success' => false, 'message' => 'Chưa đạt level!']));
        
        $claimed = json_decode($stats['claimed_rewards'] ?? '[]', true);
        if (in_array($level, $claimed)) exit(json_encode(['success' => false, 'message' => 'Đã nhận rồi!']));
        
        // Phát thưởng theo level
        $rewardMoney = $level * 50000; // Ví dụ: Level 1 nhận 50k, Level 2 nhận 100k...
        
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE users SET Money = Money + $rewardMoney WHERE Iduser = $userId");
            $claimed[] = $level;
            $claimedJson = json_encode($claimed);
            $conn->query("UPDATE bp_stats SET claimed_rewards = '$claimedJson' WHERE user_id = $userId");
            $conn->commit();
            echo json_encode(['success' => true, 'reward' => $rewardMoney]);
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
