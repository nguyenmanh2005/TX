<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';
require_once 'game_history_helper.php';

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$userId = (int)$_SESSION['Iduser'];
$action = $_GET['action'] ?? '';

// 1. Duy trì trạng thái phòng (Auto-manage rooms)
function manageRoom(mysqli $conn) {
    // Tìm phòng đang đợi hoặc đang đua
    $stmt = $conn->prepare("SELECT * FROM horserace_pvp_rooms WHERE status IN ('waiting', 'racing') ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();

    if (!$room) {
        // Tạo phòng mới đếm ngược 60s
        $startTime = date('Y-m-d H:i:s', strtotime('+60 seconds'));
        $stmt = $conn->prepare("INSERT INTO horserace_pvp_rooms (status, start_time) VALUES ('waiting', ?)");
        $stmt->bind_param("s", $startTime);
        $stmt->execute();
        return manageRoom($conn);
    }

    $now = time();
    $start = strtotime($room['start_time']);

    // Nếu đang đợi và đã quá giờ bắt đầu -> Chuyển sang đua
    if ($room['status'] === 'waiting' && $now >= $start) {
        $stmt = $conn->prepare("UPDATE horserace_pvp_rooms SET status = 'racing' WHERE id = ?");
        $stmt->bind_param("i", $room['id']);
        $stmt->execute();
        $room['status'] = 'racing';
    }

    // Nếu đang đua quá 15s -> Kết thúc và trả thưởng
    if ($room['status'] === 'racing' && $now >= ($start + 15)) {
        $winner = rand(1, 6);
        $stmt = $conn->prepare("UPDATE horserace_pvp_rooms SET status = 'finished', winner_horse = ? WHERE id = ?");
        $stmt->bind_param("ii", $winner, $room['id']);
        $stmt->execute();
        
        // Trả thưởng (X6.0)
        $stmt = $conn->prepare("SELECT * FROM horserace_pvp_bets WHERE room_id = ?");
        $stmt->bind_param("i", $room['id']);
        $stmt->execute();
        $bets = $stmt->get_result();

        while ($b = $bets->fetch_assoc()) {
            $winAmount = 0;
            $isWin = false;
            if ($b['horse_id'] == $winner) {
                $winAmount = (float)$b['amount'] * 6.0;
                $isWin = true;
                
                $upd = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
                $upd->bind_param("di", $winAmount, $b['user_id']);
                $upd->execute();
                
                $updBet = $conn->prepare("UPDATE horserace_pvp_bets SET win_amount = ? WHERE id = ?");
                $updBet->bind_param("di", $winAmount, $b['id']);
                $updBet->execute();
            }
            
            // Log game history cho từng người chơi
            logGameHistory($conn, (int)$b['user_id'], 'PvP Horse Racing', (float)$b['amount'], (float)$winAmount, $isWin);
        }
        
        $room['status'] = 'finished';
        $room['winner_horse'] = $winner;
    }

    return $room;
}

if ($action === 'get_state') {
    $room = manageRoom($conn);
    $stmt = $conn->prepare("SELECT * FROM horserace_pvp_bets WHERE room_id = ? ORDER BY id DESC");
    $stmt->bind_param("i", $room['id']);
    $stmt->execute();
    $bets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'room' => $room,
        'bets' => $bets,
        'server_time' => date('Y-m-d H:i:s')
    ]);
    exit;
}

if ($action === 'place_bet') {
    $horseId = (int)$_POST['horse_id'];
    $amount = (float)$_POST['amount'];

    if ($amount < 1000) {
        echo json_encode(['success' => false, 'message' => 'Tối thiểu 1.000 gtlm']);
        exit;
    }

    $room = manageRoom($conn);
    if ($room['status'] !== 'waiting') {
        echo json_encode(['success' => false, 'message' => 'Cuộc đua đã bắt đầu, không thể đặt cược!']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Check balance securely
        $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if ($user['Money'] < $amount) throw new Exception("Không đủ  Gtlm!");

        $stmt = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
        $stmt->bind_param("di", $amount, $userId);
        $stmt->execute();

        $stmt = $conn->prepare("INSERT INTO horserace_pvp_bets (room_id, user_id, horse_id, amount) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $room['id'], $userId, $horseId, $amount);
        $stmt->execute();
        
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
