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
    case 'status':
        $stmt = $conn->prepare("SELECT * FROM user_greedy_cave WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $cave = $stmt->get_result()->fetch_assoc();

        if (!$cave) {
            echo json_encode(['success' => true, 'has_session' => false]);
        } else {
            echo json_encode(['success' => true, 'has_session' => true, 'session' => $cave]);
        }
        break;

    case 'start':
        $betAmount = (float)str_replace(['.', ','], '', $_POST['bet'] ?? '0');
        if ($betAmount < 1000) {
            echo json_encode(['success' => false, 'message' => 'Mức cược tối thiểu là 1,000 GTLM']);
            exit;
        }

        $conn->begin_transaction();
        try {
            // Kiểm tra session cũ
            $stmt = $conn->prepare("SELECT * FROM user_greedy_cave WHERE user_id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $cave = $stmt->get_result()->fetch_assoc();

            if ($cave && $cave['status'] === 'playing') {
                throw new Exception("Bạn đang trong hang động rồi! Hãy chơi tiếp hoặc rút GTLM.");
            }

            // Lock user
            $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if ($user['Money'] < $betAmount) {
                throw new Exception("Không đủ GTLM (GTLM) để đặt cược!");
            }

            // Trừ GTLM
            $stmt = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
            $stmt->bind_param("di", $betAmount, $userId);
            $stmt->execute();

            // Tạo/Cập nhật session hang động
            if ($cave) {
                $stmt = $conn->prepare("UPDATE user_greedy_cave SET bet_amount = ?, current_step = 0, accumulated_prize = ?, status = 'playing', started_at = NOW() WHERE user_id = ?");
                $stmt->bind_param("ddi", $betAmount, $betAmount, $userId);
            } else {
                $stmt = $conn->prepare("INSERT INTO user_greedy_cave (user_id, bet_amount, current_step, accumulated_prize, status, started_at) VALUES (?, ?, 0, ?, 'playing', NOW())");
                $stmt->bind_param("idd", $userId, $betAmount, $betAmount);
            }
            $stmt->execute();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Đã vào hang!', 'accumulated_prize' => $betAmount]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'step':
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT * FROM user_greedy_cave WHERE user_id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $cave = $stmt->get_result()->fetch_assoc();

            if (!$cave || $cave['status'] !== 'playing') {
                throw new Exception("Bạn không có phiên thám hiểm nào đang diễn ra!");
            }

            $nextStep = $cave['current_step'] + 1;
            
            // Tính toán tỉ lệ sống sót
            // Công thức: Bước 1 = 95%, Bước 2 = 90%, ... Bước 15 = 25%. Tối thiểu 15%.
            $survivalRate = max(15, 100 - ($nextStep * 5));
            $isSurvive = (rand(1, 100) <= $survivalRate);

            if ($isSurvive) {
                // Sống sót -> Tính GTLM thưởng
                // Hệ số nhân = 1 + (step * step * 0.05)
                // Bước 1: x1.05. Bước 2: x1.20. Bước 3: x1.45. Bước 5: x2.25. Bước 10: x6.00.
                $multiplier = 1 + ($nextStep * $nextStep * 0.05);
                $newPrize = round($cave['bet_amount'] * $multiplier);

                $stmt = $conn->prepare("UPDATE user_greedy_cave SET current_step = ?, accumulated_prize = ? WHERE user_id = ?");
                $stmt->bind_param("idi", $nextStep, $newPrize, $userId);
                $stmt->execute();

                $conn->commit();
                echo json_encode([
                    'success' => true, 
                    'survived' => true, 
                    'step' => $nextStep, 
                    'prize' => $newPrize,
                    'multiplier' => $multiplier,
                    'message' => 'Bước đi an toàn!'
                ]);
            } else {
                // Sập hầm -> Mất hết
                $stmt = $conn->prepare("UPDATE user_greedy_cave SET current_step = ?, status = 'crashed', accumulated_prize = 0 WHERE user_id = ?");
                $stmt->bind_param("ii", $nextStep, $userId);
                $stmt->execute();

                // Ghi lịch sử game
                require_once 'game_history_helper.php';
                logGameHistoryWithAll($conn, $userId, 'Hang Động Tham Lam', $cave['bet_amount'], 0, false);

                $conn->commit();
                echo json_encode([
                    'success' => true, 
                    'survived' => false, 
                    'step' => $nextStep,
                    'message' => 'SẬP HẦM! Bạn đã mất trắng số GTLM cược.'
                ]);
            }

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'cashout':
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT * FROM user_greedy_cave WHERE user_id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $cave = $stmt->get_result()->fetch_assoc();

            if (!$cave || $cave['status'] !== 'playing') {
                throw new Exception("Bạn không có phiên thám hiểm nào để rút!");
            }

            if ($cave['current_step'] == 0) {
                throw new Exception("Bạn chưa bước đi bước nào, không thể rút!");
            }

            $prize = $cave['accumulated_prize'];

            // Trả GTLM cho user
            $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            $stmt->bind_param("di", $prize, $userId);
            $stmt->execute();

            // Kết thúc session
            $stmt = $conn->prepare("UPDATE user_greedy_cave SET status = 'cashed_out' WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();

            // Ghi lịch sử game
            require_once 'game_history_helper.php';
            logGameHistoryWithAll($conn, $userId, 'Hang Động Tham Lam', $cave['bet_amount'], $prize, true);

            $conn->commit();
            
            // Format số GTLM để trả về
            $prizeFormatted = number_format($prize, 0, ',', '.');
            
            // Check nếu ăn to thì báo chat
            if ($prize > $cave['bet_amount'] * 3 && $prize > 500000) {
                $sysId = 0; $sysName = 'Tin Đồn'; $sysAvatar = 'https://cdn-icons-png.flaticon.com/512/1041/1041044.png';
                $stmt_user = $conn->prepare("SELECT Name FROM users WHERE Iduser = ?");
                $stmt_user->bind_param("i", $userId);
                $stmt_user->execute();
                $uName = $stmt_user->get_result()->fetch_assoc()['Name'];
                
                $msg = "Tin Đồn: $uName vừa toàn mạng trở về từ Hang Động Tham Lam, vác theo $prizeFormatted GTLM! Quá liều lĩnh!";
                $stmt_chat = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt_chat->bind_param("isss", $sysId, $sysName, $msg, $sysAvatar);
                $stmt_chat->execute();
            }

            // Trả về số dư mới để update giao diện
            $stmt_balance = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
            $stmt_balance->bind_param("i", $userId);
            $stmt_balance->execute();
            $newMoney = $stmt_balance->get_result()->fetch_assoc()['Money'];

            echo json_encode([
                'success' => true, 
                'prize' => $prize, 
                'bet_amount' => $cave['bet_amount'],
                'new_money' => $newMoney,
                'message' => "Tuyệt vời! Bạn đã rút thành công $prizeFormatted GTLM."
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ.']);
}
?>
