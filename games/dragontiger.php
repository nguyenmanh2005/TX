<?php
session_start();
require '../db_connect.php';
require_once '../load_theme.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['Iduser'];

// Auto-create history table if missing
$conn->query("CREATE TABLE IF NOT EXISTS history_dragontiger (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Iduser INT NOT NULL,
    Bet DECIMAL(30,2) NOT NULL,
    Result VARCHAR(255) NOT NULL,
    WinAmount DECIMAL(30,2) NOT NULL,
    Time DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Lấy thông tin user
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$userName = $user['Name'];
$stmt->close();

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false, 'message' => ''];

    $suits = ['♠', '♥', '♦', '♣'];
    $values = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];

    if ($action === 'deal') {
        $betDragon = (int) ($_POST['betDragon'] ?? 0);
        $betTiger = (int) ($_POST['betTiger'] ?? 0);
        $betTie = (int) ($_POST['betTie'] ?? 0);
        $totalBet = $betDragon + $betTiger + $betTie;

        if ($totalBet <= 0 || $totalBet > $money) {
            $response['message'] = "gtlm cược không hợp lệ hoặc không đủ Số Gtlm!";
        } else {
            $dValIdx = rand(0, 12);
            $dSuitIdx = rand(0, 3);
            $tValIdx = rand(0, 12);
            $tSuitIdx = rand(0, 3);

            $dragonCard = ['val' => $values[$dValIdx], 'suit' => $suits[$dSuitIdx], 'score' => $dValIdx + 1];
            $tigerCard = ['val' => $values[$tValIdx], 'suit' => $suits[$tSuitIdx], 'score' => $tValIdx + 1];

            $winAmount = -$totalBet; // Ban đầu trừ hết cược
            if ($dragonCard['score'] > $tigerCard['score']) {
                $winAmount += ($betDragon * 2);
            } elseif ($dragonCard['score'] < $tigerCard['score']) {
                $winAmount += ($betTiger * 2);
            } else {
                // Tie
                $winAmount += ($betTie * 9); // Đặt Tie ăn x8 cộng trả cược gốc = x9 (user says x8)
                // Theo luật user: "house lấy 50% nếu Tie khi đặt Dragon/Tiger"
                $winAmount += ($betDragon * 0.5) + ($betTiger * 0.5);
            }

            $newMoney = $money + $winAmount;
            $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
            $stmt->bind_param("di", $newMoney, $userId);
            $stmt->execute();
            $stmt->close();

            // Lưu lịch sử
            $resSide = ($dragonCard['score'] > $tigerCard['score']) ? 'Dragon' : ($dragonCard['score'] < $tigerCard['score'] ? 'Tiger' : 'Tie');
            $resStr = "D: " . $dragonCard['val'] . $dragonCard['suit'] . " vs T: " . $tigerCard['val'] . $tigerCard['suit'] . " ($resSide)";
            $his = $conn->prepare("INSERT INTO history_dragontiger (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
            $his->bind_param("idss", $userId, $totalBet, $resStr, $winAmount);
            $his->execute();
            $his->close();

            $response = [
                'success' => true,
                'dragonCard' => $dragonCard,
                'tigerCard' => $tigerCard,
                'winSide' => $resSide,
                'winAmount' => $winAmount,
                'money' => number_format($newMoney, 0, ',', '.'),
                'rawMoney' => $newMoney
            ];
        }
    } elseif ($action === 'get_history') {
        $stmt = $conn->prepare("SELECT Bet, Result, WinAmount, Time FROM history_dragontiger WHERE Iduser = ? ORDER BY Time DESC LIMIT 10");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $history = [];
        while ($row = $res->fetch_assoc()) {
            $history[] = $row;
        }
        $response = ['success' => true, 'history' => $history];
    }

    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Long Hổ Tranh Đấu - Dragon Tiger</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <style>
        :root {
            --dragon: #3498db;
            --tiger: #e74c3c;
            --tie: #f1c40f;
            --bg-deep: #150506;
            --glass: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            color: #fff;
            min-height: 100vh;
            font-family: 'Exo 2', system-ui, sans-serif;
            overflow-x: hidden;
        }

        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
        }

        .main-container {
            position: relative;
            z-index: 1;
            width: 95%;
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem 1rem;
            text-align: center;
        }

        .game-header h1 {
            font-size: clamp(2.5rem, 10vw, 4rem);
            text-transform: uppercase;
            font-weight: 900;
            letter-spacing: 5px;
            background: linear-gradient(45deg, var(--tie), #e67e22, var(--tie));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        .balance-pill {
            background: var(--glass);
            padding: 0.8rem 2rem;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 2rem;
            border: 1px solid var(--glass-border);
            font-weight: 700;
            backdrop-filter: blur(10px);
        }

        .table-area {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1.5rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }

        .side-box {
            flex: 1;
            min-width: 280px;
            padding: 2rem;
            border-radius: 2rem;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(15px);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .side-box.dragon {
            border-color: var(--dragon);
            box-shadow: 0 0 30px rgba(52, 152, 219, 0.1);
        }

        .side-box.tiger {
            border-color: var(--tiger);
            box-shadow: 0 0 30px rgba(231, 76, 60, 0.1);
        }

        .side-box.winner-dragon {
            box-shadow: 0 0 50px var(--dragon);
            background: rgba(52, 152, 219, 0.15);
            transform: scale(1.02);
        }

        .side-box.winner-tiger {
            box-shadow: 0 0 50px var(--tiger);
            background: rgba(231, 76, 60, 0.15);
            transform: scale(1.02);
        }

        .side-box.winner-tie {
            box-shadow: 0 0 50px var(--tie);
            background: rgba(241, 196, 15, 0.15);
            transform: scale(1.02);
        }

        .playing-card {
            width: clamp(100px, 15vw, 140px);
            aspect-ratio: 2/3;
            background: #fff;
            border-radius: 1rem;
            color: #000;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin: 1.5rem auto;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .playing-card.red {
            color: var(--tiger);
        }

        .playing-card.black {
            color: #2c3e50;
        }

        .card-val {
            font-size: 1.5rem;
            position: absolute;
            top: 0.8rem;
            left: 0.8rem;
            font-weight: 900;
        }

        .card-suit {
            font-size: clamp(3rem, 8vw, 5rem);
        }

        .card-placeholder {
            font-size: 4rem;
            opacity: 0.1;
        }

        .bet-area {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1.5rem;
            margin: 2.5rem 0;
        }

        .bet-zone {
            padding: 1.5rem;
            border-radius: 1.5rem;
            background: var(--glass);
            border: 2px solid var(--glass-border);
            cursor: pointer;
            transition: all 0.3s;
        }

        .bet-zone:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-3px);
        }

        .bet-zone.active {
            border-color: var(--tie);
            background: rgba(241, 196, 15, 0.1);
        }

        .bet-label {
            font-size: 1.2rem;
            font-weight: 900;
            margin-bottom: 0.8rem;
            letter-spacing: 1px;
        }

        .dragon-label {
            color: var(--dragon);
        }

        .tiger-label {
            color: var(--tiger);
        }

        .tie-label {
            color: var(--tie);
        }

        input[type="number"] {
            width: 100%;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--glass-border);
            border-radius: 0.5rem;
            color: #fff;
            text-align: center;
            font-size: 1.2rem;
            padding: 0.5rem;
            outline: none;
            transition: border-color 0.3s;
        }

        input[type="number"]:focus {
            border-color: var(--tie);
        }

        .btn-deal {
            background: linear-gradient(135deg, var(--tie) 0%, #d35400 100%);
            border: none;
            padding: 1.2rem 4rem;
            border-radius: 50px;
            color: #fff;
            font-size: clamp(1.2rem, 4vw, 1.8rem);
            font-weight: 900;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(211, 84, 0, 0.3);
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 3px;
            width: 100%;
            max-width: 400px;
        }

        .btn-deal:hover:not(:disabled) {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(211, 84, 0, 0.5);
        }

        .btn-deal:disabled {
            opacity: 0.5;
            filter: grayscale(1);
            cursor: not-allowed;
        }

        .history-card {
            background: var(--glass);
            border-radius: 2rem;
            padding: 2rem;
            margin-top: 3rem;
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(10px);
        }

        .history-container {
            overflow-x: auto;
            margin-top: 1rem;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 500px;
        }

        .history-table th {
            color: rgba(255, 255, 255, 0.4);
            text-transform: uppercase;
            font-size: 0.8rem;
            padding: 1rem;
            border-bottom: 2px solid var(--glass-border);
        }

        .history-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--glass-border);
            font-weight: 600;
        }

        @media (max-width: 600px) {
            .side-box {
                min-width: 100%;
            }

            .btn-deal {
                width: 100%;
            }
        }
    </style>
</head>

<body>


    <div class="main-container">
        <div class="game-header">
            <h1>DRAGON TIGER</h1>
            <div class="balance-pill">💰 <span id="balance-display"><?= number_format($money, 0, ',', '.') ?></span>
                gtlm</div>
        </div>

        <div class="table-area">
            <div id="dragon-box" class="side-box dragon">
                <div class="dragon-label bet-label" style="font-size: 2rem;">DRAGON</div>
                <div id="dragon-card" class="playing-card">
                    <div class="card-placeholder">🐉</div>
                </div>
            </div>

            <div style="font-size: 3rem; font-weight: 900; color: rgba(255,255,255,0.2);">VS</div>

            <div id="tiger-box" class="side-box tiger">
                <div class="tiger-label bet-label" style="font-size: 2rem;">TIGER</div>
                <div id="tiger-card" class="playing-card">
                    <div class="card-placeholder">🐯</div>
                </div>
            </div>
        </div>

        <div id="status-text" style="font-size: 2rem; font-weight: 900; min-height: 3rem; margin: 20px 0;"></div>

        <div class="bet-area">
            <div class="bet-zone" onclick="$('#bet-dragon').focus()">
                <div class="dragon-label bet-label">DRAGON</div>
                <input type="number" id="bet-dragon" placeholder="0" value="0">
            </div>
            <div class="bet-zone" onclick="$('#bet-tie').focus()">
                <div class="tie-label bet-label">TIE (x8)</div>
                <input type="number" id="bet-tie" placeholder="0" value="0">
            </div>
            <div class="bet-zone" onclick="$('#bet-tiger').focus()">
                <div class="tiger-label bet-label">TIGER</div>
                <input type="number" id="bet-tiger" placeholder="0" value="0">
            </div>
        </div>

        <button id="deal-btn" class="btn-deal">QUYẾT ĐẤU</button>

        <div class="history-card">
            <h2 style="text-transform: uppercase; letter-spacing: 2px; margin-bottom: 1.5rem;">Lịch sử ván đấu</h2>
            <div class="history-container">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>gtlm cược</th>
                            <th>Kết quả</th>
                            <th>Thắng/Thua</th>
                        </tr>
                    </thead>
                    <tbody id="history-body">
                        <!-- History via AJAX -->
                    </tbody>
                </table>
            </div>
            <a href="../index.php" class="btn-deal"
                style="margin-top: 2rem; display: inline-block; text-decoration: none; padding: 1rem 2.5rem; font-size: 1.1rem; width: auto;">Quay
                lại gtlm sảnh</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>

    <?php require_once '../casino_help.php'; ?>












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