<?php
/**
 * Lottery Bot Logic
 * Handles automatic purchasing of community lottery tickets.
 */

function handleLotteryBot($baseUrl, $cFile, $botMoney) {
    if ($botMoney < 50000) return null; // Cần ít nhất 50k GTLM để mua vé số (vé giá 10k)

    // 1. Kiểm tra trạng thái Xổ số
    $statusRes = executeBotAction($baseUrl . "/api_lottery.php?action=status", null, $cFile);
    
    if (!isset($statusRes['success']) || !$statusRes['success']) {
        return null;
    }

    $todayDraw = $statusRes['today'] ?? [];
    $userTickets = $statusRes['user_tickets'] ?? [];

    // Nếu không có đợt xổ số nào đang chờ, hoặc bot đã mua quá 3 vé hôm nay thì bỏ qua
    if (empty($todayDraw) || $todayDraw['status'] !== 'pending' || count($userTickets) >= 3) {
        return null;
    }

    $actions = [];

    // 2. Sinh 6 số ngẫu nhiên từ 01 đến 99
    $nums = [];
    while (count($nums) < 6) {
        $n = str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);
        if (!in_array($n, $nums)) {
            $nums[] = $n;
        }
    }
    sort($nums);
    $winningStr = implode(',', $nums);

    // 3. Mua vé số
    $buyRes = executeBotAction($baseUrl . "/api_lottery.php", [
        'action' => 'buy',
        'numbers' => $winningStr
    ], $cFile);

    if (isset($buyRes['success']) && $buyRes['success']) {
        $actions[] = "Đã mua một vé số Vietlott với dãy số hy vọng: <b>$winningStr</b>";
        return ['status' => 'success', 'actions' => $actions];
    }

    return null;
}
?>
