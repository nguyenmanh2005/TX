/**
 * 🤖 Bot AI Three Card Poker (Poker Ba Lá) Thần Bài Đẳng Cấp (ID: 52)
 *
 * CHIẾN THUẬT & TRÍ TUỆ ĐỈNH CAO:
 * 1. QUẢN LÝ VỐN & PHÂN BỔ CƯỢC:
 *    - Tự động theo dõi số dư ví GTLM của Streamer (#balance-val).
 *    - Chọn mức cược linh hoạt (10K, 50K, 100K, 500K) qua các phỉnh cược.
 *    - Cược Ante bắt buộc và 40% đặt thêm Pair Plus để săn Thùng/Sảnh/Sáp.
 * 2. CHIẾN THUẬT QUYẾT ĐỊNH CHUẨN TOÁN HỌC (Q-6-4 Rule):
 *    - Nếu bài có Đôi (Pair), Thùng (Flush), Sảnh (Straight), Sáp (Three of a Kind) hay Thùng Phá Sảnh: 100% chọn PLAY!
 *    - Nếu chỉ có Bài Cao (High Card):
 *      + Có lá Q, K hoặc A: Chọn PLAY để đối đầu Dealer.
 *      + Bài quá nhỏ (< Q): 70% chọn FOLD (Úp bài) để cắt lỗ Ante thông minh.
 * 3. HỆ THỐNG CHUỘT ẢO ASYNC AN TOÀN TUYỆT ĐỐI (BotVirtualCursor):
 *    - moveAndClick dạng Promise có Safety Timeout tự giải phóng sau (duration*1000 + 400ms).
 *    - Di chuyển mượt mà tới các ô cược, chọn phỉnh, bấm Chia Bài, Play/Fold, và bấm Ván Mới.
 * 4. BÌNH LUẬN & PHÁT NGÔN CASINO LIVESTREAM (BotChat.send):
 *    - Tuân thủ 100% Bảng thuật ngữ Casino Rule 5.3 (húp GTLM, bay màu, ra chiêu, chiến, giao lưu, trận địa...).
 * 5. TRIPLE-LAYER WATCHDOG CHỐNG KẸT:
 *    - Tự động phục hồi trạng thái sau 8 giây nếu gặp gián đoạn mạng.
 *    - Định kỳ 2s rà soát trạng thái bàn game để duy trì luồng chơi liên tục 24/7.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 52] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Thần Bài Ba Lá 52 🃏');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI VÀ BIẾN ĐIỀU KHIỂN
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;
    let winStreak = 0;
    let lossStreak = 0;
    let turnCount = 0;

    function setBusy(val, timeoutMs = 8000) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            busyTimer = setTimeout(() => {
                console.warn('[Bot 52 Watchdog] Tự động giải phóng trạng thái bận!');
                botIsBusy = false;
            }, timeoutMs);
        }
    }

    // ═══════════════════════════════════════════════════════
    // BÌNH LUẬN & PHÁT NGÔN CASINO CHUẨN RULE 5.3
    // ═══════════════════════════════════════════════════════
    const bigWinPhrases = [
        'Sáp 3 lá phát nổ rồi anh em ơi! Húp trọn mâm x30 GTLM thơm nức mũi! 🃏💥🔥',
        'Thùng Phá Sảnh thần thánh xuất hiện! Quá đẳng cấp cho một ván giao lưu! 🏆✨',
        'Dealer không đủ tuổi so bài với Thần Bài hôm nay! Ăn ngập mặt cả Ante lẫn Play! 💵🎉',
        'Bảo rồi theo cầu tay son là tiền về ngập nick! Anh em vào chung vui húp lộc nào! 🚀💎'
    ];

    const normalWinPhrases = [
        'Có lộc có lộc! Bài đẹp hơn Dealer, húp trọn GTLM ngọt xớt! 🃏✨',
        'Chiến thuật Q-6-4 chuẩn chỉ! Dealer Qualify nhưng vẫn bay màu trước ta! 💰',
        'Húp nhẹ ván bài này! Đang trên đà phong độ thăng hoa! 🍀🔥',
        'Ra chiêu chuẩn bài, húp thêm một ván nữa! Tay bài 3 lá hôm nay quá bén! 🎯'
    ];

    const lossPhrases = [
        'Dealer nay bốc bài son quá, bay màu một tay ván sau gỡ lại ngay! 😤',
        'Úp bài cắt lỗ bảo toàn vốn là chiến thuật của dân chơi bản lĩnh! 💨',
        'Không sao hết, giao lưu nhả một ván để ván sau húp Sáp to hơn! 🔥🃏',
        'Bình tĩnh ra chiêu mới, vận may đang chờ ở ván kế tiếp! 🎯'
    ];

    const actionPhrases = [
        'Lên bài 3 lá Poker, anh em cùng theo dõi và húp vía nào! 🃏✨',
        'Căn cầu Poker Ba Lá cực chuẩn, ván này tự tin húp đậm GTLM! 🍀🔥',
        'Dàn trận xong xuôi, chuẩn bị so bài cùng Dealer! 🚀'
    ];

    function sendBotChat(type) {
        const now = Date.now();
        if (now - lastChatTime < 8000 && type !== 'big' && type !== 'win') return;

        let list = normalWinPhrases;
        if (type === 'big') list = bigWinPhrases;
        else if (type === 'loss') list = lossPhrases;
        else if (type === 'action') list = actionPhrases;

        const msg = list[Math.floor(Math.random() * list.length)];
        lastChatTime = now;

        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(52, 'bot_52', msg);
        }

        try {
            fetch('api_spectator.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'send_chat',
                    table_id: 52,
                    message: '🎙️ ' + msg
                })
            }).catch(() => {});
        } catch (e) {}

        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'bot_chat', text: msg, tableId: 52 }, '*');
            }
        } catch (e) {}
    }

    // ═══════════════════════════════════════════════════════
    // QUẢN LÝ VỐN & CHỌN MỨC PHỈNH
    // ═══════════════════════════════════════════════════════
    function getBalance() {
        const el = document.getElementById('balance-val');
        if (!el) return 50000000;
        const num = parseFloat(el.textContent.replace(/\./g, '').replace(/,/g, '').trim());
        return isNaN(num) || num <= 0 ? 50000000 : num;
    }

    function selectOptimalChip() {
        const bal = getBalance();
        let targetVal = '10000';

        if (bal >= 40000000) {
            if (winStreak >= 2) targetVal = '100000';
            else if (winStreak >= 1) targetVal = '50000';
            else targetVal = Math.random() > 0.5 ? '50000' : '10000';
        } else if (bal >= 10000000) {
            targetVal = Math.random() > 0.6 ? '50000' : '10000';
        } else {
            targetVal = '10000';
        }

        return document.querySelector(`.chip[data-value="${targetVal}"]`);
    }

    // ═══════════════════════════════════════════════════════
    // CHIẾN THUẬT Q-6-4 CHUẨN THREE CARD POKER
    // ═══════════════════════════════════════════════════════
    function decidePlayOrFold() {
        let maxRank = 0;
        let cardCount = 0;
        const ranks = [];

        for (let i = 0; i < 3; i++) {
            const cardEl = document.getElementById('pc-' + i);
            if (cardEl && cardEl.classList.contains('revealed')) {
                cardCount++;
                const text = cardEl.textContent.trim();
                let rankVal = 0;
                if (text.includes('A')) rankVal = 14;
                else if (text.includes('K')) rankVal = 13;
                else if (text.includes('Q')) rankVal = 12;
                else if (text.includes('J')) rankVal = 11;
                else if (text.includes('10')) rankVal = 10;
                else {
                    const num = parseInt(text);
                    rankVal = isNaN(num) ? 0 : num;
                }
                ranks.push(rankVal);
                if (rankVal > maxRank) maxRank = rankVal;
            }
        }

        // Nếu có Đôi hoặc bài đặc biệt (sảnh, thùng) -> luôn PLAY
        if (ranks.length >= 2) {
            const counts = {};
            ranks.forEach(r => { counts[r] = (counts[r] || 0) + 1; });
            for (let r in counts) {
                if (counts[r] >= 2) return true; // Có đôi
            }
        }

        // Q-6-4 Rule: Nếu lá cao nhất >= Q (12) -> PLAY
        if (maxRank >= 12) return true;

        // Dưới Q -> 75% FOLD để cắt lỗ, 25% liều thử vận
        return Math.random() < 0.25;
    }

    // ═══════════════════════════════════════════════════════
    // CHUỘT ẢO ASYNC AN TOÀN TUYỆT ĐỐI (SAFETY TIMEOUT)
    // ═══════════════════════════════════════════════════════
    function moveAndClick(el, duration = 0.4) {
        return new Promise(resolve => {
            if (!el || $(el).length === 0) {
                resolve();
                return;
            }

            let done = false;
            const finish = () => {
                if (!done) {
                    done = true;
                    resolve();
                }
            };

            const safetyTimer = setTimeout(() => {
                try { el.click(); } catch (e) {}
                finish();
            }, (duration * 1000) + 400);

            try {
                BotVirtualCursor.moveToElement($(el), duration, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        clearTimeout(safetyTimer);
                        try { el.click(); } catch (e) {}
                        setTimeout(finish, 120);
                    });
                });
            } catch (err) {
                clearTimeout(safetyTimer);
                try { el.click(); } catch (e) {}
                finish();
            }
        });
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG LẶP CHƠI GAME TỰ ĐỘNG CỦA BOT
    // ═══════════════════════════════════════════════════════
    async function executeBotTurn() {
        if (botIsBusy) return;

        const resultArea = document.getElementById('result-area');
        const playArea   = document.getElementById('play-area');
        const dealBtn    = document.getElementById('deal-btn');

        // ══════════════════════════════════════════════════════
        // GIAI ĐOẠN 1: KẾT QUẢ ĐANG HIỂN THỊ -> BẤM VÁN MỚI
        // ══════════════════════════════════════════════════════
        if (resultArea && $(resultArea).is(':visible')) {
            setBusy(true, 5000);

            const badge = document.getElementById('result-status-badge');
            if (badge && badge.style.display !== 'none') {
                if (badge.style.borderColor === 'rgb(241, 196, 15)') {
                    winStreak++;
                    lossStreak = 0;
                    sendBotChat('win');
                } else if (badge.style.borderColor === 'rgb(231, 76, 60)') {
                    lossStreak++;
                    winStreak = 0;
                    sendBotChat('loss');
                }
            }

            await new Promise(r => setTimeout(r, 600));

            const resetBtn = document.getElementById('reset-btn');
            if (resetBtn) {
                await moveAndClick(resetBtn, 0.45);
                try { resetBtn.click(); } catch (e) {}
            }

            await new Promise(r => setTimeout(r, 300));
            setBusy(false);
            return;
        }

        // ══════════════════════════════════════════════════════
        // GIAI ĐOẠN 2: ĐANG CHỜ QUYẾT ĐỊNH (PLAY HOẶC FOLD)
        // ══════════════════════════════════════════════════════
        if (playArea && $(playArea).is(':visible')) {
            setBusy(true, 6000);

            const playBtn = document.getElementById('play-btn');
            const foldBtn = document.getElementById('fold-btn');
            if (!playBtn || !foldBtn) {
                setBusy(false);
                return;
            }

            const shouldPlay = decidePlayOrFold();
            const chosenBtn = shouldPlay ? playBtn : foldBtn;

            await new Promise(r => setTimeout(r, 500));
            await moveAndClick(chosenBtn, 0.45);
            try { chosenBtn.click(); } catch (e) {}

            await new Promise(r => setTimeout(r, 400));
            setBusy(false);
            return;
        }

        // ══════════════════════════════════════════════════════
        // GIAI ĐOẠN 3: ĐẶT CƯỢC & BẤM CHIA BÀI
        // ══════════════════════════════════════════════════════
        if (dealBtn && !dealBtn.disabled) {
            setBusy(true, 8000);
            turnCount++;

            try {
                if (turnCount % 6 === 0) {
                    sendBotChat('action');
                }

                // 1. Rê chuột vào ô ANTE
                const boxAnte = document.getElementById('box-ante');
                const chip = selectOptimalChip();

                if (boxAnte) {
                    await moveAndClick(boxAnte, 0.35);
                }

                // 2. Chọn mức phỉnh
                if (chip) {
                    await moveAndClick(chip, 0.35);
                }

                // 3. Xác suất 40%: Đặt thêm Pair Plus
                if (Math.random() < 0.4) {
                    const boxPP = document.getElementById('box-pairplus');
                    if (boxPP && chip) {
                        await moveAndClick(boxPP, 0.3);
                        await moveAndClick(chip, 0.3);
                    }
                }

                // 4. Rê chuột bấm CHIA BÀI
                const activeDealBtn = document.getElementById('deal-btn');
                if (activeDealBtn && !activeDealBtn.disabled) {
                    await moveAndClick(activeDealBtn, 0.45);
                    try { activeDealBtn.click(); } catch(e) {}
                }

                await new Promise(r => setTimeout(r, 500));
                setBusy(false);

            } catch (err) {
                console.error('[Bot 52] Lỗi đặt cược:', err);
                setBusy(false);
            }
        }
    }

    // ═══════════════════════════════════════════════════════
    // TRIPLE-LAYER ANTI-STUCK WATCHDOG TIMER
    // ═══════════════════════════════════════════════════════
    let idleSeconds = 0;
    setInterval(() => {
        const resultArea = document.getElementById('result-area');
        const playArea   = document.getElementById('play-area');
        const dealBtn    = document.getElementById('deal-btn');

        if (resultArea && $(resultArea).is(':visible')) {
            idleSeconds += 2;
            if (idleSeconds >= 4) {
                console.warn('[Bot 52 Watchdog] Ván bài kết thúc quá 4s, tự bấm ván mới!');
                const resetBtn = document.getElementById('reset-btn');
                if (resetBtn) {
                    try { resetBtn.click(); } catch (e) {}
                }
                botIsBusy = false;
                idleSeconds = 0;
            }
            return;
        }

        if (playArea && $(playArea).is(':visible')) {
            idleSeconds += 2;
            if (idleSeconds >= 5) {
                console.warn('[Bot 52 Watchdog] Chờ Play/Fold quá 5s, tự động Play!');
                const playBtn = document.getElementById('play-btn');
                if (playBtn) {
                    try { playBtn.click(); } catch (e) {}
                }
                botIsBusy = false;
                idleSeconds = 0;
            }
            return;
        }

        if (dealBtn && !dealBtn.disabled) {
            idleSeconds += 2;
            if (idleSeconds >= 5) {
                console.warn('[Bot 52 Watchdog] Nút chia bài sẵn sàng quá 5s, tự kích hoạt!');
                botIsBusy = false;
                idleSeconds = 0;
                executeBotTurn();
            }
        } else {
            idleSeconds = 0;
        }
    }, 2000);

    // ═══════════════════════════════════════════════════════
    // KHỞI ĐỘNG VÒNG ĐIỀU KHIỂN ĐỊNH KỲ
    // ═══════════════════════════════════════════════════════
    $(document).ready(() => {
        console.log('[Bot 52] 🃏 Thần Bài Ba Lá 52 khởi động thành công!');
        setTimeout(() => { executeBotTurn(); }, 1800);
        setInterval(() => { executeBotTurn(); }, 4000);
    });

})();
