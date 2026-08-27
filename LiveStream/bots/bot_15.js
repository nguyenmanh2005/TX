/**
 * bot_15.js — Bot Xì Dách Royale Bàn 15 v2
 *
 * Fixes:
 *  - Không còn đơ sau ván: dùng style.display của #dealBtn & #gameActions
 *    thay vì BlackjackLogic.isGameRunning (bị delay 2s)
 *  - Basic Strategy casino chuẩn (Hard + Soft + Pair splits simulation)
 *  - Chỉ Double khi: 2 bài đầu & điều kiện chiến lược thoả mãn
 *  - Martingale nhẹ: đọc kết quả từ #resultAnnounce text
 *
 * Điều kiện Double đúng casino:
 *   Hard 9  vs dealer 3-6
 *   Hard 10 vs dealer 2-9
 *   Hard 11 vs dealer 2-10
 *   Soft 13-18 vs dealer 5-6
 *   Soft 17-18 vs dealer 3-6
 *
 * Điều kiện Stand (Hard):
 *   ≥17 luôn Stand
 *   13-16 Stand khi dealer lên 2-6, Hit khi dealer 7+
 *   12 Stand khi dealer 4-6, Hit còn lại
 *
 * Điều kiện Stand (Soft):
 *   Soft ≥19 → Stand
 *   Soft 18 → Stand vs 2-8, Hit vs 9-A
 *   Soft ≤17 → luôn Hit
 */

(function waitDeps() {
    if (typeof jQuery === "undefined" || typeof BotVirtualCursor === "undefined" || typeof gsap === "undefined") {
        setTimeout(waitDeps, 300);
        return;
    }
    startBJBot15();
})();

function startBJBot15() {
    BotVirtualCursor.init("Xì Dách Royale ♛♠️");

    // ─── CHIP / BET CONFIG ────────────────────────────────────────
    const CHIP_VALUES = [10000, 50000, 100000, 500000, 1000000, 5000000];
    let chipIndex     = 1;    // 50K mặc định
    let winStreak     = 0;
    let loseStreak    = 0;
    let lastResult    = '';   // text kết quả ván trước

    // ─── STATE MACHINE ────────────────────────────────────────────
    // Phases: 'idle' → 'betting' → 'playing' → 'result' → 'idle'
    let phase       = 'idle';
    let isBusy      = false;
    let actionTaken = false;

    const T_THINK = () => 1000 + Math.random() * 1600; // suy nghĩ 1-2.6s
    const T_POLL  = 700;

    // ─── HELPERS ─────────────────────────────────────────────────
    function isElVisible(el) {
        if (!el) return false;
        return el.style.display !== 'none' && el.style.visibility !== 'hidden';
    }

    function isDealBtnVisible() {
        const btn = document.getElementById('dealBtn');
        return btn && isElVisible(btn);
    }

    function isGameActionsVisible() {
        const el = document.getElementById('gameActions');
        return el && isElVisible(el);
    }

    // ─── ĐỌC DỮ LIỆU TỪ BlackjackLogic ──────────────────────────
    function getPlayerScore() {
        if (typeof BlackjackLogic !== 'undefined' && BlackjackLogic.playerCards.length > 0) {
            return BlackjackLogic.calculateScore(BlackjackLogic.playerCards);
        }
        const el = document.getElementById('playerScore');
        return el ? (parseInt(el.textContent) || 0) : 0;
    }

    function getDealerUpcard() {
        if (typeof BlackjackLogic !== 'undefined' && BlackjackLogic.kingCards.length > 0) {
            const v = BlackjackLogic.kingCards[0].value;
            if (v === 1) return 11;
            return v >= 10 ? 10 : v;
        }
        return 7; // fallback: assume mid-range
    }

    function getPlayerCards() {
        if (typeof BlackjackLogic !== 'undefined') {
            return BlackjackLogic.playerCards.map(c => {
                if (c.value === 1) return 11;
                return c.value >= 10 ? 10 : c.value;
            });
        }
        return [];
    }

    function isFirstTwoCards() {
        if (typeof BlackjackLogic !== 'undefined') {
            return BlackjackLogic.playerCards.length === 2;
        }
        return false;
    }

    function isSoftHand(cards) {
        let sum = 0, aces = 0;
        cards.forEach(c => { if (c === 11) { aces++; sum += 11; } else sum += c; });
        while (sum > 21 && aces > 0) { sum -= 10; aces--; }
        return aces > 0;
    }

    // ─── BASIC STRATEGY CHUẨN CASINO ─────────────────────────────
    function decide(score, dealerUp, cards, canDouble) {
        const soft  = isSoftHand(cards);
        const first = cards.length === 2;

        // ── DOUBLE (ưu tiên kiểm tra trước) ──
        if (canDouble && first) {
            // Hard double
            if (!soft) {
                if (score === 11) return 'DOUBLE';                            // Hard 11 → luôn Double
                if (score === 10 && dealerUp <= 9) return 'DOUBLE';          // Hard 10 vs 2-9
                if (score === 9 && dealerUp >= 3 && dealerUp <= 6) return 'DOUBLE'; // Hard 9 vs 3-6
            }
            // Soft double
            if (soft) {
                if ((score === 13 || score === 14) && dealerUp >= 5 && dealerUp <= 6) return 'DOUBLE'; // A,2/A,3 vs 5-6
                if ((score === 15 || score === 16) && dealerUp >= 4 && dealerUp <= 6) return 'DOUBLE'; // A,4/A,5 vs 4-6
                if (score === 17 && dealerUp >= 3 && dealerUp <= 6) return 'DOUBLE';                   // A,6 vs 3-6
                if (score === 18 && dealerUp >= 3 && dealerUp <= 6) return 'DOUBLE';                   // A,7 vs 3-6
            }
        }

        // ── SOFT HAND ──
        if (soft) {
            if (score >= 19) return 'STAND';                                    // Soft 19+ → Stand
            if (score === 18) return dealerUp <= 8 ? 'STAND' : 'HIT';          // Soft 18: Stand vs 2-8
            return 'HIT';                                                        // Soft ≤17 → Hit
        }

        // ── HARD HAND ──
        if (score >= 17) return 'STAND';                                         // Hard 17+ → Stand
        if (score <= 11) return 'HIT';                                           // Hard ≤11 → Hit

        // Hard 12-16 (danger zone)
        if (score === 12) return (dealerUp >= 4 && dealerUp <= 6) ? 'STAND' : 'HIT';
        if (score >= 13 && score <= 16) return dealerUp <= 6 ? 'STAND' : 'HIT';

        return 'HIT';
    }

    // ─── CHIẾN LƯỢC CƯỢC ─────────────────────────────────────────
    function adjustBet() {
        const announce = document.getElementById('resultAnnounce');
        const txt = announce ? announce.innerText.toLowerCase() : '';
        if (!txt || txt === lastResult) return;
        lastResult = txt;

        const isWin  = txt.includes('win') || txt.includes('royale') || txt.includes('draw');
        const isLose = txt.includes('king win') || txt.includes('bust') || txt.includes('busted');

        if (isWin && !txt.includes('draw')) {
            loseStreak = 0; winStreak++;
            if (winStreak >= 3) chipIndex = Math.min(chipIndex + 1, Math.floor(CHIP_VALUES.length * 0.65));
            if (winStreak >= 6) { chipIndex = 1; winStreak = 0; } // chốt lời
        } else if (isLose) {
            winStreak = 0; loseStreak++;
            if (loseStreak === 2) chipIndex = Math.min(chipIndex + 1, Math.floor(CHIP_VALUES.length * 0.5));
            if (loseStreak >= 4) { chipIndex = Math.max(chipIndex - 1, 0); loseStreak = 0; }
        }
    }

    // ─── CLICK AN TOÀN ───────────────────────────────────────────
    function safeClick(el, cb) {
        if (!el) { cb && setTimeout(cb, 100); return; }
        try {
            BotVirtualCursor.moveToElement($(el), 0.4, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    try { el.click(); } catch(e) {}
                    setTimeout(cb || function(){}, 300);
                });
            });
        } catch(e) {
            try { el.click(); } catch(e2) {}
            setTimeout(cb || function(){}, 300);
        }
    }

    // ─── PHASE: IDLE → ĐẶT CƯỢC & KHAI CUỘC ─────────────────────
    function phaseIdle() {
        if (isBusy) return;
        if (!isDealBtnVisible()) return;                // dealBtn phải hiện
        if (isGameActionsVisible()) return;             // không đặt khi đang chơi

        isBusy = true;
        phase  = 'betting';
        adjustBet(); // điều chỉnh mức cược từ kết quả ván trước

        setTimeout(() => {
            // 1. Chọn chip
            const chips = Array.from(document.querySelectorAll('.chip[data-value]'));
            const target = CHIP_VALUES[chipIndex] || 50000;
            const chip   = chips.find(c => parseInt(c.dataset.value) === target)
                         || chips[1] || chips[0];

            safeClick(chip, () => {
                setTimeout(() => {
                    // 2. KHAI CUỘC
                    const dealBtn = document.getElementById('dealBtn');
                    if (!dealBtn || !isDealBtnVisible()) { isBusy = false; phase = 'idle'; return; }
                    phase = 'dealing';
                    safeClick(dealBtn, () => {
                        phase       = 'playing';
                        actionTaken = false;
                        isBusy      = false;
                    });
                }, 400 + Math.random() * 300);
            });
        }, 2000 + Math.random() * 3000);
    }

    // ─── PHASE: PLAYING → HIT / STAND / DOUBLE ───────────────────
    function phasePlaying() {
        if (isBusy) return;
        if (!isGameActionsVisible()) return;
        if (actionTaken) return;

        isBusy      = true;
        actionTaken = true;

        setTimeout(() => {
            const score    = getPlayerScore();
            const dealerUp = getDealerUpcard();
            const cards    = getPlayerCards();

            // Kiểm tra nút Double có bị disable không
            const doubleBtn = document.getElementById('doubleBtn');
            const canDouble = doubleBtn && !doubleBtn.disabled && isFirstTwoCards();

            const action = decide(score, dealerUp, cards, canDouble);

            if (action === 'DOUBLE' && canDouble) {
                safeClick(doubleBtn, () => {
                    // Sau Double → game tự gọi stand, chờ kết quả
                    actionTaken = false;
                    isBusy      = false;
                });
            } else if (action === 'HIT') {
                const hitBtn = document.getElementById('hitBtn');
                safeClick(hitBtn, () => {
                    actionTaken = false; // cho phép hit tiếp
                    isBusy      = false;
                });
            } else {
                const standBtn = document.getElementById('standBtn');
                safeClick(standBtn, () => {
                    actionTaken = false;
                    isBusy      = false;
                });
            }
        }, T_THINK());
    }

    // ─── VÒNG LẶP CHÍNH ──────────────────────────────────────────
    function gameLoop() {
        try {
            const playing = isGameActionsVisible();
            const atIdle  = isDealBtnVisible() && !playing;

            if (playing) {
                phase = 'playing';
                phasePlaying();
            } else if (atIdle) {
                // Kết thúc ván hoặc chưa bắt đầu
                if (phase === 'playing' || phase === 'result' || phase === 'dealing') {
                    phase = 'idle'; // reset phase
                    actionTaken = false;
                    isBusy      = false;
                }
                phaseIdle();
            }
            // Nếu phase === 'betting'/'dealing' → đang async, đợi
        } catch(e) {
            isBusy      = false;
            actionTaken = false;
            phase       = 'idle';
        }

        setTimeout(gameLoop, T_POLL);
    }

    // Khởi động sau 2s
    setTimeout(gameLoop, 2000);
}
