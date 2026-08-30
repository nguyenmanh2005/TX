// LiveStream/bots/bot_33.js
// Kịch bản Bot cho game Keno (ID 33)

if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Bot Streamer");

    async function playKenoRound() {
        // 0. Tắt thông báo SweetAlert nếu có
        const swalBtn = document.querySelector('.swal2-confirm');
        if (swalBtn) {
            BotVirtualCursor.moveToElement($(swalBtn), 0.5, 0, () => {
                BotVirtualCursor.simulateClick(() => { swalBtn.click(); });
            });
            await new Promise(r => setTimeout(r, 800));
            return; 
        }

        const drawBtn = document.getElementById('drawBtn');
        // Tránh click lung tung khi bóng đang xổ (nút bị vô hiệu hóa và đang chưa có bảng kết quả)
        if (drawBtn && drawBtn.disabled && document.querySelectorAll('.keno-number.selected').length > 0 && !swalBtn) {
            return; 
        }

        // 1. Đổi vé số? (30% cơ hội bot xóa số cũ để đánh số mới, nếu không sẽ giữ nguyên số cũ như người thật)
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
                BotVirtualCursor.simulateClick(() => { drawBtn.click(); });
            });
        }
    }

    // Vòng lặp hành động chơi game
    function scheduleNextRound() {
        const waitTime = 4000 + Math.random() * 4000; // Đợi 4-8 giây sau khi ván trước kết thúc
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
