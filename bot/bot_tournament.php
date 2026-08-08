<?php
/**
 * Tournament Bot Logic
 * Quản lý bot tự động đăng ký tham gia các Giải Đấu (Tournaments).
 */

function handleTournamentBot($baseUrl, $cFile, $botMoney) {
    $actions = [];

    // 1. Lấy danh sách giải đấu
    $listRes = executeBotAction($baseUrl . "/api_tournament.php?action=get_active_list", null, $cFile);
    
    if (isset($listRes['success']) && $listRes['success'] && !empty($listRes['data'])) {
        $tournaments = $listRes['data'];

        foreach ($tournaments as $t) {
            // Lọc các giải đấu đang mở đăng ký
            if ($t['status'] === 'registration' || strtolower($t['status']) === 'pending' || strtolower($t['status']) === 'upcoming') {
                
                // Kiểm tra xem Bot đã tham gia chưa và giải còn chỗ không
                $isJoined = (int)$t['is_joined'];
                $registered = (int)$t['registered_players'];
                $maxPlayers = (int)$t['max_players'];
                $entryFee = (float)$t['entry_fee'];

                if ($isJoined === 0 && ($maxPlayers === 0 || $registered < $maxPlayers)) {
                    // Kiểm tra tài chính
                    if ($botMoney >= $entryFee) {
                        // Đăng ký tham gia giải đấu
                        $joinRes = executeBotAction($baseUrl . "/api_tournament.php", [
                            'action' => 'join',
                            'tournament_id' => $t['id']
                        ], $cFile);

                        if (isset($joinRes['success']) && $joinRes['success']) {
                            $tourName = $t['name'] ?? 'Giải Đấu #' . $t['id'];
                            $actions[] = "Vừa nộp <b>" . number_format($entryFee) . " GTLM</b> để ghi danh vào <b>$tourName</b>! Chuẩn bị tranh tài thôi!";
                            return ['status' => 'success', 'actions' => $actions];
                        }
                    }
                }
            }
        }
    }

    return null;
}
?>
