<?php
/**
 * 📺 Sảnh LiveStream v3.0 (Trận Địa Live 24/7 Engine)
 * 5 Bàn Phát Song Song Realtime Sync | Đồng bộ Theme load_theme.php & Mobile/Desktop
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../load_theme.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}

$userId = (int)$_SESSION['Iduser'];

// Lấy thông tin user
$stmtUser = $conn->prepare("SELECT Name, Money, ImageURL FROM users WHERE Iduser = ?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$userData = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

$userName = $userData['Name'] ?? 'Đạo Hữu';
$userMoney = (float)($userData['Money'] ?? 0);
$userAvatar = !empty($userData['ImageURL']) ? $userData['ImageURL'] : '../img/avatar_default.png';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sảnh Trận Địa Live 24/7 | GTLM Gaming</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        html, body, div, p, span, section, header, footer, aside, nav, table, tr, td, iframe, canvas {
            cursor: url('img/chuot.png'), default;
        }
        a, button, input, select, textarea, label, .btn, [role="button"], [onclick], .clickable, .live-card, .cat-chip,
        a *, button *, [onclick] *, .live-card *, .cat-chip *, .swal2-popup button, .swal2-popup [onclick] {
            cursor: url('img/tay.png'), pointer !important;
        }
        #threejs-background { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; pointer-events: none; }
        :root {
            --bg-main: #0b0f19;
            --card-bg: rgba(30, 41, 59, 0.75);
            --card-border: rgba(255, 255, 255, 0.1);
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
            min-height: 100vh;
            <?= isset($bgGradientCSS) ? "background-image: $bgGradientCSS; background-attachment: fixed;" : "" ?>
            overflow-x: hidden;
        }

        .live-header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(11, 15, 25, 0.92);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--card-border);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #fff;
            font-weight: 900;
            font-size: 1.2rem;
        }

        .brand-badge {
            background: linear-gradient(135deg, var(--rose), var(--purple));
            color: #fff;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 800;
            animation: pulse-glow 1.5s infinite alternate;
        }

        @keyframes pulse-glow {
            0% { box-shadow: 0 0 5px var(--rose); }
            100% { box-shadow: 0 0 15px var(--purple); }
        }

        .header-search {
            flex: 1;
            max-width: 400px;
            position: relative;
        }

        .header-search input {
            width: 100%;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--card-border);
            color: #fff;
            border-radius: 12px;
            padding: 8px 15px 8px 38px;
            font-size: 0.9rem;
            outline: none;
        }

        .header-search i {
            position: absolute; left: 12px; top: 50%;
            transform: translateY(-50%); color: var(--text-sub);
        }

        .user-balance-chip {
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 12px;
            padding: 6px 14px;
            display: flex; align-items: center; gap: 8px;
            font-weight: 800; color: var(--emerald); font-size: 0.9rem;
        }

        .btn-back-lobby {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--card-border);
            color: var(--text-sub);
            padding: 8px 14px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.85rem;
            display: flex; align-items: center; gap: 6px;
        }

        .live-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 280px 1fr 320px;
            gap: 20px;
        }

        .sidebar-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 18px;
            position: sticky;
            top: 75px;
        }

        .channel-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            margin-bottom: 8px;
            transition: all 0.2s;
        }

        .channel-item:hover {
            background: rgba(99, 102, 241, 0.15);
            border-color: rgba(99, 102, 241, 0.4);
            transform: translateX(4px);
        }

        .dot-live {
            width: 8px; height: 8px; background: var(--rose);
            border-radius: 50%; animation: pulse-dot 1s infinite;
        }

        @keyframes pulse-dot {
            0% { transform: scale(0.8); opacity: 0.6; }
            100% { transform: scale(1.2); opacity: 1; }
        }

        .main-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .spotlight-banner {
            position: relative;
            aspect-ratio: 16/9;
            background: radial-gradient(circle at center, #1e1b4b 0%, #090a0f 100%);
            border-radius: 20px;
            border: 1px solid rgba(168, 85, 247, 0.35);
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 20px;
        }

        .badge-live-big {
            background: var(--rose);
            color: #fff;
            padding: 4px 12px;
            border-radius: 8px;
            font-weight: 900;
            font-size: 0.85rem;
            display: flex; align-items: center; gap: 6px;
        }

        .phase-pill {
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(10px);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--gold);
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        .spotlight-title {
            font-size: 1.8rem;
            font-weight: 900;
            margin: 0 0 8px 0;
            background: linear-gradient(135deg, #fff, var(--cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn-join-live {
            background: linear-gradient(135deg, var(--purple), var(--primary));
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 900;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.2s;
        }

        .btn-join-live:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.6);
        }

        .category-bar {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 5px;
            scrollbar-width: none;
        }

        .category-bar::-webkit-scrollbar { display: none; }

        .cat-chip {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-sub);
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }

        .cat-chip:hover, .cat-chip.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .live-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 16px;
        }

        .live-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--card-border);
            border-radius: 18px;
            padding: 16px;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .live-card:hover {
            transform: translateY(-4px);
            border-color: rgba(168, 85, 247, 0.5);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }

        .card-thumb {
            aspect-ratio: 16/9;
            background: #181c25;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-thumb-icon { font-size: 3rem; opacity: 0.8; }

        .card-timer-badge {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            padding: 3px 10px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--gold);
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        .buff-lounge {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(168, 85, 247, 0.25);
            border-radius: 20px;
            padding: 20px;
        }

        .buff-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 15px;
        }

        .buff-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s;
        }

        .buff-card:hover {
            border-color: var(--gold);
            transform: translateY(-3px);
            background: rgba(251, 191, 36, 0.05);
        }

        .right-sidebar {
            display: flex; flex-direction: column; gap: 20px;
        }

        .chat-widget {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            height: 480px;
            display: flex; flex-direction: column;
            overflow: hidden; position: sticky; top: 75px;
        }

        .chat-widget-header {
            padding: 14px; border-bottom: 1px solid var(--card-border);
            font-weight: 800; font-size: 0.9rem;
            display: flex; align-items: center; justify-content: space-between;
        }

        .chat-widget-body {
            flex: 1; overflow-y: auto; padding: 12px;
            display: flex; flex-direction: column; gap: 8px; font-size: 0.85rem;
        }

        @media (max-width: 1024px) {
            .live-container { grid-template-columns: 1fr; }
            .sidebar-card, .chat-widget { position: static; }
            .chat-widget { height: 350px; }
        }

        @media (max-width: 640px) {
            .header-search { display: none; }
            .live-container { padding: 12px; gap: 15px; }
            .spotlight-title { font-size: 1.3rem; }
            .buff-grid { grid-template-columns: 1fr; }
            .live-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <header class="live-header">
        <a href="spectator.php" class="brand-logo">
            <span style="font-size: 1.4rem;">📺</span> TRẬN ĐỊA LIVE 24/7
            <span class="brand-badge">LIVE</span>
        </a>

        <div class="header-search">
            <i class="fa fa-search"></i>
            <input type="text" id="searchInput" placeholder="Tìm bàn live, streamer..." onkeyup="filterStreams()">
        </div>

        <div style="display: flex; align-items: center; gap: 10px;">
            <div class="user-balance-chip">
                <i class="fa fa-wallet" style="color: var(--emerald);"></i>
                <span><?= number_format($userMoney) ?> GTLM</span>
            </div>
            <a href="/1/index.php" class="btn-back-lobby">
                <i class="fa fa-arrow-left"></i> <span>Sảnh Chính</span>
            </a>
        </div>
    </header>

    <div class="live-container">

        <aside>
            <div class="sidebar-card">
                <div style="font-size: 0.85rem; font-weight: 800; text-transform: uppercase; color: var(--text-sub); margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-fire" style="color: var(--rose);"></i> 8 Bàn Live 24/7
                </div>
                <div id="sidebar-tables-list">
                    <div style="text-align: center; color: var(--text-sub); padding: 15px; font-size: 0.85rem;">
                        <i class="fa fa-spinner fa-spin"></i> Đang tải luồng live...
                    </div>
                </div>
            </div>
        </aside>

        <main class="main-content">

            <div class="spotlight-banner" id="spotlightBanner">
                <div style="display: flex; justify-content: space-between; align-items: center; z-index: 2;">
                    <div class="badge-live-big">
                        <span class="dot-live"></span> ĐANG TRỰC TIẾP
                    </div>
                    <div class="phase-pill" id="spotlightPhasePill">
                        ⏳ RA CHIÊU: 15s
                    </div>
                </div>

                <div style="text-align: center; margin: auto; z-index: 2;">
                    <h2 class="spotlight-title" id="spotlightStreamer">Thế Giới Linh Thú 3D</h2>
                    <div style="color: var(--text-sub); font-size: 0.95rem;" id="spotlightDesc">Đang ra chiêu tại: 🐾 Bát Lắc Linh Thú</div>
                </div>

                <div style="background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(12px); border: 1px solid var(--card-border); border-radius: 14px; padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; z-index: 2;">
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-sub); font-weight: 700;">CHẾ ĐỘ XEM LIVE</div>
                        <div style="font-weight: 900; color: var(--gold); font-size: 0.95rem;">BOT AUTO-PLAY 24/7</div>
                    </div>
                    <a href="watch.php?id=1" class="btn-join-live" id="btnJoinSpotlight">
                        <i class="fa fa-play"></i> VÀO RA CHIÊU NGAY
                    </a>
                </div>
            </div>

            <div class="category-bar">
                <div class="cat-chip active" onclick="filterCategory('all')">🔥 Tất cả 8 bàn</div>
                <div class="cat-chip" onclick="filterCategory('xocdia')">🎲 Trận Địa Trắng Đỏ</div>
                <div class="cat-chip" onclick="filterCategory('baucua')">🐾 Thế Giới Linh Thú</div>
                <div class="cat-chip" onclick="filterCategory('crash')">🚀 Tiên Tri Vũ Trụ</div>
                <div class="cat-chip" onclick="filterCategory('daga')">🐓 Đại Chiến Thần Kê</div>
                <div class="cat-chip" onclick="filterCategory('dragontiger')">🐉 Chiến Trường Rồng Hổ</div>
            </div>

            <div class="live-grid" id="liveGrid">
                <!-- Grid items render via JS -->
            </div>

            <div class="buff-lounge">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h3 style="margin: 0; font-size: 1.1rem; color: #fff; font-weight: 900;">
                            🔥 BƠM BUFF & CỔ VŨ IDOL
                        </h3>
                        <p style="margin: 4px 0 0 0; font-size: 0.8rem; color: var(--text-sub);">
                            Gửi bùa cổ vũ để Idol húp ngập mặt và bảo hộ Trận Địa!
                        </p>
                    </div>
                </div>

                <div class="buff-grid">
                    <div class="buff-card">
                        <div style="font-size: 2rem;">🍀</div>
                        <div style="font-weight: 800; color: var(--gold); font-size: 0.9rem; margin: 4px 0;">Bùa May Mắn</div>
                        <div style="font-size: 0.75rem; color: var(--text-sub);">Tăng vận may lộc vần trong 3 ván</div>
                        <div style="margin-top: 10px; font-weight: 900; color: var(--gold); font-size: 0.85rem;">15,000 GTLM</div>
                    </div>
                    <div class="buff-card">
                        <div style="font-size: 2rem;">🚀</div>
                        <div style="font-weight: 800; color: var(--primary); font-size: 0.9rem; margin: 4px 0;">Tên Lửa Hype</div>
                        <div style="font-size: 0.75rem; color: var(--text-sub);">+20% lộc húp nhân hệ số thưởng</div>
                        <div style="margin-top: 10px; font-weight: 900; color: var(--primary); font-size: 0.85rem;">25,000 GTLM</div>
                    </div>
                    <div class="buff-card">
                        <div style="font-size: 2rem;">🛡️</div>
                        <div style="font-weight: 800; color: var(--emerald); font-size: 0.9rem; margin: 4px 0;">Khiên Hộ Mệnh</div>
                        <div style="font-size: 0.75rem; color: var(--text-sub);">Hoàn 50% GTLM nếu Idol thất bại</div>
                        <div style="margin-top: 10px; font-weight: 900; color: var(--emerald); font-size: 0.85rem;">20,000 GTLM</div>
                    </div>
                </div>
            </div>

        </main>

        <aside class="right-sidebar">
            <div class="chat-widget">
                <div class="chat-widget-header">
                    <span><i class="fa fa-comments" style="color: var(--purple);"></i> CHAT PHÒNG LIVE 24/7</span>
                    <span style="font-size: 0.75rem; color: var(--emerald);"><i class="fa fa-circle" style="font-size: 0.5rem;"></i> LIVE</span>
                </div>
                <div class="chat-widget-body" id="worldChatBox">
                    <div class="chat-line"><span style="color:var(--purple); font-weight:800;">Lão Tiên Tri:</span> Chào các chiến hữu! Vòng mới vừa bắt đầu, vào Ra Chiêu thôi!</div>
                    <div class="chat-line"><span style="color:var(--gold); font-weight:800;">Thánh Húp GTLM:</span> Bàn Trắng Đỏ ván này đang báo về Chẵn cực cao nhé!</div>
                </div>
            </div>
        </aside>

    </div>

    <script>
        let allTables = [];
        let currentSpotlightId = 1;

        function fetchLiveTables() {
            $.get('api_spectator.php?action=get_tables', function(res) {
                if (res.success && res.tables) {
                    allTables = res.tables;
                    renderSidebarList(allTables);
                    renderGridCards(allTables);
                    updateSpotlightBanner(res.state);
                }
            });
        }

        function updateSpotlightBanner(state) {
            const currentTable = allTables.find(t => t.id === currentSpotlightId) || allTables[0];
            if (!currentTable) return;

            $('#spotlightStreamer').text(currentTable.name);
            $('#spotlightDesc').text(currentTable.icon + ' ' + currentTable.desc + ' — Streamer: ' + currentTable.streamer_name);
            $('#btnJoinSpotlight').attr('href', 'watch.php?id=' + currentTable.id);

            let phaseText = '';
            if (state.phase === 'betting') {
                phaseText = `⏳ RA CHIÊU: ${state.time_left}s`;
            } else if (state.phase === 'shaking') {
                phaseText = `🔥 NÍN THỞ LẮC 3D: ${state.time_left}s`;
            } else {
                phaseText = `🏆 HÚP GTLM: ${state.time_left}s`;
            }
            $('#spotlightPhasePill').text(phaseText);
        }

        function renderSidebarList(tables) {
            const html = tables.map(t => `
                <a href="watch.php?id=${t.id}" class="channel-item">
                    <div style="font-size: 1.5rem; width:36px; text-align:center;">${t.icon}</div>
                    <div style="flex:1; min-width:0;">
                        <div style="font-weight:800; font-size:0.85rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${t.name}</div>
                        <div style="font-size:0.75rem; color:var(--text-sub);">${t.streamer_name}</div>
                    </div>
                    <div style="display:flex; align-items:center; gap:4px; font-size:0.75rem; color:var(--rose); font-weight:800;">
                        <span class="dot-live"></span> ${t.viewers}
                    </div>
                </a>
            `).join('');
            $('#sidebar-tables-list').html(html);
        }

        function renderGridCards(tables) {
            const html = tables.map(t => `
                <a href="watch.php?id=${t.id}" class="live-card">
                    <div class="card-thumb">
                        <div class="card-thumb-icon">${t.icon}</div>
                        <div class="badge-live-big" style="position:absolute; top:8px; left:8px; font-size:0.7rem; padding:2px 6px;">
                            <span class="dot-live"></span> 24/7
                        </div>
                        <div class="card-timer-badge">
                            <i class="fa fa-user"></i> ${t.viewers} người xem
                        </div>
                    </div>
                    <div>
                        <div style="font-weight:900; font-size:0.95rem; color:#fff;">${t.name}</div>
                        <div style="font-size:0.75rem; color:var(--text-sub); margin-top:2px;">Streamer: <b>${t.streamer_name}</b></div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
                            <span style="font-size:0.75rem; color:var(--emerald); font-weight:800;"><i class="fa fa-circle" style="font-size:0.5rem"></i> AUTO-PLAY 24/7</span>
                            <span style="background:var(--primary); color:#fff; padding:4px 10px; border-radius:8px; font-weight:800; font-size:0.75rem;">VÀO XEM NGAY</span>
                        </div>
                    </div>
                </a>
            `).join('');
            $('#liveGrid').html(html);
        }

        function filterStreams() {
            const q = $('#searchInput').val().toLowerCase();
            const filtered = allTables.filter(t => t.name.toLowerCase().includes(q) || t.streamer_name.toLowerCase().includes(q));
            renderGridCards(filtered);
        }

        function filterCategory(cat) {
            $('.cat-chip').removeClass('active');
            $(event.target).addClass('active');
            if (cat === 'all') {
                renderGridCards(allTables);
            } else {
                const filtered = allTables.filter(t => t.game_code.toLowerCase().includes(cat));
                renderGridCards(filtered);
            }
        }

        (function () {
            window.themeConfig = {
                particleCount: 500, particleSize: 0.05, particleColor: "#00ff88", particleOpacity: 0.4,
                shapeCount: 15, shapeColors: ["#00ff88", "#00b894", "#ffffff"], shapeOpacity: 0.15,
                bgGradient: ["#000000", "#001a11", "#002a1b"]
            };
            const prefix = '../';
            ['threejs-background.js', 'assets/js/game-effects.js'].forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src; s.async = false;
                document.head.appendChild(s);
            });
        })();

        $(document).ready(() => {
            fetchLiveTables();
            setInterval(fetchLiveTables, 1000);
        });
    </script>
    <canvas id="threejs-background"></canvas>
</body>
</html>
