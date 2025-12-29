<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['Iduser'];
require_once 'load_theme.php';

if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

// Kiểm tra bảng tồn tại
$checkTable = $conn->query("SHOW TABLES LIKE 'user_statistics'");
$tableExists = $checkTable && $checkTable->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏆 Bảng Xếp Hạng</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/leaderboard-enhancements.css">

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
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
        }

        .header h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 15px;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 12px 24px;
            background: transparent;
            border: none;
            font-weight: 600;
            font-size: 15px;
            color: #666;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab:hover {
            color: #667eea;
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
        
        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .leaderboard-table th,
        .leaderboard-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .leaderboard-table th {
            background: rgba(102, 126, 234, 0.1);
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
        }
        
        .leaderboard-table tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }
        
        .leaderboard-table tr.current-user {
            background: rgba(255, 193, 7, 0.2);
            font-weight: 600;
        }
        
        .rank-badge {
            display: inline-block;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            font-weight: 800;
            font-size: 18px;
            color: white;
        }
        
        .rank-1 {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
        }
        
        .rank-2 {
            background: linear-gradient(135deg, #C0C0C0 0%, #808080 100%);
        }
        
        .rank-3 {
            background: linear-gradient(135deg, #CD7F32 0%, #8B4513 100%);
        }
        
        .rank-other {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
        }
        
        .user-details {
            flex: 1;
        }
        
        .user-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .user-title {
            font-size: 12px;
            color: #999;
        }
        
        .score-value {
            font-weight: 800;
            font-size: 18px;
            color: #667eea;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        .game-selector {
            margin-bottom: 20px;
        }
        
        .game-selector select {
            padding: 10px 20px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            font-size: 16px;
            width: 100%;
            max-width: 300px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
        <h1>🏆 Bảng Xếp Hạng</h1>
        </div>

        <?php if (!$tableExists): ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h2>Hệ thống Leaderboard chưa được kích hoạt!</h2>
                    <p>Vui lòng chạy file <code>create_leaderboard_tables.sql</code> trong phpMyAdmin trước.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('overall')">📊 Tổng Thể</button>
                    <button class="tab" onclick="switchTab('weekly')">📅 Tuần Này</button>
                    <button class="tab" onclick="switchTab('monthly')">📆 Tháng Này</button>
                    <button class="tab" onclick="switchTab('streak')">🔥 Chuỗi</button>
                    <button class="tab" onclick="switchTab('winrate')">🎯 Tỷ Lệ Thắng</button>
                    <button class="tab" onclick="switchTab('game')">🎮 Theo Game</button>
                </div>
                
                <div id="game-selector" class="game-selector" style="display: none;">
                    <select id="game-select" onchange="loadGameLeaderboard()">
                        <option value="">Chọn game...</option>
                    </select>
                </div>
                
                <div id="user-rank-info" style="margin-bottom: 20px; padding: 15px; background: rgba(102, 126, 234, 0.1); border-radius: 12px; display: none;">
                    <strong>Vị trí của bạn: </strong><span id="user-rank-text"></span>
                </div>
                
                <div id="leaderboard-content">
                    <div class="empty-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Đang tải...</p>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <h2 style="margin-bottom: 20px;">📈 Thống Kê Của Bạn</h2>
                <div class="stats-grid" id="user-stats">
                    <div class="empty-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Đang tải...</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let currentTab = 'overall';
        let currentGame = '';
        
        function switchTab(tab) {
            currentTab = tab;
            $('.tab').removeClass('active');
            $(`.tab:contains(${getTabName(tab)})`).addClass('active');
            
            if (tab === 'game') {
                $('#game-selector').show();
                loadGamesList();
            } else {
                $('#game-selector').hide();
                loadLeaderboard();
            }
        }
        
        function getTabName(tab) {
            const names = {
                'overall': 'Tổng Thể',
                'weekly': 'Tuần Này',
                'monthly': 'Tháng Này',
                'streak': 'Chuỗi',
                'winrate': 'Tỷ Lệ Thắng',
                'game': 'Theo Game'
            };
            return names[tab] || tab;
        }
        
        function loadLeaderboard() {
            const content = $('#leaderboard-content');
            content.html('<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Đang tải...</p></div>');
            
            let url = 'api_leaderboard.php?action=get_' + currentTab;
            if (currentTab === 'game' && currentGame) {
                url += '&game_name=' + encodeURIComponent(currentGame);
            }
            
            $.get(url)
                .done(function(response) {
                    if (response.success) {
                        displayLeaderboard(response.leaderboard, response.type);
                        if (response.type === 'overall' || response.type === 'game') {
                        loadUserRank(response.type, response.game_name);
                        } else {
                            $('#user-rank-info').hide();
                        }
                    } else {
                        content.html('<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h2>Lỗi</h2><p>' + (response.message || 'Không thể tải dữ liệu') + '</p></div>');
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('Error loading leaderboard:', error);
                    content.html('<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h2>Lỗi Kết Nối</h2><p>Không thể tải dữ liệu. Vui lòng thử lại sau.</p></div>');
                });
        }
        
        function displayLeaderboard(leaderboard, type) {
            const content = $('#leaderboard-content');
            
            if (leaderboard.length === 0) {
                content.html('<div class="empty-state"><i class="fas fa-trophy"></i><h2>Chưa có dữ liệu</h2><p>Chưa có người chơi nào trong bảng xếp hạng này!</p></div>');
                return;
            }
            
            let html = '<table class="leaderboard-table"><thead><tr>';
            html += '<th style="width: 80px;">Hạng</th>';
            html += '<th>Người Chơi</th>';
            
            if (type === 'overall') {
                html += '<th style="text-align: right;">Tổng Tiền</th>';
                html += '<th style="text-align: right;">Số Game</th>';
                html += '<th style="text-align: right;">Tỷ Lệ Thắng</th>';
            } else if (type === 'streak') {
                html += '<th style="text-align: right;">Chuỗi Dài Nhất</th>';
                html += '<th style="text-align: right;">Chuỗi Hiện Tại</th>';
                html += '<th style="text-align: right;">Ngày Hoạt Động</th>';
            } else if (type === 'winrate') {
                html += '<th style="text-align: right;">Tỷ Lệ Thắng</th>';
                html += '<th style="text-align: right;">Số Game</th>';
                html += '<th style="text-align: right;">Số Thắng</th>';
            } else if (type === 'game') {
                html += '<th style="text-align: right;">Tổng Kiếm Được</th>';
                html += '<th style="text-align: right;">Số Game</th>';
                html += '<th style="text-align: right;">Số Thắng</th>';
            } else {
                html += '<th style="text-align: right;">Kiếm Được</th>';
                html += '<th style="text-align: right;">Số Game</th>';
            }
            
            html += '</tr></thead><tbody>';
            
            leaderboard.forEach((player, index) => {
                const rank = player.rank || (index + 1);
                const isCurrentUser = player.Iduser == <?= $userId ?>;
                const rowClass = isCurrentUser ? 'current-user' : '';
                
                let rankBadge = '';
                if (rank === 1) {
                    rankBadge = '<span class="rank-badge rank-1">🥇</span>';
                } else if (rank === 2) {
                    rankBadge = '<span class="rank-badge rank-2">🥈</span>';
                } else if (rank === 3) {
                    rankBadge = '<span class="rank-badge rank-3">🥉</span>';
                } else {
                    rankBadge = '<span class="rank-badge rank-other">' + rank + '</span>';
                }
                
                const avatar = player.ImageURL || 'images.ico';
                const title = player.title_icon && player.title_name 
                    ? player.title_icon + ' ' + player.title_name 
                    : '';
                
                html += `<tr class="${rowClass}">`;
                html += '<td>' + rankBadge + '</td>';
                html += '<td><div class="user-info">';
                html += '<img src="' + avatar + '" alt="' + player.Name + '" class="user-avatar" onerror="this.src=\'images.ico\'">';
                html += '<div class="user-details">';
                html += '<div class="user-name">' + player.Name + '</div>';
                if (title) {
                    html += '<div class="user-title">' + title + '</div>';
                }
                html += '</div></div></td>';
                
                if (type === 'overall') {
                    html += '<td style="text-align: right;"><span class="score-value">' + number_format(player.Money) + '</span> VNĐ</td>';
                    html += '<td style="text-align: right;">' + (player.total_games_played || 0) + '</td>';
                    const winRate = player.win_rate ? parseFloat(player.win_rate) : 0;
                    html += '<td style="text-align: right;">' + (winRate ? winRate.toFixed(1) : '0') + '%</td>';
                } else if (type === 'streak') {
                    html += '<td style="text-align: right;">' + (player.longest_streak || 0) + ' ngày</td>';
                    html += '<td style="text-align: right;">' + (player.current_streak || 0) + ' ngày</td>';
                    html += '<td style="text-align: right;">' + (player.total_days_played || 0) + ' ngày</td>';
                } else if (type === 'winrate') {
                    const winRate = player.win_rate ? parseFloat(player.win_rate) : 0;
                    html += '<td style="text-align: right;">' + (winRate ? winRate.toFixed(1) : '0') + '%</td>';
                    html += '<td style="text-align: right;">' + (player.total_games_played || 0) + '</td>';
                    html += '<td style="text-align: right;">' + (player.total_games_won || 0) + '</td>';
                } else if (type === 'game') {
                    html += '<td style="text-align: right;"><span class="score-value">' + number_format(player.total_earned || 0) + '</span> VNĐ</td>';
                    html += '<td style="text-align: right;">' + (player.games_played || 0) + '</td>';
                    html += '<td style="text-align: right;">' + (player.games_won || 0) + '</td>';
                } else {
                    const earned = type === 'weekly' ? player.weekly_earned : player.monthly_earned;
                    html += '<td style="text-align: right;"><span class="score-value">' + number_format(earned || 0) + '</span> VNĐ</td>';
                    html += '<td style="text-align: right;">' + (player.games_played || 0) + '</td>';
                }
                
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            content.html(html);
        }
        
        function loadUserRank(type, gameName) {
            let url = 'api_leaderboard.php?action=get_user_rank&type=' + type;
            if (gameName) {
                url += '&game_name=' + encodeURIComponent(gameName);
            }
            
            $.get(url, function(response) {
                if (response.success) {
                    const rankInfo = $('#user-rank-info');
                    const rankText = $('#user-rank-text');
                    
                    if (response.rank > 0) {
                        rankText.text('Hạng ' + response.rank + ' / ' + response.total);
                        rankInfo.show();
                    } else {
                        rankInfo.hide();
                    }
                }
            });
        }
        
        function loadGamesList() {
            $.get('api_leaderboard.php?action=get_games_list', function(response) {
                if (response.success) {
                    const select = $('#game-select');
                    select.html('<option value="">Chọn game...</option>');
                    
                    response.games.forEach(game => {
                        select.append('<option value="' + game.game_name + '">' + game.game_name + ' (' + game.total_plays + ' lượt chơi)</option>');
                    });
                }
            });
        }
        
        function loadGameLeaderboard() {
            currentGame = $('#game-select').val();
            if (currentGame) {
                loadLeaderboard();
            }
        }
        
        function loadUserStatistics() {
            $.get('api_leaderboard.php?action=get_statistics')
                .done(function(response) {
                    if (response.success) {
                        const stats = response.statistics;
                        const statsGrid = $('#user-stats');
                        
                        const winRate = stats.total_games_played > 0 
                            ? ((stats.total_games_won / stats.total_games_played) * 100).toFixed(1)
                            : 0;
                        
                        statsGrid.html(`
                            <div class="stat-card">
                                <div class="stat-value">${number_format(stats.total_games_played || 0)}</div>
                                <div class="stat-label">Tổng Số Game</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value">${number_format(stats.total_games_won || 0)}</div>
                                <div class="stat-label">Số Game Thắng</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value">${winRate}%</div>
                                <div class="stat-label">Tỷ Lệ Thắng</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value">${number_format(stats.total_money_earned || 0)}</div>
                                <div class="stat-label">Tổng Kiếm Được</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value">${number_format(stats.highest_win || 0)}</div>
                                <div class="stat-label">Thắng Lớn Nhất</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value">${stats.longest_win_streak || 0}</div>
                                <div class="stat-label">Chuỗi Thắng Dài Nhất</div>
                            </div>
                        `);
                    } else {
                        $('#user-stats').html('<div class="empty-state"><p>' + (response.message || 'Không thể tải thống kê') + '</p></div>');
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('Error loading statistics:', error);
                    $('#user-stats').html('<div class="empty-state"><p>Không thể tải thống kê</p></div>');
                });
        }
        
        function number_format(number) {
            return new Intl.NumberFormat('vi-VN').format(number);
        }
        
        // Load khi trang load
        $(document).ready(function() {
            loadLeaderboard();
            loadUserStatistics();
        });
    </script>
</body>
</html>
