<?php
/**
 * Cron: Weekly Leaderboard Reset
 * Chạy mỗi thứ 2 lúc 0:05 sáng
 * Crontab: 5 0 * * 1 php /path/to/cron_weekly_leaderboard.php
 */

// Chặn chạy qua trình duyệt trừ khi có flag đặc biệt
if (php_sapi_name() !== 'cli' && !defined('ALLOW_CRON_WEB')) {
    http_response_code(403);
    die('Forbidden');
}

require_once __DIR__ . '/db_connect.php';

// ── GUARD: Chống chạy 2 cron song song (DB Advisory Lock) ──────────────────
$lockName    = 'cron_weekly_leaderboard';
$lockTimeout = 0; // Không chờ — thoát ngay lập tức nếu đang có process giữ
$lockResult  = $conn->query("SELECT GET_LOCK('$lockName', $lockTimeout) as locked")->fetch_assoc()['locked'];
if ((int)$lockResult !== 1) {
    logCron("⚠️ Cron weekly đang chạy ở process khác, bỏ qua lần này.");
    die("⚠️ Cron đang chạy ở process khác, bỏ qua lần này.\n");
}
logCron("🔒 Lock acquired — bắt đầu xử lý Reset BXH Tuần.");

$log = [];
// Tuần hiện tại (thứ 2 vừa rồi)
$weekStart = date('Y-m-d', strtotime('last monday', strtotime('tomorrow'))); 
// Tuần VỪA KẾT THÚC (thứ 2 tuần trước)
$prevWeekStart = date('Y-m-d', strtotime('-7 days', strtotime($weekStart)));
$prevWeekEnd   = date('Y-m-d', strtotime('-1 day', strtotime($weekStart)));

logCron("=== Weekly Leaderboard Reset ===");
logCron("Xử lý tuần: $prevWeekStart → $prevWeekEnd");

// Phần thưởng theo hạng
$rewards = [
    1 => ['gtlm' => 5000000, 'badge_name' => 'Vương Giả Trận Địa', 'badge_icon' => '👑', 'color' => '#FFD700', 'title_class' => 'sparkle-text'],
    2 => ['gtlm' => 2000000, 'badge_name' => 'Nhất Lưu Cao Thủ',  'badge_icon' => '🥈', 'color' => '#C0C0C0', 'title_class' => 'sparkle-gold'],
    3 => ['gtlm' => 1000000, 'badge_name' => 'Tuyệt Đỉnh Hạng Ba', 'badge_icon' => '🥉', 'color' => '#CD7F32', 'title_class' => 'sparkle-gold'],
    4 => ['gtlm' => 500000,  'badge_name' => 'Cao Thủ Top 10',     'badge_icon' => '🏅', 'color' => '#9C27B0', 'title_class' => ''],
    5 => ['gtlm' => 500000,  'badge_name' => 'Cao Thủ Top 10',     'badge_icon' => '🏅', 'color' => '#9C27B0', 'title_class' => ''],
    6 => ['gtlm' => 500000,  'badge_name' => 'Cao Thủ Top 10',     'badge_icon' => '🏅', 'color' => '#9C27B0', 'title_class' => ''],
    7 => ['gtlm' => 500000,  'badge_name' => 'Cao Thủ Top 10',     'badge_icon' => '🏅', 'color' => '#9C27B0', 'title_class' => ''],
    8 => ['gtlm' => 500000,  'badge_name' => 'Cao Thủ Top 10',     'badge_icon' => '🏅', 'color' => '#9C27B0', 'title_class' => ''],
    9 => ['gtlm' => 500000,  'badge_name' => 'Cao Thủ Top 10',     'badge_icon' => '🏅', 'color' => '#9C27B0', 'title_class' => ''],
    10=> ['gtlm' => 500000,  'badge_name' => 'Cao Thủ Top 10',     'badge_icon' => '🏅', 'color' => '#9C27B0', 'title_class' => ''],
];

$conn->begin_transaction();

try {
    // 1. Tính BXH tuần vừa rồi từ game_history
    $stmt = $conn->prepare("
        SELECT 
            gh.user_id,
            u.Name,
            u.ImageURL,
            SUM(CASE WHEN gh.is_win = 1 THEN gh.win_amount - gh.bet_amount ELSE -gh.bet_amount END) as net_winnings,
            COUNT(*) as total_games
        FROM game_history gh
        JOIN users u ON gh.user_id = u.Iduser
        WHERE gh.played_at >= ? AND gh.played_at < ?
          AND u.Email NOT REGEXP '^bot[0-9]+@'  -- Loại bot
        GROUP BY gh.user_id
        HAVING net_winnings > 0               -- Chỉ lấy người có lãi
        ORDER BY net_winnings DESC
        LIMIT 50
    ");
    $stmt->bind_param("ss", $prevWeekStart, $weekStart);
    $stmt->execute();
    $rankings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($rankings)) {
        logCron("Không có dữ liệu tuần này, bỏ qua.");
        $conn->commit();
        exit;
    }

    // 2. Lưu snapshot
    $insertSnap = $conn->prepare("
        INSERT INTO weekly_leaderboard_snapshots 
            (user_id, week_start, total_winnings, total_games, rank_position)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            total_winnings = VALUES(total_winnings),
            total_games    = VALUES(total_games),
            rank_position  = VALUES(rank_position)
    ");

    foreach ($rankings as $rank => $player) {
        $position = $rank + 1;
        $insertSnap->bind_param(
            "isdii",
            $player['user_id'],
            $prevWeekStart,
            $player['net_winnings'],
            $player['total_games'],
            $position
        );
        $insertSnap->execute();
    }
    $insertSnap->close();
    logCron("Đã lưu snapshot " . count($rankings) . " người chơi.");

    // 3. Trao thưởng top 3
    require_once 'api_notifications.php';
    foreach ($rewards as $position => $reward) {
        if (!isset($rankings[$position - 1])) continue;

        $winner = $rankings[$position - 1];
        $userId = (int) $winner['user_id'];

        // Kiểm tra đã trao chưa (tránh chạy cron 2 lần)
        $checkStmt = $conn->prepare("
            SELECT id FROM weekly_reward_log 
            WHERE user_id = ? AND week_start = ?
        ");
        $checkStmt->bind_param("is", $userId, $prevWeekStart);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            logCron("Top $position ({$winner['Name']}) đã nhận thưởng rồi, bỏ qua.");
            $checkStmt->close();
            continue;
        }
        $checkStmt->close();

        // Cộng GTLM
        $gtlm = $reward['gtlm'];
        $moneyStmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
        $moneyStmt->bind_param("di", $gtlm, $userId);
        $moneyStmt->execute();
        $moneyStmt->close();

        // Trao badge đặc biệt
        $badgeId = ensureWeeklyBadge($conn, $reward, $prevWeekStart);
        if ($badgeId) {
            $badgeStmt = $conn->prepare("
                INSERT IGNORE INTO user_achievements (user_id, achievement_id, unlocked_at)
                VALUES (?, ?, NOW())
            ");
            $badgeStmt->bind_param("ii", $userId, $badgeId);
            $badgeStmt->execute();
            $badgeStmt->close();
        }

        // Ghi log trao thưởng
        $logLogStmt = $conn->prepare("
            INSERT INTO weekly_reward_log (user_id, week_start, rank_position, reward_gtlm, reward_badge_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $logLogStmt->bind_param("isidi", $userId, $prevWeekStart, $position, $gtlm, $badgeId);
        $logLogStmt->execute();
        $logLogStmt->close();

        // [NEW] Cấp Khung Chat Danh Vọng
        $chatFrameName = 'Vinh Quang Hạng ' . $position;
        $frameCheck = $conn->query("SELECT id FROM chat_frames WHERE name = '$chatFrameName'");
        if ($frameCheck && $frameCheck->num_rows > 0) {
            $frameId = $frameCheck->fetch_assoc()['id'];
        } else {
            // Tạo Khung Chat Mới nếu chưa có
            $desc = 'Khung Chat Độc Quyền Dành Cho Top ' . $position . ' Tuần';
            $conn->query("INSERT INTO chat_frames (name, description, rarity, price) VALUES ('$chatFrameName', '$desc', 'Legendary', 0)");
            $frameId = $conn->insert_id;
        }
        if ($frameId) {
            $conn->query("INSERT IGNORE INTO user_chat_frames (user_id, chat_frame_id) VALUES ($userId, $frameId)");
            $conn->query("UPDATE users SET chat_frame_id = $frameId WHERE Iduser = $userId");
        }

        // Gửi thông báo cho người thắng
        $rankLabels = [
            1 => '🥇 Hạng 1', 2 => '🥈 Hạng 2', 3 => '🥉 Hạng 3',
            4 => '🏅 Hạng 4', 5 => '🏅 Hạng 5', 6 => '🏅 Hạng 6',
            7 => '🏅 Hạng 7', 8 => '🏅 Hạng 8', 9 => '🏅 Hạng 9', 10 => '🏅 Hạng 10'
        ];
        $rankLabel = isset($rankLabels[$position]) ? $rankLabels[$position] : "Hạng $position";
        $weekLabelStr  = date('d/m/Y', strtotime($prevWeekStart));
        $notifMsg = "Chúc mừng! Bạn đạt $rankLabel BXH tuần $weekLabelStr. Phần thưởng: +".number_format($gtlm)." GTLM và huy hiệu vinh danh đã được trao!";
        sendNotification($conn, $userId, "🎁 PHẦN THƯỞNG TUẦN", $notifMsg, 'weekly_reward');

        // Đánh dấu đã nhận trong snapshot
        $conn->query("
            UPDATE weekly_leaderboard_snapshots 
            SET reward_claimed = 1 
            WHERE user_id = $userId AND week_start = '$prevWeekStart'
        ");

        logCron("✅ Top $position: {$winner['Name']} → +" . number_format($gtlm) . " GTLM + badge");
    }

    // 4. Thông báo toàn server trên chat
    broadcastWeeklyResults($conn, $rankings, $prevWeekStart, $rewards);

    $conn->commit();
    logCron("=== Hoàn tất ===");

} catch (Exception $e) {
    $conn->rollback();
    logCron("❌ LỖI: " . $e->getMessage());
    exit(1);
}

// --- Helper functions ---

function ensureWeeklyBadge(mysqli $conn, array $reward, string $weekStart): ?int {
    // Badge mang tên tuần cụ thể, VD: "🏆 Vô địch tuần 2025-W20"
    $weekLabel = date('Y-\WW', strtotime($weekStart));
    $badgeName = "{$reward['badge_icon']} {$reward['badge_name']} $weekLabel";
    $desc = "Đạt hạng cao nhất BXH tuần $weekLabel";

    $check = $conn->prepare("SELECT id FROM achievements WHERE name = ? LIMIT 1");
    $check->bind_param("s", $badgeName);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    $check->close();

    if ($row) return (int) $row['id'];

    // Tạo badge mới cho tuần này
    $ins = $conn->prepare("
        INSERT INTO achievements (name, description, icon, requirement_type, requirement_value, category)
        VALUES (?, ?, ?, 'weekly_reward', 1, 'weekly')
    ");
    $ins->bind_param("sss", $badgeName, $desc, $reward['badge_icon']);
    $ins->execute();
    $id = (int) $conn->insert_id;
    $ins->close();
    return $id;
}

function broadcastWeeklyResults(mysqli $conn, array $rankings, string $weekStart, array $rewards): void {
    if (empty($rankings)) return;

    $weekLabelStr = date('d/m/Y', strtotime($weekStart));
    $lines = ["🏆 KẾT QUẢ BXH TUẦN $weekLabelStr:"];

    foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10] as $pos) {
        if (!isset($rankings[$pos - 1])) continue;
        $p = $rankings[$pos - 1];
        $icon  = $rewards[$pos]['badge_icon'];
        $gtlm  = number_format($rewards[$pos]['gtlm'], 0, ',', '.');
        $lines[] = "$icon Hạng $pos: {$p['Name']} (+".number_format($p['net_winnings'])." GTLM lãi) → Nhận $gtlm GTLM";
    }
    $lines[] = "🎯 Tuần mới đã bắt đầu! Ai sẽ là nhà vô địch tiếp theo?";

    $msg = implode("\n", $lines);
    
    require_once 'vocabulary_helper.php';
    $msg = VocabularyHelper::mask($msg);

    $botId  = 1; // ID bot MC hoặc system
    $botName = 'Hệ Thống';

    $stmt = $conn->prepare("
        INSERT INTO chat_messages (user_id, username, message, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iss", $botId, $botName, $msg);
    $stmt->execute();
    $stmt->close();
}

function logCron(string $msg): void {
    $line = "[" . date('H:i:s') . "] $msg\n";
    echo $line;
    $dir = __DIR__ . '/bot/logs/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($dir . 'cron_weekly_' . date('Y-m-d') . '.log', $line, FILE_APPEND);
}
