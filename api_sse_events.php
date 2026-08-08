<?php
// api_sse_events.php - Xử lý Polling thay thế SSE (Giảm tải server, tránh block PHP-FPM)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

require_once 'db_connect.php';
require_once 'api_event_helper.php'; // getActiveSeasonalEvent()

session_start();
if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false]);
    exit;
}

// Lấy sự kiện mùa giải
$eventData = getActiveSeasonalEvent($conn, false, 'id, name, theme_emoji');

// Lấy sự kiện đột xuất
$randomEvent = $conn->query("SELECT id, event_name, event_type FROM random_events WHERE is_active = 1 LIMIT 1")->fetch_assoc();

// Lấy Jackpot lớn nhất (Thắng >= 1.000.000 GTLM) trong 60 giây qua
$jackpotWin = null;
$stmtJp = $conn->prepare("
    SELECT u.Name, h.game_name, h.win_amount 
    FROM game_history h 
    JOIN users u ON h.user_id = u.Iduser 
    WHERE h.is_win = 1 AND h.win_amount >= 1000000 AND h.played_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
    ORDER BY h.played_at DESC LIMIT 1
");
if ($stmtJp) {
    $stmtJp->execute();
    $resJp = $stmtJp->get_result();
    if ($rowJp = $resJp->fetch_assoc()) {
        $jackpotWin = [
            'name' => $rowJp['Name'],
            'game' => $rowJp['game_name'],
            'amount' => (float)$rowJp['win_amount']
        ];
    }
    $stmtJp->close();
}

// Lấy danh sách nhiệm vụ vừa hoàn thành (is_notified = 0)
$unnotifiedMissions = [];
if ($eventData && isset($_SESSION['Iduser'])) {
    $eventId = (int)$eventData['id'];
    $uid = (int)$_SESSION['Iduser'];
    
    $stmt = $conn->prepare("
        SELECT ump.id, m.title, m.reward_xp, m.reward_currency 
        FROM user_mission_progress ump
        JOIN event_missions m ON ump.mission_id = m.id
        WHERE ump.user_id = ? AND m.event_id = ? AND ump.is_notified = 0
    ");
    $stmt->bind_param("ii", $uid, $eventId);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $updateIds = [];
    while ($row = $res->fetch_assoc()) {
        $unnotifiedMissions[] = [
            'title' => $row['title'],
            'reward_xp' => (int)$row['reward_xp'],
            'reward_currency' => (int)$row['reward_currency']
        ];
        $updateIds[] = (int)$row['id'];
    }
    $stmt->close();
    
    // Đánh dấu đã thông báo
    if (!empty($updateIds)) {
        $idStr = implode(',', $updateIds);
        $conn->query("UPDATE user_mission_progress SET is_notified = 1 WHERE id IN ($idStr)");
    }
}

echo json_encode([
    'success' => true,
    'seasonal_event' => $eventData ? [
        'id' => (int)$eventData['id'],
        'name' => $eventData['name'],
        'emoji' => $eventData['theme_emoji'] ?? '🏆'
    ] : null,
    'random_event' => $randomEvent ? [
        'id' => (int)$randomEvent['id'],
        'name' => $randomEvent['event_name'],
        'type' => $randomEvent['event_type']
    ] : null,
    'jackpot_win' => $jackpotWin,
    'completed_missions' => $unnotifiedMissions
]);
exit;
?>
