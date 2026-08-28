// assets/js/bot_virtual_cursor.js
// Framework chuẩn cho Bot Streamer (chuột ảo GSAP)

window.BotVirtualCursor = {
    cursorId: 'botVirtualCursor',
    nameId: 'cursorBotName',
    
    init: function(botName) {
        if ($('#' + this.cursorId).length === 0) {
            $('head').append(`
                <style>
                    /* 🤖 Custom Virtual Bot Streamer Pointer */
                    .bot-virtual-cursor {
                        position: absolute;
                        top: -100px; left: -100px;
                        pointer-events: none;
                        z-index: 9999999;
                        opacity: 0;
                        display: flex;
                        flex-direction: column;
                        filter: drop-shadow(0 10px 15px rgba(0, 0, 0, 0.4));
                        transform-origin: top left;
                    }
                    .cursor-pointer-arrow {
                        width: 28px; height: 28px;
                        transform: rotate(-25deg);
                    }
                    .cursor-pointer-arrow svg {
                        filter: drop-shadow(0 0 5px rgba(0, 255, 136, 0.8));
                    }
                    .cursor-bot-tag {
                        background: rgba(15, 23, 42, 0.85);
                        backdrop-filter: blur(8px);
                        border: 1px solid rgba(0, 255, 136, 0.5);
                        color: #00ff88;
                        font-size: 0.7rem;
                        font-weight: 800;
                        padding: 4px 10px;
                        border-radius: 12px;
                        margin-top: -5px;
                        margin-left: 15px;
                        white-space: nowrap;
                        display: flex;
                        align-items: center;
                        gap: 5px;
                        box-shadow: 0 4px 12px rgba(0, 255, 136, 0.2);
                    }
                    .bot-tag-dot {
                        width: 6px;
                        height: 6px;
                        background: #00ff88;
                        border-radius: 50%;
                        box-shadow: 0 0 8px #00ff88;
                        animation: pulse-dot 1.5s infinite;
                    }
                    @keyframes pulse-dot { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.5); opacity: 0.4; } }
                </style>
            `);
            $('body').append(`
                <div id="botVirtualCursor" class="bot-virtual-cursor">
                    <div class="cursor-pointer-arrow">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                            <path d="M3 3l7 18 3-7 7-3L3 3z" fill="#00ff88" stroke="#ffffff" stroke-width="2" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="cursor-bot-tag">
                        <span class="bot-tag-dot"></span>
                        <span id="cursorBotName">${botName}</span>
                    </div>
                </div>
            `);
        } else {
            $('#' + this.nameId).text(botName);
        }
    },
    
    moveToElement: function($el, duration, delay, onComplete) {
        if ($el.length === 0) {
            if (onComplete) onComplete();
            return;
        }

        // --- GLOBAL BOT FILTER ---
        // Ngăn chặn bot click vào âm lượng, quay lại sảnh, chuyển kênh...
        const txt = ($el.text() || $el.val() || "").toLowerCase();
        const id = ($el.attr('id') || "").toLowerCase();
        const cls = ($el.attr('class') || "").toLowerCase();
        
        if (
            txt.includes('âm lượng') || txt.includes('quay lại') || txt.includes('thoát') || txt.includes('sảnh') || txt.includes('kênh') ||
            id.includes('volume') || id.includes('sound') || id.includes('back') || id.includes('home') || id.includes('nav') ||
            cls.includes('volume') || cls.includes('sound') || cls.includes('back') || cls.includes('home') || cls.includes('nav') || cls.includes('channel')
        ) {
            console.warn("[BotVirtualCursor] Blocked interaction with forbidden UI element:", $el);
            return; // Dừng lại, không di chuyển và không gọi onComplete (hủy click)
        }
        
        const cursor = $('#' + this.cursorId);
        gsap.set(cursor, { opacity: 1 });
        
        const offset = $el.offset();
        const targetX = offset.left + $el.outerWidth() / 2;
        const targetY = offset.top + $el.outerHeight() / 2;
        
        gsap.to(cursor, {
            left: targetX,
            top: targetY,
            duration: duration || 0.8,
            delay: delay || 0,
            ease: "power2.inOut",
            onComplete: onComplete
        });
    },
    
    // Mô phỏng click (nhấn xuống rồi nhả ra)
    simulateClick: function(onComplete) {
        const cursor = $('#' + this.cursorId);
        gsap.to(cursor, { 
            scale: 0.7, 
            duration: 0.12, 
            yoyo: true, 
            repeat: 1, 
            onComplete: onComplete 
        });
    },
    
    // Ẩn chuột
    hide: function(delay, duration) {
        gsap.to($('#' + this.cursorId), { 
            opacity: 0, 
            delay: delay || 0.5, 
            duration: duration || 0.4 
        });
    }
};

window.BotChat = {
    send: function(gameId, botId, message) {
        $.post('../api_bot_streamer_chat.php', {
            action: 'send_chat',
            game_id: gameId,
            bot_id: botId,
            message: message,
            secret: 'gtlm_bot_secret_999'
        });
    }
};
