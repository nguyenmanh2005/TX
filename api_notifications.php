<?php
$isDirectCall = (isset($_SERVER['SCRIPT_FILENAME']) && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']));

if ($isDirectCall) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    require_once 'db_connect.php';

    $userId = $_SESSION['Iduser'] ?? 0;
    if (!$userId) exit(json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập!']));

    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    header('Content-Type: application/json');

    switch ($action) {
        case 'get_list':
            $unreadOnly = isset($_GET['unread_only']) ? (int)$_GET['unread_only'] : 0;
            $importantOnly = isset($_GET['important_only']) ? (int)$_GET['important_only'] : 0;
            
            $sql = "SELECT * FROM user_notifications WHERE user_id = ?";
            if ($unreadOnly) {
                $sql .= " AND is_read = 0";
            }
            if ($importantOnly) {
                $sql .= " AND is_important = 1";
            }
            $sql .= " ORDER BY created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            $notifs = [];
            while ($row = $res->fetch_assoc()) {
                $notifs[] = $row;
            }
            $stmt->close();
            
            echo json_encode(['success' => true, 'notifications' => $notifs]);
            break;

        case 'mark_read':
            $notifId = (int)($_POST['notification_id'] ?? 0);
            if ($notifId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Thông báo không hợp lệ!']);
                exit();
            }
            
            $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $notifId, $userId);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['success' => true]);
            break;

        case 'mark_all_read':
            $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $notifId = (int)($_POST['notification_id'] ?? 0);
            if ($notifId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Thông báo không hợp lệ!']);
                exit();
            }
            
            $stmt = $conn->prepare("DELETE FROM user_notifications WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $notifId, $userId);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['success' => true]);
            break;

        case 'delete_all':
            $stmt = $conn->prepare("DELETE FROM user_notifications WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['success' => true]);
            break;

        case 'get_unread_count':
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_notifications WHERE user_id = ? AND is_read = 0");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            echo json_encode(['success' => true, 'count' => (int)$res['count']]);
            break;

        case 'get_settings':
            $stmt = $conn->prepare("SELECT * FROM notification_settings WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $settings = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$settings) {
                // Khởi tạo cài đặt mặc định
                $conn->query("INSERT IGNORE INTO notification_settings (user_id, friend_request, private_message, achievement, gift_received, event_update, tournament_update, guild_invite, guild_message, sound_enabled, email_notifications) VALUES ($userId, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1)");
                
                $stmt = $conn->prepare("SELECT * FROM notification_settings WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $settings = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
            
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;

        case 'update_settings':
            $friend_request = (int)($_POST['friend_request'] ?? 1);
            $private_message = (int)($_POST['private_message'] ?? 1);
            $achievement = (int)($_POST['achievement'] ?? 1);
            $gift_received = (int)($_POST['gift_received'] ?? 1);
            $event_update = (int)($_POST['event_update'] ?? 1);
            $tournament_update = (int)($_POST['tournament_update'] ?? 1);
            $guild_invite = (int)($_POST['guild_invite'] ?? 1);
            $guild_message = (int)($_POST['guild_message'] ?? 1);
            $sound_enabled = (int)($_POST['sound_enabled'] ?? 1);
            $email_notifications = (int)($_POST['email_notifications'] ?? 1);
            
            $stmt = $conn->prepare("INSERT INTO notification_settings (user_id, friend_request, private_message, achievement, gift_received, event_update, tournament_update, guild_invite, guild_message, sound_enabled, email_notifications) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE friend_request = VALUES(friend_request), private_message = VALUES(private_message), achievement = VALUES(achievement), gift_received = VALUES(gift_received), event_update = VALUES(event_update), tournament_update = VALUES(tournament_update), guild_invite = VALUES(guild_invite), guild_message = VALUES(guild_message), sound_enabled = VALUES(sound_enabled), email_notifications = VALUES(email_notifications)");
            $stmt->bind_param("iiiiiiiiiii", $userId, $friend_request, $private_message, $achievement, $gift_received, $event_update, $tournament_update, $guild_invite, $guild_message, $sound_enabled, $email_notifications);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['success' => true]);
            break;
            
        // Giữ lại hành động cũ đề phòng tương thích ngược
        case 'get_notifications':
            $limit = (int) ($_GET['limit'] ?? 10);
            $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ?");
            $stmt->bind_param("ii", $userId, $limit);
            $stmt->execute();
            $res = $stmt->get_result();
            $notifs = [];
            while ($row = $res->fetch_assoc()) $notifs[] = $row;
            $stmt->close();
            
            $unread = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $userId AND is_read = 0")->fetch_assoc()['count'];
            echo json_encode(['success' => true, 'notifications' => $notifs, 'unread_count' => (int)$unread]);
            break;

        case 'mark_as_read':
            $notifId = (int) ($_POST['id'] ?? 0);
            if ($notifId > 0) {
                $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $notifId AND user_id = $userId");
            } else {
                $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $userId");
            }
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ!']);
            break;
    }
} // End $isDirectCall check

// Hàm helper để gửi thông báo (có thể gọi từ bất cứ đâu)
function sendNotification(mysqli $conn, int $userId, string $title, string $message, string $type = 'system') {
    require_once 'vocabulary_helper.php';
    $title = VocabularyHelper::mask($title);
    $message = VocabularyHelper::mask($message);
    
    // Ghi vào bảng notifications cũ
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $title, $message, $type);
    $stmt->execute();
    $stmt->close();
    
    // Ghi vào bảng user_notifications mới nếu nó tồn tại
    $checkTable = $conn->query("SHOW TABLES LIKE 'user_notifications'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $icon = '🔔';
        if ($type === 'weekly_reward') $icon = '🎁';
        elseif (strpos($title, 'NỔ HŨ') !== false) $icon = '🎉';
        
        $stmt2 = $conn->prepare("INSERT INTO user_notifications (user_id, type, title, content, icon) VALUES (?, ?, ?, ?, ?)");
        $stmt2->bind_param("issss", $userId, $type, $title, $message, $icon);
        $stmt2->execute();
        $stmt2->close();
    }
}
