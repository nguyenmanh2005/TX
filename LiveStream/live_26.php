<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_26', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

if (!isset($botUserId)) {
    header('Location: ../login.php');
    exit;
}
include '../db_connect.php';
$userId = $botUserId;
$stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userMoney = $stmt->get_result()->fetch_assoc()['Money'];

require_once '../load_theme.php';
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #1a1c29 0%, #2a2d3e 100%)';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hang Động Tham Lam - VIP</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Space+Grotesk:wght@400;700;800&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: <?= $bgGradientCSS ?>;
            color: #fff;
            min-height: 100vh;
        }

        .game-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 10px;
        }

        .game-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 25px;
            background: rgba(15, 17, 21, 0.8);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .market-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(90deg, #38bdf8, #818cf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }
        .game-subtitle { color: #94a3b8; font-size: 1rem; }
        .back-btn { color: #94a3b8; text-decoration: none; margin-bottom: 10px; display: inline-block; transition: 0.2s; }
        .back-btn:hover { color: #fff; }

        .user-balance {
            background: rgba(255,255,255,0.05); 
            padding: 15px 25px; 
            border-radius: 12px; 
            border: 1px solid rgba(255,255,255,0.1);
            text-align: right;
        }
        .user-balance span { font-size: 1.5rem; font-family: 'Space Grotesk'; font-weight: bold; color: #fbbf24; }

        .guide-box {
            background: rgba(234, 179, 8, 0.05);
            border: 1px solid rgba(234, 179, 8, 0.2);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        .guide-box i { font-size: 2rem; color: #eab308; }
        .guide-box h4 { margin: 0 0 5px 0; color: #fde047; }
        .guide-box p { margin: 0; color: #cbd5e1; font-size: 0.95rem; line-height: 1.5; }

        .cave-wrapper {
            display: flex;
            justify-content: center;
        }

        .cave-container {
            width: 100%;
            max-width: 800px;
            background: radial-gradient(circle at 50% 100%, #1a1005 0%, #050505 100%);
            border-radius: 20px;
            padding: 20px;
            box-shadow: inset 0 0 100px rgba(0,0,0,0.9), 0 20px 50px rgba(0,0,0,0.5);
            position: relative;
            overflow: hidden;
            text-align: center;
            border: 2px solid #222;
        }

        .cave-status-panel {
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 15px;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }

        .cave-status-panel h2 {
            font-family: 'Space Grotesk', sans-serif;
            color: #eab308;
            margin-top: 0;
            font-size: 1.4rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 0 0 15px rgba(234, 179, 8, 0.5);
        }

        .cave-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 1rem;
        }

        .stat-box {
            background: rgba(255,255,255,0.02);
            border-radius: 12px;
            padding: 15px;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .stat-box span {
            display: block;
            font-size: 0.85rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .stat-box b {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.4rem;
            color: #fff;
        }

        .highlight-money {
            color: #4ade80 !important;
            text-shadow: 0 0 15px rgba(74, 222, 128, 0.4);
        }

        .risk-meter {
            width: 100%;
            height: 12px;
            background: #222;
            border-radius: 6px;
            margin-top: 25px;
            overflow: hidden;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.5);
        }

        .risk-bar {
            height: 100%;
            background: linear-gradient(90deg, #22c55e, #eab308, #ef4444);
            width: 5%;
            transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 6px;
        }

        .cave-character {
            font-size: 4rem;
            margin: 1.5rem 0;
            position: relative;
            z-index: 2;
            transition: transform 0.3s;
            display: inline-block;
            filter: drop-shadow(0 10px 15px rgba(0,0,0,0.5));
        }

        .cave-character.walking { animation: walk 0.5s infinite alternate ease-in-out; }
        .cave-character.crashed { animation: shake 0.5s; filter: grayscale(100%); opacity: 0.5; color: #ef4444; }

        @keyframes walk {
            0% { transform: translateY(0) rotate(-10deg); }
            100% { transform: translateY(-20px) rotate(10deg); }
        }

        @keyframes shake {
            0% { transform: translate(1px, 1px) rotate(0deg); }
            10% { transform: translate(-1px, -2px) rotate(-1deg); }
            20% { transform: translate(-3px, 0px) rotate(1deg); }
            30% { transform: translate(3px, 2px) rotate(0deg); }
            40% { transform: translate(1px, -1px) rotate(1deg); }
            50% { transform: translate(-1px, 2px) rotate(-1deg); }
            60% { transform: translate(-3px, 1px) rotate(0deg); }
            70% { transform: translate(3px, 1px) rotate(-1deg); }
            80% { transform: translate(-1px, -1px) rotate(1deg); }
            90% { transform: translate(1px, 2px) rotate(0deg); }
            100% { transform: translate(1px, -2px) rotate(-1deg); }
        }

        .input-bet-container {
            margin-top: 1rem;
            position: relative;
            z-index: 2;
            background: rgba(0,0,0,0.5);
            padding: 20px;
            border-radius: 16px;
            border: 1px solid #333;
        }

        .input-bet {
            background: #000;
            border: 2px solid #444;
            color: #fbbf24;
            padding: 10px 15px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.2rem;
            font-weight: bold;
            border-radius: 12px;
            width: 200px;
            text-align: center;
            margin-bottom: 10px;
            outline: none;
            transition: all 0.3s;
        }
        .input-bet:focus { border-color: #3b82f6; box-shadow: 0 0 20px rgba(59, 130, 246, 0.3); }

        .btn-action {
            padding: 12px 25px;
            font-size: 1rem;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 800;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-transform: uppercase;
            letter-spacing: 1px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-start {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.4);
        }
        .btn-start:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(59, 130, 246, 0.6); }

        .cave-actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 2rem;
            position: relative;
            z-index: 2;
        }

        .btn-step {
            background: linear-gradient(135deg, #eab308, #ca8a04);
            color: #000;
            box-shadow: 0 10px 30px rgba(234, 179, 8, 0.4);
        }
        .btn-step:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(234, 179, 8, 0.6); }
        .btn-step:disabled { background: #333; color: #666; transform: none; box-shadow: none; cursor: not-allowed; }

        .btn-cashout {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #fff;
            box-shadow: 0 10px 30px rgba(34, 197, 94, 0.4);
        }
        .btn-cashout:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(34, 197, 94, 0.6); }
        .btn-cashout:disabled { background: #333; color: #666; transform: none; box-shadow: none; cursor: not-allowed; }

        @media (max-width: 768px) {
            .cave-stats { grid-template-columns: 1fr; gap: 10px; }
            .cave-actions { flex-direction: column; }
            .btn-action { width: 100%; justify-content: center; }
            .cave-wrapper, .premium-header { padding: 15px; }
            .guide-box { margin: 0 15px 25px 15px; }
        }
    </style>
</head>
<body>
    <div class="game-container">
        <!-- Header -->
        <div class="game-header">
            <div class="header-left">
                <a href="../index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Quay lại</a>
                <div class="game-title">
                    <h1 class="market-title"><i class="fas fa-dungeon"></i> HANG ĐỘNG THAM LAM</h1>
                    <span class="game-subtitle">Push-Your-Luck - Đừng chết vì tham!</span>
                </div>
            </div>
            <div class="user-balance">
                <i class="fas fa-wallet"></i>
                <span id="userMoney"><?= number_format($userMoney, 0, ',', '.') ?></span> GTLM
            </div>
        </div>


    <div class="cave-wrapper">
        <div class="cave-container" id="caveContainer">
            
            <div class="cave-status-panel">
                <h2 id="statusTitle">Chuẩn bị thám hiểm</h2>
                <div class="cave-stats">
                    <div class="stat-box">
                        <span>Số Bước Đã Đi</span>
                        <b id="txtStep">0</b>
                    </div>
                    <div class="stat-box">
                        <span>GTLM Đang Ôm</span>
                        <b id="txtPrize" class="highlight-money">0</b>
                    </div>
                    <div class="stat-box">
                        <span>Nguy Cơ Sập Hầm</span>
                        <b id="txtRisk" style="color: #ef4444;">5%</b>
                    </div>
                </div>
                <div class="risk-meter">
                    <div class="risk-bar" id="riskBar"></div>
                </div>
            </div>

            <div class="cave-character" id="characterIcon">
                <i class="fas fa-user-astronaut" style="color: #cbd5e1;"></i>
            </div>

            <div class="input-bet-container" id="setupPanel">
                <p style="color: #888; margin-bottom: 10px; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px;">Mức cược ban đầu (Tối thiểu 1,000)</p>
                <input type="text" id="betAmount" class="input-bet" value="10,000">
                <br>
                <button class="btn-action btn-start" id="btnStart"><i class="fas fa-door-open"></i> TIẾN VÀO HANG</button>
            </div>

            <div class="cave-actions" id="actionPanel" style="display: none;">
                <button class="btn-action btn-step" id="btnStep"><i class="fas fa-shoe-prints"></i> BƯỚC TIẾP</button>
                <button class="btn-action btn-cashout" id="btnCashout"><i class="fas fa-running"></i> CHẠY TRỐN</button>
            </div>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 30px;">
        <a href="../index.php" style="display: inline-block; color: #94a3b8; text-decoration: none; font-weight: 600; font-size: 1.1rem; transition: 0.3s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">
            🏠 QUAY LẠI TRANG CHỦ
        </a>
    </div>

    <script src="../assets/js/game-effects.js"></script>
    <script src="../assets/js/game-effects-auto.js"></script>
    <script src="../assets/js/game-greedy-cave.js"></script>

<!-- CUSTOM BOT SCRIPT FOR GREEDY CAVE -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script src="bots/bot_26.js"></script>

</body>
</html>
