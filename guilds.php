<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

// Load theme
require_once 'load_theme.php';

// Đảm bảo $bgGradientCSS có giá trị
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

$userId = $_SESSION['Iduser'];

// Lấy thông tin người dùng
$sql = "SELECT Iduser, Name, Money FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Kiểm tra bảng guilds có tồn tại không
$checkTable = $conn->query("SHOW TABLES LIKE 'guilds'");
$guildsTableExists = $checkTable && $checkTable->num_rows > 0;

// Lấy guild của user hiện tại (nếu có)
$userGuild = null;
$userGuildRole = null;
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
        $userGuildRole = $userGuild['user_role'];
    }
    $guildStmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guild - Hội</title>
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

        button, a, input[type="button"], input[type="submit"], label, select, input[type="text"], textarea {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .guilds-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header-guilds {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 24px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
        }

        .header-guilds h1 {
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
        
        .guild-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .guild-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .guild-details h2 {
            font-size: 28px;
            color: #333;
            margin-bottom: 8px;
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
            display: flex;
            gap: 30px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
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
        
        .search-box {
            margin-bottom: 25px;
        }
        
        .search-input {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .guild-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        
        .guild-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .guild-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            border-color: #667eea;
        }
        
        .guild-card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .guild-card-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            color: white;
        }
        
        .guild-card-info h3 {
            font-size: 20px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .guild-card-stats {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        
        .member-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .member-card {
            background: rgba(247, 247, 247, 0.8);
            border-radius: 12px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
        }
        
        .member-card:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateX(5px);
        }
        
        .member-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #667eea;
        }
        
        .member-info {
            flex: 1;
        }
        
        .member-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }
        
        .member-role {
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 6px;
            display: inline-block;
        }
        
        .role-leader {
            background: #ffc107;
            color: #333;
        }
        
        .role-officer {
            background: #17a2b8;
            color: white;
        }
        
        .role-member {
            background: #6c757d;
            color: white;
        }
        
        .chat-container {
            display: flex;
            flex-direction: column;
            height: 500px;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: rgba(247, 247, 247, 0.5);
            border-radius: 12px;
            margin-bottom: 15px;
        }
        
        .chat-message {
            margin-bottom: 15px;
            padding: 12px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .chat-message-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        
        .chat-message-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .chat-message-name {
            font-weight: 600;
            color: #333;
        }
        
        .chat-message-time {
            font-size: 12px;
            color: #999;
            margin-left: auto;
        }
        
        .chat-input-container {
            display: flex;
            gap: 10px;
        }
        
        .chat-input {
            flex: 1;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
        }
        
        .chat-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-input, .form-textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
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
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 18px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .guilds-container {
                padding: 10px;
            }
            
            .header-guilds {
                padding: 25px;
            }
            
            .guild-info {
                flex-direction: column;
                text-align: center;
            }
            
            .guild-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="guilds-container">
        <div class="header-guilds">
            <h1>🏆 Guild System</h1>
            <p style="color: #666; font-size: 18px; margin-top: 10px;">Tạo hoặc tham gia guild để cùng nhau phát triển!</p>
        </div>

        <?php if (!$guildsTableExists): ?>
            <div class="card">
                <div class="message error">
                    ⚠️ Hệ thống Guild chưa được kích hoạt! Vui lòng chạy file <strong>create_guild_tables.sql</strong> trước.
                </div>
            </div>
        <?php else: ?>
            <div class="tabs">
                <?php if ($userGuild): ?>
                    <div class="tab active" data-tab="my-guild">🏠 Guild Của Tôi</div>
                    <div class="tab" data-tab="guild-war" style="background: rgba(241, 196, 15, 0.1); border: 1px solid rgba(241, 196, 15, 0.3); color: #f1c40f;">⚔️ Đua Top Guild</div>
                    <div class="tab" data-tab="skills">✨ Kỹ Năng Bang</div>
                    <div class="tab" data-tab="guild-pro" style="background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.3); color: #8b5cf6;">🏰 Trung Tâm Guild</div>
                    <div class="tab" data-tab="members">👥 Thành Viên</div>
                    <div class="tab" data-tab="chat">💬 Chat Guild</div>
                    <?php if ($userGuildRole === 'leader' || $userGuildRole === 'officer'): ?>
                        <div class="tab" data-tab="applications">📝 Đơn Xin Vào</div>
                        <div class="tab" data-tab="manage">⚙️ Quản Lý</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="tab active" data-tab="search">🔍 Tìm Guild</div>
                    <div class="tab" data-tab="create">➕ Tạo Guild</div>
                <?php endif; ?>
            </div>

            <?php if ($userGuild): ?>
                <!-- Tab: Guild Của Tôi -->
                <div class="tab-content active" id="my-guild">
                    <div class="card">
                        <div class="guild-info">
                            <div class="guild-avatar"><?= strtoupper(substr($userGuild['name'], 0, 2)) ?></div>
                            <div class="guild-details">
                                <h2>
                                    <span class="guild-tag">[<?= htmlspecialchars($userGuild['tag']) ?>]</span>
                                    <?= htmlspecialchars($userGuild['name']) ?>
                                </h2>
                                <p style="color: #666; margin-top: 5px;"><?= htmlspecialchars($userGuild['description'] ?? 'Chưa có mô tả') ?></p>
                                <div class="guild-stats">
                                    <div class="stat-item">
                                        <div class="stat-value"><?= $userGuild['level'] ?></div>
                                        <div class="stat-label">Level</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?= number_format($userGuild['experience']) ?></div>
                                        <div class="stat-label">XP</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value" id="member-count">-</div>
                                        <div class="stat-label">Thành Viên</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?= $userGuild['max_members'] ?></div>
                                        <div class="stat-label">Tối Đa</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <span class="btn btn-warning">Vai Trò: <?= ucfirst($userGuildRole) ?></span>
                            <?php if ($userGuildRole !== 'leader'): ?>
                                <button class="btn btn-danger" onclick="leaveGuild(<?= $userGuild['id'] ?>)">Rời Guild</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tab: Đua Top Guild -->
                <div class="tab-content" id="guild-war">
                    <div class="card" style="background: linear-gradient(135deg, rgba(26, 26, 46, 0.95), rgba(22, 33, 62, 0.95)); border: 1px solid rgba(241, 196, 15, 0.3);">
                        <div style="text-align: center; padding: 20px;">
                            <i class="fa fa-trophy" style="font-size: 64px; color: #f1c40f; margin-bottom: 20px;"></i>
                            <h2 style="color: #fff; margin-bottom: 15px;">Sự Kiện Đua Top Bang Hội</h2>
                            <p style="color: #bdc3c7; margin-bottom: 30px; font-size: 1.1em;">
                                Cùng các thành viên trong Bang hội tham gia các trò chơi để tích lũy Điểm Chiến Công. 
                                Bang hội có điểm cao nhất vào cuối tuần sẽ nhận được phần thưởng cực khủng!
                            </p>
                            <a href="guild_war.php" class="btn btn-primary" style="padding: 15px 40px; font-size: 1.2em;">
                                <i class="fa fa-shield-halved"></i> Xem Bảng Xếp Hạng Ngay
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Tab: Kỹ Năng Bang -->
                <div class="tab-content" id="skills">
                    <div class="card">
                        <h2 style="margin-bottom: 20px;"><i class="fa fa-sparkles" style="color: #f1c40f;"></i> Kỹ Năng Bang Hội</h2>
                        <p style="color: #bdc3c7; margin-bottom: 25px;">Sử dụng điểm <strong>Guild XP</strong> để nâng cấp các kỹ năng hỗ trợ cho toàn bộ thành viên.</p>
                        
                        <div class="skills-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                            <!-- Skill: Fortune -->
                            <div class="skill-card" style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.1);">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <div>
                                        <h3 style="color: #f1c40f;"><i class="fa fa-coins"></i> Tài Lộc (Fortune)</h3>
                                        <p style="font-size: 0.9em; color: #aaa; margin: 10px 0;">Tăng tỉ lệ nhận thêm  Gtlm khi thắng game.</p>
                                        <div style="font-weight: bold; color: #2ecc71;">Hiệu ứng: +<span id="lvl-fortune-val">0</span>%  Gtlm thắng</div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 1.2em; font-weight: bold;">Lv.<span id="lvl-fortune">0</span></div>
                                    </div>
                                </div>
                                <button onclick="upgradeSkill('fortune')" class="btn btn-primary" style="width: 100%; margin-top: 15px;">Nâng cấp (<span id="cost-fortune">0</span> XP)</button>
                            </div>

                            <!-- Skill: Unity -->
                            <div class="skill-card" style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.1);">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <div>
                                        <h3 style="color: #3498db;"><i class="fa fa-handshake"></i> Đoàn Kết (Unity)</h3>
                                        <p style="font-size: 0.9em; color: #aaa; margin: 10px 0;">Tăng điểm chiến công nhận được cho Guild War.</p>
                                        <div style="font-weight: bold; color: #2ecc71;">Hiệu ứng: +<span id="lvl-unity-val">0</span>% điểm CW</div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 1.2em; font-weight: bold;">Lv.<span id="lvl-unity">0</span></div>
                                    </div>
                                </div>
                                <button onclick="upgradeSkill('unity')" class="btn btn-primary" style="width: 100%; margin-top: 15px;">Nâng cấp (<span id="cost-unity">0</span> XP)</button>
                            </div>

                            <!-- Skill: Charisma -->
                            <div class="skill-card" style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.1);">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <div>
                                        <h3 style="color: #e74c3c;"><i class="fa fa-heart"></i> Nhân Phẩm (Charisma)</h3>
                                        <p style="font-size: 0.9em; color: #aaa; margin: 10px 0;">Tăng lượng XP cá nhân nhận được khi chơi game.</p>
                                        <div style="font-weight: bold; color: #2ecc71;">Hiệu ứng: +<span id="lvl-charisma-val">0</span>% XP nhận được</div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 1.2em; font-weight: bold;">Lv.<span id="lvl-charisma">0</span></div>
                                    </div>
                                </div>
                                <button onclick="upgradeSkill('charisma')" class="btn btn-primary" style="width: 100%; margin-top: 15px;">Nâng cấp (<span id="cost-charisma">0</span> XP)</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Trung Tâm Guild -->
                <div class="tab-content" id="guild-pro">
                    <div class="card" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(236, 72, 153, 0.1)); border: 1px solid rgba(139, 92, 246, 0.3);">
                        <div style="text-align: center; padding: 20px;">
                            <i class="fa fa-fort-awesome" style="font-size: 64px; color: #8b5cf6; margin-bottom: 20px;"></i>
                            <h2 style="color: #fff; margin-bottom: 15px;">Trung Tâm Nâng Cao Bang Hội</h2>
                            <p style="color: #cbd5e1; margin-bottom: 30px; font-size: 1.1em;">
                                Nơi quản lý các Lãnh địa đang chiếm đóng và Cửa hàng Bang hội độc quyền. 
                                Sử dụng Điểm đóng góp (Contribution Points) để mua sắm và gia tăng sức mạnh cho Bang hội!
                            </p>
                            <a href="guild_pro.php" class="btn" style="background: linear-gradient(135deg, #8b5cf6, #ec4899); padding: 15px 40px; font-size: 1.2em; border: none;">
                                <i class="fa fa-shopping-bag"></i> VÀO TRUNG TÂM NGAY
                            </a>
                        </div>
                    </div>
                </div>
                <div class="tab-content" id="members">
                    <div class="card">
                        <h2 style="margin-bottom: 20px;">Danh Sách Thành Viên</h2>
                        <div id="members-list" class="member-list">
                            <div class="no-data">Đang tải...</div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Chat Guild -->
                <div class="tab-content" id="chat">
                    <div class="card">
                        <h2 style="margin-bottom: 20px;">Chat Guild</h2>
                        <div class="chat-container">
                            <div class="chat-messages" id="chat-messages">
                                <div class="no-data">Đang tải tin nhắn...</div>
                            </div>
                            <div class="chat-input-container">
                                <input type="text" class="chat-input" id="chat-input" placeholder="Nhập tin nhắn...">
                                <button class="btn btn-primary" onclick="sendChatMessage()">Gửi</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Đơn Xin Vào -->
                <?php if ($userGuildRole === 'leader' || $userGuildRole === 'officer'): ?>
                    <div class="tab-content" id="applications">
                        <div class="card">
                            <h2 style="margin-bottom: 20px;">Đơn Xin Vào Guild</h2>
                            <div id="applications-list">
                                <div class="no-data">Đang tải...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Quản Lý -->
                    <div class="tab-content" id="manage">
                        <div class="card">
                            <h2 style="margin-bottom: 20px;">Quản Lý Guild</h2>
                            <p style="color: #666; margin-bottom: 20px;">Các tính năng quản lý sẽ được thêm vào sau.</p>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Tab: Tìm Guild -->
                <div class="tab-content active" id="search">
                    <div class="card">
                        <div class="search-box">
                            <input type="text" class="search-input" id="search-input" placeholder="Tìm kiếm guild theo tên hoặc tag...">
                        </div>
                        <div id="guilds-list" class="guild-list">
                            <div class="no-data">Nhập từ khóa để tìm kiếm guild</div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Tạo Guild -->
                <div class="tab-content" id="create">
                    <div class="card">
                        <h2 style="margin-bottom: 20px;">Tạo Guild Mới</h2>
                        <form id="create-guild-form">
                            <div class="form-group">
                                <label class="form-label">Tên Guild *</label>
                                <input type="text" class="form-input" id="guild-name" placeholder="Nhập tên guild" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tag Guild * (2-10 ký tự)</label>
                                <input type="text" class="form-input" id="guild-tag" placeholder="VD: ABC" maxlength="10" required>
                                <small style="color: #666;">Tag sẽ hiển thị trong ngoặc vuông [ABC]</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Mô Tả</label>
                                <textarea class="form-textarea" id="guild-description" placeholder="Mô tả về guild của bạn..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Tạo Guild</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        const userId = <?= $userId ?>;
        const userGuildId = <?= $userGuild ? $userGuild['id'] : 'null' ?>;
        const userGuildRole = '<?= $userGuildRole ?? '' ?>';

        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                this.classList.add('active');
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
                
                // Load data when switching tabs
                if (tabId === 'members' && userGuildId) {
                    loadMembers();
                } else if (tabId === 'chat' && userGuildId) {
                    loadChat();
                    startChatPolling();
                } else if (tabId === 'applications' && userGuildId) {
                    loadApplications();
                }
            });
        });

        // Load guild members
        function loadMembers() {
            $.ajax({
                url: 'api_guilds.php',
                method: 'GET',
                data: { action: 'get_members', guild_id: userGuildId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let html = '';
                        if (response.members.length === 0) {
                            html = '<div class="no-data">Chưa có thành viên</div>';
                        } else {
                            response.members.forEach(member => {
                                const avatar = member.ImageURL || 'default-avatar.png';
                                const roleClass = 'role-' + member.role;
                                const roleText = member.role === 'leader' ? 'Leader' : 
                                                member.role === 'officer' ? 'Officer' : 'Member';
                                
                                html += `
                                    <div class="member-card">
                                        <img src="${avatar}" alt="${member.Name}" class="member-avatar" onerror="this.src='default-avatar.png'">
                                        <div class="member-info">
                                            <div class="member-name">${member.Name}</div>
                                            <span class="member-role ${roleClass}">${roleText}</span>
                                        </div>
                                        ${userGuildRole === 'leader' && member.role !== 'leader' ? `
                                            <div class="action-buttons">
                                                ${member.role === 'member' ? '<button class="btn btn-sm btn-success" onclick="promoteMember(' + member.user_id + ')">Thăng</button>' : ''}
                                                ${member.role === 'officer' ? '<button class="btn btn-sm btn-warning" onclick="demoteMember(' + member.user_id + ')">Giáng</button>' : ''}
                                                <button class="btn btn-sm btn-danger" onclick="kickMember(${member.user_id})">Kick</button>
                                            </div>
                                        ` : ''}
                                    </div>
                                `;
                            });
                        }
                        $('#members-list').html(html);
                        
                        // Update member count
                        $('#member-count').text(response.members.length);
                    }
                }
            });
        }

        // Load guild chat
        function loadChat() {
            $.ajax({
                url: 'api_guilds.php',
                method: 'GET',
                data: { action: 'get_chat', guild_id: userGuildId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let html = '';
                        if (response.messages.length === 0) {
                            html = '<div class="no-data">Chưa có tin nhắn nào</div>';
                        } else {
                            response.messages.forEach(msg => {
                                const avatar = msg.ImageURL || 'default-avatar.png';
                                const time = new Date(msg.created_at).toLocaleString('vi-VN');
                                html += `
                                    <div class="chat-message">
                                        <div class="chat-message-header">
                                            <img src="${avatar}" alt="${msg.Name}" class="chat-message-avatar" onerror="this.src='default-avatar.png'">
                                            <span class="chat-message-name">${msg.Name}</span>
                                            <span class="chat-message-time">${time}</span>
                                        </div>
                                        <div>${msg.message}</div>
                                    </div>
                                `;
                            });
                        }
                        $('#chat-messages').html(html);
                        $('#chat-messages').scrollTop($('#chat-messages')[0].scrollHeight);
                    }
                }
            });
        }

        // Send chat message
        function sendChatMessage() {
            const message = $('#chat-input').val().trim();
            if (!message) return;
            
            $.ajax({
                url: 'api_guilds.php',
                method: 'POST',
                data: {
                    action: 'chat',
                    guild_id: userGuildId,
                    message: message
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#chat-input').val('');
                        loadChat();
                    } else {
                        Swal.fire('Lỗi', response.message, 'error');
                    }
                }
            });
        }

        // Chat input enter key
        $('#chat-input').on('keypress', function(e) {
            if (e.which === 13) {
                sendChatMessage();
            }
        });

        // Chat polling
        let chatPollingInterval = null;
        function startChatPolling() {
            if (chatPollingInterval) clearInterval(chatPollingInterval);
            chatPollingInterval = setInterval(loadChat, 3000);
        }

        // Stop polling when leaving chat tab
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                if (chatPollingInterval) {
                    clearInterval(chatPollingInterval);
                    chatPollingInterval = null;
                }
            });
        });

        // Load applications
        function loadApplications() {
            // Tải danh sách đơn xin vào guild
            $.ajax({
                url: 'api_guilds.php',
                method: 'GET',
                data: { action: 'get_applications' },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.applications && response.applications.length > 0) {
                        let html = '<div class="applications-container">';
                        response.applications.forEach(app => {
                            html += `
                                <div class="application-item">
                                    <div class="app-user">👤 ${escapeHtml(app.user_name)}</div>
                                    <div class="app-message">${escapeHtml(app.message || 'Không có lời nhắn')}</div>
                                    <div class="app-actions">
                                        <button class="btn-approve" onclick="approveApplication(${app.id})">✅ Duyệt</button>
                                        <button class="btn-reject" onclick="rejectApplication(${app.id})">❌ Từ chối</button>
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                        $('#applications-list').html(html);
                    } else {
                        $('#applications-list').html('<div class="no-data">Không có đơn xin vào</div>');
                    }
                },
                error: function() {
                    $('#applications-list').html('<div class="no-data">Lỗi khi tải đơn xin</div>');
                }
            });
        }

        // Search guilds
        let searchTimeout;
        $('#search-input').on('input', function() {
            clearTimeout(searchTimeout);
            const keyword = $(this).val().trim();
            
            if (keyword.length < 2) {
                $('#guilds-list').html('<div class="no-data">Nhập ít nhất 2 ký tự để tìm kiếm</div>');
                return;
            }
            
            searchTimeout = setTimeout(() => {
                $.ajax({
                    url: 'api_guilds.php',
                    method: 'GET',
                    data: { action: 'search', keyword: keyword },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            let html = '';
                            if (response.guilds.length === 0) {
                                html = '<div class="no-data">Không tìm thấy guild nào</div>';
                            } else {
                                response.guilds.forEach(guild => {
                                    html += `
                                        <div class="guild-card">
                                            <div class="guild-card-header">
                                                <div class="guild-card-avatar">${guild.name.substring(0, 2).toUpperCase()}</div>
                                                <div class="guild-card-info">
                                                    <h3>[${guild.tag}] ${guild.name}</h3>
                                                    <p style="color: #666; font-size: 14px;">Leader: ${guild.leader_name}</p>
                                                </div>
                                            </div>
                                            <p style="color: #666; margin-bottom: 15px;">${guild.description || 'Chưa có mô tả'}</p>
                                            <div class="guild-card-stats">
                                                <div><strong>Level:</strong> ${guild.level}</div>
                                                <div><strong>Thành viên:</strong> ${guild.member_count}/${guild.max_members}</div>
                                            </div>
                                            <div style="margin-top: 15px;">
                                                <button class="btn btn-primary btn-sm" onclick="applyToGuild(${guild.id})">Xin Vào</button>
                                            </div>
                                        </div>
                                    `;
                                });
                            }
                            $('#guilds-list').html(html);
                        }
                    }
                });
            }, 500);
        });

        // Create guild
        $('#create-guild-form').on('submit', function(e) {
            e.preventDefault();
            
            const name = $('#guild-name').val().trim();
            const tag = $('#guild-tag').val().trim().toUpperCase();
            const description = $('#guild-description').val().trim();
            
            $.ajax({
                url: 'api_guilds.php',
                method: 'POST',
                data: {
                    action: 'create',
                    name: name,
                    tag: tag,
                    description: description
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Thành công', response.message, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Lỗi', response.message, 'error');
                    }
                }
            });
        });

        // Apply to guild
        function applyToGuild(guildId) {
            Swal.fire({
                title: 'Gửi đơn xin vào guild',
                input: 'textarea',
                inputPlaceholder: 'Lời nhắn (tùy chọn)',
                showCancelButton: true,
                confirmButtonText: 'Gửi đơn',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'api_guilds.php',
                        method: 'POST',
                        data: {
                            action: 'apply',
                            guild_id: guildId,
                            message: result.value || ''
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Thành công', response.message, 'success');
                            } else {
                                Swal.fire('Lỗi', response.message, 'error');
                            }
                        }
                    });
                }
            });
        }

        // Leave guild
        function leaveGuild(guildId) {
            Swal.fire({
                title: 'Xác nhận rời guild?',
                text: 'Bạn có chắc chắn muốn rời guild này?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Rời',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'api_guilds.php',
                        method: 'POST',
                        data: {
                            action: 'leave',
                            guild_id: guildId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Thành công', response.message, 'success').then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Lỗi', response.message, 'error');
                            }
                        }
                    });
                }
            });
        }

        // Promote member
        function promoteMember(targetUserId) {
            $.ajax({
                url: 'api_guilds.php',
                method: 'POST',
                data: {
                    action: 'promote',
                    guild_id: userGuildId,
                    target_user_id: targetUserId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Thành công', response.message, 'success');
                        loadMembers();
                    } else {
                        Swal.fire('Lỗi', response.message, 'error');
                    }
                }
            });
        }

        // Demote member
        function demoteMember(targetUserId) {
            $.ajax({
                url: 'api_guilds.php',
                method: 'POST',
                data: {
                    action: 'demote',
                    guild_id: userGuildId,
                    target_user_id: targetUserId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Thành công', response.message, 'success');
                        loadMembers();
                    } else {
                        Swal.fire('Lỗi', response.message, 'error');
                    }
                }
            });
        }

        // Kick member
        function kickMember(targetUserId) {
            Swal.fire({
                title: 'Xác nhận kick thành viên?',
                text: 'Bạn có chắc chắn muốn kick thành viên này?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Kick',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'api_guilds.php',
                        method: 'POST',
                        data: {
                            action: 'kick',
                            guild_id: userGuildId,
                            target_user_id: targetUserId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Thành công', response.message, 'success');
                                loadMembers();
                            } else {
                                Swal.fire('Lỗi', response.message, 'error');
                            }
                        }
                    });
                }
            });
        }

        // Load initial data
        <?php if ($userGuild): ?>
            loadMembers();
        <?php endif; ?>
        function loadSkills() {
            $.get('api_guild_skills.php', { action: 'get_skills' }, function(res) {
                if (res.success) {
                    for (const [type, lvl] of Object.entries(res.skills)) {
                        $(`#lvl-${type}`).text(lvl);
                        $(`#lvl-${type}-val`).text(lvl * (type === 'unity' ? 2 : 1));
                        $(`#cost-${type}`).text((lvl + 1) * 5000);
                    }
                }
            }, 'json');
        }

        function upgradeSkill(type) {
            Swal.fire({
                title: 'Nâng cấp kỹ năng?',
                text: `Xác nhận sử dụng Guild XP để nâng cấp kỹ năng này?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Nâng cấp ngay'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_guild_skills.php', { action: 'upgrade', type: type }, function(res) {
                        if (res.success) {
                            Swal.fire('Thành công!', 'Đã nâng cấp kỹ năng bang hội.', 'success');
                            loadSkills();
                            location.reload(); // Reload to update XP display
                        } else {
                            Swal.fire('Lỗi!', res.message, 'error');
                        }
                    }, 'json');
                }
            });
        }

        $(document).ready(function() {
            loadSkills();
            // ... existing ready code ...
        });
    </script>
</body>
</html>

