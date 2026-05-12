<?php
require 'db_connect.php';

echo "<h2>Dọn dẹp & Khởi tạo hệ thống Crafting</h2>";

// 1. Cập nhật bảng items để có Rarity
$tables = ['themes', 'cursors', 'chat_frames', 'avatar_frames'];
foreach ($tables as $table) {
    $res = $conn->query("SHOW COLUMNS FROM $table LIKE 'rarity'");
    if ($res->num_rows == 0) {
        $conn->query("ALTER TABLE $table ADD COLUMN rarity ENUM('Common', 'Rare', 'Epic', 'Legendary') DEFAULT 'Common'");
        echo "✅ Đã thêm cột 'rarity' vào bảng $table<br>";
    }
}

// 2. Tạo bảng Công thức (Recipes)
$conn->query("CREATE TABLE IF NOT EXISTS crafting_recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    output_type ENUM('theme', 'cursor', 'chat_frame', 'avatar_frame') NOT NULL,
    output_item_id INT NOT NULL,
    gtlm_cost DECIMAL(15,2) DEFAULT 0,
    success_rate INT DEFAULT 100, -- Phần trăm (0-100)
    input_requirements JSON NOT NULL, -- Ví dụ: {'theme': 3, 'gtlm': 50000}
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
echo "✅ Đã tạo bảng 'crafting_recipes'<br>";

// 3. Tạo bảng Lịch sử Crafting
$conn->query("CREATE TABLE IF NOT EXISTS crafting_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    recipe_id INT NOT NULL,
    is_success BOOLEAN NOT NULL,
    gtlm_spent DECIMAL(15,2),
    crafted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(Iduser)
)");
echo "✅ Đã tạo bảng 'crafting_logs'<br>";

// 4. Thêm một số công thức mẫu
$checkRecipes = $conn->query("SELECT COUNT(*) as total FROM crafting_recipes");
if ($checkRecipes->fetch_assoc()['total'] == 0) {
    // Giả sử ta có theme ID 4 là Rare
    $conn->query("INSERT INTO crafting_recipes (name, output_type, output_item_id, gtlm_cost, success_rate, input_requirements, description) VALUES 
    ('Nâng cấp Theme Huyền Thoại', 'theme', 4, 100000, 75, '{\"theme\": 3}', 'Kết hợp 3 Themes bất kỳ và 100k GTLM để có cơ hội nhận Theme đặc biệt.'),
    ('Rèn Khung Avatar Epic', 'avatar_frame', 2, 50000, 90, '{\"avatar_frame\": 2}', 'Kết hợp 2 Khung Avatar và 50k GTLM.')");
    echo "✅ Đã thêm công thức mẫu<br>";
}

echo "<br><a href='crafting.php'>Đến trang Workshop -></a>";
?>
