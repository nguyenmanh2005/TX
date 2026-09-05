<?php
session_start();

require '../db_connect.php'; // DB kết nối trước!
require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_49', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;
if (!isset($_SESSION['Iduser'])) {
    $_SESSION['Iduser'] = $botUserId;
}

require_once '../load_theme.php';

$userId = $botUserId;
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$userMoney = $userRow['Money'] ?? 50000000;
$userName = $userRow['Name'] ?? 'Bot Streamer 49';
$stmt->close();

$isTableActive = isset($_GET['id']) && (int)$_GET['id'] > 0;
$tableId = $isTableActive ? (int)$_GET['id'] : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sâm Lốc Live Stream | GTLM Gaming</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <style>
        :root {
            --bg: #0f172a;
            --panel: rgba(30, 41, 59, 0.7);
            --primary: #6366f1;
            --secondary: #a855f7;
            --success: #22c55e;
            --danger: #ef4444;
            --text: #f8fafc;
            --glass: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        body {
            background: transparent;
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #threejs-background {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: -999; pointer-events: none;
            background: <?= $bgGradientCSS ?? 'linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%)' ?>;
        }

        /* ── Sảnh Chờ (Lobby UI) ── */
        .lobby-container {
            width: 90vw;
            max-width: 950px;
            background: rgba(15, 23, 42, 0.85);
            padding: 35px;
            border-radius: 24px;
            border: 1px solid var(--glass-border);
            overflow-y: auto;
            max-height: 88vh;
            box-shadow: 0 20px 50px rgba(0,0,0,0.6);
            backdrop-filter: blur(16px);
            position: relative;
            z-index: 10;
        }
        .lobby-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--glass-border);
        }
        .lobby-title {
            margin: 0;
            font-size: 26px;
            font-weight: 800;
            background: linear-gradient(135deg, #818cf8, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .room-card {
            background: rgba(30, 41, 59, 0.6);
            padding: 18px 24px;
            border-radius: 16px;
            border: 1px solid var(--glass-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.25s ease;
        }
        .room-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.2);
        }

        /* ── Bàn Chơi Game (Table UI) ── */
        .game-table {
            width: 95vw;
            height: 90vh;
            max-width: 1200px;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 100px;
            border: 8px solid #334155;
            position: relative;
            box-shadow: inset 0 0 100px rgba(0,0,0,0.5), 0 20px 50px rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(8px);
        }

        .player-slot {
            position: absolute;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            z-index: 10;
        }

        .slot-self { bottom: -30px; left: 50%; transform: translateX(-50%); }
        .slot-left { left: 40px; top: 50%; transform: translateY(-50%); }
        .slot-top-left { top: 20px; left: 30%; transform: translateX(-50%); }
        .slot-top-right { top: 20px; right: 30%; transform: translateX(50%); }
        .slot-right { right: 40px; top: 50%; transform: translateY(-50%); }

        .player-info {
            background: var(--panel);
            backdrop-filter: blur(10px);
            padding: 10px 20px;
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            text-align: center;
            min-width: 120px;
            transition: all 0.3s;
        }
        .player-info.active {
            border-color: var(--primary);
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.4);
            transform: scale(1.1);
        }
        .player-name { font-weight: 800; font-size: 14px; }
        .card-count { font-size: 12px; color: var(--primary); font-weight: 600; }
        .player-info.passed { opacity: 0.5; filter: grayscale(1); }

        .card {
            width: 70px;
            height: 100px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            cursor: pointer;
            transition: transform 0.2s, margin 0.2s;
            position: relative;
            user-select: none;
            border: 1px solid transparent;
            object-fit: cover;
            padding: 0;
        }
        .card.selected { transform: translateY(-20px); border: 2px solid var(--primary); box-shadow: 0 0 15px rgba(99,102,241,0.8); }

        .hand {
            display: flex;
            gap: -40px;
            justify-content: center;
            margin-top: 20px;
            height: 120px;
        }
        .hand .card:first-child { margin-left: 0; }

        .center-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }
        .played-cards {
            display: flex;
            gap: 10px;
            min-height: 120px;
            align-items: center;
            justify-content: center;
            perspective: 1000px;
        }
        .played-card {
            transform: rotate(var(--r));
            box-shadow: 0 10px 20px rgba(0,0,0,0.5);
            pointer-events: none;
        }

        .controls {
            position: absolute;
            bottom: 150px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 15px;
            z-index: 100;
        }
        .btn {
            padding: 12px 28px;
            border-radius: 12px;
            border: none;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
        }
        .btn-primary { background: var(--primary); color: white; box-shadow: 0 4px 15px rgba(99,102,241,0.4); }
        .btn-secondary { background: #334155; color: white; }
        .btn-success { background: var(--success); color: white; box-shadow: 0 4px 15px rgba(34,197,94,0.4); }
        .btn:hover { filter: brightness(1.2); transform: translateY(-2px); }
        .btn:disabled { opacity: 0.3; cursor: not-allowed; transform: none; }

        .status-msg {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 34px;
            font-weight: 800;
            color: rgba(255,255,255,0.85);
            pointer-events: none;
            text-transform: uppercase;
            letter-spacing: 4px;
            text-align: center;
            text-shadow: 0 4px 15px rgba(0,0,0,0.8);
            z-index: 50;
        }

        /* 🏆 Modal Badge Kết Quả Giống Game ID 1 */
        #result-status-badge {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.5);
            z-index: 99999;
            background: rgba(15, 23, 42, 0.95);
            border: 3px solid #fbbf24;
            border-radius: 24px;
            padding: 30px 50px;
            text-align: center;
            box-shadow: 0 0 50px rgba(251, 191, 36, 0.5);
            backdrop-filter: blur(15px);
            transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            pointer-events: none;
        }
        #result-status-badge.win {
            border-color: #fbbf24;
            box-shadow: 0 0 50px rgba(251, 191, 36, 0.5);
        }
        #result-status-badge.loss {
            border-color: #ef4444;
            box-shadow: 0 0 50px rgba(239, 68, 68, 0.5);
        }
        #result-badge-icon { font-size: 50px; margin-bottom: 8px; }
        #result-badge-title { font-size: 26px; font-weight: 900; letter-spacing: 2px; text-transform: uppercase; }
        #result-badge-amount { font-size: 18px; font-weight: 700; margin-top: 6px; font-family: 'JetBrains Mono', monospace; }

        @keyframes popUp {
            0% { transform: translate(-50%, -50%) scale(0.6); opacity: 0; }
            50% { transform: translate(-50%, -50%) scale(1.05); opacity: 1; }
            100% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
        }
    </style>
</head>
<body>
<canvas id="threejs-background"></canvas>

<!-- Modal Badge Kết Quả Giống Game ID 1 -->
<div id="result-status-badge">
    <div id="result-badge-icon">🏆</div>
    <div id="result-badge-title" style="color: #fbbf24;">THẮNG SÂM LỐC!</div>
    <div id="result-badge-amount" style="color: #4ade80;">+500,000 GTLM</div>
</div>

<?php if (!$isTableActive): ?>
    <!-- ================== SẢNH CHỜ (LOBBY VIEW) ================== -->
    <div class="lobby-container">
        <div class="lobby-header">
            <div>
                <h1 class="lobby-title"><i class="fa-solid fa-cards"></i> SẢNH SÂM LỐC MULTIPLAYER</h1>
                <div style="font-size: 13px; color: #94a3b8; margin-top: 4px;">Streamer Bot tự động chọn bàn hoặc tạo phòng thi đấu</div>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="background: rgba(0,0,0,0.5); padding: 8px 16px; border-radius: 12px; border: 1px solid var(--glass-border);">
                    <span style="opacity: 0.8; font-size: 13px;">Ví Streamer:</span>
                    <strong style="color: #fbbf24; font-size: 16px; font-family: 'JetBrains Mono', monospace; margin-left: 5px;"><?= number_format($userMoney, 0, ',', '.') ?></strong>
                </div>
                <button onclick="botCreateRoom()" class="btn btn-success bot-allowed" id="btn-create-room" data-allow-bot="true">
                    <i class="fa-solid fa-plus"></i> + TẠO PHÒNG MỚI
                </button>
            </div>
        </div>

        <div style="margin-bottom: 15px; font-weight: 700; color: #cbd5e1; display: flex; justify-content: space-between;">
            <span>DANH SÁCH BÀN ĐANG MỞ</span>
            <span style="font-size: 13px; color: var(--primary);"><i class="fa-solid fa-sync fa-spin"></i> Cập nhật trực tiếp</span>
        </div>

        <div id="lobby-rooms" style="display: grid; gap: 14px;">
            <div style="text-align:center; padding: 30px; color: #94a3b8;">Đang tải danh sách phòng...</div>
        </div>
    </div>

    <script>
        window.roomsLoaded = false;
        async function loadRooms() {
            try {
                const res = await fetch('../api_samloc_lobby.php?action=list');
                const data = await res.json();
                if(data.success) {
                    window.roomsLoaded = true;
                    const container = document.getElementById('lobby-rooms');
                    if (data.tables.length === 0) {
                        container.innerHTML = '<div style="text-align:center; padding: 30px; color: #94a3b8;">Hiện chưa có phòng nào. Bot đang chuẩn bị tạo phòng mới...</div>';
                    } else {
                        container.innerHTML = data.tables.map(t => `
                            <div class="room-card" data-room-id="${t.id}" data-players="${t.player_count}" data-status="${t.status}">
                                <div>
                                    <h3 style="margin: 0; color: #fbbf24; font-size: 18px;">${t.room_name}</h3>
                                    <div style="font-size: 13px; opacity: 0.8; margin-top: 4px; font-family: 'JetBrains Mono', monospace;">
                                        Mức cược: ${Number(t.min_bet).toLocaleString()} GTLM
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 16px;">
                                    <div style="font-weight: bold; font-size: 14px;">
                                        <span style="color: ${t.status === 'playing' ? 'var(--danger)' : 'var(--success)'}">${t.status === 'playing' ? 'Đang Chơi' : 'Đang Chờ'}</span>
                                        | 👥 ${t.player_count}/4
                                    </div>
                                    <button onclick="joinTable(${t.id})" class="btn btn-primary btn-join-room bot-allowed" data-allow-bot="true" data-id="${t.id}" style="padding: 8px 18px; font-size: 13px;">
                                        VÀO BÀN
                                    </button>
                                </div>
                            </div>
                        `).join('');
                    }
                }
            } catch(e) {
                console.error('loadRooms error:', e);
            }
        }

        function joinTable(id) {
            window.location.href = 'live_49.php?id=' + id;
        }

        async function botCreateRoom() {
            const roomNames = [
                'Bàn Sâm Lốc Streamer #' + Math.floor(Math.random()*900 + 100),
                'Đại Chiến Sâm Lốc VIP #' + Math.floor(Math.random()*900 + 100),
                'Sát Phạt Đêm Nay #' + Math.floor(Math.random()*900 + 100),
                'Đấu Trường Sâm Lốc #' + Math.floor(Math.random()*900 + 100)
            ];
            const randomTitle = roomNames[Math.floor(Math.random() * roomNames.length)];
            const fd = new FormData();
            fd.append('room_name', randomTitle);
            fd.append('min_bet', 50000);
            fd.append('max_bet', 5000000);
            fd.append('bot_count', 3);
            
            try {
                const res = await fetch('../api_samloc_lobby.php?action=create', { method: 'POST', body: fd });
                const data = await res.json();
                if(data.success && data.table_id) {
                    window.location.href = 'live_49.php?id=' + data.table_id;
                }
            } catch(e) {
                console.error('botCreateRoom error:', e);
            }
        }

        loadRooms();
        setInterval(loadRooms, 2500);
    </script>

<?php else: ?>
    <!-- ================== BÀN CHƠI GAME (TABLE VIEW) ================== -->
    <a href="live_49.php" id="btn-back-lobby" data-allow-bot="true" class="bot-allowed" style="position:fixed; top:20px; left:20px; color:#94a3b8; text-decoration:none; font-weight:600; background: rgba(0,0,0,0.6); padding: 10px 20px; border-radius: 12px; z-index: 1000; border: 1px solid var(--glass-border); display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-arrow-left"></i> ⬅ Sảnh Chờ
    </a>

    <div style="position:fixed; top:20px; right:20px; background: rgba(0,0,0,0.6); padding: 10px 20px; border-radius: 12px; z-index: 1000; border: 1px solid var(--glass-border); display: flex; align-items: center; gap: 10px;">
        <span style="opacity: 0.8; font-size: 14px;">GTLM:</span>
        <strong style="color: #fbbf24; font-size: 18px; font-family: 'JetBrains Mono', monospace;"><?= number_format($userMoney, 0, ',', '.') ?></strong>
    </div>

    <div class="game-table">
        <div class="status-msg" id="game-status">ĐANG TẢI BÀN SÂM LỐC...</div>

        <!-- Opponents -->
        <div class="player-slot slot-left" id="slot-4" style="display:none;">
            <div class="player-info"><div class="player-name">TRỐNG</div><div class="card-count">0 lá</div></div>
        </div>
        <div class="player-slot slot-top-left" id="slot-3" style="display:none;">
            <div class="player-info"><div class="player-name">TRỐNG</div><div class="card-count">0 lá</div></div>
        </div>
        <div class="player-slot slot-top-right" id="slot-2" style="display:none;">
            <div class="player-info"><div class="player-name">TRỐNG</div><div class="card-count">0 lá</div></div>
        </div>
        <div class="player-slot slot-right" id="slot-1" style="display:none;">
            <div class="player-info"><div class="player-name">TRỐNG</div><div class="card-count">0 lá</div></div>
        </div>

        <!-- Center Area -->
        <div class="center-area">
            <div class="played-cards" id="last-move-cards"></div>
        </div>

        <!-- Self (Streamer Bot) -->
        <div class="player-slot slot-self" id="slot-0">
            <div class="hand" id="my-hand"></div>
            <div class="player-info">
                <div class="player-name">BẠN (STREAMER BOT)</div>
                <div class="card-count" id="count-0">0 lá</div>
            </div>
        </div>

        <!-- Controls -->
        <div class="controls" id="game-controls" style="display:none;">
            <button class="btn btn-secondary" onclick="passTurn()" id="btn-pass">BỎ LƯỢT</button>
            <button class="btn btn-primary" onclick="playCards()" id="btn-play">ĐÁNH BÀI</button>
        </div>
        
        <div class="controls" id="waiting-controls" style="display:none; bottom: 80px;">
            <button class="btn btn-success" onclick="addBot()" id="btn-add-bot">🤖 THÊM BOT ĐỂ BẮT ĐẦU</button>
        </div>
        
        <div class="controls" id="xinlang-controls" style="display:none; bottom: 150px; gap: 15px;">
            <button class="btn btn-primary" onclick="xinLang()" id="btn-xin-lang" style="background: var(--danger); box-shadow: 0 0 20px red;">🔥 HÔ SÂM (XIN LÀNG)</button>
            <button class="btn btn-secondary" onclick="skipXinLang()" id="btn-skip-xin-lang">⏩ BỎ QUA</button>
        </div>
    </div>

    <!-- Error toast -->
    <div id="error-toast" style="display:none; position:fixed; bottom:180px; left:50%; transform:translateX(-50%); background: rgba(239,68,68,0.95); color: white; padding: 10px 25px; border-radius: 12px; font-weight: 700; z-index: 9999; backdrop-filter: blur(8px); border: 1px solid #ef4444; box-shadow: 0 4px 20px rgba(239,68,68,0.4); font-size: 14px; pointer-events:none;"></div>

    <script>
        const tableId = <?= $tableId ?>;
        const myUserId = <?= $userId ?>;
        let selectedCards = [];
        let mySeat = -1;
        let isMyTurn = false;
        window.currentTableLastMove = null;
        window.lastRoundWinner = null;
        
        async function joinRoom() {
            try {
                await fetch(`../api_samloc_multi.php?action=join&table_id=${tableId}`);
            } catch(e) {
                console.error('joinRoom error:', e);
            }
        }
        
        async function addBot() {
            try {
                await fetch(`../api_samloc_multi.php?action=add_bot&table_id=${tableId}`);
                pollState();
            } catch(e) {
                console.error('addBot error:', e);
            }
        }
        
        async function xinLang() {
            $('#xinlang-controls').hide();
            try {
                await fetch(`../api_samloc_multi.php?action=xin_lang&table_id=${tableId}`);
                pollState();
            } catch(e) {
                console.error('xinLang error:', e);
            }
        }

        async function skipXinLang() {
            window.hasSkippedXinLang = true;
            $('#xinlang-controls').hide();
            try {
                await fetch(`../api_samloc_multi.php?action=skip_xin_lang&table_id=${tableId}`);
            } catch(e) {}
        }

        function createCardUI(card, isSmall = false) {
            const suitMap = {'s': 'spades', 'c': 'clubs', 'd': 'diamonds', 'h': 'hearts'};
            const suitStr = suitMap[card.s] || 'spades';
            let valStr = card.v;
            if (card.v == 14) valStr = 'A';
            else if (card.v == 11) valStr = 'J';
            else if (card.v == 12) valStr = 'Q';
            else if (card.v == 13) valStr = 'K';
            else if (card.v == 15) valStr = '02';
            else if (card.v < 10) valStr = '0' + card.v;
            
            const url = `img/anh-bai/PNG/Cards (large)/card_${suitStr}_${valStr}.png`;
            return `
                <img class="card ${isSmall ? 'played-card' : ''}" 
                     data-id="${card.id}" 
                     src="${url}"
                     onerror="if(!this.dataset.retried){this.dataset.retried=1;this.src='../games/'+this.getAttribute('src');}"
                     style="--r: ${(Math.random()*20-10)}deg;"
                     onclick="toggleCard('${card.id}')">
            `;
        }

        function toggleCard(id) {
            if (!isMyTurn) return;
            const idx = selectedCards.indexOf(id);
            if (idx > -1) {
                selectedCards.splice(idx, 1);
                $(`.card[data-id="${id}"]`).removeClass('selected');
            } else {
                selectedCards.push(id);
                $(`.card[data-id="${id}"]`).addClass('selected');
            }
        }

        async function playCards() {
            if (selectedCards.length === 0) return;
            const fd = new FormData();
            selectedCards.forEach(c => fd.append('cards[]', c));
            try {
                const res = await fetch(`../api_samloc_multi.php?action=play&table_id=${tableId}`, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    selectedCards = [];
                    pollState();
                } else {
                    showErrorToast(data.message || 'Nước đi không hợp lệ!');
                }
            } catch(e) {
                console.error('playCards error:', e);
            }
        }

        async function passTurn() {
            try {
                const res = await fetch(`../api_samloc_multi.php?action=pass&table_id=${tableId}`);
                const data = await res.json();
                if (data.success) {
                    selectedCards = [];
                    pollState();
                } else {
                    showErrorToast(data.message || 'Không thể bỏ lượt!');
                }
            } catch(e) {
                console.error('passTurn error:', e);
            }
        }

        function showErrorToast(msg) {
            const $toast = $('#error-toast');
            $toast.text(msg).fadeIn(200);
            setTimeout(() => $toast.fadeOut(400), 2000);
        }

        function showResultBadge(isWin, amountText) {
            const $b = $('#result-status-badge');
            if (isWin) {
                $b.removeClass('loss').addClass('win');
                $('#result-badge-icon').text('🏆');
                $('#result-badge-title').css('color', '#fbbf24').text('THẮNG SÂM LỐC!');
                $('#result-badge-amount').css('color', '#4ade80').text(amountText || '+500,000 GTLM');
                if (typeof confetti === 'function') {
                    confetti({ particleCount: 100, spread: 70, origin: { y: 0.6 } });
                }
            } else {
                $b.removeClass('win').addClass('loss');
                $('#result-badge-icon').text('❌');
                $('#result-badge-title').css('color', '#ef4444').text('THUA VÁN BÀI!');
                $('#result-badge-amount').css('color', '#f87171').text(amountText || '-50,000 GTLM');
            }
            $b.css({display: 'block', opacity: 0, transform: 'translate(-50%, -50%) scale(0.6)'});
            gsap.to($b[0], { scale: 1, opacity: 1, duration: 0.35, ease: 'back.out(1.7)' });
            setTimeout(() => {
                gsap.to($b[0], { scale: 0.6, opacity: 0, duration: 0.3, onComplete: () => $b.hide() });
            }, 3200);
        }

        async function pollState() {
            try {
                const res = await fetch(`../api_samloc_multi.php?action=status&table_id=${tableId}`);
                const data = await res.json();
                if (!data.success) return;
                
                if (data.redirect) {
                    window.location.href = 'live_49.php';
                    return;
                }
                
                if (data.reload) {
                    window.isProgrammaticReload = true;
                    pollState();
                    return;
                }
                
                const table = data.table;
                const players = data.players;
                mySeat = data.my_seat;
                window.currentTableLastMove = table.last_move;
                
                if (table.status === 'xin_lang') {
                    window.hasShownResult = false;
                    $('#game-status').text(`HÔ SÂM / XIN LÀNG... (${table.timeLeft}S)`).show();
                    $('#game-controls').hide();
                    $('#waiting-controls').hide();
                    if (!window.hasSkippedXinLang) {
                        $('#xinlang-controls').show();
                    } else {
                        $('#xinlang-controls').hide();
                    }
                } else if (table.status === 'waiting') {
                    window.hasSkippedXinLang = false;
                    window.hasShownResult = false;
                    let msg = 'ĐANG CHỜ ĐỐI THỦ...';
                    if (players.length > 1) {
                        msg = table.timeLeft > 0 ? `BẮT ĐẦU SAU ${table.timeLeft}S` : 'ĐANG CHIA BÀI...';
                    }
                    $('#game-status').text(msg).show();
                    $('#game-controls').hide();
                    $('#waiting-controls').show();
                    $('#xinlang-controls').hide();
                    
                    if (players.length >= 4) $('#btn-add-bot').hide();
                    else $('#btn-add-bot').show();
                } else if (table.status === 'playing') {
                    window.hasShownResult = false;
                    $('#game-status').hide();
                    $('#waiting-controls').hide();
                    $('#xinlang-controls').hide();
                    $('#game-controls').show();
                } else if (table.status === 'ended') {
                    let isWinner = false;
                    let winnerPlayer = null;
                    players.forEach(p => { 
                        if (p.status === 'won') {
                            winnerPlayer = p;
                            if (p.user_id == myUserId) isWinner = true;
                        }
                    });
                    window.lastRoundWinner = winnerPlayer ? winnerPlayer.user_id : null;
                    
                    if (!window.hasShownResult) {
                        window.hasShownResult = true;
                        const myPlayerObj = players.find(p => p.user_id == myUserId);
                        const pen = myPlayerObj && myPlayerObj.penalty ? Number(myPlayerObj.penalty).toLocaleString() : '';
                        if (isWinner) {
                            showResultBadge(true, '+500,000 GTLM');
                        } else {
                            showResultBadge(false, pen ? `-${pen} GTLM` : '-50,000 GTLM');
                        }
                    }
                    
                    $('#game-status').text(`KẾT THÚC VÁN - ĐỢI ${table.timeLeft}S`).show();
                    $('#game-controls').hide();
                }
                
                let positions = [
                    { id: '#slot-0' }, // 0: Self
                    { id: '#slot-1' }, // 1: Right
                    { id: '#slot-2' }, // 2: Top Right
                    { id: '#slot-3' }, // 3: Top Left
                    { id: '#slot-4' }  // 4: Left
                ];
                
                $('.player-slot').hide();
                $('.player-info').removeClass('active passed');
                
                players.forEach(p => {
                    let posIndex = mySeat > -1 ? (p.seat_index - mySeat + 5) % 5 : p.seat_index;
                    let $slot = $(positions[posIndex].id);
                    $slot.show();
                    
                    let name = p.user_id == myUserId ? 'BẠN (STREAMER)' : (p.is_bot ? 'Bot AI ' + p.seat_index : 'User ' + p.user_id);
                    $slot.find('.player-name').text(name);
                    
                    if (table.status === 'ended') {
                        $slot.find('.card-count').hide();
                        let $cardContainer = $slot.find('.opp-cards-container');
                        if ($cardContainer.length === 0) {
                            $slot.prepend('<div class="opp-cards-container"></div>');
                            $cardContainer = $slot.find('.opp-cards-container');
                        }
                        
                        let oppHandHtml = '';
                        if (p.cards && p.cards.length > 0) {
                            let scale = posIndex === 0 ? 0.75 : 0.6;
                            let mt = posIndex === 0 ? '-30px' : '-60px';
                            oppHandHtml += `<div class="hand" style="transform: scale(${scale}); margin-top: ${mt}; height: 80px; position:relative; z-index:50; justify-content: center;">`;
                            p.cards.forEach(c => {
                                oppHandHtml += createCardUI(c, false);
                            });
                            oppHandHtml += '</div>';
                        }
                        
                        if (p.penalty > 0) {
                            oppHandHtml += `<div style="position: absolute; top: ${posIndex === 0 ? '-50px' : '-100px'}; left: 50%; transform: translateX(-50%); font-weight: 900; font-size: 20px; color: var(--danger); text-shadow: 0 2px 5px black; z-index: 1000; background: rgba(0,0,0,0.85); padding: 5px 15px; border-radius: 10px; border: 2px solid var(--danger); white-space: nowrap; animation: popUp 0.5s ease;">- ${Number(p.penalty).toLocaleString()} GTLM</div>`;
                        } else if (p.status === 'won') {
                            oppHandHtml += `<div style="position: absolute; top: ${posIndex === 0 ? '-50px' : '-100px'}; left: 50%; transform: translateX(-50%); font-weight: 900; font-size: 20px; color: var(--success); text-shadow: 0 2px 5px black; z-index: 1000; background: rgba(0,0,0,0.85); padding: 5px 15px; border-radius: 10px; border: 2px solid var(--success); white-space: nowrap; animation: popUp 0.5s ease;">🏆 WINNER</div>`;
                        }
                        
                        $cardContainer.html(oppHandHtml);
                    } else {
                        $slot.find('.opp-cards-container').empty();
                        $slot.find('.card-count').text(p.card_count + ' lá').show();
                    }
                    
                    if (table.current_turn === p.seat_index && table.status === 'playing') {
                        $slot.find('.player-info').addClass('active');
                    }
                    if (table.passed_players && table.passed_players.includes(p.seat_index) && table.status === 'playing') {
                        $slot.find('.player-info').addClass('passed');
                    }
                });
                
                if (mySeat > -1 && (table.status === 'playing' || table.status === 'xin_lang') && data.my_cards) {
                    let handHtml = '';
                    data.my_cards.forEach(c => {
                        handHtml += createCardUI(c);
                    });
                    $('#my-hand').html(handHtml);
                    
                    selectedCards.forEach(id => {
                        $(`.card[data-id="${id}"]`).addClass('selected');
                    });
                }
                
                isMyTurn = (table.current_turn === mySeat && table.status === 'playing');
                $('#btn-play').prop('disabled', !isMyTurn);
                $('#btn-pass').prop('disabled', !isMyTurn || table.last_move === null);
                
                if (table.last_move && table.last_move.cards) {
                    let lastMoveKey = JSON.stringify(table.last_move) + table.last_player;
                    if (window.lastRenderedMoveKey !== lastMoveKey) {
                        window.lastRenderedMoveKey = lastMoveKey;
                        
                        let newCardsHtml = '<div class="move-group" style="position:absolute; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); display: flex; gap: -20px;">';
                        table.last_move.cards.forEach(c => {
                            newCardsHtml += createCardUI(c, true);
                        });
                        newCardsHtml += '</div>';
                        
                        let $newGroup = $(newCardsHtml);
                        let randomAngle = (Math.random() * 30 - 15) + 'deg';
                        let randomX = (Math.random() * 60 - 30) + 'px';
                        let randomY = (Math.random() * 60 - 30) + 'px';
                        
                        $newGroup.css({transform: `translate(${randomX}, ${randomY}) scale(2)`, opacity: 0});
                        $('#last-move-cards').append($newGroup);
                        
                        setTimeout(() => {
                            $newGroup.css({transform: `translate(${randomX}, ${randomY}) scale(1) rotate(${randomAngle})`, opacity: 1});
                        }, 50);
                    }
                } else {
                    $('#last-move-cards').empty();
                    window.lastRenderedMoveKey = null;
                }

            } catch (e) {
                console.error('pollState error:', e);
            }
        }

        $(document).ready(function() {
            joinRoom().then(() => {
                pollState();
                setInterval(pollState, 1200);
            });
        });
    </script>
<?php endif; ?>

<!-- ── Nạp ThreeJS Background & Bot Streamer ── -->
<script>
    window.themeConfig = {
        particleCount: <?= $particleCount ?? 1500 ?>,
        particleSize: <?= $particleSize ?? 0.05 ?>,
        particleColor: '<?= $particleColor ?? "#ffffff" ?>',
        particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
        shapeCount: <?= $shapeCount ?? 15 ?>,
        shapeColors: <?= json_encode($shapeColors ?? ["#ef4444", "#eab308", "#22c55e", "#3b82f6"]) ?>,
        shapeOpacity: <?= $shapeOpacity ?? 0.3 ?>
    };
</script>
<script src="../threejs-background.js"></script>
<script src="../assets/js/game-effects.js"></script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script src="bots/bot_49.js"></script>

</body>
</html>
