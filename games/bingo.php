<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}

require '../db_connect.php';

// Load theme
require_once '../load_theme.php';

$userId = $_SESSION['Iduser'];
$sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$soDu = $user['Money'];
$tenNguoiChoi = $user['Name'];

// Khởi tạo bảng Bingo 5x5 (1-25)
if (!isset($_SESSION['bingo_card'])) {
    $numbers = range(1, 25);
    shuffle($numbers);
    $_SESSION['bingo_card'] = array_chunk($numbers, 5);
    $_SESSION['bingo_marked'] = [];
    $_SESSION['bingo_drawn'] = [];
    $_SESSION['bingo_last_drawn'] = null;
}

$thongBao = "";
$ketQuaClass = "";
$laThang = false;
$lastDrawn = $_SESSION['bingo_last_drawn'] ?? null;
$drawnCount = count($_SESSION['bingo_drawn']);
$remainingCount = 25 - $drawnCount;

// Lấy số gtlm cược từ session (nếu có) hoặc lần trước
$lastBetAmount = $_SESSION['bingo_last_bet'] ?? 0;

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");

    if ($action === "new_card") {
        $numbers = range(1, 25);
        shuffle($numbers);
        $_SESSION['bingo_card'] = array_chunk($numbers, 5);
        $_SESSION['bingo_marked'] = [];
        $_SESSION['bingo_drawn'] = [];
        $_SESSION['bingo_last_drawn'] = null;
        $thongBao = "🆕 Bảng Bingo mới đã được tạo!";
        $lastDrawn = null;
        $drawnCount = 0;
        $remainingCount = 25;
    } elseif ($action === "draw" && $cuoc > 0) {
        if ($cuoc > $soDu || $cuoc <= 0) {
            $thongBao = "⚠️ Số GTLM ra chiêu không hợp lệ!";
        } else {
            $_SESSION['bingo_last_bet'] = $cuoc;

            $available = array_diff(range(1, 25), $_SESSION['bingo_drawn']);
            if (empty($available)) {
                $thongBao = "🎯 Tất cả số đã được rút! Tạo bảng mới để chơi tiếp!";
            } else {
                $drawn = array_rand(array_flip($available));
                $_SESSION['bingo_drawn'][] = $drawn;
                $_SESSION['bingo_last_drawn'] = $drawn;
                $lastDrawn = $drawn;

                $found = false;
                foreach ($_SESSION['bingo_card'] as $row) {
                    if (in_array($drawn, $row)) {
                        $_SESSION['bingo_marked'][] = $drawn;
                        $found = true;
                        break;
                    }
                }

                $won = false;
                if (count($_SESSION['bingo_marked']) >= 5) {
                    foreach ($_SESSION['bingo_card'] as $row) {
                        if (count(array_intersect($row, $_SESSION['bingo_marked'])) === 5) {
                            $won = true;
                            break;
                        }
                    }
                    if (!$won) {
                        for ($i = 0; $i < 5; $i++) {
                            if (count(array_intersect(array_column($_SESSION['bingo_card'], $i), $_SESSION['bingo_marked'])) === 5) {
                                $won = true;
                                break;
                            }
                        }
                    }
                }

                $thang = 0;
                if ($won) {
                    $thang = $cuoc * 5;
                    $soDu += $thang;
                    $thongBao = "🎉 BINGO! Bạn thắng " . number_format($thang) . " gtlm!";
                    $ketQuaClass = "thang";
                    $laThang = true;

                    $numbers = range(1, 25);
                    shuffle($numbers);
                    $_SESSION['bingo_card'] = array_chunk($numbers, 5);
                    $_SESSION['bingo_marked'] = [];
                    $_SESSION['bingo_drawn'] = [];
                    $_SESSION['bingo_last_drawn'] = null;

                    require_once '../game_history_helper.php';
                    logGameHistoryWithAll($conn, $userId, 'Bingo', $cuoc, $thang, true);
                    require_once '../tournament_helper.php';
                    logTournamentGame($conn, $userId, 'Bingo', $cuoc, $thang, true);
                } else {
                    $soDu -= $cuoc;
                    $thongBao = "Rút được số " . $drawn . ($found ? " ✅" : " ❌") . ". Mất " . number_format($cuoc) . " gtlm";
                    $ketQuaClass = "thua";

                    require_once '../game_history_helper.php';
                    logGameHistoryWithAll($conn, $userId, 'Bingo', $cuoc, 0, false);
                    require_once '../tournament_helper.php';
                    logTournamentGame($conn, $userId, 'Bingo', $cuoc, 0, false);
                }
            }
        }
    }

    if ($isAjax && $_SERVER["REQUEST_METHOD"] === "POST") {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'thongBao' => $thongBao,
            'ketQuaClass' => $ketQuaClass,
            'laThang' => $laThang,
            'lastDrawn' => $lastDrawn,
            'drawnCount' => count($_SESSION['bingo_drawn']),
            'remainingCount' => 25 - count($_SESSION['bingo_drawn']),
            'soDu' => $soDu,
            'bingo_card' => $_SESSION['bingo_card'],
            'bingo_marked' => $_SESSION['bingo_marked'],
            'bingo_drawn' => $_SESSION['bingo_drawn']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Bingo</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <link rel="stylesheet" href="../assets/css/game-effects.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <style>
        body {
            cursor: url('../chuot.png'), auto !important;
            font-family: 'Segoe UI', sans-serif;
            text-align: center;
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            padding: 50px;
            min-height: 100vh;
            position: relative;
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

        .game-box {
            position: relative;
            z-index: 1;
        }

        * {
            cursor: inherit;
        }

        button,
        a,
        input {
            cursor: url('../img/tay.png'), pointer !important;
        }

        .game-box {
            display: inline-block;
            background: rgba(102, 126, 234, 0.95);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.4);
            border: 3px solid rgba(255, 255, 255, 0.5);
            max-width: 700px;
        }

        .game-box h1 {
            color: white;
            font-size: 28px;
            margin: 15px 0;
        }

        .balance {
            margin: 20px 0;
            font-size: 22px;
            font-weight: 700;
            color: #ffd700;
        }

        .bingo-card {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin: 30px auto;
            max-width: 400px;
        }

        .bingo-cell {
            background: white;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 15px;
            font-size: 24px;
            font-weight: 700;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.36, 0, 0.66, 1);
            cursor: default;
            will-change: transform, background, box-shadow;
        }

        .bingo-cell.marked {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.8), inset 0 0 10px rgba(255, 255, 255, 0.3);
            animation: markCell 0.5s cubic-bezier(0.36, 0, 0.66, 1);
            font-weight: 800;
        }

        @keyframes markCell {
            0% {
                transform: scale(0.7) rotate(-180deg);
                opacity: 0.3;
            }

            50% {
                transform: scale(1.25) rotate(10deg);
            }

            100% {
                transform: scale(1.1) rotate(0deg);
                opacity: 1;
            }
        }

        input {
            padding: 14px 18px;
            margin: 12px;
            font-size: 16px;
            border-radius: var(--border-radius);
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.95);
            width: 80%;
            max-width: 300px;
        }

        button {
            padding: 16px 32px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            will-change: transform;
        }

        button:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.5);
            background: linear-gradient(135deg, #20c997 0%, #28a745 100%);
        }

        .thongbao {
            margin-top: 20px;
            font-size: 20px;
            font-weight: 700;
            padding: 15px 25px;
            border-radius: var(--border-radius);
            min-height: 50px;
        }

        .thongbao.thang {
            color: #00ff00;
            background: rgba(40, 167, 69, 0.3);
        }

        .thongbao.thua {
            color: #ff6b6b;
            background: rgba(220, 53, 69, 0.3);
        }

        .game-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            color: white;
            font-weight: 600;
        }

        .drawn-number-display {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.2) 0%, rgba(255, 193, 7, 0.2) 100%);
            border: 3px solid #ffc107;
            border-radius: var(--border-radius);
            margin: 15px 0;
            animation: numberPulse 0.6s cubic-bezier(0.36, 0, 0.66, 1);
            will-change: transform;
        }

        .drawn-number-display .number {
            font-size: 48px;
            font-weight: 800;
            color: #ffc107;
            text-shadow: 0 0 20px rgba(255, 193, 7, 0.8);
            animation: numberBounce 0.6s cubic-bezier(0.36, 0, 0.66, 1);
        }

        .drawn-number-display .label {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
            margin-top: 5px;
        }

        @keyframes numberPulse {
            0% {
                transform: scale(0.5);
                opacity: 0;
            }

            50% {
                transform: scale(1.15);
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes numberBounce {

            0%,
            100% {
                transform: translateY(0);
            }

            25% {
                transform: translateY(-15px);
            }

            50% {
                transform: translateY(0);
            }

            75% {
                transform: translateY(-8px);
            }
        }

        .progress-bar {
            width: 100%;
            height: 12px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 8px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
            transition: width 0.5s cubic-bezier(0.36, 0, 0.66, 1);
            box-shadow: 0 0 10px rgba(40, 167, 69, 0.8);
        }

        .drawn-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 15px 0;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--border-radius);
            min-height: 40px;
            align-items: center;
        }

        .drawn-item {
            background: rgba(52, 152, 219, 0.3);
            color: #87ceeb;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            border: 1px solid rgba(135, 206, 235, 0.5);
            animation: drawnItemSlide 0.4s cubic-bezier(0.36, 0, 0.66, 1);
            will-change: transform;
        }

        @keyframes drawnItemSlide {
            0% {
                transform: translateX(-20px) scale(0.8);
                opacity: 0;
            }

            100% {
                transform: translateX(0) scale(1);
                opacity: 1;
            }
        }

        .button-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
            margin: 20px 0;
        }

        .btn-new-card {
            background: linear-gradient(135deg, #6c63ff 0%, #5a54d8 100%) !important;
            box-shadow: 0 4px 15px rgba(108, 99, 255, 0.3) !important;
        }

        .btn-new-card:hover {
            background: linear-gradient(135deg, #5a54d8 0%, #6c63ff 100%) !important;
            box-shadow: 0 8px 25px rgba(108, 99, 255, 0.5) !important;
        }
    </style>
</head>

<body>

    <?php if ($laThang): ?>
        <canvas id="phaohoa"></canvas>
        <script>
            const canvas = document.getElementById("phaohoa");
            const ctx = canvas.getContext("2d");
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            let particles = [];
            function createFirework() {
                let x = Math.random() * canvas.width;
                let y = Math.random() * canvas.height / 2;
                let colors = ["#ffd700", "#ff6b6b", "#4ecdc4", "#45b7d1"];
                for (let i = 0; i < 30; i++) {
                    let angle = (Math.PI * 2 * i) / 30;
                    let speed = 2 + Math.random() * 2;
                    particles.push({
                        x: x, y: y, dx: Math.cos(angle) * speed, dy: Math.sin(angle) * speed,
                        life: 50, maxLife: 50, color: colors[Math.floor(Math.random() * colors.length)], size: 2
                    });
                }
            }
            function update() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                for (let i = particles.length - 1; i >= 0; i--) {
                    let p = particles[i];
                    p.x += p.dx; p.y += p.dy; p.dy += 0.1; p.life--;
                    if (p.life <= 0) { particles.splice(i, 1); continue; }
                    let alpha = p.life / p.maxLife;
                    ctx.globalAlpha = alpha; ctx.fillStyle = p.color; ctx.fillRect(p.x, p.y, 3, 3);
                }
                ctx.globalAlpha = 1;
            }
            for (let i = 0; i < 2; i++) { setTimeout(() => createFirework(), i * 500); }
            let updateInterval = setInterval(update, 50);
            setTimeout(() => { clearInterval(updateInterval); canvas.remove(); }, 3000);
        </script>
    <?php endif; ?>

    <div class="game-box game-container">
        <h1 class="game-title">🎱 Bingo</h1>
        <div class="balance">💰 Số Gtlm: <b><?= number_format($soDu, 0, ',', '.') ?> gtlm</b></div>

        <div class="game-info">
            <div>
                <div style="font-size: 14px; opacity: 0.8;">Số Đã Rút</div>
                <div style="font-size: 24px; color: #ffc107; margin-top: 5px;"><?= $drawnCount ?>/25</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= ($drawnCount / 25) * 100 ?>%;"></div>
                </div>
            </div>
            <div>
                <div style="font-size: 14px; opacity: 0.8;">Số Còn Lại</div>
                <div style="font-size: 24px; color: #87ceeb; margin-top: 5px;"><?= $remainingCount ?> số</div>
            </div>
        </div>

        <?php if ($lastDrawn !== null): ?>
            <div class="drawn-number-display">
                <div class="number"><?= $lastDrawn ?></div>
                <div class="label">🎲 Số Vừa Rút</div>
            </div>
        <?php endif; ?>

        <div style="text-align: left; margin: 10px 0;">
            <div style="font-size: 12px; font-weight: 600; color: rgba(255, 255, 255, 0.7); margin-bottom: 8px;">📊 Các
                số đã rút:</div>
            <div class="drawn-list" id="drawnList">
                <?php
                $sortedDrawn = $_SESSION['bingo_drawn'];
                sort($sortedDrawn);
                foreach ($sortedDrawn as $num): ?>
                    <div class="drawn-item"><?= $num ?></div>
                <?php endforeach; ?>
                <?php if (empty($_SESSION['bingo_drawn'])): ?>
                    <div style="color: rgba(255, 255, 255, 0.5); font-size: 13px;">Chưa rút số nào</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bingo-card">
            <?php foreach ($_SESSION['bingo_card'] as $row): ?>
                <?php foreach ($row as $num): ?>
                    <div class="bingo-cell <?= in_array($num, $_SESSION['bingo_marked']) ? 'marked' : '' ?>">
                        <?= $num ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>

        <form method="post" id="gameForm">
            <label style="display: block; margin: 15px 0 8px; font-weight: 600; color: white; font-size: 16px;">💰 Số
                GTLM muốn liều:</label>
            <input type="number" name="cuoc" id="betInput" placeholder="Nhập số GTLM muốn liều" required min="1"
                value="<?= $lastBetAmount > 0 ? $lastBetAmount : '' ?>"><br>

            <div class="button-group">
                <button type="submit" name="action" value="draw" class="btn-draw">🎲 Rút Số Tiếp Theo</button>
                <button type="submit" name="action" value="new_card" class="btn-new-card">🆕 Bảng Mới</button>
            </div>
            <p><a href="../index.php" style="color: white; text-decoration: none;">🏠 Quay Lại Trang Chủ</a></p>
        </form>

        <?php if ($thongBao): ?>
            <div class="thongbao <?= $ketQuaClass ?> result-banner <?= $ketQuaClass === 'thang' ? 'result-win' : 'result-lose shake-on-lose' ?>"
                id="messageDisplay">
                <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('gameForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const action = e.submitter ? e.submitter.value : 'draw';
            formData.append('action', action);

            try {
                const response = await fetch('bingo.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                if (data.success) {
                    document.querySelector('.balance b').textContent = parseInt(data.soDu).toLocaleString('vi-VN') + ' gtlm';
                    document.querySelector('.game-info div:first-child div:nth-child(2)').textContent = data.drawnCount + '/25';
                    document.querySelector('.progress-fill').style.width = (data.drawnCount / 25 * 100) + '%';
                    document.querySelector('.game-info div:last-child div:nth-child(2)').textContent = data.remainingCount + ' số';

                    const cells = document.querySelectorAll('.bingo-cell');
                    data.bingo_card.flat().forEach((num, idx) => {
                        const cell = cells[idx];
                        cell.textContent = num;
                        if (data.bingo_marked.includes(num)) { cell.classList.add('marked'); }
                        else { cell.classList.remove('marked'); }
                    });

                    const list = document.getElementById('drawnList');
                    list.innerHTML = '';
                    if (data.bingo_drawn.length > 0) {
                        [...data.bingo_drawn].sort((a, b) => a - b).forEach(num => {
                            const item = document.createElement('div');
                            item.className = 'drawn-item';
                            item.textContent = num;
                            list.appendChild(item);
                        });
                    } else {
                        list.innerHTML = '<div style="color: rgba(255, 255, 255, 0.5); font-size: 13px;">Chưa rút số nào</div>';
                    }

                    let msgDisplay = document.getElementById('messageDisplay');
                    if (!msgDisplay) {
                        msgDisplay = document.createElement('div');
                        msgDisplay.id = 'messageDisplay';
                        document.querySelector('.game-box').appendChild(msgDisplay);
                    }
                    msgDisplay.className = `thongbao ${data.ketQuaClass} result-banner ${data.ketQuaClass === 'thang' ? 'result-win' : 'result-lose shake-on-lose'}`;
                    msgDisplay.innerHTML = data.thongBao;

                    if (data.laThang && typeof createFirework === 'function') {
                        const fireworkCanvas = document.createElement('canvas');
                        fireworkCanvas.id = 'phaohoa';
                        document.body.appendChild(fireworkCanvas);
                    }
                }
            } catch (error) { console.error('Draw error:', error); }
        });

        (function () {
            window.themeConfig = {
                particleCount: <?= $particleCount ?>, particleSize: <?= $particleSize ?>,
                particleColor: '<?= $particleColor ?>', particleOpacity: <?= $particleOpacity ?>,
                shapeCount: <?= $shapeCount ?>, shapeColors: <?= json_encode($shapeColors) ?>,
                shapeOpacity: <?= $shapeOpacity ?>, bgGradient: <?= json_encode($bgGradient) ?>
            };
            const script = document.createElement('script');
            script.src = '../threejs-background.js';
            document.head.appendChild(script);
        })();
    </script>

    <script src="../assets/js/game-effects.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/game-effects-auto.js"></script>
    <script src="../assets/js/game-enhancements.js"></script>
    <script>
        if (typeof GameEffectsAuto !== 'undefined') { GameEffectsAuto.init(); }
    </script>











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