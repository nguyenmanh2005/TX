<?php
/**
 * Storyline Bot Logic
 * Quản lý bot tự động nhận phần thưởng từ Đại Chiến Cổ Tích.
 */

function handleStorylineBot($baseUrl, $cFile) {
    $actions = [];

    // 1. Lấy trạng thái Cốt truyện
    $stateRes = executeBotAction($baseUrl . "/api_storyline.php?action=get_state", null, $cFile);
    if (!isset($stateRes['success']) || !$stateRes['success'] || empty($stateRes['state'])) {
        return null;
    }

    $state = $stateRes['state'];
    $chapters = $stateRes['chapters'] ?? [];
    
    $eventId = $state['event_id'];
    $unlocked = $state['unlocked_chapters'];
    $completed = $state['completed_chapters'];
    $betsPlaced = $state['bets_placed_today'];

    // 2. Kiểm tra xem có chương nào đang mở khóa mà chưa hoàn thành không
    if ($unlocked > $completed) {
        // Tìm thông tin của chương hiện tại đang cần hoàn thành
        $targetChapter = null;
        foreach ($chapters as $ch) {
            if ($ch['chapter_number'] == $unlocked) {
                $targetChapter = $ch;
                break;
            }
        }

        if ($targetChapter) {
            $targetBets = $targetChapter['target_bets'];
            // 3. Nếu số lần cược đã đủ, tiến hành nhận thưởng
            if ($betsPlaced >= $targetBets) {
                $claimRes = executeBotAction($baseUrl . "/api_storyline.php", [
                    'action' => 'claim',
                    'chapter_number' => $unlocked,
                    'event_id' => $eventId
                ], $cFile);

                if (isset($claimRes['success']) && $claimRes['success']) {
                    $actions[] = "Đã cày đủ KPI và nhận <b>" . number_format($claimRes['reward']) . " GTLM</b> từ Chương $unlocked (Đại Chiến Cổ Tích)!";
                    return ['status' => 'success', 'actions' => $actions];
                }
            }
        }
    }

    return null;
}
?>
