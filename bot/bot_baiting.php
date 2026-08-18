<?php
/**
 * Bot Gà Mồi (Baiting)
 * Tìm người chơi thật trên server và khích tướng họ vào cược Coinflip lớn.
 */

function handleBaitingBot($conn, $baseUrl, $cFile, $botMoney, $botId, $botName, $botAvatar) {
    $actions = [];

    // Tỉ lệ thấp 2% cho WHALE bot
    if (rand(1, 100) <= 2 && $botMoney > 500000) {
        // Tìm 1 người chơi thật (không có @gmail.com) có nhiều GTLM
        $query = "SELECT Iduser, Name, Money FROM users 
                  WHERE Email NOT LIKE '%@gmail.com%' 
                  AND Role < 10 
                  AND Money >= 100000 
                  ORDER BY RAND() LIMIT 1";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $target = $result->fetch_assoc();
            
            // Tính số GTLM thách đấu: 10% đến 20% GTLM của nạn nhân (hoặc tối đa số GTLM bot đang có)
            $baitAmount = rand((int)($target['Money'] * 0.1), (int)($target['Money'] * 0.2));
            if ($baitAmount > $botMoney) {
                $baitAmount = $botMoney;
            }
            $baitAmount = floor($baitAmount);

            if ($baitAmount >= 50000) {
                // Gửi thách đấu Coinflip tới người chơi đó
                $pvpRes = executeBotAction($baseUrl . "/api_pvp_challenge.php", [
                    'action' => 'create_challenge',
                    'opponent_id' => $target['Iduser'],
                    'game_type' => 'coinflip',
                    'bet_amount' => $baitAmount
                ], $cFile, true);
                
                if (isset($pvpRes['success']) && $pvpRes['success']) {
                    $actions[] = "Tạo phòng Coinflip " . number_format($baitAmount) . " GTLM nhắm vào " . htmlspecialchars($target['Name']);
                    
                    // Chat khích tướng lên Kênh Thế Giới
                    $trashTalk = [
                        "@" . $target['Name'] . " dạo này thấy bú đậm, có dám vào húp " . number_format($baitAmount) . " GTLM của tao không? 🐔",
                        "@" . $target['Name'] . " tao lập phòng " . number_format($baitAmount) . " GTLM rồi, ngon thì vào mà ăn ngập mặt! 💸",
                        "Ê @" . $target['Name'] . ", có bản lĩnh thì vào gõ Coinflip với tao ván " . number_format($baitAmount) . " GTLM xem ai bay màu trước? 🔥",
                        "Nghe giang hồ đồn @" . $target['Name'] . " dạo này đang đỏ, vào chiến kèo " . number_format($baitAmount) . " GTLM giải ảo nào! ⚔️"
                    ];
                    
                    $msg = $trashTalk[array_rand($trashTalk)];
                    $sysId = $botId;
                    
                    $msgStmt = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $msgStmt->bind_param("isss", $botId, $botName, $msg, $botAvatar);
                    $msgStmt->execute();
                    $msgStmt->close();
                    
                    $actions[] = "Đã chửi bới khích tướng trên Kênh Chat Thế Giới!";
                }
            }
        }
    }

    if (!empty($actions)) {
        return ['status' => 'success', 'actions' => $actions];
    }
    
    return null;
}
?>
