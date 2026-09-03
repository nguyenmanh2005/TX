/**
 * 🤖 Bot AI Cải Tiến Trí Thông Minh cho Game Mahjong Clash (ID: 37)
 * - Tự động quản lý vốn thông minh (Smart Bankroll Management).
 * - Phân tích chuỗi thắng / thua (Win/Loss Streaks) để tăng giảm mức cược.
 * - Di chuyển chuột ảo mượt mà chọn Chip phù hợp và bấm "XUẤT QUÂN".
 * - Tự động tương tác chat theo ngôn ngữ chuẩn của dự án (GTLM, húp, bay màu...).
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 37] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Đại Sư Mạt Chược');

    let botIsBusy = false;
    let recentResults = []; // true = win, false = loss
    let lastMoney = null;
    let lastChatTime = 0;

    // Chat messages theo chuẩn từ lóng dự án
    const winChatMessages = [
        'Húp đậm ván mạt chược này rồi anh em ơi! 🔥',
        'Bộ bài quá ảo diệu, Queen GTLM bay màu ngay! 😎',
        'Thần bài hiển linh, tiếp tục húp GTLM nào! 💰',
        'Cầu mạt chược đang đỏ rực, ai theo tôi không! 🚀',
        'Húp trọn điểm cao, phong độ quá đỉnh! 🎴'
    ];

    const loseChatMessages = [
        'Ván này bài Dealer cao tay quá, tạm thời bay màu nhẹ! 😅',
        'Không sao, để ván sau ra chiêu lớn gỡ lại ngay! 😤',
        'Chơi game giải trí, ván sau ta sẽ phục thù! 🎲',
        'Dừng 1 bước để tiến 2 bước nha anh em ơi! ✨'
    ];

    function sendBotChat(isWin) {
        const now = Date.now();
        // Chat giãn cách tối thiểu 15s để không bị spam
        if (now - lastChatTime < 15000) return;

        // Xác suất chat 45% mỗi ván
        if (Math.random() > 0.45) return;

        const list = isWin ? winChatMessages : loseChatMessages;
        const msg = list[Math.floor(Math.random() * list.length)];
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(37, 'bot_37', msg);
            lastChatTime = now;
        }
    }

    function getWinStreak() {
        let streak = 0;
        for (let i = recentResults.length - 1; i >= 0; i--) {
            if (recentResults[i] === true) streak++;
            else break;
        }
        return streak;
    }

    function getLossStreak() {
        let streak = 0;
        for (let i = recentResults.length - 1; i >= 0; i--) {
            if (recentResults[i] === false) streak++;
            else break;
        }
        return streak;
    }

    function readBalance() {
        const el = document.getElementById('balance-val');
        if (!el) return 50000000;
        const text = el.innerText || el.textContent || '';
        const num = parseInt(text.replace(/[^\d]/g, ''), 10);
        return isNaN(num) ? 50000000 : num;
    }

    function playSmartTurn() {
        if (botIsBusy) return;

        const playBtn = document.getElementById('play-btn');
        if (!playBtn || playBtn.disabled) return;

        const balance = readBalance();

        // Cập nhật tâm lý từ biến động số dư
        if (lastMoney !== null && balance !== lastMoney) {
            const won = balance > lastMoney;
            recentResults.push(won);
            if (recentResults.length > 10) recentResults.shift();
            sendBotChat(won);
        }
        lastMoney = balance;

        const winStreak = getWinStreak();
        const lossStreak = getLossStreak();

        // 🧠 CHIẾN THUẬT CHỌN CHIP THÔNG MINH
        // Mức cơ sở: 10K hoặc 50K
        let targetChipText = '10K';

        if (winStreak >= 3) {
            // Thắng liên tiếp 3 ván: Tự tin nâng lên 500K hoặc 1M
            targetChipText = Math.random() < 0.6 ? '500K' : '1M';
        } else if (winStreak >= 1) {
            // Thắng 1-2 ván: Tăng lên 50K hoặc 100K
            targetChipText = Math.random() < 0.6 ? '50K' : '100K';
        } else if (lossStreak >= 3) {
            // Thua sâu 3 ván: Thận trọng hạ về 10K để giữ vốn
            targetChipText = '10K';
        } else if (lossStreak >= 1) {
            // Vừa thua 1-2 ván: Tăng cược gỡ gạc (Martingale nhẹ lên 50K)
            targetChipText = '50K';
        }

        // Tìm nút chip tương ứng
        const quickBtns = Array.from(document.querySelectorAll('.quick-btn'));
        let targetChipBtn = quickBtns.find(b => (b.innerText || '').trim().toUpperCase() === targetChipText);
        if (!targetChipBtn && quickBtns.length > 0) {
            targetChipBtn = quickBtns[0]; // Mặc định 10K
        }

        botIsBusy = true;

        // BƯỚC 1: Di chuyển chuột ảo chọn Chip cược
        if (targetChipBtn) {
            BotVirtualCursor.moveToElement($(targetChipBtn), 0.8, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { targetChipBtn.click(); } catch (e) { }

                        // BƯỚC 2: Di chuyển chuột tới nút "XUẤT QUÂN"
                        setTimeout(() => {
                            BotVirtualCursor.moveToElement($(playBtn), 0.8, 0, () => {
                                setTimeout(() => {
                                    BotVirtualCursor.simulateClick(() => {
                                        try { playBtn.click(); } catch (e) { }

                                        // Chờ bài mở xong và lặp lại
                                        setTimeout(() => {
                                            botIsBusy = false;
                                        }, 4000);
                                    });
                                }, 300);
                            });
                        }, 400);
                    });
                }, 300);
            });
        } else {
            // Nếu không tìm thấy chip thì bấm thẳng Xuất Quân
            BotVirtualCursor.moveToElement($(playBtn), 0.8, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { playBtn.click(); } catch (e) { }
                        setTimeout(() => {
                            botIsBusy = false;
                        }, 4000);
                    });
                }, 300);
            });
        }
    }

    // Bắt đầu vòng lặp auto-play với tần suất hợp lý (mỗi 5.5 - 7.5 giây một ván)
    setInterval(() => {
        playSmartTurn();
    }, 5500 + Math.random() * 2000);

    // Bắt đầu ván đầu tiên sau 3 giây khi tải trang
    setTimeout(playSmartTurn, 3000);

})();
