/**
 * 🤖 BOT STREAMER PRO V3 - THẦN QUAY HŨ (SLOT MACHINE NEON FORTUNE)
 * - Tự động nhận diện trạng thái: Chờ quay -> Chọn chip cược -> Nhấn QUAY NGAY -> Xem nổ hũ.
 * - Quản lý vốn thông minh: Đa dạng hóa phỉnh cược (50K, 100K, 500K, 1M, 5M).
 * - Chuột ảo GSAP di chuyển mượt mà 0.25s - 0.3s với hiệu ứng click nảy rõ nét.
 */
if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Thần Quay Hũ 🎰💎");
    let botBusy = false;
    let lastKnownBalance = null;

    const CASINO_CHATS = {
        spin: [
            "Kéo cần gạt Neon! Ra chiêu quay hũ săn Jackpot nổ tung màn hình! 🎰✨🔥",
            "Bảng quay đang nóng rực! Căn đúng nhịp giật hũ rước lộc GTLM về nick! 💎🚀",
            "Ra chiêu đều tay, hũ Neon 2077 ván này không nổ thì ván sau phải nổ! 🌟💰",
            "Tập trung cao độ! Cầu may mắn đang đến, húp trọn Jackpot nào anh em! 👑🎰"
        ],
        win: [
            "NỔ HŨ TO RỒI ANH EM ƠI! Húp trọn Jackpot GTLM ngập tràn nick! 🏆🎰🤑",
            "Dây biểu tượng thẳng hàng! Gáy khét lẹt cả sảnh quay hôm nay! 👑🔥💰",
            "Tiền nổ tưng bừng! Húp đậm quà tặng vũ trụ slot Neon! 💎🎉"
        ],
        loss: [
            "Lệch mất một biểu tượng, bay màu nhẹ ván quay này! 😅🔄",
            "Hũ đang tích tụ năng lượng, kiên trì ra chiêu săn cú nổ lịch sử! 💪🎰",
            "Bay màu vài đồng lẻ không xi nhê, chuẩn bị cho cú nổ Jackpot siêu to! 🛡️⚡"
        ]
    };

    function sendChat(type) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.spin;
            const msg = list[Math.floor(Math.random() * list.length)];
            BotChat.send(8, 'Thần Quay Hũ 🎰💎', msg);
        }
    }

    function getBotBalance() {
        const balanceEl = document.getElementById('balance-txt');
        if (!balanceEl) return 50000000;
        const bal = parseInt(balanceEl.innerText.replace(/\D/g, '')) || 0;
        if (lastKnownBalance !== null) {
            if (bal > lastKnownBalance + 50000) {
                if (Math.random() < 0.6) sendChat('win');
            } else if (bal < lastKnownBalance - 200000) {
                if (Math.random() < 0.3) sendChat('loss');
            }
        }
        lastKnownBalance = bal;
        return bal;
    }

    function runSlotStreamerBot() {
        if (botBusy) return;

        const spinBtn = document.getElementById('spin-trigger');
        if (!spinBtn) {
            setTimeout(runSlotStreamerBot, 500);
            return;
        }

        // ── 1. NẾU MÁY SLOT ĐANG QUAY: BOT CHỜ VÀ THEO DÕI CÁC CUỘN ──
        if (spinBtn.disabled || $(spinBtn).is(':disabled') || (typeof spinning !== 'undefined' && spinning)) {
            setTimeout(runSlotStreamerBot, 400);
            return;
        }

        // ── 2. NẾU MÁY RẢNH: CHUẨN BỊ VÁN QUAY MỚI ──
        botBusy = true;
        const balance = getBotBalance();

        // Lấy danh sách phỉnh cược
        const chips = Array.from(document.querySelectorAll('.chip')).filter(c => {
            const val = c.getAttribute('data-value');
            return val !== 'allin';
        });

        // Phân loại phỉnh cược theo chiến thuật streamer
        let targetChip = null;
        if (chips.length > 0) {
            const chipMap = {};
            chips.forEach(c => {
                const txt = c.innerText.trim();
                chipMap[txt] = c;
            });

            const rand = Math.random();
            if (rand < 0.25 && chipMap['50K']) {
                targetChip = chipMap['50K'];   // 25% cược thăm dò 50K
            } else if (rand < 0.55 && chipMap['100K']) {
                targetChip = chipMap['100K'];  // 30% cược nhẹ 100K
            } else if (rand < 0.80 && chipMap['500K']) {
                targetChip = chipMap['500K'];  // 25% cược tiêu chuẩn 500K
            } else if (rand < 0.95 && chipMap['1M']) {
                targetChip = chipMap['1M'];    // 15% cược to 1M
            } else if (chipMap['5M'] && balance >= 10000000) {
                targetChip = chipMap['5M'];    // 5% cược khủng 5M
            } else {
                targetChip = chips[Math.floor(Math.random() * chips.length)];
            }
        }

        // BƯỚC 1: Nghỉ 0.8s - 1.2s trước ván mới để tận hưởng âm thanh / kết quả
        setTimeout(() => {
            if (targetChip) {
                BotVirtualCursor.moveToElement($(targetChip), 0.26, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try { targetChip.click(); } catch(e){}

                        // BƯỚC 2: Rê chuột dứt khoát tới nút "🎰 QUAY NGAY"
                        executeSpin(spinBtn);
                    });
                });
            } else {
                executeSpin(spinBtn);
            }
        }, 900 + Math.random() * 500);
    }

    function executeSpin(spinBtn) {
        setTimeout(() => {
            BotVirtualCursor.moveToElement($(spinBtn), 0.25, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    try { 
                        spinBtn.click(); 
                        if (Math.random() < 0.35) sendChat('spin');
                    } catch(e){}
                    botBusy = false;
                    setTimeout(runSlotStreamerBot, 600);
                });
            });
        }, 180);
    }

    // Khởi động bot streamer
    $(document).ready(function() {
        setTimeout(runSlotStreamerBot, 1000);
    });
}

