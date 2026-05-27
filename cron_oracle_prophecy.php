<?php
/**
 * 🔮 Cron: Tự động chấm điểm Lời Tiên Tri cuối tuần
 * Chạy vào 23:00 Chủ Nhật (hoặc set cron job thích hợp)
 * 
 * Cách dùng: php cron_oracle_prophecy.php
 * Hoặc gọi qua URL nếu có IP whitelist
 */

// Chỉ chạy từ CLI hoặc localhost
if (php_sapi_name() !== 'cli') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1', '::1'])) {
        http_response_code(403);
        exit('Forbidden');
    }
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/lore_helper.php';
require_once __DIR__ . '/notification_helper.php';

echo "[" . date('Y-m-d H:i:s') . "] 🔮 Oracle Prophecy Cron Started\n";

// ─── 1. Tìm tuần đang active (đã qua ngày Chủ Nhật) ──────────────────────
$today   = date('Y-m-d');
$results_q = $conn->query(
    "SELECT * FROM oracle_prophecy_weeks 
     WHERE status = 'active' AND week_end < '$today' AND processing_attempts < 5
     ORDER BY week_start ASC"
);

if (!$results_q || $results_q->num_rows === 0) {
    echo "✅ Không có tuần nào cần chấm điểm.\n";
    exit;
}

while ($week = $results_q->fetch_assoc()) {
    $weekId    = (int)$week['id'];
    $weekStart = $week['week_start'];
    $weekEnd   = $week['week_end'];
    $attempts  = (int)$week['processing_attempts'] + 1;

    // Cập nhật số lần thử trong DB để tránh lặp vô hạn nếu có lỗi cứng
    $conn->query("UPDATE oracle_prophecy_weeks SET processing_attempts = $attempts WHERE id = $weekId");

    echo "📅 Đang chấm điểm tuần $weekStart → $weekEnd (ID: $weekId, Lần thử: $attempts)\n";

    // ─── 2. Lấy 3 lời tiên tri của tuần (không lọc kết quả pending để cho phép chạy lại idempotent)
    $prophecies = $conn->query("SELECT * FROM oracle_prophecies WHERE week_id=$weekId")->fetch_all(MYSQLI_ASSOC);

    if (empty($prophecies)) {
        echo "   ⚠️  Không có lời tiên tri nào cho tuần này, bỏ qua.\n";
        $conn->query("UPDATE oracle_prophecy_weeks SET status='completed' WHERE id=$weekId");
        continue;
    }

    $correctCount = 0;
    $conn->begin_transaction();
    try {
        foreach ($prophecies as $p) {
            $actual  = 0;
            $correct = false;
            $cType   = $p['condition_type'];
            $cVal    = (int)$p['condition_value'];

            switch ($cType) {
                case 'boss_killed':
                    $r = $conn->query("SELECT COUNT(*) as c FROM server_lore WHERE event_type='boss_kill' AND created_at BETWEEN '$weekStart 00:00:00' AND '$weekEnd 23:59:59'");
                    $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
                    break;
                case 'big_win_count':
                    $r = $conn->query("SELECT COUNT(*) as c FROM arena_memory WHERE event_type='big_win' AND created_at BETWEEN '$weekStart 00:00:00' AND '$weekEnd 23:59:59'");
                    $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
                    break;
                case 'guild_war_conquest':
                    $r = $conn->query("SELECT COUNT(*) as c FROM territories WHERE last_reset BETWEEN '$weekStart 00:00:00' AND '$weekEnd 23:59:59'");
                    $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
                    break;
                case 'new_users':
                    $r = $conn->query("SELECT COUNT(*) as c FROM users WHERE created_at BETWEEN '$weekStart 00:00:00' AND '$weekEnd 23:59:59'");
                    $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
                    break;
                case 'total_winnings':
                    $r = $conn->query("SELECT COALESCE(SUM(win_amount),0) as c FROM game_history WHERE is_win=1 AND played_at BETWEEN '$weekStart 00:00:00' AND '$weekEnd 23:59:59'");
                    $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
                    break;
                case 'single_mega_win':
                    $r = $conn->query("SELECT COALESCE(MAX(win_amount),0) as c FROM game_history WHERE is_win=1 AND played_at BETWEEN '$weekStart 00:00:00' AND '$weekEnd 23:59:59'");
                    $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
                    break;
                case 'streak_holders':
                    $r = $conn->query("SELECT COUNT(*) as c FROM user_streaks WHERE current_streak >= 5");
                    $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
                    break;
                case 'lucky_wheel_spins':
                    $r = $conn->query("SELECT COUNT(*) as c FROM game_history WHERE game_name LIKE '%Lucky Wheel%' AND played_at BETWEEN '$weekStart 00:00:00' AND '$weekEnd 23:59:59'");
                    $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
                    break;
                case 'pvp_battles':
                    $r = $conn->query("SELECT COUNT(*) as c FROM pvp_challenges WHERE status='completed' AND created_at BETWEEN '$weekStart 00:00:00' AND '$weekEnd 23:59:59'");
                    $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
                    break;
                case 'community_challenge_completed':
                    $r = $conn->query("SELECT COUNT(*) as c FROM community_challenges WHERE status='completed' AND updated_at BETWEEN '$weekStart 00:00:00' AND '$weekEnd 23:59:59'");
                    $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
                    break;
            }

            $correct = ($actual >= $cVal);
            if ($correct) $correctCount++;
            $result = $correct ? 'correct' : 'wrong';
            $now = date('Y-m-d H:i:s');

            $upStmt = $conn->prepare("UPDATE oracle_prophecies SET result=?, actual_value=?, checked_at=? WHERE id=?");
            $upStmt->bind_param("sisi", $result, $actual, $now, $p['id']);
            $upStmt->execute();
            $upStmt->close();

            $icon = $correct ? '✅' : '❌';
            echo "   $icon  [{$p['prophecy_index']}] $cType: $actual / $cVal → $result\n";
        }

        // ─── 3. Cộng Community Buff nếu 3/3 ─────────────────────────────
        $buffType = null;
        $buffExpires = date('Y-m-d H:i:s', strtotime('+7 days'));
        $loreMsg = "Lão Tiên Tri đã xem xét: $correctCount/3 lời tiên tri ứng nghiệm trong tuần $weekStart.";

        if ($correctCount === 3) {
            $buffType = 'oracle_blessing';
            $buffDesc = '🔮 Phúc Lành Tiên Tri: +20% GTLM cho toàn bộ chiến thắng trong 7 ngày!';
            $mult = 1.20;

            $bStmt = $conn->prepare("INSERT INTO community_buffs (buff_type, multiplier, description, source, expires_at, is_active) VALUES (?,?,?,'oracle',?,1)");
            $bStmt->bind_param("sdss", $buffType, $mult, $buffDesc, $buffExpires);
            $bStmt->execute();
            $bStmt->close();

            $loreMsg = "THIÊN CƠ ỨNG NGHIỆM! Cả 3 lời tiên tri của Lão Tiên Tri đã thành sự thật trong tuần $weekStart. Phúc Lành Tiên Tri ban xuống — toàn server nhận +20% GTLM trong 7 ngày tiếp theo!";

            echo "   🎉 3/3 ĐÚNG! Community Buff 'oracle_blessing' đã được kích hoạt!\n";

            // FIX: Bỏ LIMIT 500, dùng bulk INSERT theo batch 50 user/lần để tránh timeout
            $allUserIds = $conn->query("SELECT Iduser FROM users WHERE status='active'");
            if ($allUserIds) {
                $userIdBuffer = [];
                while ($u = $allUserIds->fetch_assoc()) {
                    $userIdBuffer[] = (int)$u['Iduser'];
                }
                $allUserIds->free();

                $ORACLE_BATCH  = 50;
                $totalNotified = 0;
                $nTitle = $conn->real_escape_string('🔮 Phúc Lành Tiên Tri Kích Hoạt!');
                $nMsg   = $conn->real_escape_string($loreMsg);
                $now    = date('Y-m-d H:i:s');

                foreach (array_chunk($userIdBuffer, $ORACLE_BATCH) as $batch) {
                    $rows = [];
                    foreach ($batch as $uid) {
                        $rows[] = "($uid, 'event_update', '$nTitle', '$nMsg', '🔮', 'oracle_prophecy.php', NULL, 1, '$now')";
                    }
                    $conn->query("
                        INSERT INTO notifications (user_id, type, title, message, icon, link, related_id, is_read, created_at)
                        VALUES " . implode(',', $rows)
                    );
                    $totalNotified += count($batch);
                }
                echo "   📬 Đã thông báo cho $totalNotified người dùng (batch $ORACLE_BATCH/lần).\n";
            }
        } else {
            echo "   ℹ️  Chỉ $correctCount/3 đúng — Không đủ điều kiện cộng buff.\n";
        }

        // ─── 4. Đánh dấu tuần hoàn thành ────────────────────────────────
        $upWeek = $conn->prepare("UPDATE oracle_prophecy_weeks SET status='completed', correct_count=?, buff_granted=1, buff_type=?, buff_expires_at=? WHERE id=?");
        $upWeek->bind_param("issi", $correctCount, $buffType, $buffExpires, $weekId);
        $upWeek->execute();
        $upWeek->close();

        $conn->commit();

        // ─── 5. Ghi vào Sử Ký ────────────────────────────────────────────
        $importance = $correctCount === 3 ? 3 : ($correctCount >= 1 ? 2 : 1);
        recordServerLore($conn, 'oracle', "🔮 Phán Quyết Lời Tiên Tri ($correctCount/3 Đúng)", $loreMsg, $importance);

        echo "✅ Xong tuần $weekStart. Kết quả: $correctCount/3 đúng.\n";

    } catch (\Throwable $e) {
        $conn->rollback();
        echo "❌ Lỗi khi chấm điểm tuần $weekId: " . $e->getMessage() . "\n";
    }
}

// ─── 6. Tự động tạo tuần mới nếu chưa có ────────────────────────────────
// Chạy mọi lần cron để bắt kịp nếu thứ Hai bị bỏ qua
$monday = date('Y-m-d', strtotime('monday this week'));
$sunday = date('Y-m-d', strtotime('sunday this week'));

$existingWeek = $conn->query("SELECT id FROM oracle_prophecy_weeks WHERE week_start='$monday'")->fetch_assoc();
if (!$existingWeek) {
    // Pool lời tiên tri — định nghĩa trực tiếp tại đây để chạy được từ CLI (không cần session)
    $PROPHECY_POOL = [
        ['text' => "Khi bóng tối chớm phủ, Rồng Thần sẽ ngã xuống — linh khí tuôn trào khắp Trận Địa.", 'type' => 'boss_killed', 'value' => 1],
        ['text' => "Vận số của tuần này nằm trong tay kẻ dám cược lớn — ai bạo dạn, người đó gặt hái.", 'type' => 'big_win_count', 'value' => 10],
        ['text' => "Một liên minh hùng mạnh sẽ trỗi dậy và in dấu lãnh thổ trên bản đồ quyền lực.", 'type' => 'guild_war_conquest', 'value' => 1],
        ['text' => "Trận địa sẽ đón chào những chiến binh mới trong 7 ngày tới.", 'type' => 'new_users', 'value' => 5],
        ['text' => "Vàng sẽ chảy như suối — tổng lộc tuần này vượt qua ngưỡng thiên thư.", 'type' => 'total_winnings', 'value' => 50000000],
        ['text' => "Một đêm đặc biệt chứng kiến kẻ giành được kho báu khổng lồ — hơn 5 triệu trong một ván.", 'type' => 'single_mega_win', 'value' => 5000000],
        ['text' => "Số phận ủng hộ kẻ trung thành — hơn 20 linh hồn sẽ duy trì streak trong tuần này.", 'type' => 'streak_holders', 'value' => 20],
        ['text' => "Chiếc bánh xe vận mệnh sẽ quay hơn một ngàn vòng trước khi tuần tàn.", 'type' => 'lucky_wheel_spins', 'value' => 100],
        ['text' => "Một cuộc đấu kiếm giữa hai anh hùng sẽ rung chuyển Trận Địa — PVP sôi sục như chưa từng.", 'type' => 'pvp_battles', 'value' => 30],
        ['text' => "Cộng đồng sẽ đoàn kết — nhiệm vụ cộng đồng tuần này sẽ được hoàn thành.", 'type' => 'community_challenge_completed', 'value' => 1],
    ];

    shuffle($PROPHECY_POOL);
    $chosen = array_slice($PROPHECY_POOL, 0, 3);

    $conn->begin_transaction();
    try {
        $stmtW = $conn->prepare("INSERT INTO oracle_prophecy_weeks (week_start, week_end, status) VALUES (?, ?, 'active')");
        $stmtW->bind_param("ss", $monday, $sunday);
        $stmtW->execute();
        $newWeekId = $conn->insert_id;
        $stmtW->close();

        $stmtP = $conn->prepare("INSERT INTO oracle_prophecies (week_id, prophecy_index, prophecy_text, condition_type, condition_value) VALUES (?, ?, ?, ?, ?)");
        foreach ($chosen as $idx => $p) {
            $i = $idx + 1;
            $stmtP->bind_param("iissi", $newWeekId, $i, $p['text'], $p['type'], $p['value']);
            $stmtP->execute();
        }
        $stmtP->close();

        $conn->commit();
        recordServerLore($conn, 'oracle', '🔮 Lão Tiên Tri Giáng Lời',
            "Đầu tuần " . date('d/m/Y', strtotime($monday)) . ", Lão Tiên Tri đã công bố 3 lời tiên tri huyền bí. Toàn server nín thở chờ đợi...", 2);

        echo "📅 Đã tự động tạo tuần Oracle mới: $monday → $sunday (ID: $newWeekId)\n";
    } catch (\Throwable $e) {
        $conn->rollback();
        echo "❌ Lỗi tạo tuần Oracle mới: " . $e->getMessage() . "\n";
    }
} else {
    echo "📅 Tuần Oracle $monday đã tồn tại (ID: {$existingWeek['id']}), bỏ qua.\n";
}

echo "[" . date('Y-m-d H:i:s') . "] 🔮 Oracle Prophecy Cron Finished\n";
?>
