<?php
session_start();
require '../db_connect.php';
require_once '../load_theme.php';
require_once '../game_history_helper.php';

/** @var int $particleCount */
/** @var float $particleSize */
/** @var string $particleColor */
/** @var float $particleOpacity */
/** @var int $shapeCount */
/** @var array $shapeColors */
/** @var float $shapeOpacity */
/** @var array $bgGradient */
/** @var string $bgGradientCSS */

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

// Auto-create history table
$conn->query("CREATE TABLE IF NOT EXISTS history_hilo (Id INT AUTO_INCREMENT PRIMARY KEY, Iduser INT NOT NULL, Bet DECIMAL(30,2) NOT NULL, Result VARCHAR(255) NOT NULL, WinAmount DECIMAL(30,2) NOT NULL, Time DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Get statistics for chart
$gameThang = 0;
$gameThua = 0;
$sqlStats = "SELECT COUNT(*) as total, SUM(CASE WHEN WinAmount > 0 THEN 1 ELSE 0 END) as wins FROM history_hilo WHERE Iduser = ?";
$stmtStats = $conn->prepare($sqlStats);
$stmtStats->bind_param("i", $userId);
$stmtStats->execute();
$resultStats = $stmtStats->get_result();
if ($rowStats = $resultStats->fetch_assoc()) {
    $gameThang = $rowStats['wins'] ?? 0;
    $gameThua = ($rowStats['total'] ?? 0) - $gameThang;
}
$stmtStats->close();

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false];

    if ($action === 'start') {
        $bet = (float) ($_POST['bet'] ?? 0);
        if ($bet <= 0 || $bet > $money) {
            $response['message'] = "Gtlm cược không hợp lệ!";
        } else {
            $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");
            $card = rand(1, 13);
            $_SESSION['hilo_bet'] = $bet;
            $_SESSION['hilo_card'] = $card;
            $_SESSION['hilo_mult'] = 1.0;
            $response = ['success' => true, 'card' => $card, 'money' => number_format($money - $bet, 0, ',', '.')];
        }
    } elseif ($action === 'guess') {
        $guess = $_POST['guess'];
        $oldCard = $_SESSION['hilo_card'];
        $bet = $_SESSION['hilo_bet'];
        $newCard = rand(1, 13);
        $win = false;
        if ($guess === 'higher' && $newCard >= $oldCard) $win = true;
        if ($guess === 'lower' && $newCard <= $oldCard) $win = true;

        if ($win) {
            $multAdd = ($oldCard == $newCard) ? 0.1 : 0.5;
            $_SESSION['hilo_mult'] += $multAdd;
            $_SESSION['hilo_card'] = $newCard;
            $response = ['success' => true, 'win' => true, 'card' => $newCard, 'mult' => number_format($_SESSION['hilo_mult'], 2)];
        } else {
            $resStr = "Lost at x" . number_format($_SESSION['hilo_mult'], 2);
            $his = $conn->prepare("INSERT INTO history_hilo (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
            $negBet = -$bet;
            $his->bind_param("idss", $userId, $bet, $resStr, $negBet);
            $his->execute();
            logGameHistoryWithAll($conn, $userId, 'Hi-Lo', $bet, 0, false);
            unset($_SESSION['hilo_bet']);
            $response = ['success' => true, 'win' => false, 'card' => $newCard];
        }
    } elseif ($action === 'collect') {
        $bet = $_SESSION['hilo_bet'];
        $mult = $_SESSION['hilo_mult'];
        $winAmount = round($bet * $mult);
        $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $userId");
        $resStr = "Collect at x" . round($mult, 2);
        $his = $conn->prepare("INSERT INTO history_hilo (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $profit = $winAmount - $bet;
        $his->bind_param("idss", $userId, $bet, $resStr, $profit);
        $his->execute();
        logGameHistoryWithAll($conn, $userId, 'Hi-Lo', $bet, $winAmount, true);
        unset($_SESSION['hilo_bet']);
        $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
        $response = ['success' => true, 'winAmount' => number_format($winAmount, 0, ',', '.'), 'money' => number_format($newMoney, 0, ',', '.')];
    }
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hi-Lo - Dự Đoán Đỉnh Cao</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-color: #00d2ff;
            --accent-color: #f1c40f;
            --glass: rgba(255, 255, 255, 0.05);
        }
        h1 {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #fff;
            min-height: 100vh;
            font-family: 'Exo 2', sans-serif;
            margin: 0;
        }
        .main-container {
            padding: 2rem;
            max-width: 1000px;
            margin: 0 auto;
        }
        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 2rem;
            padding: 2rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            margin-bottom: 2rem;
        }
        .game-layout { display: flex; gap: 2rem; align-items: center; justify-content: center; flex-wrap: wrap; }
        .card-display {
            width: 200px; height: 280px; background: #fff; border-radius: 20px;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            color: #000; box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        .card-val { font-size: 5rem; font-weight: 900; }
        .red-suit { color: #ff4757; }
        .controls { display: flex; flex-direction: column; gap: 1rem; flex: 1; }
        .btn-guess { padding: 1rem; border: none; border-radius: 1rem; color: #fff; font-weight: 900; font-size: 1.2rem; cursor: pointer; transition: 0.3s; }
        .btn-higher { background: linear-gradient(135deg, #00b894, #55efc4); }
        .btn-lower { background: linear-gradient(135deg, #d63031, #ff7675); }
        .btn-collect { background: var(--accent-color); color: #000; padding: 1rem; border-radius: 50px; border: none; font-weight: 900; cursor: pointer; }
        .footer-container { display: grid; grid-template-columns: 1fr 350px; gap: 2rem; margin-top: 2rem; }
        @media (max-width: 768px) { .footer-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="glass-card" style="display: flex; justify-content: space-between; align-items: center;">
            <h1 style="margin:0; color: var(--primary-color);">HI-LO</h1>
            <div id="userMoney" style="font-weight:900; font-size:1.5rem; color:var(--accent-color)"><?= number_format($money, 0, ',', '.') ?> gtlm</div>
        </div>

        <div class="glass-card">
            <div class="game-layout">
                <div class="card-display" id="playingCard">
                    <div class="card-val" id="cardVal">?</div>
                </div>
                <div class="controls">
                    <input type="number" id="betAmount" value="10000" style="background: rgba(255,255,255,0.1); border: 1px solid var(--primary-color); color: #fff; padding: 1rem; border-radius: 1rem; font-size: 1.5rem; text-align: center; outline: none;">
                    <div style="display: flex; gap: 10px;">
                        <button class="btn-guess btn-higher" id="btnHigher" onclick="guess('higher')" disabled style="flex:1">CAO HƠN</button>
                        <button class="btn-guess btn-lower" id="btnLower" onclick="guess('lower')" disabled style="flex:1">THẤP HƠN</button>
                    </div>
                    <button class="btn-guess" style="background:var(--primary-color); width:100%" id="btnStart" onclick="startGame()">BẮT ĐẦU</button>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
                        <span>Thưởng: <b id="multVal" style="color:var(--accent-color)">x1.00</b></span>
                        <button class="btn-collect" id="btnCollect" onclick="collect()" disabled>NHẬN Gtlm</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-container">
            <div class="glass-card" style="margin-bottom: 0;">
                <h3 style="color: var(--primary-color); margin-top: 0;">LỊCH SỬ</h3>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                        <tbody id="historyTableBody"><tr><td colspan="4" style="text-align: center; padding: 20px; opacity: 0.5;">Đang tải...</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="glass-card" style="margin-bottom: 0;">
                <h3 style="color: var(--primary-color); margin-top: 0;">THỐNG KÊ</h3>
                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                    <div style="flex:1; background: rgba(74, 222, 128, 0.1); padding: 10px; border-radius: 10px; text-align: center;">Thắng: <?= $gameThang ?></div>
                    <div style="flex:1; background: rgba(255, 107, 107, 0.1); padding: 10px; border-radius: 10px; text-align: center;">Thua: <?= $gameThua ?></div>
                </div>
                <canvas id="gameChart" style="max-height: 150px;"></canvas>
            </div>
        </div>
    </div>

    <canvas id="threejs-background" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; pointer-events: none;"></canvas>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const suits = ['♠', '♣', '♥', '♦'], values = ['', 'A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
        function startGame() {
            const bet = $('#betAmount').val();
            $.post('hilo.php?action=start', { bet: bet }, function (res) {
                if (res.success) {
                    $('#userMoney').text(res.money + ' gtlm'); updateCardDisplay(res.card);
                    $('#btnStart, #betAmount').prop('disabled', true); $('#btnHigher, #btnLower, #btnCollect').prop('disabled', false);
                } else Swal.fire('Lỗi', res.message, 'error');
            });
        }
        function guess(type) {
            $.post('hilo.php?action=guess', { guess: type }, function (res) {
                if (res.success) {
                    updateCardDisplay(res.card);
                    if (res.win) { $('#multVal').text('x' + res.mult); }
                    else { Swal.fire('THUA!', 'Đoán sai rồi.', 'error').then(() => location.reload()); }
                }
            });
        }
        function collect() {
            $.post('hilo.php?action=collect', function (res) {
                if (res.success) Swal.fire('THÀNH CÔNG', 'Nhận: ' + res.winAmount + ' gtlm', 'success').then(() => location.reload());
            });
        }
        function updateCardDisplay(val) {
            const suit = suits[Math.floor(Math.random() * 4)];
            $('#cardVal').text(values[val]).removeClass('red-suit');
            if (suit === '♥' || suit === '♦') $('#cardVal').addClass('red-suit');
        }
        async function loadHistory() {
            const res = await $.getJSON('../api_game_history.php?game=Hi-Lo');
            if (res.success && res.history) {
                $('#historyTableBody').html(res.history.slice(0, 10).map(r => `
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <td style="padding: 8px;">#${r.id}</td>
                        <td style="padding: 8px; text-align: right;">${parseInt(r.bet_amount).toLocaleString()}</td>
                        <td style="padding: 8px;"><span style="color: ${r.is_win ? '#4ade80' : '#ff6b6b'}">${r.is_win ? 'THẮNG' : 'THUA'}</span></td>
                        <td style="padding: 8px; text-align: right;">${parseInt(r.win_amount).toLocaleString()}</td>
                    </tr>
                `).join(''));
            }
        }
        $(document).ready(() => {
            loadHistory();
            const ctx = document.getElementById('gameChart');
            if (ctx) new Chart(ctx, { type: 'doughnut', data: { labels: ['Thắng', 'Thua'], datasets: [{ data: [<?= $gameThang ?>, <?= $gameThua ?>], backgroundColor: ['#4ade80', '#ff6b6b'] }] }, options: { responsive: true, maintainAspectRatio: false } });
        });
        (function () {
            window.themeConfig = {
                particleCount: <?= $particleCount ?>, particleSize: <?= $particleSize ?>, particleColor: '<?= $particleColor ?>', particleOpacity: <?= $particleOpacity ?>,
                shapeCount: <?= $shapeCount ?>, shapeColors: <?= json_encode($shapeColors) ?>, shapeOpacity: <?= $shapeOpacity ?>, bgGradient: <?= json_encode($bgGradient) ?>
            };
            const prefix = '../';
            ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'].forEach(src => {
                const s = document.createElement('script'); s.src = prefix + src; s.async = false; document.head.appendChild(s);
            });
        })();
    </script>
</body>
</html>