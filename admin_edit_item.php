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

// Load admin helper
require_once 'admin_helper.php';

$userId = $_SESSION['Iduser'];

// Kiểm tra quyền admin (Role = 1)
if (!isAdmin($conn, $userId)) {
    die("Bạn không có quyền truy cập trang này! Chỉ admin (Role = 1) mới có thể truy cập.");
}

$message = '';
$messageType = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if (!in_array($type, ['cursor', 'achievement', 'theme']) || $id <= 0) {
    die("Yêu cầu không hợp lệ hoặc vật phẩm không tồn tại!");
}

// Lấy thông tin vật phẩm hiện tại
$item = null;
if ($type === 'cursor') {
    $stmt = $conn->prepare("SELECT * FROM cursors WHERE id = ?");
} elseif ($type === 'achievement') {
    $stmt = $conn->prepare("SELECT * FROM achievements WHERE id = ?");
} elseif ($type === 'theme') {
    $stmt = $conn->prepare("SELECT * FROM themes WHERE id = ?");
}

if ($stmt) {
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$item) {
    die("Không tìm thấy vật phẩm cần chỉnh sửa!");
}

// Xử lý cập nhật khi POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF Token verification failed.");
    }

    if ($type === 'cursor' && isset($_POST['update_cursor'])) {
        $name = trim($_POST['cursor_name']);
        $description = trim($_POST['cursor_description']);
        $price = (float) $_POST['cursor_price'];
        $cursor_image = trim($_POST['cursor_image']);
        $is_premium = isset($_POST['cursor_premium']) ? 1 : 0;

        if (!empty($name) && !empty($cursor_image)) {
            $updateSql = "UPDATE cursors SET name = ?, description = ?, price = ?, cursor_image = ?, is_premium = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            if ($updateStmt) {
                $updateStmt->bind_param("ssdsii", $name, $description, $price, $cursor_image, $is_premium, $id);
                if ($updateStmt->execute()) {
                    $message = '✅ Cập nhật cursor thành công!';
                    $messageType = 'success';
                    // Cập nhật lại dữ liệu hiển thị
                    $item['name'] = $name;
                    $item['description'] = $description;
                    $item['price'] = $price;
                    $item['cursor_image'] = $cursor_image;
                    $item['is_premium'] = $is_premium;
                } else {
                    $message = '❌ Lỗi khi cập nhật cursor: ' . $updateStmt->error;
                    $messageType = 'error';
                }
                $updateStmt->close();
            }
        } else {
            $message = '❌ Vui lòng điền đầy đủ thông tin!';
            $messageType = 'error';
        }
    }

    elseif ($type === 'theme' && isset($_POST['update_theme'])) {
        $name = trim($_POST['theme_name']);
        $description = trim($_POST['theme_description']);
        $price = (float) $_POST['theme_price'];
        $preview_image = trim($_POST['theme_preview']);
        $is_premium = isset($_POST['theme_premium']) ? 1 : 0;

        $particle_count = (int) $_POST['particle_count'];
        $particle_size = (float) $_POST['particle_size'];
        $particle_color = trim($_POST['particle_color']);
        $particle_opacity = (float) $_POST['particle_opacity'];
        $shape_count = (int) $_POST['shape_count'];
        $shape_colors = trim($_POST['shape_colors']);
        $shape_opacity = (float) $_POST['shape_opacity'];
        $background_gradient = trim($_POST['background_gradient']);

        // Validate JSON
        $shapeColorsValid = json_decode($shape_colors) !== null;
        $bgGradientValid = json_decode($background_gradient) !== null;

        if (!empty($name) && $shapeColorsValid && $bgGradientValid) {
            $updateSql = "UPDATE themes SET name = ?, description = ?, price = ?, preview_image = ?, is_premium = ?, 
                          particle_count = ?, particle_size = ?, particle_color = ?, particle_opacity = ?,
                          shape_count = ?, shape_colors = ?, shape_opacity = ?, background_gradient = ? 
                          WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            if ($updateStmt) {
                $updateStmt->bind_param(
                    "ssdsiiidsdssi",
                    $name,
                    $description,
                    $price,
                    $preview_image,
                    $is_premium,
                    $particle_count,
                    $particle_size,
                    $particle_color,
                    $particle_opacity,
                    $shape_count,
                    $shape_colors,
                    $shape_opacity,
                    $background_gradient,
                    $id
                );
                if ($updateStmt->execute()) {
                    $message = '✅ Cập nhật theme thành công!';
                    $messageType = 'success';
                    // Cập nhật lại dữ liệu hiển thị
                    $item['name'] = $name;
                    $item['description'] = $description;
                    $item['price'] = $price;
                    $item['preview_image'] = $preview_image;
                    $item['is_premium'] = $is_premium;
                    $item['particle_count'] = $particle_count;
                    $item['particle_size'] = $particle_size;
                    $item['particle_color'] = $particle_color;
                    $item['particle_opacity'] = $particle_opacity;
                    $item['shape_count'] = $shape_count;
                    $item['shape_colors'] = $shape_colors;
                    $item['shape_opacity'] = $shape_opacity;
                    $item['background_gradient'] = $background_gradient;
                } else {
                    $message = '❌ Lỗi khi cập nhật theme: ' . $updateStmt->error;
                    $messageType = 'error';
                }
                $updateStmt->close();
            }
        } else {
            $message = '❌ Vui lòng điền đầy đủ thông tin và kiểm tra các chuỗi JSON hợp lệ!';
            $messageType = 'error';
        }
    }

    elseif ($type === 'achievement' && isset($_POST['update_achievement'])) {
        $name = trim($_POST['achievement_name']);
        $description = trim($_POST['achievement_description']);
        $icon = trim($_POST['achievement_icon']);
        $requirement_type = trim($_POST['achievement_type']);
        $requirement_value = (float) $_POST['achievement_value'];
        $reward_money = (float) $_POST['achievement_reward'];
        $rarity = trim($_POST['achievement_rarity']);

        if (!empty($name) && !empty($requirement_type)) {
            $updateSql = "UPDATE achievements SET name = ?, description = ?, icon = ?, requirement_type = ?, requirement_value = ?, reward_money = ?, rarity = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            if ($updateStmt) {
                $updateStmt->bind_param("ssssddsi", $name, $description, $icon, $requirement_type, $requirement_value, $reward_money, $rarity, $id);
                if ($updateStmt->execute()) {
                    $message = '✅ Cập nhật achievement thành công!';
                    $messageType = 'success';
                    // Cập nhật lại dữ liệu hiển thị
                    $item['name'] = $name;
                    $item['description'] = $description;
                    $item['icon'] = $icon;
                    $item['requirement_type'] = $requirement_type;
                    $item['requirement_value'] = $requirement_value;
                    $item['reward_money'] = $reward_money;
                    $item['rarity'] = $rarity;
                } else {
                    $message = '❌ Lỗi khi cập nhật achievement: ' . $updateStmt->error;
                    $messageType = 'error';
                }
                $updateStmt->close();
            }
        } else {
            $message = '❌ Vui lòng điền đầy đủ thông tin!';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Chỉnh Sửa Vật Phẩm</title>
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
            max-width: 900px;
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

        .form-content {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.5s ease;
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
            cursor: pointer !important;
        }

        .submit-button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 18px;
            font-weight: 600;
            cursor: pointer !important;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }

        .submit-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(52, 152, 219, 0.6);
        }

        .message {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-weight: 600;
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

        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .help-text {
            font-size: 14px;
            color: var(--text-dark);
            opacity: 0.7;
            margin-top: 5px;
        }

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
    <canvas id="threejs-background"></canvas>

    <div class="admin-container">
        <div class="header-admin">
            <h1>⚙️ Chỉnh Sửa <?= ucfirst($type) ?></h1>
            <p>Đang sửa vật phẩm ID: <?= htmlspecialchars($id) ?></p>
        </div>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="form-content">
            
            <!-- FORM CHO CURSOR -->
            <?php if ($type === 'cursor'): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group">
                        <label for="cursor_name">Tên cursor *</label>
                        <input type="text" id="cursor_name" name="cursor_name" value="<?= htmlspecialchars($item['name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="cursor_description">Mô tả</label>
                        <textarea id="cursor_description" name="cursor_description"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="cursor_price">Giá (gtlm) *</label>
                        <input type="number" id="cursor_price" name="cursor_price" min="0" step="1000" value="<?= (int)$item['price'] ?>" required>
                        <div class="help-text">Nhập 0 nếu miễn phí</div>
                    </div>

                    <div class="form-group">
                        <label for="cursor_image">Đường dẫn ảnh cursor *</label>
                        <input type="text" id="cursor_image" name="cursor_image" value="<?= htmlspecialchars($item['cursor_image']) ?>" required>
                        <div class="help-text">Đường dẫn tương đối từ thư mục gốc</div>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="cursor_premium" value="1" <?= $item['is_premium'] ? 'checked' : '' ?>>
                            Premium (có phí)
                        </label>
                    </div>

                    <button type="submit" name="update_cursor" class="submit-button">💾 Lưu Thay Đổi</button>
                </form>
            <?php endif; ?>

            <!-- FORM CHO THEME -->
            <?php if ($type === 'theme'): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group">
                        <label for="theme_name">Tên theme *</label>
                        <input type="text" id="theme_name" name="theme_name" value="<?= htmlspecialchars($item['name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="theme_description">Mô tả</label>
                        <textarea id="theme_description" name="theme_description"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="theme_price">Giá (gtlm) *</label>
                        <input type="number" id="theme_price" name="theme_price" min="0" step="1000" value="<?= (int)$item['price'] ?>" required>
                        <div class="help-text">Nhập 0 nếu miễn phí</div>
                    </div>

                    <div class="form-group">
                        <label for="theme_preview">Đường dẫn ảnh preview</label>
                        <input type="text" id="theme_preview" name="theme_preview" value="<?= htmlspecialchars($item['preview_image'] ?? '') ?>">
                    </div>

                    <h3 style="margin: 30px 0 15px 0; color: var(--primary-color);">⚙️ Cấu hình Three.js Background</h3>

                    <div class="form-group">
                        <label for="particle_count">Số lượng particles *</label>
                        <input type="number" id="particle_count" name="particle_count" min="100" max="5000" step="100" value="<?= (int)($item['particle_count'] ?? 1000) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="particle_size">Kích thước particle *</label>
                        <input type="number" id="particle_size" name="particle_size" min="0.01" max="1" step="0.01" value="<?= (float)($item['particle_size'] ?? 0.05) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="particle_color">Màu particle *</label>
                        <input type="color" id="particle_color" name="particle_color" value="<?= htmlspecialchars($item['particle_color'] ?? '#ffffff') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="particle_opacity">Độ trong suốt particle *</label>
                        <input type="number" id="particle_opacity" name="particle_opacity" min="0" max="1" step="0.1" value="<?= (float)($item['particle_opacity'] ?? 0.6) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="shape_count">Số lượng hình 3D *</label>
                        <input type="number" id="shape_count" name="shape_count" min="5" max="50" step="1" value="<?= (int)($item['shape_count'] ?? 15) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="shape_colors">Màu hình 3D (JSON) *</label>
                        <input type="text" id="shape_colors" name="shape_colors" value='<?= htmlspecialchars($item['shape_colors'] ?? '["#667eea", "#764ba2", "#4facfe", "#00f2fe"]') ?>' required>
                        <div class="help-text">Mảng JSON các màu hex</div>
                    </div>

                    <div class="form-group">
                        <label for="shape_opacity">Độ trong suốt hình 3D *</label>
                        <input type="number" id="shape_opacity" name="shape_opacity" min="0" max="1" step="0.1" value="<?= (float)($item['shape_opacity'] ?? 0.3) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="background_gradient">Background gradient (JSON) *</label>
                        <input type="text" id="background_gradient" name="background_gradient" value='<?= htmlspecialchars($item['background_gradient'] ?? '["#667eea", "#764ba2", "#4facfe"]') ?>' required>
                        <div class="help-text">Mảng JSON 2-3 màu cho gradient</div>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="theme_premium" value="1" <?= $item['is_premium'] ? 'checked' : '' ?>>
                            Premium (có phí)
                        </label>
                    </div>

                    <button type="submit" name="update_theme" class="submit-button">💾 Lưu Thay Đổi</button>
                </form>
            <?php endif; ?>

            <!-- FORM CHO ACHIEVEMENT -->
            <?php if ($type === 'achievement'): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group">
                        <label for="achievement_name">Tên achievement *</label>
                        <input type="text" id="achievement_name" name="achievement_name" value="<?= htmlspecialchars($item['name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="achievement_description">Mô tả</label>
                        <textarea id="achievement_description" name="achievement_description"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="achievement_icon">Icon (Emoji) *</label>
                        <input type="text" id="achievement_icon" name="achievement_icon" value="<?= htmlspecialchars($item['icon']) ?>" required maxlength="2">
                    </div>

                    <div class="form-group">
                        <label for="achievement_type">Loại yêu cầu *</label>
                        <select id="achievement_type" name="achievement_type" required>
                            <option value="money" <?= $item['requirement_type'] === 'money' ? 'selected' : '' ?>>Số gtlm (money)</option>
                            <option value="games_played" <?= $item['requirement_type'] === 'games_played' ? 'selected' : '' ?>>Số game đã chơi (games_played)</option>
                            <option value="big_win" <?= $item['requirement_type'] === 'big_win' ? 'selected' : '' ?>>Thắng lớn (big_win)</option>
                            <option value="streak" <?= $item['requirement_type'] === 'streak' ? 'selected' : '' ?>>Chuỗi thắng (streak)</option>
                            <option value="rank" <?= $item['requirement_type'] === 'rank' ? 'selected' : '' ?>>Xếp hạng (rank)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="achievement_value">Giá trị yêu cầu *</label>
                        <input type="number" id="achievement_value" name="achievement_value" min="0" step="0.01" value="<?= $item['requirement_value'] ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="achievement_reward">Phần thưởng (gtlm)</label>
                        <input type="number" id="achievement_reward" name="achievement_reward" min="0" step="1000" value="<?= (int)$item['reward_money'] ?>">
                    </div>

                    <div class="form-group">
                        <label for="achievement_rarity">Độ hiếm *</label>
                        <select id="achievement_rarity" name="achievement_rarity" required>
                            <option value="common" <?= $item['rarity'] === 'common' ? 'selected' : '' ?>>Common (Thường)</option>
                            <option value="rare" <?= $item['rarity'] === 'rare' ? 'selected' : '' ?>>Rare (Hiếm)</option>
                            <option value="epic" <?= $item['rarity'] === 'epic' ? 'selected' : '' ?>>Epic (Cực hiếm)</option>
                            <option value="legendary" <?= $item['rarity'] === 'legendary' ? 'selected' : '' ?>>Legendary (Huyền thoại)</option>
                        </select>
                    </div>

                    <button type="submit" name="update_achievement" class="submit-button">💾 Lưu Thay Đổi</button>
                </form>
            <?php endif; ?>

        </div>

        <a href="admin_manage_items.php" class="back-link">⬅️ Quay Lại Quản Lý</a>
        <a href="index.php" class="back-link" style="margin-left: 10px;">🏠 Về Trang Chủ</a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";

            const interactiveElements = document.querySelectorAll('button, a, input, label, select');
            interactiveElements.forEach(el => {
                if (el.type !== 'text' && el.type !== 'number' && el.tagName !== 'TEXTAREA') {
                    el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                }
            });
        });
    </script>

    <script>
        // Initialize Three.js Background
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
            const script = document.createElement('script');
            script.src = 'threejs-background.js';
            document.head.appendChild(script);
        })();
    </script>
</body>
</html>
