<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require_once 'load_theme.php';

if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

$userId = $_SESSION['Iduser'];

$sql = "SELECT Iduser, Name, Money FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$checkRewardsTable = $conn->query("SHOW TABLES LIKE 'lucky_wheel_rewards'");
$checkLogsTable = $conn->query("SHOW TABLES LIKE 'lucky_wheel_logs'");
$wheelExists = $checkRewardsTable && $checkRewardsTable->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎡 Vòng Quay May Mắn - GTLM Gaming</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --gold: #ffd700;
            --gold2: #ffaa00;
            --spin-glow: rgba(255,215,0,0.5);
            --card-bg: rgba(255,255,255,0.04);
            --card-border: rgba(255,255,255,0.10);
            --text: #f0f4ff;
            --text-dim: rgba(240,244,255,0.55);
            --radius: 20px;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: #08091a;
            min-height: 100vh;
            color: var(--text);
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            overflow-x: hidden;
        }

        * { cursor: inherit; }
        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        /* ── Animated gradient background ── */
        .bg-aura {
            position: fixed; inset: 0; z-index: 0; overflow: hidden; pointer-events: none;
        }
        .bg-aura::before {
            content: '';
            position: absolute;
            width: 800px; height: 800px;
            top: -200px; left: 50%;
            transform: translateX(-50%);
            background: radial-gradient(circle, rgba(120,60,220,0.35) 0%, transparent 70%);
            animation: aura-pulse 6s ease-in-out infinite alternate;
        }
        .bg-aura::after {
            content: '';
            position: absolute;
            width: 600px; height: 600px;
            bottom: -100px; left: 50%;
            transform: translateX(-50%);
            background: radial-gradient(circle, rgba(255,170,0,0.20) 0%, transparent 70%);
            animation: aura-pulse 8s ease-in-out infinite alternate-reverse;
        }
        @keyframes aura-pulse {
            from { transform: translateX(-50%) scale(1); opacity: 0.7; }
            to   { transform: translateX(-50%) scale(1.2); opacity: 1; }
        }

        /* Stars */
        .stars {
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image:
                radial-gradient(1px 1px at 10% 20%, rgba(255,255,255,.8) 0%, transparent 100%),
                radial-gradient(1px 1px at 30% 60%, rgba(255,255,255,.6) 0%, transparent 100%),
                radial-gradient(1px 1px at 60% 10%, rgba(255,255,255,.9) 0%, transparent 100%),
                radial-gradient(1px 1px at 80% 80%, rgba(255,255,255,.7) 0%, transparent 100%),
                radial-gradient(1px 1px at 50% 40%, rgba(255,255,255,.5) 0%, transparent 100%),
                radial-gradient(1px 1px at 90% 30%, rgba(255,255,255,.8) 0%, transparent 100%),
                radial-gradient(1px 1px at 15% 85%, rgba(255,255,255,.6) 0%, transparent 100%),
                radial-gradient(1px 1px at 70% 55%, rgba(255,255,255,.7) 0%, transparent 100%);
            background-size: 300px 300px;
        }

        /* ── Layout ── */
        #app {
            position: relative; z-index: 1;
            max-width: 1100px;
            margin: 0 auto;
            padding: 28px 20px 60px;
        }

        /* ── Header ── */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 36px;
            flex-wrap: wrap;
            gap: 14px;
        }
        .top-bar .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, #a855f7, #6366f1);
            display: flex; align-items: center; justify-content: center;
            font-size: 26px;
            box-shadow: 0 0 24px rgba(168,85,247,0.5);
        }
        .logo-text h1 {
            font-size: 22px;
            font-weight: 900;
            background: linear-gradient(90deg, var(--gold), #ff8a00, var(--gold));
            background-size: 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmer 3s linear infinite;
        }
        @keyframes shimmer {
            from { background-position: 0% 50%; }
            to   { background-position: 200% 50%; }
        }
        .logo-text p { font-size: 12px; color: var(--text-dim); margin-top: 2px; }

        .user-badge {
            display: flex; align-items: center; gap: 10px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 50px;
            padding: 8px 18px 8px 10px;
            backdrop-filter: blur(12px);
        }
        .user-badge .avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #a855f7);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            font-weight: 900;
            color: white;
        }
        .user-badge .info { line-height: 1.2; }
        .user-badge .name { font-size: 13px; font-weight: 700; color: var(--text); }
        .user-badge .money { font-size: 12px; color: #4ade80; font-weight: 600; }

        /* ── Main grid ── */
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 28px;
            align-items: start;
        }
        @media (max-width: 820px) {
            .main-grid { grid-template-columns: 1fr; }
        }

        /* ── Glass card ── */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        /* ── Wheel section ── */
        .wheel-card {
            padding: 36px 28px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .wheel-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 50% 0%, rgba(168,85,247,0.12), transparent 70%);
            pointer-events: none;
        }

        .spin-label {
            font-size: 12px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 8px;
        }
        .spin-title {
            font-size: 28px;
            font-weight: 900;
            background: linear-gradient(90deg, var(--gold), #ff8a00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 28px;
        }

        /* Wheel wrapper */
        .wheel-wrapper {
            position: relative;
            display: inline-block;
            margin: 0 auto 32px;
        }

        /* Outer glow ring */
        .wheel-glow-ring {
            position: absolute;
            inset: -14px;
            border-radius: 50%;
            background: conic-gradient(
                #ff6b6b, #ffd700, #00f5a0, #667eea,
                #f093fb, #ffd700, #ff6b6b
            );
            animation: spin-ring 8s linear infinite;
            opacity: 0.65;
            filter: blur(3px);
        }
        @keyframes spin-ring { to { transform: rotate(360deg); } }

        .wheel-glow-ring-inner {
            position: absolute;
            inset: -8px;
            border-radius: 50%;
            background: #08091a;
        }

        #wheel {
            width: 420px;
            height: 420px;
            border-radius: 50%;
            position: relative;
            z-index: 2;
            transition: transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99);
            box-shadow: 0 0 40px rgba(168,85,247,0.3), 0 0 80px rgba(168,85,247,0.1);
        }
        @media (max-width: 520px) {
            #wheel { width: 300px; height: 300px; }
        }

        /* Pointer */
        .wheel-pointer {
            position: absolute;
            top: -22px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            filter: drop-shadow(0 4px 12px rgba(255,215,0,0.7));
        }
        .wheel-pointer svg { display: block; }

        /* Center circle */
        .wheel-center {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 60px; height: 60px;
            border-radius: 50%;
            background: radial-gradient(circle, #fff 0%, #e0e0e0 100%);
            z-index: 5;
            box-shadow: 0 4px 20px rgba(0,0,0,0.4), inset 0 2px 4px rgba(255,255,255,0.6);
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
        }

        /* Spin button */
        .spin-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 52px;
            font-size: 18px;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            color: #000;
            background: linear-gradient(135deg, var(--gold), var(--gold2));
            border: none;
            border-radius: 50px;
            box-shadow: 0 0 24px var(--spin-glow), 0 8px 30px rgba(255,170,0,0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .spin-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.25), transparent);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .spin-btn:hover:not(:disabled)::before { opacity: 1; }
        .spin-btn:hover:not(:disabled) {
            transform: translateY(-3px) scale(1.04);
            box-shadow: 0 0 40px var(--spin-glow), 0 12px 40px rgba(255,170,0,0.4);
        }
        .spin-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed !important;
            transform: none;
            box-shadow: none;
        }
        .spin-btn .btn-icon { font-size: 22px; animation: spin-icon 2s linear infinite; }
        @keyframes spin-icon { to { transform: rotate(360deg); } }
        .spin-btn:disabled .btn-icon { animation: none; }

        /* Status badge */
        .spin-status {
            margin-top: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
        }
        .spin-status.can-spin {
            background: rgba(74,222,128,0.12);
            border: 1px solid rgba(74,222,128,0.3);
            color: #4ade80;
        }
        .spin-status.no-spin {
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.3);
            color: #f87171;
        }
        .spin-status.spinning {
            background: rgba(251,191,36,0.12);
            border: 1px solid rgba(251,191,36,0.3);
            color: #fbbf24;
        }

        /* ── Right sidebar ── */
        .sidebar { display: flex; flex-direction: column; gap: 20px; }

        /* Daily info card */
        .info-card {
            padding: 22px;
            position: relative;
            overflow: hidden;
        }
        .info-card::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 140px; height: 140px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(168,85,247,0.2), transparent);
            pointer-events: none;
        }
        .info-card-title {
            font-size: 11px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 14px;
        }
        .daily-badge {
            display: flex; align-items: center; gap: 14px;
        }
        .daily-icon {
            width: 50px; height: 50px;
            border-radius: 14px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 16px rgba(251,191,36,0.4);
            flex-shrink: 0;
        }
        .daily-text h3 { font-size: 16px; font-weight: 700; color: var(--text); }
        .daily-text p { font-size: 12px; color: var(--text-dim); margin-top: 3px; }

        /* History card */
        .history-card { padding: 22px; }
        .history-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 16px;
        }
        .history-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
        }
        .history-count {
            font-size: 11px;
            color: var(--text-dim);
            background: rgba(255,255,255,0.06);
            padding: 3px 10px;
            border-radius: 50px;
        }

        #historyList { display: flex; flex-direction: column; gap: 8px; }
        .history-item {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 12px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px;
            transition: background 0.2s;
        }
        .history-item:hover { background: rgba(255,255,255,0.06); }
        .history-item .h-icon {
            width: 36px; height: 36px;
            border-radius: 10px;
            background: rgba(255,255,255,0.06);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        .history-item .h-info { flex: 1; min-width: 0; }
        .history-item .h-name {
            font-size: 13px; font-weight: 600; color: var(--text);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .history-item .h-date { font-size: 11px; color: var(--text-dim); margin-top: 2px; }
        .history-item .h-badge {
            font-size: 10px; font-weight: 700;
            padding: 3px 8px; border-radius: 50px;
            background: rgba(74,222,128,0.15);
            border: 1px solid rgba(74,222,128,0.3);
            color: #4ade80;
            flex-shrink: 0;
        }
        .history-empty {
            text-align: center;
            padding: 24px;
            color: var(--text-dim);
            font-size: 13px;
        }
        .history-empty .empty-icon { font-size: 32px; margin-bottom: 8px; opacity: 0.5; }

        /* Back link */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 28px;
            padding: 11px 22px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 50px;
            color: var(--text);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.25s;
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }

        /* ── Reward Popup ── */
        #popup-overlay {
            display: none;
            position: fixed; inset: 0; z-index: 1000;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
        }
        #popup-overlay.show { display: flex; }

        .reward-popup {
            background: #12132a;
            border: 1px solid rgba(255,215,0,0.3);
            border-radius: 28px;
            padding: 44px 40px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            position: relative;
            animation: popIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 0 60px rgba(255,215,0,0.2), 0 30px 80px rgba(0,0,0,0.6);
        }
        @keyframes popIn {
            from { opacity: 0; transform: scale(0.7); }
            to   { opacity: 1; transform: scale(1); }
        }

        .popup-confetti {
            position: absolute;
            top: -12px; left: 50%;
            transform: translateX(-50%);
            font-size: 40px;
            animation: float-confetti 2s ease-in-out infinite;
        }
        @keyframes float-confetti {
            0%,100% { transform: translateX(-50%) translateY(0) rotate(-5deg); }
            50%      { transform: translateX(-50%) translateY(-12px) rotate(5deg); }
        }

        .popup-icon {
            font-size: 80px;
            margin: 16px 0 12px;
            animation: bounce-icon 1.2s ease infinite;
        }
        @keyframes bounce-icon {
            0%,100% { transform: translateY(0); }
            50%      { transform: translateY(-16px); }
        }

        .popup-title {
            font-size: 26px;
            font-weight: 900;
            background: linear-gradient(90deg, var(--gold), #ff8a00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        .popup-msg {
            font-size: 15px;
            color: var(--text-dim);
            margin-bottom: 28px;
            line-height: 1.5;
        }
        .popup-close {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 13px 36px;
            background: linear-gradient(135deg, var(--gold), var(--gold2));
            border: none;
            border-radius: 50px;
            color: #000;
            font-size: 16px;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            transition: all 0.25s;
            box-shadow: 0 6px 24px rgba(255,170,0,0.35);
        }
        .popup-close:hover { transform: translateY(-2px) scale(1.04); }

        /* ── Not activated warning ── */
        .warn-box {
            padding: 24px;
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.25);
            border-radius: var(--radius);
            color: #f87171;
            font-size: 15px;
            font-weight: 600;
            text-align: center;
        }

        /* Spinning animation on wheel canvas */
        .wheel-spinning { filter: drop-shadow(0 0 20px rgba(255,215,0,0.7)); }

        /* scrollbar */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); border-radius: 10px; }
    </style>
</head>
<body>
    <div class="bg-aura"></div>
    <div class="stars"></div>

    <div id="app">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="logo-area">
                <div class="logo-icon">🎡</div>
                <div class="logo-text">
                    <h1>Vòng Quay May Mắn</h1>
                    <p>Lucky Wheel • GTLM Gaming</p>
                </div>
            </div>
            <div class="user-badge">
                <div class="avatar"><?= mb_substr($user['Name'], 0, 1) ?></div>
                <div class="info">
                    <div class="name"><?= htmlspecialchars($user['Name']) ?></div>
                    <div class="money">💰 <?= number_format($user['Money'], 0, ',', '.') ?> GTLM</div>
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="main-grid">

            <!-- LEFT: Wheel -->
            <div class="card wheel-card">
                <div class="spin-label">⭐ Thử Vận May Hôm Nay</div>
                <div class="spin-title">🎡 Lucky Wheel</div>

                <?php if (!$wheelExists): ?>
                    <div class="warn-box">
                        ⚠️ Hệ thống Lucky Wheel chưa được kích hoạt.<br>
                        <small>Vui lòng chạy file <strong>create_lucky_wheel_tables.sql</strong></small>
                    </div>
                <?php else: ?>
                    <div class="wheel-wrapper">
                        <!-- Glow ring -->
                        <div class="wheel-glow-ring"></div>
                        <div class="wheel-glow-ring-inner"></div>
                        <!-- Pointer -->
                        <div class="wheel-pointer">
                            <svg width="44" height="56" viewBox="0 0 44 56">
                                <defs>
                                    <linearGradient id="ptr" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#ff4444"/>
                                        <stop offset="100%" stop-color="#cc0000"/>
                                    </linearGradient>
                                </defs>
                                <polygon points="0,0 44,0 22,56" fill="url(#ptr)" stroke="#fff" stroke-width="2" stroke-linejoin="round"/>
                                <circle cx="22" cy="8" r="8" fill="#fff" opacity="0.3"/>
                            </svg>
                        </div>
                        <!-- Wheel -->
                        <canvas id="wheel" width="420" height="420"></canvas>
                        <!-- Center -->
                        <div class="wheel-center">✨</div>
                    </div>

                    <button id="spinButton" class="spin-btn">
                        <span class="btn-icon">🎡</span>
                        <span>Quay Ngay!</span>
                    </button>

                    <div id="spinStatus" class="spin-status spinning">
                        <span>⏳</span>
                        <span>Đang kiểm tra...</span>
                    </div>
                <?php endif; ?>

                <a href="index.php" class="back-btn">
                    <i class="fa fa-home"></i> Về Trang Chủ
                </a>
            </div>

            <!-- RIGHT: Sidebar -->
            <div class="sidebar">

                <!-- Daily Info -->
                <div class="card info-card">
                    <div class="info-card-title">🎁 Phần thưởng hàng ngày</div>
                    <div class="daily-badge">
                        <div class="daily-icon">🆓</div>
                        <div class="daily-text">
                            <h3>1 Lượt Quay Miễn Phí</h3>
                            <p>Reset lúc 00:00 mỗi ngày</p>
                        </div>
                    </div>
                    <div style="margin-top:16px; padding:12px; background:rgba(251,191,36,0.08); border:1px solid rgba(251,191,36,0.2); border-radius:12px; font-size:13px; color:rgba(251,191,36,0.9); line-height:1.5;">
                        💡 Mỗi ngày bạn có <strong>1 lượt quay miễn phí</strong>. Vòng quay có thể mang lại GTLM, items hiếm, và nhiều phần thưởng bí ẩn!
                    </div>
                </div>

                <!-- History -->
                <div class="card history-card">
                    <div class="history-header">
                        <div class="history-title">📜 Lịch sử quay</div>
                        <span class="history-count">10 lần gần nhất</span>
                    </div>
                    <div id="historyList">
                        <div class="history-empty">
                            <div class="empty-icon">📭</div>
                            Đang tải...
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Reward Popup Overlay -->
    <div id="popup-overlay">
        <div class="reward-popup">
            <div class="popup-confetti">🎊</div>
            <div class="popup-icon" id="rewardIcon">🎁</div>
            <div class="popup-title" id="rewardMessage">Chúc mừng!</div>
            <div class="popup-msg" id="rewardDetails"></div>
            <button class="popup-close" onclick="closeRewardPopup()">
                <i class="fa fa-check"></i> Tuyệt vời!
            </button>
        </div>
    </div>

    <script>
        let rewards = [];
        let canSpin = false;
        let isSpinning = false;
        let currentRotationDeg = 0;

        document.addEventListener('DOMContentLoaded', function () {
            const wheelExists = <?= $wheelExists ? 'true' : 'false' ?>;
            if (wheelExists) {
                checkSpinStatus();
                loadRewards();
                loadHistory();
            }
        });

        function setStatus(msg, type = 'spinning') {
            const el = document.getElementById('spinStatus');
            if (!el) return;
            el.className = 'spin-status ' + type;
            el.innerHTML = msg;
        }

        function checkSpinStatus() {
            fetch('api_lucky_wheel.php?action=check_spin')
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        canSpin = !data.has_spun;
                        const btn = document.getElementById('spinButton');
                        if (data.has_spun) {
                            btn.disabled = true;
                            setStatus('<span>❌</span><span>Đã quay hôm nay – quay lại vào ngày mai!</span>', 'no-spin');
                        } else {
                            btn.disabled = false;
                            setStatus('<span>✅</span><span>Sẵn sàng! Thử vận may ngay!</span>', 'can-spin');
                        }
                    }
                })
                .catch(() => setStatus('<span>⚠️</span><span>Không thể kết nối</span>', 'no-spin'));
        }

        function loadRewards() {
            fetch('api_lucky_wheel.php?action=get_rewards')
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        rewards = data.rewards || [];
                        drawWheel();
                    }
                })
                .catch(() => console.error('Không thể tải phần thưởng'));
        }

        const PREMIUM_COLORS = [
            ['#a855f7','#9333ea'],['#f59e0b','#d97706'],['#3b82f6','#2563eb'],
            ['#ef4444','#dc2626'],['#10b981','#059669'],['#ec4899','#db2777'],
            ['#06b6d4','#0891b2'],['#8b5cf6','#7c3aed'],['#f97316','#ea580c'],
            ['#14b8a6','#0d9488'],['#e11d48','#be123c'],['#6366f1','#4f46e5'],
        ];

        function drawWheel() {
            const canvas = document.getElementById('wheel');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const W = canvas.width, H = canvas.height;
            const cx = W / 2, cy = H / 2;
            const R = W / 2 - 6;

            ctx.clearRect(0, 0, W, H);

            if (!rewards || rewards.length === 0) {
                ctx.beginPath();
                ctx.arc(cx, cy, R, 0, 2 * Math.PI);
                ctx.fillStyle = '#1e1e3f';
                ctx.fill();
                ctx.fillStyle = '#fff';
                ctx.font = 'bold 16px Outfit, Arial';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('Đang tải...', cx, cy);
                return;
            }

            const n = rewards.length;
            const sliceAngle = (2 * Math.PI) / n;

            rewards.forEach((reward, i) => {
                const startAngle = i * sliceAngle - Math.PI / 2;
                const endAngle   = startAngle + sliceAngle;
                const midAngle   = startAngle + sliceAngle / 2;

                const [c1, c2] = PREMIUM_COLORS[i % PREMIUM_COLORS.length];

                // Draw sector with gradient
                const gx1 = cx + Math.cos(midAngle) * R * 0.25;
                const gy1 = cy + Math.sin(midAngle) * R * 0.25;
                const gx2 = cx + Math.cos(midAngle) * R * 0.95;
                const gy2 = cy + Math.sin(midAngle) * R * 0.95;
                const grad = ctx.createLinearGradient(gx1, gy1, gx2, gy2);
                grad.addColorStop(0, c1);
                grad.addColorStop(1, c2);

                ctx.beginPath();
                ctx.moveTo(cx, cy);
                ctx.arc(cx, cy, R, startAngle, endAngle);
                ctx.closePath();
                ctx.fillStyle = grad;
                ctx.fill();
                ctx.strokeStyle = 'rgba(255,255,255,0.4)';
                ctx.lineWidth = 2;
                ctx.stroke();

                // ── Draw text (always readable, pointing outward from center) ──
                ctx.save();
                ctx.translate(cx, cy);
                ctx.rotate(midAngle);           // align with sector center
                // now +x points toward outer edge of this sector

                const iconSize  = Math.max(14, Math.min(20, 260 / n));
                const textSize  = Math.max(10, Math.min(13, 200 / n));
                const iconR     = R * 0.72;     // icon distance from center
                const textR     = R * 0.50;     // text distance from center

                // Icon
                const icon = reward.icon || '';
                if (icon) {
                    ctx.font = `${iconSize}px Arial`;
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(icon, iconR, 0);
                }

                // Reward name
                let name = (reward.reward_name || 'N/A').trim();
                // Trim if too long
                const maxChars = Math.max(6, Math.round(260 / n));
                if (name.length > maxChars) name = name.slice(0, maxChars - 1) + '…';

                ctx.font = `bold ${textSize}px Outfit, Arial, sans-serif`;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                // Shadow for readability
                ctx.strokeStyle = 'rgba(0,0,0,0.8)';
                ctx.lineWidth = 4;
                ctx.lineJoin = 'round';
                ctx.strokeText(name, textR, 0);
                ctx.fillStyle = '#ffffff';
                ctx.fillText(name, textR, 0);

                ctx.restore();
            });

            // Outer decorative ring
            ctx.beginPath();
            ctx.arc(cx, cy, R, 0, 2 * Math.PI);
            ctx.strokeStyle = 'rgba(255,255,255,0.25)';
            ctx.lineWidth = 3;
            ctx.stroke();

            // Center circle
            const cg = ctx.createRadialGradient(cx, cy, 0, cx, cy, 32);
            cg.addColorStop(0, '#ffffff');
            cg.addColorStop(1, '#d0d0d0');
            ctx.beginPath();
            ctx.arc(cx, cy, 30, 0, 2 * Math.PI);
            ctx.fillStyle = cg;
            ctx.fill();
            ctx.strokeStyle = 'rgba(255,255,255,0.6)';
            ctx.lineWidth = 2;
            ctx.stroke();

            // Star in center
            ctx.font = '22px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('✨', cx, cy);
        }


        function spinWheel() {
            if (isSpinning || !canSpin) return;
            isSpinning = true;
            const btn = document.getElementById('spinButton');
            btn.disabled = true;
            setStatus('<span>⏳</span><span>Đang quay vòng may mắn...</span>', 'spinning');

            fetch('api_lucky_wheel.php?action=spin', { method: 'POST' })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        const wheel = document.getElementById('wheel');
                        currentRotationDeg += data.angle + 360 * 5; // at least 5 full spins
                        wheel.style.transition = 'transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99)';
                        wheel.style.transform = `rotate(${currentRotationDeg}deg)`;
                        wheel.classList.add('wheel-spinning');

                        setTimeout(() => {
                            wheel.classList.remove('wheel-spinning');
                            showRewardPopup(data.reward, data.message);
                            checkSpinStatus();
                            loadHistory();
                            isSpinning = false;
                            if (data.reward_given) {
                                setTimeout(() => window.location.reload(), 3500);
                            }
                        }, 4200);
                    } else {
                        alert('❌ ' + (data.message || 'Có lỗi xảy ra'));
                        isSpinning = false;
                        btn.disabled = false;
                        checkSpinStatus();
                    }
                })
                .catch(() => {
                    alert('❌ Có lỗi xảy ra khi quay wheel!');
                    isSpinning = false;
                    btn.disabled = false;
                    checkSpinStatus();
                });
        }

        function showRewardPopup(reward, message) {
            document.getElementById('rewardIcon').textContent = reward.icon || '🎁';
            document.getElementById('rewardMessage').textContent = (reward.reward_value > 0) ? '🎉 Chúc mừng!' : '😢 Chúc may mắn lần sau!';
            document.getElementById('rewardDetails').textContent = message;
            document.getElementById('popup-overlay').classList.add('show');
        }

        function closeRewardPopup() {
            document.getElementById('popup-overlay').classList.remove('show');
        }

        document.getElementById('spinButton')?.addEventListener('click', spinWheel);
        document.getElementById('popup-overlay')?.addEventListener('click', function(e) {
            if (e.target === this) closeRewardPopup();
        });

        function loadHistory() {
            fetch('api_lucky_wheel.php?action=get_history')
                .then(r => r.json())
                .then(data => {
                    const list = document.getElementById('historyList');
                    if (!list) return;
                    if (!data.history || data.history.length === 0) {
                        list.innerHTML = `<div class="history-empty"><div class="empty-icon">📭</div>Chưa có lịch sử quay</div>`;
                        return;
                    }
                    list.innerHTML = data.history.map(item => {
                        const d = new Date(item.spun_at);
                        const dateStr = d.toLocaleDateString('vi-VN') + ' ' + d.toLocaleTimeString('vi-VN', { hour:'2-digit', minute:'2-digit' });
                        return `
                        <div class="history-item">
                            <div class="h-icon">${item.icon || '🎁'}</div>
                            <div class="h-info">
                                <div class="h-name">${item.reward_name}</div>
                                <div class="h-date">${dateStr}</div>
                            </div>
                            <span class="h-badge">+${Number(item.reward_value || 0).toLocaleString('vi-VN')}</span>
                        </div>`;
                    }).join('');
                })
                .catch(() => {});
        }
    </script>

    <!-- Premium Effects System -->
    <canvas id="threejs-background"></canvas>
    <script>
        (function() {
            window.themeConfig = {
                particleCount: <?= $particleCount ?? 800 ?>,
                particleSize: <?= $particleSize ?? 0.05 ?>,
                particleColor: '<?= $particleColor ?? "#ffffff" ?>',
                particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
                shapeCount: <?= $shapeCount ?? 10 ?>,
                shapeColors: <?= json_encode($shapeColors ?? ["#667eea", "#764ba2", "#4facfe", "#00f2fe"]) ?>,
                shapeOpacity: <?= $shapeOpacity ?? 0.3 ?>,
                bgGradient: <?= json_encode($bgGradient ?? ["#667eea", "#764ba2", "#4facfe"]) ?>
            };
            const prefix = window.location.pathname.includes('/games/') ? '../' : '';
            ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'].forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();
    </script>
</body>
</html>