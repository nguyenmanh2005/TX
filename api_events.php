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

        $spinCount = isset($_POST['spin_count']) ? (int)$_POST['spin_count'] : 1;
        if (!in_array($spinCount, [1, 5, 10])) $spinCount = 1;

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
            
            $totalCost = $event['spin_cost'] * $spinCount;
            if ($userMoney < $totalCost) throw new Exception("Số dư không đủ cho $spinCount lượt quay!");

            // 3. Lấy danh sách phần thưởng còn hàng (FOR UPDATE lock để tránh oversell)
            $stmtRw = $conn->prepare("SELECT * FROM event_rewards WHERE event_id = ? AND (quantity_left = -1 OR quantity_left > 0) FOR UPDATE");
            $stmtRw->bind_param("i", $event['id']);
            $stmtRw->execute();
            $rewards = $stmtRw->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtRw->close();
            
            if (empty($rewards)) throw new Exception("Tất cả phần thưởng đã được nhận hết!");

            // 4. Logic Pity — Đọc TRONG transaction sau khi đã lock
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
            $pityCount = (int)$stmtPity->get_result()->fetch_assoc()['total'];
            $stmtPity->close();
            
            $results = [];
            $totalMoneyWon = 0;
            
            for ($i = 0; $i < $spinCount; $i++) {
                $winner = null;
                $currentRewards = $rewards; // Copy để lọc
                
                // Cập nhật lại số lượng còn lại cho currentRewards (nếu trong cùng lượt trước đó bị trừ)
                foreach($currentRewards as $k => $cr) {
                    if ($cr['is_limited']) {
                        // Đếm xem đã trúng bao nhiêu lần trong lượt này
                        $wonCount = 0;
                        foreach($results as $res) {
                            if ($res['id'] == $cr['id']) $wonCount++;
                        }
                        if ($cr['quantity_left'] !== -1 && $cr['quantity_left'] - $wonCount <= 0) {
                            unset($currentRewards[$k]); // Loại khỏi pool
                        }
                    }
                }
                
                if (empty($currentRewards)) break; // Hết sạch quà
                
                if ($pityCount >= 10) {
                    // Guaranteed rare item if exists
                    $rareRewards = array_filter($currentRewards, function($r) { 
                        return in_array($r['reward_type'], ['item', 'title', 'avatar_frame']); 
                    });
                    if (!empty($rareRewards)) $currentRewards = $rareRewards;
                }

                // Weighted Random
                $totalWeight = array_sum(array_column($currentRewards, 'weight'));
                $rand = mt_rand(1, $totalWeight);
                $cumulative = 0;
                foreach ($currentRewards as $r) {
                    $cumulative += $r['weight'];
                    if ($rand <= $cumulative) {
                        $winner = $r;
                        break;
                    }
                }
                
                if (!$winner) $winner = array_values($currentRewards)[0]; // Fallback
                
                // Cập nhật pity logic
                if (in_array($winner['reward_type'], ['money', 'nothing'])) {
                    $pityCount++;
                } else {
                    $pityCount = 0;
                }
                
                $results[] = $winner;
                if ($winner['reward_type'] === 'money') {
                    $totalMoneyWon += (int)$winner['reward_value'];
                }
            }

            // 5. Lưu vào Database
            // Trừ lượng quà hữu hạn
            $qtyUpdates = [];
            foreach ($results as $res) {
                if ($res['is_limited']) {
                    if (!isset($qtyUpdates[$res['id']])) $qtyUpdates[$res['id']] = 0;
                    $qtyUpdates[$res['id']]++;
                }
            }
            
            foreach ($qtyUpdates as $rId => $deductAmount) {
                $stmtQty = $conn->prepare("UPDATE event_rewards SET quantity_left = quantity_left - ? WHERE id = ?");
                $stmtQty->bind_param("ii", $deductAmount, $rId);
                $stmtQty->execute();
                $stmtQty->close();
            }

            // Trừ GTLM
            $stmtDeduct = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
            $stmtDeduct->bind_param("ii", $totalCost, $userId);
            $stmtDeduct->execute();
            $stmtDeduct->close();

            // Insert spins & Trao giải
            foreach ($results as $res) {
                $stmtSpin = $conn->prepare("INSERT INTO event_spins (event_id, user_id, reward_id) VALUES (?, ?, ?)");
                $stmtSpin->bind_param("iii", $event['id'], $userId, $res['id']);
                $stmtSpin->execute();
                $stmtSpin->close();
                
                deliverReward($userId, $res, $conn);
            }

            $finalMoney = $userMoney - $totalCost + $totalMoneyWon;

            $conn->commit();
            echo json_encode([
                'success' => true, 
                'results' => $results,
                'reward' => $results[0], // Giữ fallback cho frontend cũ
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
