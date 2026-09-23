/**
 * 🏎️ Bot Streamer Bàn 6: Tay Đua Cyber 06 (Đua Thú Cyberpunk 2077) - TURBO FAST-PACED MODE
 * Chuẩn Modern Async/Await + Chuột Ảo An Toàn + Triple Watchdog + Casino Rule 5.3
 * Nhịp điệu siêu tốc: Cược ngay trong 1s đầu phase, rê chuột chớp nhoáng, tương tác sôi nổi!
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 6] BotVirtualCursor chưa tải!');
        return;
    }

    BotVirtualCursor.init('Tay Đua Cyber 06 🏎️');

    let botIsBusy = false;
    let lastHandledCycle = -1;
    let busyTimestamp = 0;

    const CASINO_CHATS = {
        betting: [
            "Sói Cyber ván này vào form rồi, tất tay nào anh em! 🐺🔥",
            "Báo Plasma tăng tốc cực gắt, tiếng này thơm phức! 🐆⚡",
            "Cáo Neon luồn lách kinh điển, vào nhẹ 200k lấy lộc! 🦊✨",
            "Đại Bàng Lượng Tử x6.0, liều ăn nhiều nổ hũ to! 🦅🚀",
            "Gấu Sắt đầm chắc đường đua, kèo này sáng cửa húp! 🐻💎",
            "Tiếng này cầu đua đang bệt Sói, anh em theo tôi húp trọn! 🏎️💨"
        ],
        racing: [
            "Bứt tốc đi Sói ơi, đừng để Cáo vượt! 🔥🔥🔥",
            "Ép ga hết số nào! Khét lẹt đường đua Neon 2077! ⚡💨",
            "Đua căng thẳng vãi chưởng, nín thở đếm từng mét! 😱🏁",
            "Pha drift đỉnh cao của Báo Plasma! Cố lên về đích nào! 🐆"
        ],
        win: [
            "Húp trọn kèo rồi anh em ơi! Lộc lá đầy túi! 🏆🎉",
            "Tuyệt vời! Chiến thuật soi kèo thú đua chuẩn không cần chỉnh! 🤑💰",
            "Ăn đậm tiếng này rồi! Cảm ơn anh em đã cổ vũ! 🔥👑"
        ],
        loss: [
            "Gió đổi chiều phút chót rồi, bay màu tiếng này! 😭💔",
            "Bị ép cua phút cuối tiếc quá, ván sau làm lại gấp đôi! 🔄💪",
            "Không sao, còn thở là còn gỡ, ván sau tất tay phục thù! ⚔️🔥"
        ]
    };

    function sendChat(type) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.betting;
            const msg = list[Math.floor(Math.random() * list.length)];
            BotChat.send(6, 'bot_6', msg);
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

    async function executeBotTurn() {
        if (botIsBusy) return;

        const currentCycle = window.currentCycleId || 0;
        const currentPhase = window.currentPhase || 'betting';

        // Xử lý Phase BETTING: Cược siêu tốc ngay khi cycle mới bắt đầu
        if (currentPhase === 'betting' && currentCycle !== lastHandledCycle) {
            botIsBusy = true;
            busyTimestamp = Date.now();
            lastHandledCycle = currentCycle;

            try {
                if (Math.random() < 0.4) sendChat('betting');
                await wait(100);

                // Chọn ngẫu nhiên thú đua (ưu tiên Sói, Cáo, Báo)
                const animals = ['wolf', 'fox', 'panther', 'bear', 'eagle'];
                const weights = [35, 25, 20, 10, 10];
                let rand = Math.random() * 100;
                let chosen = 'wolf';
                for (let i = 0; i < weights.length; i++) {
                    if (rand < weights[i]) {
                        chosen = animals[i];
                        break;
                    }
                    rand -= weights[i];
                }

                // 1. Rê chuột click chọn thú chớp nhoáng (0.22s)
                const targetCard = $(`#betItem_${chosen}`);
                if (targetCard.length) {
                    await fastMoveAndClick(targetCard, 0.22);
                    await wait(80);
                }

                // 2. Rê chuột bấm mức cược (+100K hoặc +500K)
                const betButtons = ['#btnBet100k', '#btnBet500k', '#btnBet10k'];
                const chosenBtn = betButtons[Math.floor(Math.random() * betButtons.length)];
                const $btn = $(chosenBtn);
                if ($btn.length) {
                    await fastMoveAndClick($btn, 0.2);
                    await wait(80);
                }

                // 3. Rê chuột bấm XÁC NHẬN CƯỢC
                const $submit = $('#btnSubmitBet');
                if ($submit.length) {
                    await fastMoveAndClick($submit, 0.22);
                    await wait(100);
                }

            } catch (e) {
                console.error('[Bot 6 Error]', e);
            } finally {
                botIsBusy = false;
            }
        }

        // Xử lý Phase RACING: Chat cổ vũ
        if (currentPhase === 'racing' && Math.random() < 0.15) {
            sendChat('racing');
        }

        // Xử lý Phase RESULT: Chat kết quả
        if (currentPhase === 'result' && Math.random() < 0.2) {
            if (Math.random() < 0.5) {
                sendChat('win');
            } else {
                sendChat('loss');
            }
        }
    }

    // Vòng lặp điều khiển chính: 1.2 giây
    setInterval(executeBotTurn, 1200);

    // TRIPLE-LAYER WATCHDOG
    setInterval(() => {
        // Tầng 1: Tự giải phóng nếu bận quá 6s
        if (botIsBusy && Date.now() - busyTimestamp > 6000) {
            console.warn('[Bot 6 Watchdog] Bận quá lâu, tự giải phóng cờ!');
            botIsBusy = false;
        }

        // Tầng 2: Nếu đang phase betting mà chưa cược và thời gian còn dưới 10s -> Fallback cược trực tiếp
        if (window.currentPhase === 'betting' && window.currentCycleId !== lastHandledCycle && window.currentTimer <= 10) {
            console.log('[Bot 6 Watchdog] Kích hoạt Fallback Bet nhanh');
            lastHandledCycle = window.currentCycleId;
            try {
                if (typeof selectAnimal === 'function') selectAnimal('wolf');
                if (typeof addBet === 'function') addBet(100000);
                if (typeof submitBet === 'function') submitBet();
            } catch (err) {}
        }
    }, 2000);

    console.log('[Bot 6] Tay Đua Cyber 06 TURBO đã sẵn sàng!');
})();
