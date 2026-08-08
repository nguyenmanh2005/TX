<?php
/**
 * Monthly Pass Bot Logic
 * Quản lý bot tự động mua và nhận thưởng Thẻ Tháng.
 */

function handleMonthlyPassBot($baseUrl, $cFile, $botMoney) {
    $actions = [];

    // 1. Thử nhận thưởng Thẻ Tháng
    $claimRes = executeBotAction($baseUrl . "/api_monthly_pass.php", [
        'action' => 'claim'
    ], $cFile);

    if (isset($claimRes['status']) && $claimRes['status'] === 'success') {
        $actions[] = "Vừa rút GTLM lãi từ Thẻ Tháng: <b>" . str_replace("Đã nhận ", "", $claimRes['message']) . "</b>";
        return ['status' => 'success', 'actions' => $actions];
    }

    // 2. Nếu báo lỗi "Bạn không có gói Monthly Pass nào đang hoạt động!" thì tiến hành mua
    if (isset($claimRes['status']) && $claimRes['status'] === 'error' && strpos($claimRes['message'], 'không có gói') !== false) {
        $passToBuy = 0;
        $passName = "";

        if ($botMoney > 20000000) { // Nếu có hơn 20 Triệu GTLM, mua Gold Pass (id = 2)
            $passToBuy = 2;
            $passName = "Gold Pass";
        } elseif ($botMoney > 5000000) { // Nếu có hơn 5 Triệu GTLM, mua Silver Pass (id = 1)
            $passToBuy = 1;
            $passName = "Silver Pass";
        }

        if ($passToBuy > 0) {
            $buyRes = executeBotAction($baseUrl . "/api_monthly_pass.php", [
                'action' => 'buy',
                'id' => $passToBuy
            ], $cFile);

            if (isset($buyRes['status']) && $buyRes['status'] === 'success') {
                $actions[] = "Thấy tài chính rủng rỉnh nên vừa gia hạn thêm gói <b>$passName</b>!";
                return ['status' => 'success', 'actions' => $actions];
            }
        }
    }

    return null;
}
?>
