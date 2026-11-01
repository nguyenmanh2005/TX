<?php
/**
 * â™Ÿï¸ VÃµ ÄÃ i Cá» Ca-rÃ´ AI LiveStream (BÃ n 66) â€” Bot vs Bot 24/7
 * Tráº­n Ä‘áº¡i chiáº¿n siÃªu trÃ­ tuá»‡ nhÃ¢n táº¡o: Master AlphaX (âŒ) vs Grandmaster DeepO (â­•)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/bot_streamer_helper.php';

$botUserX = getOrCreateBotStreamerUser($conn, 'bot_61', 50000000);
$moneyX = (float)($botUserX['Money'] ?? 50000000);

if (isset($_GET['action']) && $_GET['action'] === 'record_round') {
    header('Content-Type: application/json; charset=utf-8');
    $winner = $_GET['winner'] ?? 'X';
    $pot = 1000000;
    // Cáº­p nháº­t sá»‘ dÆ° tÆ°á»£ng trÆ°ng cho Bot Streamer
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
    <title>â™Ÿï¸ Äáº¡i Chiáº¿n Cá» Ca-rÃ´ AI (AlphaX vs DeepO) - Live BÃ n 66</title>
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
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            overflow-x: hidden;
        }

        /* ðŸ‘‘ SÃ‚N Äáº¤U HEADER */
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
            font-family: 'Orbitron', sans-serif;
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
            font-family: 'Orbitron', sans-serif;
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

        /* â™Ÿï¸ BÃ€N Cá»œ CA-RÃ” CHUáº¨N 15x15 */
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

        /* Tá»a Ä‘á»™ cá» */
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
            background: rgba(255, 255, 255, 0.025);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 900;
            cursor: pointer;
            transition: background 0.15s ease;
            position: relative;
        }
        .caro-cell:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        /* Äiá»ƒm sao thiÃªn nguyÃªn (H8, D4, L4, D12, L12) */
        .caro-cell.star-point::after {
            content: '';
            width: 4px;
            height: 4px;
            background: rgba(251, 191, 36, 0.4);
            border-radius: 50%;
            position: absolute;
        }

        /* QuÃ¢n cá» X */
        .caro-cell.piece-x {
            color: var(--red-neon);
            text-shadow: 0 0 10px rgba(239, 68, 68, 0.9);
            animation: dropPiece 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        /* QuÃ¢n cá» O */
        .caro-cell.piece-o {
            color: var(--blue-neon);
            text-shadow: 0 0 10px rgba(56, 189, 248, 0.9);
            animation: dropPiece 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes dropPiece {
            0% { transform: scale(1.6); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* NÆ°á»›c cá» vá»«a háº¡ (Last move aura) */
        .caro-cell.last-move {
            border-color: var(--gold-neon) !important;
            box-shadow: 0 0 10px var(--gold-neon), inset 0 0 8px rgba(251, 191, 36, 0.4);
        }

        /* 5 quÃ¢n tháº¯ng cuá»™c sÃ¡ng rá»±c */
        .caro-cell.win-cell {
            background: rgba(251, 191, 36, 0.35) !important;
            border-color: #f59e0b !important;
            box-shadow: 0 0 20px #fbbf24, inset 0 0 10px #fbbf24 !important;
            animation: winPulse 0.8s infinite alternate !important;
            z-index: 10;
        }
        @keyframes winPulse {
            0% { transform: scale(1); filter: brightness(1); }
            100% { transform: scale(1.12); filter: brightness(1.5); }
        }

        /* ðŸ¤– VIRTUAL BOT CURSORS TRÃŠN BÃ€N Cá»œ */
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

        /* ðŸ“œ NHáº¬T KÃ NÆ¯á»šC ÄI & FOOTER STATUS */
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

        /* ðŸ† VICTORY POPUP OVERLAY */
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
            font-family: 'Orbitron', sans-serif;
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

    <!-- ðŸ‘‘ SÃ‚N Äáº¤U HEADER: BOT ALPHAX VS BOT DEEPO -->
    <div class="arena-header">
        <!-- BOT 1: ALPHAX -->
        <div class="bot-card bot-x active-turn" id="botCardX">
            <div class="bot-avatar">
                <i class="fa-solid fa-robot"></i>
            </div>
            <div class="bot-info">
                <div class="bot-name">
                    <span>AlphaX</span>
                    <span style="color:var(--red-neon); font-size:1.1rem;">âŒ</span>
                </div>
                <div class="bot-status">
                    <span id="statusTextX">ðŸ”´ Äang suy nghÄ©...</span>
                </div>
                <div class="bot-score">
                    ðŸ† <span id="scoreX">0</span> VÃ¡n Tháº¯ng
                </div>
            </div>
        </div>

        <!-- VS & POT -->
        <div class="vs-center">
            <div class="vs-badge">VS</div>
            <div class="pot-badge">
                ðŸ’° 1.000.000 GTLM
            </div>
            <div class="turn-timer" id="turnTimerText">
                â±ï¸ NÆ°á»›c Ä‘i: <span id="moveCount">0</span>
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
                    <span style="color:var(--blue-neon); font-size:1.1rem;">â­•</span>
                </div>
                <div class="bot-status">
                    <span id="statusTextO">âšª Chá» lÆ°á»£t</span>
                </div>
                <div class="bot-score">
                    ðŸ† <span id="scoreO">0</span> VÃ¡n Tháº¯ng
                </div>
            </div>
        </div>
    </div>

    <!-- â™Ÿï¸ BÃ€N Cá»œ CA-RÃ” 15x15 Äá»ˆNH CAO -->
    <div class="board-wrapper" id="boardWrapper">
        <!-- Con trá» áº£o chuyá»ƒn Ä‘á»™ng -->
        <div class="bot-cursor cursor-x" id="cursorX"><i class="fa-solid fa-arrow-pointer"></i></div>
        <div class="bot-cursor cursor-o" id="cursorO"><i class="fa-solid fa-arrow-pointer"></i></div>

        <!-- Tá»a Ä‘á»™ chá»¯ A - O -->
        <div class="coord-letters">
            <span>A</span><span>B</span><span>C</span><span>D</span><span>E</span>
            <span>F</span><span>G</span><span>H</span><span>I</span><span>J</span>
            <span>K</span><span>L</span><span>M</span><span>N</span><span>O</span>
        </div>

        <!-- LÆ°á»›i cá» 15x15 kÃ¨m tá»a Ä‘á»™ sá»‘ 1-15 -->
        <div class="coord-row">
            <div class="coord-num" style="display:flex; flex-direction:column; justify-content:space-between; height:418px; padding:6px 0;">
                <?php for ($i = 1; $i <= 15; $i++) echo "<span>$i</span>"; ?>
            </div>
            <div class="caro-grid" id="caroGrid"></div>
            <div class="coord-num" style="display:flex; flex-direction:column; justify-content:space-between; height:418px; padding:6px 0;">
                <?php for ($i = 1; $i <= 15; $i++) echo "<span>$i</span>"; ?>
            </div>
        </div>

        <!-- Tá»a Ä‘á»™ chá»¯ A - O Ä‘Ã¡y -->
        <div class="coord-letters" style="margin-top:4px; margin-bottom:0;">
            <span>A</span><span>B</span><span>C</span><span>D</span><span>E</span>
            <span>F</span><span>G</span><span>H</span><span>I</span><span>J</span>
            <span>K</span><span>L</span><span>M</span><span>N</span><span>O</span>
        </div>

        <!-- VICTORY OVERLAY BANNER -->
        <div class="victory-overlay" id="victoryOverlay">
            <div class="victory-title" id="victoryTitle">ðŸŽ‰ CHIáº¾N THáº®NG TUYá»†T Äá»I!</div>
            <div class="victory-sub" id="victorySub">Master AlphaX chá»‘t háº¡ 5 quÃ¢n liÃªn tiáº¿p (+1.000.000 GTLM)</div>
            <div class="victory-next">ðŸ”„ VÃ¡n má»›i sáº½ tá»± Ä‘á»™ng báº¯t Ä‘áº§u sau <b id="countdownNext">4</b>s...</div>
        </div>
    </div>

    <!-- ðŸ“œ NHáº¬T KÃ NÆ¯á»šC ÄI & ÄIá»€U KHIá»‚N KHÃN GIáº¢ -->
    <div class="arena-footer">
        <div class="log-ticker" id="logTicker">
            âš¡ Tráº­n Ä‘áº¥u cá» Ca-rÃ´ AI 15x15 trá»±c tiáº¿p Ä‘ang diá»…n ra ká»‹ch tÃ­nh!
        </div>
        <div class="arena-controls">
            <button class="btn-ctrl" id="btnSpeed" title="Thay Ä‘á»•i tá»‘c Ä‘á»™ bot">âš¡ 1x</button>
            <button class="btn-ctrl" id="btnSound" title="Báº­t/Táº¯t Ã¢m thanh">ðŸ”Š Ã‚m</button>
            <button class="btn-ctrl" id="btnRestart" title="Báº¯t Ä‘áº§u vÃ¡n má»›i ngay">ðŸ”„ Äá»•i VÃ¡n</button>
        </div>
    </div>

    <!-- ThÆ° viá»‡n bot chat vÃ  ká»‹ch báº£n Ä‘áº¥u trÃ­ -->
    <script src="../assets/js/bot_chat.js"></script>
    <script src="bots/bot_61.js?v=<?= time() ?>"></script>

    <script>
    (function () {
        'use strict';

        // ðŸŽµ SYNTHESIZER AUDIO (KhÃ´ng phá»¥ thuá»™c file ngoÃ i)
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

        // â™Ÿï¸ CARO ENGINE CONFIGURATION
        const SIZE = 15;
        let board = Array.from({ length: SIZE }, () => Array(SIZE).fill(0));
        let currentTurn = 1; // 1: X (AlphaX), 2: O (DeepO)
        let firstTurnNext = 2; // LuÃ¢n phiÃªn lÆ°á»£t má»Ÿ mÃ n
        let isGameOver = false;
        let isThinking = false;
        let moveCount = 0;
        let scoreX = 0;
        let scoreO = 0;
        let turnDelay = 1600; // ms per turn (1x mode)

        const LETTERS = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O'];
        const STAR_POINTS = [[7,7], [3,3], [11,3], [3,11], [11,11]]; // H8, D4, L4, D12, L12

        // Khá»Ÿi táº¡o lÆ°á»›i HTML 15x15
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

        // Cáº­p nháº­t giao diá»‡n tráº¡ng thÃ¡i 2 Bot
        function updateTurnUI() {
            $('#moveCount').text(moveCount);
            if (currentTurn === 1) {
                $('#botCardX').addClass('active-turn');
                $('#botCardO').removeClass('active-turn');
                $('#statusTextX').html('ðŸ”´ Äang suy nghÄ©...');
                $('#statusTextO').html('âšª Chá» Ä‘á»‘i thá»§');
            } else {
                $('#botCardO').addClass('active-turn');
                $('#botCardX').removeClass('active-turn');
                $('#statusTextO').html('ðŸ”µ Äang tÃ­nh nÆ°á»›c...');
                $('#statusTextX').html('âšª Chá» Ä‘á»‘i thá»§');
            }
        }

        // Äáº¶T QUÃ‚N Cá»œ
        function placePiece(r, c, player) {
            board[r][c] = player;
            moveCount++;
            const cell = getCellEl(r, c);
            if (!cell) return;

            $('.caro-cell').removeClass('last-move');
            cell.classList.add('last-move');

            if (player === 1) {
                cell.classList.add('piece-x');
                cell.textContent = 'âŒ';
            } else {
                cell.classList.add('piece-o');
                cell.textContent = 'â­•';
            }

            playStoneClick();

            const coordName = LETTERS[c] + (r + 1);
            const botName = (player === 1) ? 'ðŸ”´ AlphaX' : 'ðŸ”µ DeepO';
            $('#logTicker').html(`<b>${botName}</b> háº¡ quÃ¢n táº¡i Ã´ <b>[${coordName}]</b> â€” NÆ°á»›c thá»© ${moveCount}`);

            // Kiá»ƒm tra chiáº¿n tháº¯ng
            const winLine = checkWin(r, c, player);
            if (winLine) {
                handleVictory(player, winLine);
                return;
            }

            // Kiá»ƒm tra hÃ²a cá» (háº¿t Ã´)
            if (moveCount >= SIZE * SIZE) {
                handleDraw();
                return;
            }

            // Chuyá»ƒn lÆ°á»£t
            currentTurn = (currentTurn === 1) ? 2 : 1;
            updateTurnUI();
            scheduleNextMove();
        }

        // KIá»‚M TRA 5 QUÃ‚N LIá»€N HÃ€NG
        const DIRECTIONS = [
            [[0, 1], [0, -1]],   // Ngang
            [[1, 0], [-1, 0]],   // Dá»c
            [[1, 1], [-1, -1]],  // ChÃ©o chÃ­nh
            [[1, -1], [-1, 1]]   // ChÃ©o phá»¥
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

        // Xá»¬ LÃ Káº¾T THÃšC VÃN Äáº¤U
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
                $('#victoryTitle').html('ðŸ”´ MASTER ALPHAX CHIáº¾N THáº®NG!');
                $('#victorySub').html(`NgÅ© Tá»­ Liá»n HÃ ng chá»‘t háº¡ vÃ¡n Ä‘áº¥u thá»© ${scoreX + scoreO}! (+1.000.000 GTLM)`);
            } else {
                scoreO++;
                $('#scoreO').text(scoreO);
                $('#victoryTitle').html('ðŸ”µ GRANDMASTER DEEPO TOÃ€N THáº®NG!');
                $('#victorySub').html(`KhÃ³a báº«y Ã©p gÃ³c pháº£n cÃ´ng ngoáº¡n má»¥c! (+1.000.000 GTLM)`);
            }

            // Báº¯n tÃ­n hiá»‡u sang BotChat
            if (window.CaroLiveArena && window.CaroLiveArena.onWin) {
                window.CaroLiveArena.onWin(winner, winnerName);
            }

            $('#victoryOverlay').addClass('show');

            // Cáº­p nháº­t sá»‘ dÆ° tÆ°á»£ng trÆ°ng
            $.get('live_62.php?action=record_round&winner=' + (winner === 1 ? 'X' : 'O'));

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
            $('#victoryTitle').html('ðŸ¤ BÃ€N Cá»œ Báº¤T PHÃ‚N THáº®NG Báº I!');
            $('#victorySub').html('225 Ã´ cá» Ä‘Ã£ kÃ­n, hai Ä‘áº¡i sÆ° ká»³ phÃ¹ng Ä‘á»‹ch thá»§!');
            $('#victoryOverlay').addClass('show');
            setTimeout(startNewGame, 3500);
        }

        // ðŸ§  AI HEURISTIC ENGINE CHO Cáº¢ 2 BOT
        function evaluateCell(r, c, player) {
            const opp = (player === 1) ? 2 : 1;
            let myScore = getPatternScore(r, c, player);
            let oppScore = getPatternScore(r, c, opp);

            // AlphaX thiÃªn vá» cÃ´ng (1.3x cÃ´ng), DeepO thiÃªn vá» thá»§ / pháº£n cÃ´ng (1.3x thá»§)
            const attackWeight = (player === 1) ? 1.3 : 1.1;
            const defenseWeight = (player === 1) ? 1.05 : 1.3;

            // Äiá»ƒm trá»ng sá»‘ trung tÃ¢m bÃ n cá»
            const distFromCenter = Math.abs(r - 7) + Math.abs(c - 7);
            const centerBonus = Math.max(0, 16 - distFromCenter);

            // ChÃºt ngáº«u nhiÃªn Ä‘á»ƒ cÃ¡c tráº­n biáº¿n hÃ³a
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
            // NÆ°á»›c Ä‘i Ä‘áº§u tiÃªn: Ä‘Ã¡nh vÃ o trung tÃ¢m
            if (moveCount === 0) {
                return [7, 7];
            }
            // NÆ°á»›c Ä‘i thá»© 2: Ä‘Ã¡nh gáº§n trung tÃ¢m
            if (moveCount === 1) {
                const candidates = [[6,6], [6,7], [6,8], [7,6], [7,8], [8,6], [8,7], [8,8]];
                return candidates[Math.floor(Math.random() * candidates.length)];
            }

            let bestScore = -1;
            let bestMoves = [];

            for (let r = 0; r < SIZE; r++) {
                for (let c = 0; c < SIZE; c++) {
                    if (board[r][c] !== 0) continue;

                    // Tá»‘i Æ°u: Chá»‰ xÃ©t cÃ¡c Ã´ gáº§n quÃ¢n Ä‘Ã£ Ä‘Ã¡nh (trong bÃ¡n kÃ­nh 2 Ã´)
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
                // Fallback náº¿u khÃ´ng cÃ³ Ã´ gáº§n
                for (let r = 0; r < SIZE; r++) {
                    for (let c = 0; c < SIZE; c++) {
                        if (board[r][c] === 0) return [r, c];
                    }
                }
            }

            // Chá»n ngáº«u nhiÃªn trong cÃ¡c nÆ°á»›c Ä‘iá»ƒm cao nháº¥t
            return bestMoves[Math.floor(Math.random() * bestMoves.length)];
        }

        // Láº¬P Lá»ŠCH NÆ¯á»šC ÄI Cá»¦A BOT
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
                    const bOffset = $('#boardWrapper').position();

                    // Di chuyá»ƒn chuá»™t áº£o Ä‘áº¿n Ã´ cá»
                    cursorEl.addClass('active');
                    cursorEl.css({
                        top: (offset.top + 13) + 'px',
                        left: (offset.left + 13) + 'px'
                    });

                    // Cáº£nh bÃ¡o náº¿u cÃ³ nÆ°á»›c 3 hoáº·c 4
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

        // Báº®T Äáº¦U VÃN Má»šI
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
            $('#logTicker').html(`âœ¨ VÃ¡n má»›i khá»Ÿi tranh! <b>${currentTurn === 1 ? 'ðŸ”´ AlphaX' : 'ðŸ”µ DeepO'}</b> giá»¯ quyá»n TiÃªn thá»§!`);

            scheduleNextMove();
        }

        // ÄIá»€U KHIá»‚N KHÃN GIáº¢
        $('#btnSpeed').on('click', function () {
            if (turnDelay === 1600) {
                turnDelay = 800;
                $(this).text('âš¡ 2x').css('color', '#fbbf24');
            } else if (turnDelay === 800) {
                turnDelay = 400;
                $(this).text('ðŸš€ Turbo').css('color', '#ef4444');
            } else {
                turnDelay = 1600;
                $(this).text('âš¡ 1x').css('color', '#fff');
            }
        });

        $('#btnSound').on('click', function () {
            soundEnabled = !soundEnabled;
            $(this).text(soundEnabled ? 'ðŸ”Š Ã‚m' : 'ðŸ”‡ Táº¯t');
        });

        $('#btnRestart').on('click', function () {
            startNewGame();
        });

        // Báº­t Ã¢m thanh trÃªn tÆ°Æ¡ng tÃ¡c Ä‘áº§u tiÃªn
        document.addEventListener('click', () => { initAudio(); }, { once: true });

        // Cáº§u ná»‘i giao tiáº¿p vá»›i bot_61.js
        window.CaroLiveArena = {
            onWin: function (wCode, wName) {}
        };

        // KHá»žI Äá»˜NG TRáº¬N Äáº¤U Äáº¦U TIÃŠN
        updateTurnUI();
        setTimeout(scheduleNextMove, 1200);

    })();
    </script>
</body>
</html>

