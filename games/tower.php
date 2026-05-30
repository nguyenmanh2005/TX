<?php
session_start();
include '../db_connect.php';
require_once '../include_css.php';
include '../load_theme.php';
require_once '../game_history_helper.php';

if (!isset($_SESSION['Iduser'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['Iduser'];
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$userName = $user['Name'];
$stmt->close();

$conn->query("CREATE TABLE IF NOT EXISTS history_tower (Id INT AUTO_INCREMENT PRIMARY KEY, Iduser INT NOT NULL, Bet DECIMAL(30,2) NOT NULL, Result VARCHAR(255) NOT NULL, WinAmount DECIMAL(30,2) NOT NULL, Time DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

function getTowerMultiplier($floor)
{
    if ($floor <= 0)
        return 1.0;
    return round(pow(1.45, $floor) * 0.98, 2);
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false];
    if ($action === 'start') {
        $bet = (float) ($_POST['bet'] ?? 0);
        if ($bet <= 0 || $bet > $money) {
            $response['message'] = "gtlm cược không hợp lệ!";
        } else {
            $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");
            $traps = [];
            for ($i = 0; $i < 10; $i++)
                $traps[$i] = rand(0, 2);
            $_SESSION['tower_game'] = ['bet' => $bet, 'traps' => $traps, 'currentFloor' => 0, 'status' => 'active'];
            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
            $response = ['success' => true, 'money' => number_format($newMoney, 0, ',', '.')];
        }
    } elseif ($action === 'pick') {
        $tileIdx = (int) ($_POST['tile'] ?? -1);
        if (!isset($_SESSION['tower_game']) || $_SESSION['tower_game']['status'] !== 'active' || $tileIdx < 0 || $tileIdx > 2) {
            $response['message'] = "Phiên chơi không hợp lệ!";
        } else {
            $game = &$_SESSION['tower_game'];
            $floor = $game['currentFloor'];
            if ($tileIdx === $game['traps'][$floor]) {
                $game['status'] = 'lost';
                $bet = $game['bet'];
                $resStr = "Lost at Floor $floor";
                $his = $conn->prepare("INSERT INTO history_tower (Iduser,Bet,Result,WinAmount,Time) VALUES (?,?,?,?,NOW())");
                $negBet = -$bet;
                $his->bind_param("idss", $userId, $bet, $resStr, $negBet);
                $his->execute();
                logGameHistoryWithAll($conn, $userId, 'Tower', $bet, 0, false);
                $response = ['success' => true, 'hit' => true, 'trap' => $game['traps'][$floor]];
                unset($_SESSION['tower_game']);
            } else {
                $game['currentFloor']++;
                if ($game['currentFloor'] >= 10) {
                    $mult = getTowerMultiplier(10);
                    $winAmount = round($game['bet'] * $mult);
                    $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $userId");
                    $resStr = "Win Tower Max x$mult";
                    $profit = $winAmount - $game['bet'];
                    $his = $conn->prepare("INSERT INTO history_tower (Iduser,Bet,Result,WinAmount,Time) VALUES (?,?,?,?,NOW())");
                    $his->bind_param("idss", $userId, $game['bet'], $resStr, $profit);
                    $his->execute();
                    logGameHistoryWithAll($conn, $userId, 'Tower', $game['bet'], $winAmount, true);
                    $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
                    $response = ['success' => true, 'hit' => false, 'floor' => $game['currentFloor'], 'winAmount' => number_format($winAmount, 0, ',', '.'), 'money' => number_format($newMoney, 0, ',', '.'), 'max' => true];
                    unset($_SESSION['tower_game']);
                } else {
                    $nextMult = getTowerMultiplier($game['currentFloor']);
                    $response = ['success' => true, 'hit' => false, 'floor' => $game['currentFloor'], 'multiplier' => $nextMult];
                }
            }
        }
    } elseif ($action === 'cashout') {
        if (!isset($_SESSION['tower_game']) || $_SESSION['tower_game']['status'] !== 'active') {
            $response['message'] = "Không có gtlm để rút!";
        } else {
            $game = $_SESSION['tower_game'];
            $floor = $game['currentFloor'];
            if ($floor == 0) {
                $response['message'] = "Hãy leo ít nhất 1 tầng!";
            } else {
                $mult = getTowerMultiplier($floor);
                $winAmount = round($game['bet'] * $mult);
                $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $userId");
                $resStr = "Cashout Floor $floor x$mult";
                $profit = $winAmount - $game['bet'];
                $his = $conn->prepare("INSERT INTO history_tower (Iduser,Bet,Result,WinAmount,Time) VALUES (?,?,?,?,NOW())");
                $his->bind_param("idss", $userId, $game['bet'], $resStr, $profit);
                $his->execute();
                logGameHistoryWithAll($conn, $userId, 'Tower', $game['bet'], $winAmount, true);
                $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
                $response = ['success' => true, 'winAmount' => number_format($winAmount, 0, ',', '.'), 'money' => number_format($newMoney, 0, ',', '.')];
                unset($_SESSION['tower_game']);
            }
        }
    }
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Tower of Light Premium - Vegas</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;500;700&display=swap"
        rel="stylesheet">
    <?php echo getCSSIncludes(['special_effects' => true]); ?>
    <style>
        :root {
            --primary: #f39c12;
            --accent: #f1c40f;
            --glass: rgba(255, 255, 255, 0.08);
        }

        body {
            margin: 0;
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Inter', sans-serif;
            overflow: hidden;
            cursor: url('../img/chuot.png'), auto !important;
        }

        .main-container {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 2rem;
            padding: 1.5rem;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.6);
            width: 95%;
            max-width: 1100px;
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 1.5rem;
            max-height: 92vh;
            align-self: center;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .tower-area {
            position: relative;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            overflow-y: auto;
            padding: 40px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            scroll-behavior: smooth;
        }

        .tower-area::-webkit-scrollbar {
            width: 0;
        }

        .tower-grid {
            display: flex;
            flex-direction: column-reverse;
            gap: 12px;
            width: 400px;
        }

        .floor {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            opacity: 0.3;
            transition: 0.3s;
            padding: 10px;
            border-radius: 15px;
        }

        .floor.active {
            opacity: 1;
            background: rgba(243, 156, 18, 0.1);
            border: 1px solid rgba(243, 156, 18, 0.3);
            box-shadow: 0 0 30px rgba(243, 156, 18, 0.1);
        }

        .floor.completed {
            opacity: 0.6;
            filter: grayscale(0.5);
        }

        .tile {
            height: 50px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            position: relative;
            transform-style: preserve-3d;
            perspective: 500px;
        }

        .active .tile:hover {
            transform: translateY(-5px) scale(1.05);
            background: rgba(243, 156, 18, 0.2);
            border-color: var(--primary);
            box-shadow: 0 10px 20px rgba(243, 156, 18, 0.3);
        }

        .tile.safe {
            background: linear-gradient(135deg, #2ecc71, #27ae60) !important;
            box-shadow: 0 0 25px #2ecc71;
            border: none;
            color: #fff;
        }

        .tile.trap {
            background: linear-gradient(135deg, #e74c3c, #c0392b) !important;
            box-shadow: 0 0 25px #e74c3c;
            border: none;
            color: #fff;
        }

        .input-group {
            background: rgba(0, 0, 0, 0.4);
            padding: 0.8rem 1.2rem;
            border-radius: 1.2rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .input-group label {
            display: block;
            font-size: 0.6rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 5px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .input-group input {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.2rem;
            font-weight: 900;
            width: 100%;
            outline: none;
            font-family: 'Orbitron';
        }

        .btn-action {
            padding: 1.2rem;
            border-radius: 1.5rem;
            border: none;
            font-weight: 900;
            font-size: 1.3rem;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary), #e67e22);
            color: #fff;
            box-shadow: 0 10px 30px rgba(243, 156, 18, 0.3);
        }

        .btn-action:hover:not(:disabled) {
            transform: translateY(-3px);
            filter: brightness(1.1);
        }

        #cashoutBtn {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            box-shadow: 0 10px 30px rgba(46, 204, 113, 0.3);
            display: none;
        }

        .multiplier-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column-reverse;
            gap: 5px;
            max-height: 250px;
            overflow-y: auto;
            padding-right: 5px;
        }

        .multiplier-list::-webkit-scrollbar {
            width: 3px;
        }

        .multiplier-list::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .mult-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            transition: 0.3s;
        }

        .mult-item.active {
            background: var(--primary);
            color: #000;
            transform: scale(1.05);
            box-shadow: 0 0 15px var(--primary);
        }

        .stat-card {
            background: rgba(0, 0, 0, 0.2);
            padding: 0.8rem;
            border-radius: 1.2rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            text-align: center;
        }

        .stat-card span {
            display: block;
            font-size: 0.6rem;
            opacity: 0.5;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .stat-card b {
            font-size: 1.2rem;
            font-family: 'Orbitron';
            color: var(--accent);
        }
    </style>
</head>

<body>
    <div class="main-container">
        <div class="glass-card">
            <div class="sidebar">
                <div style="margin-bottom: 0.5rem;">
                    <h1
                        style="margin:0; font-size: 2.2rem; font-weight: 900; color: var(--primary); font-family: 'Orbitron'; letter-spacing: 2px;">
                        TOWER</h1>
                    <p style="margin:0; opacity:0.4; font-size: 0.75rem; letter-spacing: 1px;">Royal Golden Climb</p>
                </div>

                <div class="input-group">
                    <label>Gtlm cược (gtlm)</label>
                    <input type="number" id="betAmount" value="10000" min="1000" step="5000">
                    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:5px; margin-top:8px;">
                        <button onclick="adjBet(0.5)"
                            style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:8px; padding:5px; font-size:0.6rem; cursor:pointer; font-weight:800;">1/2</button>
                        <button onclick="adjBet(2)"
                            style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:8px; padding:5px; font-size:0.6rem; cursor:pointer; font-weight:800;">x2</button>
                        <button onclick="adjBet('max')"
                            style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:8px; padding:5px; font-size:0.6rem; cursor:pointer; font-weight:800;">MAX</button>
                    </div>
                </div>

                <div class="multiplier-list">
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                        <div class="mult-item" id="mult-<?= $i ?>">
                            <span>Tầng <?= $i ?></span>
                            <b>x<?= getTowerMultiplier($i) ?></b>
                        </div>
                    <?php endfor; ?>
                </div>

                <button id="startBtn" class="btn-action" onclick="startGame()">🚀 BẮT ĐẦU LEO</button>
                <button id="cashoutBtn" class="btn-action" onclick="cashout()">💰 RÚT Gtlm (x<span
                        id="curMult">1.0</span>)</button>

                <div style="margin-top:auto; padding-top:1rem; border-top:1px solid rgba(255,255,255,0.1);">
                    <div class="stat-card">
                        <span>Số Gtlm HIỆN TẠI</span>
                        <div style="display:flex; align-items:baseline; justify-content:center; gap:5px;">
                            <b id="userMoney"><?= number_format($money, 0, ',', '.') ?></b>
                            <small style="opacity:0.5; font-weight:900; font-size:0.6rem;">GTLM</small>
                        </div>
                    </div>
                    <div style="text-align: center; margin-top: 0.8rem;">
                        <a href="../index.php"
                            style="color: #fff; text-decoration: none; font-size: 0.75rem; opacity: 0.3;">← Quay về
                            Dashboard</a>
                    </div>
                </div>
            </div>

            <div class="tower-area" id="towerContainer">
                <div class="tower-grid">
                    <?php for ($i = 0; $i < 10; $i++): ?>
                        <div class="floor" id="floor-<?= $i ?>">
                            <div class="tile" onclick="pickTile(<?= $i ?>, 0)">?</div>
                            <div class="tile" onclick="pickTile(<?= $i ?>, 1)">?</div>
                            <div class="tile" onclick="pickTile(<?= $i ?>, 2)">?</div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        let isGameRunning = false, currentFloor = 0;

        function adjBet(ratio) {
            const cur = parseFloat($('#betAmount').val()) || 0;
            const money = parseFloat($('#userMoney').text().replace(/\./g, ''));
            if (ratio === 'max') $('#betAmount').val(money);
            else $('#betAmount').val(Math.floor(cur * ratio));
        }

        function startGame() {
            const bet = $('#betAmount').val();
            $.post('tower.php?action=start', { bet }, function (res) {
                if (res.success) {
                    isGameRunning = true; currentFloor = 0;
                    $('#userMoney').text(res.money);
                    $('#startBtn').hide(); $('#cashoutBtn').show();
                    $('#betAmount').prop('disabled', true);
                    $('.floor').removeClass('active completed');
                    $('.tile').removeClass('safe trap').text('?');
                    $('.mult-item').removeClass('active');
                    activateFloor(0);
                } else { Swal.fire('Lỗi', res.message, 'error'); }
            });
        }

        function activateFloor(f) {
            $('.floor').removeClass('active');
            const el = document.getElementById('floor-' + f);
            if (el) {
                el.classList.add('active');
                // Center the active floor
                const container = document.getElementById('towerContainer');
                container.scrollTo({ top: el.offsetTop - container.offsetHeight / 2 + 25, behavior: 'smooth' });
            }
        }

        function pickTile(floor, idx) {
            if (!isGameRunning || floor !== currentFloor) return;
            $.post('tower.php?action=pick', { tile: idx }, function (res) {
                if (res.success) {
                    const floorEl = $(`#floor-${floor}`);
                    const tileEl = floorEl.find('.tile').eq(idx);

                    if (res.hit) {
                        tileEl.addClass('trap').text('💥');
                        isGameRunning = false;
                        if (window.GameEffects) window.GameEffects.showLoss(0);
                        setTimeout(() => {
                            Swal.fire({ title: '💥 RƠI THÁP!', text: 'Bạn đã trúng bẫy!', icon: 'error', background: '#1a1a1a', color: '#fff' });
                            resetGameUI();
                        }, 800);
                    } else {
                        tileEl.addClass('safe').text('💎');
                        floorEl.addClass('completed');
                        currentFloor++;
                        $('.mult-item').removeClass('active');
                        const curMult = $(`#mult-${currentFloor}`).addClass('active').find('b').text().replace('x', '');
                        $('#curMult').text(curMult);

                        if (res.max) {
                            isGameRunning = false;
                            const rawWin = parseInt((res.winAmount + '').replace(/[^0-9]/g, '')) || 0;
                            if (window.GameEffects) window.GameEffects.showBigWin(rawWin);
                            Swal.fire({ title: '🏆 KING OF TOWER!', html: `Leo đỉnh thành công! Thắng <b style="color:#f1c40f">${res.winAmount} gtlm</b>`, icon: 'success', background: '#1a1a1a', color: '#fff' });
                            $('#userMoney').text(res.money);
                            resetGameUI();
                        } else {
                            activateFloor(currentFloor);
                        }
                    }
                }
            });
        }

        function cashout() {
            if (!isGameRunning || currentFloor === 0) return;
            $.post('tower.php?action=cashout', function (res) {
                if (res.success) {
                    isGameRunning = false;
                    $('#userMoney').text(res.money);
                    const rawWin = parseInt((res.winAmount + '').replace(/[^0-9]/g, '')) || 0;
                    if (window.GameEffects) {
                        if (currentFloor >= 5) window.GameEffects.showBigWin(rawWin);
                        else window.GameEffects.showWin(rawWin);
                    }
                    Swal.fire({ title: '💰 RÚT Gtlm THÀNH CÔNG!', html: `Leo tầng ${currentFloor} → nhận <b style="color:#2ecc71">${res.winAmount} gtlm</b>`, icon: 'success', background: '#1a1a1a', color: '#fff' });
                    resetGameUI();
                }
            });
        }

        function resetGameUI() {
            $('#startBtn').show(); $('#cashoutBtn').hide();
            $('#betAmount').prop('disabled', false);
            $('#curMult').text('1.0');
        }
    </script>

    <canvas id="threejs-background"></canvas>
    <script>
        (function () {
            window.themeConfig = {
                particleCount: 600,
                particleSize: 0.05,
                particleColor: '#f39c12',
                particleOpacity: 0.5,
                shapeCount: 10,
                shapeColors: ["#f39c12", "#e67e22", "#f1c40f"],
                shapeOpacity: 0.2,
                bgGradient: ["#1a1a1a", "#2c3e50", "#000000"]
            };
            const prefix = '../';
            ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'].forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src; s.async = false;
                document.head.appendChild(s);
            });
        })();
    </script>
</body>

</html>