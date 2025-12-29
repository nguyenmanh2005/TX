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

// Xử lý xóa cursor
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_cursor'])) {
    $cursorId = (int)$_POST['cursor_id'];
    $deleteSql = "DELETE FROM cursors WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    if ($deleteStmt) {
        $deleteStmt->bind_param("i", $cursorId);
        if ($deleteStmt->execute()) {
            $message = '✅ Xóa cursor thành công!';
            $messageType = 'success';
        } else {
            $message = '❌ Lỗi khi xóa cursor: ' . $deleteStmt->error;
            $messageType = 'error';
        }
        $deleteStmt->close();
    }
}

// Xử lý xóa achievement
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_achievement'])) {
    $achievementId = (int)$_POST['achievement_id'];
    $deleteSql = "DELETE FROM achievements WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    if ($deleteStmt) {
        $deleteStmt->bind_param("i", $achievementId);
        if ($deleteStmt->execute()) {
            $message = '✅ Xóa achievement thành công!';
            $messageType = 'success';
        } else {
            $message = '❌ Lỗi khi xóa achievement: ' . $deleteStmt->error;
            $messageType = 'error';
        }
        $deleteStmt->close();
    }
}

// Xử lý xóa theme
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_theme'])) {
    $themeId = (int)$_POST['theme_id'];
    $deleteSql = "DELETE FROM themes WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    if ($deleteStmt) {
        $deleteStmt->bind_param("i", $themeId);
        if ($deleteStmt->execute()) {
            $message = '✅ Xóa theme thành công!';
            $messageType = 'success';
        } else {
            $message = '❌ Lỗi khi xóa theme: ' . $deleteStmt->error;
            $messageType = 'error';
        }
        $deleteStmt->close();
    }
}

// Lấy danh sách cursors
$cursors = [];
$cursorsSql = "SELECT * FROM cursors ORDER BY id ASC";
$cursorsResult = $conn->query($cursorsSql);
if ($cursorsResult) {
    while ($row = $cursorsResult->fetch_assoc()) {
        $cursors[] = $row;
    }
}

// Lấy danh sách achievements
$achievements = [];
$achievementsSql = "SELECT * FROM achievements ORDER BY 
    CASE rarity 
        WHEN 'legendary' THEN 1 
        WHEN 'epic' THEN 2 
        WHEN 'rare' THEN 3 
        ELSE 4 
    END, id ASC";
$achievementsResult = $conn->query($achievementsSql);
if ($achievementsResult) {
    while ($row = $achievementsResult->fetch_assoc()) {
        $achievements[] = $row;
    }
}

// Lấy danh sách themes
$themes = [];
$themesSql = "SELECT * FROM themes ORDER BY id ASC";
$themesResult = $conn->query($themesSql);
if ($themesResult) {
    while ($row = $themesResult->fetch_assoc()) {
        $themes[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Quản Lý Items</title>
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
            max-width: 1400px;
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
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .items-table th,
        .items-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .items-table th {
            background: var(--primary-color);
            color: white;
            font-weight: 700;
        }
        
        .items-table tr:hover {
            background: rgba(0, 0, 0, 0.05);
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge.free {
            background: var(--success-color);
            color: white;
        }
        
        .badge.premium {
            background: var(--warning-color);
            color: white;
        }
        
        .badge.common {
            background: #95a5a6;
            color: white;
        }
        
        .badge.rare {
            background: #3498db;
            color: white;
        }
        
        .badge.epic {
            background: #9b59b6;
            color: white;
        }
        
        .badge.legendary {
            background: #f39c12;
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-edit, .btn-delete {
            padding: 8px 16px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 600;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: all 0.3s ease;
        }
        
        .btn-edit {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-edit:hover {
            background: var(--secondary-dark);
            transform: translateY(-2px);
        }
        
        .btn-delete {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-delete:hover {
            background: #c0392b;
            transform: translateY(-2px);
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
        
        .add-new-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--success-color) 0%, #27ae60 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .add-new-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.4);
        }
        
        .cursor-preview {
            width: 32px;
            height: 32px;
            object-fit: contain;
        }
        
        .confirm-delete {
            background: rgba(220, 53, 69, 0.1);
            border: 2px solid var(--danger-color);
            padding: 10px;
            border-radius: var(--border-radius);
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="header-admin">
            <h1>⚙️ Admin - Quản Lý Items</h1>
            <p>Quản lý cursors, achievements và themes</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab-button active" onclick="switchTab('cursors')">🖱️ Quản Lý Cursors</button>
            <button class="tab-button" onclick="switchTab('achievements')">🏆 Quản Lý Achievements</button>
            <button class="tab-button" onclick="switchTab('themes')">🎨 Quản Lý Themes</button>
        </div>
        
        <!-- Tab Quản Lý Cursors -->
        <div id="cursors-tab" class="tab-content active">
            <a href="admin_add_items.php" class="add-new-btn">➕ Thêm Cursor Mới</a>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên</th>
                        <th>Mô tả</th>
                        <th>Ảnh</th>
                        <th>Giá</th>
                        <th>Premium</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cursors)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                Chưa có cursor nào. <a href="admin_add_items.php">Thêm cursor mới</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cursors as $cursor): ?>
                            <tr>
                                <td><?= htmlspecialchars($cursor['id']) ?></td>
                                <td><strong><?= htmlspecialchars($cursor['name']) ?></strong></td>
                                <td><?= htmlspecialchars($cursor['description'] ?? '') ?></td>
                                <td>
                                    <img src="<?= htmlspecialchars($cursor['cursor_image']) ?>" 
                                         alt="<?= htmlspecialchars($cursor['name']) ?>" 
                                         class="cursor-preview"
                                         onerror="this.src='chuot.png'">
                                </td>
                                <td><?= number_format($cursor['price'], 0, ',', '.') ?> VNĐ</td>
                                <td>
                                    <span class="badge <?= $cursor['is_premium'] ? 'premium' : 'free' ?>">
                                        <?= $cursor['is_premium'] ? 'Premium' : 'Miễn phí' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="admin_edit_item.php?type=cursor&id=<?= $cursor['id'] ?>" class="btn-edit">✏️ Sửa</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Bạn có chắc muốn xóa cursor này?');">
                                            <input type="hidden" name="cursor_id" value="<?= $cursor['id'] ?>">
                                            <button type="submit" name="delete_cursor" class="btn-delete">🗑️ Xóa</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Tab Quản Lý Achievements -->
        <div id="achievements-tab" class="tab-content">
            <a href="admin_add_items.php" class="add-new-btn">➕ Thêm Achievement Mới</a>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Icon</th>
                        <th>Tên</th>
                        <th>Mô tả</th>
                        <th>Yêu cầu</th>
                        <th>Phần thưởng</th>
                        <th>Độ hiếm</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($achievements)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">
                                Chưa có achievement nào. <a href="admin_add_items.php">Thêm achievement mới</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($achievements as $achievement): ?>
                            <tr>
                                <td><?= htmlspecialchars($achievement['id']) ?></td>
                                <td style="font-size: 24px;"><?= htmlspecialchars($achievement['icon']) ?></td>
                                <td><strong><?= htmlspecialchars($achievement['name']) ?></strong></td>
                                <td><?= htmlspecialchars($achievement['description'] ?? '') ?></td>
                                <td>
                                    <?php
                                    $reqType = $achievement['requirement_type'];
                                    $reqValue = number_format($achievement['requirement_value'], 0, ',', '.');
                                    $reqLabels = [
                                        'money' => 'Tiền',
                                        'games_played' => 'Số game',
                                        'big_win' => 'Thắng lớn',
                                        'streak' => 'Chuỗi thắng',
                                        'rank' => 'Xếp hạng'
                                    ];
                                    echo ($reqLabels[$reqType] ?? $reqType) . ': ' . $reqValue;
                                    ?>
                                </td>
                                <td><?= number_format($achievement['reward_money'], 0, ',', '.') ?> VNĐ</td>
                                <td>
                                    <span class="badge <?= $achievement['rarity'] ?>">
                                        <?= ucfirst($achievement['rarity']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="admin_edit_item.php?type=achievement&id=<?= $achievement['id'] ?>" class="btn-edit">✏️ Sửa</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Bạn có chắc muốn xóa achievement này?');">
                                            <input type="hidden" name="achievement_id" value="<?= $achievement['id'] ?>">
                                            <button type="submit" name="delete_achievement" class="btn-delete">🗑️ Xóa</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Tab Quản Lý Themes -->
        <div id="themes-tab" class="tab-content">
            <a href="admin_add_items.php" class="add-new-btn">➕ Thêm Theme Mới</a>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên</th>
                        <th>Mô tả</th>
                        <th>Preview</th>
                        <th>Giá</th>
                        <th>Premium</th>
                        <th>Cấu hình</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($themes)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">
                                Chưa có theme nào. <a href="admin_add_items.php">Thêm theme mới</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($themes as $theme): ?>
                            <tr>
                                <td><?= htmlspecialchars($theme['id']) ?></td>
                                <td><strong><?= htmlspecialchars($theme['name']) ?></strong></td>
                                <td><?= htmlspecialchars($theme['description'] ?? '') ?></td>
                                <td>
                                    <?php
                                    $bgGradient = !empty($theme['background_gradient']) ? json_decode($theme['background_gradient'], true) : ['#667eea', '#764ba2', '#4facfe'];
                                    $gradient = 'linear-gradient(135deg, ' . htmlspecialchars($bgGradient[0]) . ' 0%, ' . htmlspecialchars($bgGradient[1]) . ' 50%, ' . htmlspecialchars($bgGradient[2] ?? $bgGradient[1]) . ' 100%)';
                                    ?>
                                    <div style="width: 100px; height: 60px; background: <?= $gradient ?>; border-radius: var(--border-radius); border: 2px solid var(--border-color);"></div>
                                </td>
                                <td><?= number_format($theme['price'], 0, ',', '.') ?> VNĐ</td>
                                <td>
                                    <span class="badge <?= $theme['is_premium'] ? 'premium' : 'free' ?>">
                                        <?= $theme['is_premium'] ? 'Premium' : 'Miễn phí' ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        Particles: <?= $theme['particle_count'] ?? 1000 ?><br>
                                        Color: <span style="color: <?= htmlspecialchars($theme['particle_color'] ?? '#ffffff') ?>">●</span>
                                    </small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="admin_edit_item.php?type=theme&id=<?= $theme['id'] ?>" class="btn-edit">✏️ Sửa</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Bạn có chắc muốn xóa theme này?');">
                                            <input type="hidden" name="theme_id" value="<?= $theme['id'] ?>">
                                            <button type="submit" name="delete_theme" class="btn-delete">🗑️ Xóa</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <a href="index.php" class="back-link">🏠 Về Trang Chủ</a>
        <a href="admin_add_items.php" class="back-link" style="margin-left: 10px;">➕ Thêm Items</a>
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

