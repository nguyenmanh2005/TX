if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Thợ Săn Kho Báu ⛏️💎");

    const CASINO_CHATS = {
        start: [
            "Thắp đuốc bước vào Hang Động Tham Lam! Ra chiêu săn rương vàng cổ đại! ⛏️💎🔥",
            "Dũng khí thợ săn! Vào hang sâu đoạt ngọc báu rước GTLM về nick! 🏰⚡",
            "Bắt đầu chuyến thám hiểm! Anh em cùng nín thở theo từng bước chân! 🌟💰",
            "Trang bị tận răng, ra chiêu quyết tâm càn quét hang kho báu! 🛡️💎"
        ],
        step: [
            "Tiến sâu thêm một tầng nữa! Đầy rẫy cạm bẫy nhưng rương vàng x3 x5! ⛏️💎",
            "Càng sâu càng đậm lộc! Bước đi vững chãi không sợ sập hầm! 🛡️⚡",
            "Linh tính mách bảo tầng này có kim cương! Dấn bước săn kho báu! 🌟🔥"
        ],
        cashout: [
            "QUYẾT ĐỊNH RÚT LUI KỊP THỜI! Húp trọn rương báu GTLM an toàn về nick! 🏆🎉🤑",
            "Chốt hạ đúng đỉnh! Mang kho báu về gáy khét cả hang động! 👑💎💰",
            "Ăn chắc mặc bền! Đút túi kho báu GTLM rồi rút lui! 💎✨"
        ],
        win: [
            "MỞ ĐƯỢC RƯƠNG VÀNG HOÀNG GIA! GTLM nổ tràn trề khắp nick! 🏆🎉🤑",
            "Thợ săn đại tài húp trọn vẹn chuyến thám hiểm! 💎✨"
        ],
        loss: [
            "Sập bẫy quái vật rồi, bay màu chuyến thám hiểm này! 😭💔",
            "Hang sập bốc hơi chút ít tài nguyên, lần sau trang bị cẩn thận hơn! 💪🔄"
        ]
    };

    function sendChat(type) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.start;
            const msg = list[Math.floor(Math.random() * list.length)];
            BotChat.send(25, 'Thợ Săn Kho Báu ⛏️💎', msg);
        }
    }

    function botThink() {
        // Read UI state
        if ($('#btnStart').is(':visible') && !$('#btnStart').prop('disabled')) {
            let possibleBets = [10000, 20000, 50000, 100000, 200000];
            let randomBet = possibleBets[Math.floor(Math.random() * possibleBets.length)];

            BotVirtualCursor.moveToElement($('#betAmount'), 1, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        $('#betAmount').val(randomBet.toLocaleString('vi-VN'));
                        setTimeout(() => {
                            BotVirtualCursor.moveToElement($('#btnStart'), 1, 0, () => {
                                setTimeout(() => {
                                    BotVirtualCursor.simulateClick(() => {
                                        if (!$('#btnStart').prop('disabled')) {
                                            $('#btnStart').click();
                                            if (Math.random() < 0.4) sendChat('start');
                                        }
                                    });
                                }, 500);
                            });
                        }, 500);
                    });
                }, 500);
            });
        } else if ($('#actionPanel').is(':visible') && !$('#btnStep').prop('disabled')) {
            let currentStep = parseInt($('#txtStep').text()) || 0;
            let riskText = $('#txtRisk').text();
            let crashChance = parseInt(riskText) || 15;

            let shouldCashout = false;
            if (crashChance >= 40) shouldCashout = true;
            else if (currentStep >= 5) shouldCashout = Math.random() < 0.7;
            else if (currentStep >= 3) shouldCashout = Math.random() < 0.3;
            else shouldCashout = false;

            if (currentStep === 0) shouldCashout = false;

            let targetBtn = shouldCashout ? $('#btnCashout') : $('#btnStep');
            BotVirtualCursor.moveToElement(targetBtn, 1, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        if (!targetBtn.prop('disabled')) {
                            targetBtn.click();
                            if (shouldCashout) {
                                sendChat('cashout');
                            } else if (Math.random() < 0.3) {
                                sendChat('step');
                            }
                        }
                    });
                }, 500);
            });
        } else {
            if ($('.swal2-confirm').is(':visible')) {
                const swalTxt = ($('.swal2-title').text() || '').toLowerCase();
                if (swalTxt.includes('thành công') || swalTxt.includes('win') || swalTxt.includes('nhận')) {
                    if (Math.random() < 0.6) sendChat('win');
                } else if (swalTxt.includes('thua') || swalTxt.includes('sập') || swalTxt.includes('bẫy') || swalTxt.includes('mất')) {
                    if (Math.random() < 0.5) sendChat('loss');
                }
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
