<?php
/**
 * 🌊 GTLM Server-Sent Events (SSE) Stream Fallback
 * Cung cấp luồng dữ liệu liên tục qua HTTP thông thường khi WebSocket chưa bật hoặc bị chặn cổng.
 */

session_write_close();
ignore_user_abort(true);
set_time_limit(0);

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Accel-Buffering: no'); // Tắt buffer Nginx nếu có

require_once dirname(__DIR__) . '/db_connect.php';

$channel = $_GET['channel'] ?? 'chat';
$lastId = (int)($_GET['last_id'] ?? 0);

// Nếu lastId chưa có, lấy ID tin nhắn mới nhất
if ($lastId <= 0) {
    $row = $conn->query("SELECT MAX(id) as max_id FROM chat_messages")->fetch_assoc();
    $lastId = (int)($row['max_id'] ?? 0);
}

// Báo kết nối thành công
echo "event: connected\n";
echo "data: " . json_encode(['status' => 'connected', 'mode' => 'sse', 'channel' => $channel]) . "\n\n";
@ob_flush();
@flush();

$startTime = time();
$maxExecutionTime = 25; // Chạy tối đa 25s rồi đóng để trình duyệt tự kết nối lại sạch sẽ

while (time() - $startTime < $maxExecutionTime) {
    if (connection_aborted()) {
        break;
    }

    if ($channel === 'chat') {
        $stmt = $conn->prepare("SELECT id, user_id, username, message, avatar, chat_frame_id, created_at 
                                FROM chat_messages WHERE id > ? ORDER BY id ASC LIMIT 10");
        $stmt->bind_param("i", $lastId);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($msg = $res->fetch_assoc()) {
            $lastId = (int)$msg['id'];
            echo "event: chat_message\n";
            echo "data: " . json_encode($msg, JSON_UNESCAPED_UNICODE) . "\n\n";
            @ob_flush();
            @flush();
        }
        $stmt->close();
    } elseif (strpos($channel, 'pvp_') === 0) {
        $challengeId = (int)str_replace('pvp_', '', $channel);
        $stmt = $conn->prepare("SELECT id, board_state, current_turn_id, status, winner_id FROM pvp_challenges WHERE id = ?");
        $stmt->bind_param("i", $challengeId);
        $stmt->execute();
        $ch = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($ch) {
            echo "event: caro_sync\n";
            echo "data: " . json_encode($ch, JSON_UNESCAPED_UNICODE) . "\n\n";
            @ob_flush();
            @flush();
        }
    }

    // Ping giữ kết nối
    echo ": ping\n\n";
    @ob_flush();
    @flush();

    usleep(1000000); // Ngủ 1 giây
}
