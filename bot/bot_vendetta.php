<?php
/**
 * Bot Thù Dai (Vendetta System)
 * Phân tích lịch sử thua lỗ để lưu vào Danh Sách Đen (Death Note).
 * Bot sẽ dùng danh sách này để ghim thù và mỉa mai trên Kênh Chat (thông qua Smart AI) 
 * hoặc trực tiếp gửi Lời Thách Đấu PvP tới Kẻ thù.
 */

function handleVendettaBot($conn, $baseUrl, $cFile, $botMoney, $botId, $botName, $botAvatar) {
    $actions = [];
    $rivalFile = __DIR__ . '/sessions/rivalries.json';
    $rivals = file_exists($rivalFile) ? json_decode(file_get_contents($rivalFile), true) : [];
    
    if (!isset($rivals[$botId])) {
        $rivals[$botId] = [];
    }

    // 1. Phân tích Thù Hận (Học từ Lịch Sử) - 10% cơ hội mỗi lượt để lục lại hồ sơ
    if (rand(1, 100) <= 10) {
        // Tìm 5 trận PvP thua gần nhất của bot này trước người chơi thật
        $query = "SELECT h.winner_id, u.Name as winner_name, h.bet_amount 
                  FROM pvp_match_history h
                  JOIN users u ON h.winner_id = u.Iduser
                  WHERE (h.challenger_id = $botId OR h.opponent_id = $botId)
                  AND h.winner_id != $botId 
                  AND u.Email NOT LIKE '%@gmail.com%'
                  ORDER BY h.id DESC LIMIT 5";
        
        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $eId = $row['winner_id'];
                if (!isset($rivals[$botId][$eId])) {
                    $rivals[$botId][$eId] = [
                        'name' => $row['winner_name'],
                        'score' => 0,
                        'amount_lost' => 0,
                        'last_encounter' => date('Y-m-d H:i:s')
                    ];
                }
                $rivals[$botId][$eId]['score'] += 1;
                $rivals[$botId][$eId]['amount_lost'] += $row['bet_amount'];
                $rivals[$botId][$eId]['last_encounter'] = date('Y-m-d H:i:s');
            }
            // Sắp xếp giữ lại top 10 kẻ thù
            uasort($rivals[$botId], fn($a, $b) => $b['score'] <=> $a['score']);
            $rivals[$botId] = array_slice($rivals[$botId], 0, 10, true);
            file_put_contents($rivalFile, json_encode($rivals, JSON_PRETTY_PRINT));
        }
    }

    // 2. Trả thù Chủ Động (Chỉ 2% cơ hội và phải có vốn)
    if (rand(1, 100) <= 2 && $botMoney > 100000 && !empty($rivals[$botId])) {
        // Lấy ngẫu nhiên 1 kẻ thù trong danh sách
        $enemyId = array_rand($rivals[$botId]);
        $enemy = $rivals[$botId][$enemyId];
        
        // Thách cược = 1.5 lần số GTLM đã mất hoặc tối thiểu 50k
        $betAmount = max(50000, min($botMoney, $enemy['amount_lost'] * 1.5));
        $betAmount = floor($betAmount);
        
        if ($betAmount >= 50000) {
            // Tạo thách đấu mới nhắm thẳng vào kẻ thù
            $pvpRes = executeBotAction($baseUrl . "/api_pvp_challenge.php", [
                'action' => 'create_challenge',
                'opponent_id' => $enemyId,
                'game_type' => 'coinflip',
                'bet_amount' => $betAmount
            ], $cFile, true);
            
            if (isset($pvpRes['success']) && $pvpRes['success']) {
                $actions[] = "Sách trắng trả thù! Đã gạ kèo @" . htmlspecialchars($enemy['name']) . " với " . number_format($betAmount) . " GTLM!";
                
                // Mỉa mai lên kênh chat
                $trashTalk = [
                    "@" . $enemy['name'] . " hôm trước húp của tao " . number_format($enemy['amount_lost']) . " GTLM, nay tao ghim rồi đấy, ra đây chiến tiếp! 😡",
                    "Ê @" . $enemy['name'] . " ăn xong định chuồn à? Tao báo thù kèo " . number_format($betAmount) . " GTLM rồi đấy, dám nhận không? 🔪",
                    "Kẻ thù không đội trời chung! @" . $enemy['name'] . " ra đây tái đấu xem hôm nay ai bay màu! 🩸",
                    "Quân tử trả thù 10 năm chưa muộn! @" . $enemy['name'] . " tao thách đấu " . number_format($betAmount) . " GTLM, mau vào đền mạng! 🔥"
                ];
                
                $msg = $trashTalk[array_rand($trashTalk)];
                
                $msgStmt = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar, created_at) VALUES (?, ?, ?, ?, NOW())");
                if ($msgStmt) {
                    $msgStmt->bind_param("isss", $botId, $botName, $msg, $botAvatar);
                    $msgStmt->execute();
                    $msgStmt->close();
                }
                $actions[] = "Đã dán cáo thị báo thù lên Kênh Chat!";
            }
        }
    }

    if (!empty($actions)) {
        return ['status' => 'success', 'actions' => $actions];
    }
    
    return null;
}
?>
