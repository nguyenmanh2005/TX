/**
 * 🤖 Bot AI Plinko V2 Pro - Bậc Thầy Trọng Lực 59 (ID: 59)
 *
 * CHIẾN THUẬT & TRÍ TUỆ NHÂN TẠO:
 * 1. QUẢN LÝ VỐN & PHÂN BỔ CƯỢC THÔNG MINH (SMART BANKROLL):
 *    - Đọc số dư ví GTLM của Streamer qua #balance-val.
 *    - Phân bổ chip cược an toàn (10K, 50K, 100K, 500K) tương xứng với số dư ví.
 *    - Tuyệt đối không cược vượt quá tỷ lệ an toàn, không ALL IN.
 * 2. CHIẾN LƯỢC THEO CHUỖI (ADAPTIVE STREAK STRATEGY):
 *    - Theo dõi chuỗi Thắng (Húp) / Thua (Bay Màu):
 *      + Khi thắng liên tiếp: Chuyển sang chế độ "Săn Hũ" - chọn Risk HIGH, 16 Hàng Đinh để săn SIÊU HŨ x1000.
 *      + Khi thua liên tiếp: Lùi về chế độ "Bảo Toàn" - chọn Risk LOW hoặc MED (8-12 hàng) và thả 5-10 bóng để hoàn vốn.
 *      + Trạng thái bình thường: Xoay vòng linh hoạt các kịch bản cân bằng.
 * 3. HỆ THỐNG CHUỘT ẢO CHUYÊN NGHIỆP (BotVirtualCursor):
 *    - Di chuyển mượt mà tới các nút Risk, Hàng Đinh, Số Bóng, Phím Cược Nhanh và nút Thả Bóng.
 *    - Async/Await tuần tự, tránh đứt gãy luồng tương tác DOM.
 * 4. BÌNH LUẬN & PHÁT NGÔN CASINO SÔI NỔI (BotChat.send):
 *    - Tuân thủ 100% bảng từ lóng Rule 5.3: húp GTLM, bay màu, ra chiêu, chiến, giao lưu, trận địa...
 * 5. WATCHDOG TIMER & ANTI-STUCK:
 *    - Tự động giải phóng trạng thái sau 12 giây nếu gặp trục trặc mạng.
 *    - Tự động mở khóa nút #dropBtn nếu bị treo quá thời gian.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 59] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Bậc Thầy Plinko 59');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI VÀ BIẾN ĐIỀU KHIỂN
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;
    let winStreak = 0;
    let loseStreak = 0;
    let turnCount = 0;

    function setBusy(val, timeoutMs = 12000) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            busyTimer = setTimeout(() => {
                console.log('[Bot 59] Watchdog giải phóng trạng thái bận!');
                botIsBusy = false;
                const btn = document.getElementById('dropBtn');
                if (btn && btn.disabled) {
                    btn.disabled = false;
                    btn.innerText = '🎱 THẢ BÓNG';
                }
            }, timeoutMs);
        }
    }

    // ═══════════════════════════════════════════════════════
    // PHÁT NGÔN & BÌNH LUẬN CASINO CHUẨN RULE 5.3
    // ═══════════════════════════════════════════════════════
    const jackpotPhrases = [
        'SIÊU HŨ PLINKO NỔ TUNG BẢNG RỒI! Húp trọn hàng triệu GTLM ngập ví anh em ơi! 👑💥🎱',
        'Quả bóng chạm mép x1000 đỉnh cao! Đỉnh cao trọng lực thần thánh hôm nay! 🏆✨',
        'Ăn ngập mặt với siêu nhân High Risk! Thần may mắn độ nick streamer rồi! 🚀💰'
    ];

    const normalWinPhrases = [
        'Húp nhẹ tiền thưởng Plinko! Tích tiểu thành đại quá ngọt ngào! 🎱✨',
        'Bóng lượn trúng khe ngon! Lãi ròng rồi anh em ơi! 💸🍀',
        'Trọng lực đưa đường quá chuẩn, GTLM cộng về nick êm ru! ⚡',
        'Ra chiêu nhịp nhàng là có quà, húp đều tay mỗi ván! 🎯'
    ];

    const lossPhrases = [
        'Bóng lượn góc hiểm làm bay màu nhẹ một tay, ván sau ra chiêu lớn phục thù! 😤🔥',
        'Nhả nhẹ một ván cho máy ấm đinh, ván sau săn quả x1000 gỡ lại mâm to! 💨',
        'Một chút va chạm chưa chuẩn góc, set lại hàng đinh chiến tiếp! 🎲'
    ];

    const actionPhrases = [
        'Căn góc cực chuẩn, chuẩn bị thả bóng săn hũ to nào anh em! 🎯🎱',
        'Điều chỉnh lại độ nảy, ván này tự tin húp đậm GTLM! 🍀'
    ];

    function sendBotChat(type, extraMsg) {
        const now = Date.now();
        if (now - lastChatTime < 9000) return;
        if (type === 'normal' && Math.random() > 0.6) return;

        let list = normalWinPhrases;
        if (type === 'jackpot') list = jackpotPhrases;
        else if (type === 'loss') list = lossPhrases;
        else if (type === 'action') list = actionPhrases;

        const msg = extraMsg || list[Math.floor(Math.random() * list.length)];
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(59, 'bot_59', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // QUẢN LÝ VỐN & CHIẾN LƯỢC CƯỢC
    // ═══════════════════════════════════════════════════════
    function getBalanceNumber() {
        const txt = $('#balance-val').text().replace(/\./g, '').replace(/,/g, '').trim();
        const val = parseFloat(txt);
        return isNaN(val) ? 50000000 : val;
    }

    function decideStrategy() {
        const balance = getBalanceNumber();
        turnCount++;

        // 1. Xác định mức cược an toàn dựa theo số dư
        let safeBet = 10000;
        if (balance >= 50000000) {
            safeBet = Math.random() > 0.4 ? 100000 : (Math.random() > 0.5 ? 500000 : 50000);
        } else if (balance >= 10000000) {
            safeBet = Math.random() > 0.5 ? 50000 : 100000;
        } else if (balance >= 2000000) {
            safeBet = Math.random() > 0.3 ? 50000 : 10000;
        } else {
            safeBet = 10000;
        }

        // 2. Thích ứng theo Streak Thắng/Thua
        let risk = 'medium';
        let rows = '12';
        let balls = '1';

        if (winStreak >= 2) {
            // Đang đỏ: Hưng Phấn săn Hũ x1000
            risk = 'high';
            rows = '16';
            balls = Math.random() > 0.5 ? '1' : '5';
        } else if (loseStreak >= 2) {
            // Đang đen: Bảo toàn vốn, đa dạng hóa rủi ro
            risk = Math.random() > 0.5 ? 'low' : 'medium';
            rows = Math.random() > 0.5 ? '8' : '12';
            balls = Math.random() > 0.4 ? '5' : '10';
            safeBet = Math.min(safeBet, 50000); // Hạ cược
        } else {
            // Xoay vòng các kịch bản chiến thuật
            const comboIdx = turnCount % 4;
            if (comboIdx === 0) {
                risk = 'medium'; rows = '12'; balls = '1';
            } else if (comboIdx === 1) {
                risk = 'high'; rows = '12'; balls = '5';
            } else if (comboIdx === 2) {
                risk = 'low'; rows = '8'; balls = '10';
            } else {
                risk = 'high'; rows = '16'; balls = '1';
            }
        }

        return { risk, rows, balls, bet: safeBet };
    }

    // ═══════════════════════════════════════════════════════
    // CHUỘT ẢO HỖ TRỢ ASYNC / PROMISE
    // ═══════════════════════════════════════════════════════
    function moveAndClick(el, duration = 0.5) {
        return new Promise(resolve => {
            if (!el || $(el).length === 0) {
                resolve();
                return;
            }
            BotVirtualCursor.moveToElement($(el), duration, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    try { el.click(); } catch (e) {}
                    setTimeout(resolve, 250);
                });
            });
        });
    }

    function moveOnly(el, duration = 0.4) {
        return new Promise(resolve => {
            if (!el || $(el).length === 0) {
                resolve();
                return;
            }
            BotVirtualCursor.moveToElement($(el), duration, 0, () => {
                setTimeout(resolve, 150);
            });
        });
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG LẶP CHƠI GAME TỰ ĐỘNG CỦA BOT
    // ═══════════════════════════════════════════════════════
    async function executeBotTurn() {
        if (botIsBusy) return;

        const dropBtn = document.getElementById('dropBtn');
        if (!dropBtn || dropBtn.disabled) return;

        setBusy(true, 14000);

        try {
            const strat = decideStrategy();
            console.log(`[Bot 59] Ra chiêu #${turnCount}: Risk=${strat.risk}, Rows=${strat.rows}, Balls=${strat.balls}, Bet=${strat.bet}`);

            if (turnCount % 6 === 0) {
                sendBotChat('action');
            }

            // 1. Chọn Risk (LOW / MED / HIGH)
            const curRiskBtn = document.querySelector(`#riskCtrl .seg-btn[data-val="${strat.risk}"]`);
            if (curRiskBtn && !curRiskBtn.classList.contains('active')) {
                await moveAndClick(curRiskBtn, 0.45);
            }

            // 2. Chọn Số Hàng Đinh (8 / 12 / 16)
            const curRowBtn = document.querySelector(`#rowsCtrl .seg-btn[data-val="${strat.rows}"]`);
            if (curRowBtn && !curRowBtn.classList.contains('active')) {
                await moveAndClick(curRowBtn, 0.45);
            }

            // 3. Chọn Số Bóng (1 / 5 / 10 / 25)
            const curBallBtn = document.querySelector(`#ballsCtrl .seg-btn[data-val="${strat.balls}"]`);
            if (curBallBtn && !curBallBtn.classList.contains('active')) {
                await moveAndClick(curBallBtn, 0.4);
            }

            // 4. Chọn Phím Cược Nhanh (10K / 50K / 100K / 500K)
            const quickBtn = Array.from(document.querySelectorAll('.quick-bets .q-btn')).find(b => {
                const txt = (b.innerText || '').toUpperCase();
                if (strat.bet === 10000 && txt.includes('10K')) return true;
                if (strat.bet === 50000 && txt.includes('50K')) return true;
                if (strat.bet === 100000 && txt.includes('100K')) return true;
                if (strat.bet === 500000 && txt.includes('500K')) return true;
                return false;
            });

            if (quickBtn) {
                await moveAndClick(quickBtn, 0.4);
            } else {
                const betInput = document.getElementById('betAmt');
                if (betInput) {
                    await moveOnly(betInput, 0.35);
                    betInput.value = strat.bet;
                    betInput.dispatchEvent(new Event('input', { bubbles: true }));
                    betInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            // 5. Rê chuột tới nút THẢ BÓNG và nhấn dứt khoát
            const activeDropBtn = document.getElementById('dropBtn');
            if (activeDropBtn && !activeDropBtn.disabled) {
                await moveAndClick(activeDropBtn, 0.5);

                // Thời gian chờ các bóng rơi hoàn tất (2.2s cơ sở + 160ms mỗi bóng)
                const numBalls = parseInt(strat.balls) || 1;
                const totalWaitTime = 2200 + (numBalls * 180) + 1200;

                setTimeout(() => {
                    // Quan sát kết quả từ huy hiệu hoặc thanh thống kê
                    const badge = document.getElementById('result-status-badge');
                    if (badge) {
                        if (badge.classList.contains('badge-jackpot')) {
                            winStreak++;
                            loseStreak = 0;
                            sendBotChat('jackpot');
                        } else if (badge.classList.contains('badge-win')) {
                            winStreak++;
                            loseStreak = 0;
                            sendBotChat('normal');
                        } else if (badge.classList.contains('badge-lose')) {
                            loseStreak++;
                            winStreak = 0;
                            sendBotChat('loss');
                        }
                    }

                    setBusy(false);
                }, totalWaitTime);
            } else {
                setBusy(false);
            }
        } catch (err) {
            console.error('[Bot 59] Lỗi khi thực hiện vòng chơi:', err);
            setBusy(false);
        }
    }

    // ═══════════════════════════════════════════════════════
    // KHỞI ĐỘNG VÒNG ĐIỀU KHIỂN ĐỊNH KỲ
    // ═══════════════════════════════════════════════════════
    console.log('[Bot 59] Bậc Thầy Plinko 59 khởi động thành công!');
    setTimeout(() => { executeBotTurn(); }, 1800);
    setInterval(() => { executeBotTurn(); }, 5000);

})();
