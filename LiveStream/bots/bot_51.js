/**
 * 🤖 Bot AI Sicbo (Tài Xỉu / Xúc Xắc Cổ Điển) Siêu Thông Minh (ID: 51)
 *
 * CHIẾN THUẬT & TRÍ TUỆ ĐỈNH CAO:
 * 1. QUẢN LÝ VỐN & PHÂN BỔ CƯỢC (SMART SICBO BANKROLL):
 *    - Tự động theo dõi số dư ví GTLM của Streamer (#balance-val).
 *    - Chọn mức cược an toàn (10K, 50K, 100K, 500K) tương xứng số dư ví.
 *    - Tránh ALL IN bừa bãi, bảo toàn vốn để livestream 24/7 bền bỉ.
 * 2. CHIẾN THUẬT ĐẶT CỬA SICBO THƯỢNG THỪA:
 *    - 75% ván: Đặt vào các cửa cân bằng xác suất cao: Tài (Big 11-17), Xỉu (Small 4-10), Chẵn (Even), Lẻ (Odd).
 *    - 25% ván: Đặt kèm 1 cửa tài lộc thưởng lớn: Bất kỳ bộ ba (any_triple x30), Tổng may mắn (total_9 -> total_12).
 *    - Có thuật toán nuôi cầu / bẻ cầu theo chuỗi thắng thua.
 * 3. HỆ THỐNG CHUỘT ẢO ASYNC AN TOÀN TUYỆT ĐỐI (BotVirtualCursor):
 *    - moveAndClick dạng Promise có Safety Timeout tự giải phóng sau (duration*1000 + 400ms).
 *    - Rê chuột tự nhiên chọn phỉnh -> click ô cược -> click nút "LẮC XÚC XẮC".
 * 4. BÌNH LUẬN & PHÁT NGÔN CASINO LIVESTREAM (BotChat.send):
 *    - Tuân thủ 100% Bảng thuật ngữ Casino Rule 5.3 (húp GTLM, bay màu, ra chiêu, chiến, giao lưu, trận địa...).
 * 5. TRIPLE-LAYER WATCHDOG CHỐNG KẸT:
 *    - Tự động giải phóng trạng thái bận sau 10 giây nếu gặp trục trặc mạng.
 *    - Quét định kỳ mỗi 2.5s, nếu nút #roll-btn sẵn sàng mà chưa lắc thì tự kích hoạt.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 51] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Thần Đổ Xúc Xắc 51 🎲');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI VÀ BIẾN ĐIỀU KHIỂN
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;
    let winStreak = 0;
    let lossStreak = 0;
    let lastBetType = null;
    let roundCount = 0;

    function setBusy(val, timeoutMs = 10000) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            busyTimer = setTimeout(() => {
                console.warn('[Bot 51 Watchdog] Tự động giải phóng trạng thái bận!');
                botIsBusy = false;
                if (typeof isRolling !== 'undefined') isRolling = false;
                const btn = document.getElementById('roll-btn');
                if (btn && btn.disabled) btn.disabled = false;
            }, timeoutMs);
        }
    }

    // ═══════════════════════════════════════════════════════
    // BÌNH LUẬN & PHÁT NGÔN CASINO CHUẨN RULE 5.3
    // ═══════════════════════════════════════════════════════
    const bigWinPhrases = [
        'Nổ hũ bộ ba rồi anh em ơi! x30 GTLM thơm nức mũi! 🎲💥🔥',
        'Cầu chuẩn như sách giáo khoa! Húp trọn mâm xúc xắc vàng! 🏆✨',
        'Xúc xắc lắc ra kim cương! Tay thơm húp bạc tỷ đây rồi! 💵🎉',
        'Bảo rồi theo cầu là ăn ngập mặt! Anh em vào xin vía húp GTLM nào! 🚀💎'
    ];

    const normalWinPhrases = [
        'Có phúc lộc rồi ae ơi! Đoán chuẩn cửa, húp trọn GTLM tươi rói! 🎲✨',
        'Tiếng xúc xắc leng keng nghe sướng tai quá! Tiếp đà chiến thắng! 💰',
        'Húp nhẹ ván giao lưu này! Trận địa cầu đang chạy quá êm! 🍀🔥',
        'Ra chiêu chuẩn chỉ, húp thêm một ván ngọt ngào nữa! 🎯'
    ];

    const lossPhrases = [
        'Xúc xắc bẻ cầu một nhịp làm bay màu nhẹ, ván sau bắt lại ngay! 😤',
        'Hụt một phát xíu xiu, bình tĩnh ra chiêu mới gỡ lại gấp bội! 💨',
        'Cầu đang đảo chiều, ván sau đổi cửa húp lộc liền! 🔥🎲',
        'Bay màu một hiệp không sao cả, còn thở là còn gỡ, chuẩn bị tay mới! 🎯'
    ];

    const actionPhrases = [
        'Chuẩn bị ra chiêu mâm xúc xắc Sicbo, anh em sẵn sàng húp vía nào! 🎲✨',
        'Soi cầu Tài Xỉu cực bén, ván này tự tin húp đậm GTLM! 🍀🔥',
        'Lên đạn dàn trận xong xuôi, chuẩn bị lắc xúc xắc rực lửa! 🚀'
    ];

    function sendBotChat(type) {
        const now = Date.now();
        if (now - lastChatTime < 7000 && type !== 'big' && type !== 'win') return;

        let list = normalWinPhrases;
        if (type === 'big') list = bigWinPhrases;
        else if (type === 'loss') list = lossPhrases;
        else if (type === 'action') list = actionPhrases;

        const msg = list[Math.floor(Math.random() * list.length)];
        lastChatTime = now;

        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(51, 'bot_51', msg);
        }

        try {
            fetch('api_spectator.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'send_chat',
                    table_id: 51,
                    message: '🎙️ ' + msg
                })
            }).catch(() => {});
        } catch (e) {}

        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'bot_chat', text: msg, tableId: 51 }, '*');
            }
        } catch (e) {}
    }

    // ═══════════════════════════════════════════════════════
    // QUẢN LÝ VỐN & PHÂN BỔ CƯỢC
    // ═══════════════════════════════════════════════════════
    function getBalance() {
        const el = document.getElementById('balance-val');
        if (!el) return 50000000;
        const num = parseFloat(el.textContent.replace(/\./g, '').replace(/,/g, '').trim());
        return isNaN(num) || num <= 0 ? 50000000 : num;
    }

    function selectOptimalChipButton() {
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

        return document.querySelector(`.btn-quick-bet[data-val="${targetVal}"]`);
    }

    // ═══════════════════════════════════════════════════════
    // CHIẾN THUẬT CHỌN CỬA SICBO THÔNG MINH
    // ═══════════════════════════════════════════════════════
    const mainBetTypes = ['small', 'big', 'odd', 'even'];
    const luckyBetTypes = ['any_triple', 'total_9', 'total_10', 'total_11', 'total_12'];

    function pickBetItems() {
        const selectedTypes = [];

        // 1. Cửa chính (Tài / Xỉu / Chẵn / Lẻ)
        let primaryType = mainBetTypes[Math.floor(Math.random() * mainBetTypes.length)];
        if (lastBetType && winStreak >= 1) {
            // Nuôi tiếp cửa cũ (theo cầu son)
            primaryType = lastBetType;
        } else if (lastBetType && lossStreak >= 2) {
            // Bẻ cầu khi thua 2 ván
            if (lastBetType === 'small') primaryType = 'big';
            else if (lastBetType === 'big') primaryType = 'small';
            else if (lastBetType === 'odd') primaryType = 'even';
            else if (lastBetType === 'even') primaryType = 'odd';
        }

        lastBetType = primaryType;
        selectedTypes.push(primaryType);

        // 2. 25% cơ hội đánh thêm 1 cửa phụ để săn thưởng lớn
        if (Math.random() < 0.25) {
            const lucky = luckyBetTypes[Math.floor(Math.random() * luckyBetTypes.length)];
            selectedTypes.push(lucky);
        }

        const items = [];
        selectedTypes.forEach(t => {
            const el = document.querySelector(`.bet-item[data-type="${t}"]`);
            if (el) items.push(el);
        });

        return items;
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
        if (typeof isRolling !== 'undefined' && isRolling) return;

        const rollBtn = document.getElementById('roll-btn');
        if (!rollBtn || rollBtn.disabled) return;

        setBusy(true, 10000);
        roundCount++;

        try {
            if (roundCount % 6 === 0) {
                sendBotChat('action');
            }

            // BƯỚC 1: Chọn Chip cược
            const chipBtn = selectOptimalChipButton();
            if (chipBtn && !chipBtn.classList.contains('sel')) {
                await moveAndClick(chipBtn, 0.35);
            }

            // BƯỚC 2: Chọn các cửa cược Sicbo
            const betItems = pickBetItems();
            for (const item of betItems) {
                await moveAndClick(item, 0.3);
            }

            // BƯỚC 3: Rê chuột tới nút "LẮC XÚC XẮC" và click
            const activeRollBtn = document.getElementById('roll-btn');
            if (activeRollBtn && !activeRollBtn.disabled) {
                await moveAndClick(activeRollBtn, 0.45);

                // Quan sát kết quả từ huy hiệu
                setTimeout(() => {
                    const badge = document.getElementById('result-status-badge');
                    const isWin = badge && badge.style.display !== 'none' && badge.style.borderColor === 'rgb(241, 196, 15)';

                    if (isWin) {
                        winStreak++;
                        lossStreak = 0;
                        const amtText = $('#result-badge-amount').text();
                        if (amtText.includes('500') || amtText.includes('1.000')) {
                            sendBotChat('big');
                        } else {
                            sendBotChat('win');
                        }
                    } else if (badge && badge.style.display !== 'none') {
                        lossStreak++;
                        winStreak = 0;
                        sendBotChat('loss');
                    }

                    setBusy(false);
                }, 4500);
            } else {
                setBusy(false);
            }

        } catch (err) {
            console.error('[Bot 51] Lỗi lượt cược:', err);
            setBusy(false);
        }
    }

    // ═══════════════════════════════════════════════════════
    // TRIPLE-LAYER ANTI-STUCK WATCHDOG TIMER
    // ═══════════════════════════════════════════════════════
    let idleSeconds = 0;
    setInterval(() => {
        const rollBtn = document.getElementById('roll-btn');
        if (rollBtn && !rollBtn.disabled && (typeof isRolling === 'undefined' || !isRolling)) {
            idleSeconds += 2;
            if (idleSeconds >= 4) {
                console.warn('[Bot 51 Watchdog] Bàn Sicbo đang sẵn sàng mà bot nghỉ quá 4s, tự kích hoạt!');
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
        console.log('[Bot 51] 🎲 Thần Đổ Xúc Xắc 51 khởi động thành công!');
        setTimeout(() => { executeBotTurn(); }, 1800);
        setInterval(() => { executeBotTurn(); }, 5000);
    });

})();
