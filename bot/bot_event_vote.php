<?php
/**
 * Event Vote Bot Logic
 * Quản lý bot tự động tham gia Bầu Chọn Sự Kiện Mùa.
 */

function handleEventVoteBot($baseUrl, $cFile) {
    $actions = [];

    // 1. Lấy thông tin Bầu chọn
    $optionsRes = executeBotAction($baseUrl . "/api_event_vote.php?action=get_options", null, $cFile);
    
    if (isset($optionsRes['success']) && $optionsRes['success'] && !empty($optionsRes['options'])) {
        // Kiểm tra xem Bot đã vote chưa
        if (is_null($optionsRes['my_vote'])) {
            $options = $optionsRes['options'];
            $eventId = $optionsRes['event_id'];
            
            // Chọn ngẫu nhiên 1 option (Có thể điều chỉnh trọng số sau này nếu muốn bot nghiêng về phe nào)
            $chosenOption = $options[array_rand($options)];
            $optionId = $chosenOption['id'];
            $optionTitle = $chosenOption['title'];

            // Tiến hành Vote
            $voteRes = executeBotAction($baseUrl . "/api_event_vote.php", [
                'action' => 'vote',
                'option_id' => $optionId
            ], $cFile);

            if (isset($voteRes['success']) && $voteRes['success']) {
                $actions[] = "Đã bỏ phiếu cho phe <b>$optionTitle</b> trong Sự kiện Mùa!";
                return ['status' => 'success', 'actions' => $actions];
            }
        }
    }

    return null;
}
?>
