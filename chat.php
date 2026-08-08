<?php
session_start();
if (!isset($_SESSION['Iduser'])) {
    die("Vui lòng đăng nhập để sử dụng chức năng chat!");
}

require 'db_connect.php';

// Load theme
$bypassThemeScripts = true;
require_once 'load_theme.php';
// Đảm bảo $bgGradientCSS có giá trị
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

$userId = $_SESSION['Iduser'];
$stmt = $conn->prepare("SELECT ImageURL, chat_frame_id, avatar_frame_id, Role FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$avatar = $user['ImageURL'] ?? "https://ui-avatars.com/api/?name=" . urlencode($_SESSION['Name']);
$chatFrame = $user['chat_frame_id'] ?? null;
$avatarFrame = $user['avatar_frame_id'] ?? null;
$userRole = (int)($user['Role'] ?? 0);
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    // Rate limit: Tối đa 1 tin nhắn mỗi 1.5 giây để tránh spam
    // KOC (Role 2) và Thương Gia (Role 3) được bypass rate limit
    if ($userRole < 2) {
        $now = microtime(true);
        if (isset($_SESSION['last_chat_time'])) {
            $diff = $now - $_SESSION['last_chat_time'];
            if ($diff < 1.5) {
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => 'Bạn gửi quá nhanh! Vui lòng giãn cách 1.5 giây.']);
                exit;
            }
        }
        $_SESSION['last_chat_time'] = $now;
    }

    $username = $_SESSION['Name'];
    $message = trim($_POST['message']);
    if (!empty($message)) {
        require_once 'vocabulary_helper.php';
        $message = VocabularyHelper::mask($message);
        
        $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar, chat_frame_id) VALUES (?, ?, ?, ?, ?)");
        $chatFrameValue = $chatFrame ?? null;
        $stmt->bind_param("isssi", $userId, $username, $message, $avatar, $chatFrameValue);
        $stmt->execute();
        $stmt->close();

        // --- INTERCEPT CHAT COMMANDS FOR RANDOM EVENTS ---
        $lowerMsg = mb_strtolower($message);
        if ($lowerMsg === '!nhận') {
            // Kiểm tra có event money_rain nào đang hoạt động
            $event = $conn->query("SELECT * FROM random_events WHERE event_type = 'money_rain' AND is_active = 1 AND ends_at > NOW() LIMIT 1")->fetch_assoc();
            if ($event) {
                $eventId = (int)$event['id'];
                // Check if user already claimed
                $check = $conn->query("SELECT id FROM random_event_participants WHERE event_id = $eventId AND user_id = $userId")->fetch_assoc();
                if (!$check) {
                    $config = json_decode($event['config'], true);
                    $claimed = (int)$conn->query("SELECT COUNT(*) as c FROM random_event_participants WHERE event_id = $eventId")->fetch_assoc()['c'];
                    if ($claimed < ($config['max_claims'] ?? 50)) {
                        $reward = rand($config['min_reward'], $config['max_reward']);
                        $conn->begin_transaction();
                        try {
                            $conn->query("UPDATE users SET Money = Money + $reward WHERE Iduser = $userId");
                            $ins = $conn->prepare("INSERT INTO random_event_participants (event_id, user_id, reward_given, reward_amount) VALUES (?, ?, 1, ?)");
                            $ins->bind_param("iii", $eventId, $userId, $reward);
                            $ins->execute();
                            $ins->close();
                            $conn->commit();

                            // Gửi thông báo Hệ thống lên Chat thế giới
                            $sysMsg = "🎉 Chúc mừng @{$username} đã gõ !nhận và húp thành công " . number_format($reward) . " GTLM từ Cơn Mưa GTLM!";
                            $sysMsg = VocabularyHelper::mask($sysMsg);
                            $sysId = 0;
                            $sysName = 'Hệ Thống';
                            $sysAvatar = 'https://cdn-icons-png.flaticon.com/512/1041/1041044.png';
                            $msgStmt = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar, created_at) VALUES (?, ?, ?, ?, NOW())");
                            $msgStmt->bind_param("isss", $sysId, $sysName, $sysMsg, $sysAvatar);
                            $msgStmt->execute();
                            $msgStmt->close();
                        } catch (Exception $e) {
                            $conn->rollback();
                        }
                    }
                }
            }
        } elseif (preg_match('/^!đoán\s+(\d+)$/ui', $message, $matches)) {
            $guess = (int)$matches[1];
            // Kiểm tra có event lucky_number nào đang hoạt động
            $event = $conn->query("SELECT * FROM random_events WHERE event_type = 'lucky_number' AND is_active = 1 AND ends_at > NOW() LIMIT 1")->fetch_assoc();
            if ($event) {
                $eventId = (int)$event['id'];
                $config = json_decode($event['config'], true);
                $range = (int)($config['number_range'] ?? 10);
                if ($guess >= 1 && $guess <= $range) {
                    // Check if already participated
                    $check = $conn->query("SELECT id FROM random_event_participants WHERE event_id = $eventId AND user_id = $userId")->fetch_assoc();
                    if (!$check) {
                        $luckyNumber = $config['lucky_number'] ?? ((($eventId * 7 + 13) % $range) + 1);
                        $winners = (int)$conn->query("SELECT COUNT(*) as c FROM random_event_participants WHERE event_id = $eventId AND reward_given = 1")->fetch_assoc()['c'];
                        $isWin = ($guess === $luckyNumber) && ($winners < ($config['max_winners'] ?? 5));
                        $reward = $isWin ? (int)$config['reward'] : 0;

                        $conn->begin_transaction();
                        try {
                            if ($isWin) {
                                $conn->query("UPDATE users SET Money = Money + $reward WHERE Iduser = $userId");
                            }
                            $ins = $conn->prepare("INSERT INTO random_event_participants (event_id, user_id, reward_given, reward_amount) VALUES (?, ?, ?, ?)");
                            $given = $isWin ? 1 : 0;
                            $ins->bind_param("iiii", $eventId, $userId, $given, $reward);
                            $ins->execute();
                            $ins->close();
                            $conn->commit();

                            // Gửi thông báo Hệ thống lên Chat thế giới
                            if ($isWin) {
                                $sysMsg = "🎉 Chúc mừng @{$username} đã đoán chính xác con số may mắn {$luckyNumber} và húp trọn " . number_format($reward) . " GTLM!";
                            } else {
                                $sysMsg = "❌ @{$username} đã đoán số {$guess} nhưng không chính xác! Số may mắn của vòng này là {$luckyNumber}.";
                            }
                            $sysMsg = VocabularyHelper::mask($sysMsg);
                            $sysId = 0;
                            $sysName = 'Hệ Thống';
                            $sysAvatar = 'https://cdn-icons-png.flaticon.com/512/1041/1041044.png';
                            $msgStmt = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar, created_at) VALUES (?, ?, ?, ?, NOW())");
                            $msgStmt->bind_param("isss", $sysId, $sysName, $sysMsg, $sysAvatar);
                            $msgStmt->execute();
                            $msgStmt->close();
                        } catch (Exception $e) {
                            $conn->rollback();
                        }
                    }
                }
            }
        }
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'load') {
    $result = $conn->query("
        SELECT cm.id, cm.username, cm.message, cm.created_at, cm.avatar, cm.chat_frame_id, 
               cf.ImageURL AS frame_image,
               u.active_title_id, u.avatar_frame_id, u.Role,
               a.icon as title_icon, a.name as title_name,
               af.ImageURL AS avatar_frame_image
        FROM chat_messages cm
        LEFT JOIN chat_frames cf ON cm.chat_frame_id = cf.id
        LEFT JOIN users u ON cm.user_id = u.Iduser
        LEFT JOIN achievements a ON u.active_title_id = a.id
        LEFT JOIN avatar_frames af ON u.avatar_frame_id = af.id
        WHERE cm.username != 'Admin Tester Bot'
        ORDER BY cm.id DESC LIMIT 50
    ");
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $msgId = $row['id'];
        // Fetch reactions for this message
        $reactRes = $conn->query("SELECT emoji, COUNT(*) as count FROM chat_reactions WHERE message_id = $msgId GROUP BY emoji");
        $reactions = [];
        while ($r = $reactRes->fetch_assoc()) {
            $reactions[] = $r;
        }
        $row['reactions'] = $reactions;
        $messages[] = $row;
    }
    echo json_encode(array_reverse($messages));
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'react' && isset($_GET['msg_id']) && isset($_GET['emoji'])) {
    $msgId = (int)$_GET['msg_id'];
    $emoji = $_GET['emoji'];
    
    // Check if already reacted
    $check = $conn->prepare("SELECT id FROM chat_reactions WHERE message_id = ? AND user_id = ? AND emoji = ?");
    $check->bind_param("iis", $msgId, $userId, $emoji);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        // Remove reaction
        $del = $conn->prepare("DELETE FROM chat_reactions WHERE message_id = ? AND user_id = ? AND emoji = ?");
        $del->bind_param("iis", $msgId, $userId, $emoji);
        $del->execute();
        echo json_encode(['status' => 'removed']);
    } else {
        // Add reaction
        $ins = $conn->prepare("INSERT INTO chat_reactions (message_id, user_id, emoji) VALUES (?, ?, ?)");
        $ins->bind_param("iis", $msgId, $userId, $emoji);
        $ins->execute();
        echo json_encode(['status' => 'added']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <meta charset="UTF-8">
  <title>Chat Thế Giới</title>
      <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/red-envelope.css">
    <link rel="stylesheet" href="assets/css/sound-fx-hub.css">
    <script src="assets/js/sound-fx-hub.js"></script>
    <script src="assets/js/red-envelope.js"></script>
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
        margin: 20px auto; 
        padding: 15px; 
    }
    
    #chat-form form {
        display: flex;
        width: 100%;
        gap: 15px;
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
    
    .reaction-bar {
        display: flex;
        gap: 8px;
        margin-top: 10px;
        flex-wrap: wrap;
    }
    
    .reaction-btn {
        background: rgba(255, 255, 255, 0.8);
        border: 1px solid rgba(0,0,0,0.05);
        border-radius: 20px;
        padding: 4px 10px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 4px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    
    .reaction-btn:hover {
        background: #f0f7ff;
        transform: scale(1.1);
        border-color: var(--secondary-color);
    }
    
    .reaction-btn.active {
        background: #e1f5fe;
        border-color: var(--secondary-color);
        box-shadow: 0 2px 8px rgba(52, 152, 219, 0.2);
    }
    
    .reaction-btn span {
        font-weight: 700;
        color: var(--secondary-color);
    }

    .message-actions {
        opacity: 0;
        transition: opacity 0.3s;
        margin-left: 10px;
    }

    .chat-message:hover .message-actions {
        opacity: 1;
    }

    .emoji-picker {
        display: inline-flex;
        gap: 5px;
        background: white;
        padding: 5px 10px;
        border-radius: 30px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        border: 1px solid #eee;
    }

    .emoji-picker span {
        cursor: pointer;
        font-size: 18px;
        transition: transform 0.2s;
    }

    .emoji-picker span:hover {
        transform: scale(1.3);
    }
          \n
    
        /* Three.js canvas background */
        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

    </style>
</head>
<body>
    
  <h2 style="text-align:center;">💬 Kênh Chat Thế Giới</h2>
  <div id="chat-box"></div>
  <div id="chat-form">
    <form onsubmit="sendMessage(); return false;">
      <input type="text" id="message" placeholder="Nhập tin nhắn của bạn... (thử !nhận khi có event)" autocomplete="off">
      <button type="submit">💬 Gửi</button>
      <button type="button" id="btn-red-packet" onclick="openRedPacketModal()" style="background: linear-gradient(135deg, #e53935 0%, #b71c1c 100%); margin-left: 10px; position:relative;">🧧 Lì Xì
        <span id="rp-pulse" style="display:none; position:absolute; top:-4px; right:-4px; width:12px; height:12px; background:#fbbf24; border-radius:50%; box-shadow:0 0 8px #fbbf24; animation:rpPulse 1s infinite;"></span>
      </button>
      <button type="button" onclick="toggleSoundWidget()" title="Âm Thanh" style="background:linear-gradient(135deg,#1e293b,#0f172a); border:1px solid #475569; margin-left:8px; padding:14px 16px; font-size:18px;">🔊</button>
    </form>
  </div>
  <style>
  @keyframes rpPulse {
    0%,100%{transform:scale(1);opacity:1;}
    50%{transform:scale(1.6);opacity:0.5;}
  }
  </style>
  <div style="text-align:center; margin-top: 20px;">
    <a href="index.php" class="nav-button">🏠 Trang chủ</a>
    <a href="chat2.php" class="nav-button">💬 Chat 2</a>
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
    
    let lastMessageId = 0;
    let isInitialLoad = true;

    // --- AVATAR FALLBACK: Tạo SVG avatar từ chữ cái đầu, không cần internet ---
    function makeAvatarFallback(username) {
        const colors = [
            '#ef4444','#f97316','#eab308','#22c55e','#14b8a6',
            '#3b82f6','#8b5cf6','#ec4899','#06b6d4','#f59e0b'
        ];
        const name = (username || 'U').trim();
        // Hash tên để chọn màu ổn định (cùng tên luôn cùng màu)
        let hash = 0;
        for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
        const color = colors[Math.abs(hash) % colors.length];
        // Lấy 1-2 ký tự đầu
        const initials = name.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 50 50">
            <rect width="50" height="50" rx="25" fill="${color}"/>
            <text x="50%" y="50%" dominant-baseline="central" text-anchor="middle"
                font-family="Segoe UI,Arial,sans-serif" font-size="20" font-weight="700" fill="white">${initials}</text>
        </svg>`;
        return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
    }

    function loadMessages() {
      fetch("chat.php?action=load")
        .then(res => {
          if (!res.ok) {
            throw new Error(`Server returned HTTP ${res.status}`);
          }
          return res.json();
        })
        .then(data => {
          if (!data || !Array.isArray(data)) return;
          if (data.length === 0) return;
          
          const chatBox = document.getElementById("chat-box");
          
          if (isInitialLoad) {
            chatBox.innerHTML = '';
          }
          
          data.forEach((msg, index) => {
            try {
              const avatarUrl = (msg.avatar && msg.avatar.trim() && !msg.avatar.includes('avatar_default')) 
                  ? msg.avatar 
                  : makeAvatarFallback(msg.username);
              const frameImage = msg.frame_image || null;
              const avatarFrameImage = msg.avatar_frame_image || null;
              
              const safeUsername = (msg.username || 'User').replace(/</g, '&lt;').replace(/>/g, '&gt;');
              const safeMessage = (msg.message || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
              const titleIcon = msg.title_icon ? msg.title_icon.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '';
              const titleName = msg.title_name ? msg.title_name.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '';
              const titleDisplay = titleIcon ? `<span style="font-size: 18px; margin-right: 5px;" title="${titleName}">${titleIcon}</span>` : '';
              
              const role = parseInt(msg.Role) || 0;
              let roleBadge = '';
              if (role === 2) {
                  roleBadge = '<span style="background: #00e5ff; color: #000; padding: 2px 6px; border-radius: 4px; font-size: 12px; margin-right: 5px; font-weight: bold; text-shadow: 0 0 5px rgba(255,255,255,0.5);">💎 KOC</span>';
              } else if (role === 3) {
                  roleBadge = '<span style="background: linear-gradient(135deg, #ffd700 0%, #ff8c00 100%); color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 12px; margin-right: 5px; font-weight: bold; box-shadow: 0 0 8px rgba(255,215,0,0.6);">👑 Thương Gia</span>';
              }

              let reactionHtml = '<div class="reaction-bar">';
              if (msg.reactions && msg.reactions.length > 0) {
                msg.reactions.forEach(r => {
                  reactionHtml += `
                    <div class="reaction-btn" onclick="toggleReaction(${msg.id}, '${r.emoji}')">
                      ${r.emoji} <span>${r.count}</span>
                    </div>
                  `;
                });
              }
              
              const quickEmojis = ['👍', '😂', '🔥', '❤️', '😮', '😢'];
              let pickerHtml = '<div class="message-actions"><div class="emoji-picker">';
              quickEmojis.forEach(e => {
                pickerHtml += `<span onclick="toggleReaction(${msg.id}, '${e}')">${e}</span>`;
              });
              pickerHtml += '</div></div>';
              
              reactionHtml += '</div>';

              // Check if message is already rendered
              const existingDiv = chatBox.querySelector(`.chat-message[data-msg-id="${msg.id}"]`);
              if (existingDiv) {
                // Update only the reaction bar in real-time
                const existingBar = existingDiv.querySelector('.reaction-bar');
                if (existingBar) {
                  existingBar.outerHTML = reactionHtml;
                }
              } else {
                // Render new message
                let avatarHtml = `<div class="avatar-frame">`;
                if (avatarFrameImage && typeof avatarFrameImage === 'string' && avatarFrameImage.trim() !== '') {
                  avatarHtml += `<div class="frame-overlay" style="pointer-events: none !important;"><img src="${avatarFrameImage.replace(/</g, '&lt;').replace(/>/g, '&gt;')}" alt="Frame" style="pointer-events: none !important;" onerror="this.style.display='none'"></div>`;
                }
                 const _safeAvtUrl = avatarUrl.replace(/"/g, '&quot;');
                 avatarHtml += `<img src="${_safeAvtUrl}" alt="${safeUsername}" style="pointer-events: auto;" onerror="this.onerror=null; this.src=makeAvatarFallback('${safeUsername.replace(/'/g, '')}');"></div>`;

                let messageDiv = document.createElement('div');
                messageDiv.setAttribute('data-msg-id', msg.id);
                
                const delay = isInitialLoad ? (index * 0.05) : 0;
                
                if (frameImage && typeof frameImage === 'string' && frameImage.startsWith('uploads/')) {
                  messageDiv.className = 'chat-message';
                  messageDiv.style.backgroundImage = `url('${frameImage.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/'/g, "\\'")}')`;
                  messageDiv.style.backgroundSize = 'cover';
                  messageDiv.style.backgroundRepeat = 'no-repeat';
                  messageDiv.style.backgroundPosition = 'center';
                  messageDiv.style.animationDelay = `${delay}s`;
                } else {
                  messageDiv.className = 'chat-message default-frame';
                  messageDiv.style.animationDelay = `${delay}s`;
                }

                messageDiv.innerHTML = `
                  ${avatarHtml}
                  <div class="message-content">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                      <strong>${roleBadge}${titleDisplay}${safeUsername}</strong>
                      ${pickerHtml}
                    </div>
                    <p>${safeMessage.replace(/\[Click để nhận\]\(#packet-(\d+)\)/g, '<br><button type="button" class="btn-sm btn-danger" style="margin-top:5px; padding:5px 10px; font-size:12px; background: #e53935; color: #fff; border:none; border-radius:5px; cursor:pointer;" onclick="claimRedPacket($1)">🧧 Nhận Lì Xì</button>')}</p>
                    ${reactionHtml}
                    <small>(${msg.created_at})</small>
                  </div>
                `;
                
                chatBox.appendChild(messageDiv);
                
                if (parseInt(msg.id) > lastMessageId) {
                  lastMessageId = parseInt(msg.id);
                }
              }
            } catch (err) {
              console.error("Error rendering message:", msg, err);
            }
          });
          
          if (isInitialLoad) {
            chatBox.scrollTop = chatBox.scrollHeight;
            isInitialLoad = false;
          }
        })
        .catch(err => console.error('Error loading messages:', err));
    }

    function toggleReaction(msgId, emoji) {
      fetch(`chat.php?action=react&msg_id=${msgId}&emoji=${encodeURIComponent(emoji)}`)
        .then(res => res.json())
        .then(data => {
          loadMessages();
        })
        .catch(err => console.error('Error toggling reaction:', err));
    }

    function sendMessage() {
      const messageInput = document.getElementById("message");
      const message = messageInput.value.trim();
      const submitButton = document.querySelector('button[type="submit"]');
      
      if (message === '') {
        if (typeof Swal !== 'undefined') { Swal.fire('Thông báo', String("Vui lòng nhập nội dung!"), 'warning'); }
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
        .then(async res => {
          if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            throw new Error(data.message || 'Có lỗi xảy ra khi gửi tin nhắn!');
          }
          messageInput.value = '';
          loadMessages();
        })
        .catch(err => {
          console.error('Error sending message:', err);
          if (typeof Swal !== 'undefined') { Swal.fire('Thông báo', String(err.message || 'Có lỗi xảy ra khi gửi tin nhắn!'), 'info'); }
        })
        .finally(() => {
          messageInput.disabled = false;
          if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = 'Gửi';
          }
          messageInput.focus();
        });
    }
    
    // --- LÌ XÌ (RED ENVELOPE) LOGIC — Dùng api_red_envelope.php (4B) ---
    function openRedPacketModal() {
        if (typeof Swal === 'undefined') return;
        Swal.fire({
            title: '🧧 Phát Mưa Lì Xì Lên Kênh Chat',
            background: '#1e293b',
            color: '#f8fafc',
            html: `
                <div style="text-align:left; margin-top:15px;">
                    <label style="font-weight:800; color:#fbbf24; display:block; margin-bottom:4px;">💰 Tổng Số GTLM Phát (tối thiểu 10,000):</label>
                    <input type="number" id="rp-amount" class="swal2-input" placeholder="VD: 50000" min="10000" style="background:#0f172a; color:#f8fafc; border-color:#475569;">

                    <label style="font-weight:800; color:#fbbf24; display:block; margin:14px 0 4px;">📦 Số Lượng Bao Lì Xì (1-50):</label>
                    <input type="number" id="rp-pieces" class="swal2-input" placeholder="VD: 10" min="1" max="50" style="background:#0f172a; color:#f8fafc; border-color:#475569;">

                    <label style="font-weight:800; color:#fbbf24; display:block; margin:14px 0 4px;">🎊 Kiểu Chia Lộc:</label>
                    <select id="rp-type" class="swal2-input" style="background:#0f172a; color:#f8fafc; border-color:#475569;">
                        <option value="random">🎲 May Mắn — Ngẫu nhiên (giật nhiều hơn nếu may)</option>
                        <option value="equal">⚖️ Chia Đều — Mỗi bao như nhau</option>
                    </select>

                    <label style="font-weight:800; color:#fbbf24; display:block; margin:14px 0 4px;">💬 Lời Chúc Phát Lộc:</label>
                    <input type="text" id="rp-message" class="swal2-input" placeholder="Chúc đạo hữu húp đậm GTLM!" value="Chúc đạo hữu húp đậm GTLM! 🧧" style="background:#0f172a; color:#f8fafc; border-color:#475569;">
                </div>
            `,
            confirmButtonText: '🧧 Phát Mưa Lì Xì',
            confirmButtonColor: '#dc2626',
            showCancelButton: true,
            cancelButtonText: 'Hủy',
            preConfirm: () => {
                const amt = parseInt(document.getElementById('rp-amount').value);
                const pcs = parseInt(document.getElementById('rp-pieces').value);
                const msg = document.getElementById('rp-message').value;
                const type = document.getElementById('rp-type').value;
                if (!amt || amt < 10000) {
                    Swal.showValidationMessage('Số GTLM tối thiểu là 10,000!');
                    return false;
                }
                if (!pcs || pcs < 1 || pcs > 50) {
                    Swal.showValidationMessage('Số lượng bao từ 1 đến 50!');
                    return false;
                }
                if (amt / pcs < 100) {
                    Swal.showValidationMessage('Mỗi bao tối thiểu 100 GTLM!');
                    return false;
                }
                return { amt, pcs, msg, type };
            }
        }).then((res) => {
            if (res.isConfirmed) {
                const fd = new FormData();
                fd.append('total_amount', res.value.amt);
                fd.append('total_count', res.value.pcs);
                fd.append('message', res.value.msg);
                fd.append('type', res.value.type);

                fetch('api_red_envelope.php?action=create', {
                    method: 'POST',
                    body: fd
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        if (typeof SoundFXHub !== 'undefined') SoundFXHub.playLuckySpin();
                        Swal.fire({
                            title: '🎉 Mưa Lì Xì Đã Rơi!',
                            text: data.message,
                            icon: 'success',
                            background: '#1e293b',
                            color: '#f8fafc',
                            confirmButtonColor: '#dc2626'
                        });
                        loadMessages();
                        // Trigger RedEnvelopeHub poll ngay lập tức
                        if (typeof RedEnvelopeHub !== 'undefined') RedEnvelopeHub.poll();
                    } else {
                        Swal.fire({ title: 'Lỗi', text: data.message, icon: 'error', background: '#1e293b', color: '#f8fafc' });
                    }
                }).catch(() => {
                    Swal.fire('Lỗi', 'Có lỗi xảy ra khi phát lì xì!', 'error');
                });
            }
        });
    }

    function toggleSoundWidget() {
        const widget = document.getElementById('gtlm-sound-widget');
        if (widget) {
            widget.style.display = widget.style.display === 'none' ? 'flex' : 'none';
        } else if (typeof SoundFXHub !== 'undefined') {
            SoundFXHub.playPop();
        }
    }

    // Hiện pulse khi có lì xì active
    setInterval(() => {
        fetch('api_red_envelope.php?action=list')
            .then(r => r.json())
            .then(d => {
                const pulse = document.getElementById('rp-pulse');
                if (pulse) pulse.style.display = (d.success && d.envelopes && d.envelopes.length > 0) ? 'block' : 'none';
            })
            .catch(() => {});
    }, 10000);
    // Lần đầu check ngay
    setTimeout(() => {
        fetch('api_red_envelope.php?action=list').then(r=>r.json()).then(d=>{
            const pulse = document.getElementById('rp-pulse');
            if (pulse) pulse.style.display = (d.success && d.envelopes && d.envelopes.length > 0) ? 'block' : 'none';
        }).catch(()=>{});
    }, 1500);

    function claimRedPacket(packetId) {
        if (!packetId) return;
        // Dùng RedEnvelopeHub nếu có, fallback về api cũ
        if (typeof RedEnvelopeHub !== 'undefined') {
            RedEnvelopeHub.claim(packetId);
            return;
        }
        const fd = new FormData();
        fd.append('envelope_id', packetId);
        fetch('api_red_envelope.php?action=claim', {
            method: 'POST',
            body: fd
        }).then(r => r.json()).then(data => {
            if (data.success) {
                if (typeof SoundFXHub !== 'undefined') SoundFXHub.playLotteryWin();
                Swal.fire({
                    title: '🧧 GIẬT LỘC THÀNH CÔNG!',
                    html: `<b>+${new Intl.NumberFormat().format(data.amount)} GTLM</b><br>${data.message}`,
                    icon: 'success',
                    background: '#1e293b',
                    color: '#f8fafc',
                    confirmButtonColor: '#dc2626'
                });
            } else {
                Swal.fire({ title: 'Thông báo', text: data.message, icon: 'warning', background: '#1e293b', color: '#f8fafc' });
            }
        }).catch(() => {
            Swal.fire('Lỗi', 'Có lỗi xảy ra khi nhận lì xì!', 'error');
        });
    }

    // Refresh chat auto
    setInterval(loadMessages, 3000);
    loadMessages(); // Gọi hàm load message ban đầu
  </script>

    <!-- Three.js Background System -->
    <canvas id="threejs-background"></canvas>
    <script>
        (function() {
            window.themeConfig = {
                particleCount: <?= $particleCount ?? 800 ?>,
                particleSize: <?= $particleSize ?? 0.05 ?>,
                particleColor: '<?= $particleColor ?? "#ffffff" ?>',
                particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
                shapeCount: <?= $shapeCount ?? 10 ?>,
                shapeColors: <?= json_encode($shapeColors ?? ["#667eea", "#764ba2", "#4facfe", "#00f2fe"]) ?>,
                shapeOpacity: <?= $shapeOpacity ?? 0.3 ?>,
                bgGradient: <?= json_encode($bgGradient ?? ["#667eea", "#764ba2", "#4facfe"]) ?>
            };
            const isInGames = window.location.pathname.includes('/games/');
            const script = document.createElement('script');
            script.src = isInGames ? '../threejs-background.js' : 'threejs-background.js';
            document.head.appendChild(script);
        })();
    </script>

</body>
</html>