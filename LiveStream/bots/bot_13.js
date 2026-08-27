/**
 * bot_bj.js — Bot Blackjack thông minh v2
 *
 * Cải tiến:
 *  - State Machine thực thụ: không click loạn, không double-action
 *  - isBusy flag chặn mọi hành động song song
 *  - Timing tự nhiên: suy nghĩ 1–3s trước mỗi quyết định
 *  - Basic Strategy chuẩn casino (Hard + Soft totals)
 *  - Martingale nhẹ: tăng cược sau thua, giảm sau thắng dài
 */

(function waitJQ() {
    if (typeof jQuery === "undefined" || typeof BotVirtualCursor === "undefined" || typeof gsap === "undefined") {
        setTimeout(waitJQ, 300);
        return;
    }
    startBJBot();
})();

function startBJBot() {
    BotVirtualCursor.init("Bot Streamer");

    // ─── STATE MACHINE ──────────────────────────────────────────
    // Phases: 'idle' → 'betting' → 'dealing' → 'playing' → 'result' → 'idle'
    let phase        = 'idle';    // trạng thái hiện tại
    let isBusy       = false;     // khóa chặn mọi hành động song song
    let actionTaken  = false;     // đã thực hiện action trong turn này chưa

    // ─── CHIP / BET STATE ───────────────────────────────────────
    const CHIP_LEVELS  = [10000, 50000, 100000, 500000, 1000000, 5000000, 10000000, 50000000, 100000000, 500000000];
    let chipIndex      = 1;       // 50K mặc định
    let winStreak      = 0;
    let loseStreak     = 0;
    let lastResultSeen = '';

    // ─── TIMING ─────────────────────────────────────────────────
    const T_THINK_MIN  = 1200;    // ms suy nghĩ tối thiểu
    const T_THINK_MAX  = 2800;    // ms suy nghĩ tối đa
    const T_BETWEEN    = 3000;    // ms nghỉ giữa các ván
    const T_POLL       = 800;     // ms polling

    function think() {
        return T_THINK_MIN + Math.random() * (T_THINK_MAX - T_THINK_MIN);
    }

    // ─── HELPERS ────────────────────────────────────────────────
    function log(msg) {
        // console.debug('[BJBot]', msg); // bật khi debug
    }

    function isVisible(el) {
        if (!el) return false;
        const style = window.getComputedStyle(el);
        return style.display !== 'none' && style.visibility !== 'hidden' && el.offsetParent !== null;
    }

    // ─── ĐỌC DỮ LIỆU GAME ──────────────────────────────────────
    function getPlayerScore() {
        if (typeof BlackjackLogic !== 'undefined' && BlackjackLogic.playerCards && BlackjackLogic.playerCards.length > 0) {
            return BlackjackLogic.calculateScore(BlackjackLogic.playerCards);
        }
        const el = document.getElementById('playerScore') || document.getElementById('player-score');
        if (el) {
            const v = parseInt(el.textContent.trim());
            if (!isNaN(v) && v > 0) return v;
        }
        return null;
    }

    function getDealerUpcard() {
        if (typeof BlackjackLogic !== 'undefined' && BlackjackLogic.kingCards && BlackjackLogic.kingCards.length > 0) {
            const v = BlackjackLogic.kingCards[0].value;
            if (v === 1) return 11;
            return v >= 10 ? 10 : v;
        }
        return null;
    }

    function getPlayerCards() {
        if (typeof BlackjackLogic !== 'undefined' && BlackjackLogic.playerCards && BlackjackLogic.playerCards.length > 0) {
            return BlackjackLogic.playerCards.map(c => {
                if (c.value === 1) return 11;
                if (c.value >= 10) return 10;
                return c.value;
            });
        }
        return [];
    }

    function isSoftTotal(cards) {
        let sum = 0, aces = 0;
        for (const c of cards) {
            if (c === 11) { aces++; sum += 11; }
            else sum += c;
        }
        while (sum > 21 && aces > 0) { sum -= 10; aces--; }
        return aces > 0; // còn ace đang tính là 11
    }

    // ─── BASIC STRATEGY CHUẨN CASINO ───────────────────────────
    function shouldDouble(playerScore, dealerUp, playerCards) {
        if (playerCards.length !== 2) return false;
        
        // Tỷ lệ random 20% tự động gấp đôi khi điểm từ 9-11 (Mô phỏng máu liều)
        if (playerScore >= 9 && playerScore <= 11 && Math.random() < 0.20) return true;
        
        // Basic Strategy Double
        if (playerScore === 11) return true;
        if (playerScore === 10 && dealerUp <= 9) return true;
        if (playerScore === 9 && dealerUp >= 3 && dealerUp <= 6) return true;
        
        const soft = isSoftTotal(playerCards);
        if (soft && playerScore >= 13 && playerScore <= 18 && dealerUp >= 4 && dealerUp <= 6) return true;
        
        return false;
    }

    function shouldHit(playerScore, dealerUp, playerCards) {
        if (playerScore === null) return true;
        if (dealerUp === null)   return playerScore < 17;

        const soft = isSoftTotal(playerCards);

        if (soft) {
            if (playerScore >= 19) return false;          // A,8+ → Stand
            if (playerScore === 18) return dealerUp >= 9; // A,7 → Hit vs 9,10,A
            return true;                                  // A,2–A,6 → luôn Hit
        } else {
            if (playerScore >= 17) return false;          // Hard 17+ → Stand
            if (playerScore <= 11) return true;           // Hard ≤11 → Hit
            if (playerScore === 12) return !(dealerUp >= 4 && dealerUp <= 6); // Stand vs 4-6
            return dealerUp >= 7;                         // Hard 13-16: Hit vs 7+
        }
    }

    // ─── CẬP NHẬT CHIẾN LƯỢC CƯỢC ──────────────────────────────
    function updateBettingStrategy() {
        let txt = '';

        if (typeof BlackjackLogic !== 'undefined') {
            const box = document.getElementById('resultAnnounce');
            if (box && isVisible(box)) txt = box.textContent || '';
        }
        if (!txt) {
            const box = document.getElementById('result-text');
            if (box) txt = box.textContent || '';
        }

        if (!txt || txt.trim() === lastResultSeen) return;
        lastResultSeen = txt.trim();

        const low = txt.toLowerCase();
        const isWin  = low.includes('win') || low.includes('royale') || low.includes('blackjack') || low.includes('draw');
        const isLose = low.includes('bust') || low.includes('king win') || low.includes('busted');

        if (isWin && !low.includes('draw')) {
            loseStreak = 0;
            winStreak++;
            if (winStreak >= 3) chipIndex = Math.min(chipIndex + 1, Math.floor(CHIP_LEVELS.length * 0.7)); // Tăng dần theo % mảng
            if (winStreak >= 6) { chipIndex = 1; winStreak = 0; }        // chốt lời
        } else if (isLose) {
            winStreak = 0;
            loseStreak++;
            if (loseStreak === 2) chipIndex = Math.min(chipIndex + 1, Math.floor(CHIP_LEVELS.length * 0.5)); // Martingale nhẹ
            if (loseStreak >= 4) { chipIndex = Math.max(chipIndex - 1, 1); loseStreak = 0; } // bảo vệ
        }
        // Hòa → giữ nguyên
        log(`Result: "${txt}" | chips[${chipIndex}]=${CHIP_LEVELS[chipIndex]} | W${winStreak} L${loseStreak}`);
    }

    // ─── CLICK AN TOÀN ──────────────────────────────────────────
    function safeClick(el, callback) {
        if (!el) { callback && callback(); return; }
        try {
            BotVirtualCursor.moveToElement($(el), 0.5, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    try { el.click(); } catch(e) {}
                    setTimeout(callback || function(){}, 300);
                });
            });
        } catch(e) {
            try { el.click(); } catch(e2) {}
            setTimeout(callback || function(){}, 300);
        }
    }

    // ─── PHASE: IDLE — Chọn chip & đặt cược ────────────────────
    function phaseIdle() {
        if (isBusy) return;

        // Kiểm tra dealBtn có hiện không
        const dealBtn = document.getElementById('dealBtn');
        if (!dealBtn || !isVisible(dealBtn)) return; // không phải lúc bắt đầu
        if (typeof BlackjackLogic !== 'undefined' && BlackjackLogic.isGameRunning) return;

        isBusy = true;
        phase  = 'betting';
        log('Phase: IDLE → BETTING');

        // Đọc kết quả ván trước
        updateBettingStrategy();

        // Chờ tự nhiên 1.5–4s trước khi đặt cược
        const waitTime = 1500 + Math.random() * 2500;
        setTimeout(() => {
            // Chọn chip
            const chips      = Array.from(document.querySelectorAll('.chip[data-value]'));
            const targetVal  = CHIP_LEVELS[chipIndex] || 50000;
            const targetChip = chips.find(c => parseInt(c.dataset.value) === targetVal)
                            || chips[1]
                            || chips[0];

            safeClick(targetChip, () => {
                setTimeout(() => {
                    // Nhấn KHAI CUỘC
                    const btn = document.getElementById('dealBtn');
                    if (btn && isVisible(btn) && typeof BlackjackLogic !== 'undefined' && !BlackjackLogic.isGameRunning) {
                        phase = 'dealing';
                        log('Phase: BETTING → DEALING (clicking dealBtn)');
                        safeClick(btn, () => {
                            phase   = 'playing';
                            actionTaken = false;
                            isBusy  = false;
                            log('Phase: DEALING → PLAYING');
                        });
                    } else {
                        // Không click được → reset
                        isBusy = false;
                        phase  = 'idle';
                    }
                }, 400 + Math.random() * 300);
            });
        }, waitTime);
    }

    // ─── PHASE: PLAYING — Hit / Stand ───────────────────────────
    function phasePlaying() {
        if (isBusy) return;

        // Kiểm tra gameActions có hiện không
        const gameActions = document.getElementById('gameActions');
        if (!gameActions || !isVisible(gameActions)) return;

        // Chỉ hành động 1 lần mỗi lượt
        if (actionTaken) return;

        // Kiểm tra BlackjackLogic đang chạy
        if (typeof BlackjackLogic !== 'undefined' && !BlackjackLogic.isGameRunning) return;

        isBusy      = true;
        actionTaken = true;
        log('Phase: PLAYING — thinking...');

        setTimeout(() => {
            // Đọc lại state sau khi "suy nghĩ"
            const playerScore = getPlayerScore();
            const dealerUp    = getDealerUpcard();
            const playerCards = getPlayerCards();
            
            let action = 'STAND';
            if (shouldDouble(playerScore, dealerUp, playerCards)) {
                action = 'DOUBLE';
            } else if (shouldHit(playerScore, dealerUp, playerCards)) {
                action = 'HIT';
            }

            log(`Score=${playerScore} DealerUp=${dealerUp} → ${action}`);

            if (action === 'DOUBLE') {
                const btn = document.getElementById('doubleBtn')
                         || document.getElementById('btn-double')
                         || document.querySelector('form input[name="action"][value="double"] ~ button')
                         || document.getElementById('hitBtn'); // fallback
                
                safeClick(btn, () => {
                    if (btn && (btn.id === 'doubleBtn' || btn.id === 'btn-double')) {
                        phase = 'result'; // Xong turn ngay sau khi double
                    }
                    actionTaken = false; 
                    isBusy      = false;
                });
            } else if (action === 'HIT') {
                const btn = document.getElementById('hitBtn')
                         || document.getElementById('btn-hit')
                         || document.querySelector('form input[name="action"][value="hit"] ~ button');
                safeClick(btn, () => {
                    actionTaken = false; // cho phép hành động tiếp sau khi rút bài
                    isBusy      = false;
                });
            } else {
                const btn = document.getElementById('standBtn')
                         || document.getElementById('btn-stand')
                         || document.querySelector('form input[name="action"][value="stand"] ~ button');
                safeClick(btn, () => {
                    phase       = 'result';
                    actionTaken = false;
                    isBusy      = false;
                    log('Phase: PLAYING → RESULT');
                });
            }
        }, think());
    }

    // ─── PHASE: RESULT — Chờ animation xong ─────────────────────
    function phaseResult() {
        // Khi gameActions ẩn và dealBtn hiện lại → ván kết thúc
        const gameActions = document.getElementById('gameActions');
        const dealBtn     = document.getElementById('dealBtn');

        const gameOver = (!gameActions || !isVisible(gameActions))
                      && dealBtn && isVisible(dealBtn);

        if (gameOver) {
            phase = 'idle';
            log('Phase: RESULT → IDLE (ván kết thúc)');
        }
    }

    // ─── VÒNG LẶP CHÍNH ─────────────────────────────────────────
    function gameLoop() {
        try {
            // Xác định blackjack 3D mode
            const is3D = typeof BlackjackLogic !== 'undefined';

            if (!is3D) {
                // Chế độ fallback (không có BlackjackLogic) — không làm gì
                setTimeout(gameLoop, T_POLL * 2);
                return;
            }

            switch (phase) {
                case 'idle':
                    phaseIdle();
                    break;
                case 'playing':
                    phasePlaying();
                    break;
                case 'result':
                    phaseResult();
                    break;
                // 'betting' và 'dealing' → đang xử lý async, không làm gì thêm
            }

        } catch(e) {
            // Recovery: reset về idle nếu có exception không lường
            isBusy      = false;
            actionTaken = false;
            phase       = 'idle';
        }

        setTimeout(gameLoop, T_POLL);
    }

    // Khởi động sau 2s để page load xong
    setTimeout(gameLoop, 2000);
}
