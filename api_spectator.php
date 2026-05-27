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
        $stmt = $conn->prepare("SELECT s.id, s.user_id, s.game_type, s.status, s.started_at, u.Name as streamer_name 
                                FROM live_streams s 
                                JOIN users u ON s.user_id = u.Iduser 
                                WHERE s.status = 'live' 
                                ORDER BY s.started_at DESC");
        $stmt->execute();
        $res = $stmt->get_result();
        $lives = [];
        while ($row = $res->fetch_assoc()) $lives[] = $row;
        $stmt->close();
        echo json_encode(['success' => true, 'lives' => $lives]);
        break;

    case 'get_details':
        $streamId = (int)($_GET['stream_id'] ?? 0);
        
        // 1. Thông tin trận đấu (Chỉ lấy các cột công khai)
        $stmt = $conn->prepare("SELECT s.id, s.user_id, s.game_type, s.status, s.started_at, u.Name as streamer_name 
                                FROM live_streams s 
                                JOIN users u ON s.user_id = u.Iduser 
                                WHERE s.id = ? AND s.status = 'live'");
        $stmt->bind_param("i", $streamId);
        $stmt->execute();
        $stream = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$stream) {
            echo json_encode(['success' => false, 'message' => 'Trận đấu không tồn tại hoặc đã kết thúc.']);
            exit;
        }

        // 2. Lấy cược của người xem hiện tại
        $stmt = $conn->prepare("SELECT * FROM spectator_bets WHERE game_id = ? AND user_id = ? AND status = 'pending'");
        $stmt->bind_param("ii", $streamId, $userId);
        $stmt->execute();
        $myBet = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // 3. Lấy Chat (15 tin gần nhất)
        $stmt = $conn->prepare("SELECT c.*, u.Name as user_name FROM spectator_chat c JOIN users u ON c.user_id = u.Iduser WHERE c.game_id = ? ORDER BY c.created_at DESC LIMIT 15");
        $stmt->bind_param("i", $streamId);
        $stmt->execute();
        $chatRes = $stmt->get_result();
        $chats = [];
        while ($c = $chatRes->fetch_assoc()) $chats[] = $c;
        $stmt->close();

        // 4. Lấy Reactions (trong 5 giây gần nhất)
        $stmt = $conn->prepare("SELECT * FROM spectator_reactions WHERE game_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 SECOND)");
        $stmt->bind_param("i", $streamId);
        $stmt->execute();
        $reactRes = $stmt->get_result();
        $reactions = [];
        while ($r = $reactRes->fetch_assoc()) $reactions[] = $r;
        $stmt->close();

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
            $stmt = $conn->prepare("SELECT status, game_type FROM live_streams WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $streamId);
            $stmt->execute();
            $stream = $stmt->get_result()->fetch_assoc();
            if (!$stream || $stream['status'] !== 'live') throw new Exception("Trận đấu đã kết thúc!");

            // Kiểm tra số dư
            $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $userMoney = $stmt->get_result()->fetch_assoc()['Money'];
            if ($userMoney < $amount) throw new Exception("Số dư không đủ!");

            // Lưu cược
            $stmt = $conn->prepare("INSERT INTO spectator_bets (user_id, game_id, game_type, bet_on_user, amount) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisii", $userId, $streamId, $stream['game_type'], $betOnUser, $amount);
            $stmt->execute();

            // Trừ  Gtlm
            $stmt = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
            $stmt->bind_param("ii", $amount, $userId);
            $stmt->execute();

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

            $stmt = $conn->prepare("SELECT user_id FROM live_streams WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $streamId);
            $stmt->execute();
            $stream = $stmt->get_result()->fetch_assoc();
            if (!$stream) throw new Exception("Trận đấu không tồn tại!");
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

    case 'purchase_buff':
        $streamId = (int)$_POST['stream_id'];
        $buffType = $_POST['buff_type'] ?? '';

        $costs = [
            'luck' => 15000,
            'hype' => 25000,
            'shield' => 20000
        ];

        if (!isset($costs[$buffType])) {
            echo json_encode(['success' => false, 'message' => 'Loại bùa cổ vũ không hợp lệ!']);
            exit;
        }

        $cost = $costs[$buffType];

        $conn->begin_transaction();
        try {
            // Check spectator balance
            $stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $specData = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$specData || $specData['Money'] < $cost) {
                throw new Exception("Số dư GTLM của bạn không đủ!");
            }

            // Get streamer info
            $stmt = $conn->prepare("SELECT s.user_id, u.Name as streamer_name FROM live_streams s JOIN users u ON s.user_id = u.Iduser WHERE s.id = ? FOR UPDATE");
            $stmt->bind_param("i", $streamId);
            $stmt->execute();
            $stream = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$stream) {
                throw new Exception("Luồng live không tồn tại!");
            }

            $streamerId = $stream['user_id'];
            $streamerName = $stream['streamer_name'];

            // Deduct cost from spectator
            $stmt = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
            $stmt->bind_param("di", $cost, $userId);
            $stmt->execute();
            $stmt->close();

            // Log spectator transaction
            $stmt = $conn->prepare("INSERT INTO bot_transactions (user_id, amount, type, reason) VALUES (?, ?, 'spend', ?)");
            $reasonSpec = "Cổ vũ bùa " . strtoupper($buffType) . " cho Idol $streamerName";
            $stmt->bind_param("ids", $userId, $cost, $reasonSpec);
            $stmt->execute();
            $stmt->close();

            // Add buff to streamer
            require_once __DIR__ . '/user_buff_helper.php';
            UserBuffHelper::addBuff($conn, $streamerId, $buffType, 3);

            // Add a high-fidelity system message to spectator chat
            $buffNames = [
                'luck' => '🍀 Bùa May Mắn',
                'hype' => '🚀 Tên Lửa Hype',
                'shield' => '🛡️ Khiên Hộ Mệnh'
            ];
            $chatMsg = "🎉 *" . htmlspecialchars($specData['Name']) . "* vừa gửi tặng **" . $buffNames[$buffType] . "** cổ vũ cho Idol **$streamerName**! (3 ván hiệu lực)";
            
            $stmt = $conn->prepare("INSERT INTO spectator_chat (game_id, user_id, message) VALUES (?, 1, ?)"); // user_id 1 is system
            $stmt->bind_param("is", $streamId, $chatMsg);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => "Cổ vũ thành công! Đã bơm " . $buffNames[$buffType] . " cho Idol $streamerName!"
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
}
?>
