<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/game_history_helper.php';

header('Content-Type: application/json; charset=utf-8');

$animals = [
    'wolf' => 'Sói Cyber',
    'fox' => 'Cáo Neon',
    'panther' => 'Báo Plasma',
    'bear' => 'Gấu Sắt',
    'eagle' => 'Đại Bàng Lượng Tử'
];
$animalKeys = array_keys($animals);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- Logic Time Sync ---
$now = time();
$sec = $now % 45;

if ($sec < 20) {
    $phase = 'betting';
    $timeLeft = 20 - $sec;
} else if ($sec < 35) {
    $phase = 'racing';
    $timeLeft = 35 - $sec;
} else {
    $phase = 'result';
    $timeLeft = 45 - $sec;
}
$cycleId = floor($now / 45);

// Tính kết quả cho cycle
$seedStr = "cyber_racing_cycle_" . $cycleId;
$hash = crc32($seedStr);

// Generate deterministic rankings based on the hash
$rankings = $animalKeys;
// Simple deterministic shuffle using the hash
for ($i = 4; $i > 0; $i--) {
    $j = abs($hash) % ($i + 1);
    $temp = $rankings[$i];
    $rankings[$i] = $rankings[$j];
    $rankings[$j] = $temp;
    // Mix the hash up for the next iteration
    $hash = crc32($hash . $i);
}

if ($action === 'get_state') {
    // Nếu phase là result, và user đã login, kiểm tra trả thưởng
    if ($phase === 'result' && isset($_SESSION['Iduser'])) {
        $userId = (int)$_SESSION['Iduser'];
        $conn->begin_transaction();
        
        // Lấy các cược chưa thanh toán của user trong cycle này
        $stmt = $conn->prepare("SELECT id, animal, amount FROM user_bets_cyber_racing WHERE user_id = ? AND cycle_id = ? AND is_paid = 0 FOR UPDATE");
        $stmt->bind_param("ii", $userId, $cycleId);
        $stmt->execute();
        $unpaidBets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $totalWin = 0;
        $totalBet = 0;
        
        $payouts = [
            $rankings[0] => 3.0, // Top 1: x3
            $rankings[1] => 2.0, // Top 2: x2
            $rankings[2] => 0.5, // Top 3: x0.5
            $rankings[3] => 0.0, // Top 4: x0
            $rankings[4] => 0.0  // Top 5: x0
        ];
        
        foreach ($unpaidBets as $b) {
            $amt = (float)$b['amount'];
            $totalBet += $amt;
            
            $multiplier = $payouts[$b['animal']] ?? 0;
            $isWin = ($multiplier > 0) ? 1 : 0;
            $winAmt = $amt * $multiplier;
            
            $totalWin += $winAmt;
            
            $uStmt = $conn->prepare("UPDATE user_bets_cyber_racing SET is_win = ?, is_paid = 1 WHERE id = ?");
            $uStmt->bind_param("ii", $isWin, $b['id']);
            $uStmt->execute();
            $uStmt->close();
        }
        
        if ($totalWin > 0) {
            $conn->query("UPDATE users SET Money = Money + $totalWin WHERE Iduser = $userId");
        }
        
        if ($totalBet > 0) {
            // Log history 
            $profit = $totalWin - $totalBet;
            logGameHistoryWithAll($conn, $userId, 'CYBER RACING', $totalBet, $totalWin, $totalWin > 0);
        }
        
        $conn->commit();
    }
    
    // Nếu đang ở phase betting, trả về tổng cược để render UI
    $totalBets = [];
    foreach ($animalKeys as $k) $totalBets[$k] = 0;
    
    if ($phase === 'betting') {
        $bRes = $conn->query("SELECT animal, SUM(amount) as total FROM user_bets_cyber_racing WHERE cycle_id = $cycleId GROUP BY animal");
        if ($bRes) {
            while ($br = $bRes->fetch_assoc()) {
                $totalBets[$br['animal']] = (float)$br['total'];
            }
        }
    }
    
    $userMoney = 0;
    if (isset($_SESSION['Iduser'])) {
        $u = (int)$_SESSION['Iduser'];
        $resM = $conn->query("SELECT Money FROM users WHERE Iduser = $u");
        if ($resM) $userMoney = (float)$resM->fetch_assoc()['Money'];
    }
    
    // Trả về state chung
    echo json_encode([
        'success' => true,
        'cycle_id' => $cycleId,
        'phase' => $phase,
        'time_left' => $timeLeft,
        'total_bets' => $totalBets,
        'user_money' => $userMoney,
        'rankings' => ($phase !== 'betting') ? $rankings : null
    ]);
    exit;
}

if ($action === 'bot_bet') {
    if ($phase !== 'betting') {
        echo json_encode(['success' => false, 'message' => 'Not betting phase']);
        exit;
    }
    require_once __DIR__ . '/LiveStream/bot_streamer_helper.php';
    $botUser = getOrCreateBotStreamerUser($conn, 'bot_cyber_racing');
    $botId = (int)$botUser['Iduser'];
    
    $bets = json_decode($_POST['bets'] ?? '[]', true);
    $totalBet = 0;
    foreach ($bets as $b) {
        if (in_array($b['animal'], $animalKeys) && $b['amount'] > 0) {
            $totalBet += (float)$b['amount'];
        }
    }
    
    if ($totalBet <= 0) {
        echo json_encode(['success' => false, 'message' => 'Mức cược không hợp lệ!']);
        exit;
    }
    
    $conn->begin_transaction();
    $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
    $stmtLock->bind_param("i", $botId);
    $stmtLock->execute();
    $lockedMoney = (float)($stmtLock->get_result()->fetch_assoc()['Money'] ?? 0);
    $stmtLock->close();
    
    if ($totalBet > $lockedMoney) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Số dư bot không đủ!']);
        exit;
    }
    
    $newMoney = $lockedMoney - $totalBet;
    $conn->query("UPDATE users SET Money = $newMoney WHERE Iduser = $botId");
    
    $stmt = $conn->prepare("INSERT INTO user_bets_cyber_racing (user_id, cycle_id, animal, amount) VALUES (?, ?, ?, ?)");
    foreach ($bets as $b) {
        if (in_array($b['animal'], $animalKeys) && $b['amount'] > 0) {
            $a = $b['animal'];
            $amt = (float)$b['amount'];
            $stmt->bind_param("iisd", $botId, $cycleId, $a, $amt);
            $stmt->execute();
        }
    }
    $stmt->close();
    $conn->commit();
    
    echo json_encode(['success' => true, 'new_money' => $newMoney]);
    exit;
}

if ($action === 'bet') {
    if (!isset($_SESSION['Iduser'])) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập!']);
        exit;
    }
    if ($phase !== 'betting') {
        echo json_encode(['success' => false, 'message' => 'Đã hết thời gian đặt cược! Đang đua...']);
        exit;
    }
    
    $userId = (int)$_SESSION['Iduser'];
    $bets = json_decode($_POST['bets'] ?? '[]', true); // [{animal: 'wolf', amount: 1000}]
    
    $totalBet = 0;
    foreach ($bets as $b) {
        if (in_array($b['animal'], $animalKeys) && $b['amount'] > 0) {
            $totalBet += (float)$b['amount'];
        }
    }
    
    if ($totalBet <= 0) {
        echo json_encode(['success' => false, 'message' => 'Mức cược không hợp lệ!']);
        exit;
    }
    
    $conn->begin_transaction();
    $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
    $stmtLock->bind_param("i", $userId);
    $stmtLock->execute();
    $lockedMoney = $stmtLock->get_result()->fetch_assoc()['Money'] ?? 0;
    $stmtLock->close();
    
    if ($totalBet > $lockedMoney) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Số dư không đủ!']);
        exit;
    }
    
    $conn->query("UPDATE users SET Money = Money - $totalBet WHERE Iduser = $userId");
    
    $stmt = $conn->prepare("INSERT INTO user_bets_cyber_racing (user_id, cycle_id, animal, amount) VALUES (?, ?, ?, ?)");
    foreach ($bets as $b) {
        if (in_array($b['animal'], $animalKeys) && $b['amount'] > 0) {
            $a = $b['animal'];
            $amt = (float)$b['amount'];
            $stmt->bind_param("iisd", $userId, $cycleId, $a, $amt);
            $stmt->execute();
        }
    }
    $stmt->close();
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Đặt cược thành công!']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
