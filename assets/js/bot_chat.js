/**
 * 🎙️ BotChat Framework - Kênh gửi bình luận trực tiếp của Bot Streamer vào LiveStream Chat
 * Gửi tin nhắn đến LiveStream/api_spectator.php action=bot_chat
 * Tuân thủ chuẩn Casino Rule 5.3 & Bảng từ thay thế
 */
(function () {
    'use strict';

    window.BotChat = {
        send: function (tableId, botName, message) {
            if (!message || !tableId) return;

            // Xác định đường dẫn API
            let apiUrl = 'api_spectator.php';
            if (window.location.pathname.includes('/LiveStream/')) {
                apiUrl = 'api_spectator.php';
            } else {
                apiUrl = 'LiveStream/api_spectator.php';
            }

            $.post(apiUrl, {
                action: 'bot_chat',
                table_id: tableId,
                bot_name: botName,
                message: message
            }).fail(function () {
                // Fallback nếu đang ở iframe con
                $.post('../LiveStream/api_spectator.php', {
                    action: 'bot_chat',
                    table_id: tableId,
                    bot_name: botName,
                    message: message
                }).fail(function () {
                    $.post('../api_spectator.php', {
                        action: 'bot_chat',
                        table_id: tableId,
                        bot_name: botName,
                        message: message
                    });
                });
            });
        }
    };

    console.log('[BotChat] Framework đã sẵn sàng kết nối phòng LiveStream 24/7!');
})();
