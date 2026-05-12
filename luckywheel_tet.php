<?php
session_start();
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}
require 'db_connect.php';
require_once 'load_theme.php';

$userId = $_SESSION['Iduser'];
$sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$soDu = $user['Money'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>🧧 Vòng Quay Tết Giáp Thìn 🐉</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;700&family=Dancing+Script:wght@700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/confetti.browser.min.js"></script>
    <style>
        :root {
            --tet-red: #d63031;
            --tet-gold: #fdcb6e;
            --tet-gold-dark: #e1b12c;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
        }

        body {
            background: url('assets/img/tet/bg.png') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Segoe UI', system-ui, sans-serif;
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow-x: hidden;
        }

        /* Overlay to darken background */
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(circle, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.7) 100%);
            z-index: -1;
        }

        .container {
            width: 95%;
            max-width: 1000px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2rem;
            padding: 2rem;
        }

        .game-card {
            background: rgba(18, 18, 18, 0.8);
            backdrop-filter: blur(20px);
            border: 2px solid var(--tet-gold);
            border-radius: 40px;
            padding: 3rem;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5), 0 0 30px rgba(214, 48, 49, 0.3);
            text-align: center;
            position: relative;
            width: 100%;
        }

        .title {
            font-family: 'Dancing Script', cursive;
            font-size: 3.5rem;
            color: var(--tet-gold);
            margin: 0;
            text-shadow: 0 0 15px rgba(253, 203, 110, 0.5);
        }

        .subtitle {
            font-family: 'Oswald', sans-serif;
            font-size: 1.2rem;
            letter-spacing: 5px;
            text-transform: uppercase;
            color: #fff;
            margin-bottom: 2rem;
            opacity: 0.8;
        }

        .balance-box {
            background: linear-gradient(90deg, transparent, rgba(253, 203, 110, 0.1), transparent);
            border-top: 1px solid var(--glass-border);
            border-bottom: 1px solid var(--glass-border);
            padding: 1rem;
            margin-bottom: 2rem;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .balance-box b {
            color: var(--tet-gold);
            text-shadow: 0 0 10px rgba(253, 203, 110, 0.3);
        }

        .wheel-wrapper {
            position: relative;
            width: 450px;
            height: 450px;
            margin: 2rem auto;
            border: 10px solid var(--tet-gold);
            border-radius: 50%;
            padding: 10px;
            box-shadow: 0 0 50px rgba(253, 203, 110, 0.2);
        }

        /* Pointer */
        .wheel-wrapper::after {
            content: '🧧';
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 3rem;
            z-index: 10;
            filter: drop-shadow(0 5px 10px rgba(0,0,0,0.5));
        }

        #canvas-wheel {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            transition: transform 5s cubic-bezier(0.15, 0, 0.15, 1);
        }

        .controls {
            margin-top: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
        }

        .input-group {
            position: relative;
            width: 300px;
        }

        .input-group input {
            width: 100%;
            padding: 1rem 1.5rem;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--glass-border);
            border-radius: 50px;
            color: white;
            font-size: 1.2rem;
            text-align: center;
            outline: none;
            transition: 0.3s;
        }

        .input-group input:focus {
            border-color: var(--tet-gold);
            background: rgba(255,255,255,0.1);
        }

        .btn-spin {
            background: linear-gradient(135deg, var(--tet-red), #b33939);
            color: white;
            border: 2px solid var(--tet-gold);
            padding: 1.2rem 4rem;
            font-size: 1.5rem;
            font-weight: 700;
            border-radius: 50px;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
            letter-spacing: 2px;
            box-shadow: 0 10px 20px rgba(214, 48, 49, 0.4);
        }

        .btn-spin:hover:not(:disabled) {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 30px rgba(214, 48, 49, 0.6);
        }

        .btn-spin:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            filter: grayscale(1);
        }

        .back-link {
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            font-size: 0.9rem;
            margin-top: 1rem;
            transition: 0.3s;
        }

        .back-link:hover {
            color: var(--tet-gold);
        }

        @media (max-width: 600px) {
            .wheel-wrapper {
                width: 300px;
                height: 300px;
            }
            .title { font-size: 2.5rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="game-card">
        <h1 class="title">🧧 Vòng Quay Tết 🧨</h1>
        <p class="subtitle">GIÁP THÌN 2024 - NHẬN LỘC ĐẦU NĂM</p>
        
        <div class="info-guide" style="background: rgba(214, 48, 49, 0.1); border: 1px solid var(--tet-gold); padding: 1rem; border-radius: 10px; margin-bottom: 2rem; font-size: 0.9rem; text-align: left;">
            🏮 <b>THỬ VẬN:</b> Nhập số GTLM muốn liều và nhấn "KHAI LỘC". Vòng quay sẽ dừng lại ở một ô phần thưởng ngẫu nhiên. Số GTLM húp = GTLM liều x Hệ số ô đó.
        </div>

        <div class="balance-box">
            💰 Tài sản: <b id="balance-display"><?= number_format($soDu, 0, ',', '.') ?> gtlm</b>
        </div>

        <div class="wheel-wrapper">
            <canvas id="canvas-wheel"></canvas>
        </div>

        <div class="controls">
            <div class="quick-bets" style="margin-bottom: 1rem; display: flex; justify-content: center; gap: 0.5rem;">
                <button class="btn-small" onclick="$('#bet-amount').val(1000)">1K</button>
                <button class="btn-small" onclick="$('#bet-amount').val(10000)">10K</button>
                <button class="btn-small" onclick="$('#bet-amount').val(50000)">50K</button>
                <button class="btn-small" onclick="$('#bet-amount').val(100000)">100K</button>
            </div>
            <div class="input-group">
                <input type="number" id="bet-amount" placeholder="GTLM thả thính..." min="1000" step="1000">
            </div>
            <button id="spin-btn" class="btn-spin">🧧 KHAI LỘC</button>
            <a href="index.php" class="back-link">🏠 Quay Lại Trang Chủ</a>
        </div>

        <div class="history-board" style="margin-top: 3rem; background: rgba(0,0,0,0.4); border-radius: 20px; padding: 1.5rem; border: 1px solid var(--glass-border);">
            <h3 style="color: var(--tet-gold); font-size: 1rem; margin-bottom: 1rem;">📜 LỊCH SỬ NHẬN LỘC</h3>
            <div id="wheel-history" style="font-size: 0.8rem; max-height: 150px; overflow-y: auto;">
                <!-- History items -->
                <div style="opacity: 0.5;">Chưa có lượt quay nào...</div>
            </div>
        </div>

        <style>
            .btn-small { background: rgba(0,0,0,0.3); color: var(--tet-gold); border: 1px solid var(--tet-gold); padding: 5px 15px; border-radius: 50px; cursor: pointer; transition: 0.3s; }
            .btn-small:hover { background: var(--tet-gold); color: #000; }
        </style>
    </div>
</div>

<script>
    const segments = [
        { label: "Lì Xì Nhỏ (x0.5)", multiplier: 0.5, color: "#d63031" },
        { label: "Năm Mới (x1)", multiplier: 1, color: "#feca57" },
        { label: "Bánh Chưng (x2)", multiplier: 2, color: "#1dd1a1" },
        { label: "Cành Mai (x3)", multiplier: 3, color: "#ff9f43" },
        { label: "Bao Đỏ (x5)", multiplier: 5, color: "#ee5253" },
        { label: "Thỏi Vàng (x10)", multiplier: 10, color: "#ff9f43" },
        { label: "Mất Lộc (x0)", multiplier: 0, color: "#576574" },
        { label: "Hạt Dưa (x1.5)", multiplier: 1.5, color: "#ff6b6b" }
    ];

    const canvas = document.getElementById('canvas-wheel');
    const ctx = canvas.getContext('2d');
    const size = 500;
    canvas.width = size;
    canvas.height = size;

    function drawWheel() {
        const centerX = size / 2;
        const centerY = size / 2;
        const radius = size / 2 - 10;
        const sliceAngle = (2 * Math.PI) / segments.length;

        ctx.clearRect(0, 0, size, size);

        segments.forEach((seg, i) => {
            const angle = i * sliceAngle;
            
            // Draw slice
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, angle, angle + sliceAngle);
            ctx.fillStyle = seg.color;
            ctx.fill();
            ctx.strokeStyle = "rgba(0,0,0,0.1)";
            ctx.lineWidth = 2;
            ctx.stroke();

            // Draw text
            ctx.save();
            ctx.translate(centerX, centerY);
            ctx.rotate(angle + sliceAngle / 2);
            ctx.textAlign = "right";
            ctx.fillStyle = "white";
            ctx.font = "bold 20px Oswald";
            ctx.fillText(seg.label, radius - 20, 10);
            ctx.restore();
        });

        // Center hub
        ctx.beginPath();
        ctx.arc(centerX, centerY, 60, 0, 2 * Math.PI);
        ctx.fillStyle = "#fdcb6e";
        ctx.fill();
        ctx.lineWidth = 5;
        ctx.strokeStyle = "#fff";
        ctx.stroke();

        // Draw Center Image if possible, otherwise text
        ctx.fillStyle = "#d63031";
        ctx.font = "bold 40px Dancing Script";
        ctx.textAlign = "center";
        ctx.fillText("Tết", centerX, centerY + 15);
    }

    drawWheel();

    let isSpinning = false;
    const spinBtn = document.getElementById('spin-btn');
    const betInput = document.getElementById('bet-amount');
    const balanceDisplay = document.getElementById('balance-display');

    spinBtn.addEventListener('click', async () => {
        if (isSpinning) return;
        
        const bet = parseInt(betInput.value);
        if (!bet || bet < 1000) {
            Swal.fire('Lỗi', 'Thả thính tối thiểu 1.000 GTLM!', 'error');
            return;
        }

        isSpinning = true;
        spinBtn.disabled = true;

        try {
            const response = await fetch('api_luckywheel_tet.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `cuoc=${bet}`
            });
            const data = await response.json();

            if (!data.success) {
                Swal.fire('Lỗi', data.message, 'error');
                isSpinning = false;
                spinBtn.disabled = false;
                return;
            }

            // Calculate rotation
            const extraRounds = 5 + Math.floor(Math.random() * 5);
            const sliceAngle = 360 / segments.length;
            const targetRotation = extraRounds * 360 + (360 - (data.index * sliceAngle + sliceAngle / 2));
            
            canvas.style.transform = `rotate(${targetRotation}deg)`;

            setTimeout(() => {
                isSpinning = false;
                spinBtn.disabled = false;
                balanceDisplay.textContent = data.formattedBalance;
                addWheelHistory(data);

                if (data.winAmount > 0) {
                    confetti({
                        particleCount: 150,
                        spread: 70,
                        origin: { y: 0.6 },
                        colors: ['#fdcb6e', '#d63031', '#fff']
                    });
                    
                    Swal.fire({
                        title: 'Chúc Mừng Năm Mới!',
                        html: `Bạn nhận được <b>${data.label}</b><br>Húp: <span style="color: #2ecc71; font-size: 1.5rem">+${new Intl.NumberFormat().format(data.winAmount)} GTLM</span>`,
                        icon: 'success',
                        confirmButtonText: 'Nhận Lộc'
                    });
                } else {
                    Swal.fire({
                        title: 'BAY MÀU!',
                        text: 'Về cõi rồi, hãy liều lại để lấy may mắn nhé!',
                        icon: 'error',
                        confirmButtonText: 'Thử lại'
                    });
                }
                
                // Reset rotation for next spin (cleanly)
                // canvas.style.transition = 'none';
                // canvas.style.transform = `rotate(${targetRotation % 360}deg)`;
                // setTimeout(() => canvas.style.transition = 'transform 5s cubic-bezier(0.15, 0, 0.15, 1)', 50);
                
            }, 5000);

        } catch (error) {
            console.error(error);
            Swal.fire('Lỗi', 'Đã có lỗi xảy ra, vui lòng thử lại!', 'error');
            isSpinning = false;
            spinBtn.disabled = false;
        }
    });

    function addWheelHistory(data) {
        const time = new Date().toLocaleTimeString();
        const win = data.winAmount > 0;
        const color = win ? '#2ecc71' : '#ff7675';
        const html = `<div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding: 5px 0;">
            <span>[${time}] Liều: ${new Intl.NumberFormat().format(data.bet)}</span>
            <span style="color: ${color}; font-weight: bold;">${data.label} (${win ? 'húp ' : ''}${new Intl.NumberFormat().format(data.winAmount)})</span>
        </div>`;
        
        if ($('#wheel-history div').length === 1 && $('#wheel-history div').css('opacity') === '0.5') {
            $('#wheel-history').empty();
        }
        
        $('#wheel-history').prepend(html);
        if ($('#wheel-history div').length > 10) $('#wheel-history div:last').remove();
    }
</script>

</body>
</html>