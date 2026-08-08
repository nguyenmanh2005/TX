<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

$userId = $_SESSION['Iduser'] ?? 0;

/**
 * HÃ m láº¥y Round hiá»‡n táº¡i hoáº·c táº¡o má»›i
 */
function getCurrentRound(mysqli $conn) {
    $res = $conn->query("SELECT * FROM megaspin_rounds WHERE status = 'active' LIMIT 1");
    if ($res->num_rows > 0) {
        $round = $res->fetch_assoc();
        
        // Kiá»ƒm tra xem Ä‘Ã£ háº¿t giá» chÆ°a (60s)
        $endTime = strtotime($round['start_at']) + 60;
        if (time() >= $endTime) {
            return processWinner($conn, $round);
        }
        return $round;
    } else {
        // Táº¡o round má»›i
        $endAt = date('Y-m-d H:i:s', time() + 60);
        $conn->query("INSERT INTO megaspin_rounds (start_at, end_at, status) VALUES (NOW(), '$endAt', 'active')");
        return getCurrentRound($conn);
    }
}

/**
 * HÃ m xá»­ lÃ½ chá»n ngÆ°á»i chiáº¿n tháº¯ng
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
        
        // Trao thÆ°á»Ÿng 95% pool
        // Trao thưởng 95% pool
        $winAmount = $totalPool * 0.95;
        $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $winnerId");
        
        // Gửi thông báo cho winner
        require_once 'notification_helper.php';
        $winnerNameRes = $conn->query("SELECT Name FROM users WHERE Iduser = $winnerId")->fetch_assoc();
        $wName = $winnerNameRes['Name'] ?? "Người chơi ẩn danh";
        
        // Log vào chat hệ thống
        $msg = "🎉 Chúc mừng [$wName] đã húp hũ Mega Spin trị giá " . number_format($winAmount) . " GTLM!";
        $conn->query("INSERT INTO chat_messages (user_id, username, message, avatar) VALUES (0, 'MEGA SPIN', '$msg', 'https://cdn-icons-png.flaticon.com/512/2583/2583150.png')");
    }

    // Káº¿t thÃºc round cÅ©
    $conn->query("UPDATE megaspin_rounds SET status = 'ended', winner_id = " . ($winnerId ?? 'NULL') . ", pool_amount = $totalPool WHERE id = $roundId");
    
    // Táº¡o round má»›i
    return getCurrentRound($conn);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_status':
        $round = getCurrentRound($conn);
        $roundId = $round['id'];
        
        // Láº¥y danh sÃ¡ch ngÆ°á»i tham gia
        $participantsRes = $conn->query("SELECT u.Name, u.ImageURL, SUM(t.amount) as total_bet 
                                       FROM megaspin_tickets t 
                                       JOIN users u ON t.user_id = u.Iduser 
                                       WHERE t.round_id = $roundId 
                                       GROUP BY t.user_id 
                                       ORDER BY t.created_at DESC LIMIT 20");
        $participants = [];
        $myChance = 0;
        $currentPool = 0;
        
        // TÃ­nh tá»•ng pool thá»±c táº¿ tá»« cÃ¡c vÃ©
        $poolRes = $conn->query("SELECT SUM(amount) as pool FROM megaspin_tickets WHERE round_id = $roundId")->fetch_assoc();
        $currentPool = (float)($poolRes['pool'] ?? 0);

        while ($row = $participantsRes->fetch_assoc()) {
            $participants[] = $row;
        }

        // TÃ­nh % tháº¯ng cá»§a báº£n thÃ¢n
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
        if (!$userId) exit(json_encode(['success' => false, 'message' => 'ChÆ°a Ä‘Äƒng nháº­p']));
        
        $amount = (int)$_POST['amount'];
        $allowed = [1000, 5000, 10000, 50000, 100000, 500000];
        if (!in_array($amount, $allowed)) exit(json_encode(['success' => false, 'message' => 'Má»©c cÆ°á»£c khÃ´ng há»£p lá»‡']));

        $round = getCurrentRound($conn);
        $roundId = $round['id'];

        // Kiá»ƒm tra  Gtlm
        $userMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
        if ($userMoney < $amount) exit(json_encode(['success' => false, 'message' => 'KhÃ´ng Ä‘á»§  Gtlm!']));

        $conn->begin_transaction();
        try {
            $conn->query("UPDATE users SET Money = Money - $amount WHERE Iduser = $userId");
            $conn->query("INSERT INTO megaspin_tickets (round_id, user_id, amount, tickets) VALUES ($roundId, $userId, $amount, $amount)");
            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lá»—i há»‡ thá»‘ng']);
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
