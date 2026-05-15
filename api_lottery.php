<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

$userId = $_SESSION['Iduser'] ?? null;
$action = $_GET['action'] ?? 'status';

// --- CONFIG ---
$ticketPrice = 10000; // 10k GTLM per ticket
$drawTime = "20:00:00";
$baseJackpot = 1000000; // 1M GTLM base

// --- HELPERS ---

function getDrawForDate(mysqli $conn, string $date) {
    $res = $conn->query("SELECT * FROM lottery_draws WHERE draw_date = '$date'");
    return $res->fetch_assoc();
}

function ensureDrawsExist(mysqli $conn, float $baseJackpot) {
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    // Check Today
    $d1 = getDrawForDate($conn, $today);
    if (!$d1) {
        // Find last jackpot to carry over
        $last = $conn->query("SELECT jackpot_pool FROM lottery_draws WHERE status = 'paid' OR status = 'drawn' ORDER BY draw_date DESC LIMIT 1")->fetch_assoc();
        $initialPool = $last ? $last['jackpot_pool'] : $baseJackpot;
        $conn->query("INSERT INTO lottery_draws (draw_date, jackpot_pool, status) VALUES ('$today', $initialPool, 'pending')");
    }
    
    // Check Tomorrow
    $d2 = getDrawForDate($conn, $tomorrow);
    if (!$d2) {
        $conn->query("INSERT INTO lottery_draws (draw_date, jackpot_pool, status) VALUES ('$tomorrow', $baseJackpot, 'pending')");
    }
}

// --- INITIALIZE & PROCESS ---
ensureDrawsExist($conn, $baseJackpot);

$todayDate = date('Y-m-d');
$currentDraw = getDrawForDate($conn, $todayDate);
$now = time();
$drawTimestamp = strtotime($todayDate . ' ' . $drawTime);

// Check if we need to execute the draw
if ($currentDraw['status'] === 'pending' && $now >= $drawTimestamp) {
    // Generate winning numbers (6 numbers from 01-99)
    $nums = [];
    while(count($nums) < 6) {
        $n = str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);
        if (!in_array($n, $nums)) $nums[] = $n;
    }
    sort($nums);
    $winningStr = implode(',', $nums);
    
    $conn->query("UPDATE lottery_draws SET winning_numbers = '$winningStr', status = 'drawn' WHERE id = {$currentDraw['id']}");
    $currentDraw = getDrawForDate($conn, $todayDate);
    
    // Calculate winners
    $jackpot = (float)$currentDraw['jackpot_pool'];
    $winners = $conn->query("SELECT user_id, id FROM lottery_tickets WHERE draw_id = {$currentDraw['id']} AND numbers = '$winningStr'");
    $winnerCount = $winners->num_rows;
    
    if ($winnerCount > 0) {
        $share = $jackpot / $winnerCount;
        while($w = $winners->fetch_assoc()) {
            $wUid = $w['user_id'];
            $conn->query("UPDATE users SET Money = Money + $share WHERE Iduser = $wUid");
        }
        $conn->query("UPDATE lottery_draws SET status = 'paid' WHERE id = {$currentDraw['id']}");
        // Next day starts at base
        $tomorrowDate = date('Y-m-d', strtotime('+1 day'));
        $conn->query("UPDATE lottery_draws SET jackpot_pool = $baseJackpot WHERE draw_date = '$tomorrowDate'");
    } else {
        // No winners, carry over to tomorrow
        $tomorrowDate = date('Y-m-d', strtotime('+1 day'));
        $conn->query("UPDATE lottery_draws SET jackpot_pool = jackpot_pool + ($jackpot * 0.1) WHERE draw_date = '$tomorrowDate'"); // Add 10% carry over + current
        // Actually usually the WHOLE pool carries over in community lotteries
        $conn->query("UPDATE lottery_draws SET jackpot_pool = $jackpot + 500000 WHERE draw_date = '$tomorrowDate'"); // Simple carry + 500k increase
        $conn->query("UPDATE lottery_draws SET status = 'paid' WHERE id = {$currentDraw['id']}");
    }
}

// --- ACTIONS ---

if ($action === 'status') {
    $userTickets = [];
    if ($userId) {
        $res = $conn->query("SELECT numbers FROM lottery_tickets WHERE draw_id = {$currentDraw['id']} AND user_id = $userId");
        while($row = $res->fetch_assoc()) $userTickets[] = $row['numbers'];
    }
    
    echo json_encode([
        'success' => true,
        'today' => [
            'id' => $currentDraw['id'],
            'date' => $currentDraw['draw_date'],
            'jackpot' => (float)$currentDraw['jackpot_pool'],
            'status' => $currentDraw['status'],
            'winning_numbers' => $currentDraw['winning_numbers'],
            'draw_time' => $todayDate . ' ' . $drawTime
        ],
        'user_tickets' => $userTickets,
        'ticket_price' => $ticketPrice,
        'server_time' => date('Y-m-d H:i:s')
    ]);
}

elseif ($action === 'buy' && $userId) {
    $numbers = $_POST['numbers'] ?? ''; // Format: "01,02,03,04,05,06"
    
    // Validate format
    $numArr = explode(',', $numbers);
    if (count($numArr) !== 6) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng chọn đủ 6 số']);
        exit();
    }
    sort($numArr);
    $numbers = implode(',', $numArr);
    
    // Check balance and deduct in transaction
    $conn->begin_transaction();
    try {
        // Khóa hàng người dùng để tránh race condition
        $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if ($user['Money'] < $ticketPrice) {
            throw new Exception('Không đủ  Gtlm');
        }
        
        // Deduct
        $newMoney = $user['Money'] - $ticketPrice;
        $upd = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $upd->bind_param("di", $newMoney, $userId);
        $upd->execute();
        
        $ins = $conn->prepare("INSERT INTO lottery_tickets (user_id, draw_id, numbers) VALUES (?, ?, ?)");
        $ins->bind_param("iis", $userId, $currentDraw['id'], $numbers);
        $ins->execute();
        
        // Add 50% of ticket price to jackpot pool
        $poolIncrease = $ticketPrice * 0.5;
        $conn->query("UPDATE lottery_draws SET jackpot_pool = jackpot_pool + $poolIncrease WHERE id = {$currentDraw['id']}");
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Mua vé thành công!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

elseif ($action === 'history') {
    $res = $conn->query("SELECT * FROM lottery_draws WHERE status IN ('drawn', 'paid') ORDER BY draw_date DESC LIMIT 10");
    $history = [];
    while($row = $res->fetch_assoc()) $history[] = $row;
    echo json_encode(['success' => true, 'history' => $history]);
}
