<?php
/**
 * Plinko V2 Bot Logic
 * Quản lý bot tự động chơi Plinko V2.
 */

function handlePlinkoBot($baseUrl, $cFile, $botMoney, $botPersonality, &$state) {
    $actions = [];

    // Chỉ chơi nếu có đủ GTLM cược tối thiểu
    if ($botMoney < 20000) {
        return null;
    }

    // Thiết lập thông số dựa trên tính cách
    $rowsOptions = [8, 12, 16];
    $rows = $rowsOptions[array_rand($rowsOptions)];
    
    $ballCount = rand(5, 50); // Thả nhiều bóng cho ngầu
    
    $risk = 'medium';
    if ($botPersonality === 'coward' || $botPersonality === 'reporter') {
        $risk = 'low';
        $ballCount = rand(1, 10); // Nhát gan thì thả ít
    } else if ($botPersonality === 'whale' || $botPersonality === 'hambo') {
        $risk = 'high';
        $rows = 16; // Liều lĩnh thì chơi lớn nhất
    } else {
        $riskOptions = ['low', 'medium', 'high'];
        $risk = $riskOptions[array_rand($riskOptions)];
    }

    // Khởi tạo Base Bet
    $baseBet = rand(1000, min(100000, floor($botMoney * 0.02))); // Max 2% tài sản làm base bet
    $betAmount = $baseBet;

    // MARTINGALE STRATEGY (Gấp thếp nếu thua)
    if (isset($state['plinko_loss_streak']) && $state['plinko_loss_streak'] > 0) {
        // Gấp thếp x2 cho mỗi lần thua liên tiếp
        $multiplier = pow(2, min($state['plinko_loss_streak'], 5)); // Tối đa x32 (5 lần thua)
        $betAmount = $baseBet * $multiplier;
        
        // Cản bot cược quá 20% tài sản trong 1 ván gấp thếp để tránh cháy túi
        if ($betAmount > ($botMoney * 0.2)) {
            $betAmount = floor($botMoney * 0.2);
        }
    }

    $postData = [
        'bet' => $betAmount,
        'ballCount' => $ballCount,
        'rows' => $rows,
        'risk' => $risk
    ];

    $plinkoRes = executeBotAction($baseUrl . "/api_plinko_v2.php?action=drop", $postData, $cFile);

    if (isset($plinkoRes['success']) && $plinkoRes['success']) {
        $totalWin = $plinkoRes['totalWin'] ?? 0;
        $profit = $totalWin - $betAmount;

        $riskText = $risk == 'high' ? 'Cao' : ($risk == 'medium' ? 'TB' : 'Thấp');

        if ($profit > 0) {
            $state['plinko_loss_streak'] = 0; // Đã húp, reset gấp thếp
            $actions[] = "Thả <b>$ballCount bóng</b> (Risk: $riskText) ở Plinko và TRÚNG ĐẬM <b>" . number_format($totalWin) . " GTLM</b>!";
        } else {
            $state['plinko_loss_streak'] = ($state['plinko_loss_streak'] ?? 0) + 1; // Thua, tăng chuỗi loss
            $actions[] = "Chơi Plinko (Risk: $riskText) bay sạch <b>" . number_format($betAmount) . " GTLM</b> vì xui xẻo... " . ($state['plinko_loss_streak'] >= 2 ? " (Ván sau gấp thếp x" . pow(2, $state['plinko_loss_streak']) . ")" : "");
        }
        return ['status' => 'success', 'actions' => $actions];
    }

    return null;
}
?>
