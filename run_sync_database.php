<?php
/**
 * Script chạy đồng bộ database trên server
 * 
 * CẢNH BÁO: Chỉ chạy script này một lần sau khi đã backup database!
 * 
 * Cách sử dụng:
 * 1. Backup database trước
 * 2. Upload file này lên server
 * 3. Truy cập: http://yourdomain.com/run_sync_database.php
 * 4. Xóa file này sau khi chạy xong (bảo mật)
 */

// BẢO MẬT: Thêm password để tránh người khác chạy
$SECRET_PASSWORD = 'change_this_password_123'; // ĐỔI PASSWORD NÀY!
$password = $_GET['pass'] ?? '';

if ($password !== $SECRET_PASSWORD) {
    die('❌ Không có quyền truy cập! Vui lòng nhập password: ?pass=your_password');
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
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
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
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 10px 0; }
        button {
            padding: 12px 24px;
            font-size: 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        button:hover { background: #0056b3; }
        button.danger { background: #dc3545; }
        button.danger:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Đồng Bộ Database Lên Server</h1>
        <div class="warning">
            ⚠️ <strong>CẢNH BÁO:</strong> Script này sẽ thay đổi database. Đảm bảo đã backup trước!
        </div>

        <?php
        if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
            if ($_POST['action'] === 'sync') {
                echo '<div class="container">';
                echo '<h2>Đang chạy script đồng bộ...</h2>';
                
                // Đọc file SQL
                $sqlFile = 'sync_database_to_production.sql';
                if (!file_exists($sqlFile)) {
                    die('<div class="error">❌ Không tìm thấy file sync_database_to_production.sql</div>');
                }
                
                $sql = file_get_contents($sqlFile);
                
                // Tách các câu lệnh SQL
                $statements = array_filter(
                    array_map('trim', explode(';', $sql)),
                    function($stmt) {
                        return !empty($stmt) && 
                               !preg_match('/^--/', $stmt) && 
                               !preg_match('/^SET @/', $stmt) &&
                               !preg_match('/^PREPARE/', $stmt) &&
                               !preg_match('/^EXECUTE/', $stmt) &&
                               !preg_match('/^DEALLOCATE/', $stmt);
                    }
                );
                
                $success = 0;
                $errors = 0;
                $warnings = [];
                
                // Xử lý các biến MySQL trước
                $conn->query("SET @dbname = DATABASE()");
                
                foreach ($statements as $statement) {
                    // Bỏ qua các dòng comment
                    if (empty($statement) || strpos(trim($statement), '--') === 0) {
                        continue;
                    }
                    
                    // Xử lý các câu lệnh đặc biệt
                    if (preg_match('/SET @(\w+) = (.+);/', $statement, $matches)) {
                        $varName = $matches[1];
                        $varValue = $matches[2];
                        $conn->query("SET @$varName = $varValue");
                        continue;
                    }
                    
                    if (preg_match('/PREPARE (\w+) FROM (.+);/', $statement, $matches)) {
                        // Xử lý PREPARE statement
                        continue;
                    }
                    
                    if (preg_match('/EXECUTE (\w+);/', $statement, $matches)) {
                        // Xử lý EXECUTE statement
                        continue;
                    }
                    
                    if (preg_match('/DEALLOCATE PREPARE (\w+);/', $statement, $matches)) {
                        // Xử lý DEALLOCATE statement
                        continue;
                    }
                    
                    try {
                        // Thực thi câu lệnh
                        if ($conn->query($statement)) {
                            $success++;
                            echo "<div class='success'>✓ Đã chạy: " . substr($statement, 0, 50) . "...</div>";
                        } else {
                            $errors++;
                            $errorMsg = $conn->error;
                            // Bỏ qua lỗi "Duplicate" vì đó là bình thường
                            if (strpos($errorMsg, 'Duplicate') === false && 
                                strpos($errorMsg, 'already exists') === false) {
                                echo "<div class='error'>✗ Lỗi: " . htmlspecialchars($errorMsg) . "</div>";
                                echo "<div class='error'>Câu lệnh: " . htmlspecialchars(substr($statement, 0, 100)) . "...</div>";
                            } else {
                                $warnings[] = "Đã bỏ qua (đã tồn tại): " . substr($statement, 0, 50);
                            }
                        }
                    } catch (Exception $e) {
                        $errors++;
                        echo "<div class='error'>✗ Exception: " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                }
                
                // Xử lý các câu lệnh đặc biệt về cột
                echo '<h3>Xử lý các cột đặc biệt...</h3>';
                
                // Thêm cột price vào chat_frames
                $checkColumn = $conn->query("SHOW COLUMNS FROM chat_frames LIKE 'price'");
                if (!$checkColumn || $checkColumn->num_rows == 0) {
                    $conn->query("ALTER TABLE chat_frames ADD COLUMN price DECIMAL(15, 2) NOT NULL DEFAULT 0 AFTER rarity");
                    echo "<div class='success'>✓ Đã thêm cột price vào chat_frames</div>";
                } else {
                    echo "<div class='warning'>⚠ Cột price đã tồn tại trong chat_frames</div>";
                }
                
                // Thêm cột avatar_frame_id vào users
                $checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar_frame_id'");
                if (!$checkColumn || $checkColumn->num_rows == 0) {
                    $conn->query("ALTER TABLE users ADD COLUMN avatar_frame_id INT NULL AFTER chat_frame_id");
                    echo "<div class='success'>✓ Đã thêm cột avatar_frame_id vào users</div>";
                } else {
                    echo "<div class='warning'>⚠ Cột avatar_frame_id đã tồn tại trong users</div>";
                }
                
                echo '</div>';
                
                echo '<div class="container">';
                echo '<h2>📊 Kết Quả</h2>';
                echo "<p class='success'>✅ Thành công: $success câu lệnh</p>";
                if ($errors > 0) {
                    echo "<p class='error'>❌ Lỗi: $errors câu lệnh</p>";
                }
                if (!empty($warnings)) {
                    echo "<p class='warning'>⚠ Cảnh báo: " . count($warnings) . " câu lệnh</p>";
                    foreach ($warnings as $warning) {
                        echo "<div class='warning'>$warning</div>";
                    }
                }
                echo '</div>';
                
                // Kiểm tra kết quả
                echo '<div class="container">';
                echo '<h2>🔍 Kiểm Tra Kết Quả</h2>';
                
                $checks = [
                    'Bảng avatar_frames' => "SHOW TABLES LIKE 'avatar_frames'",
                    'Bảng user_chat_frames' => "SHOW TABLES LIKE 'user_chat_frames'",
                    'Bảng user_avatar_frames' => "SHOW TABLES LIKE 'user_avatar_frames'",
                    'Cột price trong chat_frames' => "SHOW COLUMNS FROM chat_frames LIKE 'price'",
                    'Cột avatar_frame_id trong users' => "SHOW COLUMNS FROM users LIKE 'avatar_frame_id'"
                ];
                
                foreach ($checks as $name => $query) {
                    $result = $conn->query($query);
                    $exists = $result && $result->num_rows > 0;
                    $status = $exists ? "<span class='success'>✓ Có</span>" : "<span class='error'>✗ Chưa có</span>";
                    echo "<p>$name: $status</p>";
                }
                
                echo '</div>';
                
                echo '<div class="container info">';
                echo '<h3>✅ Hoàn thành!</h3>';
                echo '<p>Đã chạy script đồng bộ database. Vui lòng:</p>';
                echo '<ol>';
                echo '<li>Kiểm tra kết quả ở trên</li>';
                echo '<li>Upload ảnh khung lên thư mục uploads/frames/</li>';
                echo '<li>Test tính năng trên website</li>';
                echo '<li><strong>XÓA FILE NÀY</strong> để bảo mật!</li>';
                echo '</ol>';
                echo '</div>';
            }
        } else {
            ?>
            <div class="info">
                <h3>📋 Hướng dẫn:</h3>
                <ol>
                    <li><strong>BACKUP database trước!</strong></li>
                    <li>Đảm bảo file <code>sync_database_to_production.sql</code> nằm cùng thư mục</li>
                    <li>Click nút "Chạy Đồng Bộ" bên dưới</li>
                    <li>Đợi script chạy xong và kiểm tra kết quả</li>
                    <li><strong>XÓA FILE NÀY</strong> sau khi hoàn thành để bảo mật!</li>
                </ol>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="sync">
                <button type="submit" onclick="return confirm('Bạn đã backup database chưa?')">
                    🚀 Chạy Đồng Bộ Database
                </button>
            </form>
            <?php
        }
        ?>
    </div>
    
    <div class="container">
        <p><strong>⚠️ Lưu ý bảo mật:</strong> Xóa file này sau khi chạy xong!</p>
        <p><a href="test_database_sync.php?pass=<?= htmlspecialchars($password) ?>">🔍 Kiểm tra database</a></p>
    </div>
</body>
</html>

