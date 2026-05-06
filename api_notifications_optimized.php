<?php
/**
 * API Notifications - OPTIMIZED VERSION
 * Tối ưu performance với caching và batch queries
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
    echo json_encode(['success' => false, 'message' => 'Hệ thống Notifications chưa được kích hoạt!']);
    exit;
}

// Cache key prefix
$cachePrefix = "notif_user_{$userId}_";

/**
 * Get cached data (simple file-based cache)
 */
function getCache($key, $ttl = 60) {
    $cacheFile = sys_get_temp_dir() . '/' . md5($key) . '.cache';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    return null;
}

/**
 * Set cache
 */
function setCache($key, $data, $ttl = 60) {
    $cacheFile = sys_get_temp_dir() . '/' . md5($key) . '.cache';
    file_put_contents($cacheFile, json_encode($data));
}

/**
 * Clear cache for user
 */
function clearUserCache($userId) {
    $pattern = sys_get_temp_dir() . '/' . md5("notif_user_{$userId}_*") . '.cache';
    array_map('unlink', glob($pattern));
}

switch ($action) {
    case 'get_list':
        // Lấy danh sách thông báo với cache
        $limit = min(100, (int)($_GET['limit'] ?? 50));
        $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] == '1';
        $cacheKey = $cachePrefix . 'list_' . ($unreadOnly ? 'unread' : 'all') . '_' . $limit;
        
        // Thử lấy từ cache
        $cached = getCache($cacheKey, 30); // Cache 30 giây
        if ($cached !== null) {
            echo json_encode(['success' => true, 'notifications' => $cached, 'cached' => true]);
            exit;
        }
        
        // Query từ database
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
        
        // Lưu vào cache
        setCache($cacheKey, $notifications, 30);
        
        echo json_encode(['success' => true, 'notifications' => $notifications]);
        break;
        
    case 'get_unread_count':
        // Lấy số lượng thông báo chưa đọc với cache
        $cacheKey = $cachePrefix . 'unread_count';
        
        // Thử lấy từ cache
        $cached = getCache($cacheKey, 10); // Cache 10 giây
        if ($cached !== null) {
            echo json_encode(['success' => true, 'count' => $cached, 'cached' => true]);
            exit;
        }
        
        // Query từ database
        $sql = "SELECT COUNT(*) as count FROM user_notifications WHERE user_id = ? AND is_read = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $count = (int)$result['count'];
        
        // Lưu vào cache
        setCache($cacheKey, $count, 10);
        
        echo json_encode(['success' => true, 'count' => $count]);
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
            // Clear cache
            clearUserCache($userId);
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
            // Clear cache
            clearUserCache($userId);
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
            // Clear cache
            clearUserCache($userId);
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
        
    case 'get_recent':
        // Lấy thông báo mới nhất (cho real-time updates)
        $lastId = (int)($_GET['last_id'] ?? 0);
        $limit = min(20, (int)($_GET['limit'] ?? 10));
        
        $sql = "SELECT * FROM user_notifications 
                WHERE user_id = ? AND id > ? 
                ORDER BY created_at DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $userId, $lastId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'notifications' => $notifications]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!']);
        break;
}

$conn->close();

