if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Bot Streamer");
    
    function botThink() {
        if ($('.swal2-confirm').is(':visible')) {
            BotVirtualCursor.moveToElement($('.swal2-confirm'), 1, 0, () => {
                setTimeout(() => { 
                    BotVirtualCursor.simulateClick(() => {
                        $('.swal2-confirm').click();
                    }); 
                }, 500);
            });
            setTimeout(botThink, 3000);
            return;
        }

        const statusEl = document.getElementById('room-status');
        if (statusEl && statusEl.innerText.includes("ĐANG ĐỢI")) {
            if (window.game && !window.game.myBet) {
                const horseCards = Array.from(document.querySelectorAll('.horse-bet-card'));
                if (horseCards.length > 0) {
                    const horseIndex = Math.floor(Math.random() * horseCards.length);
                    const targetCard = horseCards[horseIndex];
                    
                    BotVirtualCursor.moveToElement($(targetCard), 1, 0, () => {
                        setTimeout(() => {
                            BotVirtualCursor.simulateClick(() => {
                                targetCard.click();
                                
                                setTimeout(() => {
                                    const targetInput = document.getElementById('bet-amount');
                                    BotVirtualCursor.moveToElement($(targetInput), 1, 0, () => {
                                        setTimeout(() => {
                                            BotVirtualCursor.simulateClick(() => {
                                                let moneyText = document.getElementById('user-balance').innerText.replace(/,/g, '').replace(/\./g, '');
                                                let currentMoney = parseInt(moneyText) || 100000;
                                                
                                                // Cược ngẫu nhiên từ 2,000 đến 30,000 GTLM (cho bớt ảo)
                                                let betAmount = 1000 * Math.floor(2 + Math.random() * 28);
                                                
                                                if (betAmount > currentMoney) betAmount = currentMoney;
                                                if (betAmount < 1000) betAmount = 1000;
                                                
                                                targetInput.value = betAmount;
                                                
                                                setTimeout(() => {
                                                    const targetBtn = document.getElementById('place-bet-btn');
                                                    BotVirtualCursor.moveToElement($(targetBtn), 1, 0, () => {
                                                        setTimeout(() => {
                                                            BotVirtualCursor.simulateClick(() => {
                                                                targetBtn.click();
                                                                if (window.BotChat && Math.random() < 0.3) {
                                                                    const msgs = [
                                                                        `Tôi đặt cược ngựa #${horseIndex + 1} nhé mọi người!`,
                                                                        `Ngựa #${horseIndex + 1} ván này chắc chắn vô địch!`,
                                                                        `PVP đua ngựa là phải theo ngựa #${horseIndex + 1}!`
                                                                    ];
                                                                    window.BotChat.send(31, window.currentBotId || 0, msgs[Math.floor(Math.random() * msgs.length)]);
                                                                }
                                                            });
                                                        }, 500);
                                                    });
                                                }, 500);
                                            });
                                        }, 500);
                                    });
                                }, 500);
                            });
                        }, 500);
                    });
                    
                    setTimeout(botThink, 10000 + Math.random() * 5000);
                    return;
                }
            }
        }
        
        setTimeout(botThink, 4000);
    }
    
    setTimeout(botThink, 2000);
}
