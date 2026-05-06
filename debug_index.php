<?php
/**
 * Debug file để kiểm tra lỗi index.php
 */

// Bật hiển thị lỗi
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>Debug Index.php</h1>";

// Test 1: Kiểm tra session
echo "<h2>Test 1: Session</h2>";
session_start();
if (!isset($_SESSION['Iduser'])) {
    echo "❌ Chưa đăng nhập. <a href='login.php'>Đăng nhập</a><br>";
    echo "Session ID: " . session_id() . "<br>";
} else {
    echo "✅ Đã đăng nhập. User ID: " . $_SESSION['Iduser'] . "<br>";
}

// Test 2: Kiểm tra kết nối database
echo "<h2>Test 2: Database Connection</h2>";
try {
    require 'db_connect.php';
    if ($conn && !$conn->connect_error) {
        echo "✅ Kết nối database thành công!<br>";
        echo "Server: " . $servername . "<br>";
        echo "Database: " . $dbname . "<br>";
        
        // Test query
        $testQuery = "SELECT 1 as test";
        $result = $conn->query($testQuery);
        if ($result) {
            echo "✅ Query test thành công!<br>";
        } else {
            echo "❌ Query test thất bại: " . $conn->error . "<br>";
        }
    } else {
        echo "❌ Không thể kết nối database!<br>";
        if ($conn) {
            echo "Lỗi: " . $conn->connect_error . "<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}

// Test 3: Kiểm tra file có tồn tại
echo "<h2>Test 3: File Existence</h2>";
$requiredFiles = [
    'db_connect.php',
    'api_check_rank_achievements.php',
    'assets/css/main.css'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "✅ $file tồn tại<br>";
    } else {
        echo "❌ $file KHÔNG tồn tại<br>";
    }
}

// Test 4: Kiểm tra bảng database
echo "<h2>Test 4: Database Tables</h2>";
if (isset($conn) && $conn && !$conn->connect_error) {
    $tables = ['users', 'chat_frames', 'avatar_frames', 'achievements', 'themes'];
    foreach ($tables as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check && $check->num_rows > 0) {
            echo "✅ Bảng $table tồn tại<br>";
        } else {
            echo "⚠️ Bảng $table chưa tồn tại<br>";
        }
    }
}

// Test 5: Kiểm tra query user
echo "<h2>Test 5: User Query</h2>";
if (isset($conn) && $conn && !$conn->connect_error && isset($_SESSION['Iduser'])) {
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
            echo "✅ Query user thành công!<br>";
            echo "Name: " . htmlspecialchars($user['Name']) . "<br>";
            echo "Money: " . number_format($user['Money']) . "<br>";
        } else {
            echo "❌ Không tìm thấy user!<br>";
        }
        $stmt->close();
    } else {
        echo "❌ Lỗi prepare statement: " . $conn->error . "<br>";
    }
} else {
    echo "⚠️ Không thể test (chưa đăng nhập hoặc không kết nối database)<br>";
}

echo "<hr>";
echo "<p><a href='index.php'>Quay lại index.php</a></p>";
echo "<p><a href='test_connection.php'>Kiểm tra kết nối database</a></p>";
?>

