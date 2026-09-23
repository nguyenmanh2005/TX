/**
 * 🚀 Bot Streamer Bàn 35: Cơ Trưởng Limbo 35 (Limbo Rocket) - AI SMART COMMANDER PRO
 * Chuẩn Modern Async/Await + Chuột Ảo Tương Tác Đa Điểm + AI Phân Tích Cầu Thích Nghi + Casino Rule 5.3
 * Tích hợp LiveStream Chat 24/7 đồng bộ qua BotChat
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 35] BotVirtualCursor chưa tải!');
        return;
    }

    BotVirtualCursor.init('Cơ Trưởng Limbo 35 🚀');

    let botIsBusy = false;
    let busyTimestamp = 0;

    // AI Flight Commander Intelligence Engine
    const aiCommander = {
        consecutiveWins: 0,
        consecutiveLosses: 0,
        recentCrashes: [], // Lưu trữ các điểm nổ gần nhất
        currentStrategy: 'SAFE_GRIND', // 'SAFE_GRIND', 'STANDARD_DOUBLE', 'MOONSHOT', 'CRASH_SHIELD'
        currentTarget: 2.00,
        currentBetBtn: '#btnBet100k',
        currentTargetBtn: '#btnTarget2x',
        totalProfit: 0
    };

    const CASINO_CHATS = {
        crash_shield: [
            "Cầu đang nén bệt nổ sớm liên tục! Kích hoạt chế độ Bảo Hộ Khí Tài: Hạ mục tiêu 1.30x (xác suất 76%) bảo toàn nick! 🛡️⚡",
            "Động cơ quá nhiệt nổ sớm, không đu đỉnh cao! Giữ mục tiêu 1.30x an toàn hồi máu kéo lại vốn! 🔄💪",
            "Quản trị rủi ro tối cao: Bẻ cầu ở mốc an toàn 1.30x, ra chiêu chắc tay đón lộc! 🎯💰"
        ],
        safe_grind: [
            "Khóa mục tiêu 1.50x (xác suất 66%), ra chiêu 100.000 GTLM làm nóng buồng đốt nào anh em! 🚀✨",
            "Tích tiểu thành đại, giữ nhịp độ bay ổn định ở mốc 1.50x húp GTLM đều đều! 🛡️💎",
            "Nhịp bay tầm thấp đang cực kỳ êm ái, gom lộc lá tích lũy ngân khố! 🌟💰"
        ],
        standard_double: [
            "Cầu trận địa đang cân bằng đẹp, khóa mục tiêu x2.00! Chuẩn bị cất cánh x2 vốn! ⚡🚀",
            "Target 2.00x x2 tài khoản, nín thở đếm từng nhịp động cơ đẩy! Ra chiêu dứt khoát! 🤞💎",
            "Kèo 50/50 kinh điển của Vegas Limbo, tiếng này tiền về ngập nick! 🎯💰"
        ],
        moonshot: [
            "BUỒNG ĐỐT ĐẠT CỰC ĐẠI! Kích hoạt chế độ Moonshot Target {target}x bắn thẳng lên sao Hỏa! 🌌🚀💥",
            "Kèo liều ăn nhiều: Đẩy mục tiêu lên {target}x! Một bước lên mây húp trọn siêu hũ! 👑💎🔥",
            "Bắn tên lửa không gian Target {target}x! Anh em cùng tôi thắt dây an toàn nào! 🌠🛸"
        ],
        win_moonshot: [
            "CHẠM ĐỈNH QUỸ ĐẠO! Tên lửa vượt mốc {target}x! Húp trọn ngập mặt +{win} GTLM! 🏆🎉🤑",
            "NỔ SIÊU HŨ MOONSHOT! Cú phóng ngoạn mục ăn đậm tiền về đầy nick! 🚀💎👑",
            "Đẳng cấp Cơ Trưởng Limbo! Bắn phát nào trúng phát đó, húp trọn trận địa! 🌟💰"
        ],
        win_normal: [
            "Cất cánh thành công! Nhẹ nhàng húp +{win} GTLM vào nick! 🚀✨",
            "Về đích an toàn vượt mốc {target}x, cộng GTLM chuẩn chỉ! 💰👍"
        ],
        loss: [
            "Động cơ quá tải phát nổ ở {result}x, bay màu tiếng này! Ván sau chỉnh lại thông số buồng đốt! 😅💥",
            "Áp suất buồng lái tăng cao phát nổ ở {result}x, không sao, còn thở là còn ra chiêu! 🔄💪"
        ]
    };

    function sendChat(type, replaceObj = {}) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.safe_grind;
            let msg = list[Math.floor(Math.random() * list.length)];
            for (let k in replaceObj) {
                msg = msg.replace(new RegExp(`\\{${k}\\}`, 'g'), replaceObj[k]);
            }
            BotChat.send(35, 'bot_35', msg);
        }
    }

    // Chuột ảo Turbo 0.20s di chuyển và click
    function fastMoveAndClick(el, duration = 0.20) {
        return new Promise((resolve) => {
            const $el = $(el);
            if (!$el.length) return resolve(false);

            let hasResolved = false;
            const safetyTimer = setTimeout(() => {
                if (!hasResolved) {
                    hasResolved = true;
                    resolve(true);
                }
            }, (duration * 1000) + 300);

            try {
                BotVirtualCursor.moveToElement($el, duration, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try {
                            $el[0].click();
                        } catch (e) {}
                        if (!hasResolved) {
                            hasResolved = true;
                            clearTimeout(safetyTimer);
                            resolve(true);
                        }
                    });
                });
            } catch (err) {
                if (!hasResolved) {
                    hasResolved = true;
                    clearTimeout(safetyTimer);
                    resolve(false);
                }
            }
        });
    }

    const wait = (ms) => new Promise((res) => setTimeout(res, ms));

    // THUẬT TOÁN AI PHÂN TÍCH CẦU & KẾ HOẠCH BAY ĐỘNG
    function analyzeAndPlanFlight() {
        let strategy = 'STANDARD_DOUBLE';
        let betBtn = '#btnBet100k';
        let targetBtn = '#btnTarget2x';
        let targetVal = 2.00;

        // Đọc lịch sử các ván gần nhất từ window.limboHistory nếu có
        const history = window.limboHistory || [];
        const lastGames = history.slice(-4);

        // Đếm số lần nổ sớm liên tiếp (< 1.6x)
        let lowCrashCount = 0;
        for (let i = lastGames.length - 1; i >= 0; i--) {
            if (lastGames[i].result < 1.6) {
                lowCrashCount++;
            } else {
                break;
            }
        }

        // 1. CHIẾN LƯỢC 1: BẢO HỘ KHÍ TÀI (CRASH SHIELD)
        // Khi 2 lần nổ sớm liên tiếp hoặc vừa thua 2 ván
        if (lowCrashCount >= 2 || aiCommander.consecutiveLosses >= 2) {
            strategy = 'CRASH_SHIELD';
            betBtn = '#btnBet500k'; // Cược lớn hơn để bẻ cầu
            targetBtn = '#btnTarget1_3x';
            targetVal = 1.30;
        }
        // 2. CHIẾN LƯỢC 2: BẮN SIÊU NĂNG MOONSHOT
        // Khi đang có đà thắng (consecutiveWins >= 2)
        else if (aiCommander.consecutiveWins >= 2) {
            strategy = 'MOONSHOT';
            betBtn = '#btnBet50k'; // Cược nhỏ săn x10
            if (Math.random() < 0.6) {
                targetBtn = '#btnTarget5x';
                targetVal = 5.00;
            } else {
                targetBtn = '#btnTarget10x';
                targetVal = 10.00;
            }
        }
        // 3. CHIẾN LƯỢC 3: LÀM NÓNG BUỒNG ĐỐT (SAFE GRIND)
        else if (aiCommander.consecutiveWins === 0 && Math.random() < 0.4) {
            strategy = 'SAFE_GRIND';
            betBtn = '#btnBet100k';
            targetBtn = '#btnTarget1_5x';
            targetVal = 1.50;
        }
        // 4. CHIẾN LƯỢC 4: KÈO X2 CHUẨN MỰC
        else {
            strategy = 'STANDARD_DOUBLE';
            betBtn = '#btnBet100k';
            targetBtn = '#btnTarget2x';
            targetVal = 2.00;
        }

        aiCommander.currentStrategy = strategy;
        aiCommander.currentBetBtn = betBtn;
        aiCommander.currentTargetBtn = targetBtn;
        aiCommander.currentTarget = targetVal;

        return { strategy, betBtn, targetBtn, targetVal };
    }

    async function executeBotTurn() {
        if (botIsBusy) return;

        const rollBtn = document.getElementById('rollBtn');
        if (!rollBtn || rollBtn.disabled) return;

        botIsBusy = true;
        busyTimestamp = Date.now();

        try {
            // 1. AI Phân tích cầu & Lập kế hoạch bay
            const flight = analyzeAndPlanFlight();

            // 2. Gửi bình luận chiến thuật vào LiveStream Chat
            if (flight.strategy === 'CRASH_SHIELD') {
                sendChat('crash_shield');
            } else if (flight.strategy === 'MOONSHOT') {
                sendChat('moonshot', { target: flight.targetVal.toFixed(2) });
            } else if (flight.strategy === 'SAFE_GRIND') {
                sendChat('safe_grind');
            } else if (Math.random() < 0.3) {
                sendChat('standard_double');
            }

            // 3. THAO TÁC 1: Chuột ảo lướt click chọn mức cược (0.18s)
            const $betBtn = $(flight.betBtn);
            if ($betBtn.length) {
                await fastMoveAndClick($betBtn, 0.18);
                await wait(60);
            }

            // 4. THAO TÁC 2: Chuột ảo lướt click chọn Target Multiplier (0.18s)
            const $targetBtn = $(flight.targetBtn);
            if ($targetBtn.length) {
                await fastMoveAndClick($targetBtn, 0.18);
                await wait(60);
            } else {
                // Fallback nếu nút chưa render
                if (typeof setTarget === 'function') setTarget(flight.targetVal);
            }

            // 5. THAO TÁC 3: Chuột ảo lướt sang bấm nút PHÓNG TÊN LỬA (0.20s)
            await fastMoveAndClick($(rollBtn), 0.20);

            // 6. Chờ tên lửa phóng và hiển thị kết quả (khoảng 0.9s)
            await wait(950);

            // 7. Đọc kết quả từ giao diện
            const isWin = $('.multiplier-display.win').length > 0;
            const currentMultText = $('#resultMult').text().replace(/[^0-9.]/g, '');
            const finalMult = parseFloat(currentMultText || 1.0);
            const betAmt = parseFloat($('#betAmount').val()) || 100000;
            const winAmount = isWin ? Math.floor(betAmt * flight.targetVal) : 0;
            const profit = isWin ? (winAmount - betAmt) : -betAmt;

            aiCommander.totalProfit += profit;

            if (isWin) {
                aiCommander.consecutiveWins++;
                aiCommander.consecutiveLosses = 0;

                if (flight.targetVal >= 4.0) {
                    sendChat('win_moonshot', {
                        target: flight.targetVal.toFixed(2),
                        win: new Intl.NumberFormat('vi-VN').format(winAmount)
                    });
                } else {
                    if (Math.random() < 0.4) {
                        sendChat('win_normal', {
                            target: flight.targetVal.toFixed(2),
                            win: new Intl.NumberFormat('vi-VN').format(winAmount)
                        });
                    }
                }
            } else {
                aiCommander.consecutiveWins = 0;
                aiCommander.consecutiveLosses++;

                if (Math.random() < 0.35) {
                    sendChat('loss', { result: finalMult.toFixed(2) });
                }
            }

            await wait(150);

        } catch (e) {
            console.error('[Bot 35 AI Error]', e);
        } finally {
            botIsBusy = false;
        }
    }

    // Nhịp điệu siêu tốc mỗi 1.4 giây
    setInterval(executeBotTurn, 1400);

    // TRIPLE-LAYER WATCHDOG
    setInterval(() => {
        // Tầng 1: Tự giải phóng nếu bận quá 4s
        if (botIsBusy && Date.now() - busyTimestamp > 4000) {
            botIsBusy = false;
            const rollBtn = document.getElementById('rollBtn');
            if (rollBtn) rollBtn.disabled = false;
        }

        // Tầng 2: Nếu nút sẵn sàng mà bot chưa chạy -> Kích hoạt lượt phóng mới
        const rollBtn = document.getElementById('rollBtn');
        if (rollBtn && !rollBtn.disabled && !botIsBusy) {
            executeBotTurn();
        }
    }, 2000);

    console.log('[Bot 35] Cơ Trưởng Limbo 35 AI SMART COMMANDER PRO đã sẵn sàng cất cánh 24/7!');
})();
