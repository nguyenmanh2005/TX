/**
 * 🤖 Bot Streamer Bàn 66: Đại Chiến Cờ Ca-rô AI Live (AlphaX vs DeepO)
 * - Tương tác bình luận trực tiếp Casino Rule 5.3 (GTLM, húp, gáy khét, tam hoa, ngũ tử)
 */
(function () {
    'use strict';

    const CHATS = {
        alphaX_taunt: [
            "AlphaX ta đã giăng thế trận Tam Hoa Đôi! DeepO liệu có phá nổi không? ♟️⚡🔥",
            "Đọc vị nước cờ trước 5 bước! Tiếng cờ này chốt hạ húp trọn GTLM! 🎯💎",
            "Cờ bí dí tốt, còn AlphaX thì hạ quân ép góc mở đường Ngũ Tử Liền Hàng! 🚀👑"
        ],
        deepO_taunt: [
            "DeepO tính toán lượng tử! Hóa giải bẫy ép góc trong gang tấc! 🛡️⚡",
            "Đừng vội mừng! Một nước phản công của DeepO là bay màu cả ván cờ! ⚔️🔥",
            "Đỉnh cao phòng ngự phản kích! Xem ai mới là bậc thầy cờ Ca-rô thực thụ! 💎🏆"
        ],
        alphaX_win: [
            "NGŨ TỬ LIỀN HÀNG! Master AlphaX hạ gục DeepO húp trọn 1.000.000 GTLM! 🏆♟️🤑",
            "Gáy khét lẹt cả sàn đấu hôm nay! Thế cờ thần thánh không thể cản phá! 👑💎💰"
        ],
        deepO_win: [
            "CHIẾN THẮNG TUYỆT ĐỐI! DeepO phản công chớp nhoáng húp trọn hũ 1.000.000 GTLM! 🏆🔥🎉",
            "Bậc thầy tính toán lượng tử! Gáy khét cả phòng live cùng DeepO nào anh em! 🚀👑💎"
        ]
    };

    function sendChat(sender, msg) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(66, sender, msg);
        }
    }

    // Kết nối với Live Arena
    if (window.CaroLiveArena) {
        window.CaroLiveArena.onWin = function (winnerCode, winnerName) {
            if (winnerCode === 1) {
                const msg = CHATS.alphaX_win[Math.floor(Math.random() * CHATS.alphaX_win.length)];
                sendChat('🤖 Master AlphaX ❌', msg);
                setTimeout(() => {
                    sendChat('🤖 Grandmaster DeepO ⭕', 'Thế cờ biến ảo khôn lường! Ván sau ta quyết phục hận! 🤝🔥');
                }, 1500);
            } else {
                const msg = CHATS.deepO_win[Math.floor(Math.random() * CHATS.deepO_win.length)];
                sendChat('🤖 Grandmaster DeepO ⭕', msg);
                setTimeout(() => {
                    sendChat('🤖 Master AlphaX ❌', 'Đòn phản công quá hiểm! Đợi đấy, ván sau ta lấy lại! 😅🔄');
                }, 1500);
            }
        };
    }

    // Chat tương tác định kỳ tạo không khí
    setInterval(() => {
        if (Math.random() < 0.5) {
            const isAlpha = Math.random() < 0.5;
            const list = isAlpha ? CHATS.alphaX_taunt : CHATS.deepO_taunt;
            const sender = isAlpha ? '🤖 Master AlphaX ❌' : '🤖 Grandmaster DeepO ⭕';
            const msg = list[Math.floor(Math.random() * list.length)];
            sendChat(sender, msg);
        }
    }, 18000);

    // Lời chào mở màn
    setTimeout(() => {
        sendChat('👑 Trọng Tài AI ♟️', 'Chào mừng toàn thể đạo hữu đến với Võ Đài Ca-rô Bàn 66! Đại chiến AlphaX vs DeepO 24/7 chính thức bùng nổ! ♟️✨🚀');
    }, 1500);

})();
