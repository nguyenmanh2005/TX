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

if (!isset($_SESSION['Iduser'])) { header('Location: login.php'); exit(); }
$currentUserId = (int)$_SESSION['Iduser'];
if (!isAdmin($conn, $currentUserId)) { header("Location: 403.php"); exit(); }

$stats = [
    'users'  => ['total' => null, 'new7d' => null, 'active15m' => null, 'warnings' => []],
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

/* ── Divider ── */
.divider{border:none;border-top:1px solid var(--border);margin:8px 0 16px;}
</style>
</head>
<body>
<div class="wrapper">

    <!-- Header -->
    <div class="header">
        <div class="header-left">
            <div class="breadcrumb">⬡ Admin Panel</div>
            <h1>Dashboard <span>Overview</span></h1>
        </div>
        <div class="header-right">
            <a href="bot/tester_bot.php" target="_blank" class="btn btn-ghost" style="color: #ff7185; border-color: #fb7185;">
                <i class="fa fa-robot"></i> Chạy Tester Bot
            </a>
            <a href="chat3.php" class="btn btn-ghost">
                <i class="fa fa-bug"></i> Báo Cáo Lỗi
            </a>
            <a href="admin_analytics.php" class="btn btn-primary">
                <i class="fa fa-chart-line"></i> Phân Tích Website
            </a>
            <a href="logout.php" class="btn btn-ghost">
                <i class="fa fa-sign-out-alt"></i> Đăng xuất
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
</body>
</html>