<?php
require_once __DIR__ . '/db_connect.php';

class DynamicEventHelper {
    /**
     * Lấy modifier cho một game cụ thể
     */
    public static function getModifier(mysqli $conn, string $gameType) {
        $now = date('Y-m-d H:i:s');
        // FIX: dùng prepared statement thay vì ghép biến vào SQL
        $stmt = $conn->prepare("
            SELECT multiplier FROM dynamic_events 
            WHERE status = 'active' 
            AND starts_at <= ?
            AND ends_at >= ?
            AND (game_type = ? OR game_type = 'all')
            ORDER BY multiplier DESC LIMIT 1
        ");
        $stmt->bind_param("sss", $now, $now, $gameType);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (float)$row['multiplier'] : 1.0;
    }

    /**
     * Tự động sinh sự kiện mới nếu không có sự kiện nào đang active
     */
    public static function autoGenerate(mysqli $conn) {
        $now = date('Y-m-d H:i:s');
        // FIX: dùng prepared statement
        $stmtCheck = $conn->prepare("SELECT id FROM dynamic_events WHERE status = 'active' AND ends_at >= ? LIMIT 1");
        $stmtCheck->bind_param("s", $now);
        $stmtCheck->execute();
        $check = $stmtCheck->get_result();
        $stmtCheck->close();
        
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
            
            // Announce ra chat chung
            $chatMsg = "🌟 [SỰ KIỆN ĐỘNG] " . $e['name'] . " - " . $e['desc'];
            $conn->query("INSERT INTO chat_messages (user_id, username, message, avatar) VALUES (0, 'Hệ Thống', '" . $conn->real_escape_string($chatMsg) . "', 'https://cdn-icons-png.flaticon.com/512/1041/1041044.png')");
            
            return $e; // Trả về để announce
        }
        return null;
    }
    
    public static function getActiveEvent(mysqli $conn) {
        $now = date('Y-m-d H:i:s');
        // FIX: dùng prepared statement
        $stmt = $conn->prepare("SELECT * FROM dynamic_events WHERE status = 'active' AND starts_at <= ? AND ends_at >= ? LIMIT 1");
        $stmt->bind_param("ss", $now, $now);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }
}
?>
