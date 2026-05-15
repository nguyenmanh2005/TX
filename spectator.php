<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['Iduser'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spectator Mode - Xem Live & Tip</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #0f0c29;
            background: linear-gradient(to right, #24243e, #302b63, #0f0c29);
            color: #fff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 40px; }
        .live-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .live-card {
            background: rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 20px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: transform 0.3s;
            position: relative;
            overflow: hidden;
        }
        .live-card:hover { transform: translateY(-5px); background: rgba(255,255,255,0.15); }
        .live-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #e74c3c;
            color: white;
            padding: 2px 8px;
            border-radius: 5px;
            font-size: 0.7em;
            font-weight: bold;
            animation: blink 1s infinite;
        }
        @keyframes blink { 50% { opacity: 0.5; } }
        .streamer-name { font-weight: bold; font-size: 1.2em; color: #3498db; }
        .game-type { color: #bdc3c7; margin-bottom: 15px; }
        .btn-watch {
            width: 100%;
            background: #2ecc71;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
        }
        .tip-section { margin-top: 15px; display: flex; gap: 5px; }
        .tip-input {
            flex: 1;
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
        }
        .btn-tip { background: #f1c40f; color: #000; border: none; padding: 5px 15px; border-radius: 5px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fa fa-eye"></i> Spectator Mode</h1>
            <p>Xem các cao thủ đang chơi và ủng hộ (Tip) cho họ!</p>
        </div>

        <div id="live-list" class="live-grid">
            <!-- Dữ liệu sẽ load qua JS -->
            <div style="grid-column: 1/-1; text-align: center;">Đang tìm kiếm trận đấu live...</div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function loadLive() {
            fetch('api_spectator.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_live'
            })
            .then(r => r.json())
            .then(data => {
                const container = document.getElementById('live-list');
                if (data.lives.length === 0) {
                    container.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #7f8c8d;">Hiện không có ai đang live.</div>';
                    return;
                }
                container.innerHTML = data.lives.map(live => `
                    <div class="live-card">
                        <div class="live-badge">LIVE</div>
                        <div class="streamer-name">${live.streamer_name}</div>
                        <div class="game-type">Đang chơi: ${live.game_type}</div>
                        <button class="btn-watch" onclick="watchStream(${live.id})">VÀO XEM</button>
                        <div class="tip-section">
                            <input type="number" id="tip-amount-${live.id}" class="tip-input" placeholder="GTLM Tip...">
                            <button class="btn-tip" onclick="tipStreamer(${live.id})">TIP</button>
                        </div>
                    </div>
                `).join('');
            });
        }

        function watchStream(id) {
            window.location.href = 'watch.php?id=' + id;
        }

        function tipStreamer(id) {
            const amount = document.getElementById('tip-amount-' + id).value;
            if (!amount || amount <= 0) return;

            fetch('api_spectator.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=tip&stream_id=${id}&amount=${amount}`
            })
            .then(r => r.json())
            .then(data => {
                Swal.fire(data.success ? 'Thành công' : 'Lỗi', data.message, data.success ? 'success' : 'error');
                if (data.success) document.getElementById('tip-amount-' + id).value = '';
            });
        }

        setInterval(loadLive, 5000);
        loadLive();
    </script>
</body>
</html>
