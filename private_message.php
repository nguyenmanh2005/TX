<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

// Load theme
require_once 'load_theme.php';

// Đảm bảo $bgGradientCSS có giá trị
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

$userId = $_SESSION['Iduser'];
$friendId = (int)($_GET['friend_id'] ?? 0);
$friendName = $_GET['friend_name'] ?? 'Người dùng';

if ($friendId <= 0) {
    header("Location: friends.php");
    exit();
}

// Kiểm tra có phải bạn bè không
$checkFriendship = $conn->prepare("SELECT * FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'accepted'");
$checkFriendship->bind_param("ii", $userId, $friendId);
$checkFriendship->execute();
$result = $checkFriendship->get_result();

if ($result->num_rows === 0) {
    $checkFriendship->close();
    header("Location: friends.php");
    exit();
}
$checkFriendship->close();

// Lấy thông tin người dùng
$sql = "SELECT Iduser, Name, Money FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Lấy thông tin bạn bè
$sql = "SELECT Iduser, Name, ImageURL FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $friendId);
$stmt->execute();
$result = $stmt->get_result();
$friend = $result->fetch_assoc();
$stmt->close();

if (!$friend) {
    header("Location: friends.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhắn Tin - <?= htmlspecialchars($friend['Name']) ?></title>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .message-container {
            max-width: 900px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 100px);
        }
        
        .message-header {
            padding: 20px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .friend-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
        }
        
        .friend-info h2 {
            margin: 0;
            font-size: 20px;
        }
        
        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .message {
            margin-bottom: 15px;
            display: flex;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message.sent {
            justify-content: flex-end;
        }
        
        .message.received {
            justify-content: flex-start;
        }
        
        .message-bubble {
            max-width: 70%;
            padding: 12px 18px;
            border-radius: 18px;
            word-wrap: break-word;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .message.sent .message-bubble {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 4px;
        }
        
        .message.received .message-bubble {
            background: white;
            color: #333;
            border-bottom-left-radius: 4px;
        }
        
        .message-time {
            font-size: 11px;
            color: #999;
            margin-top: 5px;
        }
        
        .message-input-area {
            padding: 20px;
            background: white;
            border-top: 2px solid #e0e0e0;
            display: flex;
            gap: 10px;
        }
        
        .message-input {
            flex: 1;
            padding: 12px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 24px;
            font-size: 16px;
            resize: none;
            max-height: 100px;
        }
        
        .message-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .send-button {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 24px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .send-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .send-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }
    </style>
</head>
<body>
    <div class="message-container">
        <div class="message-header">
            <img src="<?= htmlspecialchars($friend['ImageURL'] ?? 'images.ico') ?>" alt="<?= htmlspecialchars($friend['Name']) ?>" class="friend-avatar" onerror="this.src='images.ico'">
            <div class="friend-info">
                <h2><?= htmlspecialchars($friend['Name']) ?></h2>
            </div>
        </div>
        
        <div class="messages-area" id="messages-area">
            <div style="text-align: center; padding: 20px; color: #999;">Đang tải tin nhắn...</div>
        </div>
        
        <div class="message-input-area">
            <textarea id="message-input" class="message-input" placeholder="Nhập tin nhắn..." rows="1"></textarea>
            <button id="send-button" class="send-button" onclick="sendMessage()">Gửi</button>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 20px;">
        <a href="friends.php" class="back-link">👥 Về Danh Sách Bạn Bè</a>
    </div>
    
    <script>
        const friendId = <?= $friendId ?>;
        let messageInterval = null;
        
        function loadMessages() {
            fetch(`api_friends.php?action=get_messages&friend_id=${friendId}`)
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('messages-area');
                    if (data.success && data.messages.length > 0) {
                        container.innerHTML = data.messages.map(msg => {
                            const isSent = msg.from_user_id == <?= $userId ?>;
                            const date = new Date(msg.created_at);
                            const timeStr = date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
                            
                            return `
                                <div class="message ${isSent ? 'sent' : 'received'}">
                                    <div class="message-bubble">
                                        <div>${escapeHtml(msg.message)}</div>
                                        <div class="message-time">${timeStr}</div>
                                    </div>
                                </div>
                            `;
                        }).join('');
                        container.scrollTop = container.scrollHeight;
                    } else {
                        container.innerHTML = '<div style="text-align: center; padding: 40px; color: #999;">Chưa có tin nhắn nào. Hãy bắt đầu cuộc trò chuyện!</div>';
                    }
                });
        }
        
        function sendMessage() {
            const input = document.getElementById('message-input');
            const message = input.value.trim();
            
            if (!message) return;
            
            const sendButton = document.getElementById('send-button');
            sendButton.disabled = true;
            sendButton.textContent = 'Đang gửi...';
            
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('to_user_id', friendId);
            formData.append('message', message);
            
            fetch('api_friends.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    loadMessages();
                } else {
                    alert('Lỗi: ' + data.message);
                }
                sendButton.disabled = false;
                sendButton.textContent = 'Gửi';
            });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Auto-resize textarea
        document.getElementById('message-input').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        });
        
        // Send on Enter (Shift+Enter for new line)
        document.getElementById('message-input').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
        // Load messages every 3 seconds
        loadMessages();
        messageInterval = setInterval(loadMessages, 3000);
        
        // Cursor fix
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
            
            const interactiveElements = document.querySelectorAll('button, a, input, label, select');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            });
        });
        
        // Cleanup interval on page unload
        window.addEventListener('beforeunload', function() {
            if (messageInterval) {
                clearInterval(messageInterval);
            }
        });
    </script>
</body>
</html>

