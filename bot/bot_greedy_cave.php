<?php
/**
 * Greedy Cave Bot Logic
 * Quản lý bot tự động thám hiểm Hang Động Tham Lam.
 */

function handleGreedyCaveBot($baseUrl, $cFile, $botMoney, $botPersonality, $botName) {
    $actions = [];

    // 1. Kiểm tra trạng thái hiện tại
    $statusRes = executeBotAction($baseUrl . "/api_greedy_cave.php", ['action' => 'status'], $cFile);
    if (!isset($statusRes['success']) || !$statusRes['success']) {
        return null;
    }

    $isPlay = $statusRes['has_session'] && ($statusRes['session']['status'] ?? '') === 'playing';

    if (!$isPlay) {
        // CHƯA CHƠI: 10% cơ hội bắt đầu thám hiểm (nếu bot có đủ GTLM)
        if (rand(1, 100) <= 10 && $botMoney > 10000) {
            $betAmount = rand(1000, min(500000, floor($botMoney * 0.1)));
            $startRes = executeBotAction($baseUrl . "/api_greedy_cave.php", [
                'action' => 'start',
                'bet' => $betAmount
            ], $cFile);

            if (isset($startRes['success']) && $startRes['success']) {
                $actions[] = "Cầm <b>" . number_format($betAmount) . " GTLM</b> hiên ngang tiến vào Hang Động Tham Lam!";
                return ['status' => 'started', 'actions' => $actions];
            }
        }
        return null;
    }

    // ĐANG CHƠI: Quyết định đi tiếp hay rút
    $currentStep = (int)($statusRes['session']['current_step'] ?? 0);
    $currentPrize = (float)($statusRes['session']['accumulated_prize'] ?? 0);

    // Tính toán xác suất đi tiếp dựa trên tính cách và số bước
    $continueChance = 50; // Mặc định 50%

    if ($botPersonality === 'whale' || $botPersonality === 'hambo') {
        // Tính cách liều lĩnh / tham lam
        $continueChance = 80 - ($currentStep * 5); 
    } else if ($botPersonality === 'coward' || $botPersonality === 'reporter' || $currentPrize > 2000000) {
        // Tính cách nhát gan, an toàn hoặc khi phần thưởng đã quá lớn
        $continueChance = 30 - ($currentStep * 5);
    }

    // Luôn đảm bảo có tỷ lệ tối thiểu / tối đa
    $continueChance = max(5, min(95, $continueChance));

    if (rand(1, 100) <= $continueChance) {
        // ĐI TIẾP
        $stepRes = executeBotAction($baseUrl . "/api_greedy_cave.php", ['action' => 'step'], $cFile);
        
        if (isset($stepRes['survived'])) {
            if ($stepRes['survived']) {
                $actions[] = "Sống sót ở Bước " . $stepRes['step'] . "! Giải thưởng tăng lên <b>" . number_format($stepRes['prize']) . " GTLM</b>.";
            } else {
                $actions[] = "SẬP HẦM ở Bước " . $stepRes['step'] . "! Mất trắng toàn bộ số GTLM cược.";
            }
            return ['status' => 'step', 'actions' => $actions];
        }
    } else {
        // RÚT GTLM (Chỉ rút khi đã đi ít nhất 1 bước)
        if ($currentStep > 0) {
            $cashoutRes = executeBotAction($baseUrl . "/api_greedy_cave.php", ['action' => 'cashout'], $cFile);
            if (isset($cashoutRes['success']) && $cashoutRes['success']) {
                $actions[] = "Quay xe an toàn ở Bước $currentStep! Ôm gọn <b>" . number_format($cashoutRes['prize']) . " GTLM</b> mang về.";
                return ['status' => 'cashout', 'actions' => $actions];
            }
        } else {
            // Nếu chưa đi bước nào mà đã nhát gan, ép đi 1 bước
            $stepRes = executeBotAction($baseUrl . "/api_greedy_cave.php", ['action' => 'step'], $cFile);
            if (isset($stepRes['survived'])) {
                if ($stepRes['survived']) {
                    $actions[] = "Run rẩy nhích 1 bước (Bước " . $stepRes['step'] . ") và còn sống. Giải thưởng: " . number_format($stepRes['prize']) . " GTLM.";
                } else {
                    $actions[] = "Xui xẻo! Mới bước 1 đã SẬP HẦM!";
                }
                return ['status' => 'step', 'actions' => $actions];
            }
        }
    }

    return null;
}
?>
