<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
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

/** @var int $particleCount */
/** @var float $particleSize */
/** @var string $particleColor */
/** @var float $particleOpacity */
/** @var int $shapeCount */
/** @var array $shapeColors */
/** @var float $shapeOpacity */
/** @var array $bgGradient */
/** @var string $bgGradientCSS */

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

// Khởi tạo gtlm Bot hiển thị ban đầu (Tầm vài triệu - tỷ phú)
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

            // Khởi tạo gtlm Bot khủng (Vài triệu -> 1 tỷ)
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
        
            // Insert vào history_poker table logic placeholder
            $historyStmt = $conn->prepare("INSERT INTO history_poker (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
            if ($historyStmt) {
                $resStr = 'Pre-flop';
                $win = 0;
                $historyStmt->bind_param("iisi", $userId, $bet, $resStr, $win);
                $historyStmt->execute();
                $historyStmt->close();
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
                if ($strength == 1 && $res[1] < 110) { // Dưới J High
                    if (rand(1, 100) < 30)
                        $shouldFold = true;
                }
            } else {
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
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            color: white;
            overflow-x: hidden;
            overflow-y: auto;
            padding-top: 20px;
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
            width: 1000px;
            height: 550px;
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
            flex-shrink: 0;
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

        .hand {
            display: flex;
            justify-content: center;
            gap: -20px;
        }

        .card {
            width: 75px;
            height: 105px;
            background: #ffffff;
            border-radius: 10px;
            color: #1e272e;
            font-weight: 900;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            font-size: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.4);
            position: relative;
            padding: 8px;
            user-select: none;
        }

        .card.hidden {
            background: linear-gradient(135deg, #1e272e 0%, #000 100%);
            color: transparent;
            border: 2px solid #3d2b1f;
        }

        .card.red { color: #eb4d4b; }

        .community-area {
            position: absolute;
            top: 55%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            gap: 12px;
            padding: 10px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 12px;
            min-height: 125px;
            width: 450px;
            justify-content: center;
            align-items: center;
            border: 1px dashed rgba(255, 255, 255, 0.1);
        }

        .pot-container {
            position: absolute;
            top: 35%;
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
        }

        .controls {
            margin: 20px 0;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(20px);
            padding: 20px 40px;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .btn-poker {
            padding: 12px 30px;
            border-radius: 30px;
            border: none;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
            font-size: 14px;
        }

        .btn-premium { background: linear-gradient(135deg, #ffd700, #d4af37); color: #000; }
        .btn-next { background: #2ecc71; color: #fff; }
        .btn-fold { background: #e74c3c; color: #fff; }
        .btn-secondary { background: rgba(255, 255, 255, 0.1); color: #fff; text-decoration: none; display: flex; align-items: center; }

        .status-box {
            background: rgba(0, 0, 0, 0.8);
            padding: 10px 40px;
            border-radius: 30px;
            color: #ffd700;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 215, 0, 0.3);
            font-weight: 600;
        }
        
        .game-wrapper {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .game-header {
            width: 100%;
            background: rgba(0, 0, 0, 0.7);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #d4af37;
            margin-bottom: 20px;
            box-sizing: border-box;
            backdrop-filter: blur(10px);
        }

        .game-header .back-btn {
            color: #d4af37;
            text-decoration: none;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .game-header .game-info {
            text-align: center;
        }

        .game-header .game-info h1 {
            margin: 0;
            color: #fff;
            font-size: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .game-header .help-btn {
            background: transparent;
            border: 2px solid #d4af37;
            color: #d4af37;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }

        .game-header .help-btn:hover {
            background: #d4af37;
            color: #000;
        }

        .chip-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-bottom: 15px;
            width: 100%;
        }

        .chip {
            padding: 8px 18px;
            background: rgba(255,255,255,0.1);
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            color: #fff;
            transition: 0.3s;
            user-select: none;
        }

        .chip:hover, .chip.active {
            background: #d4af37;
            color: #000;
            border-color: #d4af37;
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.4);
        }
    </style>
</head>

<body>
    <div class="game-wrapper">
        <div class="game-header">
            <a href="../index.php" class="back-btn">← Trang chủ</a>
            <div class="game-info">
                <h1>TEXAS HOLD'EM POKER</h1>
                <div class="balance-box">
                    <span>Ngân khố:</span>
                    <strong id="balance-val" style="color: #ffd700;"><?= number_format($soDu) ?></strong> GTLM
                </div>
            </div>
            <button class="help-btn" onclick="Swal.fire({title: 'Luật chơi', text: 'Poker Texas Hold\\'em...', background: '#1a1a1a', color: '#fff'})">?</button>
        </div>

        <div class="status-box" id="status-box">Chào mừng đến với Poker Texas Hold'em!</div>

    <div class="poker-table">
        <div class="pot-container">
            <div class="pot-val" id="pot-val">Pot: 0</div>
        </div>
        <div class="seat seat-left" id="seat-bot1">
            <div class="player-info"><b>Bot 1</b><br><span id="bot1-money"><?= number_format($initBotMoney[1]) ?> gtlm</span></div>
            <div class="status-badge">ĐÃ BỎ BÀI</div>
            <div class="hand" id="bot1-hand"></div>
        </div>
        <div class="seat seat-top" id="seat-bot2">
            <div class="player-info"><b>Bot 2</b><br><span id="bot2-money"><?= number_format($initBotMoney[2]) ?> gtlm</span></div>
            <div class="status-badge">ĐÃ BỎ BÀI</div>
            <div class="hand" id="bot2-hand"></div>
        </div>
        <div class="seat seat-right" id="seat-bot3">
            <div class="player-info"><b>Bot 3</b><br><span id="bot3-money"><?= number_format($initBotMoney[3]) ?> gtlm</span></div>
            <div class="status-badge">ĐÃ BỎ BÀI</div>
            <div class="hand" id="bot3-hand"></div>
        </div>
        <div class="community-area" id="community-area"></div>
        <div class="seat seat-bottom">
            <div class="hand" id="player-hand"></div>
            <div class="player-info"><b><?= htmlspecialchars($tenNguoiChoi) ?></b><br><span id="balance-val" style="color: #ffd700;"><?= number_format($soDu) ?> gtlm</span></div>
        </div>
    </div>

    <div class="controls">
        <div id="start-controls" style="display: flex; flex-direction: column; align-items: center; gap: 15px; width: 100%;">
            <input type="hidden" id="bet-amount" value="10000">
            <div class="chip-selector" style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                <div class="chip active" data-value="10000">10K</div>
                <div class="chip" data-value="50000">50K</div>
                <div class="chip" data-value="100000">100K</div>
                <div class="chip" data-value="500000">500K</div>
                <div class="chip" data-value="1000000">1M</div>
                <div class="chip" data-value="5000000">5M</div>
                <div class="chip" data-value="<?= $soDu ?>">MAX</div>
            </div>
            <button id="btn-start" class="btn-poker btn-premium">🃏 KHAI CUỘC</button>
        </div>
        <div id="play-controls" style="display: none; gap: 10px;">
            <button id="btn-call" class="btn-poker btn-next">✅ THEO (CALL/CHECK)</button>
            <button id="btn-fold" class="btn-poker btn-fold">🏳️ BỎ BÀI (FOLD)</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const btnStart = document.getElementById('btn-start'), btnCall = document.getElementById('btn-call'), btnFold = document.getElementById('btn-fold'), statusBox = document.getElementById('status-box'), potVal = document.getElementById('pot-val'), balanceVal = document.getElementById('balance-val'), betInput = document.getElementById('bet-amount'), pHand = document.getElementById('player-hand'), b1Hand = document.getElementById('bot1-hand'), b2Hand = document.getElementById('bot2-hand'), b3Hand = document.getElementById('bot3-hand'), commArea = document.getElementById('community-area');

            function renderCard(card, isHidden = false, delay = 0) {
                const el = document.createElement('div');
                el.className = 'card card-img' + (isHidden ? ' hidden' : '');
                el.style.animation = `deal 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) backwards ${delay}s`;
                el.style.background = 'transparent';
                el.style.padding = '0';
                el.style.border = 'none';
                el.style.boxShadow = 'none';
                
                let url;
                if (!isHidden) {
                    const suitMap = {'♥': 'hearts', '♦': 'diamonds', '♣': 'clubs', '♠': 'spades'};
                    const suitStr = suitMap[card.slice(-1)];
                    let valStr = card.slice(0, -1);
                    if (!isNaN(valStr) && parseInt(valStr) < 10) valStr = '0' + parseInt(valStr);
                    url = `img/anh-bai/PNG/Cards (large)/card_${suitStr}_${valStr}.png`;
                } else {
                    url = `img/anh-bai/PNG/Cards (large)/card_back.png`;
                }
                el.innerHTML = `<img src="${url}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.4);">`;
                return el;
            }

            // Chip Selection Logic
            document.querySelectorAll('.chip').forEach(chip => {
                chip.addEventListener('click', () => {
                    document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
                    chip.classList.add('active');
                    betInput.value = chip.dataset.value;
                    
                    // Chip click effect
                    if (window.GameEffects) {
                        GameEffects.createCoinBlast(event.clientX, event.clientY);
                    }
                });
            });

            btnStart.addEventListener('click', async () => {
                const bet = betInput.value; const fd = new FormData(); fd.append('bet', bet);
                const res = await fetch('poker.php?action=start_game', { method: 'POST', body: fd }); const data = await res.json();
                if (data.success) {
                    pHand.innerHTML = ''; b1Hand.innerHTML = ''; b2Hand.innerHTML = ''; b3Hand.innerHTML = ''; commArea.innerHTML = '';
                    document.querySelectorAll('.seat').forEach(s => s.classList.remove('folded'));
                    data.playerHand.forEach((c, i) => pHand.appendChild(renderCard(c, false, i * 0.15)));
                    for (let i = 0; i < 2; i++) { b1Hand.appendChild(renderCard(null, true, 0.3 + i * 0.15)); b2Hand.appendChild(renderCard(null, true, 0.6 + i * 0.15)); b3Hand.appendChild(renderCard(null, true, 0.9 + i * 0.15)); }
                    potVal.textContent = 'Pot: ' + data.pot; balanceVal.textContent = data.balance; statusBox.textContent = data.message;
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
                            if (b.folded) document.getElementById(`seat-bot${i}`).classList.add('folded');
                        }
                    }
                    if (data.state === 'showdown' && data.allHands) {
                        b1Hand.innerHTML = ''; data.allHands.bot1.forEach(c => b1Hand.appendChild(renderCard(c)));
                        b2Hand.innerHTML = ''; data.allHands.bot2.forEach(c => b2Hand.appendChild(renderCard(c)));
                        b3Hand.innerHTML = ''; data.allHands.bot3.forEach(c => b3Hand.appendChild(renderCard(c)));
                        document.getElementById('start-controls').style.display = 'flex'; document.getElementById('play-controls').style.display = 'none';
                        if (data.newBalance) balanceVal.textContent = data.newBalance;
                        
                        if (data.isWin) { 
                            if (window.GameEffects) GameEffects.showWin(data.message.replace('🏆 CHIẾN THẮNG: ', '')); 
                        } else if (!data.folded) {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'info',
                                title: data.message,
                                showConfirmButton: false,
                                timer: 3000,
                                background: '#1a1a1a',
                                color: '#fff'
                            });
                        }
                    }
                    if (data.folded) { 
                        document.getElementById('start-controls').style.display = 'flex'; 
                        document.getElementById('play-controls').style.display = 'none'; 
                    }
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
    </script>

</body>
</html>
