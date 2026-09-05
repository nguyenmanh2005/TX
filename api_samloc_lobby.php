<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (!isset($_SESSION['Iduser']) && !isset($_SESSION['Iduser_temp_bot'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$userId = isset($_SESSION['Iduser_temp_bot']) && (int)$_SESSION['Iduser_temp_bot'] > 0 ? (int)$_SESSION['Iduser_temp_bot'] : (int)$_SESSION['Iduser'];


$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    // Xóa bàn trống
    $cleanupSql = "
        DELETE FROM samloc_multi_tables 
        WHERE last_activity < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
          AND (SELECT COUNT(*) FROM samloc_multi_players WHERE table_id = samloc_multi_tables.id AND is_bot = 0) = 0
    ";
    $conn->query($cleanupSql);

    // Lấy danh sách phòng
    $stmt = $conn->prepare("
        SELECT t.*, 
               (SELECT COUNT(*) FROM samloc_multi_players WHERE table_id = t.id) as player_count
        FROM samloc_multi_tables t 
        WHERE t.status IN ('waiting', 'playing') 
        ORDER BY t.id DESC 
        LIMIT 20
    ");
    $stmt->execute();
    $tables = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'tables' => $tables]);
    exit;
}

if ($action === 'create') {
    $roomName = trim($_POST['room_name'] ?? 'Bàn VIP ' . rand(100, 999));
    $minBet = (float)($_POST['min_bet'] ?? 10000);
    $maxBet = (float)($_POST['max_bet'] ?? 50000000);
    $botCount = isset($_POST['bot_count']) ? (int)$_POST['bot_count'] : 0;
    
    if ($minBet <= 0 || $maxBet < $minBet) {
        echo json_encode(['success' => false, 'message' => 'Mức cược không hợp lệ']);
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO samloc_multi_tables (room_name, min_bet, max_bet, status, passed_players, last_move) VALUES (?, ?, ?, 'waiting', '[]', 'null')");
    $stmt->bind_param("sdd", $roomName, $minBet, $maxBet);
    
    if ($stmt->execute()) {
        $newTableId = $conn->insert_id;
        
        // Thêm bot nếu có
        if ($botCount > 0) {
            $botCount = min(4, $botCount); // Max 5 players in Sam Loc, so 1 creator + up to 4 bots
            for ($i = 0; $i < $botCount; $i++) {
                $botId = -rand(1000, 9999);
                $seatIndex = $i + 1; // leave seat 0 for creator
                $initialCard = '[]';
                
                $stmt = $conn->prepare("INSERT INTO samloc_multi_players (table_id, user_id, seat_index, cards, status, is_bot) VALUES (?, ?, ?, ?, 'waiting', 1)");
                $stmt->bind_param("iiis", $newTableId, $botId, $seatIndex, $initialCard);
                $stmt->execute();
            }
            
            // Có bot thì auto start sau 5s (turn_expires_at)
            $nextExpiry = date('Y-m-d H:i:s', time() + 5);
            $stmt = $conn->prepare("UPDATE samloc_multi_tables SET turn_expires_at = ? WHERE id = ?");
            $stmt->bind_param("si", $nextExpiry, $newTableId);
            $stmt->execute();
        }
        
        echo json_encode(['success' => true, 'table_id' => $newTableId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi tạo phòng: ' . $conn->error]);
    }
    exit;
}
