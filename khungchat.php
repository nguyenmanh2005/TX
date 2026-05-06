<?php
session_start();
if (!isset($_SESSION['Iduser'])) {
    die("Vui lòng đăng nhập!");
}

require 'db_connect.php';

// Kiểm tra kết nối database
if (!$conn || $conn->connect_error) {
    die("Lỗi kết nối database: " . ($conn ? $conn->connect_error : "Không thể kết nối"));
}

// Load theme
require_once 'load_theme.php';
// Đảm bảo $bgGradientCSS có giá trị
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

$userId = $_SESSION['Iduser'];

// Kiểm tra xem bảng chat_frames có tồn tại không
$checkTable = $conn->query("SHOW TABLES LIKE 'chat_frames'");
$chatFramesExists = $checkTable && $checkTable->num_rows > 0;

// Kiểm tra xem cột chat_frame_id có tồn tại không
$checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'chat_frame_id'");
$chatFrameIdExists = $checkColumn && $checkColumn->num_rows > 0;

// Kiểm tra xem bảng user_chat_frames có tồn tại không
$checkUserFrames = $conn->query("SHOW TABLES LIKE 'user_chat_frames'");
$userChatFramesExists = $checkUserFrames && $checkUserFrames->num_rows > 0;

// Lấy thông báo từ session (nếu có)
$message = $_SESSION['khungchat_message'] ?? "";
unset($_SESSION['khungchat_message']);

// Cập nhật lựa chọn khung chat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_frame_id']) && $chatFrameIdExists) {
    $frameId = intval($_POST['chat_frame_id']);
    $stmt = $conn->prepare("UPDATE users SET chat_frame_id = ? WHERE Iduser = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $frameId, $userId);
        if ($stmt->execute()) {
            $_SESSION['khungchat_message'] = "✅ Khung chat đã được cập nhật!";
        } else {
            $_SESSION['khungchat_message'] = "❌ Lỗi: " . $stmt->error;
        }
        $stmt->close();
    }
    // Redirect để tránh resubmit
    header("Location: khungchat.php");
    exit();
}

// Lấy các khung chat mà user đã sở hữu
$frames = [];
if ($chatFramesExists) {
    if ($userChatFramesExists) {
        // Nếu có bảng user_chat_frames, lấy khung đã mua và khung miễn phí
        $sql = "SELECT cf.* FROM chat_frames cf 
                INNER JOIN user_chat_frames ucf ON cf.id = ucf.chat_frame_id 
                WHERE ucf.user_id = ? 
                UNION 
                SELECT cf.* FROM chat_frames cf 
                WHERE cf.price = 0
                ORDER BY price ASC, id ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $frames[] = $row;
            }
            $stmt->close();
        }
    } else {
        // Nếu không có bảng user_chat_frames, chỉ lấy khung miễn phí
        $sql = "SELECT * FROM chat_frames WHERE price = 0 ORDER BY id ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $frames[] = $row;
            }
            $stmt->close();
        }
    }
}

// Lấy khung chat hiện tại của user
$currentFrameId = null;
if ($chatFrameIdExists) {
    $stmt = $conn->prepare("SELECT chat_frame_id FROM users WHERE Iduser = ?");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $currentFrameId = $row['chat_frame_id'];
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <meta charset="UTF-8">
    <title>Chọn khung chat</title>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            font-family: 'Segoe UI', sans-serif;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 20px;
            min-height: 100vh;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select, .frame-option {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        form {
            background: rgba(255, 255, 255, 0.98);
            border-radius: var(--border-radius-lg);
            padding: 40px;
            max-width: 800px;
            width: 100%;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
        }

        h2 {
            margin-top: 0;
            font-size: 32px;
            font-weight: 700;
            text-align: center;
            color: var(--primary-color);
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
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

        .frame-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .frame-option {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            text-align: center;
            padding: 20px;
            border: 3px solid transparent;
            border-radius: var(--border-radius);
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .frame-option::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(52, 152, 219, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .frame-option:hover::before {
            width: 300px;
            height: 300px;
        }

        .frame-option:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border-color: var(--secondary-color);
            background: rgba(255, 255, 255, 1);
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .frame-option.selected {
            border-color: var(--secondary-color);
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.1) 0%, rgba(52, 152, 219, 0.2) 100%);
            box-shadow: 0 0 20px rgba(52, 152, 219, 0.4);
            transform: scale(1.05);
            animation: selectedPulse 2s ease infinite;
        }
        
        @keyframes selectedPulse {
            0%, 100% {
                box-shadow: 0 0 20px rgba(52, 152, 219, 0.4);
            }
            50% {
                box-shadow: 0 0 30px rgba(52, 152, 219, 0.6);
            }
        }

        .frame-option img {
            width: 120px;
            height: 120px;
            object-fit: contain;
            border-radius: var(--border-radius);
            margin-bottom: 12px;
            transition: transform 0.3s ease;
            border: 2px solid rgba(0, 0, 0, 0.1);
        }
        
        .frame-option:hover img {
            transform: scale(1.1) rotate(5deg);
        }
        
        .frame-option.selected img {
            border-color: var(--secondary-color);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        button {
            margin-top: 30px;
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 18px;
            font-weight: 600;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
            position: relative;
            overflow: hidden;
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
            width: 400px;
            height: 400px;
        }

        button:hover {
            background: linear-gradient(135deg, var(--secondary-dark) 0%, var(--secondary-color) 100%);
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 6px 25px rgba(52, 152, 219, 0.6);
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        button:active {
            transform: translateY(-1px) scale(1);
        }
        
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed !important;
        }

        a {
            display: inline-block;
            margin: 15px 10px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            text-decoration: none;
            font-weight: 600;
            border-radius: var(--border-radius);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            position: relative;
            overflow: hidden;
        }
        
        a::before {
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
        
        a:hover::before {
            width: 300px;
            height: 300px;
        }

        a:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 6px 25px rgba(52, 152, 219, 0.6);
            text-decoration: none;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .frame-name {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 16px;
            margin-top: 8px;
        }
        
        .frame-option.selected .frame-name {
            color: var(--secondary-color);
        }
        
        .nav-links {
            text-align: center;
            margin-top: 20px;
        }
            /* Three.js canvas background */\n        #threejs-background {\n            position: fixed;\n            top: 0;\n            left: 0;\n            width: 100%;\n            height: 100%;\n            z-index: -1;\n            pointer-events: none;\n        }\n
    </style>
</head>
<body>
    <canvas id="threejs-background"></canvas>

    <form method="post" id="frameForm">
        <h2>🎨 Chọn khung chat của bạn</h2>
        <input type="hidden" name="chat_frame_id" id="chat_frame_id">

        <div class="frame-options">
            <?php if (empty($frames)): ?>
                <p style="text-align: center; color: #666; padding: 20px;">
                    Chưa có khung chat nào. <a href="shop.php">Mua khung chat tại đây</a>
                </p>
            <?php else: ?>
                <?php foreach ($frames as $frame): ?>
                    <div class="frame-option <?= $frame['id'] == $currentFrameId ? 'selected' : '' ?>" 
                         data-id="<?= $frame['id'] ?>">
                        <?php if (!empty($frame['ImageURL'])): ?>
                            <img src="<?= htmlspecialchars($frame['ImageURL']) ?>" alt="<?= htmlspecialchars($frame['frame_name']) ?>"
                                 onerror="this.src='images.ico'">
                        <?php else: ?>
                            <div style="width: 120px; height: 120px; background: #f0f0f0; border-radius: var(--border-radius); display: flex; align-items: center; justify-content: center; font-size: 48px; margin: 0 auto 12px;">🖼️</div>
                        <?php endif; ?>
                        <div class="frame-name"><?= htmlspecialchars($frame['frame_name']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <button type="submit">💾 Lưu khung chat</button>
    </form>

    <?php if ($message): ?>
        <div style="background: rgba(40, 167, 69, 0.1); border: 2px solid #28a745; border-radius: var(--border-radius); padding: 15px; margin: 20px auto; max-width: 800px; text-align: center; color: #28a745; font-weight: 600;">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="nav-links">
        <a href="chat.php">⬅ Quay lại chat</a>
        <a href="shop.php">🛒 Mua thêm khung</a>
        <?php 
        // Kiểm tra quyền admin (ID = 1)
        if ($userId == 1): ?>
            <a href="admin_manage_frames.php">⚙️ Quản lý khung (Admin)</a>
        <?php endif; ?>
    </div>

    <script>
        // Đảm bảo cursor luôn hoạt động
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
            
            // Set cursor cho tất cả buttons và links
            const interactiveElements = document.querySelectorAll('button, a, .frame-option');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                // Đảm bảo cursor không bị mất khi hover
                el.addEventListener('mouseenter', function() {
                    this.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                });
                el.addEventListener('mouseleave', function() {
                    this.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                });
            });
        });
        
        const options = document.querySelectorAll('.frame-option');
        const hiddenInput = document.getElementById('chat_frame_id');
        const form = document.getElementById('frameForm');
        const submitButton = form.querySelector('button[type="submit"]');

        options.forEach(option => {
            option.addEventListener('click', () => {
                options.forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');
                hiddenInput.value = option.dataset.id;
            });

            // Gán giá trị ban đầu
            if (option.classList.contains('selected')) {
                hiddenInput.value = option.dataset.id;
            }
        });
        
        // Xử lý form submit
        if (form) {
            form.addEventListener('submit', function(e) {
                if (!hiddenInput.value) {
                    e.preventDefault();
                    alert('Vui lòng chọn một khung chat!');
                    return false;
                }
                
                // Disable button khi đang submit
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Đang lưu...';
                }
            });
        }
    </script>

<script>
    // Initialize Three.js Background\n    (function() {\n        // Pass theme config từ PHP sang JavaScript\n        window.themeConfig = {\n            particleCount: <?= $particleCount ?? 800 ?>,\n            particleSize: <?= $particleSize ?? 0.05 ?>,\n            particleColor: '<?= $particleColor ?? "#ffffff" ?>',\n            particleOpacity: <?= $particleOpacity ?? 0.6 ?>,\n            shapeCount: <?= $shapeCount ?? 10 ?>,\n            shapeColors: <?= json_encode($shapeColors ?? ["#667eea", "#764ba2", "#4facfe", "#00f2fe"]) ?>,\n            shapeOpacity: <?= $shapeOpacity ?? 0.3 ?>,\n            bgGradient: <?= json_encode($bgGradient ?? ["#667eea", "#764ba2", "#4facfe"]) ?>\n        };\n        \n        // Load Three.js background script với đường dẫn chính xác\n        const isInGames = window.location.pathname.includes('/games/');\n        const script = document.createElement('script');\n        script.src = isInGames ? '../threejs-background.js' : 'threejs-background.js';\n        script.onload = function() {\n            console.log('Three.js background loaded');\n        };\n        document.head.appendChild(script);\n    })();
</script>
</body>
</html>
