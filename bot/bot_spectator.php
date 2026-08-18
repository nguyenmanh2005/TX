<?php
/**
 * Bot Spectator Logic
 * Giả lập hành vi bot đi hóng chuyện, xem livestream và buff cho Idol
 */

function handleSpectatorBot($baseUrl, $cFile, $botMoney, $brain, $userId, $userName, $justWon = false) {
    $actions = [];

    // Tỉ lệ Bot rảnh rỗi đi xem live, ưu tiên cao nếu vừa thắng ($justWon)
    if ($justWon || rand(1, 100) <= 15) {
        $liveRes = executeBotAction($baseUrl . "/api_spectator.php", ['action' => 'get_tables'], $cFile);
        
        if (isset($liveRes['success']) && $liveRes['success']) {
            $lives = $liveRes['tables'] ?? [];
            if (!empty($lives)) {
                $targetLive = $lives[array_rand($lives)];
                $tableId = $targetLive['id'];
                $streamerName = $targetLive['streamer_name'];
                $gameName = $targetLive['name']; // Tên chuẩn (VD: Trận Địa Trắng Đỏ, Đại Chiến Thần Kê)

                $rnd = rand(1, 100);
                
                // --- TẶNG QUÀ / TIP GTLM ---
                // Điều kiện: Vừa thắng game, hoặc Tùy hứng (khi có nhiều tiền)
                if ($justWon || ($rnd <= 20 && $botMoney > 500000)) {
                    if (rand(1, 100) > 50 && $botMoney > 1000000) {
                        // Tặng quà Tiktok (Siêu xe, Hoa hồng, Tên lửa...)
                        $gifts = ['rose', 'coffee', 'beer', 'rocket'];
                        $giftId = $gifts[array_rand($gifts)];
                        $combo = rand(1, 3);
                        $actRes = executeBotAction($baseUrl . "/api_spectator.php", [
                            'action' => 'gift_tiktok',
                            'table_id' => $tableId,
                            'gift_id' => $giftId,
                            'combo' => $combo
                        ], $cFile);
                        
                        if (isset($actRes['success']) && $actRes['success']) {
                            $actions[] = "Tùy hứng vung tay tặng quà <b>Combo x$combo $giftId</b> cho Idol <b>$streamerName</b> ở $gameName!";
                            // Bắn kèm câu chat gáy
                            $msg = $justWon ? "Vừa húp đậm xong, qua $gameName tặng quà lấy lộc cho Idol $streamerName nhé!" : "Nay vui tính, tặng nhẹ món quà cho Idol $streamerName!";
                            executeBotAction($baseUrl . "/api_spectator.php", [
                                'action' => 'send_chat',
                                'table_id' => $tableId,
                                'message' => $msg
                            ], $cFile);
                        }
                    } else {
                        // Tip GTLM
                        $tipAmount = rand(10, 50) * 1000;
                        $actRes = executeBotAction($baseUrl . "/api_spectator.php", [
                            'action' => 'tip',
                            'table_id' => $tableId,
                            'amount' => $tipAmount
                        ], $cFile);
                        
                        if (isset($actRes['success']) && $actRes['success']) {
                            $actions[] = "Phóng khoáng Tip nóng " . number_format($tipAmount) . " GTLM cho Idol <b>$streamerName</b> ở $gameName!";
                            $msg = $justWon ? "Vừa bú đậm nơi khác, Tip Idol $streamerName " . number_format($tipAmount) . " GTLM xả láng!" : "Cho Idol $streamerName xin " . number_format($tipAmount) . " GTLM lấy lộc, cháy quá!";
                            executeBotAction($baseUrl . "/api_spectator.php", [
                                'action' => 'send_chat',
                                'table_id' => $tableId,
                                'message' => $msg
                            ], $cFile);
                        }
                    }
                }
                // --- BÌNH LUẬN AI (Chuẩn Từ Vựng) ---
                else if ($rnd <= 60) {
                    $chats = [
                        "Idol $streamerName húp mạnh lên nào, $gameName nay đang nhả đó!",
                        "Nhìn pha xử lý ở $gameName mù mắt quá Idol ơi =))",
                        "Chuẩn bị bay màu nè, tôi đoán trước rồi!",
                        "Đẳng cấp đấy, chiến tiếp ở $gameName đi Idol!",
                        "Có ai theo Idol $streamerName kèo này không?",
                        "Cháy quá Idol ơi, làm ván All-in lấy số má đi!"
                    ];
                    $chatMsg = $chats[array_rand($chats)];
                    
                    $actRes = executeBotAction($baseUrl . "/api_spectator.php", [
                        'action' => 'send_chat',
                        'table_id' => $tableId,
                        'message' => $chatMsg
                    ], $cFile);
                    
                    if (isset($actRes['success']) && $actRes['success']) {
                        $actions[] = "Đang đứng hóng Live <b>$streamerName</b> ($gameName) và bình luận: <i>\"$chatMsg\"</i>";
                    }
                }
                // --- THẢ EMOJI ---
                else {
                    $emojis = ['❤️', '🔥', '😂', '🤑', '💸', '🚀'];
                    $emoji = $emojis[array_rand($emojis)];
                    $actRes = executeBotAction($baseUrl . "/api_spectator.php", [
                        'action' => 'send_reaction',
                        'table_id' => $tableId,
                        'emoji' => $emoji
                    ], $cFile);
                    
                    if (isset($actRes['success']) && $actRes['success']) {
                        $actions[] = "Vừa vào luồng Live của Idol <b>$streamerName</b> thả nhẹ cái Emoji $emoji";
                    }
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
