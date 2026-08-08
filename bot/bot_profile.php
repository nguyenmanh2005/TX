<?php
/**
 * Bot Profile Logic
 * Giả lập hành vi bot cập nhật hồ sơ cá nhân để trông giống thật hơn
 */

function handleProfileBot($baseUrl, $cFile) {
    $actions = [];

    // Tỉ lệ rất thấp (2%) để bot đổi tiểu sử
    if (rand(1, 100) <= 2) {
        $bios = [
            'Chỉ là một lữ khách đi ngang qua...',
            'Đam mê cày cuốc, sống về đêm.',
            'Tay chơi thứ thiệt, anh em nào giao lưu không?',
            'Mục tiêu: Đứng đầu bảng xếp hạng!',
            'Người chơi hệ tâm linh, trước khi chơi phải thắp nhang.',
            'Vui là chính, húp GTLM là chủ yếu.',
            'Đang trong chuỗi trầm cảm vì bay màu liên tục...'
        ];
        
        $locations = ['Hà Nội', 'Hồ Chí Minh', 'Đà Nẵng', 'Hải Phòng', 'Cần Thơ', 'Mặt Trăng', 'Sao Hỏa', 'Bí mật'];
        $colors = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff', '#000000', '#ffffff'];

        $bio = $bios[array_rand($bios)];
        $location = $locations[array_rand($locations)];
        $color = $colors[array_rand($colors)];

        $profRes = executeBotAction($baseUrl . "/api_profile.php", [
            'action' => 'update_profile',
            'bio' => $bio,
            'location' => $location,
            'favorite_color' => $color,
            'show_statistics' => 'on'
        ], $cFile, true);
        
        if (isset($profRes['success']) && $profRes['success']) {
            $actions[] = "Vừa tân trang lại Profile cá nhân (Bio: <i>\"$bio\"</i>)";
        }
    }

    if (!empty($actions)) {
        return ['status' => 'success', 'actions' => $actions];
    }
    
    return null;
}
?>
