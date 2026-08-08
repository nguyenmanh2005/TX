<?php
/**
 * Bot VIP Logic
 * Quản lý bot tự động nhận thưởng VIP hàng ngày nếu có cấp độ VIP
 */

function handleVipBot($baseUrl, $cFile) {
    $actions = [];

    // Chỉ thực hiện 1 lần mỗi ngày hoặc với tỉ lệ nhỏ
    if (rand(1, 100) <= 5) {
        $vipRes = executeBotAction($baseUrl . "/api_vip.php", ['action' => 'claim_daily_bonus'], $cFile, true);
        
        if (isset($vipRes['status']) && $vipRes['status'] === 'success') {
            $actions[] = "Tự động kích hoạt đặc quyền và nhận Quà VIP Hàng Ngày!";
        }
    }

    if (!empty($actions)) {
        return ['status' => 'success', 'actions' => $actions];
    }
    
    return null;
}
?>
