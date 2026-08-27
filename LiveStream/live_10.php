<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
require_once '../db_connect.php';

$botUser = getOrCreateBotStreamerUser($conn, 'bot_10', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;
$userId = $botUserId;

if (isset($_GET['action']) && $_GET['action'] === 'get_balance') {
    header('Content-Type: application/json');
    $stmtB = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
    $stmtB->bind_param("i", $userId);
    $stmtB->execute();
    $resB = $stmtB->get_result()->fetch_assoc();
    $stmtB->close();
    echo json_encode(['success' => true, 'money' => number_format($resB['Money'] ?? 0, 0, ',', '.')]);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bắn Cá Arcade LiveStream | Bàn 10</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #020617;
            --panel: rgba(15, 23, 42, 0.85);
            --primary: #0ea5e9;
            --secondary: #22d3ee;
            --gold: #fbbf24;
            --danger: #ef4444;
            --text: #f8fafc;
        }

        body {
            margin: 0;
            padding: 0;
            background: var(--bg);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100vh;
            user-select: none;
        }

        #gameCanvas {
            display: block;
            width: 100vw;
            height: 100vh;
            background: radial-gradient(circle at 50% 50%, #0c4a6e 0%, #020617 100%);
            cursor: crosshair;
        }

        /* 🖼️ Game UI Overlays */
        #ui-layer {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 15px 25px;
            box-sizing: border-box;
        }

        #ui-layer > * { pointer-events: auto; }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-box {
            background: var(--panel);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(14, 165, 233, 0.3);
            padding: 8px 20px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stat-label { font-size: 11px; text-transform: uppercase; color: #94a3b8; letter-spacing: 1px; font-weight: 700; }
        .stat-value { font-size: 20px; font-weight: 900; color: var(--gold); font-family: 'Orbitron', sans-serif; }

        .bottom-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            padding-bottom: 10px;
        }

        /* 🔫 Cannon & Bullet Select */
        .cannon-controls {
            display: flex;
            background: var(--panel);
            backdrop-filter: blur(12px);
            padding: 6px 12px;
            border-radius: 25px;
            border: 1px solid rgba(14, 165, 233, 0.3);
            gap: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }

        .bullet-btn {
            padding: 8px 16px;
            border-radius: 16px;
            border: 1px solid transparent;
            background: rgba(255,255,255,0.05);
            color: #94a3b8;
            cursor: pointer;
            transition: 0.25s;
            font-weight: 800;
            font-size: 13px;
        }

        .bullet-btn.active {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
            box-shadow: 0 0 15px rgba(14, 165, 233, 0.6);
            border-color: #38bdf8;
        }

        .bullet-btn:hover:not(.active) { background: rgba(255,255,255,0.1); color: white; }

        /* 📜 History Side */
        #history-box {
            position: absolute;
            right: 20px;
            top: 70px;
            width: 200px;
            max-height: 240px;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(8px);
            border-radius: 16px;
            padding: 12px;
            font-size: 11px;
            overflow-y: hidden;
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 10px 20px rgba(0,0,0,0.4);
        }

        .history-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        /* 💥 Floating Text for Score */
        .score-popup {
            position: absolute;
            color: var(--gold);
            font-weight: 900;
            font-size: 22px;
            font-family: 'Orbitron', sans-serif;
            pointer-events: none;
            animation: floatUp 1s ease-out forwards;
            text-shadow: 0 0 12px rgba(0,0,0,1), 0 0 8px rgba(251,191,36,0.6);
            z-index: 100;
        }

        @keyframes floatUp {
            from { transform: translateY(0) scale(0.8); opacity: 1; }
            to { transform: translateY(-80px) scale(1.2); opacity: 0; }
        }
    </style>
</head>
<body>

    <canvas id="gameCanvas"></canvas>

    <div id="ui-layer">
        <div class="top-bar">
            <div class="stat-box">
                <span style="font-size: 20px;">🐟</span>
                <div>
                    <div class="stat-label">NGÂN KHỐ STREAMER</div>
                    <div class="stat-value" id="userBalance"><?= number_format($user['Money'] ?? 0, 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="stat-box" style="border-color: rgba(251,191,36,0.4);">
                <span style="color: #fbbf24; font-weight: 800; font-size: 13px;">🔱 BẮN CÁ ARCADE 3D</span>
            </div>
        </div>

        <div id="history-box">
            <div class="stat-label" style="margin-bottom: 8px; color: #38bdf8;">🏆 LỊCH SỬ HÚP CÁ</div>
            <div id="historyList"></div>
        </div>

        <div class="bottom-bar">
            <div class="cannon-controls">
                <button class="bullet-btn" onclick="setBullet(100, this)">100</button>
                <button class="bullet-btn active" onclick="setBullet(500, this)">500</button>
                <button class="bullet-btn" onclick="setBullet(1000, this)">1,000</button>
                <button class="bullet-btn" onclick="setBullet(5000, this)">5,000</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="../games/banharc.js"></script>
    <script>
        let currentBulletPrice = 500;

        function setBullet(price, el) {
            currentBulletPrice = price;
            $('.bullet-btn').removeClass('active');
            $(el).addClass('active');
        }

        function updateBalance() {
            $.get('?action=get_balance', function(res) {
                if (res && res.success && res.money) {
                    $('#userBalance').text(res.money);
                }
            }, 'json');
        }

        function loadHistory() {
            $.get('../api_banharc.php', { action: 'get_history' }, function(res) {
                if (res && res.success && res.history) {
                    let html = '';
                    res.history.slice(0, 6).forEach(h => {
                        html += `
                            <div class="history-item">
                                <span>🐟 ${h.fish_name || 'Cá'}</span>
                                <span style="color: #4ade80; font-weight: 800;">+${Number(h.reward).toLocaleString()}</span>
                            </div>
                        `;
                    });
                    $('#historyList').html(html);
                }
            }, 'json');
        }

        function onFishCaught(fishType, reward, fishName) {
            loadHistory();
            updateBalance();
        }

        $(document).ready(function() {
            updateBalance();
            loadHistory();
            setInterval(loadHistory, 3000);
            setInterval(updateBalance, 2000);
        });
    </script>

    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="bots/bot_10.js"></script>
</body>
</html>
