<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    // Tự động xóa các bàn trống không có ai chơi sau 1 phút (chỉ những bàn đang waiting và không đếm ngược)
    $cleanupSql = "
        DELETE FROM blackjack_multi_tables 
        WHERE status = 'waiting' 
          AND turn_expires_at IS NULL 
          AND last_activity < DATE_SUB(NOW(), INTERVAL 1 MINUTE)
          AND (SELECT COUNT(*) FROM blackjack_multi_players WHERE table_id = blackjack_multi_tables.id) = 0
    ";
    $conn->query($cleanupSql);

    // Lấy danh sách các phòng đang mở
    $stmt = $conn->prepare("
        SELECT t.*, 
               (SELECT COUNT(*) FROM blackjack_multi_players WHERE table_id = t.id) as player_count
        FROM blackjack_multi_tables t 
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
    
    $stmt = $conn->prepare("INSERT INTO blackjack_multi_tables (room_name, min_bet, max_bet, status, dealer_cards) VALUES (?, ?, ?, 'waiting', '[]')");
    $stmt->bind_param("sdd", $roomName, $minBet, $maxBet);
    
    if ($stmt->execute()) {
        $newTableId = $conn->insert_id;
        
        if ($botCount > 0) {
            $botCount = min(5, $botCount);
            for ($i = 0; $i < $botCount; $i++) {
                $botId = -rand(1000, 9999);
                
                // Random bot amount based on realistic values within table limits
                $possibleBets = [10000, 20000, 50000, 100000, 200000, 500000, 1000000, 2000000, 5000000];
                $validBets = array_filter($possibleBets, function($b) use ($minBet, $maxBet) {
                    return $b >= $minBet && $b <= $maxBet;
                });
                if (empty($validBets)) $validBets = [$minBet];
                $botAmount = $validBets[array_rand($validBets)];
                
                $initialCard = '[]';
                $seatIndex = $i; // Seats 0 to botCount-1
                
                $stmt = $conn->prepare("INSERT INTO blackjack_multi_players (table_id, user_id, seat_index, bet_amount, cards, status, is_bot) VALUES (?, ?, ?, ?, ?, 'waiting', 1)");
                $stmt->bind_param("iiids", $newTableId, $botId, $seatIndex, $botAmount, $initialCard);
                $stmt->execute();
            }
            
            // Cập nhật lại turn_expires_at cho bàn vì có bot
            $nextExpiry = date('Y-m-d H:i:s', time() + 45);
            $stmt = $conn->prepare("UPDATE blackjack_multi_tables SET turn_expires_at = ? WHERE id = ?");
            $stmt->bind_param("si", $nextExpiry, $newTableId);
            $stmt->execute();
        }
        
        echo json_encode(['success' => true, 'table_id' => $newTableId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi tạo phòng']);
    }
    exit;
}
