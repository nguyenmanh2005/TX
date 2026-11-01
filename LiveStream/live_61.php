<?php
/**
 * ♟️ Võ Đài Cờ Ca-rô AI LiveStream (Bàn 61) — Bot vs Bot 24/7
 * Trận đại chiến siêu trí tuệ nhân tạo: Master AlphaX (❌) vs Grandmaster DeepO (⭕)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/bot_streamer_helper.php';

$botUserX = getOrCreateBotStreamerUser($conn, 'bot_61', 50000000);
$moneyX = (float)($botUserX['Money'] ?? 50000000);

if (isset($_GET['action']) && $_GET['action'] === 'record_round') {
    header('Content-Type: application/json; charset=utf-8');
    $winner = $_GET['winner'] ?? 'X';
    $pot = 1000000;
    // Cập nhật số dư tượng trưng cho Bot Streamer
    $diff = ($winner === 'X') ? ($pot / 2) : -($pot / 2);
    $conn->query("UPDATE users SET Money = Money + $diff WHERE Iduser = " . (int)$botUserX['Iduser']);
    echo json_encode(['success' => true, 'new_balance' => number_format($moneyX + $diff, 0, ',', '.')]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>♟️ Đại Chiến Cờ Ca-rô AI (AlphaX vs DeepO) - Live Bàn 61</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&family=Orbitron:wght@700;900&family=JetBrains+Mono:wght@600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --red-neon: #ef4444;
            --blue-neon: #38bdf8;
            --gold-neon: #fbbf24;
            --emerald: #10b981;
            --board-bg: #131722;
            --grid-line: rgba(255, 255, 255, 0.12);
        }
        * { box-sizing: border-box; user-select: none; }
        body {
            margin: 0;
            padding: 10px;
            background: radial-gradient(circle at 50% 20%, #0d1527 0%, #060913 100%);
            color: #fff;
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            overflow-x: hidden;
        }

        /* 👑 SÂN ĐẤU HEADER */
        .arena-header {
            width: 100%;
            max-width: 820px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
            padding: 10px 16px;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(12px);
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .bot-card {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            padding: 8px 12px;
            border-radius: 14px;
            transition: all 0.3s ease;
            position: relative;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid transparent;
        }
        .bot-card.active-turn {
            background: rgba(255, 255, 255, 0.08);
            transform: scale(1.02);
        }
        .bot-card.bot-x.active-turn {
            border-color: var(--red-neon);
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.4);
        }
        .bot-card.bot-o.active-turn {
            border-color: var(--blue-neon);
            box-shadow: 0 0 20px rgba(56, 189, 248, 0.4);
        }

        .bot-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            position: relative;
            flex-shrink: 0;
        }
        .bot-x .bot-avatar {
            background: linear-gradient(135deg, #ef4444, #991b1b);
            border: 2px solid #f87171;
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.5);
        }
        .bot-o .bot-avatar {
            background: linear-gradient(135deg, #0284c7, #075985);
            border: 2px solid #38bdf8;
            box-shadow: 0 0 15px rgba(56, 189, 248, 0.5);
        }

        .bot-info {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .bot-name {
            font-family: 'Orbitron', 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .bot-x .bot-name { color: #fca5a5; }
        .bot-o .bot-name { color: #bae6fd; }

        .bot-status {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .bot-score {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--gold-neon);
        }

        /* VS CENTER BADGE */
        .vs-center {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 0 8px;
            min-width: 140px;
        }
        .vs-badge {
            font-family: 'Orbitron', 'Outfit', sans-serif;
            font-weight: 900;
            font-size: 1.1rem;
            background: linear-gradient(135deg, #f59e0b, #ef4444);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 2px;
        }
        .pot-badge {
            font-size: 0.72rem;
            background: rgba(251, 191, 36, 0.15);
            border: 1px solid rgba(251, 191, 36, 0.4);
            color: var(--gold-neon);
            padding: 2px 8px;
            border-radius: 999px;
            font-weight: 700;
            margin-top: 3px;
        }
        .turn-timer {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            color: #94a3b8;
            margin-top: 2px;
        }

        /* ♟️ BÀN CỜ CA-RÔ CHUẨN 15x15 */
        .board-wrapper {
            position: relative;
            background: #141b2d;
            padding: 10px;
            border-radius: 18px;
            border: 2px solid rgba(59, 130, 246, 0.3);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8), inset 0 0 40px rgba(0, 0, 0, 0.6);
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 8px;
        }

        /* Tọa độ cờ */
        .coord-letters {
            display: grid;
            grid-template-columns: repeat(15, 26px);
            gap: 2px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            color: #64748b;
            text-align: center;
            margin-bottom: 4px;
        }
        .coord-row {
            display: flex;
            align-items: center;
        }
        .coord-num {
            width: 18px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            color: #64748b;
            text-align: center;
        }

        .caro-grid {
            display: grid;
            grid-template-columns: repeat(15, 26px);
            grid-template-rows: repeat(15, 26px);
            gap: 2px;
            background: #0f172a;
            padding: 6px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .caro-cell {
            width: 26px;
            height: 26px;
            background: #1e293b;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 900;
            cursor: default;
            transition: all 0.15s ease;
            position: relative;
        }
        .caro-cell:hover {
            background: #334155;
        }
        .caro-cell.star-point::after {
            content: '';
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
        }

        /* Quân X và Quân O */
        .caro-cell.piece-x {
            background: rgba(239, 68, 68, 0.15);
            color: var(--red-neon);
            text-shadow: 0 0 10px rgba(239, 68, 68, 0.8);
            animation: popStone 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .caro-cell.piece-o {
            background: rgba(56, 189, 248, 0.15);
            color: var(--blue-neon);
            text-shadow: 0 0 10px rgba(56, 189, 248, 0.8);
            animation: popStone 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes popStone {
            0% { transform: scale(0.2); opacity: 0; }
            70% { transform: scale(1.25); }
            100% { transform: scale(1); opacity: 1; }
        }

        /* Nước đi cuối cùng (highlight) */
        .caro-cell.last-move {
            outline: 2px solid var(--gold-neon);
            outline-offset: -2px;
            box-shadow: 0 0 12px var(--gold-neon);
            z-index: 5;
        }

        /* Đường thắng 5 quân */
        .caro-cell.win-cell {
            background: rgba(251, 191, 36, 0.3) !important;
            outline: 2px solid #fbbf24 !important;
            animation: winPulse 0.8s infinite alternate ease-in-out;
            z-index: 10;
        }
        @keyframes winPulse {
            0% { transform: scale(1); filter: brightness(1); }
            100% { transform: scale(1.12); filter: brightness(1.5); }
        }

        /* 🤖 VIRTUAL BOT CURSORS TRÊN BÀN CỜ */
        .bot-cursor {
            position: absolute;
            width: 22px;
            height: 22px;
            pointer-events: none;
            z-index: 50;
            transition: all 0.35s cubic-bezier(0.25, 1, 0.5, 1);
            filter: drop-shadow(0 2px 8px rgba(0,0,0,0.8));
            opacity: 0;
            transform: translate(-50%, -50%);
        }
        .bot-cursor.active { opacity: 1; }
        .bot-cursor.cursor-x { color: var(--red-neon); }
        .bot-cursor.cursor-o { color: var(--blue-neon); }
        .bot-cursor i { font-size: 18px; transform: rotate(-45deg); }

        /* 📜 NHẬT KÝ NƯỚC ĐI & FOOTER STATUS */
        .arena-footer {
            width: 100%;
            max-width: 820px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 16px;
            background: rgba(15, 23, 42, 0.7);
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 0.82rem;
            gap: 10px;
        }
        .log-ticker {
            color: #cbd5e1;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }
        .arena-controls {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-ctrl {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #fff;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 0.75rem;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.2s;
        }
        .btn-ctrl:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-1px);
        }

        /* 🏆 VICTORY POPUP OVERLAY */
        .victory-overlay {
            position: absolute;
            inset: 0;
            background: rgba(3, 7, 18, 0.85);
            backdrop-filter: blur(8px);
            border-radius: 18px;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 100;
            animation: fadeIn 0.3s ease;
        }
        .victory-overlay.show { display: flex; }
        .victory-title {
            font-family: 'Orbitron', 'Outfit', sans-serif;
            font-size: 1.6rem;
            font-weight: 900;
            text-align: center;
            margin-bottom: 6px;
        }
        .victory-sub {
            font-size: 0.95rem;
            color: var(--gold-neon);
            font-weight: 700;
            margin-bottom: 15px;
        }
        .victory-next {
            font-size: 0.8rem;
            color: #94a3b8;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        @media (max-width: 500px) {
            .caro-grid, .coord-letters {
                grid-template-columns: repeat(15, 20px);
                grid-template-rows: repeat(15, 20px);
            }
            .caro-cell { width: 20px; height: 20px; font-size: 11px; }
            .coord-letters { font-size: 8px; }
            .coord-num { width: 14px; font-size: 8px; }
        }
    </style>
</head>
<body>

    <!-- 👑 SÂN ĐẤU HEADER: BOT ALPHAX VS BOT DEEPO -->
    <div class="arena-header">
        <!-- BOT 1: ALPHAX -->
        <div class="bot-card bot-x active-turn" id="botCardX">
            <div class="bot-avatar">
                <i class="fa-solid fa-robot"></i>
            </div>
            <div class="bot-info">
                <div class="bot-name">
                    <span>AlphaX</span>
                    <span style="color:var(--red-neon); font-size:1.1rem;">❌</span>
                </div>
                <div class="bot-status">
                    <span id="statusTextX">🔴 Đang suy nghĩ...</span>
                </div>
                <div class="bot-score">
                    🏆 <span id="scoreX">0</span> Ván Thắng
                </div>
            </div>
        </div>

        <!-- VS & POT -->
        <div class="vs-center">
            <div class="vs-badge">VS</div>
            <div class="pot-badge">
                💰 1.000.000 GTLM
            </div>
            <div class="turn-timer" id="turnTimerText">
                ⏳ Nước đi: <span id="moveCount">0</span>
            </div>
        </div>

        <!-- BOT 2: DEEPO -->
        <div class="bot-card bot-o" id="botCardO">
            <div class="bot-avatar">
                <i class="fa-solid fa-microchip"></i>
            </div>
            <div class="bot-info">
                <div class="bot-name">
                    <span>DeepO</span>
                    <span style="color:var(--blue-neon); font-size:1.1rem;">⭕</span>
                </div>
                <div class="bot-status">
                    <span id="statusTextO">⚪ Chờ lượt</span>
                </div>
                <div class="bot-score">
                    🏆 <span id="scoreO">0</span> Ván Thắng
                </div>
            </div>
        </div>
    </div>

    <!-- ♟️ BÀN CỜ CA-RÔ 15x15 ĐỈNH CAO -->
    <div class="board-wrapper" id="boardWrapper">
        <!-- Con trỏ ảo chuyển động -->
        <div class="bot-cursor cursor-x" id="cursorX"><i class="fa-solid fa-arrow-pointer"></i></div>
        <div class="bot-cursor cursor-o" id="cursorO"><i class="fa-solid fa-arrow-pointer"></i></div>

        <!-- Tọa độ chữ A - O -->
        <div class="coord-letters">
            <span>A</span><span>B</span><span>C</span><span>D</span><span>E</span>
            <span>F</span><span>G</span><span>H</span><span>I</span><span>J</span>
            <span>K</span><span>L</span><span>M</span><span>N</span><span>O</span>
        </div>

        <!-- Lưới cờ 15x15 kèm tọa độ số 1-15 -->
        <div class="coord-row">
            <div class="coord-num" style="display:flex; flex-direction:column; justify-content:space-between; height:418px; padding:6px 0;">
                <?php for ($i = 1; $i <= 15; $i++) echo "<span>$i</span>"; ?>
            </div>
            <div class="caro-grid" id="caroGrid"></div>
            <div class="coord-num" style="display:flex; flex-direction:column; justify-content:space-between; height:418px; padding:6px 0;">
                <?php for ($i = 1; $i <= 15; $i++) echo "<span>$i</span>"; ?>
            </div>
        </div>

        <!-- Tọa độ chữ A - O đáy -->
        <div class="coord-letters" style="margin-top:4px; margin-bottom:0;">
            <span>A</span><span>B</span><span>C</span><span>D</span><span>E</span>
            <span>F</span><span>G</span><span>H</span><span>I</span><span>J</span>
            <span>K</span><span>L</span><span>M</span><span>N</span><span>O</span>
        </div>

        <!-- VICTORY OVERLAY BANNER -->
        <div class="victory-overlay" id="victoryOverlay">
            <div class="victory-title" id="victoryTitle">🎉 CHIẾN THẮNG TUYỆT ĐỐI!</div>
            <div class="victory-sub" id="victorySub">Master AlphaX chốt hạ 5 quân liên tiếp (+1.000.000 GTLM)</div>
            <div class="victory-next">🔄 Ván mới sẽ tự động bắt đầu sau <b id="countdownNext">4</b>s...</div>
        </div>
    </div>

    <!-- 📜 NHẬT KÝ NƯỚC ĐI & ĐIỀU KHIỂN KHÁN GIẢ -->
    <div class="arena-footer">
        <div class="log-ticker" id="logTicker">
            ⚡ Trận đấu cờ Ca-rô AI 15x15 trực tiếp đang diễn ra kịch tính!
        </div>
        <div class="arena-controls">
            <button class="btn-ctrl" id="btnSpeed" title="Thay đổi tốc độ bot">⚡ 1x</button>
            <button class="btn-ctrl" id="btnSound" title="Bật/Tắt âm thanh">🔊 Âm</button>
            <button class="btn-ctrl" id="btnRestart" title="Bắt đầu ván mới ngay">🔄 Đổi Ván</button>
        </div>
    </div>

    <!-- Thư viện bot chat và kịch bản đấu trí -->
    <script src="../assets/js/bot_chat.js"></script>
    <script src="bots/bot_61.js?v=<?= time() ?>"></script>

    <script>
    (function () {
        'use strict';

        // 🎵 SYNTHESIZER AUDIO (Không phụ thuộc file ngoài)
        let audioCtx = null;
        let soundEnabled = true;

        function initAudio() {
            if (!audioCtx) {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (AudioContext) audioCtx = new AudioContext();
            }
        }
        function playTone(freq, type, duration, gainVal = 0.15) {
            if (!soundEnabled) return;
            try {
                initAudio();
                if (!audioCtx) return;
                if (audioCtx.state === 'suspended') audioCtx.resume();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = type;
                osc.frequency.setValueAtTime(freq, audioCtx.currentTime);
                gain.gain.setValueAtTime(gainVal, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + duration);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start();
                osc.stop(audioCtx.currentTime + duration);
            } catch (e) {}
        }
        function playStoneClick() {
            playTone(320, 'triangle', 0.08, 0.25);
            setTimeout(() => playTone(180, 'sine', 0.05, 0.15), 25);
        }
        function playThreatSound() {
            playTone(650, 'sine', 0.12, 0.2);
            setTimeout(() => playTone(880, 'sine', 0.15, 0.2), 100);
        }
        function playWinSound() {
            [523, 659, 784, 1046].forEach((f, idx) => {
                setTimeout(() => playTone(f, 'triangle', 0.35, 0.3), idx * 120);
            });
        }

        // ♟️ CARO ENGINE CONFIGURATION
        const SIZE = 15;
        let board = Array.from({ length: SIZE }, () => Array(SIZE).fill(0));
        let currentTurn = 1; // 1: X (AlphaX), 2: O (DeepO)
        let firstTurnNext = 2; // Luân phiên lượt mở màn
        let isGameOver = false;
        let isThinking = false;
        let moveCount = 0;
        let scoreX = 0;
        let scoreO = 0;
        let turnDelay = 1600; // ms per turn (1x mode)

        const LETTERS = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O'];
        const STAR_POINTS = [[7,7], [3,3], [11,3], [3,11], [11,11]]; // H8, D4, L4, D12, L12

        // Khởi tạo lưới HTML 15x15
        const gridEl = document.getElementById('caroGrid');
        function initGridDOM() {
            gridEl.innerHTML = '';
            for (let r = 0; r < SIZE; r++) {
                for (let c = 0; c < SIZE; c++) {
                    const cell = document.createElement('div');
                    cell.className = 'caro-cell';
                    cell.dataset.row = r;
                    cell.dataset.col = c;
                    if (STAR_POINTS.some(([sr, sc]) => sr === r && sc === c)) {
                        cell.classList.add('star-point');
                    }
                    gridEl.appendChild(cell);
                }
            }
        }
        initGridDOM();

        function getCellEl(r, c) {
            return gridEl.querySelector(`.caro-cell[data-row="${r}"][data-col="${c}"]`);
        }

        // Cập nhật giao diện trạng thái 2 Bot
        function updateTurnUI() {
            $('#moveCount').text(moveCount);
            if (currentTurn === 1) {
                $('#botCardX').addClass('active-turn');
                $('#botCardO').removeClass('active-turn');
                $('#statusTextX').html('🔴 Đang suy nghĩ...');
                $('#statusTextO').html('⚪ Chờ đối thủ');
            } else {
                $('#botCardO').addClass('active-turn');
                $('#botCardX').removeClass('active-turn');
                $('#statusTextO').html('🔵 Đang tính nước...');
                $('#statusTextX').html('⚪ Chờ đối thủ');
            }
        }

        // ĐẶT QUÂN CỜ
        function placePiece(r, c, player) {
            board[r][c] = player;
            moveCount++;
            const cell = getCellEl(r, c);
            if (!cell) return;

            $('.caro-cell').removeClass('last-move');
            cell.classList.add('last-move');

            if (player === 1) {
                cell.classList.add('piece-x');
                cell.textContent = '❌';
            } else {
                cell.classList.add('piece-o');
                cell.textContent = '⭕';
            }

            playStoneClick();

            const coordName = LETTERS[c] + (r + 1);
            const botName = (player === 1) ? '🔴 AlphaX' : '🔵 DeepO';
            $('#logTicker').html(`<b>${botName}</b> hạ quân tại ô <b>[${coordName}]</b> — Nước thứ ${moveCount}`);

            // Kiểm tra chiến thắng
            const winLine = checkWin(r, c, player);
            if (winLine) {
                handleVictory(player, winLine);
                return;
            }

            // Kiểm tra hòa cờ (hết ô)
            if (moveCount >= SIZE * SIZE) {
                handleDraw();
                return;
            }

            // Chuyển lượt
            currentTurn = (currentTurn === 1) ? 2 : 1;
            updateTurnUI();
            scheduleNextMove();
        }

        // KIỂM TRA 5 QUÂN LIỀN HÀNG
        const DIRECTIONS = [
            [[0, 1], [0, -1]],   // Ngang
            [[1, 0], [-1, 0]],   // Dọc
            [[1, 1], [-1, -1]],  // Chéo chính
            [[1, -1], [-1, 1]]   // Chéo phụ
        ];

        function checkWin(r, c, player) {
            for (let pair of DIRECTIONS) {
                let line = [[r, c]];
                for (let [dr, dc] of pair) {
                    let nr = r + dr;
                    let nc = c + dc;
                    while (nr >= 0 && nr < SIZE && nc >= 0 && nc < SIZE && board[nr][nc] === player) {
                        line.push([nr, nc]);
                        nr += dr;
                        nc += dc;
                    }
                }
                if (line.length >= 5) return line;
            }
            return null;
        }

        // XỬ LÝ KẾT THÚC VÁN ĐẤU
        function handleVictory(winner, winLine) {
            isGameOver = true;
            playWinSound();

            winLine.forEach(([wr, wc]) => {
                const cEl = getCellEl(wr, wc);
                if (cEl) cEl.classList.add('win-cell');
            });

            const winnerName = (winner === 1) ? 'Master AlphaX' : 'Grandmaster DeepO';
            if (winner === 1) {
                scoreX++;
                $('#scoreX').text(scoreX);
                $('#victoryTitle').html('🔴 MASTER ALPHAX CHIẾN THẮNG!');
                $('#victorySub').html(`Ngũ Tử Liền Hàng chốt hạ ván đấu thứ ${scoreX + scoreO}! (+1.000.000 GTLM)`);
            } else {
                scoreO++;
                $('#scoreO').text(scoreO);
                $('#victoryTitle').html('🔵 GRANDMASTER DEEPO TOÀN THẮNG!');
                $('#victorySub').html(`Khóa bẫy ép góc phản công ngoạn mục! (+1.000.000 GTLM)`);
            }

            // Bắn tín hiệu sang BotChat
            if (window.CaroLiveArena && window.CaroLiveArena.onWin) {
                window.CaroLiveArena.onWin(winner, winnerName);
            }

            $('#victoryOverlay').addClass('show');

            // Cập nhật số dư tượng trưng cho bàn 61
            $.get('live_61.php?action=record_round&winner=' + (winner === 1 ? 'X' : 'O'));

            let count = 4;
            $('#countdownNext').text(count);
            const cdTimer = setInterval(() => {
                count--;
                $('#countdownNext').text(count);
                if (count <= 0) {
                    clearInterval(cdTimer);
                    startNewGame();
                }
            }, 1000);
        }

        function handleDraw() {
            isGameOver = true;
            $('#victoryTitle').html('🤝 BÀN CỜ BẤT PHÂN THẮNG BẠI!');
            $('#victorySub').html('225 ô cờ đã kín, hai đại sư kỳ phùng địch thủ!');
            $('#victoryOverlay').addClass('show');
            setTimeout(startNewGame, 3500);
        }

        // 🧠 AI HEURISTIC ENGINE CHO CẢ 2 BOT
        function evaluateCell(r, c, player) {
            const opp = (player === 1) ? 2 : 1;
            let myScore = getPatternScore(r, c, player);
            let oppScore = getPatternScore(r, c, opp);

            // AlphaX thiên về công (1.3x công), DeepO thiên về thủ / phản công (1.3x thủ)
            const attackWeight = (player === 1) ? 1.3 : 1.1;
            const defenseWeight = (player === 1) ? 1.05 : 1.3;

            // Điểm trọng số trung tâm bàn cờ
            const distFromCenter = Math.abs(r - 7) + Math.abs(c - 7);
            const centerBonus = Math.max(0, 16 - distFromCenter);

            // Chút ngẫu nhiên để các trận biến hóa
            const jitter = Math.random() * 8;

            return (myScore * attackWeight) + (oppScore * defenseWeight) + centerBonus + jitter;
        }

        function getPatternScore(r, c, p) {
            let total = 0;
            for (let pair of DIRECTIONS) {
                let count = 1;
                let openEnds = 0;

                for (let [dr, dc] of pair) {
                    let nr = r + dr;
                    let nc = c + dc;
                    while (nr >= 0 && nr < SIZE && nc >= 0 && nc < SIZE && board[nr][nc] === p) {
                        count++;
                        nr += dr;
                        nc += dc;
                    }
                    if (nr >= 0 && nr < SIZE && nc >= 0 && nc < SIZE && board[nr][nc] === 0) {
                        openEnds++;
                    }
                }

                if (count >= 5) total += 100000;
                else if (count === 4) {
                    if (openEnds === 2) total += 25000;
                    else if (openEnds === 1) total += 5000;
                } else if (count === 3) {
                    if (openEnds === 2) total += 4000;
                    else if (openEnds === 1) total += 600;
                } else if (count === 2) {
                    if (openEnds === 2) total += 200;
                    else if (openEnds === 1) total += 30;
                }
            }
            return total;
        }

        function findBestMove(player) {
            // Nước đi đầu tiên: đánh vào trung tâm
            if (moveCount === 0) {
                return [7, 7];
            }
            // Nước đi thứ 2: đánh gần trung tâm
            if (moveCount === 1) {
                const candidates = [[6,6], [6,7], [6,8], [7,6], [7,8], [8,6], [8,7], [8,8]];
                return candidates[Math.floor(Math.random() * candidates.length)];
            }

            let bestScore = -1;
            let bestMoves = [];

            for (let r = 0; r < SIZE; r++) {
                for (let c = 0; c < SIZE; c++) {
                    if (board[r][c] !== 0) continue;

                    // Tối ưu: Chỉ xét các ô gần quân đã đánh (trong bán kính 2 ô)
                    let hasNeighbor = false;
                    for (let dr = -2; dr <= 2 && !hasNeighbor; dr++) {
                        for (let dc = -2; dc <= 2 && !hasNeighbor; dc++) {
                            const nr = r + dr;
                            const nc = c + dc;
                            if (nr >= 0 && nr < SIZE && nc >= 0 && nc < SIZE && board[nr][nc] !== 0) {
                                hasNeighbor = true;
                            }
                        }
                    }
                    if (!hasNeighbor) continue;

                    let score = evaluateCell(r, c, player);
                    if (score > bestScore) {
                        bestScore = score;
                        bestMoves = [[r, c]];
                    } else if (Math.abs(score - bestScore) < 5) {
                        bestMoves.push([r, c]);
                    }
                }
            }

            if (bestMoves.length === 0) {
                // Fallback nếu không có ô gần
                for (let r = 0; r < SIZE; r++) {
                    for (let c = 0; c < SIZE; c++) {
                        if (board[r][c] === 0) return [r, c];
                    }
                }
            }

            // Chọn ngẫu nhiên trong các nước điểm cao nhất
            return bestMoves[Math.floor(Math.random() * bestMoves.length)];
        }

        // LẬP LỊCH NƯỚC ĐI CỦA BOT
        function scheduleNextMove() {
            if (isGameOver || isThinking) return;
            isThinking = true;

            const player = currentTurn;
            const cursorEl = (player === 1) ? $('#cursorX') : $('#cursorO');

            setTimeout(() => {
                if (isGameOver) { isThinking = false; return; }

                const [bestR, bestC] = findBestMove(player);
                const targetCell = getCellEl(bestR, bestC);

                if (targetCell) {
                    const offset = $(targetCell).position();

                    // Di chuyển chuột ảo đến ô cờ
                    cursorEl.addClass('active');
                    cursorEl.css({
                        top: (offset.top + 13) + 'px',
                        left: (offset.left + 13) + 'px'
                    });

                    // Cảnh báo nếu có nước 3 hoặc 4
                    const patternScore = getPatternScore(bestR, bestC, player);
                    if (patternScore >= 4000) playThreatSound();

                    setTimeout(() => {
                        placePiece(bestR, bestC, player);
                        cursorEl.removeClass('active');
                        isThinking = false;
                    }, Math.max(250, turnDelay * 0.35));
                } else {
                    isThinking = false;
                }
            }, turnDelay);
        }

        // BẮT ĐẦU VÁN MỚI
        function startNewGame() {
            isGameOver = false;
            isThinking = false;
            moveCount = 0;
            board = Array.from({ length: SIZE }, () => Array(SIZE).fill(0));

            $('.caro-cell').removeClass('piece-x piece-o last-move win-cell').text('');
            $('#victoryOverlay').removeClass('show');

            currentTurn = firstTurnNext;
            firstTurnNext = (firstTurnNext === 1) ? 2 : 1;

            updateTurnUI();
            $('#logTicker').html(`✨ Ván mới khởi tranh! <b>${currentTurn === 1 ? '🔴 AlphaX' : '🔵 DeepO'}</b> giữ quyền Tiên thủ!`);

            scheduleNextMove();
        }

        // ĐIỀU KHIỂN KHÁN GIẢ
        $('#btnSpeed').on('click', function () {
            if (turnDelay === 1600) {
                turnDelay = 800;
                $(this).text('⚡ 2x').css('color', '#fbbf24');
            } else if (turnDelay === 800) {
                turnDelay = 400;
                $(this).text('🚀 Turbo').css('color', '#ef4444');
            } else {
                turnDelay = 1600;
                $(this).text('⚡ 1x').css('color', '#fff');
            }
        });

        $('#btnSound').on('click', function () {
            soundEnabled = !soundEnabled;
            $(this).text(soundEnabled ? '🔊 Âm' : '🔇 Tắt');
        });

        $('#btnRestart').on('click', function () {
            startNewGame();
        });

        // Bật âm thanh trên tương tác đầu tiên
        document.addEventListener('click', () => { initAudio(); }, { once: true });

        // Cầu nối giao tiếp với bot_61.js
        window.CaroLiveArena = {
            onWin: function (wCode, wName) {}
        };

        // KHỞI ĐỘNG TRẬN ĐẤU ĐẦU TIÊN
        updateTurnUI();
        setTimeout(scheduleNextMove, 1200);

    })();
    </script>
</body>
</html>
