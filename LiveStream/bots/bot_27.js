if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Bot Streamer");
    
    function botThink() {
        if ($('#btnStart').is(':visible') && !$('#btnStart').prop('disabled')) {
            let moneyText = $('#userMoney').text().replace(/[^0-9]/g, '');
            let balance = parseInt(moneyText) || 50000;
            
            let minBet = Math.max(1000, Math.floor(balance * 0.01));
            let maxBet = Math.max(5000, Math.floor(balance * 0.10));
            let randomBet = Math.floor(Math.random() * (maxBet - minBet + 1) + minBet);
            randomBet = Math.floor(randomBet / 1000) * 1000;
            
            if (randomBet < 10000) randomBet = 10000;
            
            BotVirtualCursor.moveToElement($('#betAmount'), 1, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        $('#betAmount').val(randomBet);
                        setTimeout(() => {
                            BotVirtualCursor.moveToElement($('#btnStart'), 1, 0, () => {
                                setTimeout(() => {
                                    BotVirtualCursor.simulateClick(() => {
                                        if (!$('#btnStart').prop('disabled')) {
                                            if (window.BotChat) window.BotChat.send(27, window.currentBotId || 0, `Vừa ra chiêu ${randomBet.toLocaleString()} GTLM! Giao lưu nhé ae!`);
                                            $('#btnStart').click();
                                        }
                                    });
                                }, 500);
                            });
                        }, 500);
                    });
                }, 500);
            });
        } 
        else if ($('#btnHigher').is(':visible') && !$('#btnHigher').prop('disabled')) {
            let multText = $('#multVal').text().replace('x', '');
            let mult = parseFloat(multText) || 1.0;
            
            let shouldCollect = false;
            if (mult > 3.0) shouldCollect = true; 
            else if (mult > 2.0 && Math.random() < 0.6) shouldCollect = true; 
            else if (mult > 1.5 && Math.random() < 0.3) shouldCollect = true; 
            
            if (shouldCollect && !$('#btnCollect').prop('disabled')) {
                BotVirtualCursor.moveToElement($('#btnCollect'), 1, 0, () => {
                    setTimeout(() => {
                        BotVirtualCursor.simulateClick(() => {
                            if (!$('#btnCollect').prop('disabled')) {
                                if (window.BotChat) window.BotChat.send(27, window.currentBotId || 0, `Húp được x${mult} GTLM rồi! Dừng lại ăn chắc mặc bền ae ạ =))`);
                                $('#btnCollect').click();
                            }
                        });
                    }, 500);
                });
            } else {
                let src = $('#cardImg').attr('src');
                let cardVal = 7; 
                let match = src.match(/card_[a-z]+_(A|J|Q|K|\d+)\.png/);
                if (match) {
                    let valStr = match[1];
                    if (valStr === 'A') cardVal = 1;
                    else if (valStr === 'J') cardVal = 11;
                    else if (valStr === 'Q') cardVal = 12;
                    else if (valStr === 'K') cardVal = 13;
                    else cardVal = parseInt(valStr);
                }
                
                let targetBtn = $('#btnHigher');
                if (cardVal < 7) targetBtn = $('#btnHigher');
                else if (cardVal > 7) targetBtn = $('#btnLower');
                else targetBtn = Math.random() < 0.5 ? $('#btnHigher') : $('#btnLower');
                
                BotVirtualCursor.moveToElement(targetBtn, 1, 0, () => {
                    setTimeout(() => {
                        BotVirtualCursor.simulateClick(() => {
                            if (!targetBtn.prop('disabled')) {
                                if (window.BotChat && Math.random() < 0.4) {
                                    let msg = targetBtn.attr('id') === 'btnHigher' ? 'Chiến Cao Hơn! Bay màu thì chịu!' : 'Chiến Thấp Hơn! Anh em tin tôi!';
                                    window.BotChat.send(27, window.currentBotId || 0, msg);
                                }
                                targetBtn.click();
                            }
                        });
                    }, 500);
                });
            }
        }
        else {
            if ($('.swal2-confirm').is(':visible')) {
                BotVirtualCursor.moveToElement($('.swal2-confirm'), 1, 0, () => {
                    setTimeout(() => { 
                        BotVirtualCursor.simulateClick(() => {
                            $('.swal2-confirm').click();
                        }); 
                    }, 500);
                });
            }
        }
        
        setTimeout(botThink, 4000 + Math.random() * 2000);
    }
    
    setTimeout(botThink, 2000);
}
