<?php
/**
 * 🃏 Blackjack Multiplayer Bot Logic Hook
 * Logic: Bot sẽ tham gia bàn, đặt cược và thực hiện Hit/Stand
 */

function handleBlackjackMultiBot(mysqli $conn, string $baseUrl, string $cookieFile, array $state) {
    // 1. Lấy trạng thái bàn hiện tại
    $res = executeBotAction($baseUrl . "/api_blackjack_multi.php?action=get_state", null, $cookieFile);
    if (!$res || !$res['success']) return;

    $table = $res['table'];
    $players = $res['players'];
    $myId = (int)$res['current_user_id']; // Cần API trả về cái này để bot biết mình là ai

    // 2. Nếu bàn đang chờ -> Đặt cược để vào bàn
    if ($table['status'] === 'waiting') {
        $isJoined = false;
        foreach ($players as $p) { if ($p['user_id'] == $myId) $isJoined = true; }
        
        if (!$isJoined && count($players) < 5 && rand(1, 100) <= 40) {
            $bet = rand(1, 10) * 10000;
            executeBotAction($baseUrl . "/api_blackjack_multi.php?action=bet", ['amount' => $bet], $cookieFile);
            echo "🃏 <span style='color:#fbbf24;'>Bot vừa ngồi vào bàn Blackjack Multiplayer.</span><br>";
        }
    }

    // 3. Nếu đang trong ván và đến lượt Bot
    if ($table['status'] === 'playing' && $table['current_turn_user_id'] == $myId) {
        $me = null;
        foreach ($players as $p) { if ($p['user_id'] == $myId) $me = $p; }
        
        if ($me) {
            $cards = json_decode($me['cards'], true);
            $score = calculateScore($cards); // Cần copy hàm này hoặc include
            
            // Chiến thuật cơ bản: < 17 thì Hit, >= 17 thì Stand
            if ($score < 17) {
                executeBotAction($baseUrl . "/api_blackjack_multi.php?action=hit", null, $cookieFile);
                echo "🃏 <span style='color:#fbbf24;'>Bot vừa HIT (Điểm hiện tại: $score).</span><br>";
            } else {
                executeBotAction($baseUrl . "/api_blackjack_multi.php?action=stand", null, $cookieFile);
                echo "🃏 <span style='color:#fbbf24;'>Bot vừa STAND (Điểm hiện tại: $score).</span><br>";
            }
        }
    }
}

function calculateScore($cards) {
    $score = 0; $aces = 0;
    foreach ($cards as $c) {
        if (in_array($c['value'], ['J','Q','K'])) $score += 10;
        elseif ($c['value'] === 'A') { $score += 11; $aces++; }
        else $score += (int)$c['value'];
    }
    while ($score > 21 && $aces > 0) { $score -= 10; $aces--; }
    return $score;
}

