/**
 * Bot AI cho Limbo (Bàn 35)
 */
$(document).ready(function() {
    if (typeof BotVirtualCursor === "undefined") {
        console.error("BotVirtualCursor không khả dụng.");
        return;
    }

    BotVirtualCursor.init("Bot Streamer");

    let isBotThinking = false;
    let clickSequence = [];

    function think() {
        if (isBotThinking || isRolling) return;
        isBotThinking = true;

        // Xóa chuỗi hành động cũ
        clickSequence = [];

        // 1. Chọn mức cược ngẫu nhiên (80% đổi cược, 20% giữ nguyên)
        if (Math.random() > 0.2) {
            const betButtons = Array.from(document.querySelectorAll('.btn-quick-bet')).filter(b => b.innerText !== 'ALL IN');
            if (betButtons.length > 0) {
                const randomBetBtn = betButtons[Math.floor(Math.random() * betButtons.length)];
                clickSequence.push(randomBetBtn);
            }
        }

        // 2. Chọn target ngẫu nhiên (70% đổi target, 30% giữ nguyên)
        if (Math.random() > 0.3) {
            const targetButtons = Array.from(document.querySelectorAll("button[onclick*='targetMult']"));
            if (targetButtons.length > 0) {
                const randomTargetBtn = targetButtons[Math.floor(Math.random() * targetButtons.length)];
                clickSequence.push(randomTargetBtn);
            }
        }

        // 3. Phóng Tên Lửa
        const rollBtn = document.getElementById('rollBtn');
        if (rollBtn && !rollBtn.disabled) {
            clickSequence.push(rollBtn);
        }

        executeSequence();
    }

    function executeSequence() {
        if (clickSequence.length === 0) {
            isBotThinking = false;
            return;
        }

        const nextBtn = clickSequence.shift();
        
        BotVirtualCursor.moveToElement($(nextBtn), 0.5, 0, () => {
            setTimeout(() => {
                BotVirtualCursor.simulateClick(() => {
                    try { 
                        if(!nextBtn.disabled) nextBtn.click(); 
                    } catch(e) {}
                    
                    setTimeout(executeSequence, 800 + Math.random() * 1000);
                });
            }, 300);
        });
    }

    // Bot hành động mỗi 4-8 giây nếu đang không làm gì
    setInterval(() => {
        if (!isBotThinking && !isRolling) {
            think();
        }
    }, 4000 + Math.random() * 4000);
});
