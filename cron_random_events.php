<?php
/**
 * Cron: Random Event Engine
 * Chạy mỗi 5 phút để kiểm tra và bật event
 * Crontab: * /5 * * * * php /path/to/cron_random_events.php
 */

// Chặn chạy qua trình duyệt trừ khi có flag
if (php_sapi_name() !== 'cli' && !defined('ALLOW_EVENT_WEB')) {
    http_response_code(403);
    die('Forbidden');
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/vocabulary_helper.php';

// ── Danh sách 5 loại event ──────────────────────────────────────────
$eventTypes = [
    'golden_hour' => [
        'name'        => '⚡ Giờ Vàng XP',
        'description' => 'Tất cả XP nhận được x2 trong 15 phút!',
        'duration'    => 15,
        'weight'      => 30,
        'config'      => ['xp_multiplier' => 2],
    ],
    'money_rain' => [
        'name'        => '💸 Mưa GTLM',
        'description' => 'Gõ !nhận trong chat hoặc click banner để húp GTLM ngẫu nhiên!',
        'duration'    => 15,
        'weight'      => 20,
        'config'      => ['min_reward' => 500, 'max_reward' => 50000, 'max_claims' => 50],
    ],
    'double_win' => [
        'name'        => '🔥 Nhân Đôi Chiến Thắng',
        'description' => 'Thắng ván nào trong 15 phút này húp gấp đôi GTLM!',
        'duration'    => 15,
        'weight'      => 15,
        'config' => ['win_multiplier' => 2, 'max_bonus_per_win' => 500000],
    ],
    'mystery_box' => [
        'name'        => '🎁 Hộp Quà Bí Ẩn',
        'description' => 'Mở ngay hộp quà để húp phần thưởng ngẫu nhiên cực khủng!',
        'duration'    => 15,
        'weight'      => 20,
        'config'      => [
            'prizes' => [
                ['type' => 'gtlm',  'amount' => 5000,   'weight' => 40, 'label' => '5,000 GTLM'],
                ['type' => 'gtlm',  'amount' => 50000,  'weight' => 30, 'label' => '50,000 GTLM'],
                ['type' => 'gtlm',  'amount' => 200000, 'weight' => 15, 'label' => '200,000 GTLM'],
                ['type' => 'xp',    'amount' => 1000,   'weight' => 14, 'label' => '+1,000 XP'],
                ['type' => 'gtlm',  'amount' => 5000000,'weight' => 1,  'label' => '🎉 5,000,000 GTLM'],
            ]
        ],
    ],
    'lucky_number' => [
        'name'        => '🔢 Số May Mắn',
        'description' => 'Đoán đúng số từ 1-10, húp ngay 200,000 GTLM!',
        'duration'    => 15,
        'weight'      => 10,
        'config'      => ['number_range' => 10, 'reward' => 200000, 'max_winners' => 5],
    ],
    'sudden_boss' => [
        'name'        => '🐉 Huyết Ma Long Đột Kích',
        'description' => 'World Boss bất ngờ xuất hiện! Đánh ngay kẻo lỡ!',
        'duration'    => 30, // 30 phút
        'weight'      => 10,
        'config'      => ['boss_id' => 1], // Trigger boss ID 1
    ],
    'weekend_boost' => [
        'name'        => '🎉 Lễ Hội Cuối Tuần',
        'description' => 'Gấp 3 phần thưởng chiến thắng và +50% XP mọi hoạt động!',
        'duration'    => 60, // 1 tiếng
        'weight'      => 10,
        'config'      => ['win_multiplier' => 3, 'xp_multiplier' => 1.5],
    ],
];

// If today is not weekend, remove weekend_boost from weight calculation
$isWeekend = (date('N') >= 6);
if (!$isWeekend) {
    $eventTypes['weekend_boost']['weight'] = 0;
}

// If world boss is already active, remove sudden_boss from weight calculation
$isBossActive = $conn->query("SELECT id FROM world_boss WHERE status = 'active'")->fetch_assoc();
if ($isBossActive) {
    $eventTypes['sudden_boss']['weight'] = 0;
}

// 1. Check có nên bật event không
$activeEvent = $conn->query("
    SELECT id FROM random_events 
    WHERE is_active = 1 AND ends_at > NOW() 
    LIMIT 1
")->fetch_assoc();

if ($activeEvent) {
    echo "Event đang chạy (id={$activeEvent['id']}), bỏ qua.\n";
    exit;
}

// Lấy event cuối cùng đã chạy
$lastEvent = $conn->query("
    SELECT ended_real_at FROM random_events 
    ORDER BY id DESC LIMIT 1
")->fetch_assoc();

// Cooldown ngẫu nhiên 2-4 tiếng
if (!isset($_GET['force'])) {
    $cooldown = rand(2, 4) * 3600;
    $lastTime  = $lastEvent ? strtotime($lastEvent['ended_real_at'] ?? 'now') : 0;
    if (time() - $lastTime < $cooldown) {
        echo "Còn trong cooldown, bỏ qua.\n";
        exit;
    }
}

// 2. Roll event ngẫu nhiên theo weight
$totalWeight = array_sum(array_column($eventTypes, 'weight'));
$roll = rand(1, $totalWeight);
$cum  = 0;
$chosen = null;
foreach ($eventTypes as $type => $cfg) {
    $cum += $cfg['weight'];
    if ($roll <= $cum) { $chosen = $type; break; }
}
if (!$chosen) $chosen = 'golden_hour';

$event   = $eventTypes[$chosen];
$endsAt  = date('Y-m-d H:i:s', time() + $event['duration'] * 60);

// Generate a truly random lucky number instead of formula
if ($chosen === 'lucky_number') {
    $event['config']['lucky_number'] = rand(1, $event['config']['number_range']);
}

$configJson = json_encode($event['config']);

// 3. Insert event vào DB
$stmt = $conn->prepare("
    INSERT INTO random_events (event_type, event_name, description, config, ends_at)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param("sssss",
    $chosen,
    $event['name'],
    $event['description'],
    $configJson,
    $endsAt
);
$stmt->execute();
$eventId = (int)$conn->insert_id;
$stmt->close();

// ⚡ Wake up the World Boss if sudden_boss
// Bug fix: Đảm bảo boss thực sự được kích hoạt, có fallback INSERT nếu chưa tồn tại,
// và gửi thông báo đến tất cả user đang online.
if ($chosen === 'sudden_boss') {
    $bossId = (int)$event['config']['boss_id'];
    $maxHp  = 500000000;

    // Tạo boss nếu chưa tồn tại (lần đầu)
    $conn->query("
        INSERT IGNORE INTO world_boss (id, name, hp, max_hp, status, last_spawn)
        VALUES ($bossId, 'Huyết Ma Long', $maxHp, $maxHp, 'inactive', '2000-01-01 00:00:00')
    ");

    // Kích hoạt boss
    $bossUpdated = $conn->query("
        UPDATE world_boss 
        SET hp         = $maxHp, 
            max_hp     = $maxHp, 
            status     = 'active', 
            last_spawn = NOW() 
        WHERE id = $bossId
    ");

    // Reset bảng sát thương của đợt mới
    $conn->query("DELETE FROM world_boss_damage WHERE boss_id = $bossId");

    if ($bossUpdated && $conn->affected_rows >= 0) {
        echo "⚡ World Boss (id=$bossId) đã được kích hoạt!\n";

        // Gửi thông báo cho tất cả user đang online (active 30 phút gần nhất)
        $bossNotif = "🐉 HUYẾT MA LONG đột nhiên xuất hiện! Vào ngay World Boss để chiến đấu!";
        $conn->query("
            INSERT IGNORE INTO notifications (user_id, title, message, type) 
            SELECT Iduser,
                   '⚔️ BOSS ĐỘT KÍCH!',
                   '$bossNotif',
                   'world_boss'
            FROM users 
            WHERE last_active > NOW() - INTERVAL 30 MINUTE
              AND Email NOT REGEXP '^bot[0-9]+@'
        ");
    } else {
        echo "⚠️ Không tìm thấy world_boss với id=$bossId để kích hoạt!\n";
    }
}

// 4. Thông báo chat toàn server
$chatMsg = "🚨 SỰ KIỆN BẤT NGỜ: {$event['name']}\n"
         . "{$event['description']}\n"
         . "⏰ Kết thúc sau {$event['duration']} phút!";

$chatMsg = VocabularyHelper::mask($chatMsg);

$sysId   = 1;
$sysName = 'Hệ Thống';
$msgStmt = $conn->prepare("
    INSERT INTO chat_messages (user_id, username, message, created_at)
    VALUES (?, ?, ?, NOW())
");
$msgStmt->bind_param("iss", $sysId, $sysName, $chatMsg);
$msgStmt->execute();
$msgStmt->close();

// 5. Notification cho tất cả user đang online
$notifMsg = "🎉 {$event['name']} đang diễn ra! Tham gia ngay!";
$conn->query("
    INSERT IGNORE INTO notifications (user_id, title, message, type) 
    SELECT Iduser, '🎁 SỰ KIỆN MỚI', '$notifMsg', 'random_event' 
    FROM users 
    WHERE last_active > NOW() - INTERVAL 30 MINUTE 
      AND Email NOT REGEXP '^bot[0-9]+@'
");

echo "✅ Đã bật event: {$event['name']} (id=$eventId) đến $endsAt\n";
?>
