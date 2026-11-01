/**
 * 🤖 Bot Streamer Bàn 63: Thương Nhân Thần Tài 🏪💎 (Market)
 * - Tự động giao dịch bảo vật, chốt đơn húp GTLM
 */
(function () {
    'use strict';
    if (typeof BotVirtualCursor === 'undefined') return;
    BotVirtualCursor.init("Thương Nhân Thần Tài 🏪💎");

    const CASINO_CHATS = {
        trade: [
            "Phiên chợ Thần Tài mở cửa! Ra chiêu săn bảo vật hiếm chốt lời ngập nick! 🏪💎✨",
            "Săn được món hời giá siêu hời! Sang tay chốt lãi húp trọn vẹn GTLM! 💰🚀",
            "Thương trường như chiến trường, mắt tinh tai thính chốt đơn rực rỡ! 🛡️🌟"
        ],
        success: [
            "CHỐT ĐƠN THÀNH CÔNG BẢO VẬT {item}! Húp trọn +{profit} GTLM vào nick! 🏆🎉🤑",
            "Gáy khét lẹt cả sàn giao dịch hôm nay! Lãi đậm ting ting tài khoản! 👑💎💰"
        ]
    };

    function sendChat(type, obj = {}) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.trade;
            let msg = list[Math.floor(Math.random() * list.length)];
            for (let k in obj) msg = msg.replace(`{${k}}`, obj[k]);
            BotChat.send(63, 'Thương Nhân Thần Tài 🏪💎', msg);
        }
    }

    let isBusy = false;

    function runTradeTurn() {
        if (isBusy) return;
        const btn = document.getElementById('btnTrade');
        if (!btn) return;

        isBusy = true;
        if (Math.random() < 0.4) sendChat('trade');

        BotVirtualCursor.moveToElement($(btn), 0.5, 0, () => {
            setTimeout(() => {
                BotVirtualCursor.simulateClick(() => {
                    $.get('live_63.php?action=trade', function (res) {
                        if (res && res.success) {
                            $('#balance-val').text(res.balance);
                            $('#tradeLog').text(`Đã chốt thành công ${res.item} (+${res.profit.toLocaleString()} GTLM)!`);
                            sendChat('success', { item: res.item, profit: res.profit.toLocaleString('vi-VN') });
                        }
                        isBusy = false;
                    }).fail(function () { isBusy = false; });
                });
            }, 300);
        });
    }

    setInterval(() => {
        if (!isBusy) runTradeTurn();
    }, 5000);

    setTimeout(() => {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(63, 'Thương Nhân Thần Tài 🏪💎', 'Chào mừng anh em đến với Chợ Thần Tài Bàn 63! Mời anh em cùng tôi săn bảo vật chốt lời GTLM nhé! 🏪💎🚀');
        }
        runTradeTurn();
    }, 1500);
})();
