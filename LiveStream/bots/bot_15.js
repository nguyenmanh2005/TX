/**
* bot_15.js — Bot Xì Dách Royale Bàn 15 v3 "Grand Master"
*
* Cải thiện so với v2:
*  ① Card Counting (Hi-Lo lite): theo dõi bài đã ra → biết khi nào bộ bài "nóng"
*  ② Adaptive Betting: kết hợp streak + balance thực + count
*  ③ Surrender simulation: Hard 16 vs 9/10/A → Stand (thay vì hit chết)
*  ④ Dealer Ace awareness: dealer A upcard → strategy thay đổi hẳn
*  ⑤ Pair split simulation: A,A → Double (vì không có split → xử lý như Soft 12)
*  ⑥ Timing thông minh: quyết định rõ ràng (≥17→stand) nhanh hơn, nghi ngờ (12-16) chậm hơn
*
* ════ FULL BASIC STRATEGY TABLE ════
*
*  HARD:
*   4-8  → Always Hit
*   9    → Double vs 3-6, else Hit
*   10   → Double vs 2-9, else Hit
*   11   → Double vs 2-10, vs A: Hit
*   12   → Stand vs 4-6, else Hit
*   13   → Stand vs 2-6, else Hit
*   14   → Stand vs 2-6, else Hit
*   15   → Stand vs 2-6, vs 10: Sur/Stand, else Hit
*   16   → Stand vs 2-6, vs 9/10/A: Sur/Stand, else Hit
*   17+  → Always Stand
*
*  SOFT:
*   13(A2)→ Double vs 5-6, else Hit
*   14(A3)→ Double vs 5-6, else Hit
*   15(A4)→ Double vs 4-6, else Hit
*   16(A5)→ Double vs 4-6, else Hit
*   17(A6)→ Double vs 3-6, else Hit
*   18(A7)→ Double vs 3-6, Stand vs 2/7/8, Hit vs 9/10/A
*   19+  → Always Stand
*/

(function waitDeps() {
    if (typeof jQuery === "undefined" || typeof BotVirtualCursor === "undefined" || typeof gsap === "undefined") {
        setTimeout(waitDeps, 300);
        return;
    }
    startBJBot15();
})();

function startBJBot15() {
    BotVirtualCursor.init("Grand Master ♛♠️🃏");

    // ─── BETTING CONFIG ───────────────────────────────────────────
    const BET_UNIT = 50000;  // Đơn vị cơ bản
    const MAX_BET_RATIO = 0.02; // Max 2% balance mỗi ván
    let winStreak = 0;
    let loseStreak = 0;
    let lastResult = '';
    let sessionProfit = 0; // P&L phiên này (để biết khi nào chốt lời)

    // ─── HI-LO CARD COUNTING ────────────────────────────────────
    // +1 = bài thấp (2-6), 0 = trung (7-9), -1 = bài cao (10,A)
    let runningCount = 0;
    const seenCards = new Set(); // tránh đếm lại

    function countCard(card) {
        if (!card) return;
        const key = `${card.value}_${card.suit}`;
        if (seenCards.has(key)) return;
        seenCards.add(key);
        const v = card.value;
        if (v >= 2 && v <= 6) runningCount++;
        else if (v === 1 || v >= 10) runningCount--;
        // 7-9: neutral, không đếm
    }

    function updateCount() {
        if (typeof BlackjackLogic === 'undefined') return;
        [...(BlackjackLogic.playerCards || []), ...(BlackjackLogic.kingCards || [])].forEach(countCard);
    }

    // runningCount > 0: bộ bài nhiều bài thấp → dealer dễ bust → aggressive
    // runningCount < 0: bộ bài nhiều bài cao → player dễ bust → conservative
    function getCountAdvantage() {
        // Normalise: -1 đến +1
        return Math.max(-1, Math.min(1, runningCount / 10));
    }

    // ─── STATE MACHINE ────────────────────────────────────────────
    let phase = 'idle';
    let isBusy = false;
    let actionTaken = false;

    // Timing: quyết định rõ ràng nhanh hơn, phức tạp chậm hơn
    function thinkTime(difficulty) {
        const base = { easy: 600, medium: 1200, hard: 2000 };
        return (base[difficulty] || 1200) + Math.random() * 800;
    }
    const T_POLL = 650;

    // ─── HELPERS ─────────────────────────────────────────────────
    function isElVisible(el) {
        if (!el) return false;
        return el.style.display !== 'none' && el.style.visibility !== 'hidden';
    }
    function isDealBtnVisible() { const b = document.getElementById('dealBtn'); return b && isElVisible(b); }
    function isGameActionsVisible() { const e = document.getElementById('gameActions'); return e && isElVisible(e); }

    // ─── ĐỌC DỮ LIỆU ─────────────────────────────────────────────
    function getPlayerScore() {
        if (typeof BlackjackLogic !== 'undefined' && BlackjackLogic.playerCards.length > 0)
            return BlackjackLogic.calculateScore(BlackjackLogic.playerCards);
        return parseInt(document.getElementById('playerScore')?.textContent || '0') || 0;
    }

    function getDealerUpcard() {
        if (typeof BlackjackLogic !== 'undefined' && BlackjackLogic.kingCards.length > 0) {
            const v = BlackjackLogic.kingCards[0].value;
            return v === 1 ? 11 : (v >= 10 ? 10 : v);
        }
        return 7;
    }

    function isDealerAce() {
        if (typeof BlackjackLogic !== 'undefined' && BlackjackLogic.kingCards.length > 0)
            return BlackjackLogic.kingCards[0].value === 1;
        return false;
    }

    function getPlayerCards() {
        if (typeof BlackjackLogic !== 'undefined')
            return BlackjackLogic.playerCards.map(c => c.value === 1 ? 11 : (c.value >= 10 ? 10 : c.value));
        return [];
    }

    function getPlayerCardCount() {
        return typeof BlackjackLogic !== 'undefined' ? BlackjackLogic.playerCards.length : 0;
    }

    function isSoftHand(cards) {
        let sum = 0, aces = 0;
        cards.forEach(c => { if (c === 11) { aces++; sum += 11; } else sum += c; });
        while (sum > 21 && aces > 0) { sum -= 10; aces--; }
        return aces > 0;
    }

    function isPair() {
        const cards = typeof BlackjackLogic !== 'undefined' ? BlackjackLogic.playerCards : [];
        if (cards.length !== 2) return false;
        const v0 = cards[0].value >= 10 ? 10 : cards[0].value;
        const v1 = cards[1].value >= 10 ? 10 : cards[1].value;
        return v0 === v1;
    }

    function getBalance() {
        const el = document.getElementById('userBalance');
        if (!el) return 5000000;
        return parseInt(el.textContent.replace(/[^0-9]/g, '')) || 5000000;
    }

    // ─── FULL BASIC STRATEGY ─────────────────────────────────────
    function decide(score, dealerUp, cards, canDouble) {
        const soft = isSoftHand(cards);
        const nCards = cards.length;
        const first = nCards === 2;
        const dealerAce = isDealerAce();
        const countAdv = getCountAdvantage();

        // ─ PAIR HANDLING (simulate split) ─
        if (first && isPair()) {
            const cardVal = cards[0]; // đã normalise
            if (cardVal === 11 || cardVal === 1) {
                // A,A: Treat as soft 12, always hit (nếu có double thì double)
                if (canDouble) return 'DOUBLE'; // best we can do without split
                return 'HIT';
            }
            // 8,8 → Hard 16: surrender simulation (stand vs 9/10/A, hit 7, stand 2-6)
            if (cardVal === 8) {
                if (dealerUp >= 9) return 'STAND'; // surrender → stand
                return dealerUp <= 6 ? 'STAND' : 'HIT';
            }
        }

        // ─ SOFT HAND ─
        if (soft) {
            if (score >= 20) return 'STAND'; // Soft 20+ (A,9 or A,A,8...)

            if (score === 19) {
                // A,8: Double vs 6 nếu count dương (dealer yếu)
                if (canDouble && first && dealerUp === 6 && countAdv > 0.3) return 'DOUBLE';
                return 'STAND';
            }

            if (score === 18) { // A,7
                if (canDouble && first && dealerUp >= 3 && dealerUp <= 6) return 'DOUBLE';
                if (dealerUp <= 8) return 'STAND';
                return 'HIT'; // vs 9, 10, A
            }

            if (score === 17) { // A,6
                if (canDouble && first && dealerUp >= 3 && dealerUp <= 6) return 'DOUBLE';
                return 'HIT';
            }

            if (score === 16) { // A,5
                if (canDouble && first && dealerUp >= 4 && dealerUp <= 6) return 'DOUBLE';
                return 'HIT';
            }

            if (score === 15) { // A,4
                if (canDouble && first && dealerUp >= 4 && dealerUp <= 6) return 'DOUBLE';
                return 'HIT';
            }

            if (score === 14) { // A,3
                if (canDouble && first && dealerUp >= 5 && dealerUp <= 6) return 'DOUBLE';
                return 'HIT';
            }

            if (score === 13) { // A,2
                if (canDouble && first && dealerUp >= 5 && dealerUp <= 6) return 'DOUBLE';
                return 'HIT';
            }

            return 'HIT'; // Soft ≤12
        }

        // ─ HARD HAND ─
        if (score >= 17) return 'STAND';

        if (score === 16) {
            // Surrender simulation: vs 9, 10, A → Stand (cut loss)
            // Count bonus: nếu count âm nhiều (bài cao) → stand thêm
            if (dealerUp >= 9) return 'STAND';
            if (dealerUp <= 6) return 'STAND';
            return 'HIT'; // vs 7, 8
        }

        if (score === 15) {
            if (dealerAce) return 'HIT';
            if (dealerUp === 10) return 'STAND'; // surrender → stand
            if (dealerUp <= 6) return 'STAND';
            return 'HIT';
        }

        if (score === 14) return dealerUp <= 6 ? 'STAND' : 'HIT';
        if (score === 13) return dealerUp <= 6 ? 'STAND' : 'HIT';

        if (score === 12) {
            // Hard 12: hơi phức tạp
            if (dealerUp >= 4 && dealerUp <= 6) return 'STAND';
            // Count bonus: bộ bài nhiều bài thấp → hit an toàn hơn
            return 'HIT';
        }

        if (score === 11) {
            if (canDouble && first) {
                if (dealerAce) return 'HIT'; // Hard 11 vs A: Hit (không double)
                return 'DOUBLE'; // vs 2-10: Double
            }
            return 'HIT';
        }

        if (score === 10) {
            if (canDouble && first && dealerUp <= 9 && !dealerAce) return 'DOUBLE';
            return 'HIT';
        }

        if (score === 9) {
            if (canDouble && first && dealerUp >= 3 && dealerUp <= 6) return 'DOUBLE';
            return 'HIT';
        }

        return 'HIT'; // 4-8: luôn hit
    }

    // ─── TÍNH MỨC CƯỢC THÔNG MINH ───────────────────────────────
    function calcBetAmount() {
        const balance = getBalance();
        const countAdv = getCountAdvantage();

        // Base bet
        let bet = BET_UNIT;

        // Streak adjustment
        if (winStreak >= 3) bet = BET_UNIT * (1 + winStreak * 0.5);
        if (loseStreak >= 2) bet = BET_UNIT * Math.max(1.5, loseStreak); // Martingale nhẹ

        // Count bonus: count dương → tăng thêm, âm → giảm
        if (countAdv > 0.4) bet *= 1.5;
        if (countAdv > 0.7) bet *= 2;
        if (countAdv < -0.4) bet = BET_UNIT; // reset về cơ bản khi count xấu

        // Safety cap: không quá 2% balance
        const maxBet = Math.max(BET_UNIT, balance * MAX_BET_RATIO);
        bet = Math.min(bet, maxBet);

        // Chốt lời: nếu đang lãi nhiều → giảm bet
        if (sessionProfit > balance * 0.15) bet = BET_UNIT;

        // Tìm chip gần nhất
        const CHIP_VALUES = [10000, 50000, 100000, 500000, 1000000, 5000000];
        let best = CHIP_VALUES[0];
        for (const cv of CHIP_VALUES) {
            if (cv <= bet && cv <= balance * 0.05) best = cv;
        }
        return best;
    }

    // ─── ĐỌC KẾT QUẢ VÁN ─────────────────────────────────────────
    function parseLastResult() {
        const announce = document.getElementById('resultAnnounce');
        const txt = announce ? announce.innerText.toLowerCase() : '';
        if (!txt || txt === lastResult) return null;
        lastResult = txt;

        if (txt.includes('royale') || txt.includes('challenger win')) return 'WIN';
        if (txt.includes('draw')) return 'PUSH';
        if (txt.includes('king win') || txt.includes('bust')) return 'LOSE';
        return null;
    }

    function adjustAfterResult() {
        const result = parseLastResult();
        if (!result) return;

        if (result === 'WIN') {
            loseStreak = 0; winStreak++;
            sessionProfit += 50000; // ước tính (không biết exact payout ở đây)
        } else if (result === 'LOSE') {
            winStreak = 0; loseStreak++;
            sessionProfit -= 50000;
        } else {
            // PUSH: giữ nguyên streak
        }

        // Reset count khi shuffle (heuristic: count quá cao/thấp hoặc đã nhiều ván)
        if (Math.abs(runningCount) > 20) {
            runningCount = 0;
            seenCards.clear();
        }
    }

    // ─── CLICK AN TOÀN ───────────────────────────────────────────
    function safeClick(el, cb) {
        if (!el) { cb && setTimeout(cb, 100); return; }
        try {
            BotVirtualCursor.moveToElement($(el), 0.4, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    try { el.click(); } catch (e) { }
                    setTimeout(cb || function () { }, 300);
                });
            });
        } catch (e) {
            try { el.click(); } catch (e2) { }
            setTimeout(cb || function () { }, 300);
        }
    }

    // ─── PHASE: IDLE → ĐẶT CƯỢC ──────────────────────────────────
    function phaseIdle() {
        if (isBusy) return;
        if (!isDealBtnVisible()) return;
        if (isGameActionsVisible()) return;

        isBusy = true;
        phase = 'betting';
        adjustAfterResult();

        const betAmount = calcBetAmount();

        // Delay tự nhiên: 2-4s (quan sát, suy nghĩ)
        setTimeout(() => {
            // 1. Chọn chip khớp với bet amount
            const chips = Array.from(document.querySelectorAll('.chip[data-value]'));
            const chip = chips.find(c => parseInt(c.dataset.value) === betAmount)
                || chips[1] || chips[0];

            safeClick(chip, () => {
                setTimeout(() => {
                    // 2. Khai cuộc
                    const dealBtn = document.getElementById('dealBtn');
                    if (!dealBtn || !isDealBtnVisible()) { isBusy = false; phase = 'idle'; return; }
                    phase = 'dealing';
                    safeClick(dealBtn, () => {
                        phase = 'playing';
                        actionTaken = false;
                        isBusy = false;
                        // Đếm bài sau khi bài được chia
                        setTimeout(updateCount, 1500);
                    });
                }, 350 + Math.random() * 300);
            });
        }, 2000 + Math.random() * 2000);
    }

    // ─── PHASE: PLAYING → HIT / STAND / DOUBLE ───────────────────
    function phasePlaying() {
        if (isBusy) return;
        if (!isGameActionsVisible()) return;
        if (actionTaken) return;

        isBusy = true;
        actionTaken = true;

        // Cập nhật count trước khi quyết định
        updateCount();

        const score = getPlayerScore();
        const dealerUp = getDealerUpcard();
        const cards = getPlayerCards();
        const nCards = getPlayerCardCount();

        const doubleBtn = document.getElementById('doubleBtn');
        const canDouble = doubleBtn && !doubleBtn.disabled && nCards === 2;

        const action = decide(score, dealerUp, cards, canDouble);

        // Timing thông minh: quyết định dễ → nhanh, khó → suy nghĩ lâu
        let difficulty = 'easy';
        if (score >= 12 && score <= 16) difficulty = 'hard';       // danger zone
        else if (isSoftHand(cards) && score === 18) difficulty = 'hard'; // soft 18 phức tạp
        else if (action === 'DOUBLE') difficulty = 'medium';        // double cần cân nhắc

        setTimeout(() => {
            if (action === 'DOUBLE' && canDouble) {
                safeClick(doubleBtn, () => {
                    updateCount();
                    actionTaken = false;
                    isBusy = false;
                });

            } else if (action === 'HIT') {
                const hitBtn = document.getElementById('hitBtn');
                safeClick(hitBtn, () => {
                    // Cập nhật count sau khi nhận bài mới
                    setTimeout(updateCount, 600);
                    actionTaken = false; // cho hit tiếp
                    isBusy = false;
                });

            } else {
                const standBtn = document.getElementById('standBtn');
                safeClick(standBtn, () => {
                    actionTaken = false;
                    isBusy = false;
                });
            }
        }, thinkTime(difficulty));
    }

    // ─── VÒNG LẶP CHÍNH ──────────────────────────────────────────
    function gameLoop() {
        try {
            const playing = isGameActionsVisible();
            const atIdle = isDealBtnVisible() && !playing;

            if (playing) {
                phase = 'playing';
                phasePlaying();
            } else if (atIdle) {
                if (phase === 'playing' || phase === 'result' || phase === 'dealing') {
                    phase = 'idle';
                    actionTaken = false;
                    isBusy = false;
                }
                phaseIdle();
            }
        } catch (e) {
            isBusy = false;
            actionTaken = false;
            phase = 'idle';
        }

        setTimeout(gameLoop, T_POLL);
    }

    // Khởi động sau 2s
    setTimeout(gameLoop, 2000);
}
