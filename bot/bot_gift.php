<?php
/**
 * Bot Gift Logic
 * Giả lập hành vi bot đi tặng quà GTLM dạo cho người chơi khác
 */

function handleGiftBot($baseUrl, $cFile, $botMoney) {
    $actions = [];

    // Chỉ tặng quà nếu bot giàu (hơn 50k) và thỉnh thoảng (5%)
    if ($botMoney > 50000 && rand(1, 100) <= 5) {
        
        // 1. Lấy danh sách người chơi (Search random)
        $searchKeywords = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Anh', 'Minh', 'Tuấn', 'Hải'];
        $keyword = $searchKeywords[array_rand($searchKeywords)];

        $searchRes = executeBotAction($baseUrl . "/api_gift.php?action=get_users&search=" . urlencode($keyword), null, $cFile);
        
        if (isset($searchRes['success']) && $searchRes['success']) {
            $users = $searchRes['users'] ?? [];
            if (!empty($users)) {
                // Chọn ngẫu nhiên 1 người may mắn
                $luckyUser = $users[array_rand($users)];
                
                // Tặng từ 1k đến 5k GTLM
                $giftAmount = rand(1, 5) * 1000;
                $messages = ['Cho bạn nè, lấy thảo nha!', 'Làm giàu không khó!', 'Đại gia bot ban phát!', 'Bot thấy bạn nghèo quá, cho ít này!', 'Hello bạn hiền!'];
                $msg = $messages[array_rand($messages)];

                // Gửi quà
                $giftRes = executeBotAction($baseUrl . "/api_gift.php", [
                    'to_user_id' => $luckyUser['id'],
                    'amount' => $giftAmount,
                    'message' => $msg,
                    'gift_wrap' => 'standard',
                    'is_anonymous' => rand(0, 1) // 50% cơ hội tặng ẩn danh
                ], $cFile, true);

                if (isset($giftRes['success']) && $giftRes['success']) {
                    $actions[] = "Đại gia hào phóng! Vừa tặng " . number_format($giftAmount) . " GTLM cho <b>" . $luckyUser['name'] . "</b> với lời nhắn: <i>$msg</i>";
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
