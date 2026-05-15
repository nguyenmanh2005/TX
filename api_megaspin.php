<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

$userId = $_SESSION['Iduser'] ?? 0;

// 1. Khởi tạo Database nếu chưa có
$conn->query("CREATE TABLE IF NOT EXISTS megaspin_rounds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pool_amount BIGINT DEFAULT 0,
    status ENUM('active', 'ended') DEFAULT 'active',
    winner_id INT DEFAULT NULL,
    start_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_at TIMESTAMP NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS megaspin_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    round_id INT,
    user_id INT,
    amount BIGINT,
    tickets BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(round_id),
    INDEX(user_id)
)");

/**
 * Hàm lấy Round hiện tại hoặc tạo mới
 */
function getCurrentRound(mysqli $conn) {
    $res = $conn->query("SELECT * FROM megaspin_rounds WHERE status = 'active' LIMIT 1");
    if ($res->num_rows > 0) {
        $round = $res->fetch_assoc();
        
        // Kiểm tra xem đã hết giờ chưa (60s)
        $endTime = strtotime($round['start_at']) + 60;
        if (time() >= $endTime) {
            return processWinner($conn, $round);
        }
        return $round;
    } else {
        // Tạo round mới
        $endAt = date('Y-m-d H:i:s', time() + 60);
        $conn->query("INSERT INTO megaspin_rounds (start_at, end_at, status) VALUES (NOW(), '$endAt', 'active')");
        return getCurrentRound($conn);
    }
}

/**
 * Hàm xử lý chọn người chiến thắng
 */
function processWinner(mysqli $conn, array $round) {
    $roundId = $round['id'];
    $ticketsRes = $conn->query("SELECT user_id, SUM(tickets) as total_user_tickets FROM megaspin_tickets WHERE round_id = $roundId GROUP BY user_id");
    
    $allTickets = [];
    $totalPool = 0;
    
    while ($row = $ticketsRes->fetch_assoc()) {
        for ($i = 0; $i < $row['total_user_tickets']; $i++) {
            $allTickets[] = $row['user_id'];
        }
        $totalPool += ($row['total_user_tickets']); // 1 ticket = 1 GTLM
    }

    $winnerId = null;
    if (!empty($allTickets)) {
        $winnerId = $allTickets[array_rand($allTickets)];
        
        // Trao thưởng 95% pool
        $winAmount = $totalPool * 0.95;
        $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $winnerId");
        
        // Gửi thông báo cho winner
        require_once 'notification_helper.php';
        $winnerNameRes = $conn->query("SELECT Name FROM users WHERE Iduser = $winnerId")->fetch_assoc();
        $wName = $winnerNameRes['Name'] ?? "Người chơi ẩn danh";
        
        // Log vào chat hệ thống
        $msg = "🎉 Chúc mừng [$wName] đã thắng hũ Mega Spin trị giá " . number_format($winAmount) . " GTLM!";
        $conn->query("INSERT INTO chat_messages (user_id, username, message, avatar) VALUES (0, 'MEGA SPIN', '$msg', 'https://cdn-icons-png.flaticon.com/512/2583/2583150.png')");
    }

    // Kết thúc round cũ
    $conn->query("UPDATE megaspin_rounds SET status = 'ended', winner_id = " . ($winnerId ?? 'NULL') . ", pool_amount = $totalPool WHERE id = $roundId");
    
    // Tạo round mới
    return getCurrentRound($conn);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_status':
        $round = getCurrentRound($conn);
        $roundId = $round['id'];
        
        // Lấy danh sách người tham gia
        $participantsRes = $conn->query("SELECT u.Name, u.ImageURL, SUM(t.amount) as total_bet 
                                       FROM megaspin_tickets t 
                                       JOIN users u ON t.user_id = u.Iduser 
                                       WHERE t.round_id = $roundId 
                                       GROUP BY t.user_id 
                                       ORDER BY t.created_at DESC LIMIT 20");
        $participants = [];
        $myChance = 0;
        $currentPool = 0;
        
        // Tính tổng pool thực tế từ các vé
        $poolRes = $conn->query("SELECT SUM(amount) as pool FROM megaspin_tickets WHERE round_id = $roundId")->fetch_assoc();
        $currentPool = (float)($poolRes['pool'] ?? 0);

        while ($row = $participantsRes->fetch_assoc()) {
            $participants[] = $row;
        }

        // Tính % thắng của bản thân
        if ($userId > 0 && $currentPool > 0) {
            $myRes = $conn->query("SELECT SUM(amount) as my_bet FROM megaspin_tickets WHERE round_id = $roundId AND user_id = $userId")->fetch_assoc();
            $myBet = (float)($myRes['my_bet'] ?? 0);
            $myChance = ($myBet / $currentPool) * 100;
        }

        echo json_encode([
            'success' => true,
            'round_id' => $roundId,
            'pool' => $currentPool,
            'time_left' => max(0, (strtotime($round['start_at']) + 60) - time()),
            'participants' => $participants,
            'my_chance' => round($myChance, 2),
            'last_winner' => $conn->query("SELECT u.Name FROM megaspin_rounds r JOIN users u ON r.winner_id = u.Iduser WHERE r.status = 'ended' ORDER BY r.id DESC LIMIT 1")->fetch_assoc()
        ]);
        break;

    case 'join':
        if (!$userId) exit(json_encode(['success' => false, 'message' => 'Chưa đăng nhập']));
        
        $amount = (int)$_POST['amount'];
        $allowed = [1000, 5000, 10000, 50000, 100000, 500000];
        if (!in_array($amount, $allowed)) exit(json_encode(['success' => false, 'message' => 'Mức cược không hợp lệ']));

        $round = getCurrentRound($conn);
        $roundId = $round['id'];

        // Kiểm tra  Gtlm
        $userMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
        if ($userMoney < $amount) exit(json_encode(['success' => false, 'message' => 'Không đủ  Gtlm!']));

        $conn->begin_transaction();
        try {
            $conn->query("UPDATE users SET Money = Money - $amount WHERE Iduser = $userId");
            $conn->query("INSERT INTO megaspin_tickets (round_id, user_id, amount, tickets) VALUES ($roundId, $userId, $amount, $amount)");
            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống']);
        }
        break;

    case 'get_history':
        $res = $conn->query("SELECT r.pool_amount, r.end_at, u.Name as winner_name, u.ImageURL 
                            FROM megaspin_rounds r 
                            JOIN users u ON r.winner_id = u.Iduser 
                            WHERE r.status = 'ended' 
                            ORDER BY r.id DESC LIMIT 10");
        $history = [];
        while ($row = $res->fetch_assoc()) $history[] = $row;
        echo json_encode(['success' => true, 'history' => $history]);
        break;
}
