<?php
// cron_bot_rankings.php
// Chạy mỗi ngày 1 lần để quét TOP 10 Bot và cập nhật Role

if (php_sapi_name() !== 'cli' && (!isset($_GET['secret']) || $_GET['secret'] !== 'bot_rank_secret')) {
    die("Access denied");
}

require_once __DIR__ . '/db_connect.php';

$today = date('Y-m-d');

// Lấy TOP 10 Bot
$sql = "SELECT Iduser, Name, Money FROM users WHERE Email REGEXP '^bot[0-9]+@' ORDER BY Money DESC LIMIT 10";
$res = $conn->query($sql);

$topBots = [];
$rank = 1;
while ($row = $res->fetch_assoc()) {
    $row['rank'] = $rank++;
    $topBots[] = $row;
}

$conn->begin_transaction();

try {
    // Đánh dấu tất cả bot chưa cập nhật hôm nay (để sau đó những bot không lọt top sẽ bị reset)
    $botIdsInTop = array_column($topBots, 'Iduser');
    
    foreach ($topBots as $bot) {
        $botId = (int)$bot['Iduser'];
        $rankPos = $bot['rank'];
        
        // Xác định category: 1 = Top 2-5 (Thương Gia), 2 = Top 6-10 (KOC)
        $category = 0;
        if ($rankPos >= 2 && $rankPos <= 5) {
            $category = 1;
        } else if ($rankPos >= 6 && $rankPos <= 10) {
            $category = 2;
        }
        
        if ($category > 0) {
            // Kiểm tra streak cũ
            $stmt = $conn->prepare("SELECT current_streak_days, last_rank_category, last_updated FROM bot_rank_streaks WHERE bot_id = ?");
            $stmt->bind_param("i", $botId);
            $stmt->execute();
            $streakData = $stmt->get_result()->fetch_assoc();
            
            $newStreak = 1;
            if ($streakData) {
                // Nếu cùng category và ngày cập nhật là hôm qua, tăng streak
                if ($streakData['last_rank_category'] == $category && $streakData['last_updated'] == date('Y-m-d', strtotime('-1 day'))) {
                    $newStreak = $streakData['current_streak_days'] + 1;
                } else if ($streakData['last_updated'] == $today) {
                    // Đã chạy hôm nay rồi, bỏ qua
                    continue;
                }
            }
            
            // Cập nhật streak
            $stmt = $conn->prepare("INSERT INTO bot_rank_streaks (bot_id, current_streak_days, last_rank_category, last_updated) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE current_streak_days = VALUES(current_streak_days), last_rank_category = VALUES(last_rank_category), last_updated = VALUES(last_updated)");
            $stmt->bind_param("iiis", $botId, $newStreak, $category, $today);
            $stmt->execute();
            
            // Cấp Role nếu đủ 7 ngày
            if ($newStreak >= 7) {
                $roleToAssign = ($category == 1) ? 3 : 2; // 1: Thương Gia (Role 3), 2: KOC (Role 2)
                $conn->query("UPDATE users SET Role = $roleToAssign WHERE Iduser = $botId");
                
                // Ghi log
                $logDir = __DIR__ . '/bot/logs/';
                if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
                $file = $logDir . date('Y-m-d') . '.log';
                $timestamp = date('H:i:s d/m');
                $roleName = ($roleToAssign == 3) ? 'Thương Gia Vàng' : 'KOC';
                @file_put_contents($file, "[$timestamp] [INFO] [SYSTEM] GACHA: Bot {$bot['Name']} đã giữ vững Top liên tiếp 7 ngày và được thăng cấp $roleName!" . PHP_EOL, FILE_APPEND);
            }
        } else {
            // Top 1: Reset streak
            $conn->query("UPDATE bot_rank_streaks SET current_streak_days = 0, last_updated = '$today' WHERE bot_id = $botId");
        }
    }
    
    // Bất kỳ bot nào có trong bảng bot_rank_streaks mà hôm nay không có trong Top 10 (và chưa được update hôm nay) sẽ bị reset
    if (!empty($botIdsInTop)) {
        $idsStr = implode(',', $botIdsInTop);
        $conn->query("UPDATE bot_rank_streaks SET current_streak_days = 0, last_updated = '$today' WHERE bot_id NOT IN ($idsStr) AND last_updated != '$today'");
    }
    
    $conn->commit();
    echo "Cron bot rankings executed successfully at " . date('Y-m-d H:i:s');
} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage();
}
