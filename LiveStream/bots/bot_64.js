/**
 * 🤖 Bot Streamer Bàn 64: Bậc Thầy Huấn Luyện 🐾🌟 (Pets)
 * - Tự động huấn luyện linh thú, thu hoạch EXP và lộc lá GTLM
 */
(function () {
    'use strict';
    if (typeof BotVirtualCursor === 'undefined') return;
    BotVirtualCursor.init("Bậc Thầy Huấn Luyện 🐾🌟");

    const CASINO_CHATS = {
        train: [
            "Linh thú cưng đã thức giấc! Ra chiêu tuyệt kỹ huấn luyện thần thú rước lộc về nick! 🐾✨🔥",
            "Chăm sóc đàn linh thú chu đáo, lộc lá GTLM tự khắc tìm đến gõ cửa! 🦊💎",
            "Thần thú xuất trận mang theo vinh hoa phú quý! Anh em cùng tôi thưởng ngoạn nhé! 🌟🚀"
        ],
        success: [
            "HUẤN LUYỆN ĐẠI THÀNH CÔNG {pet}! Húp trọn lộc lá +{reward} GTLM! 🏆🐾🤑",
            "Gáy khét lẹt cả vườn thú cưng hôm nay! Linh thú thăng cấp vinh quang! 👑💎💰"
        ]
    };

    function sendChat(type, obj = {}) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.train;
            let msg = list[Math.floor(Math.random() * list.length)];
            for (let k in obj) msg = msg.replace(`{${k}}`, obj[k]);
            BotChat.send(64, 'Bậc Thầy Huấn Luyện 🐾🌟', msg);
        }
    }

    let isBusy = false;

    function runPetTurn() {
        if (isBusy) return;
        const btn = document.getElementById('btnTrainPet');
        if (!btn) return;

        isBusy = true;
        if (Math.random() < 0.4) sendChat('train');

        BotVirtualCursor.moveToElement($(btn), 0.5, 0, () => {
            setTimeout(() => {
                BotVirtualCursor.simulateClick(() => {
                    $.get('live_64.php?action=train_pet', function (res) {
                        if (res && res.success) {
                            $('#balance-val').text(res.balance);
                            $('#petLog').text(`Đã huấn luyện ${res.pet}: +${res.xp} EXP (+${res.reward.toLocaleString()} GTLM)!`);
                            sendChat('success', { pet: res.pet, reward: res.reward.toLocaleString('vi-VN') });
                        }
                        isBusy = false;
                    }).fail(function () { isBusy = false; });
                });
            }, 300);
        });
    }

    setInterval(() => {
        if (!isBusy) runPetTurn();
    }, 4800);

    setTimeout(() => {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(64, 'Bậc Thầy Huấn Luyện 🐾🌟', 'Chào mừng toàn thể anh em đến với Vườn Linh Thú Bàn 64! Cùng tôi chăm sóc thần thú rinh bão GTLM nhé! 🐾🌟🚀');
        }
        runPetTurn();
    }, 1500);
})();
