
<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

require 'db_connect.php';

// Load theme
require_once 'load_theme.php';
// Äáº£m báº£o $bgGradientCSS có giá trị
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

// Load admin helper
require_once 'admin_helper.php';

$userId = $_SESSION['Iduser'];

// Kiểm tra quyá»n Super Admin (Role >= 2)
if (!isSuperAdmin($conn, $userId)) {
    header("Location: Shared/403/403.php");
    exit();
}

$message = '';
$messageType = '';

// Xử lý xóa cursor
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_cursor'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'âŒ Yêu cầu không hợp lệ (CSRF)!';
        $messageType = 'error';
    } else {
    $cursorId = (int) $_POST['cursor_id'];
    $deleteSql = "DELETE FROM cursors WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    if ($deleteStmt) {
        $deleteStmt->bind_param("i", $cursorId);
        if ($deleteStmt->execute()) {
            $message = '✅ Xóa cursor thành công!';
            $messageType = 'success';
        } else {
            $message = 'âŒ Lỗi khi xóa cursor: ' . $deleteStmt->error;
            $messageType = 'error';
        }
        $deleteStmt->close();
    }
    } // end CSRF check
}

// Xử lý xóa achievement
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_achievement'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'âŒ Yêu cầu không hợp lệ (CSRF)!';
        $messageType = 'error';
    } else {
    $achievementId = (int) $_POST['achievement_id'];
    $deleteSql = "DELETE FROM achievements WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    if ($deleteStmt) {
        $deleteStmt->bind_param("i", $achievementId);
        if ($deleteStmt->execute()) {
            $message = '✅ Xóa achievement thành công!';
            $messageType = 'success';
        } else {
            $message = 'âŒ Lỗi khi xóa achievement: ' . $deleteStmt->error;
            $messageType = 'error';
        }
        $deleteStmt->close();
    }
    } // end CSRF check
}

// Xử lý xóa theme
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_theme'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'âŒ Yêu cầu không hợp lệ (CSRF)!';
        $messageType = 'error';
    } else {
    $themeId = (int) $_POST['theme_id'];
    $deleteSql = "DELETE FROM themes WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    if ($deleteStmt) {
        $deleteStmt->bind_param("i", $themeId);
        if ($deleteStmt->execute()) {
            $message = '✅ Xóa theme thành công!';
            $messageType = 'success';
        } else {
            $message = 'âŒ Lỗi khi xóa theme: ' . $deleteStmt->error;
            $messageType = 'error';
        }
        $deleteStmt->close();
    }
    } // end CSRF check
}

// Láº¥y danh sách cursors
$cursors = [];
$cursorsSql = "SELECT * FROM cursors ORDER BY id ASC";
$cursorsResult = $conn->query($cursorsSql);
if ($cursorsResult) {
    while ($row = $cursorsResult->fetch_assoc()) {
        $cursors[] = $row;
    }
}

// Láº¥y danh sách achievements
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

// Láº¥y danh sách themes
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
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
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            min-height: 100vh;
            position: relative;
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

        input[type="text"],
        input[type="number"],
        textarea {
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
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .btn-edit,
        .btn-delete {
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

            .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(3px); }
        .modal-content { background-color: #fff; margin: 3% auto; padding: 20px 30px; border-radius: var(--border-radius-lg); width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 25px rgba(0,0,0,0.3); animation: slideIn 0.3s ease; position: relative; }
        .close-modal { color: #aaa; position: absolute; right: 20px; top: 20px; font-size: 28px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .close-modal:hover { color: #333; }
        .modal-content .form-group { margin-bottom: 15px; }
        .modal-content label { display: block; margin-bottom: 5px; font-weight: bold; color: var(--primary-color); }
        .modal-content input[type="text"], .modal-content input[type="number"], .modal-content textarea, .modal-content select { width: 100%; padding: 10px; border: 2px solid var(--border-color); border-radius: var(--border-radius); box-sizing: border-box; }
        .modal-content .submit-button { width: 100%; padding: 12px; background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%); color: white; border: none; border-radius: var(--border-radius); font-size: 16px; font-weight: bold; cursor: pointer; }
</style>
</head>

<body>
    <canvas id="threejs-background"></canvas>

    <div class="admin-container">
        <div class="header-admin">
            <h1>⚙️ Admin - Quản Lý Items</h1>
            <p>Quản lý cursors, achievements và themes</p>
        </div>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-button active" onclick="switchTab('cursors')">ðŸ–±ï¸ Quản Lý Cursors</button>
            <button class="tab-button" onclick="switchTab('achievements')">ðŸ† Quản Lý Achievements</button>
            <button class="tab-button" onclick="switchTab('themes')">ðŸŽ¨ Quản Lý Themes</button>
        </div>

        <!-- Tab Quản Lý Cursors -->
        <div id="cursors-tab" class="tab-content active">
            <button type="button" class="add-new-btn" onclick="openItemModal('add', 'cursor')" style="border:none;">➕ Thêm Cursor Mới</button>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên</th>
                        <th>MÃ´ táº£</th>
                        <th>áº¢nh</th>
                        <th>Giá</th>
                        <th>Premium</th>
                        <th>Thao tÃ¡c</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cursors)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                Chưa có cursor nào. <a href="?action=add">Thêm cursor mới</a>
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
                                        alt="<?= htmlspecialchars($cursor['name']) ?>" class="cursor-preview"
                                        onerror="this.src='chuot.png'">
                                </td>
                                <td><?= number_format($cursor['price'], 0, ',', '.') ?> gtlm</td>
                                <td>
                                    <span class="badge <?= $cursor['is_premium'] ? 'premium' : 'free' ?>">
                                        <?= $cursor['is_premium'] ? 'Premium' : 'Miễn phí' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-edit" onclick='openItemModal("edit", "cursor", <?= json_encode($cursor, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>✏️ Sửa</button>
                                        <form method="POST" style="display: inline;"
                                            onsubmit="return confirm('Bạn có chắc muốn xóa cursor này?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
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
            <button type="button" class="add-new-btn" onclick="openItemModal('add', 'achievement')" style="border:none;">➕ Thêm Achievement Mới</button>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Icon</th>
                        <th>Tên</th>
                        <th>MÃ´ táº£</th>
                        <th>Yêu cầu</th>
                        <th>Pháº§n thÆ°á»Ÿng</th>
                        <th>Äá»™ hiáº¿m</th>
                        <th>Thao tÃ¡c</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($achievements)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">
                                Chưa có achievement nào. <a href="?action=add">Thêm achievement mới</a>
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
                                        'money' => 'gtlm',
                                        'games_played' => 'Sá»‘ game',
                                        'big_win' => 'Tháº¯ng lá»›n',
                                        'streak' => 'Chuá»—i tháº¯ng',
                                        'rank' => 'Xáº¿p háº¡ng'
                                    ];
                                    echo ($reqLabels[$reqType] ?? $reqType) . ': ' . $reqValue;
                                    ?>
                                </td>
                                <td><?= number_format($achievement['reward_money'], 0, ',', '.') ?> gtlm</td>
                                <td>
                                    <span class="badge <?= $achievement['rarity'] ?>">
                                        <?= ucfirst($achievement['rarity']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-edit" onclick='openItemModal("edit", "achievement", <?= json_encode($achievement, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>✏️ Sửa</button>
                                        <form method="POST" style="display: inline;"
                                            onsubmit="return confirm('Bạn có chắc muốn xóa achievement này?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
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
            <button type="button" class="add-new-btn" onclick="openItemModal('add', 'theme')" style="border:none;">➕ Thêm Theme Mới</button>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên</th>
                        <th>MÃ´ táº£</th>
                        <th>Preview</th>
                        <th>Giá</th>
                        <th>Premium</th>
                        <th>Cáº¥u hình</th>
                        <th>Thao tÃ¡c</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($themes)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">
                                Chưa có theme nào. <a href="?action=add">Thêm theme mới</a>
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
                                    <div
                                        style="width: 100px; height: 60px; background: <?= $gradient ?>; border-radius: var(--border-radius); border: 2px solid var(--border-color);">
                                    </div>
                                </td>
                                <td><?= number_format($theme['price'], 0, ',', '.') ?> gtlm</td>
                                <td>
                                    <span class="badge <?= $theme['is_premium'] ? 'premium' : 'free' ?>">
                                        <?= $theme['is_premium'] ? 'Premium' : 'Miễn phí' ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        Particles: <?= $theme['particle_count'] ?? 1000 ?><br>
                                        Color: <span
                                            style="color: <?= htmlspecialchars($theme['particle_color'] ?? '#ffffff') ?>">â—</span>
                                    </small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-edit" onclick='openItemModal("edit", "theme", <?= json_encode($theme, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>✏️ Sửa</button>
                                        <form method="POST" style="display: inline;"
                                            onsubmit="return confirm('Bạn có chắc muốn xóa theme này?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
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

        <a href="index.php" class="back-link">ðŸ  Về Trang Chủ</a>
        <a href="?action=add" class="back-link" style="margin-left: 10px;">➕ Thêm Items</a>
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

<script>
    // Initialize Three.js Background
    (function() {
        // Pass theme config từ PHP sang JavaScript
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
        
        // Load Three.js background script vá»›i Ä‘Æ°á»ng dáº«n chính xác
        const isInGames = window.location.pathname.includes('/games/');
        const script = document.createElement('script');
        script.src = isInGames ? '../threejs-background.js' : 'threejs-background.js';
        script.onload = function() {
            console.log('Three.js background loaded');
        };
        document.head.appendChild(script);
    })();
</script>
    <!-- Modal cho Items -->
    <div id="itemModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeItemModal()">&times;</span>
            <h2 id="modalTitle" style="margin-bottom: 20px; color: var(--primary-color);">Thêm Mới</h2>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="item_id" id="modal_item_id" value="0">
                <input type="hidden" name="item_type" id="modal_item_type" value="">
                
                <!-- Cursor Fields -->
                <div id="cursorFields" style="display:none;">
                    <div class="form-group">
                        <label>Tên cursor *</label>
                        <input type="text" id="cursor_name" name="cursor_name">
                    </div>
                    <div class="form-group">
                        <label>Mô tả</label>
                        <textarea id="cursor_description" name="cursor_description"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Giá (gtlm) *</label>
                        <input type="number" id="cursor_price" name="cursor_price" min="0" step="1000">
                    </div>
                    <div class="form-group">
                        <label>Đường dẫn ảnh *</label>
                        <input type="text" id="cursor_image" name="cursor_image">
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" id="cursor_premium" name="cursor_premium" value="1"> Premium</label>
                    </div>
                </div>

                <!-- Achievement Fields -->
                <div id="achievementFields" style="display:none;">
                    <div class="form-group">
                        <label>Tên thành tựu *</label>
                        <input type="text" id="achievement_name" name="achievement_name">
                    </div>
                    <div class="form-group">
                        <label>Mô tả</label>
                        <textarea id="achievement_description" name="achievement_description"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Icon (Emoji) *</label>
                        <input type="text" id="achievement_icon" name="achievement_icon" maxlength="2">
                    </div>
                    <div class="form-group">
                        <label>Loại yêu cầu *</label>
                        <select id="achievement_type" name="achievement_type">
                            <option value="money">Số GTLM (money)</option>
                            <option value="games_played">Số game chơi (games_played)</option>
                            <option value="big_win">Thắng lớn (big_win)</option>
                            <option value="streak">Chuỗi thắng (streak)</option>
                            <option value="rank">Xếp hạng (rank)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Giá trị yêu cầu *</label>
                        <input type="number" id="achievement_value" name="achievement_value" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Phần thưởng (gtlm)</label>
                        <input type="number" id="achievement_reward" name="achievement_reward" step="1000">
                    </div>
                    <div class="form-group">
                        <label>Độ hiếm *</label>
                        <select id="achievement_rarity" name="achievement_rarity">
                            <option value="common">Thường</option>
                            <option value="rare">Hiếm</option>
                            <option value="epic">Cực hiếm</option>
                            <option value="legendary">Huyền thoại</option>
                        </select>
                    </div>
                </div>

                <!-- Theme Fields -->
                <div id="themeFields" style="display:none;">
                    <div class="form-group">
                        <label>Tên theme *</label>
                        <input type="text" id="theme_name" name="theme_name">
                    </div>
                    <div class="form-group">
                        <label>Mô tả</label>
                        <textarea id="theme_description" name="theme_description"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Giá (gtlm) *</label>
                        <input type="number" id="theme_price" name="theme_price" step="1000">
                    </div>
                    <div class="form-group">
                        <label>Đường dẫn preview</label>
                        <input type="text" id="theme_preview" name="theme_preview">
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" id="theme_premium" name="theme_premium" value="1"> Premium</label>
                    </div>
                    
                    <h3 style="margin:20px 0 10px;color:var(--primary-color);">Cấu hình 3D Background</h3>
                    <div class="form-group"><label>Số lượng particles</label><input type="number" id="particle_count" name="particle_count" value="1000"></div>
                    <div class="form-group"><label>Kích thước particle</label><input type="number" id="particle_size" name="particle_size" step="0.01" value="0.05"></div>
                    <div class="form-group"><label>Màu particle</label><input type="color" id="particle_color" name="particle_color" value="#ffffff"></div>
                    <div class="form-group"><label>Độ trong suốt particle</label><input type="number" id="particle_opacity" name="particle_opacity" step="0.1" value="0.6"></div>
                    <div class="form-group"><label>Số lượng hình 3D</label><input type="number" id="shape_count" name="shape_count" value="15"></div>
                    <div class="form-group"><label>Màu hình 3D (JSON)</label><input type="text" id="shape_colors" name="shape_colors" value='["#667eea", "#764ba2", "#4facfe", "#00f2fe"]'></div>
                    <div class="form-group"><label>Độ trong suốt hình 3D</label><input type="number" id="shape_opacity" name="shape_opacity" step="0.1" value="0.3"></div>
                    <div class="form-group"><label>Màu Gradient (JSON)</label><input type="text" id="background_gradient" name="background_gradient" value='["#667eea", "#764ba2", "#4facfe"]'></div>
                </div>

                <button type="submit" name="save_item" class="submit-button">💾 Lưu Thay Đổi</button>
            </form>
        </div>
    </div>

    <script>
    function openItemModal(action, type, data = null) {
        document.getElementById('itemModal').style.display = 'block';
        document.getElementById('modal_item_type').value = type;
        
        let titleName = type.charAt(0).toUpperCase() + type.slice(1);
        document.getElementById('modalTitle').innerText = (action === 'edit' ? '⚙️ Sửa ' : '➕ Thêm ') + titleName;
        
        document.getElementById('cursorFields').style.display = 'none';
        document.getElementById('achievementFields').style.display = 'none';
        document.getElementById('themeFields').style.display = 'none';
        
        document.getElementById(type + 'Fields').style.display = 'block';
        
        document.getElementById('modal_item_id').value = (action === 'edit' && data) ? data.id : '0';
        
        if (type === 'cursor') {
            document.getElementById('cursor_name').value = data ? data.name : '';
            document.getElementById('cursor_description').value = data ? data.description : '';
            document.getElementById('cursor_price').value = data ? parseInt(data.price) : 0;
            document.getElementById('cursor_image').value = data ? data.cursor_image : '';
            document.getElementById('cursor_premium').checked = data ? (data.is_premium == 1) : false;
        } else if (type === 'achievement') {
            document.getElementById('achievement_name').value = data ? data.name : '';
            document.getElementById('achievement_description').value = data ? data.description : '';
            document.getElementById('achievement_icon').value = data ? data.icon : '';
            document.getElementById('achievement_type').value = data ? data.requirement_type : 'money';
            document.getElementById('achievement_value').value = data ? data.requirement_value : 0;
            document.getElementById('achievement_reward').value = data ? parseInt(data.reward_money) : 0;
            document.getElementById('achievement_rarity').value = data ? data.rarity : 'common';
        } else if (type === 'theme') {
            document.getElementById('theme_name').value = data ? data.name : '';
            document.getElementById('theme_description').value = data ? data.description : '';
            document.getElementById('theme_price').value = data ? parseInt(data.price) : 0;
            document.getElementById('theme_preview').value = data ? (data.preview_image || '') : '';
            document.getElementById('theme_premium').checked = data ? (data.is_premium == 1) : false;
            
            document.getElementById('particle_count').value = data ? data.particle_count : 1000;
            document.getElementById('particle_size').value = data ? data.particle_size : 0.05;
            document.getElementById('particle_color').value = data ? data.particle_color : '#ffffff';
            document.getElementById('particle_opacity').value = data ? data.particle_opacity : 0.6;
            document.getElementById('shape_count').value = data ? data.shape_count : 15;
            document.getElementById('shape_colors').value = data ? data.shape_colors : '["#667eea", "#764ba2", "#4facfe", "#00f2fe"]';
            document.getElementById('shape_opacity').value = data ? data.shape_opacity : 0.3;
            document.getElementById('background_gradient').value = data ? data.background_gradient : '["#667eea", "#764ba2", "#4facfe"]';
        }
    }

    function closeItemModal() {
        document.getElementById('itemModal').style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == document.getElementById('itemModal')) {
            closeItemModal();
        }
    }
    </script>
</body>

</html>