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

$soDu = $user['Money'];
$tenNguoiChoi = $user['Name'];

// --- AJAX HANDLER ---
if (isset($_GET['action']) && $_GET['action'] === 'play_rps') {
    header('Content-Type: application/json');
    $chon = $_POST["chon"] ?? "";
    $cuoc = (int) ($_POST["cuoc"] ?? 0);

    if (!in_array($chon, ["Đá", "Giấy", "Kéo"])) {
        echo json_encode(['success' => false, 'message' => '❌ Vui lòng chọn Đá, Giấy hoặc Kéo!']);
        exit;
    }
    if ($cuoc > $soDu || $cuoc <= 0) {
        echo json_encode(['success' => false, 'message' => '⚠️ Số Gtlm không đủ hoặc cược không hợp lệ!']);
        exit;
    }

    $choices = ["Đá", "Giấy", "Kéo"];
    $botChon = $choices[rand(0, 2)];
    $emojis = ["Đá" => "👊", "Giấy" => "✋", "Kéo" => "✌️"];

    $status = ""; // win, draw, lose
    $msg = "";
    $winAmount = 0;
    $laThang = false;

    if ($chon === $botChon) {
        $status = "draw";
        $msg = "🤝 Hòa! Cả hai cùng chọn " . $emojis[$chon] . ".";
        $winAmount = $cuoc; // Refund
    } elseif (
        ($chon === "Đá" && $botChon === "Kéo") ||
        ($chon === "Giấy" && $botChon === "Đá") ||
        ($chon === "Kéo" && $botChon === "Giấy")
    ) {
        $status = "win";
        $laThang = true;
        $winAmount = $cuoc * 2;
        $msg = "🎉 Bạn thắng! " . $emojis[$chon] . " thắng " . $emojis[$botChon] . ". Nhận " . number_format($cuoc) . " gtlm!";
    } else {
        $status = "lose";
        $msg = "😢 Bạn thua! " . $emojis[$chon] . " thua " . $emojis[$botChon] . ".";
        $winAmount = 0;
    }

    $newMoney = $soDu - $cuoc + $winAmount;
    $conn->query("UPDATE users SET Money = $newMoney WHERE Iduser = $userId");

    if (file_exists('../game_history_helper.php')) {
        require_once '../game_history_helper.php';
        logGameHistoryWithAll($conn, $userId, 'RPS', $cuoc, ($laThang ? $cuoc : 0), $laThang);
    }

    echo json_encode([
        'success' => true,
        'userChon' => $chon,
        'botChon' => $botChon,
        'userEmoji' => $emojis[$chon],
        'botEmoji' => $emojis[$botChon],
        'status' => $status,
        'newMoney' => number_format($newMoney) . ' gtlm',
        'message' => $msg,
        'laThang' => $laThang
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Oẳn Tù Tì - Premium Edition</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/canvas-confetti/1.6.0/confetti.browser.min.js"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            cursor: url('../img/chuot.png'), auto !important;
            font-family: 'Poppins', sans-serif;
            background:
                <?= $bgGradientCSS ?>
            ;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            overflow: hidden;
        }

        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 40px;
            padding: 50px;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.6);
            text-align: center;
            width: 600px;
            position: relative;
        }

        .vs-stage {
            display: flex;
            justify-content: space-around;
            align-items: center;
            margin: 40px 0;
            min-height: 150px;
        }

        .hand-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .hand-emoji {
            font-size: 80px;
            filter: drop-shadow(0 0 20px rgba(255, 255, 255, 0.3));
            transition: 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .vs-label {
            font-size: 40px;
            font-weight: 800;
            color: gold;
            font-style: italic;
            opacity: 0.5;
        }

        .player-label {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #aaa;
        }

        .balance {
            color: gold;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .choice-group {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 30px 0;
        }

        .choice-btn {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 35px;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            outline: none;
        }

        .choice-btn:hover {
            transform: translateY(-5px);
            border-color: gold;
            background: rgba(255, 215, 0, 0.1);
        }

        .choice-btn.active {
            border-color: gold;
            background: gold;
            transform: scale(1.1);
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.4);
        }

        .bet-input {
            padding: 12px 25px;
            border-radius: 25px;
            border: none;
            background: rgba(0, 0, 0, 0.3);
            color: white;
            width: 180px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: bold;
        }

        .btn-play {
            padding: 15px 50px;
            border-radius: 35px;
            border: none;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 2px;
            cursor: pointer;
            transition: 0.3s;
            width: 100%;
            max-width: 300px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .btn-play:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            filter: grayscale(1);
        }

        .status-msg {
            margin-top: 30px;
            font-size: 18px;
            font-weight: 600;
            min-height: 25px;
            color: gold;
        }

        @keyframes shake {
            0% {
                transform: translateY(0);
            }

            25% {
                transform: translateY(-30px);
            }

            50% {
                transform: translateY(0);
            }

            75% {
                transform: translateY(-30px);
            }

            100% {
                transform: translateY(0);
            }
        }

        .shaking {
            animation: shake 0.5s infinite linear;
        }
    </style>
</head>

<body>


    <div class="glass-panel">
        <h1 style="margin: 0; font-size: 32px; letter-spacing: 3px;">✌️ OẲN TÙ TÌ</h1>
        <div class="balance">💰 VÀNG: <span id="balance-val"><?= number_format($soDu) ?></span></div>

        <div class="vs-stage">
            <div class="hand-container">
                <div class="player-label">BẠN</div>
                <div class="hand-emoji" id="user-hand">👊</div>
            </div>
            <div class="vs-label">VS</div>
            <div class="hand-container">
                <div class="player-label">BOT</div>
                <div class="hand-emoji" id="bot-hand">👊</div>
            </div>
        </div>

        <div class="choice-group">
            <button class="choice-btn" data-choice="Đá">👊</button>
            <button class="choice-btn" data-choice="Giấy">✋</button>
            <button class="choice-btn" data-choice="Kéo">✌️</button>
        </div>

        <input type="number" id="cuoc" class="bet-input" value="10000" step="5000">
        <br>
        <button class="btn-play" id="btn-play">CHẾT NÀY!</button>

        <div class="status-msg" id="status-msg">Chọn Đá, Giấy hoặc Kéo để so tài!</div>
        <p><a href="../index.php" style="color: rgba(255,255,255,0.3); text-decoration: none; font-size: 14px;">🏠 Trang
                chủ</a></p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function () {
            window.themeConfig = { particleCount: <?= $particleCount ?>, particleSize: <?= $particleSize ?>, particleColor: '<?= $particleColor ?>', particleOpacity: <?= $particleOpacity ?>, shapeCount: <?= $shapeCount ?>, shapeColors: <?= json_encode($shapeColors) ?>, shapeOpacity: <?= $shapeOpacity ?>, bgGradient: <?= json_encode($bgGradient) ?> };
            const script = document.createElement('script'); script.src = '../threejs-background.js'; document.head.appendChild(script);
        })();

        let selectedChoice = "";
        document.querySelectorAll('.choice-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.choice-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                selectedChoice = this.dataset.choice;
                document.getElementById('user-hand').textContent = this.textContent;
            });
        });

        document.getElementById('btn-play').addEventListener('click', async function () {
            const cuoc = document.getElementById('cuoc').value;
            if (!selectedChoice) { Swal.fire('Lỗi', 'Hãy chọn Đá, Giấy hoặc Kéo!', 'warning'); return; }
            if (cuoc <= 0) { Swal.fire('Lỗi', 'gtlm cược không hợp lệ!', 'error'); return; }

            const btn = this;
            btn.disabled = true;

            const userHand = document.getElementById('user-hand');
            const botHand = document.getElementById('bot-hand');
            const statusMsg = document.getElementById('status-msg');

            // Animation lắc tay
            userHand.textContent = '👊';
            botHand.textContent = '👊';
            userHand.classList.add('shaking');
            botHand.classList.add('shaking');
            statusMsg.textContent = 'Oẳn tù tì...';

            try {
                const fd = new FormData();
                fd.append('chon', selectedChoice);
                fd.append('cuoc', cuoc);

                const res = await fetch('rps.php?action=play_rps', { method: 'POST', body: fd });
                const data = await res.json();

                setTimeout(() => {
                    userHand.classList.remove('shaking');
                    botHand.classList.remove('shaking');

                    if (data.success) {
                        userHand.textContent = data.userEmoji;
                        botHand.textContent = data.botEmoji;
                        document.getElementById('balance-val').textContent = data.newMoney;
                        statusMsg.textContent = data.message;
                        btn.disabled = false;

                        if (data.status === 'win') {
                            confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
                            Swal.fire('Thắng rồi!', data.message, 'success');
                        } else if (data.status === 'draw') {
                            Swal.fire('Hòa', data.message, 'info');
                        } else {
                            Swal.fire('Thua rồi', data.message, 'error');
                        }
                    } else {
                        Swal.fire('Lỗi', data.message, 'error');
                        btn.disabled = false;
                    }
                }, 1500); // 1.5s animation
            } catch (e) {
                console.error(e);
                btn.disabled = false;
            }
        });
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