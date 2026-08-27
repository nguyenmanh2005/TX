if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');

// Đảm bảo bot_virtual_cursor.js đã được tải trước
function startBaccaratBot() {
    if (typeof BotVirtualCursor === "undefined") {
        setTimeout(startBaccaratBot, 500);
        return;
    }

    BotVirtualCursor.init("Bot Streamer");
    
    // Logic bot baccarat thông minh hơn
    const playRound = () => {
        const chips = Array.from(document.querySelectorAll(".chip:not([data-value='0'])")).filter(b => b.offsetParent !== null);
        const options = Array.from(document.querySelectorAll(".bet-option")).filter(b => b.offsetParent !== null);
        const dealBtn = document.getElementById("dealBtn");
        
        if (chips.length > 0 && options.length > 0 && dealBtn && dealBtn.offsetParent !== null && !dealBtn.disabled) {
            
            // 1. Chọn chip ngẫu nhiên (thiên về các chip nhỏ/vừa để cược nhiều lần)
            // Trọng số để hay chọn 10K, 50K, 100K hơn là 5M
            const chipWeights = [1, 2, 5, 5, 3, 2, 1, 1]; 
            let totalWeight = chipWeights.reduce((a, b) => a + b, 0);
            let randomNum = Math.random() * totalWeight;
            let chipIndex = 0;
            for (let i = 0; i < chipWeights.length; i++) {
                randomNum -= chipWeights[i];
                if (randomNum <= 0) {
                    chipIndex = Math.min(i, chips.length - 1);
                    break;
                }
            }
            const randChip = chips[chipIndex];
            
            // 2. Phân tích Lịch Sử (Roadmap) để Chọn cửa cược thông minh
            // Đọc beadPlate để xem kết quả trước đó
            const beads = document.querySelectorAll('#beadPlate .bead');
            let lastWinner = null;
            let streak = 0;
            if (beads.length > 0) {
                const history = Array.from(beads).map(b => b.classList.contains('bead-player') ? 'player' : (b.classList.contains('bead-banker') ? 'banker' : 'tie'));
                
                // Lấy kết quả không phải Hòa gần nhất
                for (let i = history.length - 1; i >= 0; i--) {
                    if (history[i] !== 'tie') {
                        lastWinner = history[i];
                        streak = 1;
                        // Đếm chuỗi
                        for (let j = i - 1; j >= 0; j--) {
                            if (history[j] === 'tie') continue;
                            if (history[j] === lastWinner) streak++;
                            else break;
                        }
                        break;
                    }
                }
            }

            // Mặc định: Player: 45%, Tie: 10%, Banker: 45%
            let optionWeights = [45, 10, 45]; 
            
            if (lastWinner === 'player') {
                if (streak >= 4) {
                    // Chuỗi Player quá dài (4+) -> Bẻ cầu sang Banker (Banker 70%)
                    optionWeights = [20, 10, 70];
                    console.log("[Bot Baccarat] Bẻ cầu Banker! (Player đã win " + streak + " lần)");
                } else {
                    // Đu cầu Player (Player 65%)
                    optionWeights = [65, 10, 25];
                    console.log("[Bot Baccarat] Đu cầu Player! (Streak: " + streak + ")");
                }
            } else if (lastWinner === 'banker') {
                if (streak >= 4) {
                    // Chuỗi Banker quá dài (4+) -> Bẻ cầu sang Player (Player 70%)
                    optionWeights = [70, 10, 20];
                    console.log("[Bot Baccarat] Bẻ cầu Player! (Banker đã win " + streak + " lần)");
                } else {
                    // Đu cầu Banker (Banker 65%)
                    optionWeights = [25, 10, 65];
                    console.log("[Bot Baccarat] Đu cầu Banker! (Streak: " + streak + ")");
                }
            } else {
                console.log("[Bot Baccarat] Cược ngẫu nhiên");
            }

            totalWeight = optionWeights.reduce((a, b) => a + b, 0);
            randomNum = Math.random() * totalWeight;
            let optionIndex = 0;
            for (let i = 0; i < optionWeights.length; i++) {
                randomNum -= optionWeights[i];
                if (randomNum <= 0) {
                    optionIndex = Math.min(i, options.length - 1);
                    break;
                }
            }
            const randOption = options[optionIndex];
            
            // Số lần nhấp vào cửa cược (để tăng tiền cược)
            const clicksCount = Math.floor(Math.random() * 3) + 1; // 1 đến 3 lần click

            // Bắt đầu chuỗi hành động
            BotVirtualCursor.moveToElement($(randChip), 0.6, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    try { randChip.click(); } catch(e){}
                    
                    // Di chuyển đến cửa cược
                    setTimeout(() => {
                        BotVirtualCursor.moveToElement($(randOption), 0.6, 0, () => {
                            
                            // Hàm click đệ quy để click nhiều lần vào cửa cược
                            let clicksDone = 0;
                            const doClickOption = () => {
                                BotVirtualCursor.simulateClick(() => {
                                    try { randOption.click(); } catch(e){}
                                    clicksDone++;
                                    
                                    if (clicksDone < clicksCount) {
                                        setTimeout(doClickOption, 300 + Math.random() * 200); // Click nhanh liên tiếp
                                    } else {
                                        // Hoàn thành click cửa cược, đôi khi cược thêm cửa TIE lót tay (15% cơ hội)
                                        if (Math.random() < 0.15 && randOption.dataset.type !== 'tie') {
                                            const tieOption = options.find(o => o.dataset.type === 'tie');
                                            if (tieOption) {
                                                setTimeout(() => {
                                                    // Chọn chip nhỏ nhất để lót hòa
                                                    BotVirtualCursor.moveToElement($(chips[0]), 0.4, 0, () => {
                                                        BotVirtualCursor.simulateClick(() => {
                                                            try { chips[0].click(); } catch(e){}
                                                            setTimeout(() => {
                                                                BotVirtualCursor.moveToElement($(tieOption), 0.4, 0, () => {
                                                                    BotVirtualCursor.simulateClick(() => {
                                                                        try { tieOption.click(); } catch(e){}
                                                                        finalizeDeal();
                                                                    });
                                                                });
                                                            }, 300);
                                                        });
                                                    });
                                                }, 400);
                                                return; // Dừng luồng hiện tại để lót hòa xử lý tiếp
                                            }
                                        }
                                        
                                        finalizeDeal();
                                    }
                                });
                            };
                            
                            const finalizeDeal = () => {
                                setTimeout(() => {
                                    BotVirtualCursor.moveToElement($(dealBtn), 0.5, 0, () => {
                                        BotVirtualCursor.simulateClick(() => {
                                            try { dealBtn.click(); } catch(e){}
                                        });
                                    });
                                }, 800 + Math.random() * 1000); // Giả vờ chần chừ trước khi chốt
                            };
                            
                            doClickOption();
                        });
                    }, 500);
                });
            });
        }
        
        // Random thời gian cho ván tiếp theo (sau khi ván hiện tại kết thúc)
        // Vì baccarat cần thời gian lật bài, nên delay đủ lâu để nút KHAI CUỘC hiện lại
        setTimeout(playRound, 8000 + Math.random() * 6000);
    };

    // Khởi chạy vòng đầu tiên sau một khoảng delay nhỏ
    setTimeout(playRound, 3000);
}

startBaccaratBot();
