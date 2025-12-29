<?php
session_start();
if (!isset($_SESSION['Iduser'])) {
    die("Vui lòng đăng nhập để sử dụng chức năng chat!");
}

require 'db_connect.php';

// Load theme
require_once 'load_theme.php';

$userId = $_SESSION['Iduser'];
$stmt = $conn->prepare("SELECT ImageURL, chat_frame_id, avatar_frame_id FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$avatar = $user['ImageURL'] ?? "https://ui-avatars.com/api/?name=" . urlencode($_SESSION['Name']);
$chatFrame = $user['chat_frame_id'] ?? null;
$avatarFrame = $user['avatar_frame_id'] ?? null;
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $username = $_SESSION['Name'];
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar, chat_frame_id) VALUES (?, ?, ?, ?, ?)");
        $chatFrameValue = $chatFrame ?? null;
        $stmt->bind_param("isssi", $userId, $username, $message, $avatar, $chatFrameValue);
        $stmt->execute();
        $stmt->close();
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'load') {
    $result = $conn->query("
        SELECT cm.username, cm.message, cm.created_at, cm.avatar, cm.chat_frame_id, 
               cf.ImageURL AS frame_image,
               u.active_title_id, u.avatar_frame_id,
               a.icon as title_icon, a.name as title_name,
               af.ImageURL AS avatar_frame_image
        FROM chat_messages cm
        LEFT JOIN chat_frames cf ON cm.chat_frame_id = cf.id
        LEFT JOIN users u ON cm.user_id = u.Iduser
        LEFT JOIN achievements a ON u.active_title_id = a.id
        LEFT JOIN avatar_frames af ON u.avatar_frame_id = af.id
        ORDER BY cm.id DESC LIMIT 50
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
  <meta charset="UTF-8">
  <title>Chat Thế Giới</title>
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
    
    * {
        cursor: inherit;
    }

    button, a, input[type="button"], input[type="submit"], label, select {
        cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
    }
    
    input[type="text"] {
        cursor: text !important;
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
        border: 2px solid rgba(255, 255, 255, 0.5);
    }
    
    #chat-box::-webkit-scrollbar {
        width: 8px;
    }
    
    #chat-box::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.05);
        border-radius: 10px;
    }
    
    #chat-box::-webkit-scrollbar-thumb {
        background: var(--secondary-color);
        border-radius: 10px;
    }
    
    .chat-message { 
        display: flex; 
        margin-bottom: 18px; 
        padding: 15px; 
        border-radius: var(--border-radius); 
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease;
        animation: messageSlideIn 0.4s ease;
    }
    
    @keyframes messageSlideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .chat-message:hover { 
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }
    
    .avatar-frame { 
        width: 50px; 
        height: 50px; 
        overflow: visible; 
        border-radius: 50%; 
        margin-right: 15px; 
        border: 3px solid var(--secondary-color);
        box-shadow: var(--shadow);
        flex-shrink: 0;
        position: relative;
        display: inline-block;
    }
    
    .avatar-frame img { 
        width: 100%; 
        height: 100%; 
        object-fit: cover; 
        border-radius: 50%;
        position: relative;
        z-index: 2;
        pointer-events: auto;
        display: block;
    }
    
    .avatar-frame .frame-overlay {
        position: absolute;
        top: -5px;
        left: -5px;
        width: calc(100% + 10px);
        height: calc(100% + 10px);
        border-radius: 50%;
        z-index: 1;
        pointer-events: none !important;
    }
    
    .avatar-frame .frame-overlay img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        border-radius: 50%;
        position: absolute;
        top: 0;
        left: 0;
        pointer-events: none !important;
    }
    
    .message-content { 
        flex: 1; 
    }
    
    .message-content strong { 
        color: var(--primary-color);
        font-size: 16px;
        font-weight: 700;
    }
    
    .message-content p { 
        margin: 8px 0; 
        color: var(--text-dark);
        font-size: 15px;
        line-height: 1.5;
    }
    
    .message-content small { 
        color: var(--text-light); 
        font-size: 0.85em; 
    }
    
    .default-frame { 
        background: rgba(227, 242, 253, 0.95);
        border-left: 5px solid #2196f3; 
    }
    
    .blue-frame { 
        background: rgba(224, 247, 250, 0.95);
        border-left: 5px solid #00acc1; 
    }
    
    .red-frame { 
        background: rgba(255, 235, 238, 0.95);
        border-left: 5px solid #e53935; 
    }
    .gold-frame { 
        background: rgba(255, 249, 230, 0.95);
        border-left: 5px solid #fbc02d; 
    }
    
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
        border: 2px solid var(--border-color);
        border-radius: var(--border-radius);
        font-size: 16px;
        background: rgba(255, 255, 255, 0.95);
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
        cursor: text !important;
    }
    
    #message:focus {
        outline: none;
        border-color: var(--secondary-color);
        box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.15);
        background: rgba(255, 255, 255, 1);
    }

    button { 
        padding: 14px 28px; 
        background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
        color: white; 
        border: none; 
        border-radius: var(--border-radius);
        cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        font-weight: 600;
        font-size: 16px;
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }
    
    button:hover {
        cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
    }
    
    button::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }
    
    button:hover::before {
        width: 300px;
        height: 300px;
    }
    
    button:hover:not(:disabled) {
        transform: translateY(-3px) scale(1.05);
        box-shadow: 0 6px 25px rgba(52, 152, 219, 0.6);
    }
    
    button:active:not(:disabled) {
        transform: translateY(-1px) scale(1.02);
    }
    
    button:disabled {
        opacity: 0.6;
        cursor: not-allowed !important;
    }
    
    .nav-button {
      display: inline-block;
      margin: 8px 12px;
      padding: 12px 24px;
      background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
      color: #fff;
      border-radius: var(--border-radius);
      text-decoration: none;
      font-weight: 600;
      box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
      position: relative;
      overflow: hidden;
    }
    
    .nav-button:hover {
        cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
    }
    
    .nav-button::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }
    
    .nav-button:hover::before {
        width: 300px;
        height: 300px;
    }
    
    .nav-button:hover {
      transform: translateY(-3px) scale(1.05);
      box-shadow: 0 8px 25px rgba(52, 152, 219, 0.6);
    }
    
    h2 {
        text-align: center;
        color: var(--primary-color);
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 20px;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        animation: fadeInDown 0.6s ease;
    }
    
    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .avatar-frame {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .avatar-frame:hover {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
    }
  </style>
</head>
<body>
  <h2 style="text-align:center;">💬 Kênh Chat Thế Giới</h2>
  <div id="chat-box"></div>
  <div id="chat-form">
    <form onsubmit="sendMessage(); return false;">
      <input type="text" id="message" placeholder="Nhập tin nhắn của bạn..." autocomplete="off">
      <button type="submit">Gửi</button>
    </form>
  </div>
  <div style="text-align:center; margin-top: 20px;">
    <a href="index.php" class="nav-button">🏠 Trang chủ</a>
    <a href="khungchat.php" class="nav-button">🎨 Chọn khung chat</a>
    <a href="khungavatar.php" class="nav-button">🖼️ Chọn khung avatar</a>
  </div>

  <script>
    // Đảm bảo cursor luôn hoạt động
    document.addEventListener('DOMContentLoaded', function() {
        document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
        
        // Set cursor cho tất cả buttons và links
        const buttons = document.querySelectorAll('button, a, input[type="button"], input[type="submit"]');
        buttons.forEach(el => {
            el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            // Đảm bảo cursor không bị mất khi hover
            el.addEventListener('mouseenter', function() {
                this.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            });
            el.addEventListener('mouseleave', function() {
                this.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            });
        });
        
        // Đặc biệt xử lý input text
        const textInputs = document.querySelectorAll('input[type="text"]');
        textInputs.forEach(input => {
            input.style.cursor = "text";
            input.addEventListener('focus', function() {
                this.style.cursor = "text";
            });
        });
        
        // Xử lý các phần tử khác
        const otherElements = document.querySelectorAll('label, select');
        otherElements.forEach(el => {
            el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
        });
    });
    
    function loadMessages() {
      fetch("chat.php?action=load")
        .then(res => res.json())
        .then(data => {
          const chatBox = document.getElementById("chat-box");
          chatBox.innerHTML = '';
          data.forEach((msg, index) => {
            const avatarUrl = msg.avatar || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(msg.username);
            const frameImage = msg.frame_image || null;
            const avatarFrameImage = msg.avatar_frame_image || null;
            
            // Escape HTML để tránh XSS
            const safeUsername = msg.username.replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const safeMessage = msg.message.replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const titleIcon = msg.title_icon ? msg.title_icon.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '';
            const titleName = msg.title_name ? msg.title_name.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '';
            const titleDisplay = titleIcon ? `<span style="font-size: 18px; margin-right: 5px;" title="${titleName}">${titleIcon}</span>` : '';

            // Tạo avatar với frame nếu có
            let avatarHtml = `<div class="avatar-frame">`;
            if (avatarFrameImage && avatarFrameImage.trim() !== '') {
              avatarHtml += `<div class="frame-overlay" style="pointer-events: none !important;"><img src="${avatarFrameImage.replace(/</g, '&lt;').replace(/>/g, '&gt;')}" alt="Frame" style="pointer-events: none !important;" onerror="this.style.display='none'"></div>`;
            }
            avatarHtml += `<img src="${avatarUrl}" alt="${safeUsername}" style="pointer-events: auto;" onerror="this.src='images.ico'"></div>`;

            let messageDiv = '';
            if (frameImage && frameImage.startsWith('uploads/')) {
              messageDiv = `
                <div class="chat-message" style="background-image: url('${frameImage.replace(/</g, '&lt;').replace(/>/g, '&gt;')}');
                background-size: cover; background-repeat: no-repeat; background-position: center; animation-delay: ${index * 0.05}s;">
                  ${avatarHtml}
                  <div class="message-content">
                    <strong>${titleDisplay}${safeUsername}</strong>
                    <p>${safeMessage}</p>
                    <small>(${msg.created_at})</small>
                  </div>
                </div>
               `;
            } else {
              messageDiv = `
                <div class="chat-message default-frame" style="animation-delay: ${index * 0.05}s;">
                  ${avatarHtml}
                  <div class="message-content">
                    <strong>${titleDisplay}${safeUsername}</strong>
                    <p>${safeMessage}</p>
                    <small>(${msg.created_at})</small>
                  </div>
                </div>
               `;
            }

            chatBox.innerHTML += messageDiv;
          });
          chatBox.scrollTop = chatBox.scrollHeight;
        })
        .catch(err => console.error('Error loading messages:', err));
    }

    function sendMessage() {
      const messageInput = document.getElementById("message");
      const message = messageInput.value.trim();
      const submitButton = document.querySelector('button[type="submit"]');
      
      if (message === '') {
        alert("Vui lòng nhập nội dung!");
        return;
      }
      
      // Disable button và input trong khi gửi
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Đang gửi...';
      }
      messageInput.disabled = true;
      
      const formData = new FormData();
      formData.append("message", message);

      fetch("chat.php", { method: "POST", body: formData })
        .then(() => {
          messageInput.value = '';
          messageInput.disabled = false;
          if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = 'Gửi';
          }
          loadMessages();
        })
        .catch(err => {
          console.error('Error sending message:', err);
          alert('Có lỗi xảy ra khi gửi tin nhắn!');
          messageInput.disabled = false;
          if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = 'Gửi';
          }
        });
    }
    
    // Auto refresh messages every 3 seconds
    setInterval(loadMessages, 3000);
    
    window.onload = function() {
        loadMessages();
    };
  </script>
</body>
</html>