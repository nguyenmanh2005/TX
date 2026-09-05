/**
 * Bot AI Bàn 59 - Plinko V2 Pro
 * Chiến thuật: Random Risk, thay đổi Rows và số bóng theo chiến lược
 */
(function() {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot59] BotVirtualCursor chưa load, thử lại sau 2s...');
        setTimeout(arguments.callee, 2000);
        return;
    }

    const bot = new BotVirtualCursor({ speed: 1.1, visible: true });

    // Chiến lược risk theo chuỗi
    const RISKS    = ['low', 'medium', 'medium', 'high', 'medium'];
    const ROWS_SEL = ['8', '12', '12', '16', '12'];
    const BALLS    = [1, 5, 10, 5, 1];

    let step = 0;
    let isRunning = false;
    let cooldown  = false;

    function pickRisk() {
        const i = step % RISKS.length;
        return { risk: RISKS[i], rows: ROWS_SEL[i], balls: BALLS[i] };
    }

    async function doRound() {
        if (isRunning || cooldown) return;
        isRunning = true;
        cooldown  = true;

        const cfg = pickRisk();

        try {
            // Chọn risk
            const riskBtn = document.querySelector(`#riskCtrl .seg-btn[data-val="${cfg.risk}"]`);
            if (riskBtn) await bot.clickElement(riskBtn);

            // Chọn rows
            const rowBtn = document.querySelector(`#rowsCtrl .seg-btn[data-val="${cfg.rows}"]`);
            if (rowBtn) await bot.clickElement(rowBtn);

            // Chọn số bóng
            const ballBtn = document.querySelector(`#ballsCtrl .seg-btn[data-val="${cfg.balls}"]`);
            if (ballBtn) await bot.clickElement(ballBtn);

            // Đặt cược ngẫu nhiên 10K - 100K
            const bets = [10000, 20000, 50000, 100000, 10000, 50000];
            const bet = bets[Math.floor(Math.random() * bets.length)];
            const betInput = document.getElementById('betAmt');
            if (betInput) {
                betInput.value = bet;
                betInput.dispatchEvent(new Event('input', { bubbles: true }));
            }

            await bot.wait(600);

            // Bấm THẢ BÓNG
            const dropBtn = document.getElementById('dropBtn');
            if (dropBtn && !dropBtn.disabled) {
                await bot.clickElement(dropBtn);
                step++;
                // Chờ animation xong (khoảng 1.5s) + cooldown
                await bot.wait(2000 + Math.random() * 1500);
            }
        } catch (e) {
            console.error('[Bot59] Lỗi round:', e);
        }

        isRunning = false;

        // Cooldown ngẫu nhiên 3-7s
        const delay = 3000 + Math.random() * 4000;
        setTimeout(() => { cooldown = false; }, delay);
    }

    // Khởi động sau 3s
    setTimeout(() => {
        setInterval(doRound, 1500);
        console.log('[Bot59] Plinko V2 Bot khởi động!');
    }, 3000);

})();
