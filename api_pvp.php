<?php
/**
 * 🧠 PvP Arena Backend API
 * Xử lý đồng bộ trạng thái và tính toán kết quả trận đấu.
 */
require_once 'db_connect.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_GET['action'] ?? '';
$matchId = (int)($_GET['id'] ?? 0);

if ($matchId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid match ID']);
    exit;
}

// Lấy thông tin trận đấu
$stmt = $conn->prepare("SELECT * FROM pvp_challenges WHERE id = ?");
$stmt->bind_param("i", $matchId);
$stmt->execute();
$match = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$match) {
    echo json_encode(['success' => false, 'message' => 'Match not found']);
    exit;
}

// 1. SYNC STATE: Cập nhật "last seen" và kiểm tra đối thủ
if ($action === 'sync') {
    $now = date('Y-m-d H:i:s');
    // Cập nhật timestamp để báo hiệu đang online trong arena
    $isChallenger = ($userId == $match['challenger_id']);
    $field = $isChallenger ? 'updated_at' : 'updated_at'; // Dùng chung updated_at làm nhịp tim
    
    $conn->query("UPDATE pvp_challenges SET updated_at = NOW() WHERE id = $matchId");
    
    // Giả lập kiểm tra đối thủ (trong môi trường thực tế cần 2 cột last_seen_1 và last_seen_2)
    // Ở đây ta đơn giản hóa: nếu trận đấu ở trạng thái 'accepted' hoặc 'fighting' thì coi như sẵn sàng
    echo json_encode([
        'success' => true,
        'status' => $match['status'],
        'opponent_online' => ($match['status'] !== 'pending')
    ]);
    exit;
}

// 2. GET RESULT: Tính toán thắng thua
if ($action === 'get_result') {
    if ($match['status'] === 'finished') {
        // Trận đấu đã xong, trả về kết quả cũ
        echo json_encode([
            'winner_id' => (int)$match['winner_id'],
            'reward' => (int)($match['bet_amount'] * 0.95), // Trừ 5% phí sàn
            'bet' => (int)$match['bet_amount']
        ]);
        exit;
    }

    // Nếu chưa xong, thực hiện tính toán (Chỉ người thách đấu hoặc hệ thống mới được trigger)
    $conn->begin_transaction();
    try {
        // Thuật toán thắng thua: 50/50 hoặc dựa trên chỉ số (XP/Level)
        // Lấy thông tin 2 user để so sánh level
        $u1 = $conn->query("SELECT level FROM users WHERE Iduser = " . $match['challenger_id'])->fetch_assoc();
        $u2 = $conn->query("SELECT level FROM users WHERE Iduser = " . $match['challenged_id'])->fetch_assoc();
        
        $chance1 = 50 + ($u1['level'] - $u2['level']) * 2; // Mỗi level lệch +2% tỉ lệ thắng
        $chance1 = max(20, min(80, $chance1)); // Giới hạn 20-80%

        $rand = rand(1, 100);
        $winnerId = ($rand <= $chance1) ? $match['challenger_id'] : $match['challenged_id'];
        $loserId = ($winnerId == $match['challenger_id']) ? $match['challenged_id'] : $match['challenger_id'];
        
        $bet = $match['bet_amount'];
        $reward = floor($bet * 1.95); // Thắng nhận 1.95 lần (phí sàn 5% trên tiền thắng)

        // Cập nhật số dư
        $conn->query("UPDATE users SET Money = Money + $reward WHERE Iduser = $winnerId");
        // Tiền thua đã trừ lúc đặt thách đấu (giả định logic challenge hiện tại đã trừ)
        // Nếu chưa trừ, ta trừ ở đây:
        // $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $loserId");

        // Cập nhật trạng thái trận đấu
        $stmt = $conn->prepare("UPDATE pvp_challenges SET status = 'finished', winner_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $winnerId, $matchId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        echo json_encode([
            'winner_id' => (int)$winnerId,
            'reward' => (int)($bet * 0.95),
            'bet' => (int)$bet
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
