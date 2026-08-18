<?php
/**
 * Bot Quests Logic
 * Quản lý bot tự động hoàn thành nhiệm vụ và nhận thưởng hàng ngày/tuần
 */

function handleQuestsBot($baseUrl, $cFile) {
    $actions = [];

    // Kiểm tra nhiệm vụ Hàng Ngày
    if (rand(1, 100) <= 20) { // 20% cơ hội kiểm tra Quest Daily
        $questRes = executeBotAction($baseUrl . "/api_quests.php", ['action' => 'get_quests', 'type' => 'daily'], $cFile, false);
        
        if (isset($questRes['success']) && $questRes['success'] || isset($questRes['status']) && $questRes['status'] === 'success') {
            $quests = $questRes['quests'] ?? [];
            foreach ($quests as $quest) {
                // Nếu hoàn thành nhưng chưa claim
                if (isset($quest['is_completed']) && $quest['is_completed'] == 1 && isset($quest['is_claimed']) && $quest['is_claimed'] == 0) {
                    $claimRes = executeBotAction($baseUrl . "/api_quests.php", [
                        'action' => 'claim_reward',
                        'quest_id' => $quest['id'],
                        'quest_type' => 'daily'
                    ], $cFile, true);
                    
                    if ((isset($claimRes['success']) && $claimRes['success']) || (isset($claimRes['status']) && $claimRes['status'] === 'success')) {
                        $qName = $quest['name'] ?? $quest['quest_name'] ?? $quest['title'] ?? 'Nhiệm vụ hàng ngày';
                        $actions[] = "Đã hoàn thành Nhiệm vụ Hàng Ngày <b>\"" . $qName . "\"</b> và nhận thưởng!";
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
