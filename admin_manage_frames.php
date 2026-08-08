
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

// Xử lý xóa chat frame
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_chat_frame'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'âŒ Yêu cầu không hợp lệ (CSRF)!';
        $messageType = 'error';
    } else {
    $frameId = (int) $_POST['chat_frame_id'];
    $deleteSql = "DELETE FROM chat_frames WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    if ($deleteStmt) {
        $deleteStmt->bind_param("i", $frameId);
        if ($deleteStmt->execute()) {
            $message = '✅ Xóa khung chat thành công!';
            $messageType = 'success';
        } else {
            $message = 'âŒ Lỗi khi xóa khung chat: ' . $deleteStmt->error;
            $messageType = 'error';
        }
        $deleteStmt->close();
    }
    } // end CSRF check
}

// Xử lý xóa avatar frame
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_avatar_frame'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'âŒ Yêu cầu không hợp lệ (CSRF)!';
        $messageType = 'error';
    } else {
    $frameId = (int) $_POST['avatar_frame_id'];
    $deleteSql = "DELETE FROM avatar_frames WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    if ($deleteStmt) {
        $deleteStmt->bind_param("i", $frameId);
        if ($deleteStmt->execute()) {
            $message = '✅ Xóa khung avatar thành công!';
            $messageType = 'success';
        } else {
            $message = 'âŒ Lỗi khi xóa khung avatar: ' . $deleteStmt->error;
            $messageType = 'error';
        }
        $deleteStmt->close();
    }
    } // end CSRF check
}

// Láº¥y danh sách chat frames
$chatFrames = [];
$chatFramesSql = "SELECT * FROM chat_frames ORDER BY id ASC";
$chatFramesResult = $conn->query($chatFramesSql);
if ($chatFramesResult) {
    while ($row = $chatFramesResult->fetch_assoc()) {
        $chatFrames[] = $row;
    }
}

// Láº¥y danh sách avatar frames
$avatarFrames = [];
$avatarFramesSql = "SELECT * FROM avatar_frames ORDER BY id ASC";
$avatarFramesResult = $conn->query($avatarFramesSql);
if ($avatarFramesResult) {
    while ($row = $avatarFramesResult->fetch_assoc()) {
        $avatarFrames[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Quản Lý Khung Chat & Avatar</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
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

        .frames-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .frame-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .frame-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            border-color: var(--secondary-color);
        }

        .frame-preview {
            width: 100%;
            height: 150px;
            background: #f0f0f0;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .frame-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .frame-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .frame-info {
            color: var(--text-dark);
            margin-bottom: 10px;
        }

        .frame-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--success-color);
            margin-bottom: 15px;
        }

        .frame-price.free {
            color: var(--secondary-color);
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
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
            flex: 1;
            padding: 10px;
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

            /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(3px);
        }
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px 30px;
            border-radius: var(--border-radius-lg);
            width: 90%;
            max-width: 600px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 4px 25px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
            position: relative;
        }
        .close-modal {
            color: #aaa;
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }
        .close-modal:hover {
            color: #333;
        }
        /* Style form inside modal */
        .modal-content .form-group { margin-bottom: 15px; }
        .modal-content label { display: block; margin-bottom: 5px; font-weight: bold; color: var(--primary-color); }
        .modal-content input[type="text"], .modal-content input[type="number"], .modal-content textarea, .modal-content select {
            width: 100%; padding: 10px; border: 2px solid var(--border-color); border-radius: var(--border-radius); box-sizing: border-box;
        }
        .modal-content input[type="file"] { width: 100%; padding: 10px; border: 2px dashed var(--border-color); border-radius: var(--border-radius); }
        .modal-content .submit-button {
            width: 100%; padding: 12px; background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white; border: none; border-radius: var(--border-radius); font-size: 16px; font-weight: bold; cursor: pointer;
        }
        .modal-content .submit-button:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(52, 152, 219, 0.4); }
</style>
</head>

<body>
    <canvas id="threejs-background"></canvas>

    <div class="admin-container">
        <div class="header-admin">
            <h1>⚙️ Admin - Quản Lý Khung Chat & Avatar</h1>
            <p>Quản lý khung chat và khung avatar</p>
        </div>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-button active" onclick="switchTab('chat-frames')">💬 Quản Lý Khung Chat</button>
            <button class="tab-button" onclick="switchTab('avatar-frames')">🖼️ Quản Lý Khung Avatar</button>
        </div>

        <!-- Tab Quản Lý Khung Chat -->
        <div id="chat-frames-tab" class="tab-content active">
            <button type="button" class="add-new-btn" onclick="openFrameModal('add', 'chat')" style="border:none;">➕ Thêm Khung Chat Mới</button>
            <div class="frames-grid">
                <?php if (empty($chatFrames)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                        Chưa có khung chat nào. <a href="?action=add&type=chat">Thêm khung chat mới</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($chatFrames as $frame): ?>
                        <div class="frame-card">
                            <div class="frame-preview">
                                <?php if (!empty($frame['ImageURL'])): ?>
                                    <img src="<?= htmlspecialchars($frame['ImageURL']) ?>"
                                        alt="<?= htmlspecialchars($frame['frame_name']) ?>"
                                        onerror="this.parentElement.style.background='#f0f0f0'; this.style.display='none';">
                                <?php else: ?>
                                    <div style="color: #999;">Không có ảnh</div>
                                <?php endif; ?>
                            </div>
                            <div class="frame-name"><?= htmlspecialchars($frame['frame_name']) ?></div>
                            <div class="badge <?= htmlspecialchars($frame['rarity'] ?? 'common') ?>">
                                <?= ucfirst(htmlspecialchars($frame['rarity'] ?? 'common')) ?>
                            </div>
                            <div class="frame-info"><?= htmlspecialchars($frame['description'] ?? '') ?></div>
                            <div class="frame-price <?= $frame['price'] == 0 ? 'free' : '' ?>">
                                <?= $frame['price'] == 0 ? 'Miễn phí' : number_format($frame['price'], 0, ',', '.') . ' gtlm' ?>
                            </div>
                            <div class="action-buttons">
                                <button type="button" class="btn-edit" onclick='openFrameModal("edit", "chat", <?= json_encode($frame, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>✏️ Sửa</button>
                                <form method="POST" style="display: inline; flex: 1;"
                                    onsubmit="return confirm('Bạn có chắc muốn xóa khung chat này?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="chat_frame_id" value="<?= $frame['id'] ?>">
                                    <button type="submit" name="delete_chat_frame" class="btn-delete">🗑️ Xóa</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab Quản Lý Khung Avatar -->
        <div id="avatar-frames-tab" class="tab-content">
            <button type="button" class="add-new-btn" onclick="openFrameModal('add', 'avatar')" style="border:none;">➕ Thêm Khung Avatar Mới</button>
            <div class="frames-grid">
                <?php if (empty($avatarFrames)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                        Chưa có khung avatar nào. <a href="?action=add&type=avatar">Thêm khung avatar mới</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($avatarFrames as $frame): ?>
                        <div class="frame-card">
                            <div class="frame-preview">
                                <?php if (!empty($frame['ImageURL'])): ?>
                                    <img src="<?= htmlspecialchars($frame['ImageURL']) ?>"
                                        alt="<?= htmlspecialchars($frame['frame_name']) ?>"
                                        onerror="this.parentElement.style.background='#f0f0f0'; this.style.display='none';">
                                <?php else: ?>
                                    <div style="color: #999;">Không có ảnh</div>
                                <?php endif; ?>
                            </div>
                            <div class="frame-name"><?= htmlspecialchars($frame['frame_name']) ?></div>
                            <div class="badge <?= htmlspecialchars($frame['rarity'] ?? 'common') ?>">
                                <?= ucfirst(htmlspecialchars($frame['rarity'] ?? 'common')) ?>
                            </div>
                            <div class="frame-info"><?= htmlspecialchars($frame['description'] ?? '') ?></div>
                            <div class="frame-price <?= $frame['price'] == 0 ? 'free' : '' ?>">
                                <?= $frame['price'] == 0 ? 'Miễn phí' : number_format($frame['price'], 0, ',', '.') . ' gtlm' ?>
                            </div>
                            <div class="action-buttons">
                                <button type="button" class="btn-edit" onclick='openFrameModal("edit", "avatar", <?= json_encode($frame, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>✏️ Sửa</button>
                                <form method="POST" style="display: inline; flex: 1;"
                                    onsubmit="return confirm('Bạn có chắc muốn xóa khung avatar này?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="avatar_frame_id" value="<?= $frame['id'] ?>">
                                    <button type="submit" name="delete_avatar_frame" class="btn-delete">🗑️ Xóa</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <a href="index.php" class="back-link">ðŸ  Về Trang Chủ</a>
        <a href="admin_manage_items.php" class="back-link" style="margin-left: 10px;">📦 Quản Lý Items</a>
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

    <!-- Modal Form Sửa/Thêm Khung -->
    <div id="frameModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeFrameModal()">&times;</span>
            <h2 id="modalTitle" style="margin-bottom: 20px; color: var(--primary-color);">Thêm Khung Mới</h2>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="frame_id" id="modal_frame_id" value="0">
                <input type="hidden" name="frame_type" id="modal_frame_type" value="">
                <input type="hidden" name="existing_image" id="modal_existing_image" value="">
                
                <div class="form-group">
                    <label for="frame_name">Tên khung *</label>
                    <input type="text" id="frame_name" name="frame_name" required placeholder="Ví dụ: Khung vàng">
                </div>

                <div class="form-group">
                    <label for="description">Mô tả</label>
                    <textarea id="description" name="description" placeholder="Mô tả về khung này..."></textarea>
                </div>

                <div class="form-group">
                    <label for="rarity">Độ hiếm *</label>
                    <select id="rarity" name="rarity" required>
                        <option value="common">🟢 Thường</option>
                        <option value="rare">🔵 Hiếm</option>
                        <option value="epic">🟣 Cực hiếm</option>
                        <option value="legendary">🟡 Huyền thoại</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="price">Giá (gtlm) *</label>
                    <input type="number" id="price" name="price" min="0" step="1000" value="0" required>
                    <div style="font-size:12px; color:#666; margin-top:4px;">Nhập 0 nếu miễn phí</div>
                </div>

                <div class="form-group">
                    <label for="image">Hình ảnh khung</label>
                    <input type="file" id="image" name="image" accept="image/*">
                    <div style="font-size:12px; color:#666; margin-top:4px;">(Để trống nếu giữ nguyên ảnh hiện tại khi sửa)</div>
                    
                    <div id="imagePreviewContainer" style="margin-top: 10px; background: #f0f0f0; padding: 10px; border-radius: 8px; display: none;">
                        <div style="font-size: 12px; margin-bottom: 5px; color: #666; font-weight: bold;">Ảnh hiện tại:</div>
                        <img id="imagePreview" src="" alt="Ảnh hiện tại" style="max-width: 120px; max-height: 120px; object-fit: contain;">
                    </div>
                </div>

                <button type="submit" name="save_frame" class="submit-button">💾 Lưu Thay Đổi</button>
            </form>
        </div>
    </div>

    <script>
    function openFrameModal(action, type, data = null) {
        document.getElementById('frameModal').style.display = 'block';
        document.getElementById('modal_frame_type').value = type;
        
        let typeName = (type === 'chat') ? 'Chat' : 'Avatar';
        
        if (action === 'edit' && data) {
            document.getElementById('modalTitle').innerText = '⚙️ Sửa Khung ' + typeName;
            document.getElementById('modal_frame_id').value = data.id;
            document.getElementById('frame_name').value = data.frame_name;
            document.getElementById('description').value = data.description || '';
            document.getElementById('rarity').value = data.rarity;
            document.getElementById('price').value = parseInt(data.price);
            document.getElementById('modal_existing_image').value = data.ImageURL || '';
            
            if (data.ImageURL) {
                document.getElementById('imagePreviewContainer').style.display = 'block';
                document.getElementById('imagePreview').src = data.ImageURL;
            } else {
                document.getElementById('imagePreviewContainer').style.display = 'none';
            }
        } else {
            // Add
            document.getElementById('modalTitle').innerText = '➕ Thêm Khung ' + typeName;
            document.getElementById('modal_frame_id').value = '0';
            document.getElementById('frame_name').value = '';
            document.getElementById('description').value = '';
            document.getElementById('rarity').value = 'common';
            document.getElementById('price').value = '0';
            document.getElementById('modal_existing_image').value = '';
            document.getElementById('imagePreviewContainer').style.display = 'none';
        }
    }

    function closeFrameModal() {
        document.getElementById('frameModal').style.display = 'none';
    }

    // Ä á» ng modal khi click bÃªn ngoÃ i
    window.onclick = function(event) {
        if (event.target == document.getElementById('frameModal')) {
            closeFrameModal();
        }
    }
    </script>
</body>

</html>