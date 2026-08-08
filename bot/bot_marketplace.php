<?php
/**
 * Bot Marketplace Logic
 * Cho phép bot mua sắm trên Chợ Đen để tiêu thụ vật phẩm
 */

function handleMarketplaceBot($baseUrl, $cFile, $botMoney) {
    $actions = [];

    // Chỉ hoạt động nếu bot dư dả
    if ($botMoney > 100000 && rand(1, 100) <= 20) { // 20% cơ hội check chợ đen
        $marketRes = executeBotAction($baseUrl . "/api_marketplace.php?action=get_listings", null, $cFile);
        
        if (isset($marketRes['success']) && $marketRes['success']) {
            $listings = $marketRes['listings'] ?? [];
            if (!empty($listings)) {
                // Lọc đồ rác hoặc rẻ (price < 10% tài sản bot)
                $affordableItems = array_filter($listings, function($item) use ($botMoney) {
                    return $item['price'] < ($botMoney * 0.1);
                });

                if (!empty($affordableItems)) {
                    $targetItem = $affordableItems[array_rand($affordableItems)];
                    
                    // 10% cơ hội quyết định mua món rẻ đó
                    if (rand(1, 100) <= 10) {
                        $buyRes = executeBotAction($baseUrl . "/api_marketplace.php", [
                            'action' => 'buy',
                            'id' => $targetItem['id']
                        ], $cFile, true);

                        if (isset($buyRes['success']) && $buyRes['success']) {
                            $actions[] = "Vừa chốt đơn mua <b>[" . $targetItem['item_name'] . "]</b> từ <b>" . $targetItem['seller_name'] . "</b> trên Chợ Đen với giá " . number_format($targetItem['price']) . " GTLM!";
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
