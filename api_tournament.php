<?php
/**
 * API xử lý các thao tác với Tournament
 * 
 * Actions:
 * - get_list: Lấy danh sách giải đấu
 * - get_info: Lấy thông tin giải đấu
 * - register: Đăng ký tham gia giải đấu
 * - unregister: Hủy đăng ký
 * - get_leaderboard: Lấy bảng xếp hạng
 * - get_my_stats: Lấy thống kê của user trong giải đấu
 * - log_game: Ghi lại game đã chơi trong giải đấu
 * - claim_reward: Nhận phần thưởng
 */

session_start();
require 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Kiểm tra bảng tồn tại
$checkTournaments = $conn->query("SHOW TABLES LIKE 'tournaments'");
if (!$checkTournaments || $checkTournaments->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Hệ thống Tournament chưa được kích hoạt! Vui lòng chạy file create_tournament_tables.sql trước.']);
    exit;
}

/**
 * Tính điểm số cho game
 */
function calculateScore($betAmount, $winAmount, $isWin) {
    if ($isWin) {
        // Thắng: điểm = tiền thắng * 0.1 + tiền cược * 0.05
        return ($winAmount * 0.1) + ($betAmount * 0.05);
    } else {
        // Thua: điểm = tiền cược * 0.01 (khuyến khích chơi)
        return $betAmount * 0.01;
    }
}

/**
 * Cập nhật xếp hạng cho giải đấu
 */
function updateTournamentRanking($conn, $tournamentId) {
    $sql = "UPDATE tournament_participants tp1
            JOIN (
                SELECT id, 
                       ROW_NUMBER() OVER (ORDER BY score DESC, total_wins DESC, total_win_amount DESC) as new_rank
                FROM tournament_participants
                WHERE tournament_id = ?
            ) tp2 ON tp1.id = tp2.id
            SET tp1.rank = tp2.new_rank
            WHERE tp1.tournament_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $tournamentId, $tournamentId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Lấy phần thưởng theo rank
 */
function getRewardByRank($rewardStructure, $rank) {
    $rewards = json_decode($rewardStructure, true);
    if (!$rewards) return 0;
    
    // Kiểm tra rank cụ thể
    if (isset($rewards[(string)$rank])) {
        return $rewards[(string)$rank];
    }
    
    // Kiểm tra khoảng rank (VD: "4-10")
    foreach ($rewards as $key => $value) {
        if (strpos($key, '-') !== false) {
            list($min, $max) = explode('-', $key);
            if ($rank >= (int)$min && $rank <= (int)$max) {
                return $value;
            }
        }
    }
    
    return 0;
}

// ============================================
// XỬ LÝ CÁC ACTION
// ============================================

switch ($action) {
    case 'get_list':
        // Lấy danh sách giải đấu
        $status = $_GET['status'] ?? 'all';
        $type = $_GET['type'] ?? 'all';
        
        $sql = "SELECT t.*, 
                (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id) as participant_count,
                (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id AND user_id = ?) as is_registered
                FROM tournaments t
                WHERE 1=1";
        
        $params = [$userId];
        $types = 'i';
        
        if ($status !== 'all') {
            $sql .= " AND t.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        if ($type !== 'all') {
            $sql .= " AND t.tournament_type = ?";
            $params[] = $type;
            $types .= 's';
        }
        
        $sql .= " ORDER BY t.start_time DESC LIMIT 50";
        
        $stmt = $conn->prepare($sql);
        if (count($params) > 1) {
            $stmt->bind_param($types, ...$params);
        } else {
            $stmt->bind_param("i", $userId);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tournaments = [];
        while ($row = $result->fetch_assoc()) {
            $tournaments[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'tournaments' => $tournaments]);
        break;
        
    case 'get_info':
        // Lấy thông tin giải đấu
        $tournamentId = (int)($_GET['tournament_id'] ?? 0);
        
        if (!$tournamentId) {
            echo json_encode(['success' => false, 'message' => 'Tournament ID không hợp lệ!']);
            exit;
        }
        
        $sql = "SELECT t.*, 
                (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id) as participant_count,
                (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id AND user_id = ?) as is_registered
                FROM tournaments t
                WHERE t.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $tournamentId);
        $stmt->execute();
        $tournament = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$tournament) {
            echo json_encode(['success' => false, 'message' => 'Giải đấu không tồn tại!']);
            exit;
        }
        
        echo json_encode(['success' => true, 'tournament' => $tournament]);
        break;
        
    case 'register':
        // Đăng ký tham gia giải đấu
        $tournamentId = (int)($_POST['tournament_id'] ?? 0);
        
        if (!$tournamentId) {
            echo json_encode(['success' => false, 'message' => 'Tournament ID không hợp lệ!']);
            exit;
        }
        
        // Lấy thông tin giải đấu
        $sql = "SELECT * FROM tournaments WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $tournamentId);
        $stmt->execute();
        $tournament = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$tournament) {
            echo json_encode(['success' => false, 'message' => 'Giải đấu không tồn tại!']);
            exit;
        }
        
        // Kiểm tra thời gian đăng ký
        $now = time();
        $regStart = strtotime($tournament['registration_start']);
        $regEnd = strtotime($tournament['registration_end']);
        
        if ($now < $regStart) {
            echo json_encode(['success' => false, 'message' => 'Chưa đến thời gian đăng ký!']);
            exit;
        }
        
        if ($now > $regEnd) {
            echo json_encode(['success' => false, 'message' => 'Đã hết thời gian đăng ký!']);
            exit;
        }
        
        // Kiểm tra số lượng người tham gia
        $countSql = "SELECT COUNT(*) as count FROM tournament_participants WHERE tournament_id = ?";
        $countStmt = $conn->prepare($countSql);
        $countStmt->bind_param("i", $tournamentId);
        $countStmt->execute();
        $countResult = $countStmt->get_result()->fetch_assoc();
        $countStmt->close();
        
        if ($countResult['count'] >= $tournament['max_participants']) {
            echo json_encode(['success' => false, 'message' => 'Giải đấu đã đầy!']);
            exit;
        }
        
        // Kiểm tra đã đăng ký chưa
        $checkSql = "SELECT id FROM tournament_participants WHERE tournament_id = ? AND user_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("ii", $tournamentId, $userId);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Bạn đã đăng ký giải đấu này rồi!']);
            exit;
        }
        $checkStmt->close();
        
        // Đăng ký
        $insertSql = "INSERT INTO tournament_participants (tournament_id, user_id) VALUES (?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("ii", $tournamentId, $userId);
        
        if ($insertStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Đăng ký thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi đăng ký!']);
        }
        $insertStmt->close();
        break;
        
    case 'unregister':
        // Hủy đăng ký
        $tournamentId = (int)($_POST['tournament_id'] ?? 0);
        
        if (!$tournamentId) {
            echo json_encode(['success' => false, 'message' => 'Tournament ID không hợp lệ!']);
            exit;
        }
        
        // Kiểm tra giải đấu đã bắt đầu chưa
        $sql = "SELECT start_time FROM tournaments WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $tournamentId);
        $stmt->execute();
        $tournament = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$tournament) {
            echo json_encode(['success' => false, 'message' => 'Giải đấu không tồn tại!']);
            exit;
        }
        
        if (strtotime($tournament['start_time']) <= time()) {
            echo json_encode(['success' => false, 'message' => 'Không thể hủy đăng ký sau khi giải đấu đã bắt đầu!']);
            exit;
        }
        
        // Hủy đăng ký
        $deleteSql = "DELETE FROM tournament_participants WHERE tournament_id = ? AND user_id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param("ii", $tournamentId, $userId);
        
        if ($deleteStmt->execute() && $deleteStmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Hủy đăng ký thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng ký giải đấu này!']);
        }
        $deleteStmt->close();
        break;
        
    case 'get_leaderboard':
        // Lấy bảng xếp hạng
        $tournamentId = (int)($_GET['tournament_id'] ?? 0);
        $limit = (int)($_GET['limit'] ?? 50);
        
        if (!$tournamentId) {
            echo json_encode(['success' => false, 'message' => 'Tournament ID không hợp lệ!']);
            exit;
        }
        
        // Cập nhật ranking trước
        updateTournamentRanking($conn, $tournamentId);
        
        // Lấy leaderboard
        $sql = "SELECT tp.*, u.Name, u.ImageURL, u.active_title_id,
                a.icon as title_icon, a.name as title_name
                FROM tournament_participants tp
                JOIN users u ON tp.user_id = u.Iduser
                LEFT JOIN achievements a ON u.active_title_id = a.id
                WHERE tp.tournament_id = ?
                ORDER BY tp.score DESC, tp.total_wins DESC, tp.total_win_amount DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $tournamentId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $leaderboard = [];
        $rank = 1;
        while ($row = $result->fetch_assoc()) {
            $row['current_rank'] = $rank++;
            $leaderboard[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'leaderboard' => $leaderboard]);
        break;
        
    case 'get_my_stats':
        // Lấy thống kê của user trong giải đấu
        $tournamentId = (int)($_GET['tournament_id'] ?? 0);
        
        if (!$tournamentId) {
            echo json_encode(['success' => false, 'message' => 'Tournament ID không hợp lệ!']);
            exit;
        }
        
        // Cập nhật ranking
        updateTournamentRanking($conn, $tournamentId);
        
        // Lấy thống kê
        $sql = "SELECT tp.*, 
                (SELECT COUNT(*) FROM tournament_games WHERE tournament_id = ? AND user_id = ?) as game_count,
                (SELECT COUNT(*) FROM tournament_games WHERE tournament_id = ? AND user_id = ? AND is_win = 1) as win_count
                FROM tournament_participants tp
                WHERE tp.tournament_id = ? AND tp.user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiiii", $tournamentId, $userId, $tournamentId, $userId, $tournamentId, $userId);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$stats) {
            echo json_encode(['success' => false, 'message' => 'Bạn chưa tham gia giải đấu này!']);
            exit;
        }
        
        // Lấy thông tin giải đấu để tính phần thưởng
        $tournamentSql = "SELECT reward_structure FROM tournaments WHERE id = ?";
        $tournamentStmt = $conn->prepare($tournamentSql);
        $tournamentStmt->bind_param("i", $tournamentId);
        $tournamentStmt->execute();
        $tournament = $tournamentStmt->get_result()->fetch_assoc();
        $tournamentStmt->close();
        
        if ($stats['rank']) {
            $stats['potential_reward'] = getRewardByRank($tournament['reward_structure'], $stats['rank']);
        } else {
            $stats['potential_reward'] = 0;
        }
        
        echo json_encode(['success' => true, 'stats' => $stats]);
        break;
        
    case 'log_game':
        // Ghi lại game đã chơi trong giải đấu
        $tournamentId = (int)($_POST['tournament_id'] ?? 0);
        $gameName = trim($_POST['game_name'] ?? '');
        $betAmount = (float)($_POST['bet_amount'] ?? 0);
        $winAmount = (float)($_POST['win_amount'] ?? 0);
        $isWin = (int)($_POST['is_win'] ?? 0);
        
        if (!$tournamentId || !$gameName || $betAmount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ!']);
            exit;
        }
        
        // Kiểm tra giải đấu đang active
        $sql = "SELECT * FROM tournaments WHERE id = ? AND status = 'active'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $tournamentId);
        $stmt->execute();
        $tournament = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$tournament) {
            echo json_encode(['success' => false, 'message' => 'Giải đấu không tồn tại hoặc chưa bắt đầu!']);
            exit;
        }
        
        // Kiểm tra game type
        if ($tournament['game_type'] !== 'All' && $tournament['game_type'] !== $gameName) {
            echo json_encode(['success' => false, 'message' => 'Game này không thuộc giải đấu!']);
            exit;
        }
        
        // Kiểm tra min bet
        if ($betAmount < $tournament['min_bet']) {
            echo json_encode(['success' => false, 'message' => 'Cược quá nhỏ! Cược tối thiểu: ' . number_format($tournament['min_bet'], 0, ',', '.')]);
            exit;
        }
        
        // Kiểm tra user đã đăng ký chưa
        $checkSql = "SELECT id FROM tournament_participants WHERE tournament_id = ? AND user_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("ii", $tournamentId, $userId);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng ký giải đấu này!']);
            exit;
        }
        $checkStmt->close();
        
        // Tính điểm
        $scorePoints = calculateScore($betAmount, $winAmount, $isWin);
        
        // Ghi lại game
        $conn->begin_transaction();
        try {
            // Insert vào tournament_games
            $insertSql = "INSERT INTO tournament_games (tournament_id, user_id, game_name, bet_amount, win_amount, is_win, score_points) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("iissddi", $tournamentId, $userId, $gameName, $betAmount, $winAmount, $isWin, $scorePoints);
            $insertStmt->execute();
            $insertStmt->close();
            
            // Cập nhật thống kê participant
            $updateSql = "UPDATE tournament_participants 
                         SET total_games = total_games + 1,
                             total_wins = total_wins + ?,
                             total_bet = total_bet + ?,
                             total_win_amount = total_win_amount + ?,
                             score = score + ?
                         WHERE tournament_id = ? AND user_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("iddddii", $isWin, $betAmount, $winAmount, $scorePoints, $tournamentId, $userId);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Cập nhật ranking
            updateTournamentRanking($conn, $tournamentId);
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Ghi lại game thành công!', 'score_points' => $scorePoints]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;
        
    case 'claim_reward':
        // Nhận phần thưởng
        $tournamentId = (int)($_POST['tournament_id'] ?? 0);
        
        if (!$tournamentId) {
            echo json_encode(['success' => false, 'message' => 'Tournament ID không hợp lệ!']);
            exit;
        }
        
        // Kiểm tra giải đấu đã kết thúc chưa
        $sql = "SELECT * FROM tournaments WHERE id = ? AND status = 'ended'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $tournamentId);
        $stmt->execute();
        $tournament = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$tournament) {
            echo json_encode(['success' => false, 'message' => 'Giải đấu chưa kết thúc!']);
            exit;
        }
        
        // Cập nhật ranking
        updateTournamentRanking($conn, $tournamentId);
        
        // Lấy thống kê user
        $statsSql = "SELECT * FROM tournament_participants WHERE tournament_id = ? AND user_id = ?";
        $statsStmt = $conn->prepare($statsSql);
        $statsStmt->bind_param("ii", $tournamentId, $userId);
        $statsStmt->execute();
        $stats = $statsStmt->get_result()->fetch_assoc();
        $statsStmt->close();
        
        if (!$stats) {
            echo json_encode(['success' => false, 'message' => 'Bạn chưa tham gia giải đấu này!']);
            exit;
        }
        
        if (!$stats['rank']) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có xếp hạng trong giải đấu này!']);
            exit;
        }
        
        // Tính phần thưởng
        $reward = getRewardByRank($tournament['reward_structure'], $stats['rank']);
        
        if ($reward <= 0) {
            echo json_encode(['success' => false, 'message' => 'Bạn không đủ điều kiện nhận phần thưởng!']);
            exit;
        }
        
        if ($stats['reward_received'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Bạn đã nhận phần thưởng rồi!']);
            exit;
        }
        
        // Cấp phần thưởng
        $conn->begin_transaction();
        try {
            // Cập nhật tiền user
            $updateMoneySql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
            $updateMoneyStmt = $conn->prepare($updateMoneySql);
            $updateMoneyStmt->bind_param("di", $reward, $userId);
            $updateMoneyStmt->execute();
            $updateMoneyStmt->close();
            
            // Đánh dấu đã nhận phần thưởng
            $updateRewardSql = "UPDATE tournament_participants SET reward_received = ? WHERE tournament_id = ? AND user_id = ?";
            $updateRewardStmt = $conn->prepare($updateRewardSql);
            $updateRewardStmt->bind_param("dii", $reward, $tournamentId, $userId);
            $updateRewardStmt->execute();
            $updateRewardStmt->close();
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Nhận phần thưởng thành công!', 'reward' => $reward]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!']);
        break;
}

$conn->close();

