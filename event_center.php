<?php
session_start();
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}
require 'db_connect.php';
require_once 'load_theme.php';
require_once 'api_event_helper.php'; // getActiveSeasonalEvent()

// Kiểm tra xem có event active không, nếu không thì redirect sang events.php (trừ khi là admin preview)
$isPreview = isset($_GET['preview']) || isset($_GET['preview_event_id']);
if (!$isPreview) {
    $activeEvent = getActiveSeasonalEvent($conn, false, 'id');
    if (!$activeEvent) {
        header("Location: events.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trung Tâm Sự Kiện Mùa Giải</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --event-primary: #f43f5e; /* Rose */
            --event-secondary: #fbbf24; /* Amber */
            --bg: #0f172a;
        }

        body {
            background: var(--bg);
            background-image: 
                radial-gradient(circle at 10% 10%, rgba(244, 63, 94, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 90% 90%, rgba(251, 191, 36, 0.1) 0%, transparent 40%);
            color: #fff;
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
        }

        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }

        /* ── Event Hero ── */
        .event-hero {
            background: linear-gradient(135deg, #f43f5e, #e11d48);
            border-radius: 30px;
            padding: 50px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(225, 29, 72, 0.3);
            margin-bottom: 40px;
        }

        .event-hero::before {
            content: '🧧';
            position: absolute;
            top: -20px;
            right: -20px;
            font-size: 200px;
            opacity: 0.1;
            transform: rotate(20deg);
        }

        .event-title { font-size: 48px; font-weight: 900; margin: 0; text-transform: uppercase; letter-spacing: 2px; }
        .event-timer { background: rgba(0,0,0,0.2); display: inline-block; padding: 10px 25px; border-radius: 50px; margin-top: 20px; font-weight: 700; }

        /* ── Currency Bar ── */
        .currency-bar {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 40px;
        }

        .currency-card {
            background: rgba(255,255,255,0.05);
            padding: 15px 30px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .currency-val { font-size: 24px; font-weight: 900; color: var(--event-secondary); }

        /* ── Tabs ── */
        .event-tabs {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .e-tab {
            background: rgba(255,255,255,0.05);
            border: none;
            color: #94a3b8;
            padding: 12px 30px;
            border-radius: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
        }

        .e-tab.active { background: var(--event-primary); color: #fff; box-shadow: 0 5px 15px rgba(244, 63, 94, 0.4); }

        /* ── Missions ── */
        .mission-card {
            background: rgba(255,255,255,0.03);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.05);
            border-left: 4px solid #334155; /* default: gray */
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: border-left-color 0.3s, opacity 0.3s;
        }
        /* 🟢 Đã hoàn thành (có thể nhận) */
        .mission-card.status-claimable { border-left-color: #22c55e; }
        /* ✅ Đã nhận */
        .mission-card.status-claimed   { border-left-color: #3b82f6; opacity: 0.65; }
        /* 🟡 Đang tiến hành */
        .mission-card.status-progress  { border-left-color: #fbbf24; }
        /* ⚪ Chưa bắt đầu */
        .mission-card.status-idle      { border-left-color: #475569; }
        /* 🔒 Bị khóa */
        .mission-card.status-locked    { border-left-color: #ef4444; opacity: 0.55; }

        .progress-container { flex: 1; margin: 0 40px; }
        .progress-bar { height: 8px; background: rgba(255,255,255,0.1); border-radius: 10px; overflow: hidden; margin-top: 10px; }
        .progress-fill { height: 100%; background: linear-gradient(to right, #f43f5e, #fbbf24); transition: width 0.5s; }

        .btn-claim {
            background: var(--event-secondary);
            color: #000;
            border: none;
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 800;
            cursor: pointer;
            min-width: 140px;
            transition: transform 0.2s, filter 0.2s;
        }

        .btn-claim:hover:not(:disabled) { transform: scale(1.05); filter: brightness(1.1); }
        .btn-claim:disabled { background: #334155; color: #94a3b8; cursor: not-allowed; }

        /* ── Shop ── */
        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .exchange-card {
            background: rgba(255,255,255,0.05);
            border-radius: 24px;
            padding: 25px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .exchange-icon { font-size: 50px; margin-bottom: 15px; }

        .btn-exchange {
            width: 100%;
            background: #fff;
            color: #000;
            border: none;
            padding: 12px;
            border-radius: 15px;
            font-weight: 800;
            margin-top: 20px;
            cursor: pointer;
        }

        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 10px 20px;
            border-radius: 50px;
            text-decoration: none;
            backdrop-filter: blur(10px);
            z-index: 100;
        }

        /* ── Empty State ── */
        .empty-state {
            text-align: center;
            padding: 100px 20px;
            animation: fadeIn 0.5s ease;
        }
        .empty-state .es-icon { font-size: 80px; margin-bottom: 20px; animation: float 3s ease-in-out infinite; }
        .empty-state h2 { font-size: 28px; font-weight: 900; margin: 0 0 10px; }
        .empty-state p { opacity: 0.6; font-size: 16px; max-width: 400px; margin: 0 auto 30px; }
        .empty-state a { background: var(--event-primary); color: #fff; padding: 14px 35px; border-radius: 50px; text-decoration: none; font-weight: 800; box-shadow: 0 5px 20px rgba(244,63,94,0.4); }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* ── Skeleton Loading ── */
        .skeleton {
            background: linear-gradient(90deg, rgba(255,255,255,0.05) 25%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.05) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
            border-radius: 10px;
        }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

        /* ── Coin Fly Animation ── */
        .coin-fly {
            position: fixed;
            font-size: 28px;
            pointer-events: none;
            z-index: 9999;
            animation: coinFly 1.2s ease forwards;
        }
        @keyframes coinFly {
            0%   { opacity: 1; transform: translateY(0) scale(1); }
            80%  { opacity: 1; transform: translateY(-120px) scale(1.4); }
            100% { opacity: 0; transform: translateY(-160px) scale(0.5); }
        }

        /* ── Mission Filter Bar ── */
        .mission-filter-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            align-items: center;
        }
        .filter-btn {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: #94a3b8;
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }
        .filter-btn.active { border-color: var(--event-secondary); color: var(--event-secondary); background: rgba(251,191,36,0.1); }
        .filter-btn:hover { border-color: rgba(255,255,255,0.3); color: #fff; }

        /* ── Animated Counter ── */
        .counter-bump {
            animation: bump 0.4s cubic-bezier(.36,.07,.19,.97);
        }
        @keyframes bump {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.35); color: #4ade80; }
        }

        /* ── Shop Toolbar & Filters ── */
        .shop-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            background: rgba(30, 41, 59, 0.4);
            padding: 15px 25px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
        }
        .shop-filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .shop-filter-btn {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #94a3b8;
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .shop-filter-btn:hover {
            border-color: rgba(255, 255, 255, 0.2);
            color: #fff;
            background: rgba(255, 255, 255, 0.06);
        }
        .shop-filter-btn.active {
            border-color: var(--event-primary);
            color: #fff;
            background: var(--event-primary);
            box-shadow: 0 4px 15px rgba(244, 63, 94, 0.3);
        }
        .shop-sort select {
            background: #1e293b;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 8px 15px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            outline: none;
            transition: border-color 0.3s;
        }
        .shop-sort select:focus {
            border-color: var(--event-primary);
        }
    </style>
</head>
<body>

    <a href="index.php" class="back-btn"><i class="fa fa-arrow-left"></i> Sảnh</a>

    <div class="container">
        <?php if ($isPreview): ?>
        <div style="background: #f59e0b; color: #000; padding: 12px; text-align: center; font-weight: 900; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4); border: 2px solid #fbbf24; text-transform: uppercase; letter-spacing: 1px;">
            <i class="fa fa-exclamation-triangle"></i> CHẾ ĐỘ XEM TRƯỚC (ADMIN PREVIEW). Tiến trình hiển thị là giả lập 0%. Bạn không thể nhận thưởng thật.
        </div>
        <?php endif; ?>

        <div class="event-hero" id="event-hero">
            <h1 class="event-title" id="event-name">SỰ KIỆN ĐANG TẢI...</h1>
            <div class="event-timer" id="event-timer">Kết thúc sau: -- ngày -- giờ</div>
        </div>

        <div class="currency-bar">
            <div class="currency-card">
                <span style="font-size: 30px;">🧧</span>
                <div>
                    <div class="currency-val" id="user-tokens" data-event-currency="1">0</div>
                    <div style="font-size: 11px; opacity: 0.6; text-transform: uppercase;">Xu Sự Kiện</div>
                </div>
            </div>
            <div class="currency-card">
                <span style="font-size: 30px;">🏆</span>
                <div>
                    <div class="currency-val" id="user-points">0</div>
                    <div style="font-size: 11px; opacity: 0.6; text-transform: uppercase;">Điểm Vinh Danh</div>
                </div>
            </div>
        </div>

        <!-- Empty State — shown when no event is active -->
        <div id="no-event-state" style="display: none;">
            <div class="empty-state">
                <div class="es-icon">🌙</div>
                <h2>Không Có Sự Kiện Đang Diễn Ra</h2>
                <p>Server đang trong giai đoạn nghỉ ngơi. Sự kiện mới sẽ sớm được khởi động — hãy quay lại sau nhé!</p>
                <a href="index.php"><i class="fa fa-home"></i>&nbsp; Về Sảnh Chính</a>
            </div>
        </div>

        <!-- Main UI — hidden until event data loads -->
        <div id="event-ui-wrapper">
        <div class="event-tabs">
            <button class="e-tab active" onclick="switchTab('missions')">NHIỆM VỤ SỰ KIỆN</button>
            <button class="e-tab" onclick="switchTab('shop')">CỬA HÀNG ĐỔI QUÀ</button>
            <button class="e-tab" onclick="switchTab('history')">LỊCH SỬ ĐỔI QUÀ</button>
            <button class="e-tab" id="tab-btn-mission-log" onclick="switchTab('mission-log')">📝 LỊCH SỬ NV</button>
            <button class="e-tab" onclick="switchTab('leaderboard')">BẢNG XẾP HẠNG</button>
            <button class="e-tab" id="tab-btn-guild-board" onclick="switchTab('guild-board')">⚔️ BANG HỘI</button>
            <button class="e-tab" onclick="switchTab('vote')">BÌNH CHỌN</button>
            <button class="e-tab" style="background: linear-gradient(135deg, #f59e0b, #ef4444); color: white; border: none;" onclick="window.location.href='events_spin.php'">VÒNG QUAY 🎰</button>
        </div>

        <div id="missions-section">
            <!-- Community Goal Co-op -->
            <div id="community-goal-container" style="display: none; background: linear-gradient(135deg, rgba(56, 189, 248, 0.1), rgba(0,0,0,0.5)); border: 1px solid rgba(56, 189, 248, 0.5); padding: 20px; border-radius: 20px; margin-bottom: 25px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h3 style="margin: 0; color: #38bdf8;"><i class="fa fa-users"></i> Mục Tiêu Cộng Đồng</h3>
                    <span style="background: rgba(56, 189, 248, 0.2); color: #38bdf8; padding: 5px 15px; border-radius: 20px; font-weight: 800; font-size: 12px;">CO-OP TOÀN SERVER</span>
                </div>
                <p style="margin: 0 0 15px 0; color: #94a3b8; font-size: 13px;">Cùng nhau đạt <strong style="color: #fff;">100,000 Điểm Vinh Danh</strong> để mở khóa Rương Ma Thuật cho toàn bộ người chơi tham gia sự kiện!</p>
                <div style="background: rgba(0,0,0,0.3); height: 15px; border-radius: 10px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05); margin-bottom: 5px;">
                    <div id="cg-progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #38bdf8, #818cf8); box-shadow: 0 0 10px #38bdf8; transition: 1s ease;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: bold; color: #cbd5e1;">
                    <span id="cg-current">0</span>
                    <span id="cg-target">100,000</span>
                </div>
            </div>

            <div id="chain-progress-container" style="display: none; background: rgba(0,0,0,0.3); border: 1px solid var(--event-secondary); padding: 20px; border-radius: 20px; margin-bottom: 25px;">
                <!-- Render Chuỗi Nhiệm Vụ -->
            </div>

            <!-- Mission Filter Bar -->
            <div class="mission-filter-bar" id="mission-filter-bar" style="display:none;">
                <span style="font-size:13px; opacity:0.5; font-weight:700;">LỌC THEO:</span>
                <button class="filter-btn active" data-filter="all" onclick="applyMissionFilter('all', this)">🌐 Tất Cả</button>
                <button class="filter-btn" data-filter="daily" onclick="applyMissionFilter('daily', this)">🌅 Hằng Ngày</button>
                <button class="filter-btn" data-filter="weekly" onclick="applyMissionFilter('weekly', this)">📅 Hằng Tuần</button>
                <button class="filter-btn" data-filter="permanent" onclick="applyMissionFilter('permanent', this)">♾️ Vĩnh Viễn</button>
                <button class="filter-btn" data-filter="claimable" onclick="applyMissionFilter('claimable', this)">🎁 Có Thể Nhận</button>
                <!-- FEAT 3: Daily reset countdown -->
                <span id="daily-reset-timer" style="margin-left:auto; font-size:12px; font-weight:700; color:#38bdf8; background:rgba(56,189,248,0.1); padding:5px 14px; border-radius:20px; border:1px solid rgba(56,189,248,0.3);"
                      title="Nhiệm vụ hằng ngày reset lúc 00:00">
                    ⏰ Reset sau: --
                </span>
            </div>

            <div id="missions-list">
                <!-- Missions load here -->
            </div>
        </div>

        </div><!-- /#event-ui-wrapper -->

        <div id="shop-section" style="display: none;">
            <div class="shop-toolbar">
                <div class="shop-filters">
                    <button class="shop-filter-btn active" data-filter="all" onclick="applyShopFilter('all', this)">🌐 Tất Cả</button>
                    <button class="shop-filter-btn" data-filter="title" onclick="applyShopFilter('title', this)">👑 Danh Hiệu</button>
                    <button class="shop-filter-btn" data-filter="chat_frame" onclick="applyShopFilter('chat_frame', this)">💬 Khung Chat</button>
                    <button class="shop-filter-btn" data-filter="theme" onclick="applyShopFilter('theme', this)">🎨 Theme</button>
                    <button class="shop-filter-btn" data-filter="cursor" onclick="applyShopFilter('cursor', this)">🖱️ Con Trỏ</button>
                    <button class="shop-filter-btn" data-filter="utility" onclick="applyShopFilter('utility', this)">⚡ Tiện Ích</button>
                    <button class="shop-filter-btn" data-filter="money" onclick="applyShopFilter('money', this)">💵 GTLM</button>
                </div>
                <div class="shop-sort">
                    <select id="shop-sort-select" onchange="applyShopSort()">
                        <option value="default">⚖️ Sắp Xếp: Mặc Định</option>
                        <option value="price-asc">💵 Giá: Thấp → Cao</option>
                        <option value="price-desc">💵 Giá: Cao → Thấp</option>
                        <option value="stock-desc">📦 Hàng Còn: Nhiều → Ít</option>
                        <option value="stock-asc">📦 Hàng Còn: Ít → Nhiều</option>
                    </select>
                </div>
            </div>
            <div class="shop-grid" id="shop-grid">
                <!-- Shop items load here -->
            </div>
        </div>

        <div id="history-section" style="display: none;">
            <div style="display: flex; justify-content: flex-end; margin-bottom: 15px;">
                <button onclick="showRandomEventHistory()" style="background: rgba(244, 63, 94, 0.2); border: 1px solid #f43f5e; color: #f43f5e; padding: 10px 20px; border-radius: 10px; cursor: pointer; font-weight: bold;"><i class="fa fa-bolt"></i> Lịch Sử Sự Kiện Đột Xuất</button>
            </div>
            <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; padding: 20px; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 2px solid rgba(255,255,255,0.1); color: var(--event-secondary); font-size: 14px; font-weight: 900;">
                            <th style="padding: 15px;">THỜI GIAN</th>
                            <th style="padding: 15px;">VẬT PHẨM</th>
                            <th style="padding: 15px;">LOẠI</th>
                            <th style="padding: 15px; text-align: right;">CHI PHÍ 🧧</th>
                        </tr>
                    </thead>
                    <tbody id="history-rows">
                        <!-- History load dynamically here -->
                    </tbody>
                </table>
            </div>
        </div>

        <div id="leaderboard-section" style="display: none;">
            <!-- Personal Rank Banner -->
            <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; padding: 20px 30px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; backdrop-filter: blur(10px);">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <span style="font-size: 32px;">🎖️</span>
                    <div>
                        <div style="font-size: 13px; opacity: 0.6; font-weight: 700; text-transform: uppercase;">Hạng Của Bạn</div>
                        <div style="font-size: 26px; font-weight: 900; color: var(--event-secondary);" id="my-event-rank">--</div>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 13px; opacity: 0.6; font-weight: 700; text-transform: uppercase;">Điểm Tích Lũy</div>
                    <div style="font-size: 26px; font-weight: 900; color: var(--event-primary);" id="my-event-points">0</div>
                </div>
            </div>

            <!-- Points Milestones Preview -->
            <div id="milestone-progress-container" style="display: none; background: rgba(0,0,0,0.3); border: 1px solid var(--event-primary); padding: 20px; border-radius: 20px; margin-bottom: 25px;">
                <!-- Render Milestones here -->
            </div>

            <!-- Leaderboard Rewards Preview -->
            <div style="background: linear-gradient(135deg, rgba(251, 191, 36, 0.1), rgba(0,0,0,0.5)); border: 1px solid rgba(251, 191, 36, 0.5); border-radius: 20px; padding: 20px; margin-bottom: 25px;">
                <h3 style="margin-top: 0; color: #fbbf24; display: flex; align-items: center; gap: 10px;"><i class="fa fa-gift"></i> Phần Thưởng Xếp Hạng Cuối Mùa</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                    <div style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 15px; text-align: center; border: 1px solid #fbbf24;">
                        <div style="font-size: 24px;">🥇</div>
                        <div style="font-weight: 900; color: #fbbf24; margin: 5px 0;">TOP 1</div>
                        <div style="font-size: 13px; opacity: 0.8;">Khung Chat Độc Quyền<br>+ 5,000,000 GTLM</div>
                    </div>
                    <div style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 15px; text-align: center; border: 1px solid #94a3b8;">
                        <div style="font-size: 24px;">🥈</div>
                        <div style="font-weight: 900; color: #94a3b8; margin: 5px 0;">TOP 2 - 3</div>
                        <div style="font-size: 13px; opacity: 0.8;">Danh Hiệu Á Quân<br>+ 2,500,000 GTLM</div>
                    </div>
                    <div style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 15px; text-align: center; border: 1px solid #b45309;">
                        <div style="font-size: 24px;">🥉</div>
                        <div style="font-weight: 900; color: #b45309; margin: 5px 0;">TOP 4 - 10</div>
                        <div style="font-size: 13px; opacity: 0.8;">+ 1,000,000 GTLM<br>+ 5,000 XP</div>
                    </div>
                </div>
            </div>

            <!-- Rankings Table -->
            <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; padding: 20px; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;" id="leaderboard-table">
                    <thead>
                        <tr style="border-bottom: 2px solid rgba(255,255,255,0.1); color: var(--event-secondary); font-size: 14px; font-weight: 900;">
                            <th style="padding: 15px;">HẠNG</th>
                            <th style="padding: 15px;">NGƯỜI CHƠI</th>
                            <th style="padding: 15px; text-align: right;">ĐIỂM VINH DANH 🏆</th>
                        </tr>
                    </thead>
                    <tbody id="leaderboard-rows">
                        <!-- Rankings load dynamically here -->
                    </tbody>
                </table>
            </div>
        </div>

        <div id="vote-section" style="display: none;">
            <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; padding: 30px; text-align: center;">
                <h2 style="margin-top: 0; color: var(--event-primary);"><i class="fa fa-bullhorn"></i> BÌNH CHỌN SỰ KIỆN TIẾP THEO</h2>
                <p style="opacity: 0.8; margin-bottom: 30px;">Quyết định của bạn sẽ ảnh hưởng đến chủ đề và nội dung của Mùa Giải kế tiếp!</p>
                <div id="vote-options-container" style="display: flex; flex-direction: column; gap: 15px; max-width: 600px; margin: 0 auto;">
                    <!-- Vote options load here -->
                </div>
                <div id="vote-total" style="margin-top: 20px; font-weight: bold; opacity: 0.5;">Đang tải dữ liệu...</div>
            </div>
        </div>
    </div>

        <!-- FEAT 2: Lịch Sử Nhiệm Vụ -->
        <div id="mission-log-section" style="display:none;">
            <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);border-radius:20px;padding:20px;overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;text-align:left;">
                    <thead>
                        <tr style="border-bottom:2px solid rgba(255,255,255,0.1);color:var(--event-secondary);font-size:14px;font-weight:900;">
                            <th style="padding:15px;">THỜI GIAN</th>
                            <th style="padding:15px;">NHIỆM VỤ</th>
                            <th style="padding:15px;">CHU KỲ</th>
                            <th style="padding:15px;text-align:right;">PHẦN THƯỞNG</th>
                        </tr>
                    </thead>
                    <tbody id="mission-log-rows">
                        <tr><td colspan="4" style="text-align:center;padding:30px;opacity:0.5;">Mở tab này để tải lịch sử nhiệm vụ.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- FEAT 4: BXH Bang Hội -->
        <div id="guild-board-section" style="display:none;">
            <div id="my-guild-banner" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:20px;padding:20px 30px;margin-bottom:25px;display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-size:13px;opacity:0.6;font-weight:700;text-transform:uppercase;">Hạng Bang Hội Của Bạn</div>
                    <div id="my-guild-rank-display" style="font-size:26px;font-weight:900;color:var(--event-secondary);">--</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:13px;opacity:0.6;font-weight:700;text-transform:uppercase;">Điểm Cộng Đồng</div>
                    <div id="my-guild-pts-display" style="font-size:26px;font-weight:900;color:var(--event-primary);">0</div>
                </div>
            </div>
            <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);border-radius:20px;padding:20px;overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;text-align:left;">
                    <thead>
                        <tr style="border-bottom:2px solid rgba(255,255,255,0.1);color:var(--event-secondary);font-size:14px;font-weight:900;">
                            <th style="padding:15px;">HẠNG</th>
                            <th style="padding:15px;">BANG HỘI</th>
                            <th style="padding:15px;">THÀNH VIÊN</th>
                            <th style="padding:15px;text-align:right;">ĐIỂM ĐÓNG GÓP</th>
                        </tr>
                    </thead>
                    <tbody id="guild-board-rows">
                        <tr><td colspan="4" style="text-align:center;padding:30px;opacity:0.5;">Mở tab này để tải BXH Bang Hội.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <script>
        let eventData = null;
        let countdownInterval = null;

        // Custom Event Bus for Currency Sync
        document.addEventListener('eventCurrencyUpdate', function(e) {
            const endVal = e.detail.balance;
            
            // Sync inside eventData cache if active
            if (eventData && eventData.user_data) {
                eventData.user_data.event_currency = endVal;
            }

            $('[data-event-currency]').each(function() {
                const $el = $(this);
                const startVal = parseInt($el.text().replace(/,/g, '')) || 0;
                if (startVal !== endVal) {
                    $({ val: startVal }).animate({ val: endVal }, {
                        duration: 800,
                        easing: 'swing',
                        step: function() {
                            $el.text(new Intl.NumberFormat().format(Math.floor(this.val)));
                        },
                        complete: function() {
                            $el.text(new Intl.NumberFormat().format(endVal));
                        }
                    });
                } else {
                    $el.text(new Intl.NumberFormat().format(endVal));
                }
            });

            // Re-render shop cards to update "Còn thiếu X xu" indicators
            if (typeof allShopItems !== 'undefined' && allShopItems && allShopItems.length > 0) {
                filterAndRenderShop();
            }
        });

        function dispatchCurrencyUpdate(newBalance) {
            document.dispatchEvent(new CustomEvent('eventCurrencyUpdate', {
                detail: { balance: newBalance }
            }));
        }

        // ── Countdown Timer ──
        function startCountdown(endsAt) {
            if (countdownInterval) clearInterval(countdownInterval);
            const endTime = new Date(endsAt).getTime();

            function tick() {
                const now  = Date.now();
                const diff = endTime - now;
                if (diff <= 0) {
                    clearInterval(countdownInterval);
                    $('#event-timer').text('⏰ Sự kiện đã kết thúc!');
                    return;
                }
                const d  = Math.floor(diff / 86400000);
                const h  = Math.floor((diff % 86400000) / 3600000);
                const mi = Math.floor((diff % 3600000)  / 60000);
                const s  = Math.floor((diff % 60000)    / 1000);
                const pad = n => String(n).padStart(2, '0');
                $('#event-timer').text(`⏳ Kết thúc sau: ${d} ngày ${pad(h)}:${pad(mi)}:${pad(s)}`);
            }
            tick();
            countdownInterval = setInterval(tick, 1000);
        }

        function loadEvent() {
            // Hiển thị skeleton loading
            let skeletonMissions = '';
            for(let i=0; i<3; i++) {
                skeletonMissions += `<div class="mission-card status-idle" style="opacity:0.3; pointer-events:none;"><div style="width:320px; height:60px; background:rgba(255,255,255,0.1); border-radius:10px;"></div><div style="flex:1; margin:0 40px; height:20px; background:rgba(255,255,255,0.1); border-radius:10px;"></div><div style="width:140px; height:40px; background:rgba(255,255,255,0.1); border-radius:10px;"></div></div>`;
            }
            $('#missions-list').html(skeletonMissions);
            
            let skeletonShop = '';
            for(let i=0; i<4; i++) {
                skeletonShop += `<div class="exchange-card" style="opacity:0.3; height:200px; background:rgba(255,255,255,0.1);"></div>`;
            }
            $('#shop-grid').html(skeletonShop);

            const urlParams = new URLSearchParams(window.location.search);
            const isPreview = urlParams.get('preview') === '1';
            const previewEventId = urlParams.get('preview_event_id');

            if (isPreview && window.parent && typeof window.parent.getPreviewData === 'function') {
                const mockData = window.parent.getPreviewData();
                renderEventData(mockData);
            } else {
                let apiUrl = 'api_event_engine.php?action=get_event_data';
                if (previewEventId) apiUrl += '&preview_event_id=' + previewEventId;
                
                $.get(apiUrl, function(res) {
                    renderEventData(res);
                });
            }
        }

        function renderEventData(res) {
            if (res.success) {
                eventData = res;

                // Show main UI, hide empty state
                $('#no-event-state').hide();
                $('#event-ui-wrapper').show();
                $('#event-hero').show();
                $('#currency-bar-wrap').show();
                
                // 1. Render Hero & Info
                $('#event-name').text(res.event.name).prepend(`<span style="margin-right:15px; filter: drop-shadow(0 0 10px currentColor);">${res.event.theme_emoji || '🏆'}</span>`);
                startCountdown(res.event.ends_at);

                if (res.event.theme_config) {
                    try {
                        const tc = JSON.parse(res.event.theme_config);
                        if (tc.primary) document.documentElement.style.setProperty('--event-primary', tc.primary);
                        if (tc.secondary) document.documentElement.style.setProperty('--event-secondary', tc.secondary);
                        if (tc.bg) document.documentElement.style.setProperty('--bg', tc.bg);
                        if (tc.hero_bg) $('#event-hero').css('background-image', `url('${tc.hero_bg}')`);
                    } catch(e){}
                }

                dispatchCurrencyUpdate(parseInt(res.user_data.event_currency) || 0);
                $('#user-points').text(new Intl.NumberFormat().format(res.user_data.points));

                // ✅ Khởi động countdown
                if (res.event.ends_at) startCountdown(res.event.ends_at);

                // Community Goal Co-op
                if (res.total_server_points !== undefined) {
                    $('#community-goal-container').show();
                    const target = 100000;
                    const current = res.total_server_points;
                    const pct = Math.min(100, (current / target) * 100);
                    $('#cg-current').text(new Intl.NumberFormat().format(current));
                    $('#cg-progress-bar').css('width', pct + '%');
                    if(current >= target) {
                        $('#cg-progress-bar').css('background', 'linear-gradient(90deg, #4ade80, #22c55e)').css('box-shadow', '0 0 10px #4ade80');
                        $('#cg-current').text(new Intl.NumberFormat().format(current) + " (Mục tiêu đã đạt!)").css('color', '#4ade80');
                    }
                }

                renderMissions(res.missions);
                renderMissionChain(res.missions, res.event.chain_config, res.user_data.milestones_claimed);
                renderMilestones(res.user_data.points, res.event.milestone_config, res.user_data.milestones_claimed);
                renderShop(res.shop_items);
            } else {
                // Show beautiful empty state instead of hanging skeleton
                $('#event-hero').hide();
                $('#currency-bar-wrap').hide();
                $('#event-ui-wrapper').hide();
                $('#no-event-state').show();
                
                // Show last event summary popup if not shown yet
                checkAndShowSummaryPopup();
            }
        }

        function checkAndShowSummaryPopup() {
            $.get('api_event_engine.php?action=get_last_event_summary', function(res) {
                if (res.success) {
                    const storageKey = `event_summary_shown_${res.event_id}`;
                    if (localStorage.getItem(storageKey)) return;

                    Swal.fire({
                        title: `✨ TỔNG KẾT SỰ KIỆN ✨`,
                        html: `
                            <div style="text-align:center; padding:10px;">
                                <div style="font-size:50px; margin-bottom:15px;">${res.emoji}</div>
                                <p style="font-size:18px; font-weight:800; color:#fbbf24;">${res.event_name}</p>
                                <p style="opacity:0.8; font-size:14px; margin-bottom:20px;">Sự kiện đã chính thức khép lại. Dưới đây là thành tích vinh quang của bạn:</p>
                                
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px;">
                                    <div style="background:rgba(255,255,255,0.05); padding:12px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                        <div style="font-size:11px; opacity:0.5; font-weight:800;">HẠNG CHUNG CUỘC</div>
                                        <div style="font-size:20px; font-weight:900; color:#f59e0b; margin-top:5px;">#${res.rank}</div>
                                    </div>
                                    <div style="background:rgba(255,255,255,0.05); padding:12px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                        <div style="font-size:11px; opacity:0.5; font-weight:800;">ĐIỂM TÍCH LŨY</div>
                                        <div style="font-size:20px; font-weight:900; color:#38bdf8; margin-top:5px;">${new Intl.NumberFormat().format(res.points)}</div>
                                    </div>
                                    <div style="background:rgba(255,255,255,0.05); padding:12px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                        <div style="font-size:11px; opacity:0.5; font-weight:800;">NHIỆM VỤ XONG</div>
                                        <div style="font-size:20px; font-weight:900; color:#22c55e; margin-top:5px;">${res.missions_completed}</div>
                                    </div>
                                    <div style="background:rgba(255,255,255,0.05); padding:12px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                        <div style="font-size:11px; opacity:0.5; font-weight:800;">XU DƯ CHƯA ĐỔI</div>
                                        <div style="font-size:20px; font-weight:900; color:#ef4444; margin-top:5px;">${new Intl.NumberFormat().format(res.leftover_currency)}</div>
                                    </div>
                                </div>
                                
                                ${res.leftover_currency > 0 ? `<p style="color:#f59e0b; font-size:13px; font-weight:700;"><i class="fa fa-info-circle"></i> Bạn có thể mang số xu dư này sang trang Lịch Sử để đổi lấy GTLM!</p>` : ''}
                            </div>
                        `,
                        background: '#0f172a',
                        color: '#fff',
                        confirmButtonText: 'Xem Biên Niên Sử 📖',
                        showCancelButton: true,
                        cancelButtonText: 'Đóng',
                        confirmButtonColor: '#3b82f6',
                        cancelButtonColor: 'rgba(255,255,255,0.1)'
                    }).then((result) => {
                        localStorage.setItem(storageKey, '1');
                        if (result.isConfirmed) {
                            window.location.href = 'event_archive.php';
                        }
                    });
                }
            });
        }

        function renderMissionChain(missions, chainConfigStr, claimedStr) {
            let config = [];
            let claimed = [];
            try { config = JSON.parse(chainConfigStr || '[]'); } catch(e){}
            try { claimed = JSON.parse(claimedStr || '[]'); } catch(e){}
            
            if (!config || config.length === 0) {
                $('#chain-progress-container').hide();
                return;
            }

            const totalClaimed = missions.filter(m => m.is_claimed).length;
            let html = `<div style="font-size:16px; font-weight:800; margin-bottom:15px; color:var(--event-secondary);">
                <i class="fa fa-gift"></i> CHUỖI NHIỆM VỤ (${totalClaimed} đã nhận)
            </div><div style="display:flex; flex-wrap:wrap; gap:15px;">`;

            config.forEach(chain => {
                const req = chain.required_missions;
                const isCompleted = totalClaimed >= req;
                const isClaimedChain = claimed.includes(`chain_${eventData.event.id}_${req}`);
                
                let bColor = isClaimedChain ? '#3b82f6' : (isCompleted ? '#22c55e' : '#475569');
                let txt = isClaimedChain ? 'Đã Nhận' : (isCompleted ? 'Chưa Nhận' : `${totalClaimed}/${req}`);

                html += `
                <div style="flex:1; min-width:200px; background:rgba(255,255,255,0.05); padding:15px; border-radius:15px; border:2px solid ${bColor}; text-align:center;">
                    <div style="font-weight:800; font-size:14px;">${chain.label || `Mốc ${req} nhiệm vụ`}</div>
                    <div style="margin:10px 0; font-size:12px; opacity:0.7;">Phần thưởng: ${chain.reward_type === 'money' ? new Intl.NumberFormat().format(chain.reward_value)+' GTLM' : 'Vật phẩm'}</div>
                    <div style="display:inline-block; padding:4px 12px; border-radius:10px; background:${bColor}; color:#fff; font-size:12px; font-weight:800;">${txt}</div>
                </div>`;
            });

            html += `</div><p style="margin-top:15px; font-size:12px; opacity:0.6; text-align:center;">* Phần thưởng chuỗi nhiệm vụ sẽ được tự động nhận khi bạn đổi đủ số nhiệm vụ quy định.</p>`;
            $('#chain-progress-container').html(html).show();
        }

        function renderMilestones(currentPoints, milestoneConfigStr, claimedStr) {
            let config = [];
            let claimed = [];
            try { config = JSON.parse(milestoneConfigStr || '[]'); } catch(e){}
            try { claimed = JSON.parse(claimedStr || '[]'); } catch(e){}
            
            if (!config || config.length === 0) {
                $('#milestone-progress-container').hide();
                return;
            }

            let html = `<div style="font-size:16px; font-weight:800; margin-bottom:15px; color:var(--event-primary);">
                <i class="fa fa-star"></i> MỐC ĐIỂM TÍCH LŨY
            </div><div style="display:flex; flex-wrap:wrap; gap:15px;">`;

            config.forEach(ms => {
                const req = ms.points;
                const isCompleted = currentPoints >= req;
                const isClaimedMs = claimed.includes(`m_${eventData.event.id}_${req}`);
                
                let bColor = isClaimedMs ? '#3b82f6' : (isCompleted ? '#22c55e' : '#475569');
                let txt = isClaimedMs ? 'Đã Nhận' : (isCompleted ? 'Chưa Nhận' : `${currentPoints}/${req}`);

                html += `
                <div style="flex:1; min-width:200px; background:rgba(255,255,255,0.05); padding:15px; border-radius:15px; border:2px solid ${bColor}; text-align:center;">
                    <div style="font-weight:800; font-size:14px; color: ${bColor};">${ms.label || `Mốc ${req} điểm`}</div>
                    <div style="margin:10px 0; font-size:12px; opacity:0.7;">Phần thưởng: ${ms.reward_type === 'money' ? new Intl.NumberFormat().format(ms.reward_value)+' GTLM' : 'Vật phẩm'}</div>
                    <div style="display:inline-block; padding:4px 12px; border-radius:10px; background:${bColor}; color:#fff; font-size:12px; font-weight:800;">${txt}</div>
                </div>`;
            });

            html += `</div><p style="margin-top:15px; font-size:12px; opacity:0.6; text-align:center;">* Phần thưởng mốc điểm sẽ tự động gửi vào túi đồ/tài khoản khi bạn đạt đủ điểm.</p>`;
            $('#milestone-progress-container').html(html).show();
        }

        // Icon theo loại mission
        const missionIcons = {
            'play_game':    '🎮',
            'win_game':     '🏆',
            'earn_money':   '💰',
            'big_win':      '💥',
            'bet':          '🎲',
            'login':        '📌',
            'invite':       '👥',
            'default':      '⭐'
        };

        // ── Active missions cache for filtering ──
        let allMissions = [];
        let activeFilter = 'all';

        function applyMissionFilter(filter, btn) {
            activeFilter = filter;
            $('.filter-btn').removeClass('active');
            $(btn).addClass('active');
            renderMissions(allMissions);
        }

        function renderMissions(missions) {
            allMissions = missions || [];

            // Apply filter
            let filtered = allMissions;
            if (activeFilter === 'daily')     filtered = allMissions.filter(m => m.cycle === 'daily');
            else if (activeFilter === 'weekly')    filtered = allMissions.filter(m => m.cycle === 'weekly');
            else if (activeFilter === 'permanent') filtered = allMissions.filter(m => !m.cycle || m.cycle === 'permanent');
            else if (activeFilter === 'claimable') filtered = allMissions.filter(m => m.is_completed && !m.is_claimed && !m.is_locked);

            if (!filtered || filtered.length === 0) {
                $('#missions-list').html(`<div style="text-align:center;padding:60px;opacity:0.5;"><div style="font-size:40px;margin-bottom:15px;">🔍</div>${activeFilter !== 'all' ? 'Không có nhiệm vụ nào khớp bộ lọc này.' : 'Chưa có nhiệm vụ nào trong sự kiện này.'}</div>`);
                if (allMissions.length > 0) $('#mission-filter-bar').show();
                return;
            }

            $('#mission-filter-bar').show();
            let html = '';
            filtered.forEach(m => {
                const percent  = Math.min(100, (m.current_value / m.target_value) * 100);
                const canClaim = m.is_completed && !m.is_claimed && !m.is_locked;
                const icon     = missionIcons[m.mission_type] || missionIcons['default'];

                // Xác định CSS status class
                let statusClass = 'status-idle';
                if (m.is_locked)         statusClass = 'status-locked';
                else if (m.is_claimed)   statusClass = 'status-claimed';
                else if (canClaim)       statusClass = 'status-claimable';
                else if (m.current_value > 0) statusClass = 'status-progress';

                // Chu kỳ hiển thị
                let cycleText = 'VĨNH VIỄN';
                let cycleColor = 'rgba(148, 163, 184, 0.15)';
                let cycleTextHex = '#94a3b8';
                if (m.cycle === 'daily') {
                    cycleText = 'HẰNG NGÀY';
                    cycleColor = 'rgba(34, 197, 94, 0.15)';
                    cycleTextHex = '#22c55e';
                } else if (m.cycle === 'weekly') {
                    cycleText = 'HẰNG TUẦN';
                    cycleColor = 'rgba(59, 130, 246, 0.15)';
                    cycleTextHex = '#3b82f6';
                }
                let cycleBadge = `<span style="background:${cycleColor}; color:${cycleTextHex}; font-size:10px; padding:2px 8px; border-radius:5px; font-weight:900; border:1px solid ${cycleTextHex}33; display:inline-block; margin-bottom:5px;">${cycleText}</span>`;

                // Điểm khóa
                let lockInfo = '';
                if (m.is_locked) {
                    lockInfo = `<div style="font-size:12px; color:#ef4444; font-weight:700; margin-top:4px;"><i class="fa fa-lock"></i> KHÓA: Cần nhận quà "${m.prerequisite_title}" trước</div>`;
                }

                let btnHtml = '';
                if (m.is_locked) {
                    btnHtml = `<button class="btn-claim" disabled style="background:#1e293b; color:#64748b;"><i class="fa fa-lock"></i> ĐANG KHÓA</button>`;
                } else {
                    btnHtml = `<button class="btn-claim" ${canClaim ? '' : 'disabled'} onclick="claimReward(${m.id})">
                        ${m.is_claimed ? '✅ ĐÃ NHẬN' : (m.is_completed ? '🎁 NHẬN THƯỜNG' : 'CHƯA XONG')}
                    </button>`;
                }

                html += `
                    <div class="mission-card ${statusClass}">
                        <div style="width: 320px; display:flex; gap:12px; align-items:center;">
                            <span style="font-size:28px;">${icon}</span>
                            <div>
                                ${cycleBadge}
                                <div style="font-weight:800;font-size:16px;line-height:1.3;">${m.title}</div>
                                <div style="color:var(--event-secondary);font-size:13px;font-weight:700;margin-top:3px;">
                                    +${m.reward_currency} Xu 🧧 &nbsp;+${m.reward_xp} XP
                                </div>
                                ${lockInfo}
                            </div>
                        </div>
                        <div class="progress-container">
                            <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:800;">
                                <span>TIẾN TRÌNH</span>
                                <span>${new Intl.NumberFormat().format(m.current_value)} / ${new Intl.NumberFormat().format(m.target_value)}</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width:${percent}%"></div>
                            </div>
                        </div>
                        ${btnHtml}
                    </div>
                `;
            });
            $('#missions-list').html(html);
        }

        const shopIcons = { title:'👑', theme:'🎨', cursor:'🖱️', chat_frame:'💬', default:'🎁' };
        let allShopItems = [];
        let activeShopFilter = 'all';
        let activeShopSort = 'default';

        function applyShopFilter(category, btn) {
            activeShopFilter = category;
            $('.shop-filter-btn').removeClass('active');
            $(btn).addClass('active');
            filterAndRenderShop();
        }

        function applyShopSort() {
            activeShopSort = $('#shop-sort-select').val();
            filterAndRenderShop();
        }

        function filterAndRenderShop() {
            let filtered = [...allShopItems];

            // 1. Filter
            if (activeShopFilter === 'title') {
                filtered = allShopItems.filter(item => item.item_type === 'title');
            } else if (activeShopFilter === 'chat_frame') {
                filtered = allShopItems.filter(item => item.item_type === 'chat_frame');
            } else if (activeShopFilter === 'theme') {
                filtered = allShopItems.filter(item => item.item_type === 'theme');
            } else if (activeShopFilter === 'cursor') {
                filtered = allShopItems.filter(item => item.item_type === 'cursor');
            } else if (activeShopFilter === 'utility') {
                filtered = allShopItems.filter(item => ['xp', 'vip', 'buff'].includes(item.item_type));
            } else if (activeShopFilter === 'money') {
                filtered = allShopItems.filter(item => ['money', 'gtlm'].includes(item.item_type));
            }

            // 2. Sort
            if (activeShopSort === 'price-asc') {
                filtered.sort((a, b) => parseInt(a.cost_currency) - parseInt(b.cost_currency));
            } else if (activeShopSort === 'price-desc') {
                filtered.sort((a, b) => parseInt(b.cost_currency) - parseInt(a.cost_currency));
            } else if (activeShopSort === 'stock-desc') {
                filtered.sort((a, b) => {
                    const stockA = a.total_stock < 0 ? 99999999 : parseInt(a.total_stock);
                    const stockB = b.total_stock < 0 ? 99999999 : parseInt(b.total_stock);
                    return stockB - stockA;
                });
            } else if (activeShopSort === 'stock-asc') {
                filtered.sort((a, b) => {
                    const stockA = a.total_stock < 0 ? 99999999 : parseInt(a.total_stock);
                    const stockB = b.total_stock < 0 ? 99999999 : parseInt(b.total_stock);
                    return stockA - stockB;
                });
            }

            renderShopGrid(filtered);
        }

        function renderShopGrid(items) {
            if (!items || items.length === 0) {
                $('#shop-grid').html('<div style="text-align:center;padding:60px;opacity:0.5;">Không có vật phẩm nào khớp với bộ lọc này.</div>');
                return;
            }
            let html = '';
            const userTokens = eventData && eventData.user_data ? parseInt(eventData.user_data.event_currency) || 0 : 0;
            
            items.forEach(item => {
                const stockDisplay = item.total_stock < 0 ? 'Vô hạn' : item.total_stock;
                const isLimited    = item.total_stock >= 0;
                const icon         = shopIcons[item.item_type] || shopIcons['default'];
                const outOfStock   = item.total_stock === 0;

                const cost = parseInt(item.cost_currency);
                let missingBadge = '';
                if (userTokens < cost) {
                    const missing = cost - userTokens;
                    missingBadge = `<div class="missing-tokens" style="font-size:11px; color:#ef4444; font-weight:800; margin-top:5px;"><i class="fa fa-info-circle"></i> Còn thiếu ${new Intl.NumberFormat().format(missing)} Xu</div>`;
                }

                html += `
                    <div class="exchange-card" style="position:relative;${outOfStock ? 'opacity:0.5;' : ''}">
                        ${isLimited ? '<div style="position:absolute;top:10px;left:10px;background:#ef4444;color:#fff;padding:2px 8px;border-radius:5px;font-size:10px;font-weight:800;">LIMITED</div>' : ''}
                        <div class="exchange-icon">${icon}</div>
                        <h3 style="margin:0;font-size:20px;">${item.item_name}</h3>
                        <div style="font-size:12px;opacity:0.5;margin:6px 0;">${item.item_type?.toUpperCase() || ''}</div>
                        <div style="color:var(--event-secondary);font-weight:900;font-size:24px;margin-top:10px;">
                            ${new Intl.NumberFormat().format(item.cost_currency)} <span style="font-size:14px;">XU</span>
                        </div>
                        ${missingBadge}
                        <div style="display: flex; gap: 5px; margin-top: 10px;">
                            <button class="btn-exchange" style="flex:2;" onclick="exchangeItem(${item.id})" ${outOfStock ? 'disabled' : ''}>
                                ${outOfStock ? '❌ HẾT HÀNG' : 'ĐỔI QUÀ NGAY'}
                            </button>
                            ${['title', 'theme', 'cursor', 'chat_frame'].includes(item.item_type) ? `
                                <button class="btn-preview" onclick="previewItem('${item.item_type}', ${item.item_id}, '${item.item_name.replace(/'/g, "\\'")}')" style="flex:1; background: rgba(255,255,255,0.1); color: white; border: none; border-radius: 10px; cursor: pointer;">
                                    <i class="fa fa-eye"></i>
                                </button>
                            ` : ''}
                        </div>
                        <div style="font-size:12px;opacity:0.5;margin-top:10px;">
                            Còn lại: ${stockDisplay} &nbsp;|  Giới hạn: ${item.limit_per_user > 0 ? item.limit_per_user + '/người' : 'Không giới hạn'}
                        </div>
                    </div>
                `;
            });
            $('#shop-grid').html(html);
        }

        function renderShop(items) {
            allShopItems = items || [];
            filterAndRenderShop();
        }HẾT HÀNG' : 'ĐỔI QUÀ NGAY'}
                            </button>
                            ${['title', 'theme', 'cursor', 'chat_frame'].includes(item.item_type) ? `
                                <button class="btn-preview" onclick="previewItem('${item.item_type}', ${item.item_id}, '${item.item_name.replace(/'/g, "\\'")}')" style="flex:1; background: rgba(255,255,255,0.1); color: white; border: none; border-radius: 10px; cursor: pointer;">
                                    <i class="fa fa-eye"></i>
                                </button>
                            ` : ''}
                        </div>
                        <div style="font-size:12px;opacity:0.5;margin-top:10px;">
                            Còn lại: ${stockDisplay} &nbsp;|  Giới hạn: ${item.limit_per_user > 0 ? item.limit_per_user + '/người' : 'Không giới hạn'}
                        </div>
                    </div>
                `;
            });
            $('#shop-grid').html(html);
        }

        function previewItem(type, id, name) {
            Swal.fire({
                title: 'Đang tải xem trước...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            $.get(`api_event_engine.php?action=preview_item&item_type=${type}&item_id=${id}`, function(res) {
                if (res.success) {
                    Swal.fire({
                        title: `Xem Trước: ${name}`,
                        html: `<div style="margin: 20px 0;">${res.html}</div>`,
                        confirmButtonText: 'Đóng',
                        confirmButtonColor: 'var(--event-primary)'
                    });
                } else {
                    Swal.fire('Lỗi', 'Không thể tải bản xem trước', 'error');
                }
            }).fail(() => {
                Swal.fire('Lỗi', 'Mất kết nối máy chủ', 'error');
            });
        }

        function switchTab(tab) {
            $('.e-tab').removeClass('active');
            $(`button[onclick="switchTab('${tab}')"]`).addClass('active');

            // Ẩn tất cả sections
            $('#missions-section, #shop-section, #history-section, #leaderboard-section, #vote-section, #mission-log-section, #guild-board-section').hide();
            $(`#${tab}-section`).show();

            if (tab === 'history')      loadHistory();
            if (tab === 'leaderboard')  loadLeaderboard();
            if (tab === 'vote')         loadVoteOptions();
            if (tab === 'mission-log')  loadMissionLog();
            if (tab === 'guild-board')  loadGuildBoard();
        }

        // ── FEAT 3: Daily reset countdown ──────────────────────────────────
        function startDailyResetTimer() {
            function tick() {
                const now = new Date();
                const midnight = new Date();
                midnight.setHours(24, 0, 0, 0); // next midnight
                const diff = midnight - now;
                const h  = Math.floor(diff / 3600000);
                const mi = Math.floor((diff % 3600000) / 60000);
                const s  = Math.floor((diff % 60000)   / 1000);
                const pad = n => String(n).padStart(2, '0');
                $('#daily-reset-timer').text(`⏰ Daily reset sau: ${pad(h)}:${pad(mi)}:${pad(s)}`);
            }
            tick();
            setInterval(tick, 1000);
        }

        // ── FEAT 2: Lịch sử nhiệm vụ ───────────────────────────────────────
        let missionLogLoaded = false;
        function loadMissionLog() {
            if (missionLogLoaded) return;
            $('#mission-log-rows').html('<tr><td colspan="4" style="text-align:center;padding:20px;opacity:0.5;"><i class="fa fa-spinner fa-spin"></i> Đang tải...</td></tr>');
            $.get('api_event_engine.php?action=get_mission_history', function(res) {
                missionLogLoaded = true;
                if (!res.success || !res.history || res.history.length === 0) {
                    $('#mission-log-rows').html('<tr><td colspan="4" style="text-align:center;padding:30px;opacity:0.5;">Bạn chưa nhận thưởng nhiệm vụ nào trong sự kiện này.</td></tr>');
                    return;
                }
                const cycleLabels = { daily: '🌅 Hằng Ngày', weekly: '📅 Hằng Tuần', permanent: '♾️ Vĩnh Viễn' };
                const typeIcons   = { play_game:'🎮', win_game:'🏆', earn_money:'💰', big_win:'💥', bet:'🎲', login:'📌', invite:'👥' };
                let html = '';
                res.history.forEach(row => {
                    const icon = typeIcons[row.mission_type] || '⭐';
                    const date = new Date(row.claimed_at).toLocaleString('vi-VN');
                    const cycle = cycleLabels[row.cycle] || '♾️';
                    html += `
                        <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">
                            <td style="padding:14px;font-size:13px;opacity:0.7;">${date}</td>
                            <td style="padding:14px;font-weight:700;">${icon} ${row.title}</td>
                            <td style="padding:14px;font-size:13px;opacity:0.7;">${cycle}</td>
                            <td style="padding:14px;text-align:right;font-weight:800;color:var(--event-secondary);">
                                +${new Intl.NumberFormat().format(row.reward_currency)} 🧧
                                &nbsp;+${new Intl.NumberFormat().format(row.reward_xp)} XP
                            </td>
                        </tr>`;
                });
                $('#mission-log-rows').html(html);
            });
        }

        // ── FEAT 4: BXH Bang Hội ───────────────────────────────────────────
        let guildBoardLoaded = false;
        function loadGuildBoard() {
            if (guildBoardLoaded) return;
            $('#guild-board-rows').html('<tr><td colspan="4" style="text-align:center;padding:20px;opacity:0.5;"><i class="fa fa-spinner fa-spin"></i> Đang tải...</td></tr>');
            $.get('api_event_engine.php?action=get_guild_leaderboard', function(res) {
                guildBoardLoaded = true;
                if (!res.success) { return; }
                $('#my-guild-rank-display').text(res.my_guild_rank);
                $('#my-guild-pts-display').text(new Intl.NumberFormat().format(res.my_guild_pts));

                if (!res.guild_board || res.guild_board.length === 0) {
                    $('#guild-board-rows').html('<tr><td colspan="4" style="text-align:center;padding:30px;opacity:0.5;">Chưa có bang hội nào đóng góp điểm trong sự kiện này.</td></tr>');
                    return;
                }
                let html = '';
                res.guild_board.forEach((g, i) => {
                    const rank = i + 1;
                    let badge = rank === 1 ? '🥇' : rank === 2 ? '🥈' : rank === 3 ? '🥉' : rank;
                    const isMe = g.guild_id == res.my_guild_id;
                    const rowStyle = isMe ? 'background:rgba(244,63,94,0.12);border-left:4px solid var(--event-primary);' : '';
                    const icon = g.guild_icon || '⚔️';
                    html += `
                        <tr style="border-bottom:1px solid rgba(255,255,255,0.05);${rowStyle}">
                            <td style="padding:15px;font-weight:800;font-size:18px;">${badge}</td>
                            <td style="padding:15px;font-weight:700;">${icon} ${g.guild_name}${isMe ? ' <span style="font-size:11px;background:var(--event-primary);color:#fff;padding:2px 7px;border-radius:5px;margin-left:6px;">BANG BẠN</span>' : ''}</td>
                            <td style="padding:15px;opacity:0.7;">${g.member_count} người</td>
                            <td style="padding:15px;text-align:right;font-weight:800;color:var(--event-secondary);">${new Intl.NumberFormat().format(g.total_points)}</td>
                        </tr>`;
                });
                $('#guild-board-rows').html(html);
            });
        }

        function loadHistory() {
            // Skeleton loading for history
            let skHtml = '';
            for(let i = 0; i < 5; i++) {
                skHtml += `<tr><td style="padding:15px;"><div class="skeleton" style="height:16px;width:120px;"></div></td><td style="padding:15px;"><div class="skeleton" style="height:16px;width:160px;"></div></td><td style="padding:15px;"><div class="skeleton" style="height:16px;width:60px;"></div></td><td style="padding:15px;"><div class="skeleton" style="height:16px;width:80px;margin-left:auto;"></div></td></tr>`;
            }
            $('#history-rows').html(skHtml);

            $.get('api_event_engine.php?action=get_exchange_history', function(res) {
                if (res.success) {
                    if (!res.history || res.history.length === 0) {
                        $('#history-rows').html('<tr><td colspan="4" style="text-align:center;padding:30px;opacity:0.5;">Bạn chưa đổi vật phẩm nào trong sự kiện này.</td></tr>');
                        return;
                    }
                    let html = '';
                    res.history.forEach(row => {
                        const date = new Date(row.created_at).toLocaleString('vi-VN');
                        html += `
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 15px; opacity: 0.7; font-size: 13px;">${date}</td>
                                <td style="padding: 15px; font-weight: 700; color: var(--event-primary);">${row.item_name}</td>
                                <td style="padding: 15px; opacity: 0.7; font-size: 13px;">${row.item_type.toUpperCase()}</td>
                                <td style="padding: 15px; text-align: right; font-weight: 800; color: var(--event-secondary);">
                                    -${new Intl.NumberFormat().format(row.cost_currency)}
                                </td>
                            </tr>
                        `;
                    });
                    $('#history-rows').html(html);
                } else {
                    $('#history-rows').html('<tr><td colspan="4" style="text-align:center;padding:30px;color:red;">Lỗi tải lịch sử!</td></tr>');
                }
            });
        }

        function loadLeaderboard() {
            // Skeleton loading for leaderboard
            let skHtml = '';
            for(let i = 0; i < 8; i++) {
                const w = [40, 180, 90][i % 3] || 40;
                skHtml += `<tr style="border-bottom:1px solid rgba(255,255,255,0.05);"><td style="padding:15px;"><div class="skeleton" style="height:20px;width:30px;"></div></td><td style="padding:15px;"><div style="display:flex;align-items:center;gap:12px;"><div class="skeleton" style="width:36px;height:36px;border-radius:50%;"></div><div class="skeleton" style="height:16px;width:120px;"></div></div></td><td style="padding:15px;"><div class="skeleton" style="height:16px;width:80px;margin-left:auto;"></div></td></tr>`;
            }
            $('#leaderboard-rows').html(skHtml);

            $.get('api_event_engine.php?action=get_leaderboard', function(res) {
                if (res.success) {
                    $('#my-event-rank').text(res.my_rank);
                    $('#my-event-points').text(new Intl.NumberFormat().format(res.my_points));
                    
                    if (!res.leaderboard || res.leaderboard.length === 0) {
                        $('#leaderboard-rows').html('<tr><td colspan="3" style="text-align:center;padding:30px;opacity:0.5;">Chưa có dữ liệu xếp hạng. Hãy tích lũy điểm ngay!</td></tr>');
                        return;
                    }
                    
                    let html = '';
                    res.leaderboard.forEach((row, index) => {
                        const rank = index + 1;
                        let rankBadge = rank;
                        if (rank === 1) rankBadge = '🥇';
                        else if (rank === 2) rankBadge = '🥈';
                        else if (rank === 3) rankBadge = '🥉';
                        
                        const isMe = row.username === '<?php echo htmlspecialchars($_SESSION['Name'] ?? ''); ?>';
                        const meStyle = isMe ? 'background: rgba(244,63,94,0.15); border-left: 4px solid var(--event-primary); font-weight: bold;' : '';
                        
                        const avatar = row.avatar ? row.avatar : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(row.username) + '&background=random';
                        
                        html += `
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05); ${meStyle}">
                                <td style="padding: 15px; font-weight: 800; font-size: 18px;">${rankBadge}</td>
                                <td style="padding: 15px; display: flex; align-items: center; gap: 12px;">
                                    <img src="${avatar}" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(255,255,255,0.1);">
                                    <span style="font-weight: 700; ${isMe ? 'color: var(--event-primary);' : ''}">${row.username}</span>
                                </td>
                                <td style="padding: 15px; text-align: right; font-weight: 800; color: var(--event-secondary);">
                                    ${new Intl.NumberFormat().format(row.points)}
                                </td>
                            </tr>
                        `;
                    });
                    $('#leaderboard-rows').html(html);
                } else {
                    Swal.fire('Lỗi!', res.message, 'error');
                }
            });
        }

        function claimReward(id) {
            // Lấy vị trí nút bấm để spawn coin fly
            const btn = event.currentTarget || document.querySelector(`button[onclick="claimReward(${id})"]`);
            const rect = btn ? btn.getBoundingClientRect() : { left: window.innerWidth/2, top: window.innerHeight/2 };

            $.post('api_event_engine.php', { action: 'claim_reward', mission_id: id }, function(res) {
                if (res.success) {
                    // Spawn 5 coin emoji bay lên
                    for (let i = 0; i < 5; i++) {
                        const coin = $('<div class="coin-fly">🧧</div>').css({
                            left: (rect.left + rect.width/2 - 14 + (Math.random()-0.5)*60) + 'px',
                            top:  (rect.top + rect.height/2 - 14) + 'px',
                            animationDelay: (i * 0.12) + 's'
                        });
                        $('body').append(coin);
                        setTimeout(() => coin.remove(), 1400);
                    }

                    // Animated counter bump
                    const currentTokens = parseInt($('#user-tokens').text().replace(/,/g,'')) || 0;
                    const reward = parseInt(res.reward) || 0;
                    const newVal = currentTokens + reward;
                    animateCounter('#user-tokens', currentTokens, newVal);
                    $('#user-tokens').addClass('counter-bump').one('animationend', function() { $(this).removeClass('counter-bump'); });

                    Swal.fire({
                        title: 'Nhận Thưởng!',
                        html: `<div style="font-size:48px;">🧧</div><p>+<b style="color:#fbbf24;font-size:22px;">${new Intl.NumberFormat().format(reward)}</b> Xu Sự Kiện</p>`,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false,
                        background: '#0f172a',
                        color: '#fff'
                    });
                    // Reload event sau 2s để cập nhật missions
                    setTimeout(loadEvent, 2100);
                } else {
                    Swal.fire('Lỗi!', res.message, 'error');
                }
            });
        }

        function animateCounter(selector, from, to) {
            const duration = 800;
            const startTime = performance.now();
            function step(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
                const current = Math.round(from + (to - from) * eased);
                $(selector).text(new Intl.NumberFormat().format(current));
                if (progress < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        }

        function exchangeItem(id) {
            $.post('api_event_engine.php', { action: 'exchange_item', item_id: id }, function(res) {
                if (res.success) {
                    Swal.fire('Thành công!', res.message, 'success');
                    loadEvent();
                } else {
                    Swal.fire('Lỗi!', res.message, 'error');
                }
            });
        }

        // ================= VOTE LOGIC =================
        function loadVoteOptions() {
            $('#vote-options-container').html('<div style="opacity: 0.5;">Đang tải bình chọn...</div>');
            $.get('api_event_vote.php?action=get_options', function(res) {
                if(res.success) {
                    let html = '';
                    const totalVotes = res.total_votes;
                    $('#vote-total').text(`Tổng lượt bình chọn: ${new Intl.NumberFormat().format(totalVotes)}`);

                    res.options.forEach(opt => {
                        const pct = totalVotes > 0 ? ((opt.votes / totalVotes) * 100).toFixed(1) : 0;
                        const isMyVote = res.my_vote === parseInt(opt.id);
                        
                        let voteBtnHtml = '';
                        if (res.my_vote) {
                            if (isMyVote) {
                                voteBtnHtml = `<span style="color: #4ade80; font-weight: bold;"><i class="fa fa-check-circle"></i> BẠN ĐÃ CHỌN</span>`;
                            } else {
                                voteBtnHtml = `<span style="opacity: 0.5;">Đã vote tùy chọn khác</span>`;
                            }
                        } else {
                            voteBtnHtml = `<button onclick="castVote(${opt.id})" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; padding: 10px 20px; border-radius: 50px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);"><i class="fa fa-thumbs-up"></i> BÌNH CHỌN</button>`;
                        }

                        html += `
                            <div style="background: rgba(0,0,0,0.3); border: 2px solid ${isMyVote ? '#4ade80' : 'rgba(255,255,255,0.1)'}; padding: 20px; border-radius: 15px; display: flex; flex-direction: column; text-align: left; position: relative; overflow: hidden;">
                                <div style="position: absolute; left: 0; bottom: 0; height: 5px; width: ${pct}%; background: ${isMyVote ? '#4ade80' : 'var(--event-primary)'}; transition: 1s ease;"></div>
                                
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <div style="font-size: 18px; font-weight: 900;">${opt.icon} ${opt.title}</div>
                                    <div style="font-size: 16px; font-weight: bold; color: ${isMyVote ? '#4ade80' : 'var(--event-primary)'};">${pct}% (${new Intl.NumberFormat().format(opt.votes)} lượt)</div>
                                </div>
                                <p style="margin: 0 0 15px 0; font-size: 14px; opacity: 0.8;">${opt.description}</p>
                                <div style="text-align: right;">
                                    ${voteBtnHtml}
                                </div>
                            </div>
                        `;
                    });
                    $('#vote-options-container').html(html);
                } else {
                    $('#vote-options-container').html(`<div style="color: red;">${res.message}</div>`);
                }
            });
        }

        function castVote(optionId) {
            Swal.fire({
                title: 'Xác nhận bình chọn?',
                text: "Bạn không thể thay đổi sau khi đã bình chọn!",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Đồng ý',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_event_vote.php', { action: 'vote', option_id: optionId }, function(res) {
                        if (res.success) {
                            Swal.fire('Thành công!', res.message, 'success');
                            loadVoteOptions();
                        } else {
                            Swal.fire('Lỗi!', res.message, 'error');
                        }
                    });
                }
            });
        }

        $(document).ready(function() {
            loadEvent();
            startDailyResetTimer(); // FEAT 3: countdown reset nhiệm vụ daily
        });
    </script>
</body>
</html>
