<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

// Kiểm tra kết nối database
if (!$conn || $conn->connect_error) {
    die("Lỗi kết nối database: " . ($conn ? $conn->connect_error : "Không thể kết nối"));
}

$userId = $_SESSION['Iduser'];

// Lấy thông tin user
$userSql = "SELECT Money, current_theme_id FROM users WHERE Iduser = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$userStmt->close();

// Xử lý apply theme
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['apply_theme'])) {
    $themeId = (int)$_POST['theme_id'];
    
    // Kiểm tra đã sở hữu theme chưa
    $checkSql = "SELECT * FROM user_themes WHERE user_id = ? AND theme_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    if ($checkStmt) {
        $checkStmt->bind_param("ii", $userId, $themeId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Đã sở hữu, chỉ cần kích hoạt
            $updateSql = "UPDATE user_themes SET is_active = 1 WHERE user_id = ? AND theme_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            if ($updateStmt) {
                $updateStmt->bind_param("ii", $userId, $themeId);
                $updateStmt->execute();
                $updateStmt->close();
            }
        } else {
            // Chưa sở hữu, cần mua
            $themeSql = "SELECT * FROM themes WHERE id = ?";
            $themeStmt = $conn->prepare($themeSql);
            if ($themeStmt) {
                $themeStmt->bind_param("i", $themeId);
                $themeStmt->execute();
                $themeResult = $themeStmt->get_result();
                $theme = $themeResult->fetch_assoc();
                $themeStmt->close();
                
                if ($theme && $user['Money'] >= $theme['price']) {
                    // Trừ tiền
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
                } else {
                    $_SESSION['preview_message'] = "❌ Không đủ tiền hoặc theme không tồn tại!";
                    header("Location: preview_themes.php");
                    exit();
                }
            }
        }
        
        // Cập nhật current_theme_id
        $updateUserSql = "UPDATE users SET current_theme_id = ? WHERE Iduser = ?";
        $updateUserStmt = $conn->prepare($updateUserSql);
        if ($updateUserStmt) {
            $updateUserStmt->bind_param("ii", $themeId, $userId);
            $updateUserStmt->execute();
            $updateUserStmt->close();
        }
        
        $_SESSION['preview_message'] = "✅ Áp dụng theme thành công! Đang chuyển hướng...";
        $checkStmt->close();
        
        // Reload sau 1 giây
        echo '<script>setTimeout(function(){ window.location.href = "index.php"; }, 1000);</script>';
    }
}

// Lấy tất cả themes
$themes = [];
$checkThemesTable = $conn->query("SHOW TABLES LIKE 'themes'");
if ($checkThemesTable && $checkThemesTable->num_rows > 0) {
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
    }
}

// Reload số dư
$userSql = "SELECT Money, current_theme_id FROM users WHERE Iduser = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$userStmt->close();

$message = $_SESSION['preview_message'] ?? "";
unset($_SESSION['preview_message']);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xem Trước Themes - Full Background Preview</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/animations.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            font-family: 'Segoe UI', sans-serif;
            overflow-x: hidden;
        }

        button, a, input, label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .theme-preview-container {
            position: relative;
            width: 100%;
            min-height: 100vh;
            overflow: hidden;
        }

        .theme-preview {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            z-index: 1;
            transition: opacity 0.5s ease, z-index 0.5s ease;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .theme-preview.active {
            z-index: 10;
            opacity: 1 !important;
            visibility: visible !important;
            display: block !important;
        }

        .theme-preview.hidden {
            opacity: 0 !important;
            pointer-events: none;
            z-index: 0;
            /* CRITICAL: Keep visibility and display so child canvas can render */
            visibility: visible !important;
            display: block !important;
        }

        .theme-preview canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            opacity: 1;
            transition: opacity 0.5s ease;
        }

        .theme-preview.hidden canvas,
        .theme-preview.hidden .threejs-canvas {
            opacity: 0 !important;
            pointer-events: none;
            /* CRITICAL: Keep visibility visible so browser continues rendering Three.js */
            visibility: visible !important;
            display: block !important;
        }

        .theme-preview.active canvas,
        .theme-preview.active .threejs-canvas {
            opacity: 1 !important;
            z-index: 10;
            pointer-events: none;
            visibility: visible !important;
            display: block !important;
        }

        .threejs-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 2;
            pointer-events: none;
            opacity: 1;
            transition: opacity 0.5s ease;
        }

        .threejs-canvas.hidden {
            opacity: 0 !important;
            pointer-events: none;
            /* CRITICAL: Must keep visible and in viewport for Three.js to render */
            visibility: visible !important;
            display: block !important;
            /* Canvas must stay in DOM and viewport for WebGL context to work */
        }

        .threejs-canvas.active {
            opacity: 1 !important;
            z-index: 10;
            pointer-events: none;
            visibility: visible !important;
            display: block !important;
        }
        
        /* Ensure all canvases are always rendered */
        .threejs-canvas {
            /* Force rendering even when hidden */
            will-change: transform, opacity;
        }

        .theme-controls {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.9);
            padding: 30px;
            z-index: 1000;
            backdrop-filter: blur(10px);
            border-top: 2px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 -5px 30px rgba(0, 0, 0, 0.5);
        }

        .theme-info {
            text-align: center;
            color: white;
            margin-bottom: 20px;
        }

        .theme-info h2 {
            font-size: 32px;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .theme-info p {
            font-size: 18px;
            opacity: 0.9;
        }

        .theme-nav {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .theme-nav button {
            padding: 15px 30px;
            font-size: 18px;
            font-weight: 700;
            border: none;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .theme-nav button:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }

        .theme-actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .btn-apply {
            padding: 18px 40px;
            font-size: 20px;
            font-weight: 700;
            border: none;
            border-radius: 50px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(40, 167, 69, 0.4);
        }

        .btn-apply:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 30px rgba(40, 167, 69, 0.6);
        }

        .btn-apply:disabled {
            background: rgba(255, 255, 255, 0.2);
            opacity: 0.5;
            cursor: not-allowed !important;
        }

        .btn-buy {
            padding: 18px 40px;
            font-size: 20px;
            font-weight: 700;
            border: none;
            border-radius: 50px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(255, 107, 107, 0.4);
        }

        .btn-buy:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 30px rgba(255, 107, 107, 0.6);
        }

        .btn-back {
            padding: 15px 30px;
            font-size: 18px;
            font-weight: 700;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-3px);
        }

        .theme-list {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 2000;
            background: rgba(0, 0, 0, 0.8);
            padding: 20px;
            border-radius: 15px;
            max-width: 300px;
            max-height: 80vh;
            overflow-y: auto;
            backdrop-filter: blur(10px);
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.5);
        }

        .theme-list h3 {
            color: white;
            margin-bottom: 15px;
            font-size: 20px;
        }

        .theme-item {
            padding: 12px;
            margin-bottom: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .theme-item:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(-5px);
        }

        .theme-item.active {
            border-color: #28a745;
            background: rgba(40, 167, 69, 0.2);
        }

        .theme-item-name {
            color: white;
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .theme-item-price {
            color: #ffd700;
            font-size: 14px;
        }

        .theme-item-owned {
            color: #28a745;
            font-size: 12px;
            font-weight: 600;
        }

        .balance-display {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 2000;
            background: rgba(0, 0, 0, 0.8);
            padding: 15px 25px;
            border-radius: 50px;
            color: white;
            font-size: 20px;
            font-weight: 700;
            backdrop-filter: blur(10px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }

        .message {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10000;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 30px 50px;
            border-radius: 15px;
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        .theme-counter {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 5000;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 20px 40px;
            border-radius: 50px;
            font-size: 48px;
            font-weight: 700;
            backdrop-filter: blur(10px);
            display: none;
            animation: pulse 1s ease;
        }

        .theme-counter.show {
            display: block;
        }

        @keyframes pulse {
            0%, 100% {
                transform: translate(-50%, -50%) scale(1);
            }
            50% {
                transform: translate(-50%, -50%) scale(1.2);
            }
        }

        @media (max-width: 768px) {
            .theme-list {
                position: relative;
                top: auto;
                right: auto;
                max-width: 100%;
                margin: 20px;
            }

            .balance-display {
                position: relative;
                top: auto;
                left: auto;
                margin: 20px;
                display: inline-block;
            }

            .theme-controls {
                padding: 20px;
            }

            .theme-info h2 {
                font-size: 24px;
            }

            .theme-nav button,
            .btn-apply,
            .btn-buy {
                padding: 12px 24px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="balance-display">
        💰 Số dư: <?= number_format($user['Money'], 0, ',', '.') ?> VNĐ
    </div>

    <?php if ($message): ?>
        <div class="message">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="theme-counter" id="themeCounter">1</div>

    <div class="theme-list">
        <h3>🎨 Danh Sách Themes</h3>
        <?php foreach ($themes as $index => $theme): ?>
            <?php
            $bgGradient = !empty($theme['background_gradient']) ? json_decode($theme['background_gradient'], true) : ['#667eea', '#764ba2', '#4facfe'];
            if (!is_array($bgGradient) || count($bgGradient) < 2) {
                $bgGradient = ['#667eea', '#764ba2', '#4facfe'];
            }
            if (count($bgGradient) < 3) {
                $bgGradient[] = $bgGradient[count($bgGradient) - 1];
            }
            ?>
            <div class="theme-item <?= $index === 0 ? 'active' : '' ?>" 
                 data-theme-id="<?= $theme['id'] ?>" 
                 data-index="<?= $index ?>"
                 onclick="showTheme(<?= $index ?>)"
                 style="border-left: 4px solid <?= htmlspecialchars($bgGradient[0]) ?>;">
                <div class="theme-item-name"><?= htmlspecialchars($theme['name']) ?></div>
                <?php if ($theme['owned'] > 0): ?>
                    <div class="theme-item-owned">✓ Đã sở hữu</div>
                <?php else: ?>
                    <div class="theme-item-price">
                        <?= $theme['price'] == 0 ? 'Miễn phí' : number_format($theme['price'], 0, ',', '.') . ' VNĐ' ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="theme-preview-container">
        <?php foreach ($themes as $index => $theme): ?>
            <?php
            $bgGradient = !empty($theme['background_gradient']) ? json_decode($theme['background_gradient'], true) : ['#667eea', '#764ba2', '#4facfe'];
            if (!is_array($bgGradient) || count($bgGradient) < 2) {
                $bgGradient = ['#667eea', '#764ba2', '#4facfe'];
            }
            if (count($bgGradient) < 3) {
                $bgGradient[] = $bgGradient[count($bgGradient) - 1];
            }
            $bgGradientCSS = 'linear-gradient(135deg, ' . 
                htmlspecialchars($bgGradient[0]) . ' 0%, ' . 
                htmlspecialchars($bgGradient[1]) . ' 50%, ' . 
                htmlspecialchars($bgGradient[2] ?? $bgGradient[1]) . ' 100%)';
            ?>
            <?php
            // Parse theme config for Three.js
            $particleCount = $theme['particle_count'] ?? 1000;
            $particleSize = $theme['particle_size'] ?? 0.05;
            $particleColor = $theme['particle_color'] ?? '#ffffff';
            $particleOpacity = $theme['particle_opacity'] ?? 0.6;
            $shapeCount = $theme['shape_count'] ?? 15;
            $shapeColors = !empty($theme['shape_colors']) ? json_decode($theme['shape_colors'], true) : ['#667eea', '#764ba2', '#4facfe', '#00f2fe'];
            $shapeOpacity = $theme['shape_opacity'] ?? 0.3;
            ?>
            <div class="theme-preview <?= $index === 0 ? 'active' : 'hidden' ?>" 
                 id="theme-<?= $index ?>" 
                 data-theme-index="<?= $index ?>"
                 data-theme-name="<?= htmlspecialchars($theme['name'] ?? '') ?>"
                 data-particle-count="<?= $particleCount ?>"
                 data-particle-size="<?= $particleSize ?>"
                 data-particle-color="<?= htmlspecialchars($particleColor) ?>"
                 data-particle-opacity="<?= $particleOpacity ?>"
                 data-shape-count="<?= $shapeCount ?>"
                 data-shape-colors="<?= htmlspecialchars(json_encode($shapeColors)) ?>"
                 data-shape-opacity="<?= $shapeOpacity ?>"
                 style="background: <?= $bgGradientCSS ?>; background-attachment: fixed; background-size: 100% 100%;">
                <canvas class="threejs-canvas <?= $index === 0 ? 'active' : 'hidden' ?>" 
                        id="threejs-canvas-<?= $index ?>"></canvas>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="theme-controls">
        <div class="theme-info">
            <h2 id="themeName"><?= !empty($themes) ? htmlspecialchars($themes[0]['name']) : 'Không có theme' ?></h2>
            <p id="themeDescription"><?= !empty($themes) ? htmlspecialchars($themes[0]['description']) : '' ?></p>
        </div>

        <div class="theme-nav">
            <button onclick="previousTheme()">⬅ Theme Trước</button>
            <button onclick="nextTheme()">Theme Tiếp ➡</button>
        </div>

        <div class="theme-actions">
            <a href="index.php" class="btn-back">🏠 Về Trang Chủ</a>
            <?php if (!empty($themes)): ?>
                <?php 
                $currentTheme = $themes[0];
                $canApply = ($currentTheme['owned'] > 0) || ($currentTheme['price'] == 0) || ($user['Money'] >= $currentTheme['price']);
                ?>
                <?php if ($currentTheme['owned'] > 0 || $currentTheme['price'] == 0): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="theme_id" id="applyThemeId" value="<?= $currentTheme['id'] ?>">
                        <button type="submit" name="apply_theme" class="btn-apply">
                            ✅ Áp Dụng Theme Này
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="theme_id" id="buyThemeId" value="<?= $currentTheme['id'] ?>">
                        <button type="submit" name="apply_theme" class="btn-buy" 
                                <?= !$canApply ? 'disabled' : '' ?>>
                            💰 Mua Và Áp Dụng (<?= number_format($currentTheme['price'], 0, ',', '.') ?> VNĐ)
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let currentThemeIndex = 0;
        const themes = <?= json_encode($themes) ?>;
        const totalThemes = themes.length;

        function showTheme(index) {
            // Ẩn tất cả themes
            document.querySelectorAll('.theme-preview').forEach(preview => {
                preview.classList.remove('active');
                preview.classList.add('hidden');
            });

            // Hiển thị theme được chọn
            const selectedPreview = document.getElementById('theme-' + index);
            if (selectedPreview) {
                selectedPreview.classList.remove('hidden');
                selectedPreview.classList.add('active');
            }

            // Update theme info
            if (themes[index]) {
                document.getElementById('themeName').textContent = themes[index].name;
                document.getElementById('themeDescription').textContent = themes[index].description || '';
                document.getElementById('applyThemeId').value = themes[index].id;
                document.getElementById('buyThemeId').value = themes[index].id;

                // Update theme item active
                document.querySelectorAll('.theme-item').forEach(item => {
                    item.classList.remove('active');
                });
                document.querySelector(`[data-index="${index}"]`).classList.add('active');

                // Update buttons
                updateActionButtons(themes[index]);
            }

            currentThemeIndex = index;
            updateCounter();
        }

        function updateActionButtons(theme) {
            const actionsDiv = document.querySelector('.theme-actions');
            const userMoney = <?= $user['Money'] ?>;
            const owned = theme.owned > 0;
            const canBuy = theme.price == 0 || userMoney >= theme.price;

            if (owned || theme.price == 0) {
                actionsDiv.innerHTML = `
                    <a href="index.php" class="btn-back">🏠 Về Trang Chủ</a>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="theme_id" value="${theme.id}">
                        <button type="submit" name="apply_theme" class="btn-apply">
                            ✅ Áp Dụng Theme Này
                        </button>
                    </form>
                `;
            } else {
                actionsDiv.innerHTML = `
                    <a href="index.php" class="btn-back">🏠 Về Trang Chủ</a>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="theme_id" value="${theme.id}">
                        <button type="submit" name="apply_theme" class="btn-buy" ${!canBuy ? 'disabled' : ''}>
                            💰 Mua Và Áp Dụng (${parseInt(theme.price).toLocaleString('vi-VN')} VNĐ)
                        </button>
                    </form>
                `;
            }
        }

        function nextTheme() {
            currentThemeIndex = (currentThemeIndex + 1) % totalThemes;
            showTheme(currentThemeIndex);
        }

        function previousTheme() {
            currentThemeIndex = (currentThemeIndex - 1 + totalThemes) % totalThemes;
            showTheme(currentThemeIndex);
        }

        function updateCounter() {
            const counter = document.getElementById('themeCounter');
            counter.textContent = `${currentThemeIndex + 1} / ${totalThemes}`;
            counter.classList.add('show');
            setTimeout(() => {
                counter.classList.remove('show');
            }, 1000);
        }

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') {
                previousTheme();
            } else if (e.key === 'ArrowRight') {
                nextTheme();
            }
        });

        // Auto-rotate themes (optional)
        // setInterval(nextTheme, 5000);

        // Three.js Background Animation
        let scenes = [];
        let particlesMaterials = [];
        let shapesArray = [];
        let cameras = [];
        let renderers = [];

        function initThreeJS(index) {
            const canvas = document.getElementById('threejs-canvas-' + index);
            if (!canvas) {
                console.error('Canvas not found for theme', index);
                return;
            }

            const themePreview = document.getElementById('theme-' + index);
            if (!themePreview) {
                console.error('Theme preview not found for theme', index);
                return;
            }
            
            console.log('Initializing Three.js for theme', index, 'canvas:', canvas, 'preview:', themePreview);

            const particleCount = parseInt(themePreview.getAttribute('data-particle-count')) || 1000;
            const particleSize = parseFloat(themePreview.getAttribute('data-particle-size')) || 0.05;
            const particleColor = themePreview.getAttribute('data-particle-color') || '#ffffff';
            const particleOpacity = parseFloat(themePreview.getAttribute('data-particle-opacity')) || 0.6;
            const shapeCount = parseInt(themePreview.getAttribute('data-shape-count')) || 15;
            const shapeColors = JSON.parse(themePreview.getAttribute('data-shape-colors') || '["#667eea", "#764ba2", "#4facfe", "#00f2fe"]');
            const shapeOpacity = parseFloat(themePreview.getAttribute('data-shape-opacity')) || 0.3;
            const themeName = (themePreview.getAttribute('data-theme-name') || '').toLowerCase();
            
            // Kiểm tra xem có phải theme Anime không
            const isAnimeTheme = themeName.includes('anime') || themeName.includes('sakura') || themeName.includes('pastel');
            
            // Kiểm tra xem có phải theme Sao Băng không
            const isShootingStarTheme = themeName.includes('sao băng') || themeName.includes('shooting star');
            
            // Kiểm tra xem có phải theme Tận Thế không
            const isApocalypseTheme = themeName.includes('tận thế') || themeName.includes('apocalypse') || themeName.includes('end');

            const scene = new THREE.Scene();
            const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
            
            // Create renderer with proper settings - Tối ưu performance
            const renderer = new THREE.WebGLRenderer({ 
                canvas: canvas, 
                alpha: true, 
                antialias: false, // Tắt antialias để giảm lag
                powerPreference: "high-performance"
            });
            renderer.setSize(window.innerWidth, window.innerHeight);
            renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5)); // Giảm pixel ratio
            renderer.setClearColor(0x000000, 0); // Transparent background

            // Create particles
            const particlesGeometry = new THREE.BufferGeometry();
            const particlesCount = particleCount;
            const posArray = new Float32Array(particlesCount * 3);
            const velocityArray = new Float32Array(particlesCount * 3);
            const sizeArray = new Float32Array(particlesCount);

            for (let i = 0; i < particlesCount * 3; i += 3) {
                if (isApocalypseTheme) {
                    // Hiệu ứng Tận Thế: Tro bụi và lửa bay
                    // Particles rơi từ trên xuống như tro bụi và lửa
                    const startX = (Math.random() - 0.5) * 70;
                    const startY = Math.random() * 40 + 25; // Bắt đầu từ trên
                    const startZ = (Math.random() - 0.5) * 30;
                    
                    posArray[i] = startX;
                    posArray[i + 1] = startY;
                    posArray[i + 2] = startZ;
                    
                    // Vận tốc: rơi xuống và bay lên nhẹ (như lửa)
                    velocityArray[i] = (Math.random() - 0.5) * 0.04; // Bay ngang như gió
                    velocityArray[i + 1] = -(Math.random() * 0.08 + 0.03); // Rơi xuống chậm hơn sao băng
                    velocityArray[i + 2] = (Math.random() - 0.5) * 0.02; // Độ sâu
                    
                    // Kích thước tro bụi/lửa
                    sizeArray[i / 3] = Math.random() * particleSize * 4 + particleSize * 1.2;
                } else if (isShootingStarTheme) {
                    // Hiệu ứng Sao Băng rơi CHÉO
                    // Sao băng bắt đầu từ góc trên và rơi chéo xuống rõ ràng
                    const startX = (Math.random() - 0.5) * 100; // Rộng hơn để có nhiều góc
                    const startY = Math.random() * 50 + 40; // Bắt đầu từ trên cao hơn
                    const startZ = (Math.random() - 0.5) * 40;
                    
                    posArray[i] = startX;
                    posArray[i + 1] = startY;
                    posArray[i + 2] = startZ;
                    
                    // Vận tốc sao băng: rơi nhanh và CHÉO rõ ràng (góc -60 đến 60 độ)
                    const angle = Math.random() * Math.PI * 1.0 - Math.PI * 0.5; // Góc chéo rộng hơn
                    const speed = Math.random() * 0.2 + 0.15; // Tốc độ nhanh hơn
                    
                    // Di chuyển chéo rõ ràng: ngang và xuống cùng lúc
                    velocityArray[i] = Math.sin(angle) * speed; // Di chuyển ngang mạnh hơn
                    velocityArray[i + 1] = -speed * 1.2; // Rơi xuống nhanh
                    velocityArray[i + 2] = Math.cos(angle) * speed * 0.5; // Độ sâu
                    
                    // Kích thước sao băng lớn hơn và sáng
                    sizeArray[i / 3] = Math.random() * particleSize * 5 + particleSize * 2;
                } else if (isAnimeTheme) {
                    // Hiệu ứng Sakura rơi cho anime theme
                    posArray[i] = (Math.random() - 0.5) * 60;
                    posArray[i + 1] = Math.random() * 30 + 10;
                    posArray[i + 2] = (Math.random() - 0.5) * 20;
                    
                    velocityArray[i] = (Math.random() - 0.5) * 0.03;
                    velocityArray[i + 1] = -(Math.random() * 0.05 + 0.02);
                    velocityArray[i + 2] = (Math.random() - 0.5) * 0.01;
                    
                    sizeArray[i / 3] = Math.random() * particleSize * 3 + particleSize;
                } else {
                    posArray[i] = (Math.random() - 0.5) * 20;
                    posArray[i + 1] = (Math.random() - 0.5) * 20;
                    posArray[i + 2] = (Math.random() - 0.5) * 20;
                    
                    velocityArray[i] = (Math.random() - 0.5) * 0.02;
                    velocityArray[i + 1] = (Math.random() - 0.5) * 0.02;
                    velocityArray[i + 2] = (Math.random() - 0.5) * 0.02;
                    
                    sizeArray[i / 3] = Math.random() * particleSize * 2 + particleSize * 0.5;
                }
            }

            particlesGeometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));
            particlesGeometry.setAttribute('aVelocity', new THREE.BufferAttribute(velocityArray, 3));
            particlesGeometry.setAttribute('aSize', new THREE.BufferAttribute(sizeArray, 1));

            const particleColorNum = parseInt(particleColor.replace('#', ''), 16);
            const particlesMaterial = new THREE.PointsMaterial({
                size: particleSize,
                color: particleColorNum,
                transparent: true,
                opacity: particleOpacity,
                blending: (isShootingStarTheme || isApocalypseTheme) ? THREE.AdditiveBlending : (isAnimeTheme ? THREE.NormalBlending : THREE.AdditiveBlending),
                sizeAttenuation: true
            });

            const particlesMesh = new THREE.Points(particlesGeometry, particlesMaterial);
            scene.add(particlesMesh);

            // Hàm tạo hình trái tim 3D
            function createHeartGeometry(size) {
                const shape = new THREE.Shape();
                const heartSize = size * 0.08;
                const segments = 30;
                
                for (let i = 0; i <= segments; i++) {
                    const t = (i / segments) * Math.PI * 2;
                    const x = heartSize * 16 * Math.pow(Math.sin(t), 3);
                    const y = heartSize * (13 * Math.cos(t) - 5 * Math.cos(2*t) - 2 * Math.cos(3*t) - Math.cos(4*t));
                    
                    if (i === 0) {
                        shape.moveTo(x, y);
                    } else {
                        shape.lineTo(x, y);
                    }
                }
                shape.closePath();
                
                const extrudeSettings = {
                    depth: size * 0.25,
                    bevelEnabled: true,
                    bevelThickness: size * 0.04,
                    bevelSize: size * 0.04,
                    bevelSegments: 4
                };
                return new THREE.ExtrudeGeometry(shape, extrudeSettings);
            }
            
            // Hàm tạo hình ngôi sao 3D
            function createStarGeometry(size, points = 5) {
                const shape = new THREE.Shape();
                const outerRadius = size;
                const innerRadius = size * 0.4;
                
                for (let i = 0; i < points * 2; i++) {
                    const angle = (i * Math.PI) / points;
                    const radius = i % 2 === 0 ? outerRadius : innerRadius;
                    const x = Math.cos(angle) * radius;
                    const y = Math.sin(angle) * radius;
                    
                    if (i === 0) {
                        shape.moveTo(x, y);
                    } else {
                        shape.lineTo(x, y);
                    }
                }
                shape.lineTo(outerRadius, 0);
                
                const extrudeSettings = {
                    depth: size * 0.2,
                    bevelEnabled: true,
                    bevelThickness: size * 0.03,
                    bevelSize: size * 0.03,
                    bevelSegments: 2
                };
                return new THREE.ExtrudeGeometry(shape, extrudeSettings);
            }
            
            // Hàm tạo hình hoa sakura 5 cánh
            function createSakuraGeometry(size) {
                const shape = new THREE.Shape();
                const petals = 5;
                const petalLength = size;
                const petalWidth = size * 0.5;
                const centerRadius = size * 0.15;
                
                shape.moveTo(0, -centerRadius);
                
                for (let i = 0; i < petals; i++) {
                    const angle = (i * Math.PI * 2) / petals - Math.PI / 2;
                    const nextAngle = ((i + 1) * Math.PI * 2) / petals - Math.PI / 2;
                    
                    const x1 = Math.cos(angle) * petalLength;
                    const y1 = Math.sin(angle) * petalLength;
                    
                    const midAngle = angle + (nextAngle - angle) / 2;
                    const xMid = Math.cos(midAngle) * petalWidth;
                    const yMid = Math.sin(midAngle) * petalWidth;
                    
                    const x2 = Math.cos(nextAngle) * petalLength;
                    const y2 = Math.sin(nextAngle) * petalLength;
                    
                    shape.quadraticCurveTo(xMid, yMid, x1, y1);
                    shape.quadraticCurveTo(xMid, yMid, x2, y2);
                }
                
                shape.lineTo(0, -centerRadius);
                shape.closePath();
                
                const extrudeSettings = {
                    depth: size * 0.2,
                    bevelEnabled: true,
                    bevelThickness: size * 0.03,
                    bevelSize: size * 0.03,
                    bevelSegments: 3
                };
                return new THREE.ExtrudeGeometry(shape, extrudeSettings);
            }
            
            // Hàm tạo sparkle hình ngôi sao nhỏ
            function createSparkleGeometry(size) {
                return createStarGeometry(size, 4);
            }
            
            // Create shapes - Bỏ shapes cho theme Sao Băng và Tận Thế, chỉ giữ particles
            const shapes = [];
            const colors = shapeColors.map(c => parseInt(c.replace('#', ''), 16));

            // Chỉ tạo shapes nếu không phải theme Sao Băng hoặc Tận Thế
            if (!isShootingStarTheme && !isApocalypseTheme) {
                for (let i = 0; i < shapeCount; i++) {
                    let geometry;
                    const shapeType = Math.random();
                    const size = Math.random() * 0.5 + 0.3;
                    
                    if (isAnimeTheme) {
                        // Cho anime theme: tạo hình trái tim, ngôi sao, hoa sakura thật
                        if (shapeType < 0.35) {
                            geometry = createHeartGeometry(size);
                        } else if (shapeType < 0.65) {
                            geometry = createStarGeometry(size, 5);
                        } else if (shapeType < 0.85) {
                            geometry = createSakuraGeometry(size);
                        } else {
                            geometry = createSparkleGeometry(size * 0.6);
                        }
                    } else {
                        // Theme thường: sử dụng hình khối
                        geometry = new THREE.IcosahedronGeometry(Math.random() * 0.5 + 0.3, 0);
                    }
                    
                    const material = new THREE.MeshStandardMaterial({
                        color: colors[Math.floor(Math.random() * colors.length)],
                        transparent: true,
                        opacity: shapeOpacity,
                        emissive: isAnimeTheme ? colors[Math.floor(Math.random() * colors.length)] : 0x000000,
                        emissiveIntensity: isAnimeTheme ? 0.5 : 0,
                        metalness: isAnimeTheme ? 0.1 : 0.3,
                        roughness: isAnimeTheme ? 0.9 : 0.7,
                        wireframe: isAnimeTheme ? false : (Math.random() > 0.5)
                    });
                    
                    const mesh = new THREE.Mesh(geometry, material);
                    
                    const angle = (Math.PI * 2 * i) / shapeCount;
                    const radius = isAnimeTheme ? (6 + Math.random() * 4) : 7;
                    mesh.position.set(
                        Math.cos(angle) * radius + (Math.random() - 0.5) * 3,
                        isAnimeTheme ? (Math.random() * 8 - 4) : ((Math.random() - 0.5) * 15),
                        Math.sin(angle) * radius + (Math.random() - 0.5) * 3
                    );
                    
                    mesh.rotation.set(
                        Math.random() * Math.PI,
                        Math.random() * Math.PI,
                        Math.random() * Math.PI
                    );
                    
                    mesh.userData = {
                        rotationSpeed: {
                            x: isAnimeTheme ? ((Math.random() - 0.5) * 0.03) : ((Math.random() - 0.5) * 0.02),
                            y: isAnimeTheme ? ((Math.random() - 0.5) * 0.03) : ((Math.random() - 0.5) * 0.02),
                            z: isAnimeTheme ? ((Math.random() - 0.5) * 0.03) : ((Math.random() - 0.5) * 0.02)
                        },
                        orbitRadius: radius,
                        orbitAngle: angle,
                        orbitSpeed: isAnimeTheme ? ((Math.random() - 0.5) * 0.015) : ((Math.random() - 0.5) * 0.01),
                        originalY: mesh.position.y,
                        floatSpeed: isAnimeTheme ? (Math.random() * 0.015 + 0.008) : (Math.random() * 0.01 + 0.005),
                        floatAmount: isAnimeTheme ? (Math.random() * 3 + 1.5) : (Math.random() * 2 + 1),
                        isAnime: isAnimeTheme
                    };
                    
                    shapes.push(mesh);
                    scene.add(mesh);
                }
            }

            // Lighting - khác nhau cho anime, sao băng, tận thế và theme thường
            if (isApocalypseTheme) {
                // Ánh sáng đỏ rực như lửa cho tận thế
                const ambientLight = new THREE.AmbientLight(0xff4500, 0.4); // Ánh sáng đỏ cam
                scene.add(ambientLight);
                
                // Ánh sáng lửa đỏ
                const pointLight1 = new THREE.PointLight(colors[0] || 0xFF4500, 2.5, 100);
                pointLight1.position.set(10, 8, 10);
                scene.add(pointLight1);
                
                // Ánh sáng lửa cam
                if (colors[1]) {
                    const pointLight2 = new THREE.PointLight(colors[1], 2, 100);
                    pointLight2.position.set(-10, 6, -10);
                    scene.add(pointLight2);
                }
                
                // Ánh sáng đỏ từ dưới lên (như lửa cháy)
                if (colors[2]) {
                    const pointLight3 = new THREE.PointLight(colors[2], 1.8, 100);
                    pointLight3.position.set(0, -5, 0);
                    scene.add(pointLight3);
                }
                
                camera.position.z = 15;
                camera.position.y = 5;
            } else if (isShootingStarTheme) {
                // Ánh sáng sáng rực cho sao băng
                const ambientLight = new THREE.AmbientLight(0xffffff, 0.3);
                scene.add(ambientLight);
                
                const pointLight1 = new THREE.PointLight(colors[0] || 0xFFFFFF, 2, 100);
                pointLight1.position.set(10, 10, 10);
                scene.add(pointLight1);
                
                if (colors[1]) {
                    const pointLight2 = new THREE.PointLight(colors[1], 1.5, 100);
                    pointLight2.position.set(-10, 8, -10);
                    scene.add(pointLight2);
                }
                
                if (colors[2]) {
                    const pointLight3 = new THREE.PointLight(colors[2], 1.2, 100);
                    pointLight3.position.set(0, 15, 0);
                    scene.add(pointLight3);
                }
                
                camera.position.z = 15;
                camera.position.y = 5;
            } else if (isAnimeTheme) {
                const ambientLight = new THREE.AmbientLight(0xffffff, 0.7);
                scene.add(ambientLight);
                
                const pointLight1 = new THREE.PointLight(colors[0] || 0xFFB6C1, 1.2, 100);
                pointLight1.position.set(8, 8, 8);
                scene.add(pointLight1);
                
                if (colors[1]) {
                    const pointLight2 = new THREE.PointLight(colors[1], 0.9, 100);
                    pointLight2.position.set(-8, 6, -8);
                    scene.add(pointLight2);
                }
                
                camera.position.z = 12;
                camera.position.y = 3;
            } else {
                const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
                scene.add(ambientLight);

                const pointLight = new THREE.PointLight(0xffffff, 1);
                pointLight.position.set(5, 5, 5);
                scene.add(pointLight);

                camera.position.z = 5;
            }

            // Store references before animation
            scenes[index] = scene;
            particlesMaterials[index] = particlesMaterial;
            shapesArray[index] = shapes;
            cameras[index] = camera;
            renderers[index] = renderer;

            // Animation function - ALWAYS runs for all themes
            // This ensures Three.js keeps rendering even when hidden
            let time = 0;
            let lastTime = 0;
            const targetFPS = 30; // Giảm FPS để tối ưu
            const frameInterval = 1000 / targetFPS;
            
            function animate(currentTime) {
                // Always request next frame - animation runs continuously
                requestAnimationFrame(animate);
                
                // Throttle FPS để giảm lag
                const deltaTime = currentTime - lastTime;
                if (deltaTime < frameInterval) {
                    return;
                }
                lastTime = currentTime - (deltaTime % frameInterval);
                
                time += 0.01;

                // Update particles
                if (particlesMesh && particlesMesh.rotation) {
                    if (isApocalypseTheme) {
                        // Hiệu ứng Tận Thế: Tro bụi và lửa bay - Tối ưu
                        particlesMesh.rotation.y += 0.0004;
                        particlesMesh.rotation.x += 0.0002;
                        
                        // Di chuyển tro bụi và lửa - tối ưu performance
                        const positions = particlesGeometry.attributes.position.array;
                        const velocities = particlesGeometry.attributes.aVelocity.array;
                        const sinTime1 = Math.sin(time * 0.3); // Tính toán 1 lần
                        const sinTime2 = Math.sin(time * 0.5);
                        
                        for (let i = 0; i < positions.length; i += 3) {
                            // Tro bụi/lửa rơi và bay lên nhẹ
                            positions[i] += velocities[i] + sinTime1 * 0.008; // Giảm tính toán
                            positions[i + 1] += velocities[i + 1] + sinTime2 * 0.006;
                            positions[i + 2] += velocities[i + 2];
                            
                            // Reset khi rơi quá thấp
                            if (positions[i + 1] < -25) {
                                positions[i] = (Math.random() - 0.5) * 70;
                                positions[i + 1] = Math.random() * 40 + 25;
                                positions[i + 2] = (Math.random() - 0.5) * 30;
                                
                                velocities[i] = (Math.random() - 0.5) * 0.04;
                                velocities[i + 1] = -(Math.random() * 0.08 + 0.03);
                                velocities[i + 2] = (Math.random() - 0.5) * 0.02;
                            }
                        }
                        particlesGeometry.attributes.position.needsUpdate = true;
                    } else if (isShootingStarTheme) {
                        // Hiệu ứng Sao Băng rơi nhanh CHÉO - Tối ưu
                        particlesMesh.rotation.y += 0.0003;
                        
                        // Di chuyển sao băng - tối ưu hơn
                        const positions = particlesGeometry.attributes.position.array;
                        const velocities = particlesGeometry.attributes.aVelocity.array;
                        for (let i = 0; i < positions.length; i += 3) {
                            // Sao băng rơi nhanh và CHÉO rõ ràng
                            positions[i] += velocities[i];
                            positions[i + 1] += velocities[i + 1];
                            positions[i + 2] += velocities[i + 2];
                            
                            // Reset khi rơi quá thấp hoặc ra ngoài màn hình
                            if (positions[i + 1] < -30 || Math.abs(positions[i]) > 60) {
                                const angle = Math.random() * Math.PI * 1.0 - Math.PI * 0.5;
                                positions[i] = (Math.random() - 0.5) * 100;
                                positions[i + 1] = Math.random() * 50 + 40;
                                positions[i + 2] = (Math.random() - 0.5) * 40;
                                
                                const speed = Math.random() * 0.2 + 0.15;
                                velocities[i] = Math.sin(angle) * speed;
                                velocities[i + 1] = -speed * 1.2;
                                velocities[i + 2] = Math.cos(angle) * speed * 0.5;
                            }
                        }
                        particlesGeometry.attributes.position.needsUpdate = true;
                    } else if (isAnimeTheme) {
                        particlesMesh.rotation.y += 0.0002;
                        particlesMesh.rotation.x += 0.0001;
                        particlesMesh.rotation.z += 0.00015;
                        
                        // Di chuyển sakura rơi - tối ưu
                        const positions = particlesGeometry.attributes.position.array;
                        const velocities = particlesGeometry.attributes.aVelocity.array;
                        // Giảm số lần tính toán sin
                        const sinValue = Math.sin(time * 0.5);
                        for (let i = 0; i < positions.length; i += 3) {
                            positions[i] += velocities[i] + sinValue * 0.008; // Giảm tính toán
                            positions[i + 1] += velocities[i + 1];
                            positions[i + 2] += velocities[i + 2];
                            
                            if (positions[i + 1] < -15) {
                                positions[i + 1] = 30;
                                positions[i] = (Math.random() - 0.5) * 60;
                                positions[i + 2] = (Math.random() - 0.5) * 20;
                            }
                        }
                        particlesGeometry.attributes.position.needsUpdate = true;
                    } else {
                        particlesMesh.rotation.y += 0.001;
                        particlesMesh.rotation.x += 0.0005;
                    }
                }

                // Update shapes - Tối ưu: chỉ update mỗi frame nhất định
                if (shapes && Array.isArray(shapes) && shapes.length > 0 && Math.floor(time * 10) % 2 === 0) {
                    shapes.forEach((shape, idx) => {
                        if (shape && shape.rotation && shape.position && shape.userData) {
                            const userData = shape.userData;
                            
                            if (userData.isAnime) {
                                // Hiệu ứng anime - giảm tính toán
                                shape.rotation.x += userData.rotationSpeed.x;
                                shape.rotation.y += userData.rotationSpeed.y;
                                shape.rotation.z += userData.rotationSpeed.z;
                                
                                userData.orbitAngle += userData.orbitSpeed;
                                const cosAngle = Math.cos(userData.orbitAngle);
                                const sinAngle = Math.sin(userData.orbitAngle);
                                shape.position.x = cosAngle * userData.orbitRadius + Math.sin(time * 0.3) * 0.4;
                                shape.position.z = sinAngle * userData.orbitRadius + Math.cos(time * 0.3) * 0.4;
                                shape.position.y = userData.originalY + Math.sin(time * userData.floatSpeed) * userData.floatAmount;
                                
                                const pulse = Math.sin(time * 1.5) * 0.12 + 1;
                                shape.scale.set(pulse, pulse, pulse);
                                
                                if (shape.material.emissiveIntensity !== undefined) {
                                    shape.material.emissiveIntensity = 0.3 + Math.sin(time * 1.2) * 0.25;
                                }
                            } else {
                                // Hiệu ứng theme thường - đơn giản hơn
                                shape.rotation.x += 0.008 * (idx % 3 + 1);
                                shape.rotation.y += 0.008 * (idx % 2 + 1);
                                shape.position.y += Math.sin(time * 10 + idx) * 0.0008;
                            }
                        }
                    });
                }
                
                // Di chuyển camera
                if (isApocalypseTheme && camera) {
                    // Camera rung nhẹ như động đất
                    camera.position.x = Math.sin(time * 0.15) * 0.5;
                    camera.position.y = 5 + Math.cos(time * 0.18) * 0.3;
                    camera.lookAt(Math.sin(time * 0.15) * 0.2, Math.cos(time * 0.18) * 0.1, 0);
                } else if (isShootingStarTheme && camera) {
                    // Camera ổn định để xem sao băng rơi
                    camera.position.x = Math.sin(time * 0.05) * 1;
                    camera.position.y = 5 + Math.cos(time * 0.08) * 0.5;
                    camera.lookAt(0, 0, 0);
                } else if (isAnimeTheme && camera) {
                    camera.position.x = Math.sin(time * 0.08) * 1.5;
                    camera.position.y = 3 + Math.cos(time * 0.12) * 0.8;
                    camera.lookAt(Math.sin(time * 0.08) * 0.5, Math.cos(time * 0.12) * 0.3, 0);
                }
                
                // Di chuyển ánh sáng
                if (isApocalypseTheme) {
                    // Ánh sáng lửa nhấp nháy như lửa cháy
                    const lights = scene.children.filter(child => child instanceof THREE.PointLight);
                    if (lights[0]) {
                        lights[0].position.x = 10 + Math.sin(time * 0.4) * 3;
                        lights[0].position.y = 8 + Math.cos(time * 0.5) * 2;
                        lights[0].intensity = 2.5 + Math.sin(time * 2) * 0.5; // Nhấp nháy
                    }
                    if (lights[1]) {
                        lights[1].position.x = -10 + Math.cos(time * 0.45) * 3;
                        lights[1].position.y = 6 + Math.sin(time * 0.55) * 2;
                        lights[1].intensity = 2 + Math.cos(time * 1.8) * 0.4;
                    }
                    if (lights[2]) {
                        lights[2].position.y = -5 + Math.sin(time * 0.6) * 2; // Lửa từ dưới
                        lights[2].intensity = 1.8 + Math.sin(time * 2.2) * 0.3;
                    }
                } else if (isShootingStarTheme) {
                    // Di chuyển ánh sáng cho sao băng
                    const lights = scene.children.filter(child => child instanceof THREE.PointLight);
                    if (lights[0]) {
                        lights[0].position.x = 10 + Math.sin(time * 0.3) * 5;
                        lights[0].position.y = 10 + Math.cos(time * 0.4) * 4;
                    }
                    if (lights[1]) {
                        lights[1].position.x = -10 + Math.cos(time * 0.35) * 4;
                        lights[1].position.y = 8 + Math.sin(time * 0.45) * 3;
                    }
                }

                // ALWAYS render - even when canvas is hidden, keep it ready
                // CSS opacity will handle visibility, but rendering continues
                if (renderer && scene && camera && canvas) {
                    try {
                        // Always render regardless of visibility
                        // Browser will handle optimization if needed
                        renderer.render(scene, camera);
                    } catch (error) {
                        // Only log non-context errors (context loss is normal)
                        if (!error.message || !error.message.includes('context')) {
                            console.warn('Render issue for theme', index);
                        }
                    }
                }
            }

            // Start animation immediately - it runs continuously for all themes
            animate();
            
            console.log('✓ Three.js initialized for theme', index, '- Particles:', particleCount, 'Shapes:', shapeCount);
        }

        // Global resize handler for all renderers
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                renderers.forEach((renderer, idx) => {
                    if (renderer && cameras[idx]) {
                        cameras[idx].aspect = window.innerWidth / window.innerHeight;
                        cameras[idx].updateProjectionMatrix();
                        renderer.setSize(window.innerWidth, window.innerHeight);
                    }
                });
            }, 100);
        });

        // Initialize Three.js for all themes
        function initAllThreeJS() {
            console.log('Initializing Three.js for', themes.length, 'themes');
            if (!themes || themes.length === 0) {
                console.error('No themes found!');
                return;
            }
            
            themes.forEach((theme, index) => {
                setTimeout(() => {
                    console.log('Initializing Three.js for theme', index, theme.name);
                    try {
                        initThreeJS(index);
                        // Verify canvas exists and is visible
                        const canvas = document.getElementById('threejs-canvas-' + index);
                        if (canvas) {
                            console.log('✓ Canvas created for theme', index);
                            // Force canvas to be visible initially if it's the first theme
                            if (index === 0) {
                                canvas.classList.add('active');
                                canvas.classList.remove('hidden');
                            }
                        } else {
                            console.error('✗ Canvas NOT found for theme', index);
                        }
                    } catch (error) {
                        console.error('Error initializing Three.js for theme', index, ':', error);
                    }
                }, index * 150); // Increased delay to prevent conflicts
            });
        }

        // Store original showTheme function before modifying
        const originalShowTheme = showTheme;
        
        // Override showTheme to properly handle canvas visibility
        showTheme = function(index) {
            // Call original function to update UI
            originalShowTheme(index);
            
            // Update canvas visibility - use parent container classes
            document.querySelectorAll('.theme-preview').forEach((preview, idx) => {
                const canvas = preview.querySelector('.threejs-canvas');
                if (canvas) {
                    if (idx === index) {
                        // Show canvas for active theme
                        preview.classList.add('active');
                        preview.classList.remove('hidden');
                        canvas.classList.add('active');
                        canvas.classList.remove('hidden');
                        canvas.style.opacity = '1';
                        console.log('✓ Showing Three.js for theme', index, '- Canvas:', canvas.id);
                    } else {
                        // Hide canvas for other themes (but keep rendering)
                        preview.classList.remove('active');
                        preview.classList.add('hidden');
                        canvas.classList.remove('active');
                        canvas.classList.add('hidden');
                        canvas.style.opacity = '0';
                        // CRITICAL: Keep visibility and display so Three.js continues rendering
                        canvas.style.visibility = 'visible';
                        canvas.style.display = 'block';
                        canvas.style.zIndex = '1';
                        // Also ensure parent stays visible for rendering
                        preview.style.visibility = 'visible';
                        preview.style.display = 'block';
                    }
                } else {
                    console.warn('⚠ Canvas not found for theme', idx);
                }
            });
        };

        // Initialize Three.js after DOM is loaded and themes are ready
        document.addEventListener('DOMContentLoaded', function() {
            // Wait a bit to ensure all HTML is rendered
            setTimeout(() => {
                console.log('DOM loaded, initializing Three.js for', themes.length, 'themes...');
                
                // Verify all canvas elements exist before initialization
                let allCanvasesExist = true;
                themes.forEach((theme, index) => {
                    const canvas = document.getElementById('threejs-canvas-' + index);
                    if (!canvas) {
                        console.error('✗ Canvas', index, 'NOT found in DOM!');
                        allCanvasesExist = false;
                    } else {
                        console.log('✓ Canvas', index, 'found:', canvas.id);
                    }
                });
                
                if (allCanvasesExist) {
                    console.log('All canvases found, initializing Three.js...');
                    initAllThreeJS();
                    
                    // Verify all renderers were created
                    setTimeout(() => {
                        console.log('Verifying Three.js initialization...');
                        themes.forEach((theme, index) => {
                            const canvas = document.getElementById('threejs-canvas-' + index);
                            const renderer = renderers[index];
                            if (canvas && renderer) {
                                console.log('✓ Theme', index, ':', theme.name, '- Renderer OK, Canvas:', canvas.id);
                            } else {
                                console.error('✗ Theme', index, ':', theme.name, '- Renderer:', !!renderer, 'Canvas:', !!canvas);
                            }
                        });
                    }, 2000);
                } else {
                    console.error('Some canvases are missing! Cannot initialize Three.js.');
                }
            }, 200);
        });
        
        // Also check if Three.js library is loaded
        if (typeof THREE === 'undefined') {
            console.error('Three.js library not loaded!');
        } else {
            console.log('Three.js library loaded:', THREE.REVISION);
        }
    </script>
</body>
</html>

