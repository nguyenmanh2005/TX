<?php
/**
 * Bot Guild Logic
 * Cho phép Bot tự động xin vào Guild, Chat trong Guild và mua sắm trong Guild Shop.
 */

function handleGuildBot($baseUrl, $cFile, $botMoney) {
    $actions = [];

    // 1. Kiểm tra trạng thái Guild của Bot
    $guildRes = executeBotAction($baseUrl . "/api_guild_pro.php?action=get_guild_pro", null, $cFile);
    
    if (isset($guildRes['success']) && $guildRes['success']) {
        // --- BOT ĐÃ TRONG GUILD ---
        $guild = $guildRes['guild'] ?? [];
        $member = $guildRes['member'] ?? [];

        // 1.1 Mua sắm đồ trong Guild Shop (nếu có điểm cống hiến)
        $shopItems = $guildRes['shop_items'] ?? [];
        if (!empty($shopItems) && isset($member['contribution_points']) && $member['contribution_points'] > 500) {
            $affordableItems = array_filter($shopItems, function($item) use ($member) {
                return $item['price_contribution'] <= $member['contribution_points'] && $item['stock'] > 0;
            });

            if (!empty($affordableItems) && rand(1, 100) <= 20) { // 20% tỷ lệ mua
                $itemToBuy = $affordableItems[array_rand($affordableItems)];
                $buyRes = executeBotAction($baseUrl . "/api_guild_pro.php", [
                    'action' => 'buy_shop_item',
                    'item_id' => $itemToBuy['id']
                ], $cFile);

                if (isset($buyRes['success']) && $buyRes['success']) {
                    $actions[] = "Vừa dùng điểm cống hiến đổi lấy <b>" . $itemToBuy['item_name'] . "</b> trong Shop Bang Hội!";
                }
            }
        }

        // 1.2 Quyên góp cho bang (Donate)
        if (rand(1, 100) <= 25 && $botMoney > 100000) { // 25% tỷ lệ donate nếu có trên 100k
            $donateAmount = rand(10000, 50000);
            $donateRes = executeBotAction($baseUrl . "/api_guild_pro.php", [
                'action' => 'donate',
                'amount' => $donateAmount
            ], $cFile);

            if (isset($donateRes['success']) && $donateRes['success']) {
                $actions[] = "Vừa quyên góp <b>" . number_format($donateAmount) . " GTLM</b> để xây dựng bang hội!";
            }
        }

        // 1.3 Chat trong kênh Bang Hội
        if (rand(1, 100) <= 30) { // 30% tỷ lệ chat guild
            $chatMsgs = [
                "Anh em bang mình húp đậm chưa? 🔥",
                "Bang chủ phát lộc đi nào!",
                "Có ai đi đánh Boss không cho ké với?",
                "Bang ta là số 1! Mãi đỉnh!",
                "Dạo này đen quá anh em ạ, bay màu suốt...",
                "Điểm danh bang hội nhé anh em! 🚀"
            ];
            $msg = $chatMsgs[array_rand($chatMsgs)];

            executeBotAction($baseUrl . "/api_guild_chat.php", [
                'action' => 'send',
                'message' => $msg
            ], $cFile);
        }

    } else {
        // --- BOT CHƯA VÀO GUILD ---
        // Cho Bot tìm và xin vào một Guild
        if (rand(1, 100) <= 15) { // 15% tỷ lệ xin vào bang
            $searchRes = executeBotAction($baseUrl . "/api_guilds.php", ['action' => 'search', 'query' => ''], $cFile);
            
            if (isset($searchRes['success']) && $searchRes['success']) {
                $guilds = $searchRes['guilds'] ?? [];
                
                // Lọc bang chưa đầy
                $availableGuilds = array_filter($guilds, function($g) {
                    return $g['member_count'] < $g['max_members'];
                });

                if (!empty($availableGuilds)) {
                    $targetGuild = $availableGuilds[array_rand($availableGuilds)];
                    
                    $applyRes = executeBotAction($baseUrl . "/api_guilds.php", [
                        'action' => 'apply',
                        'guild_id' => $targetGuild['id']
                    ], $cFile);

                    if (isset($applyRes['success']) && $applyRes['success']) {
                        $actions[] = "Tự động gửi đơn xin gia nhập bang hội <b>[" . $targetGuild['name'] . "]</b>!";
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
