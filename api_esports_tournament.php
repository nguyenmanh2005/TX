<?php
/**
 * 🏆 API ESPORTS TOURNAMENT BRACKET & PREDICTIONS
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/esports_bracket_helper.php';

$userId = (int)($_SESSION['Iduser'] ?? 0);
$action = trim($_POST['action'] ?? $_GET['action'] ?? 'get_tournament_bracket');

switch ($action) {
    case 'get_tournament_bracket':
        $tournament = getCurrentEsportsTournament($conn);
        $myBets = [];
        $userMoney = 0;

        if ($userId > 0) {
            $myBets = getUserEsportsPredictions($conn, $userId, $tournament['season_id']);
            $uStmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
            $uStmt->bind_param("i", $userId);
            $uStmt->execute();
            $userMoney = (float)($uStmt->get_result()->fetch_assoc()['Money'] ?? 0);
            $uStmt->close();
        }

        // Lấy danh sách Quán Quân các mùa trước (Hall of Fame)
        $recentChamps = [];
        $champQuery = $conn->query("SELECT season_id, title, champion_id, champion_name, total_bets_pool, end_time FROM esports_tournaments WHERE champion_name IS NOT NULL ORDER BY season_id DESC LIMIT 5");
        if ($champQuery) {
            $recentChamps = $champQuery->fetch_all(MYSQLI_ASSOC);
        }

        echo json_encode([
            'success' => true,
            'tournament' => $tournament,
            'my_bets' => $myBets,
            'user_money' => $userMoney,
            'user_money_formatted' => number_format($userMoney),
            'recent_champions' => $recentChamps,
            'server_time' => time()
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'predict_champion':
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để tham gia dự đoán quán quân!']);
            exit;
        }

        $playerId = trim($_POST['player_id'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);

        $res = placeEsportsPrediction($conn, $userId, $playerId, $amount);
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        break;

    case 'get_my_predictions':
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
            exit;
        }

        $stmt = $conn->prepare("SELECT p.*, t.title as tournament_title, t.status as tournament_status FROM esports_predictions p LEFT JOIN esports_tournaments t ON p.tournament_id = t.id WHERE p.user_id = ? ORDER BY p.id DESC LIMIT 20");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode([
            'success' => true,
            'history' => $history
        ], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ!']);
        break;
}
