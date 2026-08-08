<?php
session_start();
require_once 'db_connect.php';
require_once 'admin_helper.php';

// Bảo mật: Chỉ cho phép Admin (Role >= 1) truy cập
requireAdmin($conn, $_SESSION['Iduser'] ?? 0);

$userId = $_SESSION['Iduser'];

// Load theme để đồng bộ giao diện
require_once 'load_theme.php';
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

// Thống kê pvp
$totalMatches = $conn->query("SELECT COUNT(*) as count FROM pvp_challenges")->fetch_assoc()['count'] ?? 0;
$activeMatches = $conn->query("SELECT COUNT(*) as count FROM pvp_challenges WHERE status IN ('pending', 'accepted')")->fetch_assoc()['count'] ?? 0;
$totalVolume = $conn->query("SELECT SUM(bet_amount) as total FROM pvp_challenges WHERE status = 'finished'")->fetch_assoc()['total'] ?? 0;

// Lấy danh sách trận đấu PvP
$sql = "SELECT c.*, 
               u1.Name as challenger_name, u1.ImageURL as challenger_avatar,
               u2.Name as challenged_name, u2.ImageURL as challenged_avatar
        FROM pvp_challenges c
        JOIN users u1 ON c.challenger_id = u1.Iduser
        JOIN users u2 ON c.opponent_id = u2.Iduser
        ORDER BY c.created_at DESC";
$res = $conn->query($sql);
$matches = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $matches[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛡️ Quản Lý Đấu Trường PvP | Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg: #0b0f19;
            --panel: rgba(17, 24, 39, 0.75);
            --primary: #8b5cf6;
            --primary-glow: rgba(139, 92, 246, 0.4);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text-main: #f3f4f6;
            --text-sub: #9ca3af;
            --glass-border: rgba(255, 255, 255, 0.08);
            --glass-hover: rgba(255, 255, 255, 0.03);
            --border-radius: 24px;
        }

        body {
            background: var(--bg);
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(139, 92, 246, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(16, 185, 129, 0.08) 0%, transparent 40%);
            color: var(--text-main);
            font-family: 'Outfit', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .portal-header {
            background: var(--panel);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 30px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }

        .header-title h1 {
            margin: 0;
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, #a78bfa, #c084fc, #60a5fa);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-title p {
            margin: 8px 0 0 0;
            color: var(--text-sub);
            font-size: 14px;
            font-weight: 500;
        }

        .btn-portal {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-portal:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 255, 255, 0.1);
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
        }

        .card {
            background: var(--panel);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 35px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            margin-bottom: 30px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            padding: 18px 12px;
            border-bottom: 2px solid var(--glass-border);
            color: var(--text-sub);
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 18px 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 15px;
            vertical-align: middle;
        }

        tr:hover td {
            background: var(--glass-hover);
        }

        .player-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .player-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .player-name {
            font-weight: 600;
            color: #fff;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-pending {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .badge-accepted {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
            animation: pulse 1.5s infinite alternate;
        }

        .badge-finished {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-cancelled {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        @keyframes pulse {
            from { opacity: 0.7; }
            to { opacity: 1; }
        }

        .bet-amount {
            color: #fbbf24;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
        }

        .actions-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 8px 12px;
            border-radius: 8px;
            border: none;
            font-size: 13px;
            font-weight: bold;
            color: #fff;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        .btn-spectate {
            background: rgba(139, 92, 246, 0.2);
            color: #c084fc;
            border: 1px solid rgba(139, 92, 246, 0.4);
        }

        .btn-spectate:hover {
            background: var(--primary);
            color: #fff;
            transform: translateY(-1px);
        }

        .btn-cancel {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.4);
        }

        .btn-cancel:hover {
            background: var(--danger);
            color: #fff;
            transform: translateY(-1px);
        }

        .btn-force {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.4);
        }

        .btn-force:hover {
            background: #2563eb;
            color: #fff;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: rgba(156, 163, 175, 0.2);
            color: #d1d5db;
            border: 1px solid rgba(156, 163, 175, 0.4);
        }

        .btn-delete:hover {
            background: #4b5563;
            color: #fff;
            transform: translateY(-1px);
        }

        /* --- STATS CARD OVERRIDES --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--panel);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: rgba(255, 255, 255, 0.15);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
        }

        .stat-icon {
            font-size: 32px;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }

        .stat-content {
            flex: 1;
        }

        .stat-title {
            font-size: 13px;
            color: var(--text-sub);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 26px;
            font-weight: 800;
            color: #fff;
            margin-top: 5px;
        }

        /* --- TAB SWITCH PANEL --- */
        .controls-panel {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
        }

        .search-wrapper {
            position: relative;
            flex: 1;
            max-width: 450px;
            min-width: 250px;
        }

        .search-wrapper input {
            width: 100%;
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            color: #fff;
            font-family: inherit;
            font-size: 14.5px;
            outline: none;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .search-wrapper input:focus {
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .tabs-wrapper {
            display: flex;
            gap: 8px;
            background: rgba(255, 255, 255, 0.03);
            padding: 6px;
            border-radius: 14px;
            border: 1px solid var(--glass-border);
        }

        .tab-btn {
            padding: 10px 18px;
            background: transparent;
            border: none;
            border-radius: 10px;
            color: var(--text-sub);
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%);
            color: #fff;
            box-shadow: 0 4px 10px var(--primary-glow);
        }

        .tab-btn:hover:not(.active) {
            color: #fff;
            background: rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- --- HEADER --- -->
        <header class="portal-header">
            <div class="header-title">
                <h1>⚔️ Quản Lý Đấu Trường PvP</h1>
                <p>Danh sách toàn bộ các phòng thách đấu PvP và bảng điều phối khẩn dành cho quản trị viên</p>
            </div>
            <div>
                <a href="testadmin.php" class="btn-portal"><i class="fa fa-sliders"></i> Admin Portal</a>
            </div>
        </header>

        <!-- --- STATS CARDS --- -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon" style="background: rgba(139, 92, 246, 0.15); color: #c084fc;">📊</span>
                <div class="stat-content">
                    <div class="stat-title">Tổng Số Trận</div>
                    <div class="stat-value"><?= number_format($totalMatches) ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <span class="stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">⚔️</span>
                <div class="stat-content">
                    <div class="stat-title">Đang Hoạt Động</div>
                    <div class="stat-value"><?= number_format($activeMatches) ?></div>
                </div>
            </div>

            <div class="stat-card">
                <span class="stat-icon" style="background: rgba(251, 191, 36, 0.15); color: #fbbf24;">💰</span>
                <div class="stat-content">
                    <div class="stat-title">GTLM Cược Hoàn Tất</div>
                    <div class="stat-value" style="color: #fbbf24;"><?= number_format($totalVolume) ?> GTLM</div>
                </div>
            </div>
        </div>

        <!-- --- MATCHES LIST --- -->
        <div class="card">
            <!-- Filter Controls -->
            <div class="controls-panel">
                <div class="search-wrapper">
                    <input type="text" id="search-input" placeholder="🔍 Nhập tên người chơi hoặc ID để lọc..." autocomplete="off">
                </div>
                <div class="tabs-wrapper">
                    <button class="tab-btn active" onclick="filterStatus('all')">Tất Cả</button>
                    <button class="tab-btn" onclick="filterStatus('active')">Đang Hoạt Động</button>
                    <button class="tab-btn" onclick="filterStatus('completed')">Đã Kết Thúc</button>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Game</th>
                            <th>Đấu Thủ 1 (Challenger)</th>
                            <th>Đấu Thủ 2 (Opponent)</th>
                            <th>GTLM Cược</th>
                            <th>Trạng Thái</th>
                            <th>Ngày Tạo</th>
                            <th style="text-align: right;">Hành Động</th>
                        </tr>
                    </thead>
                    <tbody id="matches-table-body">
                        <?php if (empty($matches)): ?>
                            <tr class="no-data-row">
                                <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-sub);">
                                    <i class="fa fa-circle-question" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                                    Không tìm thấy trận đấu PvP nào trong hệ thống.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($matches as $m): ?>
                                <tr class="match-row" 
                                    data-status="<?= ($m['status'] === 'pending' || $m['status'] === 'accepted') ? 'active' : 'completed' ?>"
                                    data-search-content="<?= strtolower($m['id'] . ' ' . $m['game_type'] . ' ' . $m['challenger_name'] . ' ' . $m['challenged_name']) ?>">
                                    <td><span style="font-family: 'JetBrains Mono', monospace; font-weight: bold; color: var(--text-sub);">#<?= $m['id'] ?></span></td>
                                    <td><span class="badge" style="background: rgba(255,255,255,0.06); color: #fff; border: 1px solid rgba(255,255,255,0.15);"><?= htmlspecialchars($m['game_type']) ?></span></td>
                                    <td>
                                        <div class="player-info">
                                            <img src="<?= $m['challenger_avatar'] ?: 'img/avatar_default.png' ?>" class="player-avatar" onerror="this.src='img/avatar_default.png'">
                                            <span class="player-name"><?= htmlspecialchars($m['challenger_name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="player-info">
                                            <img src="<?= $m['challenged_avatar'] ?: 'img/avatar_default.png' ?>" class="player-avatar" onerror="this.src='img/avatar_default.png'">
                                            <span class="player-name"><?= htmlspecialchars($m['challenged_name']) ?></span>
                                        </div>
                                    </td>
                                    <td><span class="bet-amount"><?= number_format($m['bet_amount']) ?> GTLM</span></td>
                                    <td>
                                        <?php if ($m['status'] === 'pending'): ?>
                                            <span class="badge badge-pending">Chờ nhận</span>
                                        <?php elseif ($m['status'] === 'accepted'): ?>
                                            <span class="badge badge-accepted">Đang đấu ⚔️</span>
                                        <?php elseif ($m['status'] === 'finished' || $m['status'] === 'completed'): ?>
                                            <span class="badge badge-finished">Kết thúc</span>
                                        <?php else: ?>
                                            <span class="badge badge-cancelled">Đã hủy</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span style="font-size: 13px; color: var(--text-sub);"><?= date('H:i d/m/Y', strtotime($m['created_at'])) ?></span></td>
                                    <td style="text-align: right;">
                                        <div class="actions-cell" style="justify-content: flex-end;">
                                            <a href="pvp_arena.php?id=<?= $m['id'] ?>" class="btn-action btn-spectate" target="_blank" title="Vào xem màn hình arena">
                                                <i class="fa fa-eye"></i> Xem Live
                                            </a>
                                            <?php if ($m['status'] === 'pending' || $m['status'] === 'accepted'): ?>
                                                <button class="btn-action btn-cancel" onclick="adminCancel(<?= $m['id'] ?>)" title="Hủy phòng PvP này và hoàn cược">
                                                    <i class="fa fa-ban"></i> Hủy & Hoàn cược
                                                </button>
                                                <button class="btn-action btn-force" onclick="adminForceWin(<?= $m['id'] ?>, <?= $m['challenger_id'] ?>, '<?= addslashes($m['challenger_name']) ?>')" title="Cưỡng chế phân thắng cuộc cho Đấu Thủ 1">
                                                    🏆 Thắng P1
                                                </button>
                                                <button class="btn-action btn-force" onclick="adminForceWin(<?= $m['id'] ?>, <?= $m['opponent_id'] ?>, '<?= addslashes($m['challenged_name']) ?>')" title="Cưỡng chế phân thắng cuộc cho Đấu Thủ 2">
                                                    🏆 Thắng P2
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-action btn-delete" onclick="adminDelete(<?= $m['id'] ?>)" title="Xóa lịch sử trận đấu này khỏi database">
                                                    <i class="fa fa-trash"></i> Xóa Lịch Sử
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        let activeTab = 'all';

        function filterStatus(tab) {
            activeTab = tab;
            $('.tab-btn').removeClass('active');
            $(`.tab-btn[onclick="filterStatus('${tab}')"]`).addClass('active');
            applyFilters();
        }

        function applyFilters() {
            const searchVal = $('#search-input').val().toLowerCase().trim();
            let visibleCount = 0;

            $('.match-row').each(function() {
                const rowStatus = $(this).attr('data-status');
                const rowContent = $(this).attr('data-search-content');
                
                const matchesTab = (activeTab === 'all' || rowStatus === activeTab);
                const matchesSearch = (searchVal === '' || rowContent.includes(searchVal));

                if (matchesTab && matchesSearch) {
                    $(this).show();
                    visibleCount++;
                } else {
                    $(this).hide();
                }
            });

            // Hiển thị dòng thông báo không có dữ liệu nếu không có hàng nào khớp
            if (visibleCount === 0) {
                if ($('.no-data-row').length === 0) {
                    $('#matches-table-body').append(`
                        <tr class="no-data-row">
                            <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-sub);">
                                <i class="fa fa-circle-question" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                                Không tìm thấy kết quả phù hợp.
                            </td>
                        </tr>
                    `);
                } else {
                    $('.no-data-row').show();
                }
            } else {
                $('.no-data-row').hide();
            }
        }

        // Đăng ký sự kiện lọc trực tiếp khi nhập ô tìm kiếm
        $('#search-input').on('input', applyFilters);

        function adminCancel(matchId) {
            Swal.fire({
                title: 'Hủy trận đấu PvP?',
                text: "Trận đấu sẽ bị hủy ngay lập tức và hoàn trả 100% GTLM cược lại cho cả hai người chơi!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Đồng ý hủy',
                cancelButtonText: 'Hủy bỏ'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.get(`api_pvp.php?action=admin_cancel&id=${matchId}`, function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Thành công',
                                text: response.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Lỗi', response.message, 'error');
                        }
                    });
                }
            });
        }

        function adminForceWin(matchId, winnerId, winnerName) {
            Swal.fire({
                title: 'Xử thắng cuộc?',
                text: `Bạn có chắc chắn muốn xử thắng trực tiếp cho đấu thủ [${winnerName}]?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                confirmButtonText: 'Đồng ý xử thắng',
                cancelButtonText: 'Hủy bỏ'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post(`api_pvp.php?action=admin_force_result&id=${matchId}`, {
                        winner_id: winnerId
                    }, function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Thành công',
                                text: response.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Lỗi', response.message, 'error');
                        }
                    });
                }
            });
        }

        function adminDelete(matchId) {
            Swal.fire({
                title: 'Xóa lịch sử trận đấu?',
                text: "Xóa bản ghi này khỏi cơ sở dữ liệu vĩnh viễn! Hành động không ảnh hưởng đến số dư của người chơi.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#4b5563',
                confirmButtonText: 'Đồng ý xóa',
                cancelButtonText: 'Hủy bỏ'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.get(`api_pvp.php?action=admin_delete&id=${matchId}`, function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Thành công',
                                text: response.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Lỗi', response.message, 'error');
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>
