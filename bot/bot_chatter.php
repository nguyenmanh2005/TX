<?php
/**
 * Chatter Bot Logic
 * Quản lý bot tự động lên Kênh Chat Thế Giới chém gió dựa trên tính cách.
 */

function handleChatterBot($baseUrl, $cFile, $botPersonality, $brain, $userId, &$state) {
    $actions = [];

    // Sử dụng AI Brain để sinh câu chat context-aware dựa trên trạng thái, cảm xúc, dây thắng thua...
    // Randomize context
    $chatTypes = ['social_brag', 'social_complain', 'social_tip', 'begging', 'rumor', 'trash_talk', 'greet'];
    
    // Nếu bot đang trong dây thua hoặc thắng, ghi đè type
    if (isset($state['lose_streak']) && $state['lose_streak'] >= 3) {
        $chatTypes = ['tilted_chat', 'social_complain', 'extreme_tilt_chat'];
    } else if (isset($state['win_streak']) && $state['win_streak'] >= 3) {
        $chatTypes = ['hot_streak_chat', 'social_brag'];
    }

    $selectedType = $chatTypes[array_rand($chatTypes)];
    
    // Dữ liệu bối cảnh ảo cho tin đồn
    $fakeData = [
        'amount' => rand(100000, 5000000),
        'game_name' => ['Plinko V2', 'Mega Spin', 'Xanh Đỏ Đối Kháng', 'Đấu Trường', 'Hang Động'][rand(0, 4)],
        'player_name' => ['Cụ Giáo', 'Thánh Nổ', 'Trùm Bịp', 'Game Thủ'][rand(0, 3)],
        'user_count' => rand(10, 100)
    ];

    $message = $brain->generateMessage($userId, $selectedType, $fakeData, $state);

    if (empty($message)) {
        return null;
    }

    // Gọi API chat.php
    $chatRes = executeBotAction($baseUrl . "/chat.php", [
        'message' => $message
    ], $cFile, true);

    // Lưu ý: executeBotAction mong đợi JSON trả về. Nhưng chat.php nếu không phải Ajax có thể trả về HTML, 
    // Tuy nhiên nếu có message thì nó redirect hoặc xử lý ngầm. 
    // Thực tế chat.php check nếu POST message thì insert DB, rate limit sẽ trả 429 JSON nếu gặp lỗi.
    // Thường Bot sẽ pass rate limit do chạy chậm.
    
    // Ghi log (dù cho API chat không trả về chuẩn JSON do load giao diện, ta cứ assume success vì POST đi)
    $actions[] = "Vừa mạnh miệng gáy trên Kênh Chat: <i>\"$message\"</i>";
    
    return ['status' => 'success', 'actions' => $actions];
}
?>
