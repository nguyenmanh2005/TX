<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

// Load theme
require_once 'load_theme.php';

$userId = $_SESSION['Iduser'];

// Lấy thông tin người dùng
$sql = "SELECT Iduser, Name, Money FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Kiểm tra bảng game_history có tồn tại không
$checkTable = $conn->query("SHOW TABLES LIKE 'game_history'");
$gameHistoryExists = $checkTable && $checkTable->num_rows > 0;

// Thống kê tổng quan
$totalGames = 0;
$totalWins = 0;
$totalLosses = 0;
$totalEarned = 0;
$totalSpent = 0;
$winRate = 0;
$gameStats = [];
$recentGames = [];

if ($gameHistoryExists) {
    // Tổng số game đã chơi
    $sql = "SELECT COUNT(*) as total FROM game_history WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $totalGames = $result->fetch_assoc()['total'] ?? 0;
    $stmt->close();
    
    // Tổng số thắng/thua
    $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN is_win = 0 THEN 1 ELSE 0 END) as losses,
                SUM(CASE WHEN is_win = 1 THEN win_amount - bet_amount ELSE 0 END) as earned,
                SUM(bet_amount) as spent
            FROM game_history WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $totalWins = $stats['wins'] ?? 0;
    $totalLosses = $stats['losses'] ?? 0;
    $totalEarned = $stats['earned'] ?? 0;
    $totalSpent = $stats['spent'] ?? 0;
    $winRate = $totalGames > 0 ? round(($totalWins / $totalGames) * 100, 2) : 0;
    $stmt->close();
    
    // Thống kê theo từng game
    $sql = "SELECT 
                game_name,
                COUNT(*) as plays,
                SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN is_win = 1 THEN win_amount - bet_amount ELSE 0 END) as earned,
                SUM(bet_amount) as spent
            FROM game_history 
            WHERE user_id = ? 
            GROUP BY game_name 
            ORDER BY plays DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['win_rate'] = $row['plays'] > 0 ? round(($row['wins'] / $row['plays']) * 100, 2) : 0;
        $gameStats[] = $row;
    }
    $stmt->close();
    
    // Game gần đây (30 ngày)
    $sql = "SELECT game_name, bet_amount, win_amount, is_win, played_at 
            FROM game_history 
            WHERE user_id = ? AND played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY played_at DESC 
            LIMIT 20";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentGames[] = $row;
    }
    $stmt->close();
    
    // Thống kê theo ngày (30 ngày gần nhất) - cho biểu đồ
    $sql = "SELECT 
                DATE(played_at) as date,
                COUNT(*) as plays,
                SUM(CASE WHEN is_win = 1 THEN win_amount - bet_amount ELSE 0 END) as earned,
                SUM(bet_amount) as spent
            FROM game_history 
            WHERE user_id = ? AND played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(played_at)
            ORDER BY date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $dailyStats = [];
    while ($row = $result->fetch_assoc()) {
        $dailyStats[] = $row;
    }
    $stmt->close();
}

// Lấy thống kê achievements
$achievementsCount = 0;
$checkAchievementsTable = $conn->query("SHOW TABLES LIKE 'achievements'");
if ($checkAchievementsTable && $checkAchievementsTable->num_rows > 0) {
    $sql = "SELECT COUNT(*) as total FROM user_achievements WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $achievementsCount = $result->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}

// Lấy thống kê quests
$questsCompleted = 0;
$checkQuestsTable = $conn->query("SHOW TABLES LIKE 'user_quests'");
if ($checkQuestsTable && $checkQuestsTable->num_rows > 0) {
    $sql = "SELECT COUNT(*) as total FROM user_quests WHERE user_id = ? AND is_completed = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $questsCompleted = $result->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống Kê - Statistics</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/animations.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            animation: fadeIn 0.6s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .statistics-container {
            max-width: 1400px;
            margin: 0 auto;
            animation: slideUp 0.6s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header-stats {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 24px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
            animation: fadeInDown 0.6s ease;
            position: relative;
            overflow: hidden;
        }
        
        .header-stats::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.05) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        .header-stats h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 42px;
            font-weight: 800;
            letter-spacing: -1px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            text-align: center;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
            animation: fadeInScale 0.5s ease backwards;
        }

        .stat-card::before {
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
        
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-card:hover::after {
            left: 100%;
        }

        .stat-card:hover {
            transform: translateY(-10px) scale(1.05) rotate(1deg);
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.25),
                        0 0 0 1px rgba(102, 126, 234, 0.1);
        }
        
        .stat-icon {
            font-size: 56px;
            margin-bottom: 15px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.1));
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 15px 0;
            letter-spacing: -1px;
        }
        
        .stat-label {
            font-size: 16px;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .chart-container {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 24px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: fadeIn 0.8s ease;
        }
        
        .chart-title {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 25px;
            text-align: center;
            letter-spacing: -0.5px;
        }
        
        .game-stats-table {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 24px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            margin-bottom: 30px;
            overflow-x: auto;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        table thead {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        }

        table th {
            padding: 16px;
            text-align: left;
            font-weight: 700;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
        }

        table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s ease;
        }

        table tbody tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        table tbody tr:last-child td {
            border-bottom: none;
        }

        @media (max-width: 768px) {
            .header-stats {
                padding: 25px;
            }

            .header-stats h1 {
                font-size: 32px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .stat-card {
                padding: 20px;
            }
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background: var(--primary-color);
            color: white;
            font-weight: 600;
        }
        
        tr:hover {
            background: rgba(52, 152, 219, 0.1);
        }
        
        .positive {
            color: var(--success-color);
            font-weight: 600;
        }
        
        .negative {
            color: #dc3545;
            font-weight: 600;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .back-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--text-dark);
            font-size: 18px;
        }
        
        .win-rate-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .win-rate-high {
            background: rgba(40, 167, 69, 0.2);
            color: var(--success-color);
        }
        
        .win-rate-medium {
            background: rgba(255, 193, 7, 0.2);
            color: var(--warning-color);
        }
        
        .win-rate-low {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        
        .refresh-bar {
            margin: 20px 0 10px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
        }
        
        .refresh-btn {
            padding: 12px 28px;
            border: none;
            border-radius: 999px;
            background: linear-gradient(135deg, #20c997 0%, #28a745 100%);
            color: #fff;
            font-weight: 700;
            font-size: 16px;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            box-shadow: 0 8px 20px rgba(32, 201, 151, 0.4);
            transition: all 0.3s ease;
        }
        
        .refresh-btn:hover:not(:disabled) {
            transform: translateY(-2px) scale(1.01);
            box-shadow: 0 12px 30px rgba(32, 201, 151, 0.6);
        }
        
        .refresh-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed !important;
        }
        
        .refresh-status {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-dark);
            min-height: 20px;
        }
        
        .refresh-status.error {
            color: #dc3545;
        }
        
        .refresh-status.success {
            color: var(--success-color);
        }
    </style>
</head>
<body>
    <div class="statistics-container">
        <div class="header-stats">
            <h1>📊 Thống Kê Cá Nhân</h1>
            <div style="margin-top: 15px; font-size: 18px; color: var(--success-color); font-weight: 600;">
                👤 <span id="headerUserName"><?= htmlspecialchars($user['Name']) ?></span> | 💰 <span id="headerUserMoney"><?= number_format($user['Money'], 0, ',', '.') ?></span> VNĐ
            </div>
        </div>
        
        <?php if (!$gameHistoryExists): ?>
            <div class="no-data">
                <h2>📊 Hệ thống thống kê chưa được kích hoạt</h2>
                <p>Vui lòng chạy file <strong>create_quests_tables.sql</strong> để tạo bảng game_history</p>
            </div>
        <?php else: ?>
            <div class="refresh-bar">
                <button id="refreshStatsBtn" class="refresh-btn">🔄 Làm mới dữ liệu</button>
                <span id="refreshStatus" class="refresh-status"></span>
            </div>
            <!-- Thống kê tổng quan -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">🎮</div>
                    <div class="stat-value" id="totalGamesValue"><?= number_format($totalGames) ?></div>
                    <div class="stat-label">Tổng Số Game</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🏆</div>
                    <div class="stat-value" id="totalWinsValue"><?= number_format($totalWins) ?></div>
                    <div class="stat-label">Số Lần Thắng</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📉</div>
                    <div class="stat-value" id="totalLossesValue"><?= number_format($totalLosses) ?></div>
                    <div class="stat-label">Số Lần Thua</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-value" id="winRateValue"><?= number_format($winRate, 1) ?>%</div>
                    <div class="stat-label">Tỷ Lệ Thắng</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-value <?= $totalEarned >= 0 ? 'positive' : 'negative' ?>" id="totalEarnedValue">
                        <?= number_format($totalEarned, 0, ',', '.') ?> VNĐ
                    </div>
                    <div class="stat-label">Tổng Kiếm Được</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💸</div>
                    <div class="stat-value" id="totalSpentValue"><?= number_format($totalSpent, 0, ',', '.') ?> VNĐ</div>
                    <div class="stat-label">Tổng Đã Cược</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🏅</div>
                    <div class="stat-value" id="achievementsValue"><?= number_format($achievementsCount) ?></div>
                    <div class="stat-label">Danh Hiệu</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🎯</div>
                    <div class="stat-value" id="questsValue"><?= number_format($questsCompleted) ?></div>
                    <div class="stat-label">Nhiệm Vụ Hoàn Thành</div>
                </div>
            </div>
            
            <!-- Biểu đồ thống kê -->
            <div class="chart-container" id="dailyChartSection">
                <div class="chart-title">📈 Thống Kê 30 Ngày Gần Nhất</div>
                <canvas id="dailyChart" height="80" style="<?= count($dailyStats) > 0 ? '' : 'display:none;' ?>"></canvas>
                <div class="no-data" id="dailyChartEmpty" style="<?= count($dailyStats) > 0 ? 'display:none;' : 'block;' ?>">
                    Chưa có dữ liệu để hiển thị biểu đồ.
                </div>
            </div>
            
            <div class="chart-container" id="gameChartSection">
                <div class="chart-title">🎮 Thống Kê Theo Game</div>
                <canvas id="gameChart" height="80" style="<?= count($gameStats) > 0 ? '' : 'display:none;' ?>"></canvas>
                <div class="no-data" id="gameChartEmpty" style="<?= count($gameStats) > 0 ? 'display:none;' : 'block;' ?>">
                    Chưa có dữ liệu để hiển thị biểu đồ.
                </div>
            </div>
            
            <!-- Thống kê theo game -->
            <div class="game-stats-table" id="gameStatsSection">
                <h2 style="margin-bottom: 20px; color: var(--primary-color);">🎮 Chi Tiết Theo Game</h2>
                <div class="no-data" id="gameStatsEmpty" style="<?= count($gameStats) > 0 ? 'display:none;' : 'block;' ?>">
                    Chưa có dữ liệu game để hiển thị.
                </div>
                <div id="gameStatsTableWrapper" style="<?= count($gameStats) > 0 ? 'display:block;' : 'display:none;' ?>">
                    <table>
                        <thead>
                            <tr>
                                <th>Game</th>
                                <th>Số Lần Chơi</th>
                                <th>Thắng</th>
                                <th>Tỷ Lệ Thắng</th>
                                <th>Kiếm Được</th>
                                <th>Đã Cược</th>
                            </tr>
                        </thead>
                        <tbody id="gameStatsBody">
                            <?php foreach ($gameStats as $stat): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($stat['game_name']) ?></strong></td>
                                <td><?= number_format($stat['plays']) ?></td>
                                <td><?= number_format($stat['wins']) ?></td>
                                <td>
                                    <span class="win-rate-badge <?= $stat['win_rate'] >= 50 ? 'win-rate-high' : ($stat['win_rate'] >= 30 ? 'win-rate-medium' : 'win-rate-low') ?>">
                                        <?= number_format($stat['win_rate'], 1) ?>%
                                    </span>
                                </td>
                                <td class="<?= $stat['earned'] >= 0 ? 'positive' : 'negative' ?>">
                                    <?= number_format($stat['earned'], 0, ',', '.') ?> VNĐ
                                </td>
                                <td><?= number_format($stat['spent'], 0, ',', '.') ?> VNĐ</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Game gần đây -->
            <div class="game-stats-table" id="recentGamesSection">
                <h2 style="margin-bottom: 20px; color: var(--primary-color);">🕐 Game Gần Đây (30 Ngày)</h2>
                <div class="no-data" id="recentGamesEmpty" style="<?= count($recentGames) > 0 ? 'display:none;' : 'block;' ?>">
                    Chưa có lịch sử chơi game gần đây.
                </div>
                <div id="recentGamesTableWrapper" style="<?= count($recentGames) > 0 ? 'display:block;' : 'display:none;' ?>">
                    <table>
                        <thead>
                            <tr>
                                <th>Game</th>
                                <th>Cược</th>
                                <th>Thắng</th>
                                <th>Kết Quả</th>
                                <th>Thời Gian</th>
                            </tr>
                        </thead>
                        <tbody id="recentGamesBody">
                            <?php foreach ($recentGames as $game): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($game['game_name']) ?></strong></td>
                                <td><?= number_format($game['bet_amount'], 0, ',', '.') ?> VNĐ</td>
                                <td><?= number_format($game['win_amount'], 0, ',', '.') ?> VNĐ</td>
                                <td>
                                    <?php if ($game['is_win']): ?>
                                        <span class="positive">✅ Thắng</span>
                                    <?php else: ?>
                                        <span class="negative">❌ Thua</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($game['played_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <a href="index.php" class="back-link">🏠 Về Trang Chủ</a>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
            const interactiveElements = document.querySelectorAll('button, a, input, label, select');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            });
            
            <?php if ($gameHistoryExists): ?>
            const initialStats = {
                totals: {
                    totalGames: <?= (int)$totalGames ?>,
                    totalWins: <?= (int)$totalWins ?>,
                    totalLosses: <?= (int)$totalLosses ?>,
                    totalEarned: <?= (float)$totalEarned ?>,
                    totalSpent: <?= (float)$totalSpent ?>,
                    winRate: <?= (float)$winRate ?>
                },
                gameStats: <?= json_encode($gameStats) ?>,
                dailyStats: <?= json_encode($dailyStats) ?>,
                recentGames: <?= json_encode($recentGames) ?>,
                achievementsCount: <?= (int)$achievementsCount ?>,
                questsCompleted: <?= (int)$questsCompleted ?>,
                gameHistoryEnabled: true
            };
            
            renderStats(initialStats);
            
            const refreshBtn = document.getElementById('refreshStatsBtn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', fetchLatestStats);
            }
            <?php endif; ?>
        });
        
        const numberFormatter = new Intl.NumberFormat('vi-VN');
        const currencyFormatter = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 });
        let dailyChartInstance = null;
        let gameChartInstance = null;
        
        function fetchLatestStats() {
            const btn = document.getElementById('refreshStatsBtn');
            const statusEl = document.getElementById('refreshStatus');
            if (!statusEl) return;
            statusEl.textContent = 'Đang tải dữ liệu...';
            statusEl.classList.remove('error', 'success');
            if (btn) btn.disabled = true;
            
            fetch('api_statistics.php', { headers: { 'Accept': 'application/json' }})
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Không thể kết nối tới máy chủ.');
                    }
                    return response.json();
                })
                .then(payload => {
                    if (!payload.success) {
                        throw new Error(payload.message || 'Không thể tải dữ liệu.');
                    }
                    if (!payload.stats || !payload.stats.gameHistoryEnabled) {
                        throw new Error('Hệ thống thống kê chưa sẵn sàng.');
                    }
                    renderStats(payload.stats);
                    updateHeaderInfo(payload.user);
                    const now = new Date();
                    statusEl.textContent = 'Đã cập nhật lúc ' + now.toLocaleTimeString('vi-VN');
                    statusEl.classList.add('success');
                })
                .catch(err => {
                    statusEl.textContent = 'Lỗi: ' + err.message;
                    statusEl.classList.add('error');
                })
                .finally(() => {
                    if (btn) btn.disabled = false;
                });
        }
        
        function renderStats(data) {
            if (!data || !data.totals) return;
            updateTotals(data);
            renderGameTable(data.gameStats || []);
            renderRecentGames(data.recentGames || []);
            renderCharts(data.dailyStats || [], data.gameStats || []);
        }
        
        function updateTotals(data) {
            const totals = data.totals;
            setText('totalGamesValue', formatNumber(totals.totalGames));
            setText('totalWinsValue', formatNumber(totals.totalWins));
            setText('totalLossesValue', formatNumber(totals.totalLosses));
            setText('winRateValue', (totals.winRate ?? 0).toFixed(1) + '%');
            
            const earnedEl = document.getElementById('totalEarnedValue');
            if (earnedEl) {
                earnedEl.classList.remove('positive', 'negative');
                earnedEl.classList.add(totals.totalEarned >= 0 ? 'positive' : 'negative');
                earnedEl.textContent = formatCurrency(totals.totalEarned);
            }
            setText('totalSpentValue', formatCurrency(totals.totalSpent));
            setText('achievementsValue', formatNumber(data.achievementsCount ?? 0));
            setText('questsValue', formatNumber(data.questsCompleted ?? 0));
        }
        
        function renderGameTable(gameStats) {
            const tbody = document.getElementById('gameStatsBody');
            const emptyEl = document.getElementById('gameStatsEmpty');
            const wrapper = document.getElementById('gameStatsTableWrapper');
            if (!tbody || !emptyEl || !wrapper) return;
            
            tbody.innerHTML = '';
            if (!gameStats.length) {
                emptyEl.style.display = 'block';
                wrapper.style.display = 'none';
                toggleChartVisibility('gameChart', 'gameChartEmpty', false);
                return;
            }
            emptyEl.style.display = 'none';
            wrapper.style.display = 'block';
            
            gameStats.forEach(stat => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${escapeHtml(stat.game_name)}</strong></td>
                    <td>${formatNumber(stat.plays)}</td>
                    <td>${formatNumber(stat.wins)}</td>
                    <td>
                        <span class="win-rate-badge ${getWinRateClass(stat.win_rate)}">
                            ${Number(stat.win_rate || 0).toFixed(1)}%
                        </span>
                    </td>
                    <td class="${(stat.earned ?? 0) >= 0 ? 'positive' : 'negative'}">
                        ${formatCurrency(stat.earned)}
                    </td>
                    <td>${formatCurrency(stat.spent)}</td>
                `;
                tbody.appendChild(tr);
            });
        }
        
        function renderRecentGames(recentGames) {
            const tbody = document.getElementById('recentGamesBody');
            const emptyEl = document.getElementById('recentGamesEmpty');
            const wrapper = document.getElementById('recentGamesTableWrapper');
            if (!tbody || !emptyEl || !wrapper) return;
            
            tbody.innerHTML = '';
            if (!recentGames.length) {
                emptyEl.style.display = 'block';
                wrapper.style.display = 'none';
                return;
            }
            emptyEl.style.display = 'none';
            wrapper.style.display = 'block';
            
            recentGames.forEach(game => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${escapeHtml(game.game_name)}</strong></td>
                    <td>${formatCurrency(game.bet_amount)}</td>
                    <td>${formatCurrency(game.win_amount)}</td>
                    <td>${game.is_win ? '<span class="positive">✅ Thắng</span>' : '<span class="negative">❌ Thua</span>'}</td>
                    <td>${formatDateTime(game.played_at)}</td>
                `;
                tbody.appendChild(tr);
            });
        }
        
        function renderCharts(dailyStats, gameStats) {
            renderDailyChart(dailyStats);
            renderGameChart(gameStats);
        }
        
        function renderDailyChart(dailyStats) {
            const canvas = document.getElementById('dailyChart');
            if (!canvas) return;
            const hasData = dailyStats.length > 0;
            toggleChartVisibility('dailyChart', 'dailyChartEmpty', hasData);
            if (!hasData) {
                if (dailyChartInstance) {
                    dailyChartInstance.destroy();
                    dailyChartInstance = null;
                }
                return;
            }
            const labels = dailyStats.map(d => formatChartDate(d.date));
            const earnedData = dailyStats.map(d => d.earned);
            const spentData = dailyStats.map(d => d.spent);
            
            if (!dailyChartInstance) {
                dailyChartInstance = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Kiếm Được (VNĐ)',
                            data: earnedData,
                            borderColor: 'rgb(40, 167, 69)',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            tension: 0.4
                        }, {
                            label: 'Đã Cược (VNĐ)',
                            data: spentData,
                            borderColor: 'rgb(220, 53, 69)',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            } else {
                dailyChartInstance.data.labels = labels;
                dailyChartInstance.data.datasets[0].data = earnedData;
                dailyChartInstance.data.datasets[1].data = spentData;
                dailyChartInstance.update();
            }
        }
        
        function renderGameChart(gameStats) {
            const canvas = document.getElementById('gameChart');
            if (!canvas) return;
            const hasData = gameStats.length > 0;
            toggleChartVisibility('gameChart', 'gameChartEmpty', hasData);
            if (!hasData) {
                if (gameChartInstance) {
                    gameChartInstance.destroy();
                    gameChartInstance = null;
                }
                return;
            }
            const labels = gameStats.map(g => g.game_name);
            const plays = gameStats.map(g => g.plays);
            
            if (!gameChartInstance) {
                gameChartInstance = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Số Lần Chơi',
                            data: plays,
                            backgroundColor: 'rgba(52, 152, 219, 0.8)'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            } else {
                gameChartInstance.data.labels = labels;
                gameChartInstance.data.datasets[0].data = plays;
                gameChartInstance.update();
            }
        }
        
        function toggleChartVisibility(canvasId, emptyId, showChart) {
            const canvas = document.getElementById(canvasId);
            const emptyEl = document.getElementById(emptyId);
            if (canvas) canvas.style.display = showChart ? 'block' : 'none';
            if (emptyEl) emptyEl.style.display = showChart ? 'none' : 'block';
        }
        
        function updateHeaderInfo(user) {
            if (!user) return;
            const nameEl = document.getElementById('headerUserName');
            const moneyEl = document.getElementById('headerUserMoney');
            if (nameEl && user.name) {
                nameEl.textContent = user.name;
            }
            if (moneyEl && user.money !== undefined) {
                moneyEl.textContent = formatNumber(user.money);
            }
        }
        
        function getWinRateClass(rate) {
            if (rate >= 50) return 'win-rate-high';
            if (rate >= 30) return 'win-rate-medium';
            return 'win-rate-low';
        }
        
        function setText(id, value) {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = value;
            }
        }
        
        function formatNumber(value) {
            return numberFormatter.format(Math.round(value || 0));
        }
        
        function formatCurrency(value) {
            return currencyFormatter.format(Math.round(value || 0)) + ' VNĐ';
        }
        
        function formatDateTime(value) {
            if (!value) return '--';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return value;
            }
            return date.toLocaleString('vi-VN');
        }
        
        function formatChartDate(value) {
            if (!value) return '';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return value;
            }
            return `${date.getDate()}/${date.getMonth() + 1}`;
        }
        
        function escapeHtml(value) {
            if (value === null || value === undefined) return '';
            return value.toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>
</body>
</html>

