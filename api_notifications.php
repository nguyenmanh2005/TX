<?php
/**
 * API xử lý Notifications System
 * 
 * Actions:
 * - get_list: Lấy danh sách thông báo
 * - get_unread_count: Lấy số lượng thông báo chưa đọc
 * - mark_read: Đánh dấu đã đọc
 * - mark_all_read: Đánh dấu tất cả đã đọc
 * - delete: Xóa thông báo
 * - get_settings: Lấy cài đặt thông báo
 * - update_settings: Cập nhật cài đặt thông báo
 * - create: Tạo thông báo mới (dùng trong code)
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
$checkTable = $conn->query("SHOW TABLES LIKE 'user_notifications'");
if (!$checkTable || $checkTable->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Hệ thống Notifications chưa được kích hoạt! Vui lòng chạy file create_notifications_tables.sql trước.']);
    exit;
}

/**
 * Tạo thông báo mới
 */
function createNotification($conn, $userId, $type, $title, $content, $icon = '🔔', $link = null, $relatedId = null, $isImportant = false) {
    $checkTable = $conn->query("SHOW TABLES LIKE 'user_notifications'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        return false;
    }
    
    // Kiểm tra cài đặt thông báo
    $settingsSql = "SELECT * FROM notification_settings WHERE user_id = ?";
    $settingsStmt = $conn->prepare($settingsSql);
    $settingsStmt->bind_param("i", $userId);
    $settingsStmt->execute();
    $settings = $settingsStmt->get_result()->fetch_assoc();
    $settingsStmt->close();
    
    // Kiểm tra loại thông báo có được bật không
    $typeMap = [
        'friend_request' => 'friend_request',
        'private_message' => 'private_message',
        'achievement' => 'achievement',
        'gift_received' => 'gift_received',
        'event_update' => 'event_update',
        'tournament_update' => 'tournament_update',
        'guild_invite' => 'guild_invite',
        'guild_message' => 'guild_message'
    ];
    
    if ($settings && isset($typeMap[$type])) {
        $settingKey = $typeMap[$type];
        if (!$settings[$settingKey]) {
            return false; // Thông báo này bị tắt
        }
    }
    
    // Tạo thông báo
    $sql = "INSERT INTO user_notifications (user_id, type, title, content, icon, link, related_id, is_important) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssssii", $userId, $type, $title, $content, $icon, $link, $relatedId, $isImportant);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

switch ($action) {
    case 'get_list':
        // Lấy danh sách thông báo
        $limit = (int)($_GET['limit'] ?? 50);
        $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] == '1';
        
        $sql = "SELECT * FROM user_notifications WHERE user_id = ?";
        
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'notifications' => $notifications]);
        break;
        
    case 'get_unread_count':
        // Lấy số lượng thông báo chưa đọc
        $sql = "SELECT COUNT(*) as count FROM user_notifications WHERE user_id = ? AND is_read = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        echo json_encode(['success' => true, 'count' => (int)$result['count']]);
        break;
        
    case 'mark_read':
        // Đánh dấu đã đọc
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        
        if (!$notificationId) {
            echo json_encode(['success' => false, 'message' => 'Notification ID không hợp lệ!']);
            exit;
        }
        
        $sql = "UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $notificationId, $userId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Đánh dấu đã đọc thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi đánh dấu đã đọc!']);
        }
        $stmt->close();
        break;
        
    case 'mark_all_read':
        // Đánh dấu tất cả đã đọc
        $sql = "UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Đánh dấu tất cả đã đọc thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi đánh dấu đã đọc!']);
        }
        $stmt->close();
        break;
        
    case 'delete':
        // Xóa thông báo
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        
        if (!$notificationId) {
            echo json_encode(['success' => false, 'message' => 'Notification ID không hợp lệ!']);
            exit;
        }
        
        $sql = "DELETE FROM user_notifications WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $notificationId, $userId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Xóa thông báo thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa thông báo!']);
        }
        $stmt->close();
        break;
        
    case 'get_settings':
        // Lấy cài đặt thông báo
        $sql = "SELECT * FROM notification_settings WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $settings = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Tạo cài đặt mặc định nếu chưa có
        if (!$settings) {
            $insertSql = "INSERT INTO notification_settings (user_id) VALUES (?)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("i", $userId);
            $insertStmt->execute();
            $insertStmt->close();
            
            // Lấy lại
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $settings = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        
        echo json_encode(['success' => true, 'settings' => $settings]);
        break;
        
    case 'update_settings':
        // Cập nhật cài đặt thông báo
        $friendRequest = isset($_POST['friend_request']) ? 1 : 0;
        $privateMessage = isset($_POST['private_message']) ? 1 : 0;
        $achievement = isset($_POST['achievement']) ? 1 : 0;
        $giftReceived = isset($_POST['gift_received']) ? 1 : 0;
        $eventUpdate = isset($_POST['event_update']) ? 1 : 0;
        $tournamentUpdate = isset($_POST['tournament_update']) ? 1 : 0;
        $guildInvite = isset($_POST['guild_invite']) ? 1 : 0;
        $guildMessage = isset($_POST['guild_message']) ? 1 : 0;
        $soundEnabled = isset($_POST['sound_enabled']) ? 1 : 0;
        $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
        
        // Kiểm tra đã có settings chưa
        $checkSql = "SELECT user_id FROM notification_settings WHERE user_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("i", $userId);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->num_rows > 0;
        $checkStmt->close();
        
        if ($exists) {
            $sql = "UPDATE notification_settings SET 
                    friend_request = ?, private_message = ?, achievement = ?, 
                    gift_received = ?, event_update = ?, tournament_update = ?,
                    guild_invite = ?, guild_message = ?, sound_enabled = ?, 
                    email_notifications = ?
                    WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiiiiiiiiii", $friendRequest, $privateMessage, $achievement, 
                            $giftReceived, $eventUpdate, $tournamentUpdate,
                            $guildInvite, $guildMessage, $soundEnabled, 
                            $emailNotifications, $userId);
        } else {
            $sql = "INSERT INTO notification_settings 
                    (user_id, friend_request, private_message, achievement, 
                     gift_received, event_update, tournament_update,
                     guild_invite, guild_message, sound_enabled, email_notifications) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiiiiiiiiii", $userId, $friendRequest, $privateMessage, $achievement,
                            $giftReceived, $eventUpdate, $tournamentUpdate,
                            $guildInvite, $guildMessage, $soundEnabled, $emailNotifications);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Cập nhật cài đặt thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật cài đặt!']);
        }
        $stmt->close();
        break;
        
    case 'create':
        // Tạo thông báo mới (dùng trong code)
        $targetUserId = (int)($_POST['user_id'] ?? $userId);
        $type = $_POST['type'] ?? '';
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $icon = $_POST['icon'] ?? '🔔';
        $link = $_POST['link'] ?? null;
        $relatedId = isset($_POST['related_id']) ? (int)$_POST['related_id'] : null;
        $isImportant = isset($_POST['is_important']) ? 1 : 0;
        
        if (empty($type) || empty($title) || empty($content)) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không đầy đủ!']);
            exit;
        }
        
        if (createNotification($conn, $targetUserId, $type, $title, $content, $icon, $link, $relatedId, $isImportant)) {
            echo json_encode(['success' => true, 'message' => 'Tạo thông báo thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi tạo thông báo!']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!']);
        break;
}

$conn->close();

