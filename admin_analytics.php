<?php
session_start();
require_once 'db_connect.php';
require_once 'admin_helper.php';

$currentUserId = (int)($_SESSION['Iduser'] ?? 0);
if (!isAdmin($conn, $currentUserId)) { header("Location: 403.php"); exit(); }

function gc($conn, $sql) {
    $res = $conn->query($sql);
    return $res ? (int)$res->fetch_row()[0] : 0;
}
function gRows($conn, $sql) {
    $res = $conn->query($sql);
    $out = [];
    if ($res) while ($r = $res->fetch_assoc()) $out[] = $r;
    return $out;
}

// Check table
$tableCheck = $conn->query("SHOW TABLES LIKE 'site_analytics'");
if (!$tableCheck || $tableCheck->num_rows == 0) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:sans-serif;background:#07090f;color:#e8eaf0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}</style></head><body><div style="text-align:center;"><p style="font-size:24px">⚠️</p><p>Bảng <code>site_analytics</code> chưa được tạo.</p><p>Vui lòng chạy file <code>analytics_schema.sql</code> trong database.</p><a href="admin_dashboard.php" style="color:#4f8dff">← Quay lại Dashboard</a></div></body></html>');
}

// ── Date boundaries ──
$today     = date('Y-m-d 00:00:00');
$yday_s    = date('Y-m-d 00:00:00', strtotime('-1 day'));
$yday_e    = date('Y-m-d 23:59:59', strtotime('-1 day'));

// ── Core metrics ──
$total_req   = gc($conn,"SELECT COUNT(*) FROM site_analytics");
$req_today   = gc($conn,"SELECT COUNT(*) FROM site_analytics WHERE visited_at >= '$today'");
$req_yday    = gc($conn,"SELECT COUNT(*) FROM site_analytics WHERE visited_at BETWEEN '$yday_s' AND '$yday_e'");
$req_chg     = $req_yday > 0 ? (($req_today - $req_yday) / $req_yday * 100) : 0;

$total_vis   = gc($conn,"SELECT COUNT(DISTINCT ip_address) FROM site_analytics");
$vis_today   = gc($conn,"SELECT COUNT(DISTINCT ip_address) FROM site_analytics WHERE visited_at >= '$today'");
$vis_yday    = gc($conn,"SELECT COUNT(DISTINCT ip_address) FROM site_analytics WHERE visited_at BETWEEN '$yday_s' AND '$yday_e'");
$vis_chg     = $vis_yday > 0 ? (($vis_today - $vis_yday) / $vis_yday * 100) : 0;

$total_pv    = gc($conn,"SELECT COUNT(*) FROM site_analytics WHERE page_url NOT LIKE '%api_%'");
$pv_today    = gc($conn,"SELECT COUNT(*) FROM site_analytics WHERE page_url NOT LIKE '%api_%' AND visited_at >= '$today'");
$pv_yday     = gc($conn,"SELECT COUNT(*) FROM site_analytics WHERE page_url NOT LIKE '%api_%' AND visited_at BETWEEN '$yday_s' AND '$yday_e'");
$pv_chg      = $pv_yday > 0 ? (($pv_today - $pv_yday) / $pv_yday * 100) : 0;

$total_api   = gc($conn,"SELECT COUNT(*) FROM site_analytics WHERE page_url LIKE '%api_%'");
$api_today   = gc($conn,"SELECT COUNT(*) FROM site_analytics WHERE page_url LIKE '%api_%' AND visited_at >= '$today'");
$api_yday    = gc($conn,"SELECT COUNT(*) FROM site_analytics WHERE page_url LIKE '%api_%' AND visited_at BETWEEN '$yday_s' AND '$yday_e'");
$api_chg     = $api_yday > 0 ? (($api_today - $api_yday) / $api_yday * 100) : 0;

// ── Time range filter for chart ──
$range = $_GET['range'] ?? '7d';
$trend_labels = $trend_req = $trend_vis = [];

if ($range === '24h') {
    // Last 24 hours, grouped by hour
    for ($i = 23; $i >= 0; $i--) {
        $h = date('Y-m-d H:00:00', strtotime("-$i hours"));
        $h_end = date('Y-m-d H:59:59', strtotime("-$i hours"));
        $trend_labels[] = date('H:i', strtotime($h));
        $trend_req[]    = gc($conn, "SELECT COUNT(*) FROM site_analytics WHERE visited_at BETWEEN '$h' AND '$h_end'");
        $trend_vis[]    = gc($conn, "SELECT COUNT(DISTINCT ip_address) FROM site_analytics WHERE visited_at BETWEEN '$h' AND '$h_end'");
    }
} elseif ($range === '1y' || $range === 'all') {
    // Last 12 months (or all), grouped by month
    $months = ($range === '1y') ? 11 : 23; // Simple all-time as 2 years for now or adjust as needed
    for ($i = $months; $i >= 0; $i--) {
        $m_start = date('Y-m-01 00:00:00', strtotime("-$i months"));
        $m_end = date('Y-m-t 23:59:59', strtotime("-$i months"));
        $trend_labels[] = date('m/Y', strtotime($m_start));
        $trend_req[]    = gc($conn, "SELECT COUNT(*) FROM site_analytics WHERE visited_at BETWEEN '$m_start' AND '$m_end'");
        $trend_vis[]    = gc($conn, "SELECT COUNT(DISTINCT ip_address) FROM site_analytics WHERE visited_at BETWEEN '$m_start' AND '$m_end'");
    }
} else {
    // Default 7d or 30d, grouped by day
    $days = ($range === '30d') ? 29 : 6;
    for ($i = $days; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $trend_labels[] = date('d/m', strtotime($d));
        $trend_req[]    = gc($conn, "SELECT COUNT(*) FROM site_analytics WHERE DATE(visited_at)='$d'");
        $trend_vis[]    = gc($conn, "SELECT COUNT(DISTINCT ip_address) FROM site_analytics WHERE DATE(visited_at)='$d'");
    }
}

// ── Summary Comparison Data ──
$comp_periods = [
    '24h' => date('Y-m-d H:i:s', strtotime('-24 hours')),
    '7d'  => date('Y-m-d 00:00:00', strtotime('-7 days')),
    '30d' => date('Y-m-d 00:00:00', strtotime('-30 days')),
    '1y'  => date('Y-m-d 00:00:00', strtotime('-1 year')),
    'all' => '2000-01-01 00:00:00'
];
$comp_req = [];
$comp_vis = [];
foreach ($comp_periods as $p => $start) {
    $comp_req[] = gc($conn, "SELECT COUNT(*) FROM site_analytics WHERE visited_at >= '$start'");
    $comp_vis[] = gc($conn, "SELECT COUNT(DISTINCT ip_address) FROM site_analytics WHERE visited_at >= '$start'");
}

// ── Table data ──
$countries    = gRows($conn,"SELECT country, COUNT(*) as cnt FROM site_analytics GROUP BY country ORDER BY cnt DESC LIMIT 10");
$top_pages    = gRows($conn,"SELECT page_url, COUNT(*) as cnt FROM site_analytics WHERE page_url NOT LIKE '%api_%' GROUP BY page_url ORDER BY cnt DESC LIMIT 10");
$sources      = gRows($conn,"SELECT source, COUNT(*) as cnt FROM site_analytics GROUP BY source ORDER BY cnt DESC LIMIT 10");
$os_dist      = gRows($conn,"SELECT os, COUNT(*) as cnt FROM site_analytics GROUP BY os ORDER BY cnt DESC LIMIT 8");
$browser_dist = gRows($conn,"SELECT browser, COUNT(*) as cnt FROM site_analytics GROUP BY browser ORDER BY cnt DESC LIMIT 8");
$devices      = gRows($conn,"SELECT device, COUNT(*) as cnt FROM site_analytics GROUP BY device ORDER BY cnt DESC LIMIT 8");
$http_ver     = gRows($conn,"SELECT http_version, COUNT(*) as cnt FROM site_analytics GROUP BY http_version ORDER BY cnt DESC LIMIT 5");
$statuses     = []; // status_code not tracked yet
$recent       = gRows($conn,"SELECT ip_address, country, page_url, browser, visited_at FROM site_analytics ORDER BY visited_at DESC LIMIT 15");

// pie data for devices
$dev_labels = $dev_vals = [];
foreach ($devices as $d) { $dev_labels[] = $d['device'] ?: 'Unknown'; $dev_vals[] = (int)$d['cnt']; }

// donut for OS
$os_labels = $os_vals = [];
foreach ($os_dist as $o) { $os_labels[] = $o['os'] ?: 'Unknown'; $os_vals[] = (int)$o['cnt']; }

// bar for status
$st_labels = $st_vals = [];
foreach ($statuses as $s) { $st_labels[] = (string)$s['status_code']; $st_vals[] = (int)$s['cnt']; }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Website Analytics — gtlmanh.id.vn</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
:root{
    --bg:#07090f;
    --surface:#0e111a;
    --surface2:#141825;
    --surface3:#1a1f30;
    --border:rgba(255,255,255,0.07);
    --border2:rgba(255,255,255,0.12);
    --text:#e8eaf0;
    --muted:#636b80;
    --blue:#4f8dff;
    --cyan:#22d3ee;
    --green:#34d399;
    --amber:#fbbf24;
    --red:#fb7185;
    --purple:#a78bfa;
    --pink:#f472b6;
    --orange:#fb923c;
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
    font-family:'DM Sans',sans-serif;
    background:var(--bg);
    color:var(--text);
    min-height:100vh;
    padding:28px 24px;
}
body::before{
    content:'';position:fixed;inset:0;
    background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
    opacity:.4;pointer-events:none;z-index:0;
}
.wrap{position:relative;z-index:1;max-width:1400px;margin:0 auto;}

/* ── Header ── */
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;padding-bottom:20px;border-bottom:1px solid var(--border);}
.header-left .tag{font-family:'Space Mono',monospace;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--blue);margin-bottom:6px;}
.header-left h1{font-size:22px;font-weight:700;letter-spacing:-.3px;}
.header-left h1 .domain{color:var(--cyan);}
.live-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.2);border-radius:20px;font-size:12px;font-weight:600;color:var(--green);}
.live-dot{width:7px;height:7px;border-radius:50%;background:var(--green);box-shadow:0 0 6px var(--green);animation:pulse 1.5s infinite;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
.btn{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;transition:all .2s;}
.btn-ghost{background:var(--surface2);color:var(--text);border:1px solid var(--border2);}
.btn-ghost:hover{background:var(--surface3);}

/* ── Metric cards ── */
.metrics-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
@media(max-width:960px){.metrics-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:520px){.metrics-grid{grid-template-columns:1fr}}
.m-card{
    background:var(--surface);border:1px solid var(--border);
    border-radius:18px;padding:20px;position:relative;overflow:hidden;
    transition:transform .2s,border-color .2s;
}
.m-card:hover{transform:translateY(-2px);border-color:var(--border2);}
.m-card::after{
    content:'';position:absolute;bottom:0;right:0;
    width:80px;height:80px;border-radius:50%;
    opacity:.07;transform:translate(20px,20px);
}
.m-card.c1::after{background:var(--blue);}
.m-card.c2::after{background:var(--green);}
.m-card.c3::after{background:var(--amber);}
.m-card.c4::after{background:var(--purple);}
.m-card-icon{font-size:18px;margin-bottom:12px;}
.m-card.c1 .m-card-icon{color:var(--blue);}
.m-card.c2 .m-card-icon{color:var(--green);}
.m-card.c3 .m-card-icon{color:var(--amber);}
.m-card.c4 .m-card-icon{color:var(--purple);}
.m-card-label{font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:500;margin-bottom:6px;}
.m-card-val{font-size:28px;font-weight:700;font-family:'Space Mono',monospace;letter-spacing:-1px;}
.m-card-sub{margin-top:8px;font-size:12px;font-weight:600;display:flex;align-items:center;gap:4px;}
.up{color:var(--green)}.down{color:var(--red)}.stable{color:var(--muted)}
.m-card-today{font-size:11px;color:var(--muted);margin-top:4px;}

/* ── Chart section ── */
.chart-main{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:24px;margin-bottom:24px;}
.section-title{font-size:14px;font-weight:600;color:var(--text);margin-bottom:18px;display:flex;align-items:center;gap:8px;}
.section-title .dot{width:8px;height:8px;border-radius:2px;flex-shrink:0;}
.dot-blue{background:var(--blue);}
.dot-green{background:var(--green);}
.dot-amber{background:var(--amber);}
.dot-purple{background:var(--purple);}
.dot-cyan{background:var(--cyan);}
.dot-red{background:var(--red);}

/* ── Grid layouts ── */
.g2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;}
.g3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px;}
@media(max-width:960px){.g2,.g3{grid-template-columns:1fr}}
.g13{display:grid;grid-template-columns:1.4fr 1fr;gap:16px;margin-bottom:16px;}
@media(max-width:960px){.g13{grid-template-columns:1fr}}

/* ── Cards ── */
.card{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:22px;}

/* ── Table ── */
.tbl{width:100%;border-collapse:collapse;}
.tbl th{text-align:left;color:var(--muted);font-size:10px;letter-spacing:.08em;text-transform:uppercase;padding:0 8px 10px;border-bottom:1px solid var(--border);}
.tbl td{padding:11px 8px;font-size:13px;border-bottom:1px solid rgba(255,255,255,0.03);}
.tbl tr:last-child td{border-bottom:none;}
.tbl tr:hover td{background:rgba(255,255,255,0.015);}
.tbl .num{text-align:right;font-family:'Space Mono',monospace;font-weight:700;}
.url-cell{max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

/* ── Progress bar ── */
.bar{height:4px;background:var(--surface2);border-radius:2px;overflow:hidden;margin-top:6px;}
.bar-fill{height:100%;border-radius:2px;transition:width .6s;}
.bar-blue{background:linear-gradient(90deg,var(--blue),var(--cyan));}
.bar-green{background:linear-gradient(90deg,var(--green),#6ee7b7);}
.bar-amber{background:linear-gradient(90deg,var(--amber),#fde68a);}
.bar-purple{background:linear-gradient(90deg,var(--purple),#c4b5fd);}
.bar-cyan{background:var(--cyan);}
.bar-red{background:var(--red);}
.bar-orange{background:var(--orange);}

/* ── Rank number ── */
.rank{
    display:inline-flex;align-items:center;justify-content:center;
    width:22px;height:22px;border-radius:6px;
    font-family:'Space Mono',monospace;font-size:10px;font-weight:700;
    background:var(--surface2);color:var(--muted);flex-shrink:0;
}
.rank-1{background:rgba(251,191,36,.15);color:var(--amber);}
.rank-2{background:rgba(79,141,255,.12);color:var(--blue);}
.rank-3{background:rgba(52,211,153,.12);color:var(--green);}

/* ── Status badge ── */
.status-badge{
    display:inline-block;padding:2px 8px;border-radius:5px;
    font-size:11px;font-weight:700;font-family:'Space Mono',monospace;
}
.s200{background:rgba(52,211,153,.12);color:var(--green);}
.s301,.s302{background:rgba(79,141,255,.12);color:var(--blue);}
.s404{background:rgba(251,191,36,.12);color:var(--amber);}
.s403,.s500{background:rgba(251,113,133,.12);color:var(--red);}

/* ── Recent log ── */
.recent-row{
    display:flex;align-items:center;gap:10px;
    padding:9px 0;border-bottom:1px solid var(--border);
    font-size:12px;
}
.recent-row:last-child{border-bottom:none;}
.rr-ip{font-family:'Space Mono',monospace;font-size:11px;color:var(--muted);width:120px;flex-shrink:0;}
.rr-flag{width:100px;text-align:left;flex-shrink:0;color:var(--muted);}
.rr-url{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text);margin:0 15px;}
.rr-browser{color:var(--muted);white-space:nowrap;font-size:11px;width:80px;text-align:right;}
.rr-time{color:var(--muted);font-size:11px;white-space:nowrap;font-family:'Space Mono',monospace;width:150px;text-align:right;margin-left:15px;}

/* ── Scrollable ── */
.scroll-box{max-height:320px;overflow-y:auto;}
.scroll-box::-webkit-scrollbar{width:3px;}
.scroll-box::-webkit-scrollbar-thumb{background:var(--surface3);border-radius:2px;}

/* ── Chart legend ── */
.chart-wrap{position:relative;}
/* ── Chart Filter ── */
.chart-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;}
.range-selector{display:flex;background:var(--surface2);padding:3px;border-radius:10px;gap:2px;}
.range-btn{
    padding:6px 12px;font-size:11px;font-weight:600;color:var(--muted);
    text-decoration:none;border-radius:8px;transition:all .2s;
}
.range-btn:hover{color:var(--text);}
.range-btn.active{background:var(--surface3);color:var(--blue);box-shadow:0 2px 8px rgba(0,0,0,0.2);}
</style>
</head>
<body>
<div class="wrap">

<!-- ── HEADER ── -->
<div class="header">
    <div class="header-left">
        <div class="tag">⬡ Admin — Analytics</div>
        <h1>Phân tích · <span class="domain">gtlmanh.id.vn</span></h1>
    </div>
    <div style="display:flex;align-items:center;gap:12px;">
        <span class="live-badge"><span class="live-dot"></span>Live Data</span>
        <a href="admin_analytics1.php" class="btn btn-ghost" style="border-color: var(--amber); color: var(--amber);">
            <i class="fa fa-coins"></i> Revenue Breakdown
        </a>
        <a href="admin_dashboard.php" class="btn btn-ghost"><i class="fa fa-chevron-left"></i> Dashboard</a>
    </div>
</div>

<!-- ── METRIC CARDS ── -->
<div class="metrics-grid">
    <?php
    $cards = [
        ['c1','fa-bolt','Yêu cầu',$total_req,$req_today,$req_yday,$req_chg,'blue'],
        ['c2','fa-users','Khách truy cập',$total_vis,$vis_today,$vis_yday,$vis_chg,'green'],
        ['c3','fa-eye','Lượt xem trang',$total_pv,$pv_today,$pv_yday,$pv_chg,'amber'],
        ['c4','fa-code','Yêu cầu API',$total_api,$api_today,$api_yday,$api_chg,'purple'],
    ];
    foreach ($cards as [$cls,$icon,$label,$total,$today_v,$yday_v,$chg,$color]):
        $dir = $chg > 0 ? 'up' : ($chg < 0 ? 'down' : 'stable');
        $arrow = $chg >= 0 ? 'arrow-up' : 'arrow-down';
    ?>
    <div class="m-card <?= $cls ?>">
        <div class="m-card-icon"><i class="fa <?= $icon ?>"></i></div>
        <div class="m-card-label"><?= $label ?></div>
        <div class="m-card-val"><?= number_format($total) ?></div>
        <div class="m-card-sub <?= $dir ?>">
            <i class="fa fa-<?= $arrow ?>" style="font-size:10px"></i>
            <?= number_format(abs($chg), 1) ?>% so hôm qua
        </div>
        <div class="m-card-today">Hôm nay: <strong><?= number_format($today_v) ?></strong> · Hôm qua: <?= number_format($yday_v) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── TREND CHART ── -->
<div class="chart-main">
    <div class="chart-header">
        <div class="section-title" style="margin-bottom:0;"><span class="dot dot-blue"></span>Tổng quan yêu cầu</div>
        <div class="range-selector">
            <a href="?range=24h" class="range-btn <?= $range=='24h'?'active':'' ?>">24h</a>
            <a href="?range=7d" class="range-btn <?= $range=='7d'?'active':'' ?>">7 Ngày</a>
            <a href="?range=30d" class="range-btn <?= $range=='30d'?'active':'' ?>">30 Ngày</a>
            <a href="?range=1y" class="range-btn <?= $range=='1y'?'active':'' ?>">1 Năm</a>
            <a href="?range=all" class="range-btn <?= $range=='all'?'active':'' ?>">Tất cả</a>
        </div>
    </div>
    <canvas id="trendChart" height="70"></canvas>
</div>

<!-- ── GROWTH COMPARISON CHART ── -->
<div class="chart-main">
    <div class="section-title"><span class="dot dot-green"></span>So sánh tăng trưởng theo giai đoạn</div>
    <canvas id="compChart" height="70"></canvas>
</div>

<!-- ── COUNTRIES + TOP PAGES ── -->
<div class="g2">
    <div class="card">
        <div class="section-title"><span class="dot dot-cyan"></span>Lượt truy cập theo quốc gia</div>
        <div class="scroll-box">
        <table class="tbl">
            <thead><tr><th style="width:32px">#</th><th>Quốc gia</th><th>Lượt</th><th style="width:30%"></th></tr></thead>
            <tbody>
            <?php $maxC = $countries[0]['cnt'] ?? 1; foreach ($countries as $i => $c): ?>
            <tr>
                <td><span class="rank rank-<?= $i+1 ?>"><?= $i+1 ?></span></td>
                <td><?= htmlspecialchars($c['country'] ?: 'Unknown') ?></td>
                <td class="num"><?= number_format($c['cnt']) ?></td>
                <td><div class="bar"><div class="bar-fill bar-blue" style="width:<?= ($c['cnt']/$maxC)*100 ?>%"></div></div></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="card">
        <div class="section-title"><span class="dot dot-amber"></span>Đường dẫn phổ biến</div>
        <div class="scroll-box">
        <table class="tbl">
            <thead><tr><th style="width:32px">#</th><th>Đường dẫn</th><th>Lượt</th></tr></thead>
            <tbody>
            <?php foreach ($top_pages as $i => $p): ?>
            <tr>
                <td><span class="rank rank-<?= $i+1 ?>"><?= $i+1 ?></span></td>
                <td class="url-cell" title="<?= htmlspecialchars($p['page_url']) ?>"><?= htmlspecialchars($p['page_url']) ?></td>
                <td class="num"><?= number_format($p['cnt']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- ── SOURCES + STATUS ── -->
<div class="g2">
    <div class="card">
        <div class="section-title"><span class="dot dot-green"></span>Nguồn truy cập</div>
        <?php $maxS = $sources[0]['cnt'] ?? 1; foreach ($sources as $i => $s): ?>
        <div style="margin-bottom:12px;">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                <span style="display:flex;align-items:center;gap:6px;">
                    <span class="rank rank-<?= $i+1 ?>"><?= $i+1 ?></span>
                    <?= htmlspecialchars($s['source'] ?: 'Direct') ?>
                </span>
                <span style="font-family:'Space Mono',monospace;font-size:12px;font-weight:700"><?= number_format($s['cnt']) ?></span>
            </div>
            <div class="bar"><div class="bar-fill bar-green" style="width:<?= ($s['cnt']/$maxS)*100 ?>%"></div></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="section-title"><span class="dot dot-red"></span>Mã trạng thái HTTP</div>
        <?php $maxSt = $statuses[0]['cnt'] ?? 1; foreach ($statuses as $s):
            $sc = $s['status_code'];
            $bc = $sc == 200 ? 'bar-green' : (in_array($sc,[301,302]) ? 'bar-blue' : ($sc==404 ? 'bar-amber' : 'bar-red'));
        ?>
        <div style="margin-bottom:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                <span class="status-badge s<?= $sc ?>"><?= $sc ?></span>
                <span style="font-family:'Space Mono',monospace;font-size:12px;font-weight:700"><?= number_format($s['cnt']) ?></span>
            </div>
            <div class="bar"><div class="bar-fill <?= $bc ?>" style="width:<?= ($s['cnt']/$maxSt)*100 ?>%"></div></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── OS + BROWSER + DEVICE ── -->
<div class="g3">
    <div class="card">
        <div class="section-title"><span class="dot dot-purple"></span>Hệ điều hành</div>
        <canvas id="osChart" height="200"></canvas>
        <div style="margin-top:14px;">
        <?php $maxOs = $os_dist[0]['cnt'] ?? 1; foreach ($os_dist as $o): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;margin-bottom:8px;">
            <span style="color:var(--muted)"><?= htmlspecialchars($o['os'] ?: 'Unknown') ?></span>
            <span style="font-family:'Space Mono',monospace;font-weight:700;font-size:11px"><?= number_format($o['cnt']) ?></span>
        </div>
        <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="section-title"><span class="dot dot-blue"></span>Trình duyệt</div>
        <canvas id="browserChart" height="200"></canvas>
        <div style="margin-top:14px;">
        <?php $maxBr = $browser_dist[0]['cnt'] ?? 1; foreach ($browser_dist as $b): ?>
        <div style="margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;">
                <span><?= htmlspecialchars($b['browser'] ?: 'Unknown') ?></span>
                <span style="font-family:'Space Mono',monospace;font-size:11px;font-weight:700"><?= number_format($b['cnt']) ?></span>
            </div>
            <div class="bar"><div class="bar-fill bar-blue" style="width:<?= ($b['cnt']/$maxBr)*100 ?>%"></div></div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="section-title"><span class="dot dot-amber"></span>Thiết bị</div>
        <?php if (!empty($dev_labels)): ?>
        <canvas id="deviceChart" height="200"></canvas>
        <?php endif; ?>
        <div style="margin-top:14px;">
        <?php $maxDv = $devices[0]['cnt'] ?? 1; foreach ($devices as $dv): ?>
        <div style="margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;">
                <span><?= htmlspecialchars($dv['device'] ?: 'Unknown') ?></span>
                <span style="font-family:'Space Mono',monospace;font-size:11px;font-weight:700"><?= number_format($dv['cnt']) ?></span>
            </div>
            <div class="bar"><div class="bar-fill bar-amber" style="width:<?= ($dv['cnt']/$maxDv)*100 ?>%"></div></div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── RECENT REQUESTS ── -->
<div class="card" style="margin-bottom:24px;">
    <div class="section-title"><span class="dot dot-cyan"></span>Yêu cầu gần đây</div>
    <div class="scroll-box">
    <?php foreach ($recent as $r): ?>
    <div class="recent-row">
        <span class="rr-flag"><?= htmlspecialchars($r['country'] ?? '—') ?></span>
        <span class="rr-url"><?= htmlspecialchars($r['page_url'] ?? '') ?></span>
        <span class="rr-browser"><?= htmlspecialchars($r['browser'] ?? '') ?></span>
        <span class="rr-time"><?= htmlspecialchars($r['visited_at'] ?? '') ?></span>
    </div>
    <?php endforeach; ?>
    </div>
</div>

</div><!-- /wrap -->

<script>
Chart.defaults.color = '#636b80';
Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
Chart.defaults.font.family = "'DM Sans', sans-serif";

// ── Trend line chart ──
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($trend_labels) ?>,
        datasets: [
            {
                label: 'Yêu cầu',
                data: <?= json_encode($trend_req) ?>,
                borderColor: '#4f8dff',
                backgroundColor: 'rgba(79,141,255,0.08)',
                borderWidth: 2,
                pointBackgroundColor: '#4f8dff',
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.4,
                fill: true
            },
            {
                label: 'Khách truy cập',
                data: <?= json_encode($trend_vis) ?>,
                borderColor: '#34d399',
                backgroundColor: 'rgba(52,211,153,0.06)',
                borderWidth: 2,
                pointBackgroundColor: '#34d399',
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.4,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { labels: { usePointStyle: true, pointStyle: 'circle', padding: 20, font: { size: 12 } } },
            tooltip: {
                backgroundColor: '#141825',
                borderColor: 'rgba(255,255,255,0.1)',
                borderWidth: 1,
                padding: 12,
                titleFont: { size: 12 },
                bodyFont: { size: 12 }
            }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { font: { size: 11 } } },
            x: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
    }
});

// ── OS donut ──
const osColors = ['#a78bfa','#4f8dff','#22d3ee','#34d399','#fbbf24','#fb923c','#fb7185','#f472b6'];
new Chart(document.getElementById('osChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($os_labels) ?>,
        datasets: [{ data: <?= json_encode($os_vals) ?>, backgroundColor: osColors, borderWidth: 2, borderColor: '#0e111a', hoverOffset: 4 }]
    },
    options: {
        plugins: { legend: { display: false } },
        cutout: '65%'
    }
});

// ── Browser bar ──
new Chart(document.getElementById('browserChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($browser_dist, 'browser')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($browser_dist, 'cnt')) ?>,
            backgroundColor: 'rgba(79,141,255,0.5)',
            borderColor: '#4f8dff',
            borderWidth: 1,
            borderRadius: 5
        }]
    },
    options: {
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: 'rgba(255,255,255,0.04)' }, beginAtZero: true, ticks: { font: { size: 10 } } },
            y: { grid: { display: false }, ticks: { font: { size: 10 } } }
        }
    }
});

// ── Device pie ──
<?php if (!empty($dev_labels)): ?>
const devColors = ['#fbbf24','#4f8dff','#34d399','#a78bfa','#fb7185','#22d3ee','#fb923c','#f472b6'];
new Chart(document.getElementById('deviceChart'), {
    type: 'pie',
    data: {
        labels: <?= json_encode($dev_labels) ?>,
        datasets: [{ data: <?= json_encode($dev_vals) ?>, backgroundColor: devColors, borderWidth: 2, borderColor: '#0e111a', hoverOffset: 4 }]
    },
    options: { plugins: { legend: { display: false } } }
});
<?php endif; ?>

// ── Growth Comparison Bar Chart ──
new Chart(document.getElementById('compChart'), {
    type: 'bar',
    data: {
        labels: ['24 Giờ', '7 Ngày', '30 Ngày', '1 Năm', 'Tất cả'],
        datasets: [
            {
                label: 'Tổng Yêu cầu',
                data: <?= json_encode($comp_req) ?>,
                backgroundColor: 'rgba(79,141,255,0.6)',
                borderColor: '#4f8dff',
                borderWidth: 1,
                borderRadius: 4
            },
            {
                label: 'Khách truy cập',
                data: <?= json_encode($comp_vis) ?>,
                backgroundColor: 'rgba(52,211,153,0.6)',
                borderColor: '#34d399',
                borderWidth: 1,
                borderRadius: 4
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { labels: { usePointStyle: true, pointStyle: 'circle', padding: 20 } },
            tooltip: { padding: 12 }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' } },
            x: { grid: { display: false } }
        }
    }
});
</script>
</body>
</html>