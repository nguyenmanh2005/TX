<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['Iduser'];
$action = $_GET['action'] ?? 'get_status';

// --- CONFIG ---
$bettingDuration = 60;
$racingDuration = 15;
$resultDuration = 5;
$taxRate = 0.05; // 5% fee

// --- HELPERS ---
function getCurrentRace(mysqli $conn) {
    $res = $conn->query("SELECT * FROM horse_races ORDER BY id DESC LIMIT 1");
    return $res->fetch_assoc();
}

function startNewRace(mysqli $conn, int $bettingDuration) {
    $closeAt = date('Y-m-d H:i:s', time() + $bettingDuration);
    $conn->query("INSERT INTO horse_races (status, start_at, close_at) VALUES ('betting', NOW(), '$closeAt')");
    return $conn->insert_id;
}

function calculateOdds(mysqli $conn, int $raceId) {
    $totalPool = 0;
    $horsePools = array_fill(1, 6, 0);
    
    $res = $conn->query("SELECT horse_num, SUM(amount) as total FROM horse_bets WHERE race_id = $raceId GROUP BY horse_num");
    while ($row = $res->fetch_assoc()) {
        $horsePools[$row['horse_num']] = (float)$row['total'];
        $totalPool += (float)$row['total'];
    }
    
    $odds = [];
    $payoutPool = $totalPool * 0.95;
    
    for ($i = 1; $i <= 6; $i++) {
        if ($horsePools[$i] > 0) {
            $odds[$i] = round($payoutPool / $horsePools[$i], 2);
        } else {
            // Default odds if no bets yet
            $odds[$i] = 10.0; 
        }
        // Min odds 1.1
        if ($odds[$i] < 1.1) $odds[$i] = 1.1;
    }
    
    return [
        'odds' => $odds,
        'total_pool' => $totalPool,
        'horse_pools' => $horsePools
    ];
}

// --- STATE MANAGEMENT ---
$currentRace = getCurrentRace($conn);
$now = time();

if (!$currentRace) {
    $raceId = startNewRace($conn, $bettingDuration);
    $currentRace = getCurrentRace($conn);
} else {
    $closeAt = strtotime($currentRace['close_at']);
    $status = $currentRace['status'];
    $raceId = $currentRace['id'];

    if ($status === 'betting' && $now >= $closeAt) {
        // Transition to Racing - Atomic check
        $winner = rand(1, 6);
        $update = $conn->query("UPDATE horse_races SET status = 'racing', winner_horse = $winner, close_at = '" . date('Y-m-d H:i:s', $now + $racingDuration) . "' WHERE id = $raceId AND status = 'betting'");
        
        if ($conn->affected_rows > 0) {
            $currentRace = getCurrentRace($conn);
        }
    } elseif ($status === 'racing' && $now >= $closeAt) {
        // Transition to Result - Atomic check
        $update = $conn->query("UPDATE horse_races SET status = 'result', close_at = '" . date('Y-m-d H:i:s', $now + $resultDuration) . "' WHERE id = $raceId AND status = 'racing'");
        
        if ($conn->affected_rows > 0) {
            // This process won the race to update status, handle payouts
            $winnerHorse = $currentRace['winner_horse'];
            $calc = calculateOdds($conn, $raceId);
            $winMultiplier = $calc['odds'][$winnerHorse];
            
            $bets = $conn->query("SELECT user_id, amount FROM horse_bets WHERE race_id = $raceId AND horse_num = $winnerHorse");
            while ($b = $bets->fetch_assoc()) {
                $winAmount = $b['amount'] * $winMultiplier;
                $bUserId = $b['user_id'];
                
                // Record result
                $conn->query("INSERT INTO horse_results (race_id, user_id, win_amount) VALUES ($raceId, $bUserId, $winAmount)");
                // Update balance
                $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $bUserId");
            }
            $currentRace = getCurrentRace($conn);
        }
    } elseif ($status === 'result' && $now >= $closeAt) {
        // Start New Race - Atomic check
        // We use a separate table or a lock if needed, but here we can just try to insert and then check latest
        // For simplicity, we just check if someone already started a new race
        $latest = getCurrentRace($conn);
        if ($latest['id'] == $raceId) {
            $raceId = startNewRace($conn, $bettingDuration);
            $currentRace = getCurrentRace($conn);
        }
    }
}

// Re-calculate basic info after potential transitions
$status = $currentRace['status'];
$timeLeft = strtotime($currentRace['close_at']) - $now;
if ($timeLeft < 0) $timeLeft = 0;

// --- ACTIONS ---

if ($action === 'get_status') {
    $calc = calculateOdds($conn, $currentRace['id']);
    
    // Get user's current bets
    $userBets = [];
    $res = $conn->query("SELECT horse_num, amount FROM horse_bets WHERE race_id = {$currentRace['id']} AND user_id = $userId");
    while ($row = $res->fetch_assoc()) {
        $userBets[$row['horse_num']] = ($userBets[$row['horse_num']] ?? 0) + $row['amount'];
    }
    
    echo json_encode([
        'success' => true,
        'race_id' => $currentRace['id'],
        'status' => $status,
        'time_left' => $timeLeft,
        'winner_horse' => $currentRace['winner_horse'],
        'odds' => $calc['odds'],
        'total_pool' => $calc['total_pool'],
        'horse_pools' => $calc['horse_pools'],
        'user_bets' => $userBets,
        'user_money' => (float)$conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money']
    ]);
} 

elseif ($action === 'place_bet') {
    if ($status !== 'betting') {
        echo json_encode(['success' => false, 'message' => 'Giai đoạn cược đã kết thúc']);
        exit();
    }
    
    $horseNum = (int)($_POST['horse_num'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    
    if ($horseNum < 1 || $horseNum > 6 || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
        exit();
    }
    
    // Check balance
    $user = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc();
    if ($user['Money'] < $amount) {
        echo json_encode(['success' => false, 'message' => 'Không đủ tiền']);
        exit();
    }
    
    // Deduct money
    $conn->query("UPDATE users SET Money = Money - $amount WHERE Iduser = $userId");
    
    // Record bet
    $conn->query("INSERT INTO horse_bets (race_id, user_id, horse_num, amount) VALUES ({$currentRace['id']}, $userId, $horseNum, $amount)");
    
    echo json_encode(['success' => true, 'message' => 'Đặt cược thành công']);
}
