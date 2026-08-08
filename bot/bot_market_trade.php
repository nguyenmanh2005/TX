<?php
/**
 * Market Bot Trade Logic
 * Handles automatic buying and selling of commodities/stocks.
 */

function handleMarketBot($conn, $baseUrl, $cFile, $botMoney) {
    if ($botMoney < 500000) return null; // Need some capital to trade

    // Get current market info and bot's inventory
    $infoRes = executeBotAction($baseUrl . "/api_market.php?action=info", null, $cFile);
    
    if (!isset($infoRes['success']) || !$infoRes['success']) {
        return null; // Failed to get market info
    }

    $market = $infoRes['market'] ?? [];
    $inventory = $infoRes['inventory'] ?? [];

    if (empty($market)) return null;

    $actions = [];

    foreach ($market as $item) {
        $code = $item['commodity_code'];
        $dbCol = strtolower($code);
        if (in_array($code, ['WHEAT', 'CORN', 'TOMATO', 'APPLE', 'WATERMELON', 'STRAWBERRY', 'GRAPE', 'PEACH', 'CHERRY', 'LEMON', 'BANANA', 'KIWI', 'MANGO', 'PINEAPPLE', 'COCONUT', 'MELON', 'ORANGE', 'AVOCADO', 'PEAR', 'POMEGRANATE'])) {
            $dbCol = 'crop_' . $dbCol;
        }

        $invAmount = isset($inventory[$dbCol]) ? (int)$inventory[$dbCol] : 0;
        $currentPrice = (float)$item['current_price'];
        $basePrice = (float)$item['base_price'];

        // Sell Logic: Price is high ( > 130% of base price)
        if ($invAmount > 0 && $currentPrice > $basePrice * 1.3) {
            // Sell all or half
            $sellAmount = rand(1, 100) > 50 ? $invAmount : max(1, floor($invAmount / 2));
            $tradeRes = executeBotAction($baseUrl . "/api_market.php", [
                'action' => 'trade',
                'type' => 'sell',
                'code' => $code,
                'amount' => $sellAmount
            ], $cFile);

            if (isset($tradeRes['success']) && $tradeRes['success']) {
                $profit = $currentPrice * $sellAmount;
                $actions[] = "Bán chốt lời $sellAmount $code (Giá: " . number_format($currentPrice) . " GTLM/đơn vị). Thu về: " . number_format($profit) . " GTLM";
            }
        }
        
        // Buy Logic: Price is low ( < 80% of base price)
        else if ($currentPrice < $basePrice * 0.8 && rand(1, 100) <= 30) {
            // Spend 5-15% of current money on this item
            $budget = $botMoney * (rand(5, 15) / 100);
            $buyAmount = floor($budget / $currentPrice);
            
            if ($buyAmount > 0) {
                $tradeRes = executeBotAction($baseUrl . "/api_market.php", [
                    'action' => 'trade',
                    'type' => 'buy',
                    'code' => $code,
                    'amount' => $buyAmount
                ], $cFile);

                if (isset($tradeRes['success']) && $tradeRes['success']) {
                    $cost = $currentPrice * $buyAmount;
                    $botMoney -= $cost; // Update local budget
                    $actions[] = "Bắt đáy $buyAmount $code (Giá: " . number_format($currentPrice) . " GTLM/đơn vị). Chi tiêu: " . number_format($cost) . " GTLM";
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
