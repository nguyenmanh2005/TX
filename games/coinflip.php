<?php
session_start();
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

$conn->query("CREATE TABLE IF NOT EXISTS history_coinflip (Id INT AUTO_INCREMENT PRIMARY KEY, Iduser INT NOT NULL, Bet DECIMAL(30,2) NOT NULL, Result VARCHAR(255) NOT NULL, WinAmount DECIMAL(30,2) NOT NULL, Time DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false];

    if ($action === 'play') {
        $bet = (float) ($_POST['bet'] ?? 0);
        $choice = $_POST['choice'] ?? 'Heads'; // Heads (Ngửa) or Tails (Sấp)

        if ($bet <= 0 || $bet > $money) {
            $response['message'] = "Số dư không đủ!";
        } else {
            $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");

            $result = (rand(0, 1) === 0) ? 'Heads' : 'Tails';
            $isWin = ($choice === $result);
            $winAmount = $isWin ? $bet * 1.95 : 0; // 1.95x payout

            if ($winAmount > 0)
                $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $userId");

            $profit = $winAmount - $bet;
            $his = $conn->prepare("INSERT INTO history_coinflip (Iduser,Bet,Result,WinAmount,Time) VALUES (?,?,?,?,NOW())");
            $his->bind_param("idss", $userId, $bet, $result, $profit);
            $his->execute();
            logGameHistoryWithAll($conn, $userId, 'Coin Flip', $bet, $winAmount, $isWin);

            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
            $response = [
                'success' => true,
                'result' => $result,
                'winAmount' => number_format($winAmount, 0, ',', '.'),
                'money' => number_format($newMoney, 0, ',', '.'),
                'win' => $isWin
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
    <title>Coinflip Premium - 3D Experience</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;500;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <link rel="stylesheet" href="../assets/css/game-effects.css">
    <style>
        :root {
            --primary: #f1c40f;
            --accent: #e67e22;
            --glass: rgba(255, 255, 255, 0.06);
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
        }

        * {
            cursor: url('../img/tay.png'), auto !important;
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
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.8);
            width: 95%;
            max-width: 1100px;
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 1.5rem;
            height: 85vh;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        .game-area {
            position: relative;
            background: rgba(0, 0, 0, 0.4);
            border-radius: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .stat-card {
            background: rgba(0, 0, 0, 0.3);
            padding: 1.2rem;
            border-radius: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            text-align: center;
        }

        .stat-card span {
            display: block;
            font-size: 0.7rem;
            opacity: 0.5;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .stat-card b {
            font-size: 1.5rem;
            font-family: 'Orbitron';
            color: var(--primary);
        }

        .choice-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            width: 100%;
        }

        .choice-btn {
            flex: 1;
            padding: 1rem;
            border-radius: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            cursor: pointer;
            transition: 0.3s;
            text-align: center;
        }

        .choice-btn:hover {
            border-color: var(--primary);
            background: rgba(241, 196, 15, 0.1);
        }

        .choice-btn.active {
            background: var(--primary);
            color: #000;
            font-weight: 900;
            box-shadow: 0 0 20px rgba(241, 196, 15, 0.4);
        }

        .choice-btn span {
            display: block;
            font-size: 0.7rem;
            opacity: 0.7;
        }

        .choice-btn b {
            display: block;
            font-size: 1.1rem;
            font-family: 'Orbitron';
        }

        .chip-selector {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .chip {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px dashed rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 0.7rem;
            cursor: pointer;
            transition: 0.3s;
            font-family: 'Orbitron';
        }

        .chip:hover,
        .chip.active {
            transform: scale(1.15);
            border-color: var(--primary);
            background: rgba(241, 196, 15, 0.2);
            border-style: solid;
        }

        .btn-flip {
            padding: 1.2rem;
            border-radius: 1.5rem;
            border: none;
            font-weight: 900;
            font-size: 1.3rem;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #000;
            box-shadow: 0 10px 30px rgba(241, 196, 15, 0.3);
            width: 100%;
            font-family: 'Orbitron';
            margin-top: 10px;
        }

        .btn-flip:hover:not(:disabled) {
            transform: translateY(-3px);
            filter: brightness(1.1);
        }

        .btn-flip:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        #coin-canvas {
            width: 100%;
            height: 400px;
        }

        .result-overlay {
            position: absolute;
            top: 20px;
            font-family: 'Orbitron';
            font-weight: 900;
            font-size: 2rem;
            color: var(--primary);
            text-shadow: 0 0 20px rgba(241, 196, 15, 0.5);
            opacity: 0;
        }
    </style>
</head>

<body>
    <div class="main-container">
        <div class="glass-card">
            <div class="sidebar">
                <div>
                    <h1
                        style="margin:0; font-size: 2rem; font-weight: 900; color: var(--primary); font-family: 'Orbitron'; letter-spacing: 2px;">
                        COINFLIP</h1>
                    <p style="margin:0; opacity:0.4; font-size: 0.7rem; letter-spacing: 1px;">High-Stakes 3D Protocol
                    </p>
                </div>

                <div class="stat-card">
                    <span>SỐ DƯ HIỆN TẠI</span>
                    <div style="display:flex; align-items:baseline; justify-content:center; gap:5px;">
                        <b id="userMoney"><?= number_format($money, 0, ',', '.') ?></b>
                        <small style="opacity:0.5; font-weight:900; font-size:0.6rem;">GTLM</small>
                    </div>
                </div>

                <div style="margin-top:auto;">
                    <div class="choice-container">
                        <div class="choice-btn active" data-choice="Heads">
                            <span>MẶT NGỬA</span>
                            <b>HEADS</b>
                        </div>
                        <div class="choice-btn" data-choice="Tails">
                            <span>MẶT SẤP</span>
                            <b>TAILS</b>
                        </div>
                    </div>

                    <div class="stat-card" style="margin-bottom:10px; padding:0.8rem;">
                        <span>Gtlm CƯỢC</span>
                        <input type="number" id="betAmount" value="1000" min="1000" step="1000"
                            style="background:none; border:none; color:var(--primary); font-family:'Orbitron'; font-size:1.2rem; font-weight:900; width:100%; text-align:center; outline:none;">
                    </div>

                    <div class="chip-selector">
                        <div class="chip active" data-val="1000">1K</div>
                        <div class="chip" data-val="5000">5K</div>
                        <div class="chip" data-val="10000">10K</div>
                        <div class="chip" data-val="50000">50K</div>
                        <div class="chip" data-val="100000">100K</div>
                    </div>

                    <button id="flipBtn" class="btn-flip" onclick="playGame()">⚡ TUNG NGAY</button>
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="../index.php"
                            style="color: #fff; text-decoration: none; font-size: 0.7rem; opacity: 0.3;">← Quay về
                            Dashboard</a>
                    </div>
                </div>
            </div>

            <div class="game-area">
                <div class="result-overlay" id="resultOverlay">HEADS WIN!</div>
                <div id="coin-canvas"></div>
            </div>
        </div>
    </div>

    <script>
        let scene, camera, renderer, coin;
        let isFlipping = false;
        let selectedChoice = 'Heads';

        function init3D() {
            scene = new THREE.Scene();
            camera = new THREE.PerspectiveCamera(45, $('#coin-canvas').width() / 400, 0.1, 1000);
            renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
            renderer.setSize($('#coin-canvas').width(), 400);
            $('#coin-canvas').append(renderer.domElement);

            // Lighting Upgrade
            const ambientLight = new THREE.AmbientLight(0xffffff, 1.2); // Brighter ambient
            scene.add(ambientLight);

            const hemiLight = new THREE.HemisphereLight(0xffffff, 0x444444, 1);
            scene.add(hemiLight);

            const dirLight = new THREE.DirectionalLight(0xffffff, 1.5);
            dirLight.position.set(5, 10, 7);
            scene.add(dirLight);

            // Create Coin
            const coinGeometry = new THREE.CylinderGeometry(2, 2, 0.2, 64);
            const goldMaterial = new THREE.MeshStandardMaterial({
                color: 0xffd700,
                metalness: 0.9,
                roughness: 0.1,
                emissive: 0xffd700,
                emissiveIntensity: 0.2
            });

            // Texture for sides (Heads/Tails)
            const loader = new THREE.TextureLoader();
            const createTexture = (text) => {
                const canvas = document.createElement('canvas');
                canvas.width = 512; canvas.height = 512;
                const ctx = canvas.getContext('2d');
                // Vibrant gold background
                const grad = ctx.createRadialGradient(256, 256, 50, 256, 256, 250);
                grad.addColorStop(0, '#fff200');
                grad.addColorStop(1, '#ffc400');
                ctx.fillStyle = grad; ctx.fillRect(0, 0, 512, 512);

                // Shining text
                ctx.fillStyle = '#8b6508'; ctx.font = 'bold 350px Orbitron';
                ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                ctx.shadowColor = 'rgba(0,0,0,0.5)'; ctx.shadowBlur = 20;
                ctx.fillText(text, 256, 256);

                // Border
                ctx.strokeStyle = '#8b6508'; ctx.lineWidth = 30;
                ctx.strokeRect(20, 20, 472, 472);
                return new THREE.CanvasTexture(canvas);
            };

            const headsTex = createTexture('H');
            const tailsTex = createTexture('T');

            const materials = [
                goldMaterial, // Side
                new THREE.MeshStandardMaterial({ map: headsTex, metalness: 0.8, roughness: 0.2 }), // Top
                new THREE.MeshStandardMaterial({ map: tailsTex, metalness: 0.8, roughness: 0.2 })  // Bottom
            ];

            coin = new THREE.Mesh(coinGeometry, materials);
            coin.rotation.x = Math.PI / 2;
            scene.add(coin);

            camera.position.z = 6;
            animate();
        }

        function animate() {
            requestAnimationFrame(animate);
            if (!isFlipping) {
                coin.rotation.y += 0.01;
            }
            renderer.render(scene, camera);
        }

        $('.choice-btn').click(function () {
            $('.choice-btn').removeClass('active');
            $(this).addClass('active');
            selectedChoice = $(this).data('choice');
        });

        $('.chip').click(function () {
            $('.chip').removeClass('active');
            $(this).addClass('active');
            $('#betAmount').val($(this).data('val'));
        });

        function playGame() {
            if (isFlipping) return;
            const bet = parseInt($('#betAmount').val());
            if (bet <= 0) return;

            isFlipping = true;
            $('#flipBtn').prop('disabled', true).text('ĐANG TUNG...');
            $('#resultOverlay').css('opacity', 0);

            $.post('coinflip.php?action=play', { bet: bet, choice: selectedChoice }, function (res) {
                if (res.success) {
                    // Animation Flip
                    const flipCount = 10 + Math.random() * 5;
                    const targetRotation = res.result === 'Heads' ? 0 : Math.PI;

                    gsap.to(coin.position, { y: 3, duration: 0.6, yoyo: true, repeat: 1, ease: "power2.out" });
                    gsap.to(coin.rotation, {
                        x: Math.PI * 2 * flipCount + (Math.PI / 2),
                        z: Math.PI * 2 * flipCount,
                        duration: 1.2,
                        ease: "power2.inOut",
                        onComplete: () => {
                            isFlipping = false;
                            $('#flipBtn').prop('disabled', false).text('⚡ TUNG NGAY');
                            $('#userMoney').text(res.money);

                            // Adjust final rotation to show result
                            const finalX = res.result === 'Heads' ? Math.PI / 2 : -Math.PI / 2;
                            gsap.to(coin.rotation, { x: finalX, y: 0, z: 0, duration: 0.3 });

                            $('#resultOverlay').text(res.result.toUpperCase() + (res.win ? ' WIN!' : ' LOSE!'))
                                .css({ opacity: 1, color: res.win ? '#00ff88' : '#ff4757' });

                            if (res.win) {
                                if (window.GameEffects) window.GameEffects.showWin(parseInt(res.winAmount.replace(/\./g, '')));
                                Swal.fire({ title: 'THẮNG LỚN!', html: `Bạn nhận được <b style="color:#f1c40f">${res.winAmount} gtlm</b>`, icon: 'success', background: '#1a1a1a', color: '#fff', timer: 2000, showConfirmButton: false });
                            } else {
                                if (window.GameEffects) window.GameEffects.showLoss(0);
                            }
                        }
                    });
                } else {
                    Swal.fire('Lỗi', res.message, 'error');
                    isFlipping = false;
                    $('#flipBtn').prop('disabled', false).text('⚡ TUNG NGAY');
                }
            });
        }

        $(document).ready(init3D);
    </script>

    <canvas id="threejs-background"></canvas>
    <script>
        (function () {
            window.themeConfig = {
                particleCount: <?= $particleCount ?>,
                particleSize: <?= $particleSize ?>,
                particleColor: '<?= $particleColor ?>',
                particleOpacity: <?= $particleOpacity ?>,
                shapeCount: <?= $shapeCount ?>,
                shapeColors: <?= json_encode($shapeColors) ?>,
                shapeOpacity: <?= $shapeOpacity ?>,
                bgGradient: <?= json_encode($bgGradient) ?>
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