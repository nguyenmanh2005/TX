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

$message = '';
$messageType = '';

// Xử lý thêm cursor
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_cursor'])) {
    $name = trim($_POST['cursor_name']);
    $description = trim($_POST['cursor_description']);
    $price = (float)$_POST['cursor_price'];
    $cursor_image = trim($_POST['cursor_image']);
    $is_premium = isset($_POST['cursor_premium']) ? 1 : 0;
    
    if (!empty($name) && !empty($cursor_image)) {
        $insertSql = "INSERT INTO cursors (name, description, price, cursor_image, is_premium) VALUES (?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        if ($insertStmt) {
            $insertStmt->bind_param("ssdsi", $name, $description, $price, $cursor_image, $is_premium);
            if ($insertStmt->execute()) {
                $message = '✅ Thêm cursor thành công!';
                $messageType = 'success';
            } else {
                $message = '❌ Lỗi khi thêm cursor: ' . $insertStmt->error;
                $messageType = 'error';
            }
            $insertStmt->close();
        }
    } else {
        $message = '❌ Vui lòng điền đầy đủ thông tin!';
        $messageType = 'error';
    }
}

// Xử lý thêm theme
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_theme'])) {
    $name = trim($_POST['theme_name']);
    $description = trim($_POST['theme_description']);
    $price = (float)$_POST['theme_price'];
    $preview_image = trim($_POST['theme_preview']);
    $is_premium = isset($_POST['theme_premium']) ? 1 : 0;
    
    // Three.js config
    $particle_count = (int)$_POST['particle_count'];
    $particle_size = (float)$_POST['particle_size'];
    $particle_color = trim($_POST['particle_color']);
    $particle_opacity = (float)$_POST['particle_opacity'];
    $shape_count = (int)$_POST['shape_count'];
    $shape_colors = trim($_POST['shape_colors']);
    $shape_opacity = (float)$_POST['shape_opacity'];
    $background_gradient = trim($_POST['background_gradient']);
    
    // Validate JSON
    $shapeColorsValid = json_decode($shape_colors) !== null;
    $bgGradientValid = json_decode($background_gradient) !== null;
    
    if (!empty($name) && $shapeColorsValid && $bgGradientValid) {
        $insertSql = "INSERT INTO themes (name, description, price, preview_image, is_premium, 
                      particle_count, particle_size, particle_color, particle_opacity,
                      shape_count, shape_colors, shape_opacity, background_gradient) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        if ($insertStmt) {
            $insertStmt->bind_param("ssdsiiidsdss", $name, $description, $price, $preview_image, $is_premium,
                $particle_count, $particle_size, $particle_color, $particle_opacity,
                $shape_count, $shape_colors, $shape_opacity, $background_gradient);
            if ($insertStmt->execute()) {
                $message = '✅ Thêm theme thành công!';
                $messageType = 'success';
            } else {
                $message = '❌ Lỗi khi thêm theme: ' . $insertStmt->error;
                $messageType = 'error';
            }
            $insertStmt->close();
        }
    } else {
        $message = '❌ Vui lòng điền đầy đủ thông tin và kiểm tra JSON hợp lệ!';
        $messageType = 'error';
    }
}

// Xử lý thêm achievement
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_achievement'])) {
    $name = trim($_POST['achievement_name']);
    $description = trim($_POST['achievement_description']);
    $icon = trim($_POST['achievement_icon']);
    $requirement_type = trim($_POST['achievement_type']);
    $requirement_value = (float)$_POST['achievement_value'];
    $reward_money = (float)$_POST['achievement_reward'];
    $rarity = trim($_POST['achievement_rarity']);
    
    if (!empty($name) && !empty($requirement_type)) {
        $insertSql = "INSERT INTO achievements (name, description, icon, requirement_type, requirement_value, reward_money, rarity) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        if ($insertStmt) {
            $insertStmt->bind_param("ssssdds", $name, $description, $icon, $requirement_type, $requirement_value, $reward_money, $rarity);
            if ($insertStmt->execute()) {
                $message = '✅ Thêm achievement thành công!';
                $messageType = 'success';
            } else {
                $message = '❌ Lỗi khi thêm achievement: ' . $insertStmt->error;
                $messageType = 'error';
            }
            $insertStmt->close();
        }
    } else {
        $message = '❌ Vui lòng điền đầy đủ thông tin!';
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Thêm Items</title>
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
        
        input[type="text"], input[type="number"], textarea {
            cursor: text !important;
        }
        
        .admin-container {
            max-width: 1200px;
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
        
        .tabs {
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
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.5s ease;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
        
        .form-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
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
            <h1>⚙️ Admin - Thêm Items</h1>
            <p>Thêm cursor, theme và achievement mới vào hệ thống</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab-button active" onclick="switchTab('cursor')">🖱️ Thêm Cursor</button>
            <button class="tab-button" onclick="switchTab('theme')">🎨 Thêm Theme</button>
            <button class="tab-button" onclick="switchTab('achievement')">🏆 Thêm Achievement</button>
        </div>
        
        <!-- Tab Thêm Cursor -->
        <div id="cursor-tab" class="tab-content active">
            <h2>🖱️ Thêm Cursor Mới</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="cursor_name">Tên cursor *</label>
                    <input type="text" id="cursor_name" name="cursor_name" required placeholder="Ví dụ: Magic Wand">
                </div>
                
                <div class="form-group">
                    <label for="cursor_description">Mô tả</label>
                    <textarea id="cursor_description" name="cursor_description" placeholder="Mô tả về cursor này..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="cursor_price">Giá (VNĐ) *</label>
                    <input type="number" id="cursor_price" name="cursor_price" min="0" step="1000" value="0" required>
                    <div class="help-text">Nhập 0 nếu miễn phí</div>
                </div>
                
                <div class="form-group">
                    <label for="cursor_image">Đường dẫn ảnh cursor *</label>
                    <input type="text" id="cursor_image" name="cursor_image" required placeholder="Ví dụ: cursors/sword.png">
                    <div class="help-text">Đường dẫn tương đối từ thư mục gốc</div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="cursor_premium" value="1">
                        Premium (có phí)
                    </label>
                </div>
                
                <button type="submit" name="add_cursor" class="submit-button">➕ Thêm Cursor</button>
            </form>
        </div>
        
        <!-- Tab Thêm Theme -->
        <div id="theme-tab" class="tab-content">
            <h2>🎨 Thêm Theme Mới (Three.js Background)</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="theme_name">Tên theme *</label>
                    <input type="text" id="theme_name" name="theme_name" required placeholder="Ví dụ: Dark Mode">
                </div>
                
                <div class="form-group">
                    <label for="theme_description">Mô tả</label>
                    <textarea id="theme_description" name="theme_description" placeholder="Mô tả về theme này..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="theme_price">Giá (VNĐ) *</label>
                    <input type="number" id="theme_price" name="theme_price" min="0" step="1000" value="0" required>
                    <div class="help-text">Nhập 0 nếu miễn phí</div>
                </div>
                
                <div class="form-group">
                    <label for="theme_preview">Đường dẫn ảnh preview</label>
                    <input type="text" id="theme_preview" name="theme_preview" placeholder="Ví dụ: themes/dark.jpg">
                </div>
                
                <h3 style="margin-top: 30px; color: var(--primary-color);">⚙️ Cấu hình Three.js Background</h3>
                
                <div class="form-group">
                    <label for="particle_count">Số lượng particles *</label>
                    <input type="number" id="particle_count" name="particle_count" min="100" max="5000" step="100" value="1000" required>
                    <div class="help-text">Số lượng hạt trong background (100-5000)</div>
                </div>
                
                <div class="form-group">
                    <label for="particle_size">Kích thước particle *</label>
                    <input type="number" id="particle_size" name="particle_size" min="0.01" max="1" step="0.01" value="0.05" required>
                    <div class="help-text">Kích thước mỗi hạt (0.01-1)</div>
                </div>
                
                <div class="form-group">
                    <label for="particle_color">Màu particle *</label>
                    <input type="color" id="particle_color" name="particle_color" value="#ffffff" required>
                    <div class="help-text">Màu của các hạt</div>
                </div>
                
                <div class="form-group">
                    <label for="particle_opacity">Độ trong suốt particle *</label>
                    <input type="number" id="particle_opacity" name="particle_opacity" min="0" max="1" step="0.1" value="0.6" required>
                    <div class="help-text">Độ trong suốt (0-1)</div>
                </div>
                
                <div class="form-group">
                    <label for="shape_count">Số lượng hình 3D *</label>
                    <input type="number" id="shape_count" name="shape_count" min="5" max="50" step="1" value="15" required>
                    <div class="help-text">Số lượng hình dạng 3D (5-50)</div>
                </div>
                
                <div class="form-group">
                    <label for="shape_colors">Màu hình 3D (JSON) *</label>
                    <input type="text" id="shape_colors" name="shape_colors" value='["#667eea", "#764ba2", "#4facfe", "#00f2fe"]' required>
                    <div class="help-text">Mảng JSON các màu hex, ví dụ: ["#667eea", "#764ba2", "#4facfe"]</div>
                </div>
                
                <div class="form-group">
                    <label for="shape_opacity">Độ trong suốt hình 3D *</label>
                    <input type="number" id="shape_opacity" name="shape_opacity" min="0" max="1" step="0.1" value="0.3" required>
                    <div class="help-text">Độ trong suốt (0-1)</div>
                </div>
                
                <div class="form-group">
                    <label for="background_gradient">Background gradient (JSON) *</label>
                    <input type="text" id="background_gradient" name="background_gradient" value='["#667eea", "#764ba2", "#4facfe"]' required>
                    <div class="help-text">Mảng JSON 2-3 màu cho gradient, ví dụ: ["#667eea", "#764ba2", "#4facfe"]</div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="theme_premium" value="1">
                        Premium (có phí)
                    </label>
                </div>
                
                <button type="submit" name="add_theme" class="submit-button">➕ Thêm Theme</button>
            </form>
        </div>
        
        <!-- Tab Thêm Achievement -->
        <div id="achievement-tab" class="tab-content">
            <h2>🏆 Thêm Achievement Mới</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="achievement_name">Tên achievement *</label>
                    <input type="text" id="achievement_name" name="achievement_name" required placeholder="Ví dụ: Top 1 Server">
                </div>
                
                <div class="form-group">
                    <label for="achievement_description">Mô tả</label>
                    <textarea id="achievement_description" name="achievement_description" placeholder="Mô tả về achievement này..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="achievement_icon">Icon (Emoji) *</label>
                    <input type="text" id="achievement_icon" name="achievement_icon" required placeholder="Ví dụ: 👑" maxlength="2">
                    <div class="help-text">Nhập 1-2 emoji</div>
                </div>
                
                <div class="form-group">
                    <label for="achievement_type">Loại yêu cầu *</label>
                    <select id="achievement_type" name="achievement_type" required>
                        <option value="">-- Chọn loại --</option>
                        <option value="money">Số tiền (money)</option>
                        <option value="games_played">Số game đã chơi (games_played)</option>
                        <option value="big_win">Thắng lớn (big_win)</option>
                        <option value="streak">Chuỗi thắng (streak)</option>
                        <option value="rank">Xếp hạng (rank)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="achievement_value">Giá trị yêu cầu *</label>
                    <input type="number" id="achievement_value" name="achievement_value" min="0" step="0.01" required placeholder="Ví dụ: 1000000">
                    <div class="help-text">Với rank: nhập số thứ hạng (1-10). Với money: nhập số tiền VNĐ</div>
                </div>
                
                <div class="form-group">
                    <label for="achievement_reward">Phần thưởng (VNĐ)</label>
                    <input type="number" id="achievement_reward" name="achievement_reward" min="0" step="1000" value="0" placeholder="0">
                    <div class="help-text">Số tiền thưởng khi đạt được achievement</div>
                </div>
                
                <div class="form-group">
                    <label for="achievement_rarity">Độ hiếm *</label>
                    <select id="achievement_rarity" name="achievement_rarity" required>
                        <option value="common">Common (Thường)</option>
                        <option value="rare">Rare (Hiếm)</option>
                        <option value="epic">Epic (Cực hiếm)</option>
                        <option value="legendary">Legendary (Huyền thoại)</option>
                    </select>
                </div>
                
                <button type="submit" name="add_achievement" class="submit-button">➕ Thêm Achievement</button>
            </form>
        </div>
        
        <a href="index.php" class="back-link">🏠 Về Trang Chủ</a>
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
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
    </script>
</body>
</html>

