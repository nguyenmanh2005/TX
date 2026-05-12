<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập!']);
    exit();
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Aliases for Bot compatibility
if ($action === 'register') $action = 'join';
if ($action === 'get_list') $action = 'get_active_list';

if ($action === 'join') {
    $tournamentId = (int)($_POST['tournament_id'] ?? $_GET['tournament_id'] ?? 0);

    // 1. Lấy thông tin giải đấu
    $stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->bind_param("i", $tournamentId);
    $stmt->execute();
    $tour = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$tour) {
        echo json_encode(['status' => 'error', 'message' => 'Giải đấu không tồn tại!']);
        exit();
    }

    if ($tour['status'] !== 'Pending') {
        echo json_encode(['status' => 'error', 'message' => 'Giải đấu này đã bắt đầu hoặc đã kết thúc!']);
        exit();
    }

    // 2. Kiểm tra đã tham gia chưa
    $stmt = $conn->prepare("SELECT id FROM tournament_participants WHERE tournament_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $tournamentId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Bạn đã đăng ký giải đấu này rồi!']);
        $stmt->close();
        exit();
    }
    $stmt->close();

    // 3. Kiểm tra số lượng người chơi
    $stmt = $conn->query("SELECT COUNT(*) as total FROM tournament_participants WHERE tournament_id = $tournamentId");
    $currentCount = $stmt->fetch_assoc()['total'];
    if ($currentCount >= $tour['max_players']) {
        echo json_encode(['status' => 'error', 'message' => 'Giải đấu đã đủ người chơi!']);
        exit();
    }

    // 4. Kiểm tra tiền
    $user = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc();
    if ($user['Money'] < $tour['buy_in']) {
        echo json_encode(['status' => 'error', 'message' => 'Bạn không đủ GTLM để tham gia!']);
        exit();
    }

    $conn->begin_transaction();
    try {
        // Trừ tiền user
        $conn->query("UPDATE users SET Money = Money - {$tour['buy_in']} WHERE Iduser = $userId");

        // Tính toán prize pool (trừ đi phí vận hành)
        $houseFee = $tour['buy_in'] * ($tour['house_fee_percent'] / 100);
        $contributionToPrize = $tour['buy_in'] - $houseFee;

        // Cập nhật prize pool và số người tham gia
        $conn->query("UPDATE tournaments SET prize_pool = prize_pool + $contributionToPrize, current_players = current_players + 1 WHERE id = $tournamentId");

        // Thêm participant
        $stmt = $conn->prepare("INSERT INTO tournament_participants (tournament_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $tournamentId, $userId);
        $stmt->execute();
        $stmt->close();

        // Ghi log
        require_once 'game_history_helper.php';
        if (function_exists('logGameHistory')) {
            logGameHistory($conn, $userId, 'TOURNAMENT JOIN', $tour['buy_in'], 0, false);
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => "Đã tham gia giải đấu thành công! Chúc bạn may mắn."]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
    }

} elseif ($action === 'get_active_list') {
    $sql = "SELECT t.*, 
            (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id) as registered_players,
            (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id AND user_id = ?) as is_joined
            FROM tournaments t
            WHERE t.status IN ('Pending', 'Ongoing')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['status' => 'success', 'tournaments' => $list]);
    $stmt->close();
} elseif ($action === 'get_info') {
    // Trả về thông tin chi tiết giải đấu
    $id = (int)$_POST['id'];
    $stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(['status' => 'success', 'data' => $stmt->get_result()->fetch_assoc()]);
    $stmt->close();
} elseif ($action === 'log_score') {
    $tournamentId = (int)($_POST['tournament_id'] ?? 0);
    $score = (float)($_POST['score'] ?? 0);
    
    if ($tournamentId > 0 && $score > 0) {
        $conn->query("INSERT INTO tournament_scores (tournament_id, user_id, score) VALUES ($tournamentId, $userId, $score)
                     ON DUPLICATE KEY UPDATE score = score + $score");
        echo json_encode(['status' => 'success', 'message' => 'Đã cập nhật điểm số!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ!']);
    }
} elseif ($action === 'claim_reward') {
    // Admin đã tự động trao giải, ở đây chỉ trả về thành công để bot không lỗi
    echo json_encode(['status' => 'success', 'message' => 'Phần thưởng đã được trao tự động vào tài khoản của bạn!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ!']);
}
?>
