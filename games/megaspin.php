<?php
session_start();
require_once '../db_connect.php';
if (!isset($_SESSION['Iduser'])) { header("Location: ../login.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mega Spin | Xổ Số Cộng Đồng Real-time</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #020617;
            --panel: rgba(15, 23, 42, 0.7);
            --primary: #6366f1;
            --secondary: #a855f7;
            --success: #22c55e;
            --warning: #f59e0b;
            --gold: #fbbf24;
            --text: #f8fafc;
            --glass: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
        }

        body {
            background: var(--bg);
            background-image: 
                radial-gradient(circle at 0% 0%, rgba(99, 102, 241, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 100% 100%, rgba(168, 85, 247, 0.15) 0%, transparent 40%);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
            width: 100%;
            max-width: 1000px;
            padding: 40px 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 48px;
            font-weight: 800;
            margin: 0;
            background: linear-gradient(135deg, #818cf8, #c084fc);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -1px;
        }

        /* ⏱️ Countdown Section */
        .countdown-container {
            background: var(--panel);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 40px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .countdown-timer {
            font-family: 'JetBrains Mono', monospace;
            font-size: 84px;
            font-weight: 800;
            color: var(--primary);
            text-shadow: 0 0 30px rgba(99, 102, 241, 0.4);
            margin: 20px 0;
        }

        .pool-amount {
            font-size: 32px;
            font-weight: 800;
            color: var(--gold);
        }

        .progress-bar-bg {
            width: 100%;
            height: 8px;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            margin-top: 30px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            width: 100%;
            transition: width 1s linear;
        }

        /* 🎰 Betting Panel */
        .bet-panel {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .bet-btn {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            padding: 20px;
            border-radius: 20px;
            color: white;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .bet-btn:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: var(--primary);
            transform: translateY(-5px);
        }

        .bet-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        }

        .bet-btn span {
            display: block;
            font-size: 18px;
        }

        .bet-btn small {
            font-size: 10px;
            color: var(--market-text-dim);
            opacity: 0.7;
        }

        /* 👥 Live Feed */
        .main-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 25px;
        }

        .feed-panel {
            background: var(--panel);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
            padding: 25px;
            height: 500px;
            display: flex;
            flex-direction: column;
        }

        .feed-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--glass-border);
        }

        .feed-list {
            flex: 1;
            overflow-y: auto;
            scrollbar-width: thin;
        }

        .participant-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            background: rgba(255,255,255,0.02);
            border-radius: 15px;
            margin-bottom: 10px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .participant-avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            border: 2px solid var(--glass-border);
        }

        .participant-info {
            flex: 1;
        }

        .participant-name {
            font-weight: 600;
            font-size: 14px;
        }

        .participant-bet {
            font-size: 12px;
            color: var(--gold);
            font-weight: 700;
        }

        /* 📊 Stats Panel */
        .stats-panel {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .stat-card {
            background: var(--panel);
            border: 1px solid var(--glass-border);
            border-radius: 25px;
            padding: 20px;
            text-align: center;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 800;
            margin: 5px 0;
            color: var(--primary);
        }

        .stat-label {
            font-size: 12px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-join {
            width: 100%;
            padding: 20px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 20px;
            font-size: 20px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 15px 30px rgba(99, 102, 241, 0.3);
        }

        .btn-join:hover {
            transform: translateY(-5px);
            filter: brightness(1.1);
        }

        .btn-join:active {
            transform: scale(0.98);
        }

        /* 🏆 Winner Overlay */
        #winnerOverlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(2, 6, 23, 0.9);
            z-index: 10000;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }

        .winner-content {
            text-align: center;
            animation: zoomIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes zoomIn {
            from { transform: scale(0); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="header">
            <a href="../index.php" style="color: var(--text-dim); text-decoration: none; position: absolute; left: 20px; top: 40px;"><i class="fa fa-arrow-left"></i> Về sảnh</a>
            <h1>MEGA SPIN</h1>
            <p style="color: #94a3b8;">Sự kiện Xổ số cộng đồng - 60 giây một lần quay</p>
        </div>

        <div class="countdown-container">
            <div class="stat-label">Thời gian còn lại</div>
            <div class="countdown-timer" id="timer">00:60</div>
            
            <div style="display: flex; justify-content: center; gap: 40px; margin-top: 20px;">
                <div>
                    <div class="stat-label">Tổng Hũ hiện tại</div>
                    <div class="pool-amount" id="currentPool">0 GTLM</div>
                </div>
                <div>
                    <div class="stat-label">Winner trước đó</div>
                    <div class="pool-amount" style="color: var(--success); font-size: 24px;" id="lastWinner">Đang chờ...</div>
                </div>
            </div>

            <div class="progress-bar-bg">
                <div class="progress-bar-fill" id="progressBar"></div>
            </div>
        </div>

        <div class="bet-panel">
            <div class="bet-btn" onclick="selectAmount(1000, this)">
                <span>1,000</span>
                <small>1,000 vé</small>
            </div>
            <div class="bet-btn" onclick="selectAmount(5000, this)">
                <span>5,000</span>
                <small>5,000 vé</small>
            </div>
            <div class="bet-btn active" onclick="selectAmount(10000, this)">
                <span>10,000</span>
                <small>10,000 vé</small>
            </div>
            <div class="bet-btn" onclick="selectAmount(50000, this)">
                <span>50,000</span>
                <small>50,000 vé</small>
            </div>
            <div class="bet-btn" onclick="selectAmount(100000, this)">
                <span>100,000</span>
                <small>100,000 vé</small>
            </div>
            <div class="bet-btn" onclick="selectAmount(500000, this)">
                <span>500,000</span>
                <small>500,000 vé</small>
            </div>
        </div>

        <div class="main-layout">
            <div class="feed-panel">
                <div class="feed-header">
                    <h2 style="margin:0; font-size: 18px;">🔥 Người chơi vừa vào</h2>
                    <span style="font-size: 12px; color: var(--primary);" id="participantCount">0 người</span>
                </div>
                <div class="feed-list" id="feedList">
                    <!-- Participants list -->
                </div>
            </div>

            <div class="stats-panel">
                <div class="stat-card">
                    <div class="stat-label">Tỉ lệ thắng của bạn</div>
                    <div class="stat-value" id="myChance">0%</div>
                    <p style="font-size: 10px; color: #64748b; margin-top: 10px;">Góp nhiều vé hơn để tăng cơ hội trúng hũ!</p>
                </div>
                <button class="btn-join" onclick="joinRound()">THAM GIA NGAY</button>
                
                <div class="stat-card" style="text-align: left; padding: 15px;">
                    <div class="stat-label" style="margin-bottom: 10px;">📜 Lịch sử thắng</div>
                    <div id="winHistory" style="font-size: 11px;">
                        <!-- History here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 🏆 Winner Overlay -->
    <div id="winnerOverlay">
        <div class="winner-content">
            <h1 style="color: var(--gold); font-size: 48px; margin-bottom: 0;">🎉 CHIẾN THẮNG! 🎉</h1>
            <p style="color: white; font-size: 20px; margin-bottom: 30px;">Chúc mừng người may mắn đã húp trọn Pool</p>
            <div id="winnerDisplay">
                <!-- Winner info -->
            </div>
            <button class="market-btn market-btn-primary" style="margin-top: 30px; padding: 15px 40px;" onclick="closeWinner()">TUYỆT VỜI!</button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script>
        let selectedAmount = 10000;
        let lastRoundId = 0;
        let isProcessing = false;

        function selectAmount(amount, el) {
            selectedAmount = amount;
            $('.bet-btn').removeClass('active');
            $(el).addClass('active');
        }

        function updateUI() {
            $.get('../api_megaspin.php', { action: 'get_status' }, function(res) {
                if (!res.success) return;

                // Cập nhật Pool & Timer
                $('#currentPool').text(Number(res.pool).toLocaleString() + ' GTLM');
                $('#timer').text(`00:${res.time_left < 10 ? '0' + res.time_left : res.time_left}`);
                $('#myChance').text(res.my_chance + '%');
                $('#lastWinner').text(res.last_winner ? res.last_winner.Name : 'Đang chờ...');
                
                // Progress Bar
                const percent = (res.time_left / 60) * 100;
                $('#progressBar').css('width', percent + '%');

                // Check nếu round mới bắt đầu
                if (lastRoundId !== 0 && res.round_id !== lastRoundId) {
                    showWinnerAnnounce(res.last_winner, res.pool);
                }
                lastRoundId = res.round_id;

                // Live Feed
                $('#participantCount').text(res.participants.length + ' người');
                let feedHtml = '';
                res.participants.forEach(p => {
                    feedHtml += `
                        <div class="participant-card">
                            <img src="${p.ImageURL || 'https://ui-avatars.com/api/?name='+p.Name}" class="participant-avatar">
                            <div class="participant-info">
                                <div class="participant-name">${p.Name}</div>
                                <div class="participant-bet">+${Number(p.total_bet).toLocaleString()} vé</div>
                            </div>
                        </div>
                    `;
                });
                $('#feedList').html(feedHtml);
            }, 'json');
        }

        function joinRound() {
            $.post('../api_megaspin.php', { action: 'join', amount: selectedAmount }, function(res) {
                if (res.success) {
                    Swal.fire({
                        title: 'Thành công!',
                        text: `Bạn đã tham gia ${selectedAmount.toLocaleString()} vé vào Pool.`,
                        icon: 'success',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        background: '#0f172a',
                        color: '#fff'
                    });
                    updateUI();
                } else {
                    Swal.fire('Lỗi', res.message, 'error');
                }
            }, 'json');
        }

        function showWinnerAnnounce(winner, pool) {
            if (!winner) return;
            
            $('#winnerDisplay').html(`
                <img src="${winner.ImageURL || 'https://ui-avatars.com/api/?name='+winner.Name}" style="width: 120px; height: 120px; border-radius: 30px; border: 4px solid var(--gold); margin-bottom: 20px;">
                <h2 style="color: white; margin: 0;">${winner.Name}</h2>
                <div style="color: var(--success); font-size: 28px; font-weight: 800; margin-top: 10px;">+${Number(pool * 0.95).toLocaleString()} GTLM</div>
            `);
            
            $('#winnerOverlay').css('display', 'flex');
            
            // Confetti
            var duration = 5 * 1000;
            var animationEnd = Date.now() + duration;
            var defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 11000 };

            function randomInRange(min, max) {
              return Math.random() * (max - min) + min;
            }

            var interval = setInterval(function() {
              var timeLeft = animationEnd - Date.now();
              if (timeLeft <= 0) return clearInterval(interval);
              var particleCount = 50 * (timeLeft / duration);
              confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 } }));
              confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 } }));
            }, 250);
        }

        function closeWinner() {
            $('#winnerOverlay').hide();
        }

        function loadHistory() {
            $.get('../api_megaspin.php', { action: 'get_history' }, function(res) {
                if (res.success) {
                    let html = '';
                    res.history.forEach(h => {
                        html += `
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px; color: #94a3b8;">
                                <span>🏆 ${h.winner_name}</span>
                                <span style="color: var(--success);">+${Number(h.pool_amount * 0.95).toLocaleString()}</span>
                            </div>
                        `;
                    });
                    $('#winHistory').html(html);
                }
            }, 'json');
        }

        $(document).ready(function() {
            updateUI();
            loadHistory();
            setInterval(updateUI, 2000); // Polling mỗi 2s
            setInterval(loadHistory, 10000); // Cập nhật history mỗi 10s
        });
    </script>
</body>
</html>
