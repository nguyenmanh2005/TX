/**
 * 🤖 Bot Streamer Bàn 66: Đại Sư Ca-rô ♟️✨ (PvP Caro)
 * - Tự động tính toán nước cờ, hạ quân ép góc húp GTLM
 */
(function () {
    'use strict';
    if (typeof BotVirtualCursor === 'undefined') return;
    BotVirtualCursor.init("Đại Sư Ca-rô ♟️✨");

    const CASINO_CHATS = {
        think: [
            "Bàn cờ Ca-rô 15x15 cân não! Ra chiêu giăng bẫy tam hoa đôi khóa chặt đối thủ! ♟️⚡🔥",
            "Đọc vị nước cờ đối phương trước 3 bước! Tiếng cờ này chốt hạ thắng lợi! 🎯💎",
            "Cờ bí dí tốt, còn ta thì hạ quân ép góc mở đường Ngũ Tử Liền Hàng! 🚀👑"
        ],
        win: [
            "NGŨ TỬ LIỀN HÀNG! THẮNG VÁN CỜ CA-RÔ +{reward} GTLM! 🏆♟️🤑",
            "Gáy khét lẹt cả bàn cờ hôm nay! Thế cờ thần thánh không thể cản phá! 👑💎💰"
        ],
        loss: [
            "Lỡ tay để hổng một nước chặn, bay màu ván cờ này! Ván sau lấy lại cả vốn lẫn lời! 😅🔄"
        ]
    };

    function sendChat(type, obj = {}) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.think;
            let msg = list[Math.floor(Math.random() * list.length)];
            for (let k in obj) msg = msg.replace(`{${k}}`, obj[k]);
            BotChat.send(66, 'Đại Sư Ca-rô ♟️✨', msg);
        }
    }

    let isBusy = false;

    function runCaroTurn() {
        if (isBusy) return;
        const btn = document.getElementById('btnCaroMove');
        if (!btn) return;

        isBusy = true;
        if (Math.random() < 0.4) sendChat('think');

        BotVirtualCursor.moveToElement($(btn), 0.5, 0, () => {
            setTimeout(() => {
                BotVirtualCursor.simulateClick(() => {
                    $.get('live_66.php?action=caro_move', function (res) {
                        if (res && res.success) {
                            $('#balance-val').text(res.balance);
                            if (res.win) {
                                $('#caroLog').html(`<span style="color:#4ade80;">🎉 ${res.pattern} tại ô [${res.coord}]: Thắng giòn giã (+${res.reward.toLocaleString()} GTLM)!</span>`);
                                sendChat('win', { reward: res.reward.toLocaleString('vi-VN') });
                            } else {
                                $('#caroLog').html(`<span style="color:#ef4444;">😢 Bị phản đòn tại ô [${res.coord}]: Bay màu tiếng cờ này!</span>`);
                                sendChat('loss');
                            }
                        }
                        isBusy = false;
                    }).fail(function () { isBusy = false; });
                });
            }, 300);
        });
    }

    setInterval(() => {
        if (!isBusy) runCaroTurn();
    }, 5000);

    setTimeout(() => {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(66, 'Đại Sư Ca-rô ♟️✨', 'Chào mừng toàn thể kỳ thủ đến với Bàn Cờ Ca-rô Bàn 66! Cùng tôi đấu trí hạ cờ húp trọn GTLM nhé! ♟️✨🚀');
        }
        runCaroTurn();
    }, 1500);
})();
