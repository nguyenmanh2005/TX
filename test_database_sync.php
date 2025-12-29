<?php
/**
 * Script kiểm tra database trước và sau khi đồng bộ
 * Chạy script này để xem trạng thái database
 */

require 'db_connect.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiểm Tra Database</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        h2 {
            color: #007bff;
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #007bff;
            color: white;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .warning {
            color: #ffc107;
            font-weight: bold;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <h1>🔍 Kiểm Tra Database Trước Khi Đồng Bộ</h1>

    <?php
    // Kiểm tra kết nối
    if (!$conn) {
        die('<div class="container error">❌ Không thể kết nối database!</div>');
    }
    echo '<div class="container info">✅ Đã kết nối database thành công!</div>';
    ?>

    <div class="container">
        <h2>1. Kiểm Tra Bảng</h2>
        <table>
            <tr>
                <th>Tên Bảng</th>
                <th>Trạng Thái</th>
                <th>Số Bản Ghi</th>
            </tr>
            <?php
            $tables = [
                'users',
                'chat_frames',
                'avatar_frames',
                'user_chat_frames',
                'user_avatar_frames'
            ];
            
            foreach ($tables as $table) {
                $exists = false;
                $count = 0;
                
                $check = $conn->query("SHOW TABLES LIKE '$table'");
                if ($check && $check->num_rows > 0) {
                    $exists = true;
                    $result = $conn->query("SELECT COUNT(*) as cnt FROM $table");
                    if ($result) {
                        $row = $result->fetch_assoc();
                        $count = $row['cnt'];
                    }
                }
                
                $status = $exists ? '<span class="success">✓ Tồn tại</span>' : '<span class="error">✗ Chưa có</span>';
                echo "<tr><td>$table</td><td>$status</td><td>$count</td></tr>";
            }
            ?>
        </table>
    </div>

    <div class="container">
        <h2>2. Kiểm Tra Cột Trong Bảng Users</h2>
        <table>
            <tr>
                <th>Tên Cột</th>
                <th>Trạng Thái</th>
                <th>Kiểu Dữ Liệu</th>
            </tr>
            <?php
            $columns = ['Iduser', 'Name', 'Money', 'ImageURL', 'chat_frame_id', 'avatar_frame_id'];
            
            $result = $conn->query("SHOW COLUMNS FROM users");
            $existingColumns = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $existingColumns[$row['Field']] = $row['Type'];
                }
            }
            
            foreach ($columns as $col) {
                $exists = isset($existingColumns[$col]);
                $status = $exists ? '<span class="success">✓ Có</span>' : '<span class="error">✗ Chưa có</span>';
                $type = $exists ? $existingColumns[$col] : '-';
                echo "<tr><td>$col</td><td>$status</td><td>$type</td></tr>";
            }
            ?>
        </table>
    </div>

    <div class="container">
        <h2>3. Kiểm Tra Cột Trong Bảng Chat_Frames</h2>
        <table>
            <tr>
                <th>Tên Cột</th>
                <th>Trạng Thái</th>
                <th>Kiểu Dữ Liệu</th>
            </tr>
            <?php
            $columns = ['id', 'frame_name', 'ImageURL', 'description', 'rarity', 'price'];
            
            $result = $conn->query("SHOW TABLES LIKE 'chat_frames'");
            if ($result && $result->num_rows > 0) {
                $result = $conn->query("SHOW COLUMNS FROM chat_frames");
                $existingColumns = [];
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $existingColumns[$row['Field']] = $row['Type'];
                    }
                }
                
                foreach ($columns as $col) {
                    $exists = isset($existingColumns[$col]);
                    $status = $exists ? '<span class="success">✓ Có</span>' : '<span class="error">✗ Chưa có</span>';
                    $type = $exists ? $existingColumns[$col] : '-';
                    echo "<tr><td>$col</td><td>$status</td><td>$type</td></tr>";
                }
            } else {
                echo "<tr><td colspan='3'><span class=\"error\">Bảng chat_frames chưa tồn tại</span></td></tr>";
            }
            ?>
        </table>
    </div>

    <div class="container">
        <h2>4. Thống Kê Dữ Liệu</h2>
        <table>
            <tr>
                <th>Mục</th>
                <th>Giá Trị</th>
            </tr>
            <?php
            // Số user
            $result = $conn->query("SELECT COUNT(*) as cnt FROM users");
            $userCount = $result ? $result->fetch_assoc()['cnt'] : 0;
            echo "<tr><td>Tổng số User</td><td>$userCount</td></tr>";
            
            // Số khung chat
            $result = $conn->query("SHOW TABLES LIKE 'chat_frames'");
            if ($result && $result->num_rows > 0) {
                $result = $conn->query("SELECT COUNT(*) as cnt FROM chat_frames");
                $frameCount = $result ? $result->fetch_assoc()['cnt'] : 0;
                echo "<tr><td>Tổng số Khung Chat</td><td>$frameCount</td></tr>";
            }
            
            // Số khung avatar
            $result = $conn->query("SHOW TABLES LIKE 'avatar_frames'");
            if ($result && $result->num_rows > 0) {
                $result = $conn->query("SELECT COUNT(*) as cnt FROM avatar_frames");
                $avatarFrameCount = $result ? $result->fetch_assoc()['cnt'] : 0;
                echo "<tr><td>Tổng số Khung Avatar</td><td>$avatarFrameCount</td></tr>";
            }
            
            // User có chat_frame_id
            $result = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE chat_frame_id IS NOT NULL AND chat_frame_id > 0");
            $userWithChatFrame = $result ? $result->fetch_assoc()['cnt'] : 0;
            echo "<tr><td>User có Chat Frame</td><td>$userWithChatFrame</td></tr>";
            
            // User có avatar_frame_id
            $result = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar_frame_id'");
            if ($result && $result->num_rows > 0) {
                $result = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE avatar_frame_id IS NOT NULL AND avatar_frame_id > 0");
                $userWithAvatarFrame = $result ? $result->fetch_assoc()['cnt'] : 0;
                echo "<tr><td>User có Avatar Frame</td><td>$userWithAvatarFrame</td></tr>";
            }
            ?>
        </table>
    </div>

    <div class="container">
        <h2>5. Đề Xuất Hành Động</h2>
        <div class="info">
            <?php
            $actions = [];
            
            // Kiểm tra bảng
            $result = $conn->query("SHOW TABLES LIKE 'avatar_frames'");
            if (!$result || $result->num_rows == 0) {
                $actions[] = "⚠️ Cần tạo bảng <strong>avatar_frames</strong>";
            }
            
            $result = $conn->query("SHOW TABLES LIKE 'user_chat_frames'");
            if (!$result || $result->num_rows == 0) {
                $actions[] = "⚠️ Cần tạo bảng <strong>user_chat_frames</strong>";
            }
            
            $result = $conn->query("SHOW TABLES LIKE 'user_avatar_frames'");
            if (!$result || $result->num_rows == 0) {
                $actions[] = "⚠️ Cần tạo bảng <strong>user_avatar_frames</strong>";
            }
            
            // Kiểm tra cột
            $result = $conn->query("SHOW COLUMNS FROM chat_frames LIKE 'price'");
            if (!$result || $result->num_rows == 0) {
                $actions[] = "⚠️ Cần thêm cột <strong>price</strong> vào bảng chat_frames";
            }
            
            $result = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar_frame_id'");
            if (!$result || $result->num_rows == 0) {
                $actions[] = "⚠️ Cần thêm cột <strong>avatar_frame_id</strong> vào bảng users";
            }
            
            if (empty($actions)) {
                echo "<p class='success'>✅ Database đã được cập nhật đầy đủ! Không cần thao tác gì thêm.</p>";
            } else {
                echo "<p class='warning'>📋 Cần thực hiện các hành động sau:</p><ul>";
                foreach ($actions as $action) {
                    echo "<li>$action</li>";
                }
                echo "</ul>";
                echo "<p><strong>👉 Chạy file sync_database_to_production.sql để cập nhật!</strong></p>";
            }
            ?>
        </div>
    </div>

    <div class="container">
        <h2>6. Thông Tin Database</h2>
        <table>
            <tr>
                <th>Thông Tin</th>
                <th>Giá Trị</th>
            </tr>
            <?php
            $result = $conn->query("SELECT DATABASE() as db");
            $dbName = $result ? $result->fetch_assoc()['db'] : 'N/A';
            echo "<tr><td>Tên Database</td><td>$dbName</td></tr>";
            
            $result = $conn->query("SELECT VERSION() as version");
            $version = $result ? $result->fetch_assoc()['version'] : 'N/A';
            echo "<tr><td>Phiên Bản MySQL</td><td>$version</td></tr>";
            
            $result = $conn->query("SELECT USER() as user");
            $user = $result ? $result->fetch_assoc()['user'] : 'N/A';
            echo "<tr><td>User Database</td><td>$user</td></tr>";
            ?>
        </table>
    </div>

    <div class="container">
        <h2>📝 Hướng Dẫn Tiếp Theo</h2>
        <ol>
            <li><strong>Nếu có cảnh báo ở trên:</strong> Chạy file <code>sync_database_to_production.sql</code></li>
            <li><strong>Sau khi chạy script:</strong> Refresh trang này để kiểm tra lại</li>
            <li><strong>Upload ảnh khung:</strong> Đảm bảo thư mục <code>uploads/frames/</code> có đầy đủ ảnh</li>
            <li><strong>Test tính năng:</strong> Kiểm tra shop, mua khung, chọn khung</li>
        </ol>
    </div>
</body>
</html>

