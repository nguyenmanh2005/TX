<?php
/**
 * API Dashboard Widgets
 * Cung cấp dữ liệu cho các widgets trên dashboard
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập']);
    exit();
}

require 'db_connect.php';

$userId = $_SESSION['Iduser'];
$action = $_GET['action'] ?? '';

if ($action === 'recent_activity') {
    $activities = [];
    
    // Kiểm tra bảng game_history
    $checkTable = $conn->query("SHOW TABLES LIKE 'game_history'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $sql = "SELECT game_name, played_at, is_win, win_amount, bet_amount 
                FROM game_history 
                WHERE user_id = ? 
                ORDER BY played_at DESC 
                LIMIT 5";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $icon = $row['is_win'] ? '🎉' : '🎮';
            $title = $row['is_win'] 
                ? "Thắng " . number_format($row['win_amount'], 0, ',', '.') . " GTLM trong " . $row['game_name']
                : "Chơi " . $row['game_name'];
            
            $time = timeAgo($row['played_at']);
            
            $activities[] = [
                'icon' => $icon,
                'title' => $title,
                'time' => $time
            ];
        }
        $stmt->close();
    }
    
    // Nếu không có activity, thêm mẫu
    if (empty($activities)) {
        $activities[] = [
            'icon' => '🎮',
            'title' => 'Bắt đầu chơi game đầu tiên của bạn!',
            'time' => 'Bây giờ'
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        'activities' => $activities
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ']);
}

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Vừa xong';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' phút trước';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' giờ trước';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' ngày trước';
    } else {
        return date('d/m/Y', $timestamp);
    }
}

$conn->close();
?>
