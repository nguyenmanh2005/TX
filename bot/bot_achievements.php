<?php
/**
 * Bot Achievements Logic
 * Giả lập hành vi bot tự động kiểm tra và nhận thưởng thành tựu
 */

function handleAchievementsBot($baseUrl, $cFile) {
    $actions = [];

    if (rand(1, 100) <= 20) { // 20% cơ hội kiểm tra thành tựu
        $achvRes = executeBotAction($baseUrl . "/api_achievements.php", ['action' => 'check_all'], $cFile, true);
        
        if (isset($achvRes['status']) && $achvRes['status'] === 'success') {
            $unlocked = $achvRes['unlocked'] ?? 0;
            if ($unlocked > 0) {
                $actions[] = "Vừa xuất sắc mở khóa thành công <b>$unlocked Thành Tựu</b> mới và húp bộn GTLM thưởng!";
            }
        }
    }

    if (!empty($actions)) {
        return ['status' => 'success', 'actions' => $actions];
    }
    
    return null;
}
?>
