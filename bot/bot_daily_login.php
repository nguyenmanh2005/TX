<?php
/**
 * Bot Daily Login Logic
 * Giả lập hành vi điểm danh hàng ngày của người chơi
 */

function handleDailyLoginBot($baseUrl, $cFile) {
    $actions = [];

    if (rand(1, 100) <= 10) { // 10% cơ hội điểm danh hàng ngày mỗi chu kỳ
        // 1. Điểm danh
        $checkRes = executeBotAction($baseUrl . "/api_daily_login.php", ['action' => 'check_login'], $cFile, true);
        
        // 2. Lấy trạng thái
        $statusRes = executeBotAction($baseUrl . "/api_daily_login.php", ['action' => 'get_status'], $cFile, true);

        if (isset($statusRes['success']) && $statusRes['success']) {
            if ($statusRes['can_claim']) {
                // 3. Nhận phần thưởng
                $claimRes = executeBotAction($baseUrl . "/api_daily_login.php", ['action' => 'claim_reward'], $cFile, true);
                if (isset($claimRes['success']) && $claimRes['success']) {
                    $actions[] = "Đã vào điểm danh báo danh (Ngày " . $statusRes['reward_day'] . ") và húp trọn quà điểm danh!";
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
