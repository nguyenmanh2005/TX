<?php
/**
 * Bot Coinflip Logic
 * Cho phép bot chơi Lật Xu (Sấp/Ngửa) với chiến thuật Martingale
 */

function handleCoinflipBot($baseUrl, $cFile, $botMoney, &$state) {
    $actions = [];

    // Chỉ chơi nếu có GTLM và có tỉ lệ tham gia (25%)
    if ($botMoney > 5000 && rand(1, 100) <= 25) {
        // Đọc state Martingale của riêng Coinflip
        if (!isset($state['coinflip_streak'])) $state['coinflip_streak'] = 0;
        if (!isset($state['coinflip_last_bet'])) $state['coinflip_last_bet'] = 1000;

        // Tính toán GTLM cược (Gấp thếp khi thua)
        $betAmount = $state['coinflip_last_bet'];
        
        // Nếu cược quá 10% tài sản, reset về mốc 1000 để tránh bot cháy túi
        if ($betAmount > ($botMoney * 0.1) || $betAmount > 1000000) {
            $betAmount = 1000;
            $state['coinflip_streak'] = 0;
        }

        $choice = (rand(1, 100) <= 50) ? 'sấp' : 'ngửa';

        $cfRes = executeBotAction($baseUrl . "/api_coinflip.php", [
            'betAmount' => $betAmount,
            'choice' => $choice
        ], $cFile, true);
        
        if (isset($cfRes['success']) && $cfRes['success']) {
            $isWin = $cfRes['is_win'] ?? false;
            $resultChoice = $cfRes['result_choice'] ?? 'không rõ';
            
            if ($isWin) {
                // Thắng -> Reset Martingale
                $state['coinflip_last_bet'] = 1000;
                $state['coinflip_streak'] = 0;
                
                $actions[] = "Vừa giao lưu Lật Xu: Chọn <b>$choice</b> (Ra <b>$resultChoice</b>). Húp <b>" . number_format($cfRes['win_amount'] ?? ($betAmount*2)) . " GTLM</b>!";
            } else {
                // Thua -> Gấp thếp x2 cho ván sau
                $state['coinflip_last_bet'] = $betAmount * 2;
                $state['coinflip_streak']++;
                
                $actions[] = "Vừa giao lưu Lật Xu: Chọn <b>$choice</b> (Ra <b>$resultChoice</b>). Bay màu <b>" . number_format($betAmount) . " GTLM</b> (Thua chuỗi " . $state['coinflip_streak'] . ")!";
            }
        }
    }

    if (!empty($actions)) {
        return ['status' => 'success', 'actions' => $actions];
    }
    
    return null;
}
?>
