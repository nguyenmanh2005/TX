// LiveStream/bots/bot_34.js
// Kịch bản Bot cho game Let It Ride (ID 34)

if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Bot Streamer");

    async function playLetItRide() {
        // Xử lý nút VÁN MỚI
        const resetBtn = document.getElementById('reset-btn');
        const resultArea = document.getElementById('result-area');
        if (resetBtn && resultArea && resultArea.style.display !== 'none') {
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
                    BotVirtualCursor.simulateClick(() => { btn.click(); });
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
                    BotVirtualCursor.simulateClick(() => { btn.click(); });
                });
            }
            return;
        }

        // Xử lý BẮT ĐẦU VÁN (nút Deal)
        const dealBtn = document.getElementById('deal-btn');
        const betForm = document.getElementById('bet-form');
        if (dealBtn && betForm && betForm.style.display !== 'none') {
            // Thay đổi mức cược? (50% cơ hội đổi)
            if (Math.random() > 0.5) {
                const chips = Array.from(document.querySelectorAll('.chip')).filter(b => !b.innerText.includes('MAX') && !b.innerText.includes('5M'));
                if (chips.length > 0) {
                    const chipBtn = chips[Math.floor(Math.random() * chips.length)];
                    BotVirtualCursor.moveToElement($(chipBtn), 0.4, 0, () => {
                        BotVirtualCursor.simulateClick(() => { chipBtn.click(); });
                    });
                    await new Promise(r => setTimeout(r, 600)); // Đợi chip click
                }
            }
            BotVirtualCursor.moveToElement($(dealBtn), 0.7, 0, () => {
                BotVirtualCursor.simulateClick(() => { dealBtn.click(); });
            });
        }
    }

    function scheduleNextRound() {
        const waitTime = 2500 + Math.random() * 2000;
        setTimeout(() => {
            // Tắt SweetAlert nếu có
            const swalBtn = document.querySelector('.swal2-confirm');
            if (swalBtn) {
                BotVirtualCursor.moveToElement($(swalBtn), 0.5, 0, () => {
                    BotVirtualCursor.simulateClick(() => { swalBtn.click(); });
                });
                scheduleNextRound();
                return;
            }

            if (Math.random() > 0.1) { // 90% chơi tiếp
                playLetItRide();
            }
            
            scheduleNextRound();
        }, waitTime);
    }
    
    // Kích hoạt bot
    setTimeout(scheduleNextRound, 2000);
}
