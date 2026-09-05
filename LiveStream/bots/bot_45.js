/**
 * 🤖 Bot AI Red Dog Poker (Spread Betting) Thông Minh (ID: 45)
 *
 * CHIẾN THUẬT & TOÁN HỌC RED DOG:
 * 1. CHIẾN THUẬT RIDE / SHOW TỐI ƯU TOÁN HỌC:
 *    - Trong Red Dog, lá thứ 3 phải nằm GIỮA 2 lá đầu tiên (Khoảng / Spread).
 *    - Khoảng (Spread) từ 1 đến 11:
 *        + Spread >= 7: Tỉ lệ thắng > 54% -> TOÁN HỌC TỐI ƯU LÀ RIDE (Gấp đôi cược)!
 *        + Spread < 7 (hoặc = 0): Tỉ lệ thắng thấp (< 46%) -> CHỌN SHOW (Mở bài giữ nguyên cược để bảo toàn vốn)!
 * 2. QUẢN LÝ VỐN CHUYÊN NGHIỆP:
 *    - Mức cược tăng theo chuỗi thắng: 10K -> 50K -> 100K -> 500K.
 *    - Khi thua: Tự động hạ về 10K an toàn.
 * 3. HỆ THỐNG VÒNG LẶP & ĐIỀU KHIỂN:
 *    - Nhận biết 3 trạng thái: DEAL (Chia bài) -> DECIDE (Ra quyết định Ride/Show) -> NEW_GAME (Ván mới).
 *    - Không bao giờ click lung tung hay nhấn nút ngoài lượt.
 * 4. WATCHDOG & CHỐNG KẸT:
 *    - Tự động đóng popup cản trở, phục hồi trạng thái sau 3s nếu có sự cố.
 *    - Chat tương tác phong cách dân chơi Poker thượng lưu.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 45] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Red Dog Pro 45');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI VÀ CHỈ SỐ BOT
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;
    let winStreak = 0;
    let lossStreak = 0;

    function setBusy(val, timeoutMs = 3000) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            busyTimer = setTimeout(() => {
                botIsBusy = false;
            }, timeoutMs);
        }
    }

    // ═══════════════════════════════════════════════════════
    // ĐỌC KHOẢNG SPREAD HIỆN TẠI
    // ═══════════════════════════════════════════════════════
    function getCurrentSpread() {
        const spreadEl = document.getElementById('spreadValue');
        if (!spreadEl) return 0;
        return parseInt(spreadEl.textContent.trim()) || 0;
    }

    // ═══════════════════════════════════════════════════════
    // PHÁT HIỆN TRẠNG THÁI GAME
    // ═══════════════════════════════════════════════════════
    const STATE = { DEAL: 'deal', DECIDE: 'decide', NEW_GAME: 'new_game', NONE: 'none' };

    function detectState() {
        const dealBtn = document.getElementById('dealBtn');
        const rideBtn = document.getElementById('rideBtn');
        const showBtn = document.getElementById('showBtn');
        const newBtn = document.getElementById('newBtn');

        if (newBtn && window.getComputedStyle(newBtn).display !== 'none') {
            return STATE.NEW_GAME;
        }

        const showVisible = showBtn && window.getComputedStyle(showBtn).display !== 'none';
        const rideVisible = rideBtn && window.getComputedStyle(rideBtn).display !== 'none';
        if (showVisible || rideVisible) {
            return STATE.DECIDE;
        }

        if (dealBtn && window.getComputedStyle(dealBtn).display !== 'none') {
            return STATE.DEAL;
        }

        return STATE.NONE;
    }

    // ═══════════════════════════════════════════════════════
    // CHAT PHONG CÁCH CASINO RED DOG
    // ═══════════════════════════════════════════════════════
    const winPhrases = [
        'Lá thứ 3 lọt thỏm ngay giữa khoảng, húp trọn GTLM! 🃏💰',
        'Khoảng cách rộng quá, Ride gấp đôi ăn to anh em ơi! ✨',
        'Thần bài Red Dog ra chiêu, GTLM về đầy túi! 🏆',
        'Đọc tỉ lệ toán học chuẩn xác, thắng đẹp mắt! 🚀',
        'Chuỗi thắng lại tiếp diễn, ván sau bung lụa tiếp! 😎'
    ];

    const losePhrases = [
        'Lá thứ 3 lệch mất 1 nút, tiếc hùi hụi! Không sao ván sau gỡ lại! 😅',
        'Khoảng hẹp hơi đen, ván sau phục thù! 😤',
        'Thua nhẹ một ván không sờn lòng, quản lý vốn tốt là đường dài thắng! 💪',
        'Ván sau gặp khoảng rộng là tất tay húp lại cả vốn lẫn lời! 🎯'
    ];

    function sendBotChat(isWin) {
        const now = Date.now();
        if (now - lastChatTime < 14000) return;
        if (Math.random() > 0.5) return;

        const list = isWin ? winPhrases : losePhrases;
        const msg = list[Math.floor(Math.random() * list.length)];
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(45, 'bot_45', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // HÀNH ĐỘNG 1: CHỌN CHIP VÀ CHIA BÀI
    // ═══════════════════════════════════════════════════════
    function doDeal() {
        const dealBtn = document.getElementById('dealBtn');
        if (!dealBtn || dealBtn.disabled) return;

        setBusy(true, 3000);

        // Quản lý vốn thông minh
        let targetValue = 10000;
        if (winStreak >= 3) targetValue = 500000;
        else if (winStreak >= 2) targetValue = 100000;
        else if (winStreak >= 1) targetValue = 50000;
        else targetValue = 10000;

        const chips = Array.from(document.querySelectorAll('#chipSelector .chip'));
        let targetChip = chips.find(c => parseInt(c.getAttribute('data-value') || '0') === targetValue);
        if (!targetChip) {
            targetChip = chips.find(c => {
                const v = parseInt(c.getAttribute('data-value') || '0');
                return v > 0 && v <= 100000;
            }) || chips[0];
        }

        if (targetChip) {
            BotVirtualCursor.moveToElement($(targetChip), 0.4, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { targetChip.click(); } catch (e) { }

                        setTimeout(() => {
                            BotVirtualCursor.moveToElement($(dealBtn), 0.45, 0, () => {
                                setTimeout(() => {
                                    BotVirtualCursor.simulateClick(() => {
                                        try { dealBtn.click(); } catch (e) { }
                                        setTimeout(() => { setBusy(false); }, 700);
                                    });
                                }, 80);
                            });
                        }, 180);
                    });
                }, 80);
            });
        } else {
            BotVirtualCursor.moveToElement($(dealBtn), 0.45, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { dealBtn.click(); } catch (e) { }
                        setTimeout(() => { setBusy(false); }, 700);
                    });
                }, 80);
            });
        }
    }

    // ═══════════════════════════════════════════════════════
    // HÀNH ĐỘNG 2: RA QUYẾT ĐỊNH RIDE (GẤP ĐÔI) HOẶC SHOW (MỞ BÀI)
    // ═══════════════════════════════════════════════════════
    function doDecide() {
        const rideBtn = document.getElementById('rideBtn');
        const showBtn = document.getElementById('showBtn');

        const rideAvailable = rideBtn && window.getComputedStyle(rideBtn).display !== 'none';
        const showAvailable = showBtn && window.getComputedStyle(showBtn).display !== 'none';

        if (!rideAvailable && !showAvailable) return;

        setBusy(true, 3000);

        const spread = getCurrentSpread();

        // 🧠 CHIẾN THUẬT TOÁN HỌC CHUẨN XÁC:
        // Nếu khoảng từ 7 trở lên (tỉ lệ thắng > 54%) và có nút RIDE -> Chọn RIDE (Gấp đôi)!
        // Ngược lại nếu khoảng < 7 (tỉ lệ thắng < 46%) -> Chọn SHOW (Mở bài giữ nguyên cược)!
        let targetBtn = showBtn;
        if (spread >= 7 && rideAvailable) {
            targetBtn = rideBtn;
        } else if (showAvailable) {
            targetBtn = showBtn;
        } else if (rideAvailable) {
            targetBtn = rideBtn;
        }

        const thinkMs = 400 + Math.random() * 350;

        setTimeout(() => {
            BotVirtualCursor.moveToElement($(targetBtn), 0.4, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { targetBtn.click(); } catch (e) { }
                        setTimeout(() => { setBusy(false); }, 800);
                    });
                }, 80);
            });
        }, thinkMs);
    }

    // ═══════════════════════════════════════════════════════
    // HÀNH ĐỘNG 3: VÁN MỚI (NEW GAME)
    // ═══════════════════════════════════════════════════════
    function doNewGame() {
        const newBtn = document.getElementById('newBtn');
        if (!newBtn) return;

        setBusy(true, 3000);

        // Chờ 1.5s cho hiệu ứng thông báo hiển thị rõ ràng
        setTimeout(() => {
            BotVirtualCursor.moveToElement($(newBtn), 0.45, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { newBtn.click(); } catch (e) { }
                        setTimeout(() => { setBusy(false); }, 600);
                    });
                }, 80);
            });
        }, 1500);
    }

    // ═══════════════════════════════════════════════════════
    // THEO DÕI KẾT QUẢ TỪ BADGE
    // ═══════════════════════════════════════════════════════
    function setupResultWatcher() {
        const badge = document.getElementById('result-status-badge');
        if (badge) {
            const observer = new MutationObserver(() => {
                if (badge.style.display !== 'none' && badge.style.opacity === '1') {
                    const title = (document.getElementById('result-badge-title') || {}).textContent || '';
                    if (title.includes('THẮNG')) {
                        winStreak++;
                        lossStreak = 0;
                        sendBotChat(true);
                    } else if (title.includes('THUA')) {
                        lossStreak++;
                        winStreak = 0;
                        sendBotChat(false);
                    }
                }
            });
            observer.observe(badge, { attributes: true, attributeFilter: ['style'] });
        }
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG LẶP CHÍNH (Game Loop)
    // ═══════════════════════════════════════════════════════
    function playTurn() {
        if (botIsBusy) return;

        const state = detectState();
        if (state === STATE.DEAL) {
            doDeal();
        } else if (state === STATE.DECIDE) {
            doDecide();
        } else if (state === STATE.NEW_GAME) {
            doNewGame();
        }
    }

    // Khởi tạo
    setupResultWatcher();

    // Tần suất quét nhịp nhàng mỗi 700ms
    setInterval(playTurn, 700);
    setTimeout(playTurn, 1000);

})();
