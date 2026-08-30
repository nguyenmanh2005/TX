<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_27', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

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

$userId = $botUserId;
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$userName = $user['Name'];
$stmt->close();
// Auto-create history table
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
            $conn->begin_transaction();
            $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $stmtLock->bind_param("i", $userId);
            $stmtLock->execute();
            $lockedMoney = $stmtLock->get_result()->fetch_assoc()['Money'] ?? 0;
            $stmtLock->close();
            if ($bet > $lockedMoney) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Số dư không đủ hoặc thao tác quá nhanh!']);
                exit;
            }
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
        $conn->commit();
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;700;900&display=swap" rel="stylesheet">
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
        .footer-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }

        .result-banner {
            position: fixed;
            top: 50px;
            left: 50%;
            transform: translateX(-50%) translateY(-100px);
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 1.5rem;
            font-weight: 900;
            color: white;
            z-index: 9999;
            opacity: 0;
            transition: 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            pointer-events: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .result-banner.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
        .result-banner.win {
            background: linear-gradient(135deg, #4ade80, #10b981);
            border: 2px solid #fff;
        }
        .result-banner.lose {
            background: linear-gradient(135deg, #ef4444, #f43f5e);
            border: 2px solid #fff;
        }

        .main-container {
            padding: 2rem;
            max-width: 1000px;
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .main-container { padding: 1rem; }
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
            width: 200px; height: 280px; background: transparent; border-radius: 20px;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
        }
        .controls { display: flex; flex-direction: column; gap: 1rem; flex: 1; }
        .btn-guess { padding: 1rem; border: none; border-radius: 1rem; color: #fff; font-weight: 900; font-size: 1.2rem; cursor: pointer; transition: 0.3s; }
        .btn-higher { background: linear-gradient(135deg, #00b894, #55efc4); }
        .btn-lower { background: linear-gradient(135deg, #d63031, #ff7675); }
        .btn-collect { background: var(--accent-color); color: #000; padding: 1rem; border-radius: 50px; border: none; font-weight: 900; cursor: pointer; }
        .quick-bet-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-bottom: 20px; width: 100%; }
        .quick-btn { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #e2e8f0; padding: 10px 20px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
        .quick-btn:hover { background: rgba(255,255,255,0.15); transform: translateY(-2px); }
        .quick-btn.active { background: #f59e0b; color: #fff; border-color: #f59e0b; }
        .footer-container { display: grid; grid-template-columns: 1fr 350px; gap: 2rem; margin-top: 2rem; }
        @media (max-width: 768px) { .footer-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="glass-card" style="display: flex; justify-content: space-between; align-items: center;">
            <h1 style="margin:0; color: var(--primary-color);">HI-LO</h1>
            <div style="display:flex; align-items:center; gap:1.5rem;">
                <div id="userMoney" style="font-weight:900; font-size:1.5rem; color:var(--accent-color)"><?= number_format($money, 0, ',', '.') ?> gtlm</div>
                <a href="../index.php" style="color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.2); padding: 0.5rem 1.5rem; border-radius: 50px; font-weight: 900; background: rgba(255,255,255,0.05); transition: 0.3s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'">THOÁT</a>
            </div>
        </div>
        <div class="glass-card">
            <div class="game-layout">
                <div class="card-display" id="playingCard">
                    <img src="../games/img/anh-bai/PNG/Cards (large)/card_back.png" alt="Card" id="cardImg" style="width: 100%; height: 100%; object-fit: cover; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.4);">
                </div>
                <div class="controls">
                    <input type="number" id="betAmount" value="10000" style="background: rgba(255,255,255,0.1); border: 1px solid var(--primary-color); color: #fff; padding: 1rem; border-radius: 1rem; font-size: 1.5rem; text-align: center; outline: none;">
                    <div class="quick-bet-grid">
                        <button class="quick-btn" onclick="setBet(10000, event)">10K</button>
                        <button class="quick-btn" onclick="setBet(50000, event)">50K</button>
                        <button class="quick-btn" onclick="setBet(100000, event)">100K</button>
                        <button class="quick-btn" onclick="setBet(500000, event)">500K</button>
                        <button class="quick-btn" onclick="setBet(1000000, event)">1M</button>
                        <button class="quick-btn" onclick="setBet(5000000, event)">5M</button>
                        <button class="quick-btn" onclick="setBet(<?= $money ?>, event)">ALL IN</button>
                    </div>
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
        <div class="footer-container" style="display: none;">
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
        function setBet(amount, event) {
            $('#betAmount').val(amount);
            $('.quick-btn').removeClass('active');
            if (event && event.target) {
                event.target.classList.add('active');
            }
        }
        
        function showBanner(msg, type) {
            let b = document.getElementById('resultBanner');
            if (!b) {
                b = document.createElement('div');
                b.id = 'resultBanner';
                document.body.appendChild(b);
            }
            b.className = 'result-banner ' + type + ' show';
            b.innerHTML = msg;
            setTimeout(() => { b.classList.remove('show'); }, 3000);
        }
        
        function resetGameUI() {
            $('#btnStart, #betAmount').prop('disabled', false);
            $('#btnHigher, #btnLower, #btnCollect').prop('disabled', true);
            $('#multVal').text('x1.00');
            $('#cardImg').attr('src', '../games/img/anh-bai/PNG/Cards (large)/card_back.png');
        }

        const suits = ['♠', '♣', '♥', '♦'], values = ['', 'A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
        function startGame() {
            const bet = $('#betAmount').val();
            $.post('?action=start', { bet: bet }, function (res) {
                if (res.success) {
                    $('#userMoney').text(res.money + ' gtlm'); updateCardDisplay(res.card);
                    $('#btnStart, #betAmount').prop('disabled', true); $('#btnHigher, #btnLower, #btnCollect').prop('disabled', false);
                } else Swal.fire('Lỗi', res.message, 'error');
            });
        }
        function guess(type) {
            $.post('?action=guess', { guess: type }, function (res) {
                if (res.success) {
                    updateCardDisplay(res.card);
                    if (res.win) { 
                        $('#multVal').text('x' + res.mult); 
                    }
                    else { 
                        let betStr = $('#betAmount').val().replace(/\./g, '');
                        showBanner('THUA RỒI! -' + betStr + ' GTLM', 'lose');
                        if (window.GameEffects) window.GameEffects.showLoss(parseInt(betStr) || 0);
                        setTimeout(resetGameUI, 2500);
                    }
                }
            });
        }
        function collect() {
            $.post('?action=collect', function (res) {
                if (res.success) {
                    let betStr = $('#betAmount').val().replace(/\./g, '');
                    let profit = parseInt(res.winAmount.replace(/\./g, '')) - parseInt(betStr);
                    showBanner('THẮNG LỚN! +' + new Intl.NumberFormat().format(profit) + ' GTLM', 'win');
                    $('#userMoney').text(new Intl.NumberFormat().format(res.money) + ' gtlm');
                    if (window.GameEffects && profit > 0) window.GameEffects.showWin(profit);
                    setTimeout(resetGameUI, 2500);
                }
            });
        }
        function updateCardDisplay(val) {
            const suitMap = {'♠': 'spades', '♣': 'clubs', '♥': 'hearts', '♦': 'diamonds'};
            const suitKey = suits[Math.floor(Math.random() * 4)];
            const suitStr = suitMap[suitKey];
            let valStr = values[val];
            if (!isNaN(valStr) && parseInt(valStr) < 10) valStr = '0' + parseInt(valStr);
            const url = `../games/img/anh-bai/PNG/Cards (large)/card_${suitStr}_${valStr}.png`;
            $('#cardImg').attr('src', url);
        }
        async function loadHistory() {
            // History API is currently unavailable
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
        })();
        window.currentBotId = <?= $botUserId ?? 0 ?>;
    </script>
    <script src="../threejs-background.js"></script>
    <script src="../assets/js/game-effects.js"></script>
    <script src="../assets/js/game-effects-auto.js"></script>

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script src="bots/bot_27.js"></script>

</body>
</html>