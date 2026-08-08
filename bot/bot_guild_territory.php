<?php
/**
 * Bot Guild Territory Logic
 * Giả lập hành vi bot Bang Chủ đi chiếm lĩnh lãnh địa
 */

function handleGuildTerritoryBot($baseUrl, $cFile) {
    $actions = [];

    // Tỉ lệ 5% để bot đi ngó nghiêng bản đồ lãnh địa
    if (rand(1, 100) <= 5) {
        $mapRes = executeBotAction($baseUrl . "/api_guild_territory.php", ['action' => 'get_map'], $cFile, true);
        
        if (isset($mapRes['success']) && $mapRes['success']) {
            $role = $mapRes['role'] ?? 'member';
            $myGuildId = $mapRes['my_guild_id'] ?? 0;
            $territories = $mapRes['territories'] ?? [];
            
            // Chỉ Leader/Officer mới đi chiếm
            if ($myGuildId > 0 && ($role === 'leader' || $role === 'officer') && !empty($territories)) {
                // Tìm lãnh địa chưa có chủ hoặc của bang khác (ngẫu nhiên)
                $target = $territories[array_rand($territories)];
                
                if ($target['guild_id'] != $myGuildId) {
                    $capRes = executeBotAction($baseUrl . "/api_guild_territory.php", [
                        'action' => 'capture',
                        'territory_id' => $target['id']
                    ], $cFile, true);
                    
                    if (isset($capRes['success']) && $capRes['success']) {
                        $actions[] = "Thân chinh dẫn quân đi đánh chiếm Lãnh Địa <b>" . $target['name'] . "</b> thành công vang dội!";
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
