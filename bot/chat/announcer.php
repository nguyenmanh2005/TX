<?php
/**
 * 🎙️ Announcer Bot Message Templates
 */

return [
    'world_boss' => [
        'critical' => [
            "🔴 BOSS GẦN CHẾT! HP còn {hp}%! Tất cả vào đánh gấp!",
            "🔥 CƠ HỘI CUỐI! World Boss chỉ còn {hp}% máu! Ai sẽ là người kết liễu?",
            "⚔️ World Boss đang hấp hối! Mau vào húp lộc anh em ơi!"
        ],
        'slain' => [
            "💀 HẮC LONG THẦN ĐÃ BỊ TIÊU DIỆT! MVP: {username} với {damage} sát thương!",
            "🏆 CHIẾN THẮNG! Boss đã gục ngã dưới tay {username}. Phần thưởng đã được gửi!",
            "🎊 Chúc mừng {username} đã hạ gục World Boss thành công!"
        ]
    ],
    'tournament' => [
        'ending' => [
            "⏰ Tournament còn {time} PHÚT! Top 3 hiện tại: {top3}",
            "🚨 GIAI ĐOẠN NƯỚC RÚT! Chỉ còn {time} phút để leo hạng. Đừng bỏ lỡ!",
            "🏆 Ai sẽ là nhà vô địch tuần này? {time} phút nữa sẽ rõ!"
        ]
    ],
    'flash_mob' => [
        'start' => [
            "🎉 FLASH MOB KÍCH HOẠT! {duration} phút x{multiplier} reward mọi game! Nhanh lên!",
            "⚡️ SỰ KIỆN BẤT NGỜ: {name} đang diễn ra! Tăng thưởng cực mạnh trong {duration} phút!",
            "🎁 QUÀ TẶNG TRỜI CHO! {name} đã bắt đầu, x{multiplier} lộc lá cho anh em online!"
        ]
    ],
    'megaspin' => [
        'new_round' => [
            "🎰 VÒNG MỚI BẮT ĐẦU! Pool hiện tại: {amount}! Ai vào không?",
            "💰 Mega Spin đã khởi động lại! Jackpot đang ở mức {amount} GTLM!",
            "🎡 Thử vận may với Mega Spin nào! Pool đang rất to: {amount}!"
        ]
    ]
];
