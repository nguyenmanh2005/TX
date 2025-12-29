<?php
/**
 * API xử lý Leaderboard System
 * 
 * Actions:
 * - get_overall: Lấy bảng xếp hạng tổng thể (theo tiền)
 * - get_game: Lấy bảng xếp hạng theo game
 * - get_weekly: Lấy bảng xếp hạng tuần này
 * - get_monthly: Lấy bảng xếp hạng tháng này
 * - get_user_rank: Lấy vị trí xếp hạng của user
 * - get_statistics: Lấy thống kê của user
 * - update_cache: Cập nhật cache (admin only)
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

// Kiểm tra bảng users tồn tại (quan trọng nhất)
$checkUsersTable = $conn->query("SHOW TABLES LIKE 'users'");
if (!$checkUsersTable || $checkUsersTable->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Bảng users không tồn tại! Vui lòng chạy file RESTORE_USERS_TABLE.sql hoặc ALL_DATABASE_TABLES.sql trước.']);
    exit;
}

// Các bảng khác có thể chưa tồn tại, sẽ xử lý trong từng function

/**
 * Lấy bảng xếp hạng tổng thể (theo tiền)
 */
function getOverallLeaderboard($conn, $limit = 100) {
    $sql = "SELECT u.Iduser, u.Name, u.Money, u.ImageURL, u.active_title_id,
            a.icon as title_icon, a.name as title_name,
            us.total_games_played, us.total_games_won, us.win_rate,
            (SELECT COUNT(*) + 1 FROM users u2 WHERE u2.Money > u.Money) as rank
            FROM users u
            LEFT JOIN achievements a ON u.active_title_id = a.id
            LEFT JOIN user_statistics us ON u.Iduser = us.user_id
            ORDER BY u.Money DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $leaderboard = [];
    while ($row = $result->fetch_assoc()) {
        $leaderboard[] = $row;
    }
    $stmt->close();
    
    return $leaderboard;
}

/**
 * Lấy bảng xếp hạng theo game
 */
function getGameLeaderboard($conn, $gameName, $limit = 100) {
    // Kiểm tra bảng game_history tồn tại
    $checkTable = $conn->query("SHOW TABLES LIKE 'game_history'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        return [];
    }
    
    try {
        // Lấy từ game_history
        $sql = "SELECT u.Iduser, u.Name, u.Money, u.ImageURL, u.active_title_id,
                a.icon as title_icon, a.name as title_name,
                COUNT(gh.id) as games_played,
                SUM(CASE WHEN gh.is_win = 1 THEN 1 ELSE 0 END) as games_won,
                COALESCE(SUM(gh.win_amount), 0) as total_earned
                FROM users u
                INNER JOIN game_history gh ON u.Iduser = gh.user_id AND gh.game_name = ?
                LEFT JOIN achievements a ON u.active_title_id = a.id
                GROUP BY u.Iduser, u.Name, u.Money, u.ImageURL, u.active_title_id, a.icon, a.name
                ORDER BY total_earned DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param("si", $gameName, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $leaderboard = [];
        $rank = 1;
        while ($row = $result->fetch_assoc()) {
            $row['rank'] = $rank++;
            $leaderboard[] = $row;
        }
        $stmt->close();
        
        return $leaderboard;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Lấy bảng xếp hạng tuần này
 */
function getWeeklyLeaderboard($conn, $limit = 100) {
    // Kiểm tra bảng game_history tồn tại
    $checkTable = $conn->query("SHOW TABLES LIKE 'game_history'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        return [];
    }
    
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $weekEnd = date('Y-m-d', strtotime('sunday this week'));
    
    try {
        // Sử dụng subquery để tính rank đúng cách
        $sql = "SELECT u.Iduser, u.Name, u.Money, u.ImageURL, u.active_title_id,
                a.icon as title_icon, a.name as title_name,
                COALESCE(SUM(gh.win_amount), 0) as weekly_earned,
                COUNT(gh.id) as games_played
                FROM users u
                LEFT JOIN game_history gh ON u.Iduser = gh.user_id AND DATE(gh.played_at) BETWEEN ? AND ?
                LEFT JOIN achievements a ON u.active_title_id = a.id
                GROUP BY u.Iduser, u.Name, u.Money, u.ImageURL, u.active_title_id, a.icon, a.name
                HAVING weekly_earned > 0
                ORDER BY weekly_earned DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param("ssi", $weekStart, $weekEnd, $limit);
        if (!$stmt->execute()) {
            throw new Exception("Lỗi execute: " . $stmt->error);
        }
        $result = $stmt->get_result();
        if (!$result) {
            throw new Exception("Lỗi get_result: " . $conn->error);
        }
        
        $leaderboard = [];
        $rank = 1;
        while ($row = $result->fetch_assoc()) {
            $row['rank'] = $rank++;
            $leaderboard[] = $row;
        }
        $stmt->close();
        
        return $leaderboard;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Lấy bảng xếp hạng tháng này
 */
function getMonthlyLeaderboard($conn, $limit = 100) {
    // Kiểm tra bảng game_history tồn tại
    $checkTable = $conn->query("SHOW TABLES LIKE 'game_history'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        return [];
    }
    
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    
    try {
        // Sử dụng subquery để tính rank đúng cách
        $sql = "SELECT u.Iduser, u.Name, u.Money, u.ImageURL, u.active_title_id,
                a.icon as title_icon, a.name as title_name,
                COALESCE(SUM(gh.win_amount), 0) as monthly_earned,
                COUNT(gh.id) as games_played
                FROM users u
                LEFT JOIN game_history gh ON u.Iduser = gh.user_id AND DATE(gh.played_at) BETWEEN ? AND ?
                LEFT JOIN achievements a ON u.active_title_id = a.id
                GROUP BY u.Iduser, u.Name, u.Money, u.ImageURL, u.active_title_id, a.icon, a.name
                HAVING monthly_earned > 0
                ORDER BY monthly_earned DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param("ssi", $monthStart, $monthEnd, $limit);
        if (!$stmt->execute()) {
            throw new Exception("Lỗi execute: " . $stmt->error);
        }
        $result = $stmt->get_result();
        if (!$result) {
            throw new Exception("Lỗi get_result: " . $conn->error);
        }
        
        $leaderboard = [];
        $rank = 1;
        while ($row = $result->fetch_assoc()) {
            $row['rank'] = $rank++;
            $leaderboard[] = $row;
        }
        $stmt->close();
        
        return $leaderboard;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Bảng xếp hạng streak (dựa trên user_streaks)
 */
function getStreakLeaderboard($conn, $limit = 100) {
    $checkTable = $conn->query("SHOW TABLES LIKE 'user_streaks'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        return [];
    }

    $sql = "SELECT u.Iduser, u.Name, u.Money, u.ImageURL, u.active_title_id,
            a.icon as title_icon, a.name as title_name,
            us.current_streak, us.longest_streak, us.total_days_played
            FROM user_streaks us
            INNER JOIN users u ON u.Iduser = us.user_id
            LEFT JOIN achievements a ON u.active_title_id = a.id
            ORDER BY us.longest_streak DESC, us.current_streak DESC, u.Money DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $leaderboard = [];
    $rank = 1;
    while ($row = $result->fetch_assoc()) {
        $row['rank'] = $rank++;
        $leaderboard[] = $row;
    }
    $stmt->close();

    return $leaderboard;
}

/**
 * Bảng xếp hạng theo tỷ lệ thắng (dựa trên user_statistics)
 */
function getWinrateLeaderboard($conn, $limit = 100, $minGames = 10) {
    $checkTable = $conn->query("SHOW TABLES LIKE 'user_statistics'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        return [];
    }

    $sql = "SELECT u.Iduser, u.Name, u.Money, u.ImageURL, u.active_title_id,
            a.icon as title_icon, a.name as title_name,
            us.total_games_played, us.total_games_won,
            CASE WHEN us.total_games_played > 0 THEN (us.total_games_won / us.total_games_played) * 100 ELSE 0 END AS win_rate,
            us.total_money_earned
            FROM user_statistics us
            INNER JOIN users u ON u.Iduser = us.user_id
            LEFT JOIN achievements a ON u.active_title_id = a.id
            WHERE us.total_games_played >= ?
            ORDER BY win_rate DESC, us.total_games_played DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param("ii", $minGames, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $leaderboard = [];
    $rank = 1;
    while ($row = $result->fetch_assoc()) {
        $row['rank'] = $rank++;
        $leaderboard[] = $row;
    }
    $stmt->close();

    return $leaderboard;
}

switch ($action) {
    case 'get_overall':
        $limit = (int)($_GET['limit'] ?? 100);
        $leaderboard = getOverallLeaderboard($conn, $limit);
        echo json_encode(['success' => true, 'leaderboard' => $leaderboard, 'type' => 'overall']);
        break;
        
    case 'get_game':
        $gameName = $_GET['game_name'] ?? '';
        $limit = (int)($_GET['limit'] ?? 100);
        
        if (empty($gameName)) {
            echo json_encode(['success' => false, 'message' => 'Tên game không được để trống!']);
            exit;
        }
        
        $leaderboard = getGameLeaderboard($conn, $gameName, $limit);
        echo json_encode(['success' => true, 'leaderboard' => $leaderboard, 'type' => 'game', 'game_name' => $gameName]);
        break;
        
    case 'get_weekly':
        $limit = (int)($_GET['limit'] ?? 100);
        try {
            $leaderboard = getWeeklyLeaderboard($conn, $limit);
            echo json_encode(['success' => true, 'leaderboard' => $leaderboard, 'type' => 'weekly']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage(), 'leaderboard' => []]);
        }
        break;
        
    case 'get_monthly':
        $limit = (int)($_GET['limit'] ?? 100);
        try {
            $leaderboard = getMonthlyLeaderboard($conn, $limit);
            echo json_encode(['success' => true, 'leaderboard' => $leaderboard, 'type' => 'monthly']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage(), 'leaderboard' => []]);
        }
        break;

    case 'get_streak':
        $limit = (int)($_GET['limit'] ?? 100);
        $leaderboard = getStreakLeaderboard($conn, $limit);
        echo json_encode(['success' => true, 'leaderboard' => $leaderboard, 'type' => 'streak']);
        break;

    case 'get_winrate':
        $limit = (int)($_GET['limit'] ?? 100);
        $minGames = (int)($_GET['min_games'] ?? 10);
        $leaderboard = getWinrateLeaderboard($conn, $limit, $minGames);
        echo json_encode(['success' => true, 'leaderboard' => $leaderboard, 'type' => 'winrate']);
        break;
        
    case 'get_user_rank':
        $type = $_GET['type'] ?? 'overall';
        $gameName = $_GET['game_name'] ?? null;
        
        $rank = 0;
        $total = 0;
        
        if ($type === 'overall') {
            // Lấy rank tổng thể
            $sql = "SELECT COUNT(*) + 1 as rank FROM users WHERE Money > (SELECT Money FROM users WHERE Iduser = ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $rank = $result['rank'];
            $stmt->close();
            
            $totalSql = "SELECT COUNT(*) as total FROM users";
            $totalResult = $conn->query($totalSql)->fetch_assoc();
            $total = $totalResult['total'];
        } elseif ($type === 'game' && $gameName) {
            // Lấy rank theo game
            $userEarnedSql = "SELECT COALESCE(SUM(win_amount), 0) as total FROM game_history WHERE user_id = ? AND game_name = ?";
            $userStmt = $conn->prepare($userEarnedSql);
            $userStmt->bind_param("is", $userId, $gameName);
            $userStmt->execute();
            $userTotal = $userStmt->get_result()->fetch_assoc()['total'];
            $userStmt->close();
            
            $rankSql = "SELECT COUNT(*) + 1 as rank FROM (
                SELECT user_id, SUM(win_amount) as total
                FROM game_history
                WHERE game_name = ?
                GROUP BY user_id
                HAVING total > ?
            ) as sub";
            $rankStmt = $conn->prepare($rankSql);
            $rankStmt->bind_param("sd", $gameName, $userTotal);
            $rankStmt->execute();
            $rankResult = $rankStmt->get_result()->fetch_assoc();
            $rank = $rankResult['rank'];
            $rankStmt->close();
            
            $totalSql = "SELECT COUNT(DISTINCT user_id) as total FROM game_history WHERE game_name = ?";
            $totalStmt = $conn->prepare($totalSql);
            $totalStmt->bind_param("s", $gameName);
            $totalStmt->execute();
            $totalResult = $totalStmt->get_result()->fetch_assoc();
            $total = $totalResult['total'];
            $totalStmt->close();
        }
        
        echo json_encode([
            'success' => true,
            'rank' => $rank,
            'total' => $total,
            'type' => $type,
            'game_name' => $gameName
        ]);
        break;
        
    case 'get_statistics':
        // Lấy thống kê của user
        $sql = "SELECT * FROM user_statistics WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$stats) {
            // Tạo mới nếu chưa có
            $insertSql = "INSERT INTO user_statistics (user_id) VALUES (?)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("i", $userId);
            $insertStmt->execute();
            $insertStmt->close();
            
            // Lấy lại
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stats = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        
        echo json_encode(['success' => true, 'statistics' => $stats]);
        break;
        
    case 'get_games_list':
        // Lấy danh sách các game có trong hệ thống
        $sql = "SELECT DISTINCT game_name, COUNT(*) as total_plays 
                FROM game_history 
                GROUP BY game_name 
                ORDER BY total_plays DESC";
        $result = $conn->query($sql);
        
        $games = [];
        while ($row = $result->fetch_assoc()) {
            $games[] = $row;
        }
        
        echo json_encode(['success' => true, 'games' => $games]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!']);
        break;
}

$conn->close();

