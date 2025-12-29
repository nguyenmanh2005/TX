<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

// Load theme
require_once 'load_theme.php';

// Load admin helper
require_once 'admin_helper.php';

$userId = $_SESSION['Iduser'];

// Kiểm tra quyền admin (Role = 1)
if (!isAdmin($conn, $userId)) {
    die("Bạn không có quyền truy cập trang này! Chỉ admin (Role = 1) mới có thể truy cập.");
}

$frameType = $_GET['type'] ?? 'chat'; // 'chat' or 'avatar'
$message = '';
$messageType = '';

// Xử lý thêm khung
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $frame_name = trim($_POST['frame_name']);
    $description = trim($_POST['description']);
    $rarity = $_POST['rarity'];
    $price = (float)$_POST['price'];
    
    // Xử lý upload ảnh
    $targetFile = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $targetDir = "uploads/";
        $fileName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFile = $targetDir . $fileName;
        
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
            // Upload thành công
        } else {
            $message = "❌ Lỗi khi upload ảnh.";
            $messageType = 'error';
        }
    }
    
    if (empty($message) && !empty($frame_name)) {
        if ($frameType === 'chat') {
            $insertSql = "INSERT INTO chat_frames (frame_name, ImageURL, description, rarity, price) VALUES (?, ?, ?, ?, ?)";
        } else {
            $insertSql = "INSERT INTO avatar_frames (frame_name, ImageURL, description, rarity, price) VALUES (?, ?, ?, ?, ?)";
        }
        
        $insertStmt = $conn->prepare($insertSql);
        if ($insertStmt) {
            $insertStmt->bind_param("ssssd", $frame_name, $targetFile, $description, $rarity, $price);
            if ($insertStmt->execute()) {
                $message = "✅ Thêm khung " . ($frameType === 'chat' ? 'chat' : 'avatar') . " thành công!";
                $messageType = 'success';
                // Reset form
                $_POST = [];
            } else {
                $message = "❌ Lỗi khi thêm khung: " . $insertStmt->error;
                $messageType = 'error';
            }
            $insertStmt->close();
        }
    } else {
        if (empty($message)) {
            $message = "❌ Vui lòng điền đầy đủ thông tin!";
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Thêm Khung <?= $frameType === 'chat' ? 'Chat' : 'Avatar' ?></title>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        input[type="text"], input[type="number"], textarea {
            cursor: text !important;
        }
        
        input[type="file"] {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .admin-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header-admin {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: var(--border-radius-lg);
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            text-align: center;
        }
        
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
            color: var(--primary-color);
            font-size: 16px;
        }
        
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 18px;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 16px;
            background: rgba(255, 255, 255, 0.95);
            color: var(--text-dark);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            cursor: text !important;
            box-sizing: border-box;
        }
        
        .form-group input[type="text"]:focus,
        .form-group input[type="number"]:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.15);
            background: rgba(255, 255, 255, 1);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-group input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px dashed var(--border-color);
            border-radius: var(--border-radius);
            background: rgba(255, 255, 255, 0.9);
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: border-color 0.3s ease, background 0.3s ease;
        }
        
        .form-group input[type="file"]:hover {
            border-color: var(--secondary-color);
            background: rgba(232, 244, 248, 0.5);
        }
        
        .submit-button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--success-color) 0%, #27ae60 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 18px;
            font-weight: 600;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.4);
            position: relative;
            overflow: hidden;
        }
        
        .submit-button::before {
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
        
        .submit-button:hover::before {
            width: 400px;
            height: 400px;
        }
        
        .submit-button:hover:not(:disabled) {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 6px 25px rgba(46, 204, 113, 0.6);
        }
        
        .submit-button:disabled {
            opacity: 0.6;
            cursor: not-allowed !important;
        }
        
        .message {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-weight: 600;
            animation: slideIn 0.3s ease;
        }
        
        .message.success {
            background: rgba(40, 167, 69, 0.2);
            border: 2px solid #28a745;
            color: #28a745;
        }
        
        .message.error {
            background: rgba(220, 53, 69, 0.2);
            border: 2px solid #dc3545;
            color: #dc3545;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(-100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
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
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .back-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }
        
        .help-text {
            font-size: 14px;
            color: var(--text-dark);
            opacity: 0.7;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="header-admin">
            <h1>➕ Admin - Thêm Khung <?= $frameType === 'chat' ? 'Chat' : 'Avatar' ?></h1>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="frame_name">Tên khung *</label>
                    <input type="text" id="frame_name" name="frame_name" required 
                           value="<?= htmlspecialchars($_POST['frame_name'] ?? '') ?>"
                           placeholder="Ví dụ: Khung vàng huyền thoại">
                </div>
                
                <div class="form-group">
                    <label for="description">Mô tả</label>
                    <textarea id="description" name="description" 
                              placeholder="Mô tả về khung này..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="rarity">Độ hiếm *</label>
                    <select id="rarity" name="rarity" required>
                        <option value="common" <?= (($_POST['rarity'] ?? '') === 'common') ? 'selected' : '' ?>>🟢 Thường</option>
                        <option value="rare" <?= (($_POST['rarity'] ?? '') === 'rare') ? 'selected' : '' ?>>🔵 Hiếm</option>
                        <option value="epic" <?= (($_POST['rarity'] ?? '') === 'epic') ? 'selected' : '' ?>>🟣 Cực hiếm</option>
                        <option value="legendary" <?= (($_POST['rarity'] ?? '') === 'legendary') ? 'selected' : '' ?>>🟡 Huyền thoại</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="price">Giá (VNĐ) *</label>
                    <input type="number" id="price" name="price" min="0" step="1000" 
                           value="<?= htmlspecialchars($_POST['price'] ?? '0') ?>" required>
                    <div class="help-text">Nhập 0 nếu miễn phí</div>
                </div>
                
                <div class="form-group">
                    <label for="image">Hình ảnh khung *</label>
                    <input type="file" id="image" name="image" accept="image/*" required>
                    <div class="help-text">Chọn file ảnh cho khung</div>
                </div>
                
                <button type="submit" class="submit-button">📤 Thêm Khung</button>
            </form>
        </div>
        
        <a href="admin_manage_frames.php" class="back-link">⬅ Quay lại quản lý khung</a>
        <a href="index.php" class="back-link" style="margin-left: 10px;">🏠 Về Trang Chủ</a>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
            
            const interactiveElements = document.querySelectorAll('button, a, input, label, select');
            interactiveElements.forEach(el => {
                if (el.type !== 'text' && el.type !== 'number' && el.tagName !== 'TEXTAREA') {
                    el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                }
            });
        });
    </script>
</body>
</html>

