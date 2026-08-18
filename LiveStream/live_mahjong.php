<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_mahjong', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require '../db_connect.php';
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
            echo json_encode(['success' => false, 'message' => 'Cược không hợp lệ!']);
            exit;
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
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
            margin: 2rem auto;
            text-align: center;
        }
        .game-title {
            font-size: clamp(2rem, 6vw, 4rem);
            font-weight: 900;
            color: var(--primary);
            text-shadow: 0 0 30px rgba(252, 211, 77, 0.4);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 10px;
        }
        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 3rem;
            padding: clamp(1rem, 4vw, 3rem);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            margin-bottom: 2rem;
            position: relative;
        }
        .balance-pill {
            background: rgba(110, 231, 183, 0.1);
            border: 1px solid var(--secondary);
            padding: 0.6rem 2rem;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 2rem;
            color: var(--secondary);
            font-weight: 700;
        }
        .area-label {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.6;
            margin-bottom: 1.5rem;
        }
        .tile-area {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin: 1.5rem 0;
            min-height: 130px;
        }
        .tile {
            width: clamp(60px, 12vw, 85px);
            aspect-ratio: 0.7;
            background: #fdfdfd;
            color: #1e293b;
            border-radius: 0.8rem;
            border-bottom: 8px solid #cbd5e1;
            border-right: 4px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.4);
            position: relative;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .tile:hover {
            transform: translateY(-10px) rotate(-2deg);
        }
        .tile-type {
            font-size: 0.6rem;
            color: #94a3b8;
            position: absolute;
            top: 8px;
            font-weight: 700;
        }
        .tile-val {
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            margin-top: 10px;
        }
        .type-dots {
            border-left: 6px solid #3b82f6;
        }
        .type-bamboo {
            border-left: 6px solid #10b981;
        }
        .type-chars {
            border-left: 6px solid #ef4444;
        }
        .type-winds {
            border-left: 6px solid #6366f1;
        }
        .type-dragons {
            border-left: 6px solid #f59e0b;
        }
        .vs-divider {
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--primary);
            margin: 2rem 0;
            opacity: 0.5;
            position: relative;
        }
        .vs-divider::before,
        .vs-divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background: var(--glass-border);
        }
        .vs-divider::before {
            left: 0;
        }
        .vs-divider::after {
            right: 0;
        }
        .rank-badge {
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--secondary);
            padding: 0.3rem 1.2rem;
            border-radius: 50px;
            background: rgba(110, 231, 183, 0.1);
            margin-top: 0.5rem;
            display: inline-block;
            min-height: 1.5rem;
        }
        .bet-input-container {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
            padding: 1.2rem;
            border-radius: 2rem;
            margin: 2rem auto;
            max-width: 250px;
        }
        .bet-input-container span {
            display: block;
            font-size: 0.7rem;
            opacity: 0.5;
            margin-bottom: 0.5rem;
            letter-spacing: 2px;
        }
        .bet-input-container input {
            width: 100%;
            background: transparent;
            border: none;
            color: #fff;
            text-align: center;
            font-size: 1.8rem;
            font-weight: 900;
            outline: none;
        }
        .btn-play {
            background: linear-gradient(135deg, var(--primary) 0%, #d97706 100%);
            border: none;
            padding: 1.2rem 5rem;
            border-radius: 50px;
            color: #000;
            font-size: 1.5rem;
            font-weight: 900;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 4px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 10px 30px rgba(252, 211, 77, 0.4);
        }
        .btn-play:hover:not(:disabled) {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(252, 211, 77, 0.6);
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
            <div id="dealer-view">
                <div class="area-label">Queen GTLM (DEALER)</div>
                <div id="dealer-tiles" class="tile-area">
                    <div class="tile">🀫</div>
                    <div class="tile">🀫</div>
                    <div class="tile">🀫</div>
                </div>
                <div id="dealer-rank" class="rank-badge">---</div>
            </div>
            <div class="vs-divider">VS</div>
            <div id="player-view">
                <div id="player-tiles" class="tile-area">
                    <div class="tile">🀫</div>
                    <div class="tile">🀫</div>
                    <div class="tile">🀫</div>
                </div>
                <div id="player-rank" class="rank-badge">---</div>
                <div class="area-label" style="margin-top: 1rem;">NGƯỜI CHƠI (YOU)</div>
            </div>
            <div class="bet-input-container">
                <span>gtlm CƯỢC</span>
                <input type="number" id="bet-amt" value="1000" min="100" step="100">
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
            <button id="play-btn" class="btn-play">XUẤT QUÂN</button>
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
        <div style="margin-top: 1rem; text-align: center;"><a href="../index.php"
                style="color: var(--primary); text-decoration: none; font-weight: 700; border: 1px solid var(--primary); padding: 0.8rem 2.5rem; border-radius: 50px; transition: 0.3s; font-size: 0.9rem; display: inline-block;">🏠
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
                        if (res.winAmount > 0) {
                            Swal.fire({
                                title: 'THẮNG!',
                                text: res.status + ` (+${res.winAmount} gtlm)`,
                                icon: 'success',
                                background: '#1e293b',
                                color: '#fff'
                            });
                        } else if (res.winAmount < 0) {
                            Swal.fire({
                                title: 'THUA!',
                                text: res.status,
                                icon: 'error',
                                background: '#1e293b',
                                color: '#fff'
                            });
                        } else {
                            Swal.fire({
                                title: 'HÒA!',
                                text: res.status,
                                icon: 'info',
                                background: '#1e293b',
                                color: '#fff'
                            });
                        }
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
            $.getJSON('mahjong.php?action=get_history', function(res) {
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

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script>
    if (typeof BotVirtualCursor !== "undefined") {
        BotVirtualCursor.init("Bot Streamer");
        setInterval(() => {
            const allBtns = Array.from(document.querySelectorAll("button, .btn-bet, .chip, .spin-btn, #btnSpin, .bet-button, .card, .btn-primary, .btn-success, input[type='button'], input[type='submit']"));
            const btns = allBtns.filter(b => {
                if(b.offsetParent === null || b.disabled) return false;
                const txt = (b.innerText || b.value || "").toLowerCase();
                const cls = (b.className || "").toLowerCase();
                const id = (b.id || "").toLowerCase();
                
                // Exclude common navigation/help buttons
                if(txt.includes("hướng dẫn") || txt.includes("trang chủ") || txt.includes("nạp") || txt.includes("rút") || txt.includes("lịch sử") || txt.includes("quay lại") || txt.includes("thoát")) return false;
                if(cls.includes("back") || cls.includes("help") || cls.includes("guide") || cls.includes("close") || cls.includes("swal") || cls.includes("nav")) return false;
                if(id.includes("guide") || id.includes("back") || id.includes("close") || id.includes("nav")) return false;
                
                return true;
            });
            
            if(btns.length > 0) {
                const btn = btns[Math.floor(Math.random() * btns.length)];
                BotVirtualCursor.moveToElement($(btn), 1, 0, () => {
                    setTimeout(() => { 
                        BotVirtualCursor.simulateClick(() => {
                            try { btn.click(); } catch(e){}
                        });
                    }, 500);
                });
            }
        }, 3000 + Math.random() * 4000);
    }
</script>

</body>
</html>