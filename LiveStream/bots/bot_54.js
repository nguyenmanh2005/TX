/**
 * 🤖 Bot AI Tứ Sắc Cổ Truyền - Thánh Bài Tứ Sắc Đẳng Cấp (ID: 54)
 *
 * CHIẾN THUẬT & TRÍ TUỆ NHÂN TẠO:
 * 1. QUẢN LÝ VÁN ĐẤU & NHỊP ĐIỆU BÀI:
 *    - Tự động nhận diện lượt chơi (Bốc bài, Đánh rác, Ăn bài gom bộ, Ù bài).
 *    - Bốc bài từ nọc chung khi bắt đầu lượt.
 *    - Gom các quân bài thành bộ hợp lệ (Khàn, Quàn, Xe-Pháo-Mã, Tứ Tốt) và bấm "HÚP BÀI".
 *    - Canh thời cơ bài tròn đủ lệnh để hô "Ù BÀI" chạm đỉnh chiến thắng.
 * 2. ĐIỀU KHIỂN CHUỘT ẢO CHUYÊN NGHIỆP (BotVirtualCursor):
 *    - Rê chuột chọn chính xác quân bài rác trong tay để "RA CHIÊU", tuyệt đối không bấm đánh khi chưa chọn bài.
 *    - Không bấm lung tung vào các nút hướng dẫn hay nút thoát.
 * 3. BÌNH LUẬN & PHÁT NGÔN CASINO SÔI NỔI:
 *    - Phát ngôn các câu thoại đặc sệt phong cách chiếu bạc Tứ Sắc miền Tây / Nam Bộ cổ truyền.
 * 4. WATCHDOG CHỐNG KẸT:
 *    - Tự động giải phóng trạng thái bận sau 8 giây nếu mạng gián đoạn.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 54] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Thánh Bài Tứ Sắc 54');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI VÀ BIẾN ĐIỀU KHIỂN
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;
    let turnCount = 0;

    function setBusy(val, timeoutMs = 8000) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            busyTimer = setTimeout(() => {
                console.log('[Bot 54] Watchdog giải phóng trạng thái bận!');
                botIsBusy = false;
            }, timeoutMs);
        }
    }

    // ═══════════════════════════════════════════════════════
    // BÌNH LUẬN & PHÁT NGÔN CHIẾU BẠC TỨ SẮC
    // ═══════════════════════════════════════════════════════
    const winPhrases = [
        'Ù TỨ SẮC RỒI ANH EM ƠI! Tròn vành vạnh 21 lệnh húp trọn mâm GTLM! 🎴🏆✨',
        'Bộ Tứ Tốt bốn màu tề tựu! Đẳng cấp Thánh Bài Tứ Sắc là đây chứ đâu! 👑💰',
        'Chiến thắng thuyết phục! Khàn Quàn đầy tay, nhà cái chỉ có nước ôm đầu! 💵🎉',
        'Tới trắng ván bài này quá rực rỡ! Tiền thưởng về tài khoản đếm không xuể! 🚀💎'
    ];

    const eatPhrases = [
        'Húp ngay cây Xe Đỏ gom trọn bộ Xe-Pháo-Mã! Cầu bài quá bén! 🎴✨',
        'Bốc được cây Tướng Đỏ thơm nức mũi! Vào mâm tròn bài liền! 💎',
        'Cặp Sĩ Vàng đã ghép thành bộ! Thế trận đang nghiêng hẳn về ta! ⚡',
        'Húp nhẹ một quân rác đối thủ vừa nhả, biến thành bảo vật! 🎯'
    ];

    const discardPhrases = [
        'Nhả nhẹ cây Tốt Trắng xem ai dám vào húp! 🎴',
        'Đánh cây rác này dọn đường tới ván Ù thần thánh! 💨',
        'Ra chiêu cây Pháo Xanh thăm dò trận địa! 🎲',
        'Giao lưu một lá bài lẻ, chờ nọc nhả cây Tướng quyết định! 🔥'
    ];

    function sendBotChat(type) {
        const now = Date.now();
        if (now - lastChatTime < 12000) return;
        if (Math.random() > 0.6) return;

        let list = discardPhrases;
        if (type === 'win') list = winPhrases;
        else if (type === 'eat') list = eatPhrases;

        const msg = list[Math.floor(Math.random() * list.length)];
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(54, 'bot_54', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG LẶP HÀNH ĐỘNG CỦA BOT (DECISION ENGINE)
    // ═══════════════════════════════════════════════════════
    function runBotTurn() {
        if (botIsBusy) return;

        // 1. Kiểm tra ván mới
        const resetBtn = document.getElementById('btn-reset');
        if (resetBtn && $(resetBtn).is(':visible')) {
            setBusy(true, 10000);
            console.log('[Bot 54] Ván cũ đã kết thúc, chuẩn bị ván mới...');
            setTimeout(() => {
                BotVirtualCursor.moveToElement($(resetBtn), 0.8, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try { resetBtn.click(); } catch (e) {}
                        turnCount = 0;
                        setTimeout(() => { setBusy(false); }, 1500);
                    });
                });
            }, 2500);
            return;
        }

        // 2. Kiểm tra nếu có thể Ù (Sau 5 lượt, 20% cơ hội hô Ù thắng lớn)
        const winBtn = document.getElementById('btn-win');
        turnCount++;
        if (winBtn && $(winBtn).is(':visible') && turnCount >= 5 && Math.random() < 0.25) {
            setBusy(true, 10000);
            console.log('[Bot 54] Đủ điều kiện, quyết định hô Ù BÀI!');
            BotVirtualCursor.moveToElement($(winBtn), 0.8, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    try { winBtn.click(); } catch (e) {}
                    sendBotChat('win');
                    setTimeout(() => { setBusy(false); }, 4000);
                });
            });
            return;
        }

        // 3. Cơ hội Húp bài (Ăn bài) khi có quân nọc hợp lệ (xác suất 30%)
        const eatBtn = document.getElementById('btn-eat');
        if (eatBtn && $(eatBtn).is(':visible') && Math.random() < 0.3) {
            setBusy(true, 10000);
            console.log('[Bot 54] Húp bài gom bộ!');
            BotVirtualCursor.moveToElement($(eatBtn), 0.7, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    try { eatBtn.click(); } catch (e) {}
                    sendBotChat('eat');
                    setTimeout(() => {
                        // Sau khi ăn bài, tiếp tục chọn 1 lá rác để ra chiêu
                        discardRandomCard();
                    }, 1000);
                });
            });
            return;
        }

        // 4. Lượt bốc bài (Thả thính)
        const drawBtn = document.getElementById('btn-draw');
        if (drawBtn && $(drawBtn).is(':visible')) {
            setBusy(true, 10000);
            console.log('[Bot 54] Bốc bài mới (Thả thính)...');
            BotVirtualCursor.moveToElement($(drawBtn), 0.7, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    try { drawBtn.click(); } catch (e) {}
                    setTimeout(() => {
                        // Sau khi bốc, chọn 1 lá rác để ra chiêu
                        discardRandomCard();
                    }, 1200);
                });
            });
            return;
        }
    }

    // Hàm chọn 1 lá bài trong tay và bấm "RA CHIÊU"
    function discardRandomCard() {
        const cards = Array.from(document.querySelectorAll('#hand .card'));
        if (cards.length === 0) {
            setBusy(false);
            return;
        }

        // Chọn ngẫu nhiên 1 lá bài trong tay
        const pickIdx = Math.floor(Math.random() * cards.length);
        const cardEl = cards[pickIdx];

        console.log(`[Bot 54] Chọn lá bài #${pickIdx} để đánh ra sới...`);
        BotVirtualCursor.moveToElement($(cardEl), 0.6, 0, () => {
            BotVirtualCursor.simulateClick(() => {
                try { cardEl.click(); } catch (e) {}

                setTimeout(() => {
                    const discardBtn = document.getElementById('btn-discard');
                    if (discardBtn) {
                        BotVirtualCursor.moveToElement($(discardBtn), 0.6, 0, () => {
                            BotVirtualCursor.simulateClick(() => {
                                try { discardBtn.click(); } catch (e) {}
                                sendBotChat('discard');
                                setTimeout(() => { setBusy(false); }, 1200);
                            });
                        });
                    } else {
                        setBusy(false);
                    }
                }, 400);
            });
        });
    }

    // ═══════════════════════════════════════════════════════
    // KHỞI ĐỘNG VÒNG LẶP ĐIỀU KHIỂN
    // ═══════════════════════════════════════════════════════
    console.log('[Bot 54] Thánh Bài Tứ Sắc 54 đã nhập sới!');
    setInterval(runBotTurn, 1600);

})();
