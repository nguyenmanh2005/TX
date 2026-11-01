/**
 * 🎯 Bot Streamer Bàn 35: Thánh Soi Cầu Mini (Xổ Số Mini / Tài Xỉu Cân Cửa Số Học)
 * - TURBO AI SOI CẦU ĐỈNH CAO: Bắt Cầu Bệt, Bẻ Cầu Bệt, Cầu Đảo 1-1, Bắt Số Hot
 * - Phân tích Tài / Xỉu theo tổng điểm và chữ số đuôi của chuỗi số
 * - Quản lý vốn cược thích ứng theo chuỗi thắng/thua (Streak Hunter / Bankroll Protection)
 * - Chuột ảo Turbo Async 0.16s - 0.20s di chuyển & click dứt khoát
 * - Triple-Layer Watchdog chống kẹt 24/7
 * - Lời thoại chém gió chuẩn Casino Rule 5.3 cực cuốn
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 35] BotVirtualCursor chưa sẵn sàng!');
        return;
    }

    BotVirtualCursor.init('Thánh Soi Cầu Mini 🎯🎲');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI & AI ENGINE COMMANDER
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimestamp = 0;
    let lastChatTime = 0;
    let lastKnownBalance = null;
    let isSpinningState = false;

    const aiCommander = {
        consecutiveWins: 0,
        consecutiveLosses: 0,
        streakCount: 0,
        lastTrend: null,       // 'TAI' hoặc 'XIU'
        currentStrategy: 'STREAK_HUNTER', // 'STREAK_HUNTER', 'BRIDGE_BREAKER', 'ALTERNATING', 'HOT_DIGIT'
        historyNumbers: [],    // Danh sách 15 chuỗi số gần nhất
        lastTargetNumber: '',
        totalSpins: 0
    };

    // ═══════════════════════════════════════════════════════
    // BÌNH LUẬN CASINO CHUẨN RULE 5.3
    // ═══════════════════════════════════════════════════════
    const CASINO_CHATS = {
        streak_hunter: [
            "Cầu bệt {trend} đang vào guồng cực mượt! Ra chiêu theo cầu bộ số {num} húp trọn GTLM! 🎯⚡",
            "Cầu bệt {trend} tay thứ {streak} liên tiếp! Không bẻ khi cầu đang đẹp, khóa ngay dàn số {num}! 💎🔥",
            "Theo chân cầu bệt {trend}: Dồn toàn lực vào bộ số may mắn {num} đón Jackpot về nick! 🚀💰",
            "Bắt nhịp cầu bệt chuẩn sách giáo khoa, ván này tự tin húp đậm GTLM! 🌟💎"
        ],
        bridge_breaker: [
            "Cầu bệt {trend} đã chạy {streak} tay quá giới hạn! Tiếng bạc này tôi BẺ CẦU sang {newTrend} với số {num}! ⚡👑",
            "Đỉnh cao soi cầu là biết điểm bẻ: Bẻ gãy cầu {trend}, ra chiêu đón đầu {newTrend} bộ số {num}! 🛡️🔥",
            "Bẻ cầu thế kỷ! Toàn bộ sòng cùng tôi đón cú quay xe lịch sử sang {newTrend}! 🏆💰",
            "Cảm giác số học mách bảo: Nhịp này phải bẻ cầu {newTrend} mới húp trọn hũ lớn! 🎯💎"
        ],
        alternating: [
            "Cầu đảo 1-1 (Tài - Xỉu) đang chạy nhịp nhàng! Đón đầu nhịp {trend} với bộ số tài lộc {num}! 🔄✨",
            "Bắt võ cầu nhảy đảo: Ra chiêu chuẩn xác hướng về {trend} dàn số {num} húp nhẹ GTLM! 🎯🎲"
        ],
        hot_digit: [
            "Radar quét chữ số vàng: Dồn số hot {digits} tạo thành siêu phẩm {num} bắn phá trận địa! 🌟🔥",
            "Dàn số hội tụ tinh hoa số học! Khóa chặt mã {num} rước bão tiền thưởng về nick! 💎🚀"
        ],
        win: [
            "TRÚNG SỐ RỒI ANH EM ƠI! HÚP TRỌN GIẢI THƯỞNG XỔ SỐ MINI! Tiền nhảy ting ting đầy nick! 🏆🎉🤑",
            "SOI CẦU CHUẨN TỪNG MILIMET! Gáy khét lẹt cả sòng live hôm nay anh em ơi! 👑💎💰",
            "Cơm gạo về nick rực rỡ! Thắng áp đảo trên trận địa số học! 🌟🎉✨"
        ],
        loss: [
            "Lệch mất vài nhịp chữ số, bay màu nhẹ ván này! Không sao, còn thở là còn bẻ cầu! 😅🔄",
            "Bốc hơi chút ít GTLM, xốc lại tinh thần soi lại bảng cầu phục thù ván mới! 💪🎯"
        ]
    };

    function sendChat(type, replaceObj = {}) {
        const now = Date.now();
        if (now - lastChatTime < 2800) return;
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.streak_hunter;
            let msg = list[Math.floor(Math.random() * list.length)];
            for (let k in replaceObj) {
                msg = msg.replace(new RegExp(`\\{${k}\\}`, 'g'), replaceObj[k]);
            }
            BotChat.send(35, 'Thánh Soi Cầu Mini 🎯🎲', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // THUẬT TOÁN SOI CẦU MINI / TÀI XỈU SỐ HỌC
    // ═══════════════════════════════════════════════════════

    // Đánh giá 1 chuỗi 5 số là TÀI hay XỈU
    // Dựa trên: Tổng 5 số (>= 23 là TÀI, < 23 là XỈU) kết hợp đuôi số
    function evaluateNumberTrend(numStr) {
        if (!numStr || numStr.length !== 5) return 'TAI';
        let sum = 0;
        for (let i = 0; i < 5; i++) sum += parseInt(numStr[i]) || 0;
        const lastDigit = parseInt(numStr[4]) || 0;

        // Nếu tổng >= 23 hoặc đuôi từ 5-9 -> TÀI (Đỏ)
        // Nếu tổng < 23 và đuôi 0-4 -> XỈU (Xanh)
        return (sum >= 23 || lastDigit >= 5) ? 'TÀI' : 'XỈU';
    }

    // Đọc lịch sử từ bảng kết quả trên giao diện
    function parseHistoryFromUI() {
        const rows = document.querySelectorAll('#history-body tr');
        const results = [];
        rows.forEach(r => {
            const firstTd = r.querySelector('td');
            if (firstTd) {
                const txt = firstTd.innerText.trim();
                if (/^\d{5}$/.test(txt)) results.push(txt);
            }
        });
        if (results.length > 0) {
            aiCommander.historyNumbers = results.slice(0, 15);
        }
    }

    // Thuật toán sinh 5 chữ số thông minh dựa trên chiến thuật cầu
    function generateSmartNumber() {
        parseHistoryFromUI();

        // 1. Phân tích chuỗi cầu gần đây
        const trends = aiCommander.historyNumbers.map(n => evaluateNumberTrend(n));
        let currentStreak = 0;
        let streakTrend = trends[0] || (Math.random() < 0.5 ? 'TÀI' : 'XỈU');

        for (let i = 0; i < trends.length; i++) {
            if (trends[i] === streakTrend) currentStreak++;
            else break;
        }

        aiCommander.streakCount = Math.max(1, currentStreak);
        aiCommander.lastTrend = streakTrend;

        // 2. Quyết định chiến thuật: Theo cầu bệt hay Bẻ cầu
        let targetTrend = streakTrend;

        if (currentStreak >= 4) {
            // Cầu bệt quá dài (>= 4 ván) -> KÍCH HOẠT BẺ CẦU (BRIDGE_BREAKER)
            aiCommander.currentStrategy = 'BRIDGE_BREAKER';
            targetTrend = (streakTrend === 'TÀI') ? 'XỈU' : 'TÀI';
        } else if (currentStreak >= 2) {
            // Cầu bệt đang trong đà đẹp (2-3 ván) -> THEO CẦU BỆT (STREAK_HUNTER)
            aiCommander.currentStrategy = 'STREAK_HUNTER';
            targetTrend = streakTrend;
        } else if (trends.length >= 3 && trends[0] !== trends[1] && trends[1] !== trends[2]) {
            // Cầu đảo 1-1 (Tài -> Xỉu -> Tài) -> BẮT CẦU ĐẢO (ALTERNATING)
            aiCommander.currentStrategy = 'ALTERNATING';
            targetTrend = (trends[0] === 'TÀI') ? 'XỈU' : 'TÀI';
        } else {
            // Cầu tự do -> BẮT SỐ HOT (HOT_DIGIT)
            aiCommander.currentStrategy = 'HOT_DIGIT';
            targetTrend = (Math.random() < 0.5) ? 'TÀI' : 'XỈU';
        }

        // 3. Thống kê chữ số hot xuất hiện nhiều trong lịch sử
        const digitCounts = Array(10).fill(0);
        aiCommander.historyNumbers.forEach(n => {
            for (let ch of n) digitCounts[parseInt(ch)]++;
        });

        // 4. Sinh bộ số 5 chữ số thỏa mãn targetTrend
        let digits = [];
        if (targetTrend === 'TÀI') {
            // TÀI: Ưu tiên các chữ số lớn (5, 6, 7, 8, 9)
            for (let i = 0; i < 5; i++) {
                if (Math.random() < 0.65) {
                    digits.push(Math.floor(Math.random() * 5) + 5); // 5-9
                } else {
                    digits.push(Math.floor(Math.random() * 10));
                }
            }
        } else {
            // XỈU: Ưu tiên các chữ số nhỏ (0, 1, 2, 3, 4)
            for (let i = 0; i < 5; i++) {
                if (Math.random() < 0.65) {
                    digits.push(Math.floor(Math.random() * 5)); // 0-4
                } else {
                    digits.push(Math.floor(Math.random() * 10));
                }
            }
        }

        // Đảm bảo chữ số đầu tiên khác 0 nếu muốn số đẹp 5 chữ số
        if (digits[0] === 0) digits[0] = Math.floor(Math.random() * 9) + 1;

        const generatedNumber = digits.join('');
        aiCommander.lastTargetNumber = generatedNumber;

        return {
            number: generatedNumber,
            trend: streakTrend,
            targetTrend: targetTrend,
            streak: aiCommander.streakCount,
            strategy: aiCommander.currentStrategy
        };
    }

    // ═══════════════════════════════════════════════════════
    // THEO DÕI SỐ DƯ & KẾT QUẢ VÁN CHƠI
    // ═══════════════════════════════════════════════════════
    function getBalance() {
        const el = document.getElementById('balance-val') || document.getElementById('userMoney') || document.querySelector('.money-text');
        if (!el) return 50000000;
        return parseInt(el.innerText.replace(/\D/g, '')) || 0;
    }

    function checkOutcomeAndPsychology() {
        const currentBal = getBalance();
        if (lastKnownBalance !== null && currentBal !== lastKnownBalance) {
            const isWin = currentBal > lastKnownBalance;
            if (isWin) {
                aiCommander.consecutiveWins++;
                aiCommander.consecutiveLosses = 0;
                sendChat('win');
            } else {
                aiCommander.consecutiveLosses++;
                aiCommander.consecutiveWins = 0;
                if (Math.random() < 0.5) sendChat('loss');
            }
        }
        lastKnownBalance = currentBal;
    }

    // ═══════════════════════════════════════════════════════
    // CHUỘT ẢO TURBO (0.16s - 0.20s)
    // ═══════════════════════════════════════════════════════
    function fastMoveAndClick(el, duration = 0.18) {
        return new Promise((resolve) => {
            const $el = $(el);
            if (!$el.length || $el.is(':hidden')) return resolve(false);

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
                        } catch (e) {
                            $el.trigger('click');
                        }
                        if (!hasResolved) {
                            hasResolved = true;
                            clearTimeout(safetyTimer);
                            resolve(true);
                        }
                    });
                });
            } catch (err) {
                try { $el[0].click(); } catch (e) {}
                if (!hasResolved) {
                    hasResolved = true;
                    clearTimeout(safetyTimer);
                    resolve(true);
                }
            }
        });
    }

    // ═══════════════════════════════════════════════════════
    // CHU TRÌNH ĐẶT SỐ TURBO (PLAY LOTTERY CYCLE)
    // ═══════════════════════════════════════════════════════
    async function executeLotteryTurn() {
        if (botIsBusy || isSpinningState) return;

        const inputField = document.getElementById('user-number');
        const submitBtn = document.getElementById('btn-submit');

        if (!inputField || !submitBtn || submitBtn.disabled) return;

        botIsBusy = true;
        busyTimestamp = Date.now();

        try {
            checkOutcomeAndPsychology();

            // 1. Phân tích cầu và sinh 5 chữ số thông minh
            const plan = generateSmartNumber();

            // 2. Bình luận chat thể hiện trí tuệ soi cầu
            if (plan.strategy === 'STREAK_HUNTER') {
                if (Math.random() < 0.45) sendChat('streak_hunter', { trend: plan.trend, streak: plan.streak, num: plan.number });
            } else if (plan.strategy === 'BRIDGE_BREAKER') {
                sendChat('bridge_breaker', { trend: plan.trend, newTrend: plan.targetTrend, streak: plan.streak, num: plan.number });
            } else if (plan.strategy === 'ALTERNATING') {
                if (Math.random() < 0.4) sendChat('alternating', { trend: plan.targetTrend, num: plan.number });
            } else {
                if (Math.random() < 0.35) sendChat('hot_digit', { digits: plan.number.slice(-2), num: plan.number });
            }

            // 3. Rê chuột đến ô input và điền số (0.16s)
            await fastMoveAndClick(inputField, 0.16);
            inputField.value = plan.number;
            inputField.dispatchEvent(new Event('input', { bubbles: true }));

            // 4. Rê chuột đến nút "Quay số ngay" và click dứt khoát (0.18s)
            const liveSubmitBtn = document.getElementById('btn-submit');
            if (liveSubmitBtn && !liveSubmitBtn.disabled) {
                isSpinningState = true;
                await fastMoveAndClick(liveSubmitBtn, 0.18);

                // Hiệu ứng quay số trong live_36.php mất khoảng 500ms - 800ms
                // Chờ 1.6s để người xem thưởng thức kết quả và nhận badge thưởng
                setTimeout(() => {
                    isSpinningState = false;
                    botIsBusy = false;
                    checkOutcomeAndPsychology();
                }, 1600 + Math.random() * 400);
            } else {
                botIsBusy = false;
                isSpinningState = false;
            }

        } catch (err) {
            console.error('[Bot 35 Error]:', err);
            botIsBusy = false;
            isSpinningState = false;
        }
    }

    // ═══════════════════════════════════════════════════════
    // TRIPLE-LAYER WATCHDOG & TỐI ƯU NHỊP ĐỘ (POLLING)
    // ═══════════════════════════════════════════════════════
    setInterval(() => {
        // Tầng 1: Tự đóng SweetAlert2 nếu có lỗi phát sinh
        const swalConfirm = document.querySelector('.swal2-confirm');
        if (swalConfirm && swalConfirm.offsetParent !== null) {
            try { swalConfirm.click(); } catch (e) {}
            if (typeof Swal !== 'undefined' && Swal.close) Swal.close();
        }

        // Tầng 2: Watchdog chống kẹt nếu busy kéo dài quá 4.5s
        if (botIsBusy && (Date.now() - busyTimestamp > 4500)) {
            console.warn('[Bot 35 Watchdog] Tự động giải phóng trạng thái kẹt!');
            botIsBusy = false;
            isSpinningState = false;
            const btn = document.getElementById('btn-submit');
            if (btn && btn.disabled) {
                btn.disabled = false;
                btn.textContent = '🎰 Quay số ngay';
            }
        }

        // Tầng 3: Tự động chạy hiệp tiếp theo khi sẵn sàng
        if (!botIsBusy && !isSpinningState) {
            const submitBtn = document.getElementById('btn-submit');
            if (submitBtn && !submitBtn.disabled) {
                executeLotteryTurn();
            }
        }
    }, 700);

    // Mở màn khi Streamer lên sóng
    setTimeout(() => {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(35, 'Thánh Soi Cầu Mini 🎯🎲', 'Chào mừng toàn thể anh em đến với Trận Địa Xổ Số Mini Bàn 35! Thánh soi cầu vào việc, cùng tôi bắt cầu bệt bẻ cầu húp trọn GTLM nhé! 🎯🔥🚀');
        }
        executeLotteryTurn();
    }, 1500);

    console.log('[Bot 35] Thánh Soi Cầu Mini TURBO AI Pro khởi động thành công!');
})();
