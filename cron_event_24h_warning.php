<?php
/**
 * Cron: Event 24h Warning Announcer
 * Chạy mỗi giờ để phát hiện sự kiện mùa sắp kết thúc (dưới 24 giờ)
 * và tự động gửi thông báo nhắc nhở đổi quà cho tất cả người chơi tham gia.
 * Crontab: 0 * * * * php /path/to/cron_event_24h_warning.php
 */

// Chặn chạy qua trình duyệt trừ khi có CLI hoặc flag đặc biệt
if (php_sapi_name() !== 'cli' && !defined('ALLOW_EVENT_WEB')) {
    http_response_code(403);
    die('Forbidden');
}

require_once __DIR__ . '/db_connect.php';

echo "[CRON] Bắt đầu quét sự kiện sắp kết thúc...\n";

// 1. Tìm sự kiện đang active kết thúc trong vòng 24 giờ tới
$eventRes = $conn->query("
    SELECT id, name, theme_emoji, ends_at 
    FROM seasonal_events 
    WHERE status = 'active' 
      AND ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
    LIMIT 1
");

$event = $eventRes ? $eventRes->fetch_assoc() : null;

if (!$event) {
    echo "[CRON] Không có sự kiện nào sắp kết thúc trong 24 giờ tới.\n";
    exit;
}

$eventId = (int)$event['id'];
$endsAt = $event['ends_at'];
echo "[CRON] Phát hiện sự kiện sắp kết thúc: '{$event['name']}' (ID: $eventId) - Ends at: $endsAt\n";

// 2. Gửi thông báo hàng loạt cho toàn bộ người chơi tham gia (hoạt động trong vòng 30 ngày)
// Sử dụng subquery để loại trừ những user đã nhận thông báo này trước đó
$sqlInsert = "
    INSERT INTO notifications (user_id, title, message, type, related_id, is_read, created_at)
    SELECT d.user_id, 
           '🚨 Sự kiện {$event['theme_emoji']} {$conn->real_escape_string($event['name'])} Sắp Kết Thúc!', 
           'Chỉ còn chưa đầy 24 giờ nữa sự kiện sẽ khép lại! Hãy nhanh tay hoàn thành các nhiệm vụ và đổi quà trong Cửa Hàng ngay trước khi quá muộn!', 
           'event_update', 
           $eventId,
           0,
           NOW()
    FROM user_event_data d
    JOIN users u ON d.user_id = u.Iduser
    WHERE d.event_id = $eventId
      AND u.last_active >= NOW() - INTERVAL 30 DAY
      AND u.Email NOT REGEXP '^bot[0-9]+@'
      AND d.user_id NOT IN (
          SELECT DISTINCT user_id FROM notifications 
          WHERE related_id = $eventId 
            AND type = 'event_update' 
            AND title LIKE '%Sắp kết thúc%'
      )
";

if ($conn->query($sqlInsert)) {
    $affected = $conn->affected_rows;
    echo "[CRON] Gửi thành công thông báo cảnh báo 24h tới $affected người chơi.\n";
} else {
    echo "[CRON] Lỗi trong quá trình gửi thông báo: " . $conn->error . "\n";
}

echo "[CRON] Hoàn thành job.\n";
?>
