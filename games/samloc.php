<?php
session_start();
require_once '../db_connect.php';
require_once '../load_theme.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['Iduser'];
$stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userMoney = $stmt->get_result()->fetch_assoc()['Money'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sâm Lốc Multiplayer | GTLM Gaming</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
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
            background: var(--bg);
            background-image: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
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

        .lobby-container {
            width: 90vw;
            max-width: 900px;
            background: rgba(0,0,0,0.8);
            padding: 30px;
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            overflow-y: auto;
            max-height: 90vh;
        }

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
        .card.selected { transform: translateY(-20px); border: 2px solid var(--primary); }

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
            padding: 12px 30px;
            border-radius: 12px;
            border: none;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-secondary { background: #334155; color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn:hover { filter: brightness(1.2); transform: translateY(-2px); }
        .btn:disabled { opacity: 0.3; cursor: not-allowed; transform: none; }

        #result-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(10px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        .result-box {
            background: var(--panel);
            padding: 50px;
            border-radius: 40px;
            text-align: center;
            border: 2px solid var(--primary);
        }

        .status-msg {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 40px;
            font-weight: 800;
            color: rgba(255,255,255,0.8);
            pointer-events: none;
            text-transform: uppercase;
            letter-spacing: 5px;
            text-align: center;
            text-shadow: 0 4px 10px rgba(0,0,0,0.8);
            z-index: 50;
        }

        #threejs-background {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: -999; pointer-events: none;
        }
    </style>
</head>
<body>
<canvas id="threejs-background"></canvas>

<?php if (!isset($_GET['id'])): ?>
    <!-- ================== LOBBY UI ================== -->
    <div class="lobby-container">
        <a href="../index.php" style="position:absolute; top:20px; left:20px; color:#94a3b8; text-decoration:none; font-weight:600; background: rgba(0,0,0,0.5); padding: 10px 20px; border-radius: 10px;">🏠 Trang Chủ</a>
        <h1 style="text-align: center; color: var(--primary); margin-bottom: 30px; font-size: 30px;">SẢNH SÂM LỐC MULTIPLAYER</h1>
        
        <div style="display: flex; justify-content: space-between; margin-bottom: 20px; align-items: center;">
            <h2 style="margin: 0;">Danh Sách Bàn Đang Mở</h2>
            <button onclick="createRoom()" class="btn btn-success">+ TẠO PHÒNG MỚI</button>
        </div>
        
        <div id="lobby-rooms" style="display: grid; gap: 15px;">
            <div style="text-align:center; padding: 20px;">Đang tải danh sách phòng...</div>
        </div>
    </div>
    
    <script>
        async function loadRooms() {
            try {
                const res = await fetch('../api_samloc_lobby.php?action=list');
                const data = await res.json();
                if(data.success) {
                    const container = document.getElementById('lobby-rooms');
                    container.innerHTML = data.tables.length === 0 ? '<div style="text-align:center;">Không có phòng nào đang mở.</div>' : data.tables.map(t => `
                        <div style="background: rgba(255,255,255,0.1); padding: 15px 25px; border-radius: 15px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h3 style="margin: 0; color: #fbbf24; font-size: 20px;">${t.room_name}</h3>
                                <div style="font-size: 14px; opacity: 0.8; margin-top: 5px;">
                                    Mức cược: ${Number(t.min_bet).toLocaleString()} GTLM
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 20px;">
                                <div style="font-weight: bold;">
                                    <span style="color: ${t.status === 'playing' ? 'var(--danger)' : 'var(--success)'}">${t.status === 'playing' ? 'Đang Chơi' : 'Đang Chờ'}</span>
                                    | 👥 ${t.player_count}/4
                                </div>
                                <button onclick="window.location.href='samloc.php?id=${t.id}'" class="btn btn-primary" style="padding: 8px 20px;">
                                    VÀO BÀN
                                </button>
                            </div>
                        </div>
                    `).join('');
                }
            } catch(e) {}
        }
        
        async function createRoom() {
            const { value: formValues } = await Swal.fire({
                title: 'Tạo Phòng Sâm Lốc',
                html:
                    '<input id="swal-input1" class="swal2-input" placeholder="Tên phòng (VD: Bàn Sâm của tôi)">' +
                    '<select id="swal-input2" class="swal2-select" style="display:flex; width: 73%; margin: 1em auto;">' +
                        '<option value="10000">Mức Cược: 10,000 GTLM</option>' +
                        '<option value="50000">Mức Cược: 50,000 GTLM</option>' +
                        '<option value="100000">Mức Cược: 100,000 GTLM</option>' +
                        '<option value="500000">Mức Cược: 500,000 GTLM</option>' +
                        '<option value="1000000">Mức Cược: 1,000,000 GTLM</option>' +
                        '<option value="5000000">Mức Cược: 5,000,000 GTLM</option>' +
                    '</select>' +
                    '<select id="swal-input3" class="swal2-select" style="display:flex; width: 73%; margin: 1em auto;">' +
                        '<option value="0">Không có Bot</option>' +
                        '<option value="1">Thêm sẵn 1 Bot</option>' +
                        '<option value="2">Thêm sẵn 2 Bot</option>' +
                        '<option value="3">Thêm sẵn 3 Bot</option>' +
                        '<option value="4">Thêm sẵn 4 Bot</option>' +
                    '</select>',
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Tạo Phòng',
                confirmButtonColor: '#10b981',
                preConfirm: () => {
                    const name = document.getElementById('swal-input1').value;
                    const minBet = document.getElementById('swal-input2').value;
                    const botCount = document.getElementById('swal-input3').value;
                    if (!name || !minBet) {
                        Swal.showValidationMessage('Vui lòng nhập thông tin');
                        return false;
                    }
                    return [name, minBet, botCount]
                }
            });

            if (formValues) {
                const fd = new FormData();
                fd.append('room_name', formValues[0]);
                fd.append('min_bet', formValues[1]);
                fd.append('max_bet', formValues[1] * 100);
                fd.append('bot_count', formValues[2]);
                
                const res = await fetch('../api_samloc_lobby.php?action=create', { method: 'POST', body: fd });
                const data = await res.json();
                if(data.success) {
                    window.location.href = 'samloc.php?id=' + data.table_id;
                } else {
                    Swal.fire('Lỗi', data.message, 'error');
                }
            }
        }
        
        loadRooms();
        setInterval(loadRooms, 3000);
    </script>

<?php else: ?>
    <!-- ================== GAME UI ================== -->
    <a href="samloc.php" style="position:fixed; top:20px; left:20px; color:#94a3b8; text-decoration:none; font-weight:600; background: rgba(0,0,0,0.5); padding: 10px 20px; border-radius: 10px; z-index: 1000;">⬅ Sảnh Chờ</a>
    
    <div style="position:fixed; top:20px; right:20px; background: rgba(0,0,0,0.5); padding: 10px 20px; border-radius: 10px; z-index: 1000; border: 1px solid var(--glass-border);">
        <span style="opacity: 0.8; font-size: 14px;">GTLM:</span>
        <strong style="color: #fbbf24; font-size: 18px; margin-left: 5px;"><?= number_format($userMoney, 0, ',', '.') ?></strong>
    </div>

    <div class="game-table">
        <div class="status-msg" id="game-status">ĐANG TẢI BÀN...</div>

        <!-- Opponents -->
        <div class="player-slot slot-left" id="slot-4" style="display:none;">
            <div class="player-info"><div class="player-name">TRỐNG</div><div class="card-count">0 cards</div></div>
        </div>
        <div class="player-slot slot-top-left" id="slot-3" style="display:none;">
            <div class="player-info"><div class="player-name">TRỐNG</div><div class="card-count">0 cards</div></div>
        </div>
        <div class="player-slot slot-top-right" id="slot-2" style="display:none;">
            <div class="player-info"><div class="player-name">TRỐNG</div><div class="card-count">0 cards</div></div>
        </div>
        <div class="player-slot slot-right" id="slot-1" style="display:none;">
            <div class="player-info"><div class="player-name">TRỐNG</div><div class="card-count">0 cards</div></div>
        </div>

        <!-- Center Area -->
        <div class="center-area">
            <div class="played-cards" id="last-move-cards">
                <!-- Cards played by last player -->
            </div>
        </div>

        <!-- Self -->
        <div class="player-slot slot-self" id="slot-0">
            <div class="hand" id="my-hand"></div>
            <div class="player-info">
                <div class="player-name">BẠN</div>
                <div class="card-count" id="count-0">0 cards</div>
            </div>
        </div>

        <!-- Controls -->
        <div class="controls" id="game-controls" style="display:none;">
            <button class="btn btn-secondary" onclick="passTurn()" id="btn-pass">BỎ LƯỢT</button>
            <button class="btn btn-primary" onclick="playCards()" id="btn-play">ĐÁNH BÀI</button>
        </div>
        
        <div class="controls" id="waiting-controls" style="display:none; bottom: 80px;">
            <button class="btn btn-success" onclick="addBot()" id="btn-add-bot">🤖 THÊM BOT</button>
        </div>
        
        <div class="controls" id="xinlang-controls" style="display:none; bottom: 150px; gap: 15px;">
            <button class="btn btn-primary" onclick="xinLang()" id="btn-xin-lang" style="background: var(--danger); box-shadow: 0 0 20px red;">🔥 HÔ SÂM (XIN LÀNG)</button>
            <button class="btn btn-secondary" onclick="skipXinLang()" id="btn-skip-xin-lang">⏩ BỎ QUA</button>
        </div>
    </div>

    <!-- Error toast (no popup needed) -->
    <div id="error-toast" style="display:none; position:fixed; bottom:180px; left:50%; transform:translateX(-50%); background: rgba(239,68,68,0.9); color: white; padding: 10px 25px; border-radius: 12px; font-weight: 700; z-index: 9999; backdrop-filter: blur(8px); border: 1px solid #ef4444; box-shadow: 0 4px 20px rgba(239,68,68,0.4); font-size: 14px; pointer-events:none;"></div>


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
    
    <script>
        const tableId = <?= (int)$_GET['id'] ?>;
        const myUserId = <?= $userId ?>;
        let selectedCards = [];
        let mySeat = -1;
        let isMyTurn = false;
        
        async function joinRoom() {
            await fetch(`../api_samloc_multi.php?action=join&table_id=${tableId}`);
        }
        
        async function addBot() {
            await fetch(`../api_samloc_multi.php?action=add_bot&table_id=${tableId}`);
        }
        
        async function xinLang() {
            $('#xinlang-controls').hide();
            await fetch(`../api_samloc_multi.php?action=xin_lang&table_id=${tableId}`);
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
            const suitStr = suitMap[card.s];
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
            const res = await fetch(`../api_samloc_multi.php?action=play&table_id=${tableId}`, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                selectedCards = [];
                pollState();
            } else {
                showErrorToast(data.message || 'Nước đi không hợp lệ!');
            }
        }

        async function passTurn() {
            const res = await fetch(`../api_samloc_multi.php?action=pass&table_id=${tableId}`);
            const data = await res.json();
            if (data.success) {
                selectedCards = [];
                pollState();
            } else {
                showErrorToast(data.message || 'Không thể bỏ lượt!');
            }
        }

        function showErrorToast(msg) {
            const $toast = $('#error-toast');
            $toast.text(msg).fadeIn(200);
            setTimeout(() => $toast.fadeOut(400), 2000);
        }

        async function pollState() {
            try {
                const res = await fetch(`../api_samloc_multi.php?action=status&table_id=${tableId}`);
                const data = await res.json();
                if (!data.success) return;
                
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                
                if (data.reload) {
                    window.isProgrammaticReload = true;
                    window.location.reload();
                    return;
                }
                
                const table = data.table;
                const players = data.players;
                mySeat = data.my_seat;
                
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
                    let msg = 'ĐANG CHỜ NGƯỜI CHƠI...';
                    if (players.length > 1 && table.timeLeft > 0) {
                        msg = `BẮT ĐẦU SAU ${table.timeLeft}s`;
                    }
                    $('#game-status').text(msg).show();
                    $('#game-controls').hide();
                    $('#waiting-controls').show();
                    $('#xinlang-controls').hide();
                    
                    if (players.length >= 5) $('#btn-add-bot').hide();
                } else if (table.status === 'playing') {
                    window.hasShownResult = false;
                    $('#game-status').hide();
                    $('#waiting-controls').hide();
                    $('#xinlang-controls').hide();
                    $('#game-controls').show();
                } else if (table.status === 'ended') {
                    let isWinner = false;
                    players.forEach(p => { if(p.status === 'won' && p.user_id == myUserId) isWinner = true; });
                    
                    if (!window.hasShownResult) {
                        window.hasShownResult = true;
                        if (isWinner) {
                            if (typeof GameEffects !== 'undefined') GameEffects.showWin();
                        } else {
                            if (typeof GameEffects !== 'undefined') GameEffects.showLoss();
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
                    
                    let name = p.user_id == myUserId ? 'BẠN' : (p.is_bot ? 'Bot ' + p.seat_index : 'User ' + p.user_id);
                    $slot.find('.player-name').text(name);
                    
                    // Reveal cards & Penalty if game ended
                    if (table.status === 'ended') {
                        $slot.find('.card-count').hide();
                        
                        let $cardContainer = $slot.find('.opp-cards-container');
                        if ($cardContainer.length === 0) {
                            $slot.prepend('<div class="opp-cards-container"></div>');
                            $cardContainer = $slot.find('.opp-cards-container');
                        }
                        
                        let oppHandHtml = '';
                        if (p.cards && p.cards.length > 0) { // Show for ALL players when ended (including self)
                            let scale = posIndex === 0 ? 0.75 : 0.6;
                            let mt = posIndex === 0 ? '-30px' : '-60px';
                            oppHandHtml += `<div class="hand" style="transform: scale(${scale}); margin-top: ${mt}; height: 80px; position:relative; z-index:50; justify-content: center;">`;
                            p.cards.forEach(c => {
                                oppHandHtml += createCardUI(c, false);
                            });
                            oppHandHtml += '</div>';
                        }
                        
                        // Status badge (Penalty / Winner)
                        if (p.penalty > 0) {
                            oppHandHtml += `<div style="position: absolute; top: ${posIndex === 0 ? '-50px' : '-100px'}; left: 50%; transform: translateX(-50%); font-weight: 900; font-size: 20px; color: var(--danger); text-shadow: 0 2px 5px black; z-index: 1000; background: rgba(0,0,0,0.7); padding: 5px 15px; border-radius: 10px; border: 2px solid var(--danger); white-space: nowrap; animation: popUp 0.5s ease;">- ${Number(p.penalty).toLocaleString()} GTLM</div>`;
                        } else if (p.status === 'won') {
                            oppHandHtml += `<div style="position: absolute; top: ${posIndex === 0 ? '-50px' : '-100px'}; left: 50%; transform: translateX(-50%); font-weight: 900; font-size: 20px; color: var(--success); text-shadow: 0 2px 5px black; z-index: 1000; background: rgba(0,0,0,0.7); padding: 5px 15px; border-radius: 10px; border: 2px solid var(--success); white-space: nowrap; animation: popUp 0.5s ease;">WINNER</div>`;
                        }
                        
                        $cardContainer.html(oppHandHtml);
                    } else {
                        // Normal playing mode
                        $slot.find('.opp-cards-container').empty();
                        $slot.find('.card-count').text(p.card_count + ' lá').show();
                    }
                    
                    if (table.current_turn === p.seat_index && table.status === 'playing') {
                        $slot.find('.player-info').addClass('active');
                    }
                    if (table.passed_players.includes(p.seat_index) && table.status === 'playing') {
                        $slot.find('.player-info').addClass('passed');
                    }
                });
                
                // My Cards
                if (mySeat > -1 && (table.status === 'playing' || table.status === 'xin_lang')) {
                    let handHtml = '';
                    data.my_cards.forEach(c => {
                        handHtml += createCardUI(c);
                    });
                    $('#my-hand').html(handHtml);
                    
                    // Keep selection
                    selectedCards.forEach(id => {
                        $(`.card[data-id="${id}"]`).addClass('selected');
                    });
                }
                
                isMyTurn = (table.current_turn === mySeat && table.status === 'playing');
                $('#btn-play').prop('disabled', !isMyTurn);
                $('#btn-pass').prop('disabled', !isMyTurn || table.last_move === null);
                
                // Last Move Stacking
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
                        
                        // Start animation from scaled up
                        $newGroup.css({transform: `translate(${randomX}, ${randomY}) scale(2)`, opacity: 0});
                        $('#last-move-cards').append($newGroup);
                        
                        // Play small pop sound if possible
                        setTimeout(() => {
                            $newGroup.css({transform: `translate(${randomX}, ${randomY}) scale(1) rotate(${randomAngle})`, opacity: 1});
                        }, 50);
                    }
                } else {
                    $('#last-move-cards').empty();
                    window.lastRenderedMoveKey = null;
                }

            } catch (e) {
                console.error(e);
            }
        }

        $(document).ready(function() {
            joinRoom().then(() => {
                pollState();
                setInterval(pollState, 1500); // Poll every 1.5s
            });
            
            // Gọi API leave khi người chơi thoát trang
            window.addEventListener('beforeunload', function() {
                if (!window.isProgrammaticReload) {
                    navigator.sendBeacon(`../api_samloc_multi.php?action=leave&table_id=${tableId}`);
                }
            });
        });

    </script>
<?php endif; ?>
</body>
</html>
