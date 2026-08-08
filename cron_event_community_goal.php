<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/api_event_helper.php';
require_once __DIR__ . '/reward_helper.php';

// ── GUARD: Chống chạy 2 cron song song (DB Advisory Lock) ──────────────────
// GET_LOCK() trả về 1 nếu lấy được lock, 0 nếu đang bị giữ bởi process khác.
// Lock tự giải phóng khi kết nối DB đóng (PHP process kết thúc).
$lockName    = 'cron_community_goal';
$lockTimeout = 0; // Không chờ — nếu đang chạy thì thoát ngay
$lockResult  = $conn->query("SELECT GET_LOCK('$lockName', $lockTimeout) as locked")->fetch_assoc()['locked'];
if ((int)$lockResult !== 1) {
    die("⚠️ Cron đang chạy ở process khác, bỏ qua lần này.\n");
}
echo "🔒 Lock acquired — bắt đầu xử lý Community Goal.\n";

// Kiểm tra sự kiện đang diễn ra
$activeEvent = EventHelper::getActiveEvent($conn);
if (!$activeEvent) {
    die("Không có sự kiện nào đang diễn ra.\n");
}

$eventId     = (int)$activeEvent['id'];
// FIX: Đọc target_points từ bảng seasonal_events thay vì hardcode
$targetPoints = (int)($activeEvent['target_points'] ?? 100000);
if ($targetPoints <= 0) {
    // Fallback an toàn nếu cột chưa được set
    $targetPoints = 100000;
    echo "⚠️ Cảnh báo: target_points chưa được cấu hình trong DB, dùng mặc định 100,000.\n";
}

// Tính tổng điểm toàn server
$totalRes = $conn->query("SELECT SUM(points) as total FROM user_event_data WHERE event_id = $eventId")->fetch_assoc();
$totalPoints = (int)($totalRes['total'] ?? 0);

echo "Event: " . $activeEvent['name'] . "\n";
echo "Total Points: " . $totalPoints . " / " . $targetPoints . "\n";

if ($totalPoints >= $targetPoints) {
    // Lấy danh sách những người đã tham gia (có điểm > 0) nhưng CHƯA nhận quà community goal
    $users = $conn->query("
        SELECT user_id FROM user_event_data 
        WHERE event_id = $eventId 
        AND points > 0 
        AND JSON_CONTAINS(COALESCE(milestones_claimed, '[]'), '\"cg_reward\"') = 0
    ")->fetch_all(MYSQLI_ASSOC);

    if (count($users) > 0) {
        // FIX: Xử lý theo batch nhỏ để tránh timeout khi server có nhiều người chơi
        $BATCH_SIZE    = 50;
        $totalRewarded = 0;
        $chunks        = array_chunk($users, $BATCH_SIZE);

        foreach ($chunks as $chunk) {
            $conn->begin_transaction();
            try {
                $uids = array_map(fn($u) => (int)$u['user_id'], $chunk);

                // ── Phát thưởng GTLM cho từng user trong batch (deliverReward cần gọi riêng) ──
                foreach ($uids as $uid) {
                    deliverReward($uid, ['reward_type' => 'money', 'reward_value' => '50000'], $conn);
                }

                // ── Bulk UPDATE đánh dấu đã nhận cho cả batch ──
                $inList = implode(',', $uids);
                $conn->query("
                    UPDATE user_event_data 
                    SET milestones_claimed = JSON_ARRAY_APPEND(COALESCE(milestones_claimed, JSON_ARRAY()), '$', 'cg_reward')
                    WHERE user_id IN ($inList) AND event_id = $eventId
                ");

                // ── Bulk INSERT thông báo cho cả batch trong 1 câu query ──
                $notifTitle   = $conn->real_escape_string("🌍 Mục Tiêu Cộng Đồng Đã Đạt!");
                $notifMsg     = $conn->real_escape_string("Tuyệt vời! Toàn server đã gom đủ {$targetPoints} Điểm Vinh Danh. Bạn nhận được 50,000 GTLM thưởng mốc!");
                $notifRows    = [];
                $now          = date('Y-m-d H:i:s');
                foreach ($uids as $uid) {
                    $notifRows[] = "($uid, 'event_update', '$notifTitle', '$notifMsg', '🎁', 'event_center.php', $eventId, 1, '$now')";
                }
                if (!empty($notifRows)) {
                    $conn->query("
                        INSERT INTO notifications (user_id, type, title, message, icon, link, related_id, is_read, created_at)
                        VALUES " . implode(',', $notifRows)
                    );
                }

                $conn->commit();
                $totalRewarded += count($uids);
                echo "   ✅ Batch " . ceil($totalRewarded / $BATCH_SIZE) . ": phát quà cho " . count($uids) . " người.\n";
            } catch (Exception $e) {
                $conn->rollback();
                echo "   ❌ Lỗi batch: " . $e->getMessage() . "\n";
            }
        }
        echo "Đã phát quà Community Goal cho $totalRewarded / " . count($users) . " người chơi!\n";
    } else {
        echo "Mục tiêu đã đạt nhưng không có ai cần nhận quà mới.\n";
    }
} else {
    echo "Chưa đạt mục tiêu.\n";
}
?>
