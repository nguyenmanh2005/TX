<?php
/**
 * Bot Combo Bet Logic
 * Giả lập hành vi bot chơi cá cược kết hợp (Triple Sweep Combo Bet)
 */

function handleComboBetBot($baseUrl, $cFile, $botMoney) {
    $actions = [];

    // Chỉ chơi nếu có trên 100k GTLM và tỉ lệ chơi là 15%
    if ($botMoney > 100000 && rand(1, 100) <= 15) {
        
        $betAmount = rand(10, 50) * 1000; // Cược từ 10k đến 50k
        
        $crashTargets = [1.5, 2.0, 3.0, 5.0];
        $sicboChoices = ['ac_quy', 'thien_than', 'le', 'chan'];
        $baccaratChoices = ['player', 'banker', 'tie'];

        $ct = $crashTargets[array_rand($crashTargets)];
        $sc = $sicboChoices[array_rand($sicboChoices)];
        $bc = $baccaratChoices[array_rand($baccaratChoices)];

        $comboRes = executeBotAction($baseUrl . "/api_combo_bet.php", [
            'bet_amount' => $betAmount,
            'crash_target' => $ct,
            'sicbo_choice' => $sc,
            'baccarat_choice' => $bc
        ], $cFile, true);
        
        if (isset($comboRes['success']) && $comboRes['success']) {
            if ($comboRes['all_won']) {
                $payout = $comboRes['payout_formatted'];
                $actions[] = "Tất tay Combo Bet cực cháy: Crash (x$ct), Sicbo ($sc), Baccarat ($bc) và <b>ăn ngập mặt $payout GTLM</b>!";
            } else {
                $actions[] = "Chiến Combo Bet " . number_format($betAmount) . " GTLM nhưng thành tro rồi (Crash x$ct, Sicbo $sc, Baccarat $bc)!";
            }
        }
    }

    if (!empty($actions)) {
        return ['status' => 'success', 'actions' => $actions];
    }
    
    return null;
}
?>
