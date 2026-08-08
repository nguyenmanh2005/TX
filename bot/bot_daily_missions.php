<?php
/**
 * Bot Daily Missions Logic
 * Giả lập hành vi tự động nhận thưởng nhiệm vụ hàng ngày khi hoàn thành
 */

function handleDailyMissionsBot($baseUrl, $cFile) {
    $actions = [];

    if (rand(1, 100) <= 20) { // 20% cơ hội check nhiệm vụ
        $missionsRes = executeBotAction($baseUrl . "/api_daily_missions.php", ['action' => 'get_missions'], $cFile, true);
        
        if (isset($missionsRes['success']) && $missionsRes['success']) {
            $missions = $missionsRes['missions'] ?? [];
            
            foreach ($missions as $mission) {
                // Nhận thưởng nếu đã hoàn thành nhưng chưa nhận
                if ($mission['is_completed'] && !$mission['is_claimed']) {
                    $claimRes = executeBotAction($baseUrl . "/api_daily_missions.php", [
                        'action' => 'claim_reward',
                        'mission_id' => $mission['id']
                    ], $cFile, true);
                    
                    if (isset($claimRes['success']) && $claimRes['success']) {
                        $actions[] = "Hoàn thành nhiệm vụ <b>[" . $mission['title'] . "]</b> và nhận ngay " . number_format($mission['reward_value']) . " GTLM!";
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
