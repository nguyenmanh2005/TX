<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

// Load theme
require_once 'load_theme.php';

if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

$userId = $_SESSION['Iduser'];

// Kiểm tra bảng tournaments có tồn tại không
$checkTable = $conn->query("SHOW TABLES LIKE 'tournaments'");
$tournamentsTableExists = $checkTable && $checkTable->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournament - Giải Đấu</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        * {
            cursor: inherit;
        }

        button,
        a,
        input[type="button"],
        input[type="submit"],
        label,
        select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .tournament-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-tournament {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 24px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
        }

        .header-tournament h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 42px;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 15px;
        }

        .tabs {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            background: rgba(247, 247, 247, 0.8);
            padding: 8px;
            border-radius: 16px;
        }

        .tab {
            padding: 16px 32px;
            background: transparent;
            border: 2px solid transparent;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            color: #666;
            position: relative;
            overflow: hidden;
        }

        .tab::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: left 0.3s ease;
            z-index: -1;
        }

        .tab.active {
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .tab.active::before {
            left: 0;
        }

        .tab:hover:not(.active) {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            transform: translateY(-2px);
        }

        .tab-content {
            display: none;
            animation: fadeInContent 0.4s ease;
        }

        @keyframes fadeInContent {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .tab-content.active {
            display: block;
        }

        .card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .tournament-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
        }

        .tournament-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
            animation: fadeInScale 0.5s ease backwards;
        }

        .tournament-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.1) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .tournament-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .tournament-card:hover {
            transform: translateY(-8px) scale(1.02) rotate(1deg);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
            border-color: #667eea;
        }

        .tournament-card:hover::before {
            opacity: 1;
        }

        .tournament-card:hover::after {
            left: 100%;
        }

        .tournament-card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .tournament-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .tournament-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .badge-daily {
            background: #28a745;
            color: white;
        }

        .badge-weekly {
            background: #007bff;
            color: white;
        }

        .badge-monthly {
            background: #ffc107;
            color: #333;
        }

        .badge-special {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .tournament-status {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-upcoming {
            background: #6c757d;
            color: white;
        }

        .status-registration {
            background: #17a2b8;
            color: white;
        }

        .status-active {
            background: #28a745;
            color: white;
        }

        .status-ended {
            background: #dc3545;
            color: white;
        }

        .tournament-info {
            margin: 15px 0;
            color: #666;
            line-height: 1.8;
        }

        .tournament-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 20px 0;
            padding: 15px;
            background: rgba(247, 247, 247, 0.8);
            border-radius: 12px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 12px;
            color: #999;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .tournament-rewards {
            margin: 20px 0;
            padding: 15px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-radius: 12px;
        }

        .rewards-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .rewards-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .reward-item {
            padding: 6px 12px;
            background: white;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #667eea;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-block;
            text-decoration: none;
            color: white;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .leaderboard-table th,
        .leaderboard-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .leaderboard-table th {
            background: rgba(102, 126, 234, 0.1);
            font-weight: 600;
            color: #333;
        }

        .leaderboard-table tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .rank-badge {
            display: inline-block;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            font-weight: 700;
            color: white;
        }

        .rank-1 {
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
        }

        .rank-2 {
            background: linear-gradient(135deg, #c0c0c0 0%, #e8e8e8 100%);
            color: #333;
        }

        .rank-3 {
            background: linear-gradient(135deg, #cd7f32 0%, #daa520 100%);
        }

        .rank-other {
            background: #6c757d;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .stat-card {
            background: rgba(247, 247, 247, 0.8);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 18px;
        }

        .message {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .message.success {
            background: rgba(40, 167, 69, 0.2);
            border: 2px solid #28a745;
            color: #28a745;
        }

        .message.error {
            background: rgba(220, 53, 69, 0.2);
            border: 2px solid #dc3545;
            color: #dc3545;
        }

        @media (max-width: 768px) {
            .tournament-container {
                padding: 10px;
            }

            .header-tournament {
                padding: 25px;
            }

            .tournament-list {
                grid-template-columns: 1fr;
            }

            .tournament-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="tournament-container">
        <div class="header-tournament">
            <h1>🏆 Tournament System</h1>
            <p style="color: #666; font-size: 18px; margin-top: 10px;">Tham gia giải đấu và giành phần thưởng lớn!</p>
        </div>

        <?php if (!$tournamentsTableExists): ?>
            <div class="card">
                <div class="message error">
                    ⚠️ Hệ thống Tournament chưa được kích hoạt! Vui lòng chạy file
                    <strong>create_tournament_tables.sql</strong> trước.
                </div>
            </div>
        <?php else: ?>
            <div class="tabs">
                <div class="tab active" data-tab="list">📋 Danh Sách Giải Đấu</div>
                <div class="tab" data-tab="my-tournaments">🎯 Giải Đấu Của Tôi</div>
            </div>

            <!-- Tab: Danh Sách Giải Đấu -->
            <div class="tab-content active" id="list">
                <div class="card">
                    <h2 style="margin-bottom: 20px;">Danh Sách Giải Đấu</h2>
                    <div id="tournaments-list" class="tournament-list">
                        <div class="no-data">Đang tải...</div>
                    </div>
                </div>
            </div>

            <!-- Tab: Giải Đấu Của Tôi -->
            <div class="tab-content" id="my-tournaments">
                <div class="card">
                    <h2 style="margin-bottom: 20px;">Giải Đấu Đang Tham Gia</h2>
                    <div id="my-tournaments-list" class="tournament-list">
                        <div class="no-data">Đang tải...</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const userId = <?= $userId ?>;

        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function () {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                this.classList.add('active');
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');

                // Load data when switching tabs
                if (tabId === 'list') {
                    loadTournaments();
                } else if (tabId === 'my-tournaments') {
                    loadMyTournaments();
                }
            });
        });

        // Load tournaments list
        function loadTournaments() {
            $.ajax({
                url: 'api_tournament.php',
                method: 'GET',
                data: { action: 'get_list', status: 'all' },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        let html = '';
                        if (response.tournaments.length === 0) {
                            html = '<div class="no-data">Chưa có giải đấu nào</div>';
                        } else {
                            response.tournaments.forEach(tournament => {
                                const badgeClass = 'badge-' + tournament.tournament_type;
                                const statusClass = 'status-' + tournament.status;
                                const statusText = {
                                    'upcoming': 'Sắp Diễn Ra',
                                    'registration': 'Đang Đăng Ký',
                                    'active': 'Đang Diễn Ra',
                                    'ended': 'Đã Kết Thúc',
                                    'cancelled': 'Đã Hủy'
                                };

                                const typeText = {
                                    'daily': 'Hàng Ngày',
                                    'weekly': 'Hàng Tuần',
                                    'monthly': 'Hàng Tháng',
                                    'special': 'Đặc Biệt'
                                };

                                const rewards = JSON.parse(tournament.reward_structure || '{}');
                                let rewardsHtml = '';
                                Object.keys(rewards).forEach(key => {
                                    rewardsHtml += `<span class="reward-item">Top ${key}: ${formatMoney(rewards[key])}</span>`;
                                });

                                const startTime = new Date(tournament.start_time).toLocaleString('vi-VN');
                                const endTime = new Date(tournament.end_time).toLocaleString('vi-VN');
                                const regEnd = new Date(tournament.registration_end).toLocaleString('vi-VN');

                                html += `
                                    <div class="tournament-card">
                                        <div class="tournament-card-header">
                                            <div>
                                                <div class="tournament-title">
                                                    ${tournament.name}
                                                    <span class="tournament-badge ${badgeClass}">${typeText[tournament.tournament_type]}</span>
                                                </div>
                                                <span class="tournament-status ${statusClass}">${statusText[tournament.status]}</span>
                                            </div>
                                        </div>
                                        <div class="tournament-info">${tournament.description || 'Chưa có mô tả'}</div>
                                        <div class="tournament-details">
                                            <div class="detail-item">
                                                <span class="detail-label">Game</span>
                                                <span class="detail-value">${tournament.game_type}</span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label">Thành Viên</span>
                                                <span class="detail-value">${tournament.participant_count}/${tournament.max_participants}</span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label">Bắt Đầu</span>
                                                <span class="detail-value">${startTime}</span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label">Kết Thúc</span>
                                                <span class="detail-value">${endTime}</span>
                                            </div>
                                        </div>
                                        <div class="tournament-rewards">
                                            <div class="rewards-title">💰 Phần Thưởng:</div>
                                            <div class="rewards-list">${rewardsHtml}</div>
                                        </div>
                                        <div class="action-buttons">
                                            ${tournament.status === 'registration' && !tournament.is_registered ?
                                        `<button class="btn btn-success" onclick="registerTournament(${tournament.id})">Đăng Ký</button>` : ''}
                                            ${tournament.status === 'registration' && tournament.is_registered ?
                                        `<button class="btn btn-danger" onclick="unregisterTournament(${tournament.id})">Hủy Đăng Ký</button>` : ''}
                                            ${tournament.status === 'active' && tournament.is_registered ?
                                        `<button class="btn btn-primary" onclick="viewTournament(${tournament.id})">Xem Chi Tiết</button>` : ''}
                                            ${tournament.status === 'ended' && tournament.is_registered ?
                                        `<button class="btn btn-warning" onclick="viewTournament(${tournament.id})">Xem Kết Quả</button>` : ''}
                                            <button class="btn btn-primary" onclick="viewTournament(${tournament.id})">Chi Tiết</button>
                                        </div>
                                    </div>
                                `;
                            });
                        }
                        $('#tournaments-list').html(html);
                    }
                }
            });
        }

        // Load my tournaments
        function loadMyTournaments() {
            $.ajax({
                url: 'api_tournament.php',
                method: 'GET',
                data: { action: 'get_list', status: 'all' },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        const myTournaments = response.tournaments.filter(t => t.is_registered);
                        let html = '';
                        if (myTournaments.length === 0) {
                            html = '<div class="no-data">Bạn chưa tham gia giải đấu nào</div>';
                        } else {
                            myTournaments.forEach(tournament => {
                                html += `
                                    <div class="tournament-card">
                                        <div class="tournament-title">${tournament.name}</div>
                                        <div class="action-buttons">
                                            <button class="btn btn-primary" onclick="viewTournament(${tournament.id})">Xem Chi Tiết</button>
                                        </div>
                                    </div>
                                `;
                            });
                        }
                        $('#my-tournaments-list').html(html);
                    }
                }
            });
        }

        // Register tournament
        function registerTournament(tournamentId) {
            $.ajax({
                url: 'api_tournament.php',
                method: 'POST',
                data: {
                    action: 'register',
                    tournament_id: tournamentId
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        Swal.fire('Thành công', response.message, 'success');
                        loadTournaments();
                    } else {
                        Swal.fire('Lỗi', response.message, 'error');
                    }
                }
            });
        }

        // Unregister tournament
        function unregisterTournament(tournamentId) {
            Swal.fire({
                title: 'Xác nhận hủy đăng ký?',
                text: 'Bạn có chắc chắn muốn hủy đăng ký giải đấu này?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Hủy Đăng Ký',
                cancelButtonText: 'Không'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'api_tournament.php',
                        method: 'POST',
                        data: {
                            action: 'unregister',
                            tournament_id: tournamentId
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                Swal.fire('Thành công', response.message, 'success');
                                loadTournaments();
                            } else {
                                Swal.fire('Lỗi', response.message, 'error');
                            }
                        }
                    });
                }
            });
        }

        // View tournament details
        function viewTournament(tournamentId) {
            // Open modal with tournament details
            $.ajax({
                url: 'api_tournament.php',
                method: 'GET',
                data: { action: 'get_info', tournament_id: tournamentId },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        const tournament = response.tournament;
                        showTournamentModal(tournament);
                    }
                }
            });
        }

        // Show tournament modal
        function showTournamentModal(tournament) {
            // Load leaderboard and stats
            $.ajax({
                url: 'api_tournament.php',
                method: 'GET',
                data: { action: 'get_leaderboard', tournament_id: tournament.id },
                dataType: 'json',
                success: function (leaderboardResponse) {
                    if (leaderboardResponse.success) {
                        let leaderboardHtml = '<table class="leaderboard-table"><thead><tr><th>Hạng</th><th>Tên</th><th>Điểm</th><th>Thắng</th><th>Game</th></tr></thead><tbody>';
                        leaderboardResponse.leaderboard.forEach((entry, index) => {
                            const rankClass = entry.current_rank === 1 ? 'rank-1' :
                                entry.current_rank === 2 ? 'rank-2' :
                                    entry.current_rank === 3 ? 'rank-3' : 'rank-other';
                            leaderboardHtml += `
                                <tr>
                                    <td><span class="rank-badge ${rankClass}">${entry.current_rank}</span></td>
                                    <td>${entry.Name}</td>
                                    <td>${formatMoney(entry.score)}</td>
                                    <td>${entry.total_wins}/${entry.total_games}</td>
                                    <td>${entry.total_games}</td>
                                </tr>
                            `;
                        });
                        leaderboardHtml += '</tbody></table>';

                        // Load my stats
                        $.ajax({
                            url: 'api_tournament.php',
                            method: 'GET',
                            data: { action: 'get_my_stats', tournament_id: tournament.id },
                            dataType: 'json',
                            success: function (statsResponse) {
                                let statsHtml = '';
                                if (statsResponse.success) {
                                    const stats = statsResponse.stats;
                                    statsHtml = `
                                        <div class="stats-grid">
                                            <div class="stat-card">
                                                <div class="stat-value">${stats.rank || '-'}</div>
                                                <div class="stat-label">Xếp Hạng</div>
                                            </div>
                                            <div class="stat-card">
                                                <div class="stat-value">${formatMoney(stats.score)}</div>
                                                <div class="stat-label">Điểm Số</div>
                                            </div>
                                            <div class="stat-card">
                                                <div class="stat-value">${stats.total_wins}/${stats.total_games}</div>
                                                <div class="stat-label">Thắng/Tổng</div>
                                            </div>
                                            <div class="stat-card">
                                                <div class="stat-value">${formatMoney(stats.potential_reward)}</div>
                                                <div class="stat-label">Phần Thưởng</div>
                                            </div>
                                        </div>
                                    `;
                                }

                                Swal.fire({
                                    title: tournament.name,
                                    html: `
                                        <div style="text-align: left;">
                                            <p><strong>Mô tả:</strong> ${tournament.description || 'Chưa có mô tả'}</p>
                                            <p><strong>Game:</strong> ${tournament.game_type}</p>
                                            <p><strong>Thời gian:</strong> ${new Date(tournament.start_time).toLocaleString('vi-VN')} - ${new Date(tournament.end_time).toLocaleString('vi-VN')}</p>
                                            <p><strong>Thành viên:</strong> ${tournament.participant_count}/${tournament.max_participants}</p>
                                            ${statsHtml}
                                            <h3 style="margin-top: 20px;">Bảng Xếp Hạng</h3>
                                            ${leaderboardHtml}
                                        </div>
                                    `,
                                    width: '800px',
                                    showCloseButton: true,
                                    showConfirmButton: false
                                });
                            }
                        });
                    }
                }
            });
        }

        // Format money
        function formatMoney(amount) {
            return new Intl.NumberFormat('vi-VN').format(amount) + ' gtlm';
        }

        // Load initial data
        loadTournaments();
    </script>
</body>

</html>