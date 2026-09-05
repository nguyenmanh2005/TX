/**
 * 🤖 Bot AI Đoán Số Thông Minh (ID: 41 - Con Số May Mắn)
 *
 * THUẬT TOÁN BINARY SEARCH:
 * - Server luôn trả hint "LỚN HƠN" hoặc "NHỎ HƠN" khi đoán sai.
 * - Bot thu hẹp khoảng [low, high] theo binary search → tìm ra số đúng tối đa trong 7 lần.
 * - Khi khoảng cách còn ≤ 10 (gần đúng), bot đoán chính xác midpoint để ăn x2.
 * - Quản lý vốn thông minh: bet nhỏ khi đang tìm, bet lớn khi tự tin gần đúng.
 * - Chat theo phong cách dự án GTLM sau mỗi kết quả.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 41] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Thám Tử Số');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI BOT
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;

    // Binary Search state
    let low = 1;
    let high = 100;
    let lastGuess = null;
    let consecutiveWins = 0;
    let consecutiveLosses = 0;
    let lastChatTime = 0;
    let isNewGame = true; // true khi chưa có hint nào từ server

    function setBusy(val) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            busyTimer = setTimeout(() => { botIsBusy = false; }, 2000);
        }
    }

    function resetSearch() {
        low = 1;
        high = 100;
        lastGuess = null;
        isNewGame = true;
    }

    // ═══════════════════════════════════════════════════════
    // PHÂN TÍCH GỢI Ý TỪ SERVER
    // ═══════════════════════════════════════════════════════
    function parseHintFromStatus() {
        const statusEl = document.getElementById('status-msg');
        if (!statusEl || statusEl.style.display === 'none') return null;

        const txt = (statusEl.textContent || '').toUpperCase();

        if (txt.includes('CHÍNH XÁC') || txt.includes('THẮNG')) {
            return 'WIN';
        }
        if (txt.includes('RẤT GẦN') || txt.includes('CÁCH')) {
            return 'NEAR_WIN';
        }
        if (txt.includes('LỚN HƠN')) {
            // Số bí mật LỚN HƠN số vừa đoán → low = lastGuess + 1
            return 'HIGHER';
        }
        if (txt.includes('NHỎ HƠN')) {
            // Số bí mật NHỎ HƠN số vừa đoán → high = lastGuess - 1
            return 'LOWER';
        }
        return null;
    }

    // ═══════════════════════════════════════════════════════
    // TÍNH SỐ ĐOÁN TIẾP THEO (BINARY SEARCH)
    // ═══════════════════════════════════════════════════════
    function computeNextGuess(hint) {
        if (hint === 'HIGHER' && lastGuess !== null) {
            low = lastGuess + 1;
        } else if (hint === 'LOWER' && lastGuess !== null) {
            high = lastGuess - 1;
        }

        // Bảo vệ tránh low > high
        if (low > high) resetSearch();

        return Math.round((low + high) / 2);
    }

    // ═══════════════════════════════════════════════════════
    // QUẢN LÝ VỐN THÔNG MINH
    // ═══════════════════════════════════════════════════════
    function chooseBetAmount(hint) {
        const rangeSize = high - low + 1;
        // Khi khoảng hẹp (≤ 15), tự tin bet lớn hơn vì gần đúng
        if (rangeSize <= 5) return 100000;   // Rất gần, bet 100K
        if (rangeSize <= 15) return 50000;   // Khá gần, bet 50K
        if (consecutiveWins >= 3) return 100000; // Chuỗi thắng → bet nhiều hơn
        return 10000; // Mặc định bet 10K khi đang tìm
    }

    // ═══════════════════════════════════════════════════════
    // CHAT
    // ═══════════════════════════════════════════════════════
    const winPhrases = [
        'Thám tử số xuất chiêu! Đoán trúng x10 rồi húp lúa! 🎯',
        'Binary search ăn GTLM thật, anh em ơi! 💰',
        'Bot tự học tự đoán, chiến thắng về tay! 🏆',
        'Số bí mật không qua được tay thám tử! 🔍',
        'Ăn to rồi anh em, GTLM về ví! 🚀'
    ];

    const nearWinPhrases = [
        'Gần đúng rồi, ăn x2 nhẹ nhàng! 🔥',
        'Tay nghề cao, sai ít vẫn có thưởng! ✨',
        'Cách vài số thôi, vẫn húp được rồi! 💎'
    ];

    const losePhrases = [
        'Thua nhẹ thôi, binary search sắp ra số đúng! 💪',
        'Bay màu một tí nhé, ván sau bung lụa! 😅',
        'Đang thu hẹp phạm vi, sắp tóm được số bí mật! 🔍',
        'Thua mà không nản, ra chiêu tiếp thôi! 😤'
    ];

    function sendBotChat(type) {
        const now = Date.now();
        if (now - lastChatTime < 12000) return;
        if (Math.random() > 0.55) return;

        let list;
        if (type === 'win') list = winPhrases;
        else if (type === 'near') list = nearWinPhrases;
        else list = losePhrases;

        const msg = list[Math.floor(Math.random() * list.length)];
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(41, 'bot_41', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG LẶP CHÍNH
    // ═══════════════════════════════════════════════════════
    function playTurn() {
        if (botIsBusy) return;

        const btnGuess = document.getElementById('btn-guess');
        const btnNew = document.getElementById('btn-new');
        const numInput = document.getElementById('guess-number');
        const betInput = document.getElementById('bet-amount');

        if (!btnGuess || !numInput || !betInput) return;
        if (btnGuess.disabled) return;

        // GIAI ĐOẠN 1: Đọc hint từ lần đoán trước
        const hint = parseHintFromStatus();

        if (hint === 'WIN' || hint === 'NEAR_WIN') {
            // Vừa thắng → reset search để chuẩn bị ván mới
            if (hint === 'WIN') {
                sendBotChat('win');
                consecutiveWins++;
                consecutiveLosses = 0;
            } else {
                sendBotChat('near');
                consecutiveWins++;
                consecutiveLosses = 0;
            }
            resetSearch();

            // Chờ 600ms rồi bắt đầu ván mới
            setBusy(true);
            setTimeout(() => {
                const statusEl = document.getElementById('status-msg');
                if (statusEl) statusEl.style.display = 'none';
                setBusy(false);
            }, 600);
            return;
        }

        if (hint === 'HIGHER' || hint === 'LOWER') {
            // Áp dụng hint để cập nhật phạm vi
            if (hint === 'HIGHER') {
                low = Math.max(low, (lastGuess || 0) + 1);
            } else {
                high = Math.min(high, (lastGuess || 101) - 1);
            }
            sendBotChat('lose');
            consecutiveLosses++;
            consecutiveWins = 0;
        }

        // GIAI ĐOẠN 2: Tính số đoán tiếp theo (không truyền hint vì đã cập nhật ở trên)
        const nextGuess = computeNextGuess(null);
        const betAmount = chooseBetAmount(hint);

        setBusy(true);

        // Bấm chọn số đoán
        BotVirtualCursor.moveToElement($(numInput), 0.4, 0, () => {
            setTimeout(() => {
                BotVirtualCursor.simulateClick(() => {
                    numInput.value = nextGuess;
                    lastGuess = nextGuess;

                    // Điền số GTLM cược
                    betInput.value = betAmount;

                    // Di chuyển tới nút Đoán và bấm
                    setTimeout(() => {
                        BotVirtualCursor.moveToElement($(btnGuess), 0.45, 0, () => {
                            setTimeout(() => {
                                BotVirtualCursor.simulateClick(() => {
                                    try { btnGuess.click(); } catch (e) { }
                                    // Chờ server phản hồi
                                    setTimeout(() => {
                                        setBusy(false);
                                    }, 500);
                                });
                            }, 100);
                        });
                    }, 120);
                });
            }, 80);
        });
    }

    // Khởi động - chạy mỗi 900ms
    setInterval(playTurn, 900);
    setTimeout(playTurn, 1000);

})();
