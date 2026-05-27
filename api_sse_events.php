<?php
// api_sse_events.php - Xử lý Server-Sent Events cho thông báo sự kiện
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

require_once 'db_connect.php';
require_once 'api_event_helper.php'; // getActiveSeasonalEvent()

// Tránh session blocking
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

set_time_limit(0);
ignore_user_abort(false);

$lastEventId = 0;
$initEvent = getActiveSeasonalEvent($conn, false, 'id');
if ($initEvent) {
    $lastEventId = (int)$initEvent['id'];
}

$startTime = time();

while (true) {
    if (connection_aborted() || (time() - $startTime) > 300) { // Đóng sau 5 phút để client tự reconnect
        break;
    }

    // ── XỬ LÝ SEASONAL EVENT ──
    $eventData   = getActiveSeasonalEvent($conn, false, 'id, name, theme_emoji');
    $currentActive = 0;
    
    if ($eventData) {
        $currentActive = (int)$eventData['id'];
    }

    if (($currentActive != $lastEventId && $currentActive > 0 && $lastEventId !== 0) || ($currentActive > 0 && $lastEventId === 0)) {
        echo "event: new_event\n";
        echo "data: " . json_encode([
            'id' => $currentActive, 
            'name' => $eventData['name'],
            'emoji' => $eventData['theme_emoji'] ?? '🏆'
        ]) . "\n\n";
        ob_flush();
        flush();
    }
    $lastEventId = $currentActive;

    // ── XỬ LÝ RANDOM EVENT ĐỘT XUẤT ──
    static $lastRandomEventId = 0;
    if ($lastRandomEventId === 0) {
        $rRes = $conn->query("SELECT id FROM random_events WHERE is_active = 1 LIMIT 1");
        if ($rRes && $rRes->num_rows > 0) $lastRandomEventId = (int)$rRes->fetch_assoc()['id'];
        else $lastRandomEventId = -1; // Đánh dấu đã check lần đầu
    } else {
        $rRes = $conn->query("SELECT id, event_name, event_type FROM random_events WHERE is_active = 1 AND id > " . ($lastRandomEventId == -1 ? 0 : $lastRandomEventId) . " LIMIT 1");
        if ($rRes && $rRes->num_rows > 0) {
            $rData = $rRes->fetch_assoc();
            $lastRandomEventId = (int)$rData['id'];
            echo "event: new_random_event\n";
            echo "data: " . json_encode([
                'id' => $rData['id'], 
                'name' => $rData['event_name'],
                'type' => $rData['event_type']
            ]) . "\n\n";
            ob_flush();
            flush();
        }
    }

    // Ping để giữ kết nối
    echo ": ping\n\n";
    ob_flush();
    flush();

    sleep(10); // Query DB mỗi 10 giây
}
?>
