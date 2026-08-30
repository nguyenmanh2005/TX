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

        if ($('#newBtn').is(':visible')) {
            BotVirtualCursor.moveToElement($('#newBtn'), 1, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        $('#newBtn').click();
                    });
                }, 500);
            });
            setTimeout(botThink, 3000);
            return;
        }

        if ($('#callBtn').is(':visible') && $('#foldBtn').is(':visible')) {
            let targetBtn = Math.random() < 0.7 ? $('#callBtn') : $('#foldBtn');
            BotVirtualCursor.moveToElement(targetBtn, 1, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        if (window.BotChat) {
                            let msg = targetBtn.attr('id') === 'callBtn' ? 'Bài đẹp, theo cược luôn ae ơi!' : 'Bài xấu quá, tui bỏ bài đây =))';
                            window.BotChat.send(28, window.currentBotId || 0, msg);
                        }
                        targetBtn.click();
                    });
                }, 500);
            });
            setTimeout(botThink, 6000);
            return;
        }

        if ($('#dealBtn').is(':visible')) {
            const chips = Array.from(document.querySelectorAll('.chip'));
            if (chips.length > 0) {
                let randomChip;
                if (Math.random() < 0.05) {
                    randomChip = chips[chips.length - 1]; 
                } else {
                    randomChip = chips[Math.floor(Math.random() * (chips.length - 1))];
                }
                
                BotVirtualCursor.moveToElement($(randomChip), 1, 0, () => {
                    setTimeout(() => {
                        BotVirtualCursor.simulateClick(() => {
                            $(randomChip).click();
                            setTimeout(() => {
                                BotVirtualCursor.moveToElement($('#dealBtn'), 1, 0, () => {
                                    setTimeout(() => {
                                        BotVirtualCursor.simulateClick(() => {
                                            if (window.BotChat && Math.random() < 0.3) {
                                                window.BotChat.send(28, window.currentBotId || 0, 'Bắt đầu ván mới nào! Chúc ae may mắn!');
                                            }
                                            $('#dealBtn').click();
                                        });
                                    }, 500);
                                });
                            }, 500);
                        });
                    }, 500);
                });
            }
            setTimeout(botThink, 7000);
            return;
        }
        
        setTimeout(botThink, 4000);
    }
    
    setTimeout(botThink, 2000);
}
