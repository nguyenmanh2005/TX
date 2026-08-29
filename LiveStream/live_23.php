<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_23', 5000000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require '../db_connect.php';
require_once '../load_theme.php';




$userId = $botUserId;

$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$stmt->close();

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'play') {
        $bet1 = (int) ($_POST['bet1'] ?? 0);
        $bet2 = (int) ($_POST['bet2'] ?? 0);
        $bet3 = (int) ($_POST['bet3'] ?? 0);
        $bet4 = (int) ($_POST['bet4'] ?? 0);
        $totalBet = $bet1 + $bet2 + $bet3 + $bet4;

        if ($totalBet <= 0 || $totalBet > $money) {
            echo json_encode(['success' => false, 'message' => 'Cược không hợp lệ!']);
            exit;
        }

        $beadsCount = rand(40, 60);
        $remainder = $beadsCount % 4;
        if ($remainder === 0)
            $remainder = 4;

        $winAmount = -$totalBet;
        if ($remainder === 1)
            $winAmount += $bet1 * 3.85;
        elseif ($remainder === 2)
            $winAmount += $bet2 * 3.85;
        elseif ($remainder === 3)
            $winAmount += $bet3 * 3.85;
        elseif ($remainder === 4)
            $winAmount += $bet4 * 3.85;

        $newMoney = $money + $winAmount;
        $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $stmt->bind_param("di", $newMoney, $userId);
        $stmt->execute();
        $stmt->close();

        // History
        $his = $conn->prepare("INSERT INTO history_fantan (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $resStr = "Kết quả: $remainder (Tổng hạt: $beadsCount)";
        $his->bind_param("idss", $userId, $totalBet, $resStr, $winAmount);
        $his->execute();
        $his->close();

        // Tích hợp hệ thống Nhiệm vụ Sự kiện, Battle Pass, XP, v.v.
        require_once '../game_history_helper.php';
        logGameHistoryWithAll($conn, $userId, 'Fan-Tan', $totalBet, $winAmount > 0 ? $winAmount + $totalBet : 0, $winAmount > 0);

        echo json_encode([
            'success' => true,
            'beadsCount' => $beadsCount,
            'remainder' => $remainder,
            'winAmount' => $winAmount,
            'money' => number_format($newMoney, 0, ',', '.'),
            'rawMoney' => $newMoney
        ]);
        exit;
    } elseif ($action === 'get_history') {
        $stmt = $conn->prepare("SELECT Bet, Result, WinAmount, Time FROM history_fantan WHERE Iduser = ? ORDER BY Time DESC LIMIT 10");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'history' => $res]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Fan-Tan - Nét Đẹp Truyền Thống</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <style>
        :root {
            --primary: #818cf8;
            --secondary: #6ee7b7;
            --danger: #ef4444;
            --accent: #fbbf24;
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
            margin: 0 auto;
            text-align: center;
            transform: scale(0.65);
            transform-origin: top center;
        }

        .game-title {
            font-size: clamp(2.5rem, 8vw, 4.5rem);
            font-weight: 900;
            color: var(--primary);
            text-shadow: 0 0 30px rgba(129, 140, 248, 0.4);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 15px;
        }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 3rem;
            padding: clamp(1.5rem, 5vw, 3.5rem);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .balance-pill {
            background: rgba(110, 231, 183, 0.1);
            border: 1px solid var(--secondary);
            padding: 0.8rem 2rem;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 2rem;
            color: var(--secondary);
            font-weight: 700;
            letter-spacing: 1px;
        }

        .beads-area {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-content: center;
            gap: 8px;
            max-width: 500px;
            min-height: 200px;
            margin: 0 auto 3rem;
            padding: 2rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 2rem;
            border: 1px solid var(--glass-border);
        }

        @keyframes dropBead {
            0% { transform: translateY(-200px) scale(0.5); opacity: 0; }
            60% { transform: translateY(15px) scale(1.1); opacity: 1; }
            80% { transform: translateY(-5px) scale(0.95); }
            100% { transform: translateY(0) scale(1); opacity: 1; }
        }

        .bead {
            width: clamp(12px, 2vw, 18px);
            aspect-ratio: 1;
            background: var(--accent);
            border-radius: 50%;
            box-shadow: 0 0 10px rgba(251, 191, 36, 0.6);
            animation: dropBead 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
        }

        .bet-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .bet-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            border-radius: 1.5rem;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .bet-box:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-5px);
        }

        .bet-box.active {
            border-color: var(--primary);
            background: rgba(129, 140, 248, 0.15);
            box-shadow: 0 0 20px rgba(129, 140, 248, 0.2);
        }

        .bet-label {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .bet-input {
            width: 100%;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.8rem;
            color: #fff;
            text-align: center;
            padding: 0.5rem;
            font-size: 1.1rem;
            font-weight: 700;
            outline: none;
            transition: 0.3s;
        }

        .bet-input:focus {
            border-color: var(--primary);
        }

        .btn-play {
            background: linear-gradient(135deg, var(--primary) 0%, #4338ca 100%);
            border: none;
            padding: 1.2rem 5rem;
            border-radius: 50px;
            color: #fff;
            font-size: 1.5rem;
            font-weight: 900;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 4px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 10px 30px rgba(129, 140, 248, 0.4);
        }

        .btn-play:hover:not(:disabled) {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(129, 140, 248, 0.6);
        }

        .btn-play:disabled {
            opacity: 0.5;
            filter: grayscale(1);
            cursor: not-allowed;
        }

        .status-msg {
            font-size: clamp(1.2rem, 3vw, 1.8rem);
            font-weight: 800;
            color: var(--accent);
            margin-bottom: 2rem;
            min-height: 3rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
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
            font-size: 0.8rem;
            padding: 1.2rem;
            border-bottom: 2px solid var(--glass-border);
        }

        .history-table td {
            padding: 1.2rem;
            border-bottom: 1px solid var(--glass-border);
            font-weight: 600;
        }

        .quick-bet-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-bottom: 30px; }
        .quick-btn { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #e2e8f0; padding: 10px 20px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
        .quick-btn:hover { background: rgba(255,255,255,0.15); transform: translateY(-2px); }
        .quick-btn.active { background: #f59e0b; color: #fff; border-color: #f59e0b; }

        @media (max-width: 600px) {
            .bet-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .bet-box {
                padding: 1rem;
            }

            .btn-play {
                padding: 1rem 2rem;
                font-size: 1.2rem;
            }
        }
    </style>
</head>

<body>


    <div class="main-container">
        <h1 class="game-title">FAN-TAN</h1>
        <div class="balance-pill">💰 Số Gtlm: <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span>
            gtlm
        </div>

        <div class="glass-card">
            <div id="beads-container" class="beads-area"></div>

            <div id="status-text" class="status-msg">Đặt cược và cùng đếm hạt!</div>

            <div class="quick-bet-grid">
                <button class="quick-btn" onclick="setBet(10000, event)">10K</button>
                <button class="quick-btn" onclick="setBet(50000, event)">50K</button>
                <button class="quick-btn" onclick="setBet(100000, event)">100K</button>
                <button class="quick-btn" onclick="setBet(500000, event)">500K</button>
                <button class="quick-btn" onclick="setBet(1000000, event)">1M</button>
                <button class="quick-btn" onclick="setBet(5000000, event)">5M</button>
                <button class="quick-btn" onclick="setBet(<?= $money ?>, event)">ALL IN</button>
            </div>

            <div class="bet-grid">
                <div class="bet-box" onclick="$('#bet-1').focus()">
                    <div class="bet-label">1</div>
                    <input type="number" id="bet-1" class="bet-input" value="0" min="0">
                </div>
                <div class="bet-box" onclick="$('#bet-2').focus()">
                    <div class="bet-label">2</div>
                    <input type="number" id="bet-2" class="bet-input" value="0" min="0">
                </div>
                <div class="bet-box" onclick="$('#bet-3').focus()">
                    <div class="bet-label">3</div>
                    <input type="number" id="bet-3" class="bet-input" value="0" min="0">
                </div>
                <div class="bet-box" onclick="$('#bet-4').focus()">
                    <div class="bet-label">4</div>
                    <input type="number" id="bet-4" class="bet-input" value="0" min="0">
                </div>
            </div>

            <button id="play-btn" class="btn-play">MỞ BÁT (COUNT)</button>
        </div>

        <div class="history-section" style="display: none;">
            <h2 style="font-size: 1.2rem; letter-spacing: 2px; margin-bottom: 1rem;">LỊCH SỬ GẦN ĐÂY</h2>
            <div style="overflow-x: auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>gtlm cược</th>
                            <th>Kết quả</th>
                            <th>Thắng/Thua</th>
                        </tr>
                    </thead>
                    <tbody id="history-body"></tbody>
                </table>
            </div>
        </div>
        <div style="margin-top: 1rem; text-align: center;"><a href="../index.php"
                style="color: var(--primary); text-decoration: none; font-weight: 700; border: 1px solid var(--primary); padding: 0.8rem 2.5rem; border-radius: 50px; transition: 0.3s; display: inline-block;">🏠
                QUAY LẠI SẢNH</a></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/confetti.browser.min.js"></script>

    <script>
        let lastFocused = '#bet-1';
        $(document).ready(function() {
            $('.bet-input').focus(function() {
                lastFocused = '#' + $(this).attr('id');
            });
        });

        function setBet(amount, event) {
            $(lastFocused).val(amount);
            $('.quick-btn').removeClass('active');
            if (event && event.target) {
                event.target.classList.add('active');
            }
        }

        $('#play-btn').click(function() {
            const bet1 = parseInt($('#bet-1').val()) || 0;
            const bet2 = parseInt($('#bet-2').val()) || 0;
            const bet3 = parseInt($('#bet-3').val()) || 0;
            const bet4 = parseInt($('#bet-4').val()) || 0;
            
            if (bet1 + bet2 + bet3 + bet4 <= 0) {
                Swal.fire('Lỗi', 'Vui lòng đặt cược', 'warning');
                return;
            }

            const btn = $(this);
            btn.prop('disabled', true).text('ĐANG ĐẾM...');
            $('#status-text').text('Đang đếm hạt...');

            $.post('?action=play', { bet1: bet1, bet2: bet2, bet3: bet3, bet4: bet4 }, function(res) {
                if (res.success) {
                    let beadsHtml = '';
                    for(let i=0; i<res.beadsCount; i++) {
                        let delay = Math.random() * 0.5;
                        beadsHtml += `<div class="bead" style="animation-delay: ${delay}s;"></div>`;
                    }
                    $('#beads-container').html(beadsHtml);

                    setTimeout(() => {
                        $('#balance-val').text(res.money);
                        let resultColor = res.winAmount > 0 ? '#4ade80' : '#ef4444';
                        $('#status-text').html(`Kết quả: <span style="color: ${resultColor}; font-size: 2.2rem;">${res.remainder} hạt!</span>`);

                        if (res.winAmount > 0) {
                            if (window.GameEffects) window.GameEffects.showWin(res.winAmount);
                        } else {
                            if (window.GameEffects) window.GameEffects.showLoss(Math.abs(res.winAmount));
                        }
                        
                        loadHistory();
                        btn.prop('disabled', false).text('MỞ BÁT (COUNT)');
                    }, 1500);
                } else {
                    Swal.fire('Lỗi', res.message, 'error');
                    btn.prop('disabled', false).text('MỞ BÁT (COUNT)');
                    $('#status-text').text('Đặt cược và cùng đếm hạt!');
                }
            }, 'json').fail(function() {
                Swal.fire('Lỗi', 'Lỗi kết nối', 'error');
                btn.prop('disabled', false).text('MỞ BÁT (COUNT)');
            });
        });

        function loadHistory() {
            $.getJSON('fantan.php?action=get_history', function(res) {
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
            const prefix = (window.location.pathname.includes('/games/') || window.location.pathname.includes('/LiveStream/')) ? '../' : '';
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
<script src="bots/bot_23.js"></script>

</body>

</html>