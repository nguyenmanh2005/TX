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

        const activeBetBtns = Array.from(document.querySelectorAll('.bet-btn')).filter(btn => !btn.disabled && btn.offsetParent !== null);

        if (activeBetBtns.length > 0) {
            const horseIndex = Math.floor(Math.random() * 6);
            const targetInput = document.getElementById(`input-${horseIndex + 1}`);
            const targetBtn = activeBetBtns[horseIndex];

            if (targetInput && targetBtn) {
                BotVirtualCursor.moveToElement($(targetInput), 1, 0, () => {
                    setTimeout(() => {
                        BotVirtualCursor.simulateClick(() => {
                            let moneyText = document.getElementById('user-money').innerText.replace(/,/g, '');
                            let currentMoney = parseInt(moneyText) || 100000;
                            let betAmount = Math.floor(currentMoney * (0.01 + Math.random() * 0.04) / 1000) * 1000;
                            if (betAmount < 1000) betAmount = 1000;

                            targetInput.value = betAmount;

                            setTimeout(() => {
                                BotVirtualCursor.moveToElement($(targetBtn), 1, 0, () => {
                                    setTimeout(() => {
                                        BotVirtualCursor.simulateClick(() => {
                                            targetBtn.click();
                                            if (window.BotChat && Math.random() < 0.2) {
                                                const msgs = [
                                                    `Tất tay con ngựa số ${horseIndex + 1} nhé ae!`,
                                                    `Tôi tin ngựa ${horseIndex + 1} ván này vô địch!`,
                                                    `Ngựa ${horseIndex + 1} uy tín, cược nhẹ ${Number(betAmount).toLocaleString()} GTLM thui!`
                                                ];
                                                window.BotChat.send(30, window.currentBotId || 0, msgs[Math.floor(Math.random() * msgs.length)]);
                                            }
                                        });
                                    }, 500);
                                });
                            }, 500);
                        });
                    }, 500);
                });

                setTimeout(botThink, 8000 + Math.random() * 5000);
                return;
            }
        }

        setTimeout(botThink, 4000);
    }

    setTimeout(botThink, 2000);
}
