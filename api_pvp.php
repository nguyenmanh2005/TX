<?php
/**
 * 🧠 PvP Arena Backend API
 * Xử lý đồng bộ trạng thái và tính toán kết quả trận đấu.
 */
require_once 'db_connect.php';
require_once 'admin_helper.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['Iduser'];
session_write_close(); // Tránh bị treo do Session Lock trong quá trình polling

$isAdmin = isAdmin($conn, $userId);
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

// 3. ADMIN ACTIONS: Hủy trận đấu hoặc xử thắng thua (Chỉ dành cho Admin)
if ($action === 'admin_cancel') {
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền admin!']);
        exit;
    }
    if ($match['status'] === 'finished' || $match['status'] === 'completed' || $match['status'] === 'cancelled') {
        echo json_encode(['success' => false, 'message' => 'Trận đấu đã kết thúc trước đó!']);
        exit;
    }
    
    $conn->begin_transaction();
    try {
        $bet = (double)$match['bet_amount'];
        $p1 = (int)$match['challenger_id'];
        $p2 = (int)$match['opponent_id'];
        
        // Hoàn GTLM cho 2 bên
        $stmtRefund1 = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
        $stmtRefund1->bind_param("di", $bet, $p1);
        $stmtRefund1->execute();
        $stmtRefund1->close();
        
        $stmtRefund2 = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
        $stmtRefund2->bind_param("di", $bet, $p2);
        $stmtRefund2->execute();
        $stmtRefund2->close();
        
        // Hủy trận
        $stmtCancel = $conn->prepare("UPDATE pvp_challenges SET status = 'cancelled' WHERE id = ?");
        $stmtCancel->bind_param("i", $matchId);
        $stmtCancel->execute();
        $stmtCancel->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Đã hủy trận đấu và hoàn trả GTLM cược!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'admin_force_result') {
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền admin!']);
        exit;
    }
    if ($match['status'] === 'finished' || $match['status'] === 'completed' || $match['status'] === 'cancelled') {
        echo json_encode(['success' => false, 'message' => 'Trận đấu đã kết thúc trước đó!']);
        exit;
    }
    
    $winnerId = (int)($_POST['winner_id'] ?? $_GET['winner_id'] ?? 0);
    if ($winnerId !== (int)$match['challenger_id'] && $winnerId !== (int)$match['opponent_id']) {
        echo json_encode(['success' => false, 'message' => 'ID người thắng cuộc không hợp lệ!']);
        exit;
    }
    
    $conn->begin_transaction();
    try {
        $bet = $match['bet_amount'];
        $reward = floor($bet * 1.95);
        
        // Thưởng GTLM cho người thắng
        $stmtWin = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
        $stmtWin->bind_param("di", $reward, $winnerId);
        $stmtWin->execute();
        $stmtWin->close();
        
        // Cập nhật kết quả pvp_challenges
        $stmt = $conn->prepare("UPDATE pvp_challenges SET status = 'finished', winner_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $winnerId, $matchId);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Xử thắng cuộc thành công!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'admin_delete') {
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền admin!']);
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM pvp_challenges WHERE id = ?");
    $stmt->bind_param("i", $matchId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Đã xóa bản ghi trận đấu thành công!']);
    exit;
}

// 1. SYNC STATE: Cập nhật "last seen" và kiểm tra đối thủ
if ($action === 'sync') {
    $now = date('Y-m-d H:i:s');
    $isChallenger = ($userId == $match['challenger_id']);
    $isChallenged = ($userId == $match['opponent_id']);
    
    // Cập nhật timestamp chỉ nếu user là một trong 2 đấu thủ
    if ($isChallenger || $isChallenged) {
        $field = $isChallenger ? 'last_seen_challenger' : 'last_seen_challenged';
        $stmtUpd = $conn->prepare("UPDATE pvp_challenges SET {$field} = NOW() WHERE id = ?");
        $stmtUpd->bind_param("i", $matchId);
        $stmtUpd->execute();
        $stmtUpd->close();
    }
    
    // Đọc trạng thái mới nhất từ DB
    $stmtGet = $conn->prepare("SELECT status, last_seen_challenger, last_seen_challenged, opponent_id, challenger_id FROM pvp_challenges WHERE id = ?");
    $stmtGet->bind_param("i", $matchId);
    $stmtGet->execute();
    $latestMatch = $stmtGet->get_result()->fetch_assoc();
    $stmtGet->close();

    $opponentId = $isChallenger ? $latestMatch['opponent_id'] : $latestMatch['challenger_id'];
    
    // Kiểm tra xem opponent có phải là BOT không
    $stmtOpp = $conn->prepare("SELECT Name FROM users WHERE Iduser = ?");
    $stmtOpp->bind_param("i", $opponentId);
    $stmtOpp->execute();
    $oppRow = $stmtOpp->get_result()->fetch_assoc();
    $stmtOpp->close();
    $isOpponentBot = ($oppRow && stripos($oppRow['Name'], 'BOT') === 0);

    $opponentField = $isChallenger ? 'last_seen_challenged' : 'last_seen_challenger';
    $opponentOnline = false;
    if ($isOpponentBot) {
        $opponentOnline = true; // Bot luôn sẵn sàng
    } elseif (!empty($latestMatch[$opponentField])) {
        $oppTime = strtotime($latestMatch[$opponentField]);
        // Nếu đối thủ hoạt động trong 10 giây gần nhất
        if (time() - $oppTime <= 10) {
            $opponentOnline = true;
        }
    }
    
    // Nếu đối thủ là BOT hoặc NẾU MÌNH LÀ NGƯỜI BỊ THÁCH ĐẤU và trạng thái đang là pending, tự động đổi thành accepted
    if (($isOpponentBot || !$isChallenger) && $latestMatch['status'] === 'pending') {
        $stmtUpdStatus = $conn->prepare("UPDATE pvp_challenges SET status = 'accepted', accepted_at = NOW() WHERE id = ?");
        $stmtUpdStatus->bind_param("i", $matchId);
        $stmtUpdStatus->execute();
        $stmtUpdStatus->close();
        $latestMatch['status'] = 'accepted';
    }
    
    echo json_encode([
        'success' => true,
        'status' => $latestMatch['status'],
        'opponent_online' => $opponentOnline
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
        // Lấy thông tin 2 user để so sánh level (từ bảng user_progress)
        $stmtU1 = $conn->prepare("SELECT level FROM user_progress WHERE user_id = ?");
        $stmtU1->bind_param("i", $match['challenger_id']);
        $stmtU1->execute();
        $resU1 = $stmtU1->get_result()->fetch_assoc();
        $u1Level = $resU1 ? (int)$resU1['level'] : 1;
        $stmtU1->close();

        $stmtU2 = $conn->prepare("SELECT level FROM user_progress WHERE user_id = ?");
        $stmtU2->bind_param("i", $match['opponent_id']);
        $stmtU2->execute();
        $resU2 = $stmtU2->get_result()->fetch_assoc();
        $u2Level = $resU2 ? (int)$resU2['level'] : 1;
        $stmtU2->close();
        
        $chance1 = 50 + ($u1Level - $u2Level) * 2; // Mỗi level lệch +2% tỉ lệ thắng
        $chance1 = max(20, min(80, $chance1)); // Giới hạn 20-80%

        $rand = rand(1, 100);
        $winnerId = ($rand <= $chance1) ? $match['challenger_id'] : $match['opponent_id'];
        $loserId = ($winnerId == $match['challenger_id']) ? $match['opponent_id'] : $match['challenger_id'];
        
        $bet = $match['bet_amount'];
        $reward = floor($bet * 1.95); // Thắng nhận 1.95 lần (phí sàn 5% trên GTLM thắng)

        // Cập nhật số dư
        $stmtWin = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
        $stmtWin->bind_param("di", $reward, $winnerId);
        $stmtWin->execute();
        $stmtWin->close();

        // Cập nhật trạng thái trận đấu
        $stmt = $conn->prepare("UPDATE pvp_challenges SET status = 'finished', winner_id = ? WHERE id = ?");
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
