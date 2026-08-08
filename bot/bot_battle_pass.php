<?php
/**
 * Bot Battle Pass Logic
 * Quản lý bot mua Battle Pass và nhận thưởng theo cấp
 */

function handleBattlePassBot($baseUrl, $cFile, $botMoney) {
    $actions = [];

    if (rand(1, 100) <= 20) { // 20% cơ hội check Battle Pass
        $bpRes = executeBotAction($baseUrl . "/api_battle_pass.php?action=get_status", null, $cFile);
        
        if (isset($bpRes['success']) && $bpRes['success']) {
            $level = (int)($bpRes['level'] ?? 1);
            $hasPremium = $bpRes['has_premium'] ?? false;
            $claimedFree = $bpRes['claimed'] ?? [];
            $claimedPremium = $bpRes['premium_claimed'] ?? [];
            $rewards = $bpRes['rewards'] ?? [];

            // 1. Nếu cấp cao mà chưa có Premium, mua Premium Pass
            if ($level >= 5 && !$hasPremium && $botMoney > 500000 && rand(1, 100) <= 10) {
                $buyRes = executeBotAction($baseUrl . "/api_battle_pass.php", [
                    'action' => 'buy_premium'
                ], $cFile, true);
                
                if (isset($buyRes['success']) && $buyRes['success']) {
                    $actions[] = "Vừa vung GTLM mở khóa <b>Premium Battle Pass</b> để bú quà VIP!";
                    $hasPremium = true; // Cập nhật state nội bộ
                }
            }

            // 2. Nhận thưởng Free Track
            for ($i = 1; $i <= $level; $i++) {
                if (!in_array($i, $claimedFree)) {
                    $claimRes = executeBotAction($baseUrl . "/api_battle_pass.php", [
                        'action' => 'claim_reward',
                        'level' => $i,
                        'track' => 'free'
                    ], $cFile, true);
                    // Giới hạn chỉ nhận 1 phần thưởng mỗi chu kỳ để tránh log rác
                    if (isset($claimRes['success']) && $claimRes['success']) {
                        $actions[] = "Đã nhận phần thưởng Battle Pass (Miễn Phí) Cấp $i!";
                        break; 
                    }
                }
            }

            // 3. Nhận thưởng Premium Track
            if ($hasPremium) {
                for ($i = 1; $i <= $level; $i++) {
                    if (!in_array($i, $claimedPremium)) {
                        $claimRes = executeBotAction($baseUrl . "/api_battle_pass.php", [
                            'action' => 'claim_reward',
                            'level' => $i,
                            'track' => 'premium'
                        ], $cFile, true);
                        if (isset($claimRes['success']) && $claimRes['success']) {
                            $actions[] = "Đã húp phần thưởng Battle Pass (Premium) Cấp $i!";
                            break; 
                        }
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
