<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';
require_once 'load_theme.php';

$userId = $_SESSION['Iduser'];
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$soDu = $user['Money'] ?? 0;
$tenNguoiChoi = $user['Name'] ?? 'Người chơi';
$stmt->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Games - Tất Cả Trò Chơi</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/game-launcher.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
        }
        * { cursor: inherit; }
        button, a, input { cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important; }
        
        .page-header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 30px 40px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border: 2px solid rgba(102, 126, 234, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .page-title {
            font-size: 36px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }
        
        .user-balance-display {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 25px;
            background: linear-gradient(135deg, rgba(46, 204, 113, 0.15), rgba(39, 174, 96, 0.15));
            border: 2px solid rgba(46, 204, 113, 0.3);
            border-radius: 16px;
            font-weight: 700;
            font-size: 20px;
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.2);
        }
    </style>
</head>
<body>
    <div class="game-launcher-container">
        <div class="page-header">
            <h1 class="page-title">🎮 Tất Cả Trò Chơi</h1>
            <div class="user-balance-display">
                <span>👤</span>
                <span><?= htmlspecialchars($tenNguoiChoi) ?></span>
                <span>|</span>
                <span>💰</span>
                <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
            </div>
        </div>
        
        <div class="game-launcher-header">
            <h2 class="game-launcher-title">Chọn Game Để Chơi</h2>
            <div class="game-search-box">
                <input type="text" id="gameSearch" placeholder="Tìm kiếm game...">
            </div>
        </div>
        
        <div class="game-category-filters">
            <button class="game-category-filter active" data-category="all">Tất Cả</button>
            <button class="game-category-filter" data-category="casino">🎰 Casino</button>
            <button class="game-category-filter" data-category="mini">⚪ Mini Game</button>
            <button class="game-category-filter" data-category="card">🃏 Card Game</button>
        </div>
        
        <div id="gamesGrid" class="games-grid-launcher">
            <!-- Games will be loaded by JavaScript -->
        </div>
        
        <div class="recent-favorite-section">
            <div class="recent-favorite-box">
                <div class="recent-favorite-title">
                    <span>🕐</span>
                    <span>Game Gần Đây</span>
                </div>
                <div id="recentGamesList">
                    <!-- Recent games will be loaded by JavaScript -->
                </div>
            </div>
            
            <div class="recent-favorite-box">
                <div class="recent-favorite-title">
                    <span>❤️</span>
                    <span>Game Yêu Thích</span>
                </div>
                <div id="favoriteGamesList">
                    <!-- Favorite games will be loaded by JavaScript -->
                </div>
            </div>
        </div>
        
        <p style="text-align: center; margin-top: 40px;">
            <a href="index.php" style="color: #667eea; text-decoration: none; font-weight: 600; font-size: 16px;">🏠 Quay Lại Trang Chủ</a>
        </p>
    </div>
    
    <script src="assets/js/game-launcher.js"></script>
</body>
</html>

