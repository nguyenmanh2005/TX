<?php
/**
 * Biệt Thự Hoàng Gia & Phòng Trưng Bày Cúp GTLM (Ý Tưởng 3)
 * [NEW FILE] - Hoạt động độc lập 100%, tuân thủ Rule 2.1 & không đè file hệ thống
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php';
$userId = isset($_SESSION['Iduser']) ? (int)$_SESSION['Iduser'] : 1;
$targetId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $userId;

// Load theme chung để có Three.js background, SweetAlert notifications và SSE event polling
require_once __DIR__ . '/load_theme.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Biệt Thự Hoàng Gia GTLM — Trưng bày cúp chiến thắng, nội thất luxury và thăm biệt thự các đạo hữu.">
    <title>🏡 Biệt Thự Hoàng Gia GTLM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800;900&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/tower-and-lounge.css?v=2">
    <link rel="stylesheet" href="assets/css/sound-fx-hub.css">
    <script src="assets/js/sound-fx-hub.js"></script>
    <script src="assets/js/tower-and-lounge.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="gtlm-royal-body">
    <div class="gtlm-main-container">
        <!-- Header -->
        <div class="royal-header" style="flex-wrap:wrap; gap:16px;">
            <div style="display:flex; align-items:center; gap:16px; flex:1; min-width:0;">
                <img id="loungeOwnerAvatar" src="img/avatar_default.png" style="width:72px; height:72px; border-radius:50%; border:3px solid #fbbf24; box-shadow:0 0 20px rgba(245,158,11,0.6); flex-shrink:0; object-fit:cover;">
                <div style="min-width:0;">
                    <h1 id="loungeTitle" style="font-size:clamp(18px,3vw,28px); margin:0; font-weight:900; background:linear-gradient(to right,#fbbf24,#f472b6,#60a5fa); -webkit-background-clip:text; -webkit-text-fill-color:transparent; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        🏡 Biệt Thự Hoàng Gia GTLM
                    </h1>
                    <p style="margin:6px 0 0; color:#fbbf24; font-weight:700; font-size:14px; display:flex; gap:16px; flex-wrap:wrap;">
                        <span>❤️ <span id="loungeLikes">0</span> Tim Chúc Phúc</span>
                        <span>👁️ <span id="loungeVisits">0</span> Lượt Ghé Thăm</span>
                    </p>
                </div>
            </div>
            <div class="actions" style="flex-wrap:wrap; gap:10px;">
                <button class="btn-royal" style="background:linear-gradient(135deg,#ef4444,#b91c1c); border-color:#fca5a5; color:#fff;" onclick="LoungeEngine.likeRoom()">❤️ Thả Tim</button>
                <button class="btn-royal btn-blue" onclick="document.getElementById('neighborSection').scrollIntoView({behavior:'smooth'})">🏘️ Hàng Xóm</button>
                <?php if ($targetId !== $userId): ?>
                <a href="my_lounge.php" class="btn-royal btn-purple">🏠 Biệt Thự Tôi</a>
                <?php endif; ?>
                <a href="index.php" class="btn-royal" style="background:rgba(30,41,59,0.8); border-color:#475569; color:#94a3b8;">⬅️ Sảnh Chính</a>
            </div>
        </div>

        <!-- Stats bar nhanh -->
        <div id="loungeStatsBar" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; margin-bottom:24px;">
            <div class="glass-card" style="padding:16px; text-align:center;">
                <div style="font-size:28px;">🏗️</div>
                <div id="loungeTrophyCount" style="font-size:22px; font-weight:900; color:#fbbf24;">...</div>
                <div style="font-size:12px; color:#94a3b8;">Cúp Chiến Thắng</div>
            </div>
            <div class="glass-card" style="padding:16px; text-align:center;">
                <div style="font-size:28px;">🏫</div>
                <div id="loungeItemCount" style="font-size:22px; font-weight:900; color:#a78bfa;">...</div>
                <div style="font-size:12px; color:#94a3b8;">Nội Thất Sở Hữu</div>
            </div>
            <div class="glass-card" style="padding:16px; text-align:center;">
                <div style="font-size:28px;">❤️</div>
                <div id="loungeStatLikes" style="font-size:22px; font-weight:900; color:#ef4444;">...</div>
                <div style="font-size:12px; color:#94a3b8;">Tim Chúc Phúc</div>
            </div>
            <div class="glass-card" style="padding:16px; text-align:center;">
                <div style="font-size:28px;">👁️</div>
                <div id="loungeStatVisits" style="font-size:22px; font-weight:900; color:#38bdf8;">...</div>
                <div style="font-size:12px; color:#94a3b8;">Lượt Ghé Thăm</div>
            </div>
        </div>

        <!-- Khu vực Quick-Access: Tháp Thần Bài -->
        <div class="glass-card" style="margin-bottom:24px; background:radial-gradient(circle at 30% 50%, rgba(124,58,237,0.25) 0%, rgba(15,23,42,0.9) 70%); border-color:rgba(167,139,250,0.3);">
            <h3 style="color:#a78bfa;"><i class="fas fa-chess-rook"></i> 🗳️ Cổng Bào: Tháp Thần Bài 100 Tầng</h3>
            <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
                <div style="flex:1; min-width:200px;">
                    <p style="color:#cbd5e1; font-size:14px; line-height:1.7; margin:0 0 16px 0;">
                        Chinh phục từng tầng Tháp, đánh bại Boss AI và nhận <b style="color:#fbbf24;">Cúp Vàng Độc Quyền</b> về trưng bày ngay tại Biệt Thự này.
                        Mỗi tầng đặc biệt (Tầng 5, 10, 20...) sẽ có phần thưởng tượng hoàng gia độc nhất vô nhị!
                    </p>
                    <div id="towerQuickStats" style="display:flex; gap:20px; flex-wrap:wrap;">
                        <div style="background:rgba(0,0,0,0.3); border-radius:12px; padding:10px 18px; text-align:center;">
                            <div style="font-size:22px; font-weight:900; color:#fbbf24;">Tầng <span id="qTowerFloor">?</span></div>
                            <div style="font-size:12px; color:#94a3b8;">Hiện Đang</div>
                        </div>
                        <div style="background:rgba(0,0,0,0.3); border-radius:12px; padding:10px 18px; text-align:center;">
                            <div style="font-size:22px; font-weight:900; color:#a78bfa;"><span id="qTowerWins">?</span> Thắng</div>
                            <div style="font-size:12px; color:#94a3b8;">Tổng Chiến Thắng</div>
                        </div>
                    </div>
                </div>
                <div style="text-align:center; flex-shrink:0;">
                    <div style="font-size:72px; margin-bottom:8px; animation:floatItem 3s ease-in-out infinite;">🗳️</div>
                    <a href="tower_of_gods.php" class="btn-royal" style="background:linear-gradient(135deg,#7c3aed,#4c1d95); color:#fff; border-color:#a78bfa; font-size:16px; padding:14px 28px;">
                        ⚤️ Tháp Thần Bài — Chinh Phục Ngay
                    </a>
                </div>
            </div>
        </div>
        <div class="glass-card" style="margin-bottom:28px;">
            <h3>✨ Phòng Trưng Bày Nội Thất & Cúp Vàng Trấn Ải</h3>
            <p style="color:#cbd5e1; font-size:14px; margin-top:-10px; margin-bottom:18px;">
                Trưng bày các cúp vàng chiến thắng thu nhận được từ Tháp Thần Bài và nội thất luxury từ cửa hàng:
            </p>
            <div class="lounge-room-view" id="loungeRoomGrid">
                <p style="color:#f8fafc; grid-column:span 4; text-align:center; align-self:center;">⏳ Đang tải nội thất phòng...</p>
            </div>
        </div>

        <!-- Kho Đồ & Cửa Hàng & Sổ Lưu Niệm -->
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px; margin-bottom:28px;">
            <!-- Kho Đồ Cá Nhân & Cửa Hàng -->
            <div class="glass-card">
                <div id="loungeInventorySection">
                    <h3>📦 Kho Báu & Nội Thất Chưa Trưng Bày</h3>
                    <div id="loungeInventoryList" style="min-height:100px; max-height:260px; overflow-y:auto; margin-bottom:24px;">
                        <p style="color:#94a3b8; font-style:italic;">⏳ Đang kiểm tra kho...</p>
                    </div>
                </div>

                <h3>🛒 Cửa Hàng Nội Thất Luxury GTLM</h3>
                <p style="color:#cbd5e1; font-size:13px; margin-top:-10px;">Dùng GTLM kiếm được để sắm sửa nội thất sang trọng:</p>
                <div id="loungeShopList" style="max-height:300px; overflow-y:auto;">
                    <p style="color:#94a3b8; font-style:italic;">⏳ Đang tải danh mục...</p>
                </div>
            </div>

            <!-- Sổ Lưu Niệm -->
            <div class="glass-card">
                <h3>📖 Sổ Lưu Niệm & Lời Chúc Khách Đáo Thăm</h3>
                <p style="color:#cbd5e1; font-size:13px; margin-top:-10px;">Để lại chữ ký và lời chúc tốt lành cho chủ nhân căn biệt thự này:</p>
                
                <div style="display:flex; gap:10px; margin-bottom:18px;">
                    <input type="text" id="gbInput" placeholder="VD: Biệt thự lộng lẫy quá, chúc chủ nhà húp đậm GTLM! ❤️" style="flex:1; background:#0f172a; border:1px solid #475569; color:#f8fafc; padding:12px 16px; border-radius:12px; outline:none; font-size:14px;">
                    <button class="btn-royal" onclick="LoungeEngine.signGuestbook()">✍️ Ký Tên</button>
                </div>

                <div id="loungeGuestbookList" style="max-height:480px; overflow-y:auto;">
                    <p style="color:#94a3b8; font-style:italic;">⏳ Đang tải Sổ Lưu Niệm...</p>
                </div>
            </div>
        </div>

        <!-- Danh Sách Biệt Thự Hàng Xóm & Ghé Thăm Người Khác -->
        <div class="glass-card" id="neighborSection" style="margin-bottom:28px;">
            <h3>🏘️ Thăm Biệt Thự Hàng Xóm & Top Đại Gia GTLM</h3>
            <p style="color:#cbd5e1; font-size:14px; margin-top:-10px; margin-bottom:18px;">
                Bạn có thể nhập trực tiếp ID người chơi muốn tới thăm, hoặc chọn từ danh sách top Biệt Thự Hoàng Gia bên dưới:
            </p>

            <div style="display:flex; gap:12px; max-width:500px; margin-bottom:24px;">
                <input type="number" id="quickVisitId" placeholder="Nhập ID người chơi (VD: 1, 2, 5...)" style="flex:1; background:#0f172a; border:1px solid #f59e0b; color:#f8fafc; padding:12px 16px; border-radius:12px; outline:none; font-size:14px; font-weight:700;">
                <button class="btn-royal" style="background:linear-gradient(135deg,#f59e0b,#d97706); color:#000; font-weight:800;" onclick="const uid = document.getElementById('quickVisitId').value; if(uid) window.location.href='my_lounge.php?user_id='+uid; else Swal.fire('Thông báo','Vui lòng nhập ID người chơi!','warning');">🚀 Đến Ngay</button>
            </div>

            <h4 style="color:#fbbf24; margin-bottom:14px;"><i class="fas fa-crown"></i> Top Biệt Thự Nổi Bật Đang Mở Cửa:</h4>
            <div id="neighborListContainer" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:16px;">
                <p style="color:#94a3b8; font-style:italic;">⏳ Đang tìm kiếm biệt thự hàng xóm...</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const targetId = urlParams.get('user_id');

            // Load Lounge rồi hook vào để cập nhật stats bar
            const origLoad = LoungeEngine.loadLounge;
            LoungeEngine.loadLounge = async function(uid) {
                await origLoad.call(LoungeEngine, uid);
                // Cập nhật stats bar sau khi data đã render
                const tId = uid || (urlParams.get('user_id') ? parseInt(urlParams.get('user_id')) : null);
                const url = tId ? `api_lounge.php?action=view&target_id=${tId}` : `api_lounge.php?action=view`;
                try {
                    const res = await fetch(url);
                    const data = await res.json();
                    if (!data.success) return;

                    const allItems = [...(data.placed_items || []), ...(data.inventory || [])];
                    const trophyItems = allItems.filter(i => i.item_type === 'trophy' || i.item_type === 'statue');

                    const el = id => document.getElementById(id);
                    if (el('loungeTrophyCount')) el('loungeTrophyCount').textContent = trophyItems.length;
                    if (el('loungeItemCount')) el('loungeItemCount').textContent = allItems.length;
                    if (el('loungeStatLikes')) el('loungeStatLikes').textContent = data.room?.likes_count ?? 0;
                    if (el('loungeStatVisits')) el('loungeStatVisits').textContent = data.room?.visits_count ?? 0;
                } catch(e) {}
            };

            LoungeEngine.loadLounge(targetId);

            // Load Tower quick-stats
            fetch('api_tower_gods.php?action=info')
                .then(r => r.json())
                .then(d => {
                    if (!d.success) return;
                    const el = id => document.getElementById(id);
                    if (el('qTowerFloor')) el('qTowerFloor').textContent = d.progress?.current_floor ?? '?';
                    if (el('qTowerWins')) el('qTowerWins').textContent = d.progress?.total_wins ?? '?';
                })
                .catch(() => {});
        });
    </script>
</body>
</html>
