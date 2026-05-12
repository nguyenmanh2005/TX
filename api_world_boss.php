<?php
session_start();
require_once 'db_connect.php';

$userId = $_SESSION['Iduser'] ?? 0;
if (!$userId) exit(json_encode(['success' => false]));

// 1. Khởi tạo Database World Boss
$setup = "
CREATE TABLE IF NOT EXISTS world_boss (
    id INT PRIMARY KEY,
    name VARCHAR(100),
    health BIGINT,
    max_health BIGINT,
    status ENUM('alive', 'dead') DEFAULT 'dead',
    last_killed_at TIMESTAMP NULL,
    next_respawn_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS world_boss_damage (
    user_id INT PRIMARY KEY,
    damage BIGINT DEFAULT 0,
    last_attack TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Khởi tạo boss đầu tiên nếu chưa có
INSERT IGNORE INTO world_boss (id, name, health, max_health, status) VALUES (1, 'Hắc Long Thần', 1000000000, 1000000000, 'alive');
";
// $conn->multi_query($setup);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_status':
        $boss = $conn->query("SELECT * FROM world_boss WHERE id = 1")->fetch_assoc();
        
        // Lấy Top 5 sát thương
        $top = $conn->query("SELECT d.*, u.Name FROM world_boss_damage d JOIN users u ON d.user_id = u.Iduser ORDER BY d.damage DESC LIMIT 5");
        $rank = [];
        while ($r = $top->fetch_assoc()) $rank[] = $r;
        
        echo json_encode(['success' => true, 'boss' => $boss, 'rank' => $rank]);
        break;

    case 'attack':
        $boss = $conn->query("SELECT * FROM world_boss WHERE id = 1")->fetch_assoc();
        if ($boss['status'] == 'dead') exit(json_encode(['success' => false, 'message' => 'Boss đã bị tiêu diệt!']));

        // Sát thương ngẫu nhiên dựa trên cấp độ hoặc tiền (Ví dụ: 10k - 50k)
        $dmg = rand(10000, 50000);
        
        $conn->begin_transaction();
        try {
            // Giảm máu boss
            $newHealth = max(0, $boss['health'] - $dmg);
            $status = ($newHealth <= 0) ? 'dead' : 'alive';
            $killedAt = ($newHealth <= 0) ? 'NOW()' : 'last_killed_at';
            
            $conn->query("UPDATE world_boss SET health = $newHealth, status = '$status', last_killed_at = $killedAt WHERE id = 1");
            
            // Ghi nhận sát thương
            $conn->query("INSERT INTO world_boss_damage (user_id, damage) VALUES ($userId, $dmg) ON DUPLICATE KEY UPDATE damage = damage + $dmg");

            // Nếu boss chết, phát thưởng nhanh (Ví dụ: 100k GTLM cho mỗi người tham gia)
            if ($newHealth <= 0) {
                // Thưởng cho người kết liễu
                $conn->query("UPDATE users SET Money = Money + 1000000 WHERE Iduser = $userId");
            }

            $conn->commit();
            echo json_encode(['success' => true, 'damage' => $dmg, 'boss_health' => $newHealth]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
}
