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

$conn->query("CREATE TABLE IF NOT EXISTS history_mines (Id INT AUTO_INCREMENT PRIMARY KEY, Iduser INT NOT NULL, Bet DECIMAL(30,2) NOT NULL, Result VARCHAR(255) NOT NULL, WinAmount DECIMAL(30,2) NOT NULL, Time DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

function getMinesMultiplier($mines, $revealed)
{
    if ($revealed <= 0)
        return 1.0;
    $total = 25;
    $safe = $total - $mines;
    $prob = 1.0;
    for ($i = 0; $i < $revealed; $i++)
        $prob *= ($safe - $i) / ($total - $i);
    return round((1.0 / $prob) * 0.97, 2); // 3% House Edge for premium
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false];
    if ($action === 'start') {
        $bet = (float) ($_POST['bet'] ?? 0);
        $minesCount = (int) ($_POST['mines'] ?? 3);
        if ($bet <= 0 || $bet > $money || $minesCount < 1 || $minesCount > 24) {
            $response['message'] = "Yêu cầu không hợp lệ!";
        } else {
            $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");
            $allTiles = range(0, 24);
            shuffle($allTiles);
            $mines = array_slice($allTiles, 0, $minesCount);
            $_SESSION['mines_game'] = ['bet' => $bet, 'mines' => $mines, 'revealed' => [], 'minesCount' => $minesCount, 'status' => 'active'];
            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
            $response = ['success' => true, 'money' => number_format($newMoney, 0, ',', '.')];
        }
    } elseif ($action === 'reveal') {
        $index = (int) ($_POST['index'] ?? -1);
        if (!isset($_SESSION['mines_game']) || $_SESSION['mines_game']['status'] !== 'active' || $index < 0 || $index > 24) {
            $response['message'] = "Phiên chơi không hợp lệ!";
        } else {
            $game = &$_SESSION['mines_game'];
            if (in_array($index, $game['mines'])) {
                $game['status'] = 'lost';
                $bet = $game['bet'];
                $resStr = "Lost at tile $index | Mines: " . $game['minesCount'];
                $his = $conn->prepare("INSERT INTO history_mines (Iduser,Bet,Result,WinAmount,Time) VALUES (?,?,?,?,NOW())");
                $negBet = -$bet;
                $his->bind_param("idss", $userId, $bet, $resStr, $negBet);
                $his->execute();
                logGameHistoryWithAll($conn, $userId, 'Mines', $bet, 0, false);
                $response = ['success' => true, 'hit' => true, 'mines' => $game['mines']];
                unset($_SESSION['mines_game']);
            } else {
                if (!in_array($index, $game['revealed']))
                    $game['revealed'][] = $index;
                $nextMult = getMinesMultiplier($game['minesCount'], count($game['revealed']));
                $response = ['success' => true, 'hit' => false, 'multiplier' => $nextMult];
            }
        }
    } elseif ($action === 'cashout') {
        if (!isset($_SESSION['mines_game']) || $_SESSION['mines_game']['status'] !== 'active') {
            $response['message'] = "Không có gtlm để rút!";
        } else {
            $game = $_SESSION['mines_game'];
            $revealedCount = count($game['revealed']);
            if ($revealedCount == 0) {
                $response['message'] = "Hãy lật ít nhất 1 ô!";
            } else {
                $mult = getMinesMultiplier($game['minesCount'], $revealedCount);
                $winAmount = round($game['bet'] * $mult);
                $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $userId");
                $resStr = "Win x$mult | Tiles: $revealedCount";
                $profit = $winAmount - $game['bet'];
                $his = $conn->prepare("INSERT INTO history_mines (Iduser,Bet,Result,WinAmount,Time) VALUES (?,?,?,?,NOW())");
                $his->bind_param("idss", $userId, $game['bet'], $resStr, $profit);
                $his->execute();
                logGameHistoryWithAll($conn, $userId, 'Mines', $game['bet'], $winAmount, true);
                $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
                $response = ['success' => true, 'winAmount' => number_format($winAmount, 0, ',', '.'), 'money' => number_format($newMoney, 0, ',', '.'), 'mines' => $game['mines']];
                unset($_SESSION['mines_game']);
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
    <title>Laser Mines Premium - Vegas Royale</title>
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
            --primary: #12c2e9;
            --accent: #f1c40f;
            --danger: #ff4757;
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

        .grid-area {
            position: relative;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            border-radius: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .mines-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            width: 100%;
            max-width: 500px;
            aspect-ratio: 1;
        }

        .tile {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            position: relative;
            overflow: hidden;
            transform-style: preserve-3d;
        }

        .tile:hover:not(.revealed) {
            transform: translateY(-5px) scale(1.05);
            background: rgba(18, 194, 233, 0.1);
            border-color: var(--primary);
            box-shadow: 0 10px 20px rgba(18, 194, 233, 0.2);
        }

        .tile.revealed {
            cursor: default;
            border: none;
        }

        .tile.safe {
            background: linear-gradient(135deg, #12c2e9, #c471ed) !important;
            box-shadow: 0 0 20px rgba(18, 194, 233, 0.6);
        }

        .tile.mine {
            background: linear-gradient(135deg, #ff4757, #ff6b81) !important;
            box-shadow: 0 0 20px rgba(255, 71, 87, 0.6);
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

        .input-group input,
        .input-group select {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.2rem;
            font-weight: 900;
            width: 100%;
            outline: none;
            font-family: 'Orbitron';
            color: var(--primary);
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
            background: linear-gradient(135deg, var(--primary), #c471ed);
            color: #fff;
            box-shadow: 0 10px 30px rgba(18, 194, 233, 0.3);
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
            font-size: 1.4rem;
            font-family: 'Orbitron';
            color: var(--accent);
        }

        .mult-bar {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            width: 100%;
            max-width: 500px;
            margin-bottom: 20px;
            padding: 10px 0;
            scrollbar-width: none;
        }

        .mult-step {
            min-width: 80px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            text-align: center;
            font-size: 0.8rem;
            font-weight: 900;
            opacity: 0.5;
            transition: 0.3s;
        }

        .mult-step.active {
            opacity: 1;
            background: var(--primary);
            color: #000;
            transform: scale(1.1);
            box-shadow: 0 0 15px var(--primary);
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
                        MINES</h1>
                    <p style="margin:0; opacity:0.4; font-size: 0.75rem; letter-spacing: 1px;">Cyber Laser Protocol</p>
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

                <div class="input-group">
                    <label>Số lượng mìn</label>
                    <select id="minesCount" onchange="updateMultBar()">
                        <?php for ($i = 1; $i <= 24; $i++)
                            echo "<option value='$i' " . ($i == 3 ? 'selected' : '') . ">$i Mìn</option>"; ?>
                    </select>
                </div>

                <div class="stat-card">
                    <span>LỢI NHUẬN TIỀM NĂNG</span>
                    <div style="display:flex; align-items:baseline; justify-content:center; gap:5px;">
                        <b id="potentialWin">0</b>
                        <small style="opacity:0.5; font-weight:900; font-size:0.6rem;">GTLM</small>
                    </div>
                </div>

                <button id="startBtn" class="btn-action" onclick="startGame()">🚀 BẮT ĐẦU DÒ</button>
                <button id="cashoutBtn" class="btn-action" onclick="cashout()">💰 RÚT (x<span
                        id="curMult">1.0</span>)</button>

                <div style="margin-top:auto; padding-top:1rem; border-top:1px solid rgba(255,255,255,0.1);">
                    <div class="stat-card" style="background:rgba(0,0,0,0.4)">
                        <span>Số Gtlm HIỆN TẠI</span>
                        <div style="display:flex; align-items:baseline; justify-content:center; gap:5px;">
                            <b id="userMoney" style="color:var(--accent)"><?= number_format($money, 0, ',', '.') ?></b>
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

            <div class="grid-area">
                <div class="mult-bar" id="multBar"></div>
                <div class="mines-grid" id="minesGrid">
                    <?php for ($i = 0; $i < 25; $i++): ?>
                        <div class="tile" data-index="<?= $i ?>" onclick="revealTile(<?= $i ?>)">?</div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        let isGameActive = false, revealedCount = 0;

        function adjBet(ratio) {
            const cur = parseFloat($('#betAmount').val()) || 0;
            const money = parseFloat($('#userMoney').text().replace(/\./g, ''));
            if (ratio === 'max') $('#betAmount').val(money);
            else $('#betAmount').val(Math.floor(cur * ratio));
        }

        function getMultJS(mines, revealed) {
            if (revealed <= 0) return 1.0;
            let total = 25, safe = total - mines, prob = 1.0;
            for (let i = 0; i < revealed; i++) prob *= (safe - i) / (total - i);
            return (1.0 / prob * 0.97).toFixed(2);
        }

        function updateMultBar() {
            const mines = parseInt($('#minesCount').val());
            const bar = $('#multBar').empty();
            for (let i = 1; i <= Math.min(10, 25 - mines); i++) {
                bar.append(`<div class="mult-step" id="step-${i}">${getMultJS(mines, i)}x</div>`);
            }
        }
        updateMultBar();

        function startGame() {
            const bet = $('#betAmount').val();
            const mines = $('#minesCount').val();
            $.post('mines.php?action=start', { bet, mines }, function (res) {
                if (res.success) {
                    isGameActive = true; revealedCount = 0;
                    $('#userMoney').text(res.money);
                    $('#startBtn').hide(); $('#cashoutBtn').show().prop('disabled', true);
                    $('#minesCount, #betAmount').prop('disabled', true);
                    $('.tile').removeClass('revealed safe mine').text('?');
                    $('.mult-step').removeClass('active');
                    $('#curMult').text('1.0');
                    $('#potentialWin').text('0');
                } else { Swal.fire('Lỗi', res.message, 'error'); }
            });
        }

        function revealTile(idx) {
            if (!isGameActive) return;
            const tile = $(`.tile[data-index="${idx}"]`);
            if (tile.hasClass('revealed')) return;

            $.post('mines.php?action=reveal', { index: idx }, function (res) {
                if (res.success) {
                    tile.addClass('revealed');
                    if (res.hit) {
                        isGameActive = false;
                        tile.addClass('mine').html('💣');
                        showAll(res.mines);
                        if (window.GameEffects) window.GameEffects.showLoss(0);
                        Swal.fire({ title: 'BÙM!', text: 'Bạn đã trúng mìn!', icon: 'error', background: '#1a1a1a', color: '#fff' });
                        resetUI();
                    } else {
                        revealedCount++;
                        tile.addClass('safe').html('💎');
                        gsap.from(tile, { scale: 0, duration: 0.4, ease: "back.out(2)" });

                        $('#curMult').text(res.multiplier);
                        const win = Math.floor($('#betAmount').val() * res.multiplier);
                        $('#potentialWin').text(win.toLocaleString('vi-VN'));
                        $('#cashoutBtn').prop('disabled', false);

                        $('.mult-step').removeClass('active');
                        $(`#step-${revealedCount}`).addClass('active');

                        if (window.GameEffects) window.GameEffects.towerSafe(tile[0]);
                    }
                }
            });
        }

        function cashout() {
            if (!isGameActive || revealedCount === 0) return;
            $.post('mines.php?action=cashout', function (res) {
                if (res.success) {
                    isGameActive = false;
                    $('#userMoney').text(res.money);
                    showAll(res.mines);
                    const win = parseInt((res.winAmount + '').replace(/\./g, '')) || 0;
                    if (window.GameEffects) {
                        if (revealedCount >= 5) window.GameEffects.showBigWin(win);
                        else window.GameEffects.showWin(win);
                    }
                    Swal.fire({ title: 'THẮNG LỚN!', html: `Bạn nhận được <b style="color:#f1c40f">${res.winAmount} gtlm</b>`, icon: 'success', background: '#1a1a1a', color: '#fff' });
                    resetUI();
                }
            });
        }

        function showAll(mines) {
            mines.forEach(m => {
                const t = $(`.tile[data-index="${m}"]`);
                if (!t.hasClass('revealed')) t.addClass('mine').html('💣').css('opacity', '0.4');
            });
            $('.tile:not(.revealed):not(.mine)').addClass('safe').html('💎').css('opacity', '0.4');
        }

        function resetUI() {
            $('#startBtn').show(); $('#cashoutBtn').hide();
            $('#minesCount, #betAmount').prop('disabled', false);
        }
    </script>

    <canvas id="threejs-background"></canvas>
    <script>
        (function () {
            window.themeConfig = {
                particleCount: 600,
                particleSize: 0.05,
                particleColor: '#12c2e9',
                particleOpacity: 0.5,
                shapeCount: 12,
                shapeColors: ["#12c2e9", "#c471ed", "#f1c40f"],
                shapeOpacity: 0.2,
                bgGradient: ["#000428", "#004e92", "#000000"]
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