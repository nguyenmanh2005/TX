// LiveStream/bots/bot_32.js
// Kịch bản Bot cho game JoJo Battle (ID 32)
// Tích hợp LiveStream Chat 24/7 theo chuẩn Casino Rule 5.3

if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Stand Master JoJo 🌟👊");

    const CASINO_CHATS = {
        bet: [
            "ORA ORA ORA! Triệu hồi Stand! Ra chiêu quyết đấu đỉnh cao trận địa JoJo! 🌟👊🔥",
            "Muda Muda! Vào tiền tự tin, Stand của tôi là vô địch thiên hạ! ⚡💎",
            "Khai mở trận chiến Stand! Ra chiêu bắt trọn bão GTLM! 💥💰",
            "Sức mạnh Stand thức tỉnh! Trận chiến này tiền phải về đầy nick! 🌟⚡"
        ],
        win: [
            "KONO DIO DA! HÚP TRỌN VÁN ĐẤU JOJO! Điểm số áp đảo, tiền về nick ngập tràn! 🏆🌟🤑",
            "Stand tối thượng gáy khét lẹt cả vũ trụ! Tiền nổ rực rỡ! 👑🔥💰",
            "Chiến thắng huy hoàng! ORA ORA quét sạch đối thủ! 💎✨"
        ],
        loss: [
            "Bị đối phương phản đòn phút chót, bay màu ván này! Không sao, hiệp sau ORA ORA phục thù! 😅🔄",
            "Bốc hơi một chút thể lực, hồi máu ra chiêu lật ngược thế cờ! 💪⚡"
        ]
    };

    function sendChat(type) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.bet;
            const msg = list[Math.floor(Math.random() * list.length)];
            BotChat.send(32, 'Stand Master JoJo 🌟👊', msg);
        }
    }

    async function playJojoRound() {
        if (typeof active !== "undefined" && active) return;

        // 0. Tắt thông báo SweetAlert nếu có bị kẹt
        const swalBtn = document.querySelector('.swal2-confirm');
        if (swalBtn) {
            const swalTxt = (document.querySelector('.swal2-title')?.innerText || '').toLowerCase();
            if (swalTxt.includes('thắng') || swalTxt.includes('win')) {
                if (Math.random() < 0.6) sendChat('win');
            } else if (swalTxt.includes('thua') || swalTxt.includes('lose')) {
                if (Math.random() < 0.5) sendChat('loss');
            }
            BotVirtualCursor.moveToElement($(swalBtn), 0.5, 0, () => {
                BotVirtualCursor.simulateClick(() => { swalBtn.click(); });
            });
            await new Promise(r => setTimeout(r, 800));
            return;
        }

        // 1. Nhấn nút Xóa cược cũ
        const btnClear = Array.from(document.querySelectorAll('.chip')).find(b => b.innerText.includes('Xóa'));
        if (btnClear) {
            BotVirtualCursor.moveToElement($(btnClear), 0.5, 0, () => {
                BotVirtualCursor.simulateClick(() => { btnClear.click(); });
            });
            await new Promise(r => setTimeout(r, 800));
        }

        // 2. Chọn nhân vật
        const cards = Array.from(document.querySelectorAll('.card'));
        if (cards.length > 0) {
            const btnChar = cards[Math.floor(Math.random() * cards.length)];
            BotVirtualCursor.moveToElement($(btnChar), 0.8, 0, () => {
                BotVirtualCursor.simulateClick(() => { btnChar.click(); });
            });
            await new Promise(r => setTimeout(r, 1200));
        }

        // 3. Đặt cược ngẫu nhiên (chỉ cược từ 1k đến 500k, không chọn 1M để tránh hết GTLM)
        const chips = Array.from(document.querySelectorAll('.chip')).filter(b => b.innerText.includes('+') && !b.innerText.includes('1M')); 
        if (chips.length > 0) {
            const clicks = Math.floor(Math.random() * 2) + 1;
            for (let i = 0; i < clicks; i++) {
                const chipBtn = chips[Math.floor(Math.random() * chips.length)];
                BotVirtualCursor.moveToElement($(chipBtn), 0.6, 0, () => {
                    BotVirtualCursor.simulateClick(() => { chipBtn.click(); });
                });
                await new Promise(r => setTimeout(r, 1000));
            }
        }

        // 4. Nhấn Bắt Đầu Chiến Đấu
        const btnStart = document.getElementById('btn-duel');
        if (btnStart && !btnStart.disabled) {
            BotVirtualCursor.moveToElement($(btnStart), 0.8, 0, () => {
                BotVirtualCursor.simulateClick(() => { 
                    btnStart.click(); 
                    if (Math.random() < 0.4) sendChat('bet');
                });
            });
        }
    }

    // Vòng lặp hành động chơi game
    function scheduleNextRound() {
        const waitTime = 3000 + Math.random() * 5000;
        setTimeout(() => {
            if (typeof active !== "undefined" && !active) {
                if (Math.random() > 0.1) {
                    playJojoRound().then(() => scheduleNextRound());
                    return;
                }
            }
            scheduleNextRound();
        }, waitTime);
    }
    
    // Kích hoạt bot sau 2 giây đầu
    setTimeout(scheduleNextRound, 2000);

    // Vòng lặp thả reaction
    setInterval(() => {
        if (Math.random() > 0.7) {
            const reacts = ['❤️', '🔥', '🤣'];
            const emoji = reacts[Math.floor(Math.random() * reacts.length)];
            const btn = Array.from(document.querySelectorAll('.dock-btn')).find(b => b.innerText.includes(emoji));
            if (btn) {
                BotVirtualCursor.moveToElement($(btn), 0.6, 0, () => {
                    BotVirtualCursor.simulateClick(() => { btn.click(); });
                });
            }
        }
    }, 8000);
}
