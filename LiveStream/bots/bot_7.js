/**
 * 🎱 Bot Streamer Bàn 7: Đại Gia Thả Bóng 07 (Plinko Royale) - AI SMART CASINO MODE
 * Thuật toán AI: Phân tích cầu tâm/biên + Quản lý vốn động Martingale/Cluster + Casino Rule 5.3 Pro
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 7] BotVirtualCursor chưa nạp!');
        return;
    }

    BotVirtualCursor.init('Đại Gia Thả Bóng 07 🎱');

    let botIsBusy = false;
    let busyTimestamp = 0;

    // AI State Tracking
    const aiState = {
        history: [],          // Lưu lịch sử các ván { balls, bet, win, roi }
        consecutiveLowRoi: 0, // Số ván liên tiếp bóng rơi vào tâm x0.2
        streakWins: 0,        // Số ván thắng liên tiếp
        currentStrategy: 'NORMAL' // 'NORMAL', 'BORDER_HUNT', 'PROFIT_LOCK', 'WHALE_RAMPAGE'
    };

    const CASINO_CHATS = {
        normal_drop: [
            "Khởi động dàn ghim với 10 bóng may mắn, rà sóng ô biên x5.0 nào anh em! 🎱✨",
            "Nhịp bóng đang mượt, thả nhẹ 10 quả lấy cảm giác đường rơi! 🚀💎",
            "Canh góc rơi chuẩn xác, đón lộc lá đầu ván! 🌟💰"
        ],
        border_hunt: [
            "Cầu đang nén ở tâm x0.2 quá lâu! Tăng lên 25 bóng phủ kín dàn ghim săn x5.0! 🔥🎱",
            "Động năng đang dồn ra hai cánh! Xả 25 bóng bão lửa đón siêu hũ biên! ⚡💎",
            "Chiến thuật bao vây dàn ghim: Tăng mức cược, nhắm thẳng ô biên x5.0 góc phải! 🚀👑"
        ],
        whale_rampage: [
            "Kèo Đại Gia tất tay: Xả 50 BÓNG LIÊN HOÀN! Mưa bóng ngập tràn màn hình! 🌊🎱🔥",
            "50 bóng đồng loạt xuất trận! Nín thở chờ siêu bão nổ hũ triệu GTLM! 💥👑",
            "Tiếng này tôi chơi lớn, 50 bóng phủ kín mọi ngóc ngách dàn ghim! 😱💎"
        ],
        profit_lock: [
            "Vừa húp trọn ô biên x5.0 cực ngọt! Thu quân về 10 bóng chốt lời bảo toàn vốn! 🛡️💰",
            "Ăn đậm rồi thì phải biết giữ lộc, giảm nhịp bóng về mức an toàn! 👑✨"
        ],
        win_big: [
            "HÚP TRỌN SIÊU HŨ BIÊN x5.0 RỒI ANH EM ƠI! Lời đậm +{profit} GTLM! 🏆🎉🤑",
            "Nổ to quá! Chiến thuật săn ô biên chuẩn xác từng milimet! Húp trọn! 💰🔥",
            "Bóng lọt khe ngoài cùng x5.0! Đẳng cấp soi cầu Plinko lên tiếng! 💎👑"
        ],
        win_normal: [
            "Lộc lá nhẹ nhàng, giữ vững phong độ nào anh em! ✨💰",
            "Bóng nảy ổn áp, có lời nhẹ ván này! 🎱🌟"
        ],
        loss_recovery: [
            "Bóng lọt khe giữa x0.2 hơi nhiều, cầu đang nén động năng! Ván sau nổ to! 😅💪",
            "Không sao, bóng rơi tâm là tín hiệu ô biên sắp bùng nổ, sẵn sàng xả bão! ⚔️🔥"
        ]
    };

    function sendChat(type, replaceObj = {}) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.normal_drop;
            let msg = list[Math.floor(Math.random() * list.length)];
            for (let k in replaceObj) {
                msg = msg.replace(`{${k}}`, replaceObj[k]);
            }
            BotChat.send(7, 'bot_7', msg);
        }
    }

    // Chuột ảo Turbo 0.22s
    function fastMoveAndClick(el, duration = 0.22) {
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

    // AI TÍNH TOÁN CHIẾN THUẬT CƯỢC THÔNG MINH
    function determineSmartBet() {
        let chosenBalls = 10;
        let chosenBet = 50000;
        let strategy = 'NORMAL';

        // 1. Kiểm tra nếu 2 ván liên tiếp ROI thấp (< 0.8) -> Kích hoạt Săn Ô Biên
        if (aiState.consecutiveLowRoi >= 2) {
            strategy = 'BORDER_HUNT';
            chosenBalls = (Math.random() < 0.7) ? 25 : 50;
            chosenBet = (Math.random() < 0.5) ? 100000 : 200000;
        }
        // 2. Nếu vừa thắng lớn ở ván trước -> Chốt lời bảo toàn
        else if (aiState.currentStrategy === 'BORDER_HUNT' && aiState.streakWins >= 1) {
            strategy = 'PROFIT_LOCK';
            chosenBalls = 10;
            chosenBet = 50000;
        }
        // 3. Cơ hội kích hoạt Bão Bóng Đại Gia (15% xác suất khi đang hòa/thắng)
        else if (Math.random() < 0.15 && aiState.streakWins >= 0) {
            strategy = 'WHALE_RAMPAGE';
            chosenBalls = 50;
            chosenBet = (Math.random() < 0.6) ? 200000 : 500000;
        }
        // 4. Bình thường
        else {
            strategy = 'NORMAL';
            chosenBalls = (Math.random() < 0.6) ? 10 : 25;
            chosenBet = (Math.random() < 0.6) ? 50000 : 100000;
        }

        aiState.currentStrategy = strategy;
        return { balls: chosenBalls, bet: chosenBet, strategy: strategy };
    }

    async function executeBotTurn() {
        if (botIsBusy || window.isDropping) return;

        const btn = document.getElementById('dropBtn');
        if (!btn || btn.disabled) return;

        botIsBusy = true;
        busyTimestamp = Date.now();

        try {
            // 1. AI Quyết định số bóng và mức cược theo chiến thuật
            const decision = determineSmartBet();

            // 2. Chat chiến thuật
            if (decision.strategy === 'BORDER_HUNT') {
                sendChat('border_hunt');
            } else if (decision.strategy === 'WHALE_RAMPAGE') {
                sendChat('whale_rampage');
            } else if (decision.strategy === 'PROFIT_LOCK') {
                sendChat('profit_lock');
            } else if (Math.random() < 0.25) {
                sendChat('normal_drop');
            }

            // 3. Áp dụng giá trị vào UI
            $('#ballCount').val(decision.balls);
            $('#totalBet').val(decision.bet);
            if (typeof updateBetInfo === 'function') updateBetInfo();

            await wait(100);

            // 4. Rê chuột click nút THẢ BÓNG siêu tốc 0.22s
            await fastMoveAndClick($(btn), 0.22);

            // 5. Đợi bóng rơi và nảy qua dàn ghim (khoảng 1.2s - 1.4s ở chế độ turbo)
            await wait(1350);

            // 6. Đọc kết quả từ giao diện
            const winText = $('#totalWinDisplay').text().replace(/[^0-9]/g, '');
            const totalWin = parseInt(winText || 0, 10);
            const roi = totalWin / decision.bet;
            const profit = totalWin - decision.bet;

            // Cập nhật trạng thái AI
            aiState.history.push({ balls: decision.balls, bet: decision.bet, win: totalWin, roi: roi });
            if (aiState.history.length > 10) aiState.history.shift();

            if (roi >= 1.5) {
                // Thắng đậm (nổ ô biên x2 - x5)
                aiState.streakWins++;
                aiState.consecutiveLowRoi = 0;
                sendChat('win_big', { profit: new Intl.NumberFormat('vi-VN').format(profit) });
            } else if (roi >= 1.0) {
                // Thắng nhẹ / hòa
                aiState.streakWins++;
                aiState.consecutiveLowRoi = 0;
                if (Math.random() < 0.4) sendChat('win_normal');
            } else {
                // Lỗ (rơi vào ô giữa x0.2)
                aiState.streakWins = 0;
                aiState.consecutiveLowRoi++;
                if (Math.random() < 0.35) sendChat('loss_recovery');
            }

            await wait(150);

        } catch (e) {
            console.error('[Bot 7 AI Error]', e);
        } finally {
            botIsBusy = false;
        }
    }

    // Vòng lặp chính siêu tốc: 1.6 giây
    setInterval(executeBotTurn, 1600);

    // TRIPLE-LAYER WATCHDOG
    setInterval(() => {
        // Tầng 1: Giải phóng bận sau 6s
        if (botIsBusy && Date.now() - busyTimestamp > 6000) {
            console.warn('[Bot 7 Watchdog] Tự giải phóng bận');
            botIsBusy = false;
            window.isDropping = false;
        }

        // Tầng 2: Nếu bàn rảnh mà bot dừng quá 4s -> Kích hoạt lượt thả bóng mới
        const btn = document.getElementById('dropBtn');
        if (btn && !btn.disabled && !botIsBusy && !window.isDropping) {
            executeBotTurn();
        }
    }, 2000);

    console.log('[Bot 7] Đại Gia Thả Bóng 07 AI SMART CASINO đã sẵn sàng xuất kích 24/7!');
})();
