<?php
/**
 * API Game Statistics
 * Trả về thống kê games cho user
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập']);
    exit();
}

require 'db_connect.php';

$userId = $_SESSION['Iduser'];
$action = $_GET['action'] ?? 'get_stats';

if ($action === 'get_stats') {
    // Kiểm tra bảng game_history có tồn tại không
    $checkTable = $conn->query("SHOW TABLES LIKE 'game_history'");
    if (!$checkTable || $checkTable->num_rows === 0) {
        echo json_encode([
            'status' => 'success',
            'stats' => [
                'totalGames' => 0,
                'totalWins' => 0,
                'totalLosses' => 0,
                'totalWagered' => 0,
                'totalWon' => 0,
                'favoriteGame' => null,
                'winRate' => 0,
                'biggestWin' => 0
            ]
        ]);
        exit();
    }
    
    // Tổng số games
    $sqlTotal = "SELECT COUNT(*) as total FROM game_history WHERE user_id = ?";
    $stmtTotal = $conn->prepare($sqlTotal);
    $stmtTotal->bind_param("i", $userId);
    $stmtTotal->execute();
    $resultTotal = $stmtTotal->get_result();
    $totalGames = $resultTotal->fetch_assoc()['total'] ?? 0;
    $stmtTotal->close();
    
    // Tổng số thắng/thua
    $sqlWinLoss = "SELECT 
        SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) as wins,
        SUM(CASE WHEN is_win = 0 THEN 1 ELSE 0 END) as losses
        FROM game_history WHERE user_id = ?";
    $stmtWinLoss = $conn->prepare($sqlWinLoss);
    $stmtWinLoss->bind_param("i", $userId);
    $stmtWinLoss->execute();
    $resultWinLoss = $stmtWinLoss->get_result();
    $winLoss = $resultWinLoss->fetch_assoc();
    $totalWins = $winLoss['wins'] ?? 0;
    $totalLosses = $winLoss['losses'] ?? 0;
    $stmtWinLoss->close();
    
    // Tổng tiền cược và thắng
    $sqlMoney = "SELECT 
        SUM(bet_amount) as total_wagered,
        SUM(win_amount) as total_won
        FROM game_history WHERE user_id = ?";
    $stmtMoney = $conn->prepare($sqlMoney);
    $stmtMoney->bind_param("i", $userId);
    $stmtMoney->execute();
    $resultMoney = $stmtMoney->get_result();
    $money = $resultMoney->fetch_assoc();
    $totalWagered = $money['total_wagered'] ?? 0;
    $totalWon = $money['total_won'] ?? 0;
    $stmtMoney->close();
    
    // Game yêu thích (chơi nhiều nhất)
    $sqlFavorite = "SELECT game_name, COUNT(*) as play_count 
        FROM game_history 
        WHERE user_id = ? 
        GROUP BY game_name 
        ORDER BY play_count DESC 
        LIMIT 1";
    $stmtFavorite = $conn->prepare($sqlFavorite);
    $stmtFavorite->bind_param("i", $userId);
    $stmtFavorite->execute();
    $resultFavorite = $stmtFavorite->get_result();
    $favorite = $resultFavorite->fetch_assoc();
    $favoriteGame = $favorite ? $favorite['game_name'] : null;
    $stmtFavorite->close();
    
    // Thắng lớn nhất
    $sqlBiggest = "SELECT MAX(win_amount) as biggest_win 
        FROM game_history 
        WHERE user_id = ? AND is_win = 1";
    $stmtBiggest = $conn->prepare($sqlBiggest);
    $stmtBiggest->bind_param("i", $userId);
    $stmtBiggest->execute();
    $resultBiggest = $stmtBiggest->get_result();
    $biggest = $resultBiggest->fetch_assoc();
    $biggestWin = $biggest['biggest_win'] ?? 0;
    $stmtBiggest->close();
    
    // Win rate
    $winRate = $totalGames > 0 ? ($totalWins / $totalGames) * 100 : 0;
    
    echo json_encode([
        'status' => 'success',
        'stats' => [
            'totalGames' => (int)$totalGames,
            'totalWins' => (int)$totalWins,
            'totalLosses' => (int)$totalLosses,
            'totalWagered' => (float)$totalWagered,
            'totalWon' => (float)$totalWon,
            'favoriteGame' => $favoriteGame,
            'winRate' => round($winRate, 2),
            'biggestWin' => (float)$biggestWin
        ]
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ']);
}

$conn->close();
?>

