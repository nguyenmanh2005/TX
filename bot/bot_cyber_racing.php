<?php
function handleCyberRacingBot($conn, $baseUrl, $cFile, $userMoney) {
    if ($userMoney < 10000) return null;
    
    $animals = ['wolf', 'fox', 'panther', 'bear', 'eagle'];
    $betAmount = floor($userMoney * (rand(2, 5) / 100)); // Cược 2-5%
    if ($betAmount < 1000) $betAmount = 1000;
    if ($betAmount > 500000) $betAmount = 500000;
    
    $animal = $animals[array_rand($animals)];
    $bets = [['animal' => $animal, 'amount' => $betAmount]];
    
    $stateRes = executeBotAction($baseUrl . "/api_cyber_racing.php?action=get_state", null, $cFile);
    if (isset($stateRes['phase']) && $stateRes['phase'] === 'betting') {
        $res = executeBotAction($baseUrl . "/api_cyber_racing.php", ['action' => 'bet', 'bets' => json_encode($bets)], $cFile);
        if (isset($res['success']) && $res['success']) {
            if (function_exists('uiLog')) {
                uiLog('🏎️', "<b>Đua Thú Cyberpunk:</b> Đã cược <span style='color:var(--primary)'>".number_format($betAmount)."</span> vào <b>".strtoupper($animal)."</b>");
            }
        }
        return $res;
    }
    
    return null;
}
