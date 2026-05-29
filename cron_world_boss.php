<?php
/**
 * Cron: World Boss Spawner
 * ─────────────────────────────────────────────────────────────────
 * Kiểm tra và tự động hồi sinh World Boss (Đại Chiến Ma Thần)
 * vào các khung giờ 09:00, 15:00, 21:00.
 *
 * Crontab: * * * * * php /path/to/cron_world_boss.php
 */

if (php_sapi_name() !== 'cli' && !isset($_GET['debug'])) {
    http_response_code(403);
    die('Forbidden');
}

require_once __DIR__ . '/db_connect.php';

echo "=== Cron: World Boss Spawner ===\n";

$boss = $conn->query("SELECT * FROM world_boss WHERE id = 1")->fetch_assoc();
if (!$boss) {
    echo "Lỗi: Không tìm thấy Boss ID=1 trong CSDL.\n";
    exit;
}

$scheduleHours = [9, 15, 21];
$now = time();
$currentDate = date('Y-m-d');

$todaySpawns = [];
foreach ($scheduleHours as $hour) {
    $todaySpawns[] = strtotime("$currentDate $hour:00:00");
}
sort($todaySpawns);

$lastApplicableSpawn = 0;
foreach ($todaySpawns as $time) {
    if ($now >= $time) {
        $lastApplicableSpawn = $time;
    }
}

if ($lastApplicableSpawn === 0) {
    $yesterdayDate = date('Y-m-d', strtotime('-1 day'));
    $lastApplicableSpawn = strtotime("$yesterdayDate 21:00:00");
}

$lastSpawnTs = strtotime($boss['last_spawn'] ?? '2000-01-01 00:00:00');

if ($boss['status'] === 'defeated' && $lastSpawnTs < $lastApplicableSpawn) {
    // Hồi sinh Ma Thần với đầy 100% HP
    $maxHp = 500000000;
    $conn->query("
        UPDATE world_boss 
        SET hp = $maxHp, 
            max_hp = $maxHp, 
            status = 'active', 
            last_spawn = NOW() 
        WHERE id = 1
    ");
    
    // Reset bảng sát thương sự kiện cũ
    $conn->query("DELETE FROM world_boss_damage WHERE boss_id = 1");
    
    // Gửi thông báo hệ thống toàn máy chủ
    $msg = '🌋 MA THẦN HỦY DIỆT đã hồi sinh! Hãy tiến vào chiến trường tranh đoạt S-Tier!';
    $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, created_at) VALUES (1, 'Hệ Thống', ?, NOW())");
    if ($stmt) {
        $stmt->bind_param("s", $msg);
        $stmt->execute();
        $stmt->close();
    }
    
    echo "✅ Đã hồi sinh Ma Thần (Spawn time: " . date('Y-m-d H:i:s', $lastApplicableSpawn) . ").\n";
} else {
    if ($boss['status'] === 'active') {
        echo "ℹ️ Ma Thần đang trong trạng thái 'active'. Không cần hồi sinh.\n";
    } else {
        echo "ℹ️ Chưa đến giờ hồi sinh kế tiếp hoặc đã hồi sinh rồi.\n";
    }
}

echo "=== Hoàn tất ===\n";
?>
