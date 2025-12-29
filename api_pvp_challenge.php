<?php
/**
 * API xử lý PvP Challenge System
 * Hệ thống đấu 1-1 giữa 2 người chơi
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
$checkTable = $conn->query("SHOW TABLES LIKE 'pvp_challenges'");
if (!$checkTable || $checkTable->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Hệ thống PvP chưa được kích hoạt! Vui lòng chạy file create_pvp_challenge_tables.sql']);
    exit;
}

switch ($action) {
    case 'create_challenge':
        // Tạo challenge mới
        $opponentId = (int)($_POST['opponent_id'] ?? 0);
        $gameType = $_POST['game_type'] ?? 'coinflip';
        $betAmount = (float)($_POST['bet_amount'] ?? 0);
        
        if ($opponentId <= 0 || $opponentId == $userId) {
            echo json_encode(['success' => false, 'message' => 'Đối thủ không hợp lệ!']);
            exit;
        }
        
        if ($betAmount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Số tiền cược phải lớn hơn 0!']);
            exit;
        }
        
        // Kiểm tra đối thủ có tồn tại không
        $checkOpponent = $conn->prepare("SELECT Iduser, Name, Money FROM users WHERE Iduser = ?");
        $checkOpponent->bind_param("i", $opponentId);
        $checkOpponent->execute();
        $opponent = $checkOpponent->get_result()->fetch_assoc();
        $checkOpponent->close();
        
        if (!$opponent) {
            echo json_encode(['success' => false, 'message' => 'Đối thủ không tồn tại!']);
            exit;
        }
        
        // Kiểm tra số dư
        $checkBalance = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
        $checkBalance->bind_param("i", $userId);
        $checkBalance->execute();
        $userBalance = $checkBalance->get_result()->fetch_assoc();
        $checkBalance->close();
        
        if ($userBalance['Money'] < $betAmount) {
            echo json_encode(['success' => false, 'message' => 'Bạn không đủ tiền để tạo challenge!']);
            exit;
        }
        
        if ($opponent['Money'] < $betAmount) {
            echo json_encode(['success' => false, 'message' => 'Đối thủ không đủ tiền để chấp nhận challenge!']);
            exit;
        }
        
        // Trừ tiền của challenger ngay
        $conn->begin_transaction();
        try {
            $updateMoney = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
            $updateMoney->bind_param("di", $betAmount, $userId);
            $updateMoney->execute();
            $updateMoney->close();
            
            // Tạo challenge
            $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
            $insertSql = "INSERT INTO pvp_challenges 
                         (challenger_id, opponent_id, game_type, bet_amount, expires_at) 
                         VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertSql);
            $stmt->bind_param("iisds", $userId, $opponentId, $gameType, $betAmount, $expiresAt);
            $stmt->execute();
            $challengeId = $conn->insert_id;
            $stmt->close();
            
            $conn->commit();
            
            // Tạo notification cho đối thủ
            require_once 'notification_helper.php';
            createNotification($conn, $opponentId, 'friend_request', 
                'Thách Đấu Mới!', 
                htmlspecialchars($userBalance['Name'] ?? 'Ai đó') . " đã thách đấu bạn " . number_format($betAmount, 0, ',', '.') . " VNĐ!",
                '⚔️', 'pvp_challenge.php?id=' . $challengeId, $challengeId, true);
            
            echo json_encode([
                'success' => true,
                'message' => 'Đã tạo challenge thành công!',
                'challenge_id' => $challengeId
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;
        
    case 'accept_challenge':
        // Chấp nhận challenge
        $challengeId = (int)($_POST['challenge_id'] ?? 0);
        
        if ($challengeId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Challenge ID không hợp lệ!']);
            exit;
        }
        
        // Lấy thông tin challenge
        $getChallenge = $conn->prepare("SELECT * FROM pvp_challenges WHERE id = ? AND opponent_id = ? AND status = 'pending'");
        $getChallenge->bind_param("ii", $challengeId, $userId);
        $getChallenge->execute();
        $challenge = $getChallenge->get_result()->fetch_assoc();
        $getChallenge->close();
        
        if (!$challenge) {
            echo json_encode(['success' => false, 'message' => 'Challenge không tồn tại hoặc đã hết hạn!']);
            exit;
        }
        
        // Kiểm tra số dư
        $checkBalance = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
        $checkBalance->bind_param("i", $userId);
        $checkBalance->execute();
        $userBalance = $checkBalance->get_result()->fetch_assoc();
        $checkBalance->close();
        
        if ($userBalance['Money'] < $challenge['bet_amount']) {
            echo json_encode(['success' => false, 'message' => 'Bạn không đủ tiền để chấp nhận challenge!']);
            exit;
        }
        
        // Trừ tiền và chấp nhận challenge
        $conn->begin_transaction();
        try {
            $updateMoney = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
            $updateMoney->bind_param("di", $challenge['bet_amount'], $userId);
            $updateMoney->execute();
            $updateMoney->close();
            
            $acceptSql = "UPDATE pvp_challenges SET status = 'accepted', accepted_at = NOW() WHERE id = ?";
            $acceptStmt = $conn->prepare($acceptSql);
            $acceptStmt->bind_param("i", $challengeId);
            $acceptStmt->execute();
            $acceptStmt->close();
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Đã chấp nhận challenge!',
                'challenge' => $challenge
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;
        
    case 'submit_choice':
        // Nộp lựa chọn (heads/tails, dice number, etc.)
        $challengeId = (int)($_POST['challenge_id'] ?? 0);
        $choice = $_POST['choice'] ?? '';
        
        if ($challengeId <= 0 || empty($choice)) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ!']);
            exit;
        }
        
        // Lấy challenge
        $getChallenge = $conn->prepare("SELECT * FROM pvp_challenges WHERE id = ? AND (challenger_id = ? OR opponent_id = ?) AND status = 'accepted'");
        $getChallenge->bind_param("iii", $challengeId, $userId, $userId);
        $getChallenge->execute();
        $challenge = $getChallenge->get_result()->fetch_assoc();
        $getChallenge->close();
        
        if (!$challenge) {
            echo json_encode(['success' => false, 'message' => 'Challenge không tồn tại!']);
            exit;
        }
        
        // Xác định là challenger hay opponent
        $isChallenger = ($challenge['challenger_id'] == $userId);
        
        // Kiểm tra đã nộp choice chưa
        if ($isChallenger && $challenge['challenger_choice']) {
            echo json_encode(['success' => false, 'message' => 'Bạn đã nộp lựa chọn rồi!']);
            exit;
        }
        
        if (!$isChallenger && $challenge['opponent_choice']) {
            echo json_encode(['success' => false, 'message' => 'Bạn đã nộp lựa chọn rồi!']);
            exit;
        }
        
        // Cập nhật choice
        if ($isChallenger) {
            $updateSql = "UPDATE pvp_challenges SET challenger_choice = ? WHERE id = ?";
        } else {
            $updateSql = "UPDATE pvp_challenges SET opponent_choice = ? WHERE id = ?";
        }
        
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("si", $choice, $challengeId);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Kiểm tra cả 2 đã nộp chưa để tính kết quả
        $checkComplete = $conn->prepare("SELECT challenger_choice, opponent_choice FROM pvp_challenges WHERE id = ?");
        $checkComplete->bind_param("i", $challengeId);
        $checkComplete->execute();
        $current = $checkComplete->get_result()->fetch_assoc();
        $checkComplete->close();
        
        $result = null;
        $winnerId = null;
        
        if ($current['challenger_choice'] && $current['opponent_choice']) {
            // Cả 2 đã nộp, tính kết quả
            $result = calculateGameResult($challenge['game_type'], $current['challenger_choice'], $current['opponent_choice']);
            
            if ($result == 'challenger_win') {
                $winnerId = $challenge['challenger_id'];
            } elseif ($result == 'opponent_win') {
                $winnerId = $challenge['opponent_id'];
            }
            
            // Cập nhật kết quả và trả tiền
            $conn->begin_transaction();
            try {
                $totalBet = $challenge['bet_amount'] * 2;
                
                if ($winnerId) {
                    // Trả tiền cho người thắng
                    $updateWinner = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
                    $updateWinner->bind_param("di", $totalBet, $winnerId);
                    $updateWinner->execute();
                    $updateWinner->close();
                    
                    // Cập nhật stats
                    updatePvpStats($conn, $winnerId, true, $totalBet);
                    updatePvpStats($conn, ($winnerId == $challenge['challenger_id'] ? $challenge['opponent_id'] : $challenge['challenger_id']), false, $challenge['bet_amount']);
                } else {
                    // Hòa, trả lại tiền cho cả 2
                    $refund = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser IN (?, ?)");
                    $refund->bind_param("dii", $challenge['bet_amount'], $challenge['challenger_id'], $challenge['opponent_id']);
                    $refund->execute();
                    $refund->close();
                    
                    updatePvpStats($conn, $challenge['challenger_id'], null, 0);
                    updatePvpStats($conn, $challenge['opponent_id'], null, 0);
                }
                
                // Cập nhật challenge
                $updateResult = $conn->prepare("UPDATE pvp_challenges SET status = 'completed', result = ?, winner_id = ?, completed_at = NOW() WHERE id = ?");
                $updateResult->bind_param("sii", $result, $winnerId, $challengeId);
                $updateResult->execute();
                $updateResult->close();
                
                // Lưu vào lịch sử
                $insertHistory = $conn->prepare("INSERT INTO pvp_match_history 
                    (challenge_id, challenger_id, opponent_id, game_type, bet_amount, challenger_choice, opponent_choice, result, winner_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insertHistory->bind_param("iiisddssi", $challengeId, $challenge['challenger_id'], $challenge['opponent_id'], 
                    $challenge['game_type'], $challenge['bet_amount'], $current['challenger_choice'], 
                    $current['opponent_choice'], $result, $winnerId);
                $insertHistory->execute();
                $insertHistory->close();
                
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Lỗi tính kết quả: ' . $e->getMessage()]);
                exit;
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Đã nộp lựa chọn!',
            'both_submitted' => ($current['challenger_choice'] && $current['opponent_choice']),
            'result' => $result,
            'winner_id' => $winnerId
        ]);
        break;
        
    case 'get_challenge':
        // Lấy thông tin challenge
        $challengeId = (int)($_GET['challenge_id'] ?? 0);
        
        $getChallenge = $conn->prepare("SELECT c.*, 
            u1.Name as challenger_name, u1.ImageURL as challenger_avatar,
            u2.Name as opponent_name, u2.ImageURL as opponent_avatar
            FROM pvp_challenges c
            LEFT JOIN users u1 ON c.challenger_id = u1.Iduser
            LEFT JOIN users u2 ON c.opponent_id = u2.Iduser
            WHERE c.id = ? AND (c.challenger_id = ? OR c.opponent_id = ?)");
        $getChallenge->bind_param("iii", $challengeId, $userId, $userId);
        $getChallenge->execute();
        $challenge = $getChallenge->get_result()->fetch_assoc();
        $getChallenge->close();
        
        if (!$challenge) {
            echo json_encode(['success' => false, 'message' => 'Challenge không tồn tại!']);
            exit;
        }
        
        // Ẩn choice của đối thủ nếu chưa nộp
        $isChallenger = ($challenge['challenger_id'] == $userId);
        if (!$isChallenger && !$challenge['challenger_choice']) {
            $challenge['challenger_choice'] = null;
        }
        if ($isChallenger && !$challenge['opponent_choice']) {
            $challenge['opponent_choice'] = null;
        }
        
        echo json_encode(['success' => true, 'challenge' => $challenge]);
        break;
        
    case 'get_my_challenges':
        // Lấy danh sách challenges của user
        $status = $_GET['status'] ?? 'all';
        
        $sql = "SELECT c.*, 
            u1.Name as challenger_name, u1.ImageURL as challenger_avatar,
            u2.Name as opponent_name, u2.ImageURL as opponent_avatar
            FROM pvp_challenges c
            LEFT JOIN users u1 ON c.challenger_id = u1.Iduser
            LEFT JOIN users u2 ON c.opponent_id = u2.Iduser
            WHERE (c.challenger_id = ? OR c.opponent_id = ?)";
        
        if ($status != 'all') {
            $sql .= " AND c.status = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $userId, $userId, $status);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $userId, $userId);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $challenges = [];
        while ($row = $result->fetch_assoc()) {
            $challenges[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'challenges' => $challenges]);
        break;
        
    case 'cancel_challenge':
        // Hủy challenge (chỉ challenger mới được hủy)
        $challengeId = (int)($_POST['challenge_id'] ?? 0);
        
        $getChallenge = $conn->prepare("SELECT * FROM pvp_challenges WHERE id = ? AND challenger_id = ? AND status = 'pending'");
        $getChallenge->bind_param("ii", $challengeId, $userId);
        $getChallenge->execute();
        $challenge = $getChallenge->get_result()->fetch_assoc();
        $getChallenge->close();
        
        if (!$challenge) {
            echo json_encode(['success' => false, 'message' => 'Không thể hủy challenge này!']);
            exit;
        }
        
        $conn->begin_transaction();
        try {
            // Trả lại tiền
            $refund = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            $refund->bind_param("di", $challenge['bet_amount'], $userId);
            $refund->execute();
            $refund->close();
            
            // Hủy challenge
            $cancel = $conn->prepare("UPDATE pvp_challenges SET status = 'cancelled' WHERE id = ?");
            $cancel->bind_param("i", $challengeId);
            $cancel->execute();
            $cancel->close();
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Đã hủy challenge!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!']);
        break;
}

// Hàm tính kết quả game
function calculateGameResult($gameType, $challengerChoice, $opponentChoice) {
    switch ($gameType) {
        case 'coinflip':
            // Coin flip: nếu cùng lựa chọn thì random, khác nhau thì ai đúng kết quả random thắng
            $randomResult = rand(0, 1) ? 'heads' : 'tails';
            if ($challengerChoice == $opponentChoice) {
                // Cùng lựa chọn, random thắng
                return ($challengerChoice == $randomResult) ? 'challenger_win' : 'opponent_win';
            } else {
                // Khác lựa chọn, ai đúng random thắng
                if ($challengerChoice == $randomResult) return 'challenger_win';
                if ($opponentChoice == $randomResult) return 'opponent_win';
            }
            break;
            
        case 'dice':
            // Dice: ai cao hơn thắng
            $challengerNum = (int)$challengerChoice;
            $opponentNum = (int)$opponentChoice;
            if ($challengerNum > $opponentNum) return 'challenger_win';
            if ($opponentNum > $challengerNum) return 'opponent_win';
            return 'draw';
            
        case 'rps':
            // Rock Paper Scissors
            $rps = ['rock' => 0, 'paper' => 1, 'scissors' => 2];
            $c = $rps[$challengerChoice] ?? 0;
            $o = $rps[$opponentChoice] ?? 0;
            if ($c == $o) return 'draw';
            if (($c + 1) % 3 == $o) return 'opponent_win';
            return 'challenger_win';
            
        case 'number':
            // Đoán số: ai gần số random hơn thắng
            $target = rand(1, 100);
            $cDiff = abs((int)$challengerChoice - $target);
            $oDiff = abs((int)$opponentChoice - $target);
            if ($cDiff < $oDiff) return 'challenger_win';
            if ($oDiff < $cDiff) return 'opponent_win';
            return 'draw';
    }
    return 'draw';
}

// Hàm cập nhật stats
function updatePvpStats($conn, $userId, $isWin, $amount) {
    $checkStats = $conn->prepare("SELECT * FROM pvp_stats WHERE user_id = ?");
    $checkStats->bind_param("i", $userId);
    $checkStats->execute();
    $stats = $checkStats->get_result()->fetch_assoc();
    $checkStats->close();
    
    if (!$stats) {
        // Tạo mới
        $insert = $conn->prepare("INSERT INTO pvp_stats (user_id, total_matches, wins, losses, draws, total_winnings, total_losses, win_streak, best_win_streak) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?)");
        $wins = $isWin === true ? 1 : 0;
        $losses = $isWin === false ? 1 : 0;
        $draws = $isWin === null ? 1 : 0;
        $winnings = $isWin === true ? $amount : 0;
        $lossesAmount = $isWin === false ? $amount : 0;
        $streak = $isWin === true ? 1 : 0;
        $insert->bind_param("iiiiiddii", $userId, $wins, $losses, $draws, $winnings, $lossesAmount, $streak, $streak);
        $insert->execute();
        $insert->close();
    } else {
        // Cập nhật
        $totalMatches = $stats['total_matches'] + 1;
        $wins = $stats['wins'] + ($isWin === true ? 1 : 0);
        $losses = $stats['losses'] + ($isWin === false ? 1 : 0);
        $draws = $stats['draws'] + ($isWin === null ? 1 : 0);
        $totalWinnings = $stats['total_winnings'] + ($isWin === true ? $amount : 0);
        $totalLosses = $stats['total_losses'] + ($isWin === false ? $amount : 0);
        
        // Tính win streak
        $winStreak = $stats['win_streak'];
        if ($isWin === true) {
            $winStreak++;
        } else {
            $winStreak = 0;
        }
        $bestStreak = max($stats['best_win_streak'], $winStreak);
        
        $update = $conn->prepare("UPDATE pvp_stats SET 
            total_matches = ?, wins = ?, losses = ?, draws = ?, 
            total_winnings = ?, total_losses = ?, win_streak = ?, best_win_streak = ? 
            WHERE user_id = ?");
        $update->bind_param("iiiiiddiii", $totalMatches, $wins, $losses, $draws, $totalWinnings, $totalLosses, $winStreak, $bestStreak, $userId);
        $update->execute();
        $update->close();
    }
}

$conn->close();
?>

