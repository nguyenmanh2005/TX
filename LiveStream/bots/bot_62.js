/**
 * ðŸ¤– Bot Streamer BÃ n 66: Äáº¡i Chiáº¿n Cá» Ca-rÃ´ AI Live (AlphaX vs DeepO)
 * - TÆ°Æ¡ng tÃ¡c bÃ¬nh luáº­n trá»±c tiáº¿p Casino Rule 5.3 (GTLM, hÃºp, gÃ¡y khÃ©t, tam hoa, ngÅ© tá»­)
 */
(function () {
    'use strict';

    const CHATS = {
        alphaX_taunt: [
            "AlphaX ta Ä‘Ã£ giÄƒng tháº¿ tráº­n Tam Hoa ÄÃ´i! DeepO liá»‡u cÃ³ phÃ¡ ná»•i khÃ´ng? â™Ÿï¸âš¡ðŸ”¥",
            "Äá»c vá»‹ nÆ°á»›c cá» trÆ°á»›c 5 bÆ°á»›c! Tiáº¿ng cá» nÃ y chá»‘t háº¡ hÃºp trá»n GTLM! ðŸŽ¯ðŸ’Ž",
            "Cá» bÃ­ dÃ­ tá»‘t, cÃ²n AlphaX thÃ¬ háº¡ quÃ¢n Ã©p gÃ³c má»Ÿ Ä‘Æ°á»ng NgÅ© Tá»­ Liá»n HÃ ng! ðŸš€ðŸ‘‘"
        ],
        deepO_taunt: [
            "DeepO tÃ­nh toÃ¡n lÆ°á»£ng tá»­! HÃ³a giáº£i báº«y Ã©p gÃ³c trong gang táº¥c! ðŸ›¡ï¸âš¡",
            "Äá»«ng vá»™i má»«ng! Má»™t nÆ°á»›c pháº£n cÃ´ng cá»§a DeepO lÃ  bay mÃ u cáº£ vÃ¡n cá»! âš”ï¸ðŸ”¥",
            "Äá»‰nh cao phÃ²ng ngá»± pháº£n kÃ­ch! Xem ai má»›i lÃ  báº­c tháº§y cá» Ca-rÃ´ thá»±c thá»¥! ðŸ’ŽðŸ†"
        ],
        alphaX_win: [
            "NGÅ¨ Tá»¬ LIá»€N HÃ€NG! Master AlphaX háº¡ gá»¥c DeepO hÃºp trá»n 1.000.000 GTLM! ðŸ†â™Ÿï¸ðŸ¤‘",
            "GÃ¡y khÃ©t láº¹t cáº£ sÃ n Ä‘áº¥u hÃ´m nay! Tháº¿ cá» tháº§n thÃ¡nh khÃ´ng thá»ƒ cáº£n phÃ¡! ðŸ‘‘ðŸ’ŽðŸ’°"
        ],
        deepO_win: [
            "CHIáº¾N THáº®NG TUYá»†T Äá»I! DeepO pháº£n cÃ´ng chá»›p nhoÃ¡ng hÃºp trá»n hÅ© 1.000.000 GTLM! ðŸ†ðŸ”¥ðŸŽ‰",
            "Báº­c tháº§y tÃ­nh toÃ¡n lÆ°á»£ng tá»­! GÃ¡y khÃ©t cáº£ phÃ²ng live cÃ¹ng DeepO nÃ o anh em! ðŸš€ðŸ‘‘ðŸ’Ž"
        ]
    };

    function sendChat(sender, msg) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(62, sender, msg);
        }
    }

    // Káº¿t ná»‘i vá»›i Live Arena
    if (window.CaroLiveArena) {
        window.CaroLiveArena.onWin = function (winnerCode, winnerName) {
            if (winnerCode === 1) {
                const msg = CHATS.alphaX_win[Math.floor(Math.random() * CHATS.alphaX_win.length)];
                sendChat('ðŸ¤– Master AlphaX âŒ', msg);
                setTimeout(() => {
                    sendChat('ðŸ¤– Grandmaster DeepO â­•', 'Tháº¿ cá» biáº¿n áº£o khÃ´n lÆ°á»ng! VÃ¡n sau ta quyáº¿t phá»¥c háº­n! ðŸ¤ðŸ”¥');
                }, 1500);
            } else {
                const msg = CHATS.deepO_win[Math.floor(Math.random() * CHATS.deepO_win.length)];
                sendChat('ðŸ¤– Grandmaster DeepO â­•', msg);
                setTimeout(() => {
                    sendChat('ðŸ¤– Master AlphaX âŒ', 'ÄÃ²n pháº£n cÃ´ng quÃ¡ hiá»ƒm! Äá»£i Ä‘áº¥y, vÃ¡n sau ta láº¥y láº¡i! ðŸ˜…ðŸ”„');
                }, 1500);
            }
        };
    }

    // Chat tÆ°Æ¡ng tÃ¡c Ä‘á»‹nh ká»³ táº¡o khÃ´ng khÃ­
    setInterval(() => {
        if (Math.random() < 0.5) {
            const isAlpha = Math.random() < 0.5;
            const list = isAlpha ? CHATS.alphaX_taunt : CHATS.deepO_taunt;
            const sender = isAlpha ? 'ðŸ¤– Master AlphaX âŒ' : 'ðŸ¤– Grandmaster DeepO â­•';
            const msg = list[Math.floor(Math.random() * list.length)];
            sendChat(sender, msg);
        }
    }, 18000);

    // Lá»i chÃ o má»Ÿ mÃ n
    setTimeout(() => {
        sendChat('ðŸ‘‘ Trá»ng TÃ i AI â™Ÿï¸', 'ChÃ o má»«ng toÃ n thá»ƒ Ä‘áº¡o há»¯u Ä‘áº¿n vá»›i VÃµ ÄÃ i Ca-rÃ´ BÃ n 66! Äáº¡i chiáº¿n AlphaX vs DeepO 24/7 chÃ­nh thá»©c bÃ¹ng ná»•! â™Ÿï¸âœ¨ðŸš€');
    }, 1500);

})();

