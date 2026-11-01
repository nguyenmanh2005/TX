/**
 * 🤖 Bot Streamer Bàn 65: Chiến Thần Đấu Trường ⚔️👑 (PvP Arena)
 * - Tự động khiêu chiến đối thủ 1vs1 trên đấu trường
 */
(function () {
    'use strict';
    if (typeof BotVirtualCursor === 'undefined') return;
    BotVirtualCursor.init("Chiến Thần Đấu Trường ⚔️👑");

    const CASINO_CHATS = {
        fight: [
            "Đấu trường rực lửa! Ra chiêu khiêu chiến cao thủ 1vs1 đè bẹp trận địa! ⚔️🔥👑",
            "Ai dám bước lên lôi đài tiếp chiêu của ta? Vào trận phân định thắng thua! 🎯⚡",
            "Khí thế ngút ngàn, kiếm xuất vỏ là phải húp trọn GTLM đối phương! 🚀💰"
        ],
        win: [
            "HẠ GỤC ĐỐI THỦ! HÚP TRỌN CHIẾN THẮNG PvP +{reward} GTLM! 🏆⚔️🤑",
            "Gáy khét lẹt cả đấu trường hôm nay! Bất khả chiến bại! 👑💎💰"
        ],
        loss: [
            "Đối thủ tung chiêu hiểm quá, bay màu tiếng này! Hiệp sau gỡ lại! 😅🔄",
            "Bốc hơi chút ít GTLM, xốc lại ý chí chiến thần phục thù ngay ván mới! 💪🔥"
        ]
    };

    function sendChat(type, obj = {}) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.fight;
            let msg = list[Math.floor(Math.random() * list.length)];
            for (let k in obj) msg = msg.replace(`{${k}}`, obj[k]);
            BotChat.send(65, 'Chiến Thần Đấu Trường ⚔️👑', msg);
        }
    }

    let isBusy = false;

    function runBattleTurn() {
        if (isBusy) return;
        const btn = document.getElementById('btnFight');
        if (!btn) return;

        isBusy = true;
        if (Math.random() < 0.4) sendChat('fight');

        BotVirtualCursor.moveToElement($(btn), 0.5, 0, () => {
            setTimeout(() => {
                BotVirtualCursor.simulateClick(() => {
                    $.get('live_65.php?action=pvp_battle', function (res) {
                        if (res && res.success) {
                            $('#balance-val').text(res.balance);
                            $('#oppName').text(res.opponent);
                            if (res.win) {
                                $('#battleLog').html(`<span style="color:#4ade80;">🎉 Chiến thắng oanh liệt trước ${res.opponent} (+${res.reward.toLocaleString()} GTLM)!</span>`);
                                sendChat('win', { reward: res.reward.toLocaleString('vi-VN') });
                            } else {
                                $('#battleLog').html(`<span style="color:#ef4444;">😢 Thất bại sát nút trước ${res.opponent} (-${res.bet.toLocaleString()} GTLM)!</span>`);
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
        if (!isBusy) runBattleTurn();
    }, 5200);

    setTimeout(() => {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(65, 'Chiến Thần Đấu Trường ⚔️👑', 'Chào mừng toàn thể anh em đến với Đấu Trường PvP Bàn 65! Chiến thần xuất kích, cùng tôi đè bẹp mọi đối thủ húp trọn GTLM nhé! ⚔️👑🚀');
        }
        runBattleTurn();
    }, 1500);
})();
