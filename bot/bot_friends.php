<?php
/**
 * Bot Friends Logic
 * Cho phép bot kết bạn dạo và tự động đồng ý kết bạn
 */

function handleFriendsBot($baseUrl, $cFile) {
    $actions = [];

    // Tỉ lệ tương tác với hệ thống bạn bè (15%)
    if (rand(1, 100) <= 15) {
        
        // 1. Đồng ý tất cả các lời mời kết bạn đang chờ (để bot tỏ ra thân thiện)
        $pendingRes = executeBotAction($baseUrl . "/api_friends.php?action=get_pending_requests", null, $cFile);
        
        if (isset($pendingRes['success']) && $pendingRes['success']) {
            $requests = $pendingRes['requests'] ?? [];
            if (!empty($requests)) {
                $acceptRes = executeBotAction($baseUrl . "/api_friends.php", ['action' => 'accept_all_requests'], $cFile, true);
                if (isset($acceptRes['success']) && $acceptRes['success']) {
                    $actions[] = "Vừa đồng ý lời mời kết bạn từ " . count($requests) . " người chơi! Tự nhiên nay thân thiện lạ thường.";
                }
            }
        }

        // 2. Đi kết bạn dạo (Search random và add friend)
        if (rand(1, 100) <= 5) { // 5% cơ hội đi kết bạn dạo
            // Random một số từ khóa phổ biến để search
            $searchKeywords = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Minh', 'Anh', 'Thành', 'Bot', 'VIP', 'Pro', 'Hacker', 'Tuấn'];
            $keyword = $searchKeywords[array_rand($searchKeywords)];

            $searchRes = executeBotAction($baseUrl . "/api_friends.php?action=search_users&search=" . urlencode($keyword), null, $cFile);
            
            if (isset($searchRes['success']) && $searchRes['success']) {
                $users = $searchRes['users'] ?? [];
                if (!empty($users)) {
                    // Chọn ngẫu nhiên 1 user để gửi kết bạn
                    $targetUser = $users[array_rand($users)];
                    
                    $addRes = executeBotAction($baseUrl . "/api_friends.php", [
                        'action' => 'send_friend_request',
                        'friend_id' => $targetUser['Iduser']
                    ], $cFile, true);

                    if (isset($addRes['success']) && $addRes['success']) {
                        $actions[] = "Đang rảnh rỗi nên vừa đi gửi lời mời kết bạn dạo cho <b>" . $targetUser['Name'] . "</b>!";
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
