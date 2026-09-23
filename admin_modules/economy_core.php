<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../admin_helper.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}
$currentUserId = (int)$_SESSION['Iduser'];
if (!isAdmin($conn, $currentUserId)) {
    header("Location: ../Shared/403/403.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    session_write_close();
}

$tab = $_GET['sub'] ?? ($_GET['tab'] ?? 'overview');
$range = $_GET['range'] ?? '7d';

// Xác định khoảng thời gian theo bộ lọc
$dateCondition = "";
$dateLabel = "7 Ngày Gần Nhất";
if ($range === '24h') {
    $dateCondition = "played_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $dateLabel = "24 Giờ Qua";
} elseif ($range === '30d') {
    $dateCondition = "played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $dateLabel = "30 Ngày Qua";
} elseif ($range === 'all') {
    $dateCondition = "1=1";
    $dateLabel = "Toàn Thời Gian";
} else {
    $range = '7d';
    $dateCondition = "played_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $dateLabel = "7 Ngày Gần Nhất";
}

// 1. Dữ liệu Kinh Tế Vĩ Mô (GDP & Tài Sản)
$resGDP = $conn->query("SELECT SUM(Money) as total_gdp, COUNT(*) as total_users FROM users");
$gdpData = $resGDP ? $resGDP->fetch_assoc() : [];
$totalGDP = (float)($gdpData['total_gdp'] ?? 0);
$totalUsers = (int)($gdpData['total_users'] ?? 0);
$avgGDP = $totalUsers > 0 ? $totalGDP / $totalUsers : 0;

$rowBotRes = $conn->query("SELECT SUM(Money) as bot_money, COUNT(*) as bot_cnt FROM users WHERE Email REGEXP '^bot[0-9]+@' OR Email LIKE '%@example.com'");
$rowBot = $rowBotRes ? $rowBotRes->fetch_assoc() : [];
$botMoney = (float)($rowBot['bot_money'] ?? 0);
$botCount = (int)($rowBot['bot_cnt'] ?? 0);
$realMoney = max(0, $totalGDP - $botMoney);
$realCount = max(0, $totalUsers - $botCount);

// 2. Dòng tiền Inflow vs Outflow từ Game History theo Range
$sqlFlow = "SELECT COUNT(*) as total_plays,
                   SUM(bet_amount) as total_bet,
                   SUM(win_amount) as total_win,
                   SUM(bet_amount - win_amount) as house_profit
            FROM game_history
            WHERE $dateCondition";
$flowRes = $conn->query($sqlFlow);
$flowData = $flowRes ? $flowRes->fetch_assoc() : [];
$totalPlays = (int)($flowData['total_plays'] ?? 0);
$totalBet = (float)($flowData['total_bet'] ?? 0);
$totalWin = (float)($flowData['total_win'] ?? 0);
$houseProfit = (float)($flowData['house_profit'] ?? 0);
$rtp = $totalBet > 0 ? round(($totalWin / $totalBet) * 100, 2) : 0;

// 3. Phí thiêu đốt từ Sinks
$resMarket = $conn->query("SELECT SUM(price * 0.05) as fees FROM marketplace_listings WHERE status = 'sold'");
$feeMarket = (float)($resMarket && $row = $resMarket->fetch_assoc() ? ($row['fees'] ?? 0) : 0);

$resCraft = $conn->query("SELECT SUM(gtlm_spent) as spent FROM crafting_logs");
$feeCraft = (float)($resCraft && $row = $resCraft->fetch_assoc() ? ($row['spent'] ?? 0) : 0);

$resGuild = $conn->query("SELECT COUNT(*) * 500000 as fees FROM guilds");
$feeGuild = (float)($resGuild && $row = $resGuild->fetch_assoc() ? ($row['fees'] ?? 0) : 0);
$totalSinks = $feeMarket + $feeCraft + $feeGuild;

// 4. Dữ liệu biểu đồ Xu Hướng (Trend Chart)
$trendLabels = [];
$trendBets = [];
$trendWins = [];
$trendProfits = [];

if ($range === '24h') {
    // 24 giờ gom theo 12 mốc 2 giờ - Chạy DUY NHẤT 1 query có index
    $startDate = date('Y-m-d H:00:00', strtotime('-24 hours'));
    $resTrend = $conn->query("SELECT FLOOR(TIMESTAMPDIFF(HOUR, '$startDate', played_at) / 2) as slot,
                                     SUM(bet_amount) as b, SUM(win_amount) as w 
                              FROM game_history 
                              WHERE played_at >= '$startDate' 
                              GROUP BY slot");
    $slotMap = [];
    if ($resTrend) {
        while ($r = $resTrend->fetch_assoc()) $slotMap[(int)$r['slot']] = $r;
    }
    for ($i = 0; $i < 12; $i++) {
        $h = date('H:i', strtotime($startDate . " +" . ($i * 2) . " hours"));
        $trendLabels[] = $h;
        $b = (float)($slotMap[$i]['b'] ?? 0);
        $w = (float)($slotMap[$i]['w'] ?? 0);
        $trendBets[] = $b;
        $trendWins[] = $w;
        $trendProfits[] = ($b - $w);
    }
} elseif ($range === '30d') {
    // 30 ngày gom theo 10 mốc 3 ngày - Chạy DUY NHẤT 1 query
    $startDate = date('Y-m-d 00:00:00', strtotime('-29 days'));
    $resTrend = $conn->query("SELECT DATE(played_at) as p_date, SUM(bet_amount) as b, SUM(win_amount) as w 
                              FROM game_history 
                              WHERE played_at >= '$startDate' 
                              GROUP BY DATE(played_at)");
    $trendMap = [];
    if ($resTrend) {
        while ($r = $resTrend->fetch_assoc()) $trendMap[$r['p_date']] = $r;
    }
    for ($i = 29; $i >= 0; $i -= 3) {
        $d_start = date('Y-m-d', strtotime("-$i days"));
        $trendLabels[] = date('d/m', strtotime($d_start));
        $b = 0; $w = 0;
        for ($k = 0; $k < 3; $k++) {
            $subDate = date('Y-m-d', strtotime("-" . ($i - $k) . " days"));
            $b += (float)($trendMap[$subDate]['b'] ?? 0);
            $w += (float)($trendMap[$subDate]['w'] ?? 0);
        }
        $trendBets[] = $b;
        $trendWins[] = $w;
        $trendProfits[] = ($b - $w);
    }
} else {
    // 7 ngày gần nhất - Chạy DUY NHẤT 1 query thay vì 7 lần scan toàn bộ 125,000 dòng
    $startDate = date('Y-m-d 00:00:00', strtotime('-6 days'));
    $resTrend = $conn->query("SELECT DATE(played_at) as p_date, SUM(bet_amount) as b, SUM(win_amount) as w 
                              FROM game_history 
                              WHERE played_at >= '$startDate' 
                              GROUP BY DATE(played_at)");
    $trendMap = [];
    if ($resTrend) {
        while ($r = $resTrend->fetch_assoc()) $trendMap[$r['p_date']] = $r;
    }
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $trendLabels[] = date('d/m', strtotime($d));
        $b = (float)($trendMap[$d]['b'] ?? 0);
        $w = (float)($trendMap[$d]['w'] ?? 0);
        $trendBets[] = $b;
        $trendWins[] = $w;
        $trendProfits[] = ($b - $w);
    }
}

// 5. Thống Kê Theo Từng Mini-Game
$gameStats = [];
$resGames = $conn->query("SELECT game_name,
                                 COUNT(*) as plays,
                                 SUM(bet_amount) as total_bet,
                                 SUM(win_amount) as total_win,
                                 SUM(bet_amount - win_amount) as house_profit,
                                 ROUND(SUM(win_amount) / NULLIF(SUM(bet_amount), 0) * 100, 1) as rtp_rate
                          FROM game_history
                          WHERE $dateCondition
                          GROUP BY game_name
                          ORDER BY total_bet DESC");
if ($resGames) {
    while ($rg = $resGames->fetch_assoc()) $gameStats[] = $rg;
}

// 6. Top 20 Phú Hào (Bảng Phong Thần)
// Tự động kiểm tra và thêm cột last_active nếu database chưa có
$chkActive = $conn->query("SHOW COLUMNS FROM users LIKE 'last_active'");
if (!$chkActive || $chkActive->num_rows == 0) {
    @$conn->query("ALTER TABLE users ADD COLUMN last_active DATETIME NULL");
    $hasLastActive = false;
} else {
    $hasLastActive = true;
}
$lastActiveCol = $hasLastActive ? "last_active" : "NULL as last_active";
$resTop = $conn->query("SELECT Iduser, Name, Email, Money, Role, $lastActiveCol FROM users ORDER BY Money DESC LIMIT 20");
$topBillionaires = [];
$top10Sum = 0;
if ($resTop) {
    while ($r = $resTop->fetch_assoc()) {
        $topBillionaires[] = $r;
        if (count($topBillionaires) <= 10) $top10Sum += (float)$r['Money'];
    }
}

// 7. Số lượng Cảnh báo Bất thường (Anomalies Count)
$resWinrate = $conn->query("SELECT COUNT(DISTINCT user_id) FROM game_history WHERE played_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY user_id HAVING COUNT(*) >= 15 AND (SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END)/COUNT(*)) >= 0.8");
$anomalyWinrateCount = (int)($resWinrate ? $resWinrate->num_rows : 0);

$resWealth = $conn->query("SELECT COUNT(*) FROM users WHERE Money >= 1000000000000");
$anomalyWealthCount = (int)($resWealth && $row = $resWealth->fetch_row() ? ($row[0] ?? 0) : 0);
$totalAnomalyBadges = $anomalyWinrateCount + $anomalyWealthCount;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Economy & Financial Intelligence — Admin Panel</title>
    <link rel="icon" href="images.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --bg: #07090f;
            --surface: #0e111a;
            --surface2: #141825;
            --surface3: #1a2035;
            --border: rgba(255, 255, 255, 0.08);
            --border2: rgba(255, 255, 255, 0.15);
            --text: #e8eaf0;
            --muted: #636b80;
            --blue: #4f8dff;
            --cyan: #22d3ee;
            --green: #34d399;
            --amber: #fbbf24;
            --red: #fb7185;
            --purple: #a78bfa;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 30px 24px;
            min-height: 100vh;
            line-height: 1.5;
        }

        .container { max-width: 1360px; margin: 0 auto; }

        /* Navigation & Header */
        .top-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .btn-nav {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--surface2);
            color: var(--text);
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid var(--border);
            transition: all 0.2s;
        }
        .btn-nav:hover {
            border-color: var(--blue);
            color: var(--blue);
            transform: translateY(-2px);
        }

        .header-title-box h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-title-box p {
            color: var(--muted);
            font-size: 13px;
            margin-top: 4px;
        }

        /* Filter bar & Tabs */
        .controls-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 8px 12px;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .tabs-group {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            text-decoration: none;
            transition: 0.2s;
            position: relative;
        }
        .tab-btn:hover { color: #fff; background: var(--surface2); }
        .tab-btn.active {
            color: #fff;
            background: var(--blue);
            box-shadow: 0 4px 15px rgba(79, 141, 255, 0.3);
        }
        .tab-btn .badge-pill {
            font-size: 10px;
            padding: 2px 7px;
            border-radius: 20px;
            background: rgba(251, 113, 133, 0.25);
            color: #fb7185;
            font-weight: 800;
        }
        .tab-btn.active .badge-pill {
            background: #fff;
            color: #ef4444;
        }

        .range-group {
            display: flex;
            background: var(--surface2);
            border-radius: 10px;
            padding: 3px;
            border: 1px solid var(--border);
        }
        .range-btn {
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            text-decoration: none;
            border-radius: 8px;
            transition: 0.2s;
        }
        .range-btn:hover { color: #fff; }
        .range-btn.active { background: rgba(255, 255, 255, 0.1); color: #fff; }

        /* Metric Cards */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }
        @media (max-width: 1080px) { .metrics-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 580px) { .metrics-grid { grid-template-columns: 1fr; } }

        .metric-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 22px;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, border-color 0.2s;
        }
        .metric-card:hover { transform: translateY(-3px); border-color: var(--border2); }
        .metric-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
        .card-blue::before { background: linear-gradient(90deg, var(--blue), var(--cyan)); }
        .card-green::before { background: linear-gradient(90deg, var(--green), #6ee7b7); }
        .card-amber::before { background: linear-gradient(90deg, var(--amber), #fde68a); }
        .card-red::before { background: linear-gradient(90deg, var(--red), #fda4af); }

        .metric-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .metric-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; color: var(--muted); }
        .metric-icon-box {
            width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px;
        }
        .card-blue .metric-icon-box { background: rgba(79, 141, 255, 0.12); color: var(--blue); }
        .card-green .metric-icon-box { background: rgba(52, 211, 153, 0.12); color: var(--green); }
        .card-amber .metric-icon-box { background: rgba(251, 191, 36, 0.12); color: var(--amber); }
        .card-red .metric-icon-box { background: rgba(251, 113, 133, 0.12); color: var(--red); }

        .metric-number { font-family: 'Space Mono', monospace; font-size: 24px; font-weight: 700; color: #fff; margin-bottom: 8px; line-height: 1.2; }
        .metric-sub { font-size: 12px; color: var(--muted); display: flex; align-items: center; gap: 6px; }

        /* Layout Grid */
        .content-grid-2 {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 28px;
        }
        @media (max-width: 990px) { .content-grid-2 { grid-template-columns: 1fr; } }

        .panel-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 24px;
        }
        .panel-card h3 {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
        }

        /* Table styles */
        .data-table-wrap { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table th {
            text-align: left;
            padding: 12px 14px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
            background: rgba(255,255,255,0.02);
        }
        .data-table td {
            padding: 14px;
            border-bottom: 1px solid rgba(255,255,255,0.03);
            vertical-align: middle;
        }
        .data-table tr:hover td { background: rgba(255,255,255,0.02); }

        .game-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            color: #fff;
        }
        .rtp-pill {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-family: 'Space Mono', monospace;
            font-size: 11px;
            font-weight: 700;
        }
        .rtp-normal { background: rgba(52, 211, 153, 0.15); color: #34d399; }
        .rtp-high { background: rgba(251, 113, 133, 0.2); color: #fb7185; }

        /* Sinks Bar */
        .sinks-list { list-style: none; margin-top: 10px; }
        .sink-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            font-size: 13px;
        }
        .sink-item:last-child { border-bottom: none; }
        .sink-name { display: flex; align-items: center; gap: 10px; color: var(--text); }
        .sink-val { font-family: 'Space Mono', monospace; font-weight: 700; color: #ffd700; }
    </style>
</head>
<body>
<div class="container">

    <?php 
    require_once __DIR__ . '/navbar.php';
    renderAdminNavbar('economy', $tab); 
    ?>

    <!-- Header Title Box -->
    <div class="top-nav" style="margin-bottom: 16px;">
        <div class="header-title-box" style="text-align: left;">
            <h1 style="font-size: 20px;"><i class="fa fa-vault" style="color: var(--amber);"></i> KINH TẾ VĨ MÔ & HIỆU SUẤT TRẬN ĐỊA</h1>
            <p>Hệ thống giám sát vĩ mô: Đo lường tổng GDP, dòng tiền Inflow/Outflow, House Edge 61+ mini-game và phát hiện gian lận.</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="admin_dashboard.php?page=economy&sub=analytics" class="btn-nav" style="border-color: var(--cyan); color: var(--cyan);">
                <i class="fa fa-chart-line"></i> Phân Tích Website
            </a>
            <a href="admin_dashboard.php?page=economy&sub=revenue" class="btn-nav" style="border-color: var(--blue); color: var(--blue);">
                <i class="fa fa-chart-pie"></i> Báo Cáo Doanh Thu
            </a>
        </div>
    </div>

    <!-- Controls Bar: Tabs & Time Range -->
    <div class="controls-bar">
        <div class="tabs-group">
            <a href="admin_dashboard.php?page=economy&sub=overview&range=<?= $range ?>" onclick="switchEcoTab(event, 'overview')" class="tab-btn <?= ($tab === 'overview' || empty($tab)) ? 'active' : '' ?>" data-eco-tab="overview">
                <i class="fa fa-gauge-high"></i> Tổng Quan Kinh Tế
            </a>
            <a href="admin_dashboard.php?page=economy&sub=games&range=<?= $range ?>" onclick="switchEcoTab(event, 'games')" class="tab-btn <?= $tab === 'games' ? 'active' : '' ?>" data-eco-tab="games">
                <i class="fa fa-dice"></i> Hiệu Suất 61 Game
            </a>
            <a href="admin_dashboard.php?page=economy&sub=billionaires&range=<?= $range ?>" onclick="switchEcoTab(event, 'billionaires')" class="tab-btn <?= $tab === 'billionaires' ? 'active' : '' ?>" data-eco-tab="billionaires">
                <i class="fa fa-trophy"></i> Bảng Phong Thần
            </a>
            <a href="admin_dashboard.php?page=economy&sub=anomalies&range=<?= $range ?>" onclick="switchEcoTab(event, 'anomalies')" class="tab-btn <?= $tab === 'anomalies' ? 'active' : '' ?>" data-eco-tab="anomalies">
                <i class="fa fa-shield-virus"></i> Giám Sát Bất Thường
                <?php if ($totalAnomalyBadges > 0): ?>
                    <span class="badge-pill"><?= $totalAnomalyBadges ?></span>
                <?php endif; ?>
            </a>
        </div>

        <div class="range-group">
            <a href="?tab=<?= $tab ?>&range=24h" class="range-btn <?= $range === '24h' ? 'active' : '' ?>">24H</a>
            <a href="?tab=<?= $tab ?>&range=7d" class="range-btn <?= $range === '7d' ? 'active' : '' ?>">7 NGÀY</a>
            <a href="?tab=<?= $tab ?>&range=30d" class="range-btn <?= $range === '30d' ? 'active' : '' ?>">30 NGÀY</a>
            <a href="?tab=<?= $tab ?>&range=all" class="range-btn <?= $range === 'all' ? 'active' : '' ?>">TẤT CẢ</a>
        </div>
    </div>

    <!-- TAB 4: FINANCIAL MONITOR & ANOMALIES PANE -->
    <div id="eco-pane-anomalies" class="eco-pane" style="display: <?= $tab === 'anomalies' ? 'block' : 'none' ?>;">
        <?php require_once __DIR__ . '/financial_monitor.php'; ?>
    </div>

    <!-- TAB 2: GAME PERFORMANCE & HOUSE EDGE PANE -->
    <div id="eco-pane-games" class="eco-pane" style="display: <?= $tab === 'games' ? 'block' : 'none' ?>;">
        <!-- TAB 2: GAME PERFORMANCE & HOUSE EDGE -->
        <div class="panel-card" style="margin-bottom: 24px;">
            <h3><i class="fa fa-chart-simple" style="color: var(--blue);"></i> Báo Cáo Hiệu Suất & Lợi Nhuận Trận Địa (Dữ liệu: <?= $dateLabel ?>)</h3>
            <p style="font-size: 13px; color: var(--muted); margin-bottom: 20px;">
                Thống kê chi tiết từ hơn 125,000+ ván cược trong bảng <code>game_history</code>. Đo lường tỷ lệ trả thưởng (RTP) và lợi nhuận ròng của Nhà Cái.
            </p>
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tên Trận Địa</th>
                            <th style="text-align:center;">Lượt Chiến</th>
                            <th style="text-align:right;">Tổng Cược (GTLM)</th>
                            <th style="text-align:right;">Tổng Trả Thưởng (GTLM)</th>
                            <th style="text-align:right;">Lợi Nhuận Nhà Cái</th>
                            <th style="text-align:center;">RTP %</th>
                            <th style="text-align:center;">Trạng Thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gameStats as $g): 
                            $gProfit = (float)$g['house_profit'];
                            $gRtp = (float)$g['rtp_rate'];
                        ?>
                            <tr>
                                <td>
                                    <div class="game-badge">
                                        <i class="fa fa-gamepad" style="color: var(--cyan);"></i>
                                        <span><?= htmlspecialchars(ucfirst($g['game_name'])) ?></span>
                                    </div>
                                </td>
                                <td style="text-align:center; font-family:'Space Mono'; font-weight:700;"><?= number_format($g['plays']) ?></td>
                                <td style="text-align:right; font-family:'Space Mono';"><?= number_format($g['total_bet']) ?></td>
                                <td style="text-align:right; font-family:'Space Mono';"><?= number_format($g['total_win']) ?></td>
                                <td style="text-align:right; font-family:'Space Mono'; font-weight:700; color:<?= $gProfit >= 0 ? '#34d399' : '#fb7185' ?>;">
                                    <?= ($gProfit > 0 ? '+' : '') . number_format($gProfit) ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="rtp-pill <?= $gRtp > 100 ? 'rtp-high' : 'rtp-normal' ?>">
                                        <?= $gRtp ?>%
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($gProfit >= 0): ?>
                                        <span style="color:#34d399; font-size:12px; font-weight:700;"><i class="fa fa-circle-check"></i> Nhà cái lãi</span>
                                    <?php else: ?>
                                        <span style="color:#fb7185; font-size:12px; font-weight:700;"><i class="fa fa-triangle-exclamation"></i> Khách húp đậm</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: BILLIONAIRES / BẢNG PHONG THẦN PANE -->
    <div id="eco-pane-billionaires" class="eco-pane" style="display: <?= $tab === 'billionaires' ? 'block' : 'none' ?>;">
        <div class="panel-card">
            <h3><i class="fa fa-trophy" style="color: #ffd700;"></i> Bảng Phong Thần — Top 20 Đại Gia Nắm Giữ Tài Sản Lớn Nhất</h3>
            <p style="font-size: 13px; color: var(--muted); margin-bottom: 20px;">
                Danh sách phân loại người chơi thật và bot streamer, kiểm soát tỷ lệ tập trung của cải trong nền kinh tế.
            </p>
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Hạng</th>
                            <th>Mã ID</th>
                            <th>Tên Người Chơi</th>
                            <th>Email</th>
                            <th>Phân Loại</th>
                            <th style="text-align:right;">Tài Sản (GTLM)</th>
                            <th style="text-align:center;">% Toàn GDP</th>
                            <th style="text-align:center;">Hoạt Động Gần Nhất</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topBillionaires as $idx => $b): 
                            $isBot = (strpos($b['Email'], 'bot') !== false || strpos($b['Email'], '@example.com') !== false);
                            $pct = $totalGDP > 0 ? round(((float)$b['Money'] / $totalGDP) * 100, 2) : 0;
                        ?>
                            <tr>
                                <td><b style="color:#ffd700; font-family:'Space Mono';">#<?= $idx + 1 ?></b></td>
                                <td><span style="font-family:'Space Mono'; opacity:0.7;">#<?= $b['Iduser'] ?></span></td>
                                <td><b><?= htmlspecialchars($b['Name']) ?></b></td>
                                <td style="font-size:12px; color:var(--muted);"><?= htmlspecialchars($b['Email']) ?></td>
                                <td>
                                    <?php if ($isBot): ?>
                                        <span style="background:rgba(167,139,250,0.15); color:var(--purple); padding:3px 8px; border-radius:4px; font-size:11px; font-weight:700;"><i class="fa fa-robot"></i> Bot</span>
                                    <?php else: ?>
                                        <span style="background:rgba(79,141,255,0.15); color:var(--blue); padding:3px 8px; border-radius:4px; font-size:11px; font-weight:700;"><i class="fa fa-user"></i> Người Thật</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right; font-family:'Space Mono'; font-weight:700; color:#ffd700; font-size:15px;">
                                    <?= number_format($b['Money']) ?>
                                </td>
                                <td style="text-align:center; font-family:'Space Mono'; font-size:12px;">
                                    <?= $pct ?>%
                                </td>
                                <td style="text-align:center; font-size:12px; color:var(--muted);">
                                    <?= $b['last_active'] ?: 'Chưa ghi' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 1: OVERVIEW & MACROECONOMIC BALANCE PANE -->
    <div id="eco-pane-overview" class="eco-pane" style="display: <?= ($tab === 'overview' || empty($tab)) ? 'block' : 'none' ?>;">
        <!-- 4 Top Cards -->
        <div class="metrics-grid">
            <div class="metric-card card-amber">
                <div class="metric-top">
                    <div class="metric-label">Tổng GDP Server</div>
                    <div class="metric-icon-box"><i class="fa fa-vault"></i></div>
                </div>
                <div class="metric-number"><?= number_format($totalGDP) ?></div>
                <div class="metric-sub">
                    <span>Thật: <?= number_format($realMoney) ?> (<?= $totalGDP > 0 ? round(($realMoney/$totalGDP)*100) : 0 ?>%)</span>
                </div>
            </div>

            <div class="metric-card card-blue">
                <div class="metric-top">
                    <div class="metric-label">Dòng Tiền Bơm Vào (Inflow - <?= $dateLabel ?>)</div>
                    <div class="metric-icon-box"><i class="fa fa-arrow-down-left"></i></div>
                </div>
                <div class="metric-number"><?= number_format($totalWin) ?></div>
                <div class="metric-sub">
                    <span style="color: var(--blue);">Khách húp qua <?= number_format($totalPlays) ?> ván cược</span>
                </div>
            </div>

            <div class="metric-card card-green">
                <div class="metric-top">
                    <div class="metric-label">Dòng Tiền Hút Về (Outflow - <?= $dateLabel ?>)</div>
                    <div class="metric-icon-box"><i class="fa fa-fire"></i></div>
                </div>
                <div class="metric-number"><?= number_format($totalBet + $totalSinks) ?></div>
                <div class="metric-sub">
                    <span>Cược: <?= number_format($totalBet) ?> | Phí đốt: <?= number_format($totalSinks) ?></span>
                </div>
            </div>

            <div class="metric-card card-red">
                <div class="metric-top">
                    <div class="metric-label">Lợi Nhuận Ròng Nhà Cái (Net Profit)</div>
                    <div class="metric-icon-box"><i class="fa fa-sack-dollar"></i></div>
                </div>
                <div class="metric-number" style="color: <?= $houseProfit >= 0 ? '#34d399' : '#fb7185' ?>;">
                    <?= ($houseProfit > 0 ? '+' : '') . number_format($houseProfit + $totalSinks) ?>
                </div>
                <div class="metric-sub">
                    <span>RTP Toàn Sàn: <b><?= $rtp ?>%</b></span>
                </div>
            </div>
        </div>

        <!-- 2-Col Layout: Charts -->
        <div class="content-grid-2">
            <div class="panel-card">
                <h3><i class="fa fa-chart-line" style="color: var(--blue);"></i> Biến Động Dòng Tiền & Doanh Thu (<?= $dateLabel ?>)</h3>
                <div style="height: 320px;">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <div class="panel-card">
                <h3><i class="fa fa-pie-chart" style="color: var(--purple);"></i> Phân Bổ Của Cải Toàn Server</h3>
                <div style="height: 250px;">
                    <canvas id="distributionChart"></canvas>
                </div>
                <div style="margin-top: 15px; font-size: 12px; color: var(--muted); text-align: center;">
                    Top 10 Đại gia nắm giữ <b style="color: #ffd700;"><?= $totalGDP > 0 ? round(($top10Sum / $totalGDP) * 100, 1) : 0 ?>%</b> tổng lượng GTLM toàn máy chủ.
                </div>
            </div>
        </div>

        <!-- Bottom Row: Sinks & Top Games -->
        <div class="content-grid-2">
            <div class="panel-card">
                <h3><i class="fa fa-fire-burner" style="color: var(--amber);"></i> Các Kênh Đốt Tiền Giảm Lạm Phát (GTLM Sinks)</h3>
                <ul class="sinks-list">
                    <li class="sink-item">
                        <div class="sink-name"><i class="fa fa-store" style="color: var(--cyan);"></i> Phí Giao Dịch Chợ Marketplace (5%)</div>
                        <div class="sink-val"><?= number_format($feeMarket) ?> GTLM</div>
                    </li>
                    <li class="sink-item">
                        <div class="sink-name"><i class="fa fa-hammer" style="color: var(--purple);"></i> Chi Phí Chế Tạo Vật Phẩm (Crafting)</div>
                        <div class="sink-val"><?= number_format($feeCraft) ?> GTLM</div>
                    </li>
                    <li class="sink-item">
                        <div class="sink-name"><i class="fa fa-shield-halved" style="color: var(--blue);"></i> Phí Thành Lập Bang Hội (Guilds)</div>
                        <div class="sink-val"><?= number_format($feeGuild) ?> GTLM</div>
                    </li>
                    <li class="sink-item">
                        <div class="sink-name"><i class="fa fa-calculator" style="color: var(--green);"></i> <b>TỔNG GTLM ĐÃ THIÊU ĐỐT VĨNH VIỄN</b></div>
                        <div class="sink-val" style="color: var(--green); font-size: 16px;"><?= number_format($totalSinks) ?> GTLM</div>
                    </li>
                </ul>
            </div>

            <div class="panel-card">
                <h3><i class="fa fa-trophy" style="color: #ffd700;"></i> Top 5 Trận Địa Thu Hút Cược Nhất</h3>
                <div class="data-table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Trận Địa</th>
                                <th style="text-align:center;">Ván</th>
                                <th style="text-align:right;">Lợi Nhuận</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($gameStats, 0, 5) as $sg): ?>
                                <tr>
                                    <td><b><?= htmlspecialchars(ucfirst($sg['game_name'])) ?></b></td>
                                    <td style="text-align:center; font-family:'Space Mono';"><?= number_format($sg['plays']) ?></td>
                                    <td style="text-align:right; font-family:'Space Mono'; font-weight:700; color:<?= (float)$sg['house_profit'] >= 0 ? '#34d399' : '#fb7185' ?>;">
                                        <?= ((float)$sg['house_profit'] > 0 ? '+' : '') . number_format($sg['house_profit']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
        // Trend Chart
        const ctxTrend = document.getElementById('trendChart');
        if (ctxTrend) {
            new Chart(ctxTrend, {
                type: 'line',
                data: {
                    labels: <?= json_encode($trendLabels) ?>,
                    datasets: [
                        {
                            label: 'Tiền Cược (Outflow)',
                            data: <?= json_encode($trendBets) ?>,
                            borderColor: '#4f8dff',
                            backgroundColor: 'rgba(79, 141, 255, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: 'Trả Thưởng (Inflow)',
                            data: <?= json_encode($trendWins) ?>,
                            borderColor: '#34d399',
                            backgroundColor: 'rgba(52, 211, 153, 0.05)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: 'Lợi Nhuận Ròng',
                            data: <?= json_encode($trendProfits) ?>,
                            borderColor: '#fbbf24',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            fill: false,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { labels: { color: '#e8eaf0', font: { family: 'DM Sans' } } }
                    },
                    scales: {
                        x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#636b80' } },
                        y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#636b80' } }
                    }
                }
            });
        }

        // Wealth Distribution Chart
        const ctxDist = document.getElementById('distributionChart');
        if (ctxDist) {
            const botOnly = Math.max(0, <?= $botMoney ?>);
            const top10Real = Math.max(0, <?= $top10Sum ?> - <?= $botMoney ?>);
            const otherReal = Math.max(0, <?= $realMoney ?> - top10Real);

            new Chart(ctxDist, {
                type: 'doughnut',
                data: {
                    labels: ['Quân Đoàn Bot', 'Top Đại Gia', 'Cộng Đồng Dân Thường'],
                    datasets: [{
                        data: [botOnly, top10Real, otherReal],
                        backgroundColor: ['#a78bfa', '#fbbf24', '#4f8dff'],
                        borderColor: 'transparent'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#e8eaf0', font: { family: 'DM Sans' } } }
                    },
                    cutout: '65%'
                }
            });
        }
        </script>
    </div>

    <script>
    let currentEcoTab = '<?= $tab ?: "overview" ?>';
    function switchEcoTab(e, tab) {
        if (e && typeof e.preventDefault === 'function') e.preventDefault();
        currentEcoTab = tab;

        // 1. Cập nhật trạng thái nút tab nội bộ trang
        document.querySelectorAll('.tab-btn[data-eco-tab]').forEach(function(btn) {
            if (btn.getAttribute('data-eco-tab') === tab) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        // 2. Chuyển đổi nội dung pane
        document.querySelectorAll('.eco-pane').forEach(function(pane) {
            pane.style.display = 'none';
        });
        var targetPane = document.getElementById('eco-pane-' + tab);
        if (targetPane) {
            targetPane.style.display = 'block';
            window.dispatchEvent(new Event('resize'));
        }

        // 3. Cập nhật trạng thái active trên navbar phân hệ
        document.querySelectorAll('.master-sub-item').forEach(function(item) {
            var href = item.getAttribute('href') || '';
            if (href.indexOf('sub=' + tab) !== -1) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });

        // 4. Đồng bộ URL trình duyệt mà không cần reload
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', 'admin_dashboard.php?page=economy&sub=' + tab + '&range=<?= $range ?>');
        }
    }
    </script>

</div>
</body>
</html>
