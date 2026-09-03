<?php
session_start();

require '../db_connect.php'; // Đảm bảo $conn được load trước khi gọi bot_streamer_helper
require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_37', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require_once '../load_theme.php';

$userId = $botUserId;
// Auto-create history table
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$stmt->close();
function getTile()
{
    $types = ['dots', 'bamboo', 'chars', 'winds', 'dragons'];
    $type = $types[rand(0, 4)];
    if ($type === 'dots' || $type === 'bamboo' || $type === 'chars') {
        $val = rand(1, 9);
        $score = $val;
    } elseif ($type === 'winds') {
        $winds = ['E', 'S', 'W', 'N'];
        $v = rand(0, 3);
        $val = $winds[$v];
        $score = 10 + $v;
    } else {
        $dragons = ['Red', 'Green', 'White'];
        $v = rand(0, 2);
        $val = $dragons[$v];
        $score = 20 + $v;
    }
    return ['type' => $type, 'val' => $val, 'score' => $score, 'id' => $type . '_' . $val];
}
function evaluate($hand)
{
    usort($hand, function ($a, $b) {
        return $b['score'] - $a['score'];
    });
    $ids = array_column($hand, 'id');
    $counts = array_count_values($ids);
    arsort($counts);
    $vals = array_values($counts);
    if ($vals[0] == 3)
        return ['rank' => 3, 'name' => 'Triple', 'score' => 3000 + $hand[0]['score']];
    if ($vals[0] == 2)
        return ['rank' => 2, 'name' => 'Pair', 'score' => 2000 + $hand[0]['score']];
    return ['rank' => 1, 'name' => 'High Tile', 'score' => 1000 + $hand[0]['score']];
}
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    if ($action === 'play') {
        $bet = (int) ($_POST['bet'] ?? 0);
        if ($bet <= 0 || $bet > $money) {
            if ($money < 10000) {
                // Tự động nạp tiền cho bot streamer để đảm bảo luồng livestream 24/7 không bị dừng
                $conn->query("UPDATE users SET Money = 50000000 WHERE Iduser = " . (int)$userId);
                $money = 50000000;
            } else {
                echo json_encode(['success' => false, 'message' => 'Cược không hợp lệ!']);
                exit;
            }
        }
        $playerHand = [getTile(), getTile(), getTile()];
        $dealerHand = [getTile(), getTile(), getTile()];
        $pEval = evaluate($playerHand);
        $dEval = evaluate($dealerHand);
        $winAmount = -$bet;
        $status = "";
        if ($pEval['score'] > $dEval['score']) {
            $winAmount = $bet;
            $status = "Bạn thắng! (" . $pEval['name'] . ")";
        } elseif ($pEval['score'] < $dEval['score']) {
            $status = "Dealer thắng! (" . $dEval['name'] . ")";
        } else {
            $winAmount = 0;
            $status = "Hòa!";
        }
        $newMoney = $money + $winAmount;
        $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $stmt->bind_param("di", $newMoney, $userId);
        $stmt->execute();
        $stmt->close();
        // History
        $his = $conn->prepare("INSERT INTO history_mahjong (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $resStr = "P: " . $pEval['name'] . " vs D: " . $dEval['name'];
        $his->bind_param("idss", $userId, $bet, $resStr, $winAmount);
        $his->execute();
        $his->close();
        echo json_encode([
            'success' => true,
            'playerHand' => $playerHand,
            'dealerHand' => $dealerHand,
            'pEval' => $pEval['name'],
            'dEval' => $dEval['name'],
            'status' => $status,
            'winAmount' => $winAmount,
            'money' => number_format($newMoney, 0, ',', '.'),
            'rawMoney' => $newMoney
        ]);
        exit;
    } elseif ($action === 'get_history') {
        $stmt = $conn->prepare("SELECT Bet, Result, WinAmount, Time FROM history_mahjong WHERE Iduser = ? ORDER BY Time DESC LIMIT 10");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        echo json_encode(['success' => true, 'history' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Mahjong Clash - Đại Chiến Mạt Chược</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="../assets/js/game-effects.js"></script>
    <style>
        :root {
            --primary: #fcd34d;
            --secondary: #6ee7b7;
            --danger: #ef4444;
            --accent: #6366f1;
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
            font-family: 'Exo 2', system-ui, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 0;
            pointer-events: none;
        }
        .main-container {
            position: relative;
            z-index: 1;
            width: 96%;
            max-width: 920px;
            margin: 0.5rem auto;
            text-align: center;
        }
        .game-title {
            font-size: clamp(1.4rem, 3.2vw, 2rem);
            font-weight: 900;
            color: var(--primary);
            text-shadow: 0 0 25px rgba(252, 211, 77, 0.4);
            margin-bottom: 0.2rem;
            text-transform: uppercase;
            letter-spacing: 5px;
        }
        .balance-pill {
            background: rgba(110, 231, 183, 0.1);
            border: 1px solid var(--secondary);
            padding: 0.3rem 1.4rem;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 0.5rem;
            color: var(--secondary);
            font-weight: 700;
            font-size: 0.85rem;
        }
        .glass-card {
            position: relative;
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 1.8rem;
            padding: 1rem 1.4rem;
            margin-bottom: 0.5rem;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .battle-arena {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.2rem;
            margin-bottom: 0.6rem;
        }
        .side-fighter {
            flex: 1;
            max-width: 320px;
            background: rgba(0, 0, 0, 0.22);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 1.2rem;
            padding: 0.6rem 0.8rem;
        }
        .area-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0.75;
            margin-bottom: 0.3rem;
            font-weight: 700;
        }
        .tile-area {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 0.3rem 0;
            min-height: 75px;
        }
        .tile {
            width: 52px;
            height: 74px;
            background: #fdfdfd;
            color: #1e293b;
            border-radius: 0.6rem;
            border-bottom: 5px solid #cbd5e1;
            border-right: 3px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.35);
            position: relative;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .tile:hover {
            transform: translateY(-4px) rotate(-2deg);
        }
        .tile-type {
            font-size: 0.48rem;
            color: #94a3b8;
            position: absolute;
            top: 3px;
            font-weight: 700;
        }
        .tile-val {
            font-size: 1.6rem;
            margin-top: 6px;
        }
        .type-dots {
            border-left: 5px solid #3b82f6;
        }
        .type-bamboo {
            border-left: 5px solid #10b981;
        }
        .type-chars {
            border-left: 5px solid #ef4444;
        }
        .type-winds {
            border-left: 5px solid #6366f1;
        }
        .type-dragons {
            border-left: 5px solid #f59e0b;
        }
        .vs-divider {
            font-size: 1.2rem;
            font-weight: 900;
            color: var(--primary);
            opacity: 0.8;
            padding: 0 0.4rem;
            text-shadow: 0 0 10px rgba(252, 211, 77, 0.6);
            flex-shrink: 0;
        }
        .rank-badge {
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--secondary);
            padding: 0.2rem 0.8rem;
            border-radius: 50px;
            background: rgba(110, 231, 183, 0.1);
            margin-top: 0.2rem;
            display: inline-block;
        }
        .bet-controls-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 0.5rem 0;
        }
        .bet-input-container {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
            padding: 0.4rem 0.8rem;
            border-radius: 1rem;
            max-width: 150px;
            margin: 0;
        }
        .bet-input-container span {
            display: block;
            font-size: 0.6rem;
            opacity: 0.6;
            margin-bottom: 0.1rem;
            letter-spacing: 1px;
            font-weight: 700;
        }
        .bet-input-container input {
            width: 100%;
            background: transparent;
            border: none;
            color: #fff;
            text-align: center;
            font-size: 1.2rem;
            font-weight: 900;
            outline: none;
        }
        .quick-bet-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 6px;
            margin: 0;
        }
        .quick-btn {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            color: #e2e8f0;
            padding: 7px 13px;
            border-radius: 9px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.82rem;
        }
        .quick-btn:hover {
            background: rgba(255,255,255,0.18);
            transform: translateY(-2px);
        }
        .quick-btn.active {
            background: #f59e0b;
            color: #fff;
            border-color: #f59e0b;
            box-shadow: 0 0 12px rgba(245, 158, 11, 0.4);
        }
        .btn-play {
            background: linear-gradient(135deg, var(--primary) 0%, #d97706 100%);
            border: none;
            padding: 0.75rem 2.8rem;
            border-radius: 50px;
            color: #000;
            font-size: 1.15rem;
            font-weight: 900;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 2.5px;
            width: 100%;
            max-width: 320px;
            margin: 0.3rem auto 0;
            display: block;
            box-shadow: 0 6px 20px rgba(252, 211, 77, 0.4);
        }
        .btn-play:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(252, 211, 77, 0.6);
        }
        .btn-play:disabled {
            opacity: 0.5;
            filter: grayscale(1);
            cursor: not-allowed;
        }
        .history-section {
            background: var(--glass);
            border-radius: 2.5rem;
            padding: 2.5rem;
            border: 1px solid var(--glass-border);
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .history-table th {
            color: rgba(255, 255, 255, 0.4);
            text-transform: uppercase;
            font-size: 0.7rem;
            padding: 1rem;
            border-bottom: 2px solid var(--glass-border);
        }
        .history-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--glass-border);
            font-weight: 600;
            font-size: 0.9rem;
        }
        .quick-bet-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-bottom: 30px; }
        .quick-btn { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #e2e8f0; padding: 10px 20px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
        .quick-btn:hover { background: rgba(255,255,255,0.15); transform: translateY(-2px); }
        .quick-btn.active { background: #f59e0b; color: #fff; border-color: #f59e0b; }
        /* Hiệu ứng thắng thua chuẩn Thế Giới Linh Thú */
        .floating-win { 
            position: absolute; 
            bottom: 45%; 
            left: 50%; 
            transform: translateX(-50%); 
            font-family: 'Orbitron', system-ui, sans-serif; 
            font-weight: 900; 
            font-size: 2.2rem; 
            pointer-events: none; 
            text-shadow: 0 0 15px rgba(0,0,0,0.8), 0 0 30px currentColor; 
            z-index: 100;
            white-space: nowrap;
        }
        .glass-card.lose-shake { 
            animation: lose-shake 0.5s cubic-bezier(.36,.07,.19,.97) both; 
        }
        @keyframes lose-shake { 
            10%, 90% { transform: translate3d(-3px, 0, 0); } 
            20%, 80% { transform: translate3d(4px, 0, 0); } 
            30%, 50%, 70% { transform: translate3d(-6px, 0, 0); } 
            40%, 60% { transform: translate3d(6px, 0, 0); } 
        }
        .glass-card.win-pulse {
            animation: win-pulse 0.8s ease-out;
        }
        @keyframes win-pulse {
            0% { box-shadow: 0 0 20px rgba(74, 222, 128, 0.3); }
            50% { box-shadow: 0 0 70px rgba(74, 222, 128, 0.8); }
            100% { box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5); }
        }
        .result-status-badge {
            margin-top: 20px;
            padding: 12px 24px;
            border-radius: 14px;
            font-weight: 800;
            font-size: 1.1rem;
            text-align: center;
            display: none;
            backdrop-filter: blur(10px);
            animation: badgePop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes badgePop {
            0% { transform: scale(0.85); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .result-status-badge.win {
            background: rgba(74, 222, 128, 0.15);
            border: 1px solid rgba(74, 222, 128, 0.5);
            color: #4ade80;
            text-shadow: 0 0 10px rgba(74, 222, 128, 0.5);
            box-shadow: 0 4px 20px rgba(74, 222, 128, 0.2);
        }
        .result-status-badge.lose {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #ef4444;
            text-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
            box-shadow: 0 4px 20px rgba(239, 68, 68, 0.2);
        }
        .result-status-badge.tie {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.5);
            color: #f59e0b;
            text-shadow: 0 0 10px rgba(245, 158, 11, 0.5);
            box-shadow: 0 4px 20px rgba(245, 158, 11, 0.2);
        }
        /* Mahjong specific animations */
        @keyframes reveal {
            from {
                transform: rotateY(90deg);
                opacity: 0;
            }
            to {
                transform: rotateY(0);
                opacity: 1;
            }
        }
        .tile-revealing {
            animation: reveal 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
    </style>
</head>
<body>
    <div class="main-container">
        <h1 class="game-title">MAHJONG CLASH</h1>
        <div class="balance-pill">💰 Số Gtlm: <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span>
            gtlm
        </div>
        <div class="glass-card">
            <div class="battle-arena">
                <div id="dealer-view" class="side-fighter">
                    <div class="area-label">👑 Queen GTLM (DEALER)</div>
                    <div id="dealer-tiles" class="tile-area">
                        <div class="tile">🀫</div>
                        <div class="tile">🀫</div>
                        <div class="tile">🀫</div>
                    </div>
                    <div id="dealer-rank" class="rank-badge">---</div>
                </div>
                <div class="vs-divider">VS</div>
                <div id="player-view" class="side-fighter">
                    <div class="area-label">⚔️ NGƯỜI CHƠI (YOU)</div>
                    <div id="player-tiles" class="tile-area">
                        <div class="tile">🀫</div>
                        <div class="tile">🀫</div>
                        <div class="tile">🀫</div>
                    </div>
                    <div id="player-rank" class="rank-badge">---</div>
                </div>
            </div>
            <div class="bet-controls-row">
                <div class="bet-input-container">
                    <span>GTLM CƯỢC</span>
                    <input type="number" id="bet-amt" value="10000" min="1000" step="1000">
                </div>
                <div class="quick-bet-grid">
                    <button class="quick-btn" onclick="setBet(10000, event)">10K</button>
                    <button class="quick-btn" onclick="setBet(50000, event)">50K</button>
                    <button class="quick-btn" onclick="setBet(100000, event)">100K</button>
                    <button class="quick-btn" onclick="setBet(500000, event)">500K</button>
                    <button class="quick-btn" onclick="setBet(1000000, event)">1M</button>
                    <button class="quick-btn" onclick="setBet(5000000, event)">5M</button>
                    <button class="quick-btn" onclick="setBet(<?= $money ?>, event)">ALL IN</button>
                </div>
            </div>
            <button id="play-btn" class="btn-play">⚡ XUẤT QUÂN</button>
            <div id="result-status-badge" class="result-status-badge"></div>
        </div>
        <div class="history-section" style="display: none;">
            <h2 style="font-size: 1.1rem; letter-spacing: 2px; margin-bottom: 1rem;">LỊCH SỬ THI ĐẤU</h2>
            <div style="overflow-x: auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>gtlm cược</th>
                            <th>Trận đấu</th>
                            <th>Kết quả</th>
                        </tr>
                    </thead>
                    <tbody id="history-body"></tbody>
                </table>
            </div>
        </div>
        <div style="margin-top: 0.4rem; text-align: center;"><a href="../index.php"
                style="color: var(--primary); text-decoration: none; font-weight: 700; border: 1px solid var(--primary); padding: 0.4rem 1.8rem; border-radius: 50px; transition: 0.3s; font-size: 0.8rem; display: inline-block;">🏠
                QUAY LẠI SẢNH</a></div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/confetti.browser.min.js"></script>
    <script>
        function setBet(amount, event) {
            $('#bet-amt').val(amount);
            $('.quick-btn').removeClass('active');
            if (event && event.target) {
                event.target.classList.add('active');
            }
        }
        $('#play-btn').click(function() {
            const bet = parseInt($('#bet-amt').val());
            if (isNaN(bet) || bet <= 0) {
                Swal.fire('Lỗi', 'Cược không hợp lệ', 'error');
                return;
            }
            const btn = $(this);
            btn.prop('disabled', true).text('ĐANG XẾP BÀI...');
            $.post('?action=play', { bet: bet }, function(res) {
                if (res.success) {
                    const renderTiles = (hand, containerId, rankId, rankText) => {
                        let html = '';
                        hand.forEach(t => {
                            let typeClass = 'type-' + t.type;
                            html += `
                            <div class="tile tile-revealing ${typeClass}">
                                <div class="tile-type">${t.type.toUpperCase()}</div>
                                <div class="tile-val">${t.val}</div>
                            </div>`;
                        });
                        $(containerId).html(html);
                        $(rankId).text(rankText);
                    };
                    renderTiles(res.dealerHand, '#dealer-tiles', '#dealer-rank', res.dEval);
                    renderTiles(res.playerHand, '#player-tiles', '#player-rank', res.pEval);
                    setTimeout(() => {
                        $('#balance-val').text(res.money);
                        const card = $('.glass-card');
                        const winAmt = parseInt(res.winAmount || 0);

                        // ── Hiệu ứng thông báo thắng thua chuẩn Thế Giới Linh Thú ──
                        if (winAmt > 0) {
                            card.removeClass('lose-shake').addClass('win-pulse');
                            setTimeout(() => card.removeClass('win-pulse'), 800);

                            if (window.GameEffects) {
                                if (winAmt >= 100000) {
                                    window.GameEffects.showBigWin(winAmt);
                                } else {
                                    window.GameEffects.showWin(winAmt);
                                }
                            }

                            const floatWin = $('<div class="floating-win" style="color: #4ade80;">+' + winAmt.toLocaleString('vi-VN') + ' GTLM</div>').appendTo(card);
                            if (typeof gsap !== 'undefined') {
                                gsap.to(floatWin, { y: -100, opacity: 0, duration: 2.2, onComplete: () => floatWin.remove() });
                            } else {
                                setTimeout(() => floatWin.fadeOut(400, () => floatWin.remove()), 1800);
                            }

                            $('#result-status-badge')
                                .removeClass('lose tie')
                                .addClass('win')
                                .html('🎉 <b>CHIẾN THẮNG!</b> ' + res.status + ' (+' + winAmt.toLocaleString('vi-VN') + ' GTLM)')
                                .fadeIn(300);
                        } else if (winAmt < 0) {
                            card.addClass('lose-shake');
                            if (window.GameEffects) {
                                window.GameEffects.showLoss(bet);
                            }

                            const floatLose = $('<div class="floating-win" style="color: #ef4444;">-' + bet.toLocaleString('vi-VN') + ' GTLM</div>').appendTo(card);
                            if (typeof gsap !== 'undefined') {
                                gsap.to(floatLose, { y: -100, opacity: 0, duration: 2.2, onComplete: () => floatLose.remove() });
                            } else {
                                setTimeout(() => floatLose.fadeOut(400, () => floatLose.remove()), 1800);
                            }
                            setTimeout(() => card.removeClass('lose-shake'), 500);

                            $('#result-status-badge')
                                .removeClass('win tie')
                                .addClass('lose')
                                .html('😢 <b>RẤT TIẾC!</b> ' + res.status + ' (-' + bet.toLocaleString('vi-VN') + ' GTLM)')
                                .fadeIn(300);
                        } else {
                            // Hòa
                            $('#result-status-badge')
                                .removeClass('win lose')
                                .addClass('tie')
                                .html('🤝 <b>HÒA NHAU!</b> ' + res.status + ' (Hoàn lại vốn cược)')
                                .fadeIn(300);
                        }

                        setTimeout(() => {
                            $('#result-status-badge').fadeOut(400);
                        }, 4500);

                        loadHistory();
                        btn.prop('disabled', false).text('XUẤT QUÂN');
                    }, 1000);
                } else {
                    Swal.fire('Lỗi', res.message, 'error');
                    btn.prop('disabled', false).text('XUẤT QUÂN');
                }
            }, 'json').fail(function() {
                Swal.fire('Lỗi', 'Lỗi kết nối', 'error');
                btn.prop('disabled', false).text('XUẤT QUÂN');
            });
        });
        function loadHistory() {
            $.getJSON('live_37.php?action=get_history', function(res) {
                if(res.success && res.history) {
                    let html = '';
                    res.history.forEach(h => {
                        html += `<tr>
                            <td>${h.Time}</td>
                            <td>${parseInt(h.Bet).toLocaleString()}</td>
                            <td>${h.Result}</td>
                            <td style="color: ${h.WinAmount > 0 ? '#4ade80' : (h.WinAmount < 0 ? '#ef4444' : '#fff')}">${parseInt(h.WinAmount).toLocaleString()}</td>
                        </tr>`;
                    });
                    $('#history-body').html(html);
                }
            });
        }
        $(document).ready(loadHistory);
    </script>
    <!-- Premium ThreeJS Background & Game Effects -->
    <canvas id="threejs-background"></canvas>
    <script>
        (function () {
            window.themeConfig = {
                particleCount: <?= $particleCount ?? 800 ?>,
                particleSize: <?= $particleSize ?? 0.05 ?>,
                particleColor: '<?= $particleColor ?? "#f1c40f" ?>',
                particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
                shapeCount: <?= $shapeCount ?? 12 ?>,
                shapeColors: <?= json_encode($shapeColors ?? ["#f1c40f", "#e67e22", "#3498db"]) ?>,
                shapeOpacity: <?= $shapeOpacity ?? 0.35 ?>,
                bgGradient: <?= json_encode($bgGradient ?? ["#000000", "#1a1500", "#2d2400"]) ?>
            };
            const prefix = '../';
            const scripts = ['threejs-background.js', 'assets/js/game-effects-auto.js'];
            scripts.forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();
    </script>

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script src="bots/bot_37.js?v=<?= time() ?>"></script>

</body>
</html>