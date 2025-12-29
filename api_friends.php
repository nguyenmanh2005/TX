<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

// Kiểm tra đăng nhập
if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập!']);
    exit();
}

$userId = (int)$_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Kiểm tra bảng friends có tồn tại không
$checkTable = $conn->query("SHOW TABLES LIKE 'friends'");
if (!$checkTable || $checkTable->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Hệ thống bạn bè chưa được kích hoạt! Vui lòng chạy file create_friends_tables.sql']);
    exit();
}

// Lấy trạng thái quan hệ bạn bè với 1 user
if ($action === 'get_status') {
    $otherId = (int)($_GET['other_id'] ?? 0);
    if ($otherId <= 0 || $otherId === $userId) {
        echo json_encode(['success' => false, 'message' => 'Người dùng không hợp lệ!']);
        exit();
    }
    
    $stmt = $conn->prepare("SELECT status, requested_by FROM friends WHERE user_id = ? AND friend_id = ? LIMIT 1");
    $stmt->bind_param("ii", $userId, $otherId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $status = 'none';
    $direction = null;
    if ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'accepted') {
            $status = 'friends';
        } elseif ($row['status'] === 'pending') {
            if ((int)$row['requested_by'] === $userId) {
                $status = 'pending';
                $direction = 'outgoing';
            } else {
                $status = 'pending';
                $direction = 'incoming';
            }
        } elseif ($row['status'] === 'blocked') {
            $status = 'blocked';
        }
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'status' => $status,
        'direction' => $direction
    ]);
    exit();
}

// Gửi lời mời kết bạn
if ($action === 'send_friend_request') {
    $friendId = (int)($_POST['friend_id'] ?? 0);
    
    if ($friendId <= 0 || $friendId === $userId) {
        echo json_encode(['success' => false, 'message' => 'Người dùng không hợp lệ!']);
        exit();
    }
    
    // Kiểm tra người dùng có tồn tại không
    $checkUser = $conn->prepare("SELECT Iduser FROM users WHERE Iduser = ?");
    $checkUser->bind_param("i", $friendId);
    $checkUser->execute();
    $result = $checkUser->get_result();
    if ($result->num_rows === 0) {
        $checkUser->close();
        echo json_encode(['success' => false, 'message' => 'Người dùng không tồn tại!']);
        exit();
    }
    $checkUser->close();
    
    // Kiểm tra đã có quan hệ bạn bè chưa
    $checkFriendship = $conn->prepare("SELECT * FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
    $checkFriendship->bind_param("iiii", $userId, $friendId, $friendId, $userId);
    $checkFriendship->execute();
    $result = $checkFriendship->get_result();
    
    if ($result->num_rows > 0) {
        $existing = $result->fetch_assoc();
        $checkFriendship->close();
        
        if ($existing['status'] === 'accepted') {
            echo json_encode(['success' => false, 'message' => 'Bạn đã là bạn bè rồi!']);
        } elseif ($existing['status'] === 'pending') {
            echo json_encode(['success' => false, 'message' => 'Đã có lời mời kết bạn đang chờ!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Người dùng này đã bị chặn!']);
        }
        exit();
    }
    $checkFriendship->close();
    
    // Tạo 2 bản ghi (mỗi người một bản ghi)
    $conn->begin_transaction();
    try {
        $stmt1 = $conn->prepare("INSERT INTO friends (user_id, friend_id, status, requested_by) VALUES (?, ?, 'pending', ?)");
        $stmt1->bind_param("iii", $userId, $friendId, $userId);
        $stmt1->execute();
        $stmt1->close();
        
        $stmt2 = $conn->prepare("INSERT INTO friends (user_id, friend_id, status, requested_by) VALUES (?, ?, 'pending', ?)");
        $stmt2->bind_param("iii", $friendId, $userId, $userId);
        $stmt2->execute();
        $stmt2->close();
        
        $conn->commit();
        
        // Gửi thông báo cho người nhận
        require_once 'notification_helper.php';
        $senderName = $conn->query("SELECT Name FROM users WHERE Iduser = $userId")->fetch_assoc()['Name'] ?? 'Ai đó';
        notifyFriendRequest($conn, $friendId, $userId, $senderName);
        
        echo json_encode(['success' => true, 'message' => 'Đã gửi lời mời kết bạn!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
    exit();
}

// Chấp nhận lời mời kết bạn
if ($action === 'accept_friend_request') {
    $friendId = (int)($_POST['friend_id'] ?? 0);
    
    if ($friendId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Người dùng không hợp lệ!']);
        exit();
    }
    
    // Kiểm tra có lời mời pending không
    $checkRequest = $conn->prepare("SELECT * FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'pending' AND requested_by != ?");
    $checkRequest->bind_param("iii", $userId, $friendId, $userId);
    $checkRequest->execute();
    $result = $checkRequest->get_result();
    
    if ($result->num_rows === 0) {
        $checkRequest->close();
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy lời mời kết bạn!']);
        exit();
    }
    $checkRequest->close();
    
    // Cập nhật cả 2 bản ghi thành accepted
    $conn->begin_transaction();
    try {
        $stmt1 = $conn->prepare("UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ?");
        $stmt1->bind_param("ii", $userId, $friendId);
        $stmt1->execute();
        $stmt1->close();
        
        $stmt2 = $conn->prepare("UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ?");
        $stmt2->bind_param("ii", $friendId, $userId);
        $stmt2->execute();
        $stmt2->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Đã chấp nhận lời mời kết bạn!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
    exit();
}

// Từ chối/Xóa bạn bè
if ($action === 'remove_friend') {
    $friendId = (int)($_POST['friend_id'] ?? 0);
    
    if ($friendId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Người dùng không hợp lệ!']);
        exit();
    }
    
    // Xóa cả 2 bản ghi
    $conn->begin_transaction();
    try {
        $stmt1 = $conn->prepare("DELETE FROM friends WHERE user_id = ? AND friend_id = ?");
        $stmt1->bind_param("ii", $userId, $friendId);
        $stmt1->execute();
        $stmt1->close();
        
        $stmt2 = $conn->prepare("DELETE FROM friends WHERE user_id = ? AND friend_id = ?");
        $stmt2->bind_param("ii", $friendId, $userId);
        $stmt2->execute();
        $stmt2->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Đã xóa bạn bè!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
    exit();
}

// Lấy danh sách bạn bè
if ($action === 'get_friends') {
    $sql = "SELECT u.Iduser, u.Name, u.Money, u.ImageURL, u.active_title_id,
            a.icon as title_icon, a.name as title_name,
            f.status, f.created_at
            FROM friends f
            INNER JOIN users u ON f.friend_id = u.Iduser
            LEFT JOIN achievements a ON u.active_title_id = a.id
            WHERE f.user_id = ? AND f.status = 'accepted'
            ORDER BY f.updated_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $friends = [];
    while ($row = $result->fetch_assoc()) {
        $friends[] = $row;
    }
    $stmt->close();
    echo json_encode(['success' => true, 'friends' => $friends]);
    exit();
}

// Lấy danh sách lời mời đang chờ
if ($action === 'get_pending_requests') {
    $sql = "SELECT u.Iduser, u.Name, u.Money, u.ImageURL, u.active_title_id,
            a.icon as title_icon, a.name as title_name,
            f.created_at
            FROM friends f
            INNER JOIN users u ON f.friend_id = u.Iduser
            LEFT JOIN achievements a ON u.active_title_id = a.id
            WHERE f.user_id = ? AND f.status = 'pending' AND f.requested_by != ?
            ORDER BY f.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();
    echo json_encode(['success' => true, 'requests' => $requests]);
    exit();
}

// Tìm kiếm người dùng để kết bạn
if ($action === 'search_users') {
    $search = trim($_GET['search'] ?? '');
    $limit = (int)($_GET['limit'] ?? 20);
    
    if (strlen($search) < 2) {
        echo json_encode(['success' => false, 'message' => 'Nhập ít nhất 2 ký tự!']);
        exit();
    }
    
    // Lấy danh sách bạn bè và lời mời đã gửi để loại trừ
    $excludeIds = [$userId];
    $excludeSql = "SELECT friend_id FROM friends WHERE user_id = ?";
    $excludeStmt = $conn->prepare($excludeSql);
    $excludeStmt->bind_param("i", $userId);
    $excludeStmt->execute();
    $excludeResult = $excludeStmt->get_result();
    while ($row = $excludeResult->fetch_assoc()) {
        $excludeIds[] = $row['friend_id'];
    }
    $excludeStmt->close();
    
    $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
    $types = str_repeat('i', count($excludeIds)) . 's';
    
    $sql = "SELECT u.Iduser, u.Name, u.Money, u.ImageURL, u.active_title_id,
            a.icon as title_icon, a.name as title_name
            FROM users u
            LEFT JOIN achievements a ON u.active_title_id = a.id
            WHERE u.Iduser NOT IN ($placeholders) AND u.Name LIKE ?
            ORDER BY u.Name LIMIT ?";
    
    $searchParam = "%$search%";
    $params = array_merge($excludeIds, [$searchParam, $limit]);
    $types .= 'i';
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $stmt->close();
        echo json_encode(['success' => true, 'users' => $users]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi database!']);
    }
    exit();
}

// Gửi tin nhắn riêng
if ($action === 'send_message') {
    $toUserId = (int)($_POST['to_user_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    
    if ($toUserId <= 0 || $toUserId === $userId) {
        echo json_encode(['success' => false, 'message' => 'Người nhận không hợp lệ!']);
        exit();
    }
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Tin nhắn không được để trống!']);
        exit();
    }
    
    // Kiểm tra có phải bạn bè không
    $checkFriendship = $conn->prepare("SELECT * FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'accepted'");
    $checkFriendship->bind_param("ii", $userId, $toUserId);
    $checkFriendship->execute();
    $result = $checkFriendship->get_result();
    
    if ($result->num_rows === 0) {
        $checkFriendship->close();
        echo json_encode(['success' => false, 'message' => 'Bạn chỉ có thể nhắn tin với bạn bè!']);
        exit();
    }
    $checkFriendship->close();
    
    // Kiểm tra bảng private_messages có tồn tại không
    $checkTable = $conn->query("SHOW TABLES LIKE 'private_messages'");
    if (!$checkTable || $checkTable->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Hệ thống tin nhắn chưa được kích hoạt!']);
        exit();
    }
    
    // Lưu tin nhắn
    $stmt = $conn->prepare("INSERT INTO private_messages (from_user_id, to_user_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $userId, $toUserId, $message);
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Đã gửi tin nhắn!']);
    } else {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Lỗi gửi tin nhắn!']);
    }
    exit();
}

// Lấy tin nhắn với một người
if ($action === 'get_messages') {
    $friendId = (int)($_GET['friend_id'] ?? 0);
    $limit = (int)($_GET['limit'] ?? 50);
    
    if ($friendId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Người dùng không hợp lệ!']);
        exit();
    }
    
    // Kiểm tra bảng private_messages có tồn tại không
    $checkTable = $conn->query("SHOW TABLES LIKE 'private_messages'");
    if (!$checkTable || $checkTable->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Hệ thống tin nhắn chưa được kích hoạt!']);
        exit();
    }
    
    // Lấy tin nhắn giữa 2 người
    $sql = "SELECT pm.*, u.Name as from_user_name, u.ImageURL as from_user_image
            FROM private_messages pm
            INNER JOIN users u ON pm.from_user_id = u.Iduser
            WHERE (pm.from_user_id = ? AND pm.to_user_id = ?) OR (pm.from_user_id = ? AND pm.to_user_id = ?)
            ORDER BY pm.created_at DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiii", $userId, $friendId, $friendId, $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();
    
    // Đánh dấu đã đọc
    $updateStmt = $conn->prepare("UPDATE private_messages SET is_read = 1 WHERE from_user_id = ? AND to_user_id = ? AND is_read = 0");
    $updateStmt->bind_param("ii", $friendId, $userId);
    $updateStmt->execute();
    $updateStmt->close();
    
    echo json_encode(['success' => true, 'messages' => array_reverse($messages)]);
    exit();
}

// Lấy số tin nhắn chưa đọc
if ($action === 'get_unread_count') {
    // Kiểm tra bảng private_messages có tồn tại không
    $checkTable = $conn->query("SHOW TABLES LIKE 'private_messages'");
    if (!$checkTable || $checkTable->num_rows === 0) {
        echo json_encode(['success' => true, 'count' => 0]);
        exit();
    }
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM private_messages WHERE to_user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    echo json_encode(['success' => true, 'count' => (int)$row['count']]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!']);
?>

