<?php
/**
 * Lucky Wheel Bot Logic
 * Quản lý bot tự động tham gia Vòng Quay May Mắn (1 lần/ngày).
 */

function handleLuckyWheelBot($baseUrl, $cFile) {
    $actions = [];

    // Cố gắng gọi API quay thưởng, nếu hôm nay quay rồi thì API tự chặn
    $spinRes = executeBotAction($baseUrl . "/api_lucky_wheel.php?action=spin", null, $cFile);

    if (isset($spinRes['status']) && $spinRes['status'] === 'success') {
        $reward = $spinRes['reward'];
        $rewardName = $reward['name'];
        $rewardType = $reward['type']; // 'money', 'item', 'nothing', ...

        if ($rewardType === 'money' || $rewardType === 'jackpot') {
            $actions[] = "Vừa quay Vòng Quay May Mắn và trúng <b>$rewardName</b>!";
        } else if ($rewardType === 'nothing') {
            $actions[] = "Quay Vòng Quay May Mắn nhưng xui quá vào ô <b>$rewardName</b>...";
        } else {
            $actions[] = "Quay Vòng Quay May Mắn nhận được <b>$rewardName</b>!";
        }
        
        return ['status' => 'success', 'actions' => $actions];
    }

    // Nếu trả về error do đã quay rồi thì return null (im lặng)
    return null;
}
?>
