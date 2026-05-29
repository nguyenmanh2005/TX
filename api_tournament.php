<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Chưa đăng nhập!']);
    exit();
}

$userId = (int)$_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Aliases for compatibility
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
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Giải đấu không tồn tại!']);
        exit();
    }

    if (!in_array($tour['status'], ['Pending', 'registration', 'Upcoming'])) {
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Giải đấu này đã bắt đầu hoặc đã kết thúc!']);
        exit();
    }

    // 2. Kiểm tra đã tham gia chưa
    $stmt = $conn->prepare("SELECT id FROM tournament_participants WHERE tournament_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $tournamentId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Bạn đã đăng ký giải đấu này rồi!']);
        $stmt->close();
        exit();
    }
    $stmt->close();

    // 3. Kiểm tra số lượng người chơi
    $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM tournament_participants WHERE tournament_id = ?");
    $stmtCount->bind_param("i", $tournamentId);
    $stmtCount->execute();
    $currentCount = $stmtCount->get_result()->fetch_assoc()['total'];
    $stmtCount->close();

    $maxPlayers = (int)($tour['max_players'] ?? $tour['max_participants'] ?? 100);
    if ($currentCount >= $maxPlayers) {
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Giải đấu đã đủ người chơi!']);
        exit();
    }

    // 4. Kiểm tra Gtlm
    $stmtUser = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
    $stmtUser->bind_param("i", $userId);
    $stmtUser->execute();
    $user = $stmtUser->get_result()->fetch_assoc();
    $stmtUser->close();

    $buyIn = (float)$tour['buy_in'];
    if ($user['Money'] < $buyIn) {
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Bạn không đủ GTLM để tham gia!']);
        exit();
    }

    $conn->begin_transaction();
    try {
        // Trừ Gtlm user
        $stmtDeduct = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
        $stmtDeduct->bind_param("di", $buyIn, $userId);
        $stmtDeduct->execute();
        $stmtDeduct->close();

        // Tính toán prize pool (trừ đi phí vận hành)
        $houseFee = $buyIn * ($tour['house_fee_percent'] / 100);
        $contributionToPrize = $buyIn - $houseFee;

        // Cập nhật prize pool và số người tham gia
        $stmtUpdateTour = $conn->prepare("UPDATE tournaments SET prize_pool = prize_pool + ?, current_players = current_players + 1 WHERE id = ?");
        $stmtUpdateTour->bind_param("di", $contributionToPrize, $tournamentId);
        $stmtUpdateTour->execute();
        $stmtUpdateTour->close();

        // Thêm participant
        $stmt = $conn->prepare("INSERT INTO tournament_participants (tournament_id, user_id, score, total_games, total_wins, total_bet, total_win_amount, rank, registered_at) VALUES (?, ?, 0, 0, 0, 0, 0, NOW())");
        $stmt->bind_param("ii", $tournamentId, $userId);
        $stmt->execute();
        $stmt->close();

        // Ghi log
        require_once 'game_history_helper.php';
        if (function_exists('logGameHistory')) {
            logGameHistory($conn, $userId, 'TOURNAMENT JOIN', $buyIn, 0, false);
        }

        $conn->commit();
        echo json_encode(['success' => true, 'status' => 'success', 'message' => "Đã tham gia giải đấu thành công! Chúc bạn may mắn."]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
    }

} elseif ($action === 'unregister') {
    $tournamentId = (int)($_POST['tournament_id'] ?? $_GET['tournament_id'] ?? 0);

    // 1. Lấy thông tin giải đấu
    $stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->bind_param("i", $tournamentId);
    $stmt->execute();
    $tour = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$tour) {
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Giải đấu không tồn tại!']);
        exit();
    }

    if (!in_array($tour['status'], ['Pending', 'registration', 'Upcoming'])) {
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Giải đấu này đã bắt đầu hoặc đã kết thúc, không thể hủy đăng ký!']);
        exit();
    }

    // 2. Kiểm tra đã đăng ký chưa
    $stmt = $conn->prepare("SELECT id FROM tournament_participants WHERE tournament_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $tournamentId, $userId);
    $stmt->execute();
    $participant = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$participant) {
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Bạn chưa đăng ký giải đấu này!']);
        exit();
    }

    $conn->begin_transaction();
    try {
        // Xóa participant
        $stmtDel = $conn->prepare("DELETE FROM tournament_participants WHERE tournament_id = ? AND user_id = ?");
        $stmtDel->bind_param("ii", $tournamentId, $userId);
        $stmtDel->execute();
        $stmtDel->close();

        // Hoàn tiền Gtlm user
        $buyIn = (float)$tour['buy_in'];
        $stmtRefund = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
        $stmtRefund->bind_param("di", $buyIn, $userId);
        $stmtRefund->execute();
        $stmtRefund->close();

        // Tính toán hoàn lại prize pool
        $houseFee = $buyIn * ($tour['house_fee_percent'] / 100);
        $contributionToPrize = $buyIn - $houseFee;

        // Cập nhật prize pool và số người tham gia
        $stmtUpdateTour = $conn->prepare("UPDATE tournaments SET prize_pool = GREATEST(0, prize_pool - ?), current_players = GREATEST(0, current_players - 1) WHERE id = ?");
        $stmtUpdateTour->bind_param("di", $contributionToPrize, $tournamentId);
        $stmtUpdateTour->execute();
        $stmtUpdateTour->close();

        // Ghi log hoàn tiền
        require_once 'game_history_helper.php';
        if (function_exists('logGameHistory')) {
            logGameHistory($conn, $userId, 'TOURNAMENT LEAVE', -$buyIn, 0, false);
        }

        $conn->commit();
        echo json_encode(['success' => true, 'status' => 'success', 'message' => 'Đã hủy đăng ký và hoàn trả tiền cược thành công!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
    }

} elseif ($action === 'get_active_list') {
    $sql = "SELECT t.*, 
            (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id) as registered_players,
            (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id AND user_id = ?) as is_joined
            FROM tournaments t
            ORDER BY t.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($list as &$t) {
        // Map status
        $dbStatus = strtolower($t['status'] ?? 'pending');
        if (in_array($dbStatus, ['pending', 'upcoming', 'registration'])) {
            $t['status'] = 'registration';
        } elseif (in_array($dbStatus, ['ongoing', 'started', 'active'])) {
            $t['status'] = 'active';
        } elseif (in_array($dbStatus, ['finished', 'ended'])) {
            $t['status'] = 'ended';
        } elseif ($dbStatus === 'cancelled') {
            $t['status'] = 'cancelled';
        } elseif ($dbStatus === 'paused') {
            $t['status'] = 'paused';
        }

        // Map expected JS fields
        $t['is_registered'] = ((int)($t['is_joined'] ?? 0)) > 0;
        $t['participant_count'] = (int)($t['registered_players'] ?? 0);
        $t['max_participants'] = (int)($t['max_participants'] ?? $t['max_players'] ?? 100);
    }
    unset($t);

    echo json_encode(['success' => true, 'status' => 'success', 'tournaments' => $list]);

} elseif ($action === 'get_info') {
    $tournamentId = (int)($_POST['tournament_id'] ?? $_GET['tournament_id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);

    $sql = "SELECT t.*, 
            (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id) as registered_players,
            (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id AND user_id = ?) as is_joined
            FROM tournaments t
            WHERE t.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $tournamentId);
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$t) {
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Giải đấu không tồn tại!']);
        exit();
    }

    // Map status
    $dbStatus = strtolower($t['status'] ?? 'pending');
    if (in_array($dbStatus, ['pending', 'upcoming', 'registration'])) {
        $t['status'] = 'registration';
    } elseif (in_array($dbStatus, ['ongoing', 'started', 'active'])) {
        $t['status'] = 'active';
    } elseif (in_array($dbStatus, ['finished', 'ended'])) {
        $t['status'] = 'ended';
    } elseif ($dbStatus === 'cancelled') {
        $t['status'] = 'cancelled';
    } elseif ($dbStatus === 'paused') {
        $t['status'] = 'paused';
    }

    // Map expected JS fields
    $t['is_registered'] = ((int)($t['is_joined'] ?? 0)) > 0;
    $t['participant_count'] = (int)($t['registered_players'] ?? 0);
    $t['max_participants'] = (int)($t['max_participants'] ?? $t['max_players'] ?? 100);

    echo json_encode(['success' => true, 'status' => 'success', 'tournament' => $t]);

} elseif ($action === 'get_leaderboard') {
    $tournamentId = (int)($_POST['tournament_id'] ?? $_GET['tournament_id'] ?? 0);

    $sql = "SELECT tp.*, u.Name 
            FROM tournament_participants tp
            JOIN users u ON tp.user_id = u.Iduser
            WHERE tp.tournament_id = ?
            ORDER BY tp.score DESC, tp.total_wins DESC, tp.id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $tournamentId);
    $stmt->execute();
    $leaderboard = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($leaderboard as $index => &$entry) {
        $entry['current_rank'] = $index + 1;
        $entry['total_wins'] = (int)($entry['total_wins'] ?? 0);
        $entry['total_games'] = (int)($entry['total_games'] ?? 0);
        $entry['score'] = (float)($entry['score'] ?? 0);
    }
    unset($entry);

    echo json_encode(['success' => true, 'status' => 'success', 'leaderboard' => $leaderboard]);

} elseif ($action === 'get_my_stats') {
    $tournamentId = (int)($_POST['tournament_id'] ?? $_GET['tournament_id'] ?? 0);

    $stmtTour = $conn->prepare("SELECT prize_pool, reward_structure FROM tournaments WHERE id = ?");
    $stmtTour->bind_param("i", $tournamentId);
    $stmtTour->execute();
    $tour = $stmtTour->get_result()->fetch_assoc();
    $stmtTour->close();

    $prizePool = (float)($tour['prize_pool'] ?? 0);
    $rewardStructureJson = $tour['reward_structure'] ?? '{}';

    $sql = "SELECT tp.*, 
            (SELECT COUNT(*)+1 FROM tournament_participants WHERE tournament_id = ? AND score > tp.score) as current_rank
            FROM tournament_participants tp
            WHERE tp.tournament_id = ? AND tp.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $tournamentId, $tournamentId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode([
            'success' => true,
            'status' => 'success',
            'stats' => [
                'rank' => '-',
                'score' => 0,
                'total_wins' => 0,
                'total_games' => 0,
                'potential_reward' => 0
            ]
        ]);
        exit();
    }

    $rank = (int)$row['current_rank'];
    
    function calculatePotentialReward($rank, $prizePool, $rewardStructureJson) {
        if ($rank <= 0) return 0;
        
        $rewards = json_decode($rewardStructureJson ?? '{}', true);
        if (!empty($rewards)) {
            foreach ($rewards as $key => $value) {
                if ((int)$key === $rank) {
                    return (float)$value;
                }
                if (strpos($key, '-') !== false) {
                    list($start, $end) = explode('-', $key);
                    if ($rank >= (int)$start && $rank <= (int)$end) {
                        return (float)$value;
                    }
                }
            }
        }
        
        $ratios = [1 => 0.5, 2 => 0.3, 3 => 0.2];
        if (isset($ratios[$rank])) {
            return (float)($prizePool * $ratios[$rank]);
        }
        
        return 0.0;
    }

    $potentialReward = calculatePotentialReward($rank, $prizePool, $rewardStructureJson);

    echo json_encode([
        'success' => true,
        'status' => 'success',
        'stats' => [
            'rank' => $rank,
            'score' => (float)$row['score'],
            'total_wins' => (int)($row['total_wins'] ?? 0),
            'total_games' => (int)($row['total_games'] ?? 0),
            'potential_reward' => $potentialReward
        ]
    ]);

} elseif ($action === 'claim_reward') {
    echo json_encode(['success' => true, 'status' => 'success', 'message' => 'Phần thưởng đã được trao tự động vào tài khoản của bạn!']);
} else {
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Hành động không hợp lệ!']);
}
?>
