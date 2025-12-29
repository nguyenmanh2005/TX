<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['Iduser'];
$viewUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $userId;
$isOwnProfile = $viewUserId == $userId;

require_once 'load_theme.php';

if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

// Kiểm tra bảng tồn tại
$checkTable = $conn->query("SHOW TABLES LIKE 'user_profiles'");
$tableExists = $checkTable && $checkTable->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isOwnProfile ? 'Hồ Sơ Của Tôi' : 'Hồ Sơ Người Chơi' ?></title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/profile-enhancements.css">

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

        button, a, input[type="button"], input[type="submit"], label, select, textarea {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .profile-header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
            animation: fadeInDown 0.6s ease;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.05) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #667eea;
            margin-bottom: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2),
                        0 0 0 0 rgba(102, 126, 234, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1;
        }
        
        .profile-avatar:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.4),
                        0 0 30px rgba(102, 126, 234, 0.3);
            border-color: #764ba2;
        }
        
        .profile-name {
            font-size: 32px;
            font-weight: 800;
            color: #333;
            margin-bottom: 10px;
        }
        
        .profile-title {
            font-size: 18px;
            color: #667eea;
            margin-bottom: 20px;
        }
        
        .profile-actions {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .profile-actions .btn {
            margin-top: 0;
            padding: 10px 20px;
            font-size: 14px;
        }
        
        .profile-stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
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
        
        .card h2 {
            margin-bottom: 20px;
            color: #333;
            font-size: 24px;
        }
        
        .bio-section {
            line-height: 1.8;
            color: #666;
            font-size: 16px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .info-item {
            padding: 15px;
            background: rgba(247, 247, 247, 0.8);
            border-radius: 12px;
        }
        
        .info-label {
            font-size: 12px;
            color: #999;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .social-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .social-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .badge-list {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .badge-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(102, 126, 234, 0.12);
            border: 1px solid rgba(102, 126, 234, 0.2);
            font-weight: 600;
            color: #333;
        }
        
        .badge-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .badge-emoji {
            font-size: 24px;
        }
        
        .badge-meta {
            font-size: 12px;
            color: #666;
        }
        
        .game-stats {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .game-item {
            padding: 15px;
            border-radius: 14px;
            background: rgba(247, 247, 247, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .game-name {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 6px;
            color: #333;
        }
        
        .game-meta {
            font-size: 13px;
            color: #666;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .game-meta span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .profit-positive {
            color: #28a745;
            font-weight: 700;
        }
        
        .profit-negative {
            color: #dc3545;
            font-weight: 700;
        }
        
        .game-time {
            margin-top: 8px;
            font-size: 12px;
            color: #999;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
            margin-top: 20px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .edit-form {
            display: none;
        }
        
        .edit-form.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .visitors-list {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }
        
        .visitor-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: rgba(247, 247, 247, 0.8);
            border-radius: 8px;
        }
        
        .visitor-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-header" id="profile-header">
            <div class="empty-state">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Đang tải...</p>
            </div>
        </div>

        <?php if (!$tableExists): ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h2>Hệ thống Profile chưa được kích hoạt!</h2>
                    <p>Vui lòng chạy file <code>ALL_DATABASE_TABLES.sql</code> trong phpMyAdmin trước.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2><?= $isOwnProfile ? '📝 Chỉnh Sửa Hồ Sơ' : '📋 Thông Tin' ?></h2>
                    <?php if ($isOwnProfile): ?>
                        <button class="btn btn-primary" onclick="toggleEdit()">
                            <i class="fas fa-edit"></i> Chỉnh Sửa
                        </button>
                    <?php endif; ?>
                </div>
                
                <div id="profile-view">
                    <div id="bio-section" class="bio-section"></div>
                    <div class="info-grid" id="info-grid"></div>
                    <div class="social-links" id="social-links"></div>
                </div>
                
                <div id="profile-edit" class="edit-form">
                    <form id="edit-form" onsubmit="saveProfile(event)">
                        <div class="form-group">
                            <label>Tiểu Sử</label>
                            <textarea name="bio" id="edit-bio" rows="4" placeholder="Giới thiệu về bản thân..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Địa Điểm</label>
                            <input type="text" name="location" id="edit-location" placeholder="Ví dụ: Hà Nội, Việt Nam">
                        </div>
                        <div class="form-group">
                            <label>Website</label>
                            <input type="url" name="website" id="edit-website" placeholder="https://...">
                        </div>
                        <div class="form-group">
                            <label>Facebook</label>
                            <input type="text" name="social_facebook" id="edit-facebook" placeholder="Facebook username hoặc URL">
                        </div>
                        <div class="form-group">
                            <label>Twitter</label>
                            <input type="text" name="social_twitter" id="edit-twitter" placeholder="Twitter username">
                        </div>
                        <div class="form-group">
                            <label>Discord</label>
                            <input type="text" name="social_discord" id="edit-discord" placeholder="Discord username">
                        </div>
                        <div class="form-group">
                            <label>Màu Yêu Thích</label>
                            <input type="color" name="favorite_color" id="edit-color" value="#667eea">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Lưu Thay Đổi
                        </button>
                        <button type="button" class="btn" onclick="toggleEdit()" style="background: #999; margin-left: 10px;">
                            Hủy
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <h2>🏅 Thành Tựu</h2>
                <div id="achievement-list" class="badge-list">
                    <div class="empty-state">
                        <i class="fas fa-medal"></i>
                        <p>Chưa có thành tựu nào</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>🎮 Game Nổi Bật</h2>
                <div id="game-stats" class="game-stats">
                    <div class="empty-state">
                        <i class="fas fa-gamepad"></i>
                        <p>Chưa có dữ liệu game</p>
                    </div>
                </div>
            </div>

            <?php if ($isOwnProfile): ?>
                <div class="card">
                    <h2>👥 Người Ghé Thăm Gần Đây</h2>
                    <div id="visitors-list" class="visitors-list">
                        <div class="empty-state">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Đang tải...</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php" class="back-link">🏠 Về Trang Chủ</a>
        </div>
    </div>

    <script>
        let profileData = null;
        let isEditing = false;
        const viewUserId = <?= $viewUserId ?>;
        const isOwnProfile = <?= $isOwnProfile ? 'true' : 'false' ?>;
        
        function loadProfile() {
            $.get('api_profile.php?action=get_profile&user_id=' + viewUserId)
                .done(function(response) {
                    if (response.success) {
                        profileData = response;
                        displayProfile(response);
                        if (<?= $isOwnProfile ? 'true' : 'false' ?>) {
                            loadVisitors();
                        }
                    } else {
                        Swal.fire('Lỗi', response.message || 'Không thể tải profile', 'error');
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('Error loading profile:', error, xhr.responseText);
                    Swal.fire('Lỗi', 'Không thể tải profile. Vui lòng thử lại sau.', 'error');
                });
        }
        
        function displayProfile(data) {
            const user = data.user;
            const profile = data.profile || {};
            const stats = data.statistics || {};
            
            // Header
            const header = $('#profile-header');
            const avatar = user.ImageURL || 'default-avatar.png';
            const title = user.title_icon && user.title_name 
                ? user.title_icon + ' ' + user.title_name 
                : '';
            
            const winRate = stats.total_games_played > 0 
                ? ((stats.total_games_won / stats.total_games_played) * 100).toFixed(1)
                : 0;
            
            header.html(`
                <img src="${avatar}" alt="${user.Name}" class="profile-avatar" onerror="this.src='default-avatar.png'">
                <div class="profile-name">${user.Name}</div>
                ${title ? '<div class="profile-title">' + title + '</div>' : ''}
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-value">${number_format(user.Money)}</div>
                        <div class="stat-label">VNĐ</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${stats.total_games_played || 0}</div>
                        <div class="stat-label">Số Game</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${winRate}%</div>
                        <div class="stat-label">Tỷ Lệ Thắng</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${data.visits_count || 0}</div>
                        <div class="stat-label">Lượt Ghé Thăm</div>
                    </div>
                </div>
                ${!isOwnProfile ? `
                <div class="profile-actions">
                    <button class="btn btn-primary" id="friendActionBtn" data-user-id="${user.Iduser}">Kết bạn</button>
                    <button class="btn" style="background:#20c997;" id="messageActionBtn" data-user-id="${user.Iduser}">Nhắn tin</button>
                    <button class="btn" style="background:#ffc107; color:#333;" id="giftActionBtn" data-user-id="${user.Iduser}">Tặng quà</button>
                </div>` : `
                <div class="profile-actions">
                    <a href="friends.php" class="btn btn-primary">Quản lý bạn bè</a>
                    <a href="gift.php" class="btn" style="background:#20c997;">Tặng quà cho bạn bè</a>
                </div>`}
            `);
            
            if (!isOwnProfile) {
                initProfileActions(user.Iduser, user.Name);
            }
            
            // Bio
            const bioSection = $('#bio-section');
            bioSection.html(profile.bio || '<em style="color: #999;">Chưa có tiểu sử</em>');
            
            // Info Grid
            const infoGrid = $('#info-grid');
            let infoHtml = '';
            
            if (profile.location) {
                infoHtml += `
                    <div class="info-item">
                        <div class="info-label">📍 Địa Điểm</div>
                        <div class="info-value">${profile.location}</div>
                    </div>
                `;
            }
            
            if (profile.favorite_color) {
                infoHtml += `
                    <div class="info-item">
                        <div class="info-label">🎨 Màu Yêu Thích</div>
                        <div class="info-value">
                            <span style="display: inline-block; width: 20px; height: 20px; background: ${profile.favorite_color}; border-radius: 50%; vertical-align: middle; margin-right: 8px;"></span>
                            ${profile.favorite_color}
                        </div>
                    </div>
                `;
            }
            
            // Thông tin Guild
            if (data.guild) {
                const guild = data.guild;
                const roleText = guild.user_role === 'leader' ? 'Leader' : 
                                guild.user_role === 'officer' ? 'Officer' : 'Member';
                infoHtml += `
                    <div class="info-item">
                        <div class="info-label">🏆 Guild</div>
                        <div class="info-value">
                            <a href="guilds.php" style="color: #667eea; text-decoration: none; font-weight: 600;">
                                [${guild.tag}] ${guild.name}
                            </a>
                            <span style="display: block; font-size: 12px; color: #999; margin-top: 4px;">
                                ${roleText} • Level ${guild.level} • ${guild.member_count || 0} thành viên
                            </span>
                        </div>
                    </div>
                `;
            }
            
            infoGrid.html(infoHtml || '<div class="empty-state"><p>Chưa có thông tin</p></div>');
            
            // Social Links
            const socialLinks = $('#social-links');
            let socialHtml = '';
            
            if (profile.website) {
                socialHtml += `<a href="${profile.website}" target="_blank" class="social-link"><i class="fas fa-globe"></i> Website</a>`;
            }
            if (profile.social_facebook) {
                const fbUrl = profile.social_facebook.startsWith('http') ? profile.social_facebook : 'https://facebook.com/' + profile.social_facebook;
                socialHtml += `<a href="${fbUrl}" target="_blank" class="social-link"><i class="fab fa-facebook"></i> Facebook</a>`;
            }
            if (profile.social_twitter) {
                const twUrl = profile.social_twitter.startsWith('http') ? profile.social_twitter : 'https://twitter.com/' + profile.social_twitter;
                socialHtml += `<a href="${twUrl}" target="_blank" class="social-link"><i class="fab fa-twitter"></i> Twitter</a>`;
            }
            if (profile.social_discord) {
                socialHtml += `<a href="#" class="social-link"><i class="fab fa-discord"></i> ${profile.social_discord}</a>`;
            }
            
            socialLinks.html(socialHtml || '<div style="color: #999;">Chưa có liên kết mạng xã hội</div>');
            
            // Achievements
            const achievements = data.achievements || [];
            const achievementList = $('#achievement-list');
            if (!achievements.length) {
                achievementList.html(`
                    <div class="empty-state">
                        <i class="fas fa-medal"></i>
                        <p>Chưa mở khóa thành tựu nào</p>
                    </div>
                `);
            } else {
                const achievementHtml = achievements.map(item => {
                    const icon = item.icon || '🏅';
                    const iconHtml = icon.startsWith('http')
                        ? `<img src="${icon}" alt="${item.name}" class="badge-icon" onerror="this.style.display='none'">`
                        : `<span class="badge-emoji">${icon}</span>`;
                    return `
                        <div class="badge-item">
                            ${iconHtml}
                            <div>
                                <div>${item.name}</div>
                                <div class="badge-meta">${formatDateTime(item.unlocked_at)}</div>
                            </div>
                        </div>
                    `;
                }).join('');
                achievementList.html(achievementHtml);
            }
            
            // Game stats
            const games = data.games || [];
            const gameStats = $('#game-stats');
            if (!games.length) {
                gameStats.html(`
                    <div class="empty-state">
                        <i class="fas fa-gamepad"></i>
                        <p>Chưa có dữ liệu game nổi bật</p>
                    </div>
                `);
            } else {
                const gameHtml = games.map(game => {
                    const winRate = game.plays > 0 ? ((game.wins / game.plays) * 100).toFixed(1) : '0.0';
                    const profitClass = game.net_profit >= 0 ? 'profit-positive' : 'profit-negative';
                    const profitLabel = `${game.net_profit >= 0 ? '+' : ''}${number_format(Math.round(game.net_profit))} VNĐ`;
                    const lastPlayed = game.last_played ? formatDateTime(game.last_played) : 'Chưa rõ';
                    return `
                        <div class="game-item">
                            <div class="game-name">${game.game_name}</div>
                            <div class="game-meta">
                                <span>Chơi: <strong>${game.plays}</strong></span>
                                <span>Thắng: <strong>${game.wins}</strong> (${winRate}%)</span>
                                <span>Lãi: <strong class="${profitClass}">${profitLabel}</strong></span>
                            </div>
                            <div class="game-time">Lần cuối: ${lastPlayed}</div>
                        </div>
                    `;
                }).join('');
                gameStats.html(gameHtml);
            }
            
            // Fill edit form
            $('#edit-bio').val(profile.bio || '');
            $('#edit-location').val(profile.location || '');
            $('#edit-website').val(profile.website || '');
            $('#edit-facebook').val(profile.social_facebook || '');
            $('#edit-twitter').val(profile.social_twitter || '');
            $('#edit-discord').val(profile.social_discord || '');
            $('#edit-color').val(profile.favorite_color || '#667eea');
        }
        
        function toggleEdit() {
            isEditing = !isEditing;
            $('#profile-view').toggle(!isEditing);
            $('#profile-edit').toggleClass('active', isEditing);
        }
        
        function saveProfile(e) {
            e.preventDefault();
            
            const formData = {
                action: 'update_profile',
                bio: $('#edit-bio').val(),
                location: $('#edit-location').val(),
                website: $('#edit-website').val(),
                social_facebook: $('#edit-facebook').val(),
                social_twitter: $('#edit-twitter').val(),
                social_discord: $('#edit-discord').val(),
                favorite_color: $('#edit-color').val()
            };
            
            $.post('api_profile.php', formData, function(response) {
                if (response.success) {
                    Swal.fire('Thành công', 'Đã cập nhật profile!', 'success');
                    toggleEdit();
                    loadProfile();
                } else {
                    Swal.fire('Lỗi', response.message, 'error');
                }
            });
        }
        
        function loadVisitors() {
            $.get('api_profile.php?action=get_recent_visitors&limit=10', function(response) {
                if (response.success) {
                    const visitorsList = $('#visitors-list');
                    
                    if (response.visitors.length === 0) {
                        visitorsList.html('<div class="empty-state"><p>Chưa có ai ghé thăm</p></div>');
                        return;
                    }
                    
                    let html = '';
                    response.visitors.forEach(visitor => {
                        const avatar = visitor.ImageURL || 'default-avatar.png';
                        const title = visitor.title_icon && visitor.title_name 
                            ? visitor.title_icon + ' ' + visitor.title_name 
                            : '';
                        html += `
                            <div class="visitor-item">
                                <img src="${avatar}" alt="${visitor.Name}" class="visitor-avatar" onerror="this.src='default-avatar.png'">
                                <div>
                                    <div style="font-weight: 600;">${visitor.Name}</div>
                                    ${title ? '<div style="font-size: 12px; color: #999;">' + title + '</div>' : ''}
                                </div>
                            </div>
                        `;
                    });
                    
                    visitorsList.html(html);
                }
            });
        }
        
        function number_format(number) {
            return new Intl.NumberFormat('vi-VN').format(number);
        }

        function formatDateTime(dateStr) {
            if (!dateStr) return 'Không rõ';
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return 'Không rõ';
            return date.toLocaleString('vi-VN', {
                hour: '2-digit',
                minute: '2-digit',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        }
        
        function initProfileActions(targetUserId, targetUserName) {
            const friendBtn = $('#friendActionBtn');
            const messageBtn = $('#messageActionBtn');
            const giftBtn = $('#giftActionBtn');
            
            // Cập nhật trạng thái bạn bè
            $.get('api_friends.php', { action: 'get_status', other_id: targetUserId })
                .done(function(res) {
                    if (!res.success) return;
                    const status = res.status;
                    const direction = res.direction;
                    
                    if (!friendBtn.length) return;
                    
                    if (status === 'friends') {
                        friendBtn.text('Đã là bạn bè (Hủy)');
                        friendBtn.removeClass('btn-primary').addClass('btn-danger');
                        friendBtn.prop('disabled', false);
                    } else if (status === 'pending' && direction === 'outgoing') {
                        friendBtn.text('Đã gửi lời mời');
                        friendBtn.prop('disabled', true);
                    } else if (status === 'pending' && direction === 'incoming') {
                        friendBtn.text('Chấp nhận lời mời');
                        friendBtn.removeClass('btn-primary').addClass('btn-success');
                        friendBtn.prop('disabled', false);
                    } else {
                        friendBtn.text('Kết bạn');
                        friendBtn.removeClass('btn-danger btn-success').addClass('btn-primary');
                        friendBtn.prop('disabled', false);
                    }
                });
            
            if (friendBtn.length) {
                friendBtn.off('click').on('click', function() {
                    const label = $(this).text();
                    if (label.startsWith('Đã gửi')) return;
                    
                    let action = 'send_friend_request';
                    let confirmText = 'Gửi lời mời kết bạn?';
                    if (label.startsWith('Chấp nhận')) {
                        action = 'accept_friend_request';
                        confirmText = 'Chấp nhận lời mời kết bạn?';
                    } else if (label.includes('(Hủy)')) {
                        action = 'remove_friend';
                        confirmText = 'Hủy kết bạn với người này?';
                    }
                    
                    if (!confirm(confirmText)) return;
                    
                    $.post('api_friends.php', { action: action, friend_id: targetUserId }, function(res) {
                        alert(res.message || (res.success ? 'Thành công!' : 'Thất bại!'));
                        if (res.success) {
                            initProfileActions(targetUserId, targetUserName);
                        }
                    }, 'json');
                });
            }
            
            if (messageBtn.length) {
                messageBtn.off('click').on('click', function() {
                    window.location.href = 'private_message.php?friend_id=' + encodeURIComponent(targetUserId) + '&friend_name=' + encodeURIComponent(targetUserName);
                });
            }
            
            if (giftBtn.length) {
                giftBtn.off('click').on('click', function() {
                    window.location.href = 'gift.php';
                });
            }
        }
        
        $(document).ready(function() {
            loadProfile();
        });
    </script>
</body>
</html>

