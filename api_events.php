<?php
/**
 * API xử lý Events System
 * 
 * Actions:
 * - get_list: Lấy danh sách sự kiện
 * - get_info: Lấy thông tin sự kiện
 * - join: Tham gia sự kiện
 * - get_progress: Lấy tiến độ của user
 * - update_progress: Cập nhật tiến độ (tự động)
 * - claim_reward: Nhận phần thưởng
 */

session_start();
require 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Kiểm tra bảng tồn tại
$checkTable = $conn->query("SHOW TABLES LIKE 'events'");
if (!$checkTable || $checkTable->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Hệ thống Events chưa được kích hoạt! Vui lòng chạy file create_events_tables.sql trước.']);
    exit;
}

/**
 * Cập nhật tiến độ sự kiện tự động
 */
function updateEventProgress($conn, $userId, $actionType, $actionValue) {
    // Tìm các sự kiện đang active mà user đã tham gia
    $sql = "SELECT ep.*, e.*
            FROM event_participants ep
            JOIN events e ON ep.event_id = e.id
            WHERE ep.user_id = ?
            AND e.status = 'active'
            AND e.is_active = 1
            AND NOW() BETWEEN e.start_time AND e.end_time
            AND ep.is_completed = 0
            AND e.requirement_type = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $userId, $actionType);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($event = $result->fetch_assoc()) {
        // Cập nhật tiến độ
        $newProgress = $event['progress'] + $actionValue;
        
        // Kiểm tra đã hoàn thành chưa
        $isCompleted = ($newProgress >= $event['requirement_value']);
        
        $conn->begin_transaction();
        try {
            // Cập nhật progress
            $updateSql = "UPDATE event_participants 
                         SET progress = ?, is_completed = ?, completed_at = ?
                         WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $completedAt = $isCompleted ? date('Y-m-d H:i:s') : null;
            $updateStmt->bind_param("diss", $newProgress, $isCompleted, $completedAt, $event['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Ghi lại progress log
            $logSql = "INSERT INTO event_progress (participant_id, action_type, action_value) 
                      VALUES (?, ?, ?)";
            $logStmt = $conn->prepare($logSql);
            $logStmt->bind_param("isd", $event['id'], $actionType, $actionValue);
            $logStmt->execute();
            $logStmt->close();
            
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Event progress update error: " . $e->getMessage());
        }
    }
    
    $stmt->close();
}

switch ($action) {
    case 'get_list':
        // Lấy danh sách sự kiện
        $status = $_GET['status'] ?? 'all';
        
        $sql = "SELECT e.*, 
                (SELECT COUNT(*) FROM event_participants WHERE event_id = e.id) as participant_count,
                (SELECT COUNT(*) FROM event_participants WHERE event_id = e.id AND user_id = ?) as is_joined,
                (SELECT progress FROM event_participants WHERE event_id = e.id AND user_id = ? LIMIT 1) as user_progress
                FROM events e
                WHERE e.is_active = 1";
        
        $params = [$userId, $userId];
        $types = 'iii';
        
        if ($status !== 'all') {
            $sql .= " AND e.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        $sql .= " ORDER BY e.start_time DESC LIMIT 50";
        
        $stmt = $conn->prepare($sql);
        if (count($params) > 2) {
            $stmt->bind_param($types, ...$params);
        } else {
            $stmt->bind_param("ii", $userId, $userId);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $events = [];
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'events' => $events]);
        break;
        
    case 'get_info':
        // Lấy thông tin sự kiện
        $eventId = (int)($_GET['event_id'] ?? 0);
        
        if (!$eventId) {
            echo json_encode(['success' => false, 'message' => 'Event ID không hợp lệ!']);
            exit;
        }
        
        $sql = "SELECT e.*, 
                (SELECT COUNT(*) FROM event_participants WHERE event_id = e.id) as participant_count,
                (SELECT COUNT(*) FROM event_participants WHERE event_id = e.id AND user_id = ?) as is_joined,
                (SELECT progress FROM event_participants WHERE event_id = e.id AND user_id = ? LIMIT 1) as user_progress,
                (SELECT is_completed FROM event_participants WHERE event_id = e.id AND user_id = ? LIMIT 1) as user_completed,
                (SELECT is_claimed FROM event_participants WHERE event_id = e.id AND user_id = ? LIMIT 1) as user_claimed
                FROM events e
                WHERE e.id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiii", $userId, $userId, $userId, $userId, $eventId);
        $stmt->execute();
        $event = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$event) {
            echo json_encode(['success' => false, 'message' => 'Sự kiện không tồn tại!']);
            exit;
        }
        
        echo json_encode(['success' => true, 'event' => $event]);
        break;
        
    case 'join':
        // Tham gia sự kiện
        $eventId = (int)($_POST['event_id'] ?? 0);
        
        if (!$eventId) {
            echo json_encode(['success' => false, 'message' => 'Event ID không hợp lệ!']);
            exit;
        }
        
        // Lấy thông tin sự kiện
        $sql = "SELECT * FROM events WHERE id = ? AND is_active = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $event = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$event) {
            echo json_encode(['success' => false, 'message' => 'Sự kiện không tồn tại!']);
            exit;
        }
        
        // Kiểm tra thời gian
        $now = time();
        $startTime = strtotime($event['start_time']);
        $endTime = strtotime($event['end_time']);
        
        if ($now < $startTime) {
            echo json_encode(['success' => false, 'message' => 'Sự kiện chưa bắt đầu!']);
            exit;
        }
        
        if ($now > $endTime) {
            echo json_encode(['success' => false, 'message' => 'Sự kiện đã kết thúc!']);
            exit;
        }
        
        // Kiểm tra số lượng người tham gia
        if ($event['max_participants']) {
            $countSql = "SELECT COUNT(*) as count FROM event_participants WHERE event_id = ?";
            $countStmt = $conn->prepare($countSql);
            $countStmt->bind_param("i", $eventId);
            $countStmt->execute();
            $countResult = $countStmt->get_result()->fetch_assoc();
            $countStmt->close();
            
            if ($countResult['count'] >= $event['max_participants']) {
                echo json_encode(['success' => false, 'message' => 'Sự kiện đã đầy!']);
                exit;
            }
        }
        
        // Kiểm tra đã tham gia chưa
        $checkSql = "SELECT id FROM event_participants WHERE event_id = ? AND user_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("ii", $eventId, $userId);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Bạn đã tham gia sự kiện này rồi!']);
            exit;
        }
        $checkStmt->close();
        
        // Tham gia sự kiện
        $insertSql = "INSERT INTO event_participants (event_id, user_id) VALUES (?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("ii", $eventId, $userId);
        
        if ($insertStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Tham gia sự kiện thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi tham gia sự kiện!']);
        }
        $insertStmt->close();
        break;
        
    case 'get_progress':
        // Lấy tiến độ của user
        $eventId = (int)($_GET['event_id'] ?? 0);
        
        if (!$eventId) {
            echo json_encode(['success' => false, 'message' => 'Event ID không hợp lệ!']);
            exit;
        }
        
        $sql = "SELECT ep.*, e.requirement_type, e.requirement_value, e.reward_type, e.reward_value
                FROM event_participants ep
                JOIN events e ON ep.event_id = e.id
                WHERE ep.event_id = ? AND ep.user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $eventId, $userId);
        $stmt->execute();
        $progress = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$progress) {
            echo json_encode(['success' => false, 'message' => 'Bạn chưa tham gia sự kiện này!']);
            exit;
        }
        
        // Tính phần trăm hoàn thành
        $progress['percentage'] = $progress['requirement_value'] > 0 
            ? min(100, round(($progress['progress'] / $progress['requirement_value']) * 100, 2))
            : 0;
        
        echo json_encode(['success' => true, 'progress' => $progress]);
        break;
        
    case 'claim_reward':
        // Nhận phần thưởng
        $eventId = (int)($_POST['event_id'] ?? 0);
        
        if (!$eventId) {
            echo json_encode(['success' => false, 'message' => 'Event ID không hợp lệ!']);
            exit;
        }
        
        // Lấy thông tin participant
        $sql = "SELECT ep.*, e.*
                FROM event_participants ep
                JOIN events e ON ep.event_id = e.id
                WHERE ep.event_id = ? AND ep.user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $eventId, $userId);
        $stmt->execute();
        $participant = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$participant) {
            echo json_encode(['success' => false, 'message' => 'Bạn chưa tham gia sự kiện này!']);
            exit;
        }
        
        if (!$participant['is_completed']) {
            echo json_encode(['success' => false, 'message' => 'Bạn chưa hoàn thành sự kiện!']);
            exit;
        }
        
        if ($participant['is_claimed']) {
            echo json_encode(['success' => false, 'message' => 'Bạn đã nhận phần thưởng rồi!']);
            exit;
        }
        
        // Cấp phần thưởng
        $conn->begin_transaction();
        try {
            // Cấp tiền nếu có
            if ($participant['reward_type'] === 'money' || $participant['reward_type'] === 'both') {
                if ($participant['reward_value'] > 0) {
                    $moneySql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
                    $moneyStmt = $conn->prepare($moneySql);
                    $moneyStmt->bind_param("di", $participant['reward_value'], $userId);
                    $moneyStmt->execute();
                    $moneyStmt->close();
                }
            }
            
            // Cấp item nếu có
            if ($participant['reward_type'] === 'item' || $participant['reward_type'] === 'both') {
                if ($participant['reward_item_type'] && $participant['reward_item_id']) {
                    $itemType = $participant['reward_item_type'];
                    $itemId = $participant['reward_item_id'];
                    
                    if ($itemType === 'theme') {
                        $itemSql = "INSERT IGNORE INTO user_themes (user_id, theme_id) VALUES (?, ?)";
                    } elseif ($itemType === 'cursor') {
                        $itemSql = "INSERT IGNORE INTO user_cursors (user_id, cursor_id) VALUES (?, ?)";
                    } elseif ($itemType === 'chat_frame') {
                        $itemSql = "INSERT IGNORE INTO user_chat_frames (user_id, chat_frame_id) VALUES (?, ?)";
                    } elseif ($itemType === 'avatar_frame') {
                        $itemSql = "INSERT IGNORE INTO user_avatar_frames (user_id, avatar_frame_id) VALUES (?, ?)";
                    }
                    
                    if (isset($itemSql)) {
                        $itemStmt = $conn->prepare($itemSql);
                        $itemStmt->bind_param("ii", $userId, $itemId);
                        $itemStmt->execute();
                        $itemStmt->close();
                    }
                }
            }
            
            // Đánh dấu đã nhận phần thưởng
            $updateSql = "UPDATE event_participants SET is_claimed = 1, claimed_at = NOW() WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("i", $participant['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Nhận phần thưởng thành công!',
                'reward' => [
                    'money' => ($participant['reward_type'] === 'money' || $participant['reward_type'] === 'both') ? $participant['reward_value'] : 0,
                    'item' => ($participant['reward_type'] === 'item' || $participant['reward_type'] === 'both') ? true : false
                ]
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!']);
        break;
}

$conn->close();

