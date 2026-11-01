// LiveStream/bots/bot_34.js
// Kịch bản Bot cho game Let It Ride (ID 34)
// Tích hợp LiveStream Chat 24/7 theo chuẩn Casino Rule 5.3

if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Cao Thủ Let It Ride ♠️🔥");

    const CASINO_CHATS = {
        deal: [
            "Khai cuộc Let It Ride Poker! Ra chiêu 3 cược liên hoàn đè bẹp nhà cái! ♠️🔥",
            "Chia 3 lá bài đầu tiên nào! Cầu đang đỏ, tự tin Let It Ride! 💎⚡",
            "Ra chiêu dứt khoát, tiếng bạc này không Thùng thì cũng Cù Lũ! 🌟💰",
            "Vào tiền chắc tay, đón 2 lá bài chung hoàn thiện tay bài siêu phẩm! 🃏💎"
        ],
        ride: [
            "Thế bài quá đẹp! Let It Ride! Giữ nguyên cược xông pha đón bão GTLM! 🚀💎",
            "Bài sáng quắc, giữ cược tất tay đè bẹp ván giao lưu! ⚡🔥"
        ],
        pull: [
            "Rút lại 1 cược phòng thủ! Quản trị vốn tối ưu bảo toàn nick! 🛡️✋",
            "Lùi một bước để tiến hai bước, thu tiền về nick giữ nhịp an toàn! 🍵✨"
        ],
        win: [
            "HÚP TRỌN VÁN POKER LET IT RIDE! Bài đẹp nổ hũ, tiền cộng mỏi tay! 🏆♠️🤑",
            "Gáy khét lẹt cả sòng poker hôm nay! Chiến thắng giòn giã! 👑🔥💰",
            "Từ đôi 10 đến Cù Lũ! Lộc lá GTLM về nick ngập tràn! 💎✨"
        ],
        loss: [
            "Không đủ đôi 10 trở lên rồi, bay màu tiếng cược này! 😭💔",
            "Bốc hơi nhẹ, ván sau ra chiêu tất tay phục thù! 💪🔄"
        ]
    };

    function sendChat(type) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.deal;
            const msg = list[Math.floor(Math.random() * list.length)];
            BotChat.send(34, 'Cao Thủ Let It Ride ♠️🔥', msg);
        }
    }

    async function playLetItRide() {
        // Xử lý nút VÁN MỚI
        const resetBtn = document.getElementById('reset-btn');
        const resultArea = document.getElementById('result-area');
        if (resetBtn && resultArea && resultArea.style.display !== 'none') {
            const resTxt = (resultArea.innerText || '').toLowerCase();
            if (resTxt.includes('thắng') || resTxt.includes('win') || resTxt.includes('nhận')) {
                if (Math.random() < 0.6) sendChat('win');
            } else if (resTxt.includes('thua') || resTxt.includes('mất') || resTxt.includes('không trúng')) {
                if (Math.random() < 0.5) sendChat('loss');
            }
            BotVirtualCursor.moveToElement($(resetBtn), 0.5, 0, () => {
                BotVirtualCursor.simulateClick(() => { resetBtn.click(); });
            });
            return;
        }

        // Xử lý Lượt 1
        const action1 = document.getElementById('action-1');
        if (action1 && action1.style.display !== 'none') {
            const btns = $(action1).find('button');
            if (btns.length > 0) {
                const btn = btns[Math.floor(Math.random() * btns.length)];
                BotVirtualCursor.moveToElement($(btn), 0.6, 0, () => {
                    BotVirtualCursor.simulateClick(() => { 
                        btn.click(); 
                        const txt = (btn.innerText || '').toLowerCase();
                        if (txt.includes('ride') || txt.includes('giữ')) sendChat('ride');
                        else sendChat('pull');
                    });
                });
            }
            return;
        }

        // Xử lý Lượt 2
        const action2 = document.getElementById('action-2');
        if (action2 && action2.style.display !== 'none') {
            const btns = $(action2).find('button');
            if (btns.length > 0) {
                const btn = btns[Math.floor(Math.random() * btns.length)];
                BotVirtualCursor.moveToElement($(btn), 0.6, 0, () => {
                    BotVirtualCursor.simulateClick(() => { 
                        btn.click(); 
                        const txt = (btn.innerText || '').toLowerCase();
                        if (txt.includes('ride') || txt.includes('giữ')) sendChat('ride');
                        else sendChat('pull');
                    });
                });
            }
            return;
        }

        // Xử lý BẮT ĐẦU VÁN (nút Deal)
        const dealBtn = document.getElementById('deal-btn');
        const betForm = document.getElementById('bet-form');
        if (dealBtn && betForm && betForm.style.display !== 'none') {
            if (Math.random() > 0.5) {
                const chips = Array.from(document.querySelectorAll('.chip')).filter(b => !b.innerText.includes('MAX') && !b.innerText.includes('5M'));
                if (chips.length > 0) {
                    const chipBtn = chips[Math.floor(Math.random() * chips.length)];
                    BotVirtualCursor.moveToElement($(chipBtn), 0.4, 0, () => {
                        BotVirtualCursor.simulateClick(() => { chipBtn.click(); });
                    });
                    await new Promise(r => setTimeout(r, 600));
                }
            }
            BotVirtualCursor.moveToElement($(dealBtn), 0.7, 0, () => {
                BotVirtualCursor.simulateClick(() => { 
                    dealBtn.click(); 
                    if (Math.random() < 0.35) sendChat('deal');
                });
            });
        }
    }

    function scheduleNextRound() {
        const waitTime = 2500 + Math.random() * 2000;
        setTimeout(() => {
            const swalBtn = document.querySelector('.swal2-confirm');
            if (swalBtn) {
                BotVirtualCursor.moveToElement($(swalBtn), 0.5, 0, () => {
                    BotVirtualCursor.simulateClick(() => { swalBtn.click(); });
                });
                scheduleNextRound();
                return;
            }

            if (Math.random() > 0.1) {
                playLetItRide();
            }
            
            scheduleNextRound();
        }, waitTime);
    }
    
    // Kích hoạt bot
    setTimeout(scheduleNextRound, 2000);
}
