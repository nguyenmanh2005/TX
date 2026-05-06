<?php
/**
 * API để load challenges cho widget trên trang chính
 */

session_start();
require 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];
$type = $_GET['type'] ?? 'daily'; // 'daily' or 'weekly'

function table_exists(mysqli $conn, string $name): bool {
    $safe = $conn->real_escape_string($name);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

if ($type === 'daily') {
    if (!table_exists($conn, 'daily_challenges')) {
        echo json_encode(['success' => false, 'message' => 'Bảng daily_challenges chưa tồn tại!']);
        exit;
    }
    
    $today = date('Y-m-d');
    $sql = "SELECT dc.*, 
            COALESCE(dcp.progress, 0) as user_progress,
            COALESCE(dcp.is_completed, 0) as is_completed,
            COALESCE(dcp.claimed, 0) as claimed
            FROM daily_challenges dc
            LEFT JOIN daily_challenge_progress dcp ON dc.id = dcp.challenge_id AND dcp.user_id = ?
            WHERE dc.challenge_date = ?
            ORDER BY dc.id
            LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $userId, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $challenges = [];
    $completed = 0;
    $claimed = 0;
    
    while ($row = $result->fetch_assoc()) {
        $challenges[] = $row;
        if ($row['is_completed'] == 1) $completed++;
        if ($row['claimed'] == 1) $claimed++;
    }
    $stmt->close();
    
    $total = count($challenges);
    $percent = $total > 0 ? round(($completed / $total) * 100) : 0;
    
    echo json_encode([
        'success' => true,
        'challenges' => $challenges,
        'summary' => [
            'completed' => $completed,
            'total' => $total,
            'claimed' => $claimed,
            'percent' => $percent
        ],
        'date' => $today
    ]);
    
} elseif ($type === 'weekly') {
    if (!table_exists($conn, 'weekly_challenges')) {
        echo json_encode(['success' => false, 'message' => 'Bảng weekly_challenges chưa tồn tại!']);
        exit;
    }
    
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $weekEnd = date('Y-m-d', strtotime('sunday this week'));
    
    $sql = "SELECT wc.*, 
            COALESCE(wcp.progress, 0) as user_progress,
            COALESCE(wcp.is_completed, 0) as is_completed,
            COALESCE(wcp.claimed, 0) as claimed
            FROM weekly_challenges wc
            LEFT JOIN weekly_challenge_progress wcp ON wc.id = wcp.challenge_id AND wcp.user_id = ?
            WHERE wc.week_start = ? AND wc.week_end = ?
            ORDER BY wc.id
            LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $userId, $weekStart, $weekEnd);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $challenges = [];
    $completed = 0;
    $claimed = 0;
    
    while ($row = $result->fetch_assoc()) {
        $challenges[] = $row;
        if ($row['is_completed'] == 1) $completed++;
        if ($row['claimed'] == 1) $claimed++;
    }
    $stmt->close();
    
    $total = count($challenges);
    $percent = $total > 0 ? round(($completed / $total) * 100) : 0;
    
    echo json_encode([
        'success' => true,
        'challenges' => $challenges,
        'summary' => [
            'completed' => $completed,
            'total' => $total,
            'claimed' => $claimed,
            'percent' => $percent
        ],
        'week_start' => $weekStart,
        'week_end' => $weekEnd
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Type không hợp lệ!']);
}

$conn->close();
?>

