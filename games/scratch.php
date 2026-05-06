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

$conn->query("CREATE TABLE IF NOT EXISTS history_scratch (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Iduser INT NOT NULL,
    Bet DECIMAL(30,2) NOT NULL,
    Result VARCHAR(255) NOT NULL,
    WinAmount DECIMAL(30,2) NOT NULL,
    Time DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$symbols = ['🍒' => 2, '🍋' => 5, '🔔' => 10, '⭐' => 20, '💎' => 50, '🎰' => 100];

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false];

    if ($action === 'buy') {
        $bet = (float) ($_POST['bet'] ?? 0);
        if ($bet <= 0 || $bet > $money) {
            $response['message'] = "gtlm cược không hợp lệ!";
        } else {
            $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");
            $grid = [];
            $winAmount = 0;
            $matchSymbol = '';
            if (rand(1, 100) <= 35) {
                $roll = rand(1, 100);
                if ($roll <= 50)
                    $matchSymbol = '🍒';
                elseif ($roll <= 75)
                    $matchSymbol = '🍋';
                elseif ($roll <= 88)
                    $matchSymbol = '🔔';
                elseif ($roll <= 95)
                    $matchSymbol = '⭐';
                elseif ($roll <= 99)
                    $matchSymbol = '💎';
                else
                    $matchSymbol = '🎰';
                $winAmount = round($bet * $symbols[$matchSymbol]);
                $grid = array_fill(0, 3, $matchSymbol);
                $keys = array_keys($symbols);
                while (count($grid) < 9) {
                    $s = $keys[array_rand($keys)];
                    if (count(array_keys($grid, $s)) < 2)
                        $grid[] = $s;
                }
            } else {
                $keys = array_keys($symbols);
                while (count($grid) < 9) {
                    $s = $keys[array_rand($keys)];
                    if (count(array_keys($grid, $s)) < 2)
                        $grid[] = $s;
                }
            }
            shuffle($grid);
            if ($winAmount > 0)
                $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $userId");
            $profit = $winAmount - $bet;
            $resStr = "Result: " . implode('|', $grid);
            $his = $conn->prepare("INSERT INTO history_scratch (Iduser, Bet, Result, WinAmount, Time) VALUES (?,?,?,?,NOW())");
            $his->bind_param("idss", $userId, $bet, $resStr, $profit);
            $his->execute();
            logGameHistoryWithAll($conn, $userId, 'Scratch Card', $bet, $winAmount, $winAmount > 0);
            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
            $response = [
                'success' => true,
                'grid' => $grid,
                'winAmount' => number_format($winAmount, 0, ',', '.'),
                'money' => number_format($newMoney, 0, ',', '.'),
                'win' => ($winAmount > 0)
            ];
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
    <title>Scratch Card Premium - Vegas Royale</title>
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
            --primary: #c471ed;
            --accent: #f1c40f;
            --glass: rgba(255, 255, 255, 0.1)
        }

        body {
            margin: 0;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Inter', sans-serif;
            overflow: hidden;
            cursor: url('../img/chuot.png'), auto !important
        }

        * {
            cursor: inherit
        }

        button,
        a,
        input,
        select,
        .btn-help-game,
        .help-close-x,
        .scratch-tile,
        .scrapper-cover {
            cursor: url('../img/tay.png'), pointer !important
        }

        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            z-index: -1;
            width: 100%;
            height: 100%
        }

        .main-container {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box
        }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 2rem;
            padding: 3rem;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.6);
            width: 95%;
            max-width: 1100px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            overflow: hidden
        }

        .sidebar,
        .game-area {
            min-width: 0
        }

        /* Scratch Grid */
        .scratch-area {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            background: rgba(0, 0, 0, 0.3);
            padding: 18px;
            border-radius: 1.5rem;
            position: relative;
            max-width: 450px;
            margin: 0 auto;
            width: 100%
        }

        .scratch-tile {
            aspect-ratio: 1;
            background: linear-gradient(135deg, #3d3d3d, #1a1a1a);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.8rem;
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.12);
            transition: transform 0.2s, box-shadow 0.2s;
            overflow: hidden
        }

        .scratch-tile:hover:not(.revealed) {
            transform: scale(1.05);
            border-color: rgba(196, 113, 237, 0.5);
            box-shadow: 0 0 20px rgba(196, 113, 237, 0.3)
        }

        .scratch-tile span {
            display: none
        }

        .scratch-tile.revealed {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.08)
        }

        .scratch-tile.revealed span {
            display: block;
            animation: symbolPop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.6) both
        }

        @keyframes symbolPop {
            0% {
                transform: scale(0) rotate(-15deg);
                opacity: 0
            }

            70% {
                transform: scale(1.3) rotate(4deg);
                opacity: 1
            }

            100% {
                transform: scale(1) rotate(0deg)
            }
        }

        .scratch-tile.match-highlight {
            animation: matchGlow 0.45s ease infinite
        }

        @keyframes matchGlow {

            0%,
            100% {
                transform: scale(1);
                box-shadow: 0 0 10px #f1c40f
            }

            50% {
                transform: scale(1.14);
                box-shadow: 0 0 40px #f1c40f, 0 0 80px rgba(241, 196, 15, 0.5)
            }
        }

        .scrapper-cover {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #8e44ad, #c0392b);
            border-radius: 12px;
            z-index: 2
        }

        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem
        }

        .input-group {
            background: rgba(0, 0, 0, 0.3);
            padding: 1.2rem;
            border-radius: 1.2rem;
            border: 1px solid rgba(255, 255, 255, 0.05)
        }

        .input-group label {
            display: block;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #888;
            margin-bottom: 0.5rem
        }

        .input-group input {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            font-weight: 900;
            width: 100%;
            outline: none
        }

        .paytable {
            background: rgba(0, 0, 0, 0.2);
            padding: 1rem;
            border-radius: 1rem;
            font-size: 0.88rem
        }

        .paytable-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05)
        }

        .paytable-item:last-child {
            border: none
        }

        .btn-buy {
            background: linear-gradient(135deg, var(--primary), #12c2e9);
            color: #fff;
            border: none;
            padding: 1.5rem;
            border-radius: 1.2rem;
            font-size: 1.4rem;
            font-weight: 900;
            transition: 0.3s;
            text-transform: uppercase;
            box-shadow: 0 10px 30px rgba(196, 113, 237, 0.3);
            width: 100%;
            position: relative;
            overflow: hidden
        }

        .btn-buy::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transform: translateX(-100%);
            transition: 0.5s
        }

        .btn-buy:hover:not(:disabled)::after {
            transform: translateX(100%)
        }

        .btn-buy:hover:not(:disabled) {
            transform: translateY(-3px);
            filter: brightness(1.1)
        }

        .btn-buy:disabled {
            opacity: 0.5;
            cursor: not-allowed !important
        }

        .game-area {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            justify-content: center
        }
    </style>
</head>

<body>
    <div class="main-container">
        <div class="glass-card">
            <div class="sidebar">
                <h1 style="margin:0;font-size:2rem;font-weight:900;color:var(--primary);font-family:'Orbitron'">SCRATCH
                    CARD</h1>
                <p style="margin:0;opacity:0.5">Cào Vé Số - Thắng Lớn ✨</p>

                <div class="input-group">
                    <label>gtlm mua vé</label>
                    <input type="number" id="betAmount" value="5000" step="1000">
                </div>

                <div class="paytable">
                    <b style="display:block;margin-bottom:10px;color:var(--accent)">BẢNG THƯỞNG (x3 khớp)</b>
                    <div class="paytable-item"><span>🍒 Cherry</span><b>x2</b></div>
                    <div class="paytable-item"><span>🍋 Lemon</span><b>x5</b></div>
                    <div class="paytable-item"><span>🔔 Bell</span><b>x10</b></div>
                    <div class="paytable-item"><span>⭐ Star</span><b>x20</b></div>
                    <div class="paytable-item"><span>💎 Diamond</span><b>x50</b></div>
                    <div class="paytable-item"><span>🎰 Jackpot</span><b>x100</b></div>
                </div>

                <button id="buyBtn" class="btn-buy" onclick="buyCard()">🎫 Mua Vé</button>

                <button id="btn-howto"
                    style="background:rgba(255,255,255,0.08);color:#fff;border:1px solid rgba(255,255,255,0.2);padding:0.9rem;border-radius:1rem;font-size:0.9rem;font-weight:700;width:100%;cursor:pointer;transition:0.2s;margin-top:4px"
                    onmouseover="this.style.background='rgba(196,113,237,0.2)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.08)'">📖 Hướng Dẫn Chơi</button>

                <div style="margin-top:auto;padding-top:1rem;border-top:1px solid rgba(255,255,255,0.1)">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <span style="opacity:0.5">Số Gtlm:</span>
                        <span id="userMoney"
                            style="font-weight:900;color:var(--accent);font-size:1.5rem"><?php echo number_format($money, 0, ',', '.'); ?>
                            gtlm</span>
                    </div>
                </div>
            </div>

            <div class="game-area">
                <div class="scratch-area" id="scratchGrid">
                    <?php for ($i = 0; $i < 9; $i++): ?>
                        <div class="scratch-tile" onclick="revealTile(this)">
                            <div class="scrapper-cover" id="cover-<?php echo $i; ?>"></div>
                            <span id="symbol-<?php echo $i; ?>">?</span>
                        </div>
                    <?php endfor; ?>
                </div>
                <p style="text-align:center;margin-top:15px;opacity:0.5;font-size:0.8rem">Nhấp vào từng ô để cào và nhận
                    thưởng! ✋</p>
                <div style="text-align:center">
                    <a href="../index.php" style="color:#fff;text-decoration:none;font-size:0.8rem;opacity:0.3">← Quay
                        về Dashboard</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentGrid = [];
        let revealedCount = 0;
        let isBuying = false;
        let lastResult = null;

        function buyCard() {
            if (isBuying) return;
            const bet = $('#betAmount').val();
            isBuying = true;
            $('#buyBtn').prop('disabled', true).text('ĐANG XỬ LÝ...');
            // Reset tiles
            gsap.to('.scrapper-cover', { scale: 1, opacity: 1, rotation: 0, duration: 0.3 });
            $('.scratch-tile').removeClass('revealed match-highlight');
            revealedCount = 0;

            $.post('scratch.php?action=buy', { bet }, function (res) {
                if (res.success) {
                    currentGrid = res.grid;
                    lastResult = res;
                    isBuying = false;
                    $('#buyBtn').prop('disabled', false).text('✋ Cào Đi!');
                    $('#userMoney').text(res.money + ' gtlm');
                } else {
                    Swal.fire('Lỗi', res.message, 'error');
                    isBuying = false;
                    $('#buyBtn').prop('disabled', false).text('🎫 Mua Vé');
                }
            });
        }

        function revealTile(el) {
            if (isBuying || currentGrid.length === 0) return;
            const idx = $(el).index();
            if ($(el).hasClass('revealed')) return;

            $(el).addClass('revealed');
            $(el).find('span').text(currentGrid[idx]);

            // Glitter!
            if (window.GameEffects) window.GameEffects.glitterReveal(el);

            const cover = $(el).find('.scrapper-cover');
            gsap.to(cover, { scale: 0, opacity: 0, rotation: 40, duration: 0.45, ease: 'power2.out' });

            revealedCount++;
            if (revealedCount === 9) setTimeout(finishGame, 600);
        }

        function finishGame() {
            if (!lastResult) return;
            const win = lastResult.win;

            if (win) {
                // Highlight matching tiles
                const counts = {};
                currentGrid.forEach(s => counts[s] = (counts[s] || 0) + 1);
                const matchSym = Object.keys(counts).find(k => counts[k] >= 3);
                if (matchSym) {
                    $('.scratch-tile').each(function (i) {
                        if (currentGrid[i] === matchSym) $(this).addClass('match-highlight');
                    });
                    setTimeout(() => $('.match-highlight').removeClass('match-highlight'), 3500);
                }
                const rawWin = parseInt((lastResult.winAmount + '').replace(/[^0-9]/g, '')) || 0;
                if (window.GameEffects) {
                    if (rawWin >= 50000) window.GameEffects.showBigWin(rawWin);
                    else window.GameEffects.showWin(rawWin);
                }
                Swal.fire({
                    title: '🎉 TRÚNG THƯỞNG!',
                    html: `Chúc mừng bạn đã trúng: <b style="color:#f1c40f;font-size:1.8rem">${lastResult.winAmount} gtlm</b>`,
                    icon: 'success', background: '#1a1a1a', color: '#fff'
                });
            } else {
                if (window.GameEffects) window.GameEffects.showLoss(0);
                Swal.fire({
                    title: '😅 CHÚC MAY MẮN LẦN SAU!',
                    text: 'Rất tiếc không có bộ 3 nào trùng khớp.',
                    icon: 'info', background: '#1a1a1a', color: '#fff'
                });
            }
            $('#buyBtn').prop('disabled', false).text('🎫 Mua Vé Tiếp');
            currentGrid = [];
        }
    </script>

    <?php require_once '../casino_help.php'; ?>

    <script src="../assets/js/scratch-tutorial.js"></script>

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
            ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'].forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src; s.async = false;
                document.head.appendChild(s);
            });
        })();
    </script>
</body>

</html>