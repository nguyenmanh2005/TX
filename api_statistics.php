<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Vui lòng đăng nhập!'
    ]);
    exit();
}

$userId = (int)$_SESSION['Iduser'];

$userStmt = $conn->prepare("SELECT Iduser, Name, Money FROM users WHERE Iduser = ?");
if (!$userStmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Không thể chuẩn bị truy vấn người dùng.'
    ]);
    exit();
}
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$userStmt->close();

if (!$user) {
    echo json_encode([
        'success' => false,
        'message' => 'Không tìm thấy người dùng.'
    ]);
    exit();
}

function tableExists($conn, $tableName) {
    $safe = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '$safe'");
    return $result && $result->num_rows > 0;
}

$gameHistoryEnabled = tableExists($conn, 'game_history');
$stats = [
    'totals' => [
        'totalGames' => 0,
        'totalWins' => 0,
        'totalLosses' => 0,
        'totalEarned' => 0,
        'totalSpent' => 0,
        'winRate' => 0
    ],
    'gameStats' => [],
    'dailyStats' => [],
    'recentGames' => [],
    'achievementsCount' => 0,
    'questsCompleted' => 0,
    'gameHistoryEnabled' => $gameHistoryEnabled
];

if ($gameHistoryEnabled) {
    // Tổng số game đã chơi
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM game_history WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['totals']['totalGames'] = (int)($result->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    
    // Thắng / thua / earned / spent
    $sql = "SELECT 
                SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN is_win = 0 THEN 1 ELSE 0 END) as losses,
                SUM(CASE WHEN is_win = 1 THEN win_amount - bet_amount ELSE 0 END) as earned,
                SUM(bet_amount) as spent
            FROM game_history
            WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    $stats['totals']['totalWins'] = (int)($row['wins'] ?? 0);
    $stats['totals']['totalLosses'] = (int)($row['losses'] ?? 0);
    $stats['totals']['totalEarned'] = (float)($row['earned'] ?? 0);
    $stats['totals']['totalSpent'] = (float)($row['spent'] ?? 0);
    $stats['totals']['winRate'] = $stats['totals']['totalGames'] > 0
        ? round(($stats['totals']['totalWins'] / $stats['totals']['totalGames']) * 100, 2)
        : 0;
    
    // Thống kê theo game
    $sql = "SELECT 
                game_name,
                COUNT(*) as plays,
                SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN is_win = 1 THEN win_amount - bet_amount ELSE 0 END) as earned,
                SUM(bet_amount) as spent
            FROM game_history
            WHERE user_id = ?
            GROUP BY game_name
            ORDER BY plays DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['plays'] = (int)$row['plays'];
        $row['wins'] = (int)$row['wins'];
        $row['earned'] = (float)$row['earned'];
        $row['spent'] = (float)$row['spent'];
        $row['win_rate'] = $row['plays'] > 0 ? round(($row['wins'] / $row['plays']) * 100, 2) : 0;
        $stats['gameStats'][] = $row;
    }
    $stmt->close();
    
    // Game gần đây (30 ngày)
    $sql = "SELECT game_name, bet_amount, win_amount, is_win, played_at
            FROM game_history
            WHERE user_id = ? AND played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY played_at DESC
            LIMIT 20";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['bet_amount'] = (float)$row['bet_amount'];
        $row['win_amount'] = (float)$row['win_amount'];
        $row['is_win'] = (bool)$row['is_win'];
        $stats['recentGames'][] = $row;
    }
    $stmt->close();
    
    // Daily stats (30 ngày gần nhất)
    $sql = "SELECT DATE(played_at) as date,
                   COUNT(*) as plays,
                   SUM(CASE WHEN is_win = 1 THEN win_amount - bet_amount ELSE 0 END) as earned,
                   SUM(bet_amount) as spent
            FROM game_history
            WHERE user_id = ? AND played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(played_at)
            ORDER BY date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['plays'] = (int)$row['plays'];
        $row['earned'] = (float)$row['earned'];
        $row['spent'] = (float)$row['spent'];
        $stats['dailyStats'][] = $row;
    }
    $stmt->close();
}

// Achievements
if (tableExists($conn, 'achievements') && tableExists($conn, 'user_achievements')) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM user_achievements WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['achievementsCount'] = (int)($result->fetch_assoc()['total'] ?? 0);
    $stmt->close();
}

// Quests
if (tableExists($conn, 'user_quests')) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM user_quests WHERE user_id = ? AND is_completed = 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['questsCompleted'] = (int)($result->fetch_assoc()['total'] ?? 0);
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'stats' => $stats,
    'user' => [
        'name' => $user['Name'],
        'money' => (float)$user['Money']
    ]
]);














