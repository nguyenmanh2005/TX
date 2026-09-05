/**
 * Bot AI Bàn 60 - Plinko Royale V3 Multi-Drop
 * Chiến thuật: Mạo hiểm cao, thả nhiều bóng, tìm JACKPOT x1000
 */
(function() {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot60] BotVirtualCursor chưa load, thử lại...');
        setTimeout(arguments.callee, 2000);
        return;
    }

    const bot = new BotVirtualCursor({ speed: 1.0, visible: true });

    // Vòng: đổi giữa HIGH, MEDIUM để tạo drama
    const STRATEGIES = [
        { risk: 'high',   rows: '16', balls: '10',  bet: 10000 },
        { risk: 'high',   rows: '16', balls: '25',  bet: 10000 },
        { risk: 'medium', rows: '12', balls: '10',  bet: 50000 },
        { risk: 'high',   rows: '16', balls: '50',  bet: 10000 },
        { risk: 'high',   rows: '16', balls: '10',  bet: 50000 },
        { risk: 'medium', rows: '16', balls: '25',  bet: 20000 },
    ];

    let stratIdx = 0;
    let isRunning = false;
    let cooldown  = false;

    async function doRound() {
        if (isRunning || cooldown) return;
        isRunning = true;
        cooldown  = true;

        const s = STRATEGIES[stratIdx % STRATEGIES.length];
        stratIdx++;

        try {
            // Chọn rows
            const rowBtn = document.querySelector(`#rowsCtrl .seg-btn[data-val="${s.rows}"]`);
            if (rowBtn) await bot.clickElement(rowBtn);

            await bot.wait(400);

            // Chọn risk
            const riskBtn = document.querySelector(`#riskCtrl .seg-btn[data-val="${s.risk}"]`);
            if (riskBtn) await bot.clickElement(riskBtn);

            await bot.wait(400);

            // Chọn số bóng
            const ballBtn = document.querySelector(`#ballsCtrl .seg-btn[data-val="${s.balls}"]`);
            if (ballBtn) await bot.clickElement(ballBtn);

            await bot.wait(300);

            // Đặt cược
            const betInput = document.getElementById('betAmt');
            if (betInput) {
                betInput.value = s.bet;
                betInput.dispatchEvent(new Event('input', { bubbles: true }));
            }

            await bot.wait(500);

            // Bấm THẢ BÓNG ROYALE
            const dropBtn = document.getElementById('dropBtn');
            if (dropBtn && !dropBtn.disabled) {
                await bot.clickElement(dropBtn);
                // Chờ animation nhiều bóng
                const waitTime = 1500 + (parseInt(s.balls) / 100) * 2000 + Math.random() * 1000;
                await bot.wait(waitTime);
            }

        } catch (e) {
            console.error('[Bot60] Lỗi:', e);
        }

        isRunning = false;
        const delay = 3500 + Math.random() * 3000;
        setTimeout(() => { cooldown = false; }, delay);
    }

    setTimeout(() => {
        setInterval(doRound, 1500);
        console.log('[Bot60] Plinko Royale V3 Bot khởi động!');
    }, 3000);

})();
