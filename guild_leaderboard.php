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

// Kiểm tra bảng guilds có tồn tại không
$checkTable = $conn->query("SHOW TABLES LIKE 'guilds'");
$guildsTableExists = $checkTable && $checkTable->num_rows > 0;

// Lấy guild của user hiện tại (nếu có)
$userGuild = null;
if ($guildsTableExists) {
    $guildSql = "SELECT g.*, gm.role as user_role 
                  FROM guilds g
                  JOIN guild_members gm ON g.id = gm.guild_id
                  WHERE gm.user_id = ?";
    $guildStmt = $conn->prepare($guildSql);
    $guildStmt->bind_param("i", $userId);
    $guildStmt->execute();
    $guildResult = $guildStmt->get_result();
    if ($guildResult->num_rows > 0) {
        $userGuild = $guildResult->fetch_assoc();
    }
    $guildStmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏆 Bảng Xếp Hạng Guild</title>
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
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 24px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
        }

        .header h1 {
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
        
        .card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .guild-rank-info {
            margin-bottom: 20px;
            padding: 20px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 12px;
            display: none;
        }
        
        .guild-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .guild-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
        }
        
        .guild-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            border-color: #667eea;
        }
        
        .guild-card.top-1 {
            border-color: #FFD700;
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.1) 0%, rgba(255, 255, 255, 0.98) 100%);
        }
        
        .guild-card.top-2 {
            border-color: #C0C0C0;
            background: linear-gradient(135deg, rgba(192, 192, 192, 0.1) 0%, rgba(255, 255, 255, 0.98) 100%);
        }
        
        .guild-card.top-3 {
            border-color: #CD7F32;
            background: linear-gradient(135deg, rgba(205, 127, 50, 0.1) 0%, rgba(255, 255, 255, 0.98) 100%);
        }
        
        .rank-badge {
            position: absolute;
            top: -15px;
            right: -15px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 24px;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            z-index: 10;
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
        
        .guild-card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .guild-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .guild-info h3 {
            font-size: 22px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .guild-tag {
            display: inline-block;
            padding: 4px 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            margin-right: 10px;
        }
        
        .guild-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
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
        
        .leader-name {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .leader-name i {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏆 Bảng Xếp Hạng Guild</h1>
            <p style="color: #666; font-size: 18px; margin-top: 10px;">Xem các guild mạnh nhất và cạnh tranh với nhau!</p>
        </div>

        <?php if (!$guildsTableExists): ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h2>Hệ thống Guild chưa được kích hoạt!</h2>
                    <p>Vui lòng chạy file <code>create_guild_tables.sql</code> trong phpMyAdmin trước.</p>
                </div>
            </div>
        <?php else: ?>
            <?php if ($userGuild): ?>
                <div class="card">
                    <div class="guild-rank-info" id="guild-rank-info">
                        <strong>🏆 Guild của bạn: </strong>
                        <span id="guild-rank-text"></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('overall')">📊 Tổng Thể</button>
                    <button class="tab" onclick="switchTab('level')">⭐ Level</button>
                    <button class="tab" onclick="switchTab('members')">👥 Thành Viên</button>
                    <button class="tab" onclick="switchTab('contribution')">💎 Đóng Góp</button>
                </div>
                
                <div id="leaderboard-content">
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
        const userGuildId = <?= $userGuild ? $userGuild['id'] : 'null' ?>;
        
        function switchTab(tab) {
            currentTab = tab;
            $('.tab').removeClass('active');
            $(`.tab[onclick="switchTab('${tab}')"]`).addClass('active');
            loadLeaderboard();
        }
        
        function loadLeaderboard() {
            const content = $('#leaderboard-content');
            content.html('<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Đang tải...</p></div>');
            
            $.ajax({
                url: 'api_guilds.php',
                method: 'GET',
                data: {
                    action: 'get_leaderboard',
                    type: currentTab,
                    limit: 50
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayLeaderboard(response.guilds);
                        if (userGuildId) {
                            loadGuildRank();
                        }
                    } else {
                        content.html('<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h2>Lỗi</h2><p>' + (response.message || 'Không thể tải dữ liệu') + '</p></div>');
                    }
                },
                error: function() {
                    content.html('<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h2>Lỗi Kết Nối</h2><p>Không thể tải dữ liệu. Vui lòng thử lại sau.</p></div>');
                }
            });
        }
        
        function displayLeaderboard(guilds) {
            const content = $('#leaderboard-content');
            
            if (guilds.length === 0) {
                content.html('<div class="empty-state"><i class="fas fa-trophy"></i><h2>Chưa có guild nào</h2><p>Hãy là người đầu tiên tạo guild!</p></div>');
                return;
            }
            
            let html = '<div class="guild-list">';
            
            guilds.forEach(guild => {
                const rank = guild.rank;
                const rankClass = rank <= 3 ? `top-${rank}` : '';
                const rankBadge = rank <= 3 ? `<div class="rank-badge rank-${rank}">${rank === 1 ? '🥇' : rank === 2 ? '🥈' : '🥉'}</div>` : '';
                const memberCount = guild.member_count || 0;
                const totalContribution = guild.total_contribution || 0;
                const totalMoney = guild.total_money_won || 0;
                
                html += `
                    <div class="guild-card ${rankClass}">
                        ${rankBadge}
                        <div class="guild-card-header">
                            <div class="guild-avatar">${guild.name.substring(0, 2).toUpperCase()}</div>
                            <div class="guild-info">
                                <h3>
                                    <span class="guild-tag">[${guild.tag}]</span>
                                    ${guild.name}
                                </h3>
                                <div class="leader-name">
                                    <i class="fas fa-crown"></i> Leader: ${guild.leader_name}
                                </div>
                            </div>
                        </div>
                        <p style="color: #666; margin-bottom: 15px; font-size: 14px;">${guild.description || 'Chưa có mô tả'}</p>
                        <div class="guild-stats">
                            <div class="stat-item">
                                <div class="stat-value">${guild.level}</div>
                                <div class="stat-label">Level</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${number_format(guild.experience)}</div>
                                <div class="stat-label">XP</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${memberCount}/${guild.max_members}</div>
                                <div class="stat-label">Thành Viên</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${number_format(totalContribution)}</div>
                                <div class="stat-label">Đóng Góp</div>
                            </div>
                        </div>
                        <div style="margin-top: 15px; text-align: center;">
                            <a href="guilds.php" class="btn btn-primary" style="text-decoration: none; display: inline-block; padding: 10px 20px; border-radius: 8px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 600;">Xem Chi Tiết</a>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            content.html(html);
        }
        
        function loadGuildRank() {
            $.ajax({
                url: 'api_guilds.php',
                method: 'GET',
                data: {
                    action: 'get_guild_rank',
                    guild_id: userGuildId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const rankInfo = $('#guild-rank-info');
                        const rankText = $('#guild-rank-text');
                        
                        rankText.html(`<strong>${response.guild.name}</strong> - Hạng <strong>${response.rank}</strong> / ${response.total} guild`);
                        rankInfo.show();
                    }
                }
            });
        }
        
        function number_format(number) {
            return new Intl.NumberFormat('vi-VN').format(number);
        }
        
        // Load khi trang load
        $(document).ready(function() {
            loadLeaderboard();
        });
    </script>
</body>
</html>

