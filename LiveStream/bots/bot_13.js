/**
 * bot_13.js — Bot Blackjack Xì Dách Thông Minh v3
 * Khớp chính xác với live_13.php (PHP-based BJ):
 *   - #btn-start      → BẮT ĐẦU VÁN MỚI
 *   - #bet-amount     → input GTLM cược
 *   - .chip[data-value] → chọn chip
 *   - #action-buttons → vùng Hit/Stand (visible khi đang chơi)
 *   - #btn-hit        → Rút thêm
 *   - #btn-stand      → Dừng
 *   - #start-form-container → visible khi game_over
 *   - #player-score   → điểm người chơi
 */

(function waitDeps() {
    if (typeof jQuery === "undefined" || typeof BotVirtualCursor === "undefined" || typeof gsap === "undefined") {
        setTimeout(waitDeps, 300);
        return;
    }
    startBJBot13();
})();

function startBJBot13() {
    BotVirtualCursor.init("Thần Bài Xì Dách ♠️🃏");

    const CASINO_CHATS = {
        bet: [
            "Khai cuộc ván giao lưu Xì Dách! Ra chiêu phỉnh {chip} GTLM đọ tài nhà cái! ♠️🃏",
            "Chia bài nào! Vận đỏ đang lên, quyết tâm bắt trọn Xì Dách 21 điểm! 💎🔥",
            "Thế trận đang đẹp, vào tiền tự tin chiến thắng giòn giã! 🌟💰",
            "Tập trung đếm bài, ra chiêu chuẩn xác bảo toàn nick rước lộc! 🛡️♠️"
        ],
        hit: [
            "Điểm còn thấp, rút thêm lá bài quyết định vận mệnh! 🃏⚡",
            "Kéo thêm một lá nữa! Tự tin không sợ quắc! 💥💪"
        ],
        stand: [
            "Điểm đẹp rồi! Dằn bài khóa điểm chờ nhà cái quắc! 🛡️✋",
            "Đủ điểm húp rồi, dằn bài chờ lộc lá về nick! 💎✨"
        ],
        win: [
            "HÚP TRỌN VÁN XÌ DÁCH! Điểm số áp đảo nhà cái, GTLM về nick ngập tràn! 🏆♠️🤑",
            "Xì Dách / 21 điểm hoàn hảo! Gáy khét lẹt cả sòng bạc Hoàng Gia! 👑🔥💰",
            "Thần bài xuất chiêu chuẩn không cần chỉnh! Tiền đầy nick! 💎🎉"
        ],
        loss: [
            "Rút quá tay bị quắc rồi, bay màu ván giao lưu này! 😭💔",
            "Nhà cái hên quá, bốc hơi chút ít GTLM, ván sau dằn dơ phục thù! 💪🔄"
        ]
    };

    function sendChat(type, replaceObj = {}) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.bet;
            let msg = list[Math.floor(Math.random() * list.length)];
            for (let k in replaceObj) {
                msg = msg.replace(new RegExp(`\\{${k}\\}`, 'g'), replaceObj[k]);
            }
            BotChat.send(13, 'Thần Bài Xì Dách ♠️🃏', msg);
        }
    }

    // ─── CHIP LEVELS ─────────────────────────────────────────────
    const CHIP_VALUES = [10000, 50000, 100000, 500000, 1000000, 5000000];
    let chipIndex = 1;   // Mặc định 50K
    let winStreak = 0;
    let loseStreak = 0;
    let lastBalance = null;

    // ─── STATE ───────────────────────────────────────────────────
    let isBusy = false;
    let actionTaken = false;
    const T_THINK = () => 1200 + Math.random() * 1600;
    const T_POLL = 800;
    const T_BETWEEN = 3500 + Math.random() * 2000;

    // ─── HELPERS ─────────────────────────────────────────────────
    function isVisible(el) {
        if (!el) return false;
        if (el.classList && el.classList.contains('hidden')) return false;
        const s = window.getComputedStyle(el);
        return s.display !== 'none' && s.visibility !== 'hidden' && el.offsetParent !== null;
    }

    function readBalance() {
        const el = document.getElementById('balance-display')
            || document.querySelector('[id*="balance"]');
        if (!el) return null;
        return parseInt(el.textContent.replace(/[^0-9]/g, '')) || null;
    }

    function getPlayerScore() {
        const el = document.getElementById('player-score');
        if (el) return parseInt(el.textContent.trim()) || null;
        return null;
    }

    function isGameOver() {
        // game_over khi start-form-container hiện (không có class "hidden")
        const sf = document.getElementById('start-form-container');
        return sf && !sf.classList.contains('hidden');
    }

    function isPlaying() {
        // đang chơi khi action-buttons hiện
        const ab = document.getElementById('action-buttons');
        return ab && !ab.classList.contains('hidden') && isVisible(ab);
    }

    // ─── CHIẾN LƯỢC CƯỢC ─────────────────────────────────────────
    function updateBettingStrategy() {
        const cur = readBalance();
        if (lastBalance !== null && cur !== null) {
            if (cur > lastBalance) {
                winStreak++; loseStreak = 0;
                if (winStreak >= 3) chipIndex = Math.min(chipIndex + 1, CHIP_VALUES.length - 1);
                if (winStreak >= 6) { chipIndex = 1; winStreak = 0; }
                if (Math.random() < 0.6) sendChat('win');
            } else if (cur < lastBalance) {
                loseStreak++; winStreak = 0;
                if (loseStreak === 2) chipIndex = Math.min(chipIndex + 1, Math.floor(CHIP_VALUES.length * 0.6));
                if (loseStreak >= 4) { chipIndex = Math.max(chipIndex - 1, 0); loseStreak = 0; }
                if (Math.random() < 0.5) sendChat('loss');
            }
        }
        lastBalance = cur;
    }

    // ─── BASIC STRATEGY ──────────────────────────────────────────
    function shouldHit(score) {
        if (score === null) return true;
        if (score >= 17) return false;
        if (score <= 11) return true;
        // 12-16: hit nhẹ theo random (mô phỏng người thật)
        if (score === 16) return Math.random() < 0.6;
        if (score === 15) return Math.random() < 0.55;
        if (score === 14) return Math.random() < 0.50;
        if (score === 13) return Math.random() < 0.40;
        if (score === 12) return Math.random() < 0.30;
        return false;
    }

    // ─── CLICK AN TOÀN ───────────────────────────────────────────
    function safeClick(el, cb) {
        if (!el) { cb && cb(); return; }
        try {
            BotVirtualCursor.moveToElement($(el), 0.4, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    try { el.click(); } catch (e) { }
                    setTimeout(cb || function () { }, 350);
                });
            });
        } catch (e) {
            try { el.click(); } catch (e2) { }
            setTimeout(cb || function () { }, 350);
        }
    }

    // ─── PHASE: ĐẶT CƯỢC & BẮT ĐẦU VÁN ─────────────────────────
    function phaseStart() {
        if (isBusy) return;
        if (!isGameOver()) return;    // chỉ bắt đầu khi game_over = true

        isBusy = true;
        updateBettingStrategy();

        // Chờ suy nghĩ tự nhiên 2-5s
        setTimeout(() => {
            // 1. Chọn chip
            const chips = Array.from(document.querySelectorAll('.chip[data-value]'));
            const targetVal = CHIP_VALUES[chipIndex] || 50000;
            const targetChip = chips.find(c => parseInt(c.dataset.value) === targetVal)
                || chips[1]
                || chips[0];

            safeClick(targetChip, () => {
                setTimeout(() => {
                    // 2. Nhấn BẮT ĐẦU VÁN MỚI
                    const startBtn = document.getElementById('btn-start');
                    if (!startBtn || !isGameOver()) {
                        isBusy = false;
                        return;
                    }
                    safeClick(startBtn, () => {
                        actionTaken = false;
                        isBusy = false;
                        if (Math.random() < 0.35) sendChat('bet', { chip: targetVal.toLocaleString() });
                    });
                }, 600 + Math.random() * 400);
            });
        }, 2000 + Math.random() * 3000);
    }

    // ─── PHASE: HIT / STAND ──────────────────────────────────────
    function phasePlay() {
        if (isBusy) return;
        if (!isPlaying()) return;
        if (actionTaken) return;

        isBusy = true;
        actionTaken = true;

        setTimeout(() => {
            const score = getPlayerScore();
            const doHit = shouldHit(score);

            if (doHit) {
                const hitBtn = document.getElementById('btn-hit');
                safeClick(hitBtn, () => {
                    if (Math.random() < 0.25) sendChat('hit');
                    // Sau khi hit, có thể cần hit lại → reset actionTaken
                    setTimeout(() => {
                        actionTaken = false;
                        isBusy = false;
                    }, 800);
                });
            } else {
                const standBtn = document.getElementById('btn-stand');
                safeClick(standBtn, () => {
                    if (Math.random() < 0.25) sendChat('stand');
                    actionTaken = false;
                    isBusy = false;
                });
            }
        }, T_THINK());
    }

    // ─── VÒNG LẶP CHÍNH ──────────────────────────────────────────
    function gameLoop() {
        try {
            if (isGameOver()) {
                // Trạng thái: ván kết thúc → bắt đầu ván mới
                actionTaken = false;
                phaseStart();
            } else if (isPlaying()) {
                // Trạng thái: đang chơi → Hit/Stand
                phasePlay();
            }
            // Nếu đang animation hoặc chờ server → đợi poll sau
        } catch (e) {
            isBusy = false;
            actionTaken = false;
        }

        setTimeout(gameLoop, T_POLL);
    }

    // Khởi động sau 2s để page load xong
    lastBalance = readBalance();
    setTimeout(gameLoop, 2000);
}
