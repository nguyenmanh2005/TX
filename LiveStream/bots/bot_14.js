/**
 * bot_14.js — Bot Bờ Lách Jack Bàn 14 v2 (Form-Click)
 *
 * Game bàn 14 dùng PHP form POST → reload trang.
 * Bot đọc trạng thái từ DOM, click nút đúng, form submit → iframe reload → bot chạy lại.
 *
 * Không dùng AJAX loop phức tạp — đơn giản và cực kỳ ổn định.
 */

(function waitDeps() {
    if (typeof jQuery === "undefined" || typeof BotVirtualCursor === "undefined" || typeof gsap === "undefined") {
        setTimeout(waitDeps, 300);
        return;
    }
    startBot14();
})();

function startBot14() {
    BotVirtualCursor.init("Huyền Thoại Xì Dách ♠️🃏");

    // ─── ĐỌC TRẠNG THÁI TỪ DOM ───────────────────────────────────
    function getPlayerScore() {
        // <h3>🃏 Bài của bạn (11 điểm):</h3>
        const h3s = document.querySelectorAll('h3');
        for (const h of h3s) {
            const m = h.textContent.match(/\((\d+)\s*điểm\)/);
            if (m && h.textContent.includes('của bạn')) {
                return parseInt(m[1]);
            }
        }
        return null;
    }

    function getHitBtn() {
        return document.querySelector('button.bg-green-600')
            || Array.from(document.querySelectorAll('button[type="submit"]')).find(b => b.textContent.includes('Hit'));
    }

    function getStandBtn() {
        return document.querySelector('button.bg-red-600')
            || Array.from(document.querySelectorAll('button[type="submit"]')).find(b => b.textContent.includes('Stand'));
    }

    function getStartBtn() {
        return document.querySelector('button.bg-blue-600')
            || Array.from(document.querySelectorAll('button[type="submit"]')).find(b => b.textContent.includes('Bắt đầu'));
    }

    function getBetInput() {
        return document.querySelector('input[name="cuoc"]');
    }

    // ─── BASIC STRATEGY ──────────────────────────────────────────
    function shouldHit(score) {
        if (!score || score <= 11) return true;
        if (score >= 17) return false;
        // 12-16: mô phỏng người thật
        const p = { 12: 0.30, 13: 0.40, 14: 0.50, 15: 0.55, 16: 0.65 };
        return Math.random() < (p[score] || 0.45);
    }

    // ─── CLICK AN TOÀN QUA CURSOR ỬO ────────────────────────────
    function clickBtn(btn, cb) {
        if (!btn) { cb && cb(); return; }
        BotVirtualCursor.moveToElement($(btn), 0.35, 0, () => {
            BotVirtualCursor.simulateClick(() => {
                setTimeout(() => {
                    try { btn.click(); } catch(e) {}
                    cb && cb();
                }, 200);
            });
        });
    }

    // ─── CHIP / CỠ CƯỢC ──────────────────────────────────────────
    const BET_POOL = [10000, 50000, 100000, 200000, 500000, 1000000];
    let betIndex   = 1; // 50K mặc định
    let winStreak  = 0;
    let loseStreak = 0;

    function adjustBetFromResult() {
        // Đọc kết quả từ DOM (thẻ kết quả cuối trang)
        const result = document.querySelector('[class*="bg-green-5"], [class*="bg-red-5"], [class*="bg-yellow"]');
        if (!result) return;
        const txt = result.textContent.toLowerCase();
        if (txt.includes('nhận') || txt.includes('không thể tin')) {
            winStreak++; loseStreak = 0;
            if (winStreak >= 3) betIndex = Math.min(betIndex + 1, BET_POOL.length - 1);
            if (winStreak >= 6) { betIndex = 1; winStreak = 0; }
        } else if (txt.includes('thua') || txt.includes('chết') || txt.includes('nhà cái') || txt.includes('mất')) {
            loseStreak++; winStreak = 0;
            if (loseStreak === 2) betIndex = Math.min(betIndex + 1, Math.floor(BET_POOL.length * 0.7));
            if (loseStreak >= 4) { betIndex = Math.max(betIndex - 1, 0); loseStreak = 0; }
        }
    }

    // ─── LUỒNG CHƠI CHÍNH ────────────────────────────────────────
    function run() {
        const hitBtn   = getHitBtn();
        const standBtn = getStandBtn();
        const startBtn = getStartBtn();
        const betInput = getBetInput();

        // ── TRƯỜNG HỢP 1: ĐANG CHƠI (có nút Hit/Stand) ──
        if (hitBtn && standBtn) {
            const score = getPlayerScore();
            const doHit = shouldHit(score);

            const thinkTime = 1200 + Math.random() * 1800;
            setTimeout(() => {
                if (doHit) {
                    clickBtn(hitBtn, () => {
                        // Form submit → iframe reload → bot tự chạy lại
                        try { hitBtn.closest('form').submit(); } catch(e) {}
                    });
                } else {
                    clickBtn(standBtn, () => {
                        try { standBtn.closest('form').submit(); } catch(e) {}
                    });
                }
            }, thinkTime);
            return;
        }

        // ── TRƯỜNG HỢP 2: KẾT THÚC VÁN / CHƯA BẮT ĐẦU (có nút Start) ──
        if (startBtn && betInput && !startBtn.disabled) {
            adjustBetFromResult();
            const bet = BET_POOL[betIndex];

            const waitTime = 2500 + Math.random() * 3000;
            setTimeout(() => {
                // 1. Rê chuột đến ô nhập cược
                BotVirtualCursor.moveToElement($(betInput), 0.3, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        // 2. Điền số tiền cược
                        const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
                        setter.call(betInput, bet);
                        betInput.dispatchEvent(new Event('input', { bubbles: true }));
                        betInput.dispatchEvent(new Event('change', { bubbles: true }));

                        setTimeout(() => {
                            // 3. Rê chuột đến nút Bắt Đầu và click
                            clickBtn(startBtn, () => {
                                try { startBtn.closest('form').submit(); } catch(e) {}
                            });
                        }, 500 + Math.random() * 400);
                    });
                });
            }, waitTime);
            return;
        }

        // ── KHÔNG NHẬN RA TRẠNG THÁI → thử lại sau ──
        setTimeout(run, 1500);
    }

    // Khởi động sau 1.5s để page render xong
    setTimeout(run, 1500);
}
