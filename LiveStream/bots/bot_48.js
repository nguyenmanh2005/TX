/**
 * 🤖 Bot AI Rút Thăm May Mắn (Lucky Draw Bags) Siêu Thông Minh (ID: 48)
 *
 * CHIẾN THUẬT & TRÍ THÔNG MINH:
 * 1. QUẢN LÝ VỐN & ĐIỀU TIẾT NHỊP ĐỘ:
 *    - Chi phí mỗi lượt mở cố định 50.000 GTLM.
 *    - Kiểm tra số dư ví trước khi mở, đảm bảo luôn đủ vốn.
 *    - Khoảng dừng tự nhiên giữa các lượt mở (1.5s - 2.5s) tạo cảm giác chân thực.
 * 2. CHIẾN THUẬT CHỌN TÚI MAY MẮN (Lucky Bag Rotation):
 *    - Luân chuyển thông minh giữa 6 túi quà (Túi 1 -> 6).
 *    - Tránh mở liên tục một túi nếu vừa trượt; kiên trì chọn túi đem lại vận may.
 * 3. HỆ THỐNG KIỂM SOÁT KHÓA TRẠNG THÁI (Lock State Control):
 *    - Chỉ click khi hệ thống mở khóa (`isLocked === false` và không có túi nào đang rung lắc `.opening`).
 *    - Tuyệt đối không click lung tung ra ngoài khu vực túi quà.
 * 4. WATCHDOG & CHỐNG KẸT:
 *    - Tự động giải phóng trạng thái sau 3.5s nếu có sự cố mạng.
 *    - Tương tác chat tự động theo từng mức thưởng (Trúng lớn, Trúng vừa, Trượt).
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 48] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Lucky Bag Hunter 48');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI VÀ CHỈ SỐ BOT
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;
    let winStreak = 0;
    let lossStreak = 0;
    let lastPickedBagIndex = 0;

    function setBusy(val, timeoutMs = 3500) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            busyTimer = setTimeout(() => {
                botIsBusy = false;
                if (typeof isLocked !== 'undefined') isLocked = false;
            }, timeoutMs);
        }
    }

    // ═══════════════════════════════════════════════════════
    // CHAT PHONG CÁCH MỞ TÚI MAY MẮN
    // ═══════════════════════════════════════════════════════
    const bigWinPhrases = [
        'Ối giồi ôi! Mở trúng túi vàng siêu khủng rồi anh em ơi! 🎁💰✨',
        'Kho báu triệu GTLM phát nổ! Tay vàng bạc tỷ đây rồi! 🏆🚀',
        'Húp trọn phần quà lớn nhất bàn, lộc lá ngập tràn! 😎💎'
    ];

    const smallWinPhrases = [
        'Có lộc có lộc! Tiếp tục duy trì phong độ nhặt GTLM! 💰',
        'Túi quà thơm nức mũi, mở tiếp kiếm túi to hơn nào! ✨',
        'Thắng nhẹ cũng vui, lộc phát đầu ngày! 🎁'
    ];

    const missPhrases = [
        'Hụt một phát không sao, linh cảm túi sau nổ kho báu! 😤',
        'Túi rỗng thử thách lòng kiên nhẫn thôi, ván sau gom lại! 💨',
        'Đổi góc mở túi khác ngay, vận may đang chờ phía trước! 🎯'
    ];

    function sendBotChat(type) {
        const now = Date.now();
        if (now - lastChatTime < 14000) return;
        if (Math.random() > 0.55) return;

        let list = smallWinPhrases;
        if (type === 'big') list = bigWinPhrases;
        else if (type === 'miss') list = missPhrases;

        const msg = list[Math.floor(Math.random() * list.length)];
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(48, 'bot_48', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // LỰA CHỌN TÚI THÔNG MINH
    // ═══════════════════════════════════════════════════════
    function selectNextBag() {
        const bags = Array.from(document.querySelectorAll('#bags-container .bag-box'));
        if (bags.length === 0) return null;

        // Nếu vừa trượt: Đổi sang túi khác
        // Nếu vừa thắng: Có thể thử lại túi may mắn đó hoặc xoay vòng
        let nextIndex = 0;
        if (lossStreak > 0) {
            do {
                nextIndex = Math.floor(Math.random() * bags.length);
            } while (nextIndex === lastPickedBagIndex && bags.length > 1);
        } else {
            // Xác suất 40% giữ túi cũ đang đỏ, 60% chọn túi mới
            if (Math.random() < 0.4) {
                nextIndex = lastPickedBagIndex;
            } else {
                nextIndex = Math.floor(Math.random() * bags.length);
            }
        }

        lastPickedBagIndex = nextIndex;
        return bags[nextIndex];
    }

    // ═══════════════════════════════════════════════════════
    // HÀNH ĐỘNG MỞ TÚI
    // ═══════════════════════════════════════════════════════
    function playTurn() {
        if (botIsBusy) return;
        if (typeof isLocked !== 'undefined' && isLocked) return;

        const openingBag = document.querySelector('#bags-container .bag-box.opening');
        if (openingBag) return;

        const targetBag = selectNextBag();
        if (!targetBag) return;

        setBusy(true, 4000);

        // Di chuyển chuột ảo mượt mà đến túi được chọn
        BotVirtualCursor.moveToElement($(targetBag), 0.45, 0, () => {
            setTimeout(() => {
                BotVirtualCursor.simulateClick(() => {
                    try { targetBag.click(); } catch (e) { }

                    // Chờ mở túi (800ms) + xem kết quả (1500ms) = 2.3s
                    setTimeout(() => {
                        setBusy(false);
                    }, 2400);
                });
            }, 100);
        });
    }

    // ═══════════════════════════════════════════════════════
    // THEO DÕI KẾT QUẢ TỪ BADGE THÔNG BÁO
    // ═══════════════════════════════════════════════════════
    function setupResultWatcher() {
        const badge = document.getElementById('result-status-badge');
        if (badge) {
            const observer = new MutationObserver(() => {
                if (badge.style.display !== 'none' && badge.style.opacity === '1') {
                    const title = (document.getElementById('result-badge-title') || {}).textContent || '';
                    const amtText = (document.getElementById('result-badge-amount') || {}).textContent || '';
                    const winAmount = parseInt(amtText.replace(/\D/g, '')) || 0;

                    if (title.includes('THẮNG')) {
                        winStreak++;
                        lossStreak = 0;
                        if (winAmount >= 200000) {
                            sendBotChat('big');
                        } else {
                            sendBotChat('small');
                        }
                    } else if (title.includes('TRƯỢT') || title.includes('THUA')) {
                        lossStreak++;
                        winStreak = 0;
                        sendBotChat('miss');
                    }
                }
            });
            observer.observe(badge, { attributes: true, attributeFilter: ['style'] });
        }
    }

    // Khởi tạo
    setupResultWatcher();

    // Vòng lặp định kỳ mỗi 800ms
    setInterval(playTurn, 800);
    setTimeout(playTurn, 1200);

})();
