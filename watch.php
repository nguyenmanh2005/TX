<?php
session_start();
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}
require 'db_connect.php';
$streamId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đang Xem Live - GTLM Gaming</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg: #0b0e14;
            --panel: #181c25;
            --accent: #a855f7;
            --primary: #6366f1;
        }

        body {
            background: var(--bg);
            color: #fff;
            font-family: 'Inter', sans-serif;
            margin: 0;
            overflow: hidden;
            height: 100vh;
        }

        .main-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            height: 100vh;
        }

        /* ── Player Area ── */
        .player-area {
            position: relative;
            background: #000;
            display: flex;
            flex-direction: column;
        }

        .stream-header {
            padding: 15px 25px;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 10;
        }

        .video-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle, #1e293b 0%, #000 100%);
            position: relative;
        }

        .game-placeholder {
            text-align: center;
            opacity: 0.5;
        }

        /* ── Floating Reactions ── */
        .reaction-overlay {
            position: absolute;
            bottom: 100px;
            right: 50px;
            width: 100px;
            height: 400px;
            pointer-events: none;
            z-index: 5;
        }

        .floating-emoji {
            position: absolute;
            bottom: 0;
            font-size: 30px;
            animation: floatUp 3s forwards ease-out;
        }

        @keyframes floatUp {
            0% { transform: translateY(0) scale(0.5); opacity: 0; }
            10% { opacity: 1; transform: translateY(-20px) scale(1.2); }
            100% { transform: translateY(-400px) translateX(calc(Math.random() * 50px)) scale(0.8); opacity: 0; }
        }

        /* ── Chat Area ── */
        .chat-area {
            background: var(--panel);
            border-left: 1px solid rgba(255,255,255,0.05);
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            padding: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-weight: 800;
            text-transform: uppercase;
            font-size: 14px;
            color: #94a3b8;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .chat-msg {
            font-size: 14px;
            line-height: 1.4;
        }

        .chat-user { font-weight: 800; color: var(--accent); margin-right: 5px; }

        .chat-input-wrapper {
            padding: 15px;
            background: rgba(0,0,0,0.2);
        }

        .chat-input {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: #fff;
            padding: 12px;
            border-radius: 10px;
            outline: none;
        }

        /* ── Bottom Controls ── */
        .bottom-bar {
            padding: 20px 25px;
            background: var(--panel);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .reaction-btns {
            display: flex;
            gap: 10px;
        }

        .react-btn {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: #fff;
            padding: 8px 15px;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 20px;
        }

        .react-btn:hover { background: rgba(255,255,255,0.15); transform: scale(1.1); }

        .betting-panel {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn-bet {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            padding: 10px 25px;
            border-radius: 10px;
            border: none;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .back-btn {
            color: #94a3b8;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>

    <div class="main-layout">
        <div class="player-area">
            <header class="stream-header">
                <a href="spectator.php" class="back-btn"><i class="fa fa-arrow-left"></i> Rời phòng</a>
                <div style="text-align: right;">
                    <div id="streamer-name" style="font-weight: 800; font-size: 18px;">---</div>
                    <div id="game-type" style="color: #94a3b8; font-size: 12px;">Đang tải...</div>
                </div>
            </header>

            <div class="video-container">
                <div class="game-placeholder">
                    <i class="fa fa-gamepad" style="font-size: 100px; margin-bottom: 20px; color: var(--accent);"></i>
                    <h2 id="stream-status">Đang đồng bộ dữ liệu trận đấu...</h2>
                    <p style="opacity: 0.7;">Bạn đang theo dõi trận đấu trực tiếp</p>
                </div>

                <!-- Floating Reactions Overlay -->
                <div class="reaction-overlay" id="reaction-overlay"></div>
            </div>

            <div class="bottom-bar">
                <div id="status-box" class="thongbao">Sẵn sàng! Hãy ra chiêu ngay.</div>
                <div class="reaction-btns">
                    <button class="react-btn" onclick="sendReaction('❤️')">❤️</button>
                    <button class="react-btn" onclick="sendReaction('🔥')">🔥</button>
                    <button class="react-btn" onclick="sendReaction('🤣')">🤣</button>
                    <button class="react-btn" onclick="sendReaction('💸')">💸</button>
                    <button class="react-btn" onclick="sendReaction('👏')">👏</button>
                </div>

                <div class="betting-panel">
                    <div id="my-bet-info" style="font-size: 13px; color: #10b981;"></div>
                    <button id="btn-start" class="btn-game btn-start" onclick="openBetModal()">🎯 Thả thính</button>
                    <button class="react-btn" style="color: #f1c40f;" onclick="tipModal()"><i class="fa fa-coins"></i> TIP</button>
                </div>
            </div>
        </div>

        <div class="chat-area">
            <div class="chat-header">Trò chuyện trực tiếp</div>
            <div class="chat-messages" id="chat-messages">
                <!-- Messages load here -->
            </div>
            <div class="chat-input-wrapper">
                <input type="text" class="chat-input" id="chat-input" placeholder="Nhập tin nhắn..." onkeypress="if(event.key==='Enter') sendChat()">
            </div>
        </div>
    </div>

    <script>
        const streamId = <?= $streamId ?>;
        let lastChatId = 0;
        let processedReactions = new Set();

        function loadDetails() {
            $.get('api_spectator.php?action=get_details&stream_id=' + streamId, function(res) {
                if (res.success) {
                    $('#streamer-name').text(res.stream.streamer_name);
                    $('#game-type').text('Đang chơi: ' + res.stream.game_type);
                    
                    // Render Chat
                    res.chats.forEach(chat => {
                        if (chat.id > lastChatId) {
                            $('#chat-messages').append(`
                                <div class="chat-msg">
                                    <span class="chat-user">${chat.user_name}:</span>
                                    <span>${chat.message}</span>
                                </div>
                            `);
                            lastChatId = chat.id;
                            scrollToBottom();
                        }
                    });

                    // Render Reactions
                    res.reactions.forEach(r => {
                        if (!processedReactions.has(r.id)) {
                            spawnEmoji(r.emoji);
                            processedReactions.add(r.id);
                        }
                    });

                    // Update My Bet
                    if (res.my_bet) {
                        $('#my-bet-info').html(`<i class="fa fa-check-circle"></i> Đã ra chiêu: <b>${new Intl.NumberFormat().format(res.my_bet.amount)}</b>`);
                        $('.btn-bet').hide();
                    }

                    // Clean up old reactions set (keep it small)
                    if (processedReactions.size > 100) processedReactions.clear();
                } else {
                    Swal.fire('Lỗi', res.message, 'error').then(() => { window.location.href = 'spectator.php'; });
                }
            });
        }

        function scrollToBottom() {
            const chat = document.getElementById('chat-messages');
            chat.scrollTop = chat.scrollHeight;
        }

        function spawnEmoji(emoji) {
            const id = 'emoji-' + Math.random().toString(36).substr(2, 9);
            const left = Math.random() * 80;
            $('#reaction-overlay').append(`<div class="floating-emoji" id="${id}" style="left: ${left}px;">${emoji}</div>`);
            setTimeout(() => { $(`#${id}`).remove(); }, 3000);
        }

        function sendReaction(emoji) {
            $.post('api_spectator.php', { action: 'send_reaction', stream_id: streamId, emoji: emoji });
            spawnEmoji(emoji);
        }

        function sendChat() {
            const msg = $('#chat-input').val().trim();
            if (!msg) return;
            $('#chat-input').val('');
            $.post('api_spectator.php', { action: 'send_chat', stream_id: streamId, message: msg }, function() {
                loadDetails();
            });
        }

        function openBetModal() {
            Swal.fire({
                title: 'Thả thính theo Streamer',
                text: 'Nếu Streamer thắng trận này, bạn sẽ nhận được thưởng (x1.95)!',
                input: 'number',
                inputAttributes: { min: 1000, step: 1000 },
                inputPlaceholder: 'Nhập số GTLM muốn chiến...',
                showCancelButton: true,
                confirmButtonText: 'Xác Nhận Cược',
                preConfirm: (amount) => {
                    if (!amount || amount < 1000) {
                        Swal.showValidationMessage('Tối thiểu 1,000 gtlm');
                    }
                    return amount;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_spectator.php', { 
                        action: 'place_bet', 
                        stream_id: streamId, 
                        bet_on_user: 0, // 0 = Streamer chính
                        amount: result.value 
                    }, function(res) {
                        if (res.success) {
                            Swal.fire('Thành công!', res.message, 'success');
                            loadDetails();
                        } else {
                            Swal.fire('Lỗi!', res.message, 'error');
                        }
                    });
                }
            });
        }

        function tipModal() {
            Swal.fire({
                title: 'Tip cho Streamer',
                input: 'number',
                inputPlaceholder: 'Số  Gtlm muốn Tip...',
                showCancelButton: true,
                confirmButtonText: 'Gửi Tip'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_spectator.php', { action: 'tip', stream_id: streamId, amount: result.value }, function(res) {
                        Swal.fire(res.success ? 'Thành công' : 'Lỗi', res.message, res.success ? 'success' : 'error');
                    });
                }
            });
        }

        $(document).ready(() => {
            loadDetails();
            setInterval(loadDetails, 2000);
        });
    </script>
</body>
</html>
