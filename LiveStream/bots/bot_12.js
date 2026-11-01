// bot_bingo.js — Chế độ bảo trì: chỉ di chuyển con trỏ ảo, không thao tác game
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');

function startBingoBot() {
    if (typeof BotVirtualCursor === "undefined") {
        setTimeout(startBingoBot, 500);
        return;
    }

    BotVirtualCursor.init("Thánh Số Bingo 🎱✨");

    const CASINO_CHATS = {
        maintenance: [
            "Bàn Bingo đang bảo trì nâng cấp công nghệ! Anh em nghỉ tay uống nước lát chiến tiếp nhé! 🍵✨",
            "Đang hiệu chỉnh lồng cầu số học! Chờ giây lát ta lại quẩy banh nóc! 🛠️🎱",
            "Hệ thống đang chuẩn bị giải thưởng cực khủng, anh em chờ xíu nhé! 💎🌟"
        ],
        draw: [
            "Quay lồng cầu Bingo! Ra chiêu rút quả bóng vàng phát tài! 🎱🌟💎",
            "Cầu đang nổ giòn giã! Anh em cùng nín thở đếm số nào! 🔮⚡",
            "Số đẹp xuất hiện rước GTLM về nick nào! Bắn bóng đỉnh cao! 🎯💰"
        ],
        win: [
            "BINGO RỒI ANH EM ƠI! Húp trọn hàng số may mắn ngập tràn nick! 🏆🎉🤑",
            "Nổ Bingo thẳng hàng! Gáy khét lẹt cả sảnh Bingo hôm nay! 👑🎱💰",
            "Đỉnh cao săn số! Tiền cộng đầy tài khoản rực rỡ! 💎✨"
        ],
        loss: [
            "Lệch đúng 1 ô nữa là trúng Bingo rồi, bay màu nhẹ! Ván sau phục thù! 😅🔄",
            "Bốc hơi chút ít lộc, bảng mới này nhất định húp đậm! 💪🎱"
        ]
    };

    function sendChat(type) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.draw;
            const msg = list[Math.floor(Math.random() * list.length)];
            BotChat.send(12, 'Thánh Số Bingo 🎱✨', msg);
        }
    }

    // Kiểm tra có đang ở trang bảo trì không (không có chip/btn-draw)
    const isMaintenance = !document.querySelector(".chip") && !document.querySelector("[name='action']");

    if (isMaintenance) {
        // Chỉ di chuyển con trỏ ảo lung tung để trông có người xem
        const wanderCursor = () => {
            const x = 100 + Math.random() * (window.innerWidth - 200);
            const y = 100 + Math.random() * (window.innerHeight - 200);
            if (typeof BotVirtualCursor.moveTo === 'function') {
                BotVirtualCursor.moveTo(x, y);
            }
            if (Math.random() < 0.25) sendChat('maintenance');
            setTimeout(wanderCursor, 8000 + Math.random() * 6000);
        };
        setTimeout(wanderCursor, 2000);
        return;
    }

    // === GAME BINGO ĐANG HOẠT ĐỘNG ===
    const playRound = () => {
        const chips = Array.from(document.querySelectorAll(".chip"));
        // Tìm nút rút số (hỗ trợ cả selector cũ lẫn mới)
        const drawBtn = document.querySelector(".btn-draw") 
            || document.querySelector("button[value='draw']")
            || document.querySelector("button[name='action'][value='draw']");
        const newCardBtn = document.querySelector(".btn-new-card")
            || document.querySelector("button[value='new_card']")
            || document.querySelector(".btn-new");

        if (chips.length > 0 && drawBtn && !drawBtn.disabled) {

            // Đọc số còn lại
            let remaining = 25;
            const remainEl = document.getElementById('remainingDisplay')
                || document.querySelector('.game-info div:last-child div:nth-child(2)');
            if (remainEl) remaining = parseInt(remainEl.textContent) || 25;

            // Nếu hết số → bấm Bảng Mới
            if (remaining <= 0 && newCardBtn) {
                BotVirtualCursor.moveToElement($(newCardBtn), 0.6, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try { newCardBtn.click(); } catch(e) {}
                    });
                });
                setTimeout(playRound, 5000 + Math.random() * 3000);
                return;
            }

            // Chọn chip theo trọng số (ưu tiên mức giữa)
            let randChip;
            if (remaining < 5 && Math.random() < 0.25) {
                // Sắp hết số → đôi khi cược ALL IN
                randChip = chips.find(c => c.getAttribute('data-value') === 'allin') || chips[chips.length - 1];
            } else {
                const weights = [1, 2, 3, 5, 5, 2, 1];
                let total = weights.slice(0, chips.length).reduce((a, b) => a + b, 0) || chips.length;
                let rnd = Math.random() * total;
                let idx = 0;
                for (let i = 0; i < Math.min(weights.length, chips.length); i++) {
                    rnd -= (weights[i] || 1);
                    if (rnd <= 0) { idx = i; break; }
                }
                randChip = chips[idx];
            }

            // Step 1: Chọn mức cược
            BotVirtualCursor.moveToElement($(randChip), 0.5, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    try { randChip.click(); } catch(e) {}

                    // Step 2: Nhấn Rút Số
                    setTimeout(() => {
                        if (drawBtn) {
                            BotVirtualCursor.moveToElement($(drawBtn), 0.6, 0, () => {
                                BotVirtualCursor.simulateClick(() => {
                                    try { 
                                        drawBtn.click(); 
                                        if (Math.random() < 0.35) sendChat('draw');
                                    } catch(e) {}
                                });
                            });
                        }
                    }, 500 + Math.random() * 500);
                });
            });
        }

        // Lặp lại sau 5–9 giây
        setTimeout(playRound, 5000 + Math.random() * 4000);
    };

    setTimeout(playRound, 2000);
}

startBingoBot();
