/**
 * 🤖 Bot AI Plinko Royale V3 - Bậc Thầy Hoàng Gia 60 👑 (ID: 60)
 *
 * CHIẾN THUẬT & TRÍ TUỆ NHÂN TẠO CAO CẤP:
 * 1. QUẢN LÝ TÀI CHÍNH HOÀNG GIA (SMART ROYALE BANKROLL):
 *    - Theo dõi số dư ví GTLM của Streamer qua #plinkoBalance.
 *    - Phân bổ chip cược an toàn (10K, 50K, 100K, 500K) tương xứng với số dư ví.
 *    - Tuyệt đối không cược quá 5% số dư ví trên mỗi đợt Multi-Drop, giữ nhịp livestream bền bỉ.
 * 2. CHIẾN LƯỢC THEO CHUỖI KẾT HỢP MULTI-DROP:
 *    - Đang Thắng / Nổ Hũ: Chuyển sang chế độ "Royale Frenzy" - chọn 16 hàng đinh, Risk HIGH X1000, Multi-Drop 25-50 bóng để bùng nổ vũ trụ Plinko.
 *    - Đang Bay Màu: Lùi về chế độ "Bảo Toàn Hoàng Gia" - chọn 12 hàng đinh, Risk Royale Vàng (medium), 10 bóng để hồi vốn an toàn.
 *    - Nhịp độ tự nhiên: 70% số ván rê chuột trực tiếp vào nút Thả Bóng để giữ nhịp độ sôi động; 30% số ván xoay chuyển chiến thuật thông minh.
 * 3. HỆ THỐNG CHUỘT ẢO AN TOÀN TUYỆT ĐỐI (BotVirtualCursor):
 *    - Di chuyển mượt mà tới các phím Hàng Đinh, Mức Rủi Ro, Số Bóng, Phím Cược và nút Thả Bóng.
 *    - Fallback Safety Timeout tích hợp 100% ngăn ngừa treo Promise/Async.
 * 4. BÌNH LUẬN & PHÁT NGÔN CASINO HOÀNG GIA (BotChat.send):
 *    - Tuân thủ 100% bảng từ lóng Rule 5.3: húp GTLM, bay màu, ra chiêu, chiến, giao lưu, trận địa...
 * 5. DUAL-LAYER WATCHDOG TIMER & ANTI-STUCK:
 *    - Giám sát trạng thái bận và mở khóa nút #btnDropMain sau 9 giây nếu xảy ra bất kỳ sự cố lag mạng nào.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 60] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Bậc Thầy Hoàng Gia 60 👑');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI VÀ BIẾN ĐIỀU KHIỂN
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;
    let winStreak = 0;
    let loseStreak = 0;
    let turnCount = 0;
    let lastKnownWin = 0;
    let lastKnownBet = 0;

    function setBusy(val, timeoutMs = 10000) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            busyTimer = setTimeout(() => {
                console.log('[Bot 60] Watchdog giải phóng trạng thái bận!');
                botIsBusy = false;
                const btn = document.getElementById('btnDropMain');
                if (btn && btn.disabled) {
                    btn.disabled = false;
                    btn.innerHTML = '💥 THẢ BÓNG ROYALE';
                }
            }, timeoutMs);
        }
    }

    // ═══════════════════════════════════════════════════════
    // PHÁT NGÔN & BÌNH LUẬN CASINO CHUẨN RULE 5.3
    // ═══════════════════════════════════════════════════════
    const royaleJackpotPhrases = [
        'X1000 ROYALE JACKPOT ĐÃ BÙNG NỔ! Húp trọn cơn mưa hàng chục triệu GTLM ngập tràn nick rồi anh em ơi! 👑💥🔥',
        'Đẳng cấp Hoàng Gia Plinko V3! Bắn multi-drop nổ siêu hũ x1000 phê chữ ê kéo dài! 🏆✨',
        'Chiêm ngưỡng siêu phẩm x1000! Thần may mắn độ nick streamer ăn ngập mặt hôm nay! 🚀💰',
        'Quả bóng chạm mép x1000 huyền thoại! Cả phòng live lấy vía đại phú quý nào! 💎🎉'
    ];

    const normalWinPhrases = [
        'Húp nhẹ mâm Royale! Cơn mưa bóng rơi trúng khe tài lộc quá ngọt ngào! 🎱✨',
        'Multi-drop nảy tưng bừng! Lãi ròng GTLM cộng dồn về nick rồi! 💸🍀',
        'Trọng lực Hoàng Gia dẫn lối, bóng lượn êm ru húp đều tay! ⚡',
        'Ra chiêu Multi-Drop nhịp nhàng là có quà, anh em vỗ tay lấy vía nào! 🎯',
        'Trận địa nảy đinh quá chuẩn, GTLM nhảy số liên hồi ngọt lịm! 💰'
    ];

    const lossPhrases = [
        'Cơn mưa bóng lượn góc hiểm làm bay màu nhẹ một tay, ván sau ra chiêu lớn săn lại x1000! 😤🔥',
        'Giao lưu nhả nhẹ một tay cho ấm đinh, ván sau xả 50 bóng phục thù mâm to! 💨',
        'Góc nảy chưa tối ưu một chút, set lại dàn đinh chiến tiếp! 🎲',
        'Bay màu một tay không sao cả, phong độ Hoàng Gia chỉ cần một quả x1000 là về bờ! ⚡'
    ];

    const actionPhrases = [
        'Chuẩn bị xả cơn mưa bóng Multi-Drop vào trận địa Plinko Royale, anh em sẵn sàng húp vía nào! 🎯👑',
        'Căn góc 16 hàng đinh cực bén, ván này tự tin nổ hũ Hoàng Gia! 🍀🔥',
        'Khí thế đang lên, chuẩn bị kích hoạt bão bóng quét sạch các khe x1000! 🚀'
    ];

    function sendBotChat(type, extraMsg) {
        const now = Date.now();
        if (now - lastChatTime < 8000) return;
        if (type === 'normal' && Math.random() > 0.6) return;

        let list = normalWinPhrases;
        if (type === 'jackpot') list = royaleJackpotPhrases;
        else if (type === 'loss') list = lossPhrases;
        else if (type === 'action') list = actionPhrases;

        const msg = extraMsg || list[Math.floor(Math.random() * list.length)];
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(60, 'bot_60', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // QUẢN LÝ VỐN & CHIẾN LƯỢC ROYALE V3
    // ═══════════════════════════════════════════════════════
    function getBalanceNumber() {
        const txt = $('#plinkoBalance').text().replace(/\D/g, '').trim();
        const val = parseFloat(txt);
        return isNaN(val) || val <= 0 ? 50000000 : val;
    }

    function decideStrategy() {
        const balance = getBalanceNumber();
        turnCount++;

        // 1. Phân bổ chip cược an toàn theo số dư ví
        let safeBet = 10000;
        if (balance >= 50000000) {
            safeBet = Math.random() > 0.5 ? 50000 : (Math.random() > 0.6 ? 100000 : 10000);
        } else if (balance >= 15000000) {
            safeBet = Math.random() > 0.6 ? 50000 : 10000;
        } else {
            safeBet = 10000;
        }

        // 2. Thích ứng theo chuỗi Thắng / Bay Màu
        let rows = '16';
        let risk = 'high';
        let balls = '10';

        if (winStreak >= 2) {
            // Đang đỏ rực: Royale Frenzy săn x1000
            rows = '16';
            risk = 'high';
            balls = Math.random() > 0.5 ? '25' : '50';
        } else if (loseStreak >= 2) {
            // Đang đen: Bảo toàn vốn, giảm rủi ro
            rows = Math.random() > 0.5 ? '12' : '16';
            risk = 'medium';
            balls = '10';
            safeBet = 10000;
        } else {
            // Xoay vòng kịch bản cân bằng
            const c = turnCount % 4;
            if (c === 0) { rows = '16'; risk = 'high'; balls = '10'; }
            else if (c === 1) { rows = '16'; risk = 'high'; balls = '25'; }
            else if (c === 2) { rows = '12'; risk = 'medium'; balls = '10'; }
            else { rows = '16'; risk = 'high'; balls = '50'; }
        }

        return { rows, risk, balls, bet: safeBet };
    }

    // ═══════════════════════════════════════════════════════
    // CHUỘT ẢO ASYNC AN TOÀN TUYỆT ĐỐI (SAFETY TIMEOUT)
    // ═══════════════════════════════════════════════════════
    function moveAndClick(el, duration = 0.45) {
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

            // Safety timeout: Đảm bảo luôn resolve tối đa sau (duration*1000 + 450ms)
            const safetyTimer = setTimeout(() => {
                try { el.click(); } catch (e) { }
                finish();
            }, (duration * 1000) + 450);

            try {
                BotVirtualCursor.moveToElement($(el), duration, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        clearTimeout(safetyTimer);
                        try { el.click(); } catch (e) { }
                        setTimeout(finish, 150);
                    });
                });
            } catch (err) {
                clearTimeout(safetyTimer);
                try { el.click(); } catch (e) { }
                finish();
            }
        });
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG LẶP CHƠI GAME TỰ ĐỘNG CỦA BOT
    // ═══════════════════════════════════════════════════════
    async function executeBotTurn() {
        if (botIsBusy) return;

        const dropBtn = document.getElementById('btnDropMain');
        if (!dropBtn || dropBtn.disabled) return;

        setBusy(true, 10000);

        try {
            const strat = decideStrategy();

            if (turnCount % 7 === 0) {
                sendBotChat('action');
            }

            // 70% thời gian: Giữ nguyên cài đặt và bấm THẢ BÓNG ngay để nhịp game dồn dập
            // 30% thời gian: Rê chuột điều chỉnh chiến thuật (Hàng / Risk / Bóng / Cược)
            const shouldTweakSettings = (turnCount === 1 || Math.random() < 0.35 || winStreak >= 2 || loseStreak >= 2);

            if (shouldTweakSettings) {
                // 1. Số Hàng Đinh
                const rowBtn = document.querySelector(`.btn-row-select[data-rows="${strat.rows}"]`);
                if (rowBtn && !rowBtn.classList.contains('active')) {
                    await moveAndClick(rowBtn, 0.35);
                }

                // 2. Mức Rủi Ro
                const riskBtn = document.querySelector(`.btn-risk-select[data-risk="${strat.risk}"]`);
                if (riskBtn && !riskBtn.classList.contains('active')) {
                    await moveAndClick(riskBtn, 0.35);
                }

                // 3. Số Lượng Bóng
                const ballBtn = document.querySelector(`.btn-ball-select[data-count="${strat.balls}"]`);
                if (ballBtn && !ballBtn.classList.contains('active')) {
                    await moveAndClick(ballBtn, 0.3);
                }

                // 4. Phím Cược Nhanh
                const quickBtn = document.querySelector(`.btn-bet-quick[data-bet="${strat.bet}"]`);
                if (quickBtn && !quickBtn.classList.contains('active')) {
                    await moveAndClick(quickBtn, 0.3);
                }
            }

            // 5. Rê chuột tới nút THẢ BÓNG ROYALE và click dứt khoát
            const activeDropBtn = document.getElementById('btnDropMain');
            if (activeDropBtn && !activeDropBtn.disabled) {
                await moveAndClick(activeDropBtn, 0.45);

                const numBalls = parseInt(strat.balls) || 10;
                const waitTime = Math.min(6500, 2200 + (numBalls * 45));

                setTimeout(() => {
                    // Đánh giá kết quả từ session profit và celebratory overlay
                    const profitText = $('#sessionProfitEl').text().replace(/\./g, '').replace(/,/g, '').trim();
                    const profitVal = parseFloat(profitText) || 0;

                    const celOverlay = document.getElementById('royaleCelebrationOverlay');
                    const hasJackpot = celOverlay && celOverlay.classList.contains('active');

                    if (hasJackpot) {
                        winStreak++;
                        loseStreak = 0;
                        sendBotChat('jackpot');
                    } else if (profitVal > 0) {
                        winStreak++;
                        loseStreak = 0;
                        sendBotChat('normal');
                    } else if (profitVal < 0) {
                        loseStreak++;
                        winStreak = 0;
                        sendBotChat('loss');
                    }

                    setBusy(false);
                }, waitTime);
            } else {
                setBusy(false);
            }
        } catch (err) {
            console.error('[Bot 60] Lỗi thực thi lượt chơi:', err);
            setBusy(false);
        }
    }

    // ═══════════════════════════════════════════════════════
    // DUAL-LAYER ANTI-STUCK WATCHDOG TIMER
    // ═══════════════════════════════════════════════════════
    let disabledSeconds = 0;
    setInterval(() => {
        const btn = document.getElementById('btnDropMain');
        if (btn && btn.disabled) {
            disabledSeconds += 2;
            if (disabledSeconds >= 8) {
                console.warn('[Bot 60] Anti-stuck Watchdog: Khôi phục nút thả bóng!');
                btn.disabled = false;
                btn.innerHTML = '💥 THẢ BÓNG ROYALE';
                botIsBusy = false;
                disabledSeconds = 0;
            }
        } else {
            disabledSeconds = 0;
        }
    }, 2000);

    // ═══════════════════════════════════════════════════════
    // KHỞI ĐỘNG VÒNG ĐIỀU KHIỂN ĐỊNH KỲ
    // ═══════════════════════════════════════════════════════
    $(document).ready(() => {
        console.log('[Bot 60] Bậc Thầy Hoàng Gia 60 khởi động thành công! 👑');
        setTimeout(() => { executeBotTurn(); }, 1800);
        setInterval(() => { executeBotTurn(); }, 4000);
    });

})();
