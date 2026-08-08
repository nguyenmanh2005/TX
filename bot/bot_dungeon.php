<?php
/**
 * Bot Dungeon Logic
 * Cho phép bot tham gia Thám Hiểm Hang Động và nhận rương phần thưởng
 */

function handleDungeonBot($baseUrl, $cFile) {
    $actions = [];

    if (rand(1, 100) <= 20) { // 20% cơ hội check Hang Động
        $dungeonRes = executeBotAction($baseUrl . "/api_dungeon.php?action=get_status", null, $cFile);
        
        if (isset($dungeonRes['success']) && $dungeonRes['success']) {
            $completions = $dungeonRes['completions'] ?? [];
            
            // Hang động có 3 tier, bot sẽ check xem có tier nào status = 'completed' để claim không
            // Nhưng API get_status không trả về status completion thẳng nếu chưa click vào chơi, 
            // nó chỉ trả progress. Do bot không thể chơi minigame 2D (chỉ có API claim), 
            // ta giả lập việc bot "gửi API claim" trực tiếp để hên xui ăn được nếu progress đầy 
            // (hoặc nếu là dev có thể fake progress)
            // Tuy nhiên, logic dungeon_helper.php bắt buộc phải đánh boss.
            
            // Tạm thời bỏ qua phần đánh quái (vì cần websocket/game logic), 
            // Bot chỉ thử nhặt rương (Claim Tier) nếu may mắn có bug hoặc đã hoàn thành.
            foreach ([1, 2, 3] as $tier) {
                // Kiểm tra xem tier này đã completed chưa
                $isCompleted = false;
                foreach ($completions as $c) {
                    if ($c['tier'] == $tier && $c['status'] === 'completed') {
                        $isCompleted = true;
                        break;
                    }
                }

                if ($isCompleted) {
                    $claimRes = executeBotAction($baseUrl . "/api_dungeon.php", [
                        'action' => 'claim_tier',
                        'claim_tier' => $tier
                    ], $cFile, true);

                    if (isset($claimRes['success']) && $claimRes['success']) {
                        $actions[] = "Vừa phá đảo xong Tầng $tier của Hang Động và húp trọn Rương Báu!";
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
