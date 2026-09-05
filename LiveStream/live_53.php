<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_53', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

include '../db_connect.php';
require_once '../include_css.php';
$useBotTheme = $botUserId;
include '../load_theme.php';
require_once '../game_history_helper.php';
if (!isset($botUserId)) {
    header('Location: ../login.php');
    exit;
}
$userId = $botUserId;
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$userName = $user['Name'];
$stmt->close();
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
            $traps = [];
            for ($i = 0; $i < 10; $i++)
                $traps[$i] = rand(0, 2);
            $_SESSION['tower_game'] = ['bet' => $bet, 'traps' => $traps, 'currentFloor' => 0, 'status' => 'active'];
            $conn->commit();
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
                    $conn->commit();
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
                $conn->commit();
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
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Inter', sans-serif;
            overflow: hidden;
            cursor: url('../img/chuot.png'), auto !important;
        }
        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        /* 🏆 Badge Thông Báo Thắng / Thua Chuẩn Game ID 1 */
        #result-status-badge {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.5);
            background: rgba(10, 12, 24, 0.94);
            border-radius: 24px;
            padding: 24px 48px;
            text-align: center;
            z-index: 99999;
            pointer-events: none;
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 2px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.85);
            font-family: 'Orbitron', 'Inter', sans-serif;
            transition: transform 0.4s cubic-bezier(0.17, 0.89, 0.32, 1.49), opacity 0.4s;
            opacity: 0;
        }
        #result-badge-icon {
            font-size: 3.5rem;
            margin-bottom: 6px;
            animation: badgeIconBounce 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
        }
        @keyframes badgeIconBounce {
            0% { transform: scale(0) rotate(-20deg); }
            70% { transform: scale(1.3) rotate(10deg); }
            100% { transform: scale(1) rotate(0deg); }
        }
        #result-badge-title {
            font-size: 1.6rem;
            font-weight: 900;
            letter-spacing: 2px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        #result-badge-amount {
            font-size: 1.3rem;
            font-weight: 800;
            opacity: 0.95;
            font-family: 'Orbitron', sans-serif;
        }
        #result-badge-msg {
            font-size: 0.82rem;
            opacity: 0.75;
            margin-top: 6px;
            max-width: 320px;
            font-family: 'Inter', sans-serif;
        }

        .main-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 15px;
            box-sizing: border-box;
            position: relative;
            z-index: 1;
        }
        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1.6rem;
            padding: 1rem 1.2rem;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.6);
            width: 96%;
            max-width: 860px;
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 1rem;
            max-height: 94vh;
            align-self: center;
        }
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .tower-area {
            position: relative;
            width: 100%;
            height: 100%;
            max-height: 480px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 1.4rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            overflow-y: auto;
            padding: 20px 0;
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
            gap: 6px;
            width: 300px;
        }
        .floor {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
            opacity: 0.3;
            transition: 0.3s;
            padding: 5px;
            border-radius: 10px;
        }
        .floor.active {
            opacity: 1;
            background: rgba(243, 156, 18, 0.1);
            border: 1px solid rgba(243, 156, 18, 0.3);
            box-shadow: 0 0 25px rgba(243, 156, 18, 0.1);
        }
        .floor.completed {
            opacity: 0.6;
            filter: grayscale(0.5);
        }
        .tile {
            height: 38px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            position: relative;
            transform-style: preserve-3d;
            perspective: 500px;
        }
        .active .tile:hover {
            transform: translateY(-3px) scale(1.04);
            background: rgba(243, 156, 18, 0.2);
            border-color: var(--primary);
            box-shadow: 0 8px 16px rgba(243, 156, 18, 0.3);
        }
        .tile.safe {
            background: linear-gradient(135deg, #2ecc71, #27ae60) !important;
            box-shadow: 0 0 20px #2ecc71;
            border: none;
            color: #fff;
        }
        .tile.trap {
            background: linear-gradient(135deg, #e74c3c, #c0392b) !important;
            box-shadow: 0 0 20px #e74c3c;
            border: none;
            color: #fff;
        }
        .input-group {
            background: rgba(0, 0, 0, 0.4);
            padding: 0.45rem 0.8rem;
            border-radius: 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .input-group label {
            display: block;
            font-size: 0.58rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 3px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .input-group input {
            background: none;
            border: none;
            color: #fff;
            font-size: 1rem;
            font-weight: 900;
            width: 100%;
            outline: none;
            font-family: 'Orbitron';
        }
        .btn-action {
            padding: 0.65rem;
            border-radius: 1rem;
            border: none;
            font-weight: 900;
            font-size: 0.95rem;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary), #e67e22);
            color: #fff;
            box-shadow: 0 6px 20px rgba(243, 156, 18, 0.3);
        }
        .btn-action:hover:not(:disabled) {
            transform: translateY(-2px);
            filter: brightness(1.1);
        }
        #cashoutBtn {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            box-shadow: 0 6px 20px rgba(46, 204, 113, 0.3);
            display: none;
        }
        .multiplier-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column-reverse;
            gap: 3px;
            max-height: 120px;
            overflow-y: auto;
            padding-right: 4px;
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
            padding: 3px 8px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 6px;
            font-size: 0.68rem;
            font-weight: 700;
            transition: 0.3s;
        }
        .mult-item.active {
            background: var(--primary);
            color: #000;
            transform: scale(1.02);
            box-shadow: 0 0 10px var(--primary);
        }
        .stat-card {
            background: rgba(0, 0, 0, 0.2);
            padding: 0.45rem;
            border-radius: 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            text-align: center;
        }
        .stat-card span {
            display: block;
            font-size: 0.55rem;
            opacity: 0.5;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .stat-card b {
            font-size: 1rem;
            font-family: 'Orbitron';
            color: var(--accent);
        }
        .btn-quick-bet {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 4px;
            border-radius: 6px;
            cursor: url('../img/tay.png'), pointer !important;
            font-weight: 600;
            transition: 0.3s;
            font-size: 0.7rem;
        }
        .btn-quick-bet:hover {
            background: var(--primary);
            color: #000;
            border-color: var(--primary);
        }
    </style>
</head>
<body>
    <!-- 🏆 Badge thông báo thắng/thua giống game ID 1 -->
    <div id="result-status-badge">
        <div id="result-badge-icon">🏆</div>
        <div id="result-badge-title">THẮNG LEO THÁP!</div>
        <div id="result-badge-amount">+100,000 GTLM</div>
        <div id="result-badge-msg">Rút thành công tầng 5!</div>
    </div>

    <div class="main-container">
        <div class="glass-card">
            <div class="sidebar">
                <div style="margin-bottom: 0.2rem;">
                    <h1 style="margin:0; font-size: 1.5rem; font-weight: 900; color: var(--primary); font-family: 'Orbitron'; letter-spacing: 2px;">TOWER</h1>
                    <p style="margin:0; opacity:0.4; font-size: 0.7rem; letter-spacing: 1px;">Royal Golden Climb</p>
                </div>
                <div class="input-group">
                    <label>Gtlm cược (gtlm)</label>
                    <input type="number" id="betAmount" value="10000" min="1000">
                    <div class="quick-bets" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; margin-top: 6px;">
                        <button class="btn-quick-bet" onclick="setBet(10000)">10K</button>
                        <button class="btn-quick-bet" onclick="setBet(50000)">50K</button>
                        <button class="btn-quick-bet" onclick="setBet(100000)">100K</button>
                        <button class="btn-quick-bet" onclick="setBet(500000)">500K</button>
                        <button class="btn-quick-bet" onclick="setBet(1000000)">1M</button>
                        <button class="btn-quick-bet" onclick="setBet(5000000)">5M</button>
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
                <button id="cashoutBtn" class="btn-action" onclick="cashout()">💰 RÚT GTLM (x<span id="curMult">1.0</span>)</button>
                <div style="margin-top:auto; padding-top:0.4rem; border-top:1px solid rgba(255,255,255,0.1);">
                    <div class="stat-card">
                        <span>Số GTLM HIỆN TẠI</span>
                        <div style="display:flex; align-items:baseline; justify-content:center; gap:4px;">
                            <b id="userMoney"><?= number_format($money, 0, ',', '.') ?></b>
                            <small style="opacity:0.5; font-weight:900; font-size:0.55rem;">GTLM</small>
                        </div>
                    </div>
                    <div style="text-align: center; margin-top: 0.5rem;">
                        <a href="../index.php" style="color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.2); padding: 0.35rem 1.2rem; border-radius: 50px; font-size: 0.72rem; font-weight: bold; background: rgba(0,0,0,0.2); transition: 0.3s; display: inline-block;">🏠 THOÁT VỀ SẢNH</a>
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

        function showResultBadge(type, title, amount, msg) {
            const badge = $('#result-status-badge');
            const icon = $('#result-badge-icon');
            const titleEl = $('#result-badge-title');
            const amountEl = $('#result-badge-amount');
            const msgEl = $('#result-badge-msg');

            if (type === 'win') {
                icon.text('🏆');
                titleEl.text(title || 'HÚP GTLM THÀNH CÔNG!').css('color', '#f1c40f');
                amountEl.text(amount).css('color', '#4ade80');
                badge.css({
                    'border-color': 'rgba(241, 196, 15, 0.7)',
                    'box-shadow': '0 0 50px rgba(241, 196, 15, 0.35)'
                });
            } else {
                icon.text('💨');
                titleEl.text(title || 'BAY MÀU LEO THÁP!').css('color', '#ff4757');
                amountEl.text(amount).css('color', '#ff4757');
                badge.css({
                    'border-color': 'rgba(239, 68, 68, 0.7)',
                    'box-shadow': '0 0 50px rgba(239, 68, 68, 0.35)'
                });
            }

            msgEl.text(msg || '');
            badge.stop(true, true).css({ display: 'block', opacity: 0, transform: 'translate(-50%, -50%) scale(0.6)' });
            
            setTimeout(() => {
                badge.css({ opacity: 1, transform: 'translate(-50%, -50%) scale(1)' });
            }, 10);

            setTimeout(() => {
                badge.css({ opacity: 0, transform: 'translate(-50%, -50%) scale(0.7)' });
                setTimeout(() => { badge.hide(); }, 400);
            }, 3500);
        }

        function setBet(amount) {
            const money = parseFloat($('#userMoney').text().replace(/\./g, ''));
            if (amount === 'ALLIN') {
                $('#betAmount').val(money);
            } else {
                $('#betAmount').val(amount);
            }
        }

        function startGame() {
            const bet = $('#betAmount').val();
            $.post('?action=start', { bet }, function (res) {
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
            $.post('?action=pick', { tile: idx }, function (res) {
                if (res.success) {
                    const floorEl = $(`#floor-${floor}`);
                    const tileEl = floorEl.find('.tile').eq(idx);
                    if (res.hit) {
                        tileEl.addClass('trap').text('💥');
                        isGameRunning = false;
                        const betAmt = parseInt($('#betAmount').val()) || 0;
                        if (window.GameEffects) window.GameEffects.showLoss(betAmt);
                        showResultBadge('loss', 'BAY MÀU LEO THÁP!', '-' + Number(betAmt).toLocaleString('vi-VN') + ' GTLM', 'Dẫm phải bẫy tầng ' + (floor + 1) + '!');
                        setTimeout(() => {
                            resetGameUI();
                        }, 1200);
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
                            $('#userMoney').text(res.money);
                            showResultBadge('win', 'ĐỈNH CAO HOÀNG GIA!', '+' + res.winAmount + ' GTLM', 'Chinh phục trọn vẹn 10 tầng tháp!');
                            setTimeout(() => {
                                resetGameUI();
                            }, 1200);
                        } else {
                            activateFloor(currentFloor);
                        }
                    }
                }
            });
        }

        function cashout() {
            if (!isGameRunning || currentFloor === 0) return;
            $.post('?action=cashout', function (res) {
                if (res.success) {
                    isGameRunning = false;
                    $('#userMoney').text(res.money);
                    const rawWin = parseInt((res.winAmount + '').replace(/[^0-9]/g, '')) || 0;
                    if (window.GameEffects) {
                        if (currentFloor >= 5) window.GameEffects.showBigWin(rawWin);
                        else window.GameEffects.showWin(rawWin);
                    }
                    showResultBadge('win', 'HÚP GTLM THÀNH CÔNG!', '+' + res.winAmount + ' GTLM', 'Rút an toàn tại tầng ' + currentFloor + ' (x' + $('#curMult').text() + ')!');
                    setTimeout(() => {
                        resetGameUI();
                    }, 1000);
                }
            });
        }

        function resetGameUI() {
            $('#startBtn').show(); $('#cashoutBtn').hide();
            $('#betAmount').prop('disabled', false);
            $('#curMult').text('1.0');
        }
    </script>

    <!-- 🌌 Nền ThreeJS 3D Vũ Trụ Hoàng Gia -->
    <canvas id="threejs-background"></canvas>
    <script>
        window.themeConfig = {
            particleCount: <?= $particleCount ?? 800 ?>,
            particleSize: <?= $particleSize ?? 0.05 ?>,
            particleColor: '<?= $particleColor ?? "#00ff88" ?>',
            particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
            shapeCount: <?= $shapeCount ?? 15 ?>,
            shapeColors: <?= json_encode($shapeColors ?? ["#00ff88", "#00b894", "#fdcb6e", "#f1c40f"]) ?>,
            shapeOpacity: <?= $shapeOpacity ?? 0.35 ?>,
            bgGradient: <?= json_encode($bgGradient ?? ["#000000", "#001a11", "#002a1b"]) ?>
        };
    </script>
    <script src="../threejs-background.js"></script>
    <script src="../assets/js/game-effects.js"></script>
    <script src="../assets/js/game-effects-auto.js"></script>

    <!-- 🤖 Nạp Bot AI Chuyên Nghiệp Thần Leo Tháp 53 -->
    <script src="../assets/js/bot_chat.js"></script>
    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="bots/bot_53.js"></script>

</body>
</html>