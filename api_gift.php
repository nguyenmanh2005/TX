<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

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
            $users[] = ['id' => $row['Iduser'], 'name' => $row['Name'], 'money' => $row['Money']];
        }
        $stmt->close();
        echo json_encode(['success' => true, 'users' => $users]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi database!']);
    }
    exit();
}

// Tặng gtlm
    $toUserId = (int)($_POST['to_user_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $giftWrap = $_POST['gift_wrap'] ?? 'standard';
    $isAnonymous = (int)($_POST['is_anonymous'] ?? 0);

    if ($toUserId <= 0) exit(json_encode(['success' => false, 'message' => 'Người nhận không hợp lệ!']));
    if ($amount <= 0 || $amount > 100000000) exit(json_encode(['success' => false, 'message' => 'Số gtlm từ 1 - 100M!']));

    $conn->begin_transaction();
    try {
        // FIX: Khóa bản ghi user để tránh Race Condition
        $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || $user['Money'] < $amount) throw new Exception("Số Gtlm không đủ!");

        $tax = $amount * 0.02;
        $receivedAmount = $amount - $tax;

        // Trừ  Gtlm sender
        $stmt = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
        $stmt->bind_param("di", $amount, $userId);
        $stmt->execute();

        // Cộng  Gtlm receiver
        $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
        $stmt->bind_param("di", $receivedAmount, $toUserId);
        $stmt->execute();

        $stmt = $conn->prepare("INSERT INTO gifts (from_user_id, to_user_id, gift_type, gift_value, message, gift_wrap, is_anonymous, is_claimed, claimed_at) VALUES (?, ?, 'money', ?, ?, ?, ?, 1, NOW())");
        $stmt->bind_param("iidssii", $userId, $toUserId, $receivedAmount, $message, $giftWrap, $isAnonymous);
        $stmt->execute();

        $conn->commit();

        require_once 'notification_helper.php';
        $senderName = $isAnonymous ? 'Người bí ẩn 👤' : ($user['Name'] ?? 'Ai đó');
        notifyGiftReceived($conn, $toUserId, $userId, $senderName, 'money', $receivedAmount);

        echo json_encode(['success' => true, 'message' => 'Tặng quà thành công!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();


// Tặng item (theme, cursor, frame)
if ($action === 'send_item') {
    $toUserId = (int)$_POST['to_user_id'];
    $itemType = $_POST['item_type'];
    $itemId = (int)$_POST['item_id'];
    $message = trim($_POST['message']);
    $giftWrap = $_POST['gift_wrap'] ?? 'standard';
    $isAnonymous = (int)($_POST['is_anonymous'] ?? 0);

    $tableMap = ['theme' => 'user_themes', 'cursor' => 'user_cursors', 'chat_frame' => 'user_chat_frames', 'avatar_frame' => 'user_avatar_frames'];
    if (!isset($tableMap[$itemType])) exit(json_encode(['success' => false, 'message' => 'Loại item không hợp lệ!']));

    $tableName = $tableMap[$itemType];
    $idCol = $itemType === 'theme' ? 'theme_id' : ($itemType === 'cursor' ? 'cursor_id' : ($itemType === 'chat_frame' ? 'chat_frame_id' : 'avatar_frame_id'));

    $conn->begin_transaction();
    try {
        // Kiểm tra sở hữu
        $stmt = $conn->prepare("SELECT 1 FROM $tableName WHERE user_id = ? AND $idCol = ?");
        $stmt->bind_param("ii", $userId, $itemId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) throw new Exception("Bạn không sở hữu vật phẩm này!");

        // Chuyển sở hữu
        $stmt = $conn->prepare("DELETE FROM $tableName WHERE user_id = ? AND $idCol = ?");
        $stmt->bind_param("ii", $userId, $itemId);
        $stmt->execute();

        $stmt = $conn->prepare("INSERT INTO $tableName (user_id, $idCol) VALUES (?, ?)");
        $stmt->bind_param("ii", $toUserId, $itemId);
        $stmt->execute();
        
        $stmt = $conn->prepare("INSERT INTO gifts (from_user_id, to_user_id, gift_type, item_id, message, gift_wrap, is_anonymous, is_claimed, claimed_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
        $stmt->bind_param("iissisii", $userId, $toUserId, $itemType, $itemId, $message, $giftWrap, $isAnonymous);
        $stmt->execute();

        $conn->commit();

        require_once 'notification_helper.php';
        $senderName = $isAnonymous ? 'Người bí ẩn 👤' : 'Bạn';
        notifyGiftReceived($conn, $toUserId, $userId, $senderName, $itemType, 0);

        echo json_encode(['success' => true, 'message' => 'Tặng quà thành công!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Lấy lịch sử tặng/nhận quà
if ($action === 'get_history') {
    $type = $_GET['type'] ?? 'all';
    $limit = (int)($_GET['limit'] ?? 50);

    if ($type === 'sent') {
        $sql = "SELECT g.*, u1.Name as from_user_name, u2.Name as to_user_name FROM gifts g LEFT JOIN users u1 ON g.from_user_id = u1.Iduser LEFT JOIN users u2 ON g.to_user_id = u2.Iduser WHERE g.from_user_id = ? ORDER BY g.created_at DESC LIMIT ?";
    } elseif ($type === 'received') {
        $sql = "SELECT g.*, u1.Name as from_user_name, u2.Name as to_user_name FROM gifts g LEFT JOIN users u1 ON g.from_user_id = u1.Iduser LEFT JOIN users u2 ON g.to_user_id = u2.Iduser WHERE g.to_user_id = ? ORDER BY g.created_at DESC LIMIT ?";
    } else {
        $sql = "SELECT g.*, u1.Name as from_user_name, u2.Name as to_user_name FROM gifts g LEFT JOIN users u1 ON g.from_user_id = u1.Iduser LEFT JOIN users u2 ON g.to_user_id = u2.Iduser WHERE (g.from_user_id = ? OR g.to_user_id = ?) ORDER BY g.created_at DESC LIMIT ?";
    }

    $stmt = $conn->prepare($sql);
    if ($type === 'all') $stmt->bind_param("iii", $userId, $userId, $limit);
    else $stmt->bind_param("ii", $userId, $limit);
    
    $stmt->execute();
    $res = $stmt->get_result();
    $history = [];
    while ($row = $res->fetch_assoc()) {
        if ($row['is_anonymous'] && $row['from_user_id'] != $userId) {
            $row['from_user_name'] = 'Người bí ẩn 👤';
        }
        $history[] = $row;
    }
    echo json_encode(['success' => true, 'history' => $history]);
    exit();
}

if ($action === 'get_daily_count') {
    $row = $conn->query("SELECT COUNT(*) as count FROM gifts WHERE from_user_id = $userId AND DATE(created_at) = CURDATE()")->fetch_assoc();
    echo json_encode(['success' => true, 'count' => (int)$row['count'], 'max' => 10]); // Nâng lên 10 cho thoải mái
    exit();
}

if ($action === 'get_user_items') {
    $itemType = $_GET['item_type'];
    $tableMap = [
        'theme' => ['table' => 'user_themes', 'id_col' => 'theme_id', 'name_table' => 'themes', 'name_col' => 'theme_name'],
        'cursor' => ['table' => 'user_cursors', 'id_col' => 'cursor_id', 'name_table' => 'cursors', 'name_col' => 'cursor_name'],
        'chat_frame' => ['table' => 'user_chat_frames', 'id_col' => 'chat_frame_id', 'name_table' => 'chat_frames', 'name_col' => 'frame_name'],
        'avatar_frame' => ['table' => 'user_avatar_frames', 'id_col' => 'avatar_frame_id', 'name_table' => 'avatar_frames', 'name_col' => 'frame_name']
    ];
    $config = $tableMap[$itemType];
    $sql = "SELECT ut.{$config['id_col']}, i.{$config['name_col']} as name FROM {$config['table']} ut JOIN {$config['name_table']} i ON ut.{$config['id_col']} = i.id WHERE ut.user_id = $userId";
    $res = $conn->query($sql);
    $items = [];
    while ($r = $res->fetch_assoc()) $items[] = ['id' => $r[$config['id_col']], 'name' => $r['name']];
    echo json_encode(['success' => true, 'items' => $items]);
    exit();
}
?>