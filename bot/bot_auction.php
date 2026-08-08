<?php
/**
 * Bot Auction Logic
 * Cho phép bot tham gia đấu giá trên thị trường ảo (Auction)
 */

function handleAuctionBot($baseUrl, $cFile, $botMoney) {
    $actions = [];

    // Chỉ hoạt động nếu bot khá giả (> 500k GTLM)
    if ($botMoney > 500000 && rand(1, 100) <= 15) { // 15% cơ hội check sàn đấu giá
        $auctionRes = executeBotAction($baseUrl . "/api_auction.php?action=get_list", null, $cFile);
        
        if (isset($auctionRes['success']) && $auctionRes['success']) {
            $list = $auctionRes['list'] ?? [];
            if (!empty($list)) {
                // Lọc những món bot đủ GTLM mua (current_price < 20% tài sản bot)
                $affordableItems = array_filter($list, function($item) use ($botMoney) {
                    return $item['current_price'] < ($botMoney * 0.2);
                });

                if (!empty($affordableItems)) {
                    $targetItem = $affordableItems[array_rand($affordableItems)];
                    
                    // Quyết định số GTLM bid thêm (ít nhất min_increment, hoặc có thể đập thẳng buyout)
                    $minBid = $targetItem['current_price'] + max(1000, $targetItem['current_price'] * 0.05); // Thêm 5%
                    $bidAmount = floor($minBid);
                    
                    // Khả năng mua đứt (buyout) nếu giàu và thích
                    if (!empty($targetItem['buyout_price']) && $botMoney > ($targetItem['buyout_price'] * 2) && rand(1, 100) <= 5) {
                        $bidAmount = $targetItem['buyout_price'];
                    }

                    $bidRes = executeBotAction($baseUrl . "/api_auction.php", [
                        'action' => 'bid',
                        'auction_id' => $targetItem['id'],
                        'amount' => $bidAmount
                    ], $cFile, true);

                    if (isset($bidRes['success']) && $bidRes['success']) {
                        if (isset($bidRes['buyout']) && $bidRes['buyout']) {
                            $actions[] = "Vừa MUA ĐỨT <b>[" . $targetItem['item_name'] . "]</b> trên sàn đấu giá với giá <b>" . number_format($bidAmount) . " GTLM</b>!";
                        } else {
                            $actions[] = "Vừa đặt giá <b>" . number_format($bidAmount) . " GTLM</b> cho vật phẩm <b>[" . $targetItem['item_name'] . "]</b> trên sàn đấu giá!";
                        }
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
