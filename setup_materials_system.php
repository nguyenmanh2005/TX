<?php
include 'db_connect.php';

$queries = [
    // 1. Materials Catalog
    "CREATE TABLE IF NOT EXISTS materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        icon VARCHAR(20),
        rarity ENUM('common', 'uncommon', 'rare', 'epic', 'legendary') DEFAULT 'common',
        family ENUM('energy', 'dust', 'ore', 'seasonal', 'scrap') NOT NULL,
        expires_in_days INT NULL,
        is_tradeable TINYINT DEFAULT 1,
        is_active TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // 2. User Materials (Inventory)
    "CREATE TABLE IF NOT EXISTS user_materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        material_id INT NOT NULL,
        quantity INT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY(user_id, material_id)
    )",

    // 3. Material Transactions (Audit)
    "CREATE TABLE IF NOT EXISTS material_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        material_id INT NOT NULL,
        quantity INT NOT NULL,
        source ENUM('game_drop', 'daily_login', 'dungeon', 'event', 'craft_cost', 'combining', 'trade', 'admin') NOT NULL,
        source_ref VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // 4. Dungeons (Daily Tasks)
    "CREATE TABLE IF NOT EXISTS dungeons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE UNIQUE NOT NULL,
        name VARCHAR(100) NOT NULL,
        type ENUM('hunt', 'accumulate', 'streak', 'specialist', 'survivor', 'explorer') NOT NULL,
        game_required VARCHAR(100) NULL,
        tier1_target INT DEFAULT 0,
        tier2_target INT DEFAULT 0,
        tier3_target INT DEFAULT 0,
        is_auto_generated TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // 5. Dungeon Rewards
    "CREATE TABLE IF NOT EXISTS dungeon_rewards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dungeon_id INT NOT NULL,
        tier TINYINT NOT NULL,
        material_id INT NOT NULL,
        quantity INT NOT NULL,
        gtlm_bonus BIGINT DEFAULT 0
    )",

    // 6. Dungeon Completions
    "CREATE TABLE IF NOT EXISTS dungeon_completions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        dungeon_id INT NOT NULL,
        tier TINYINT NOT NULL,
        progress FLOAT DEFAULT 0,
        status ENUM('in_progress', 'completed', 'claimed', 'expired') DEFAULT 'in_progress',
        completed_at TIMESTAMP NULL,
        claimed_at TIMESTAMP NULL,
        UNIQUE KEY(user_id, dungeon_id, tier)
    )",

    // 7. Combining Recipes
    "CREATE TABLE IF NOT EXISTS combining_recipes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        inputs JSON NOT NULL,
        output_material_id INT NOT NULL,
        output_quantity INT DEFAULT 1,
        success_rate TINYINT DEFAULT 100,
        gtlm_cost BIGINT DEFAULT 0
    )",

    // 8. Pity System Tracking
    "CREATE TABLE IF NOT EXISTS material_pity (
        user_id INT PRIMARY KEY,
        rare_counter INT DEFAULT 0,
        epic_counter INT DEFAULT 0,
        legendary_counter INT DEFAULT 0
    )"
];

foreach ($queries as $sql) {
    if ($conn->query($sql)) {
        echo "Query executed successfully.\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}

// Update crafting_recipes table if it exists
$check = $conn->query("SHOW COLUMNS FROM crafting_recipes LIKE 'material_requirements'");
if ($check && $check->num_rows == 0) {
    $conn->query("ALTER TABLE crafting_recipes ADD COLUMN material_requirements JSON AFTER input_requirements");
    echo "Added material_requirements to crafting_recipes.\n";
}

// Seed Data for Materials
$materials = [
    // Energy Shards
    ['fire_shard', 'Mảnh Lửa', 'Mảnh năng lượng rực cháy từ chiến thắng.', '🔥', 'common', 'energy'],
    ['ice_shard', 'Mảnh Băng', 'Mảnh năng lượng giá lạnh từ các game bài.', '❄️', 'common', 'energy'],
    ['thunder_shard', 'Mảnh Sấm', 'Năng lượng mạnh mẽ từ chuỗi thắng.', '⚡', 'uncommon', 'energy'],
    ['dark_shard', 'Mảnh Bóng Tối', 'Năng lượng u tối từ những ván cược lớn.', '🌑', 'rare', 'energy'],
    ['void_shard', 'Mảnh Hư Không', 'Năng lượng huyền bí từ Jackpot.', '🌌', 'epic', 'energy'],
    ['dragon_soul', 'Linh Hồn Rồng', 'Linh hồn cổ xưa từ World Boss.', '🐉', 'legendary', 'energy'],

    // Dust & Essence
    ['silver_dust', 'Bụi Bạc', 'Bụi tinh tú từ điểm danh hàng ngày.', '🌫️', 'common', 'dust'],
    ['gold_dust', 'Bụi Vàng', 'Bụi quý hiếm từ chuỗi điểm danh.', '✨', 'uncommon', 'dust'],
    ['moon_essence', 'Tinh Chất Trăng', 'Tinh hoa hội tụ từ điểm danh 30 ngày.', '🌙', 'rare', 'dust'],
    ['sun_essence', 'Tinh Chất Mặt Trời', 'Tinh hoa rực rỡ từ điểm danh 60 ngày.', '☀️', 'epic', 'dust'],
    ['eternal_drop', 'Giọt Vĩnh Cửu', 'Giọt nước thời gian từ chuỗi 100 ngày.', '💧', 'legendary', 'dust'],

    // Ores
    ['copper_ore', 'Quặng Đồng', 'Khoáng thạch từ Dungeon dễ.', '🪨', 'common', 'ore'],
    ['silver_ore', 'Quặng Bạc', 'Khoáng thạch từ Dungeon vừa.', '🔩', 'uncommon', 'ore'],
    ['gold_ore', 'Quặng Vàng', 'Khoáng thạch từ Dungeon khó.', '🥇', 'rare', 'ore'],
    ['red_crystal', 'Pha Lê Đỏ', 'Pha lê cực hiếm từ Dungeon Ác Mộng.', '💎', 'epic', 'ore'],
    ['meteor_stone', 'Thiên Thạch', 'Mảnh vỡ từ vũ trụ rơi vào Boss tuần.', '☄️', 'legendary', 'ore'],

    // Seasonal
    ['red_packet', 'Xu Đỏ', 'Tiền may mắn từ sự kiện Tết.', '🧧', 'rare', 'seasonal'],
    ['ghost_nut', 'Hạt Dẻ Ma', 'Vật phẩm kỳ bí từ Halloween.', '🎃', 'rare', 'seasonal'],
    ['snow_flake', 'Bông Tuyết', 'Mảnh băng tinh khiết từ Giáng Sinh.', '❄️', 'rare', 'seasonal'],
    ['cherry_blossom', 'Hoa Anh Đào', 'Cánh hoa rực rỡ mùa Xuân.', '🌸', 'rare', 'seasonal'],
    ['summer_flame', 'Ngọn Lửa Hè', 'Sức nóng rực cháy mùa Hè.', '🌞', 'rare', 'seasonal'],

    // Scrap
    ['scrap_cloth', 'Vải Rách', 'Phế liệu từ việc phân rã Theme cũ.', '🧵', 'common', 'scrap'],
    ['glass_shard', 'Mảnh Kính', 'Phế liệu từ việc phân rã Cursor cũ.', '🔮', 'common', 'scrap'],
    ['rusty_frame', 'Khung Gỉ', 'Phế liệu từ việc phân rã Avatar Frame cũ.', '⚙️', 'common', 'scrap'],
    ['refine_t1', 'Tinh Luyện Cấp 1', 'Nguyên liệu trung gian đã qua xử lý.', '🧪', 'uncommon', 'scrap'],
    ['refine_t2', 'Tinh Luyện Cấp 2', 'Nguyên liệu cao cấp đã được tinh chiết.', '🧬', 'rare', 'scrap'],
];

foreach ($materials as $m) {
    $stmt = $conn->prepare("INSERT IGNORE INTO materials (code, name, description, icon, rarity, family) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $m[0], $m[1], $m[2], $m[3], $m[4], $m[5]);
    $stmt->execute();
}

echo "Materials seeded successfully.\n";
?>
