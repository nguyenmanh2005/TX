<?php
session_start();

require '../db_connect.php';
require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_38', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảo Trì Game | Mega Spin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #020617;
            --panel: rgba(15, 23, 42, 0.85);
            --primary: #6366f1;
            --secondary: #a855f7;
            --warning: #f59e0b;
            --danger: #ef4444;
            --glass-border: rgba(255, 255, 255, 0.1);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--bg);
            background-image: 
                radial-gradient(circle at 0% 0%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, rgba(168, 85, 247, 0.15) 0%, transparent 50%);
            color: #f8fafc;
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            text-align: center;
            padding: 20px;
            overflow: hidden;
        }
        .maintenance-box {
            background: var(--panel);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 2px solid var(--primary);
            padding: 45px 35px;
            border-radius: 28px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6), 0 0 35px rgba(99, 102, 241, 0.25);
            max-width: 580px;
            width: 100%;
            animation: fadeIn 0.6s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .maintenance-icon {
            font-size: 70px;
            color: var(--warning);
            margin-bottom: 20px;
            animation: pulse 2.2s infinite ease-in-out;
            text-shadow: 0 0 25px rgba(245, 158, 11, 0.5);
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }
        .badge-status {
            display: inline-block;
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid var(--warning);
            color: var(--warning);
            font-size: 0.8rem;
            font-weight: 800;
            padding: 5px 16px;
            border-radius: 50px;
            margin-bottom: 15px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        h1 {
            margin: 0 0 12px;
            font-size: 30px;
            font-weight: 800;
            background: linear-gradient(135deg, #818cf8, #c084fc);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }
        p {
            font-size: 16px;
            color: #94a3b8;
            line-height: 1.65;
            margin-bottom: 25px;
        }
        p b {
            color: #f8fafc;
        }
        .btn-action {
            display: inline-block;
            padding: 10px 28px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.95rem;
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
            transition: all 0.3s ease;
        }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(99, 102, 241, 0.6);
        }
    </style>
</head>
<body>
    <div class="maintenance-box">
        <div class="maintenance-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
        <div class="badge-status">🛠️ Kênh Đang Bảo Trì</div>
        <h1>MEGA SPIN (ID: 38)</h1>
        <p>Game <b>Mega Spin</b> hiện đang được tạm dừng để bảo trì, tối ưu thuật toán quay số cộng đồng và hoàn thiện tính năng cho Bot Streamer.<br>Xin vui lòng quay lại sau hoặc chọn kênh live khác!</p>
        <a href="../index.php" class="btn-action">🏠 Về Sảnh Chính</a>
    </div>

<!-- AUTO-GENERATED BOT SCRIPT -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script src="bots/bot_38.js?v=<?= time() ?>"></script>

</body>
</html>
