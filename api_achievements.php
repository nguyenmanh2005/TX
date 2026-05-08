<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập!']);
    exit();
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? 'check_all';

// Logic check achievements tương tự achievements.php
if ($action === 'check_all') {
    $unlockedCount = 0;
    
    // 1. Lấy tất cả achievements
    $allAchievements = [];
    $res = $conn->query("SELECT * FROM achievements");
    while($row = $res->fetch_assoc()) $allAchievements[] = $row;
    
    // 2. Lấy achievements đã có
    $unlockedIds = [];
    $res = $conn->query("SELECT achievement_id FROM user_achievements WHERE user_id = $userId");
    while($row = $res->fetch_assoc()) $unlockedIds[] = $row['achievement_id'];
    
    // 3. Kiểm tra dữ liệu cần thiết
    $userMoney = 0;
    $res = $conn->query("SELECT Money FROM users WHERE Iduser = $userId");
    if($row = $res->fetch_assoc()) $userMoney = $row['Money'];
    
    $gamesPlayed = 0;
    $res = $conn->query("SELECT COUNT(*) as count FROM game_history WHERE user_id = $userId");
    if($row = $res->fetch_assoc()) $gamesPlayed = (int)$row['count'];
    
    $currentStreak = 0;
    $res = $conn->query("SELECT current_streak FROM user_streaks WHERE user_id = $userId");
    if($row = $res->fetch_assoc()) $currentStreak = (int)$row['current_streak'];

    foreach ($allAchievements as $achievement) {
        if (in_array($achievement['id'], $unlockedIds)) continue;
        
        $progress = 0;
        $maxProgress = $achievement['requirement_value'];
        
        switch ($achievement['requirement_type']) {
            case 'money': $progress = $userMoney; break;
            case 'games_played': $progress = $gamesPlayed; break;
            case 'streak': $progress = $currentStreak; break;
            case 'big_win':
                $res = $conn->query("SELECT COUNT(*) as count FROM game_history WHERE user_id = $userId AND win_amount >= $maxProgress");
                if($row = $res->fetch_assoc()) $progress = (int)$row['count'];
                break;
        }
        
        if ($progress >= $maxProgress && $maxProgress > 0) {
            $achId = (int)$achievement['id'];
            $conn->query("INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES ($userId, $achId)");
            
            if ($conn->affected_rows > 0) {
                $unlockedCount++;
                // Thưởng GTLM
                if ($achievement['reward_money'] > 0) {
                    $reward = (int)$achievement['reward_money'];
                    $conn->query("UPDATE users SET Money = Money + $reward WHERE Iduser = $userId");
                }
            }
        }
    }
    
    echo json_encode(['status' => 'success', 'unlocked' => $unlockedCount]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ!']);
}
