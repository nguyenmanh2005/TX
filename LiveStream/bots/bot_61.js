/**
 * Bot AI Bàn 61 - Tháp Thần Bài (Tower of Gods)
 * Chiến thuật: Chọn nhân vật ngẫu nhiên, tự bấm Chiến, leo tháp liên tục
 */
(function() {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot61] BotVirtualCursor chưa load, thử lại...');
        setTimeout(arguments.callee, 2000);
        return;
    }

    const bot = new BotVirtualCursor({ speed: 1.0, visible: true });

    const CHARS = ['kiem_thanh', 'phap_su', 'cung_thu', 'ninja', 'bao_than', 'ma_kiem_si'];
    const BETS  = [10000, 20000, 50000, 100000, 10000, 10000, 50000];

    let charIdx = 0;
    let betIdx  = 0;
    let isRunning = false;
    let cooldown  = false;
    let currentFloor = 1;  // Track để đổi nhân vật khi reset

    // Khi thua (bay màu), đổi nhân vật
    function watchForFloorReset() {
        const floorEl = document.getElementById('floorNum');
        if (!floorEl) return;
        const observer = new MutationObserver(() => {
            const f = parseInt(floorEl.textContent) || 1;
            if (f < currentFloor && currentFloor > 2) {
                // Vừa reset → đổi nhân vật
                charIdx = (charIdx + 1) % CHARS.length;
                console.log('[Bot61] Reset! Đổi sang nhân vật:', CHARS[charIdx]);
            }
            currentFloor = f;
        });
        observer.observe(floorEl, { childList: true, subtree: true, characterData: true });
    }

    async function selectRandomChar() {
        const charKey = CHARS[charIdx % CHARS.length];
        const btn = document.querySelector(`#charRow .char-btn[data-char="${charKey}"]`);
        if (btn) await bot.clickElement(btn);
    }

    async function doRound() {
        if (isRunning || cooldown) return;
        isRunning = true;
        cooldown  = true;

        try {
            // Thỉnh thoảng đổi nhân vật
            if (Math.random() < 0.15) {
                charIdx = (charIdx + 1) % CHARS.length;
                await selectRandomChar();
                await bot.wait(400);
            } else {
                await selectRandomChar();
                await bot.wait(200);
            }

            // Đặt cược
            const bet = BETS[betIdx % BETS.length];
            betIdx++;
            const betInput = document.getElementById('betAmt');
            if (betInput) {
                betInput.value = bet;
                betInput.dispatchEvent(new Event('input', { bubbles: true }));
            }

            await bot.wait(500);

            // Bấm CHIẾN!
            const battleBtn = document.getElementById('battleBtn');
            if (battleBtn && !battleBtn.disabled) {
                await bot.clickElement(battleBtn);
                // Chờ animation vòng quay + kết quả
                await bot.wait(2500 + Math.random() * 1500);
            }

        } catch (e) {
            console.error('[Bot61] Lỗi:', e);
        }

        isRunning = false;
        // Cooldown 2.5-5s
        const delay = 2500 + Math.random() * 2500;
        setTimeout(() => { cooldown = false; }, delay);
    }

    setTimeout(() => {
        watchForFloorReset();
        setInterval(doRound, 1500);
        console.log('[Bot61] Tower of Gods Bot khởi động!');
    }, 3000);

})();
