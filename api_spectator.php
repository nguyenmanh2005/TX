<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập!']);
    exit();
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? '';

// Bắt đầu "stream" (đăng ký session live)
if ($action === 'start_stream') {
    $gameType = $_POST['game_type'] ?? 'Unknown';
    $stmt = $conn->prepare("INSERT INTO live_streams (user_id, game_type, status) VALUES (?, ?, 'live')");
    $stmt->bind_param("is", $userId, $gameType);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'stream_id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

// Gửi Tip cho streamer
if ($action === 'tip') {
    $streamId = (int)$_POST['stream_id'];
    $amount = (float)$_POST['amount'];
    $message = trim($_POST['message'] ?? '');

    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Số tiền không hợp lệ!']);
        exit();
    }

    $conn->begin_transaction();
    try {
        // Lấy Id người nhận từ stream
        $stmtS = $conn->prepare("SELECT user_id FROM live_streams WHERE id = ?");
        $stmtS->bind_param("i", $streamId);
        $stmtS->execute();
        $stream = $stmtS->get_result()->fetch_assoc();
        if (!$stream) throw new Exception("Stream không tồn tại.");
        $receiverId = $stream['user_id'];

        // Kiểm tra tiền người gửi
        $stmtM = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
        $stmtM->bind_param("i", $userId);
        $stmtM->execute();
        $sender = $stmtM->get_result()->fetch_assoc();
        if ($sender['Money'] < $amount) throw new Exception("Không đủ GTLM!");

        // Chuyển tiền
        $conn->query("UPDATE users SET Money = Money - $amount WHERE Iduser = $userId");
        $conn->query("UPDATE users SET Money = Money + $amount WHERE Iduser = $receiverId");

        // Lưu log tip
        $stmtL = $conn->prepare("INSERT INTO stream_tips (stream_id, from_user_id, amount, message) VALUES (?, ?, ?, ?)");
        $stmtL->bind_param("iids", $streamId, $userId, $amount, $message);
        $stmtL->execute();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Đã Tip thành công!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Lấy danh sách live
if ($action === 'get_live') {
    $sql = "SELECT ls.*, u.Name as streamer_name 
            FROM live_streams ls 
            JOIN users u ON ls.user_id = u.Iduser 
            WHERE ls.status = 'live' 
            ORDER BY ls.started_at DESC";
    $res = $conn->query($sql);
    $lives = [];
    while($row = $res->fetch_assoc()) {
        $lives[] = $row;
    }
    echo json_encode(['success' => true, 'lives' => $lives]);
    exit();
}
?>
