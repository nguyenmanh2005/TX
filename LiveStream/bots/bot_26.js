    if (typeof BotVirtualCursor !== "undefined") {
        BotVirtualCursor.init("Bot Streamer");
        
        function botThink() {
            // Read UI state
            if ($('#btnStart').is(':visible') && !$('#btnStart').prop('disabled')) {
                // Determine a random bet amount
                let possibleBets = [10000, 20000, 50000, 100000, 200000];
                let randomBet = possibleBets[Math.floor(Math.random() * possibleBets.length)];
                
                // Move to input, change value, then click start
                BotVirtualCursor.moveToElement($('#betAmount'), 1, 0, () => {
                    setTimeout(() => {
                        BotVirtualCursor.simulateClick(() => {
                            $('#betAmount').val(randomBet.toLocaleString('vi-VN'));
                            setTimeout(() => {
                                BotVirtualCursor.moveToElement($('#btnStart'), 1, 0, () => {
                                    setTimeout(() => { 
                                        BotVirtualCursor.simulateClick(() => {
                                            if (!$('#btnStart').prop('disabled')) $('#btnStart').click();
                                        }); 
                                    }, 500);
                                });
                            }, 500);
                        });
                    }, 500);
                });
            } else if ($('#actionPanel').is(':visible') && !$('#btnStep').prop('disabled')) {
                // Decide whether to step or cashout based on risk
                let currentStep = parseInt($('#txtStep').text()) || 0;
                let riskText = $('#txtRisk').text();
                let crashChance = parseInt(riskText) || 15;
                
                // Bot strategy:
                let shouldCashout = false;
                if (crashChance >= 40) shouldCashout = true; // Too risky, run!
                else if (currentStep >= 5) shouldCashout = Math.random() < 0.7; // 70% run
                else if (currentStep >= 3) shouldCashout = Math.random() < 0.3; // 30% run
                else shouldCashout = false; // Safe, keep going
                
                // Force step if currentStep == 0 because cashout is disabled
                if (currentStep === 0) shouldCashout = false;
                
                let targetBtn = shouldCashout ? $('#btnCashout') : $('#btnStep');
                BotVirtualCursor.moveToElement(targetBtn, 1, 0, () => {
                    setTimeout(() => { 
                        BotVirtualCursor.simulateClick(() => {
                            if (!targetBtn.prop('disabled')) targetBtn.click();
                        }); 
                    }, 500);
                });
            } else {
                // Wait... maybe Swal is open. If Swal is open, close it.
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
