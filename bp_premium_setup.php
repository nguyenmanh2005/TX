<?php
require 'db_connect.php';

echo "<h2>Nâng cấp Battle Pass: Premium Track</h2>";

// 1. Thêm cột has_premium vào bp_stats
$res = $conn->query("SHOW COLUMNS FROM bp_stats LIKE 'has_premium'");
if ($res->num_rows == 0) {
    $conn->query("ALTER TABLE bp_stats ADD COLUMN has_premium TINYINT DEFAULT 0");
    $conn->query("ALTER TABLE bp_stats ADD COLUMN premium_claimed_rewards TEXT");
    echo "✅ Đã thêm cột Premium vào 'bp_stats'<br>";
}

// 2. Tạo bảng Phần thưởng (Rewards) để quản lý Free & Premium
$conn->query("CREATE TABLE IF NOT EXISTS bp_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level INT NOT NULL,
    type ENUM('free', 'premium') NOT NULL,
    reward_type ENUM('money', 'item', 'skin') DEFAULT 'money',
    reward_value VARCHAR(255), -- Số tiền hoặc ID vật phẩm
    reward_name VARCHAR(100),
    icon VARCHAR(50) DEFAULT '🎁',
    UNIQUE KEY unique_reward (level, type)
)");
echo "✅ Đã tạo bảng 'bp_rewards'<br>";

// 3. Thêm dữ liệu mẫu cho 10 level đầu tiên
$checkRewards = $conn->query("SELECT COUNT(*) as total FROM bp_rewards");
if ($checkRewards->fetch_assoc()['total'] == 0) {
    for ($i = 1; $i <= 10; $i++) {
        // Free rewards
        $freeMoney = $i * 10000;
        $conn->query("INSERT INTO bp_rewards (level, type, reward_type, reward_value, reward_name, icon) 
                      VALUES ($i, 'free', 'money', '$freeMoney', '$freeMoney GTLM', '💰')");
        
        // Premium rewards (Hấp dẫn hơn nhiều)
        $premiumMoney = $i * 50000;
        $icon = ($i % 5 == 0) ? '👑' : '💎';
        $name = ($i % 5 == 0) ? "Vật phẩm Exclusive cấp $i" : "$premiumMoney GTLM";
        $rType = ($i % 5 == 0) ? 'item' : 'money';
        
        $conn->query("INSERT INTO bp_rewards (level, type, reward_type, reward_value, reward_name, icon) 
                      VALUES ($i, 'premium', '$rType', '$premiumMoney', '$name', '$icon')");
    }
    echo "✅ Đã tạo phần thưởng mẫu cho 10 level<br>";
}

echo "<br><a href='battle_pass.php'>Đến trang Battle Pass -></a>";
?>
