<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';
require_once 'game_history_helper.php';

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$userId = (int)$_SESSION['Iduser'];
$action = $_GET['action'] ?? '';

$tableId = isset($_GET['table_id']) ? (int)$_GET['table_id'] : (isset($_POST['table_id']) ? (int)$_POST['table_id'] : 0);

if ($tableId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Thiếu table_id']);
    exit;
}

function getTable(mysqli $conn, $tableId) {
    $stmt = $conn->prepare("SELECT * FROM blackjack_multi_tables WHERE id = ?");
    $stmt->bind_param("i", $tableId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Logic chính cho Blackjack
if ($action === 'get_state') {
    $table = getTable($conn, $tableId);
    if (!$table) { echo json_encode(['success' => false, 'message' => 'Phòng không tồn tại']); exit; }
    
    $stmt = $conn->prepare("SELECT p.*, IFNULL(u.Name, CONCAT('Bot_', ABS(p.user_id))) AS Name FROM blackjack_multi_players p LEFT JOIN users u ON p.user_id = u.Iduser WHERE p.table_id = ? ORDER BY p.seat_index ASC");
    $stmt->bind_param("i", $table['id']);
    $stmt->execute();
    $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $stmt = $conn->prepare("SELECT c.*, u.Name FROM blackjack_multi_chat c JOIN users u ON c.user_id = u.Iduser WHERE c.table_id = ? ORDER BY c.id DESC LIMIT 20");
    $stmt->bind_param("i", $table['id']);
    $stmt->execute();
    $chat = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $now = time();

    // 1. Nếu đang chờ và đã đếm ngược xong -> Bắt đầu chơi
    $waitingPlayers = array_filter($players, function($p) { return $p['status'] === 'waiting' || $p['status'] === 'blackjack'; });
    if ($table['status'] === 'waiting' && count($waitingPlayers) > 0) {
        $shouldStart = false;
        if (count($waitingPlayers) == 5) {
            $shouldStart = true;
        } else if ($table['turn_expires_at'] && strtotime($table['turn_expires_at']) < $now) {
            $shouldStart = true;
        }

        if ($shouldStart) {
            // Xóa những người 'sitting' (chưa cược) khỏi bàn khi ván bắt đầu
            $stmt = $conn->prepare("DELETE FROM blackjack_multi_players WHERE table_id = ? AND status = 'sitting'");
            $stmt->bind_param("i", $table['id']);
            $stmt->execute();
            
            $stmt = $conn->prepare("SELECT p.*, IFNULL(u.Name, CONCAT('Bot_', ABS(p.user_id))) AS Name FROM blackjack_multi_players p LEFT JOIN users u ON p.user_id = u.Iduser WHERE p.table_id = ? ORDER BY p.seat_index ASC");
            $stmt->bind_param("i", $table['id']);
            $stmt->execute();
            $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            if (count($players) > 0) {
                // Chia 2 lá bài cho tất cả người chơi
                foreach ($players as &$p) {
                    $initialCards = [drawCard(), drawCard()];
                    $cardsJson = json_encode($initialCards);
                    $stmt = $conn->prepare("UPDATE blackjack_multi_players SET cards = ? WHERE id = ?");
                    $stmt->bind_param("si", $cardsJson, $p['id']);
                    $stmt->execute();
                    $p['cards'] = $cardsJson;
                }

                $firstPlayer = $players[0]['user_id'];
                $nextExpiry = date('Y-m-d H:i:s', $now + 45);
                $stmt = $conn->prepare("UPDATE blackjack_multi_tables SET status = 'playing', current_turn_user_id = ?, turn_expires_at = ? WHERE id = ?");
                $stmt->bind_param("isi", $firstPlayer, $nextExpiry, $table['id']);
                $stmt->execute();
                $table['status'] = 'playing';
                $table['current_turn_user_id'] = $firstPlayer;
                $table['turn_expires_at'] = $nextExpiry;
            }
        }
    }

    // 2. Tự động chuyển lượt nếu hết thời gian
    if ($table['status'] === 'playing' && $table['turn_expires_at'] && strtotime($table['turn_expires_at']) < $now) {
        // Tự động Stand cho người hết hạn
        $stmt = $conn->prepare("UPDATE blackjack_multi_players SET status = 'stand' WHERE table_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $table['id'], $table['current_turn_user_id']);
        $stmt->execute();
        
        processTurn($conn, $table);
    }
    
    // 2.5 Bot tự động đánh sau 2 giây
    if ($table['status'] === 'playing' && $table['turn_expires_at'] && $now >= strtotime($table['turn_expires_at']) - 43) {
        $stmt = $conn->prepare("SELECT is_bot, cards, bet_amount FROM blackjack_multi_players WHERE table_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $table['id'], $table['current_turn_user_id']);
        $stmt->execute();
        $currentPlayer = $stmt->get_result()->fetch_assoc();
        
        if ($currentPlayer && $currentPlayer['is_bot'] == 1) {
            playBotTurnOneStep($conn, $table, $table['current_turn_user_id'], $currentPlayer['cards'], $currentPlayer['bet_amount']);
            // Sau khi bot đánh xong 1 step, cập nhật lại trạng thái bàn nếu cần để fetch trả về
            $table = getTable($conn, $tableId); 
            $stmt = $conn->prepare("SELECT p.*, IFNULL(u.Name, CONCAT('Bot_', ABS(p.user_id))) AS Name FROM blackjack_multi_players p LEFT JOIN users u ON p.user_id = u.Iduser WHERE p.table_id = ? ORDER BY p.seat_index ASC");
            $stmt->bind_param("i", $table['id']);
            $stmt->execute();
            $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    // 3. Reset bàn sau khi hiện kết quả xong
    if ($table['status'] === 'finished' && $table['turn_expires_at'] && strtotime($table['turn_expires_at']) < $now) {
        $minBet = $table['min_bet'];
        
        // Reset người thật về trạng thái sitting
        $stmt = $conn->prepare("UPDATE blackjack_multi_players SET status = 'sitting', cards = '[]', bet_amount = 0 WHERE table_id = ? AND is_bot = 0");
        $stmt->bind_param("i", $table['id']);
        $stmt->execute();
        
        // Reset bot về trạng thái waiting (tự động cược lại ngẫu nhiên)
        $stmt = $conn->prepare("SELECT id FROM blackjack_multi_players WHERE table_id = ? AND is_bot = 1");
        $stmt->bind_param("i", $table['id']);
        $stmt->execute();
        $botsToReset = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $possibleBets = [10000, 20000, 50000, 100000, 200000, 500000, 1000000, 2000000, 5000000];
        $validBets = array_filter($possibleBets, function($b) use ($table) {
            return $b >= $table['min_bet'] && $b <= $table['max_bet'];
        });
        if (empty($validBets)) $validBets = [$table['min_bet']];
        
        foreach ($botsToReset as $bot) {
            $botAmount = $validBets[array_rand($validBets)];
            $stmt = $conn->prepare("UPDATE blackjack_multi_players SET status = 'waiting', cards = '[]', bet_amount = ? WHERE id = ?");
            $stmt->bind_param("di", $botAmount, $bot['id']);
            $stmt->execute();
        }
        
        // Kiểm tra xem có bot không để bắt đầu đếm ngược
        $stmt = $conn->prepare("SELECT COUNT(*) as botCount FROM blackjack_multi_players WHERE table_id = ? AND is_bot = 1");
        $stmt->bind_param("i", $table['id']);
        $stmt->execute();
        $botRes = $stmt->get_result()->fetch_assoc();
        
        $newExpiry = NULL;
        if ($botRes['botCount'] > 0) {
            $newExpiry = date('Y-m-d H:i:s', time() + 45);
        }
        
        $stmt = $conn->prepare("UPDATE blackjack_multi_tables SET status = 'waiting', dealer_cards = '[]', turn_expires_at = ? WHERE id = ?");
        $stmt->bind_param("si", $newExpiry, $table['id']);
        $stmt->execute();
        
        $table['status'] = 'waiting';
        $table['dealer_cards'] = '[]';
        $table['turn_expires_at'] = $newExpiry;
        
        $stmt = $conn->prepare("SELECT p.*, IFNULL(u.Name, CONCAT('Bot_', ABS(p.user_id))) AS Name FROM blackjack_multi_players p LEFT JOIN users u ON p.user_id = u.Iduser WHERE p.table_id = ? ORDER BY p.seat_index ASC");
        $stmt->bind_param("i", $table['id']);
        $stmt->execute();
        $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Lấy số dư hiện tại của user
    $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userRow = $stmt->get_result()->fetch_assoc();
    $currentMoney = $userRow ? (float)$userRow['Money'] : 0;

    echo json_encode([
        'success' => true,
        'table' => $table,
        'players' => $players,
        'chat' => array_reverse($chat),
        'current_user_id' => $userId,
        'current_user_money' => $currentMoney,
        'current_time' => date('Y-m-d H:i:s')
    ]);
    exit;
}

if ($action === 'sit') {
    $table = getTable($conn, $tableId);
    if (!$table) { echo json_encode(['success' => false, 'message' => 'Phòng không tồn tại']); exit; }
    
    if ($table['status'] !== 'waiting') {
        echo json_encode(['success' => false, 'message' => 'Bàn đang chơi, không thể ngồi!']); exit;
    }
    
    $seatIndex = isset($_POST['seat_index']) ? (int)$_POST['seat_index'] : -1;
    if ($seatIndex < 0 || $seatIndex > 4) {
        echo json_encode(['success' => false, 'message' => 'Ghế không hợp lệ!']); exit;
    }

    $stmt = $conn->prepare("SELECT id FROM blackjack_multi_players WHERE table_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $table['id'], $userId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Bạn đã có chỗ rồi!']); exit;
    }

    $stmt = $conn->prepare("SELECT id FROM blackjack_multi_players WHERE table_id = ? AND seat_index = ?");
    $stmt->bind_param("ii", $table['id'], $seatIndex);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Ghế này đã có người!']); exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO blackjack_multi_players (table_id, user_id, seat_index, bet_amount, cards, status) VALUES (?, ?, ?, 0, '[]', 'sitting')");
    $stmt->bind_param("iii", $table['id'], $userId, $seatIndex);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi ngồi vào ghế']);
    }
    exit;
}

if ($action === 'bet') {
    $amount = (float)$_POST['amount'];
    
    $table = getTable($conn, $tableId);
    if (!$table) { echo json_encode(['success' => false, 'message' => 'Phòng không tồn tại']); exit; }
    
    if ($amount < $table['min_bet'] || $amount > $table['max_bet']) { 
        echo json_encode(['success' => false, 'message' => 'Cược phải từ ' . number_format($table['min_bet']) . ' đến ' . number_format($table['max_bet'])]); 
        exit; 
    }
    
    if ($table['status'] !== 'waiting') {
        echo json_encode(['success' => false, 'message' => 'Bàn đang trong ván chơi, vui lòng đợi!']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, status FROM blackjack_multi_players WHERE table_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $table['id'], $userId);
    $stmt->execute();
    $player = $stmt->get_result()->fetch_assoc();
    
    if (!$player) {
        echo json_encode(['success' => false, 'message' => 'Bạn chưa ngồi vào bàn!']);
        exit;
    }
    
    if ($player['status'] !== 'sitting') {
        echo json_encode(['success' => false, 'message' => 'Bạn đã cược rồi!']);
        exit;
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if ($user['Money'] < $amount) throw new Exception("Không đủ GTLM!");

        $stmt = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
        $stmt->bind_param("di", $amount, $userId);
        $stmt->execute();

        $initialCard = '[]';
        $stmt = $conn->prepare("UPDATE blackjack_multi_players SET bet_amount = ?, cards = ?, status = 'waiting' WHERE id = ?");
        $stmt->bind_param("dsi", $amount, $initialCard, $player['id']);
        $stmt->execute();
        
        // Start timer if first person to bet (i.e. currently 0 waiting players)
        $stmt = $conn->prepare("SELECT turn_expires_at FROM blackjack_multi_tables WHERE id = ?");
        $stmt->bind_param("i", $table['id']);
        $stmt->execute();
        $t = $stmt->get_result()->fetch_assoc();
        
        if (empty($t['turn_expires_at'])) {
            $startTime = date('Y-m-d H:i:s', strtotime('+45 seconds'));
            $stmt = $conn->prepare("UPDATE blackjack_multi_tables SET turn_expires_at = ? WHERE id = ?");
            $stmt->bind_param("si", $startTime, $table['id']);
            $stmt->execute();
        }
        
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'add_bot') {
    $table = getTable($conn, $tableId);
    if ($table['status'] !== 'waiting') {
        echo json_encode(['success' => false, 'message' => 'Phòng đang chơi, không thể thêm bot']); exit;
    }
    
    $stmt = $conn->prepare("SELECT seat_index FROM blackjack_multi_players WHERE table_id = ?");
    $stmt->bind_param("i", $table['id']);
    $stmt->execute();
    $occupied = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'seat_index');
    
    $mySeat = -1;
    for ($i=0; $i<5; $i++) {
        if (!in_array($i, $occupied)) { $mySeat = $i; break; }
    }
    if ($mySeat === -1) { echo json_encode(['success' => false, 'message' => 'Bàn đã đầy!']); exit; }
    
    $botId = -rand(1000, 9999);
    
    $possibleBets = [10000, 20000, 50000, 100000, 200000, 500000, 1000000, 2000000, 5000000];
    $validBets = array_filter($possibleBets, function($b) use ($table) {
        return $b >= $table['min_bet'] && $b <= $table['max_bet'];
    });
    if (empty($validBets)) $validBets = [$table['min_bet']];
    $botAmount = $validBets[array_rand($validBets)];
    
    $initialCard = '[]';
    
    $stmt = $conn->prepare("INSERT INTO blackjack_multi_players (table_id, user_id, seat_index, bet_amount, cards, status, is_bot) VALUES (?, ?, ?, ?, ?, 'waiting', 1)");
    $stmt->bind_param("iiids", $table['id'], $botId, $mySeat, $botAmount, $initialCard);
    $stmt->execute();
    
    $stmt = $conn->prepare("SELECT turn_expires_at FROM blackjack_multi_tables WHERE id = ?");
    $stmt->bind_param("i", $table['id']);
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();
    if (empty($t['turn_expires_at'])) {
        $startTime = date('Y-m-d H:i:s', strtotime('+45 seconds'));
        $stmt = $conn->prepare("UPDATE blackjack_multi_tables SET turn_expires_at = ? WHERE id = ?");
        $stmt->bind_param("si", $startTime, $table['id']);
        $stmt->execute();
    }
    
    // Add fake user for the bot just in case JOIN fails if not exists?
    // Wait, the get_state does a JOIN with users table! If bot id is not in users table, it will NOT be returned!
    // We must handle LEFT JOIN or insert dummy user. Let's insert a dummy user if not exists.
    $stmt = $conn->prepare("SELECT Iduser FROM users WHERE Iduser = ?");
    $stmt->bind_param("i", $botId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $botNames = ['Bot_Anna', 'Bot_David', 'Bot_John', 'Bot_Maria', 'Bot_Leo'];
        $bName = $botNames[array_rand($botNames)];
        $stmt2 = $conn->prepare("INSERT INTO users (Iduser, Name, Money) VALUES (?, ?, 0)");
        $stmt2->bind_param("is", $botId, $bName);
        $stmt2->execute();
    }

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'double_down') {
    $table = getTable($conn, $tableId);
    if ($table['current_turn_user_id'] != $userId) { echo json_encode(['success' => false, 'message' => 'Không phải lượt của bạn']); exit; }

    $stmt = $conn->prepare("SELECT * FROM blackjack_multi_players WHERE table_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $table['id'], $userId);
    $stmt->execute();
    $player = $stmt->get_result()->fetch_assoc();
    
    $cards = json_decode($player['cards'], true);
    if (count($cards) != 2) {
        echo json_encode(['success' => false, 'message' => 'Chỉ được nhân đôi khi có đúng 2 lá bài']); exit;
    }
    
    $betAmount = $player['bet_amount'];
    
    // Check balance
    $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    if (!$u || $u['Money'] < $betAmount) {
        echo json_encode(['success' => false, 'message' => 'Không đủ GTLM để x2 GTLM cược!']); exit;
    }
    
    $conn->begin_transaction();
    try {
        // Trừ GTLM
        $stmt = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
        $stmt->bind_param("di", $betAmount, $userId);
        $stmt->execute();
        
        $cards[] = drawCard();
        $score = calculateScore($cards);
        $status = ($score > 21) ? 'bust' : 'stand'; // Buộc dừng sau khi rút
        
        $newCards = json_encode($cards);
        $newBet = $betAmount * 2;
        
        $stmt = $conn->prepare("UPDATE blackjack_multi_players SET cards = ?, status = ?, bet_amount = ? WHERE table_id = ? AND user_id = ?");
        $stmt->bind_param("ssdii", $newCards, $status, $newBet, $table['id'], $userId);
        $stmt->execute();
        
        $conn->commit();
        
        // Tự động chuyển lượt
        $table['current_turn_user_id'] = $userId;
        processTurn($conn, $table);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'hit') {
    $table = getTable($conn, $tableId);
    if ($table['current_turn_user_id'] != $userId) { echo json_encode(['success' => false, 'message' => 'Không phải lượt của bạn']); exit; }

    $stmt = $conn->prepare("SELECT * FROM blackjack_multi_players WHERE table_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $table['id'], $userId);
    $stmt->execute();
    $player = $stmt->get_result()->fetch_assoc();
    
    $cards = json_decode($player['cards'], true);
    $cards[] = drawCard();
    $score = calculateScore($cards);
    
    $status = ($score > 21) ? 'bust' : 'waiting';
    $newCards = json_encode($cards);
    
    $stmt = $conn->prepare("UPDATE blackjack_multi_players SET cards = ?, status = ? WHERE id = ?");
    $stmt->bind_param("ssi", $newCards, $status, $player['id']);
    $stmt->execute();
    
    if ($status === 'bust') processTurn($conn, $table);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'stand') {
    $table = getTable($conn, $tableId);
    if ($table['current_turn_user_id'] != $userId) exit;
    
    $stmt = $conn->prepare("UPDATE blackjack_multi_players SET status = 'stand' WHERE table_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $table['id'], $userId);
    $stmt->execute();
    
    processTurn($conn, $table);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'chat') {
    $msg = trim($_POST['message'] ?? '');
    if (empty($msg)) exit;
    
    $table = getTable($conn, $tableId);
    $stmt = $conn->prepare("INSERT INTO blackjack_multi_chat (table_id, user_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $table['id'], $userId, $msg);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

// Helpers
function drawCard() {
    $suits = ['♠','♣','♥','♦'];
    $values = ['2','3','4','5','6','7','8','9','10','J','Q','K','A'];
    return ['suit' => $suits[array_rand($suits)], 'value' => $values[array_rand($values)]];
}

function calculateScore($cards) {
    $score = 0; $aces = 0;
    foreach ($cards as $c) {
        if (in_array($c['value'], ['J','Q','K'])) $score += 10;
        elseif ($c['value'] === 'A') { $score += 11; $aces++; }
        else $score += (int)$c['value'];
    }
    while ($score > 21 && $aces > 0) { $score -= 10; $aces--; }
    return $score;
}

function processTurn(mysqli $conn, $table) {
    $stmt = $conn->prepare("SELECT user_id, is_bot, cards FROM blackjack_multi_players 
                          WHERE table_id = ? AND status = 'waiting' 
                          AND seat_index > (SELECT seat_index FROM blackjack_multi_players WHERE user_id = ? AND table_id = ?)
                          ORDER BY seat_index ASC LIMIT 1");
    $stmt->bind_param("iii", $table['id'], $table['current_turn_user_id'], $table['id']);
    $stmt->execute();
    $next = $stmt->get_result()->fetch_assoc();
    
    if ($next) {
        $nextExpiry = date('Y-m-d H:i:s', strtotime('+45 seconds'));
        $stmt = $conn->prepare("UPDATE blackjack_multi_tables SET current_turn_user_id = ?, turn_expires_at = ? WHERE id = ?");
        $stmt->bind_param("isi", $next['user_id'], $nextExpiry, $table['id']);
        $stmt->execute();
        
        // Bỏ logic bot đánh ngay lập tức ở đây, đã chuyển sang get_state (polling)
    } else {
        finishGame($conn, $table);
    }
}

function playBotTurnOneStep(mysqli $conn, $table, $botId, $cardsJson, $betAmount) {
    $cards = json_decode($cardsJson, true);
    $score = calculateScore($cards);
    
    // Bot logic đơn giản:
    // Nếu 2 lá và điểm = 10,11 => 50% cơ hội X2 (nếu là bot thì không cần trừ GTLM thật nên thoải mái x2)
    // Nếu < 17 => Hit
    // Nếu >= 17 => Stand
    
    if (count($cards) == 2 && ($score == 10 || $score == 11) && rand(1, 100) <= 50) {
        // Double down
        $cards[] = drawCard();
        $score = calculateScore($cards);
        $status = ($score > 21) ? 'bust' : 'stand';
        $newCards = json_encode($cards);
        $newBet = $betAmount * 2;
        
        $stmt = $conn->prepare("UPDATE blackjack_multi_players SET cards = ?, status = ?, bet_amount = ? WHERE table_id = ? AND user_id = ?");
        $stmt->bind_param("ssdii", $newCards, $status, $newBet, $table['id'], $botId);
        $stmt->execute();
        
        $table['current_turn_user_id'] = $botId;
        processTurn($conn, $table);
    } else if ($score < 17) {
        // Hit
        $cards[] = drawCard();
        $score = calculateScore($cards);
        $status = ($score > 21) ? 'bust' : 'waiting';
        $newCards = json_encode($cards);
        
        $stmt = $conn->prepare("UPDATE blackjack_multi_players SET cards = ?, status = ? WHERE table_id = ? AND user_id = ?");
        $stmt->bind_param("ssii", $newCards, $status, $table['id'], $botId);
        $stmt->execute();
        
        if ($status === 'bust') {
            $table['current_turn_user_id'] = $botId;
            processTurn($conn, $table);
        } else {
            // Reset timer để bot có thêm 2s nghỉ trước khi hit tiếp
            $nextExpiry = date('Y-m-d H:i:s', time() + 45);
            $stmt = $conn->prepare("UPDATE blackjack_multi_tables SET turn_expires_at = ? WHERE id = ?");
            $stmt->bind_param("si", $nextExpiry, $table['id']);
            $stmt->execute();
        }
    } else {
        // Stand
        $stmt = $conn->prepare("UPDATE blackjack_multi_players SET status = 'stand' WHERE table_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $table['id'], $botId);
        $stmt->execute();
        
        $table['current_turn_user_id'] = $botId;
        processTurn($conn, $table);
    }
}

function playBotTurn(mysqli $conn, $table, $botId, $cardsJson) {
    $cards = json_decode($cardsJson, true);
    $score = calculateScore($cards);
    
    if ($score < 17) {
        $cards[] = drawCard();
        $score = calculateScore($cards);
        $status = ($score > 21) ? 'bust' : 'waiting';
        $newCards = json_encode($cards);
        
        $stmt = $conn->prepare("UPDATE blackjack_multi_players SET cards = ?, status = ? WHERE table_id = ? AND user_id = ?");
        $stmt->bind_param("ssii", $newCards, $status, $table['id'], $botId);
        $stmt->execute();
        
        if ($status === 'bust') {
            // Update table object current_turn to botId for processTurn to work
            $table['current_turn_user_id'] = $botId;
            processTurn($conn, $table);
        } else {
            // Bot hits but doesn't bust, let it decide again via a recursive call
            playBotTurn($conn, $table, $botId, $newCards);
        }
    } else {
        $stmt = $conn->prepare("UPDATE blackjack_multi_players SET status = 'stand' WHERE table_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $table['id'], $botId);
        $stmt->execute();
        
        $table['current_turn_user_id'] = $botId;
        processTurn($conn, $table);
    }
}

function finishGame(mysqli $conn, $table) {
    // PVP: Nhà cái chỉ phát bài không chơi
    $finalDealer = '[]';
    // Đặt trạng thái finished, lưu bài dealer và thời gian kết thúc để frontend kịp hiển thị
    $endTime = date('Y-m-d H:i:s', strtotime('+10 seconds'));
    $stmt = $conn->prepare("UPDATE blackjack_multi_tables SET status = 'finished', dealer_cards = ?, turn_expires_at = ? WHERE id = ?");
    $stmt->bind_param("ssi", $finalDealer, $endTime, $table['id']);
    $stmt->execute();
    
    $stmt = $conn->prepare("SELECT * FROM blackjack_multi_players WHERE table_id = ?");
    $stmt->bind_param("i", $table['id']);
    $stmt->execute();
    $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Tính điểm và gom pot
    $totalPot = 0;
    $maxScore = -1;
    $validPlayers = [];
    $allBust = true;
    
    foreach ($players as $p) {
        $totalPot += $p['bet_amount'];
        $pScore = calculateScore(json_decode($p['cards'], true));
        if ($pScore <= 21) {
            $allBust = false;
            if ($pScore > $maxScore) {
                $maxScore = $pScore;
            }
        }
    }
    
    if ($allBust || $maxScore == -1) {
        // Hoàn trả cho tất cả (Hòa)
        foreach ($players as $p) {
            $winAmount = $p['bet_amount'];
            $finalStatus = "draw:" . $winAmount;
            
            $stmt = $conn->prepare("UPDATE blackjack_multi_players SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $finalStatus, $p['id']);
            $stmt->execute();
            
            if ($winAmount > 0 && $p['is_bot'] == 0) {
                $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
                $stmt->bind_param("di", $winAmount, $p['user_id']);
                $stmt->execute();
            }
            if ($p['is_bot'] == 0) {
                logGameHistoryWithAll($conn, (int)$p['user_id'], 'Blackjack Multiplayer', (float)$p['bet_amount'], (float)$winAmount, false);
            }
        }
    } else {
        // Tìm những người có maxScore
        $winners = [];
        foreach ($players as $p) {
            $pScore = calculateScore(json_decode($p['cards'], true));
            if ($pScore == $maxScore) {
                $winners[] = $p;
            }
        }
        
        $winAmountPerPlayer = $totalPot / count($winners);
        
        foreach ($players as $p) {
            $pScore = calculateScore(json_decode($p['cards'], true));
            $winAmount = 0;
            $isWin = false;
            
            if ($pScore == $maxScore) {
                $winAmount = $winAmountPerPlayer;
                $finalStatus = "win:" . $winAmount;
                $isWin = true;
            } else {
                $finalStatus = ($pScore > 21) ? "bust:" . $p['bet_amount'] : "lose:" . $p['bet_amount'];
            }
            
            $stmt = $conn->prepare("UPDATE blackjack_multi_players SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $finalStatus, $p['id']);
            $stmt->execute();
            
            if ($winAmount > 0 && $p['is_bot'] == 0) {
                $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
                $stmt->bind_param("di", $winAmount, $p['user_id']);
                $stmt->execute();
            }
            
            if ($p['is_bot'] == 0) {
                logGameHistoryWithAll($conn, (int)$p['user_id'], 'Blackjack Multiplayer', (float)$p['bet_amount'], (float)$winAmount, $isWin);
            }
        }
    }
}
