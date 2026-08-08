<?php
/**
 * Mega Spin Bot Logic
 * Quản lý bot tự động ném GTLM vào vòng quay Mega Spin.
 */

function handleMegaSpinBot($baseUrl, $cFile, $botMoney, $botPersonality) {
    $actions = [];

    // Chỉ chơi nếu có đủ GTLM cược tối thiểu
    if ($botMoney < 20000) {
        return null;
    }

    $allowedAmounts = [1000, 5000, 10000, 50000, 100000, 500000];
    $betAmount = 1000;

    if ($botPersonality === 'whale' || $botPersonality === 'hambo') {
        // Liều lĩnh thì đánh lớn
        $betAmount = $allowedAmounts[array_rand(array_slice($allowedAmounts, -3))]; // Lấy 3 mức cao nhất
    } else if ($botPersonality === 'coward' || $botPersonality === 'reporter') {
        // Nhát gan thì đánh nhỏ
        $betAmount = $allowedAmounts[array_rand(array_slice($allowedAmounts, 0, 3))]; // Lấy 3 mức thấp nhất
    } else {
        // Bình thường thì đánh ngẫu nhiên
        $betAmount = $allowedAmounts[array_rand($allowedAmounts)];
    }

    // Đảm bảo không cược quá tay
    while ($betAmount > $botMoney * 0.2 && $betAmount > 1000) {
        $index = array_search($betAmount, $allowedAmounts);
        if ($index > 0) {
            $betAmount = $allowedAmounts[$index - 1];
        } else {
            break;
        }
    }

    $joinRes = executeBotAction($baseUrl . "/api_megaspin.php", [
        'action' => 'join',
        'amount' => $betAmount
    ], $cFile);

    if (isset($joinRes['success']) && $joinRes['success']) {
        $actions[] = "Vừa bơm <b>" . number_format($betAmount) . " GTLM</b> vào Pool Mega Spin!";
        return ['status' => 'success', 'actions' => $actions];
    }

    return null;
}
?>
