<?php
session_start();
include 'db_connect.php';
require_once 'reward_helper.php';
require_once 'notification_helper.php';
require_once 'api_event_helper.php'; // getActiveSeasonalEvent()

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_active_event':
        // Sử dụng helper tập trung thay vì query trực tiếp
        $event = getActiveSeasonalEvent($conn);
        
        if (!$event) {
            echo json_encode(['success' => false, 'message' => 'Hiện không có sự kiện nào đang diễn ra.']);
            exit;
        }

        // Lấy danh sách phần thưởng kèm tỷ lệ trúng tính toán từ trọng số thô (weight)
        $stmtRw = $conn->prepare("SELECT id, reward_type, reward_name, reward_icon, weight, quantity_left FROM event_rewards WHERE event_id = ?");
        $stmtRw->bind_param("i", $event['id']);
        $stmtRw->execute();
        $rewardsRes = $stmtRw->get_result();
        $rewards = [];
        $totalWeight = 0;
        while ($r = $rewardsRes->fetch_assoc()) {
            $rewards[] = $r;
            $totalWeight += (int)$r['weight'];
        }
        $stmtRw->close();
        
        foreach ($rewards as &$r) {
            $r['chance_percent'] = $totalWeight > 0 ? round(($r['weight'] / $totalWeight) * 100, 2) : 0;
            unset($r['weight']); // Bảo mật trọng số thô
        }
        unset($r);

        echo json_encode(['success' => true, 'event' => $event, 'rewards' => $rewards]);
        break;

    case 'spin':
        // Simple rate limiting: 1 request per second
        $now = microtime(true) * 1000;
        if (isset($_SESSION['last_spin_time'])) {
            $diff = $now - $_SESSION['last_spin_time'];
            if ($diff < 1000) {
                echo json_encode(['success' => false, 'message' => 'Thao tác quá nhanh! Vui lòng đợi 1 giây.']);
                exit;
            }
        }
        $_SESSION['last_spin_time'] = $now;

        $conn->begin_transaction();
        try {
            // 1. Kiểm tra event (FOR UPDATE để lock row, tránh race condition từng bước sau)
            $event = getActiveSeasonalEvent($conn, true); // forUpdate=true
            if (!$event) throw new Exception("Sự kiện đã kết thúc!");

            // 2. Kiểm tra số dư user (FOR UPDATE lock row user)
            $stmtUser = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $stmtUser->bind_param("i", $userId);
            $stmtUser->execute();
            $userMoney = $stmtUser->get_result()->fetch_assoc()['Money'];
            $stmtUser->close();
            if ($userMoney < $event['spin_cost']) throw new Exception("Số dư không đủ!");

            // 3. Lấy danh sách phần thưởng còn hàng (FOR UPDATE lock để tránh oversell)
            $stmtRw = $conn->prepare("SELECT * FROM event_rewards WHERE event_id = ? AND (quantity_left = -1 OR quantity_left > 0) FOR UPDATE");
            $stmtRw->bind_param("i", $event['id']);
            $stmtRw->execute();
            $rewards = $stmtRw->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtRw->close();
            
            if (empty($rewards)) throw new Exception("Tất cả phần thưởng đã được nhận hết!");

            // 4. Logic Pity — Đọc TRONG transaction sau khi đã lock
            // FIX: Trước đây đọc pity ngoài transaction → 2 request đồng thời cùng đạt pity 10 sẽ đều được rare
            // FIX: Bây giờ đọc TRONG transaction sau khi lock user row, chỉ 1 request có thể chạy tại 1 thời điểm
            $stmtPity = $conn->prepare("
                SELECT COUNT(*) as total FROM (
                    SELECT r.reward_type 
                    FROM event_spins s 
                    JOIN event_rewards r ON s.reward_id = r.id 
                    WHERE s.user_id = ? AND s.event_id = ? 
                    ORDER BY s.created_at DESC 
                    LIMIT 10
                ) t 
                WHERE t.reward_type IN ('money', 'nothing')
            ");
            $stmtPity->bind_param("ii", $userId, $event['id']);
            $stmtPity->execute();
            $pityCount = $stmtPity->get_result()->fetch_assoc()['total'];
            $stmtPity->close();
            
            $winner = null;
            if ($pityCount >= 10) {
                // Guaranteed rare item if exists
                $rareRewards = array_filter($rewards, function($r) { 
                    return in_array($r['reward_type'], ['item', 'title', 'avatar_frame']); 
                });
                if (!empty($rareRewards)) $rewards = $rareRewards;
            }

            // Weighted Random
            $totalWeight = array_sum(array_column($rewards, 'weight'));
            $rand = mt_rand(1, $totalWeight);
            $cumulative = 0;
            foreach ($rewards as $r) {
                $cumulative += $r['weight'];
                if ($rand <= $cumulative) {
                    $winner = $r;
                    break;
                }
            }

            // 5. Cập nhật số lượng quà (sử dụng prepared statement)
            if ($winner['is_limited'] && $winner['quantity_left'] > 0) {
                $stmtQty = $conn->prepare("UPDATE event_rewards SET quantity_left = quantity_left - 1 WHERE id = ? AND quantity_left > 0");
                $stmtQty->bind_param("i", $winner['id']);
                $stmtQty->execute();
                if ($stmtQty->affected_rows === 0) throw new Exception("Phần thưởng vừa hết hàng! Hãy quay lại.");
                $stmtQty->close();
            }

            // 6. Trừ Gtlm và lưu lịch sử (prepared statements)
            $spinCost = $event['spin_cost'];
            $stmtDeduct = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
            $stmtDeduct->bind_param("ii", $spinCost, $userId);
            $stmtDeduct->execute();
            $stmtDeduct->close();

            $stmtSpin = $conn->prepare("INSERT INTO event_spins (event_id, user_id, reward_id) VALUES (?, ?, ?)");
            $stmtSpin->bind_param("iii", $event['id'], $userId, $winner['id']);
            $stmtSpin->execute();
            $stmtSpin->close();

            // 7. Trao giải
            deliverReward($userId, $winner, $conn);

            $finalMoney = $userMoney - $spinCost;
            if ($winner['reward_type'] === 'money') {
                $finalMoney += (int)$winner['reward_value'];
            }

            $conn->commit();
            echo json_encode([
                'success' => true, 
                'reward' => $winner, 
                'new_money' => $finalMoney
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
}

// deliverReward() đã được chuyển sang reward_helper.php
?>
