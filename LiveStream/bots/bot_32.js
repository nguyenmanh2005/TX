// LiveStream/bots/bot_32.js
// Kịch bản Bot cho game Keno (ID 33)
// Tích hợp LiveStream Chat 24/7 theo chuẩn Casino Rule 5.3

if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Vua Soi Cầu Keno 🔢");

    const CASINO_CHATS = {
        bet: [
            "Xổ số Keno siêu tốc! Ra chiêu chọn dàn số vàng đón bão GTLM! 🔢⚡",
            "Cầu số Keno đang rực sáng, vào tiền tự tin húp trọn ván này! 💎🔥",
            "Quay lồng cầu 80 số! Anh em cùng nín thở đếm số trúng nào! 🌟💰",
            "Khóa chặt các con số may mắn, chờ bóng vàng rơi về nick! 🎯🔢"
        ],
        win: [
            "TRÚNG LỚN KENO RỒI! Húp trọn vé số vàng, tiền cộng đầy nick! 🏆🔢🤑",
            "Đoán trúng cả dàn số! Gáy khét lẹt cả sảnh Keno hôm nay! 👑🔥💰",
            "Lộc lá Keno đã về! Bão tiền nổ tưng bừng! 💎✨"
        ],
        loss: [
            "Hụt mất vài con số đẹp, bay màu nhẹ ván này! Vé sau phục thù! 😅🔄",
            "Bốc hơi một chút, xốc lại dàn số ra chiêu tiếng mới! 💪🔢"
        ]
    };

    function sendChat(type) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.bet;
            const msg = list[Math.floor(Math.random() * list.length)];
            BotChat.send(32, 'Vua Soi Cầu Keno 🔢', msg);
        }
    }

    async function playKenoRound() {
        // 0. Tắt thông báo SweetAlert nếu có
        const swalBtn = document.querySelector('.swal2-confirm');
        if (swalBtn) {
            const swalTxt = (document.querySelector('.swal2-title')?.innerText || '').toLowerCase();
            if (swalTxt.includes('thắng') || swalTxt.includes('win') || swalTxt.includes('trúng')) {
                if (Math.random() < 0.6) sendChat('win');
            } else if (swalTxt.includes('thua') || swalTxt.includes('hụt') || swalTxt.includes('trượt')) {
                if (Math.random() < 0.5) sendChat('loss');
            }
            BotVirtualCursor.moveToElement($(swalBtn), 0.5, 0, () => {
                BotVirtualCursor.simulateClick(() => { swalBtn.click(); });
            });
            await new Promise(r => setTimeout(r, 800));
            return; 
        }

        const drawBtn = document.getElementById('drawBtn');
        if (drawBtn && drawBtn.disabled && document.querySelectorAll('.keno-number.selected').length > 0 && !swalBtn) {
            return; 
        }

        // 1. Đổi vé số? (30% cơ hội)
        if (Math.random() < 0.3) {
            const selected = Array.from(document.querySelectorAll('.keno-number.selected'));
            for (let num of selected) {
                BotVirtualCursor.moveToElement($(num), 0.2, 0, () => {
                    BotVirtualCursor.simulateClick(() => { num.click(); });
                });
                await new Promise(r => setTimeout(r, 300));
            }
        }

        // 2. Chọn số nếu chưa có số nào
        let selectedCount = document.querySelectorAll('.keno-number.selected').length;
        if (selectedCount === 0) {
            const targetPicks = Math.floor(Math.random() * 5) + 3; // Chọn 3 đến 7 con số
            while (selectedCount < targetPicks) {
                const allNums = Array.from(document.querySelectorAll('.keno-number:not(.selected)'));
                if (allNums.length === 0) break;
                const rndNum = allNums[Math.floor(Math.random() * allNums.length)];
                BotVirtualCursor.moveToElement($(rndNum), 0.3, 0, () => {
                    BotVirtualCursor.simulateClick(() => { rndNum.click(); });
                });
                await new Promise(r => setTimeout(r, 400));
                selectedCount++;
            }
        }

        // 3. Đổi mức cược? (50% cơ hội)
        if (Math.random() < 0.5) {
            const chips = Array.from(document.querySelectorAll('.chip')).filter(b => !b.innerText.includes('MAX') && !b.innerText.includes('5M'));
            if (chips.length > 0) {
                const chipBtn = chips[Math.floor(Math.random() * chips.length)];
                BotVirtualCursor.moveToElement($(chipBtn), 0.4, 0, () => {
                    BotVirtualCursor.simulateClick(() => { chipBtn.click(); });
                });
                await new Promise(r => setTimeout(r, 600));
            }
        }

        // 4. Bấm QUAY SỐ
        if (drawBtn && !drawBtn.disabled) {
            BotVirtualCursor.moveToElement($(drawBtn), 0.6, 0, () => {
                BotVirtualCursor.simulateClick(() => { 
                    drawBtn.click(); 
                    if (Math.random() < 0.35) sendChat('bet');
                });
            });
        }
    }

    // Vòng lặp hành động chơi game
    function scheduleNextRound() {
        const waitTime = 4000 + Math.random() * 4000;
        setTimeout(() => {
            if (Math.random() > 0.1) {
                playKenoRound().then(() => scheduleNextRound());
                return;
            }
            scheduleNextRound();
        }, waitTime);
    }
    
    // Khởi động bot
    setTimeout(scheduleNextRound, 2000);
}
