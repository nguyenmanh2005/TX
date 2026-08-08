<?php
/**
 * Bot Banharc (Bắn Cá) Logic
 * Giả lập hành vi bot chơi game Bắn Cá
 */

function handleBanharcBot($baseUrl, $cFile, $botMoney) {
    $actions = [];

    // Chỉ chơi nếu có đủ GTLM cược
    if ($botMoney > 5000 && rand(1, 100) <= 25) { // 25% cơ hội chơi Bắn Cá
        
        $bulletPrices = [100, 500, 1000, 5000];
        $fishTypes = ['small', 'medium', 'large', 'shark', 'octopus', 'gold_crab', 'dragon'];
        
        // Random mức đạn tuỳ theo độ giàu
        $maxBullet = $botMoney > 100000 ? 5000 : ($botMoney > 50000 ? 1000 : 500);
        $validBullets = array_filter($bulletPrices, function($p) use ($maxBullet) { return $p <= $maxBullet; });
        if (empty($validBullets)) $validBullets = [100];
        
        $bullet = $validBullets[array_rand($validBullets)];
        $fish = $fishTypes[array_rand($fishTypes)];

        $shootRes = executeBotAction($baseUrl . "/api_banharc.php", [
            'action' => 'shoot',
            'bullet_price' => $bullet,
            'fish_type' => $fish
        ], $cFile, true);
        
        if (isset($shootRes['success']) && $shootRes['success']) {
            if ($shootRes['caught']) {
                $reward = number_format($shootRes['reward']);
                $fishName = $shootRes['fish_name'];
                $actions[] = "Vừa vác súng đạn " . number_format($bullet) . " đi Bắn Cá và <b>SĂN ĐƯỢC $fishName</b>, húp $reward GTLM!";
            } else {
                // Không log khi xịt để tránh spam vì bắn cá xịt rất nhiều
                // $actions[] = "Bắn bay màu mất rồi!"; 
            }
        }
    }

    if (!empty($actions)) {
        return ['status' => 'success', 'actions' => $actions];
    }
    
    return null;
}
?>
