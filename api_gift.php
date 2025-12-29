<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

// Kiểm tra đăng nhập
if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập!']);
    exit();
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Lấy danh sách người dùng để tặng quà
if ($action === 'get_users') {
    $search = $_GET['search'] ?? '';
    $limit = 20;
    
    $sql = "SELECT Iduser, Name, Money FROM users WHERE Iduser != ?";
    $params = [$userId];
    $types = "i";
    
    if (!empty($search)) {
        $sql .= " AND Name LIKE ?";
        $params[] = "%$search%";
        $types .= "s";
    }
    
    $sql .= " ORDER BY Name LIMIT ?";
    $params[] = $limit;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = [
                'id' => $row['Iduser'],
                'name' => $row['Name'],
                'money' => $row['Money']
            ];
        }
        $stmt->close();
        echo json_encode(['success' => true, 'users' => $users]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi database!']);
    }
    exit();
}

// Tặng tiền
if ($action === 'send_money') {
    $toUserId = (int)($_POST['to_user_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    
    if ($toUserId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Người nhận không hợp lệ!']);
        exit();
    }
    
    if ($amount <= 0 || $amount > 100000000) {
        echo json_encode(['success' => false, 'message' => 'Số tiền phải từ 1 đến 100.000.000 VNĐ!']);
        exit();
    }
    
    // Kiểm tra số dư
    $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user || $user['Money'] < $amount) {
        echo json_encode(['success' => false, 'message' => 'Số dư không đủ!']);
        exit();
    }
    
    // Kiểm tra người nhận có tồn tại không
    $stmt = $conn->prepare("SELECT Iduser FROM users WHERE Iduser = ?");
    $stmt->bind_param("i", $toUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Người nhận không tồn tại!']);
        exit();
    }
    $stmt->close();
    
    // Giới hạn số lần tặng/ngày (tối đa 10 lần/ngày)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM gifts WHERE from_user_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row['count'] >= 10) {
        echo json_encode(['success' => false, 'message' => 'Bạn đã tặng quà tối đa hôm nay (10 lần/ngày)!']);
        exit();
    }
    
    // Bắt đầu transaction
    $conn->begin_transaction();
    
    try {
        // Trừ tiền người gửi
        $stmt = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
        $stmt->bind_param("di", $amount, $userId);
        $stmt->execute();
        $stmt->close();
        
        // Cộng tiền người nhận
        $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
        $stmt->bind_param("di", $amount, $toUserId);
        $stmt->execute();
        $stmt->close();
        
        // Lưu lịch sử tặng quà
        $stmt = $conn->prepare("INSERT INTO gifts (from_user_id, to_user_id, gift_type, gift_value, message, is_claimed, claimed_at) VALUES (?, ?, 'money', ?, ?, 1, NOW())");
        $stmt->bind_param("iids", $userId, $toUserId, $amount, $message);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        // Gửi thông báo cho người nhận
        require_once 'notification_helper.php';
        $senderNameResult = $conn->query("SELECT Name FROM users WHERE Iduser = $userId");
        $senderName = $senderNameResult ? $senderNameResult->fetch_assoc()['Name'] ?? 'Ai đó' : 'Ai đó';
        notifyGiftReceived($conn, $toUserId, $userId, $senderName, 'money', $amount);
        
        echo json_encode(['success' => true, 'message' => 'Tặng quà thành công!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
    exit();
}

// Tặng item (theme, cursor, frame)
if ($action === 'send_item') {
    $toUserId = (int)($_POST['to_user_id'] ?? 0);
    $itemType = $_POST['item_type'] ?? '';
    $itemId = (int)($_POST['item_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    
    if ($toUserId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Người nhận không hợp lệ!']);
        exit();
    }
    
    if (!in_array($itemType, ['theme', 'cursor', 'chat_frame', 'avatar_frame'])) {
        echo json_encode(['success' => false, 'message' => 'Loại item không hợp lệ!']);
        exit();
    }
    
    if ($itemId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Item không hợp lệ!']);
        exit();
    }
    
    // Kiểm tra người gửi có item này không
    $tableMap = [
        'theme' => 'user_themes',
        'cursor' => 'user_cursors',
        'chat_frame' => 'user_chat_frames',
        'avatar_frame' => 'user_avatar_frames'
    ];
    
    $tableName = $tableMap[$itemType];
    
    // Kiểm tra bảng có tồn tại không
    $checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");
    if (!$checkTable || $checkTable->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Hệ thống items chưa được kích hoạt!']);
        exit();
    }
    
    $itemIdColumn = $itemType === 'theme' ? 'theme_id' : ($itemType === 'cursor' ? 'cursor_id' : ($itemType === 'chat_frame' ? 'chat_frame_id' : 'avatar_frame_id'));
    
    $stmt = $conn->prepare("SELECT * FROM $tableName WHERE user_id = ? AND $itemIdColumn = ?");
    $stmt->bind_param("ii", $userId, $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Bạn không sở hữu item này!']);
        exit();
    }
    $stmt->close();
    
    // Kiểm tra người nhận đã có item này chưa
    $stmt = $conn->prepare("SELECT * FROM $tableName WHERE user_id = ? AND $itemIdColumn = ?");
    $stmt->bind_param("ii", $toUserId, $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Người nhận đã có item này rồi!']);
        exit();
    }
    $stmt->close();
    
    // Giới hạn số lần tặng/ngày
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM gifts WHERE from_user_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row['count'] >= 10) {
        echo json_encode(['success' => false, 'message' => 'Bạn đã tặng quà tối đa hôm nay (10 lần/ngày)!']);
        exit();
    }
    
    // Bắt đầu transaction
    $conn->begin_transaction();
    
    try {
        // Xóa item từ người gửi
        $stmt = $conn->prepare("DELETE FROM $tableName WHERE user_id = ? AND $itemIdColumn = ?");
        $stmt->bind_param("ii", $userId, $itemId);
        $stmt->execute();
        $stmt->close();
        
        // Thêm item cho người nhận
        $stmt = $conn->prepare("INSERT INTO $tableName (user_id, $itemIdColumn) VALUES (?, ?)");
        $stmt->bind_param("ii", $toUserId, $itemId);
        $stmt->execute();
        $stmt->close();
        
        // Lưu lịch sử tặng quà
        $stmt = $conn->prepare("INSERT INTO gifts (from_user_id, to_user_id, gift_type, item_id, message, is_claimed, claimed_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
        $stmt->bind_param("iisis", $userId, $toUserId, $itemType, $itemId, $message);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        // Gửi thông báo cho người nhận
        require_once 'notification_helper.php';
        $senderNameResult = $conn->query("SELECT Name FROM users WHERE Iduser = $userId");
        $senderName = $senderNameResult ? $senderNameResult->fetch_assoc()['Name'] ?? 'Ai đó' : 'Ai đó';
        notifyGiftReceived($conn, $toUserId, $userId, $senderName, $itemType, 0);
        
        echo json_encode(['success' => true, 'message' => 'Tặng quà thành công!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
    exit();
}

// Lấy lịch sử tặng/nhận quà
if ($action === 'get_history') {
    $type = $_GET['type'] ?? 'all'; // 'sent', 'received', 'all'
    $limit = (int)($_GET['limit'] ?? 50);
    
    $sql = "SELECT g.*, 
            u1.Name as from_user_name, 
            u2.Name as to_user_name 
            FROM gifts g
            LEFT JOIN users u1 ON g.from_user_id = u1.Iduser
            LEFT JOIN users u2 ON g.to_user_id = u2.Iduser
            WHERE ";
    
    if ($type === 'sent') {
        $sql .= "g.from_user_id = ?";
    } elseif ($type === 'received') {
        $sql .= "g.to_user_id = ?";
    } else {
        $sql .= "(g.from_user_id = ? OR g.to_user_id = ?)";
    }
    
    $sql .= " ORDER BY g.created_at DESC LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($type === 'all') {
            $stmt->bind_param("iii", $userId, $userId, $limit);
        } else {
            $stmt->bind_param("ii", $userId, $limit);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = [
                'id' => $row['id'],
                'from_user_id' => $row['from_user_id'],
                'from_user_name' => $row['from_user_name'],
                'to_user_id' => $row['to_user_id'],
                'to_user_name' => $row['to_user_name'],
                'gift_type' => $row['gift_type'],
                'gift_value' => $row['gift_value'],
                'item_id' => $row['item_id'],
                'message' => $row['message'],
                'created_at' => $row['created_at'],
                'is_claimed' => $row['is_claimed']
            ];
        }
        $stmt->close();
        echo json_encode(['success' => true, 'history' => $history]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi database!']);
    }
    exit();
}

// Lấy số lần tặng quà hôm nay
if ($action === 'get_daily_count') {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM gifts WHERE from_user_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    echo json_encode(['success' => true, 'count' => (int)$row['count'], 'max' => 10]);
    exit();
}

// Lấy items của user để tặng
if ($action === 'get_user_items') {
    $itemType = $_GET['item_type'] ?? '';
    
    if (!in_array($itemType, ['theme', 'cursor', 'chat_frame', 'avatar_frame'])) {
        echo json_encode(['success' => false, 'message' => 'Loại item không hợp lệ!']);
        exit();
    }
    
    $tableMap = [
        'theme' => ['table' => 'user_themes', 'id_col' => 'theme_id', 'name_table' => 'themes', 'name_col' => 'theme_name'],
        'cursor' => ['table' => 'user_cursors', 'id_col' => 'cursor_id', 'name_table' => 'cursors', 'name_col' => 'cursor_name'],
        'chat_frame' => ['table' => 'user_chat_frames', 'id_col' => 'chat_frame_id', 'name_table' => 'chat_frames', 'name_col' => 'frame_name'],
        'avatar_frame' => ['table' => 'user_avatar_frames', 'id_col' => 'avatar_frame_id', 'name_table' => 'avatar_frames', 'name_col' => 'frame_name']
    ];
    
    $config = $tableMap[$itemType];
    $tableName = $config['table'];
    $idCol = $config['id_col'];
    $nameTable = $config['name_table'];
    $nameCol = $config['name_col'];
    
    // Kiểm tra bảng có tồn tại không
    $checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");
    if (!$checkTable || $checkTable->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Hệ thống items chưa được kích hoạt!']);
        exit();
    }
    
    // Lấy items của user
    $sql = "SELECT ut.$idCol, i.$nameCol as name 
            FROM $tableName ut 
            JOIN $nameTable i ON ut.$idCol = i.id 
            WHERE ut.user_id = ? 
            ORDER BY i.$nameCol";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'id' => $row[$idCol],
                'name' => $row['name']
            ];
        }
        $stmt->close();
        echo json_encode(['success' => true, 'items' => $items]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi database!']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!']);
?>

