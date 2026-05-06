<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require '../db_connect.php';


// AJAX history endpoint
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_history') {
    header('Content-Type: application/json; charset=utf-8');
    
    $id = $_SESSION['Iduser'] ?? 0;
    $sql = "SELECT * FROM history_poker WHERE Iduser = ? ORDER BY Time DESC LIMIT 20";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'history' => $history
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once '../load_theme.php';

$userId = $_SESSION['Iduser'];
$sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();


// Get statistics from database for chart
$gameThang = 0;
$gameThua = 0;
$sqlStats = "SELECT COUNT(*) as total, SUM(CASE WHEN WinAmount > 0 THEN 1 ELSE 0 END) as wins FROM history_poker WHERE Iduser = ?";
$stmtStats = $conn->prepare($sqlStats);
$stmtStats->bind_param("i", $userId);
$stmtStats->execute();
$resultStats = $stmtStats->get_result();
if ($rowStats = $resultStats->fetch_assoc()) {
    $gameThang = $rowStats['wins'] ?? 0;
    $gameThua = ($rowStats['total'] ?? 0) - $gameThang;
}
$stmtStats->close();


$soDu = $user['Money'];
$tenNguoiChoi = $user['Name'];

// Khởi tạo gtlm Bot hiển thị ban đầu (Tầm vài triệu - tỉ phú)
$initBotMoney = [1 => 0, 2 => 0, 3 => 0];
if (isset($_SESSION['texas_holdem'])) {
    $gameS = $_SESSION['texas_holdem'];
    for ($i = 1; $i <= 3; $i++) {
        $m = $gameS["bot{$i}_money"] ?? 0;
        if ($m < 1000000)
            $m = rand(10, 1000) * 1000000; // Reset nếu quá nghèo
        $initBotMoney[$i] = $m;
        $_SESSION['texas_holdem']["bot{$i}_money"] = $m; // Cập nhật lại session luôn
    }
} else {
    $initBotMoney = [
        1 => rand(10, 1000) * 1000000,
        2 => rand(10, 1000) * 1000000,
        3 => rand(10, 1000) * 1000000
    ];
}

// --- TEXAS HOLD'EM ENGINE ---

function evaluateHand7($cards)
{
    if (count($cards) < 5)
        return [0, 0, "N/A"];
    $suits = ['♠', '♥', '♦', '♣'];
    $vals = ["2" => 2, "3" => 3, "4" => 4, "5" => 5, "6" => 6, "7" => 7, "8" => 8, "9" => 9, "10" => 10, "J" => 11, "Q" => 12, "K" => 13, "A" => 14];

    $pCards = [];
    foreach ($cards as $c) {
        $v = mb_substr($c, 0, -1, 'UTF-8');
        $s = mb_substr($c, -1, 1, 'UTF-8');
        if (!isset($vals[$v]))
            continue;
        $pCards[] = ['v' => $vals[$v], 's' => $s, 'orig' => $c];
    }
    usort($pCards, function ($a, $b) {
        return $b['v'] - $a['v'];
    });

    // Flush check
    $flushSuit = null;
    $countsS = [];
    foreach ($pCards as $c) {
        $countsS[$c['s']] = ($countsS[$c['s']] ?? 0) + 1;
    }
    foreach ($countsS as $s => $count) {
        if ($count >= 5)
            $flushSuit = $s;
    }

    if ($flushSuit) {
        $fCards = array_filter($pCards, function ($c) use ($flushSuit) {
            return $c['s'] === $flushSuit;
        });
        $straight = checkStraight($fCards);
        if ($straight) {
            if ($straight['high'] == 14)
                return [10, 1000, "Royal Flush"];
            return [9, 900 + $straight['high'], "Straight Flush"];
        }
        return [6, 600 + $fCards[0]['v'], "Flush"];
    }

    $countsV = [];
    foreach ($pCards as $c) {
        $countsV[$c['v']] = ($countsV[$c['v']] ?? 0) + 1;
    }
    arsort($countsV);

    $keys = array_keys($countsV);
    $max = reset($countsV);
    $val = key($countsV);

    if ($max === 4)
        return [8, 800 + $val, "Four of a Kind"];
    if ($max === 3) {
        if (isset($keys[1]) && $countsV[$keys[1]] >= 2)
            return [7, 700 + $val, "Full House"];
        $st = checkStraight($pCards);
        if ($st)
            return [5, 500 + $st['high'], "Straight"];
        return [4, 400 + $val, "Three of a Kind"];
    }
    if ($max === 2) {
        if (isset($keys[1]) && $countsV[$keys[1]] >= 2)
            return [3, 300 + $val, "Two Pair"];
        $st = checkStraight($pCards);
        if ($st)
            return [5, 500 + $st['high'], "Straight"];
        return [2, 200 + $val, "One Pair"];
    }
    $st = checkStraight($pCards);
    if ($st)
        return [5, 500 + $st['high'], "Straight"];
    return [1, 100 + $pCards[0]['v'], "High Card"];
}

function checkStraight($cards)
{
    if (count($cards) < 5)
        return null;
    $vals = array_unique(array_column($cards, 'v'));
    sort($vals);
    if (in_array(14, $vals) && in_array(2, $vals) && in_array(3, $vals) && in_array(4, $vals) && in_array(5, $vals))
        return ['high' => 5];
    $consecutive = 1;
    $maxHigh = 0;
    for ($i = 0; $i < count($vals) - 1; $i++) {
        if ($vals[$i + 1] == $vals[$i] + 1) {
            $consecutive++;
            if ($consecutive >= 5)
                $maxHigh = $vals[$i + 1];
        } else {
            $consecutive = 1;
        }
    }
    return $maxHigh ? ['high' => $maxHigh] : null;
}

// --- AJAX HANDLER ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false, 'message' => ''];

    if ($action === 'start_game') {
        $bet = (int) ($_POST['bet'] ?? 10000);
        if ($bet <= 0 || $bet > $soDu) {
            $response['message'] = "⚠️ Số Gtlm không đủ!";
        } else {
            $suits = ['♠', '♥', '♦', '♣'];
            $faces = ["A", "2", "3", "4", "5", "6", "7", "8", "9", "10", "J", "Q", "K"];
            $deck = [];
            foreach ($suits as $s)
                foreach ($faces as $f)
                    $deck[] = $f . $s;
            shuffle($deck);

            // Khởi tạo gtlm Bot khủng (Vài triệu -> 1 tỉ)
            $botMoney = [
                1 => rand(5, 1000) * 1000000,
                2 => rand(5, 1000) * 1000000,
                3 => rand(5, 1000) * 1000000
            ];

            $gameState = [
                'deck' => $deck,
                'player_money' => $soDu - $bet,
                'bet_per_player' => $bet,
                'community' => [],
                'player_hand' => array_splice($deck, 0, 2),
                'bot1_hand' => array_splice($deck, 0, 2),
                'bot2_hand' => array_splice($deck, 0, 2),
                'bot3_hand' => array_splice($deck, 0, 2),
                'bot1_money' => $botMoney[1] - $bet,
                'bot2_money' => $botMoney[2] - $bet,
                'bot3_money' => $botMoney[3] - $bet,
                'bot1_folded' => false,
                'bot2_folded' => false,
                'bot3_folded' => false,
                'pot' => $bet * 4,
                'state' => 'pre-flop',
                'folded_players' => [],
                'win_amount' => 0
            ];
            $_SESSION['texas_holdem'] = $gameState;
            $newMoney = $soDu - $bet;
            $conn->query("UPDATE users SET Money = $newMoney WHERE Iduser = $userId");
        
        // Insert vào history_poker table
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['Iduser'])) {
            $userId = $_SESSION['Iduser'];
            $betAmount = (int)($_POST['bet'] ?? 0);
            $resultStr = $_POST['result'] ?? 'Unknown';
            $winAmount = (int)($reward ?? 0);
            
            $historyStmt = $conn->prepare("INSERT INTO history_poker (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
            if ($historyStmt) {
                $historyStmt->bind_param("iisi", $userId, $betAmount, $resultStr, $winAmount);
                $historyStmt->execute();
                $historyStmt->close();
            }
        }
            $response = [
                'success' => true,
                'state' => 'pre-flop',
                'playerHand' => $gameState['player_hand'],
                'pot' => number_format($gameState['pot']) . ' gtlm',
                'balance' => number_format($newMoney) . ' gtlm',
                'bots' => [
                    1 => ['money' => number_format($gameState['bot1_money']), 'folded' => false],
                    2 => ['money' => number_format($gameState['bot2_money']), 'folded' => false],
                    3 => ['money' => number_format($gameState['bot3_money']), 'folded' => false],
                ],
                'message' => 'Vòng Pre-flop: Hãy xem bài riêng của bạn.'
            ];
        }
    } elseif ($action === 'play_action') {
        if (!isset($_SESSION['texas_holdem']))
            exit;
        $game = &$_SESSION['texas_holdem'];
        $userAction = $_POST['type'] ?? 'call';

        if ($userAction === 'fold') {
            $game['folded_players'][] = 0;
            $game['state'] = 'showdown';
            $response = [
                'success' => true,
                'state' => 'showdown',
                'folded' => true,
                'message' => '🏳️ Bạn đã bỏ bài.',
                'allHands' => ['player' => $game['player_hand'], 'bot1' => $game['bot1_hand'], 'bot2' => $game['bot2_hand'], 'bot3' => $game['bot3_hand']]
            ];
            echo json_encode($response);
            exit;
        }

        // Logic của Bot sau mỗi lượt
        $botDecisions = [];
        for ($i = 1; $i <= 3; $i++) {
            if ($game["bot{$i}_folded"])
                continue;

            $hand = array_merge($game["bot{$i}_hand"], $game['community']);
            $res = evaluateHand7($hand);
            $strength = $res[0]; // 1-10

            $shouldFold = false;
            // Bot fold logic
            if ($game['state'] === 'pre-flop') {
                // Pre-flop: Fold nếu bài quá yếu (High card thấp)
                if ($strength == 1 && $res[1] < 110) { // Dưới J High
                    if (rand(1, 100) < 30)
                        $shouldFold = true;
                }
            } else {
                // Các vòng sau: Fold nếu vẫn là High card hoặc bài quá tệ so với Pot
                if ($strength == 1) {
                    if (rand(1, 100) < 50)
                        $shouldFold = true;
                }
            }

            if ($shouldFold) {
                $game["bot{$i}_folded"] = true;
                $game['folded_players'][] = $i;
                $botDecisions[$i] = 'fold';
            } else {
                // Call: Trừ thêm gtlm nếu có vòng tố (ở đây đơn giản là call gtlm cược hiện tại)
                // Trong bản này ta mặc định là Call thêm 0 vì đã thu từ đầu, 
                // hoặc có thể cộng thêm vào pot nếu muốn cược thêm.
                $botDecisions[$i] = 'call';
            }
        }

        switch ($game['state']) {
            case 'pre-flop':
                $game['community'] = array_splice($game['deck'], 0, 3);
                $game['state'] = 'flop';
                $msg = "Flop: 3 lá bài chung đã lật!";
                break;
            case 'flop':
                $game['community'][] = array_splice($game['deck'], 0, 1)[0];
                $game['state'] = 'turn';
                $msg = "Turn: Lá bài thứ 4 xuất hiện!";
                break;
            case 'turn':
                $game['community'][] = array_splice($game['deck'], 0, 1)[0];
                $game['state'] = 'river';
                $msg = "River: Lá bài cuối cùng đã lật!";
                break;
            case 'river':
                $results = [];
                for ($i = 0; $i < 4; $i++) {
                    if (in_array($i, $game['folded_players']))
                        continue;
                    $key = ($i === 0) ? 'player_hand' : "bot{$i}_hand";
                    $res = evaluateHand7(array_merge($game[$key], $game['community']));
                    $results[] = ['id' => $i, 'res' => $res, 'name' => ($i === 0 ? 'Bạn' : "Bot $i")];
                }

                if (empty($results)) {
                    // Trường hợp hy hữu mọi người cùng fold
                    $winner = ['id' => 0, 'name' => 'Bạn', 'res' => [0, 0, 'Mọi người đã bỏ bài']];
                } else {
                    usort($results, function ($a, $b) {
                        if ($a['res'][0] != $b['res'][0])
                            return $b['res'][0] - $a['res'][0];
                        return $b['res'][1] - $a['res'][1];
                    });
                    $winner = $results[0];
                }

                $game['state'] = 'showdown';
                $isWin = ($winner['id'] === 0);
                if ($isWin) {
                    $winAmount = $game['pot'];
                    $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $userId");
                    $msg = "🏆 CHIẾN THẮNG: " . number_format($winAmount) . " gtlm (" . $winner['res'][2] . ")";
                } else {
                    $msg = "💀 THẤT BẠI: " . $winner['name'] . " thắng với " . $winner['res'][2];
                }

                $response = [
                    'success' => true,
                    'state' => 'showdown',
                    'winner' => $winner,
                    'allHands' => [
                        'player' => $game['player_hand'],
                        'bot1' => $game['bot1_hand'],
                        'bot2' => $game['bot2_hand'],
                        'bot3' => $game['bot3_hand']
                    ],
                    'message' => $msg,
                    'isWin' => $isWin,
                    'newBalance' => number_format($isWin ? ($game['player_money'] + $game['pot']) : $game['player_money']) . ' gtlm',
                    'bots' => [
                        1 => ['money' => number_format($game['bot1_money']), 'folded' => $game['bot1_folded']],
                        2 => ['money' => number_format($game['bot2_money']), 'folded' => $game['bot2_folded']],
                        3 => ['money' => number_format($game['bot3_money']), 'folded' => $game['bot3_folded']],
                    ]
                ];
                echo json_encode($response);
                exit;
        }

        $response = [
            'success' => true,
            'state' => $game['state'],
            'community' => $game['community'],
            'message' => $msg,
            'bots' => [
                1 => ['money' => number_format($game['bot1_money']), 'folded' => $game['bot1_folded']],
                2 => ['money' => number_format($game['bot2_money']), 'folded' => $game['bot2_folded']],
                3 => ['money' => number_format($game['bot3_money']), 'folded' => $game['bot3_folded']],
            ]
        ];
    }
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Texas Hold'em - Premium UI</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/canvas-confetti/1.6.0/confetti.browser.min.js"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <style>
        body {
            margin: 0;
            cursor: url('../img/chuot.png'), auto !important;
            font-family: 'Poppins', sans-serif;
            background: #072a1a;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            overflow: hidden;
        }

        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        .poker-table {
            background:
                radial-gradient(circle at center, rgba(26, 116, 71, 0.9) 0%, rgba(7, 42, 26, 0.95) 80%),
                url('https://www.transparenttextures.com/patterns/felt.png');
            width: 1050px;
            height: 600px;
            border: 12px solid #3d2b1f;
            border-radius: 300px;
            position: relative;
            box-shadow:
                0 30px 100px rgba(0, 0, 0, 0.8),
                inset 0 0 120px rgba(0, 0, 0, 0.6),
                0 0 40px rgba(255, 215, 0, 0.1);
            backdrop-filter: blur(10px);
            border-style: double;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .poker-table::after {
            content: '';
            position: absolute;
            inset: 35px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 230px;
            pointer-events: none;
        }

        .seat {
            position: absolute;
            width: 160px;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 10;
        }

        .seat-top {
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
        }

        .seat-left {
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
        }

        .seat-right {
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }

        .seat-bottom {
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            flex-direction: column-reverse;
        }

        .seat-bottom .player-info {
            margin-top: 0;
            margin-bottom: 10px;
        }

        .player-info {
            background: rgba(0, 0, 0, 0.85);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            margin-top: 5px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
            min-width: 120px;
            text-align: center;
            backdrop-filter: blur(10px);
            line-height: 1.2;
        }

        .player-info.active {
            border-color: #ffd700;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.4);
        }

        .hand {
            display: flex;
            justify-content: center;
            gap: -20px;
        }

        .card {
            width: 80px;
            height: 110px;
            background: #ffffff;
            border-radius: 12px;
            color: #1e272e;
            font-weight: 900;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            font-size: 24px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.4), inset 0 0 10px rgba(0, 0, 0, 0.05);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            border: 1px solid #ffffff;
            padding: 10px;
            user-select: none;
        }

        .card.hidden {
            background: linear-gradient(135deg, #2c3e50 0%, #000000 100%);
            color: transparent;
            border: 2px solid #ffd700;
        }

        .card.hidden::after {
            content: 'GTLM';
            position: absolute;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.2);
            font-weight: 800;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
        }

        .card .suit {
            font-size: 42px;
            line-height: 1;
            filter: drop-shadow(0 2px 2px rgba(0, 0, 0, 0.1));
        }

        .card.red {
            color: #eb4d4b;
        }

        .card.hidden {
            background: linear-gradient(135deg, #1e272e 0%, #000 100%);
            color: transparent;
            border: 2px solid #3d2b1f;
        }

        .card.hidden::after {
            content: '♠';
            position: absolute;
            font-size: 40px;
            color: rgba(255, 255, 255, 0.05);
        }

        .community-area {
            position: absolute;
            top: 55%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            gap: 15px;
            padding: 15px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 15px;
            min-height: 135px;
            width: 500px;
            justify-content: center;
            align-items: center;
            border: 1px dashed rgba(255, 255, 255, 0.1);
        }

        .pot-container {
            position: absolute;
            top: 38%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 5;
        }

        .pot-val {
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(10px);
            padding: 5px 20px;
            border-radius: 20px;
            border: 1px solid rgba(255, 215, 0, 0.4);
            color: #ffd700;
            font-weight: 700;
            font-size: 18px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            margin-bottom: 5px;
        }

        .chip-icon {
            width: 45px;
            height: 45px;
            background: radial-gradient(circle at 30% 30%, #e74c3c, #c0392b);
            border: 4px dashed rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            margin-bottom: 8px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.4);
            animation: rotateChip 10s linear infinite;
        }

        @keyframes rotateChip {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .controls {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            z-index: 2000;
            padding: 20px;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6);
            min-width: 200px;
        }

        .bet-input {
            width: 100% !important;
            margin-right: 0 !important;
            margin-bottom: 8px;
            text-align: center;
            font-size: 16px;
        }

        .btn-poker {
            width: 100%;
            justify-content: center;
            padding: 12px 20px;
        }

        .btn-poker {
            padding: 10px 30px;
            border-radius: 30px;
            border: none;
            font-weight: 700;
            cursor: url('../img/tay.png'), pointer !important;
            transition: all 0.3s ease;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-action {
            background: linear-gradient(135deg, #f1c40f 0%, #f39c12 100%);
            color: #333;
        }

        .btn-next {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
        }

        .btn-fold {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .btn-poker:hover {
            transform: translateY(-5px);
            filter: brightness(1.2);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.4);
        }

        .status-box {
            position: fixed;
            top: 15px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.7) 0%, rgba(0, 0, 0, 0.4) 100%);
            backdrop-filter: blur(15px);
            padding: 10px 40px;
            border-radius: 40px;
            font-size: 15px;
            font-weight: 600;
            border: 1px solid rgba(255, 215, 0, 0.3);
            color: #ffd700;
            z-index: 2000;
            white-space: nowrap;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            letter-spacing: 0.5px;
        }

        .home-fab {
            display: none !important;
        }

        .thinking {
            font-size: 12px;
            color: #aaa;
            font-style: italic;
            animation: blink 1s infinite;
        }

        .bot-money {
            color: #ffd700;
            font-size: 12px;
            font-weight: 600;
            margin-top: 3px;
        }

        .status-badge {
            position: absolute;
            top: -20px;
            background: #e74c3c;
            color: white;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
            display: none;
            z-index: 20;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .seat.folded {
            opacity: 0.5;
            filter: grayscale(0.8);
        }

        .seat.folded .status-badge {
            display: block;
        }

        @keyframes blink {

            0%,
            100% {
                opacity: 0;
            }

            50% {
                opacity: 1;
            }
        }

        @keyframes deal {
            from {
                transform: translateY(-300px) rotate(180deg);
                opacity: 0;
            }

            to {
                transform: translateY(0) rotate(0);
                opacity: 1;
            }
        }

        .card-deal {
            animation: deal 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) backwards;
        }
    
        /* History Box Styles */
        .bottom-section {
            margin-top: 50px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
        }

        .history-box, .chart-box {
            background: rgba(0, 121, 107, 0.9);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            color: white;
        }

        .history-box h3, .chart-box h3 {
            margin-top: 0;
            font-size: 20px;
            color: #ffd700;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .history-box table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .history-box table tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideIn 0.5s ease-out forwards;
        }

        .history-box table td, .history-box table th {
            padding: 10px;
            text-align: center;
        }

        .history-box table th {
            background: rgba(255, 255, 255, 0.1);
            font-weight: 700;
            color: #ffd700;
        }

        .history-box table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        @media (max-width: 768px) {
            .bottom-section {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

    
        /* Statistics Container */
        .stats-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .stat-item.wins {
            border-left: 4px solid #4ade80;
        }
        
        .stat-item.losses {
            border-left: 4px solid #ff6b6b;
        }
        
        .stat-item .label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        
        .stat-item .value {
            font-size: 28px;
            font-weight: 700;
            color: #ffd700;
        }
        
        .chart-box {
            display: flex;
            flex-direction: column;
        }
        
        .chart-box canvas {
            margin-top: 20px;
        }

    </style>
</head>

<body>

    <div class="status-box" id="status-box">Chào mừng đến với Poker Texas Hold'em!</div>
    <div class="poker-table">
        <div class="pot-container">
            <div class="chip-icon"></div>
            <div class="pot-val" id="pot-val">Pot: 0</div>
        </div>
        <!-- Players -->
        <div class="seat seat-left" id="seat-bot1">
            <div class="player-info" id="bot1-info">
                <b>Bot 1</b>
                <div class="bot-money" id="bot1-money"><?= number_format($initBotMoney[1]) ?> gtlm</div>
            </div>
            <div class="status-badge">ĐÃ BỎ BÀI</div>
            <div class="hand" id="bot1-hand"></div>
        </div>
        <div class="seat seat-top" id="seat-bot2">
            <div class="player-info" id="bot2-info">
                <b>Bot 2</b>
                <div class="bot-money" id="bot2-money"><?= number_format($initBotMoney[2]) ?> gtlm</div>
            </div>
            <div class="status-badge">ĐÃ BỎ BÀI</div>
            <div class="hand" id="bot2-hand"></div>
        </div>
        <div class="seat seat-right" id="seat-bot3">
            <div class="player-info" id="bot3-info">
                <b>Bot 3</b>
                <div class="bot-money" id="bot3-money"><?= number_format($initBotMoney[3]) ?> gtlm</div>
            </div>
            <div class="status-badge">ĐÃ BỎ BÀI</div>
            <div class="hand" id="bot3-hand"></div>
        </div>
        <div class="community-area">
            <div id="community-area" style="display: flex; gap: 15px;">
                <!-- Card Slots -->
                <div style="width:80px; height:110px; border:2px dashed rgba(255,255,255,0.1); border-radius:12px;">
                </div>
                <div style="width:80px; height:110px; border:2px dashed rgba(255,255,255,0.1); border-radius:12px;">
                </div>
                <div style="width:80px; height:110px; border:2px dashed rgba(255,255,255,0.1); border-radius:12px;">
                </div>
                <div style="width:80px; height:110px; border:2px dashed rgba(255,255,255,0.1); border-radius:12px;">
                </div>
                <div style="width:80px; height:110px; border:2px dashed rgba(255,255,255,0.1); border-radius:12px;">
                </div>
            </div>
        </div>
        <div class="seat seat-bottom">
            <div class="hand" id="player-hand"></div>
            <div class="player-info">
                <b><?= htmlspecialchars($tenNguoiChoi) ?></b><br>
                <span id="balance-val" style="color: #ffd700;"><?= number_format($soDu) ?> gtlm</span>
            </div>
        </div>
    </div>
    <div class="controls">
        <div id="start-controls">
            <input type="number" id="bet-amount" value="10000" class="bet-input">
            <button id="btn-start" class="btn-poker btn-premium">🃏 Chơi Ngay</button>
        </div>
        <div id="play-controls" style="display: none;">
            <button id="btn-call" class="btn-poker btn-next">✅ Theo (Call/Check)</button>
            <button id="btn-fold" class="btn-poker btn-fold">🏳️ Bỏ bài (Fold)</button>
        </div>
        <a href="../index.php" class="btn-poker btn-secondary">🏠 Trang chủ</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function () {
            window.themeConfig = { particleCount: <?= $particleCount ?>, particleSize: <?= $particleSize ?>, particleColor: '<?= $particleColor ?>', particleOpacity: <?= $particleOpacity ?>, shapeCount: <?= $shapeCount ?>, shapeColors: <?= json_encode($shapeColors) ?>, shapeOpacity: <?= $shapeOpacity ?>, bgGradient: <?= json_encode($bgGradient) ?> };
            const script = document.createElement('script'); script.src = '../threejs-background.js'; document.head.appendChild(script);
        })();

        document.addEventListener('DOMContentLoaded', function () {
            const btnStart = document.getElementById('btn-start'), btnCall = document.getElementById('btn-call'), btnFold = document.getElementById('btn-fold'), statusBox = document.getElementById('status-box'), potVal = document.getElementById('pot-val'), balanceVal = document.getElementById('balance-val'), betInput = document.getElementById('bet-amount'), pHand = document.getElementById('player-hand'), b1Hand = document.getElementById('bot1-hand'), b2Hand = document.getElementById('bot2-hand'), b3Hand = document.getElementById('bot3-hand'), commArea = document.getElementById('community-area');

            function renderCard(card, isHidden = false, delay = 0) {
                const el = document.createElement('div');
                el.className = 'card' + (isHidden ? ' hidden' : (card.includes('♥') || card.includes('♦') ? ' red' : '')) + ' card-deal';
                el.style.animationDelay = delay + 's';
                if (!isHidden) el.innerHTML = `<span>${card.slice(0, -1)}</span><span class="suit">${card.slice(-1)}</span><span style="transform: rotate(180deg)">${card.slice(0, -1)}</span>`;
                return el;
            }

            btnStart.addEventListener('click', async () => {
                const bet = betInput.value; const fd = new FormData(); fd.append('bet', bet);
                const res = await fetch('poker.php?action=start_game', { method: 'POST', body: fd }); const data = await res.json();
                if (data.success) {
                    pHand.innerHTML = ''; b1Hand.innerHTML = ''; b2Hand.innerHTML = ''; b3Hand.innerHTML = ''; commArea.innerHTML = '';
                    document.querySelectorAll('.seat').forEach(s => s.classList.remove('folded'));

                    data.playerHand.forEach((c, i) => pHand.appendChild(renderCard(c, false, i * 0.15)));
                    for (let i = 0; i < 2; i++) { b1Hand.appendChild(renderCard(null, true, 0.3 + i * 0.15)); b2Hand.appendChild(renderCard(null, true, 0.6 + i * 0.15)); b3Hand.appendChild(renderCard(null, true, 0.9 + i * 0.15)); }

                    if (data.bots) {
                        for (let i = 1; i <= 3; i++) {
                            document.getElementById(`bot${i}-money`).textContent = data.bots[i].money + ' gtlm';
                        }
                    }

                    potVal.textContent = 'Pot: ' + data.pot; balanceVal.textContent = data.balance + ' gtlm'; statusBox.textContent = data.message;
                    document.getElementById('start-controls').style.display = 'none'; document.getElementById('play-controls').style.display = 'flex';
                } else Swal.fire('Lỗi', data.message, 'error');
            });

            btnCall.addEventListener('click', async () => {
                btnCall.disabled = true; statusBox.textContent = 'Bots đang suy nghĩ...';
                setTimeout(async () => {
                    const fd = new FormData(); fd.append('type', 'call');
                    const res = await fetch('poker.php?action=play_action', { method: 'POST', body: fd }); const data = await res.json();
                    btnCall.disabled = false; handleResponse(data);
                }, 800);
            });

            btnFold.addEventListener('click', async () => {
                const fd = new FormData(); fd.append('type', 'fold');
                const res = await fetch('poker.php?action=play_action', { method: 'POST', body: fd }); const data = await res.json();
                handleResponse(data);
            });

            function handleResponse(data) {
                if (data.success) {
                    statusBox.textContent = data.message;
                    if (data.community) {
                        commArea.innerHTML = '';
                        data.community.forEach((c, i) => commArea.appendChild(renderCard(c, false, i * 0.1)));
                    }
                    if (data.bots) {
                        for (let i = 1; i <= 3; i++) {
                            const b = data.bots[i];
                            document.getElementById(`bot${i}-money`).textContent = b.money + ' gtlm';
                            if (b.folded) {
                                document.getElementById(`seat-bot${i}`).classList.add('folded');
                            }
                        }
                    }
                    if (data.state === 'showdown' && data.allHands) {
                        b1Hand.innerHTML = ''; data.allHands.bot1.forEach(c => b1Hand.appendChild(renderCard(c)));
                        b2Hand.innerHTML = ''; data.allHands.bot2.forEach(c => b2Hand.appendChild(renderCard(c)));
                        b3Hand.innerHTML = ''; data.allHands.bot3.forEach(c => b3Hand.appendChild(renderCard(c)));
                        document.getElementById('start-controls').style.display = 'flex'; document.getElementById('play-controls').style.display = 'none';
                        balanceVal.textContent = data.newBalance || balanceVal.textContent;
                        if (data.isWin) { confetti({ particleCount: 200, spread: 80, origin: { y: 0.6 } }); Swal.fire('🎯 CHIẾN THẮNG!', data.message, 'success'); }
                        else if (!data.folded) Swal.fire('🃏 Kết quả', data.message, 'info');
                    }
                    if (data.folded) { document.getElementById('start-controls').style.display = 'flex'; document.getElementById('play-controls').style.display = 'none'; }
                }
            }
        });
    </script>











    <!-- Premium Effects System -->
    <canvas id="threejs-background"></canvas>
    <script>
        (function () {
            window.themeConfig = {
                particleCount: <?= $particleCount ?? 800 ?>,
                particleSize: <?= $particleSize ?? 0.05 ?>,
                particleColor: '<?= $particleColor ?? "#ffffff" ?>',
                particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
                shapeCount: <?= $shapeCount ?? 10 ?>,
                shapeColors: <?= json_encode($shapeColors ?? ["#667eea", "#764ba2", "#4facfe", "#00f2fe"]) ?>,
                shapeOpacity: <?= $shapeOpacity ?? 0.3 ?>,
                bgGradient: <?= json_encode($bgGradient ?? ["#667eea", "#764ba2", "#4facfe"]) ?>
            };
            const prefix = window.location.pathname.includes('/games/') ? '../' : '';
            const scripts = ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'];

            scripts.forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();
    
    // Load game history for poker
    
            });
            
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            if (data.success && data.history.length > 0) {
                const tbody = document.getElementById('historyBody');
                tbody.innerHTML = '';
                
                data.history.forEach((record, index) => {
                    const row = document.createElement('tr');
                    row.style.animation = \`slideIn 0.5s ease-out forwards\`;
                    row.style.animationDelay = (index * 0.05) + 's';
                    row.innerHTML = \`
                        <td>${record.Result}</td>
                        <td>${Number(record.Bet).toLocaleString('vi-VN')}</td>
                        <td>${record.Result}</td>
                        <td style="color: ${record.WinAmount > 0 ? '#28a745' : '#dc3545'}">
                            ${record.WinAmount > 0 ? '+' : ''}${Number(record.WinAmount).toLocaleString('vi-VN')}
                        </td>
                    \`;
                    tbody.appendChild(row);
                });
            }
        } catch (error) {
            console.error('Lỗi load history:', error);
        }
    }
    
    // Auto load history when page loads
    



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for poker game
    const ctxPoker = document.getElementById('gameChart');
    if (ctxPoker) {
        const gameChart = new Chart(ctxPoker.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadPokerHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for poker game
    const ctxPoker = document.getElementById('gameChart');
    if (ctxPoker) {
        const gameChart = new Chart(ctxPoker.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadPokerHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for poker game
    const ctxPoker = document.getElementById('gameChart');
    if (ctxPoker) {
        const gameChart = new Chart(ctxPoker.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadPokerHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for poker game
    const ctxPoker = document.getElementById('gameChart');
    if (ctxPoker) {
        const gameChart = new Chart(ctxPoker.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadPokerHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for poker game
    const ctxPoker = document.getElementById('gameChart');
    if (ctxPoker) {
        const gameChart = new Chart(ctxPoker.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadPokerHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for poker game
    const ctxPoker = document.getElementById('gameChart');
    if (ctxPoker) {
        const gameChart = new Chart(ctxPoker.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadPokerHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for poker game
    const ctxPoker = document.getElementById('gameChart');
    if (ctxPoker) {
        const gameChart = new Chart(ctxPoker.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadPokerHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for poker game
    const ctxPoker = document.getElementById('gameChart');
    if (ctxPoker) {
        const gameChart = new Chart(ctxPoker.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadPokerHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for poker game
    const ctxPoker = document.getElementById('gameChart');
    if (ctxPoker) {
        const gameChart = new Chart(ctxPoker.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadPokerHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for poker game
    const ctxPoker = document.getElementById('gameChart');
    if (ctxPoker) {
        const gameChart = new Chart(ctxPoker.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadPokerHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for poker game
    const ctxPoker = document.getElementById('gameChart');
    if (ctxPoker) {
        const gameChart = new Chart(ctxPoker.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadPokerHistory);



    // Improved history loading function
    async function loadPokerHistory() {
        try {
            const response = await fetch('poker.php?action=get_history', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for poker game
    const ctxPoker = document.getElementById('gameChart');
    if (ctxPoker) {
        const gameChart = new Chart(ctxPoker.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadPokerHistory);

</script>>














<div class="bottom-section">
    <div class="history-box">
        <h3>📋 Lịch sử chơi (10 lần gần nhất)</h3>
        <table border="1" cellpadding="10" id="historyTable">
            <thead>
                <tr style="background: rgba(255, 255, 255, 0.1);">
                    <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">ID</th>
                    <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">Cược</th>
                    <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">Kết quả</th>
                    <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">Thắng</th>
                    <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">Thời gian</th>
                </tr>
            </thead>
            <tbody id="historyBody">
                <tr><td colspan="5" style="text-align: center; padding: 15px; color: #aaa;">Chưa có lượt chơi nào</td></tr>
            </tbody>
        </table>
    </div>
    
    <div class="chart-box">
        <h3>📊 Thống kê</h3>
        <div class="stats-container">
            <div class="stat-item wins">
                <div class="label">Lần Thắng</div>
                <div class="value"><?= $gameThang ?></div>
            </div>
            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>


</body>

</html>