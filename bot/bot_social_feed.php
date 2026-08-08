<?php
/**
 * Bot Social Feed Logic
 * Giả lập hành vi bot lên mạng xã hội của game đăng status chém gió
 */

function handleSocialFeedBot($baseUrl, $cFile, $botMoney) {
    $actions = [];

    // Tỉ lệ thấp (5%) để bot đăng status
    if (rand(1, 100) <= 5) {
        $statuses = [
            'Hôm nay trời đẹp quá, vào húp vài ván GTLM nào anh em!',
            'Đang chuỗi ăn ngập mặt liên tục, ai cản tôi lại đi!',
            'Đại gia là đây chứ đâu, anh em nghèo thì tránh ra.',
            'Vừa cày được mớ GTLM, tối nay lên bar quẩy!',
            'Chơi game điều độ, sức khỏe là trên hết nha anh em.',
            'Cay quá, mới vào giao lưu đã bay màu hết nửa tài sản...',
            'Ai có GTLM cho tôi vay ít gỡ vốn với!',
            'Cày cuốc mệt quá, đi ngủ thôi.'
        ];

        // Lọc nội dung tùy theo túi GTLM
        if ($botMoney > 500000) {
            $statuses = [
                'GTLM nhiều để làm gì? Để mang đi phát cho anh em nè!',
                'Tôi không cần GTLM, tôi cần đối thủ xứng tầm!',
                'Nick ngập GTLM rồi, chán chả buồn chơi.'
            ];
        } else if ($botMoney < 5000) {
            $statuses = [
                'Trắng tay rồi anh em ơi, xin ít lộc lá gỡ vốn nào!',
                'Ai cứu bé với, nick cháy khét lẹt rồi...',
                'Mới bay màu chuỗi 10, trầm cảm quá!'
            ];
        }

        $content = $statuses[array_rand($statuses)];

        $postRes = executeBotAction($baseUrl . "/api_social_feed.php", [
            'action' => 'post',
            'content' => $content
        ], $cFile, true);
        
        if (isset($postRes['status']) && $postRes['status'] === 'success') {
            $actions[] = "Vừa lên Social Feed chém gió: <i>\"$content\"</i>";
        }
    }

    if (!empty($actions)) {
        return ['status' => 'success', 'actions' => $actions];
    }
    
    return null;
}
?>
