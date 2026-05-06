<?php
session_start();
require_once '../db_connect.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

function logError($message)
{
    $logFile = '../php_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] Blackjack Error: $message" . PHP_EOL, FILE_APPEND);
}

try {
    if (!isset($_SESSION['Iduser'])) {
        echo json_encode(['error' => 'Chưa đăng nhập']);
        exit();
    }

    $userId = $_SESSION['Iduser'];
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'start') {
        $bet = intval($input['bet'] ?? 0);
        if ($bet <= 0)
            throw new Exception("Mức thách đấu không hợp lệ");

        // Check balance
        $sql = "SELECT Money FROM users WHERE Iduser = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $userMoney = $stmt->get_result()->fetch_assoc()['Money'];

        if ($userMoney < $bet) {
            echo json_encode(['error' => 'Ngân khố không đủ để thách đấu']);
            exit();
        }

        // Deduct money
        $conn->begin_transaction();
        $updateSql = "UPDATE users SET Money = Money - ? WHERE Iduser = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ii", $bet, $userId);
        $updateStmt->execute();
        $conn->commit();

        // Initialize Deck (6 decks)
        $deck = [];
        $suits = ['hearts', 'diamonds', 'clubs', 'spades'];
        for ($d = 0; $d < 6; $d++) {
            for ($v = 1; $v <= 13; $v++) {
                foreach ($suits as $s)
                    $deck[] = ['value' => $v, 'suit' => $s];
            }
        }
        shuffle($deck);

        $playerCards = [array_pop($deck), array_pop($deck)];
        $kingCards = [array_pop($deck), array_pop($deck)];

        $_SESSION['bj_game'] = [
            'bet' => $bet,
            'player' => $playerCards,
            'king' => $kingCards,
            'deck' => $deck,
            'status' => 'playing'
        ];

        echo json_encode([
            'success' => true,
            'playerCards' => $playerCards,
            'kingCards' => $kingCards
        ]);

    } elseif ($action === 'hit') {
        if (!isset($_SESSION['bj_game']) || $_SESSION['bj_game']['status'] !== 'playing')
            throw new Exception("Không có ván đấu nào");

        $card = array_pop($_SESSION['bj_game']['deck']);
        $_SESSION['bj_game']['player'][] = $card;

        echo json_encode(['success' => true, 'card' => $card]);

    } elseif ($action === 'double') {
        if (!isset($_SESSION['bj_game']) || $_SESSION['bj_game']['status'] !== 'playing')
            throw new Exception("Không có ván đấu nào");

        $bet = $_SESSION['bj_game']['bet'];

        // Check if enough money for additional bet
        $sql = "SELECT Money FROM users WHERE Iduser = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $userMoney = $stmt->get_result()->fetch_assoc()['Money'];

        if ($userMoney < $bet) {
            echo json_encode(['error' => 'Không đủ Gtlm để gấp đôi']);
            exit();
        }

        // Deduct additional money
        $conn->begin_transaction();
        $updateSql = "UPDATE users SET Money = Money - ? WHERE Iduser = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ii", $bet, $userId);
        $updateStmt->execute();
        $conn->commit();

        $_SESSION['bj_game']['bet'] += $bet;
        $card = array_pop($_SESSION['bj_game']['deck']);
        $_SESSION['bj_game']['player'][] = $card;

        echo json_encode(['success' => true, 'card' => $card]);

    } elseif ($action === 'stand') {
        if (!isset($_SESSION['bj_game']) || $_SESSION['bj_game']['status'] !== 'playing')
            throw new Exception("Không có ván đấu nào");

        $game = &$_SESSION['bj_game'];
        $pScore = calculateBJScore($game['player']);

        // King plays
        while (calculateBJScore($game['king']) < 17) {
            $game['king'][] = array_pop($game['deck']);
        }

        $kScore = calculateBJScore($game['king']);
        $winStatus = 'lose';
        $payout = 0;

        if ($pScore > 21) {
            $winStatus = 'bust';
        } elseif ($kScore > 21 || $pScore > $kScore) {
            $winStatus = ($pScore === 21 && count($game['player']) === 2) ? 'blackjack' : 'win';
            $multiplier = ($winStatus === 'blackjack') ? 2.5 : 2;
            $payout = $game['bet'] * $multiplier;
        } elseif ($pScore === $kScore) {
            $winStatus = 'push';
            $payout = $game['bet'];
        }

        if ($payout > 0) {
            $updateSql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("di", $payout, $userId);
            $updateStmt->execute();
        }

        $sql = "SELECT Money FROM users WHERE Iduser = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $newBalance = $stmt->get_result()->fetch_assoc()['Money'];

        $kingFinalCards = $game['king'];
        unset($_SESSION['bj_game']);

        echo json_encode([
            'success' => true,
            'winStatus' => $winStatus,
            'payout' => $payout,
            'newBalance' => $newBalance,
            'kingFinalCards' => $kingFinalCards
        ]);
    }

} catch (Exception $e) {
    logError($e->getMessage());
    echo json_encode(['error' => 'Lỗi máy chủ hoàng gia: ' . $e->getMessage()]);
}

function calculateBJScore($cards)
{
    $score = 0;
    $aces = 0;
    foreach ($cards as $c) {
        $val = $c['value'];
        if ($val === 1)
            $aces++;
        elseif ($val >= 10)
            $score += 10;
        else
            $score += $val;
    }
    for ($i = 0; $i < $aces; $i++) {
        if ($score + 11 <= 21)
            $score += 11;
        else
            $score += 1;
    }
    return $score;
}
?>