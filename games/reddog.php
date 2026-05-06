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
$conn->query("CREATE TABLE IF NOT EXISTS history_reddog (
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
            $suits = ['s', 'c', 'h', 'd'];
            $deck = [];
            foreach (['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'] as $v) {
                foreach ($suits as $s)
                    $deck[] = $v . $s;
            }
            shuffle($deck);
            $cards = array_slice($deck, 0, 3);

            $_SESSION['reddog_bet'] = $bet;
            $_SESSION['reddog_cards'] = $cards;

            $v1 = getCardValue($cards[0]);
            $v2 = getCardValue($cards[1]);
            $spread = abs($v1 - $v2) - 1;
            if ($spread < 0)
                $spread = 0;

            $response = ['success' => true, 'cards' => [$cards[0], $cards[1]], 'spread' => $spread, 'money' => number_format($money - $bet, 0, ',', '.')];
        }
    } elseif ($action === 'ride') {
        $bet = $_SESSION['reddog_bet'];
        $cards = $_SESSION['reddog_cards'];
        $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");
        $_SESSION['reddog_bet'] = $bet * 2;
        $response = ['success' => true];
    } elseif ($action === 'show') {
        $bet = $_SESSION['reddog_bet'];
        $cards = $_SESSION['reddog_cards'];
        $third = $cards[2];

        $v1 = getCardValue($cards[0]);
        $v2 = getCardValue($cards[1]);
        $v3 = getCardValue($third);

        $min = min($v1, $v2);
        $max = max($v1, $v2);
        $spread = $max - $min - 1;

        $win = ($v3 > $min && $v3 < $max);

        // House Edge: 15% chance to force loss on winning spreads
        if ($win && rand(1, 100) <= 15) {
            $win = false;
        }

        $payout = 0;
        if ($win) {
            $mult = 1;
            if ($spread == 1)
                $mult = 5;
            elseif ($spread == 2)
                $mult = 4;
            elseif ($spread == 3)
                $mult = 2;
            $payout = $bet + ($bet * $mult);
        }

        if ($payout > 0)
            $conn->query("UPDATE users SET Money = Money + $payout WHERE Iduser = $userId");

        $profit = $payout - $bet;
        $resMsg = $win ? "Thắng! Lá thứ 3 nằm trong khoảng." : "THUA RỒI! Lá thứ 3 nằm ngoài khoảng.";
        $his = $conn->prepare("INSERT INTO history_reddog (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $his->bind_param("idss", $userId, $bet, $resMsg, $profit);
        $his->execute();

        $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
        $response = [
            'success' => true,
            'third' => $third,
            'win' => $win,
            'message' => $resMsg,
            'winAmount' => number_format($payout, 0, ',', '.'),
            'money' => number_format($newMoney, 0, ',', '.')
        ];
        unset($_SESSION['reddog_bet']);
    }
    echo json_encode($response);
    exit;
}

function getCardValue($c)
{
    $v = substr($c, 0, -1);
    if ($v === 'J')
        return 11;
    if ($v === 'Q')
        return 12;
    if ($v === 'K')
        return 13;
    if ($v === 'A')
        return 14;
    return (int) $v;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Red Dog Poker - spread Betting</title>
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
            color: #fff;
            font-family: 'Exo 2', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
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
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 2.5rem;
        }

        .card {
            width: 100px;
            height: 140px;
            background: #fff;
            border-radius: 12px;
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1.8rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }

        .card.back {
            background: linear-gradient(135deg, #ff9a9e, #fad0c4);
            color: transparent;
        }

        .table-area {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 4rem 0;
            align-items: center;
        }

        .spread-badge {
            background: #f1c40f;
            color: #000;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: 900;
            font-size: 1.2rem;
            box-shadow: 0 0 15px #f1c40f;
        }

        .btn-premium {
            padding: 1.2rem 3rem;
            border: none;
            border-radius: 50px;
            font-weight: 900;
            cursor: pointer;
            transition: 0.4s;
            text-transform: uppercase;
        }

        .btn-red {
            background: #ff4757;
            color: #fff;
        }

        .btn-gold {
            background: #ffa502;
            color: #000;
        }
    </style>
</head>

<body>
    <div id="threejs-background"></div>
    <div class="container" style="max-width:900px; margin:2rem auto; position:relative; z-index:1;">
        <div class="glass"
            style="padding: 1.5rem 3rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1 style="margin:0; font-size: 2.2rem; font-weight: 900; color: #ff9a9e;">RED DOG POKER</h1>
            <div style="display:flex; align-items:center; gap:2rem;">
                <div id="userMoney" style="font-weight:900; font-size:1.5rem; color:#ffa502">
                    <?php echo number_format($money, 0, ',', '.'); ?> gtlm</div>
                <a href="../index.php"
                    style="color:#fff; text-decoration:none; border:1px solid rgba(255,255,255,0.2); padding:0.5rem 1.5rem; border-radius:50px;">THOÁT</a>
            </div>
        </div>

        <div class="glass" style="padding: 4rem; text-align: center;">
            <div class="table-area">
                <div id="card1" class="card back"></div>
                <div id="spreadInfo" style="display:none; flex-direction:column; gap:10px; align-items:center;">
                    <div class="spread-badge">KHOẢNG: <span id="spreadValue">0</span></div>
                    <div style="font-size:0.8rem; opacity:0.7">Lá thứ 3 phải nằm ở giữa</div>
                </div>
                <div id="card3" class="card back" style="transform: scale(1.1); border: 3px solid #ffa502;"></div>
                <div id="card2" class="card back"></div>
            </div>

            <div class="controls" style="display:flex; gap:20px; justify-content:center; align-items:center;">
                <input type="number" id="betAmount" value="10000" class="glass"
                    style="color:#fff; padding:12px; outline:none; font-size:1.3rem; font-weight:900; text-align:center; width:160px; border-radius:50px; border:1px solid rgba(255,255,255,0.2)">
                <button class="btn-premium btn-red" id="dealBtn" onclick="deal()">DEAL</button>
                <button class="btn-premium btn-gold" id="rideBtn" style="display:none" onclick="ride()">RIDE (Gấp đôi
                    cược)</button>
                <button class="btn-premium btn-red" id="showBtn" style="display:none" onclick="show()">MỞ THẺ</button>
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
