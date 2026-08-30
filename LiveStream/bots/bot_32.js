// LiveStream/bots/bot_32.js
// Kịch bản Bot cho game JoJo Battle (ID 32)
// Theo rule: Không được nhúng logic bot vào file game (live_32.php), phải tách ra thư mục bot/

if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Bot Streamer");

    async function playJojoRound() {
        if (typeof active !== "undefined" && active) return; // Đang chiến đấu thì không làm gì

        // 0. Tắt thông báo SweetAlert nếu có bị kẹt
        const swalBtn = document.querySelector('.swal2-confirm');
        if (swalBtn) {
            BotVirtualCursor.moveToElement($(swalBtn), 0.5, 0, () => {
                BotVirtualCursor.simulateClick(() => { swalBtn.click(); });
            });
            await new Promise(r => setTimeout(r, 800));
            return; // Đợi vòng sau làm tiếp
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
                BotVirtualCursor.simulateClick(() => { btnStart.click(); });
            });
        }
    }

    // Vòng lặp hành động chơi game
    function scheduleNextRound() {
        // Chờ từ 3 đến 8 giây để chơi tiếp
        const waitTime = 3000 + Math.random() * 5000;
        setTimeout(() => {
            if (typeof active !== "undefined" && !active) {
                // Đang nhàn rỗi -> chơi luôn (tỉ lệ 90%)
                if (Math.random() > 0.1) {
                    playJojoRound().then(() => scheduleNextRound());
                    return; // playJojoRound sẽ quyết định bao lâu thì gọi tiếp
                }
            }
            // Nếu bận hoặc skip, thử lại sau 3-5 giây
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
