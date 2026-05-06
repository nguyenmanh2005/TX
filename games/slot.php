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

$symbols = ["🍒", "🍋", "🍊", "🍇", "⭐", "💎", "🔔", "7️⃣"];

// --- AJAX HANDLER ---
if (isset($_GET['action']) && $_GET['action'] === 'spin') {
    header('Content-Type: application/json');
    $cuoc = (int) ($_GET['bet'] ?? 0);

    if ($cuoc > $soDu || $cuoc < 1000) {
        echo json_encode(['success' => false, 'message' => '⚠️ Cược tối thiểu 1.000 gtlm và không vượt quá Số Gtlm!']);
        exit;
    }

    // Quay 3 cuộn
    $reels = [];
    for ($i = 0; $i < 3; $i++) {
        $reels[] = $symbols[array_rand($symbols)];
    }

    $winAmount = 0;
    $isWin = false;

    // Tính thắng (Jackpot x10 cho 3 cái, x1.5 cho bất kỳ 2 cái)
    if ($reels[0] === $reels[1] && $reels[1] === $reels[2]) {
        // 3 giống nhau (Jackpot)
        $isWin = true;
        if ($reels[0] === "💎")
            $winAmount = $cuoc * 10;
        elseif ($reels[0] === "7️⃣")
            $winAmount = $cuoc * 8;
        elseif ($reels[0] === "⭐")
            $winAmount = $cuoc * 6;
        else
            $winAmount = $cuoc * 4;
    } elseif ($reels[0] === $reels[1] || $reels[1] === $reels[2] || $reels[0] === $reels[2]) {
        // 2 giống nhau
        $isWin = true;
        $winAmount = floor($cuoc * 1.5);
    }

    $finalBalance = $soDu - $cuoc + $winAmount;
    $conn->query("UPDATE users SET Money = $finalBalance WHERE Iduser = $userId");

    // Log sử
    if (file_exists('../game_history_helper.php')) {
        require_once '../game_history_helper.php';
        logGameHistoryWithAll($conn, $userId, 'Slot Machine', $cuoc, $winAmount, $isWin);
    }

    echo json_encode([
        'success' => true,
        'reels' => $reels,
        'winAmount' => $winAmount,
        'newBalance' => number_format($finalBalance) . ' gtlm',
        'message' => $isWin ? "🎉 CHÚC MỪNG! Bạn thắng " . number_format($winAmount) . " gtlm!" : "💀 Rất tiếc! Chúc bạn may mắn lần sau."
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Slot Machine Premium - Neon Fortune</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Poppins:wght@400;600;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --neon-gold: #ffd700;
            --neon-purple: #bc13fe;
            --glass: rgba(255, 255, 255, 0.1);
        }

        body {
            margin: 0;
            cursor: url('../img/chuot.png'), auto !important;
            font-family: 'Poppins', sans-serif;
            background:
                <?= $bgGradientCSS ?>
            ;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            color: white;
            overflow-x: hidden;
        }

        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        .header-nav {
            width: 100%;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
            box-sizing: border-box;
        }

        .game-logo {
            font-family: 'Cinzel', serif;
            font-size: 28px;
            color: var(--neon-gold);
            letter-spacing: 5px;
            text-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
        }

        .user-balance {
            background: rgba(0, 0, 0, 0.4);
            padding: 10px 30px;
            border-radius: 40px;
            border: 2px solid var(--neon-gold);
            font-weight: 800;
            color: var(--neon-gold);
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.2);
        }

        .main-stage {
            margin-top: 60px;
            text-align: center;
            max-width: 800px;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Slot Cabinet */
        .slot-cabinet {
            background: rgba(20, 20, 20, 0.8);
            backdrop-filter: blur(20px);
            border: 4px solid #333;
            border-radius: 50px;
            padding: 40px;
            box-shadow: 0 50px 100px rgba(0, 0, 0, 0.8), inset 0 0 50px rgba(255, 215, 0, 0.1);
            border-color: var(--neon-gold);
            position: relative;
            margin-bottom: 40px;
            width: 100%;
            max-width: 600px;
        }

        .slot-cabinet::after {
            content: '';
            position: absolute;
            inset: -10px;
            border-radius: 60px;
            border: 2px solid var(--neon-purple);
            opacity: 0.3;
            pointer-events: none;
        }

        .reels-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            background: #111;
            padding: 20px;
            border-radius: 20px;
            border: 2px solid #444;
            overflow: hidden;
            height: 180px;
        }

        .reel-window {
            width: 140px;
            height: 140px;
            background: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 80px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(180deg, #fff 0%, #eee 100%);
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.2);
        }

        .reel-strip {
            position: absolute;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: transform 0.1s linear;
        }

        .symbol {
            height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Controls */
        .controls {
            margin-top: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .bet-input-wrap {
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(0, 0, 0, 0.4);
            padding: 5px 20px;
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .bet-input-wrap label {
            font-size: 14px;
            font-weight: 600;
            color: #888;
        }

        .bet-input-wrap input {
            background: transparent;
            border: none;
            color: var(--neon-gold);
            font-size: 20px;
            font-weight: 800;
            width: 150px;
            text-align: center;
            outline: none;
        }

        .spin-btn {
            background: linear-gradient(135deg, var(--neon-gold) 0%, #b8860b 100%);
            color: black;
            border: none;
            padding: 20px 60px;
            border-radius: 50px;
            font-size: 24px;
            font-weight: 900;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.4);
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .spin-btn:hover:not(:disabled) {
            transform: scale(1.1) translateY(-5px);
            box-shadow: 0 0 50px rgba(255, 215, 0, 0.6);
        }

        .spin-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            filter: grayscale(1);
        }

        .status-marquee {
            position: fixed;
            bottom: 30px;
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid var(--neon-gold);
            color: var(--neon-gold);
            padding: 12px 50px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 1px;
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.2);
        }

        .back-link {
            color: rgba(255, 255, 255, 0.3);
            text-decoration: none;
            font-size: 14px;
            margin-top: 30px;
            transition: 0.3s;
        }

        .back-link:hover {
            color: var(--neon-gold);
        }

        @keyframes blurRolling {
            0% {
                filter: blur(0);
            }

            50% {
                filter: blur(10px);
            }

            100% {
                filter: blur(0);
            }
        }

        .rolling {
            animation: blurRolling 0.1s infinite;
        }
    </style>
</head>

<body>


    <header class="header-nav">
        <div class="game-logo">NEON FORTUNE SLOTS</div>
        <div class="user-balance">💰 <span id="balance-txt"><?= number_format($soDu) ?> gtlm</span></div>
        <div style="font-size: 13px; color: #666;">PLAYER: <b><?= htmlspecialchars($tenNguoiChoi) ?></b></div>
    </header>

    <div class="main-stage">
        <div class="slot-cabinet">
            <div class="reels-container">
                <div class="reel-window" id="reel-0">🍒</div>
                <div class="reel-window" id="reel-1">🍒</div>
                <div class="reel-window" id="reel-2">🍒</div>
            </div>

            <div class="controls">
                <div class="bet-input-wrap">
                    <label>MỨC CƯỢC</label>
                    <input type="number" id="bet-amount" value="10000" min="1000" step="1000">
                </div>
                <button class="spin-btn" id="spin-trigger">🎰 QUAY NGAY</button>
            </div>
        </div>

        <a href="../index.php" class="back-link">🏠 QUAY LẠI SẢNH CHỜ</a>
    </div>

    <div class="status-marquee" id="status-bar">CHÚC BẠN MAY MẮN! HÃY CHỌN MỨC CƯỢC VÀ BẤM QUAY.</div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function () {
            window.themeConfig = { particleCount: <?= $particleCount ?>, particleSize: <?= $particleSize ?>, particleColor: '<?= $particleColor ?>', particleOpacity: <?= $particleOpacity ?>, shapeCount: <?= $shapeCount ?>, shapeColors: <?= json_encode($shapeColors) ?>, shapeOpacity: <?= $shapeOpacity ?>, bgGradient: <?= json_encode($bgGradient) ?> };
            const script = document.createElement('script'); script.src = '../threejs-background.js'; document.head.appendChild(script);
        })();

        const symbols = ["🍒", "🍋", "🍊", "🍇", "⭐", "💎", "7️⃣", "🔔"];
        let spinning = false;

        async function spin() {
            if (spinning) return;
            const bet = parseInt(document.getElementById('bet-amount').value);
            if (isNaN(bet) || bet < 1000) {
                Swal.fire('Lỗi', 'Cược tối thiểu 1.000 gtlm!', 'error');
                return;
            }

            spinning = true;
            document.getElementById('spin-trigger').disabled = true;
            document.getElementById('status-bar').textContent = "🎰 ĐANG QUAY... CHỜ ĐỢI VẬN MAY MỈM CƯỜI!";

            const reelWindows = [document.getElementById('reel-0'), document.getElementById('reel-1'), document.getElementById('reel-2')];

            // Bắt đầu hiệu ứng quay ảo
            const intervals = reelWindows.map((rw, idx) => {
                rw.classList.add('rolling');
                return setInterval(() => {
                    rw.textContent = symbols[Math.floor(Math.random() * symbols.length)];
                }, 80);
            });

            try {
                const res = await fetch(`slot.php?action=spin&bet=${bet}`);
                const data = await res.json();

                if (data.success) {
                    // Dừng từng cột theo thứ tự
                    for (let i = 0; i < 3; i++) {
                        await new Promise(r => setTimeout(r, 600 + (i * 400)));
                        clearInterval(intervals[i]);
                        reelWindows[i].classList.remove('rolling');
                        reelWindows[i].textContent = data.reels[i];
                        // Hiệu ứng "pop" khi dừng
                        reelWindows[i].style.transform = "scale(1.2)";
                        setTimeout(() => reelWindows[i].style.transform = "scale(1)", 100);
                    }

                    setTimeout(() => {
                        document.getElementById('balance-txt').textContent = data.newBalance;
                        document.getElementById('status-bar').textContent = data.message;

                        if (data.winAmount > 0) {
                            if (typeof confetti === 'function') {
                                confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
                                if (data.winAmount >= bet * 5) {
                                    confetti({ particleCount: 300, spread: 160, origin: { y: 0.6 }, colors: ['#ffd700', '#ffffff', '#bc13fe'] });
                                }
                            }
                            Swal.fire({ title: '🎊 CHIẾN THẮNG!', text: data.message, icon: 'success', confirmButtonColor: '#ffd700', confirmButtonText: 'TUYỆT VỜI' });
                        }

                        spinning = false;
                        document.getElementById('spin-trigger').disabled = false;
                    }, 300);

                } else {
                    intervals.forEach(clearInterval);
                    reelWindows.forEach(rw => rw.classList.remove('rolling'));
                    Swal.fire('Lỗi', data.message, 'error');
                    spinning = false;
                    document.getElementById('spin-trigger').disabled = false;
                    document.getElementById('status-bar').textContent = "HÃY THỬ LẠI!";
                }
            } catch (e) {
                console.error(e);
                intervals.forEach(clearInterval);
                reelWindows.forEach(rw => rw.classList.remove('rolling'));
                spinning = false;
                document.getElementById('spin-trigger').disabled = false;
            }
        }

        document.getElementById('spin-trigger').onclick = spin;
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