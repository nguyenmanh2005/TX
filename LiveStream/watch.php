<?php
/**
 * 📺 Phòng Xem Live Stream v3.0 — Chế Độ Chỉ Xem (Spectator Only)
 * Nhúng bàn game thật (games/*.php) ở Chế Độ Chỉ Xem Live, khóa toàn bộ thao tác đặt cược/xóc trong iframe.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../load_theme.php';

$userId = (int)$_SESSION['Iduser'];
$tableId = isset($_GET['id']) ? (int)$_GET['id'] : 1;
if ($tableId < 1 || $tableId > 5) $tableId = 1;

$gameFilesMap = [
    1 => ['file' => 'live_baucua.php', 'real_game' => '../games/baucua.php', 'name' => 'Thế Giới Linh Thú', 'icon' => '🐾'],
    2 => ['file' => 'live_xocdia.php', 'real_game' => '../games/xocdia.php', 'name' => 'Trận Địa Trắng Đỏ', 'icon' => '🎲'],
    3 => ['file' => 'live_crash.php', 'real_game' => '../games/crash.php', 'name' => 'Tiên Tri Vũ Trụ', 'icon' => '🚀'],
    4 => ['file' => 'live_daga.php', 'real_game' => '../games/daga.php', 'name' => 'Đại Chiến Thần Kê', 'icon' => '🐓'],
    5 => ['file' => 'live_dragontiger.php', 'real_game' => '../games/dragontiger.php', 'name' => 'Chiến Trường Rồng Hổ', 'icon' => '🐉']
];

$botThemesMap = [
    1 => ['particleColor' => '#00ff88', 'shapeColors' => ["#00ff88", "#00b894", "#fdcb6e"], 'bgGradient' => ["#000000", "#001a11", "#002a1b"]],
    2 => ['particleColor' => '#00f2fe', 'shapeColors' => ["#00f2fe", "#712cf9", "#ff4757"], 'bgGradient' => ["#000000", "#050015", "#0a0025"]],
    3 => ['particleColor' => '#ff4757', 'shapeColors' => ["#ff4757", "#ff6b81", "#70a1ff"], 'bgGradient' => ["#000000", "#12001a", "#250033"]],
    4 => ['particleColor' => '#ff7f50', 'shapeColors' => ["#ff4757", "#ff7f50", "#ffd700"], 'bgGradient' => ["#000000", "#1a0500", "#2d0a00"]],
    5 => ['particleColor' => '#f1c40f', 'shapeColors' => ["#f1c40f", "#e67e22", "#3498db"], 'bgGradient' => ["#000000", "#1a1500", "#2d2400"]]
];

$currentGame = $gameFilesMap[$tableId];
$currentBotTheme = $botThemesMap[$tableId];

// Lấy số dư người dùng
$stmtUser = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$userRow = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

$userMoney = (float)($userRow['Money'] ?? 0);
$userName = $userRow['Name'] ?? 'Đạo Hữu';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Chỉ Xem Live 24/7 - Trận Địa GTLM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        html, body, div, p, span, section, header, footer, aside, nav, table, tr, td, iframe, canvas {
            cursor: url('../img/chuot.png'), default;
        }
        a, button, input, select, textarea, label, .btn, [role="button"], [onclick], .clickable, .btn-react, .btn-back, .table-selector, .tiktok-gift-card, .btn-quick-tip, .swal2-confirm, .swal2-cancel, .swal2-close,
        a *, button *, [onclick] *, .tiktok-gift-card *, .btn-quick-tip *, .btn-react *, .swal2-popup button, .swal2-popup [onclick], .swal2-popup div[onclick] {
            cursor: url('../img/tay.png'), pointer !important;
        }
        #threejs-background { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; pointer-events: none; }
        :root {
            --bg-main: #0b0f19;
            --panel-bg: rgba(30, 41, 59, 0.85);
            --panel-border: rgba(255, 255, 255, 0.1);
            --primary: #6366f1;
            --purple: #a855f7;
            --gold: #fbbf24;
            --emerald: #10b981;
            --rose: #f43f5e;
            --cyan: #06b6d4;
            --text-main: #f8fafc;
            --text-sub: #94a3b8;
        }

        * { box-sizing: border-box; }

        body {
            background: var(--bg-main);
            color: var(--text-main);
            font-family: 'Outfit', 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
            <?= isset($bgGradientCSS) ? "background-image: $bgGradientCSS; background-attachment: fixed;" : "" ?>
        }

        /* Top Header Bar */
        .watch-header {
            height: 60px;
            background: rgba(11, 15, 25, 0.95);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--panel-border);
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 50;
        }

        .btn-back {
            color: var(--text-sub);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.85rem;
            display: flex; align-items: center; gap: 8px;
        }
        .btn-back:hover { color: #fff; }

        .table-selector {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--panel-border);
            color: #fff;
            padding: 6px 12px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 0.85rem;
            outline: none;
            cursor: pointer;
        }

        .user-balance-chip {
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 12px;
            padding: 5px 12px;
            font-weight: 800;
            color: var(--emerald);
            font-size: 0.85rem;
            display: flex; align-items: center; gap: 6px;
        }

        /* Layout Grid */
        .watch-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            height: calc(100vh - 60px);
        }

        /* Player Section */
        .player-section {
            display: flex;
            flex-direction: column;
            background: #000;
            position: relative;
            overflow-y: auto;
        }

        /* 🎮 Real Game Embedded Viewport (Spectator Only) */
        .video-viewport {
            flex: 1;
            position: relative;
            background: #000;
            min-height: 420px;
            overflow: hidden;
        }

        /* 🔒 Khóa tương tác cược trực tiếp trong iframe khi xem live */
        .game-iframe {
            width: 100%;
            height: 100%;
            border: none;
            background: #0b0f19;
            pointer-events: none; /* Khóa tương tác chuột trong iframe */
        }

        /* Live HUD Overlay Badges */
        .live-hud-badge {
            position: absolute;
            top: 15px; left: 15px;
            background: rgba(244, 63, 94, 0.9);
            backdrop-filter: blur(12px);
            color: #fff;
            padding: 4px 12px;
            border-radius: 8px;
            font-weight: 900;
            font-size: 0.8rem;
            display: flex; align-items: center; gap: 6px;
            z-index: 30;
            box-shadow: 0 4px 15px rgba(244, 63, 94, 0.4);
        }

        .viewers-hud-pill {
            position: absolute;
            top: 15px; right: 15px;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(12px);
            color: #fff;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 800;
            border: 1px solid rgba(255, 255, 255, 0.15);
            z-index: 30;
        }

        /* 👁️ Banner Thông Báo Chế Độ Chỉ Xem */
        .spectator-mode-banner {
            position: absolute;
            top: 60px; left: 50%;
            transform: translateX(-50%);
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(99, 102, 241, 0.4);
            color: #fff;
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 800;
            z-index: 35;
            box-shadow: 0 4px 20px rgba(0,0,0,0.6);
            display: flex; align-items: center; gap: 8px;
        }

        /* 🎁 Banner Thông Báo Tip GTLM Vinh Danh Streamer Bot */
        .tip-notification-banner {
            position: absolute;
            top: 105px; left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.95), rgba(6, 182, 212, 0.95));
            backdrop-filter: blur(12px);
            border: 2px solid #5eead4;
            color: #fff;
            padding: 8px 24px;
            border-radius: 30px;
            font-size: 0.88rem;
            font-weight: 900;
            z-index: 36;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.6), 0 0 20px rgba(6, 182, 212, 0.5);
            display: flex; align-items: center; gap: 8px;
            text-align: center;
            white-space: nowrap;
            animation: tipBannerPulse 0.5s ease-out;
        }
        @keyframes tipBannerPulse {
            0% { transform: translate(-50%, -15px) scale(0.85); opacity: 0; }
            60% { transform: translate(-50%, 5px) scale(1.05); opacity: 1; }
            100% { transform: translate(-50%, 0) scale(1); opacity: 1; }
        }

        /* 🎁 TikTok Live Gift Store & FX Styles */
        .tiktok-gift-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            padding: 12px 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .tiktok-gift-card:hover {
            background: rgba(236, 72, 153, 0.18);
            border-color: #ec4899;
            transform: translateY(-4px) scale(1.04);
            box-shadow: 0 10px 25px rgba(236, 72, 153, 0.4);
        }
        .tiktok-gift-card.vip-castle {
            background: linear-gradient(135deg, rgba(234, 179, 8, 0.18), rgba(236, 72, 153, 0.18));
            border-color: #f59e0b;
        }
        .gift-icon-wrap { font-size: 2.4rem; margin-bottom: 4px; }
        .gift-title { font-weight: 800; font-size: 0.85rem; color: #fff; margin-bottom: 3px; }
        .gift-price { font-size: 0.75rem; color: #f472b6; font-weight: 900; font-family: 'Orbitron', sans-serif; }

        .tiktok-fx-container {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            pointer-events: none;
            z-index: 9999999;
            overflow: hidden;
        }

        /* 🎁 Rain Down From Top To Bottom Gift FX Animations */
        @keyframes giftRainDown {
            0% { transform: translateY(-130px) rotate(0deg) scale(0.6); opacity: 0; }
            15% { opacity: 1; transform: translateY(12vh) rotate(15deg) scale(1.4); }
            85% { opacity: 1; transform: translateY(85vh) rotate(-15deg) scale(1.2); }
            100% { transform: translateY(115vh) rotate(30deg) scale(0.8); opacity: 0; }
        }

        @keyframes giftCrownDropBig {
            0% { transform: translate(-50%, -300px) scale(0.2); opacity: 0; }
            50% { transform: translate(-50%, 25vh) scale(1.5); opacity: 1; }
            75% { transform: translate(-50%, 22vh) scale(1.1); opacity: 1; }
            100% { transform: translate(-50%, 25vh) scale(1.3); opacity: 1; }
        }

        @keyframes giftRocketBlastDown {
            0% { transform: translate(-50%, -200px) scale(0.6); opacity: 1; }
            100% { transform: translate(-50%, 115vh) scale(1.6); opacity: 0; }
        }

        @keyframes giftCarSpeedFull {
            0% { transform: translate(-400px, 35vh); opacity: 0; }
            15% { opacity: 1; }
            85% { opacity: 1; }
            100% { transform: translate(calc(100vw + 400px), 35vh); opacity: 0; }
        }

        @keyframes giftCastleRiseCenter {
            0% { transform: translate(-50%, -300px) scale(0); opacity: 0; }
            60% { transform: translate(-50%, 20vh) scale(1.3); opacity: 1; }
            100% { transform: translate(-50%, 20vh) scale(1.1); opacity: 1; }
        }

        /* Floating Bot Feed Overlay on Video */
        .live-bot-feed-overlay {
            position: absolute;
            bottom: 20px; left: 20px;
            background: rgba(11, 15, 25, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 8px 14px;
            font-size: 0.8rem;
            color: #fff;
            z-index: 35;
            max-width: 320px;
            pointer-events: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
        }

        /* Floating Emoji Overlay */
        .emoji-overlay {
            position: absolute;
            bottom: 20px; right: 30px;
            width: 120px; height: 350px;
            pointer-events: none; z-index: 40;
        }

        .floating-emoji {
            position: absolute; bottom: 0; font-size: 28px;
            animation: floatUp 2.8s ease-out forwards;
        }

        @keyframes floatUp {
            0% { transform: translateY(0) scale(0.6); opacity: 0; }
            15% { opacity: 1; transform: translateY(-30px) scale(1.3); }
            100% { transform: translateY(-350px) translateX(calc(Math.random() * 60px - 30px)) scale(0.8); opacity: 0; }
        }

        /* Action Controls Dock */
        .action-dock {
            background: rgba(15, 23, 42, 0.98);
            backdrop-filter: blur(16px);
            border-top: 1px solid var(--panel-border);
            padding: 12px 20px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 15px; flex-wrap: wrap; z-index: 50;
        }

        .btn-react {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--panel-border);
            color: #fff; padding: 6px 12px; border-radius: 20px;
            cursor: pointer; font-size: 1.2rem; transition: all 0.2s;
        }
        .btn-react:hover { transform: scale(1.2); background: rgba(255, 255, 255, 0.15); }

        /* Sidebar Chat */
        .sidebar-chat {
            background: var(--panel-bg);
            backdrop-filter: blur(12px);
            border-left: 1px solid var(--panel-border);
            display: flex; flex-direction: column; height: 100%;
        }

        .chat-header {
            padding: 14px 18px; border-bottom: 1px solid var(--panel-border);
            font-weight: 900; font-size: 0.9rem;
            display: flex; align-items: center; justify-content: space-between;
            color: var(--purple);
        }

        .chat-body {
            flex: 1; overflow-y: auto; padding: 15px;
            display: flex; flex-direction: column; gap: 10px;
        }

        .chat-line { font-size: 0.85rem; line-height: 1.4; word-break: break-word; }
        .chat-user { font-weight: 800; color: var(--purple); margin-right: 4px; }

        .chat-input-area {
            padding: 12px 15px; background: rgba(0, 0, 0, 0.4);
            border-top: 1px solid var(--panel-border); display: flex; gap: 8px;
        }

        .chat-input-area input {
            flex: 1; background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--panel-border); color: #fff;
            padding: 8px 12px; border-radius: 10px; font-size: 0.85rem; outline: none;
        }

        .btn-send-msg {
            background: var(--primary); color: #fff; border: none;
            border-radius: 10px; padding: 0 16px; font-weight: 800; cursor: pointer;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            body { overflow: auto; }
            .watch-layout { grid-template-columns: 1fr; height: auto; }
            .sidebar-chat { height: 380px; }
            .action-dock { padding: 10px 12px; gap: 8px; }
            .btn-react { font-size: 1rem; padding: 4px 8px; }
            .spectator-mode-banner { display: none; }
        }
    </style>
</head>
<body>

    <!-- 🎬 Full Screen Top-to-Bottom Falling Gift FX Container (Z-Index 9999999) -->
    <div id="tiktokGiftFXContainer" class="tiktok-fx-container"></div>

    <!-- Top Header Navigation Bar -->
    <header class="watch-header">
        <a href="spectator.php" class="btn-back">
            <i class="fa fa-arrow-left"></i> Rời Phòng Live
        </a>

        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 0.85rem; color: var(--text-sub); font-weight: 700;">Đổi Bàn Live:</span>
            <select class="table-selector" id="tableSelect" onchange="switchTable(this.value)">
                <option value="1">🐾 Thế Giới Linh Thú</option>
                <option value="2">🎲 Trận Địa Trắng Đỏ</option>
                <option value="3">🚀 Tiên Tri Vũ Trụ</option>
                <option value="4">🐓 Đại Chiến Thần Kê</option>
                <option value="5">🐉 Chiến Trường Rồng Hổ</option>
            </select>
        </div>

        <div class="user-balance-chip">
            <i class="fa fa-wallet"></i>
            <span><?= number_format($userMoney) ?> GTLM</span>
        </div>
    </header>

    <!-- Theater Grid Layout -->
    <div class="watch-layout">

        <!-- Player Section -->
        <div class="player-section">

            <!-- 🎮 Real Embedded Game Viewport (Spectator Mode Only) -->
            <div class="video-viewport" id="videoViewport">
                <!-- Live HUD Badge -->
                <div class="live-hud-badge">
                    <span style="width:8px; height:8px; background:#fff; border-radius:50%; display:inline-block;"></span> TRỰC TIẾP 24/7
                </div>

                <!-- Viewers Badge -->
                <div class="viewers-hud-pill" id="viewersPill">
                    <i class="fa fa-user"></i> <span id="viewersCount">320</span> Người xem
                </div>

                <!-- 👁️ Banner Thông Báo Chế Độ Chỉ Xem -->
                <div class="spectator-mode-banner">
                    <i class="fa fa-eye" style="color:var(--cyan)"></i> CHẾ ĐỘ CHỈ XEM LIVE 24/7 (ĐÃ KHÓA BÀN CƯỢC)
                </div>

                <!-- 🎁 Banner Thông Báo Tip GTLM Vinh Danh Streamer Bot -->
                <div id="tipNotificationBanner" class="tip-notification-banner" style="display: none;">
                    <i class="fa fa-gift" style="color: #fef08a; font-size: 1.1rem;"></i>
                    <span id="tipBannerText">🎉 <b>Tuấn Mạnh</b> vừa Tip vinh danh Streamer <b>bot_baucua</b> +50,000 GTLM! ❤️</span>
                </div>

                <!-- Real Game Iframe (Bản LiveStream Riêng 24/7) -->
                <iframe src="<?= htmlspecialchars($currentGame['file']) ?>" class="game-iframe" id="gameFrame" title="Real Game Live"></iframe>

                <!-- Live Bot Auto-Play Feed Overlay -->
                <div class="live-bot-feed-overlay" id="botFeedOverlay">
                    ⚡ <span style="color:var(--cyan); font-weight:800;" id="feedBotName">Thánh Húp Lộc</span> vừa Ra Chiêu <b style="color:var(--gold);" id="feedBotBet">20,000 GTLM</b>
                </div>

                <!-- Floating Emoji Overlay -->
                <div class="emoji-overlay" id="emojiOverlay"></div>
            </div>

            <!-- Action Controls Dock -->
            <div class="action-dock">
                <div style="display: flex; gap: 8px;">
                    <button class="btn-react" onclick="sendReaction('❤️')">❤️</button>
                    <button class="btn-react" onclick="sendReaction('🔥')">🔥</button>
                    <button class="btn-react" onclick="sendReaction('🤣')">🤣</button>
                    <button class="btn-react" onclick="sendReaction('💸')">💸</button>
                    <button class="btn-react" onclick="sendReaction('👏')">👏</button>
                    <button class="btn-react" onclick="sendReaction('🚀')">🚀</button>
                </div>

                <div style="display: flex; gap: 10px; align-items: center;">
                    <button style="background: rgba(251, 191, 36, 0.15); border: 1px solid var(--gold); color: var(--gold); border-radius: 12px; padding: 10px 18px; font-weight: 800; cursor: pointer;" onclick="openTipModal()">
                        <i class="fa fa-coins"></i> TIP GTLM
                    </button>

                    <button style="background: linear-gradient(135deg, rgba(236, 72, 153, 0.2), rgba(168, 85, 247, 0.2)); border: 1px solid #ec4899; color: #f472b6; border-radius: 12px; padding: 10px 18px; font-weight: 900; cursor: pointer; box-shadow: 0 0 15px rgba(236,72,153,0.3);" onclick="openGiftStoreModal()">
                        <i class="fa fa-gift"></i> VẬT PHẨM VINH DANH
                    </button>

                    <button onclick="confirmPlayNow('<?= htmlspecialchars($currentGame['real_game']) ?>', '<?= htmlspecialchars($currentGame['name']) ?>')" style="background: linear-gradient(135deg, var(--primary), var(--purple)); color: #fff; border: none; border-radius: 12px; padding: 10px 18px; font-weight: 900; cursor: pointer; font-size: 0.85rem; box-shadow: 0 4px 15px rgba(99,102,241,0.4);">
                        <i class="fa fa-gamepad"></i> VÀO TỰ CHƠI THẢ THÍNH
                    </button>
                </div>
            </div>

        </div>

        <!-- Right Chat Sidebar -->
        <div class="sidebar-chat">
            <div class="chat-header">
                <span><i class="fa fa-comments"></i> CHAT TRỰC TIẾP LIVE</span>
                <span style="font-size: 0.75rem; color: var(--emerald);"><i class="fa fa-circle" style="font-size: 0.5rem;"></i> LIVE 24/7</span>
            </div>

            <div class="chat-body" id="chatBody">
                <div class="chat-line"><span class="chat-user">Vệ Binh Trận Địa:</span> Chúc đạo hữu xem live vui vẻ! Đã bật Chế Độ Chỉ Xem Live 24/7 (Đã khóa bàn cược).</div>
            </div>

            <div class="chat-input-area">
                <input type="text" id="chatInput" placeholder="Nhập tin nhắn chat..." onkeypress="if(event.key==='Enter') sendChatMsg()">
                <button class="btn-send-msg" onclick="sendChatMsg()">GỬI</button>
            </div>
        </div>

    </div>

    <script>
        let currentTableId = <?= $tableId ?>;
        let lastChatId = 0;
        let processedReactions = new Set();
        const botNames = ['Thánh Húp Lộc', 'Tu Tiên Cụ', 'Mãnh Hổ 999', 'Lão Tiên Tri', 'Bá Vương Trận Địa', 'Kê Vương 888'];

        function switchTable(newId) {
            window.location.href = 'watch.php?id=' + newId;
        }

        function loadDetails() {
            $.get('api_spectator.php?action=get_table_detail&table_id=' + currentTableId, function(res) {
                if (res.success) {
                    $('#tableSelect').val(currentTableId);
                    if (res.table && res.table.viewers) {
                        $('#viewersCount').text(res.table.viewers);
                    }

                    // Feed cược bot nổi
                    if (Math.random() < 0.5) {
                        const rName = botNames[Math.floor(Math.random() * botNames.length)];
                        const rMoney = Math.floor(Math.random() * 8 + 1) * 10000;
                        $('#feedBotName').text(rName);
                        $('#feedBotBet').text(new Intl.NumberFormat().format(rMoney) + ' GTLM');
                    }

                    // Render Chat
                    if (res.chats) {
                        res.chats.forEach(chat => {
                            if (chat.id > lastChatId) {
                                $('#chatBody').append(`
                                    <div class="chat-line">
                                        <span class="chat-user">${chat.user_name}:</span>
                                        <span>${chat.message}</span>
                                    </div>
                                `);
                                lastChatId = chat.id;
                                scrollToBottom();
                            }
                        });
                    }

                    // Render Emojis
                    if (res.reactions) {
                        res.reactions.forEach(r => {
                            if (!processedReactions.has(r.id)) {
                                spawnEmoji(r.emoji);
                                processedReactions.add(r.id);
                            }
                        });
                        if (processedReactions.size > 80) processedReactions.clear();
                    }
                }
            });
        }

        function scrollToBottom() {
            const el = document.getElementById('chatBody');
            el.scrollTop = el.scrollHeight;
        }

        function spawnEmoji(emoji) {
            const id = 'emoji-' + Math.random().toString(36).substr(2, 9);
            const left = Math.random() * 80;
            $('#emojiOverlay').append(`<div class="floating-emoji" id="${id}" style="left: ${left}px;">${emoji}</div>`);
            setTimeout(() => { $('#' + id).remove(); }, 2800);
        }

        function sendReaction(emoji) {
            $.post('api_spectator.php', { action: 'send_reaction', table_id: currentTableId, emoji: emoji });
            spawnEmoji(emoji);
        }

        function sendChatMsg() {
            const msg = $('#chatInput').val().trim();
            if (!msg) return;
            $('#chatInput').val('');
            $.post('api_spectator.php', { action: 'send_chat', table_id: currentTableId, message: msg }, function() {
                loadDetails();
            });
        }

        // Khởi tạo và load sẵn giọng đọc trình duyệt khi tải trang
        if ('speechSynthesis' in window) {
            window.speechSynthesis.getVoices();
            if (speechSynthesis.onvoiceschanged !== undefined) {
                speechSynthesis.onvoiceschanged = () => speechSynthesis.getVoices();
            }
        }

        function speakStreamerVoice(text) {
            if (!text) return;
            const cleanText = text.replace(/[*#_`~]/g, '').trim();
            if (!cleanText) return;

            if ('speechSynthesis' in window) {
                try {
                    window.speechSynthesis.cancel();
                    
                    const utterance = new SpeechSynthesisUtterance(cleanText);
                    utterance.lang = 'vi-VN';
                    utterance.rate = 1.0;
                    utterance.pitch = 1.0;

                    const voices = window.speechSynthesis.getVoices();
                    if (voices && voices.length > 0) {
                        const viVoice = voices.find(v => v.lang && (v.lang.toLowerCase().includes('vi') || v.name.toLowerCase().includes('vietnam') || v.name.toLowerCase().includes('tiếng việt')));
                        if (viVoice) {
                            utterance.voice = viVoice;
                        }
                    }

                    window.speechSynthesis.speak(utterance);
                } catch(e) {
                    console.log("Speech synthesis error:", e);
                }
            }
        }

        function setTipAmount(amt) {
            $('#customTipInput').val(amt);
            $('.btn-quick-tip').css('border-color', 'rgba(255,255,255,0.15)');
            $(event.target).css('border-color', '#10b981');
        }

        function showTipNotification(userName, botName, amountFormatted, speechText) {
            const text = `🎉 <b style="color:#fef08a;">${userName}</b> vừa Tip vinh danh Streamer <b style="color:#67e8f9;">${botName}</b> +<b style="color:#fef08a;">${amountFormatted}</b> GTLM! ❤️`;
            $('#tipBannerText').html(text);
            const banner = $('#tipNotificationBanner');
            banner.stop(true, true).css('display', 'flex').hide().fadeIn(300);

            if (speechText) {
                speakStreamerVoice(speechText);
            }

            if (window.GameEffects && window.GameEffects.showWin) {
                window.GameEffects.showWin(parseInt(amountFormatted.replace(/\./g, '')) || 50000);
            }

            setTimeout(() => {
                banner.fadeOut(800);
            }, 5500);
        }

        function openTipModal() {
            Swal.fire({
                title: '💰 TIP GTLM CỔ VŨ',
                html: `
                    <div style="margin-bottom: 12px; font-size: 0.9rem; color: #94a3b8;">Chọn nhanh số GTLM Tip vinh danh Streamer:</div>
                    <div class="quick-tip-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 15px;">
                        <button type="button" class="btn-quick-tip" onclick="setTipAmount(10000)" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 8px; border-radius: 8px; font-weight: 800; cursor: pointer; font-size: 0.85rem;">10K</button>
                        <button type="button" class="btn-quick-tip" onclick="setTipAmount(50000)" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 8px; border-radius: 8px; font-weight: 800; cursor: pointer; font-size: 0.85rem;">50K</button>
                        <button type="button" class="btn-quick-tip" onclick="setTipAmount(100000)" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 8px; border-radius: 8px; font-weight: 800; cursor: pointer; font-size: 0.85rem;">100K</button>
                        <button type="button" class="btn-quick-tip" onclick="setTipAmount(500000)" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 8px; border-radius: 8px; font-weight: 800; cursor: pointer; font-size: 0.85rem;">500K</button>
                        <button type="button" class="btn-quick-tip" onclick="setTipAmount(1000000)" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 8px; border-radius: 8px; font-weight: 800; cursor: pointer; font-size: 0.85rem;">1M</button>
                        <button type="button" class="btn-quick-tip" onclick="setTipAmount(5000000)" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 8px; border-radius: 8px; font-weight: 800; cursor: pointer; font-size: 0.85rem;">5M</button>
                        <button type="button" class="btn-quick-tip" onclick="setTipAmount(10000000)" style="background: linear-gradient(135deg, #fbbf24, #f59e0b); border: none; color: #000; padding: 8px; border-radius: 8px; font-weight: 900; cursor: pointer; font-size: 0.85rem; grid-column: span 2;">10M (VIP)</button>
                    </div>
                    <input type="number" id="customTipInput" class="swal2-input" value="10000" min="1000" step="1000" placeholder="Hoặc nhập số GTLM..." style="width: 100%; margin: 0; box-sizing: border-box; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.2); color: #fff; text-align: center; font-weight: 800;">
                `,
                showCancelButton: true,
                confirmButtonText: 'GỬI TIP! ❤️',
                confirmButtonColor: '#10b981',
                cancelButtonText: 'Hủy',
                background: '#0f172a',
                color: '#f8fafc',
                preConfirm: () => {
                    const val = parseInt($('#customTipInput').val());
                    if (!val || val < 1000) {
                        Swal.showValidationMessage('Vui lòng nhập số GTLM tối thiểu từ 1,000 GTLM!');
                        return false;
                    }
                    return val;
                }
            }).then((res) => {
                if (res.isConfirmed && res.value) {
                    $.post('api_spectator.php', { action: 'tip', table_id: currentTableId, amount: res.value }, function(data) {
                        if (data.success) {
                            if (data.newMoney) $('#userMoneyDisplay').text(data.newMoney);
                            showTipNotification(data.userName, data.streamerName, data.amountFormatted, data.speechText);
                            loadDetails();
                        } else {
                            Swal.fire({
                                title: 'LỖI TIP',
                                text: data.message,
                                icon: 'error',
                                background: '#0f172a',
                                color: '#f8fafc'
                            });
                        }
                    });
                }
            });
        }

        function openGiftStoreModal() {
            Swal.fire({
                title: '🎁 CỬA HÀNG VẬT PHẨM VINH DANH STREAMER',
                html: `
                    <div style="font-size:0.85rem; color:#94a3b8; margin-bottom:12px;">Chọn quà vinh danh gửi trực tiếp cho Streamer Bot:</div>
                    <div class="tiktok-gift-store-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; max-height: 400px; overflow-y: auto; padding-right: 4px;">
                        <div class="tiktok-gift-card" onclick="sendTikTokGift('rose')">
                            <div class="gift-icon-wrap">🌹</div>
                            <div class="gift-title">Hoa Hồng</div>
                            <div class="gift-price">1,000 GTLM</div>
                        </div>
                        <div class="tiktok-gift-card" onclick="sendTikTokGift('heart')">
                            <div class="gift-icon-wrap">💖</div>
                            <div class="gift-title">Trái Tim</div>
                            <div class="gift-price">5,000 GTLM</div>
                        </div>
                        <div class="tiktok-gift-card" onclick="sendTikTokGift('icecream')">
                            <div class="gift-icon-wrap">🍦</div>
                            <div class="gift-title">Kem Ốc Quế</div>
                            <div class="gift-price">10,000 GTLM</div>
                        </div>
                        <div class="tiktok-gift-card" onclick="sendTikTokGift('donut')">
                            <div class="gift-icon-wrap">🍩</div>
                            <div class="gift-title">Bánh Donut</div>
                            <div class="gift-price">25,000 GTLM</div>
                        </div>
                        <div class="tiktok-gift-card" onclick="sendTikTokGift('crown')">
                            <div class="gift-icon-wrap">👑</div>
                            <div class="gift-title">Vương Miện VIP</div>
                            <div class="gift-price">50,000 GTLM</div>
                        </div>
                        <div class="tiktok-gift-card" onclick="sendTikTokGift('rocket')">
                            <div class="gift-icon-wrap">🚀</div>
                            <div class="gift-title">Tên Lửa 3D</div>
                            <div class="gift-price">100,000 GTLM</div>
                        </div>
                        <div class="tiktok-gift-card" onclick="sendTikTokGift('supercar')">
                            <div class="gift-icon-wrap">🏎️</div>
                            <div class="gift-title">Siêu Xe Neon</div>
                            <div class="gift-price">500,000 GTLM</div>
                        </div>
                        <div class="tiktok-gift-card vip-castle" onclick="sendTikTokGift('castle')">
                            <div class="gift-icon-wrap">🏰</div>
                            <div class="gift-title">Lâu Đài VIP</div>
                            <div class="gift-price">1,000,000 GTLM</div>
                        </div>
                    </div>
                `,
                showConfirmButton: false,
                showCancelButton: true,
                cancelButtonText: 'Đóng cửa hàng',
                background: '#0f172a',
                color: '#f8fafc',
                width: '600px'
            });
        }

        function sendTikTokGift(giftId) {
            Swal.close();
            $.post('api_spectator.php', { action: 'gift_tiktok', table_id: currentTableId, gift_id: giftId }, function(res) {
                if (res.success) {
                    if (res.newMoney) $('#userMoneyDisplay').text(res.newMoney);
                    showGiftBanner(res.userName, res.gift.icon, res.gift.name, res.streamerName, res.amountFormatted, res.speechText);
                    triggerGiftFX(res.gift);
                    loadDetails();
                } else {
                    Swal.fire({
                        title: 'LỖI TẶNG QUÀ',
                        text: res.message,
                        icon: 'error',
                        background: '#0f172a',
                        color: '#f8fafc'
                    });
                }
            });
        }

        function showGiftBanner(userName, giftIcon, giftName, botName, amountFormatted, speechText) {
            const text = `🎁 <b style="color:#fef08a;">${userName}</b> vừa Tặng <span style="font-size:1.2rem;">${giftIcon}</span> <b style="color:#f472b6;">${giftName}</b> cho Streamer <b style="color:#67e8f9;">${botName}</b> (+${amountFormatted} GTLM)! ❤️`;
            $('#tipBannerText').html(text);
            const banner = $('#tipNotificationBanner');
            banner.stop(true, true).css('display', 'flex').hide().fadeIn(300);

            if (speechText) {
                speakStreamerVoice(speechText);
            }

            setTimeout(() => {
                banner.fadeOut(800);
            }, 6000);
        }

        function triggerGiftFX(gift) {
            const container = $('#tiktokGiftFXContainer');
            if (container.length === 0) return;
            container.empty();

            const giftId = gift.id;
            const icon = gift.icon;

            if (['rose', 'heart', 'icecream', 'donut'].includes(giftId)) {
                for (let i = 0; i < 40; i++) {
                    const leftPos = Math.random() * 92 + 4;
                    const animDelay = Math.random() * 1.0;
                    const animDur = Math.random() * 1.5 + 2.2;
                    const size = Math.random() * 30 + 45;

                    const el = $(`
                        <div style="position:fixed; top:-120px; left:${leftPos}vw; font-size:${size}px; z-index:9999999; pointer-events:none; filter:drop-shadow(0 0 15px #fbbf24) drop-shadow(0 0 30px #ec4899); animation: giftRainDown ${animDur}s cubic-bezier(0.25, 0.46, 0.45, 0.94) ${animDelay}s forwards;">
                            ${icon}
                        </div>
                    `).appendTo(container);

                    setTimeout(() => el.remove(), (animDur + animDelay + 0.5) * 1000);
                }
            } else if (giftId === 'crown') {
                const crown = $(`
                    <div style="position:fixed; top:20vh; left:50%; transform:translateX(-50%); font-size:160px; filter:drop-shadow(0 0 60px #fbbf24) drop-shadow(0 0 100px #f59e0b); text-align:center; pointer-events:none; z-index:9999999; animation: giftCrownDropBig 1.2s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;">
                        👑
                        <div style="font-size:2rem; font-weight:900; color:#fbbf24; text-shadow:0 0 20px #000, 0 0 30px #fbbf24; letter-spacing:3px; font-family:'Outfit', sans-serif;">VƯƠNG MIỆN HOÀNG GIA VIP</div>
                    </div>
                `).appendTo(container);

                if (window.gsap) {
                    gsap.fromTo(crown, { scale: 0.2, opacity: 0 }, { scale: 1.4, opacity: 1, duration: 1.0, ease: "back.out(1.7)" });
                }

                setTimeout(() => {
                    crown.fadeOut(1000, () => crown.remove());
                }, 4500);
            } else if (giftId === 'rocket') {
                const rocket = $(`
                    <div style="position:fixed; top:-200px; left:50%; transform:translateX(-50%); font-size:180px; filter:drop-shadow(0 0 70px #ff4757) drop-shadow(0 0 120px #ff6b81); text-align:center; pointer-events:none; z-index:9999999; animation: giftRocketBlastDown 2.2s ease-in forwards;">
                        🚀
                        <div style="font-size:2rem; font-weight:900; color:#ff4757; text-shadow:0 0 20px #000, 0 0 30px #ff4757; letter-spacing:3px; font-family:'Outfit', sans-serif;">TÊN LỬA VŨ TRỤ 3D PHÓNG LIVE</div>
                    </div>
                `).appendTo(container);

                if (window.GameEffects && window.GameEffects.showWin) window.GameEffects.showWin(100000);
                setTimeout(() => rocket.remove(), 2800);
            } else if (giftId === 'supercar') {
                const car = $(`
                    <div style="position:fixed; top:35vh; left:-400px; font-size:160px; filter:drop-shadow(0 0 60px #2ed573) drop-shadow(0 0 100px #10b981); text-align:center; pointer-events:none; z-index:9999999; animation: giftCarSpeedFull 1.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;">
                        🏎️💨
                        <div style="font-size:1.8rem; font-weight:900; color:#2ed573; text-shadow:0 0 20px #000, 0 0 30px #2ed573; letter-spacing:3px; font-family:'Outfit', sans-serif;">SIÊU XE NEON SPORTSCAR</div>
                    </div>
                `).appendTo(container);

                if (window.GameEffects && window.GameEffects.showWin) window.GameEffects.showWin(500000);
                setTimeout(() => car.remove(), 2500);
            } else if (giftId === 'castle') {
                const castle = $(`
                    <div style="position:fixed; top:20vh; left:50%; transform:translateX(-50%); font-size:190px; filter:drop-shadow(0 0 80px #e84393) drop-shadow(0 0 130px #f472b6); text-align:center; pointer-events:none; z-index:9999999; animation: giftCastleRiseCenter 1.4s ease-out forwards;">
                        🏰✨
                        <div style="font-size:2.2rem; font-weight:900; color:#e84393; text-shadow:0 0 25px #000, 0 0 40px #e84393; letter-spacing:4px; font-family:'Outfit', sans-serif;">LÂU ĐÀI HOÀNG GIA VIP</div>
                    </div>
                `).appendTo(container);

                if (window.GameEffects && window.GameEffects.showWin) window.GameEffects.showWin(1000000);
                setTimeout(() => {
                    castle.fadeOut(1200, () => castle.remove());
                }, 5500);
            }
        }

        (function () {
            window.themeConfig = {
                particleCount: <?= (int)$particleCount ?>, 
                particleSize: <?= (float)$particleSize ?>, 
                particleColor: "<?= htmlspecialchars($particleColor) ?>", 
                particleOpacity: <?= (float)$particleOpacity ?>,
                shapeCount: <?= (int)$shapeCount ?>, 
                shapeColors: <?= json_encode($shapeColors) ?>, 
                shapeOpacity: <?= (float)$shapeOpacity ?>,
                bgGradient: <?= json_encode($bgGradient) ?>
            };
            const prefix = '../';
            ['threejs-background.js', 'assets/js/game-effects.js'].forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src; s.async = false;
                document.head.appendChild(s);
            });
        })();

        $(document).ready(() => {
            loadDetails();
            setInterval(loadDetails, 2000);
        });
    </script>
    <canvas id="threejs-background"></canvas>
</body>
</html>
