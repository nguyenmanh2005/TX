<?php
/**
 * File test để kiểm tra Lucky Wheel API
 * Truy cập: test_lucky_wheel.php
 */

session_start();
require 'db_connect.php';

header('Content-Type: text/html; charset=UTF-8');

echo "<h1>Test Lucky Wheel API</h1>";

// Kiểm tra session
if (!isset($_SESSION['Iduser'])) {
    echo "<p style='color: red;'>❌ Chưa đăng nhập. Vui lòng đăng nhập trước.</p>";
    exit();
}

$userId = $_SESSION['Iduser'];
echo "<p>✅ User ID: $userId</p>";

// Kiểm tra bảng
echo "<h2>1. Kiểm tra bảng database:</h2>";
$tables = ['lucky_wheel_rewards', 'lucky_wheel_logs'];
foreach ($tables as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check && $check->num_rows > 0) {
        echo "<p style='color: green;'>✅ Bảng $table tồn tại</p>";
        
        // Đếm số records
        $count = $conn->query("SELECT COUNT(*) as count FROM $table")->fetch_assoc()['count'];
        echo "<p>   - Số records: $count</p>";
    } else {
        echo "<p style='color: red;'>❌ Bảng $table KHÔNG tồn tại. Vui lòng chạy create_lucky_wheel_tables.sql</p>";
    }
}

// Kiểm tra rewards
echo "<h2>2. Kiểm tra rewards:</h2>";
$checkRewards = $conn->query("SHOW TABLES LIKE 'lucky_wheel_rewards'");
if ($checkRewards && $checkRewards->num_rows > 0) {
    $rewards = $conn->query("SELECT * FROM lucky_wheel_rewards WHERE is_active = 1 ORDER BY id ASC");
    if ($rewards && $rewards->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Tên</th><th>Loại</th><th>Giá trị</th><th>Xác suất</th><th>Màu</th></tr>";
        while ($reward = $rewards->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$reward['id']}</td>";
            echo "<td>{$reward['reward_name']}</td>";
            echo "<td>{$reward['reward_type']}</td>";
            echo "<td>{$reward['reward_value']}</td>";
            echo "<td>{$reward['probability']}%</td>";
            echo "<td style='background: {$reward['color']};'>{$reward['color']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Kiểm tra tổng probability
        $totalProb = $conn->query("SELECT SUM(probability) as total FROM lucky_wheel_rewards WHERE is_active = 1")->fetch_assoc()['total'];
        echo "<p>Tổng probability: <strong>$totalProb%</strong>";
        if ($totalProb != 100) {
            echo " <span style='color: red;'>⚠️ Cảnh báo: Tổng probability phải = 100%</span>";
        } else {
            echo " <span style='color: green;'>✅ OK</span>";
        }
        echo "</p>";
    } else {
        echo "<p style='color: red;'>❌ Không có rewards nào được kích hoạt</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Bảng lucky_wheel_rewards không tồn tại</p>";
}

// Kiểm tra lịch sử quay
echo "<h2>3. Kiểm tra lịch sử quay của user:</h2>";
$checkLogs = $conn->query("SHOW TABLES LIKE 'lucky_wheel_logs'");
if ($checkLogs && $checkLogs->num_rows > 0) {
    $today = date('Y-m-d');
    $hasSpunToday = $conn->query("SELECT * FROM lucky_wheel_logs WHERE user_id = $userId AND spin_date = '$today'");
    if ($hasSpunToday && $hasSpunToday->num_rows > 0) {
        echo "<p style='color: orange;'>⚠️ User đã quay wheel hôm nay</p>";
        $lastSpin = $hasSpunToday->fetch_assoc();
        echo "<p>Lần quay cuối: {$lastSpin['reward_name']} - {$lastSpin['reward_value']} VNĐ</p>";
    } else {
        echo "<p style='color: green;'>✅ User chưa quay wheel hôm nay</p>";
    }
    
    // Lịch sử 10 lần gần nhất
    $history = $conn->query("SELECT * FROM lucky_wheel_logs WHERE user_id = $userId ORDER BY spun_at DESC LIMIT 10");
    if ($history && $history->num_rows > 0) {
        echo "<h3>Lịch sử 10 lần gần nhất:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Ngày</th><th>Phần thưởng</th><th>Giá trị</th></tr>";
        while ($item = $history->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$item['spin_date']}</td>";
            echo "<td>{$item['reward_name']}</td>";
            echo "<td>{$item['reward_value']} VNĐ</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Chưa có lịch sử quay</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Bảng lucky_wheel_logs không tồn tại</p>";
}

// Test API endpoints
echo "<h2>4. Test API endpoints:</h2>";
echo "<p><a href='api_lucky_wheel.php?action=get_rewards' target='_blank'>Test get_rewards</a></p>";
echo "<p><a href='api_lucky_wheel.php?action=check_spin' target='_blank'>Test check_spin</a></p>";
echo "<p><a href='api_lucky_wheel.php?action=get_history' target='_blank'>Test get_history</a></p>";

echo "<hr>";
echo "<p><a href='lucky_wheel.php'>← Quay lại Lucky Wheel</a></p>";
echo "<p><a href='index.php'>← Về trang chủ</a></p>";
?>

