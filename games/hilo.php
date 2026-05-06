<?php
session_start();
require '../db_connect.php';
require_once '../load_theme.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['Iduser'];
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$userName = $user['Name'];
$stmt->close();

// Auto-create history table
$conn->query("CREATE TABLE IF NOT EXISTS history_hilo (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Iduser INT NOT NULL,
    Bet DECIMAL(30,2) NOT NULL,
    Result VARCHAR(255) NOT NULL,
    WinAmount DECIMAL(30,2) NOT NULL,
    Time DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false];

    if ($action === 'start') {
        $bet = (float) ($_POST['bet'] ?? 0);
        if ($bet <= 0 || $bet > $money) {
            $response['message'] = "Gtlm cược không hợp lệ!";
        } else {
            // Deduct money
            $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");
            // Initial card (1-13)
            $card = rand(1, 13);
            $_SESSION['hilo_bet'] = $bet;
            $_SESSION['hilo_card'] = $card;
            $_SESSION['hilo_mult'] = 1.0;
            $response = ['success' => true, 'card' => $card, 'money' => number_format($money - $bet, 0, ',', '.')];
        }
    } elseif ($action === 'guess') {
        $guess = $_POST['guess']; // 'higher' or 'lower'
        $oldCard = $_SESSION['hilo_card'];
        $bet = $_SESSION['hilo_bet'];
        $newCard = rand(1, 13);

        $win = false;
        if ($guess === 'higher' && $newCard >= $oldCard)
            $win = true;
        if ($guess === 'lower' && $newCard <= $oldCard)
            $win = true;

        if ($win) {
            $multAdd = ($oldCard == $newCard) ? 0.1 : 0.5; // Same card is hard
            $_SESSION['hilo_mult'] += $multAdd;
            $_SESSION['hilo_card'] = $newCard;
            $response = ['success' => true, 'win' => true, 'card' => $newCard, 'mult' => number_format($_SESSION['hilo_mult'], 2)];
        } else {
            $resStr = "Lost at x" . number_format($_SESSION['hilo_mult'], 2);
            $his = $conn->prepare("INSERT INTO history_hilo (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
            $negBet = -$bet;
            $his->bind_param("idss", $userId, $bet, $resStr, $negBet);
            $his->execute();
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
        unset($_SESSION['hilo_bet']);
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
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-color: #00d2ff;
            --secondary-color: #3a7bd5;
            --accent-color: #f1c40f;
            --glass: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        body {
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            color: #fff;
            min-height: 100vh;
            font-family: 'Exo 2', system-ui, sans-serif;
            overflow-x: hidden;
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
        }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 2rem;
            padding: 2rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            margin-bottom: 2rem;
        }

        .game-layout {
            display: flex;
            gap: 2rem;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
        }

        .card-display {
            width: 220px;
            height: 320px;
            background: #fff;
            border-radius: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 20px;
            color: #000;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            border: 5px solid rgba(0, 0, 0, 0.1);
        }

        .card-val {
            font-size: 5rem;
            font-weight: 900;
            text-align: center;
            margin: auto;
        }

        .card-suit {
            font-size: 3rem;
        }

        .red-suit {
            color: #ff4757;
        }

        .controls {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            flex: 1;
            min-width: 300px;
        }

        .btn-guess {
            padding: 1.5rem;
            border: none;
            border-radius: 1.5rem;
            color: #fff;
            font-weight: 900;
            font-size: 1.4rem;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
        }

        .btn-higher {
            background: linear-gradient(135deg, #00b894, #55efc4);
        }

        .btn-lower {
            background: linear-gradient(135deg, #d63031, #ff7675);
        }

        .btn-guess:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .btn-guess:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        button,
        a,
        input,
        select,
        .btn-help-game,
        .help-close-x {
            cursor: url('../img/tay.png'), pointer !important;
        }

        .collect-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0, 0, 0, 0.3);
            padding: 1rem 2rem;
            border-radius: 50px;
            border: 1px solid var(--glass-border);
            margin-top: 2rem;
        }

        .btn-collect {
            background: var(--accent-color);
            color: #000;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 50px;
            font-weight: 900;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-collect:hover:not(:disabled) {
            transform: scale(1.1);
            box-shadow: 0 0 20px var(--accent-color);
        }
    </style>
</head>

<body>
    <div id="threejs-background"></div>
    <div class="main-container">
        <!-- Header -->
        <div class="glass-card"
            style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 3rem;">
            <div>
                <h1 style="margin:0; font-size: 2.5rem; font-weight: 900; color: var(--primary-color);">HI-LO</h1>
                <p style="margin:0; opacity:0.5">Dự đoán Cao / Thấp - Cao Cấp</p>
            </div>
            <div style="display:flex; align-items:center; gap:2rem;">
                <div id="userMoney" style="font-weight:900; font-size:1.8rem; color:var(--accent-color)">
                    <?php echo number_format($money, 0, ',', '.'); ?> gtlm</div>
                <a href="../index.php"
                    style="color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.2); padding: 0.5rem 1.5rem; border-radius: 50px; font-weight: 900;">THOÁT</a>
            </div>
        </div>

        <!-- Game Area -->
        <div class="glass-card">
            <div class="game-layout">
                <div class="card-display playing-card">
                    <div class="card-suit-small" id="cardSuitSmall">🃏</div>
                    <div class="card-val" id="cardVal">?</div>
                    <div class="card-suit" id="cardSuit" style="text-align:right">🃏</div>
                </div>

                <div class="controls">
                    <div class="info-card"
                        style="background:rgba(0,0,0,0.2); padding:1.5rem; border-radius:1.5rem; border:1px solid var(--glass-border)">
                        <h3 style="margin:0 0 10px">MỨC CƯỢC</h3>
                        <input type="number" id="betAmount" value="10000"
                            style="width:100%; background:none; border:none; border-bottom:2px solid var(--primary-color); color:#fff; font-size:1.8rem; font-weight:900; outline:none;">
                    </div>

                    <div style="display:flex; gap:1rem;">
                        <button class="btn-guess btn-higher" id="btnHigher" onclick="guess('higher')" disabled>CAO
                            HƠN</button>
                        <button class="btn-guess btn-lower" id="btnLower" onclick="guess('lower')" disabled>THẤP
                            HƠN</button>
                    </div>

                    <button class="btn-guess" style="background:var(--primary-color); width:100%" id="btnStart"
                        onclick="startGame()">BẮT ĐẦU</button>

                    <div class="collect-bar">
                        <div>Nhân thưởng: <span id="multVal"
                                style="color:var(--accent-color); font-weight:900">x1.00</span></div>
                        <div id="winEst" style="opacity:0.8">Ăn: 0 gtlm</div>
                        <button class="btn-collect" id="btnCollect" onclick="collect()" disabled>NHẬN Gtlm</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Theme-Aware Three.js Background
        (function () {
            const themeConfig = {
                particleCount: <?= $particleCount ?>,
                particleSize: <?= $particleSize ?>,
                particleColor: '<?= $particleColor ?>',
                particleOpacity: <?= $particleOpacity ?>,
                shapeCount: <?= $shapeCount ?>,
                shapeColors: <?= json_encode($shapeColors) ?>,
                shapeOpacity: <?= $shapeOpacity ?>
            };

            const scene = new THREE.Scene();
            const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
            const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
            renderer.setSize(window.innerWidth, window.innerHeight);
            document.getElementById('threejs-background').appendChild(renderer.domElement);

            // Particles
            const particlesGeometry = new THREE.BufferGeometry();
            const posArray = new Float32Array(themeConfig.particleCount * 3);
            for (let i = 0; i < themeConfig.particleCount * 3; i++) posArray[i] = (Math.random() - 0.5) * 40;
            particlesGeometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));

            const particlesMaterial = new THREE.PointsMaterial({
                size: themeConfig.particleSize,
                color: parseInt(themeConfig.particleColor.replace('#', ''), 16),
                transparent: true, opacity: themeConfig.particleOpacity
            });
            const particlesMesh = new THREE.Points(particlesGeometry, particlesMaterial);
            scene.add(particlesMesh);

            // Shapes
            const shapes = [];
            const colors = themeConfig.shapeColors.map(c => parseInt(c.replace('#', ''), 16));
            for (let i = 0; i < themeConfig.shapeCount; i++) {
                const geometry = new THREE.IcosahedronGeometry(Math.random() * 0.5 + 0.3, 0);
                const material = new THREE.MeshStandardMaterial({
                    color: colors[Math.floor(Math.random() * colors.length)],
                    transparent: true, opacity: themeConfig.shapeOpacity, wireframe: Math.random() > 0.5
                });
                const mesh = new THREE.Mesh(geometry, material);
                mesh.position.set((Math.random() - 0.5) * 30, (Math.random() - 0.5) * 30, (Math.random() - 0.5) * 30);
                mesh.rotation.set(Math.random() * Math.PI, Math.random() * Math.PI, Math.random() * Math.PI);
                shapes.push(mesh); scene.add(mesh);
            }

            const light = new THREE.PointLight(0xffffff, 1, 100); light.position.set(10, 10, 10); scene.add(light);
            const ambient = new THREE.AmbientLight(0xffffff, 0.5); scene.add(ambient);
            camera.position.z = 20;

            function animate() {
                requestAnimationFrame(animate);
                particlesMesh.rotation.y += 0.001;
                shapes.forEach((s, idx) => {
                    s.rotation.x += 0.01 * (idx % 3 + 1);
                    s.rotation.y += 0.01 * (idx % 2 + 1);
                });
                renderer.render(scene, camera);
            }
            animate();

            window.addEventListener('resize', () => {
                camera.aspect = window.innerWidth / window.innerHeight; camera.updateProjectionMatrix();
                renderer.setSize(window.innerWidth, window.innerHeight);
            });
        })();

        // Game Logic
        const suits = ['♠', '♣', '♥', '♦'];
        const values = ['', 'A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];

        function startGame() {
            const bet = $('#betAmount').val();
            $.post('hilo.php?action=start', { bet: bet }, function (res) {
                if (res.success) {
                    $('#userMoney').text(res.money + ' gtlm');
                    updateCardDisplay(res.card);
                    $('#btnStart').prop('disabled', true);
                    $('#btnHigher, #btnLower, #btnCollect').prop('disabled', false);
                    $('#betAmount').prop('disabled', true);
                    $('#multVal').text('x1.00');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi',
                        text: res.message,
                        background: 'rgba(0,0,0,0.9)',
                        color: '#fff'
                    });
                }
            });
        }

        function guess(type) {
            $.post('hilo.php?action=guess', { guess: type }, function (res) {
                if (res.success) {
                    updateCardDisplay(res.card);
                    if (res.win) {
                        $('#multVal').text('x' + res.mult);
                        const bet = $('#betAmount').val();
                        $('#winEst').text('Ăn: ' + Math.round(bet * parseFloat(res.mult)).toLocaleString() + ' gtlm');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'BẠN ĐÃ THUA!',
                            text: 'Rất tiếc, đoán sai rồi.',
                            background: 'rgba(0,0,0,0.9)',
                            color: '#fff'
                        }).then(() => {
                            location.reload();
                        });
                    }
                }
            });
        }

        function collect() {
            $.post('hilo.php?action=collect', function (res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'THÀNH CÔNG',
                        text: 'Bạn đã nhận: ' + res.winAmount + ' gtlm',
                        background: 'rgba(0,0,0,0.9)',
                        color: '#fff'
                    }).then(() => {
                        location.reload();
                    });
                }
            });
        }

        function updateCardDisplay(val) {
            const card = { val: values[val], suit: suits[Math.floor(Math.random() * 4)] };
            $('#cardVal').text(card.val);
            $('#cardSuit').text(card.suit);
            $('#cardSuitSmall').text(card.suit);
            if (card.suit === '♥' || card.suit === '♦') $('.playing-card').addClass('red-suit');
            else $('.playing-card').removeClass('red-suit');
        }
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

</body>

</html>