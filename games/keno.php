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
$conn->query("CREATE TABLE IF NOT EXISTS history_keno (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Iduser INT NOT NULL,
    Bet DECIMAL(30,2) NOT NULL,
    Result VARCHAR(255) NOT NULL,
    WinAmount DECIMAL(30,2) NOT NULL,
    Time DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Keno Paytable (Balanced)
// Indices: 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 matches
$paytable = [
    1 => [0, 3],
    2 => [0, 1, 5],
    3 => [0, 1, 2, 15],
    4 => [0, 0, 2, 5, 50],
    5 => [0, 0, 1, 3, 15, 500],
    6 => [0, 0, 0, 2, 10, 50, 1000],
    7 => [0, 0, 0, 1, 3, 20, 100, 2000],
    8 => [0, 0, 0, 0, 2, 10, 50, 500, 5000],
    9 => [0, 0, 0, 0, 1, 5, 25, 200, 1000, 10000],
    10 => [0, 0, 0, 0, 0, 2, 10, 50, 500, 2000, 20000]
];

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false];

    if ($action === 'draw') {
        $bet = (float) ($_POST['bet'] ?? 0);
        $selected = $_POST['numbers'] ?? []; // Array of 1-10 numbers
        $count = count($selected);

        if ($bet <= 0 || $count < 1 || $count > 10) {
            echo json_encode(['success' => false, 'message' => "Yêu cầu không hợp lệ!"]);
            exit;
        }

        $conn->begin_transaction();
        try {
            // SELECT FOR UPDATE để khóa bản ghi user
            $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();

            if (!$u || $u['Money'] < $bet) {
                throw new Exception("Ngân khố không đủ để quay số!");
            }

            $pool = range(1, 80);
            shuffle($pool);
            $drawn = array_slice($pool, 0, 20);
            sort($drawn);

            $matches = array_intersect($selected, $drawn);
            $matchCount = count($matches);

            $mults = $paytable[$count] ?? [0];
            $mult = $mults[$matchCount] ?? 0;
            $winAmount = $bet * $mult;
            $profit = $winAmount - $bet;

            // Cập nhật số dư tương đối
            $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            $stmt->bind_param("di", $profit, $userId);
            $stmt->execute();

            $resStr = "Picks: $count | Matches: $matchCount";
            $his = $conn->prepare("INSERT INTO history_keno (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
            $his->bind_param("idid", $userId, $bet, $resStr, $profit);
            $his->execute();

            // Log history helper
            if (file_exists('../game_history_helper.php')) {
                require_once '../game_history_helper.php';
                logGameHistoryWithAll($conn, $userId, 'Keno Premium', $bet, $winAmount, $winAmount > 0);
            }

            $conn->commit();

            $finalMoney = $u['Money'] + $profit;
            $response = [
                'success' => true,
                'drawn' => $drawn,
                'matchCount' => $matchCount,
                'winAmount' => number_format($winAmount, 0, ',', '.'),
                'money' => number_format($finalMoney, 0, ',', '.')
            ];
        } catch (Exception $e) {
            $conn->rollback();
            $response = ['success' => false, 'message' => $e->getMessage()];
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
    <title>Keno - Xổ Số Cao Cấp</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-color: #00d2ff;
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
            max-width: 1200px;
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

        .flex-game {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .keno-grid {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 10px;
            flex: 2;
            min-width: 300px;
        }

        .keno-number {
            aspect-ratio: 1;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
        }

        .keno-number:hover {
            background: rgba(0, 210, 255, 0.2);
            transform: scale(1.05);
        }

        .keno-number.selected {
            background: var(--primary-color);
            color: #000;
            box-shadow: 0 0 15px var(--primary-color);
        }

        .keno-number.drawn {
            border: 2px solid var(--accent-color);
            color: var(--accent-color);
        }

        .keno-number.match {
            background: var(--accent-color) !important;
            color: #000;
            box-shadow: 0 0 20px var(--accent-color);
        }

        .sidebar {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 2rem;
            min-width: 300px;
        }

        .info-card {
            background: rgba(0, 0, 0, 0.2);
            padding: 1.5rem;
            border-radius: 1.5rem;
            border: 1px solid var(--glass-border);
        }

        .btn-draw {
            width: 100%;
            padding: 1.2rem;
            border: none;
            border-radius: 1rem;
            background: linear-gradient(135deg, var(--primary-color), #3a7bd5);
            color: #fff;
            font-weight: 900;
            font-size: 1.2rem;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-draw:hover:not(:disabled) {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 210, 255, 0.3);
        }

        .btn-draw:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        button,
        a,
        input,
        select,
        .btn-help-game,
        .help-close-x,
        .keno-number {
            cursor: url('../img/tay.png'), pointer !important;
        }

        .pay-table {
            font-size: 0.8rem;
            overflow: hidden;
            height: 0;
            transition: 0.5s;
        }

        .pay-table.show {
            height: auto;
            margin-top: 15px;
            border-top: 1px solid var(--glass-border);
            padding-top: 10px;
        }

        .pay-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>

<body>
    <div id="threejs-background"></div>
    <div class="main-container">
        <div class="glass-card"
            style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 3rem;">
            <div>
                <h1 style="margin:0; font-size: 2.5rem; font-weight: 900; color: var(--primary-color);">KENO</h1>
                <p style="margin:0; opacity:0.5">Xổ số 80 số - Payout Cân Bằng</p>
            </div>
            <div style="display:flex; align-items:center; gap:2rem;">
                <div id="userMoney" style="font-weight:900; font-size:1.8rem; color:var(--accent-color)">
                    <?php echo number_format($money, 0, ',', '.'); ?> gtlm</div>
                <a href="../index.php"
                    style="color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.2); padding: 0.5rem 1.5rem; border-radius: 50px; font-weight: 900;">THOÁT</a>
            </div>
        </div>

        <div class="glass-card">
            <div class="flex-game">
                <div class="keno-grid" id="kenoGrid">
                    <?php for ($i = 1; $i <= 80; $i++): ?>
                        <div class="keno-number" onclick="selectNumber(this, <?php echo $i; ?>)"><?php echo $i; ?></div>
                    <?php endfor; ?>
                </div>

                <div class="sidebar">
                    <div class="info-card">
                        <h3 style="margin-bottom:10px">MỨC CƯỢC</h3>
                        <input type="number" id="betAmount" value="10000"
                            style="width:100%; background:none; border:none; border-bottom:2px solid var(--primary-color); color:#fff; font-size:1.5rem; font-weight:900; outline:none;">
                        <p style="margin-top:15px">Chọn: <span id="selectCount"
                                style="color:var(--primary-color); font-weight:900">0</span>/10 số</p>

                        <div style="margin-top:10px; font-size:0.9rem; color:var(--accent-color); cursor:pointer;"
                            onclick="$('#payTable').toggleClass('show')">
                            ▶ Xem Bảng Thưởng (Paytable)
                        </div>
                        <div class="pay-table" id="payTable">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                    <button class="btn-draw" id="drawBtn" disabled onclick="drawKeno()">QUAY SỐ</button>
                    <div class="info-card">
                        <h3>KẾT QUẢ</h3>
                        <div id="resultText" style="font-size:1.1rem; font-weight:700; margin-top:10px;">Vui lòng chọn
                            từ 1-10 số.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Theme-Aware Three.js
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

            const particlesGeometry = new THREE.BufferGeometry();
            const posArray = new Float32Array(themeConfig.particleCount * 3);
            for (let i = 0; i < themeConfig.particleCount * 3; i++) posArray[i] = (Math.random() - 0.5) * 40;
            particlesGeometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));
            const particlesMaterial = new THREE.PointsMaterial({ size: themeConfig.particleSize, color: parseInt(themeConfig.particleColor.replace('#', ''), 16), transparent: true, opacity: themeConfig.particleOpacity });
            const particlesMesh = new THREE.Points(particlesGeometry, particlesMaterial);
            scene.add(particlesMesh);

            const shapes = [];
            const colors = themeConfig.shapeColors.map(c => parseInt(c.replace('#', ''), 16));
            for (let i = 0; i < themeConfig.shapeCount; i++) {
                const geometry = new THREE.IcosahedronGeometry(Math.random() * 0.5 + 0.3, 0);
                const material = new THREE.MeshStandardMaterial({ color: colors[Math.floor(Math.random() * colors.length)], transparent: true, opacity: themeConfig.shapeOpacity, wireframe: Math.random() > 0.5 });
                const mesh = new THREE.Mesh(geometry, material);
                mesh.position.set((Math.random() - 0.5) * 30, (Math.random() - 0.5) * 30, (Math.random() - 0.5) * 30);
                shapes.push(mesh); scene.add(mesh);
            }
            const light = new THREE.PointLight(0xffffff, 1, 100); light.position.set(10, 10, 10); scene.add(light);
            const ambient = new THREE.AmbientLight(0xffffff, 0.5); scene.add(ambient);
            camera.position.z = 20;

            function animate() {
                requestAnimationFrame(animate);
                particlesMesh.rotation.y += 0.001;
                shapes.forEach((s, idx) => { s.rotation.x += 0.01; s.rotation.y += 0.01; });
                renderer.render(scene, camera);
            }
            animate();
            window.addEventListener('resize', () => { camera.aspect = window.innerWidth / window.innerHeight; camera.updateProjectionMatrix(); renderer.setSize(window.innerWidth, window.innerHeight); });
        })();

        // Game Logic
        const paytable = <?= json_encode($paytable) ?>;
        let selectedNumbers = [];

        function selectNumber(el, num) {
            if ($(el).hasClass('selected')) {
                selectedNumbers = selectedNumbers.filter(n => n !== num);
                $(el).removeClass('selected');
            } else {
                if (selectedNumbers.length >= 10) return;
                selectedNumbers.push(num);
                $(el).addClass('selected');
            }
            $('#selectCount').text(selectedNumbers.length);
            $('#drawBtn').prop('disabled', selectedNumbers.length === 0);
            updatePaytableUI();
        }

        function updatePaytableUI() {
            const count = selectedNumbers.length;
            if (count === 0) { $('#payTable').html('Vui lòng chọn số.'); return; }
            const mults = paytable[count];
            let html = `<strong>Thưởng cho ${count} số chọn:</strong><br>`;
            mults.forEach((m, idx) => {
                if (m > 0) html += `<div class="pay-row"><span>Khớp ${idx}:</span> <span>x${m}</span></div>`;
            });
            $('#payTable').html(html);
        }

        function drawKeno() {
            const bet = $('#betAmount').val();
            $('#drawBtn').prop('disabled', true);
            $('.keno-number').removeClass('drawn match');

            $.post('keno.php?action=draw', { bet: bet, numbers: selectedNumbers }, function (res) {
                if (res.success) {
                    let i = 0;
                    const interval = setInterval(() => {
                        const num = res.drawn[i];
                        const el = $(`.keno-number`).filter(function () { return $(this).text() == num; });
                        el.addClass('drawn');
                        if (selectedNumbers.includes(num)) el.addClass('match');
                        i++;
                        if (i >= 20) {
                            clearInterval(interval);
                            const icon = res.matchCount > 0 ? 'success' : 'error';
                            const title = res.matchCount > 0 ? 'KẾT QUẢ' : 'THUA RỒI';
                            Swal.fire({
                                icon: icon,
                                title: title,
                                html: `Số khớp: <span style="color:var(--primary-color); font-weight:900">${res.matchCount}</span><br>gtlm thắng: <span style="color:var(--accent-color); font-weight:900">${res.winAmount} gtlm</span>`,
                                background: 'rgba(0,0,0,0.9)',
                                color: '#fff'
                            });
                            $('#resultText').html(`Số khớp: ${res.matchCount}<br>Thắng: ${res.winAmount} gtlm`);
                            $('#userMoney').text(res.money + ' gtlm');
                            $('#drawBtn').prop('disabled', false);
                        }
                    }, 80);
                } else {
                    Swal.fire({ icon: 'error', title: 'Lỗi', text: res.message, background: 'rgba(0,0,0,0.9)', color: '#fff' });
                    $('#drawBtn').prop('disabled', false);
                }
            });
        }
    </script>
    <?php require_once '../casino_help.php'; ?>


    
    


    


    


    <!-- Premium Effects System -->
    <canvas id="threejs-background"></canvas>
    <script>
        (function() {
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
