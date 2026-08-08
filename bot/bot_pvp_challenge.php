<?php
/**
 * PvP Challenge Bot Logic
 * Quản lý bot tự động nhận lời thách đấu PvP (Coinflip, Dice, RPS, Number) từ người chơi.
 */

function handlePvpChallengeBot($baseUrl, $cFile, $botMoney, $botId) {
    $actions = [];

    // Lấy danh sách các trận thách đấu đang chờ (pending)
    $pendingRes = executeBotAction($baseUrl . "/api_pvp_challenge.php?action=get_my_challenges&status=pending", null, $cFile);
    if (isset($pendingRes['success']) && $pendingRes['success'] && !empty($pendingRes['challenges'])) {
        foreach ($pendingRes['challenges'] as $challenge) {
            // Chỉ xử lý nếu Bot là người bị thách đấu (opponent)
            if ($challenge['opponent_id'] == $botId) {
                // Kiểm tra xem bot có đủ GTLM không
                if ($botMoney < $challenge['bet_amount']) {
                    // Huỷ (decline) nếu không có endpoint decline, có thể gọi cancel_challenge (nhưng cancel chỉ dành cho challenger)
                    // Vì API chưa hỗ trợ opponent decline, bot cứ để im cho hết hạn.
                    continue;
                }

                if ($challenge['game_type'] == 'caro') {
                    // Bot không biết chơi caro, bỏ qua
                    continue;
                }

                // Chấp nhận trận đấu
                $acceptRes = executeBotAction($baseUrl . "/api_pvp_challenge.php", [
                    'action' => 'accept_challenge',
                    'challenge_id' => $challenge['id']
                ], $cFile);

                if (isset($acceptRes['success']) && $acceptRes['success']) {
                    $actions[] = "Đã dũng cảm chấp nhận lời thách đấu PvP <b>" . $challenge['game_type'] . "</b> (" . number_format($challenge['bet_amount']) . " GTLM) từ <b>" . $challenge['challenger_name'] . "</b>!";
                }
            }
        }
    }

    // Lấy danh sách các trận đã được chấp nhận (accepted) để Bot nộp kết quả (submit_choice)
    $acceptedRes = executeBotAction($baseUrl . "/api_pvp_challenge.php?action=get_my_challenges&status=accepted", null, $cFile);
    if (isset($acceptedRes['success']) && $acceptedRes['success'] && !empty($acceptedRes['challenges'])) {
        foreach ($acceptedRes['challenges'] as $challenge) {
            
            // Xác định xem Bot là người nộp choice nào
            $isChallenger = ($challenge['challenger_id'] == $botId);
            $botChoiceField = $isChallenger ? 'challenger_choice' : 'opponent_choice';

            // Nếu Bot chưa nộp choice
            if (empty($challenge[$botChoiceField])) {
                $choice = "";
                switch ($challenge['game_type']) {
                    case 'coinflip':
                        $choice = rand(0, 1) ? 'heads' : 'tails';
                        break;
                    case 'dice':
                        $choice = rand(1, 6); // Đổ xúc xắc ngẫu nhiên 1-6
                        break;
                    case 'rps':
                        $rpsOptions = ['rock', 'paper', 'scissors'];
                        $choice = $rpsOptions[array_rand($rpsOptions)];
                        break;
                    case 'number':
                        $choice = rand(1, 100);
                        break;
                    case 'caro':
                        // Caro dùng make_move, tạm bỏ qua
                        continue 2; 
                }

                if (!empty($choice)) {
                    $submitRes = executeBotAction($baseUrl . "/api_pvp_challenge.php", [
                        'action' => 'submit_choice',
                        'challenge_id' => $challenge['id'],
                        'choice' => $choice
                    ], $cFile);

                    if (isset($submitRes['success']) && $submitRes['success']) {
                        // Nếu có kết quả luôn thì in ra Log
                        if (isset($submitRes['result']) && !empty($submitRes['result'])) {
                            if ($submitRes['winner_id'] == $botId) {
                                $actions[] = "Đã ăn ngập mặt áp đảo trong trận PvP " . $challenge['game_type'] . " và húp gọn <b>" . number_format($challenge['bet_amount']) . " GTLM</b>!";
                            } else if ($submitRes['winner_id']) {
                                $actions[] = "Vừa bay màu thảm hại trong trận PvP " . $challenge['game_type'] . ", nick trắng tay <b>" . number_format($challenge['bet_amount']) . " GTLM</b>...";
                            } else {
                                $actions[] = "Hoà nhau trong trận PvP " . $challenge['game_type'] . "!";
                            }
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
