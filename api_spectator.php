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

switch ($action) {
    case 'get_live':
        // Lấy danh sách các trận đang live từ bảng live_streams
        $sql = "SELECT s.*, u.Name as streamer_name 
                FROM live_streams s 
                JOIN users u ON s.user_id = u.Iduser 
                WHERE s.status = 'live' 
                ORDER BY s.started_at DESC";
        $res = $conn->query($sql);
        $lives = [];
        while ($row = $res->fetch_assoc()) $lives[] = $row;
        echo json_encode(['success' => true, 'lives' => $lives]);
        break;

    case 'get_details':
        $streamId = (int)$_GET['stream_id'];
        
        // 1. Thông tin trận đấu
        $stream = $conn->query("SELECT s.*, u.Name as streamer_name FROM live_streams s JOIN users u ON s.user_id = u.Iduser WHERE s.id = $streamId")->fetch_assoc();
        
        if (!$stream) {
            echo json_encode(['success' => false, 'message' => 'Trận đấu không tồn tại hoặc đã kết thúc.']);
            exit;
        }

        // 2. Lấy cược của người xem hiện tại
        $myBet = $conn->query("SELECT * FROM spectator_bets WHERE game_id = $streamId AND user_id = $userId AND status = 'pending'")->fetch_assoc();

        // 3. Lấy Chat (10 tin gần nhất)
        $chatRes = $conn->query("SELECT c.*, u.Name as user_name FROM spectator_chat c JOIN users u ON c.user_id = u.Iduser WHERE c.game_id = $streamId ORDER BY c.created_at DESC LIMIT 15");
        $chats = [];
        while ($c = $chatRes->fetch_assoc()) $chats[] = $c;

        // 4. Lấy Reactions (trong 10 giây gần nhất để hiển thị hiệu ứng bay)
        $reactRes = $conn->query("SELECT * FROM spectator_reactions WHERE game_id = $streamId AND created_at >= DATE_SUB(NOW(), INTERVAL 5 SECOND)");
        $reactions = [];
        while ($r = $reactRes->fetch_assoc()) $reactions[] = $r;

        echo json_encode([
            'success' => true, 
            'stream' => $stream, 
            'my_bet' => $myBet, 
            'chats' => array_reverse($chats),
            'reactions' => $reactions
        ]);
        break;

    case 'place_bet':
        $streamId = (int)$_POST['stream_id'];
        $betOnUser = (int)$_POST['bet_on_user']; // ID người chơi được đặt cược (thường là streamer)
        $amount = (int)$_POST['amount'];

        if ($amount < 1000) {
            echo json_encode(['success' => false, 'message' => 'Cược tối thiểu 1,000 gtlm.']);
            exit;
        }

        $conn->begin_transaction();
        try {
            // Kiểm tra trận đấu còn live không
            $stream = $conn->query("SELECT * FROM live_streams WHERE id = $streamId FOR UPDATE")->fetch_assoc();
            if (!$stream || $stream['status'] !== 'live') throw new Exception("Trận đấu đã kết thúc!");

            // Kiểm tra số dư
            $uRes = $conn->query("SELECT Money FROM users WHERE Iduser = $userId FOR UPDATE");
            $userMoney = $uRes->fetch_assoc()['Money'];
            if ($userMoney < $amount) throw new Exception("Số dư không đủ!");

            // Lưu cược
            $stmt = $conn->prepare("INSERT INTO spectator_bets (user_id, game_id, game_type, bet_on_user, amount) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisii", $userId, $streamId, $stream['game_type'], $betOnUser, $amount);
            $stmt->execute();

            // Trừ  Gtlm
            $conn->query("UPDATE users SET Money = Money - $amount WHERE Iduser = $userId");

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Đặt cược thành công!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'send_reaction':
        $streamId = (int)$_POST['stream_id'];
        $emoji = mb_substr($_POST['emoji'] ?? '❤️', 0, 10);
        // FIX: Prepared Statement cho Reaction
        $stmt = $conn->prepare("INSERT INTO spectator_reactions (game_id, user_id, emoji) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $streamId, $userId, $emoji);
        $stmt->execute();
        echo json_encode(['success' => true]);
        break;

    case 'send_chat':
        $streamId = (int)$_POST['stream_id'];
        $message = strip_tags($_POST['message'] ?? '');
        if (empty($message)) exit;

        // FIX: Prepared Statement cho Chat
        $stmt = $conn->prepare("INSERT INTO spectator_chat (game_id, user_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $streamId, $userId, $message);
        $stmt->execute();
        echo json_encode(['success' => true]);
        break;

    case 'tip':
        $streamId = (int)$_POST['stream_id'];
        $amount = (int)$_POST['amount'];

        if ($amount <= 0) exit;

        $conn->begin_transaction();
        try {
            // FIX: Khóa số dư của người gửi
            $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $userMoney = $stmt->get_result()->fetch_assoc()['Money'];

            if ($userMoney < $amount) throw new Exception("Số dư không đủ!");

            $stream = $conn->query("SELECT * FROM live_streams WHERE id = $streamId FOR UPDATE")->fetch_assoc();
            $streamerId = $stream['user_id'];

            // Chuyển  Gtlm an toàn
            $stmt = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
            $stmt->bind_param("ii", $amount, $userId);
            $stmt->execute();

            $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            $stmt->bind_param("ii", $amount, $streamerId);
            $stmt->execute();

            // Lưu log tip
            $stmt = $conn->prepare("INSERT INTO stream_tips (stream_id, from_user_id, amount) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $streamId, $userId, $amount);
            $stmt->execute();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => "Đã Tip cho streamer " . number_format($amount) . " gtlm!"]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
}
?>
