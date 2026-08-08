<?php
/**
 * Oracle Prophecy Bot Logic
 * Quản lý bot tự động "Chứng kiến" Lời Tiên Tri của Lão Tiên Tri.
 */

function handleOracleBot($baseUrl, $cFile) {
    $actions = [];

    // 1. Lấy tuần hiện tại của Lời Tiên Tri
    $stateRes = executeBotAction($baseUrl . "/api_oracle_prophecy.php?action=get_current", null, $cFile);
    
    if (isset($stateRes['success']) && $stateRes['success'] && isset($stateRes['week'])) {
        // Kiểm tra xem Bot đã chứng kiến chưa
        if (isset($stateRes['has_witnessed']) && $stateRes['has_witnessed'] === false) {
            // Nếu chưa, gọi API để chứng kiến
            $witnessRes = executeBotAction($baseUrl . "/api_oracle_prophecy.php", [
                'action' => 'witness'
            ], $cFile);

            if (isset($witnessRes['success']) && $witnessRes['success']) {
                $actions[] = "Vừa cúi đầu chứng kiến 3 Lời Tiên Tri tuần này! (Tổng số người chứng kiến: <b>" . $witnessRes['witness_count'] . "</b>)";
                return ['status' => 'success', 'actions' => $actions];
            }
        }
    }

    return null;
}
?>
