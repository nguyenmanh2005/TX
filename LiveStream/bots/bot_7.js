if (typeof BotVirtualCursor !== "undefined") {
        BotVirtualCursor.init("Bot Streamer");
        setInterval(() => {
            const allBtns = Array.from(document.querySelectorAll("button, .btn-bet, .chip, .spin-btn, #btnSpin, .bet-button, .card, .btn-primary, .btn-success, input[type='button'], input[type='submit']"));
            const btns = allBtns.filter(b => {
                if(b.offsetParent === null || b.disabled) return false;
                const txt = (b.innerText || b.value || "").toLowerCase();
                const cls = (b.className || "").toLowerCase();
                const id = (b.id || "").toLowerCase();
                
                if(txt.includes("hướng dẫn") || txt.includes("trang chủ") || txt.includes("nạp") || txt.includes("rút") || txt.includes("lịch sử") || txt.includes("quay lại") || txt.includes("thoát")) return false;
                if(cls.includes("back") || cls.includes("help") || cls.includes("guide") || cls.includes("close") || cls.includes("swal") || cls.includes("nav")) return false;
                if(id.includes("guide") || id.includes("back") || id.includes("close") || id.includes("nav")) return false;
                
                return true;
            });
            
            if(btns.length > 0) {
                const btn = btns[Math.floor(Math.random() * btns.length)];
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
