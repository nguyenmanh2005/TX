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
        $fishType = $_POST['fish_type'] ?? ''; // Loại cá muốn bắn (tùy chọn)
        
        $allowed = [100, 500, 1000, 5000];
        if (!in_array($bulletPrice, $allowed)) exit(json_encode(['success' => false, 'message' => 'Mức đạn không hợp lệ']));

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if ($user['Money'] < $bulletPrice) {
                throw new Exception('Hết đạn ( Gtlm)!');
            }

            // 1. Luôn trừ  Gtlm đạn
            $newBalance = $user['Money'] - $bulletPrice;
            $upd = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
            $upd->bind_param("di", $newBalance, $userId);
            $upd->execute();

            $caught = false;
            $reward = 0;
            $fishName = '';

            // 2. Nếu có nhắm vào cá, thực hiện roll xác suất ngay tại đây
            if (!empty($fishType)) {
                $fishConfig = [
                    'small' => ['x' => 2, 'name' => 'Cá Xanh', 'p' => 0.4],
                    'medium' => ['x' => 5, 'name' => 'Cá Vàng', 'p' => 0.15],
                    'large' => ['x' => 10, 'name' => 'Cá Đỏ', 'p' => 0.08],
                    'shark' => ['x' => 20, 'name' => 'Cá Mập', 'p' => 0.04],
                    'octopus' => ['x' => 50, 'name' => 'Bạch Tuộc', 'p' => 0.015],
                    'gold_crab' => ['x' => 100, 'name' => 'Cua Vàng', 'p' => 0.008],
                    'dragon' => ['x' => 500, 'name' => 'Rồng Biển', 'p' => 0.002]
                ];

                if (isset($fishConfig[$fishType])) {
                    $roll = rand(1, 10000) / 10000;
                    if ($roll <= $fishConfig[$fishType]['p']) {
                        $caught = true;
                        $reward = $bulletPrice * $fishConfig[$fishType]['x'];
                        $fishName = $fishConfig[$fishType]['name'];
                        $newBalance += $reward;
                        
                        // Cập nhật lại  Gtlm sau khi trúng
                        $updReward = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
                        $updReward->bind_param("di", $newBalance, $userId);
                        $updReward->execute();

                        // Lưu lịch sử
                        $his = $conn->prepare("INSERT INTO history_banharc (user_id, fish_name, multiplier, reward) VALUES (?, ?, ?, ?)");
                        $his->bind_param("isii", $userId, $fishName, $fishConfig[$fishType]['x'], $reward);
                        $his->execute();
                    }
                }
            }

            $conn->commit();
            echo json_encode([
                'success' => true, 
                'new_balance' => $newBalance,
                'caught' => $caught,
                'reward' => $reward,
                'fish_name' => $fishName
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_history':
        $res = $conn->query("SELECT h.*, u.Name FROM history_banharc h JOIN users u ON h.user_id = u.Iduser ORDER BY h.id DESC LIMIT 10");
        $history = [];
        while ($row = $res->fetch_assoc()) $history[] = $row;
        echo json_encode(['success' => true, 'history' => $history]);
        break;
}
