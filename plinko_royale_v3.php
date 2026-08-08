<?php
/**
 * Đấu Trường Plinko Royale V3 Multi-Drop (Ý Tưởng 6)
 * [NEW FILE] - Hoạt động độc lập 100%, tuân thủ Rule 2.1 & không đè file hệ thống
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php';
$userId = isset($_SESSION['Iduser']) ? (int)$_SESSION['Iduser'] : 1;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎰 Đấu Trường Plinko Royale V3 Multi-Drop - 100 Bóng Thần Vũ Trụ GTLM</title>
    <link rel="stylesheet" href="assets/css/plinko-royale-v3.css">
    <link rel="stylesheet" href="assets/css/sound-fx-hub.css">
    <script src="assets/js/sound-fx-hub.js"></script>
    <script src="assets/js/plinko-royale-v3.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="plinko-royale-body">
    <!-- Header -->
    <header class="plinko-header">
        <div class="plinko-header-left">
            <span class="plinko-logo-badge">🔥 ROYALE V3 MULTI-DROP</span>
            <h1>🎰 ĐẤU TRƯỜNG PLINKO ROYALE V3</h1>
        </div>

        <div class="plinko-user-stats">
            <div class="plinko-balance">
                <i class="fas fa-coins" style="color:#fbbf24;"></i>
                <span id="plinkoBalance">0 GTLM</span>
            </div>
            <a href="my_lounge.php" class="btn-royale-action" style="background:linear-gradient(135deg,#c084fc,#9333ea);">🏡 Biệt Thự Trưng Bày Cúp</a>
            <a href="tower_of_gods.php" class="btn-royale-action" style="background:linear-gradient(135deg,#60a5fa,#2563eb);">🗼 Tháp Thần Bài</a>
            <a href="chat.php" class="btn-royale-action" target="_blank" style="background:linear-gradient(135deg,#34d399,#059669);">💬 Kênh Chat</a>
            <a href="index.php" class="btn-royale-action" style="background:#334155;">⬅️ Sảnh Chính</a>
        </div>
    </header>

    <!-- Main 2-Column Grid -->
    <main class="plinko-main-grid">
        <!-- Col 1: Bảng Điều Khiển Cược -->
        <aside class="plinko-panel">
            <h3><i class="fas fa-sliders-h"></i> ⚡ BẢNG ĐIỀU KHIỂN CƯỢC</h3>

            <div class="form-group-royale">
                <label>1. Số Hàng Đinh Chốt (Rows)</label>
                <div class="segment-group">
                    <button class="segment-btn btn-row-select" data-rows="8">8 Hàng</button>
                    <button class="segment-btn btn-row-select" data-rows="12">12 Hàng</button>
                    <button class="segment-btn btn-row-select active" data-rows="16">16 Hàng 🔥</button>
                </div>
            </div>

            <div class="form-group-royale">
                <label>2. Mức Độ Rủi Ro (Risk & Multipliers)</label>
                <div class="segment-group">
                    <button class="segment-btn btn-risk-select" data-risk="low">An Toàn</button>
                    <button class="segment-btn btn-risk-select" data-risk="medium">Royale Vàng</button>
                    <button class="segment-btn btn-risk-select active" data-risk="high">Siêu Khủng X1000 👑</button>
                </div>
            </div>

            <div class="form-group-royale">
                <label>3. Số Lượng Bóng Thả Đồng Thời (Multi-Drop)</label>
                <div class="ball-count-grid">
                    <button class="segment-btn btn-ball-select" data-count="1">1</button>
                    <button class="segment-btn btn-ball-select active" data-count="10">10</button>
                    <button class="segment-btn btn-ball-select" data-count="25">25</button>
                    <button class="segment-btn btn-ball-select" data-count="50">50 🔥</button>
                    <button class="segment-btn btn-ball-select" style="background:linear-gradient(135deg,#ef4444,#b91c1c); color:#fff; font-weight:900;" data-count="100">100 💥</button>
                </div>
            </div>

            <div class="form-group-royale">
                <label>4. Tổng Số GTLM Cược (Total Bet)</label>
                <div class="bet-input-wrapper">
                    <input type="number" id="betInput" value="10000" min="1000" step="1000">
                </div>
                <div class="bet-quick-btns">
                    <button class="btn-bet-quick" onclick="PlinkoRoyaleV3.setQuickBet(0.5)">/2</button>
                    <button class="btn-bet-quick" onclick="PlinkoRoyaleV3.setQuickBet(2)">X2</button>
                    <button class="btn-bet-quick" onclick="PlinkoRoyaleV3.setQuickBet(10)">X10</button>
                    <button class="btn-bet-quick" style="background:#b45309; color:#fff;" onclick="PlinkoRoyaleV3.setQuickBet(-1)">ALL-IN 🚀</button>
                </div>
            </div>

            <div style="margin-top:28px;">
                <button id="btnDropMain" class="btn-drop-main" onclick="PlinkoRoyaleV3.triggerDrop()">
                    💥 THẢ BÓNG ROYALE NGAY
                </button>
            </div>

            <div style="margin-top:20px; background:rgba(15,23,42,0.6); border:1px dashed #475569; border-radius:14px; padding:12px; font-size:12px; color:#cbd5e1; line-height:1.5;">
                💡 <i><b>Quy tắc Royale V3:</b> Thả tối đa <b>100 bóng cùng lúc</b> trên hệ thống vật lý Canvas 60 FPS. Nếu lọt vào lỗ <b>X1000</b> hoặc thắng > 1 triệu GTLM, bạn sẽ tự động nhận <b>Cúp Vàng Hoàng Gia</b> vào Biệt Thự và vinh danh lên Kênh Chat!</i>
            </div>
        </aside>

        <!-- Col 2: Sân Khấu Plinko Canvas Physics 60 FPS -->
        <section class="plinko-stage-wrapper" id="plinkoStageWrapper">
            <!-- Session Profit Bar -->
            <div class="session-profit-bar" id="sessionProfitBar">
                <div class="session-stat">
                    <span class="session-label">💰 TỔNG CỬA CƯỢC</span>
                    <span class="session-value" id="sessionBetEl">0 GTLM</span>
                </div>
                <div class="session-divider"></div>
                <div class="session-stat">
                    <span class="session-label">🎯 TỔNG THẮNG</span>
                    <span class="session-value" id="sessionWinEl" style="color:#34d399;">0 GTLM</span>
                </div>
                <div class="session-divider"></div>
                <div class="session-stat">
                    <span class="session-label">📈 LỢI NHUẬN</span>
                    <span class="session-value" id="sessionProfitEl" style="color:#94a3b8;">0 GTLM</span>
                </div>
            </div>
            <canvas id="plinkoCanvas"></canvas>
            <div class="multiplier-slots-container" id="multiplierSlotsContainer">
                <!-- Multiplier slots injected by JS -->
            </div>
        </section>

    </main>

    <!-- Win Toast Notification Container -->
    <div id="winToastContainer"></div>

    <!-- Royale Celebration Modal Overlay -->
    <div class="royale-celebration-overlay" id="royaleCelebrationOverlay">
        <div class="royale-celebration-box">
            <div style="font-size:72px; margin-bottom:10px;">👑💥🎉</div>
            <h2 id="celMultText" style="font-size:36px; color:#fde047; margin:0 0 10px 0; font-weight:900;">X1000 ROYALE JACKPOT!</h2>
            <p style="color:#f8fafc; font-size:18px; margin:0 0 20px 0;">Chúc mừng bạn vừa bùng nổ vũ trụ Plinko Royale V3 với số GTLM thắng siêu khủng:</p>
            <div id="celWinText" style="font-size:42px; font-weight:900; color:#34d399; margin-bottom:24px;">+10,000,000 GTLM</div>
            <div style="background:rgba(245,158,11,0.2); border:1px dashed #f59e0b; padding:12px; border-radius:16px; color:#fef08a; font-size:14px;">
                🎁 <b>Báu Vật Đã Trao:</b> Cúp Vàng Plinko Royale X1000 (Xem tại Biệt Thự Hoàng Gia!)
            </div>
        </div>
    </div>
</body>
</html>
