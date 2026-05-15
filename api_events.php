<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_active_event':
        $sql = "SELECT * FROM seasonal_events WHERE status = 'active' AND starts_at <= NOW() AND ends_at >= NOW() LIMIT 1";
        $event = $conn->query($sql)->fetch_assoc();
        
        if (!$event) {
            echo json_encode(['success' => false, 'message' => 'Hiện không có sự kiện nào đang diễn ra.']);
            exit;
        }

        // Lấy danh sách phần thưởng (không gửi weight về client để bảo mật)
        $rewardsRes = $conn->query("SELECT id, reward_type, reward_name, reward_icon FROM event_rewards WHERE event_id = {$event['id']}");
        $rewards = [];
        while ($r = $rewardsRes->fetch_assoc()) $rewards[] = $r;

        echo json_encode(['success' => true, 'event' => $event, 'rewards' => $rewards]);
        break;

    case 'spin':
        $conn->begin_transaction();
        try {
            // 1. Kiểm tra event
            $event = $conn->query("SELECT * FROM seasonal_events WHERE status = 'active' AND starts_at <= NOW() AND ends_at >= NOW() LIMIT 1 FOR UPDATE")->fetch_assoc();
            if (!$event) throw new Exception("Sự kiện đã kết thúc!");

            // 2. Kiểm tra  Gtlm
            $uRes = $conn->query("SELECT Money FROM users WHERE Iduser = $userId FOR UPDATE");
            $userMoney = $uRes->fetch_assoc()['Money'];
            if ($userMoney < $event['spin_cost']) throw new Exception("Số dư không đủ!");

            // 3. Lấy danh sách phần thưởng còn hàng
            $rewardsRes = $conn->query("SELECT * FROM event_rewards WHERE event_id = {$event['id']} AND (quantity_left = -1 OR quantity_left > 0)");
            $rewards = [];
            while ($r = $rewardsRes->fetch_assoc()) $rewards[] = $r;
            
            if (empty($rewards)) throw new Exception("Tất cả phần thưởng đã được nhận hết!");

            // 4. Logic Pity (Nếu 10 lần gần nhất không trúng item/frame -> tăng trọng số cho quà xịn)
            // Đơn giản hóa: Nếu pity > 10, chỉ chọn từ các quà hiếm
            $pityRes = $conn->query("SELECT COUNT(*) as total FROM event_spins s JOIN event_rewards r ON s.reward_id = r.id 
                                     WHERE s.user_id = $userId AND s.event_id = {$event['id']} 
                                     AND r.reward_type IN ('money', 'nothing') 
                                     ORDER BY s.created_at DESC LIMIT 10");
            $pityCount = $pityRes->fetch_assoc()['total'];
            
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

            // 5. Cập nhật số lượng quà
            if ($winner['is_limited'] && $winner['quantity_left'] > 0) {
                $conn->query("UPDATE event_rewards SET quantity_left = quantity_left - 1 WHERE id = {$winner['id']}");
            }

            // 6. Trừ  Gtlm và lưu lịch sử
            $conn->query("UPDATE users SET Money = Money - {$event['spin_cost']} WHERE Iduser = $userId");
            $conn->query("INSERT INTO event_spins (event_id, user_id, reward_id) VALUES ({$event['id']}, $userId, {$winner['id']})");

            // 7. Trao giải
            deliverReward($userId, $winner, $conn);

            $conn->commit();
            echo json_encode([
                'success' => true, 
                'reward' => $winner, 
                'new_money' => $userMoney - $event['spin_cost']
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
}

function deliverReward($userId, $reward, $conn) {
    switch ($reward['reward_type']) {
        case 'money':
            $amount = (int)$reward['reward_value'];
            $conn->query("UPDATE users SET Money = Money + $amount WHERE Iduser = $userId");
            break;
            
        case 'avatar_frame':
            $frameId = (int)$reward['reward_value'];
            // Check if user already has it
            $check = $conn->query("SELECT id FROM user_avatar_frames WHERE user_id = $userId AND avatar_frame_id = $frameId");
            if ($check->num_rows === 0) {
                $conn->query("INSERT INTO user_avatar_frames (user_id, avatar_frame_id) VALUES ($userId, $frameId)");
            }
            break;
            
        case 'title':
            $titleId = (int)$reward['reward_value'];
            $check = $conn->query("SELECT id FROM user_achievements WHERE user_id = $userId AND achievement_id = $titleId");
            if ($check->num_rows === 0) {
                $conn->query("INSERT INTO user_achievements (user_id, achievement_id) VALUES ($userId, $titleId)");
            }
            break;

        case 'item':
            // Xử lý các loại item khác tùy hệ thống của bạn
            break;
    }
}
?>
