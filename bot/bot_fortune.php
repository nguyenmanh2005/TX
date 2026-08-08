<?php
/**
 * Fortune Bot Logic
 * Quản lý bot tự động đi xin xăm đầu ngày.
 */

function handleFortuneBot($baseUrl, $cFile) {
    $actions = [];

    // Cố gắng gọi API xin xăm, nếu hôm nay xin rồi thì API trả về already_drawn = true
    $fortuneRes = executeBotAction($baseUrl . "/api_fortune.php", null, $cFile);

    if (isset($fortuneRes['status']) && $fortuneRes['status'] === 'success') {
        if (isset($fortuneRes['already_drawn']) && $fortuneRes['already_drawn'] === false) {
            $fortuneText = $fortuneRes['fortune'];
            $luckyGame = $fortuneRes['lucky_game'];

            $actions[] = "Đầu ngày đi bốc quẻ: <i>\"$fortuneText\"</i> (Game may mắn: <b>$luckyGame</b>)";
            return ['status' => 'success', 'actions' => $actions];
        }
    }

    // Đã bốc rồi hoặc lỗi thì im lặng
    return null;
}
?>
