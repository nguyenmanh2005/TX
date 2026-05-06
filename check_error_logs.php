<?php
/**
 * Script để xem lỗi PHP và kiểm tra database
 */

// Bật hiển thị lỗi
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);

echo "<h1>🔍 Kiểm Tra Lỗi</h1>";
echo "<style>
    body { font-family: Arial; max-width: 1200px; margin: 20px auto; padding: 20px; }
    .success { color: green; padding: 10px; background: #d4edda; margin: 10px 0; border-radius: 5px; }
    .error { color: red; padding: 10px; background: #f8d7da; margin: 10px 0; border-radius: 5px; }
    .warning { color: orange; padding: 10px; background: #fff3cd; margin: 10px 0; border-radius: 5px; }
    .info { color: blue; padding: 10px; background: #d1ecf1; margin: 10px 0; border-radius: 5px; }
    pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow: auto; }
</style>";

// Test 1: Kiểm tra PHP version
echo "<h2>1. PHP Version</h2>";
echo "<div class='info'>PHP Version: " . phpversion() . "</div>";

// Test 2: Kiểm tra kết nối database
echo "<h2>2. Database Connection</h2>";
try {
    require 'db_connect.php';
    if ($conn && !$conn->connect_error) {
        echo "<div class='success'>✅ Kết nối database thành công!</div>";
        echo "<div class='info'>Server: $servername<br>Database: $dbname</div>";
    } else {
        echo "<div class='error'>❌ Không thể kết nối database!</div>";
        if ($conn) {
            echo "<div class='error'>Lỗi: " . htmlspecialchars($conn->connect_error) . "</div>";
        }
        die();
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Exception: " . htmlspecialchars($e->getMessage()) . "</div>";
    die();
}

// Test 3: Kiểm tra các bảng quan trọng
echo "<h2>3. Kiểm Tra Bảng Database</h2>";
$requiredTables = ['users', 'themes', 'achievements', 'chat_frames', 'avatar_frames'];
$missingTables = [];

foreach ($requiredTables as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check && $check->num_rows > 0) {
        echo "<div class='success'>✅ Bảng <strong>$table</strong> tồn tại</div>";
    } else {
        echo "<div class='warning'>⚠️ Bảng <strong>$table</strong> chưa tồn tại</div>";
        $missingTables[] = $table;
    }
}

// Test 4: Kiểm tra cột trong bảng users
echo "<h2>4. Kiểm Tra Cột Trong Bảng Users</h2>";
$requiredColumns = ['Iduser', 'Name', 'Money', 'ImageURL', 'Role', 'current_theme_id', 'active_title_id', 'chat_frame_id', 'avatar_frame_id'];
$result = $conn->query("SHOW COLUMNS FROM users");
$existingColumns = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $existingColumns[] = $row['Field'];
    }
}

foreach ($requiredColumns as $col) {
    if (in_array($col, $existingColumns)) {
        echo "<div class='success'>✅ Cột <strong>$col</strong> tồn tại</div>";
    } else {
        echo "<div class='warning'>⚠️ Cột <strong>$col</strong> chưa tồn tại</div>";
    }
}

// Test 5: Test query user
echo "<h2>5. Test Query User</h2>";
session_start();
if (isset($_SESSION['Iduser'])) {
    $userId = $_SESSION['Iduser'];
    $sql = "SELECT u.Iduser, u.Name, u.Money, u.active_title_id, u.Role, u.current_theme_id,
            a.icon as title_icon, a.name as title_name
            FROM users u
            LEFT JOIN achievements a ON u.active_title_id = a.id
            WHERE u.Iduser = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            echo "<div class='success'>✅ Query user thành công!</div>";
            echo "<div class='info'>Name: " . htmlspecialchars($user['Name']) . "<br>Money: " . number_format($user['Money']) . "</div>";
        } else {
            echo "<div class='error'>❌ Không tìm thấy user với ID: $userId</div>";
        }
        $stmt->close();
    } else {
        echo "<div class='error'>❌ Lỗi prepare statement: " . htmlspecialchars($conn->error) . "</div>";
    }
} else {
    echo "<div class='warning'>⚠️ Chưa đăng nhập (Session không có Iduser)</div>";
}

// Test 6: Kiểm tra file
echo "<h2>6. Kiểm Tra File</h2>";
$requiredFiles = [
    'db_connect.php',
    'api_check_rank_achievements.php',
    'assets/css/main.css'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "<div class='success'>✅ File <strong>$file</strong> tồn tại</div>";
    } else {
        echo "<div class='warning'>⚠️ File <strong>$file</strong> không tồn tại</div>";
    }
}

// Test 7: Kiểm tra file api_check_rank_achievements.php
echo "<h2>7. Kiểm Tra api_check_rank_achievements.php</h2>";
if (file_exists('api_check_rank_achievements.php')) {
    echo "<div class='success'>✅ File tồn tại</div>";
    // Kiểm tra syntax
    $errors = [];
    $file_content = file_get_contents('api_check_rank_achievements.php');
    if (strpos($file_content, 'function checkAndAwardRankAchievements') !== false) {
        echo "<div class='success'>✅ Hàm checkAndAwardRankAchievements tồn tại</div>";
    } else {
        echo "<div class='error'>❌ Hàm checkAndAwardRankAchievements không tồn tại</div>";
    }
} else {
    echo "<div class='error'>❌ File không tồn tại</div>";
}

// Test 8: Kiểm tra themes query
echo "<h2>8. Test Themes Query</h2>";
$checkThemesTable = $conn->query("SHOW TABLES LIKE 'themes'");
if ($checkThemesTable && $checkThemesTable->num_rows > 0) {
    $themeSql = "SELECT * FROM themes WHERE id = 1";
    $themeResult = $conn->query($themeSql);
    if ($themeResult && $themeResult->num_rows > 0) {
        $theme = $themeResult->fetch_assoc();
        echo "<div class='success'>✅ Theme mặc định tồn tại</div>";
        echo "<pre>" . print_r($theme, true) . "</pre>";
    } else {
        echo "<div class='warning'>⚠️ Theme mặc định (id=1) không tồn tại</div>";
    }
} else {
    echo "<div class='warning'>⚠️ Bảng themes chưa tồn tại - sẽ dùng giá trị mặc định</div>";
}

echo "<hr>";
echo "<h2>📋 Đề Xuất</h2>";

if (!empty($missingTables)) {
    echo "<div class='warning'>";
    echo "<strong>Cần tạo các bảng sau:</strong><ul>";
    foreach ($missingTables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    echo "<p>👉 Chạy file <code>sync_database_to_production.sql</code> để tạo các bảng</p>";
    echo "</div>";
}

echo "<p><a href='index.php' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Thử lại index.php</a></p>";
echo "<p><a href='debug_index.php' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>Debug chi tiết</a></p>";
?>

