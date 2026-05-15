<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Lấy sự kiện đang active (Seasonal Event)
$event = $conn->query("SELECT * FROM seasonal_events WHERE status = 'active' AND starts_at <= NOW() AND ends_at >= NOW() LIMIT 1")->fetch_assoc();
$eventId = $event['id'] ?? 0;

switch ($action) {
    case 'get_event_data':
        if (!$eventId) {
            echo json_encode(['success' => false, 'message' => 'Hiện không có sự kiện nào diễn ra.']);
            exit;
        }

        // 1.  Gtlm tệ và điểm của User trong event này
        $userData = $conn->query("SELECT * FROM user_event_data WHERE user_id = $userId AND event_id = $eventId")->fetch_assoc();
        if (!$userData) {
            $conn->query("INSERT INTO user_event_data (user_id, event_id) VALUES ($userId, $eventId)");
            $userData = ['event_currency' => 0, 'points' => 0];
        }

        // 2. Danh sách nhiệm vụ và tiến trình
        $missions = $conn->query("
            SELECT m.*, IFNULL(p.current_value, 0) as current_value, IFNULL(p.is_completed, 0) as is_completed, IFNULL(p.is_claimed, 0) as is_claimed
            FROM event_missions m
            LEFT JOIN user_mission_progress p ON m.id = p.mission_id AND p.user_id = $userId
            WHERE m.event_id = $eventId
        ")->fetch_all(MYSQLI_ASSOC);

        // 3. Cửa hàng đổi quà
        $shopItems = $conn->query("SELECT * FROM event_exchange_shop WHERE event_id = $eventId")->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            'success' => true,
            'event' => $event,
            'user_data' => $userData,
            'missions' => $missions,
            'shop_items' => $shopItems
        ]);
        break;

    case 'claim_reward':
        $missionId = (int)$_POST['mission_id'];
        
        $conn->begin_transaction();
        try {
            $progress = $conn->query("SELECT * FROM user_mission_progress WHERE user_id = $userId AND mission_id = $missionId FOR UPDATE")->fetch_assoc();
            $mission = $conn->query("SELECT * FROM event_missions WHERE id = $missionId")->fetch_assoc();

            if (!$progress || !$progress['is_completed']) throw new Exception("Nhiệm vụ chưa hoàn thành!");
            if ($progress['is_claimed']) throw new Exception("Bạn đã nhận thưởng nhiệm vụ này rồi!");

            // Cập nhật trạng thái nhận thưởng
            $conn->query("UPDATE user_mission_progress SET is_claimed = 1 WHERE id = {$progress['id']}");

            // Cộng  Gtlm tệ sự kiện
            $conn->query("UPDATE user_event_data SET event_currency = event_currency + {$mission['reward_currency']}, points = points + {$mission['reward_xp']} WHERE user_id = $userId AND event_id = $eventId");

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Nhận thưởng thành công!', 'reward' => $mission['reward_currency']]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'exchange_item':
        $itemId = (int)$_POST['item_id'];
        
        $conn->begin_transaction();
        try {
            $item = $conn->query("SELECT * FROM event_exchange_shop WHERE id = $itemId FOR UPDATE")->fetch_assoc();
            $userData = $conn->query("SELECT * FROM user_event_data WHERE user_id = $userId AND event_id = $eventId FOR UPDATE")->fetch_assoc();

            if (!$item) throw new Exception("Vật phẩm không tồn tại!");
            if ($userData['event_currency'] < $item['cost_currency']) throw new Exception("Bạn không đủ Xu Sự Kiện!");
            if ($item['total_stock'] == 0) throw new Exception("Vật phẩm đã hết hàng!");

            // Kiểm tra giới hạn mỗi người
            $claimCount = $conn->query("SELECT COUNT(*) as total FROM user_achievements WHERE user_id = $userId AND achievement_id = {$item['item_id']}")->fetch_assoc()['total'];
            if ($item['limit_per_user'] > 0 && $claimCount >= $item['limit_per_user']) {
                throw new Exception("Bạn đã đạt giới hạn đổi vật phẩm này!");
            }

            // Trừ  Gtlm sự kiện
            $conn->query("UPDATE user_event_data SET event_currency = event_currency - {$item['cost_currency']} WHERE user_id = $userId AND event_id = $eventId");

            // Trao giải (vd: Title/Achievement)
            if ($item['item_type'] === 'title') {
                $conn->query("INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES ($userId, {$item['item_id']})");
            }

            // Cập nhật kho
            if ($item['total_stock'] > 0) {
                $conn->query("UPDATE event_exchange_shop SET total_stock = total_stock - 1 WHERE id = $itemId");
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Đổi quà thành công!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
}
?>
