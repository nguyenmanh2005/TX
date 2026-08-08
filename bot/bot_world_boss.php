<?php
/**
 * World Boss Bot Raid Logic
 * Handles automatic participation in World Boss raids
 */

function handleWorldBossBot($conn, $baseUrl, $cFile, $botMoney, $botName) {
    if ($botMoney < 5000) return null; // Không đủ GTLM để đánh (Tank: 3500, DPS/Healer: 5000)

    // Kiểm tra trạng thái World Boss hiện tại (Boss ID 1)
    $syncRes = executeBotAction($baseUrl . "/api_world_boss.php?action=sync&id=1", null, $cFile);
    
    if (!isset($syncRes['success']) || !$syncRes['success']) {
        return null;
    }

    // Nếu Boss chưa active hoặc đã chết thì bỏ qua
    if ($syncRes['status'] !== 'active' || $syncRes['hp'] <= 0) {
        return null;
    }

    $phase = $syncRes['phase'] ?? 1;

    // Phase 3 chỉ được đánh vào ban đêm (sau 20:00)
    if ($phase === 3) {
        $currentHour = (int)date('G');
        if ($currentHour < 20) {
            return null; // Bị phong ấn, bot không thể đánh
        }
    }

    $actions = [];

    // Chọn Role ngẫu nhiên (hoặc dựa vào số dư/tình huống)
    // - Tank: Chi phí rẻ, miễn nhiễm phản đòn Phase 3
    // - DPS: Sát thương to, tốn GTLM
    // - Healer: Có cơ hội hồi lại GTLM
    $myRole = $syncRes['my_role'] ?? 'dps';
    $roles = ['dps', 'tank', 'healer'];
    
    // Đổi role ngẫu nhiên với tỉ lệ 20% mỗi lượt để tạo sự linh hoạt, hoặc nếu chưa có role
    if (rand(1, 100) <= 20) {
        $newRole = $roles[array_rand($roles)];
        if ($newRole !== $myRole) {
            $roleRes = executeBotAction($baseUrl . "/api_world_boss.php", [
                'action' => 'set_role',
                'id' => 1,
                'role' => $newRole
            ], $cFile);
            if (isset($roleRes['success']) && $roleRes['success']) {
                $myRole = $newRole;
                $actions[] = "Đổi chiến thuật sang vai trò: " . strtoupper($myRole);
            }
        }
    }

    // Tấn công
    $attackRes = executeBotAction($baseUrl . "/api_world_boss.php", [
        'action' => 'attack',
        'id' => 1
    ], $cFile);

    if (isset($attackRes['success']) && $attackRes['success']) {
        $dmg = number_format($attackRes['damage'] ?? 0);
        $mechanic = $attackRes['message'] ?? '';
        $actions[] = "Tấn công Ma Thần gây $dmg sát thương! " . $mechanic;
    }

    if (!empty($actions)) {
        return ['status' => 'success', 'actions' => $actions];
    }
    
    return null;
}
?>
