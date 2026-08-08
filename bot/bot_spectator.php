<?php
/**
 * Bot Spectator Logic
 * Giả lập hành vi bot đi hóng chuyện, xem livestream và buff cho Idol
 */

function handleSpectatorBot($baseUrl, $cFile, $botMoney) {
    $actions = [];

    // Tỉ lệ 10% bot rảnh rỗi đi xem live
    if (rand(1, 100) <= 10) {
        $liveRes = executeBotAction($baseUrl . "/api_spectator.php", ['action' => 'get_live'], $cFile, true);
        
        if (isset($liveRes['success']) && $liveRes['success']) {
            $lives = $liveRes['lives'] ?? [];
            if (!empty($lives)) {
                $targetLive = $lives[array_rand($lives)];
                $streamId = $targetLive['id'];
                $streamerName = $targetLive['streamer_name'];

                $rnd = rand(1, 100);
                
                // 30% Gửi Emoji thả tim
                if ($rnd <= 30) {
                    $emojis = ['❤️', '🔥', '😂', '🤑', '💸', '🚀'];
                    $emoji = $emojis[array_rand($emojis)];
                    $actRes = executeBotAction($baseUrl . "/api_spectator.php", [
                        'action' => 'send_reaction',
                        'stream_id' => $streamId,
                        'emoji' => $emoji
                    ], $cFile, true);
                    
                    if (isset($actRes['success']) && $actRes['success']) {
                        $actions[] = "Vừa vào luồng Live của Idol <b>$streamerName</b> thả nhẹ cái Emoji $emoji";
                    }
                }
                // 30% Gửi Chat cổ vũ/cà khịa
                else if ($rnd <= 60) {
                    $chats = [
                        "Idol $streamerName húp mạnh lên nào!",
                        "Nhìn pha xử lý mù mắt quá Idol ơi =))",
                        "Chuẩn bị bay màu nè, tôi đoán trước rồi!",
                        "Đẳng cấp đấy, chiến tiếp đi Idol!",
                        "Có cần em bơm bùa cho không Idol?",
                        "Cháy quá Idol ơi, làm ván All-in đi!"
                    ];
                    $chatMsg = $chats[array_rand($chats)];
                    
                    $actRes = executeBotAction($baseUrl . "/api_spectator.php", [
                        'action' => 'send_chat',
                        'stream_id' => $streamId,
                        'message' => $chatMsg
                    ], $cFile, true);
                    
                    if (isset($actRes['success']) && $actRes['success']) {
                        $actions[] = "Đang đứng hóng Live của <b>$streamerName</b> và bình luận: <i>\"$chatMsg\"</i>";
                    }
                }
                // 20% Mua bùa (Buff) nếu dư dả > 50k
                else if ($rnd <= 80 && $botMoney > 50000) {
                    $buffs = ['luck', 'hype', 'shield'];
                    $buffType = $buffs[array_rand($buffs)];
                    
                    $actRes = executeBotAction($baseUrl . "/api_spectator.php", [
                        'action' => 'purchase_buff',
                        'stream_id' => $streamId,
                        'buff_type' => $buffType
                    ], $cFile, true);
                    
                    if (isset($actRes['success']) && $actRes['success']) {
                        $actions[] = "Đại gia vung GTLM mua Bùa <b>" . strtoupper($buffType) . "</b> buff cho Idol <b>$streamerName</b>!";
                    }
                }
                // 20% Tip thẳng GTLM nếu siêu giàu > 1M
                else if ($botMoney > 1000000) {
                    $tipAmount = rand(5, 20) * 1000; // Tip 5k - 20k
                    
                    $actRes = executeBotAction($baseUrl . "/api_spectator.php", [
                        'action' => 'tip',
                        'stream_id' => $streamId,
                        'amount' => $tipAmount
                    ], $cFile, true);
                    
                    if (isset($actRes['success']) && $actRes['success']) {
                        $actions[] = "Phóng khoáng Tip nóng " . number_format($tipAmount) . " GTLM cho Idol <b>$streamerName</b> đang Live!";
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
