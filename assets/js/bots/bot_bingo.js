if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');

function startBingoBot() {
    if (typeof BotVirtualCursor === "undefined") {
        setTimeout(startBingoBot, 500);
        return;
    }

    BotVirtualCursor.init("Bot Streamer");
    
    const playRound = () => {
        const chips = Array.from(document.querySelectorAll(".chip"));
        const drawBtn = document.querySelector(".btn-draw");

        if (chips.length > 0 && drawBtn && !drawBtn.disabled) {
            
            // Chọn chip ngẫu nhiên, ưu tiên các mức cược lớn hơn khi bảng còn ít số
            let remainText = document.querySelector('.game-info div:last-child div:nth-child(2)')?.textContent || '25';
            let remaining = parseInt(remainText);
            
            // Nếu sắp hết số (còn dưới 5 số), bot đôi khi máu chó cược ALL IN
            let randChip;
            if (remaining < 5 && Math.random() < 0.2) {
                randChip = chips.find(c => c.getAttribute('data-value') === 'allin') || chips[chips.length - 1];
            } else {
                // Bình thường chọn các chip từ giữa trở lên
                const chipWeights = [1, 2, 3, 5, 5, 2]; // Phân bổ trọng số tùy số lượng chip
                let totalWeight = chipWeights.slice(0, chips.length).reduce((a, b) => a + b, 0) || chips.length;
                let randomNum = Math.random() * totalWeight;
                let chipIndex = 0;
                for (let i = 0; i < Math.min(chipWeights.length, chips.length); i++) {
                    randomNum -= chipWeights[i] || 1;
                    if (randomNum <= 0) {
                        chipIndex = i;
                        break;
                    }
                }
                randChip = chips[chipIndex];
            }

            // Step 1: Chọn mức cược
            BotVirtualCursor.moveToElement($(randChip), 0.5, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    try { randChip.click(); } catch(e){}

                    // Step 2: Nhấn Rút Số hoặc Bảng Mới
                    setTimeout(() => {
                        let targetBtn = (remaining === 0) ? document.querySelector('.btn-new-card') : drawBtn;

                        if (targetBtn) {
                            BotVirtualCursor.moveToElement($(targetBtn), 0.6, 0, () => {
                                BotVirtualCursor.simulateClick(() => {
                                    try { targetBtn.click(); } catch(e){}
                                });
                            });
                        }
                    }, 500 + Math.random() * 500);
                });
            });
        }
        
        // Loop
        setTimeout(playRound, 5000 + Math.random() * 4000);
    };

    setTimeout(playRound, 2000);
}

startBingoBot();
