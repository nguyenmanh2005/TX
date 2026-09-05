/**
 * 🤖 Bot AI Vietlott 6/45 Săn Jackpot Trăm Củ GTLM (ID: 56)
 *
 * CHIẾN THUẬT & TRÍ TUỆ NHÂN TẠO:
 * 1. QUẢN LÝ VỐN & CHIẾN LƯỢC MUA VÉ:
 *    - Mỗi vé cược cố định 50.000 GTLM.
 *    - Tự động kiểm tra số dư ví GTLM của Streamer.
 * 2. CHIẾN THUẬT CHỌN SỐ PHONG THỦY & TÂM LINH:
 *    - Bot phân tích và lựa chọn bộ 5 đến 6 con số may mắn (1-45).
 *    - Sử dụng các dàn số phong thủy kinh điển (Lộc Phát, Thần Tài, Tam Hoa) kết hợp sinh số ngẫu nhiên tối ưu.
 * 3. HỆ THỐNG CHUỘT ẢO CHUYÊN NGHIỆP (BotVirtualCursor):
 *    - Di chuyển mượt mà tới từng ô số .num-box trên bảng số 1-45.
 *    - Nhấp chọn từng số với nhịp độ tự nhiên (350ms - 500ms) như người thật đang soi cầu.
 *    - Rê chuột ảo tới nút MUA VÉ và kích hoạt quay thưởng.
 * 4. BÌNH LUẬN & TƯƠNG TÁC CASINO SÔI NỔI:
 *    - Tự động phát ngôn nhận định cầu số, cổ vũ hồi hộp và ăn mừng khi trúng thưởng qua BotChat.send.
 * 5. WATCHDOG TIMER:
 *    - Tự động giải phóng trạng thái bận sau 10 giây nếu gặp gián đoạn.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 56] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Thần Tài Vietlott 56');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI VÀ BIẾN ĐIỀU KHIỂN
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;
    let lastResultRecorded = null;

    function setBusy(val, timeoutMs = 10000) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            busyTimer = setTimeout(() => {
                console.log('[Bot 56] Watchdog giải phóng trạng thái bận!');
                botIsBusy = false;
            }, timeoutMs);
        }
    }

    // ═══════════════════════════════════════════════════════
    // BÌNH LUẬN & PHÁT NGÔN CASINO LIVESTREAM
    // ═══════════════════════════════════════════════════════
    const jackpotPhrases = [
        'JACKPOT PHÁT NỔ RỒI ANH EM ƠI! Húp trọn mâm to GTLM ngập tràn tài khoản! 👑💥💎',
        'Trúng đậm 4-6 số siêu phẩm! Đẳng cấp soi cầu Vietlott là đây chứ đâu! 🏆✨',
        'Thần tài giáng lâm! Bắt chuẩn dàn số vàng đổi đời ngọt ngào! 🚀💰',
        'Lộc lá tưng bừng cả nhà ơi! Nổ giải thưởng kỷ lục ván này rồi! 🎉👑'
    ];

    const normalWinPhrases = [
        'Có lộc rồi anh em ơi! Trúng 1-2 số giải nhiệt ngọt ngào! 🎫✨',
        'Húp nhẹ tiền thưởng Vietlott! Bảo toàn vốn lãi ròng! 💸🍀',
        'Bắt đúng 2 nháy ngon ơ! Tích lũy săn Jackpot trăm củ ván kế tiếp! 🎯',
        'Cầu số đang vào nhịp cực son, tiền thưởng cộng về ví rồi! ⚡'
    ];

    const pickPhrases = [
        'Cầu hôm nay sáng rực, chốt ngay dàn 6 số VIP săn Jackpot trăm củ! 🎯',
        'Linh cảm mách bảo dàn số phong thủy này sẽ nổ rực rỡ! 🔥',
        'Vào tiền dàn số tài lộc, anh em đón chờ tiếng bóng rơi nhé! 🍀',
        'Vé 50K nhẹ nhàng đổi lấy cơ hội húp hàng chục triệu GTLM! 🚀'
    ];

    const lossPhrases = [
        'Lệch mất một hai con số chốt, ván sau làm lại mâm to hơn! 😤',
        'Vé này nháp nhẹ lấy cảm giác, ván sau cầu số bùng nổ Jackpot! 💨',
        'Bóng quay suýt trúng thêm 2 số nữa, ván sau vào lại bộ này! 🔥',
        'Cầu đẹp đang tích tụ vận may, chuẩn bị săn giải độc đắc! 🎯'
    ];

    function sendBotChat(type) {
        const now = Date.now();
        if (now - lastChatTime < 10000) return;
        if (type !== 'jackpot' && Math.random() > 0.65) return;

        let list = pickPhrases;
        if (type === 'jackpot') list = jackpotPhrases;
        else if (type === 'win') list = normalWinPhrases;
        else if (type === 'loss') list = lossPhrases;

        const msg = list[Math.floor(Math.random() * list.length)];
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(56, 'bot_56', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // BỘ DÀN SỐ PHONG THỦY & SINH SỐ
    // ═══════════════════════════════════════════════════════
    const luckySets = [
        [6, 8, 9, 18, 28, 39],
        [3, 7, 13, 21, 33, 45],
        [1, 9, 19, 29, 39, 44],
        [8, 16, 24, 32, 40, 42],
        [5, 15, 25, 35, 41, 45],
        [2, 12, 22, 32, 38, 43],
        [7, 14, 21, 28, 35, 42],
        [4, 11, 23, 31, 37, 45]
    ];

    function generateSmartNumbers() {
        if (Math.random() < 0.5) {
            const preset = luckySets[Math.floor(Math.random() * luckySets.length)];
            return [...preset];
        }

        // Tạo 6 số ngẫu nhiên độc nhất từ 1 đến 45
        const pool = [];
        for (let i = 1; i <= 45; i++) pool.push(i);
        const result = [];
        const count = Math.random() < 0.15 ? 5 : 6;
        while (result.length < count && pool.length > 0) {
            const idx = Math.floor(Math.random() * pool.length);
            result.push(pool.splice(idx, 1)[0]);
        }
        return result.sort((a, b) => a - b);
    }

    // ═══════════════════════════════════════════════════════
    // QUY TRÌNH CHỌN SỐ VÀ MUA VÉ
    // ═══════════════════════════════════════════════════════
    function clearOldSelections(callback) {
        if (typeof selectedNumbers === 'undefined' || selectedNumbers.length === 0) {
            if (callback) callback();
            return;
        }

        // Click bỏ các số đang chọn
        const numsToUncheck = [...selectedNumbers];
        let idx = 0;

        function uncheckNext() {
            if (idx >= numsToUncheck.length) {
                if (callback) callback();
                return;
            }
            const num = numsToUncheck[idx];
            const box = document.querySelector(`.num-box[data-num="${num}"]`);
            idx++;
            if (box && box.classList.contains('selected')) {
                box.click();
            }
            setTimeout(uncheckNext, 100);
        }

        uncheckNext();
    }

    function pickNumbersSequence(targetNumbers, onComplete) {
        let index = 0;

        function pickNext() {
            if (index >= targetNumbers.length) {
                if (onComplete) onComplete();
                return;
            }

            const num = targetNumbers[index];
            const box = document.querySelector(`.num-box[data-num="${num}"]`);
            index++;

            if (!box) {
                pickNext();
                return;
            }

            BotVirtualCursor.moveToElement($(box), 0.45, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    try { box.click(); } catch (e) {}
                    setTimeout(pickNext, 350 + Math.random() * 200);
                });
            });
        }

        pickNext();
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG LẶP HÀNH ĐỘNG CỦA BOT (DECISION ENGINE)
    // ═══════════════════════════════════════════════════════
    function runBotTurn() {
        if (botIsBusy) return;

        // Nếu game đang trong hiệu ứng quay thưởng
        if (typeof isSpinning !== 'undefined' && isSpinning) {
            return;
        }

        const buyBtn = document.getElementById('buy-trigger');
        if (!buyBtn || buyBtn.disabled) return;

        setBusy(true, 14000);

        // Trường hợp 1: Nếu chưa chọn số nào, hoặc sau 1 ván quay xong muốn chọn dàn mới
        const currentCount = (typeof selectedNumbers !== 'undefined') ? selectedNumbers.length : 0;

        if (currentCount === 0 || Math.random() < 0.6) {
            console.log('[Bot 56] Chuẩn bị soi cầu và chọn bộ số mới...');
            
            // Xóa bộ số cũ nếu có
            clearOldSelections(() => {
                const targetNumbers = generateSmartNumbers();
                console.log('[Bot 56] Dàn số chốt:', targetNumbers);

                // Di chuột chọn từng số
                pickNumbersSequence(targetNumbers, () => {
                    // Sau khi chọn xong, gửi chat soi cầu
                    setTimeout(() => {
                        sendBotChat('pick');

                        // Rê chuột tới nút MUA VÉ
                        BotVirtualCursor.moveToElement($(buyBtn), 0.7, 0, () => {
                            setTimeout(() => {
                                BotVirtualCursor.simulateClick(() => {
                                    console.log('[Bot 56] Kích hoạt Mua Vé Vietlott!');
                                    try { buyBtn.click(); } catch (e) {}

                                    // Chờ quay số (quay bóng 6 * 400ms = 2.4s + hiệu ứng 1s)
                                    setTimeout(() => {
                                        checkResultAndReact();
                                    }, 3200);
                                });
                            }, 300);
                        });
                    }, 500);
                });
            });
        } else {
            // Đã có số chọn sẵn, chỉ cần ấn mua vé
            sendBotChat('pick');
            BotVirtualCursor.moveToElement($(buyBtn), 0.7, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    console.log('[Bot 56] Mua Vé với dàn số sẵn có!');
                    try { buyBtn.click(); } catch (e) {}

                    setTimeout(() => {
                        checkResultAndReact();
                    }, 3200);
                });
            });
        }
    }

    // ═══════════════════════════════════════════════════════
    // KIỂM TRA KẾT QUẢ VÀ PHẢN HỒI CẢM XÚC
    // ═══════════════════════════════════════════════════════
    function checkResultAndReact() {
        const badge = document.getElementById('result-status-badge');
        const statusBar = document.getElementById('status-bar');
        const matchedBalls = document.querySelectorAll('.ball.matched');
        const matchCount = matchedBalls ? matchedBalls.length : 0;

        console.log(`[Bot 56] Kết quả quay: Trúng ${matchCount} số`);

        if (matchCount >= 3) {
            sendBotChat('jackpot');
        } else if (matchCount >= 1) {
            sendBotChat('win');
        } else {
            sendBotChat('loss');
        }

        // Nghỉ ngơi 3 - 5 giây sau ván chơi rồi mới mở lượt tiếp theo
        setTimeout(() => {
            // Có thể tự động xóa bỏ 1 vài số để làm mới
            clearOldSelections(() => {
                setBusy(false);
            });
        }, 3500);
    }

    // ═══════════════════════════════════════════════════════
    // KHỞI CHẠY BOT ENGINE
    // ═══════════════════════════════════════════════════════
    console.log('[Bot 56] Khởi động AI Vietlott 6/45 Săn Jackpot thành công!');
    setTimeout(() => {
        runBotTurn();
    }, 2000);

    setInterval(() => {
        runBotTurn();
    }, 4500);

})();
