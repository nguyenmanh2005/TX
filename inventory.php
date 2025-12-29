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

// Lấy thông tin người dùng
// Kiểm tra các cột tồn tại trước
$columnsToSelect = "Iduser, Name, Money";
$checkColumns = ['current_theme_id', 'current_cursor_id', 'chat_frame_id', 'avatar_frame_id'];
foreach ($checkColumns as $col) {
    $checkColSql = "SHOW COLUMNS FROM users LIKE '$col'";
    $checkResult = $conn->query($checkColSql);
    if ($checkResult && $checkResult->num_rows > 0) {
        $columnsToSelect .= ", $col";
    }
}

$sql = "SELECT $columnsToSelect FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Đảm bảo các cột có giá trị mặc định nếu không tồn tại
    if (!isset($user['current_theme_id'])) $user['current_theme_id'] = null;
    if (!isset($user['current_cursor_id'])) $user['current_cursor_id'] = null;
    if (!isset($user['chat_frame_id'])) $user['chat_frame_id'] = null;
    if (!isset($user['avatar_frame_id'])) $user['avatar_frame_id'] = null;
} else {
    die("Lỗi: Không thể lấy thông tin người dùng. " . $conn->error);
}

$message = '';
$messageType = '';

// Xử lý kích hoạt theme
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['activate_theme'])) {
    // Kiểm tra bảng user_themes có tồn tại không
    $checkTable = $conn->query("SHOW TABLES LIKE 'user_themes'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $themeId = (int)$_POST['theme_id'];
        
        // Kiểm tra user có sở hữu theme không
        $checkSql = "SELECT * FROM user_themes WHERE user_id = ? AND theme_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        if ($checkStmt) {
            $checkStmt->bind_param("ii", $userId, $themeId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                // Tắt tất cả theme khác
                $updateAllSql = "UPDATE user_themes SET is_active = 0 WHERE user_id = ?";
                $updateAllStmt = $conn->prepare($updateAllSql);
                if ($updateAllStmt) {
                    $updateAllStmt->bind_param("i", $userId);
                    $updateAllStmt->execute();
                    $updateAllStmt->close();
                }
                
                // Kích hoạt theme được chọn
                $updateSql = "UPDATE user_themes SET is_active = 1 WHERE user_id = ? AND theme_id = ?";
                $updateStmt = $conn->prepare($updateSql);
                if ($updateStmt) {
                    $updateStmt->bind_param("ii", $userId, $themeId);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
                
                // Cập nhật current_theme_id (nếu cột tồn tại)
                $checkColSql = "SHOW COLUMNS FROM users LIKE 'current_theme_id'";
                $checkColResult = $conn->query($checkColSql);
                if ($checkColResult && $checkColResult->num_rows > 0) {
                    $updateUserSql = "UPDATE users SET current_theme_id = ? WHERE Iduser = ?";
                    $updateUserStmt = $conn->prepare($updateUserSql);
                    if ($updateUserStmt) {
                        $updateUserStmt->bind_param("ii", $themeId, $userId);
                        $updateUserStmt->execute();
                        $updateUserStmt->close();
                    }
                }
                
                $message = '✅ Kích hoạt theme thành công!';
                $messageType = 'success';
                $user['current_theme_id'] = $themeId;
            } else {
                $message = '❌ Bạn chưa sở hữu theme này!';
                $messageType = 'error';
            }
            $checkStmt->close();
        }
    } else {
        $message = '❌ Hệ thống themes chưa được kích hoạt!';
        $messageType = 'error';
    }
}

// Xử lý kích hoạt cursor
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['activate_cursor'])) {
    // Kiểm tra bảng user_cursors có tồn tại không
    $checkTable = $conn->query("SHOW TABLES LIKE 'user_cursors'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $cursorId = (int)$_POST['cursor_id'];
        
        $checkSql = "SELECT * FROM user_cursors WHERE user_id = ? AND cursor_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        if ($checkStmt) {
            $checkStmt->bind_param("ii", $userId, $cursorId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $updateAllSql = "UPDATE user_cursors SET is_active = 0 WHERE user_id = ?";
                $updateAllStmt = $conn->prepare($updateAllSql);
                if ($updateAllStmt) {
                    $updateAllStmt->bind_param("i", $userId);
                    $updateAllStmt->execute();
                    $updateAllStmt->close();
                }
                
                $updateSql = "UPDATE user_cursors SET is_active = 1 WHERE user_id = ? AND cursor_id = ?";
                $updateStmt = $conn->prepare($updateSql);
                if ($updateStmt) {
                    $updateStmt->bind_param("ii", $userId, $cursorId);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
                
                // Cập nhật current_cursor_id (nếu cột tồn tại)
                $checkColSql = "SHOW COLUMNS FROM users LIKE 'current_cursor_id'";
                $checkColResult = $conn->query($checkColSql);
                if ($checkColResult && $checkColResult->num_rows > 0) {
                    $updateUserSql = "UPDATE users SET current_cursor_id = ? WHERE Iduser = ?";
                    $updateUserStmt = $conn->prepare($updateUserSql);
                    if ($updateUserStmt) {
                        $updateUserStmt->bind_param("ii", $cursorId, $userId);
                        $updateUserStmt->execute();
                        $updateUserStmt->close();
                    }
                }
                
                $message = '✅ Kích hoạt cursor thành công!';
                $messageType = 'success';
                $user['current_cursor_id'] = $cursorId;
            } else {
                $message = '❌ Bạn chưa sở hữu cursor này!';
                $messageType = 'error';
            }
            $checkStmt->close();
        }
    } else {
        $message = '❌ Hệ thống cursors chưa được kích hoạt!';
        $messageType = 'error';
    }
}

// Xử lý kích hoạt chat frame
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['activate_chat_frame'])) {
    // Kiểm tra cột chat_frame_id có tồn tại không
    $checkColSql = "SHOW COLUMNS FROM users LIKE 'chat_frame_id'";
    $checkColResult = $conn->query($checkColSql);
    
    if ($checkColResult && $checkColResult->num_rows > 0) {
        $frameId = (int)$_POST['chat_frame_id'];
        
        // Kiểm tra bảng user_chat_frames có tồn tại không
        $checkTable = $conn->query("SHOW TABLES LIKE 'user_chat_frames'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $checkSql = "SELECT * FROM user_chat_frames WHERE user_id = ? AND chat_frame_id = ?";
            $checkStmt = $conn->prepare($checkSql);
            if ($checkStmt) {
                $checkStmt->bind_param("ii", $userId, $frameId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    $updateUserSql = "UPDATE users SET chat_frame_id = ? WHERE Iduser = ?";
                    $updateUserStmt = $conn->prepare($updateUserSql);
                    if ($updateUserStmt) {
                        $updateUserStmt->bind_param("ii", $frameId, $userId);
                        $updateUserStmt->execute();
                        $updateUserStmt->close();
                        
                        $message = '✅ Kích hoạt khung chat thành công!';
                        $messageType = 'success';
                        $user['chat_frame_id'] = $frameId;
                    }
                } else {
                    $message = '❌ Bạn chưa sở hữu khung chat này!';
                    $messageType = 'error';
                }
                $checkStmt->close();
            }
        } else {
            $message = '❌ Hệ thống khung chat chưa được kích hoạt!';
            $messageType = 'error';
        }
    } else {
        $message = '❌ Hệ thống khung chat chưa được kích hoạt!';
        $messageType = 'error';
    }
}

// Xử lý kích hoạt avatar frame
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['activate_avatar_frame'])) {
    // Kiểm tra cột avatar_frame_id có tồn tại không
    $checkColSql = "SHOW COLUMNS FROM users LIKE 'avatar_frame_id'";
    $checkColResult = $conn->query($checkColSql);
    
    if ($checkColResult && $checkColResult->num_rows > 0) {
        $frameId = (int)$_POST['avatar_frame_id'];
        
        // Kiểm tra bảng user_avatar_frames có tồn tại không
        $checkTable = $conn->query("SHOW TABLES LIKE 'user_avatar_frames'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $checkSql = "SELECT * FROM user_avatar_frames WHERE user_id = ? AND avatar_frame_id = ?";
            $checkStmt = $conn->prepare($checkSql);
            if ($checkStmt) {
                $checkStmt->bind_param("ii", $userId, $frameId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    $updateUserSql = "UPDATE users SET avatar_frame_id = ? WHERE Iduser = ?";
                    $updateUserStmt = $conn->prepare($updateUserSql);
                    if ($updateUserStmt) {
                        $updateUserStmt->bind_param("ii", $frameId, $userId);
                        $updateUserStmt->execute();
                        $updateUserStmt->close();
                        
                        $message = '✅ Kích hoạt khung avatar thành công!';
                        $messageType = 'success';
                        $user['avatar_frame_id'] = $frameId;
                    }
                } else {
                    $message = '❌ Bạn chưa sở hữu khung avatar này!';
                    $messageType = 'error';
                }
                $checkStmt->close();
            }
        } else {
            $message = '❌ Hệ thống khung avatar chưa được kích hoạt!';
            $messageType = 'error';
        }
    } else {
        $message = '❌ Hệ thống khung avatar chưa được kích hoạt!';
        $messageType = 'error';
    }
}

// Lấy themes đã sở hữu
$themes = [];
$checkThemesTable = $conn->query("SHOW TABLES LIKE 'themes'");
$checkUserThemesTable = $conn->query("SHOW TABLES LIKE 'user_themes'");
if ($checkThemesTable && $checkThemesTable->num_rows > 0 && 
    $checkUserThemesTable && $checkUserThemesTable->num_rows > 0) {
    $sql = "SELECT t.*, ut.is_active, ut.purchased_at 
            FROM themes t
            INNER JOIN user_themes ut ON t.id = ut.theme_id
            WHERE ut.user_id = ?
            ORDER BY ut.purchased_at DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $themes[] = $row;
        }
        $stmt->close();
    }
}

// Lấy cursors đã sở hữu
$cursors = [];
$checkCursorsTable = $conn->query("SHOW TABLES LIKE 'cursors'");
$checkUserCursorsTable = $conn->query("SHOW TABLES LIKE 'user_cursors'");
if ($checkCursorsTable && $checkCursorsTable->num_rows > 0 &&
    $checkUserCursorsTable && $checkUserCursorsTable->num_rows > 0) {
    $sql = "SELECT c.*, uc.is_active, uc.purchased_at 
            FROM cursors c
            INNER JOIN user_cursors uc ON c.id = uc.cursor_id
            WHERE uc.user_id = ?
            ORDER BY uc.purchased_at DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $cursors[] = $row;
        }
        $stmt->close();
    }
}

// Lấy chat frames đã sở hữu
$chatFrames = [];
$checkChatFramesTable = $conn->query("SHOW TABLES LIKE 'chat_frames'");
$checkUserChatFramesTable = $conn->query("SHOW TABLES LIKE 'user_chat_frames'");
if ($checkChatFramesTable && $checkChatFramesTable->num_rows > 0 &&
    $checkUserChatFramesTable && $checkUserChatFramesTable->num_rows > 0) {
    $sql = "SELECT cf.*, ucf.purchased_at 
            FROM chat_frames cf
            INNER JOIN user_chat_frames ucf ON cf.id = ucf.chat_frame_id
            WHERE ucf.user_id = ?
            ORDER BY ucf.purchased_at DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $chatFrames[] = $row;
        }
        $stmt->close();
    }
}

// Lấy avatar frames đã sở hữu
$avatarFrames = [];
$checkAvatarFramesTable = $conn->query("SHOW TABLES LIKE 'avatar_frames'");
$checkUserAvatarFramesTable = $conn->query("SHOW TABLES LIKE 'user_avatar_frames'");
if ($checkAvatarFramesTable && $checkAvatarFramesTable->num_rows > 0 &&
    $checkUserAvatarFramesTable && $checkUserAvatarFramesTable->num_rows > 0) {
    $sql = "SELECT af.*, uaf.purchased_at 
            FROM avatar_frames af
            INNER JOIN user_avatar_frames uaf ON af.id = uaf.avatar_frame_id
            WHERE uaf.user_id = ?
            ORDER BY uaf.purchased_at DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $avatarFrames[] = $row;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kho Đồ - Inventory</title>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        .inventory-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header-inventory {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: var(--border-radius-lg);
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            text-align: center;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            font-weight: 600;
        }
        
        .message.success {
            background: rgba(40, 167, 69, 0.2);
            color: var(--success-color);
            border: 2px solid var(--success-color);
        }
        
        .message.error {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            border: 2px solid #dc3545;
        }
        
        .inventory-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.95);
            padding: 10px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            flex-wrap: wrap;
        }
        
        .tab-button {
            flex: 1;
            min-width: 150px;
            padding: 15px 20px;
            background: var(--secondary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: all 0.3s ease;
        }
        
        .tab-button.active {
            background: var(--primary-color);
            transform: scale(1.05);
        }
        
        .tab-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .item-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius-lg);
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 3px solid transparent;
            position: relative;
        }
        
        .item-card.active {
            border-color: var(--success-color);
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(255, 255, 255, 0.95) 100%);
        }
        
        .item-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }
        
        .item-image {
            width: 100%;
            height: 150px;
            object-fit: contain;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
            background: rgba(0, 0, 0, 0.05);
        }
        
        .item-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
            text-align: center;
        }
        
        .item-description {
            color: var(--text-dark);
            margin-bottom: 15px;
            text-align: center;
            font-size: 14px;
            min-height: 40px;
        }
        
        .item-meta {
            font-size: 12px;
            color: var(--text-light);
            text-align: center;
            margin-bottom: 15px;
        }
        
        .activate-button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--success-color) 0%, #27ae60 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: all 0.3s ease;
        }
        
        .activate-button:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
        }
        
        .activate-button:disabled {
            opacity: 0.5;
            cursor: not-allowed !important;
        }
        
        .active-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--success-color);
            color: white;
            padding: 5px 10px;
            border-radius: var(--border-radius);
            font-size: 12px;
            font-weight: 600;
        }
        
        .no-items {
            text-align: center;
            padding: 40px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius-lg);
            color: var(--text-dark);
            font-size: 18px;
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
    </style>
</head>
<body>
    <div class="inventory-container">
        <div class="header-inventory">
            <h1>📦 Kho Đồ - Inventory</h1>
            <div style="margin-top: 15px; font-size: 18px; color: var(--success-color); font-weight: 600;">
                👤 <?= htmlspecialchars($user['Name']) ?> | 💰 <?= number_format($user['Money'], 0, ',', '.') ?> VNĐ
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <div class="inventory-tabs">
            <button class="tab-button active" onclick="switchTab('themes')">🎨 Themes (<?= count($themes) ?>)</button>
            <button class="tab-button" onclick="switchTab('cursors')">🖱️ Cursors (<?= count($cursors) ?>)</button>
            <button class="tab-button" onclick="switchTab('chat_frames')">💬 Khung Chat (<?= count($chatFrames) ?>)</button>
            <button class="tab-button" onclick="switchTab('avatar_frames')">🖼️ Khung Avatar (<?= count($avatarFrames) ?>)</button>
        </div>
        
        <!-- Themes Tab -->
        <div id="themes-tab" class="tab-content active">
            <?php if (count($themes) > 0): ?>
                <div class="items-grid">
                    <?php foreach ($themes as $theme): ?>
                        <div class="item-card <?= ($user['current_theme_id'] == $theme['id']) ? 'active' : '' ?>">
                            <?php if ($user['current_theme_id'] == $theme['id']): ?>
                                <div class="active-badge">✓ Đang dùng</div>
                            <?php endif; ?>
                            <div class="item-name"><?= htmlspecialchars($theme['name']) ?></div>
                            <div class="item-description"><?= htmlspecialchars($theme['description'] ?? '') ?></div>
                            <div class="item-meta">
                                Mua ngày: <?= date('d/m/Y', strtotime($theme['purchased_at'])) ?>
                            </div>
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="theme_id" value="<?= $theme['id'] ?>">
                                <button type="submit" name="activate_theme" class="activate-button" <?= ($user['current_theme_id'] == $theme['id']) ? 'disabled' : '' ?>>
                                    <?= ($user['current_theme_id'] == $theme['id']) ? '✓ Đang sử dụng' : 'Kích hoạt' ?>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-items">
                    <h2>📦 Chưa có theme nào</h2>
                    <p>Hãy vào <a href="shop.php">🛒 Cửa Hàng</a> để mua theme đẹp nhé!</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Cursors Tab -->
        <div id="cursors-tab" class="tab-content">
            <?php if (count($cursors) > 0): ?>
                <div class="items-grid">
                    <?php foreach ($cursors as $cursor): ?>
                        <div class="item-card <?= ($user['current_cursor_id'] == $cursor['id']) ? 'active' : '' ?>">
                            <?php if ($user['current_cursor_id'] == $cursor['id']): ?>
                                <div class="active-badge">✓ Đang dùng</div>
                            <?php endif; ?>
                            <img src="<?= htmlspecialchars($cursor['cursor_image']) ?>" alt="<?= htmlspecialchars($cursor['name']) ?>" class="item-image">
                            <div class="item-name"><?= htmlspecialchars($cursor['name']) ?></div>
                            <div class="item-description"><?= htmlspecialchars($cursor['description'] ?? '') ?></div>
                            <div class="item-meta">
                                Mua ngày: <?= date('d/m/Y', strtotime($cursor['purchased_at'])) ?>
                            </div>
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="cursor_id" value="<?= $cursor['id'] ?>">
                                <button type="submit" name="activate_cursor" class="activate-button" <?= ($user['current_cursor_id'] == $cursor['id']) ? 'disabled' : '' ?>>
                                    <?= ($user['current_cursor_id'] == $cursor['id']) ? '✓ Đang sử dụng' : 'Kích hoạt' ?>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-items">
                    <h2>📦 Chưa có cursor nào</h2>
                    <p>Hãy vào <a href="shop.php">🛒 Cửa Hàng</a> để mua cursor đẹp nhé!</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Chat Frames Tab -->
        <div id="chat_frames-tab" class="tab-content">
            <?php if (count($chatFrames) > 0): ?>
                <div class="items-grid">
                    <?php foreach ($chatFrames as $frame): ?>
                        <div class="item-card <?= ($user['chat_frame_id'] == $frame['id']) ? 'active' : '' ?>">
                            <?php if ($user['chat_frame_id'] == $frame['id']): ?>
                                <div class="active-badge">✓ Đang dùng</div>
                            <?php endif; ?>
                            <?php if (!empty($frame['ImageURL'])): ?>
                                <img src="<?= htmlspecialchars($frame['ImageURL']) ?>" alt="<?= htmlspecialchars($frame['frame_name']) ?>" class="item-image">
                            <?php else: ?>
                                <div class="item-image" style="display: flex; align-items: center; justify-content: center; font-size: 48px;">
                                    💬
                                </div>
                            <?php endif; ?>
                            <div class="item-name"><?= htmlspecialchars($frame['frame_name']) ?></div>
                            <div class="item-description"><?= htmlspecialchars($frame['description'] ?? '') ?></div>
                            <div class="item-meta">
                                Mua ngày: <?= date('d/m/Y', strtotime($frame['purchased_at'])) ?>
                            </div>
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="chat_frame_id" value="<?= $frame['id'] ?>">
                                <button type="submit" name="activate_chat_frame" class="activate-button" <?= ($user['chat_frame_id'] == $frame['id']) ? 'disabled' : '' ?>>
                                    <?= ($user['chat_frame_id'] == $frame['id']) ? '✓ Đang sử dụng' : 'Kích hoạt' ?>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-items">
                    <h2>📦 Chưa có khung chat nào</h2>
                    <p>Hãy vào <a href="khungchat.php">🎨 Chọn Khung Chat</a> để mua khung chat đẹp nhé!</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Avatar Frames Tab -->
        <div id="avatar_frames-tab" class="tab-content">
            <?php if (count($avatarFrames) > 0): ?>
                <div class="items-grid">
                    <?php foreach ($avatarFrames as $frame): ?>
                        <div class="item-card <?= ($user['avatar_frame_id'] == $frame['id']) ? 'active' : '' ?>">
                            <?php if ($user['avatar_frame_id'] == $frame['id']): ?>
                                <div class="active-badge">✓ Đang dùng</div>
                            <?php endif; ?>
                            <?php if (!empty($frame['ImageURL'])): ?>
                                <img src="<?= htmlspecialchars($frame['ImageURL']) ?>" alt="<?= htmlspecialchars($frame['frame_name']) ?>" class="item-image">
                            <?php else: ?>
                                <div class="item-image" style="display: flex; align-items: center; justify-content: center; font-size: 48px;">
                                    🖼️
                                </div>
                            <?php endif; ?>
                            <div class="item-name"><?= htmlspecialchars($frame['frame_name']) ?></div>
                            <div class="item-description"><?= htmlspecialchars($frame['description'] ?? '') ?></div>
                            <div class="item-meta">
                                Mua ngày: <?= date('d/m/Y', strtotime($frame['purchased_at'])) ?>
                            </div>
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="avatar_frame_id" value="<?= $frame['id'] ?>">
                                <button type="submit" name="activate_avatar_frame" class="activate-button" <?= ($user['avatar_frame_id'] == $frame['id']) ? 'disabled' : '' ?>>
                                    <?= ($user['avatar_frame_id'] == $frame['id']) ? '✓ Đang sử dụng' : 'Kích hoạt' ?>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-items">
                    <h2>📦 Chưa có khung avatar nào</h2>
                    <p>Hãy vào <a href="khungavatar.php">🖼️ Chọn Khung Avatar</a> để mua khung avatar đẹp nhé!</p>
                </div>
            <?php endif; ?>
        </div>
        
        <a href="index.php" class="back-link">🏠 Về Trang Chủ</a>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
            
            const interactiveElements = document.querySelectorAll('button, a, input, label, select');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            });
        });
        
        function switchTab(tabType) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            const targetTab = document.getElementById(tabType + '-tab');
            if (targetTab) {
                targetTab.classList.add('active');
            }
            
            // Add active class to clicked button
            if (event && event.target) {
                event.target.classList.add('active');
            }
        }
    </script>
</body>
</html>

