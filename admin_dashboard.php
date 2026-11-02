<?php
session_start();
require_once 'db_connect.php';

// Helpers
function tableExists(mysqli $conn, string $table): bool {
    $safeTable = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    return $result && $result->num_rows > 0;
}
function columnExists(mysqli $conn, string $table, string $column): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeCol   = $conn->real_escape_string($column);
    $result    = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$safeTable}' AND COLUMN_NAME = '{$safeCol}'");
    return $result && $result->num_rows > 0;
}
function fetchOne(mysqli $conn, string $sql): ?array {
    $result = $conn->query($sql);
    return $result ? $result->fetch_assoc() : null;
}

require_once 'admin_helper.php';
require_once __DIR__ . '/admin_modules/navbar.php';

if (!isset($_SESSION['Iduser'])) { header('Location: login.php'); exit(); }
$currentUserId = (int)$_SESSION['Iduser'];
if (!isAdmin($conn, $currentUserId)) { header("Location: Shared/403/403.php"); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    session_write_close();
}

// Routing các phân hệ quản trị thống nhất
$page = $_GET['page'] ?? 'overview';
if ($page === 'economy') {
    require __DIR__ . '/admin_modules/mod_economy.php';
    exit();
} elseif ($page === 'assets') {
    require __DIR__ . '/admin_modules/mod_assets.php';
    exit();
} elseif ($page === 'events') {
    require __DIR__ . '/admin_modules/mod_events.php';
    exit();
} elseif ($page === 'competitions') {
    require __DIR__ . '/admin_modules/mod_competitions.php';
    exit();
}

$stats = [
    'users'  => ['total' => null, 'new7d' => null, 'active15m' => null, 'warnings' => []],
    'economy'=> ['total' => 0],
    'bots'   => ['total' => 0],
    'games'  => ['warnings' => []],
    'system' => ['dbOk' => true, 'warnings' => [], 'errors' => []],
    'logs'   => []
];

// --- User stats ---
if (tableExists($conn, 'users')) {
    $row = fetchOne($conn, "SELECT COUNT(*) AS c FROM users");
    $stats['users']['total'] = $row ? (int)$row['c'] : 0;
    if (columnExists($conn, 'users', 'created_at')) {
        $row = fetchOne($conn, "SELECT COUNT(*) AS c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['users']['new7d'] = $row ? (int)$row['c'] : 0;
    } else {
        $stats['users']['warnings'][] = "Thiếu cột created_at → không tính được user mới 7 ngày.";
    }
    if (columnExists($conn, 'users', 'last_active')) {
        $row = fetchOne($conn, "SELECT COUNT(*) AS c FROM users WHERE last_active >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $stats['users']['active15m'] = $row ? (int)$row['c'] : 0;
    } else {
        $stats['users']['warnings'][] = "Thiếu cột last_active → không tính được user online 15 phút.";
    }
    
    // Kinh tế chia phe
    $rowBot = fetchOne($conn, "SELECT SUM(Money) AS bot_money FROM users WHERE Email REGEXP '^bot[0-9]+@'");
    $rowReal = fetchOne($conn, "SELECT SUM(Money) AS real_money FROM users WHERE Email NOT REGEXP '^bot[0-9]+@'");
    $stats['economy']['bot_total'] = $rowBot ? (float)$rowBot['bot_money'] : 0;
    $stats['economy']['real_total'] = $rowReal ? (float)$rowReal['real_money'] : 0;
    $stats['economy']['total'] = $stats['economy']['bot_total'] + $stats['economy']['real_total'];
    
    // Top 5 Đại gia
    $resTop = $conn->query("SELECT Name, Money, IF(Email REGEXP '^bot[0-9]+@', 1, 0) as is_bot FROM users ORDER BY Money DESC LIMIT 5");
    $topUsers = [];
    if ($resTop) {
        while ($r = $resTop->fetch_assoc()) {
            $topUsers[] = [
                'name' => mb_strimwidth($r['Name'], 0, 15, '...'),
                'money' => (float)$r['Money'],
                'is_bot' => (int)$r['is_bot']
            ];
        }
    }
    $stats['top_users'] = $topUsers;
    
    // Bot
    $row = fetchOne($conn, "SELECT COUNT(*) AS c FROM users WHERE Email REGEXP '^bot[0-9]+@'");
    $stats['bots']['total'] = $row ? (int)$row['c'] : 0;
} else {
    $stats['system']['errors'][] = "Bảng users chưa tồn tại.";
}


// --- Error log ---
$possibleLogs = [__DIR__ . '/error_log', __DIR__ . '/php_errors.log', __DIR__ . '/../php_error.log'];
foreach ($possibleLogs as $path) {
    if (file_exists($path)) {
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            $stats['logs'] = array_slice($lines, -20);
            break;
        }
    }
}

$allWarnings = array_merge($stats['users']['warnings'], $stats['games']['warnings'], $stats['system']['warnings']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link rel="icon" href="images.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root {
    --bg:       #07090f;
    --surface:  #0e111a;
    --surface2: #141825;
    --border:   rgba(255,255,255,0.07);
    --border2:  rgba(255,255,255,0.12);
    --text:     #e8eaf0;
    --muted:    #636b80;
    --blue:     #4f8dff;
    --cyan:     #22d3ee;
    --green:    #34d399;
    --amber:    #fbbf24;
    --red:      #fb7185;
    --purple:   #a78bfa;
}
*{box-sizing:border-box;margin:0;padding:0}
body{
    font-family:'DM Sans',sans-serif;
    background:var(--bg);
    color:var(--text);
    min-height:100vh;
    padding:32px 28px;
}
/* noise overlay */
body::before{
    content:'';
    position:fixed;inset:0;
    background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
    opacity:.4;pointer-events:none;z-index:0;
}

/* Glow blobs */
body::after{
    content:'';
    position:fixed;
    top:-200px;left:-150px;
    width:600px;height:600px;
    background:radial-gradient(circle,rgba(79,141,255,.06) 0%,transparent 70%);
    pointer-events:none;z-index:0;
}

.wrapper{position:relative;z-index:1;max-width:1200px;margin:0 auto;}

/* ── Header ── */
.header{
    display:flex;justify-content:space-between;align-items:center;
    margin-bottom:36px;
}
.header-left .breadcrumb{
    font-family:'Space Mono',monospace;
    font-size:11px;letter-spacing:.15em;text-transform:uppercase;
    color:var(--blue);margin-bottom:8px;
}
.header-left h1{font-size:28px;font-weight:700;letter-spacing:-.5px;}
.header-left h1 span{color:var(--blue);}
.header-right{display:flex;gap:10px;align-items:center;}
.btn{
    display:inline-flex;align-items:center;gap:8px;
    padding:10px 20px;border-radius:10px;
    font-size:13px;font-weight:600;text-decoration:none;
    transition:all .2s;cursor:pointer;
}
.btn-primary{
    background:var(--blue);color:#fff;
    box-shadow:0 0 20px rgba(79,141,255,.25);
}
.btn-primary:hover{background:#3d7af5;box-shadow:0 0 28px rgba(79,141,255,.4);}
.btn-ghost{
    background:var(--surface2);color:var(--text);
    border:1px solid var(--border2);
}
.btn-ghost:hover{background:var(--surface);border-color:var(--border2);}

/* ── Grid ── */
.metrics-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:16px;
    margin-bottom:24px;
}
@media(max-width:900px){.metrics-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:500px){.metrics-grid{grid-template-columns:1fr;}}

/* ── Metric Card ── */
.metric-card{
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:18px;
    padding:22px;
    position:relative;
    overflow:hidden;
    transition:transform .2s,border-color .2s;
}
.metric-card:hover{transform:translateY(-2px);border-color:var(--border2);}
.metric-card::before{
    content:'';
    position:absolute;top:0;left:0;right:0;height:2px;
    border-radius:18px 18px 0 0;
}
.metric-card.blue::before{background:linear-gradient(90deg,var(--blue),var(--cyan));}
.metric-card.green::before{background:linear-gradient(90deg,var(--green),#6ee7b7);}
.metric-card.amber::before{background:linear-gradient(90deg,var(--amber),#fde68a);}
.metric-card.purple::before{background:linear-gradient(90deg,var(--purple),#c4b5fd);}

.metric-icon{
    width:38px;height:38px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    font-size:16px;margin-bottom:14px;
}
.metric-card.blue .metric-icon{background:rgba(79,141,255,.12);color:var(--blue);}
.metric-card.green .metric-icon{background:rgba(52,211,153,.12);color:var(--green);}
.metric-card.amber .metric-icon{background:rgba(251,191,36,.12);color:var(--amber);}
.metric-card.purple .metric-icon{background:rgba(167,139,250,.12);color:var(--purple);}

.metric-label{font-size:12px;color:var(--muted);font-weight:500;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;}
.metric-value{font-size:30px;font-weight:700;font-family:'Space Mono',monospace;letter-spacing:-1px;line-height:1;}
.metric-badge{
    display:inline-flex;align-items:center;gap:5px;
    margin-top:10px;padding:4px 10px;
    border-radius:6px;font-size:11px;font-weight:600;
}
.badge-green{background:rgba(52,211,153,.12);color:var(--green);}
.badge-amber{background:rgba(251,191,36,.12);color:var(--amber);}
.badge-blue{background:rgba(79,141,255,.12);color:var(--blue);}
.badge-red{background:rgba(251,113,133,.12);color:var(--red);}

/* ── 2-col layout ── */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;}
@media(max-width:760px){.two-col{grid-template-columns:1fr;}}

/* ── Section card ── */
.section-card{
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:18px;
    padding:22px;
}
.section-card h3{
    font-size:14px;font-weight:600;color:var(--text);
    margin-bottom:16px;display:flex;align-items:center;gap:8px;
}
.section-card h3 .icon{
    width:28px;height:28px;border-radius:8px;
    background:var(--surface2);
    display:flex;align-items:center;justify-content:center;
    font-size:13px;color:var(--muted);
}

/* ── Warning / Error list ── */
.alert-list{list-style:none;}
.alert-list li{
    padding:8px 12px;border-radius:8px;font-size:13px;
    margin-bottom:6px;display:flex;align-items:flex-start;gap:8px;
}
.alert-list li::before{content:'•';flex-shrink:0;margin-top:1px;}
.alert-list.warn li{background:rgba(251,191,36,.06);color:var(--amber);}
.alert-list.warn li::before{color:var(--amber);}
.alert-list.err li{background:rgba(251,113,133,.06);color:var(--red);}
.alert-list.err li::before{color:var(--red);}
.alert-list.ok li{background:rgba(52,211,153,.06);color:var(--green);}
.alert-list.ok li::before{color:var(--green);}

/* ── Stat row (game detail) ── */
.stat-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);}
.stat-row:last-child{border-bottom:none;}
.stat-row-label{font-size:13px;color:var(--muted);}
.stat-row-value{font-size:14px;font-weight:600;font-family:'Space Mono',monospace;}

/* ── Logs ── */
.log-box{
    background:#060810;border-radius:10px;
    padding:14px;max-height:220px;overflow-y:auto;
    font-family:'Space Mono',monospace;font-size:11px;
    line-height:1.7;color:#6b7280;
    border:1px solid var(--border);
}
.log-box::-webkit-scrollbar{width:4px;}
.log-box::-webkit-scrollbar-thumb{background:var(--surface2);border-radius:2px;}

/* ── Winrate bar ── */
.win-bar{margin-top:12px;}
.win-bar-label{display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:6px;}
.win-bar-track{height:6px;background:var(--surface2);border-radius:3px;overflow:hidden;}
.win-bar-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,var(--purple),var(--blue));transition:width .8s cubic-bezier(.4,0,.2,1);}

/* ── Action Grid ── */
.action-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 30px;
}
@media(max-width: 900px) { .action-grid { grid-template-columns: repeat(2, 1fr); } }
@media(max-width: 500px) { .action-grid { grid-template-columns: 1fr; } }

.action-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    text-decoration: none;
    color: var(--text);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}
.action-card:hover {
    transform: translateY(-3px) scale(1.02);
    border-color: var(--blue);
    box-shadow: 0 10px 30px rgba(79,141,255,0.15);
    background: var(--surface2);
}
.action-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; color: #fff;
    flex-shrink: 0;
}
.action-content h4 { font-size: 15px; font-weight: 700; margin-bottom: 4px; }
.action-content p { font-size: 12px; color: var(--muted); line-height: 1.4; }

/* ── Divider ── */
.divider{border:none;border-top:1px solid var(--border);margin:8px 0 16px;}

/* ── Mobile Optimization (Task G) ── */
@media(max-width: 768px) {
    body { padding: 16px 12px; }
    .header { flex-direction: column; align-items: flex-start; gap: 16px; margin-bottom: 24px; }
    .header-right { width: 100%; justify-content: space-between; }
    .header-left h1 { font-size: 24px; }
    .btn { padding: 8px 12px; font-size: 12px; flex: 1; justify-content: center; }
    .metric-value { font-size: 24px !important; }
    .metric-card { padding: 16px; }
    .action-card { padding: 16px; flex-direction: column; text-align: center; }
    .action-icon { margin-bottom: 8px; }
    .stat-row-label { font-size: 12px; }
    .stat-row-value { font-size: 12px; }
}
@media(max-width: 480px) {
    .metrics-grid { grid-template-columns: 1fr; gap: 12px; }
    .action-grid { grid-template-columns: 1fr; gap: 12px; }
    .header-right { flex-direction: column; }
    .btn { width: 100%; }
}
</style>
</head>
<body>
<div class="wrapper">

    <?php renderAdminNavbar('overview'); ?>

    <!-- Header Title -->
    <div class="header">
        <div class="header-left">
            <div class="breadcrumb">⬡ Admin Portal</div>
            <h1>Dashboard <span>Overview</span></h1>
        </div>
        <div class="header-right">
            <a href="admin_dashboard.php?page=economy&sub=overview" class="btn btn-ghost" style="border-color: rgba(79,141,255,0.4); color: var(--blue);">
                <i class="fa fa-vault"></i> Kinh Tế Vĩ Mô
            </a>
            <a href="admin_dashboard.php?page=assets&sub=users" class="btn btn-ghost" style="border-color: rgba(52,211,153,0.4); color: var(--green);">
                <i class="fa fa-users"></i> Quản Lý User
            </a>
        </div>
    </div>

    <!-- Metrics -->
    <div class="metrics-grid">
        <div class="metric-card blue">
            <div class="metric-icon"><i class="fa fa-users"></i></div>
            <div class="metric-label">Tổng người dùng</div>
            <div class="metric-value"><?= $stats['users']['total'] !== null ? number_format($stats['users']['total']) : '—' ?></div>
            <?php if ($stats['users']['new7d'] !== null): ?>
                <div class="metric-badge badge-green"><i class="fa fa-arrow-up"></i> +<?= number_format($stats['users']['new7d']) ?> trong 7 ngày</div>
            <?php else: ?>
                <div class="metric-badge badge-amber"><i class="fa fa-exclamation"></i> Thiếu created_at</div>
            <?php endif; ?>
        </div>

        <div class="metric-card green">
            <div class="metric-icon"><i class="fa fa-circle-dot"></i></div>
            <div class="metric-label">Đang online (15 phút)</div>
            <div class="metric-value"><?= $stats['users']['active15m'] !== null ? number_format($stats['users']['active15m']) : '—' ?></div>
            <?php if ($stats['users']['active15m'] !== null): ?>
                <div class="metric-badge badge-green"><i class="fa fa-wifi"></i> Đang hoạt động</div>
            <?php else: ?>
                <div class="metric-badge badge-amber"><i class="fa fa-clock"></i> Thiếu last_active</div>
            <?php endif; ?>
        </div>

        <div class="metric-card amber">
            <div class="metric-icon"><i class="fa fa-coins"></i></div>
            <div class="metric-label">Tổng khối lượng GTLM</div>
            <div class="metric-value" style="font-size: 24px;"><?= number_format($stats['economy']['total']) ?></div>
            <div class="metric-badge badge-amber"><i class="fa fa-vault"></i> Dữ liệu hệ thống</div>
        </div>

        <div class="metric-card purple">
            <div class="metric-icon"><i class="fa fa-robot"></i></div>
            <div class="metric-label">Quân đoàn AI (Bot Army)</div>
            <div class="metric-value"><?= number_format($stats['bots']['total']) ?></div>
            <div class="metric-badge badge-blue"><i class="fa fa-brain"></i> Vận hành tự động</div>
        </div>
    </div>

    <?php
    // Kiểm tra nhanh cảnh báo tài chính khẩn cấp
    $anomalyWinCount = (int)($conn->query("SELECT COUNT(DISTINCT user_id) FROM game_history WHERE played_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY user_id HAVING COUNT(*) >= 15 AND (SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END)/COUNT(*)) >= 0.8")->num_rows ?? 0);
    $anomalyWealthCount = (int)($conn->query("SELECT COUNT(*) FROM users WHERE Money >= 1000000000000")->fetch_row()[0] ?? 0);
    $totalAnomalies = $anomalyWinCount + $anomalyWealthCount;
    if ($totalAnomalies > 0): ?>
        <div style="background: rgba(251, 113, 133, 0.1); border: 1px solid rgba(251, 113, 133, 0.3); border-radius: 14px; padding: 14px 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(251, 113, 133, 0.2); color: #fb7185; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                    <i class="fa fa-triangle-exclamation"></i>
                </div>
                <div>
                    <h4 style="margin: 0; font-size: 14px; color: #fb7185; font-weight: 700;">Hệ Thống Phát Hiện <?= $totalAnomalies ?> Cảnh Báo Tài Chính & Nghi Vấn Bất Thường!</h4>
                    <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--muted);">Phát hiện tài khoản có tỉ lệ thắng dị thường >80% hoặc số dư vượt ngưỡng kiểm soát.</p>
                </div>
            </div>
            <a href="admin_economy.php?tab=anomalies" style="background: #fb7185; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s;">
                <i class="fa fa-shield-virus"></i> Xử Lý Ngay
            </a>
        </div>
    <?php endif; ?>

    <!-- Biểu đồ -->
    <div class="two-col" style="margin-bottom: 24px;">
        <div class="section-card">
            <h3><span class="icon"><i class="fa fa-pie-chart"></i></span> Phân Bổ GTLM (Thật vs Bot)</h3>
            <div style="height: 250px;">
                <canvas id="ecoPieChart"></canvas>
            </div>
        </div>
        <div class="section-card">
            <h3><span class="icon"><i class="fa fa-bar-chart"></i></span> Top 5 Đại Gia Server</h3>
            <div style="height: 250px;">
                <canvas id="topBarChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="section-card" style="margin-bottom: 24px; padding-bottom: 10px;">
        <h3><span class="icon"><i class="fa fa-bolt"></i></span> Trung Tâm Điều Khiển Vĩ Mô (Master Control Hub)</h3>
        <div class="action-grid">
            <a href="admin_dashboard.php?page=economy&sub=overview" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, #facc15, #eab308);"><i class="fa fa-vault"></i></div>
                <div class="action-content">
                    <h4>Kinh Tế & Dòng Tiền Vĩ Mô</h4>
                    <p>Tổng GDP, dòng tiền Inflow/Outflow, House Edge 61+ game và lạm phát.</p>
                </div>
            </a>
            <a href="admin_dashboard.php?page=economy&sub=anomalies" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, #ef4444, #f43f5e);"><i class="fa fa-shield-virus"></i></div>
                <div class="action-content">
                    <h4>Giám Sát Bất Thường (Fraud Monitor)</h4>
                    <p>Dò tìm nick cheat winrate >80%, tràn số GTLM và đóng băng số dư tức thì.</p>
                </div>
            </a>
            <a href="admin_dashboard.php?page=assets&sub=users" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, #10b981, #34d399);"><i class="fa fa-users"></i></div>
                <div class="action-content">
                    <h4>Quản Trị Người Dùng</h4>
                    <p>Tìm kiếm thành viên, hiệu chỉnh số dư GTLM / KC, cấp Role và khóa tài khoản.</p>
                </div>
            </a>
            <a href="admin_dashboard.php?page=assets&sub=items" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);"><i class="fa fa-box-open"></i></div>
                <div class="action-content">
                    <h4>Vật Phẩm, Khung Avatar & Chế Tạo</h4>
                    <p>Quản lý cửa hàng vật phẩm, kho khung avatar và công thức rèn trang bị.</p>
                </div>
            </a>
            <a href="admin_dashboard.php?page=events&sub=main" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);"><i class="fa fa-calendar-star"></i></div>
                <div class="action-content">
                    <h4>Đại Sự Kiện & Cốt Truyện</h4>
                    <p>Event Manager tổng lực, Flash Event chớp nhoáng và chương hồi cốt truyện.</p>
                </div>
            </a>
            <a href="admin_dashboard.php?page=competitions&sub=tournaments" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, #8b5cf6, #c084fc);"><i class="fa fa-trophy"></i></div>
                <div class="action-content">
                    <h4>Giải Đấu & Trọng Tài PvP</h4>
                    <p>Khởi tạo giải đấu Tournaments, xếp nhánh thi đấu và giám sát trận chiến PvP.</p>
                </div>
            </a>
            <a href="bot/index.php" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, #a855f7, #ec4899);"><i class="fa fa-robot"></i></div>
                <div class="action-content">
                    <h4>Quân Đoàn Bot AI (Bot Army)</h4>
                    <p>Hệ thống sinh sản hàng loạt và quản lý tài sản của toàn bộ Bot.</p>
                </div>
            </a>
            <a href="chat3.php" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, #ef4444, #f97316);"><i class="fa fa-shield-halved"></i></div>
                <div class="action-content">
                    <h4>Master Tester Hub (All-in-One)</h4>
                    <p>Kiểm toán an ninh 5.0, Kịch bản Chat AI, Mưa Lì Xì, Sound FX & CSDL Bot.</p>
                </div>
            </a>
            <?php
            require_once __DIR__ . '/realtime/realtime_publisher.php';
            $wsOnline = RealtimePublisher::isOnline();
            ?>
            <div class="action-card" style="border-color: <?= $wsOnline ? 'rgba(16, 185, 129, 0.4)' : 'rgba(245, 158, 11, 0.4)' ?>;">
                <div class="action-icon" style="background: <?= $wsOnline ? 'linear-gradient(135deg, #10b981, #059669)' : 'linear-gradient(135deg, #f59e0b, #d97706)' ?>;">
                    <i class="fa fa-bolt"></i>
                </div>
                <div class="action-content" style="flex:1;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                        <h4>WebSocket Gateway (0ms)</h4>
                        <span style="font-size:11px; font-weight:800; padding:2px 8px; border-radius:12px; background:<?= $wsOnline ? 'rgba(16, 185, 129, 0.2); color:#10b981' : 'rgba(245, 158, 11, 0.2); color:#f59e0b' ?>;">
                            <?= $wsOnline ? '🟢 ONLINE (Port 8088)' : '🟡 STANDBY (SSE Fallback)' ?>
                        </span>
                    </div>
                    <p>Đường truyền 0ms cho Chat, PvP Cờ Ca-rô & 61 Bàn Live. Khởi chạy bằng run_websocket.bat.</p>
                </div>
            </div>
            <a href="admin_dashboard.php?page=economy&sub=analytics" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, #06b6d4, #22d3ee);"><i class="fa fa-chart-line"></i></div>
                <div class="action-content">
                    <h4>Phân Tích Lưu Lượng & Tăng Trưởng</h4>
                    <p>Lượt truy cập, thiết bị người dùng, trang xem nhiều và báo cáo doanh thu.</p>
                </div>
            </a>
        </div>
    </div>

    <!-- System Status -->
    <div class="section-card" style="margin-bottom: 24px;">
        <h3><span class="icon"><i class="fa fa-shield-halved"></i></span> Trạng Thái Hệ Thống</h3>
        <?php if (empty($allWarnings) && empty($stats['system']['errors'])): ?>
            <ul class="alert-list ok"><li>Tất cả hệ thống hoạt động bình thường.</li></ul>
        <?php else: ?>
            <?php if (!empty($allWarnings)): ?>
                <div style="font-size:12px;color:var(--amber);font-weight:600;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;">Cảnh báo</div>
                <ul class="alert-list warn">
                    <?php foreach ($allWarnings as $w): ?>
                        <li><?= htmlspecialchars($w) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (!empty($stats['system']['errors'])): ?>
                <div style="font-size:12px;color:var(--red);font-weight:600;margin:12px 0 6px;text-transform:uppercase;letter-spacing:.05em;">Lỗi Hệ Thống</div>
                <ul class="alert-list err">
                    <?php foreach ($stats['system']['errors'] as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Logs -->
    <?php if (!empty($stats['logs'])): ?>
    <div class="section-card">
        <h3><span class="icon"><i class="fa fa-terminal"></i></span> error_log <span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:6px;">(20 dòng gần nhất)</span></h3>
        <div class="log-box">
            <?php foreach ($stats['logs'] as $line): ?>
                <?= htmlspecialchars($line) ?><br>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
        <p style="color:var(--muted);font-size:13px;margin-top:8px;">Không tìm thấy hoặc không đọc được file error_log.</p>
    <?php endif; ?>

</div>

<script>
// Chart configs
Chart.defaults.color = '#94a3b8';
Chart.defaults.font.family = "'Space Mono', monospace";

const ctxPie = document.getElementById('ecoPieChart').getContext('2d');
new Chart(ctxPie, {
    type: 'doughnut',
    data: {
        labels: ['Người Chơi Thật', 'Quân Đoàn Bot'],
        datasets: [{
            data: [<?= $stats['economy']['real_total'] ?>, <?= $stats['economy']['bot_total'] ?>],
            backgroundColor: ['#4f8dff', '#a78bfa'],
            borderWidth: 0,
            hoverOffset: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { padding: 20, font: { size: 12 } } },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ' + new Intl.NumberFormat().format(context.raw) + ' GTLM';
                    }
                }
            }
        },
        cutout: '70%'
    }
});

const topNames = <?= json_encode(array_column($stats['top_users'], 'name')) ?>;
const topMoney = <?= json_encode(array_column($stats['top_users'], 'money')) ?>;
const topColors = <?= json_encode(array_map(function($u) { return $u['is_bot'] ? '#a78bfa' : '#34d399'; }, $stats['top_users'])) ?>;

const ctxBar = document.getElementById('topBarChart').getContext('2d');
new Chart(ctxBar, {
    type: 'bar',
    data: {
        labels: topNames,
        datasets: [{
            label: 'Số GTLM',
            data: topMoney,
            backgroundColor: topColors,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(255,255,255,0.05)' },
                ticks: {
                    callback: function(value) {
                        if (value >= 1000000) return (value / 1000000).toFixed(1) + 'M';
                        if (value >= 1000) return (value / 1000).toFixed(1) + 'K';
                        return value;
                    }
                }
            },
            x: { grid: { display: false } }
        },
        plugins: { 
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return new Intl.NumberFormat().format(context.raw) + ' GTLM';
                    }
                }
            }
        }
    }
});
</script>
</body>
</html>