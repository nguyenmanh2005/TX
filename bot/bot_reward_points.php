<?php
/**
 * Reward Points Bot Logic
 * Quản lý bot tự động kiểm tra và đổi điểm thưởng (Reward Points).
 */

function handleRewardPointsBot($baseUrl, $cFile) {
    $actions = [];

    // 1. Lấy thông tin điểm thưởng
    $infoRes = executeBotAction($baseUrl . "/api_reward_points.php?action=get_info", null, $cFile);
    
    if (isset($infoRes['status']) && $infoRes['status'] === 'success') {
        $availablePoints = $infoRes['points']['available_points'] ?? 0;
        $rewards = $infoRes['rewards'] ?? [];

        if ($availablePoints > 0 && !empty($rewards)) {
            // Sắp xếp rewards theo giá điểm giảm dần để mua món đắt nhất có thể
            usort($rewards, function($a, $b) {
                return $b['cost_points'] <=> $a['cost_points'];
            });

            foreach ($rewards as $reward) {
                if ($availablePoints >= $reward['cost_points']) {
                    // Mua món này
                    $redeemRes = executeBotAction($baseUrl . "/api_reward_points.php", [
                        'action' => 'redeem',
                        'reward_id' => $reward['id']
                    ], $cFile);

                    if (isset($redeemRes['status']) && $redeemRes['status'] === 'success') {
                        $actions[] = "Vừa dùng <b>" . number_format($reward['cost_points']) . " Điểm Thưởng</b> để đổi lấy phần quà <b>" . $reward['name'] . "</b>!";
                        return ['status' => 'success', 'actions' => $actions];
                    }
                }
            }
        }
    }

    return null;
}
?>
