<?php
/**
 * Cron: Flash Event Scheduler
 * ─────────────────────────────────────────────────────────────
 * Kích hoạt Flash Event (x2 phần thưởng toàn server) theo lịch
 * ngẫu nhiên có kiểm soát, KHÔNG phụ thuộc vào page-load.
 *
 * BUG FIX #6: Trước đây checkOrTriggerFlashEvent() dựa vào xác suất
 * 3% mỗi khi người dùng tải trang — nếu không có ai online thì Flash
 * Event không bao giờ xảy ra. File này thay thế cơ chế đó.
 *
 * Crontab: *\/30 * * * * php /path/to/cron_flash_event.php
 * (chạy mỗi 30 phút, tối đa 2 lần/ngày được kích hoạt)
 */

if (php_sapi_name() !== 'cli' && !defined('ALLOW_FLASH_WEB')) {
    http_response_code(403);
    die('Forbidden');
}

require_once __DIR__ . '/db_connect.php';

$today      = date('Y-m-d');
$currentHour = (int)date('G');

// Chỉ kích hoạt trong khung giờ 8:00 – 23:00
if ($currentHour < 8 || $currentHour >= 23) {
    echo "Ngoài khung giờ kích hoạt (8:00–23:00), bỏ qua.\n";
    exit;
}

// 1. Kiểm tra đã có Flash Event nào đang chạy không
$activeRes = $conn->query("SELECT id FROM flash_events WHERE status = 'active' AND NOW() BETWEEN start_time AND end_time LIMIT 1");
if ($activeRes && $activeRes->num_rows > 0) {
    echo "Flash Event đang chạy, bỏ qua.\n";
    exit;
}

// 2. Đếm số lần đã kích hoạt hôm nay (tối đa 2 lần/ngày)
$todayStart = $today . ' 00:00:00';
$todayEnd   = $today . ' 23:59:59';
$countRes = $conn->query("SELECT COUNT(*) as cnt FROM flash_events WHERE start_time BETWEEN '$todayStart' AND '$todayEnd'");
$countToday = $countRes ? (int)$countRes->fetch_assoc()['cnt'] : 0;

if ($countToday >= 2) {
    echo "Hôm nay đã kích hoạt đủ 2 Flash Events, bỏ qua.\n";
    exit;
}

// 3. Xác suất 40% mỗi lần cron chạy (30 phút/lần)
// Điều này cho ~80% cơ hội có ít nhất 1 Flash Event trong ngày
if (rand(1, 100) > 40) {
    echo "Lần này không kích hoạt Flash Event (xác suất 40%), thử lại sau 30 phút.\n";
    exit;
}

// 4. Tạo Flash Event mới
$duration    = rand(15, 30); // 15–30 phút
$multiplier  = 2.00;

$stmt = $conn->prepare("INSERT INTO flash_events (multiplier, start_time, end_time, status) VALUES (?, NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE), 'active')");
$stmt->bind_param("di", $multiplier, $duration);
$stmt->execute();
$flashEventId = (int)$conn->insert_id;
$stmt->close();

// 5. Thông báo vào chat_messages
$announceMsg = "⚡ SỰ KIỆN CHỚP NHOÁNG (FLASH EVENT)! Cổng trời mở ra x2 phần thưởng GTLM cho TOÀN BỘ trận địa trong {$duration} phút tiếp theo! Mau ra chiêu!";
$sysId   = 1;
$sysName = 'Hệ Thống';
$stmtChat = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, created_at) VALUES (?, ?, ?, NOW())");
if ($stmtChat) {
    $stmtChat->bind_param("iss", $sysId, $sysName, $announceMsg);
    $stmtChat->execute();
    $stmtChat->close();
}

// Fallback: thử bảng chat nếu chat_messages không tồn tại
$chatCheck = $conn->query("SHOW TABLES LIKE 'chat'");
if ($chatCheck && $chatCheck->num_rows > 0) {
    $stmtChat2 = $conn->prepare("INSERT INTO chat (username, message, color) VALUES ('Hệ Thống', ?, '#ef4444')");
    if ($stmtChat2) {
        $stmtChat2->bind_param("s", $announceMsg);
        $stmtChat2->execute();
        $stmtChat2->close();
    }
}

// 6. Push notification cho user đang online
$conn->query("
    INSERT IGNORE INTO notifications (user_id, title, message, type)
    SELECT Iduser,
           '⚡ FLASH EVENT!',
           'x2 phần thưởng toàn server trong {$duration} phút! Chơi ngay!',
           'flash_event'
    FROM users
    WHERE last_active > NOW() - INTERVAL 30 MINUTE
      AND Email NOT REGEXP '^bot[0-9]+@'
");

echo "✅ Flash Event (id=$flashEventId) đã kích hoạt — x{$multiplier} trong {$duration} phút.\n";
echo "   Hôm nay đã kích hoạt: " . ($countToday + 1) . "/2 lần.\n";
?>
