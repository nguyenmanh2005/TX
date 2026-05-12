<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

$userId = $_SESSION['Iduser'] ?? 0;

// 1. Khởi tạo Database
$conn->query("CREATE TABLE IF NOT EXISTS history_banharc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    fish_name VARCHAR(50),
    multiplier INT,
    reward BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'shoot':
        if (!$userId) exit(json_encode(['success' => false, 'message' => 'Chưa đăng nhập']));
        
        $bulletPrice = (int)$_POST['bullet_price'];
        $allowed = [100, 500, 1000, 5000];
        if (!in_array($bulletPrice, $allowed)) exit(json_encode(['success' => false, 'message' => 'Mức đạn không hợp lệ']));

        $user = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc();
        if ($user['Money'] < $bulletPrice) exit(json_encode(['success' => false, 'message' => 'Hết đạn (Tiền)!']));

        // Trừ tiền ngay khi bắn
        $conn->query("UPDATE users SET Money = Money - $bulletPrice WHERE Iduser = $userId");
        
        echo json_encode(['success' => true, 'new_balance' => $user['Money'] - $bulletPrice]);
        break;

    case 'catch':
        if (!$userId) exit(json_encode(['success' => false]));
        
        $fishType = $_POST['fish_type']; // 'small', 'medium', 'large', 'boss', etc.
        $bulletPrice = (int)$_POST['bullet_price'];
        
        // Cấu hình hệ số thưởng
        $fishConfig = [
            'small' => ['x' => 2, 'name' => 'Cá Xanh'],
            'medium' => ['x' => 5, 'name' => 'Cá Vàng'],
            'large' => ['x' => 10, 'name' => 'Cá Đỏ'],
            'shark' => ['x' => 20, 'name' => 'Cá Mập'],
            'octopus' => ['x' => 50, 'name' => 'Bạch Tuộc'],
            'gold_crab' => ['x' => 100, 'name' => 'Cua Vàng'],
            'dragon' => ['x' => 500, 'name' => 'Rồng Biển']
        ];

        if (!isset($fishConfig[$fishType])) exit(json_encode(['success' => false]));

        $reward = $bulletPrice * $fishConfig[$fishType]['x'];
        
        // Cộng tiền cho người chơi
        $conn->query("UPDATE users SET Money = Money + $reward WHERE Iduser = $userId");
        
        // Lưu lịch sử
        $stmt = $conn->prepare("INSERT INTO history_banharc (user_id, fish_name, multiplier, reward) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isii", $userId, $fishConfig[$fishType]['name'], $fishConfig[$fishType]['x'], $reward);
        $stmt->execute();

        echo json_encode(['success' => true, 'reward' => $reward, 'fish_name' => $fishConfig[$fishType]['name']]);
        break;

    case 'get_history':
        $res = $conn->query("SELECT h.*, u.Name FROM history_banharc h JOIN users u ON h.user_id = u.Iduser ORDER BY h.id DESC LIMIT 10");
        $history = [];
        while ($row = $res->fetch_assoc()) $history[] = $row;
        echo json_encode(['success' => true, 'history' => $history]);
        break;
}
