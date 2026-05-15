<?php
/**
 * 🏇 Horse Racing PvP Bot Logic Hook
 * Logic: Bot sẽ đặt cược ngẫu nhiên vào các con ngựa khi phòng đang chờ
 */

function handleHorseRacePvPBot(mysqli $conn, string $baseUrl, string $cookieFile) {
    // 1. Lấy trạng thái phòng đua
    $res = executeBotAction($baseUrl . "/api_horserace_pvp.php?action=get_state", null, $cookieFile);
    if (!$res || !$res['success']) return;

    $room = $res['room'];
    $bets = $res['bets'];
    
    // 2. Nếu phòng đang chờ -> Đặt cược ngẫu nhiên
    if ($room['status'] === 'waiting') {
        // Kiểm tra xem bot đã cược chưa (để tránh cược quá nhiều lần)
        // Trong demo này, bot có 20% cơ hội cược mỗi chu kỳ nếu chưa cược
        if (rand(1, 100) <= 20) {
            $horseId = rand(1, 6);
            $amount = rand(1, 5) * 10000;
            executeBotAction($baseUrl . "/api_horserace_pvp.php?action=place_bet", [
                'horse_id' => $horseId,
                'amount' => $amount
            ], $cookieFile);
            echo "🏇 <span style='color:#818cf8;'>Bot vừa đặt cược vào ngựa #$horseId trong trường đua PvP.</span><br>";
        }
    }
}
