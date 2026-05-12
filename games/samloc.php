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
    <title>Sâm Lốc - Bài Việt Nam Tốc Độ | GTLM Gaming</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .game-table {
            width: 95vw;
            height: 90vh;
            max-width: 1200px;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 100px; /* Oval table */
            border: 8px solid #334155;
            position: relative;
            box-shadow: inset 0 0 100px rgba(0,0,0,0.5), 0 20px 50px rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* --- Players --- */
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
        .slot-top { top: 20px; left: 50%; transform: translateX(-50%); }
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

        /* --- Cards --- */
        .card {
            width: 70px;
            height: 100px;
            background: white;
            border-radius: 8px;
            color: black;
            display: flex;
            flex-direction: column;
            padding: 5px;
            box-sizing: border-box;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            cursor: pointer;
            transition: transform 0.2s, margin 0.2s;
            position: relative;
            user-select: none;
            border: 1px solid #ccc;
        }

        .card.red { color: var(--danger); }
        .card.selected { transform: translateY(-20px); border: 2px solid var(--primary); }

        .card-value { font-size: 20px; font-weight: 800; line-height: 1; }
        .card-suit { font-size: 16px; margin-top: 2px; }

        .hand {
            display: flex;
            gap: -20px; /* Overlap cards */
            justify-content: center;
            margin-top: 20px;
        }

        .hand .card {
            margin-left: -35px;
        }
        .hand .card:first-child { margin-left: 0; }

        /* --- Center Play Area --- */
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

        /* --- Controls --- */
        .controls {
            position: absolute;
            bottom: 120px;
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
        .btn:hover { filter: brightness(1.2); transform: translateY(-2px); }
        .btn:disabled { opacity: 0.3; cursor: not-allowed; transform: none; }

        /* --- Overlays --- */
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

        /* --- Notifications --- */
        .status-msg {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 40px;
            font-weight: 800;
            color: rgba(255,255,255,0.1);
            pointer-events: none;
            text-transform: uppercase;
            letter-spacing: 10px;
        }

    </style>
</head>
<body>

<div class="game-table">
    <div class="status-msg" id="game-status">SÂM LỐC</div>

    <!-- Opponents -->
    <div class="player-slot slot-left">
        <div class="player-info" id="p-3">
            <div class="player-name">Bot 3</div>
            <div class="card-count" id="count-3">10 cards</div>
        </div>
    </div>
    <div class="player-slot slot-top">
        <div class="player-info" id="p-2">
            <div class="player-name">Bot 2</div>
            <div class="card-count" id="count-2">10 cards</div>
        </div>
    </div>
    <div class="player-slot slot-right">
        <div class="player-info" id="p-1">
            <div class="player-name">Bot 1</div>
            <div class="card-count" id="count-1">10 cards</div>
        </div>
    </div>

    <!-- Center Area -->
    <div class="center-area">
        <div class="played-cards" id="last-move-cards">
            <!-- Cards played by last player -->
        </div>
    </div>

    <!-- Self -->
    <div class="player-slot slot-self">
        <div class="hand" id="my-hand"></div>
        <div class="player-info" id="p-0">
            <div class="player-name">BẠN</div>
            <div class="card-count" id="count-0">10 cards</div>
        </div>
    </div>

    <!-- Controls -->
    <div class="controls">
        <button class="btn btn-secondary" onclick="passTurn()" id="btn-pass">BỎ LƯỢT</button>
        <button class="btn btn-primary" onclick="playCards()" id="btn-play">ĐÁNH BÀI</button>
    </div>
</div>

<div id="result-overlay">
    <div class="result-box">
        <h1 id="result-title" style="font-size: 60px; margin-bottom: 20px;">WIN!</h1>
        <p id="result-detail" style="font-size: 20px; opacity: 0.8;"></p>
        <button class="btn btn-primary" onclick="startGame()" style="margin-top: 30px;">CHƠI TIẾP</button>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let selectedCards = [];
    let isMyTurn = false;

    const suitChars = { 's': '♠', 'c': '♣', 'd': '♦', 'h': '♥' };
    const valueChars = { 3:'3', 4:'4', 5:'5', 6:'6', 7:'7', 8:'8', 9:'9', 10:'10', 11:'J', 12:'Q', 13:'K', 14:'A', 15:'2' };

    function createCardUI(card, isSmall = false) {
        const isRed = (card.s === 'd' || card.s === 'h');
        return `
            <div class="card ${isRed ? 'red' : ''} ${isSmall ? 'played-card' : ''}" 
                 data-id="${card.id}" 
                 style="--r: ${(Math.random()*20-10)}deg"
                 onclick="toggleCard('${card.id}')">
                <div class="card-value">${valueChars[card.v]}</div>
                <div class="card-suit">${suitChars[card.s]}</div>
            </div>
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

    function updateUI(data) {
        if (!data.success) return;
        const game = data.game;
        
        // Update Hands
        let handHtml = '';
        game.hands[0].forEach(card => {
            handHtml += createCardUI(card);
        });
        $('#my-hand').html(handHtml);
        
        // Update Card Counts
        $('#count-0').text(game.hands[0].length + ' cards');
        $('#count-1').text(game.hands[1] + ' cards');
        $('#count-2').text(game.hands[2] + ' cards');
        $('#count-3').text(game.hands[3] + ' cards');
        
        // Update Turn Highlight
        $('.player-info').removeClass('active');
        $(`#p-${game.turn}`).addClass('active');
        isMyTurn = (game.turn === 0);
        
        $('#btn-play, #btn-pass').prop('disabled', !isMyTurn);
        if (game.last_move === null) $('#btn-pass').prop('disabled', true);

        // Update Last Move
        if (game.history.length > 0) {
            const last = game.history[game.history.length - 1];
            if (last.type !== 'pass') {
                let lastMoveHtml = '';
                last.cards.forEach(c => {
                    lastMoveHtml += createCardUI(c, true);
                });
                $('#last-move-cards').html(lastMoveHtml);
            }
        } else {
            $('#last-move-cards').empty();
        }

        // Check End
        if (game.status === 'ended') {
            $('#result-overlay').css('display', 'flex');
            $('#result-title').text(game.winner === 0 ? 'CHIẾN THẮNG!' : 'THẤT BẠI!');
            $('#result-title').css('color', game.winner === 0 ? 'var(--success)' : 'var(--danger)');
        } else {
            $('#result-overlay').hide();
        }
    }

    function startGame() {
        selectedCards = [];
        $.get('../api_samloc.php?action=start', updateUI);
    }

    function playCards() {
        if (selectedCards.length === 0) return;
        $.post('../api_samloc.php?action=play', { cards: selectedCards }, function(data) {
            if (data.success) {
                selectedCards = [];
                updateUI(data);
            } else {
                Swal.fire('Lỗi', data.message, 'error');
            }
        });
    }

    function passTurn() {
        $.get('../api_samloc.php?action=pass', updateUI);
    }

    $(document).ready(function() {
        startGame();
    });

</script>
</body>
</html>
