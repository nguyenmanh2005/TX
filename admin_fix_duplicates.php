<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

// Kiểm tra quyền admin
$userId = $_SESSION['Iduser'];
$checkAdmin = $conn->prepare("SELECT Role FROM users WHERE Iduser = ?");
$checkAdmin->bind_param("i", $userId);
$checkAdmin->execute();
$result = $checkAdmin->get_result();
$userData = $result->fetch_assoc();
$checkAdmin->close();

// Role là INT: 0 = user, 1 = admin
$userRole = isset($userData['Role']) ? (int)$userData['Role'] : 0;

if ($userRole != 1) {
    die("⚠️ Bạn không có quyền truy cập trang này!");
}

require_once 'load_theme.php';

if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'check';
$message = '';
$duplicateData = [];

// Xử lý các action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'fix') {
    $table = $_POST['table'] ?? '';
    
    if ($table) {
        $conn->begin_transaction();
        try {
            switch ($table) {
                case 'user_themes':
                    $conn->query("DELETE t1 FROM user_themes t1 INNER JOIN user_themes t2 WHERE t1.id > t2.id AND t1.user_id = t2.user_id AND t1.theme_id = t2.theme_id");
                    break;
                case 'user_cursors':
                    $conn->query("DELETE t1 FROM user_cursors t1 INNER JOIN user_cursors t2 WHERE t1.id > t2.id AND t1.user_id = t2.user_id AND t1.cursor_id = t2.cursor_id");
                    break;
                case 'user_achievements':
                    $conn->query("DELETE t1 FROM user_achievements t1 INNER JOIN user_achievements t2 WHERE t1.id > t2.id AND t1.user_id = t2.user_id AND t1.achievement_id = t2.achievement_id");
                    break;
                case 'user_chat_frames':
                    $conn->query("DELETE t1 FROM user_chat_frames t1 INNER JOIN user_chat_frames t2 WHERE t1.id > t2.id AND t1.user_id = t2.user_id AND t1.chat_frame_id = t2.chat_frame_id");
                    break;
                case 'user_avatar_frames':
                    $conn->query("DELETE t1 FROM user_avatar_frames t1 INNER JOIN user_avatar_frames t2 WHERE t1.id > t2.id AND t1.user_id = t2.user_id AND t1.avatar_frame_id = t2.avatar_frame_id");
                    break;
                case 'game_history':
                    $conn->query("DELETE t1 FROM game_history t1 INNER JOIN game_history t2 WHERE t1.id > t2.id AND t1.user_id = t2.user_id AND t1.game_name = t2.game_name AND ABS(TIMESTAMPDIFF(SECOND, t1.played_at, t2.played_at)) < 5");
                    break;
                case 'friends':
                    $conn->query("DELETE t1 FROM friends t1 INNER JOIN friends t2 WHERE t1.id > t2.id AND t1.user_id = t2.user_id AND t1.friend_id = t2.friend_id");
                    break;
                case 'guild_members':
                    $conn->query("DELETE t1 FROM guild_members t1 INNER JOIN guild_members t2 WHERE t1.id > t2.id AND t1.guild_id = t2.guild_id AND t1.user_id = t2.user_id");
                    break;
                case 'tournament_participants':
                    $conn->query("DELETE t1 FROM tournament_participants t1 INNER JOIN tournament_participants t2 WHERE t1.id > t2.id AND t1.tournament_id = t2.tournament_id AND t1.user_id = t2.user_id");
                    break;
                case 'event_participants':
                    $conn->query("DELETE t1 FROM event_participants t1 INNER JOIN event_participants t2 WHERE t1.id > t2.id AND t1.event_id = t2.event_id AND t1.user_id = t2.user_id");
                    break;
            }
            $conn->commit();
            $message = "✅ Đã xóa dữ liệu trùng lặp trong bảng <strong>$table</strong>!";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "❌ Lỗi: " . $e->getMessage();
        }
    }
}

// Kiểm tra dữ liệu trùng lặp
function checkDuplicates($conn, $table, $columns) {
    $cols = implode(', ', $columns);
    $sql = "SELECT $cols, COUNT(*) as count
            FROM $table
            GROUP BY $cols
            HAVING COUNT(*) > 1";
    $result = $conn->query($sql);
    return $result ? $result->num_rows : 0;
}

$tables = [
    'user_themes' => ['user_id', 'theme_id'],
    'user_cursors' => ['user_id', 'cursor_id'],
    'user_achievements' => ['user_id', 'achievement_id'],
    'user_chat_frames' => ['user_id', 'chat_frame_id'],
    'user_avatar_frames' => ['user_id', 'avatar_frame_id'],
    'friends' => ['user_id', 'friend_id'],
    'guild_members' => ['guild_id', 'user_id'],
    'tournament_participants' => ['tournament_id', 'user_id'],
    'event_participants' => ['event_id', 'user_id']
];

foreach ($tables as $table => $columns) {
    $checkTable = $conn->query("SHOW TABLES LIKE '$table'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $duplicateData[$table] = checkDuplicates($conn, $table, $columns);
    }
}

// Kiểm tra game_history (cần điều kiện đặc biệt)
$checkTable = $conn->query("SHOW TABLES LIKE 'game_history'");
if ($checkTable && $checkTable->num_rows > 0) {
    $sql = "SELECT COUNT(*) as count FROM (
        SELECT user_id, game_name, played_at, COUNT(*) as cnt
        FROM game_history
        GROUP BY user_id, game_name, played_at
        HAVING COUNT(*) > 1
    ) as dup";
    $result = $conn->query($sql);
    $duplicateData['game_history'] = $result ? $result->fetch_assoc()['count'] : 0;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Xử Lý Dữ Liệu Trùng Lặp</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header-admin {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 24px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
        }

        .header-admin h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 42px;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 15px;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .duplicate-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .duplicate-table th,
        .duplicate-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .duplicate-table th {
            background: rgba(102, 126, 234, 0.1);
            font-weight: 600;
            color: #333;
        }
        
        .duplicate-table tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #28a745;
            color: white;
        }
        
        .badge-danger {
            background: #dc3545;
            color: white;
        }
        
        .badge-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .message {
            padding: 15px;
            border-radius: 12px;
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
        
        .info-box {
            background: rgba(247, 247, 247, 0.8);
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }
        
        .info-box h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .info-box p {
            color: #666;
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="header-admin">
            <h1>🔧 Admin - Xử Lý Dữ Liệu Trùng Lặp</h1>
            <p style="color: #666; font-size: 18px; margin-top: 10px;">Kiểm tra và xóa dữ liệu trùng lặp trong database</p>
        </div>

        <?php if ($message): ?>
            <div class="card">
                <div class="message <?= strpos($message, '✅') !== false ? 'success' : 'error' ?>">
                    <?= $message ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="info-box">
                <h3>⚠️ Lưu Ý Quan Trọng</h3>
                <p>
                    - Script này sẽ <strong>XÓA</strong> dữ liệu trùng lặp, chỉ giữ lại bản ghi đầu tiên (id nhỏ nhất)<br>
                    - Nên <strong>BACKUP database</strong> trước khi chạy<br>
                    - Kiểm tra kỹ trước khi xóa<br>
                    - Có thể chạy nhiều lần an toàn (chỉ xóa khi có trùng lặp)
                </p>
            </div>

            <h2 style="margin-bottom: 20px;">Kết Quả Kiểm Tra</h2>
            <table class="duplicate-table">
                <thead>
                    <tr>
                        <th>Bảng</th>
                        <th>Số Lượng Trùng Lặp</th>
                        <th>Trạng Thái</th>
                        <th>Hành Động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($duplicateData as $table => $count): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($table) ?></strong></td>
                            <td><?= $count ?></td>
                            <td>
                                <?php if ($count > 0): ?>
                                    <span class="badge badge-danger">Có Trùng Lặp</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Không Trùng</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($count > 0): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa dữ liệu trùng lặp trong bảng <?= $table ?>?');">
                                        <input type="hidden" name="action" value="fix">
                                        <input type="hidden" name="table" value="<?= $table ?>">
                                        <button type="submit" class="btn btn-danger">Xóa Trùng Lặp</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #999;">Không cần xử lý</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2 style="margin-bottom: 20px;">Tùy Chọn Nâng Cao</h2>
            <div class="info-box">
                <h3>Xóa Tất Cả Trùng Lặp</h3>
                <p>Xóa dữ liệu trùng lặp trong TẤT CẢ các bảng cùng lúc</p>
                <form method="POST" onsubmit="return confirm('⚠️ CẢNH BÁO: Bạn có chắc chắn muốn xóa dữ liệu trùng lặp trong TẤT CẢ các bảng? Hãy đảm bảo đã BACKUP database!');">
                    <input type="hidden" name="action" value="fix_all">
                    <button type="submit" class="btn btn-danger" style="margin-top: 10px;">Xóa Tất Cả Trùng Lặp</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h2 style="margin-bottom: 20px;">Hướng Dẫn</h2>
            <div class="info-box">
                <h3>Cách Sử Dụng:</h3>
                <ol style="margin-left: 20px; line-height: 2;">
                    <li>Kiểm tra số lượng trùng lặp ở bảng trên</li>
                    <li>Nếu có trùng lặp, click nút "Xóa Trùng Lặp" cho từng bảng</li>
                    <li>Hoặc dùng "Xóa Tất Cả Trùng Lặp" để xóa tất cả cùng lúc</li>
                    <li>Sau khi xóa, refresh trang để kiểm tra lại</li>
                </ol>
                
                <h3 style="margin-top: 20px;">Các Bảng Được Kiểm Tra:</h3>
                <ul style="margin-left: 20px; line-height: 2;">
                    <li>user_themes - User sở hữu themes</li>
                    <li>user_cursors - User sở hữu cursors</li>
                    <li>user_achievements - User đạt achievements</li>
                    <li>user_chat_frames - User sở hữu khung chat</li>
                    <li>user_avatar_frames - User sở hữu khung avatar</li>
                    <li>game_history - Lịch sử chơi game</li>
                    <li>friends - Quan hệ bạn bè</li>
                    <li>guild_members - Thành viên guild</li>
                    <li>tournament_participants - Người tham gia giải đấu</li>
                    <li>event_participants - Người tham gia sự kiện</li>
                </ul>
            </div>
        </div>
    </div>

    <?php
    // Xử lý fix all
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'fix_all') {
        $conn->begin_transaction();
        try {
            foreach ($tables as $table => $columns) {
                $checkTable = $conn->query("SHOW TABLES LIKE '$table'");
                if ($checkTable && $checkTable->num_rows > 0) {
                    switch ($table) {
                        case 'user_themes':
                            $conn->query("DELETE t1 FROM user_themes t1 INNER JOIN user_themes t2 WHERE t1.id > t2.id AND t1.user_id = t2.user_id AND t1.theme_id = t2.theme_id");
                            break;
                        case 'user_cursors':
                            $conn->query("DELETE t1 FROM user_cursors t1 INNER JOIN user_cursors t2 WHERE t1.id > t2.id AND t1.user_id = t2.user_id AND t1.cursor_id = t2.cursor_id");
                            break;
                        case 'user_achievements':
                            $conn->query("DELETE t1 FROM user_achievements t1 INNER JOIN user_achievements t2 WHERE t1.id > t2.id AND t1.user_id = t2.user_id AND t1.achievement_id = t2.achievement_id");
                            break;
                        case 'user_chat_frames':
                            $conn->query("DELETE t1 FROM user_chat_frames t1 INNER JOIN user_chat_frames t2 WHERE t1.id > t2.id AND t1.user_id = t2.user_id AND t1.chat_frame_id = t2.chat_frame_id");
                            break;
                        case 'user_avatar_frames':
                            $conn->query("DELETE t1 FROM user_avatar_frames t1 INNER JOIN user_avatar_frames t2 WHERE t1.id > t2.id AND t1.user_id = t2.user_id AND t1.avatar_frame_id = t2.avatar_frame_id");
                            break;
                        case 'friends':
                            $conn->query("DELETE t1 FROM friends t1 INNER JOIN friends t2 WHERE t1.id > t2.id AND t1.user_id = t2.user_id AND t1.friend_id = t2.friend_id");
                            break;
                        case 'guild_members':
                            $conn->query("DELETE t1 FROM guild_members t1 INNER JOIN guild_members t2 WHERE t1.id > t2.id AND t1.guild_id = t2.guild_id AND t1.user_id = t2.user_id");
                            break;
                        case 'tournament_participants':
                            $conn->query("DELETE t1 FROM tournament_participants t1 INNER JOIN tournament_participants t2 WHERE t1.id > t2.id AND t1.tournament_id = t2.tournament_id AND t1.user_id = t2.user_id");
                            break;
                        case 'event_participants':
                            $conn->query("DELETE t1 FROM event_participants t1 INNER JOIN event_participants t2 WHERE t1.id > t2.id AND t1.event_id = t2.event_id AND t1.user_id = t2.user_id");
                            break;
                    }
                }
            }
            
            // Fix game_history
            $checkTable = $conn->query("SHOW TABLES LIKE 'game_history'");
            if ($checkTable && $checkTable->num_rows > 0) {
                $conn->query("DELETE t1 FROM game_history t1 INNER JOIN game_history t2 WHERE t1.id > t2.id AND t1.user_id = t2.user_id AND t1.game_name = t2.game_name AND ABS(TIMESTAMPDIFF(SECOND, t1.played_at, t2.played_at)) < 5");
            }
            
            $conn->commit();
            echo '<script>
                Swal.fire({
                    icon: "success",
                    title: "Thành công",
                    text: "Đã xóa dữ liệu trùng lặp trong tất cả các bảng!",
                    timer: 3000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            </script>';
        } catch (Exception $e) {
            $conn->rollback();
            echo '<script>
                Swal.fire({
                    icon: "error",
                    title: "Lỗi",
                    text: "' . addslashes($e->getMessage()) . '"
                });
            </script>';
        }
    }
    ?>
</body>
</html>

