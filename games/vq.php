<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require '../db_connect.php';
require_once '../load_theme.php';

$userId = $_SESSION['Iduser'];
$sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$money = $user['Money'];
$userName = $user['Name'];

// --- AJAX ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'place_bet') {
        $chon = $_POST['chon'] ?? '';
        $cuoc = (int) ($_POST['cuoc'] ?? 0);
        $roundId = (int) ($_POST['round_id'] ?? 0);

        if ($cuoc > $money || $cuoc <= 0) {
            echo json_encode(['success' => false, 'message' => '⚠️ Số Gtlm không đủ!']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO bets (user_id, round_id, chosen_character, amount) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisd", $userId, $roundId, $chon, $cuoc);
        if ($stmt->execute()) {
            $newMoney = $money - $cuoc;
            $conn->query("UPDATE users SET Money = $newMoney WHERE Iduser = $userId");
            echo json_encode(['success' => true, 'newBalance' => number_format($newMoney) . ' gtlm']);
        } else {
            echo json_encode(['success' => false, 'message' => '❌ Lỗi hệ thống!']);
        }
        exit;
    }

    if ($action === 'get_result') {
        $roundId = (int) ($_POST['round_id'] ?? 0);
        $winner = rand(0, 1) ? "JoJo" : "Dio";

        $stmt = $conn->prepare("INSERT INTO rounds (round_id, winner) VALUES (?, ?)");
        $stmt->bind_param("is", $roundId, $winner);
        $stmt->execute();

        $stmt = $conn->prepare("SELECT chosen_character, amount FROM bets WHERE round_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $roundId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();

        $totalWin = 0;
        $totalBet = 0;
        while ($bet = $res->fetch_assoc()) {
            $totalBet += $bet['amount'];
            if ($bet['chosen_character'] === $winner)
                $totalWin += $bet['amount'] * 2;
        }

        if ($totalWin > 0)
            $conn->query("UPDATE users SET Money = Money + $totalWin WHERE Iduser = $userId");

        if (file_exists('../game_history_helper.php')) {
            require_once '../game_history_helper.php';
            logGameHistoryWithAll($conn, $userId, 'Vòng Quay JoJo', $totalBet, $totalWin, $totalWin > 0);
        }

        $stmt = $conn->query("SELECT Money FROM users WHERE Iduser = $userId");
        $newBalance = $stmt->fetch_assoc()['Money'];

        echo json_encode(['success' => true, 'winner' => $winner, 'winAmount' => $totalWin, 'newBalance' => number_format($newBalance) . ' gtlm', 'message' => ($totalWin > 0) ? "⭐ VICTORY!" : "💀 RETIRED!"]);
        exit;
    }
}

$roundId = time();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>JOJO ULTIMATE: PREMIUM EDITION</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Cinzel:wght@900&family=Bangers&family=Noto+Sans+JP:wght@900&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --jojo: #00d4ff;
            --dio: #f5c832;
            --gold: #f5c832;
            --dark: #080010;
        }

        body {
            margin: 0;
            background: var(--dark) !important;
            color: white;
            font-family: 'Poppins', sans-serif;
            overflow: hidden;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .money-badge {
            background: rgba(0, 0, 0, 0.8);
            padding: 10px 30px;
            border-radius: 50px;
            border: 2px solid var(--gold);
            color: var(--gold);
            font-weight: 900;
        }

        .theatre {
            flex: 1;
            margin: 20px;
            background: rgba(10, 5, 25, 0.8);
            backdrop-filter: blur(20px);
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .hp-arena {
            display: flex;
            gap: 50px;
            padding: 40px;
        }

        .hp-slot {
            flex: 1;
            height: 30px;
            background: #222;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }

        .hp-fill {
            height: 100%;
            transition: width 0.3s;
        }

        .hp-j {
            background: var(--jojo);
            box-shadow: 0 0 20px var(--jojo);
        }

        .hp-d {
            background: var(--dio);
            box-shadow: 0 0 20px var(--dio);
        }

        .combat-stage {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 100px;
        }

        .fighter {
            width: 300px;
            text-align: center;
        }

        .fighter img {
            width: 100%;
            filter: drop-shadow(0 0 30px #000);
        }

        .vs-logo {
            font-family: 'Cinzel';
            font-size: 80px;
            font-weight: 900;
        }

        .control-ui {
            padding: 30px;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .duel-btn {
            background: var(--gold);
            color: #000;
            padding: 15px 80px;
            border-radius: 40px;
            font-weight: 900;
            font-size: 24px;
            border: none;
            cursor: pointer;
            font-family: 'Cinzel';
        }

        .card {
            padding: 20px 40px;
            border: 2px solid #444;
            border-radius: 20px;
            cursor: pointer;
            transition: 0.3s;
        }

        .card.active {
            border-color: var(--gold);
            background: rgba(245, 200, 50, 0.1);
        }

        #fx-canvas {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 1000;
        }
    </style>
</head>

<body>
    <div id="fx-canvas"></div>
    <div
        style="padding: 20px; display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.5);">
        <a href="../index.php" style="color: white; text-decoration: none; font-weight: 900;">🏠 TRANG CHỦ</a>
        <div class="money-badge">💰 <span id="money-val"><?= number_format($money) ?> gtlm</span></div>
    </div>

    <div class="theatre">
        <div class="hp-arena">
            <div class="hp-slot">
                <div class="hp-fill hp-d" id="hp-d" style="width: 100%;"></div>
            </div>
            <div class="vs-logo" style="font-size: 20px;">VS</div>
            <div class="hp-slot">
                <div class="hp-fill hp-j" id="hp-j" style="width: 100%;"></div>
            </div>
        </div>
        <div class="combat-stage">
            <div class="fighter" id="f-dio"><img src="img/dio.png"></div>
            <div class="vs-logo">VS</div>
            <div class="fighter" id="f-jojo"><img src="img/jotaro.png"></div>
        </div>
        <div class="control-ui">
            <div style="display: flex; gap: 20px;">
                <div class="card" onclick="pick('Dio', this)">DIO</div>
                <div class="card" onclick="pick('JoJo', this)">JOTARO</div>
            </div>
            <input type="number" id="cuoc" value="100000"
                style="background: #111; color: #fff; border: 1px solid #444; padding: 10px; border-radius: 10px; text-align: center; width: 200px;">
            <button class="duel-btn" onclick="startDuel()" id="btn-duel">KÍCH HOẠT!</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let selected = null;
        let active = false;

        function pick(s, el) {
            if (active) return;
            selected = s;
            $('.card').removeClass('active');
            $(el).addClass('active');
        }

        async function startDuel() {
            if (active || !selected) return;
            const amt = $('#cuoc').val();
            active = true;
            $('#btn-duel').prop('disabled', true);

            try {
                const res = await $.post('vq.php?action=place_bet', { chon: selected, cuoc: amt, round_id: Date.now() });
                if (!res.success) { Swal.fire('Lỗi', res.message, 'error'); active = false; return; }
                $('#money-val').text(res.newBalance);

                const result = await $.post('vq.php?action=get_result', { round_id: Date.now() });

                // Animation logic (Simplified for stability)
                gsap.to('.fighter', { x: (Math.random() - 0.5) * 50, repeat: 10, yoyo: true, duration: 0.1 });

                setTimeout(() => {
                    if (result.winAmount > 0) {
                        if (window.GameEffects) GameEffects.showWin(result.winAmount);
                        Swal.fire('THẮNG!', result.message, 'success');
                    } else {
                        if (window.GameEffects) GameEffects.showLoss();
                        Swal.fire('THUA!', result.message, 'error');
                    }
                    $('#money-val').text(result.newBalance);
                    active = false;
                    $('#btn-duel').prop('disabled', false);
                }, 2000);

            } catch (e) { active = false; $('#btn-duel').prop('disabled', false); }
        }
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