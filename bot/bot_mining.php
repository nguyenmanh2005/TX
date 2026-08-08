<?php
/**
 * Bot Mining Logic
 * Quản lý bot tự động thu hoạch GTLM và vật phẩm từ hầm mỏ
 */

function handleMiningBot($baseUrl, $cFile) {
    $actions = [];

    // Lấy thông tin hầm mỏ của Bot
    $infoRes = executeBotAction($baseUrl . "/api_mining.php", ['action' => 'info'], $cFile, false);
    
    // API mining trả JSON nên $infoRes sẽ là mảng đã parse
    if (isset($infoRes['success']) && $infoRes['success']) {
        $totalAccumulated = $infoRes['total_accumulated'] ?? 0;
        
        // Thử thu hoạch nếu có số dư tích luỹ khá (ví dụ > 5,000 GTLM)
        if ($totalAccumulated > 5000 && rand(1, 100) <= 50) { // 50% tỉ lệ thu hoạch nếu đủ GTLM
            $claimRes = executeBotAction($baseUrl . "/api_mining.php", ['action' => 'claim_all'], $cFile, false);
            
            if (isset($claimRes['success']) && $claimRes['success']) {
                $actions[] = "Tự động thu hoạch hầm mỏ và nhận được <b>" . number_format($claimRes['claimed'] ?? $totalAccumulated) . " GTLM</b>!";
            }
        }
        
        // Mua slot thợ mỏ mới nếu chưa đủ slot (Logic cơ bản)
        $slots = $infoRes['slots'] ?? [];
        $emptySlots = array_filter($slots, function($s) { return isset($s['empty']) && $s['empty'] === true; });
        
        if (!empty($emptySlots) && rand(1, 100) <= 5) { // Tỉ lệ mua cực thấp để tránh cạn GTLM bot
            $firstEmptySlot = array_key_first($emptySlots);
            // Nâng cấp tốn GTLM, nên ta cần một API POST upgrade
            $upgradeRes = executeBotAction($baseUrl . "/api_mining.php", [
                'action' => 'upgrade',
                'slot' => $firstEmptySlot,
                'levels_to_add' => 1
            ], $cFile, true); // True cho HTTP POST
            
            if (isset($upgradeRes['success']) && $upgradeRes['success']) {
                $actions[] = "Vừa thuê thêm Thợ Mỏ cấp 1 vào Hầm Mỏ để tăng gia sản xuất!";
            }
        }
    }

    if (!empty($actions)) {
        return ['status' => 'success', 'actions' => $actions];
    }
    
    return null;
}
?>
