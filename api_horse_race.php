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
    case 'get_status':
        // Lấy thông tin user
        $uRes = $conn->query("SELECT Money FROM users WHERE Iduser = $userId");
        $userMoney = $uRes->fetch_assoc()['Money'];

        // Lấy phiên đua hiện tại (betting hoặc racing)
        $race = $conn->query("SELECT * FROM horse_races WHERE status IN ('betting', 'racing') ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
        
        $myPayout = 0;
        if (!$race) {
            // Nếu không có phiên đang chạy, lấy phiên vừa kết thúc (result)
            $race = $conn->query("SELECT * FROM horse_races WHERE status = 'result' ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
            if (!$race) {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy phiên đua!']);
                exit;
            }
            $status = 'result';
            $timeLeft = "00:00";
        } else {
            $status = $race['status'];
            
            // Tính thời gian còn lại
            $now = time();
            if ($status === 'betting') {
                $end = strtotime($race['close_at']); // betting -> đóng lúc close_at
                $diff = $end - $now;
                $timeLeft = sprintf("%02d:%02d", max(0, floor($diff / 60)), max(0, $diff % 60));
            } else {
                $end = strtotime($race['start_at']) + 30; // racing -> start_at + 30s
                $diff = $end - $now;
                $timeLeft = sprintf("00:%02d", max(0, $diff));
            }
        }

        $raceId = $race['id'];

        // Tính Pool và Odds
        $pools = array_fill(1, 6, 0);
        $totalPool = 0;
        $betsRes = $conn->query("SELECT horse_num, SUM(amount) as total FROM horse_bets WHERE race_id = $raceId GROUP BY horse_num");
        while ($b = $betsRes->fetch_assoc()) {
            $pools[$b['horse_num']] = (float)$b['total'];
            $totalPool += (float)$b['total'];
        }

        $odds = [];
        for ($i = 1; $i <= 6; $i++) {
            if ($pools[$i] > 0) {
                $odds[$i] = round(($totalPool / $pools[$i]) * 0.95, 2);
            } else {
                $odds[$i] = 10.0;
            }
        }

        // Tính my_payout nếu đã có kết quả
        if ($race['status'] === 'result' && $race['winner_horse']) {
            $pr = $conn->query("SELECT payout FROM horse_bets 
                                WHERE race_id=$raceId 
                                AND user_id=$userId 
                                AND horse_num={$race['winner_horse']}");
            if ($pr && $pr->num_rows > 0) {
                $myPayout = (float)$pr->fetch_assoc()['payout'];
            }
        }

        // Cược của user
        $userBets = array_fill(1, 6, 0);
        $myBetsRes = $conn->query("SELECT horse_num, amount FROM horse_bets WHERE race_id = $raceId AND user_id = $userId");
        while ($mb = $myBetsRes->fetch_assoc()) {
            $userBets[$mb['horse_num']] = (float)$mb['amount'];
        }

        echo json_encode([
            'success' => true,
            'race_id' => $raceId,
            'status' => $status,
            'winner_horse' => $race['winner_horse'],
            'time_left' => $timeLeft,
            'user_money' => $userMoney,
            'horse_pools' => $pools,
            'odds' => $odds,
            'user_bets' => $userBets,
            'my_payout' => $myPayout
        ]);
        break;

    case 'place_bet':
        $horseNum = (int)$_POST['horse_num'];
        $amount = (int)$_POST['amount'];

        if ($horseNum < 1 || $horseNum > 6) {
            echo json_encode(['success' => false, 'message' => 'Ngựa không hợp lệ!']);
            exit;
        }

        $conn->begin_transaction();
        try {
            $race = $conn->query("SELECT id FROM horse_races WHERE status = 'betting' ORDER BY created_at DESC LIMIT 1 FOR UPDATE")->fetch_assoc();
            if (!$race) {
                throw new Exception("Hiện không trong giai đoạn nhận cược!");
            }

            $uRes = $conn->query("SELECT Money FROM users WHERE Iduser = $userId FOR UPDATE");
            $userMoney = $uRes->fetch_assoc()['Money'];
            if ($userMoney < $amount) {
                throw new Exception("Số dư không đủ!");
            }

            $stmt = $conn->prepare("INSERT INTO horse_bets (race_id, user_id, horse_num, amount) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE amount = amount + VALUES(amount)");
            $stmt->bind_param("iiii", $race['id'], $userId, $horseNum, $amount);
            $stmt->execute();

            $conn->query("UPDATE users SET Money = Money - $amount WHERE Iduser = $userId");

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Đặt cược thành công!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
}
?>
