    if (typeof BotVirtualCursor !== "undefined") {
        BotVirtualCursor.init("Bot Streamer");
        setInterval(() => {
            const btn = document.getElementById('btn-pull');
            if (btn && !btn.disabled) {
                BotVirtualCursor.moveToElement($(btn), 1, 0, () => {
                    setTimeout(() => { 
                        BotVirtualCursor.simulateClick(() => {
                            try { btn.click(); } catch(e){}
                        });
                    }, 500);
                });
            }
        }, 3000 + Math.random() * 4000);
    }
