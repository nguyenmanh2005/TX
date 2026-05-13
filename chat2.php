<?php
session_start();
if (!isset($_SESSION['Iduser'])) {
    die("Vui lòng đăng nhập để sử dụng chức năng chat!");
}

require 'db_connect.php';
require_once 'load_theme.php';

if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

$userId = $_SESSION['Iduser'];
$stmt = $conn->prepare("SELECT ImageURL FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$avatar = $user['ImageURL'] ?? "https://ui-avatars.com/api/?name=" . urlencode($_SESSION['Name']);
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $username = $_SESSION['Name'];
    $message = trim($_POST['message']);
    if (!empty($message)) {
        require_once 'vocabulary_helper.php';
        $message = VocabularyHelper::mask($message);
        
        $stmt = $conn->prepare("INSERT INTO chat_errors (user_id, username, message, avatar) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $userId, $username, $message, $avatar);
        $stmt->execute();
        $stmt->close();
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'load') {
    $result = $conn->query("
        SELECT id, username, message, created_at, avatar
        FROM chat_errors
        ORDER BY id DESC LIMIT 50
    ");
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    echo json_encode(array_reverse($messages));
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <meta charset="UTF-8">
    <title>Kênh Báo Lỗi - Chat 2</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <style>
        body { 
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            font-family: 'Segoe UI', Arial, sans-serif; 
            padding: 20px;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
        }
        
        * { cursor: inherit; }

        button, a, input[type="button"], input[type="submit"] {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        #chat-box { 
            max-width: 900px; 
            height: 550px; 
            overflow-y: auto; 
            background: rgba(255, 255, 255, 0.98);
            margin: auto; 
            padding: 25px; 
            border-radius: var(--border-radius-lg); 
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 71, 87, 0.5); /* Đỏ hơn để báo lỗi */
        }
        
        .chat-message { 
            display: flex; 
            margin-bottom: 18px; 
            padding: 15px; 
            border-radius: var(--border-radius); 
            background: rgba(255, 235, 238, 0.95);
            border-left: 5px solid #e53935;
            animation: messageSlideIn 0.4s ease;
        }
        
        @keyframes messageSlideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .avatar-img { 
            width: 50px; 
            height: 50px; 
            border-radius: 50%; 
            margin-right: 15px; 
            border: 3px solid #e53935;
            flex-shrink: 0;
        }
        
        .message-content { flex: 1; }
        .message-content strong { color: #e53935; font-size: 16px; }
        .message-content p { margin: 8px 0; color: #333; font-size: 15px; line-height: 1.5; white-space: pre-wrap; }
        .message-content small { color: #888; font-size: 0.85em; }
        
        #chat-form { 
            max-width: 900px; 
            display: flex; 
            gap: 15px; 
            margin: 20px auto; 
            padding: 15px; 
        }
        
        #message { 
            flex: 1; 
            padding: 14px 18px; 
            border: 2px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
            background: #fff;
        }
        
        button { 
            padding: 14px 28px; 
            background: linear-gradient(135deg, #e53935 0%, #c62828 100%);
            color: white; border: none; border-radius: var(--border-radius);
            font-weight: 600; box-shadow: 0 4px 15px rgba(229, 57, 53, 0.4);
            transition: 0.3s;
        }
        
        button:hover { transform: translateY(-3px); box-shadow: 0 6px 25px rgba(229, 57, 53, 0.6); }

        .nav-button {
            display: inline-block;
            margin: 8px 12px;
            padding: 12px 24px;
            background: #444;
            color: #fff;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }
        
        .nav-button:hover { background: #666; transform: translateY(-3px); }

        h2 { text-align: center; color: #e53935; font-size: 32px; margin-bottom: 20px; }
        #threejs-background { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; pointer-events: none; }
    </style>
</head>
<body>
    <canvas id="threejs-background"></canvas>
    <h2>⚠️ Kênh Báo Lỗi - Hệ Thống</h2>
    <div id="chat-box"></div>
    <div id="chat-form">
        <form onsubmit="sendMessage(); return false;" style="display:flex; width:100%; gap:10px;">
            <input type="text" id="message" placeholder="Dán nội dung lỗi vào đây để báo Admin..." autocomplete="off">
            <button type="submit">Báo Lỗi</button>
        </form>
    </div>
    <div style="text-align:center; margin-top: 20px;">
        <a href="index.php" class="nav-button">🏠 Trang chủ</a>
        
        <a href="chat.php" class="nav-button">💬 Chat Thế Giới</a>
    </div>

    <script>
        let lastMessageId = 0;
        let isInitialLoad = true;

        function loadMessages() {
            fetch("chat2.php?action=load")
                .then(res => res.json())
                .then(data => {
                    if (!data || data.length === 0) return;
                    
                    const chatBox = document.getElementById("chat-box");
                    let newMessages = [];
                    
                    if (isInitialLoad) {
                        chatBox.innerHTML = '';
                        newMessages = data;
                        isInitialLoad = false;
                    } else {
                        newMessages = data.filter(msg => parseInt(msg.id) > lastMessageId);
                    }
                    
                    if (newMessages.length > 0) {
                        newMessages.forEach((msg) => {
                            const avatarUrl = msg.avatar || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(msg.username);
                            const safeUsername = msg.username.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                            const safeMessage = msg.message.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                            
                            const messageDiv = document.createElement('div');
                            messageDiv.className = 'chat-message';
                            messageDiv.innerHTML = `
                                <img src="${avatarUrl}" class="avatar-img" onerror="this.src='images.ico'">
                                <div class="message-content">
                                    <strong>${safeUsername}</strong>
                                    <p>${safeMessage}</p>
                                    <small>(${msg.created_at})</small>
                                </div>
                            `;
                            chatBox.appendChild(messageDiv);
                            
                            if (parseInt(msg.id) > lastMessageId) {
                                lastMessageId = parseInt(msg.id);
                            }
                        });
                        chatBox.scrollTop = chatBox.scrollHeight;
                    }
                });
        }

        function sendMessage() {
            const input = document.getElementById("message");
            const message = input.value.trim();
            if (message === '') return;
            
            const formData = new FormData();
            formData.append("message", message);

            fetch("chat2.php", { method: "POST", body: formData })
                .then(() => {
                    input.value = '';
                    loadMessages();
                });
        }

        setInterval(loadMessages, 3000);
        window.onload = loadMessages;

        // Background Three.js
        (function() {
            window.themeConfig = {
                particleCount: 800, particleSize: 0.05, particleColor: "#ff0000", particleOpacity: 0.4,
                shapeCount: 5, shapeColors: ["#ff0000", "#990000"], shapeOpacity: 0.2,
                bgGradient: ["#1a1a2e", "#16213e"]
            };
            const script = document.createElement('script');
            script.src = 'threejs-background.js';
            document.head.appendChild(script);
        })();
    </script>
</body>
</html>