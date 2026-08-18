<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_jojo_battle', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;




require '../db_connect.php';

if (isset($_GET['action'])) {
    $bypassThemeScripts = true;
}

require_once '../load_theme.php';

$userId = $botUserId;
$sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();





$money = $user['Money'];
$userName = $user['Name'];

// --- AJAX ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'place_bet') {
        $chon = $_POST['chon'] ?? '';
        $cuoc = (int) ($_POST['cuoc'] ?? 0);
        $roundId = (int) ($_POST['round_id'] ?? 0);

        if ($cuoc > $money || $cuoc <= 0) {
            echo json_encode(['success' => false, 'message' => '⚠️ Số Gtlm không đủ!']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO bets (user_id, round_id, chosen_character, amount) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisd", $userId, $roundId, $chon, $cuoc);
        if ($stmt->execute()) {
            $newMoney = $money - $cuoc;
            $conn->query("UPDATE users SET Money = $newMoney WHERE Iduser = $userId");
        
        // Insert vào history_vq table
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($botUserId)) {
            $userId = $botUserId;
            $betAmount = (int)($_POST['bet'] ?? 0);
            $resultStr = $_POST['result'] ?? 'Unknown';
            $winAmount = (int)($reward ?? 0);
            
            $historyStmt = $conn->prepare("INSERT INTO history_vq (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
            if ($historyStmt) {
                $historyStmt->bind_param("iisi", $userId, $betAmount, $resultStr, $winAmount);
                $historyStmt->execute();
                $historyStmt->close();
            }
        }
            echo json_encode(['success' => true, 'newBalance' => number_format($newMoney) . ' gtlm']);
        } else {
            echo json_encode(['success' => false, 'message' => '❌ Lỗi hệ thống!']);
        }
        exit;
    }

    if ($action === 'get_result') {
        $roundId = (int) ($_POST['round_id'] ?? 0);
        $winner = rand(0, 1) ? "JoJo" : "Dio";

        $stmt = $conn->prepare("INSERT INTO rounds (round_id, winner) VALUES (?, ?)");
        $stmt->bind_param("is", $roundId, $winner);
        $stmt->execute();

        $stmt = $conn->prepare("SELECT chosen_character, amount FROM bets WHERE round_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $roundId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();

        $totalWin = 0;
        $totalBet = 0;
        while ($bet = $res->fetch_assoc()) {
            $totalBet += $bet['amount'];
            if ($bet['chosen_character'] === $winner)
                $totalWin += $bet['amount'] * 2;
        }

        if ($totalWin > 0)
            $conn->query("UPDATE users SET Money = Money + $totalWin WHERE Iduser = $userId");

        if (file_exists('../game_history_helper.php')) {
            require_once '../game_history_helper.php';
            logGameHistoryWithAll($conn, $userId, 'Vòng Quay JoJo', $totalBet, $totalWin, $totalWin > 0);
        }

        $stmt = $conn->query("SELECT Money FROM users WHERE Iduser = $userId");
        $newBalance = $stmt->fetch_assoc()['Money'];

        echo json_encode(['success' => true, 'winner' => $winner, 'winAmount' => $totalWin, 'newBalance' => number_format($newBalance) . ' gtlm', 'message' => ($totalWin > 0) ? "⭐ VICTORY!" : "💀 RETIRED!"]);
        exit;
    }
}

$roundId = time();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>JOJO ULTIMATE: PREMIUM EDITION</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Cinzel:wght@900&family=Bangers&family=Noto+Sans+JP:wght@900&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --jojo: #00d4ff;
            --dio: #f5c832;
            --gold: #f5c832;
            --dark: #080010;
        }

        body {
            margin: 0;
            background: var(--dark) !important;
            color: white;
            font-family: 'Poppins', sans-serif;
            overflow: hidden;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .money-badge {
            background: rgba(0, 0, 0, 0.8);
            padding: 10px 30px;
            border-radius: 50px;
            border: 2px solid var(--gold);
            color: var(--gold);
            font-weight: 900;
        }

        .theatre {
            flex: 1;
            margin: 20px;
            background: rgba(10, 5, 25, 0.8);
            backdrop-filter: blur(20px);
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .hp-arena {
            display: flex;
            gap: 50px;
            padding: 40px;
        }

        .hp-slot {
            flex: 1;
            height: 30px;
            background: #222;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }

        .hp-fill {
            height: 100%;
            transition: width 0.3s;
        }

        .hp-j {
            background: var(--jojo);
            box-shadow: 0 0 20px var(--jojo);
        }

        .hp-d {
            background: var(--dio);
            box-shadow: 0 0 20px var(--dio);
        }

        .combat-stage {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 100px;
        }

        .fighter {
            width: 300px;
            text-align: center;
        }

        .fighter img {
            width: 100%;
            filter: drop-shadow(0 0 30px #000);
        }

        .vs-logo {
            font-family: 'Cinzel';
            font-size: 80px;
            font-weight: 900;
        }

        .control-ui {
            padding: 30px;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .duel-btn {
            background: var(--gold);
            color: #000;
            padding: 15px 80px;
            border-radius: 40px;
            font-weight: 900;
            font-size: 24px;
            border: none;
            cursor: pointer;
            font-family: 'Cinzel';
        }

        .card {
            padding: 20px 40px;
            border: 2px solid #444;
            border-radius: 20px;
            cursor: pointer;
            transition: 0.3s;
        }

        .card.active {
            border-color: var(--gold);
            background: rgba(245, 200, 50, 0.1);
        }

        /* ── FX OVERLAY (dùng để append rush-word / impact thay vì combat-stage) ── */
        #fx-canvas {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 1000;
            overflow: hidden;
        }

        /* ── COMBAT STAGE: cần position relative để GSAP hoạt động đúng ── */
        .combat-stage {
            position: relative; /* BUG FIX: thiếu cái này khiến absolute con bị lệch */
        }

        /* ── ORA ORA / MUDA MUDA ── */
        .rush-word {
            position: fixed; /* fixed thay vì absolute → tọa độ % theo viewport, không bị ảnh hưởng bởi GSAP trên .combat-stage */
            font-family: 'Bebas Neue', 'Bangers', sans-serif;
            font-size: clamp(36px, 7vw, 80px);
            letter-spacing: 0.05em;
            pointer-events: none;
            opacity: 0;
            animation: rushWord 0.4s ease-out forwards;
            z-index: 9999;
            will-change: transform, opacity;
        }
        @keyframes rushWord {
            0%   { opacity: 1; transform: scale(0.4) rotate(var(--r)); filter: blur(4px); }
            40%  { opacity: 1; transform: scale(1.15) rotate(var(--r)); filter: blur(0); }
            70%  { opacity: 0.9; transform: scale(1.0) rotate(var(--r)); }
            100% { opacity: 0; transform: scale(1.4) rotate(var(--r)); filter: blur(2px); }
        }

        /* ── PUNCH IMPACT ── */
        .impact {
            position: fixed; /* fixed cùng lý do rush-word */
            width: 100px; height: 100px;
            border-radius: 50%;
            transform: translate(-50%, -50%) scale(0);
            animation: impactPop 0.45s ease-out forwards;
            pointer-events: none;
            z-index: 9998;
        }
        @keyframes impactPop {
            0%   { transform: translate(-50%,-50%) scale(0); opacity: 1; }
            50%  { transform: translate(-50%,-50%) scale(1.3); opacity: 0.9; }
            100% { transform: translate(-50%,-50%) scale(2.2); opacity: 0; }
        }

        /* ── SCREEN FLASH ── */
        .battle-flash {
            position: fixed;
            inset: 0;
            background: white;
            pointer-events: none;
            z-index: 8000;
            opacity: 0;
            animation: battleFlash 0.15s ease-out forwards;
        }
        @keyframes battleFlash {
            0%   { opacity: 0.7; }
            100% { opacity: 0; }
        }

        /* ── HP BAR drain đỏ ── */
        .hp-fill {
            transition: width 1s cubic-bezier(0.25, 0, 0.5, 1);
        }
        .hp-fill.draining {
            background: #ff2222 !important;
            box-shadow: 0 0 20px #ff0000 !important;
            transition: width 1.2s cubic-bezier(0.25, 0, 0.2, 1);
        }

        /* ── LOSER SHAKE ── */
        .fighter.loser-shake {
            animation: loserShake 0.5s ease forwards;
        }
        @keyframes loserShake {
            0%,100% { transform: translateX(0) rotate(0); }
            15%  { transform: translateX(-12px) rotate(-3deg); }
            30%  { transform: translateX(12px) rotate(3deg); }
            45%  { transform: translateX(-8px) rotate(-2deg); }
            60%  { transform: translateX(8px) rotate(2deg); }
            75%  { transform: translateX(-4px); }
        }

        /* ── SKILL ANIMATIONS ── */
        .knife { position:fixed; pointer-events:none; opacity:0; z-index:9999; animation: knifeThrow 0.55s cubic-bezier(0.2,0,0.6,1) forwards; }
        @keyframes knifeThrow {
            0%  { opacity:1; transform:translate(0,0) rotate(var(--rot)); }
            100%{ opacity:0.2; transform:translate(var(--tx),var(--ty)) rotate(calc(var(--rot) + 360deg)) scale(0.4); }
        }

        .star-finger-beam {
            position:fixed; height:6px; border-radius:3px; z-index:9999;
            background:linear-gradient(90deg,transparent,#00d4ff,white);
            transform-origin:left center; opacity:0;
            animation: beamShot 0.5s ease-out forwards;
        }
        @keyframes beamShot {
            0%  { opacity:1; width:0; transform: rotate(var(--rot, 0deg)) scaleY(1); }
            50% { opacity:1; width:var(--len); transform: rotate(var(--rot, 0deg)) scaleY(1); }
            100%{ opacity:0; width:var(--len); transform: rotate(var(--rot, 0deg)) scaleY(3); }
        }

        .time-stop-overlay {
            position:fixed; inset:0; background:rgba(80,0,180,0.0);
            pointer-events:none; opacity:0; z-index:9000;
            animation: timeStopAnim 2.5s ease forwards;
        }
        @keyframes timeStopAnim {
            0%  { opacity:0; filter:invert(0) saturate(1); }
            10% { opacity:1; background:rgba(80,0,180,0.6); filter:invert(1) saturate(2); }
            25% { opacity:1; background:rgba(20,0,60,0.85); filter:invert(0) saturate(0) brightness(0.4); }
            80% { opacity:1; background:rgba(20,0,60,0.85); filter:invert(0) saturate(0) brightness(0.4); }
            100%{ opacity:0; background:rgba(80,0,180,0); filter:invert(0) saturate(1); }
        }

        .time-stop-text {
            position:fixed; font-family:'Bebas Neue', 'Bangers', sans-serif;
            font-size: clamp(36px,7vw,90px); letter-spacing:0.15em; color:#fff;
            text-shadow:0 0 40px currentColor; top:50%; left:50%; z-index:9001;
            transform:translate(-50%,-50%) scale(0); opacity:0;
            animation: timeStopText 2.5s ease forwards; white-space:nowrap;
        }
        @keyframes timeStopText {
            0%  { opacity:0; transform:translate(-50%,-50%) scale(0.3); }
            15% { opacity:1; transform:translate(-50%,-50%) scale(1.15); }
            25% { opacity:1; transform:translate(-50%,-50%) scale(1); }
            80% { opacity:1; transform:translate(-50%,-50%) scale(1); }
            100%{ opacity:0; transform:translate(-50%,-50%) scale(1.3); }
        }

        .road-roller {
            position:fixed; left:50%; top:-200px; z-index:9999;
            transform:translateX(-50%); opacity:0;
            animation: rollerDrop 2s cubic-bezier(0.4,0,1,1) forwards;
        }
        @keyframes rollerDrop {
            0%  { top:-200px; opacity:0; }
            10% { opacity:1; }
            55% { top:30%; }
            60% { top:32%; }
            65% { top:28%; }
            70% { top:31%; }
            85% { top:30%; opacity:1; }
            100%{ top:30%; opacity:0; }
        }

        .wryyy-text {
            position:fixed; font-family:'Bebas Neue', 'Bangers', sans-serif;
            font-size:clamp(50px,12vw,130px); letter-spacing:0.1em; color:#f5c832;
            text-shadow:0 0 60px #ff4400, 0 0 120px #ff4400; top:50%; left:50%; z-index:9001;
            transform:translate(-50%,-50%) scale(0) rotate(-8deg); opacity:0;
            animation: wryyyy 2s ease forwards;
        }
        @keyframes wryyyy {
            0%  { opacity:0; transform:translate(-50%,-50%) scale(0) rotate(-8deg); }
            20% { opacity:1; transform:translate(-50%,-50%) scale(1.3) rotate(-8deg); }
            35% { opacity:1; transform:translate(-50%,-50%) scale(1) rotate(0deg); }
            75% { opacity:1; transform:translate(-50%,-50%) scale(1) rotate(0deg); }
            100%{ opacity:0; transform:translate(-50%,-50%) scale(1.5) rotate(5deg); }
        }
        .wryyy-bg {
            position:fixed; inset:0; background:rgba(80,0,0,0); z-index:9000;
            animation: wryBg 2s ease forwards; pointer-events:none;
        }
        @keyframes wryBg {
            0%  { background:rgba(80,0,0,0); }
            20% { background:rgba(80,0,0,0.5); }
            75% { background:rgba(40,0,0,0.35); }
            100%{ background:rgba(80,0,0,0); }
        }
        .wryyy-particles .p {
            position:fixed; width:8px; height:8px; border-radius:50%; z-index:9001;
            background:#f5c832; animation: wryPart 1.5s ease-out forwards;
        }
        @keyframes wryPart {
            0%  { transform:translate(0,0) scale(1); opacity:1; }
            100%{ transform:translate(var(--px),var(--py)) scale(0); opacity:0; }
        }

        .star-breaker-flash {
            position:fixed; inset:0; background:white; z-index:9000; pointer-events:none;
            opacity:0; animation: breaKerFlash 0.8s ease forwards;
        }
        @keyframes breaKerFlash {
            0%  { opacity:0; }
            5%  { opacity:1; }
            15% { opacity:0.6; }
            30% { opacity:0; }
            35% { opacity:0.8; filter:invert(1); }
            50% { opacity:0; filter:invert(0); }
            100%{ opacity:0; }
        }
        .star-breaker-text {
            position:fixed; font-family:'Bebas Neue', 'Bangers', sans-serif; z-index:9001;
            font-size:clamp(44px,9vw,110px); letter-spacing:0.1em; color:#fff;
            text-shadow:0 0 30px #00d4ff, 0 0 80px #00d4ff; top:50%; left:50%;
            transform:translate(-50%,-50%) scale(0); opacity:0;
            animation: sbText 1s ease forwards; white-space:nowrap; pointer-events:none;
        }
        @keyframes sbText {
            0%  { opacity:0; transform:translate(-50%,-50%) scale(3); }
            20% { opacity:1; transform:translate(-50%,-50%) scale(0.9); }
            60% { opacity:1; transform:translate(-50%,-50%) scale(1); }
            100%{ opacity:0; transform:translate(-50%,-50%) scale(1.1); }
        }
        @keyframes loserShake {
            0%,100% { transform: translateX(0) rotate(0); }
            15%  { transform: translateX(-12px) rotate(-3deg); }
            30%  { transform: translateX(12px) rotate(3deg); }
            45%  { transform: translateX(-8px) rotate(-2deg); }
            60%  { transform: translateX(8px) rotate(2deg); }
            75%  { transform: translateX(-4px); }
        }

        /* ── HAMON AURA (Jotaro passive) ── */
        .hamon-ring {
            position:fixed;
            border-radius:50%;
            border: 3px solid rgba(0,212,255,0.8);
            box-shadow: 0 0 20px #00d4ff, inset 0 0 20px rgba(0,212,255,0.2);
            transform:translate(-50%,-50%) scale(0);
            opacity:0;
            animation: hamonExpand 0.8s ease-out forwards;
            pointer-events:none;
            z-index:9000;
        }
        @keyframes hamonExpand {
            0%  { transform:translate(-50%,-50%) scale(0); opacity:0.9; }
            60% { transform:translate(-50%,-50%) scale(1); opacity:0.7; }
            100%{ transform:translate(-50%,-50%) scale(1.6); opacity:0; }
        }
        .hamon-text {
            position:fixed; font-family:'Bebas Neue', 'Bangers', sans-serif;
            font-size:clamp(40px,8vw,100px); letter-spacing:0.15em; color:#00d4ff;
            text-shadow:0 0 40px #00d4ff, 0 0 80px #00aaff;
            top:50%; left:50%; transform:translate(-50%,-50%) scale(0);
            opacity:0; animation: hamonText 1.8s ease forwards;
            white-space:nowrap; z-index:9001;
        }
        @keyframes hamonText {
            0%  { opacity:0; transform:translate(-50%,-50%) scale(0.4); }
            20% { opacity:1; transform:translate(-50%,-50%) scale(1.1); }
            50% { opacity:1; transform:translate(-50%,-50%) scale(1); }
            80% { opacity:0.7; transform:translate(-50%,-50%) scale(1); }
            100%{ opacity:0; transform:translate(-50%,-50%) scale(1.2); }
        }
        .hamon-bg {
            position:fixed; inset:0; background:rgba(0,0,0,0);
            animation: hamonBg 1.8s ease forwards; z-index:8999;
        }
        @keyframes hamonBg {
            0%  { background:rgba(0,30,60,0); }
            25% { background:rgba(0,30,80,0.5); }
            75% { background:rgba(0,20,50,0.3); }
            100%{ background:rgba(0,30,60,0); }
        }

        /* ── VAMPIRE REGEN (Dio passive) ── */
        .vamp-text {
            position:fixed; font-family:'Bebas Neue', 'Bangers', sans-serif;
            font-size:clamp(40px,8vw,100px); letter-spacing:0.15em; color:#f5c832;
            text-shadow:0 0 40px #ff4400, 0 0 80px #cc2200;
            top:45%; left:50%; transform:translate(-50%,-50%) scale(0) rotate(-5deg);
            opacity:0; animation: vampText 1.8s ease forwards;
            white-space:nowrap; z-index:9001;
        }
        @keyframes vampText {
            0%  { opacity:0; transform:translate(-50%,-50%) scale(0.4) rotate(-5deg); }
            20% { opacity:1; transform:translate(-50%,-50%) scale(1.1) rotate(-5deg); }
            50% { opacity:1; transform:translate(-50%,-50%) scale(1) rotate(0deg); }
            80% { opacity:0.8; transform:translate(-50%,-50%) scale(1) rotate(0deg); }
            100%{ opacity:0; transform:translate(-50%,-50%) scale(1.2) rotate(3deg); }
        }
        .vamp-bg {
            position:fixed; inset:0; animation: vampBg 1.8s ease forwards; z-index:8999;
        }
        @keyframes vampBg {
            0%  { background:rgba(60,0,0,0); }
            25% { background:rgba(80,0,0,0.55); }
            75% { background:rgba(40,0,0,0.35); }
            100%{ background:rgba(60,0,0,0); }
        }
        .blood-drop {
            position:fixed; width:10px; height:14px;
            border-radius:50% 50% 50% 50% / 60% 60% 40% 40%;
            background:#cc0000; box-shadow:0 0 8px #ff2200;
            animation: bloodFall 1.2s ease-in forwards; z-index:9002;
        }
        @keyframes bloodFall {
            0%  { transform:translateY(0) scale(1); opacity:1; }
            100%{ transform:translateY(180px) scale(0.4); opacity:0; }
        }
        .vamp-subtitle {
            position:fixed; font-family:'Bebas Neue', 'Bangers', sans-serif;
            font-size:clamp(18px,3vw,32px); letter-spacing:0.25em; color:#ff6644;
            top:58%; left:50%; transform:translate(-50%,-50%);
            opacity:0; animation: vampSub 1.8s ease forwards;
            white-space:nowrap; z-index:9001;
        }
        @keyframes vampSub {
            0%,20%{ opacity:0; }
            40%  { opacity:1; }
            80%  { opacity:0.9; }
            100% { opacity:0; }
        }

        /* ── RETIRED & ASH EFFECTS ── */
        .retired-text {
            position: fixed;
            font-family: 'Bebas Neue', 'Bangers', sans-serif;
            font-size: clamp(60px, 15vw, 150px);
            color: #d32f2f;
            text-shadow: 3px 3px 0 #000, -3px -3px 0 #000, 3px -3px 0 #000, -3px 3px 0 #000, 0 0 50px #ff0000;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) scale(3) rotate(-10deg);
            opacity: 0;
            z-index: 9999;
            pointer-events: none;
            white-space: nowrap;
        }

        .retired-anim {
            animation: stampRetired 1s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        @keyframes stampRetired {
            0% { opacity: 0; transform: translate(-50%, -50%) scale(3) rotate(-15deg); filter: brightness(2); }
            20% { opacity: 1; transform: translate(-50%, -50%) scale(0.9) rotate(-5deg); filter: brightness(1); }
            40% { opacity: 1; transform: translate(-50%, -50%) scale(1) rotate(-5deg); }
            100% { opacity: 1; transform: translate(-50%, -50%) scale(1.1) rotate(-5deg); }
        }

        .ash-effect {
            animation: toAshes 2s ease forwards !important;
        }

        @keyframes toAshes {
            0% { filter: brightness(1) sepia(0); opacity: 1; transform: translateY(0); }
            20% { filter: brightness(0) sepia(1) hue-rotate(-50deg) saturate(5); opacity: 1; transform: translateY(0) scale(1.05); }
            100% { filter: brightness(0) sepia(1) hue-rotate(-50deg) saturate(5) blur(6px); opacity: 0; transform: translateY(-80px) scale(0.9); }
        }

        .chip {
            width: auto;
            min-width: 50px;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-weight: 700;
            font-size: 0.8rem;
            cursor: pointer;
            transition: 0.2s;
            font-family: inherit;
        }
        .chip:hover {
            background: rgba(245, 200, 50, 0.2);
            border-color: #f5c832;
            color: #f5c832;
        }
    </style>
</head>

<body>
    <div id="fx-canvas"></div>
    <div
        style="padding: 20px; display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.5);">
        <a href="../index.php" style="color: white; text-decoration: none; font-weight: 900;">🏠 TRANG CHỦ</a>
        <div class="money-badge">💰 <span id="money-val"><?= number_format($money) ?> gtlm</span></div>
    </div>

    <div class="theatre">
        <div class="hp-arena">
            <div class="hp-slot">
                <div class="hp-fill hp-d" id="hp-d" style="width: 100%;"></div>
            </div>
            <div class="vs-logo" style="font-size: 20px;">VS</div>
            <div class="hp-slot">
                <div class="hp-fill hp-j" id="hp-j" style="width: 100%;"></div>
            </div>
        </div>
        <div class="combat-stage">
            <div class="fighter" id="f-dio"><img src="img/dio.png"></div>
            <div class="vs-logo">VS</div>
            <div class="fighter" id="f-jojo"><img src="img/jotaro.png"></div>
        </div>
        <div class="control-ui">
            <div style="display: flex; gap: 20px;">
                <div class="card" onclick="pick('Dio', this)">DIO</div>
                <div class="card" onclick="pick('JoJo', this)">JOTARO</div>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; margin-top: 10px; margin-bottom: 5px; max-width: 500px;">
                <button type="button" class="chip" onclick="document.getElementById('cuoc').value=1000">1K GTLM</button>
                <button type="button" class="chip" onclick="document.getElementById('cuoc').value=5000">5K GTLM</button>
                <button type="button" class="chip" onclick="document.getElementById('cuoc').value=10000">10K GTLM</button>
                <button type="button" class="chip" onclick="document.getElementById('cuoc').value=50000">50K GTLM</button>
                <button type="button" class="chip" onclick="document.getElementById('cuoc').value=100000">100K GTLM</button>
                <button type="button" class="chip" onclick="document.getElementById('cuoc').value=500000">500K GTLM</button>
                <button type="button" class="chip" onclick="document.getElementById('cuoc').value=1000000">1M GTLM</button>
                <button type="button" class="chip" onclick="document.getElementById('cuoc').value=5000000">5M GTLM</button>
                <button type="button" class="chip" onclick="document.getElementById('cuoc').value=2000000">2M GTLM</button>
                <button type="button" class="chip" onclick="document.getElementById('cuoc').value=<?=$money?>">MAX</button>
            </div>
            <input type="number" id="cuoc" value="10000"
                style="background: #111; color: #fff; border: 1px solid #444; padding: 10px; border-radius: 10px; text-align: center; width: 200px;">
            <button class="duel-btn" onclick="startDuel()" id="btn-duel">KÍCH HOẠT!</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let selected = null;
        let active = false;

        function pick(s, el) {
            if (active) return;
            selected = s;
            document.querySelectorAll('.card').forEach(c => c.classList.remove('active'));
            el.classList.add('active');
        }

        /* ── Tiện ích ── */
        const fxCanvas = document.getElementById('fx-canvas');

        function rnd(a, b) { return a + Math.random() * (b - a); }

        /** Thêm flash trắng toàn màn hình */
        function addFlash() {
            const f = document.createElement('div');
            f.className = 'battle-flash';
            fxCanvas.appendChild(f);
            setTimeout(() => f.remove(), 200);
        }

        /**
         * Spawn rush words (ORA hoặc MUDA) lên fx-canvas (fixed overlay)
         * leftZone=true → chữ phía JoJo (bên phải màn hình)
         * leftZone=false → chữ phía Dio (bên trái màn hình)
         */
        function spawnRushWord(text, color, leftZone) {
            const el = document.createElement('div');
            el.className = 'rush-word';
            // ORA bên phải (JoJo), MUDA bên trái (Dio) — không đè nhau
            const xMin = leftZone ? 10 : 55;
            const xMax = leftZone ? 45 : 88;
            const x = rnd(xMin, xMax);
            const y = rnd(20, 70);
            const r = rnd(-28, 28);
            el.style.cssText = `left:${x}%;top:${y}%;color:${color};--r:${r}deg;text-shadow:0 0 18px ${color}, 0 0 40px ${color};`;
            el.textContent = text;
            fxCanvas.appendChild(el);
            setTimeout(() => el.remove(), 450);
        }

        /** Spawn impact burst tại toạ độ viewport (%) */
        function spawnImpact(x, y, color) {
            const imp = document.createElement('div');
            imp.className = 'impact';
            imp.style.cssText = `left:${x}%;top:${y}%;background:radial-gradient(circle,${color},rgba(255,255,255,0.3),transparent);`;
            fxCanvas.appendChild(imp);
            setTimeout(() => imp.remove(), 500);
        }

        /** Rung body nhẹ không dùng GSAP translate (tránh conflict layout) */
        function shakeBody(intensity = 6) {
            document.body.style.transform = `translate(${rnd(-intensity,intensity)}px,${rnd(-intensity,intensity)}px)`;
            setTimeout(() => document.body.style.transform = '', 60);
        }

        /* ─── SKILL JS ─── */
        function playKnife() {
            const knifeSVG = (rot) => `<svg width="80" height="16" viewBox="0 0 120 16" xmlns="http://www.w3.org/2000/svg">
                <defs><linearGradient id="kg" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" stop-color="#fff"/><stop offset="60%" stop-color="#c0ddf0"/><stop offset="100%" stop-color="#7ab0cc"/></linearGradient></defs>
                <rect x="0" y="5" width="32" height="6" rx="2" fill="#3a1a00"/><rect x="32" y="3" width="6" height="10" rx="2" fill="#f5c832"/><ellipse cx="0" cy="8" rx="5" ry="5" fill="#f5c832"/><path d="M38,3 L118,8 L38,13 Z" fill="url(#kg)"/><circle cx="119" cy="8" r="2" fill="white" opacity="0.9"/>
            </svg>`;
            for(let i=0;i<12;i++){
                setTimeout(()=>{
                    const el=document.createElement('div');
                    el.className='knife';
                    const startX=rnd(15,30), startY=rnd(30,70); // Dio ở bên trái (15-30%)
                    const tx=(rnd(40,70))+'vw'; // Ném sang phải
                    const ty=(rnd(-20,20))+'vh';
                    const rot=rnd(-15,15); // Mũi dao hướng sang phải
                    el.style.cssText=`left:${startX}%;top:${startY}%;--tx:${tx};--ty:${ty};--rot:${rot}deg;`;
                    el.innerHTML=knifeSVG(rot);
                    fxCanvas.appendChild(el);
                    setTimeout(()=>el.remove(),600);
                }, i*80);
            }
            shakeBody(8);
        }

        function playStarFinger() {
            for(let i=0;i<4;i++){
                setTimeout(()=>{
                    const el=document.createElement('div');
                    el.className='star-finger-beam';
                    const y=rnd(30,70);
                    const angle=rnd(165,195); // JoJo ở bên phải, hướng beam sang trái (~180deg)
                    const len=rnd(40,70)+'vw';
                    el.style.cssText=`left:75%;top:${y}%;--len:${len};--rot:${angle}deg;transform-origin:left center;box-shadow:0 0 12px #00d4ff, 0 0 30px #00d4ff;`;
                    fxCanvas.appendChild(el);
                    const tip=document.createElement('div');
                    tip.style.cssText=`position:absolute;left:75%;top:${y-2}%;width:20px;height:20px;background:radial-gradient(circle,white,#00d4ff,transparent);border-radius:50%;opacity:0;animation:impactPop 0.5s ease-out 0.4s forwards;`;
                    fxCanvas.appendChild(tip);
                    setTimeout(()=>{ el.remove(); tip.remove(); },800);
                }, i*150);
            }
        }

        function playJotaroTime() {
            const overlay=document.createElement('div');
            overlay.className='time-stop-overlay';
            fxCanvas.appendChild(overlay);
            const txt=document.createElement('div');
            txt.className='time-stop-text';
            txt.style.color='#00d4ff';
            txt.style.textShadow='0 0 40px #00d4ff, 0 0 80px #0044ff';
            txt.innerHTML='STAR PLATINUM<br>THE WORLD';
            fxCanvas.appendChild(txt);
            shakeBody(10);
            setTimeout(()=>{ overlay.remove(); txt.remove(); }, 2600);
        }

        function playDioTime() {
            const overlay=document.createElement('div');
            overlay.className='time-stop-overlay';
            overlay.style.background='rgba(60,30,0,0)';
            fxCanvas.appendChild(overlay);
            const txt=document.createElement('div');
            txt.className='time-stop-text';
            txt.style.color='#f5c832';
            txt.style.textShadow='0 0 40px #f5c832, 0 0 80px #ff4400';
            txt.innerHTML='時よ止まれ…<br>THE WORLD';
            fxCanvas.appendChild(txt);
            for(let i=0;i<8;i++){
                setTimeout(()=>{
                    const crack=document.createElement('div');
                    const x=rnd(10,85), y=rnd(10,85);
                    const len=rnd(60,200), angle=rnd(0,360);
                    crack.style.cssText=`position:fixed;left:${x}%;top:${y}%;width:${len}px;height:1px;background:rgba(245,200,50,0.6);transform:rotate(${angle}deg);transform-origin:left center;opacity:0;animation:impactPop 0.6s ease forwards;`;
                    fxCanvas.appendChild(crack);
                    setTimeout(()=>crack.remove(),700);
                }, i*150+300);
            }
            shakeBody(10);
            setTimeout(()=>{ overlay.remove(); txt.remove(); }, 2600);
        }

        function playRoadRoller() {
            const el=document.createElement('div');
            el.className='road-roller';
            el.innerHTML=`<svg width="220" height="130" viewBox="0 0 220 130" xmlns="http://www.w3.org/2000/svg">
                <rect x="20" y="10" width="180" height="70" rx="10" fill="#cc9900" stroke="#f5c832" stroke-width="2"/>
                <rect x="30" y="18" width="80" height="35" rx="5" fill="#1a1a00" stroke="#f5c832" stroke-width="1"/>
                <rect x="120" y="22" width="50" height="25" rx="4" fill="#333300"/>
                <circle cx="50" cy="105" r="24" fill="#222" stroke="#f5c832" stroke-width="3"/>
                <circle cx="50" cy="105" r="14" fill="#333" stroke="#888" stroke-width="1"/>
                <circle cx="170" cy="105" r="24" fill="#222" stroke="#f5c832" stroke-width="3"/>
                <circle cx="170" cy="105" r="14" fill="#333" stroke="#888" stroke-width="1"/>
                <text x="80" y="58" font-family="'Bebas Neue',sans-serif" font-size="18" fill="#f5c832" text-anchor="middle">ROAD ROLLER</text>
                <circle cx="50" cy="105" r="4" fill="#f5c832"/><circle cx="170" cy="105" r="4" fill="#f5c832"/>
            </svg>`;
            fxCanvas.appendChild(el);
            const txt=document.createElement('div');
            txt.className='time-stop-text';
            txt.style.color='#f5c832';
            txt.style.textShadow='0 0 40px #f5c832';
            txt.style.animationDelay='0.4s';
            txt.style.top='70%';
            txt.textContent='ROAD ROLLER DA!!!';
            fxCanvas.appendChild(txt);
            setTimeout(()=>shakeBody(8),400);
            setTimeout(()=>shakeBody(10),700);
            setTimeout(()=>shakeBody(12),900);
            setTimeout(()=>{ el.remove(); txt.remove(); },2200);
        }

        function playStarBreaker() {
            const flash=document.createElement('div');
            flash.className='star-breaker-flash';
            fxCanvas.appendChild(flash);
            const txt=document.createElement('div');
            txt.className='star-breaker-text';
            txt.textContent='STAR BREAKER';
            fxCanvas.appendChild(txt);
            shakeBody(10);
            setTimeout(()=>shakeBody(10), 150);
            setTimeout(()=>{ flash.remove(); txt.remove(); }, 1100);
        }

        function playWryyy() {
            const bg=document.createElement('div');
            bg.className='wryyy-bg';
            fxCanvas.appendChild(bg);
            const txt=document.createElement('div');
            txt.className='wryyy-text';
            txt.textContent='WRYYYYYY';
            fxCanvas.appendChild(txt);
            const parts=document.createElement('div');
            parts.className='wryyy-particles';
            for(let i=0;i<24;i++){
                const p=document.createElement('div');
                p.className='p';
                const angle=rnd(0,360)*Math.PI/180;
                const dist=rnd(120,300);
                p.style.cssText=`position:fixed;left:${25+rnd(-3,3)}%;top:${48+rnd(-3,3)}%;--px:${Math.cos(angle)*dist}px;--py:${Math.sin(angle)*dist}px;width:${rnd(4,10)}px;height:${rnd(4,10)}px;animation-delay:${rnd(0,0.3)}s;`; // Dio bên trái
                parts.appendChild(p);
            }
            fxCanvas.appendChild(parts);
            setTimeout(()=>{ bg.remove(); txt.remove(); parts.remove(); }, 2200);
        }

        const wait = (ms) => new Promise(resolve => setTimeout(resolve, ms));

        function playHamon() {
            const bg=document.createElement('div');
            bg.className='hamon-bg';
            fxCanvas.appendChild(bg);
            for(let i=0;i<6;i++){
                setTimeout(()=>{
                    const ring=document.createElement('div');
                    ring.className='hamon-ring';
                    const size=rnd(80,220);
                    const x=rnd(65,85), y=rnd(20,80); // JoJo bên phải
                    ring.style.cssText=`left:${x}%;top:${y}%;width:${size}px;height:${size}px;animation-delay:${i*0.1}s;`;
                    fxCanvas.appendChild(ring);
                    setTimeout(()=>ring.remove(),900);
                }, i*120);
            }
            for(let i=0;i<20;i++){
                setTimeout(()=>{
                    const spark=document.createElement('div');
                    const angle=rnd(0,360)*Math.PI/180;
                    const dist=rnd(60,200);
                    spark.style.cssText=`position:fixed;left:75%;top:50%;width:${rnd(3,8)}px;height:${rnd(3,8)}px;border-radius:50%;background:#00d4ff;box-shadow:0 0 6px #00d4ff;animation:wryPart ${rnd(0.8,1.4)}s ease-out forwards;--px:${Math.cos(angle)*dist}px;--py:${Math.sin(angle)*dist}px;z-index:9002;`;
                    fxCanvas.appendChild(spark);
                    setTimeout(()=>spark.remove(),1500);
                }, i*50);
            }
            const txt=document.createElement('div');
            txt.className='hamon-text';
            txt.innerHTML='HAMON<br>AURA';
            txt.style.lineHeight='1.0';
            txt.style.left = '75%'; // JoJo bên phải
            fxCanvas.appendChild(txt);
            shakeBody(10);
            setTimeout(()=>{ bg.remove(); txt.remove(); },1900);
        }

        function playVampRegen() {
            const bg=document.createElement('div');
            bg.className='vamp-bg';
            fxCanvas.appendChild(bg);
            for(let i=0;i<18;i++){
                setTimeout(()=>{
                    const drop=document.createElement('div');
                    drop.className='blood-drop';
                    drop.style.cssText=`left:${rnd(10,40)}%;top:${rnd(5,40)}%;animation-delay:${rnd(0,0.4)}s;width:${rnd(6,14)}px;height:${rnd(8,18)}px;`; // Dio bên trái
                    fxCanvas.appendChild(drop);
                    setTimeout(()=>drop.remove(),1400);
                }, i*60);
            }
            const txt=document.createElement('div');
            txt.className='vamp-text';
            txt.innerHTML='VAMPIRE<br>REGEN';
            txt.style.lineHeight='1.0';
            txt.style.left = '25%'; // Dio bên trái
            fxCanvas.appendChild(txt);
            const sub=document.createElement('div');
            sub.className='vamp-subtitle';
            sub.textContent='吸血鬼の再生能力';
            sub.style.left = '25%'; // Dio bên trái
            fxCanvas.appendChild(sub);
            setTimeout(()=>{
                const mini=document.createElement('div');
                mini.className='wryyy-text';
                mini.style.fontSize='clamp(24px,5vw,60px)';
                mini.style.top='30%';
                mini.style.left = '25%'; // Dio bên trái
                mini.textContent='WRYY…';
                fxCanvas.appendChild(mini);
                setTimeout(()=>mini.remove(),2000);
            }, 600);
            setTimeout(()=>{ bg.remove(); txt.remove(); sub.remove(); },1900);
        }

        async function startDuel() {
            if (active) return;
            if (!selected) {
                Swal.fire('Chú ý', 'Vui lòng chọn nhân vật (DIO hoặc JOTARO) trước khi chiến đấu!', 'warning');
                return;
            }
            const amt = document.getElementById('cuoc').value;
            active = true;
            document.getElementById('btn-duel').disabled = true;

            try {
                /* ── Đặt cược ── */
                const res = await $.post('?action=place_bet', { chon: selected, cuoc: amt, round_id: Date.now() });
                if (!res.success) {
                    Swal.fire('Lỗi', res.message, 'error');
                    active = false;
                    document.getElementById('btn-duel').disabled = false;
                    return;
                }
                document.getElementById('money-val').textContent = res.newBalance;

                const result = await $.post('?action=get_result', { round_id: Date.now() });
                const isWin = result.winAmount > 0;
                const winnerName = result.winner; // "JoJo" or "Dio"
                const loserName = winnerName === "JoJo" ? "Dio" : "JoJo";

                // Đặt lại HP đầu game
                let hpJ = 100;
                let hpD = 100;

                // ═══════════════════════════════════════════════
                //  PHASE 1 — INTRO: đổi GIF + zoom sân khấu
                // ═══════════════════════════════════════════════
                document.querySelector('#f-dio img').src  = 'gif/dio.gif';
                document.querySelector('#f-jojo img').src = 'gif/jotaro.gif';

                // Zoom nhẹ sân khấu (chỉ scale, không translate body)
                gsap.to('.combat-stage', { scale: 1.08, duration: 0.4, ease: "power2.inOut" });

                // Hai bên lao vào nhau
                gsap.to('#f-dio',  { x:  80, duration: 0.28, yoyo: true, repeat: 3, ease: "power2.inOut" });
                gsap.to('#f-jojo', { x: -80, duration: 0.28, yoyo: true, repeat: 3, ease: "power2.inOut" });
                
                await wait(1200);

                // ═══════════════════════════════════════════════
                //  PHASE 2 — LƯỢT 1: Người thua ra đòn trước
                // ═══════════════════════════════════════════════
                if (loserName === "Dio") {
                    playKnife();
                    await wait(600);
                    hpJ -= 30;
                    gsap.to('#hp-j', { width: hpJ + "%", duration: 0.4 });
                    shakeBody(8);
                } else {
                    playStarFinger();
                    await wait(800);
                    hpD -= 30;
                    gsap.to('#hp-d', { width: hpD + "%", duration: 0.4 });
                    shakeBody(8);
                }
                await wait(600);

                // ═══════════════════════════════════════════════
                //  PHASE 3 — LƯỢT 2: Người thắng phản công
                // ═══════════════════════════════════════════════
                if (winnerName === "JoJo") {
                    playStarFinger();
                    await wait(800);
                    hpD -= 30;
                    gsap.to('#hp-d', { width: hpD + "%", duration: 0.4 });
                    shakeBody(8);
                } else {
                    playKnife();
                    await wait(600);
                    hpJ -= 30;
                    gsap.to('#hp-j', { width: hpJ + "%", duration: 0.4 });
                    shakeBody(8);
                }
                await wait(600);

                // ═══════════════════════════════════════════════
                //  PHASE 3.5 — BUFF PASSIVE (Hồi phục ngẫu nhiên)
                // ═══════════════════════════════════════════════
                if (Math.random() < 0.6) {
                    const buffer = Math.random() < 0.5 ? "JoJo" : "Dio";
                    if (buffer === "JoJo") {
                        playHamon();
                        await wait(1800);
                        hpJ = Math.min(100, hpJ + 15);
                        gsap.to('#hp-j', { width: hpJ + "%", duration: 0.4 });
                    } else {
                        playVampRegen();
                        await wait(1800);
                        hpD = Math.min(100, hpD + 15);
                        gsap.to('#hp-d', { width: hpD + "%", duration: 0.4 });
                    }
                    await wait(600);
                }

                // ═══════════════════════════════════════════════
                //  PHASE 4 — ORA vs MUDA CLASH (Va chạm diện rộng)
                // ═══════════════════════════════════════════════
                const oraWords  = ['ORA','ORA','ORA!','ORA!!','ORA!!!'];
                const mudaWords = ['MUDA','MUDA','MUDA!','MUDA!!','無駄'];
                
                for (let i = 0; i < 8; i++) {
                    spawnRushWord(oraWords[i % 5], '#00d4ff', false);
                    spawnRushWord(mudaWords[i % 5], '#f5c832', true);
                    const cx = rnd(42, 58), cy = rnd(35, 65);
                    spawnImpact(cx, cy, i % 2 === 0 ? 'rgba(0,212,255,0.75)' : 'rgba(245,200,50,0.75)');
                    if (i % 4 === 0) addFlash();
                    if (i % 3 === 0) shakeBody(5);
                    gsap.to('#f-dio',  { y: rnd(-8, 8), duration: 0.06, yoyo: true, repeat: 1 });
                    gsap.to('#f-jojo', { y: rnd(-8, 8), duration: 0.06, yoyo: true, repeat: 1 });

                    // Máu giảm từ từ trong lúc va chạm
                    hpJ -= 2;
                    hpD -= 2;
                    gsap.to('#hp-j', { width: hpJ + "%", duration: 0.1 });
                    gsap.to('#hp-d', { width: hpD + "%", duration: 0.1 });

                    await wait(85 + Math.random() * 35);
                }

                await wait(400);

                // ═══════════════════════════════════════════════
                //  PHASE 5 — ULTIMATE FINISHER
                // ═══════════════════════════════════════════════
                addFlash();
                if (winnerName === "JoJo") {
                    const skills = [playJotaroTime, playStarBreaker];
                    skills[Math.floor(Math.random() * skills.length)]();
                } else {
                    const skills = [playRoadRoller, playDioTime, playWryyy];
                    skills[Math.floor(Math.random() * skills.length)]();
                }
                
                // Đợi hiệu ứng ultimate kết thúc
                await wait(2700);

                // ═══════════════════════════════════════════════
                //  PHASE 6 — RETIRED VÀ HP TỤT XUỐNG 0
                // ═══════════════════════════════════════════════
                document.querySelector('#f-dio img').src  = 'img/dio.png';
                document.querySelector('#f-jojo img').src = 'img/jotaro.png';
                gsap.to('.combat-stage', { scale: 1, duration: 0.4 });
                gsap.set(['#f-dio','#f-jojo'], { y: 0, x: 0, rotation: 0 });

                addFlash();
                setTimeout(addFlash, 80);

                const loserEl = winnerName === "JoJo" ? document.getElementById('f-dio') : document.getElementById('f-jojo');
                const loserHp = winnerName === "JoJo" ? document.getElementById('hp-d') : document.getElementById('hp-j');
                
                loserHp.classList.add('draining');
                gsap.to(loserHp, { width: "0%", duration: 1.2, ease: "power3.out" });

                // Hiệu ứng bốc hơi thành tro thay vì văng ra khỏi màn hình
                loserEl.classList.add('loser-shake');
                setTimeout(() => loserEl.classList.add('ash-effect'), 400);

                await wait(600);

                // Màn hình đen dần + RETIRED stamp
                const darkOverlay = document.createElement('div');
                darkOverlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9998;pointer-events:none;opacity:0;transition:opacity 0.5s;';
                fxCanvas.appendChild(darkOverlay);
                setTimeout(() => darkOverlay.style.opacity = '1', 10);

                const retEl = document.createElement('div');
                retEl.className = 'retired-text retired-anim';
                retEl.innerHTML = 'RETIRED';
                fxCanvas.appendChild(retEl);

                shakeBody(15);
                
                await wait(2500);

                // ═══════════════════════════════════════════════
                //  PHASE 7 — HIỂN THỊ KẾT QUẢ VÀ TỰ ĐỘNG RESET
                // ═══════════════════════════════════════════════
                if (isWin) {
                    if (window.GameEffects) GameEffects.showWin(result.winAmount);
                } else {
                    if (window.GameEffects) GameEffects.showLoss();
                }

                // Cập nhật GTLM
                document.getElementById('money-val').textContent = result.newBalance;
                
                // Đợi 4 giây cho người chơi tận hưởng kết quả mà không bị popup che mất
                await wait(4000);

                retEl.remove();
                darkOverlay.remove();
                active = false;
                document.getElementById('btn-duel').disabled = false;

                // ═══════════════════════════════════════════════
                //  PHASE 8 — RESET
                // ═══════════════════════════════════════════════
                gsap.to(['#f-dio','#f-jojo'], { x: 0, y: 0, rotation: 0, opacity: 1, duration: 0.6, ease: "power2.out" });
                document.getElementById('f-dio').classList.remove('loser-shake', 'ash-effect');
                document.getElementById('f-jojo').classList.remove('loser-shake', 'ash-effect');
                document.getElementById('hp-d').classList.remove('draining');
                document.getElementById('hp-j').classList.remove('draining');
                gsap.to('#hp-d', { width: "100%", duration: 0.6 });
                gsap.to('#hp-j', { width: "100%", duration: 0.6 });
                document.body.style.transform = '';

            } catch (e) {
                console.error(e);
                Swal.fire('Lỗi Hệ Thống', 'Có lỗi xảy ra: ' + (e.message || e.toString()) + ' | ' + (e.responseText || ''), 'error');
                active = false;
                document.getElementById('btn-duel').disabled = false;
            }
        }
    </script>






    <!-- Premium Effects System -->
    <canvas id="threejs-background"></canvas>
    <script>


        (function () {
            window.themeConfig = {
                particleCount: 800,
                particleSize: 0.05,
                particleColor: '#ffffff',
                particleOpacity: 0.6,
                shapeCount: 10,
                shapeColors: ["#667eea", "#764ba2", "#4facfe", "#00f2fe"],
                shapeOpacity: 0.3,
                bgGradient: ["#080010", "#1a0033", "#000000"]
            };
            const prefix = '../';
            ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'].forEach(src => {
                const s = document.createElement('script'); s.src = prefix + src; s.async = false; document.head.appendChild(s);
            });
        })();


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