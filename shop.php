<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

// Load theme
require_once 'load_theme.php';
// Đảm bảo $bgGradientCSS có giá trị
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

$userId = $_SESSION['Iduser'];

// Lấy thông tin người dùng
$sql = "SELECT Iduser, Name, Money FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$message = '';
$messageType = '';

// Xử lý mua theme
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['buy_theme'])) {
    $themeId = (int) $_POST['theme_id'];

    // Kiểm tra đã sở hữu chưa
    $checkSql = "SELECT * FROM user_themes WHERE user_id = ? AND theme_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    if ($checkStmt) {
        $checkStmt->bind_param("ii", $userId, $themeId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
    } else {
        $checkResult = null;
        $message = '❌ Lỗi kết nối database! Vui lòng chạy file database_updates.sql trước.';
        $messageType = 'error';
    }

    if ($checkResult && $checkResult->num_rows > 0) {
        // Đã sở hữu, chỉ cần kích hoạt
        $updateSql = "UPDATE user_themes SET is_active = 1 WHERE user_id = ? AND theme_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        if ($updateStmt) {
            $updateStmt->bind_param("ii", $userId, $themeId);
            $updateStmt->execute();
            $updateStmt->close();
        }

        // Cập nhật current_theme_id
        $updateUserSql = "UPDATE users SET current_theme_id = ? WHERE Iduser = ?";
        $updateUserStmt = $conn->prepare($updateUserSql);
        if ($updateUserStmt) {
            $updateUserStmt->bind_param("ii", $themeId, $userId);
            $updateUserStmt->execute();
            $updateUserStmt->close();
        }

        $message = '✅ Kích hoạt theme thành công! Đang tải lại trang...';
        $messageType = 'success';
        // Reload sau 1 giây để áp dụng theme mới
        echo '<script>setTimeout(function(){ window.location.href = "index.php"; }, 1000);</script>';
    } else {
        // Lấy thông tin theme
        $theme = null;
        $themeSql = "SELECT * FROM themes WHERE id = ?";
        $themeStmt = $conn->prepare($themeSql);
        if ($themeStmt) {
            $themeStmt->bind_param("i", $themeId);
            $themeStmt->execute();
            $themeResult = $themeStmt->get_result();
            $theme = $themeResult->fetch_assoc();
            $themeStmt->close();
        }

        if ($theme && $user['Money'] >= $theme['price']) {
            // Trừ gtlm
            $updateMoneySql = "UPDATE users SET Money = Money - ? WHERE Iduser = ?";
            $updateMoneyStmt = $conn->prepare($updateMoneySql);
            if ($updateMoneyStmt) {
                $updateMoneyStmt->bind_param("di", $theme['price'], $userId);
                $updateMoneyStmt->execute();
                $updateMoneyStmt->close();
            }

            // Thêm vào user_themes
            $insertSql = "INSERT INTO user_themes (user_id, theme_id, is_active) VALUES (?, ?, 1)";
            $insertStmt = $conn->prepare($insertSql);
            if ($insertStmt) {
                $insertStmt->bind_param("ii", $userId, $themeId);
                $insertStmt->execute();
                $insertStmt->close();
            }

            // Cập nhật current_theme_id
            $updateUserSql = "UPDATE users SET current_theme_id = ? WHERE Iduser = ?";
            $updateUserStmt = $conn->prepare($updateUserSql);
            if ($updateUserStmt) {
                $updateUserStmt->bind_param("ii", $themeId, $userId);
                $updateUserStmt->execute();
                $updateUserStmt->close();
            }

            // Cập nhật lại Số Gtlm
            $user['Money'] -= $theme['price'];

            $message = '🎉 Mua theme thành công! Đang tải lại trang...';
            $messageType = 'success';
            // Reload sau 1 giây để áp dụng theme mới
            echo '<script>setTimeout(function(){ window.location.href = "index.php"; }, 1000);</script>';
        } else {
            $message = '❌ Không đủ gtlm hoặc theme không tồn tại!';
            $messageType = 'error';
        }
    }
}

// Xử lý mua cursor
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['buy_cursor'])) {
    $cursorId = (int) $_POST['cursor_id'];

    // Kiểm tra đã sở hữu chưa
    $checkSql = "SELECT * FROM user_cursors WHERE user_id = ? AND cursor_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    if ($checkStmt) {
        $checkStmt->bind_param("ii", $userId, $cursorId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
    } else {
        $checkResult = null;
        $message = '❌ Lỗi kết nối database! Vui lòng chạy file database_updates.sql trước.';
        $messageType = 'error';
    }

    if ($checkResult && $checkResult->num_rows > 0) {
        // Đã sở hữu, chỉ cần kích hoạt
        $updateSql = "UPDATE user_cursors SET is_active = 1 WHERE user_id = ? AND cursor_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        if ($updateStmt) {
            $updateStmt->bind_param("ii", $userId, $cursorId);
            $updateStmt->execute();
            $updateStmt->close();
        }

        // Cập nhật current_cursor_id
        $updateUserSql = "UPDATE users SET current_cursor_id = ? WHERE Iduser = ?";
        $updateUserStmt = $conn->prepare($updateUserSql);
        if ($updateUserStmt) {
            $updateUserStmt->bind_param("ii", $cursorId, $userId);
            $updateUserStmt->execute();
            $updateUserStmt->close();
        }

        $message = '✅ Kích hoạt cursor thành công!';
        $messageType = 'success';
    } else {
        // Lấy thông tin cursor
        $cursor = null;
        $cursorSql = "SELECT * FROM cursors WHERE id = ?";
        $cursorStmt = $conn->prepare($cursorSql);
        if ($cursorStmt) {
            $cursorStmt->bind_param("i", $cursorId);
            $cursorStmt->execute();
            $cursorResult = $cursorStmt->get_result();
            $cursor = $cursorResult->fetch_assoc();
            $cursorStmt->close();
        }

        if ($cursor && $user['Money'] >= $cursor['price']) {
            // Trừ gtlm
            $updateMoneySql = "UPDATE users SET Money = Money - ? WHERE Iduser = ?";
            $updateMoneyStmt = $conn->prepare($updateMoneySql);
            if ($updateMoneyStmt) {
                $updateMoneyStmt->bind_param("di", $cursor['price'], $userId);
                $updateMoneyStmt->execute();
                $updateMoneyStmt->close();
            }

            // Thêm vào user_cursors
            $insertSql = "INSERT INTO user_cursors (user_id, cursor_id, is_active) VALUES (?, ?, 1)";
            $insertStmt = $conn->prepare($insertSql);
            if ($insertStmt) {
                $insertStmt->bind_param("ii", $userId, $cursorId);
                $insertStmt->execute();
                $insertStmt->close();
            }

            // Cập nhật current_cursor_id
            $updateUserSql = "UPDATE users SET current_cursor_id = ? WHERE Iduser = ?";
            $updateUserStmt = $conn->prepare($updateUserSql);
            if ($updateUserStmt) {
                $updateUserStmt->bind_param("ii", $cursorId, $userId);
                $updateUserStmt->execute();
                $updateUserStmt->close();
            }

            // Cập nhật lại Số Gtlm
            $user['Money'] -= $cursor['price'];

            $message = '🎉 Mua cursor thành công!';
            $messageType = 'success';
        } else {
            $message = '❌ Không đủ gtlm hoặc cursor không tồn tại!';
            $messageType = 'error';
        }
    }
}

// Xử lý mua chat frame
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['buy_chat_frame'])) {
    $frameId = (int) $_POST['chat_frame_id'];

    // Kiểm tra đã sở hữu chưa
    $checkSql = "SELECT * FROM user_chat_frames WHERE user_id = ? AND chat_frame_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    if ($checkStmt) {
        $checkStmt->bind_param("ii", $userId, $frameId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
    } else {
        $checkResult = null;
        $message = '❌ Lỗi kết nối database!';
        $messageType = 'error';
    }

    if ($checkResult && $checkResult->num_rows > 0) {
        // Đã sở hữu, chỉ cần kích hoạt
        $updateUserSql = "UPDATE users SET chat_frame_id = ? WHERE Iduser = ?";
        $updateUserStmt = $conn->prepare($updateUserSql);
        if ($updateUserStmt) {
            $updateUserStmt->bind_param("ii", $frameId, $userId);
            $updateUserStmt->execute();
            $updateUserStmt->close();
        }

        $message = '✅ Kích hoạt khung chat thành công!';
        $messageType = 'success';
    } else {
        // Lấy thông tin frame
        $frame = null;
        $frameSql = "SELECT * FROM chat_frames WHERE id = ?";
        $frameStmt = $conn->prepare($frameSql);
        if ($frameStmt) {
            $frameStmt->bind_param("i", $frameId);
            $frameStmt->execute();
            $frameResult = $frameStmt->get_result();
            $frame = $frameResult->fetch_assoc();
            $frameStmt->close();
        }

        if ($frame && $user['Money'] >= $frame['price']) {
            // Trừ gtlm
            $updateMoneySql = "UPDATE users SET Money = Money - ? WHERE Iduser = ?";
            $updateMoneyStmt = $conn->prepare($updateMoneySql);
            if ($updateMoneyStmt) {
                $updateMoneyStmt->bind_param("di", $frame['price'], $userId);
                $updateMoneyStmt->execute();
                $updateMoneyStmt->close();
            }

            // Thêm vào user_chat_frames
            $insertSql = "INSERT INTO user_chat_frames (user_id, chat_frame_id) VALUES (?, ?)";
            $insertStmt = $conn->prepare($insertSql);
            if ($insertStmt) {
                $insertStmt->bind_param("ii", $userId, $frameId);
                $insertStmt->execute();
                $insertStmt->close();
            }

            // Cập nhật chat_frame_id
            $updateUserSql = "UPDATE users SET chat_frame_id = ? WHERE Iduser = ?";
            $updateUserStmt = $conn->prepare($updateUserSql);
            if ($updateUserStmt) {
                $updateUserStmt->bind_param("ii", $frameId, $userId);
                $updateUserStmt->execute();
                $updateUserStmt->close();
            }

            // Cập nhật lại Số Gtlm
            $user['Money'] -= $frame['price'];

            $message = '🎉 Mua khung chat thành công!';
            $messageType = 'success';
        } else {
            $message = '❌ Không đủ gtlm hoặc khung chat không tồn tại!';
            $messageType = 'error';
        }
    }
}

// Xử lý mua avatar frame
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['buy_avatar_frame'])) {
    $frameId = (int) $_POST['avatar_frame_id'];

    // Kiểm tra đã sở hữu chưa
    $checkSql = "SELECT * FROM user_avatar_frames WHERE user_id = ? AND avatar_frame_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    if ($checkStmt) {
        $checkStmt->bind_param("ii", $userId, $frameId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
    } else {
        $checkResult = null;
        $message = '❌ Lỗi kết nối database!';
        $messageType = 'error';
    }

    if ($checkResult && $checkResult->num_rows > 0) {
        // Đã sở hữu, chỉ cần kích hoạt
        $updateUserSql = "UPDATE users SET avatar_frame_id = ? WHERE Iduser = ?";
        $updateUserStmt = $conn->prepare($updateUserSql);
        if ($updateUserStmt) {
            $updateUserStmt->bind_param("ii", $frameId, $userId);
            $updateUserStmt->execute();
            $updateUserStmt->close();
        }

        $message = '✅ Kích hoạt khung avatar thành công!';
        $messageType = 'success';
    } else {
        // Lấy thông tin frame
        $frame = null;
        $frameSql = "SELECT * FROM avatar_frames WHERE id = ?";
        $frameStmt = $conn->prepare($frameSql);
        if ($frameStmt) {
            $frameStmt->bind_param("i", $frameId);
            $frameStmt->execute();
            $frameResult = $frameStmt->get_result();
            $frame = $frameResult->fetch_assoc();
            $frameStmt->close();
        }

        if ($frame && $user['Money'] >= $frame['price']) {
            // Trừ gtlm
            $updateMoneySql = "UPDATE users SET Money = Money - ? WHERE Iduser = ?";
            $updateMoneyStmt = $conn->prepare($updateMoneySql);
            if ($updateMoneyStmt) {
                $updateMoneyStmt->bind_param("di", $frame['price'], $userId);
                $updateMoneyStmt->execute();
                $updateMoneyStmt->close();
            }

            // Thêm vào user_avatar_frames
            $insertSql = "INSERT INTO user_avatar_frames (user_id, avatar_frame_id) VALUES (?, ?)";
            $insertStmt = $conn->prepare($insertSql);
            if ($insertStmt) {
                $insertStmt->bind_param("ii", $userId, $frameId);
                $insertStmt->execute();
                $insertStmt->close();
            }

            // Cập nhật avatar_frame_id
            $updateUserSql = "UPDATE users SET avatar_frame_id = ? WHERE Iduser = ?";
            $updateUserStmt = $conn->prepare($updateUserSql);
            if ($updateUserStmt) {
                $updateUserStmt->bind_param("ii", $frameId, $userId);
                $updateUserStmt->execute();
                $updateUserStmt->close();
            }

            // Cập nhật lại Số Gtlm
            $user['Money'] -= $frame['price'];

            $message = '🎉 Mua khung avatar thành công!';
            $messageType = 'success';
        } else {
            $message = '❌ Không đủ gtlm hoặc khung avatar không tồn tại!';
            $messageType = 'error';
        }
    }
}

// Lấy danh sách themes
$themes = [];
$themesSql = "SELECT t.*, 
              (SELECT COUNT(*) FROM user_themes ut WHERE ut.user_id = ? AND ut.theme_id = t.id) as owned
              FROM themes t ORDER BY t.price ASC";
$themesStmt = $conn->prepare($themesSql);
if ($themesStmt) {
    $themesStmt->bind_param("i", $userId);
    $themesStmt->execute();
    $themesResult = $themesStmt->get_result();
    while ($row = $themesResult->fetch_assoc()) {
        $themes[] = $row;
    }
    $themesStmt->close();
} else {
    // Nếu bảng chưa tồn tại, tạo danh sách rỗng
    error_log("Error preparing themes query: " . $conn->error);
}

// Lấy danh sách cursors
$cursors = [];
$cursorsSql = "SELECT c.*, 
               (SELECT COUNT(*) FROM user_cursors uc WHERE uc.user_id = ? AND uc.cursor_id = c.id) as owned
               FROM cursors c ORDER BY c.price ASC";
$cursorsStmt = $conn->prepare($cursorsSql);
if ($cursorsStmt) {
    $cursorsStmt->bind_param("i", $userId);
    $cursorsStmt->execute();
    $cursorsResult = $cursorsStmt->get_result();
    while ($row = $cursorsResult->fetch_assoc()) {
        $cursors[] = $row;
    }
    $cursorsStmt->close();
} else {
    // Nếu bảng chưa tồn tại, tạo danh sách rỗng
    error_log("Error preparing cursors query: " . $conn->error);
}

// Lấy danh sách chat frames
$chatFrames = [];
$chatFramesSql = "SELECT cf.*, 
                  (SELECT COUNT(*) FROM user_chat_frames ucf WHERE ucf.user_id = ? AND ucf.chat_frame_id = cf.id) as owned
                  FROM chat_frames cf ORDER BY cf.price ASC";
$chatFramesStmt = $conn->prepare($chatFramesSql);
if ($chatFramesStmt) {
    $chatFramesStmt->bind_param("i", $userId);
    $chatFramesStmt->execute();
    $chatFramesResult = $chatFramesStmt->get_result();
    while ($row = $chatFramesResult->fetch_assoc()) {
        $chatFrames[] = $row;
    }
    $chatFramesStmt->close();
}

// Lấy danh sách avatar frames
$avatarFrames = [];
$avatarFramesSql = "SELECT af.*, 
                    (SELECT COUNT(*) FROM user_avatar_frames uaf WHERE uaf.user_id = ? AND uaf.avatar_frame_id = af.id) as owned
                    FROM avatar_frames af ORDER BY af.price ASC";
$avatarFramesStmt = $conn->prepare($avatarFramesSql);
if ($avatarFramesStmt) {
    $avatarFramesStmt->bind_param("i", $userId);
    $avatarFramesStmt->execute();
    $avatarFramesResult = $avatarFramesStmt->get_result();
    while ($row = $avatarFramesResult->fetch_assoc()) {
        $avatarFrames[] = $row;
    }
    $avatarFramesStmt->close();
}

// Lấy theme, cursor và frames hiện tại
$current = ['current_theme_id' => null, 'current_cursor_id' => null, 'chat_frame_id' => null, 'avatar_frame_id' => null];
$currentSql = "SELECT current_theme_id, current_cursor_id, chat_frame_id, avatar_frame_id FROM users WHERE Iduser = ?";
$currentStmt = $conn->prepare($currentSql);
if ($currentStmt) {
    $currentStmt->bind_param("i", $userId);
    $currentStmt->execute();
    $currentResult = $currentStmt->get_result();
    if ($currentResult) {
        $current = $currentResult->fetch_assoc() ?: $current;
    }
    $currentStmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cửa Hàng - Mua Theme & Cursor</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/shop-enhancements.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
        }

        * {
            cursor: inherit;
        }

        button,
        a,
        input[type="button"],
        input[type="submit"],
        label,
        select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .shop-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header-shop {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: var(--border-radius-lg);
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .balance-display {
            font-size: 24px;
            font-weight: 700;
            color: var(--success-color);
        }

        .shop-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.95);
            padding: 10px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .tab-button {
            flex: 1;
            padding: 15px 20px;
            background: var(--secondary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 18px;
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
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .item-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            border-color: var(--secondary-color);
        }

        .item-card.owned {
            border-color: var(--success-color);
        }

        .item-card.owned::before {
            content: '✓ Đã sở hữu';
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

        .item-card.active {
            border-color: var(--primary-color);
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.5);
        }

        .item-card.active::after {
            content: 'Đang dùng';
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--primary-color);
            color: white;
            padding: 5px 10px;
            border-radius: var(--border-radius);
            font-size: 12px;
            font-weight: 600;
        }

        .item-preview {
            width: 100%;
            height: 150px;
            background: <?= $bgGradientCSS ?>; background-attachment: fixed;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
        }

        .cursor-preview {
            width: 100%;
            height: 150px;
            background: #f0f0f0;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: space-evenly;
            position: relative;
            transition: background 0.3s ease;
        }

        .cursor-preview .preview-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .cursor-preview img {
            max-width: 32px;
            max-height: 32px;
            object-fit: contain;
        }

        .cursor-preview .label {
            font-size: 10px;
            color: #888;
            text-transform: uppercase;
            font-weight: bold;
        }

        

        .item-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .item-description {
            color: var(--text-dark);
            margin-bottom: 15px;
            min-height: 40px;
        }

        .item-price {
            font-size: 24px;
            font-weight: 700;
            color: var(--success-color);
            margin-bottom: 15px;
        }

        .item-price.free {
            color: var(--secondary-color);
        }

        .buy-button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: all 0.3s ease;
        }

        .buy-button:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }

        .buy-button:disabled {
            opacity: 0.5;
            cursor: not-allowed !important;
        }

        .buy-button.owned {
            background: linear-gradient(135deg, var(--success-color) 0%, #27ae60 100%);
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

        /* Shop Filters */
        .shop-filters {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: var(--border-radius-lg);
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: var(--border-radius);
            font-size: 14px;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
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

    
        /* CSS hỗ trợ xem thử con trỏ */
        body.previewing-cursor {
            cursor: var(--preview-default), auto !important;
        }
        body.previewing-cursor button, 
        body.previewing-cursor a, 
        body.previewing-cursor input, 
        body.previewing-cursor select,
        body.previewing-cursor .buy-button {
            cursor: var(--preview-pointer), pointer !important;
        }
</style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>


    <div class="shop-container">
        <div class="header-shop">
            <h1>🛒 Cửa Hàng</h1>
            <div class="balance-display">
                💰 Số Gtlm: <?php echo number_format($user['Money'], 0, ',', '.'); ?> gtlm (ảo)
            </div>
            <div style="font-size: 12px; color: var(--warning-color); margin-top: 5px; font-weight: 600;">
                ⚠️ Lưu ý: Web không hỗ trợ nạp/rút gtlm thật. Tất cả gtlm đều là ảo.
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="shop-tabs">
            <button class="tab-button active" onclick="switchTab('themes')">🎨 Themes</button>
            <button class="tab-button" onclick="switchTab('cursors')">🖱️ Cursors</button>
            <button class="tab-button" onclick="switchTab('chat-frames')">💬 Khung Chat</button>
            <button class="tab-button" onclick="switchTab('avatar-frames')">🖼️ Khung Avatar</button>
        </div>

        <!-- Shop Filters -->
        <div class="shop-filters" id="shop-filters">
            <div class="filter-group">
                <label>🔍 Tìm Kiếm</label>
                <input type="text" id="shop-search" placeholder="Tìm theo tên hoặc mô tả..." onkeyup="filterItems()">
            </div>
            <div class="filter-group">
                <label>Độ Hiếm</label>
                <select id="shop-rarity" onchange="filterItems()">
                    <option value="">Tất Cả</option>
                    <option value="common">Common</option>
                    <option value="rare">Rare</option>
                    <option value="epic">Epic</option>
                    <option value="legendary">Legendary</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Giá Từ</label>
                <input type="number" id="shop-min-price" placeholder="Min" min="0" onchange="filterItems()">
            </div>
            <div class="filter-group">
                <label>Giá Đến</label>
                <input type="number" id="shop-max-price" placeholder="Max" min="0" onchange="filterItems()">
            </div>
            <div class="filter-group">
                <label>Sắp Xếp</label>
                <select id="shop-sort" onchange="filterItems()">
                    <option value="price-asc">Giá: Thấp → Cao</option>
                    <option value="price-desc">Giá: Cao → Thấp</option>
                    <option value="name-asc">Tên: A → Z</option>
                    <option value="name-desc">Tên: Z → A</option>
                </select>
            </div>
        </div>

        <!-- Themes Tab -->
        <div id="themes-tab" class="tab-content active">
            <div class="items-grid">
                <?php foreach ($themes as $theme): ?>
                    <div
                        class="item-card <?= $theme['owned'] > 0 ? 'owned' : '' ?> <?= $current['current_theme_id'] == $theme['id'] ? 'active' : '' ?>">
                        <div class="item-preview"
                            style="background: <?php
                            $bgGradient = !empty($theme['background_gradient']) ? json_decode($theme['background_gradient'], true) : ['#667eea', '#764ba2', '#4facfe'];
                            echo 'linear-gradient(135deg, ' . htmlspecialchars($bgGradient[0]) . ' 0%, ' . htmlspecialchars($bgGradient[1]) . ' 50%, ' . htmlspecialchars($bgGradient[2] ?? $bgGradient[1]) . ' 100%)';
                            ?>; min-height: 150px; display: flex; align-items: center; justify-content: center; border-radius: var(--border-radius); position: relative; overflow: hidden;">
                            <div
                                style="position: relative; z-index: 1; font-size: 48px; text-shadow: 0 2px 10px rgba(0,0,0,0.3);">
                                🎨</div>
                            <div
                                style="position: absolute; top: 10px; left: 10px; background: rgba(0,0,0,0.3); color: white; padding: 5px 10px; border-radius: 5px; font-size: 12px;">
                                <?php
                                $particleColor = $theme['particle_color'] ?? '#ffffff';
                                echo '<span style="color: ' . htmlspecialchars($particleColor) . ';">●</span> ' . htmlspecialchars($theme['name']);
                                ?>
                            </div>
                        </div>
                        <div class="item-name"><?= htmlspecialchars($theme['name']) ?></div>
                        <div class="item-description"><?= htmlspecialchars($theme['description']) ?></div>
                        <div class="item-price <?= $theme['price'] == 0 ? 'free' : '' ?>">
                            <?= $theme['price'] == 0 ? 'Miễn phí' : number_format($theme['price'], 0, ',', '.') . ' gtlm' ?>
                        </div>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="theme_id" value="<?= $theme['id'] ?>">
                            <button type="submit" name="buy_theme"
                                class="buy-button <?= $theme['owned'] > 0 ? 'owned' : '' ?>" <?= $user['Money'] < $theme['price'] && $theme['owned'] == 0 ? 'disabled' : '' ?>>
                                <?php if ($current['current_theme_id'] == $theme['id']): ?>
                                    ✓ Đang sử dụng
                                <?php elseif ($theme['owned'] > 0): ?>
                                    ✓ Kích hoạt
                                <?php else: ?>
                                    💰 Mua ngay
                                <?php endif; ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Cursors Tab -->
        <div id="cursors-tab" class="tab-content">
            <div class="items-grid">
                <?php foreach ($cursors as $cursor): ?>
                    <div
                        class="item-card <?= $cursor['owned'] > 0 ? 'owned' : '' ?> <?= $current['current_cursor_id'] == $cursor['id'] ? 'active' : '' ?>"
                        onmouseenter="previewCursor(this, '<?= htmlspecialchars($cursor['cursor_image']) ?>', '<?= htmlspecialchars($cursor['pointer_image'] ?? $cursor['cursor_image']) ?>')"
                        onmouseleave="resetCursor()">
                        <div class="cursor-preview">
                            <div class="preview-item">
                                <img src="<?= htmlspecialchars($cursor['cursor_image']) ?>"
                                    alt="Default" onerror="this.src='chuot.png'">
                                <span class="label">Mặc định</span>
                            </div>
                            <?php if (!empty($cursor['pointer_image'])): ?>
                            <div class="preview-item">
                                <img src="<?= htmlspecialchars($cursor['pointer_image']) ?>"
                                    alt="Pointer" onerror="this.src='img/tay.png'">
                                <span class="label">Bàn tay</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="item-name"><?= htmlspecialchars($cursor['name']) ?></div>
                        <div class="item-description"><?= htmlspecialchars($cursor['description']) ?></div>
                        <div class="item-price <?= $cursor['price'] == 0 ? 'free' : '' ?>">
                            <?= $cursor['price'] == 0 ? 'Miễn phí' : number_format($cursor['price'], 0, ',', '.') . ' gtlm' ?>
                        </div>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="cursor_id" value="<?= $cursor['id'] ?>">
                            <button type="submit" name="buy_cursor"
                                class="buy-button <?= $cursor['owned'] > 0 ? 'owned' : '' ?>" <?= $user['Money'] < $cursor['price'] && $cursor['owned'] == 0 ? 'disabled' : '' ?>>
                                <?php if ($current['current_cursor_id'] == $cursor['id']): ?>
                                    ✓ Đang sử dụng
                                <?php elseif ($cursor['owned'] > 0): ?>
                                    ✓ Kích hoạt
                                <?php else: ?>
                                    💰 Mua ngay
                                <?php endif; ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Chat Frames Tab -->
        <div id="chat-frames-tab" class="tab-content">
            <div class="items-grid">
                <?php foreach ($chatFrames as $frame): ?>
                    <div
                        class="item-card <?= $frame['owned'] > 0 ? 'owned' : '' ?> <?= $current['chat_frame_id'] == $frame['id'] ? 'active' : '' ?>">
                        <div class="item-preview"
                            style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                            <?php if (!empty($frame['ImageURL'])): ?>
                                <img src="<?= htmlspecialchars($frame['ImageURL']) ?>"
                                    alt="<?= htmlspecialchars($frame['frame_name']) ?>"
                                    style="max-width: 100%; max-height: 150px; object-fit: contain;"
                                    onerror="this.parentElement.innerHTML='<div style=\'color: #999;\'>💬</div>'">
                            <?php else: ?>
                                <div style="font-size: 48px;">💬</div>
                            <?php endif; ?>
                        </div>
                        <div class="item-name"><?= htmlspecialchars($frame['frame_name']) ?></div>
                        <div class="item-description"><?= htmlspecialchars($frame['description'] ?? '') ?></div>
                        <div class="item-price <?= $frame['price'] == 0 ? 'free' : '' ?>">
                            <?= $frame['price'] == 0 ? 'Miễn phí' : number_format($frame['price'], 0, ',', '.') . ' gtlm' ?>
                        </div>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="chat_frame_id" value="<?= $frame['id'] ?>">
                            <button type="submit" name="buy_chat_frame"
                                class="buy-button <?= $frame['owned'] > 0 ? 'owned' : '' ?>" <?= $user['Money'] < $frame['price'] && $frame['owned'] == 0 ? 'disabled' : '' ?>>
                                <?php if ($current['chat_frame_id'] == $frame['id']): ?>
                                    ✓ Đang sử dụng
                                <?php elseif ($frame['owned'] > 0): ?>
                                    ✓ Kích hoạt
                                <?php else: ?>
                                    💰 Mua ngay
                                <?php endif; ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Avatar Frames Tab -->
        <div id="avatar-frames-tab" class="tab-content">
            <div class="items-grid">
                <?php foreach ($avatarFrames as $frame): ?>
                    <div
                        class="item-card <?= $frame['owned'] > 0 ? 'owned' : '' ?> <?= $current['avatar_frame_id'] == $frame['id'] ? 'active' : '' ?>">
                        <div class="item-preview"
                            style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                            <?php if (!empty($frame['ImageURL'])): ?>
                                <img src="<?= htmlspecialchars($frame['ImageURL']) ?>"
                                    alt="<?= htmlspecialchars($frame['frame_name']) ?>"
                                    style="max-width: 100%; max-height: 150px; object-fit: contain;"
                                    onerror="this.parentElement.innerHTML='<div style=\'color: #999;\'>🖼️</div>'">
                            <?php else: ?>
                                <div style="font-size: 48px;">🖼️</div>
                            <?php endif; ?>
                        </div>
                        <div class="item-name"><?= htmlspecialchars($frame['frame_name']) ?></div>
                        <div class="item-description"><?= htmlspecialchars($frame['description'] ?? '') ?></div>
                        <div class="item-price <?= $frame['price'] == 0 ? 'free' : '' ?>">
                            <?= $frame['price'] == 0 ? 'Miễn phí' : number_format($frame['price'], 0, ',', '.') . ' gtlm' ?>
                        </div>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="avatar_frame_id" value="<?= $frame['id'] ?>">
                            <button type="submit" name="buy_avatar_frame"
                                class="buy-button <?= $frame['owned'] > 0 ? 'owned' : '' ?>" <?= $user['Money'] < $frame['price'] && $frame['owned'] == 0 ? 'disabled' : '' ?>>
                                <?php if ($current['avatar_frame_id'] == $frame['id']): ?>
                                    ✓ Đang sử dụng
                                <?php elseif ($frame['owned'] > 0): ?>
                                    ✓ Kích hoạt
                                <?php else: ?>
                                    💰 Mua ngay
                                <?php endif; ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <a href="index.php" class="back-link">🏠 Về Trang Chủ</a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";

            const interactiveElements = document.querySelectorAll('button, a, input, label, select');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            });
        });

        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active class from all buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            const targetTab = document.getElementById(tabName + '-tab');
            if (targetTab) {
                targetTab.classList.add('active');
            }

            // Add active class to clicked button
            if (event && event.target) {
                event.target.classList.add('active');
            }

            // Re-apply filters when switching tabs
            filterItems();
        }

                        function previewCursor(element, cursorUrl, pointerUrl) {
            function getResizedCursor(url, callback) {
                const img = new Image();
                img.crossOrigin = "Anonymous";
                img.onload = function() {
                    const canvas = document.createElement("canvas");
                    canvas.width = 32; canvas.height = 32;
                    const ctx = canvas.getContext("2d");
                    ctx.drawImage(img, 0, 0, 32, 32);
                    callback(canvas.toDataURL("image/png"));
                };
                img.src = url;
            }

            // Lấy cả 2 ảnh đã resize
            getResizedCursor(cursorUrl, function(resDefault) {
                getResizedCursor(pointerUrl, function(resPointer) {
                    document.body.style.setProperty('--preview-default', `url('${resDefault}')`);
                    document.body.style.setProperty('--preview-pointer', `url('${resPointer}')`);
                    document.body.classList.add('previewing-cursor');
                });
            });

            element.style.background = "rgba(102, 126, 234, 0.1)";
        }

        function resetCursor() {
            document.body.classList.remove('previewing-cursor');
            document.body.style.removeProperty('--preview-default');
            document.body.style.removeProperty('--preview-pointer');
            document.querySelectorAll('.item-card').forEach(el => el.style.background = "");
        }

        function filterItems() {
            const search = $('#shop-search').val().toLowerCase();
            const rarity = $('#shop-rarity').val();
            const minPrice = parseFloat($('#shop-min-price').val()) || 0;
            const maxPrice = parseFloat($('#shop-max-price').val()) || Infinity;
            const sortBy = $('#shop-sort').val();

            // Get active tab
            const activeTab = document.querySelector('.tab-content.active');
            if (!activeTab) return;

            const items = activeTab.querySelectorAll('.item-card');

            items.forEach(item => {
                const name = (item.querySelector('.item-name')?.textContent || '').toLowerCase();
                const desc = (item.querySelector('.item-description')?.textContent || '').toLowerCase();
                const priceText = item.querySelector('.item-price')?.textContent || '';
                const price = parseFloat(priceText.replace(/[^\d]/g, '')) || 0;
                const rarityBadge = item.querySelector('.item-card')?.getAttribute('data-rarity') || '';

                // Search filter
                const matchesSearch = !search || name.includes(search) || desc.includes(search);

                // Rarity filter (if data-rarity attribute exists)
                const matchesRarity = !rarity || rarityBadge === rarity;

                // Price filter
                const matchesPrice = price >= minPrice && price <= maxPrice;

                if (matchesSearch && matchesRarity && matchesPrice) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });

            // Sort items
            const itemsArray = Array.from(items).filter(item => item.style.display !== 'none');
            const container = activeTab.querySelector('.items-grid');

            itemsArray.sort((a, b) => {
                if (sortBy === 'price-asc') {
                    const priceA = parseFloat((a.querySelector('.item-price')?.textContent || '').replace(/[^\d]/g, '')) || 0;
                    const priceB = parseFloat((b.querySelector('.item-price')?.textContent || '').replace(/[^\d]/g, '')) || 0;
                    return priceA - priceB;
                } else if (sortBy === 'price-desc') {
                    const priceA = parseFloat((a.querySelector('.item-price')?.textContent || '').replace(/[^\d]/g, '')) || 0;
                    const priceB = parseFloat((b.querySelector('.item-price')?.textContent || '').replace(/[^\d]/g, '')) || 0;
                    return priceB - priceA;
                } else if (sortBy === 'name-asc') {
                    const nameA = (a.querySelector('.item-name')?.textContent || '').toLowerCase();
                    const nameB = (b.querySelector('.item-name')?.textContent || '').toLowerCase();
                    return nameA.localeCompare(nameB);
                } else if (sortBy === 'name-desc') {
                    const nameA = (a.querySelector('.item-name')?.textContent || '').toLowerCase();
                    const nameB = (b.querySelector('.item-name')?.textContent || '').toLowerCase();
                    return nameB.localeCompare(nameA);
                }
                return 0;
            });

            // Re-append sorted items
            itemsArray.forEach(item => container.appendChild(item));
        }

        // Kiểm tra hash trong URL hoặc sessionStorage và tự động chọn tab
        window.addEventListener('DOMContentLoaded', function () {
            let tabToOpen = null;

            // Ưu tiên hash trong URL
            const hash = window.location.hash.replace('#', '');
            if (hash === 'themes' || hash === 'cursors' || hash === 'chat-frames' || hash === 'avatar-frames') {
                tabToOpen = hash;
            }
            // Nếu không có hash, kiểm tra sessionStorage
            else if (sessionStorage.getItem('openTab')) {
                tabToOpen = sessionStorage.getItem('openTab');
                sessionStorage.removeItem('openTab'); // Xóa sau khi dùng
            }

            if (tabToOpen) {
                // Remove active từ tab hiện tại
                document.querySelectorAll('.tab-content').forEach(tab => {
                    tab.classList.remove('active');
                });
                document.querySelectorAll('.tab-button').forEach(btn => {
                    btn.classList.remove('active');
                });

                // Activate tab được chọn
                const targetTab = document.getElementById(tabToOpen + '-tab');
                const tabButton = document.querySelector(`.tab-button[onclick*="'${tabToOpen}'"]`);

                if (targetTab && tabButton) {
                    targetTab.classList.add('active');
                    tabButton.classList.add('active');

                    // Scroll đến tab
                    setTimeout(() => {
                        targetTab.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 100);
                }
            }
        });
    </script>













    <!-- Premium Effects System -->
    <canvas id="threejs-background"></canvas>
    <script>
        (function () {
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
            const prefix = window.location.pathname.includes('/games/') ? '../' : '';
            const scripts = ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'];

            scripts.forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();
    </script>

</body>

</html>