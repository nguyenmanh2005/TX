<?php
/**
 * 🎰 Combo Bet Resolution API v1.0
 * Processes atomic high-risk, x5 combo stake resolutions concurrently.
 */
session_start();
require_once 'db_connect.php';
require_once 'game_history_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];
$betAmount = (float)($_POST['bet_amount'] ?? 0);
$crashTarget = (float)($_POST['crash_target'] ?? 1.50);
$sicboChoice = $_POST['sicbo_choice'] ?? 'ac_quy';
$baccaratChoice = $_POST['baccarat_choice'] ?? 'player';

if ($betAmount < 1000) {
    echo json_encode(['success' => false, 'message' => 'Cược tối thiểu 1,000 GTLM.']);
    exit;
}

$conn->begin_transaction();
try {
    // 1. Lock user record to ensure balance security
    $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$userData || $userData['Money'] < $betAmount) {
        throw new Exception("Số dư ví không đủ để đặt cược Combo!");
    }

    // 2. Deduct bet stake
    $stmt = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
    $stmt->bind_param("di", $betAmount, $userId);
    $stmt->execute();
    $stmt->close();

    // 3. Resolve Game 1: Crash Simulation
    $instantCrash = rand(1, 100) <= 5;
    if ($instantCrash) {
        $crashPoint = 1.00;
    } else {
        $e = 100 / (rand(1, 1000000) / 10000);
        $crashPoint = max(1.01, round($e * 0.96, 2));
    }
    $game1Won = ($crashTarget <= $crashPoint);

    // 4. Resolve Game 2: Sicbo Simulation
    $d1 = rand(1, 6);
    $d2 = rand(1, 6);
    $d3 = rand(1, 6);
    $sicboSum = $d1 + $d2 + $d3;

    $game2Won = false;
    if ($sicboChoice === 'ac_quy' && $sicboSum >= 4 && $sicboSum <= 10) {
        $game2Won = true;
    } elseif ($sicboChoice === 'thien_than' && $sicboSum >= 11 && $sicboSum <= 17) {
        $game2Won = true;
    } elseif ($sicboChoice === 'le' && $sicboSum % 2 !== 0) {
        $game2Won = true;
    } elseif ($sicboChoice === 'chan' && $sicboSum % 2 === 0) {
        $game2Won = true;
    }

    // 5. Resolve Game 3: Baccarat Simulation
    $p1 = rand(1, 9);
    $p2 = rand(1, 9);
    $b1 = rand(1, 9);
    $b2 = rand(1, 9);

    $pSum = ($p1 + $p2) % 10;
    $bSum = ($b1 + $b2) % 10;

    // Draw third cards standard
    if ($pSum < 6) {
        $p3 = rand(1, 9);
        $pSum = ($pSum + $p3) % 10;
    }
    if ($bSum < 6) {
        $b3 = rand(1, 9);
        $bSum = ($bSum + $b3) % 10;
    }

    $baccaratWinner = 'tie';
    if ($pSum > $bSum) {
        $baccaratWinner = 'player';
    } elseif ($bSum > $pSum) {
        $baccaratWinner = 'banker';
    }

    $game3Won = ($baccaratChoice === $baccaratWinner);

    // 6. Final Combo Resolution Check
    $allWon = ($game1Won && $game2Won && $game3Won);
    $payout = 0.0;

    if ($allWon) {
        // Multipliers math
        $crashMult = $crashTarget;
        $sicboMult = 2.00;
        $baccaratMult = ($baccaratChoice === 'tie') ? 9.00 : (($baccaratChoice === 'banker') ? 1.95 : 2.00);

        // Sweeping combo x5 bonus multiplier
        $payout = round($betAmount * $crashMult * $sicboMult * $baccaratMult * 5.00);

        // Award payout to user balance
        $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
        $stmt->bind_param("di", $payout, $userId);
        $stmt->execute();
        $stmt->close();

        // Log transaction
        $stmt = $conn->prepare("INSERT INTO bot_transactions (user_id, amount, type, reason) VALUES (?, ?, 'receive', 'TRIPLE SWEEP COMBO BET WIN!')");
        $stmt->bind_param("id", $userId, $payout);
        $stmt->execute();
        $stmt->close();
    }

    // 7. Track combo history
    $status = $allWon ? 'win' : 'lose';
    $stmt = $conn->prepare("INSERT INTO combo_bets_history 
        (user_id, bet_amount, game1_key, game1_bet_choice, game1_won, game2_key, game2_bet_choice, game2_won, game3_key, game3_bet_choice, game3_won, payout, status)
        VALUES (?, ?, 'crash', ?, ?, 'sicbo', ?, ?, 'baccarat', ?, ?, ?, ?)");
    $choice1 = "x" . $crashTarget;
    $g1w = $game1Won ? 1 : 0;
    $g2w = $game2Won ? 1 : 0;
    $g3w = $game3Won ? 1 : 0;
    $stmt->bind_param("idsisiisids", $userId, $betAmount, $choice1, $g1w, $sicboChoice, $g2w, $baccaratChoice, $g3w, $payout, $status);
    $stmt->execute();
    $stmt->close();

    // 8. Trigger Universal Achievements & Logs
    logGameHistoryWithAll($conn, $userId, 'Combo Bet', $betAmount, $payout, $allWon);

    // 9. Hook updateEventMissionProgress cho từng game thành phần trong Combo Bet
    updateEventMissionProgress($conn, $userId, 'Crash', $betAmount, $game1Won ? $payout : 0, $game1Won);
    updateEventMissionProgress($conn, $userId, 'Sicbo', $betAmount, $game2Won ? $payout : 0, $game2Won);
    updateEventMissionProgress($conn, $userId, 'Baccarat', $betAmount, $game3Won ? $payout : 0, $game3Won);

    $conn->commit();

    // Fetch updated balance
    $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $newMoney = $stmt->get_result()->fetch_assoc()['Money'];
    $stmt->close();

    echo json_encode([
        'success' => true,
        'all_won' => $allWon,
        'crash_point' => $crashPoint,
        'game1_won' => $game1Won,
        'sicbo_dice' => [$d1, $d2, $d3],
        'sicbo_sum' => $sicboSum,
        'game2_won' => $game2Won,
        'baccarat_player' => $pSum,
        'baccarat_banker' => $bSum,
        'game3_won' => $game3Won,
        'payout' => $payout,
        'payout_formatted' => number_format($payout),
        'new_money' => number_format($newMoney)
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
