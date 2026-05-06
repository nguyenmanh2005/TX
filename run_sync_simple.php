<?php
/**
 * Script đơn giản để chạy đồng bộ database
 * Chạy từng phần một cách an toàn
 */

// BẢO MẬT: Sử dụng environment variable hoặc file config
$SECRET_PASSWORD = getenv('SYNC_DB_PASSWORD') ?: 'sync_db_2024'; // Lấy từ environment variable
$password = $_POST['pass'] ?? $_GET['pass'] ?? ''; // Ưu tiên POST hơn GET

if (empty($password) || $password !== $SECRET_PASSWORD) {
    die('❌ Không có quyền! Vui lòng cung cấp password hợp lệ.');
}

require 'db_connect.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đồng Bộ Database</title>
    <style>
        body { font-family: Arial; max-width: 900px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0; }
        button { padding: 12px 24px; font-size: 16px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Đồng Bộ Database</h1>
        <div class="warning">⚠️ Đảm bảo đã BACKUP database trước!</div>

        <?php
        if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['sync'])) {
            echo '<h2>Đang chạy...</h2>';
            
            $steps = [];
            $steps[] = ["CREATE TABLE IF NOT EXISTS avatar_frames (id INT AUTO_INCREMENT PRIMARY KEY, frame_name VARCHAR(100) NOT NULL, ImageURL VARCHAR(255) NOT NULL, description TEXT, rarity VARCHAR(20) DEFAULT 'common', price DECIMAL(15, 2) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Tạo bảng avatar_frames"];
            
            $steps[] = ["CREATE TABLE IF NOT EXISTS user_chat_frames (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, chat_frame_id INT NOT NULL, purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE, FOREIGN KEY (chat_frame_id) REFERENCES chat_frames(id) ON DELETE CASCADE, UNIQUE KEY unique_user_chat_frame (user_id, chat_frame_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Tạo bảng user_chat_frames"];
            
            $steps[] = ["CREATE TABLE IF NOT EXISTS user_avatar_frames (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, avatar_frame_id INT NOT NULL, purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE, FOREIGN KEY (avatar_frame_id) REFERENCES avatar_frames(id) ON DELETE CASCADE, UNIQUE KEY unique_user_avatar_frame (user_id, avatar_frame_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Tạo bảng user_avatar_frames"];
            
            // Thêm cột price vào chat_frames
            $check = $conn->query("SHOW COLUMNS FROM chat_frames LIKE 'price'");
            if (!$check || $check->num_rows == 0) {
                $steps[] = ["ALTER TABLE chat_frames ADD COLUMN price DECIMAL(15, 2) NOT NULL DEFAULT 0", "Thêm cột price vào chat_frames"];
            } else {
                echo "<div class='warning'>⚠ Cột price đã tồn tại trong chat_frames</div>";
            }
            
            // Thêm cột avatar_frame_id vào users
            $check = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar_frame_id'");
            if (!$check || $check->num_rows == 0) {
                $steps[] = ["ALTER TABLE users ADD COLUMN avatar_frame_id INT NULL", "Thêm cột avatar_frame_id vào users"];
            } else {
                echo "<div class='warning'>⚠ Cột avatar_frame_id đã tồn tại trong users</div>";
            }
            
            // Thêm dữ liệu mẫu
            $steps[] = ["INSERT IGNORE INTO chat_frames (id, frame_name, ImageURL, description, rarity, price) VALUES (1, 'Khung Mặc Định', 'uploads/default_chat_frame.png', 'Khung chat cơ bản', 'common', 0)", "Thêm khung chat mặc định"];
            
            $steps[] = ["INSERT IGNORE INTO avatar_frames (id, frame_name, ImageURL, description, rarity, price) VALUES (1, 'Khung Avatar Mặc Định', 'uploads/default_avatar_frame.png', 'Khung avatar cơ bản', 'common', 0)", "Thêm khung avatar mặc định"];
            
            // Chạy từng bước
            $success = 0;
            $errors = 0;
            
            foreach ($steps as $step) {
                $sql = $step[0];
                $desc = $step[1];
                
                try {
                    if ($conn->query($sql)) {
                        echo "<div class='success'>✅ $desc</div>";
                        $success++;
                    } else {
                        $error = $conn->error;
                        if (strpos($error, 'Duplicate') === false && strpos($error, 'already exists') === false) {
                            echo "<div class='error'>❌ $desc: $error</div>";
                            $errors++;
                        } else {
                            echo "<div class='warning'>⚠ $desc: Đã tồn tại (bỏ qua)</div>";
                        }
                    }
                } catch (Exception $e) {
                    echo "<div class='error'>❌ $desc: " . $e->getMessage() . "</div>";
                    $errors++;
                }
            }
            
            echo "<h3>Kết quả: ✅ $success thành công, ❌ $errors lỗi</h3>";
            echo "<p><a href='test_database_sync.php?pass=$password'>🔍 Kiểm tra database</a></p>";
        } else {
            ?>
            <form method="post">
                <button type="submit" name="sync" onclick="return confirm('Đã backup database chưa?')">
                    🚀 Chạy Đồng Bộ
                </button>
            </form>
            <?php
        }
        ?>
    </div>
</body>
</html>

