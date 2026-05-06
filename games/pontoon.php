<?php
session_start();
require '../db_connect.php';
require_once '../load_theme.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['Iduser'];
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$userName = $user['Name'];
$stmt->close();

// history table
$conn->query("CREATE TABLE IF NOT EXISTS history_pontoon (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Iduser INT NOT NULL,
    Bet DECIMAL(30,2) NOT NULL,
    Result VARCHAR(255) NOT NULL,
    WinAmount DECIMAL(30,2) NOT NULL,
    Time DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false];

    if ($action === 'deal') {
        $bet = (float) ($_POST['bet'] ?? 0);
        if ($bet <= 0 || $bet > $money) {
            $response['message'] = "gtlm cược không hợp lệ!";
        } else {
            $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");
            // Standard deck
            $suits = ['s', 'c', 'h', 'd'];
            $deck = [];
            foreach (['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'] as $v) {
                foreach ($suits as $s)
                    $deck[] = $v . $s;
            }
            shuffle($deck);
            $playerHand = array_slice($deck, 0, 2);
            $dealerHand = array_slice($deck, 2, 2);

            $_SESSION['pontoon_bet'] = $bet;
            $_SESSION['pontoon_player'] = $playerHand;
            $_SESSION['pontoon_dealer'] = $dealerHand;
            $_SESSION['pontoon_deck'] = array_slice($deck, 4);

            $response = ['success' => true, 'player' => $playerHand, 'money' => number_format($money - $bet, 0, ',', '.')];
        }
    } elseif ($action === 'twist') {
        $deck = $_SESSION['pontoon_deck'];
        $player = $_SESSION['pontoon_player'];
        $card = array_shift($deck);
        $player[] = $card;
        $_SESSION['pontoon_player'] = $player;
        $_SESSION['pontoon_deck'] = $deck;

        $response = ['success' => true, 'card' => $card, 'isBust' => (calculatePontoonScore($player) > 21)];
        if ($response['isBust']) {
            resolvePontoon(false, "Quá 21 điểm! Bạn đã thua.");
        }
    } elseif ($action === 'stick') {
        $player = $_SESSION['pontoon_player'];
        $dealer = $_SESSION['pontoon_dealer'];
        $deck = $_SESSION['pontoon_deck'];

        // Dealer logic: Deal until 17+
        while (calculatePontoonScore($dealer) < 17) {
            $dealer[] = array_shift($deck);
        }

        $pScore = calculatePontoonScore($player);
        $dScore = calculatePontoonScore($dealer);

        $win = false;
        $msg = "";

        if ($dScore > 21) {
            $win = true;
            $msg = "Dealer Quá 21 điểm! Bạn THẮNG.";
        } elseif ($pScore > $dScore) {
            $win = true;
            $msg = "Bạn thắng Dealer với điểm cao hơn ($pScore vs $dScore).";
        } elseif (count($player) >= 5 && $pScore <= 21) {
            $win = true;
            $msg = "5-Card Trick! Bạn thắng tuyệt đối.";
        } else {
            $win = false;
            $msg = "Dealer thắng ($dScore vs $pScore). Bạn đã thua.";
        }

        // House Edge: 10% chance to force loss even if technically won
        if ($win && rand(1, 10) === 1) {
            $win = false;
            $msg = "Dealer có bộ bài ẩn mạnh hơn! Bạn đã thua.";
        }

        $res = resolvePontoon($win, $msg, $dealer);
        $response = array_merge(['success' => true], $res);
    }
    echo json_encode($response);
    exit;
}

function calculatePontoonScore($hand)
{
    $score = 0;
    $aces = 0;
    foreach ($hand as $c) {
        $v = substr($c, 0, -1);
        if ($v === 'A') {
            $aces++;
            $score += 11;
        } elseif (in_array($v, ['J', 'Q', 'K']))
            $score += 10;
        else
            $score += (int) $v;
    }
    while ($score > 21 && $aces > 0) {
        $score -= 10;
        $aces--;
    }
    return $score;
}

function resolvePontoon($win, $msg, $dealer = null)
{
    global $conn, $userId;
    $bet = $_SESSION['pontoon_bet'];
    $winAmount = $win ? $bet * 2 : 0;
    if ($winAmount > 0)
        $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $userId");

    $profit = $winAmount - $bet;
    $his = $conn->prepare("INSERT INTO history_pontoon (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
    $his->bind_param("idss", $userId, $bet, $msg, $profit);
    $his->execute();

    unset($_SESSION['pontoon_bet']);
    $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
    return ['win' => $win, 'message' => $msg, 'dealer' => $dealer, 'winAmount' => number_format($winAmount, 0, ',', '.'), 'money' => number_format($newMoney, 0, ',', '.')];
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Pontoon - UK Blackjack Royale</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Exo 2', sans-serif;
            min-height: 100vh;
        }

        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        .glass {
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 2rem;
        }

        .card {
            width: 90px;
            height: 130px;
            background: #fff;
            border-radius: 10px;
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .card.back {
            background: linear-gradient(135deg, #12c2e9, #c471ed, #f64f59);
            color: transparent;
        }

        .hand {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 2rem 0;
            min-height: 140px;
        }

        .btn-premium {
            padding: 1.2rem 2.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 900;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
        }

        .btn-twist {
            background: #00d2ff;
            color: #000;
        }

        .btn-stick {
            background: #f1c40f;
            color: #000;
        }
    </style>
</head>

<body>
    <div id="threejs-background"></div>
    <div class="container" style="max-width:900px; margin:2rem auto; position:relative; z-index:1;">
        <div class="glass"
            style="padding: 1.5rem 3rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1 style="margin:0; font-size: 2.2rem; font-weight: 900; color: #12c2e9;">PONTOON</h1>
            <div style="display:flex; align-items:center; gap:2rem;">
                <div id="userMoney" style="font-weight:900; font-size:1.5rem; color:#f1c40f">
                    <?php echo number_format($money, 0, ',', '.'); ?> gtlm</div>
                <a href="../index.php"
                    style="color:#fff; text-decoration:none; border:1px solid rgba(255,255,255,0.2); padding:0.5rem 1.5rem; border-radius:50px;">THOÁT</a>
            </div>
        </div>

        <div class="glass" style="padding: 3rem; text-align: center;">
            <div id="dealerHand" class="hand">
                <div class="card back"></div>
                <div class="card back"></div>
            </div>
            <div id="playerHand" class="hand">
                <div class="card back"></div>
                <div class="card back"></div>
            </div>

            <div class="controls" style="display:flex; gap:15px; justify-content:center; align-items:center;">
                <input type="number" id="betAmount" value="10000" class="glass"
                    style="color:#fff; padding:10px; outline:none; font-size:1.2rem; font-weight:900; text-align:center; width:150px; border-radius:50px; border:1px solid rgba(255,255,255,0.2)">
                <button class="btn-premium btn-twist" id="dealBtn" onclick="deal()">DEAL</button>
                <button class="btn-premium btn-twist" id="twistBtn" style="display:none"
                    onclick="twist()">TWIST</button>
                <button class="btn-premium btn-stick" id="stickBtn" style="display:none"
                    onclick="stick()">STICK</button>
            </div>
        </div>
    </div>

    
    <?php require_once '../casino_help.php'; ?>


    
    


    


    


    <!-- Premium Effects System -->
    <canvas id="threejs-background"></canvas>
    <script>
        (function() {
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
