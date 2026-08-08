<?php
/**
 * ⚔️ PvP Arena - Đấu Trường Trực Tuyến (All-in-One)
 * Gộp pvp_arena.php + pvp_play.php thành 1 trang duy nhất.
 * Luồng: Đấu trường VS → Đếm ngược → Chọn lựa game → Kết quả
 */
require_once 'db_connect.php';
require_once 'admin_helper.php';
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit;
}

$userId      = $_SESSION['Iduser'];
$challengeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isAdmin     = isAdmin($conn, $userId);

if ($challengeId <= 0) {
    header("Location: pvp_challenge.php");
    exit;
}

// Lấy thông tin trận đấu
if ($isAdmin) {
    $stmt = $conn->prepare("
        SELECT c.*,
               u1.Name as challenger_name, u1.ImageURL as challenger_avatar,
               u2.Name as challenged_name, u2.ImageURL as challenged_avatar
        FROM pvp_challenges c
        JOIN users u1 ON c.challenger_id = u1.Iduser
        JOIN users u2 ON c.opponent_id   = u2.Iduser
        WHERE c.id = ?
    ");
    $stmt->bind_param("i", $challengeId);
} else {
    $stmt = $conn->prepare("
        SELECT c.*,
               u1.Name as challenger_name, u1.ImageURL as challenger_avatar,
               u2.Name as challenged_name, u2.ImageURL as challenged_avatar
        FROM pvp_challenges c
        JOIN users u1 ON c.challenger_id = u1.Iduser
        JOIN users u2 ON c.opponent_id   = u2.Iduser
        WHERE c.id = ? AND (c.challenger_id = ? OR c.opponent_id = ?)
    ");
    $stmt->bind_param("iii", $challengeId, $userId, $userId);
}

$stmt->execute();
$match = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$match) {
    header("Location: pvp_challenge.php");
    exit;
}

// Caro: chuyển sang trang riêng
if ($match['game_type'] === 'caro') {
    header("Location: pvp_caro.php?id=" . $challengeId);
    exit;
}

$isChallenger = ($userId == $match['challenger_id']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚔️ Đấu Trường PvP | <?= htmlspecialchars($match['challenger_name']) ?> vs <?= htmlspecialchars($match['challenged_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:     #020617;
            --primary:#ef4444;
            --blue:   #3b82f6;
            --gold:   #fbbf24;
            --green:  #10b981;
            --purple: #8b5cf6;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            background: var(--bg);
            color: #fff;
            font-family: 'Inter', sans-serif;
            margin: 0;
            overflow: hidden;
            height: 100vh;
        }

        /* ════════════════════════════════
           🏟️  ARENA CONTAINER
        ════════════════════════════════ */
        .arena-container {
            width: 100vw; height: 100vh;
            background: radial-gradient(circle at center, #1e1b4b 0%, #020617 100%);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            position: relative;
        }

        /* Bet header */
        .bet-header {
            position: absolute; top: 30px;
            text-align: center; z-index: 10;
        }
        .bet-label { font-size: 12px; letter-spacing: 3px; opacity: 0.6; text-transform: uppercase; }
        .bet-amount { font-family: 'Bangers', cursive; font-size: 36px; color: var(--gold);
                      text-shadow: 0 0 15px rgba(251,191,36,.6); }

        /* ════ Fighter cards ════ */
        .duel-view {
            display: flex; align-items: center; gap: 80px;
            z-index: 10; transition: all .5s;
        }
        .fighter { text-align: center; transition: transform .5s cubic-bezier(.175,.885,.32,1.275); }
        .fighter-avatar {
            width: 160px; height: 160px; border-radius: 50%;
            border: 6px solid var(--blue);
            box-shadow: 0 0 30px rgba(59,130,246,.5);
            background: #1e293b; object-fit: cover; margin-bottom: 16px;
        }
        .fighter.opponent .fighter-avatar {
            border-color: var(--primary);
            box-shadow: 0 0 30px rgba(239,68,68,.5);
        }
        .fighter-name { font-family: 'Bangers', cursive; font-size: 28px; letter-spacing: 2px; }
        .fighter-choice-display { font-size: 28px; margin-top: 10px; min-height: 36px; transition: all .3s; }
        .status-badge {
            font-size: 13px; padding: 4px 14px; border-radius: 20px;
            background: rgba(0,0,0,.5); margin-top: 10px; display: inline-block;
        }
        .status-online  { color: var(--green);  border: 1px solid var(--green); }
        .status-waiting { color: #94a3b8;        border: 1px solid #94a3b8; }
        .status-done    { color: var(--gold);    border: 1px solid var(--gold); }

        .vs-logo {
            font-family: 'Bangers', cursive; font-size: 80px; color: var(--gold);
            text-shadow: 0 0 20px rgba(251,191,36,.8);
            animation: pulse 1s infinite alternate;
        }
        @keyframes pulse { from { transform: scale(1); } to { transform: scale(1.1); } }

        /* ════ Countdown overlay ════ */
        #countdown-overlay {
            position: absolute; inset: 0;
            background: rgba(0,0,0,.85);
            display: none; flex-direction: column;
            align-items: center; justify-content: center;
            z-index: 100;
        }
        .countdown-label { font-size: 18px; letter-spacing: 8px; opacity: .7; margin-bottom: 10px; }
        #countdown-number {
            font-family: 'Bangers', cursive; font-size: 160px; color: #fff;
            text-shadow: 0 0 40px rgba(255,255,255,.5);
            animation: countPulse .9s ease-out;
        }
        @keyframes countPulse {
            0%   { transform: scale(1.4); opacity: .5; }
            100% { transform: scale(1);   opacity: 1; }
        }

        /* ════ Game choice panel ════ */
        #game-panel {
            position: absolute; inset: 0;
            background: rgba(2,6,23,.92);
            display: none; flex-direction: column;
            align-items: center; justify-content: center;
            z-index: 90; gap: 30px;
            animation: fadeIn .4s ease-out;
        }
        @keyframes fadeIn { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }

        .game-panel-header {
            font-family: 'Bangers', cursive; font-size: 32px;
            letter-spacing: 3px; color: var(--gold);
        }

        /* Mini fighter row inside game panel */
        .mini-duel {
            display: flex; align-items: center; gap: 40px; margin-bottom: 5px;
        }
        .mini-fighter { text-align: center; }
        .mini-avatar {
            width: 70px; height: 70px; border-radius: 50%;
            border: 3px solid var(--blue); object-fit: cover;
        }
        .mini-fighter.opp .mini-avatar { border-color: var(--primary); }
        .mini-fighter-name { font-size: 13px; margin-top: 6px; font-weight: 600; }
        .mini-vs { font-family: 'Bangers', cursive; font-size: 36px; color: var(--gold); }

        /* Choice buttons */
        .choice-grid {
            display: flex; flex-wrap: wrap; gap: 16px; justify-content: center;
            max-width: 600px;
        }
        .choice-btn {
            padding: 18px 32px; font-size: 22px; font-weight: 700;
            border: 3px solid rgba(255,255,255,.2); border-radius: 16px;
            background: rgba(255,255,255,.05); color: #fff; cursor: pointer;
            transition: all .25s; min-width: 130px;
        }
        .choice-btn:hover:not(:disabled) {
            border-color: var(--gold); background: rgba(251,191,36,.15);
            transform: scale(1.08); box-shadow: 0 0 20px rgba(251,191,36,.3);
        }
        .choice-btn:disabled { opacity: .4; cursor: not-allowed; }
        .choice-btn.selected {
            border-color: var(--gold); background: rgba(251,191,36,.25);
            box-shadow: 0 0 20px rgba(251,191,36,.4);
        }

        /* Number input */
        .number-input {
            padding: 14px 20px; font-size: 28px; width: 160px;
            text-align: center; border-radius: 12px;
            border: 3px solid var(--gold); background: rgba(255,255,255,.05);
            color: #fff; outline: none;
        }
        .number-input:focus { box-shadow: 0 0 15px rgba(251,191,36,.4); }

        /* Waiting message */
        .wait-msg {
            padding: 20px 36px; border-radius: 14px;
            background: rgba(251,191,36,.12); border: 1px solid rgba(251,191,36,.3);
            font-size: 18px; color: var(--gold); text-align: center;
            animation: blink 2s ease-in-out infinite;
        }
        @keyframes blink { 0%,100%{opacity:1;} 50%{opacity:.5;} }

        /* ════ Slash FX & Game FX ════ */
        .slash-fx {
            position: absolute; inset: 0;
            pointer-events: none; z-index: 50; display: none;
            justify-content: center; align-items: center;
        }
        .slash {
            position: absolute; width: 400px; height: 8px; background: #fff;
            filter: blur(3px); box-shadow: 0 0 20px #fff;
            animation: slashAnim .5s forwards;
        }
        @keyframes slashAnim {
            0%   { transform: scaleX(0) rotate(45deg); opacity:1; }
            100% { transform: scaleX(2) rotate(45deg); opacity:0; }
        }
        @keyframes flipCoin { 0% { transform: rotateY(0deg) scale(1); } 50% { transform: rotateY(360deg) scale(1.5); } 100% { transform: rotateY(720deg) scale(1); } }
        @keyframes rollDice { 0% { transform: rotate(0deg) scale(1); } 50% { transform: rotate(180deg) scale(1.3); } 100% { transform: rotate(360deg) scale(1); } }
        @keyframes shakeRps { 0%, 100% { transform: translateY(0) rotate(0); } 25% { transform: translateY(-30px) rotate(-15deg); } 75% { transform: translateY(30px) rotate(15deg); } }
        @keyframes spinNum { 0% { transform: scale(1); filter: hue-rotate(0deg); } 50% { transform: scale(1.4); filter: hue-rotate(180deg); } 100% { transform: scale(1); filter: hue-rotate(360deg); } }

        /* ════ Result screen ════ */
        #result-screen {
            position: absolute; inset: 0;
            background: radial-gradient(circle, rgba(99,102,241,.92) 0%, rgba(2,6,23,.97) 100%);
            display: none; flex-direction: column;
            align-items: center; justify-content: center;
            z-index: 200; animation: zoomIn .5s ease-out;
        }
        @keyframes zoomIn { from{opacity:0;transform:scale(.5);} to{opacity:1;transform:scale(1);} }

        .result-title {
            font-family: 'Bangers', cursive; font-size: 90px;
            color: var(--gold); text-shadow: 0 0 30px rgba(251,191,36,.8);
        }
        .result-choices {
            display: flex; gap: 60px; margin: 24px 0;
            font-size: 36px; align-items: center;
        }
        .result-choice-box { text-align: center; }
        .result-choice-label { font-size: 13px; opacity: .6; letter-spacing: 2px; margin-bottom: 6px; }
        .result-reward { font-size: 28px; font-weight: 900; }
        .result-reward.win  { color: var(--green); }
        .result-reward.lose { color: var(--primary); }
        .result-reward.draw { color: var(--gold); }

        .result-actions { display: flex; gap: 16px; margin-top: 36px; }
        .btn-return {
            padding: 14px 36px; border-radius: 30px; border: none;
            background: #fff; color: #000; font-weight: 900; cursor: pointer;
            font-size: 16px; transition: transform .2s;
        }
        .btn-return:hover { transform: scale(1.05); }
        .btn-arena {
            padding: 14px 36px; border-radius: 30px; border: 2px solid var(--gold);
            background: transparent; color: var(--gold); font-weight: 900; cursor: pointer;
            font-size: 16px; transition: all .2s;
        }
        .btn-arena:hover { background: rgba(251,191,36,.15); transform: scale(1.05); }

        /* ════ Admin panel ════ */
        #admin-panel {
            position: absolute; bottom: 24px; left: 50%; transform: translateX(-50%);
            background: rgba(15,23,42,.95); border: 2px solid var(--gold);
            padding: 14px 28px; border-radius: 20px;
            box-shadow: 0 0 25px rgba(251,191,36,.4);
            text-align: center; z-index: 150; display: flex; gap: 14px;
            align-items: center; backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>
<div class="arena-container">

    <!-- 💰 Bet header -->
    <div class="bet-header">
        <div class="bet-label">Giá Trị GTLM Chiến</div>
        <div class="bet-amount"><?= number_format($match['bet_amount']) ?>GTLM</div>
    </div>

    <!-- ⚔️ Màn hình đấu trường VS -->
    <div class="duel-view" id="duelView">
        <!-- Fighter 1 (Challenger) -->
        <div class="fighter <?= !$isChallenger ? 'opponent' : '' ?>" id="fighter-1">
            <img src="<?= htmlspecialchars($match['challenger_avatar'] ?: 'images.ico') ?>" class="fighter-avatar" alt="" onerror="this.onerror=null; this.src='images.ico'">
            <div class="fighter-name"><?= htmlspecialchars($match['challenger_name']) ?></div>
            <div class="fighter-choice-display" id="choice-display-1"></div>
            <div class="status-badge <?= $isChallenger ? 'status-online' : 'status-waiting' ?>" id="status-1">
                <?= $isChallenger ? 'SẴN SÀNG' : 'ĐANG CHỜ...' ?>
            </div>
        </div>

        <div class="vs-logo">VS</div>

        <!-- Fighter 2 (Challenged/Opponent) -->
        <div class="fighter <?= $isChallenger ? 'opponent' : '' ?>" id="fighter-2">
            <img src="<?= htmlspecialchars($match['challenged_avatar'] ?: 'images.ico') ?>" class="fighter-avatar" alt="" onerror="this.onerror=null; this.src='images.ico'">
            <div class="fighter-name"><?= htmlspecialchars($match['challenged_name']) ?></div>
            <div class="fighter-choice-display" id="choice-display-2"></div>
            <div class="status-badge <?= !$isChallenger ? 'status-online' : 'status-waiting' ?>" id="status-2">
                <?= !$isChallenger ? 'SẴN SÀNG' : 'ĐANG CHỜ...' ?>
            </div>
        </div>
    </div>

    <!-- ⏲️ Countdown overlay -->
    <div id="countdown-overlay">
        <div class="countdown-label">TRẬN ĐẤU BẮT ĐẦU SAU</div>
        <div id="countdown-number">3</div>
    </div>

    <!-- 🎮 Game choice panel (hiện sau countdown) -->
    <div id="game-panel">
        <div class="game-panel-header">⚔️ CHỌN LỰA CỦA BẠN</div>

        <!-- Mini fighters row -->
        <div class="mini-duel">
            <div class="mini-fighter <?= !$isChallenger ? 'opp' : '' ?>">
                <img src="<?= htmlspecialchars($match['challenger_avatar'] ?: 'images.ico') ?>" class="mini-avatar" alt="" onerror="this.onerror=null; this.src='images.ico'">
                <div class="mini-fighter-name"><?= htmlspecialchars($match['challenger_name']) ?></div>
            </div>
            <div class="mini-vs">VS</div>
            <div class="mini-fighter <?= $isChallenger ? 'opp' : '' ?>">
                <img src="<?= htmlspecialchars($match['challenged_avatar'] ?: 'images.ico') ?>" class="mini-avatar" alt="" onerror="this.onerror=null; this.src='images.ico'">
                <div class="mini-fighter-name"><?= htmlspecialchars($match['challenged_name']) ?></div>
            </div>
        </div>

        <!-- Nội dung game (render bởi JS) -->
        <div id="game-choices-area"></div>

        <!-- Trạng thái chờ -->
        <div id="wait-area" style="display:none;"></div>
    </div>

    <!-- 💥 Slash effects -->
    <div class="slash-fx" id="slashFx">
        <div class="slash" style="top:42%; left:25%; transform:rotate(-45deg);"></div>
        <div class="slash" style="top:58%; left:45%; transform:rotate(20deg);"></div>
    </div>

    <!-- 🏁 Result screen -->
    <div id="result-screen">
        <div class="result-title" id="resultTitle">CHIẾN THẮNG</div>
        <div class="result-choices" id="resultChoices"></div>
        <div class="result-reward" id="resultReward"></div>
        <div class="result-actions">
            <button class="btn-return" onclick="location.href='pvp_challenge.php'">← Về Thách Đấu</button>
            <button class="btn-arena"  onclick="location.href='index.php'">🏠 Trang Chủ</button>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <!-- ⚡ Admin panel -->
    <div id="admin-panel">
        <div style="font-size:13px; font-weight:bold; color:var(--gold); letter-spacing:1px;">
            <i class="fas fa-shield-alt"></i> QUẢN TRỊ VIÊN:
        </div>
        <button style="background:linear-gradient(135deg,#ef4444,#b91c1c); padding:9px 16px; border-radius:10px; color:#fff; font-weight:bold; border:none; cursor:pointer;" onclick="adminCancelMatch()">❌ HỦY TRẬN</button>
        <button style="background:linear-gradient(135deg,#3b82f6,#1d4ed8); padding:9px 16px; border-radius:10px; color:#fff; font-weight:bold; border:none; cursor:pointer;" onclick="adminForceResult(<?= (int)$match['challenger_id'] ?>, '<?= htmlspecialchars($match['challenger_name']) ?>')">⚡ XỬ THẮNG P1</button>
        <button style="background:linear-gradient(135deg,#3b82f6,#1d4ed8); padding:9px 16px; border-radius:10px; color:#fff; font-weight:bold; border:none; cursor:pointer;" onclick="adminForceResult(<?= (int)$match['opponent_id'] ?>, '<?= htmlspecialchars($match['challenged_name']) ?>')">⚡ XỬ THẮNG P2</button>
    </div>
    <?php endif; ?>

</div><!-- .arena-container -->

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
/* ══════════════════════════════════════════════════════════
   CONFIG
══════════════════════════════════════════════════════════ */
const matchId      = <?= $challengeId ?>;
const userId       = <?= $userId ?>;
const gameType     = <?= json_encode($match['game_type']) ?>;
const isChallenger = <?= $isChallenger ? 'true' : 'false' ?>;

const CHALLENGER_NAME   = <?= json_encode($match['challenger_name']) ?>;
const CHALLENGED_NAME   = <?= json_encode($match['challenged_name']) ?>;

let isStarted     = false;   // countdown đã bắt đầu?
let myChoice      = null;    // lựa chọn của mình
let choiceSubmitted = false; // đã nộp chưa?
let resultPolling  = null;   // interval chờ kết quả

/* ══════════════════════════════════════════════════════════
   PHASE 1: ARENA – chờ đối thủ online
══════════════════════════════════════════════════════════ */
const opponentStatusId = isChallenger ? 'status-2' : 'status-1';

async function syncState() {
    if (isStarted) return;

    try {
        const res  = await fetch(`api_pvp.php?action=sync&id=${matchId}`);
        const data = await res.json();

        const opponentStatus = document.getElementById(opponentStatusId);

        if (data.opponent_online) {
            opponentStatus.textContent = 'SẴN SÀNG';
            opponentStatus.className   = 'status-badge status-online';

            const okStatuses = ['accepted','fighting','finished','completed'];
            if (okStatuses.includes(data.status)) {
                startCountdown();
            }
        } else {
            opponentStatus.textContent = 'ĐANG CHỜ...';
            opponentStatus.className   = 'status-badge status-waiting';
        }
    } catch(e) {}
}

/* ══════════════════════════════════════════════════════════
   PHASE 2: COUNTDOWN
══════════════════════════════════════════════════════════ */
function startCountdown() {
    if (isStarted) return;
    isStarted = true;

    const overlay = document.getElementById('countdown-overlay');
    const numEl   = document.getElementById('countdown-number');
    overlay.style.display = 'flex';

    let count = 3;
    const timer = setInterval(() => {
        count--;
        numEl.textContent = count;
        // reset animation
        numEl.style.animation = 'none';
        void numEl.offsetWidth;
        numEl.style.animation = 'countPulse .9s ease-out';

        if (count <= 0) {
            clearInterval(timer);
            overlay.style.display = 'none';
            showGamePanel();
        }
    }, 1000);
}

/* ══════════════════════════════════════════════════════════
   PHASE 3: GAME CHOICE PANEL
══════════════════════════════════════════════════════════ */
function showGamePanel() {
    // Kiểm tra trước xem đã có choice chưa (reload page giữa chừng)
    loadCurrentState();
}

async function loadCurrentState() {
    try {
        const res  = await fetch(`api_pvp_challenge.php?action=get_challenge&challenge_id=${matchId}`);
        const data = await res.json();
        if (!data.success) { renderGameChoices(); return; }

        const ch = data.challenge;

        // Đã có kết quả?
        if (ch.status === 'completed' || ch.status === 'finished') {
            showArenaResult(ch);
            return;
        }

        const myChoiceVal = isChallenger ? ch.challenger_choice : ch.opponent_choice;
        const oppChoiceVal = isChallenger ? ch.opponent_choice   : ch.challenger_choice;

        if (myChoiceVal) {
            myChoice = myChoiceVal;
            choiceSubmitted = true;
        }

        document.getElementById('game-panel').style.display = 'flex';

        if (myChoiceVal && !oppChoiceVal) {
            // Đã nộp, chờ đối thủ
            showWaitingOpponent(myChoiceVal);
            startResultPolling();
        } else if (myChoiceVal && oppChoiceVal) {
            // Cả 2 đã nộp
            startResultPolling();
        } else {
            // Chưa nộp
            renderGameChoices();
        }
    } catch(e) {
        renderGameChoices();
    }
}

function renderGameChoices() {
    document.getElementById('game-panel').style.display = 'flex';
    const area = document.getElementById('game-choices-area');

    if (gameType === 'coinflip') {
        area.innerHTML = `
            <div style="text-align:center; margin-bottom:10px; font-size:17px; opacity:.7;">🪙 Chọn Ngửa hoặc Sấp:</div>
            <div class="choice-grid">
                <button class="choice-btn" id="btn-heads" onclick="submitChoice('heads')">🪙 Ngửa</button>
                <button class="choice-btn" id="btn-tails" onclick="submitChoice('tails')">🪙 Sấp</button>
            </div>`;
    } else if (gameType === 'dice') {
        area.innerHTML = `
            <div style="text-align:center; margin-bottom:10px; font-size:17px; opacity:.7;">🎲 Chọn số xúc xắc (1-6):</div>
            <div class="choice-grid">
                ${[1,2,3,4,5,6].map(n => `<button class="choice-btn" id="btn-${n}" onclick="submitChoice('${n}')">🎲 ${n}</button>`).join('')}
            </div>`;
    } else if (gameType === 'rps') {
        area.innerHTML = `
            <div style="text-align:center; margin-bottom:10px; font-size:17px; opacity:.7;">✊ Oẳn Tù Tì:</div>
            <div class="choice-grid">
                <button class="choice-btn" id="btn-rock"     onclick="submitChoice('rock')">✊ Đá</button>
                <button class="choice-btn" id="btn-paper"    onclick="submitChoice('paper')">✋ Giấy</button>
                <button class="choice-btn" id="btn-scissors" onclick="submitChoice('scissors')">✌️ Kéo</button>
            </div>`;
    } else if (gameType === 'number') {
        area.innerHTML = `
            <div style="text-align:center; margin-bottom:10px; font-size:17px; opacity:.7;">🔢 Đoán số từ 1–100 (ai gần hơn thắng):</div>
            <div style="display:flex; flex-direction:column; align-items:center; gap:16px;">
                <input type="number" id="numberInput" class="number-input" min="1" max="100" placeholder="1-100">
                <button class="choice-btn" onclick="submitNumberChoice()" style="min-width:180px;">✅ Xác Nhận</button>
            </div>`;
    } else {
        area.innerHTML = `<div class="wait-msg">Loại game không hỗ trợ hiển thị inline.</div>`;
    }
}

function showWaitingOpponent(myChoiceVal) {
    document.getElementById('game-choices-area').innerHTML = '';
    const waitArea = document.getElementById('wait-area');
    waitArea.style.display = 'block';
    waitArea.innerHTML = `
        <div class="wait-msg">
            Lựa chọn của bạn: <strong>${formatChoice(myChoiceVal)}</strong><br>
            ⏳ Đang chờ đối thủ nộp lựa chọn...
        </div>`;
    // Cập nhật status badge
    const myStatusId = isChallenger ? 'status-1' : 'status-2';
    const myStatus   = document.getElementById(myStatusId);
    if (myStatus) { myStatus.textContent = 'ĐÃ CHỌN ✓'; myStatus.className = 'status-badge status-done'; }
}

/* ══════════════════════════════════════════════════════════
   SUBMIT CHOICE
══════════════════════════════════════════════════════════ */
async function submitChoice(choice) {
    if (choiceSubmitted) return;
    choiceSubmitted = true;
    myChoice = choice;

    // Highlight button
    document.querySelectorAll('.choice-btn').forEach(b => b.disabled = true);
    const btn = document.getElementById('btn-' + choice);
    if (btn) btn.classList.add('selected');

    try {
        const fd = new FormData();
        fd.append('action', 'submit_choice');
        fd.append('challenge_id', matchId);
        fd.append('choice', choice);

        const res  = await fetch('api_pvp_challenge.php', { method:'POST', body:fd, credentials:'same-origin' });
        const data = await res.json();

        if (!data.success) {
            Swal.fire('Lỗi', data.message || 'Không thể nộp lựa chọn!', 'error');
            choiceSubmitted = false;
            document.querySelectorAll('.choice-btn').forEach(b => b.disabled = false);
            return;
        }

        if (data.both_submitted) {
            // Cả 2 đã nộp → hiệu ứng chiến đấu
            performBattleAnimation();
        } else {
            showWaitingOpponent(choice);
            startResultPolling();
        }
    } catch(e) {
        choiceSubmitted = false;
        document.querySelectorAll('.choice-btn').forEach(b => b.disabled = false);
    }
}

function submitNumberChoice() {
    const val = document.getElementById('numberInput').value;
    if (!val || val < 1 || val > 100) {
        Swal.fire('Thông báo', 'Vui lòng nhập số từ 1-100!', 'warning');
        return;
    }
    submitChoice(String(val));
}

/* ══════════════════════════════════════════════════════════
   PHASE 4: BATTLE ANIMATION & RESULT
══════════════════════════════════════════════════════════ */
function performBattleAnimation() {
    if (document.getElementById('result-screen').style.display === 'flex') return;

    document.getElementById('game-panel').style.display  = 'none';
    document.getElementById('duelView').style.display    = 'flex';
    const slashFx = document.getElementById('slashFx');
    
    // Tùy chỉnh hiệu ứng theo game
    if (gameType === 'coinflip') {
        slashFx.innerHTML = '<div style="font-size:120px; animation: flipCoin 1.8s ease-in-out;">🪙</div>';
    } else if (gameType === 'dice') {
        slashFx.innerHTML = '<div style="font-size:120px; animation: rollDice 1.8s ease-in-out;">🎲</div>';
    } else if (gameType === 'rps') {
        slashFx.innerHTML = '<div style="font-size:120px; animation: shakeRps 0.6s ease-in-out 3;">✊</div>';
    } else if (gameType === 'number') {
        slashFx.innerHTML = '<div style="font-size:120px; animation: spinNum 1.8s ease-in-out;">🔢</div>';
    } else {
        slashFx.innerHTML = '<div class="slash"></div>';
    }
    
    slashFx.style.display = 'flex';

    document.getElementById('fighter-1').style.transform = 'translateX(80px)';
    document.getElementById('fighter-2').style.transform = 'translateX(-80px)';

    setTimeout(async () => {
        slashFx.style.display = 'none';
        document.getElementById('fighter-1').style.transform = 'translateX(0)';
        document.getElementById('fighter-2').style.transform = 'translateX(0)';

        // Lấy kết quả từ API challenge
        try {
            const res  = await fetch(`api_pvp_challenge.php?action=get_challenge&challenge_id=${matchId}`);
            const data = await res.json();
            if (data.success) showArenaResult(data.challenge);
        } catch(e) {}
    }, 1800);
}

function startResultPolling() {
    if (resultPolling) return;
    resultPolling = setInterval(async () => {
        try {
            const res  = await fetch(`api_pvp_challenge.php?action=get_challenge&challenge_id=${matchId}`);
            const data = await res.json();
            if (!data.success) return;
            const ch = data.challenge;
            
            if (ch.status === 'cancelled') {
                clearInterval(resultPolling);
                Swal.fire('Đã Hủy!', 'Trận đấu này đã bị quản trị viên hủy!', 'info').then(() => location.href = 'pvp_challenge.php');
                return;
            }

            if (ch.status === 'completed' || ch.result) {
                clearInterval(resultPolling);
                performBattleAnimation();
            }
        } catch(e) {}
    }, 2000);
}

function showArenaResult(ch) {
    clearInterval(resultPolling);

    const screen  = document.getElementById('result-screen');
    const titleEl = document.getElementById('resultTitle');
    const rewardEl= document.getElementById('resultReward');
    const choicesEl = document.getElementById('resultChoices');

    document.getElementById('game-panel').style.display = 'none';
    document.getElementById('slashFx').style.display    = 'none';

    // Các lựa chọn
    const c1 = formatChoice(ch.challenger_choice);
    const c2 = formatChoice(ch.opponent_choice);
    choicesEl.innerHTML = `
        <div class="result-choice-box">
            <div class="result-choice-label">${CHALLENGER_NAME.toUpperCase()}</div>
            <div>${c1 || '?'}</div>
        </div>
        <div style="font-size:28px; opacity:.5; align-self:center;">⚔️</div>
        <div class="result-choice-box">
            <div class="result-choice-label">${CHALLENGED_NAME.toUpperCase()}</div>
            <div>${c2 || '?'}</div>
        </div>
    `;

    const isWinner = (ch.winner_id == userId);
    const isDraw   = (ch.result === 'draw');

    if (isDraw) {
        titleEl.textContent  = '🤝 HÒA!';
        titleEl.style.color  = 'var(--gold)';
        rewardEl.textContent = 'GTLM được hoàn lại';
        rewardEl.className   = 'result-reward draw';
    } else if (isWinner) {
        titleEl.textContent  = '🏆 CHIẾN THẮNG!';
        titleEl.style.color  = 'var(--gold)';
        rewardEl.innerHTML   = `+${Number(ch.bet_amount * 2).toLocaleString('vi-VN')} GTLM`;
        rewardEl.className   = 'result-reward win';
    } else {
        titleEl.textContent  = '💀 THẤT BẠI!';
        titleEl.style.color  = 'var(--primary)';
        rewardEl.innerHTML   = `-${Number(ch.bet_amount).toLocaleString('vi-VN')} GTLM`;
        rewardEl.className   = 'result-reward lose';
    }

    screen.style.display = 'flex';
}

/* ══════════════════════════════════════════════════════════
   HELPERS
══════════════════════════════════════════════════════════ */
function formatChoice(choice) {
    if (!choice) return '';
    if (gameType === 'coinflip') return choice === 'heads' ? '🪙 Ngửa' : '🪙 Sấp';
    if (gameType === 'dice')     return `🎲 ${choice}`;
    if (gameType === 'rps') {
        return { rock:'✊ Đá', paper:'✋ Giấy', scissors:'✌️ Kéo' }[choice] || choice;
    }
    if (gameType === 'number') return `🔢 ${choice}`;
    return choice;
}

/* ══════════════════════════════════════════════════════════
   ADMIN FUNCTIONS
══════════════════════════════════════════════════════════ */
<?php if ($isAdmin): ?>
function adminCancelMatch() {
    Swal.fire({
        title: 'Xác nhận hủy?',
        text: 'Trận đấu sẽ bị hủy và GTLM cược sẽ hoàn trả lại cho cả 2!',
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#ef4444', confirmButtonText: 'Đồng ý hủy', cancelButtonText: 'Không'
    }).then(r => {
        if (!r.isConfirmed) return;
        fetch(`api_pvp.php?action=admin_cancel&id=${matchId}`)
            .then(r => r.json())
            .then(d => {
                if (d.success) Swal.fire('Thành công', d.message, 'success').then(() => location.reload());
                else           Swal.fire('Lỗi', d.message, 'error');
            });
    });
}

function adminForceResult(winnerId, winnerName) {
    Swal.fire({
        title: 'Xử thắng cuộc?',
        text: `Bạn có chắc muốn xử thắng cho [${winnerName}]?`,
        icon: 'question', showCancelButton: true,
        confirmButtonColor: '#3b82f6', confirmButtonText: 'Đồng ý', cancelButtonText: 'Không'
    }).then(r => {
        if (!r.isConfirmed) return;
        fetch(`api_pvp.php?action=admin_force_result&id=${matchId}&winner_id=${winnerId}`)
            .then(r => r.json())
            .then(d => {
                if (d.success) Swal.fire('Thành công', d.message, 'success').then(() => location.reload());
                else           Swal.fire('Lỗi', d.message, 'error');
            });
    });
}
<?php endif; ?>

/* ══════════════════════════════════════════════════════════
   BOOT
══════════════════════════════════════════════════════════ */
// Bắt đầu polling trạng thái arena
const syncInterval = setInterval(syncState, 2000);
syncState(); // gọi ngay lập tức

// Global check cho việc admin hủy trận đấu hoặc ép kết quả (dành cho người chưa nộp)
const globalCheckInterval = setInterval(async () => {
    try {
        const res  = await fetch(`api_pvp_challenge.php?action=get_challenge&challenge_id=${matchId}`);
        const data = await res.json();
        if (data.success && data.challenge) {
            const st = data.challenge.status;
            if (st === 'cancelled') {
                clearInterval(globalCheckInterval);
                Swal.fire('Đã Hủy!', 'Trận đấu này đã bị quản trị viên hủy hoặc ép kết thúc!', 'info').then(() => location.href = 'pvp_challenge.php');
            } else if (st === 'completed' || st === 'finished') {
                // Nếu trận đấu đã kết thúc bởi admin mà người dùng chưa nộp
                if (!choiceSubmitted && document.getElementById('result-screen').style.display !== 'flex') {
                    clearInterval(globalCheckInterval);
                    showArenaResult(data.challenge);
                }
            }
        }
    } catch(e) {}
}, 2500);

window.addEventListener('beforeunload', () => {
    clearInterval(syncInterval);
    clearInterval(globalCheckInterval);
    if (resultPolling) clearInterval(resultPolling);
});
</script>
</body>
</html>
