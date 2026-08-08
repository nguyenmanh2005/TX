<?php
/**
 * Bot Guild War Logic
 * Giả lập hành vi bot Bang Chủ chủ động khiêu chiến hoặc nhận lời thách đấu Bang Chiến
 */

function handleGuildWarBot($baseUrl, $cFile) {
    $actions = [];

    // Tỉ lệ thấp (3%) để bot xem xét việc bang chiến
    if (rand(1, 100) <= 3) {
        // Tạm thời lấy danh sách challenge để xem có ai thách đấu không
        // Vì api_guild_war không có hàm lấy danh sách tự động, ta sẽ giả lập thao tác accept đại 1 ID nếu may mắn trúng
        // Hoặc an toàn hơn, giả lập hành vi thách đấu ngẫu nhiên
        
        $targetGuildId = rand(1, 20); // Giả sử ID bang từ 1 đến 20
        $warRes = executeBotAction($baseUrl . "/api_guild_war.php", [
            'action' => 'challenge',
            'target_guild_id' => $targetGuildId
        ], $cFile, true);
        
        if (isset($warRes['success']) && $warRes['success']) {
            $actions[] = "Bang chủ hùng hổ vác loa sang tận cửa Bang ID $targetGuildId để gửi lời Thách Đấu Bang Chiến!";
        } else if (isset($warRes['message']) && strpos($warRes['message'], 'chấp nhận') !== false) {
            // Nếu API có tự động accept hoặc logic khác
            $actions[] = "Bang chủ đã duyệt lời thách đấu, toàn quân chuẩn bị chiến!";
        }
    }

    if (!empty($actions)) {
        return ['status' => 'success', 'actions' => $actions];
    }
    
    return null;
}
?>
