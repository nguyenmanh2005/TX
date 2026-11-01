/**
 * 🤖 Bot Streamer Bàn 67: Nữ Thần May Mắn 🎡✨ (Lucky Wheel)
 * - Tự động quay vòng may mắn, đón bão thưởng Jackpot GTLM
 */
(function () {
    'use strict';
    if (typeof BotVirtualCursor === 'undefined') return;
    BotVirtualCursor.init("Nữ Thần May Mắn 🎡✨");

    const CASINO_CHATS = {
        spin: [
            "Vòng quay Hoàng Gia khởi động! Ra chiêu quay bánh xe tài lộc đón bão Jackpot! 🎡✨💎",
            "Bánh xe may mắn đang quay tít mù! Cả sòng nín thở chờ ô thưởng lớn nhất! 🌟🚀",
            "Hôm nay tay quay đang đỏ rực rỡ, lộc lá GTLM chuẩn bị về nick ngập tràn! 💰🔥"
        ],
        win: [
            "VÒNG QUAY DỪNG LẠI Ở Ô {prize}! HÚP TRỌN GIẢI THƯỞNG +{reward} GTLM! 🏆🎡🤑",
            "Gáy khét lẹt cả sòng hôm nay! Thần tài hiển linh ban phước lành! 👑💎💰"
        ]
    };

    function sendChat(type, obj = {}) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.spin;
            let msg = list[Math.floor(Math.random() * list.length)];
            for (let k in obj) msg = msg.replace(`{${k}}`, obj[k]);
            BotChat.send(67, 'Nữ Thần May Mắn 🎡✨', msg);
        }
    }

    let isBusy = false;
    let currentRotation = 0;

    function runWheelTurn() {
        if (isBusy) return;
        const btn = document.getElementById('btnSpinWheel');
        if (!btn) return;

        isBusy = true;
        if (Math.random() < 0.4) sendChat('spin');

        BotVirtualCursor.moveToElement($(btn), 0.5, 0, () => {
            setTimeout(() => {
                BotVirtualCursor.simulateClick(() => {
                    currentRotation += 1440 + Math.floor(Math.random() * 360);
                    $('#wheelCircle').css('transform', `rotate(${currentRotation}deg)`);

                    setTimeout(() => {
                        $.get('live_67.php?action=spin_wheel', function (res) {
                            if (res && res.success) {
                                $('#balance-val').text(res.balance);
                                $('#wheelLog').html(`<span style="color:#ffd700;">🎉 Chúc mừng trúng ${res.name} (+${res.reward.toLocaleString()} GTLM)!</span>`);
                                sendChat('win', { prize: res.name, reward: res.reward.toLocaleString('vi-VN') });
                            }
                            isBusy = false;
                        }).fail(function () { isBusy = false; });
                    }, 2600);
                });
            }, 300);
        });
    }

    setInterval(() => {
        if (!isBusy) runWheelTurn();
    }, 6000);

    setTimeout(() => {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(67, 'Nữ Thần May Mắn 🎡✨', 'Chào mừng toàn thể anh em đến với Vòng Quay May Mắn Bàn 67! Nữ thần xuất trận, cùng tôi quay bánh xe tài lộc húp trọn Jackpot nhé! 🎡✨🚀');
        }
        runWheelTurn();
    }, 1500);
})();
