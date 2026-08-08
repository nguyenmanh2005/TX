<?php
/**
 * Red Packet Bot Logic
 * Handles rich bots randomly dropping red packets in the global chat.
 */

function handleRedPacketBot($baseUrl, $cFile, $botMoney, $botName) {
    if ($botMoney < 2000000) return null; // Chỉ "Đại gia" (trên 2 triệu GTLM) mới phát lì xì

    $actions = [];

    // Số GTLM lì xì: Từ 50,000 đến 200,000 GTLM
    $amount = rand(50000, 200000);
    // Số bao: Từ 5 đến 10 bao
    $pieces = rand(5, 10);

    // Những câu nói ngông nghênh của đại gia
    $messages = [
        "GTLM nhiều để làm gì? Nhặt đi các chú!",
        "Hôm nay ăn ngập mặt, phát lộc cho anh em đây!",
        "$botName đang rất vui, nhanh tay thì còn chậm tay thì mất nhé!",
        "Ai chê GTLM thì đứng sang một bên!",
        "Chút lòng thành của đại gia, anh em xài tạm!",
        "Nhặt được nhớ cảm ơn tôi một tiếng nhé!",
        "GTLM rớt kìa, không ai lụm à?",
        "Mưa GTLM đến đây! Nhào vô anh em ơi!"
    ];
    $message = $messages[array_rand($messages)];

    $createRes = executeBotAction($baseUrl . "/api_red_packet.php", [
        'action' => 'create',
        'amount' => $amount,
        'pieces' => $pieces,
        'message' => $message
    ], $cFile);

    if (isset($createRes['success']) && $createRes['success']) {
        $actions[] = "Đại gia vung tay phát <b>" . number_format($amount) . " GTLM</b> (chia làm $pieces bao) lên Kênh Chat!";
        return ['status' => 'success', 'actions' => $actions];
    }

    return null;
}
?>
