<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_banharc', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require_once '../db_connect.php';

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảo Trì Game | Bắn Cá Arcade Premium</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; padding: 0; background: #020617; color: #f8fafc; font-family: 'Outfit', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; overflow: hidden; flex-direction: column; text-align: center; }
        .maintenance-box { background: rgba(15, 23, 42, 0.8); border: 2px solid #0ea5e9; padding: 50px; border-radius: 20px; box-shadow: 0 0 30px rgba(14, 165, 233, 0.3); max-width: 600px; }
        .maintenance-icon { font-size: 80px; color: #ef4444; margin-bottom: 20px; animation: pulse 2s infinite; }
        h1 { margin: 0 0 15px; font-size: 32px; color: #fbbf24; }
        p { font-size: 18px; color: #cbd5e1; line-height: 1.6; }
        .back-btn { display: inline-block; margin-top: 30px; padding: 12px 30px; background: #0ea5e9; color: white; text-decoration: none; border-radius: 30px; font-weight: 600; transition: all 0.3s ease; }
        .back-btn:hover { background: #0284c7; transform: scale(1.05); }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
    </style>
</head>
<body>
    <div class="maintenance-box">
        <div class="maintenance-icon"><i class="fas fa-tools"></i></div>
        <h1>ĐANG BẢO TRÌ</h1>
        <p>Game <b>Bắn Cá Arcade Premium</b> hiện đang được bảo trì để nâng cấp hệ thống và khắc phục lỗi.<br>Xin vui lòng quay lại sau!</p>
        <a href="javascript:history.back()" class="back-btn">Quay lại</a>
    </div>

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script>
    if (typeof BotVirtualCursor !== "undefined") {
        BotVirtualCursor.init("Bot Streamer");
        setInterval(() => {
            const allBtns = Array.from(document.querySelectorAll("button, .btn-bet, .chip, .spin-btn, #btnSpin, .bet-button, .card, .btn-primary, .btn-success, input[type='button'], input[type='submit']"));
            const btns = allBtns.filter(b => {
                if(b.offsetParent === null || b.disabled) return false;
                const txt = (b.innerText || b.value || "").toLowerCase();
                const cls = (b.className || "").toLowerCase();
                const id = (b.id || "").toLowerCase();
                
                // Exclude common navigation/help buttons
                if(txt.includes("hướng dẫn") || txt.includes("trang chủ") || txt.includes("nạp") || txt.includes("rút") || txt.includes("lịch sử") || txt.includes("quay lại") || txt.includes("thoát")) return false;
                if(cls.includes("back") || cls.includes("help") || cls.includes("guide") || cls.includes("close") || cls.includes("swal") || cls.includes("nav")) return false;
                if(id.includes("guide") || id.includes("back") || id.includes("close") || id.includes("nav")) return false;
                
                return true;
            });
            
            if(btns.length > 0) {
                const btn = btns[Math.floor(Math.random() * btns.length)];
                BotVirtualCursor.moveToElement($(btn), 1, 0, () => {
                    setTimeout(() => { 
                        BotVirtualCursor.simulateClick(() => {
                            try { btn.click(); } catch(e){}
                        });
                    }, 500);
                });
            }
        }, 3000 + Math.random() * 4000);
    }
</script>

</body>
</html>
<?php if(false): ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bắn Cá Arcade Premium | HTML5 Canvas</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #020617;
            --panel: rgba(15, 23, 42, 0.8);
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
            overflow: hidden; /* Không cho scroll trang */
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        /* 🖼️ Game UI Overlays */
        #ui-layer {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none; /* Cho phép click xuyên qua vào Canvas */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 20px;
            box-sizing: border-box;
        }

        #ui-layer > * { pointer-events: auto; }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .stat-box {
            background: var(--panel);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 15px 25px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .stat-label { font-size: 10px; text-transform: uppercase; color: #94a3b8; letter-spacing: 1px; }
        .stat-value { font-size: 20px; font-weight: 800; color: var(--gold); }

        .bottom-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            padding-bottom: 20px;
        }

        /* 🔫 Cannon & Bullet Select */
        .cannon-controls {
            display: flex;
            background: var(--panel);
            padding: 10px;
            border-radius: 25px;
            border: 1px solid rgba(255,255,255,0.1);
            gap: 10px;
        }

        .bullet-btn {
            padding: 10px 20px;
            border-radius: 15px;
            border: 1px solid transparent;
            background: rgba(255,255,255,0.05);
            color: #94a3b8;
            cursor: pointer;
            transition: 0.3s;
            font-weight: 700;
        }

        .bullet-btn.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 0 20px rgba(14, 165, 233, 0.4);
        }

        .bullet-btn:hover:not(.active) { background: rgba(255,255,255,0.1); }

        /* 📜 History Side */
        #history-box {
            position: absolute;
            right: 20px;
            top: 100px;
            width: 200px;
            max-height: 300px;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 20px;
            padding: 15px;
            font-size: 11px;
            overflow-y: hidden;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .history-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid rgba(255,255,255,0.03);
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        canvas {
            display: block;
            background: radial-gradient(circle at 50% 50%, #0c4a6e 0%, #020617 100%);
            cursor: crosshair;
        }

        /* 💥 Floating Text for Score */
        .score-popup {
            position: absolute;
            color: var(--gold);
            font-weight: 900;
            font-size: 24px;
            pointer-events: none;
            animation: floatUp 1s ease-out forwards;
            text-shadow: 0 0 10px rgba(0,0,0,1);
        }

        @keyframes floatUp {
            from { transform: translateY(0); opacity: 1; }
            to { transform: translateY(-100px); opacity: 0; }
        }
    </style>
</head>
<body>

    <canvas id="gameCanvas"></canvas>

    <div id="ui-layer">
        <div class="top-bar">
            <div class="stat-box">
                <div class="stat-label">Số dư GTLM</div>
                <div class="stat-value" id="userBalance">--</div>
            </div>
            <a href="../index.php" class="stat-box" style="text-decoration: none; color: white; display: flex; align-items: center; gap: 10px;">
                <i class="fa fa-times"></i> THOÁT
            </a>
        </div>

        <div id="history-box">
            <div class="stat-label" style="margin-bottom: 10px;">LỊCH SỬ HÚP</div>
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
    <script src="banharc.js"></script>
    <script>
        let currentBulletPrice = 500;

        function setBullet(price, el) {
            currentBulletPrice = price;
            $('.bullet-btn').removeClass('active');
            $(el).addClass('active');
        }

        function updateBalance() {
            $.get('../api_profile.php', function(res) {
                if (res.Money !== undefined) {
                    $('#userBalance').text(Number(res.Money).toLocaleString());
                }
            }, 'json');
        }

        function loadHistory() {
            $.get('../api_banharc.php', { action: 'get_history' }, function(res) {
                if (res.success) {
                    let html = '';
                    res.history.forEach(h => {
                        html += `
                            <div class="history-item">
                                <span>🐟 ${h.fish_name}</span>
                                <span style="color: #4ade80;">+${Number(h.reward).toLocaleString()}</span>
                            </div>
                        `;
                    });
                    $('#historyList').html(html);
                }
            }, 'json');
        }

        // Triggered by game when a fish is caught
        function onFishCaught(fishType, reward, fishName) {
            // Hiển thị text bay lên
            loadHistory();
            updateBalance();
        }

        $(document).ready(function() {
            updateBalance();
            loadHistory();
            setInterval(loadHistory, 5000);
        });
    </script>

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script>
    if (typeof BotVirtualCursor !== "undefined") {
        BotVirtualCursor.init("Bot Streamer");
        setInterval(() => {
            const allBtns = Array.from(document.querySelectorAll("button, .btn-bet, .chip, .spin-btn, #btnSpin, .bet-button, .card, .btn-primary, .btn-success, input[type='button'], input[type='submit']"));
            const btns = allBtns.filter(b => {
                if(b.offsetParent === null || b.disabled) return false;
                const txt = (b.innerText || b.value || "").toLowerCase();
                const cls = (b.className || "").toLowerCase();
                const id = (b.id || "").toLowerCase();
                
                // Exclude common navigation/help buttons
                if(txt.includes("hướng dẫn") || txt.includes("trang chủ") || txt.includes("nạp") || txt.includes("rút") || txt.includes("lịch sử") || txt.includes("quay lại") || txt.includes("thoát")) return false;
                if(cls.includes("back") || cls.includes("help") || cls.includes("guide") || cls.includes("close") || cls.includes("swal") || cls.includes("nav")) return false;
                if(id.includes("guide") || id.includes("back") || id.includes("close") || id.includes("nav")) return false;
                
                return true;
            });
            
            if(btns.length > 0) {
                const btn = btns[Math.floor(Math.random() * btns.length)];
                BotVirtualCursor.moveToElement($(btn), 1, 0, () => {
                    setTimeout(() => { 
                        BotVirtualCursor.simulateClick(() => {
                            try { btn.click(); } catch(e){}
                        });
                    }, 500);
                });
            }
        }, 3000 + Math.random() * 4000);
    }
</script>

</body>
</html>
< ? p h p   e n d i f ;   ? >  
 