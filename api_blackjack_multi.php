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

// Helper: Lấy hoặc tạo bàn chơi đang chờ
function getTable(mysqli $conn) {
    $stmt = $conn->prepare("SELECT * FROM blackjack_multi_tables WHERE status IN ('waiting', 'playing') ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $table = $stmt->get_result()->fetch_assoc();
    
    if (!$table) {
        $stmt = $conn->prepare("INSERT INTO blackjack_multi_tables (status, dealer_cards) VALUES ('waiting', '[]')");
        $stmt->execute();
        return getTable($conn);
    }
    return $table;
}

// Logic chính cho Blackjack
if ($action === 'get_state') {
    $table = getTable($conn);
    
    $stmt = $conn->prepare("SELECT p.*, u.Name FROM blackjack_multi_players p JOIN users u ON p.user_id = u.Iduser WHERE p.table_id = ? ORDER BY p.seat_index ASC");
    $stmt->bind_param("i", $table['id']);
    $stmt->execute();
    $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $stmt = $conn->prepare("SELECT c.*, u.Name FROM blackjack_multi_chat c JOIN users u ON c.user_id = u.Iduser WHERE c.table_id = ? ORDER BY c.id DESC LIMIT 20");
    $stmt->bind_param("i", $table['id']);
    $stmt->execute();
    $chat = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Tự động chuyển lượt nếu hết thời gian
    if ($table['status'] === 'playing' && $table['turn_expires_at'] && strtotime($table['turn_expires_at']) < time()) {
        processTurn($conn, $table);
    }

    echo json_encode([
        'success' => true,
        'table' => $table,
        'players' => $players,
        'chat' => array_reverse($chat),
        'current_user_id' => $userId
    ]);
    exit;
}

if ($action === 'bet') {
    $amount = (float)$_POST['amount'];
    if ($amount <= 0) { echo json_encode(['success' => false, 'message' => ' Gtlm cược không hợp lệ']); exit; }
    
    $table = getTable($conn);
    if ($table['status'] !== 'waiting') {
        echo json_encode(['success' => false, 'message' => 'Bàn đang trong ván chơi, vui lòng đợi!']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM blackjack_multi_players WHERE table_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $table['id'], $userId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Bạn đã ở trong bàn!']);
        exit;
    }

    $stmt = $conn->prepare("SELECT seat_index FROM blackjack_multi_players WHERE table_id = ?");
    $stmt->bind_param("i", $table['id']);
    $stmt->execute();
    $occupied = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'seat_index');
    
    $mySeat = -1;
    for ($i=0; $i<5; $i++) {
        if (!in_array($i, $occupied)) { $mySeat = $i; break; }
    }

    if ($mySeat === -1) {
        echo json_encode(['success' => false, 'message' => 'Bàn đã đầy!']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Check balance securely
        $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if ($user['Money'] < $amount) throw new Exception("Không đủ  Gtlm!");

        $stmt = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
        $stmt->bind_param("di", $amount, $userId);
        $stmt->execute();

        $initialCard = json_encode([drawCard(), drawCard()]);
        $stmt = $conn->prepare("INSERT INTO blackjack_multi_players (table_id, user_id, seat_index, bet_amount, cards, status) VALUES (?, ?, ?, ?, ?, 'waiting')");
        $stmt->bind_param("iiids", $table['id'], $userId, $mySeat, $amount, $initialCard);
        $stmt->execute();
        
        if (count($occupied) == 0) {
            $startTime = date('Y-m-d H:i:s', strtotime('+15 seconds'));
            $stmt = $conn->prepare("UPDATE blackjack_multi_tables SET status = 'playing', current_turn_user_id = ?, turn_expires_at = ? WHERE id = ?");
            $stmt->bind_param("isi", $userId, $startTime, $table['id']);
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

if ($action === 'hit') {
    $table = getTable($conn);
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
    $table = getTable($conn);
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
    
    $table = getTable($conn);
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
    $stmt = $conn->prepare("SELECT user_id FROM blackjack_multi_players 
                          WHERE table_id = ? AND status = 'waiting' 
                          AND seat_index > (SELECT seat_index FROM blackjack_multi_players WHERE user_id = ? AND table_id = ?)
                          ORDER BY seat_index ASC LIMIT 1");
    $stmt->bind_param("iii", $table['id'], $table['current_turn_user_id'], $table['id']);
    $stmt->execute();
    $next = $stmt->get_result()->fetch_assoc();
    
    if ($next) {
        $nextExpiry = date('Y-m-d H:i:s', strtotime('+15 seconds'));
        $stmt = $conn->prepare("UPDATE blackjack_multi_tables SET current_turn_user_id = ?, turn_expires_at = ? WHERE id = ?");
        $stmt->bind_param("isi", $next['user_id'], $nextExpiry, $table['id']);
        $stmt->execute();
    } else {
        finishGame($conn, $table);
    }
}

function finishGame(mysqli $conn, $table) {
    $dealerCards = [drawCard(), drawCard()];
    while (calculateScore($dealerCards) < 17) { $dealerCards[] = drawCard(); }
    $dealerScore = calculateScore($dealerCards);
    
    $finalDealer = json_encode($dealerCards);
    $stmt = $conn->prepare("UPDATE blackjack_multi_tables SET status = 'calculating', dealer_cards = ? WHERE id = ?");
    $stmt->bind_param("si", $finalDealer, $table['id']);
    $stmt->execute();
    
    $stmt = $conn->prepare("SELECT * FROM blackjack_multi_players WHERE table_id = ?");
    $stmt->bind_param("i", $table['id']);
    $stmt->execute();
    $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($players as $p) {
        $pScore = calculateScore(json_decode($p['cards'], true));
        $winAmount = 0;
        $isWin = false;
        
        if ($pScore <= 21) {
            if ($dealerScore > 21 || $pScore > $dealerScore) {
                $winAmount = $p['bet_amount'] * 2;
                $isWin = true;
            } elseif ($pScore == $dealerScore) {
                $winAmount = $p['bet_amount'];
            }
        }
        
        if ($winAmount > 0) {
            $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            $stmt->bind_param("di", $winAmount, $p['user_id']);
            $stmt->execute();
        }
        
        // Log game history
        logGameHistory($conn, (int)$p['user_id'], 'Blackjack Multiplayer', (float)$p['bet_amount'], (float)$winAmount, $isWin);
    }
    
    $stmt = $conn->prepare("UPDATE blackjack_multi_tables SET status = 'waiting' WHERE id = ?");
    $stmt->bind_param("i", $table['id']);
    $stmt->execute();
    
    $stmt = $conn->prepare("DELETE FROM blackjack_multi_players WHERE table_id = ?");
    $stmt->bind_param("i", $table['id']);
    $stmt->execute();
}
