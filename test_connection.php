<?php
/**
 * Script kiểm tra kết nối database
 * Chạy file này để kiểm tra xem kết nối database có hoạt động không
 */

require 'db_connect.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiểm Tra Kết Nối Database</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
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
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Kiểm Tra Kết Nối Database</h1>

        <?php
        // Kiểm tra kết nối
        if (!$conn) {
            echo '<div class="error">❌ Không thể kết nối database!</div>';
            exit;
        }

        if ($conn->connect_error) {
            echo '<div class="error">❌ Lỗi kết nối: ' . $conn->connect_error . '</div>';
            exit;
        }

        echo '<div class="success">✅ Kết nối database thành công!</div>';

        // Lấy thông tin database
        $info = [];
        
        // Tên database
        $result = $conn->query("SELECT DATABASE() as db");
        if ($result) {
            $row = $result->fetch_assoc();
            $info['Database'] = $row['db'];
        }

        // Phiên bản MySQL
        $result = $conn->query("SELECT VERSION() as version");
        if ($result) {
            $row = $result->fetch_assoc();
            $info['MySQL Version'] = $row['version'];
        }

        // User hiện tại
        $result = $conn->query("SELECT USER() as user");
        if ($result) {
            $row = $result->fetch_assoc();
            $info['Current User'] = $row['user'];
        }

        // Số bảng
        $result = $conn->query("SHOW TABLES");
        $tableCount = $result ? $result->num_rows : 0;
        $info['Total Tables'] = $tableCount;

        // Kiểm tra các bảng quan trọng
        $importantTables = ['users', 'chat_frames', 'avatar_frames', 'user_chat_frames', 'user_avatar_frames'];
        $existingTables = [];
        
        if ($result) {
            while ($row = $result->fetch_array()) {
                $existingTables[] = $row[0];
            }
        }

        // Hiển thị thông tin
        echo '<div class="info">';
        echo '<h2>📊 Thông Tin Database</h2>';
        echo '<table>';
        foreach ($info as $key => $value) {
            echo "<tr><th>$key</th><td>$value</td></tr>";
        }
        echo '</table>';
        echo '</div>';

        // Kiểm tra bảng quan trọng
        echo '<div class="info">';
        echo '<h2>🔍 Kiểm Tra Bảng Quan Trọng</h2>';
        echo '<table>';
        echo '<tr><th>Tên Bảng</th><th>Trạng Thái</th></tr>';
        
        foreach ($importantTables as $table) {
            $exists = in_array($table, $existingTables);
            $status = $exists 
                ? '<span style="color: green;">✅ Tồn tại</span>' 
                : '<span style="color: red;">❌ Chưa có</span>';
            echo "<tr><td>$table</td><td>$status</td></tr>";
        }
        
        echo '</table>';
        echo '</div>';

        // Kiểm tra cột trong bảng users
        echo '<div class="info">';
        echo '<h2>🔍 Kiểm Tra Cột Trong Bảng Users</h2>';
        
        if (in_array('users', $existingTables)) {
            $result = $conn->query("SHOW COLUMNS FROM users");
            $columns = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $columns[] = $row['Field'];
                }
            }
            
            $importantColumns = ['Iduser', 'Name', 'Money', 'ImageURL', 'chat_frame_id', 'avatar_frame_id'];
            
            echo '<table>';
            echo '<tr><th>Tên Cột</th><th>Trạng Thái</th></tr>';
            
            foreach ($importantColumns as $col) {
                $exists = in_array($col, $columns);
                $status = $exists 
                    ? '<span style="color: green;">✅ Có</span>' 
                    : '<span style="color: orange;">⚠️ Chưa có</span>';
                echo "<tr><td>$col</td><td>$status</td></tr>";
            }
            
            echo '</table>';
        } else {
            echo '<p style="color: red;">❌ Bảng users chưa tồn tại!</p>';
        }
        
        echo '</div>';

        // Đề xuất hành động
        echo '<div class="info">';
        echo '<h2>📋 Đề Xuất</h2>';
        
        $needsSync = false;
        $messages = [];
        
        if (!in_array('avatar_frames', $existingTables)) {
            $needsSync = true;
            $messages[] = "Cần tạo bảng <strong>avatar_frames</strong>";
        }
        
        if (!in_array('user_chat_frames', $existingTables)) {
            $needsSync = true;
            $messages[] = "Cần tạo bảng <strong>user_chat_frames</strong>";
        }
        
        if (!in_array('user_avatar_frames', $existingTables)) {
            $needsSync = true;
            $messages[] = "Cần tạo bảng <strong>user_avatar_frames</strong>";
        }
        
        if (in_array('users', $existingTables)) {
            $result = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar_frame_id'");
            if (!$result || $result->num_rows == 0) {
                $needsSync = true;
                $messages[] = "Cần thêm cột <strong>avatar_frame_id</strong> vào bảng users";
            }
        }
        
        if ($needsSync) {
            echo '<p style="color: orange;"><strong>⚠️ Cần đồng bộ database:</strong></p>';
            echo '<ul>';
            foreach ($messages as $msg) {
                echo "<li>$msg</li>";
            }
            echo '</ul>';
            echo '<p><strong>👉 Chạy file <code>sync_database_to_production.sql</code> để cập nhật!</strong></p>';
            echo '<p><a href="test_database_sync.php" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">🔍 Kiểm Tra Chi Tiết</a></p>';
            echo '<p><a href="run_sync_simple.php?pass=sync_db_2024" style="padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;">🚀 Chạy Đồng Bộ</a></p>';
        } else {
            echo '<p style="color: green;"><strong>✅ Database đã được cập nhật đầy đủ!</strong></p>';
            echo '<p>Bạn có thể sử dụng website bình thường.</p>';
        }
        
        echo '</div>';

        // Đóng kết nối
        $conn->close();
        ?>
    </div>
</body>
</html>

