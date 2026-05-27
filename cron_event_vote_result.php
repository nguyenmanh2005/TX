<?php
/**
 * Cron: Event Vote Result Processor
 * ─────────────────────────────────────────────────────────────────
 * Đọc kết quả bình chọn của sự kiện vừa kết thúc, xác định option
 * thắng cuộc và kích hoạt buff/random_event tương ứng.
 *
 * Chạy SAU khi sự kiện kết thúc (ví dụ: mỗi giờ, hoặc ngay khi
 * seasonal_event chuyển từ active → ended).
 * Crontab:  0 * * * * php /path/to/cron_event_vote_result.php
 *
 * Bug fix #1: Trước đây vote chỉ lưu số phiếu vào DB nhưng không
 * có cơ chế nào đọc option thắng và kích hoạt hiệu ứng.
 */

// Cho phép chạy qua web khi có flag debug (CLI luôn được phép)
if (php_sapi_name() !== 'cli' && !isset($_GET['debug'])) {
    http_response_code(403);
    die('Forbidden');
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/vocabulary_helper.php';

echo "=== Cron: Event Vote Result ===\n";

// ── 1. Tìm sự kiện seasonal vừa kết thúc mà vote chưa được xử lý ──
$endedEvents = $conn->query("
    SELECT se.id, se.title
    FROM seasonal_events se
    WHERE se.ends_at < NOW()
      AND se.status IN ('active', 'ended')
      AND NOT EXISTS (
          SELECT 1 FROM event_vote_results evr WHERE evr.event_id = se.id
      )
    ORDER BY se.ends_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Đảm bảo bảng lưu kết quả vote tồn tại
$conn->query("CREATE TABLE IF NOT EXISTS event_vote_results (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    event_id    INT NOT NULL UNIQUE,
    option_id   INT NOT NULL,
    option_title VARCHAR(255),
    votes       INT DEFAULT 0,
    buff_type   VARCHAR(50),
    processed_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

if (empty($endedEvents)) {
    echo "Không có sự kiện nào cần xử lý kết quả vote.\n";
    // Không thoát — vẫn xử lý active event nếu đang trong thời gian sự kiện
}

// ── 2. Map tên option → loại buff kích hoạt ──────────────────────
// Key: chuỗi tìm kiếm (không phân biệt hoa thường) trong title option
// Value: mảng mô tả buff tương ứng
$BUFF_MAP = [
    'pvp'      => [
        'type'        => 'pvp_boost',
        'description' => '⚔️ Buff PvP: Phần thưởng PvP x1.5 trong 2 giờ!',
        'event_type'  => 'double_win',         // reuse double_win framework
        'config'      => ['win_multiplier' => 1.5, 'max_bonus_per_win' => 1000000],
        'duration_min'=> 120,
    ],
    'jackpot'  => [
        'type'        => 'jackpot_boost',
        'description' => '🎰 Buff Jackpot: Tỉ lệ nổ hũ tăng x3 trong 2 giờ!',
        'event_type'  => 'jackpot_boost',
        'config'      => ['jackpot_rate_multiplier' => 3, 'duration_min' => 120],
        'duration_min'=> 120,
    ],
    'lễ hội'   => [
        'type'        => 'festival_xp',
        'description' => '🍀 Buff Lễ Hội: XP x2 và tỉ lệ rớt đồ tăng trong 2 giờ!',
        'event_type'  => 'golden_hour',
        'config'      => ['xp_multiplier' => 2],
        'duration_min'=> 120,
    ],
    'may mắn'  => [
        'type'        => 'festival_xp',
        'description' => '🍀 Buff May Mắn: XP x2 và Jackpot dễ nổ hơn trong 2 giờ!',
        'event_type'  => 'golden_hour',
        'config'      => ['xp_multiplier' => 2, 'jackpot_rate_multiplier' => 2],
        'duration_min'=> 120,
    ],
    'đua'      => [
        'type'        => 'speedrun_boost',
        'description' => '🏃 Buff Speedrun: XP gấp 2 và phần thưởng nhiệm vụ x1.5 trong 2 giờ!',
        'event_type'  => 'golden_hour',
        'config'      => ['xp_multiplier' => 2, 'quest_reward_multiplier' => 1.5],
        'duration_min'=> 120,
    ],
    'chiến tranh' => [
        'type'        => 'guild_war_boost',
        'description' => '⚔️ Buff Guild War: Điểm Guild War x2 trong 2 giờ!',
        'event_type'  => 'double_win',
        'config'      => ['win_multiplier' => 2, 'max_bonus_per_win' => 2000000],
        'duration_min'=> 120,
    ],
];

// ── 3. Hàm chính: xử lý kết quả vote cho 1 event_id ─────────────
function processVoteResult(mysqli $conn, int $eventId, string $eventTitle, array $BUFF_MAP): void {
    // Lấy option thắng (nhiều phiếu nhất)
    $winner = $conn->query("
        SELECT id, title, votes
        FROM event_voting_options
        WHERE event_id = $eventId AND status = 'active'
        ORDER BY votes DESC
        LIMIT 1
    ")->fetch_assoc();

    if (!$winner || (int)$winner['votes'] === 0) {
        echo "  Sự kiện #$eventId ('$eventTitle'): không có ai vote, bỏ qua.\n";
        // Ghi nhận đã xử lý để không lặp lại
        $conn->query("INSERT IGNORE INTO event_vote_results (event_id, option_id, option_title, votes, buff_type)
                      VALUES ($eventId, 0, 'Không có vote', 0, 'none')");
        return;
    }

    $optionTitle = $winner['title'];
    $optionId    = (int)$winner['id'];
    $votes       = (int)$winner['votes'];

    echo "  Sự kiện #$eventId ('$eventTitle'): Option thắng = '$optionTitle' ($votes phiếu)\n";

    // Tìm buff phù hợp dựa trên tên option
    $buffDef = null;
    foreach ($BUFF_MAP as $keyword => $def) {
        if (mb_stripos($optionTitle, $keyword) !== false) {
            $buffDef = $def;
            break;
        }
    }

    // Fallback: nếu không khớp keyword nào → dùng golden_hour (XP x2)
    if (!$buffDef) {
        $buffDef = [
            'type'        => 'default_boost',
            'description' => '🎁 Buff Cộng Đồng: XP x2 trong 2 giờ theo lựa chọn của cộng đồng!',
            'event_type'  => 'golden_hour',
            'config'      => ['xp_multiplier' => 2],
            'duration_min'=> 120,
        ];
    }

    $durationMin = (int)($buffDef['duration_min'] ?? 120);
    $endsAt      = date('Y-m-d H:i:s', time() + $durationMin * 60);
    $configJson  = json_encode($buffDef['config']);
    $eventType   = $buffDef['event_type'];
    $eventName   = "🗳️ Kết Quả Vote: " . $optionTitle;
    $description = VocabularyHelper::mask($buffDef['description']);

    // Kiểm tra đã có random_event cùng loại đang chạy chưa
    $existing = $conn->query("
        SELECT id FROM random_events
        WHERE is_active = 1 AND event_type = '$eventType' AND ends_at > NOW()
        LIMIT 1
    ")->fetch_assoc();

    if (!$existing) {
        // Tạo random_event mới
        $stmt = $conn->prepare("
            INSERT INTO random_events (event_type, event_name, description, config, ends_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssss", $eventType, $eventName, $description, $configJson, $endsAt);
        $stmt->execute();
        $newEventId = (int)$conn->insert_id;
        $stmt->close();
        echo "  ✅ Đã kích hoạt buff '$eventType' (random_event id=$newEventId) đến $endsAt\n";
    } else {
        echo "  ℹ️ Buff '$eventType' đã đang chạy (id={$existing['id']}), không tạo thêm.\n";
    }

    // Ghi kết quả vote vào bảng lưu trữ
    $optTitleSafe = $conn->real_escape_string($optionTitle);
    $buffType     = $buffDef['type'];
    $conn->query("INSERT IGNORE INTO event_vote_results (event_id, option_id, option_title, votes, buff_type)
                  VALUES ($eventId, $optionId, '$optTitleSafe', $votes, '$buffType')");

    // Thông báo toàn server qua chat
    $chatMsg   = "🗳️ KẾT QUẢ BÌNH CHỌN SỰ KIỆN:\n"
               . "Cộng đồng đã chọn: «{$optionTitle}» với $votes phiếu!\n"
               . $buffDef['description']
               . "\n⏰ Buff có hiệu lực trong {$durationMin} phút tiếp theo!";
    $chatMsg   = VocabularyHelper::mask($chatMsg);
    $sysId     = 1;
    $sysName   = 'Hệ Thống';
    $msgStmt   = $conn->prepare("
        INSERT INTO chat_messages (user_id, username, message, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    if ($msgStmt) {
        $msgStmt->bind_param("iss", $sysId, $sysName, $chatMsg);
        $msgStmt->execute();
        $msgStmt->close();
    }

    // Thông báo push cho user đang online
    $notifMsg  = "🗳️ Kết quả vote: «{$optionTitle}» thắng! " . $buffDef['description'];
    $notifSafe = $conn->real_escape_string($notifMsg);
    $conn->query("
        INSERT IGNORE INTO notifications (user_id, title, message, type)
        SELECT Iduser,
               '🗳️ KẾT QUẢ BÌNH CHỌN',
               '$notifSafe',
               'event_vote'
        FROM users
        WHERE last_active > NOW() - INTERVAL 60 MINUTE
          AND Email NOT REGEXP '^bot[0-9]+@'
    ");

    echo "  📢 Đã gửi thông báo toàn server.\n";
}

// ── 4. Xử lý tất cả event cần giải quyết kết quả ────────────────
foreach ($endedEvents as $ev) {
    processVoteResult($conn, (int)$ev['id'], $ev['title'], $BUFF_MAP);
}

// ── 5. Trường hợp đặc biệt: active event đã quá thời gian vote    ──
// (seasonal_event vẫn còn active nhưng đã qua 80% thời gian → tổng kết sớm)
$earlyTally = $conn->query("
    SELECT se.id, se.title, se.ends_at,
           TIMESTAMPDIFF(SECOND, NOW(), se.ends_at) AS secs_left,
           TIMESTAMPDIFF(SECOND, se.starts_at, se.ends_at) AS total_secs
    FROM seasonal_events se
    WHERE se.status = 'active'
      AND se.starts_at <= NOW() AND se.ends_at >= NOW()
      AND NOT EXISTS (
          SELECT 1 FROM event_vote_results evr WHERE evr.event_id = se.id
      )
    HAVING (1 - secs_left / total_secs) >= 0.80
    LIMIT 1
")->fetch_assoc();

if ($earlyTally) {
    echo "\n⏳ Sự kiện #{$earlyTally['id']} đã qua 80% thời gian → tổng kết vote sớm:\n";
    processVoteResult($conn, (int)$earlyTally['id'], $earlyTally['title'], $BUFF_MAP);
}

echo "\n=== Hoàn tất ===\n";
?>
