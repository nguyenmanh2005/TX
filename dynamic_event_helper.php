<?php
require_once 'db_connect.php';

class DynamicEventHelper {
    /**
     * Lấy modifier cho một game cụ thể
     */
    public static function getModifier(mysqli $conn, string $gameType) {
        $now = date('Y-m-d H:i:s');
        $sql = "SELECT multiplier FROM dynamic_events 
                WHERE status = 'active' 
                AND starts_at <= '$now' 
                AND ends_at >= '$now' 
                AND (game_type = '$gameType' OR game_type = 'all')
                ORDER BY multiplier DESC LIMIT 1";
        $res = $conn->query($sql);
        if ($res && $row = $res->fetch_assoc()) {
            return (float)$row['multiplier'];
        }
        return 1.0;
    }

    /**
     * Tự động sinh sự kiện mới nếu không có sự kiện nào đang active
     */
    public static function autoGenerate(mysqli $conn) {
        $now = date('Y-m-d H:i:s');
        $check = $conn->query("SELECT id FROM dynamic_events WHERE status = 'active' AND ends_at >= '$now' LIMIT 1");
        
        if ($check && $check->num_rows === 0) {
            // Không có sự kiện nào, sinh ngẫu nhiên
            $eventTypes = [
                ['name' => 'Gió Đổi Chiều', 'game' => 'crash', 'mult' => 1.5, 'desc' => 'Gió đã đổi chiều! Tất cả phần thưởng Crash x1.5 trong 2 tiếng tới!'],
                ['name' => 'Đêm Blackjack', 'game' => 'blackjack', 'mult' => 1.3, 'desc' => 'Đêm nay là của Blackjack! Thưởng thắng x1.3!'],
                ['name' => 'Bão Tài Xỉu', 'game' => 'taixiu', 'mult' => 1.2, 'desc' => 'Bão đang về! Tài Xỉu thưởng x1.2 cho mọi ván thắng!'],
                ['name' => 'Giờ Vàng GTLM', 'game' => 'all', 'mult' => 1.2, 'desc' => 'GIỜ VÀNG! Tất cả các game thưởng x1.2!']
            ];
            
            $e = $eventTypes[array_rand($eventTypes)];
            $duration = rand(1, 3); // 1-3 tiếng
            $starts = date('Y-m-d H:i:s');
            $ends = date('Y-m-d H:i:s', strtotime("+$duration hours"));
            
            $stmt = $conn->prepare("INSERT INTO dynamic_events (name, description, game_type, multiplier, starts_at, ends_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssdss", $e['name'], $e['desc'], $e['game'], $e['mult'], $starts, $ends);
            $stmt->execute();
            
            return $e; // Trả về để announce
        }
        return null;
    }
    
    public static function getActiveEvent(mysqli $conn) {
        $now = date('Y-m-d H:i:s');
        return $conn->query("SELECT * FROM dynamic_events WHERE status = 'active' AND starts_at <= '$now' AND ends_at >= '$now' LIMIT 1")->fetch_assoc();
    }
}
?>
