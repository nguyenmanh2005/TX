<?php
/**
 * Secret Gift Bot Logic
 * Quản lý bot tự động tặng quà ẩn danh cho nhau.
 * Chỉ tặng cho BOT, không tặng cho người thật.
 */

function handleSecretGiftBot($baseUrl, $cFile, $botMoney, $botId, $botNameMap) {
    $actions = [];

    // Chỉ những Bot cực giàu (> 50 triệu GTLM) mới đi phát quà
    if ($botMoney < 50000000) {
        return null;
    }

    // Lấy danh sách người dùng
    $usersRes = executeBotAction($baseUrl . "/api_gift.php?action=get_users", null, $cFile);
    if (!isset($usersRes['success']) || !$usersRes['success'] || empty($usersRes['users'])) {
        return null;
    }

    // Lọc ra danh sách CHỈ gồm các Bot (và loại trừ chính nó)
    $botIds = array_column($botNameMap, 'id');
    $eligibleReceivers = array_filter($usersRes['users'], function($u) use ($botIds, $botId) {
        return in_array($u['id'], $botIds) && $u['id'] != $botId;
    });

    if (empty($eligibleReceivers)) {
        return null;
    }

    // Chọn ngẫu nhiên 1 Bot
    $targetBot = $eligibleReceivers[array_rand($eligibleReceivers)];
    
    // Tặng từ 1.000.000 đến 5.000.000 GTLM
    $giftAmount = rand(10, 50) * 100000;
    
    // Những câu nói ẩn danh
    $messages = [
        "Thấy nghèo quá cho ít GTLM xài đỡ nè!",
        "Quà từ phương xa, không cần cảm ơn.",
        "Đây là phần thưởng cho sự nỗ lực của cưng.",
        "GTLM nhiều để làm gì? Cho bớt cho nhẹ nợ.",
        "Sugar Daddy gửi quà nhé!"
    ];
    $message = $messages[array_rand($messages)];

    // Gửi quà ẩn danh
    $giftRes = executeBotAction($baseUrl . "/api_gift.php", [
        'action' => 'send_money', // Ở file api_gift.php không yêu cầu action cụ thể cho send money (mặc định nếu không phải get_users hay send_item thì nó xử lý send_money)
        'to_user_id' => $targetBot['id'],
        'amount' => $giftAmount,
        'message' => $message,
        'gift_wrap' => 'premium',
        'is_anonymous' => 1
    ], $cFile);

    if (isset($giftRes['success']) && $giftRes['success']) {
        $actions[] = "Đã bí mật gửi tặng <b>" . number_format($giftAmount) . " GTLM</b> cho Bot <b>" . $targetBot['name'] . "</b>!";
        return ['status' => 'success', 'actions' => $actions];
    }

    return null;
}
?>
