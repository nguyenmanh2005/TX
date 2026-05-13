<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}

require '../db_connect.php';

// AJAX history endpoint
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_history') {
    header('Content-Type: application/json; charset=utf-8');
    
    $id = $_SESSION['Iduser'] ?? 0;
    $sql = "SELECT * FROM history_duangua WHERE Iduser = ? ORDER BY Time DESC LIMIT 20";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'history' => $history
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once '../load_theme.php';

$userId = $_SESSION['Iduser'];

/* ══════════════════════════════════════════════
   AJAX endpoint — POST + X-Requested-With header
══════════════════════════════════════════════ */
if (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' &&
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    header('Content-Type: application/json; charset=utf-8');

    $betAnimal = (int) ($_POST['animal'] ?? 0);
    $betAmount = (int) ($_POST['amount'] ?? 0);
    $animalNames = ["Chó", "Mèo", "Sư Tử", "Khỉ", "Ngựa Vằn", "Hổ", "Cáo", "Thỏ"];

    // Lấy Số Gtlm
    $q = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
    $q->bind_param("i", $userId);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();


// Get statistics from database for chart
$gameThang = 0;
$gameThua = 0;
$sqlStats = "SELECT COUNT(*) as total, SUM(CASE WHEN WinAmount > 0 THEN 1 ELSE 0 END) as wins FROM history_duangua WHERE Iduser = ?";
$stmtStats = $conn->prepare($sqlStats);
$stmtStats->bind_param("i", $userId);
$stmtStats->execute();
$resultStats = $stmtStats->get_result();
if ($rowStats = $resultStats->fetch_assoc()) {
    $gameThang = $rowStats['wins'] ?? 0;
    $gameThua = ($rowStats['total'] ?? 0) - $gameThang;
}
$stmtStats->close();

    $q->close();
    $currentMoney = (float) $row['Money'];

    if ($betAnimal < 1 || $betAnimal > 8 || $betAmount <= 0) {
        echo json_encode(['ok' => false, 'msg' => '❌ Dữ liệu không hợp lệ!']);
        exit;
    }
    if ($betAmount > $currentMoney) {
        echo json_encode(['ok' => false, 'msg' => '⚠️ Số Gtlm không đủ!']);
        exit;
    }

    // Kết quả
    $winnerIdx = rand(0, 7); // 0-based
    $isWin = ($betAnimal - 1) === $winnerIdx;
    $reward = $isWin ? $betAmount * 7 : 0;
    $newMoney = $isWin ? $currentMoney + $reward : $currentMoney - $betAmount;

    // Vị trí % cuối cho từng con
    // FIX PHP6403: thay lcg_value() bằng mt_rand()/mt_getrandmax() (tương thích PHP 8.4)
    $positions = [];
    for ($i = 0; $i < 8; $i++) {
        $positions[] = ($i === $winnerIdx)
            ? 92
            : round(22 + (mt_rand() / mt_getrandmax()) * 58, 1);
    }

    // Lưu DB
    $upd = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
    $upd->bind_param("di", $newMoney, $userId);
    $upd->execute();
    $upd->close();

    require_once '../game_history_helper.php';
    logGameHistoryWithAll($conn, $userId, 'Đua Thú', $betAmount, $reward, $isWin);

    $his = $conn->prepare("INSERT INTO history_duangua (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
    if ($his) {
        $resultStr = "Chọn " . $animalNames[$betAnimal - 1] . " → Ra " . $animalNames[$winnerIdx];
        $his->bind_param("iisi", $userId, $betAmount, $resultStr, $reward);
        $his->execute();
        $his->close();
    }

    echo json_encode([
        'ok' => true,
        'winner' => $winnerIdx,
        'betIdx' => $betAnimal - 1,
        'isWin' => $isWin,
        'reward' => $reward,
        'betAmount' => $betAmount,
        'newMoney' => $newMoney,
        'positions' => $positions,
        'winnerName' => $animalNames[$winnerIdx],
        'betName' => $animalNames[$betAnimal - 1],
        'msg' => $isWin
            ? "🎉 Con " . $animalNames[$winnerIdx] . " về đích! Bạn thắng " . number_format($reward) . " gtlm!"
            : "😢 Con " . $animalNames[$winnerIdx] . " về đích! Bạn mất " . number_format($betAmount) . " gtlm.",
    ]);
    exit;
}

/* ══════════════════════════════════════════════
   Trang bình thường
══════════════════════════════════════════════ */
$ud = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$ud->bind_param("i", $userId);
$ud->execute();
$user = $ud->get_result()->fetch_assoc();
$ud->close();

$currentMoney = $user['Money'];
$userName = $user['Name'];
$animalNames = ["Chó", "Mèo", "Sư Tử", "Khỉ", "Ngựa Vằn", "Hổ", "Cáo", "Thỏ"];
$animalEmojis = ["🐶", "🐱", "🦁", "🐵", "🦓", "🐯", "🦊", "🐰"];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🐎 Đua Thú</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;600;700&family=Exo+2:wght@300;400;700;900&display=swap"
        rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        :root {
            --gold: #f5c842;
            --gold-glow: rgba(245, 200, 66, .38);
            --gold-dim: rgba(245, 200, 66, .12);
            --win: #00e676;
            --lose: #ff5566;
            --surface: rgba(8, 8, 24, .80);
            --surface-lite: rgba(255, 255, 255, .055);
            --border: rgba(255, 255, 255, .10);
            --border-gold: rgba(245, 200, 66, .30);
            --r-card: 22px;
            --font: 'Exo 2', sans-serif;
            --font-head: 'Rajdhani', sans-serif;
        }

        body {
            font-family: var(--font);
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 32px 16px 56px;
            overflow-x: hidden;
        }

        /* ── Three.js + Particles ── */
        #threejs-background {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none
        }

        #particles-bg {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 1;
            overflow: hidden
        }

        .ambient-dot {
            position: absolute;
            border-radius: 50%;
            animation: floatUp linear infinite;
            opacity: 0
        }

        @keyframes floatUp {
            0% {
                transform: translateY(100vh) scale(0);
                opacity: 0
            }

            10% {
                opacity: .9
            }

            90% {
                opacity: .4
            }

            100% {
                transform: translateY(-10vh) scale(1.5);
                opacity: 0
            }
        }

        /* ── Overlays ── */
        #fireworks-canvas {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 9999
        }

        .screen-flash {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 9998;
            opacity: 0;
            animation: flashOut .7s ease-out forwards
        }

        .screen-flash.win {
            background: radial-gradient(ellipse at center, rgba(0, 230, 118, .18) 0%, transparent 65%)
        }

        .screen-flash.lose {
            background: radial-gradient(ellipse at center, rgba(255, 61, 87, .14) 0%, transparent 65%)
        }

        @keyframes flashOut {
            0% {
                opacity: 0
            }

            20% {
                opacity: 1
            }

            100% {
                opacity: 0
            }
        }

        .float-reward {
            position: fixed;
            font-family: var(--font-head);
            font-size: 2.6rem;
            font-weight: 900;
            color: var(--gold);
            text-shadow: 0 0 30px rgba(245, 200, 66, .8);
            pointer-events: none;
            z-index: 10000;
            animation: fUp 2.2s cubic-bezier(.16, 1, .3, 1) forwards;
        }

        @keyframes fUp {
            0% {
                opacity: 0;
                transform: translateY(0) scale(.5)
            }

            15% {
                opacity: 1;
                transform: translateY(-22px) scale(1.15)
            }

            80% {
                opacity: .9;
                transform: translateY(-95px) scale(1)
            }

            100% {
                opacity: 0;
                transform: translateY(-125px) scale(.85)
            }
        }

        /* ── Card ── */
        .game-card {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 840px;
            background: var(--surface);
            backdrop-filter: blur(28px) saturate(1.4);
            -webkit-backdrop-filter: blur(28px) saturate(1.4);
            border: 1px solid var(--border);
            border-radius: var(--r-card);
            box-shadow: 0 0 0 1px rgba(255, 255, 255, .04) inset, 0 24px 80px rgba(0, 0, 0, .6), 0 0 80px rgba(245, 200, 66, .04);
            padding: 38px 36px 34px;
            text-align: center;
            animation: cardIn .65s cubic-bezier(.16, 1, .3, 1) both;
        }

        @keyframes cardIn {
            from {
                opacity: 0;
                transform: translateY(36px) scale(.97)
            }

            to {
                opacity: 1;
                transform: none
            }
        }

        .game-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 12%;
            right: 12%;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            animation: shimmer 3.5s ease-in-out infinite;
        }

        @keyframes shimmer {

            0%,
            100% {
                opacity: .2;
                left: 18%;
                right: 18%
            }

            50% {
                opacity: .9;
                left: 6%;
                right: 6%
            }
        }

        /* ── Header ── */
        .game-title {
            font-family: var(--font-head);
            font-size: 2.2rem;
            font-weight: 700;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: var(--gold);
            animation: titlePulse 4s ease-in-out infinite;
            margin-bottom: 4px;
        }

        @keyframes titlePulse {

            0%,
            100% {
                text-shadow: 0 0 18px var(--gold-glow)
            }

            50% {
                text-shadow: 0 0 44px rgba(245, 200, 66, .75)
            }
        }

        .game-sub {
            font-size: .75rem;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .3);
            margin-bottom: 8px
        }

        .welcome {
            font-size: .9rem;
            color: rgba(255, 255, 255, .5);
            margin-bottom: 18px
        }

        .welcome b {
            color: var(--gold)
        }

        /* ── Balance ── */
        .balance-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--gold-dim);
            border: 1px solid var(--border-gold);
            border-radius: 50px;
            padding: 9px 24px;
            margin-bottom: 28px;
            transition: box-shadow .3s;
        }

        .balance-pill:hover {
            box-shadow: 0 0 24px var(--gold-glow)
        }

        .balance-amt {
            font-family: var(--font-head);
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gold);
            letter-spacing: .5px
        }

        @keyframes balancePop {

            0%,
            100% {
                transform: scale(1)
            }

            40% {
                transform: scale(1.1);
                box-shadow: 0 0 30px var(--gold-glow)
            }
        }

        .balance-pill.pop {
            animation: balancePop .45s ease-out
        }

        /* ── Form ── */
        .form-row {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 24px
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px
        }

        .s-label {
            font-size: .68rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .35);
            text-align: left
        }

        .game-select,
        .bet-input {
            background: var(--surface-lite);
            border: 1.5px solid var(--border);
            border-radius: 13px;
            color: #fff;
            font-family: var(--font-head);
            font-size: 1.05rem;
            font-weight: 600;
            padding: 12px 16px;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        .game-select {
            min-width: 180px;
            padding-right: 36px;
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer
        }

        .game-select option {
            background: #1a1a3a;
            color: #fff
        }

        .select-wrap {
            position: relative
        }

        .select-wrap::after {
            content: '▾';
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gold);
            font-size: .85rem;
            pointer-events: none
        }

        .bet-input {
            width: 200px
        }

        .bet-input::placeholder {
            color: rgba(255, 255, 255, .22);
            font-weight: 400;
            font-size: .9rem
        }

        .game-select:focus,
        .bet-input:focus {
            border-color: rgba(245, 200, 66, .55);
            background: rgba(245, 200, 66, .05);
            box-shadow: 0 0 0 3px rgba(245, 200, 66, .1)
        }

        .qbets {
            display: flex;
            gap: 5px;
            margin-top: 4px
        }

        .qbtn {
            background: rgba(245, 200, 66, .12);
            border: 1px solid rgba(245, 200, 66, .28);
            border-radius: 8px;
            color: var(--gold);
            font-size: .68rem;
            font-weight: 700;
            padding: 4px 9px;
            cursor: pointer;
            transition: background .2s, transform .15s;
        }

        .qbtn:hover {
            background: rgba(245, 200, 66, .28);
            transform: translateY(-1px)
        }

        /* ── Race button ── */
        .race-btn {
            padding: 14px 38px;
            border-radius: 16px;
            border: none;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #f9d030 0%, #e09800 60%, #f9d030 100%);
            background-size: 200% 100%;
            color: #1c1000;
            font-family: var(--font-head);
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            box-shadow: 0 4px 22px rgba(245, 200, 66, .28);
            transition: transform .2s, box-shadow .2s, background-position .4s;
            align-self: flex-end;
            cursor: pointer;
        }

        .race-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -120%;
            width: 70%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .3), transparent);
            transform: skewX(-15deg);
            transition: left .5s ease;
        }

        .race-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 34px rgba(245, 200, 66, .45);
            background-position: 100% 0
        }

        .race-btn:hover::before {
            left: 130%
        }

        .race-btn:active {
            transform: translateY(-1px) scale(.985)
        }

        .race-btn:disabled {
            opacity: .5;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important
        }

        /* ── Countdown ── */
        .countdown-box {
            font-family: var(--font-head);
            font-size: 4rem;
            font-weight: 700;
            color: var(--gold);
            text-shadow: 0 0 40px var(--gold-glow);
            min-height: 90px;
            display: none;
            align-items: center;
            justify-content: center;
        }

        @keyframes countPop {
            from {
                transform: scale(.3);
                opacity: 0
            }

            to {
                transform: scale(1);
                opacity: 1
            }
        }

        .countdown-box.pop {
            animation: countPop .35s cubic-bezier(.34, 1.56, .64, 1)
        }

        /* ── Tracks ── */
        .tracks {
            margin: 10px 0 28px
        }

        .track {
            position: relative;
            width: 100%;
            height: 68px;
            margin: 8px 0;
            background: rgba(255, 255, 255, .035);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 12px;
            overflow: hidden;
            transition: border-color .4s, background .4s, box-shadow .4s, opacity .4s, filter .4s;
        }

        .track::before {
            content: '';
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(90deg, transparent, transparent 44px, rgba(255, 255, 255, .022) 44px, rgba(255, 255, 255, .022) 45px);
            pointer-events: none;
        }

        .track::after {
            content: '';
            position: absolute;
            inset: 0;
            background: transparent;
            transition: background .5s;
            pointer-events: none;
            border-radius: 12px;
            z-index: 1;
        }

        .track.win-track::after {
            background: linear-gradient(90deg, transparent 30%, rgba(0, 230, 118, .09) 100%)
        }

        .track-flag {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 26px;
            z-index: 5;
            opacity: .35;
            transition: opacity .4s, filter .4s;
        }

        .track.win-track .track-flag {
            opacity: 1;
            filter: drop-shadow(0 0 8px #00e676)
        }

        .track-label {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-family: var(--font-head);
            font-size: .66rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .18);
            z-index: 3;
            white-space: nowrap;
            pointer-events: none;
            transition: color .4s;
        }

        .track.bet-track {
            border-color: rgba(245, 200, 66, .35);
            background: rgba(245, 200, 66, .045)
        }

        .track.bet-track .track-label {
            color: rgba(245, 200, 66, .55)
        }

        .track.win-track {
            border-color: rgba(0, 230, 118, .5);
            background: rgba(0, 230, 118, .04);
            box-shadow: 0 0 22px rgba(0, 230, 118, .1) inset, 0 0 10px rgba(0, 230, 118, .08)
        }

        .track.win-track .track-label {
            color: rgba(0, 230, 118, .7)
        }

        .track.lose-track {
            opacity: .45;
            filter: grayscale(.35)
        }

        /* ── Animal ── */
        .animal {
            position: absolute;
            top: 50%;
            left: 10px;
            transform: translateY(-50%);
            font-size: 36px;
            z-index: 6;
            will-change: left;
            filter: drop-shadow(2px 3px 5px rgba(0, 0, 0, .5));
        }

        .animal.state-idle {
            animation: aIdle 2.2s ease-in-out infinite
        }

        .animal.state-race {
            animation: aRace .30s ease-in-out infinite
        }

        .animal.state-winner {
            animation: aWinner .65s ease-in-out infinite;
            filter: drop-shadow(0 0 14px rgba(255, 215, 0, .9))
        }

        .animal.state-loser {
            animation: none;
            filter: grayscale(.6) opacity(.45)
        }

        @keyframes aIdle {

            0%,
            100% {
                transform: translateY(-50%)
            }

            50% {
                transform: translateY(calc(-50% - 4px))
            }
        }

        @keyframes aRace {

            0%,
            100% {
                transform: translateY(-50%) scaleX(1)
            }

            30% {
                transform: translateY(calc(-50% - 9px)) scaleX(1.07)
            }

            70% {
                transform: translateY(calc(-50% + 2px)) scaleX(.95)
            }
        }

        @keyframes aWinner {

            0%,
            100% {
                transform: translateY(-50%) scale(1) rotate(0)
            }

            25% {
                transform: translateY(calc(-50% - 12px)) scale(1.2) rotate(-10deg)
            }

            75% {
                transform: translateY(calc(-50% - 12px)) scale(1.2) rotate(10deg)
            }
        }

        /* Dust */
        .dust {
            position: absolute;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: rgba(255, 200, 80, .55);
            pointer-events: none;
            z-index: 4;
            animation: dustFly .55s ease-out forwards;
        }

        @keyframes dustFly {
            0% {
                opacity: .85;
                transform: scale(1) translateX(0) translateY(0)
            }

            100% {
                opacity: 0;
                transform: scale(2.2) translateX(-28px) translateY(-8px)
            }
        }

        /* ── Result banner ── */
        .result-banner {
            border-radius: 15px;
            padding: 18px 26px;
            font-family: var(--font-head);
            font-size: 1.15rem;
            font-weight: 600;
            letter-spacing: .5px;
            line-height: 1.5;
            display: none;
        }

        .result-banner.show {
            display: block;
            animation: bannerIn .5s cubic-bezier(.16, 1, .3, 1) both
        }

        @keyframes bannerIn {
            from {
                opacity: 0;
                transform: translateY(18px) scale(.95)
            }

            to {
                opacity: 1;
                transform: none
            }
        }

        .result-banner.win {
            background: linear-gradient(135deg, rgba(0, 230, 118, .14), rgba(0, 200, 100, .06));
            border: 1px solid rgba(0, 230, 118, .4);
            color: #00ff88;
            box-shadow: 0 0 28px rgba(0, 230, 118, .12);
            text-shadow: 0 0 18px rgba(0, 255, 136, .45);
            margin-top: 20px;
        }

        .result-banner.lose {
            background: linear-gradient(135deg, rgba(255, 61, 87, .11), rgba(200, 40, 60, .05));
            border: 1px solid rgba(255, 61, 87, .3);
            color: #ff7080;
            box-shadow: 0 0 20px rgba(255, 61, 87, .08);
            margin-top: 20px;
        }

        .result-banner.error {
            background: rgba(255, 61, 87, .08);
            border: 1px solid rgba(255, 61, 87, .25);
            color: #ffaa80;
            margin-top: 12px;
        }

        /* ── Play again ── */
        .play-again-btn {
            display: none;
            margin-top: 16px;
            padding: 11px 32px;
            border-radius: 12px;
            border: 1.5px solid var(--border-gold);
            background: var(--gold-dim);
            color: var(--gold);
            font-family: var(--font-head);
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background .2s, box-shadow .2s, transform .2s;
        }

        .play-again-btn.show {
            display: inline-block
        }

        .play-again-btn:hover {
            background: rgba(245, 200, 66, .22);
            box-shadow: 0 0 22px var(--gold-glow);
            transform: translateY(-2px)
        }

        /* ── Back link ── */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 22px;
            color: rgba(255, 255, 255, .27);
            font-size: .73rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            text-decoration: none;
            transition: color .2s;
        }

        .back-link:hover {
            color: rgba(255, 255, 255, .65);
            text-decoration: none
        }

        @media(max-width:560px) {
            .game-card {
                padding: 26px 14px 24px
            }

            .game-title {
                font-size: 1.7rem
            }

            .form-row {
                flex-direction: column;
                align-items: center
            }

            .game-select,
            .bet-input {
                width: 100%;
                max-width: 280px;
                min-width: unset
            }

            .race-btn {
                width: 100%;
                max-width: 280px;
                align-self: center
            }

            .animal {
                font-size: 28px
            }

            .track {
                height: 58px
            }

            .countdown-box {
                font-size: 3rem
            }
        }
    
        /* Statistics Container */
        .stats-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .stat-item.wins {
            border-left: 4px solid #4ade80;
        }
        
        .stat-item.losses {
            border-left: 4px solid #ff6b6b;
        }
        
        .stat-item .label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        
        .stat-item .value {
            font-size: 28px;
            font-weight: 700;
            color: #ffd700;
        }
        
        .chart-box {
            display: flex;
            flex-direction: column;
        }
        
        .chart-box canvas {
            margin-top: 20px;
        }

    </style>
</head>

<body>


    <div id="particles-bg"></div>
    <canvas id="fireworks-canvas"></canvas>

    <div class="game-card" id="gameCard">

        <h1 class="game-title">🐎 Đua Thú</h1>
        <p class="game-sub">Chọn đúng con vật · Thắng ×7</p>
        <p class="welcome">Xin chào <b><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></b></p>

        <div class="balance-pill" id="balancePill">
            <span>💰</span>
            <span class="balance-amt" id="balanceAmt"><?= number_format($currentMoney, 0, ',', '.') ?> gtlm</span>
        </div>

        <form id="raceForm">
            <div class="form-row">
                <div class="form-group">
                    <span class="s-label">Chọn con vật</span>
                    <div class="select-wrap">
                        <select name="animal" class="game-select" id="animalSelect">
                            <?php for ($i = 0; $i < 8; $i++): ?>
                                <option value="<?= $i + 1 ?>"><?= $animalEmojis[$i] ?>     <?= $animalNames[$i] ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <span class="s-label">Số gtlm cược</span>
                    <input type="number" name="amount" id="betInput" class="bet-input" placeholder="Nhập gtlm cược…"
                        min="1" autocomplete="off">
                    <div class="qbets">
                        <button type="button" class="qbtn" onclick="qbet(10000)">10K</button>
                        <button type="button" class="qbtn" onclick="qbet(50000)">50K</button>
                        <button type="button" class="qbtn" onclick="qbet(100000)">100K</button>
                        <button type="button" class="qbtn" id="maxBtn" onclick="qbet(<?= $currentMoney ?>)">MAX</button>
                    </div>
                </div>
                <button type="submit" class="race-btn" id="raceBtn">🏁 Bắt đầu đua</button>
            </div>
        </form>

        <div class="countdown-box" id="countdownBox">3</div>

        <div class="tracks" id="tracksContainer">
            <?php for ($i = 0; $i < 8; $i++): ?>
                <div class="track<?= $i === 0 ? ' bet-track' : '' ?>" id="track<?= $i ?>">
                    <span class="track-label"><?= $animalNames[$i] ?></span>
                    <div class="animal state-idle" id="animal<?= $i ?>"><?= $animalEmojis[$i] ?></div>
                    <span class="track-flag">🏁</span>
                </div>
            <?php endfor; ?>
        </div>

        <div class="result-banner" id="resultBanner"></div>
        <button class="play-again-btn" id="playAgainBtn" onclick="resetGame()">🔄 Chơi lại</button>

        <a href="../index.php" class="back-link">← Quay lại trang chủ</a>

    </div>

    <script>
        /* ── Particles ─────────────────────────────────── */
        (function () {
            const bg = document.getElementById('particles-bg');
            const pal = ['rgba(245,200,66,', 'rgba(120,180,255,', 'rgba(160,100,255,', 'rgba(80,230,160,'];
            for (let i = 0; i < 22; i++) {
                const d = document.createElement('div'), c = pal[i % pal.length], s = 3 + Math.random() * 5;
                d.className = 'ambient-dot';
                d.style.cssText = 'width:' + s + 'px;height:' + s + 'px;left:' + (Math.random() * 100) + '%;' +
                    'background:' + c + (0.4 + Math.random() * 0.45) + ');' +
                    'box-shadow:0 0 ' + (s * 2.5) + 'px ' + c + '0.55);' +
                    'animation-duration:' + (7 + Math.random() * 12) + 's;' +
                    'animation-delay:' + (Math.random() * 10) + 's;';
                bg.appendChild(d);
            }
        })();

        /* ── State ─────────────────────────────────────── */
        let raceActive = false;
        let currentMoney = <?= (int) $currentMoney ?>;

        const RACE_DUR = 5200;   // ms tổng thời gian chạy
        const SETTLE = 700;    // ms sau đích mới đổi animation
        const REVEAL_LAG = 550;    // ms sau settle mới hiện kết quả
        const DUST_MS = 160;    // ms spawn dust

        /* ── Quick bet ──────────────────────────────────── */
        function qbet(v) {
            const inp = document.getElementById('betInput');
            inp.value = v;
            inp.style.borderColor = 'rgba(245,200,66,.7)';
            inp.style.boxShadow = '0 0 0 3px rgba(245,200,66,.12)';
            setTimeout(() => { inp.style.borderColor = ''; inp.style.boxShadow = ''; }, 600);
        }

        /* ── Highlight track on select change ───────────── */
        function setHighlight(idx) {
            document.querySelectorAll('.track').forEach((t, i) => t.classList.toggle('bet-track', i === idx && !raceActive));
        }
        document.getElementById('animalSelect').addEventListener('change', function () {
            setHighlight(parseInt(this.value) - 1);
        });

        /* ── Form submit ────────────────────────────────── */
        document.getElementById('raceForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            if (raceActive) return;

            const animal = parseInt(document.getElementById('animalSelect').value);
            const amount = parseInt(document.getElementById('betInput').value) || 0;

            if (amount < 1) { flashInput(document.getElementById('betInput')); return; }

            raceActive = true;
            lockForm(true);
            clearResult();

            /* 1. Countdown */
            await countdown();

            /* 2. Fetch result (server random + save DB) */
            let data;
            try {
                const fd = new FormData();
                fd.append('animal', animal);
                fd.append('amount', amount);
                const res = await fetch('duangua.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
                data = await res.json();
            } catch (err) {
                showBanner('❌ Lỗi kết nối!', 'error');
                lockForm(false); raceActive = false; return;
            }

            if (!data.ok) {
                showBanner(data.msg, 'error');
                lockForm(false); raceActive = false;
                document.getElementById('playAgainBtn').classList.add('show');
                return;
            }

            /* 3. Race animation — khán giả không biết kết quả cho đến khi ngựa về đích */
            await raceAnimation(data);

            /* 4. Reveal kết quả */
            await sleep(REVEAL_LAG);
            revealResult(data);

            raceActive = false;
        });

        /* ── Countdown ──────────────────────────────────── */
        function countdown() {
            return new Promise(resolve => {
                const box = document.getElementById('countdownBox');
                box.style.display = 'flex';
                let n = 3;
                box.textContent = n; pop(box);

                const iv = setInterval(() => {
                    n--;
                    if (n > 0) { box.textContent = n; pop(box); }
                    else {
                        box.textContent = '🏁 GO!'; pop(box);
                        clearInterval(iv);
                        setTimeout(() => { box.style.display = 'none'; resolve(); }, 450);
                    }
                }, 900);
            });
        }
        function pop(el) { el.classList.remove('pop'); el.offsetHeight; el.classList.add('pop'); }

        /* ── Race animation ─────────────────────────────── */
        function raceAnimation(data) {
            return new Promise(resolve => {

                // Reset tất cả tracks + animals
                for (let i = 0; i < 8; i++) {
                    const a = document.getElementById('animal' + i);
                    const t = document.getElementById('track' + i);
                    a.style.transition = 'none';
                    a.style.left = '10px';
                    a.className = 'animal state-idle';
                    t.classList.remove('bet-track', 'win-track', 'lose-track');
                }

                // Nhỏ xíu pause để reset render xong
                setTimeout(() => {

                    // Tất cả chuyển sang state-race
                    for (let i = 0; i < 8; i++) {
                        document.getElementById('animal' + i).className = 'animal state-race';
                    }

                    // Áp dụng transition + vị trí đích cho từng con
                    setTimeout(() => {
                        for (let i = 0; i < 8; i++) {
                            const a = document.getElementById('animal' + i);
                            const trackEl = document.getElementById('track' + i);
                            const tw = trackEl.clientWidth;
                            const pct = data.positions[i];
                            const targetPx = Math.min((pct / 100) * tw - 42, tw - 70);

                            // Winner: đường cong burst rồi brake đẹp
                            // Others: tốc độ random, easing hơi khác nhau
                            const dur = i === data.winner ? RACE_DUR : RACE_DUR * (0.68 + Math.random() * 0.30);
                            const ease = i === data.winner
                                ? 'cubic-bezier(0.15, 0.85, 0.25, 1.0)'
                                : 'cubic-bezier(0.28, 0.60, 0.55, 0.92)';

                            a.style.transition = `left ${dur}ms ${ease}`;
                            a.style.left = Math.max(targetPx, 10) + 'px';
                        }
                    }, 60);

                    // Dust liên tục từ vị trí winner
                    const dustIv = setInterval(() => spawnDust(data.winner), DUST_MS);

                    // Khi xong race
                    setTimeout(() => {
                        clearInterval(dustIv);

                        for (let i = 0; i < 8; i++) {
                            const a = document.getElementById('animal' + i);
                            const t = document.getElementById('track' + i);
                            if (i === data.winner) {
                                a.className = 'animal state-winner';
                                t.classList.add('win-track');
                            } else {
                                a.className = 'animal state-loser';
                                t.classList.add('lose-track');
                            }
                        }
                        resolve();
                    }, RACE_DUR + SETTLE);

                }, 80);
            });
        }

        /* ── Dust particle ──────────────────────────────── */
        function spawnDust(idx) {
            const track = document.getElementById('track' + idx);
            const animal = document.getElementById('animal' + idx);
            if (!track || !animal) return;
            const lx = parseFloat(animal.style.left) || 10;
            for (let i = 0; i < 3; i++) {
                const d = document.createElement('div');
                d.className = 'dust';
                d.style.left = (lx + 6 + Math.random() * 14) + 'px';
                d.style.top = (18 + Math.random() * 30) + 'px';
                d.style.animationDelay = (Math.random() * .12) + 's';
                track.appendChild(d);
                setTimeout(() => d.remove(), 650);
            }
        }

        /* ── Reveal result ──────────────────────────────── */
        function revealResult(data) {
            // Cập nhật Số Gtlm
            currentMoney = data.newMoney;
            const amt = document.getElementById('balanceAmt');
            amt.textContent = currentMoney.toLocaleString('vi-VN') + ' gtlm';
            const pill = document.getElementById('balancePill');
            pill.classList.remove('pop'); pill.offsetHeight; pill.classList.add('pop');

            // Screen flash
            const fl = document.createElement('div');
            fl.className = 'screen-flash ' + (data.isWin ? 'win' : 'lose');
            document.body.appendChild(fl);
            setTimeout(() => fl.remove(), 900);

            // Banner
            showBanner(data.msg, data.isWin ? 'win' : 'lose');

            // Play again
            document.getElementById('playAgainBtn').classList.add('show');

            // MAX button update
            document.getElementById('maxBtn').onclick = () => qbet(currentMoney);

            // Win effects
            if (data.isWin) {
                launchFireworks();
                spawnFloat('+' + data.reward.toLocaleString('vi-VN') + ' gtlm');
            }
        }

        /* ── Reset ──────────────────────────────────────── */
        function resetGame() {
            clearResult();
            for (let i = 0; i < 8; i++) {
                const a = document.getElementById('animal' + i);
                a.style.transition = 'none'; a.style.left = '10px';
                a.className = 'animal state-idle';
                const t = document.getElementById('track' + i);
                t.classList.remove('win-track', 'lose-track', 'bet-track');
            }
            const sel = parseInt(document.getElementById('animalSelect').value) - 1;
            document.getElementById('track' + sel).classList.add('bet-track');
            lockForm(false);
        }

        /* ── Helpers ────────────────────────────────────── */
        function clearResult() {
            const b = document.getElementById('resultBanner');
            b.className = 'result-banner'; b.textContent = '';
            document.getElementById('playAgainBtn').classList.remove('show');
        }
        function showBanner(msg, type) {
            const b = document.getElementById('resultBanner');
            b.className = 'result-banner ' + type + ' show';
            b.textContent = msg;
        }
        function lockForm(locked) {
            document.getElementById('raceBtn').disabled = locked;
            document.getElementById('betInput').disabled = locked;
            document.getElementById('animalSelect').disabled = locked;
            document.getElementById('raceBtn').textContent = locked ? '⏳ Đang đua…' : '🏁 Bắt đầu đua';
        }
        function flashInput(el) {
            el.style.borderColor = 'rgba(255,61,87,.7)';
            el.style.boxShadow = '0 0 0 3px rgba(255,61,87,.15)';
            el.focus();
            setTimeout(() => { el.style.borderColor = ''; el.style.boxShadow = ''; }, 700);
        }
        function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

        /* ── Fireworks ──────────────────────────────────── */
        function launchFireworks() {
            const cv = document.getElementById('fireworks-canvas');
            const ctx = cv.getContext('2d');
            cv.width = window.innerWidth; cv.height = window.innerHeight;
            const pal = ['#f5c842', '#00e676', '#40c4ff', '#ea80fc', '#ff7043', '#fff'];
            const pts = [];
            function burst(x, y) {
                for (let i = 0; i < 60; i++) {
                    const a = (Math.PI * 2 * i) / 60, spd = 1.5 + Math.random() * 5;
                    pts.push({
                        x, y, vx: Math.cos(a) * spd, vy: Math.sin(a) * spd - 1.5,
                        life: 1, decay: .010 + Math.random() * .013,
                        size: 2 + Math.random() * 3.5, color: pal[Math.floor(Math.random() * pal.length)], trail: []
                    });
                }
            }
            [[.28, .30], [.72, .25], [.50, .18], [.15, .50], [.85, .45]]
                .forEach(([x, y], i) => setTimeout(() => burst(window.innerWidth * x, window.innerHeight * y), i * 175));
            let raf;
            function draw() {
                ctx.fillStyle = 'rgba(0,0,0,.13)'; ctx.fillRect(0, 0, cv.width, cv.height);
                for (let i = pts.length - 1; i >= 0; i--) {
                    const p = pts[i];
                    p.trail.push({ x: p.x, y: p.y });
                    if (p.trail.length > 8) p.trail.shift();
                    p.x += p.vx; p.y += p.vy; p.vy += .065; p.vx *= .99; p.life -= p.decay;
                    if (p.life <= 0) { pts.splice(i, 1); continue; }
                    for (let t = 0; t < p.trail.length; t++) {
                        ctx.globalAlpha = (t / p.trail.length) * p.life * .4;
                        ctx.fillStyle = p.color; ctx.beginPath();
                        ctx.arc(p.trail[t].x, p.trail[t].y, p.size * (t / p.trail.length), 0, Math.PI * 2); ctx.fill();
                    }
                    ctx.globalAlpha = p.life; ctx.fillStyle = p.color;
                    ctx.shadowBlur = 8; ctx.shadowColor = p.color;
                    ctx.beginPath(); ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2); ctx.fill();
                    ctx.shadowBlur = 0;
                }
                ctx.globalAlpha = 1;
                if (pts.length > 0) raf = requestAnimationFrame(draw);
                else ctx.clearRect(0, 0, cv.width, cv.height);
            }
            draw();
            setTimeout(() => { cancelAnimationFrame(raf); ctx.clearRect(0, 0, cv.width, cv.height); }, 6000);
        }

        /* ── Float reward ───────────────────────────────── */
        function spawnFloat(text) {
            const el = document.createElement('div');
            el.className = 'float-reward'; el.textContent = text;
            const r = document.getElementById('gameCard').getBoundingClientRect();
            el.style.left = (r.left + r.width / 2 - 110) + 'px';
            el.style.top = (r.top + 80) + 'px';
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 2500);
        }
    </script>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="../threejs-background.js"></script>












