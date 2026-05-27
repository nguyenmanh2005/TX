<?php
session_start();
require_once 'db_connect.php';
require_once 'admin_helper.php';

$currentUserId = (int)($_SESSION['Iduser'] ?? 0);
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php?error=no_session");
    exit;
}

if (!isAdmin($conn, $currentUserId)) { header("Location: 403.php"); exit(); }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// --- Handle Form Actions (Experiments) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF Token verification failed.");
    }
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_experiment') {
        $name = $conn->real_escape_string($_POST['name'] ?? '');
        $type = $conn->real_escape_string($_POST['type'] ?? '');
        $status = $conn->real_escape_string($_POST['status'] ?? 'paused');
        $percent = (int)($_POST['user_percent'] ?? 10);
        
        if (!empty($name) && !empty($type)) {
            $conn->query("INSERT INTO experiments (name, type, status, user_percent, created_at) VALUES ('{$name}', '{$type}', '{$status}', {$percent}, NOW())");
            header("Location: admin_advanced_center.php");
            exit;
        }
    }
    
    if ($action === 'toggle_experiment') {
        $expId = (int)($_POST['id'] ?? 0);
        $currentStatus = $_POST['status'] ?? '';
        $newStatus = ($currentStatus === 'active') ? 'paused' : 'active';
        
        $conn->query("UPDATE experiments SET status = '{$newStatus}' WHERE id = {$expId}");
        header("Location: admin_advanced_center.php");
        exit;
    }
    
    if ($action === 'delete_experiment') {
        $expId = (int)($_POST['id'] ?? 0);
        $conn->query("DELETE FROM experiments WHERE id = {$expId}");
        header("Location: admin_advanced_center.php");
        exit;
    }
}

// --- Fetch Data ---

// 1. Alerts
$alerts = [];
$res = $conn->query("SELECT * FROM admin_alerts ORDER BY created_at DESC LIMIT 20");
if ($res) while ($r = $res->fetch_assoc()) $alerts[] = $r;

// 2. Experiments
$experiments = [];
$res = $conn->query("SELECT * FROM experiments ORDER BY created_at DESC");
if ($res) while ($r = $res->fetch_assoc()) $experiments[] = $r;

// 2b. Fetch distinct types from database
$existingTypes = [];
$resTypes = $conn->query("SELECT DISTINCT type FROM experiments");
if ($resTypes) {
    while ($rt = $resTypes->fetch_assoc()) {
        if (!empty($rt['type'])) $existingTypes[] = $rt['type'];
    }
}

// 2c. Fetch all game names from games/ folder dynamically
$gameNames = [];
$gameFiles = glob('games/*.php');
if ($gameFiles) {
    foreach ($gameFiles as $gf) {
        $name = pathinfo($gf, PATHINFO_FILENAME);
        // Bỏ qua các file process/widget phụ trợ
        if (!in_array($name, ['blackjack_process', 'baccarat_process', 'daily_challenge_widget', 'slot-sounds'])) {
            $gameNames[] = $name;
        }
    }
}
sort($gameNames);

$defaultTypes = ['bet_limit', 'ui_theme', 'economy_buff', 'game_rule', 'bot_behavior'];
$allExperimentTypes = array_unique(array_merge($defaultTypes, $existingTypes, $gameNames));

// 3. Bot Health (Aggregated from bot/sessions/*.state.json)
$botStates = glob('bot/sessions/*.state.json');
$botStats = ['total' => 0, 'games' => []];
foreach ($botStates as $file) {
    $state = json_decode(file_get_contents($file), true);
    $botStats['total']++;
    $game = $state['history'][0]['game'] ?? 'Idle';
    $botStats['games'][$game] = ($botStats['games'][$game] ?? 0) + 1;
}

// 4. Economy Data
$ecoHistory = file_exists('bot/sessions/economy_history.json') ? json_decode(file_get_contents('bot/sessions/economy_history.json'), true) : [];
$latestEco = end($ecoHistory);

// Calculate Inflation (Pseudo-logic based on user money change)
$inflationRate = 0;
if (count($ecoHistory) > 1) {
    $prev = $ecoHistory[count($ecoHistory)-2];
    $totalPrev = $prev['human'] + $prev['bot'];
    $totalCurr = $latestEco['human'] + $latestEco['bot'];
    if ($totalPrev > 0) $inflationRate = (($totalCurr - $totalPrev) / $totalPrev) * 100;
}

// 5. Centralized System Logs & Economy Transaction Logs
$errTodayRes = $conn->query("SELECT COUNT(*) as count FROM app_logs WHERE log_level IN ('ERROR', 'CRITICAL') AND created_at >= NOW() - INTERVAL 1 DAY");
$errorsToday = $errTodayRes ? (int)$errTodayRes->fetch_assoc()['count'] : 0;

$ecoTxTodayRes = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(ABS(amount)), 0) as volume FROM economy_transaction_logs WHERE created_at >= NOW() - INTERVAL 1 DAY");
$ecoTxData = $ecoTxTodayRes ? $ecoTxTodayRes->fetch_assoc() : ['count' => 0, 'volume' => 0];

$systemLogs = [];
$resLogs = $conn->query("SELECT l.*, u.Name as actor_name FROM app_logs l LEFT JOIN users u ON l.user_id = u.Iduser ORDER BY l.id DESC LIMIT 100");
if ($resLogs) while ($r = $resLogs->fetch_assoc()) $systemLogs[] = $r;

$economyLogs = [];
$resEco = $conn->query("SELECT * FROM economy_transaction_logs ORDER BY id DESC LIMIT 100");
if ($resEco) while ($r = $resEco->fetch_assoc()) $economyLogs[] = $r;


?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Advanced Center — Control & Analytics</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #030712;
            --surface: #0f172a;
            --surface-hover: #1e293b;
            --border: rgba(255,255,255,0.06);
            --text: #f8fafc;
            --muted: #94a3b8;
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.3);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #0ea5e9;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            position: relative;
        }

        /* noise overlay */
        body::before {
            content: '';
            position: fixed; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
            opacity: 0.4; pointer-events: none; z-index: 0;
        }
        
        /* Glow blobs */
        body::after {
            content: '';
            position: fixed;
            top: -200px; left: 100px;
            width: 800px; height: 800px;
            background: radial-gradient(circle, rgba(99,102,241,0.08) 0%, transparent 60%);
            pointer-events: none; z-index: 0;
        }

        .sidebar, .main, .modal { position: relative; z-index: 1; }

        /* --- Sidebar --- */
        .sidebar {
            width: 280px;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-right: 1px solid var(--border);
            padding: 32px 24px;
            display: flex;
            flex-direction: column;
            gap: 32px;
            position: fixed;
            height: 100vh;
        }

        .logo {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-list { list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .nav-item {
            padding: 12px 16px;
            border-radius: 12px;
            color: var(--muted);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
        }
        .nav-item:hover, .nav-item.active {
            background: var(--primary-glow);
            color: var(--text);
        }

        /* --- Main Content --- */
        .main {
            margin-left: 280px;
            flex: 1;
            padding: 40px;
            max-width: 1500px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .header-title h1 { font-size: 28px; letter-spacing: -0.5px; }
        .header-title p { color: var(--muted); font-size: 14px; margin-top: 4px; }

        /* --- Grid Layout --- */
        .grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 24px;
        }

        .card {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 28px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            transition: transform 0.2s, border-color 0.2s;
        }
        .card:hover {
            border-color: rgba(255,255,255,0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-title { font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px; }

        /* --- Tabs Content --- */
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease-out; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* --- Stats Card --- */
        .stats-grid { grid-column: span 12; display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px; }
        .stat-box {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.7) 0%, rgba(15, 23, 42, 0.7) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            padding: 24px;
            border-radius: 20px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stat-box:hover {
            transform: translateY(-4px);
            border-color: rgba(99, 102, 241, 0.4);
            box-shadow: 0 12px 40px rgba(99, 102, 241, 0.1);
        }
        .stat-box::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--info));
            border-radius: 20px 20px 0 0;
            opacity: 0.5;
        }
        .stat-label { font-size: 13px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600; margin-bottom: 12px; }
        .stat-value { font-size: 32px; font-weight: 800; font-family: 'JetBrains Mono', monospace; line-height: 1; margin-bottom: 8px; text-shadow: 0 2px 10px rgba(255,255,255,0.1); }
        .stat-trend { font-size: 12px; margin-top: 4px; }
        .stat-trend.up { color: var(--success); }
        .stat-trend.down { color: var(--danger); }

        /* --- Alert Feed --- */
        .alert-item {
            display: flex;
            gap: 16px;
            padding: 16px;
            border-bottom: 1px solid var(--border);
            transition: background 0.2s;
        }
        .alert-item:hover { background: rgba(255,255,255,0.02); }
        .alert-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .alert-icon.critical { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .alert-icon.warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .alert-body { flex: 1; }
        .alert-title { font-weight: 600; font-size: 14px; margin-bottom: 4px; }
        .alert-desc { color: var(--muted); font-size: 13px; line-height: 1.4; }
        .alert-time { font-size: 11px; color: var(--muted); margin-top: 8px; }

        /* --- Table Styling --- */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 12px; color: var(--muted); font-size: 12px; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 16px 12px; font-size: 14px; border-bottom: 1px solid var(--border); }
        tr:last-child td { border-bottom: none; }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-active { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .badge-paused { background: rgba(245, 158, 11, 0.1); color: var(--warning); }

        /* --- Bot Health --- */
        .bot-dist-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .bot-bar-wrap { flex: 1; margin: 0 16px; background: rgba(255,255,255,0.05); height: 6px; border-radius: 3px; overflow: hidden; }
        .bot-bar { height: 100%; background: var(--primary); border-radius: 3px; }

        /* --- Economy Slider --- */
        .sim-input {
            margin-bottom: 24px;
        }
        .sim-label { font-size: 14px; margin-bottom: 8px; display: block; }
        input[type="range"] { width: 100%; height: 6px; background: var(--surface-hover); border-radius: 3px; appearance: none; cursor: pointer; }
        input[type="range"]::-webkit-slider-thumb { appearance: none; width: 18px; height: 18px; background: var(--primary); border-radius: 50%; }

        .btn {
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px var(--primary-glow); }

        /* --- Logs Dashboard --- */
        .logs-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }
        .log-badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; }
        .log-DEBUG { background: rgba(148, 163, 184, 0.1); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.2); }
        .log-INFO { background: rgba(14, 165, 233, 0.1); color: #38bdf8; border: 1px solid rgba(14, 165, 233, 0.2); }
        .log-WARNING { background: rgba(245, 158, 11, 0.1); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.2); }
        .log-ERROR { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
        .log-CRITICAL { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.4); box-shadow: 0 0 10px rgba(239, 68, 68, 0.2); animation: pulseAlert 2s infinite; }
        @keyframes pulseAlert { 0% { opacity: 0.8; } 50% { opacity: 1; } 100% { opacity: 0.8; } }
        
        .amount-positive { color: var(--success); font-weight: 600; }
        .amount-negative { color: var(--danger); font-weight: 600; }
        
        .code-block { font-family: 'JetBrains Mono', monospace; background: #020617; border: 1px solid rgba(255,255,255,0.04); border-radius: 8px; padding: 12px; font-size: 12px; color: #cbd5e1; overflow-x: auto; max-height: 250px; }
        
        /* Modal Style */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(10px); }
        .modal-content { background: var(--surface); border: 1px solid var(--border); width: 90%; max-width: 700px; border-radius: 20px; padding: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); position: relative; animation: modalEnter 0.3s ease-out; }
        @keyframes modalEnter { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .modal-close { position: absolute; top: 20px; right: 20px; font-size: 20px; color: var(--muted); cursor: pointer; transition: color 0.2s; }
        .modal-close:hover { color: var(--text); }
        .modal-header { font-size: 18px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid var(--border); padding-bottom: 12px; }
        
        /* Search/Filters Layout */
        .log-filters { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .log-select { background: var(--surface-hover); border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px; color: var(--text); font-size: 13px; font-weight: 500; outline: none; cursor: pointer; }
        .log-search { flex: 1; min-width: 200px; background: var(--surface-hover); border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px; color: var(--text); font-size: 13px; outline: none; }
        
        .scrollable-feed { max-height: 500px; overflow-y: auto; padding-right: 4px; }
        .scrollable-feed::-webkit-scrollbar { width: 6px; }
        .scrollable-feed::-webkit-scrollbar-track { background: transparent; }
        .scrollable-feed::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 3px; }
        .scrollable-feed::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.15); }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="logo">
            <i class="fa-solid fa-shield-halved"></i>
            ADMIN CORE
        </div>
        <nav class="nav-list">
            <div class="nav-item active" data-tab="dashboard"><i class="fa-solid fa-chart-line"></i> Dashboard</div>
            <div class="nav-item" data-tab="alerts"><i class="fa-solid fa-bell"></i> Alerts & Fraud</div>
            <div class="nav-item" data-tab="ab-test"><i class="fa-solid fa-flask"></i> A/B Testing</div>
            <div class="nav-item" data-tab="bots"><i class="fa-solid fa-robot"></i> Bot Ecosystem</div>
            <div class="nav-item" data-tab="economy"><i class="fa-solid fa-coins"></i> Economy Simulator</div>
            <div class="nav-item" data-tab="system-logs"><i class="fa-solid fa-server"></i> Centralized Logs</div>
        </nav>
        <div style="margin-top: auto; display:flex; flex-direction:column; gap:8px;">
            <a href="oracle_prophecy.php" class="nav-item" style="color:#a78bfa; border:1px solid rgba(167,139,250,.2); border-radius:12px;" target="_blank">
                <i class="fa-solid fa-eye"></i> 🔮 Lời Tiên Tri
            </a>
            <a href="api_oracle_prophecy.php?action=admin_generate" class="nav-item" style="color:#f0c060; border:1px solid rgba(240,192,96,.2); border-radius:12px;" onclick="return confirm('Tạo lời tiên tri cho tuần này?')">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Tạo Tiên Tri
            </a>
            <a href="admin_dashboard.php" class="nav-item"><i class="fa-solid fa-arrow-left"></i> Quay lại</a>
        </div>
    </aside>

    <main class="main">
        <header>
            <div class="header-title">
                <h1>Quản trị Master Trận Địa</h1>
                <p>Điều khiển hệ thống, chẩn đoán thời gian thực và mô phỏng kinh tế GTLM.</p>
            </div>
            <div class="live-status">
                <span style="display:inline-flex; align-items:center; gap:8px; background:rgba(16,185,129,0.1); color:var(--success); padding:8px 16px; border-radius:20px; font-size:13px; font-weight:600;">
                    <span style="width:8px; height:8px; background:var(--success); border-radius:50%; box-shadow: 0 0 10px var(--success);"></span>
                    System Live
                </span>
            </div>
        </header>

        <!-- DASHBOARD TAB -->
        <div id="dashboard" class="tab-content active">
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-label">Tổng GTLM lưu thông</div>
                    <div class="stat-value"><?= number_format($latestEco['human'] + $latestEco['bot']) ?> GTLM</div>
                    <div class="stat-trend <?= $inflationRate > 0 ? 'up' : 'down' ?>">
                        <i class="fa-solid fa-arrow-<?= $inflationRate > 0 ? 'up' : 'down' ?>"></i> 
                        Tỉ lệ lạm phát: <?= number_format(abs($inflationRate), 2) ?>%
                    </div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Active Experiments</div>
                    <div class="stat-value"><?= count(array_filter($experiments, fn($e) => $e['status'] == 'active')) ?></div>
                    <div class="stat-trend">Monitoring A/B segments</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Bot Population</div>
                    <div class="stat-value"><?= $botStats['total'] ?></div>
                    <div class="stat-trend">Distributed across <?= count($botStats['games']) ?> games</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">System Health</div>
                    <div class="stat-value" style="color:var(--success)">STABLE</div>
                    <div class="stat-trend">0 critical issues in last 1h</div>
                </div>
            </div>

            <div class="grid">
                <div class="card" style="grid-column: span 8;">
                    <div class="card-header">
                        <div class="card-title">Cân bằng kinh tế (Bot vs Người chơi)</div>
                    </div>
                    <canvas id="ecoChart" height="200"></canvas>
                </div>
                <div class="card" style="grid-column: span 4;">
                    <div class="card-header">
                        <div class="card-title">Phân bổ Bot</div>
                    </div>
                    <canvas id="botChart" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- ALERTS TAB -->
        <div id="alerts" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-shield-virus"></i> Real-time Security Alerts</div>
                    <button class="btn btn-primary" onclick="generateMockAlert()"><i class="fa-solid fa-plus"></i> Simulate Alert</button>
                </div>
                <div id="alert-feed">
                    <?php if (empty($alerts)): ?>
                        <div style="text-align:center; padding:40px; color:var(--muted);">
                            <i class="fa-solid fa-check-circle" style="font-size:48px; margin-bottom:16px; color:var(--success)"></i>
                            <p>All quiet on the western front. No alerts detected.</p>
                        </div>
                    <?php else: foreach ($alerts as $a): ?>
                        <div class="alert-item">
                            <div class="alert-icon <?= $a['severity'] ?>">
                                <i class="fa-solid fa-<?= $a['type'] == 'big_win' ? 'trophy' : ($a['type'] == 'fraud' ? 'user-secret' : 'bolt') ?>"></i>
                            </div>
                            <div class="alert-body">
                                <div class="alert-title"><?= strtoupper($a['type']) ?> detected</div>
                                <div class="alert-desc"><?= htmlspecialchars($a['message']) ?></div>
                                <div class="alert-time"><?= $a['created_at'] ?></div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <!-- A/B TESTING TAB -->
        <div id="ab-test" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Experiments Framework</div>
                    <button class="btn btn-primary" onclick="openNewExperimentModal()"><i class="fa-solid fa-plus"></i> New Experiment</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Experiment Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Segments</th>
                            <th>Result (A/B)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($experiments)): ?>
                            <tr><td colspan="6" style="text-align:center; color:var(--muted)">No experiments running yet.</td></tr>
                        <?php else: foreach ($experiments as $e): ?>
                            <tr>
                                <td><b><?= htmlspecialchars($e['name']) ?></b></td>
                                <td><?= htmlspecialchars($e['type']) ?></td>
                                <td><span class="badge badge-<?= htmlspecialchars($e['status']) ?>"><?= htmlspecialchars($e['status']) ?></span></td>
                                <td><?= (int)$e['user_percent'] ?>% traffic</td>
                                <td>
                                    <span style="color:var(--muted)">Pending collection...</span>
                                </td>
                                <td style="display:flex; gap: 8px;">
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="toggle_experiment">
                                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                        <input type="hidden" name="status" value="<?= htmlspecialchars($e['status']) ?>">
                                        <button class="btn btn-sm" type="submit" title="Play/Pause Experiment" style="padding: 6px 10px; background: var(--surface-hover); color: var(--text);">
                                            <i class="fa-solid fa-<?= $e['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Bạn có chắc muốn xóa thử nghiệm này?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="delete_experiment">
                                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                        <button class="btn btn-sm" type="submit" title="Delete Experiment" style="padding: 6px 10px; background: rgba(239, 68, 68, 0.1); color: var(--danger);">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- BOTS TAB -->
        <div id="bots" class="tab-content">
            <div class="grid">
                <div class="card" style="grid-column: span 6;">
                    <div class="card-header">
                        <div class="card-title">Live Bot Activity</div>
                    </div>
                    <div id="bot-live-list">
                        <?php foreach ($botStats['games'] as $game => $count): ?>
                        <div class="bot-dist-item">
                            <span style="width:120px"><?= $game ?></span>
                            <div class="bot-bar-wrap">
                                <div class="bot-bar" style="width: <?= ($count/$botStats['total'])*100 ?>%"></div>
                            </div>
                            <span style="width:40px; text-align:right"><?= $count ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="card" style="grid-column: span 6;">
                    <div class="card-header">
                        <div class="card-title">Bot Mood Distribution</div>
                    </div>
                    <canvas id="moodChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <!-- ECONOMY TAB -->
        <div id="economy" class="tab-content">
            <div class="grid">
                <div class="card" style="grid-column: span 4;">
                    <div class="card-header">
                        <div class="card-title">Economy Simulator</div>
                    </div>
                    <div class="sim-input">
                        <label class="sim-label">New Event GTLM Inflow: <span id="val-inflow">1B</span></label>
                        <input type="range" min="0" max="10000" value="1000" id="in-inflow">
                    </div>
                    <div class="sim-input">
                        <label class="sim-label">Bet House Edge: <span id="val-edge">2.5%</span></label>
                        <input type="range" min="0" max="10" step="0.1" value="2.5" id="in-edge">
                    </div>
                    <div class="sim-input">
                        <label class="sim-label">Daily Burn Rate: <span id="val-burn">500M</span></label>
                        <input type="range" min="0" max="5000" value="500" id="in-burn">
                    </div>
                    <div style="background:rgba(255,255,255,0.03); padding:16px; border-radius:12px;">
                        <div style="font-size:12px; color:var(--muted); margin-bottom:4px;">Predicted Inflation Next Month</div>
                        <div id="sim-result" style="font-size:24px; font-weight:700; color:var(--success)">+1.24%</div>
                    </div>
                </div>
                <div class="card" style="grid-column: span 8;">
                    <div class="card-header">
                        <div class="card-title">Long-term Inflation Trend</div>
                    </div>
                    <canvas id="inflationChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <!-- SYSTEM LOGS TAB -->
        <div id="system-logs" class="tab-content">
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-label">Critical Alerts (24h)</div>
                    <div class="stat-value" style="color: <?= $errorsToday > 0 ? 'var(--danger)' : 'var(--success)' ?>"><?= $errorsToday ?></div>
                    <div class="stat-trend">Unresolved severe errors</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Total System Logs Today</div>
                    <div class="stat-value">
                        <?php
                        $totalLogsRes = $conn->query("SELECT COUNT(*) as count FROM app_logs WHERE created_at >= NOW() - INTERVAL 1 DAY");
                        echo $totalLogsRes ? number_format($totalLogsRes->fetch_assoc()['count']) : 0;
                        ?>
                    </div>
                    <div class="stat-trend">Monitoring health status</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Economy Transactions (24h)</div>
                    <div class="stat-value"><?= number_format($ecoTxData['count']) ?></div>
                    <div class="stat-trend">Audited balance adjustments</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Transacted Economy Volume</div>
                    <div class="stat-value"><?= number_format($ecoTxData['volume']) ?> GTLM</div>
                    <div class="stat-trend">Total currency flow</div>
                </div>
            </div>

            <div class="logs-grid">
                <!-- Column 1: App Logs -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-terminal"></i> Centralized System Logs</div>
                    </div>
                    
                    <div class="log-filters">
                        <input type="text" id="app-log-search" class="log-search" placeholder="Search message or category...">
                        <select id="app-log-level" class="log-select">
                            <option value="">All Levels</option>
                            <option value="DEBUG">DEBUG</option>
                            <option value="INFO">INFO</option>
                            <option value="WARNING">WARNING</option>
                            <option value="ERROR">ERROR</option>
                            <option value="CRITICAL">CRITICAL</option>
                        </select>
                    </div>

                    <div class="scrollable-feed" id="app-logs-list">
                        <?php if (empty($systemLogs)): ?>
                            <div style="text-align:center; padding:40px; color:var(--muted)">No system logs recorded yet.</div>
                        <?php else: foreach ($systemLogs as $log): ?>
                            <div class="alert-item app-log-item" data-level="<?= $log['log_level'] ?>" data-search="<?= strtolower(htmlspecialchars($log['category'] . ' ' . $log['message'])) ?>">
                                <div class="alert-body">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 6px;">
                                        <span class="log-badge log-<?= $log['log_level'] ?>"><?= $log['log_level'] ?></span>
                                        <span class="badge" style="background:rgba(255,255,255,0.04); font-family: monospace; font-size:11px;"><?= htmlspecialchars($log['category']) ?></span>
                                    </div>
                                    <div class="alert-title" style="margin-top:4px; font-weight: 500; font-size: 13px;"><?= htmlspecialchars($log['message']) ?></div>
                                    
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                                        <div class="alert-time"><i class="fa-regular fa-clock"></i> <?= $log['created_at'] ?></div>
                                        <div>
                                            <?php if ($log['details']): ?>
                                                <button class="btn btn-primary" style="padding: 4px 8px; font-size: 11px; border-radius: 6px;" onclick="showLogDetails(<?= htmlspecialchars(json_encode($log)) ?>)">
                                                    <i class="fa-solid fa-circle-info"></i> Details
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <!-- Column 2: Economy Auditing -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-coins"></i> Economy Transaction Audit</div>
                    </div>
                    
                    <div class="log-filters">
                        <input type="text" id="eco-log-search" class="log-search" placeholder="Search user or transaction...">
                        <select id="eco-log-type" class="log-select">
                            <option value="">All Types</option>
                            <option value="BALANCE_CHANGE">BALANCE_CHANGE</option>
                            <option value="BET">BET</option>
                            <option value="WIN">WIN</option>
                            <option value="CRAFTING">CRAFTING</option>
                            <option value="ADMIN_GIVE">ADMIN_GIVE</option>
                            <option value="DAILY_REWARD">DAILY_REWARD</option>
                        </select>
                    </div>

                    <div class="scrollable-feed" id="eco-logs-list">
                        <?php if (empty($economyLogs)): ?>
                            <div style="text-align:center; padding:40px; color:var(--muted)">No economy transactions audited yet.</div>
                        <?php else: foreach ($economyLogs as $eco): 
                            $isPositive = $eco['amount'] >= 0;
                            $amountClass = $isPositive ? 'amount-positive' : 'amount-negative';
                            $prefix = $isPositive ? '+' : '';
                        ?>
                            <div class="alert-item eco-log-item" data-type="<?= $eco['transaction_type'] ?>" data-search="<?= strtolower(htmlspecialchars($eco['username'] . ' ' . $eco['transaction_type'] . ' ' . $eco['user_id'])) ?>">
                                <div class="alert-body">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                        <span style="font-weight:600; font-size:13px;"><i class="fa-regular fa-user"></i> <?= htmlspecialchars($eco['username']) ?> <span style="font-weight:normal; color:var(--muted)">(ID: #<?= $eco['user_id'] ?>)</span></span>
                                        <span class="badge" style="background:rgba(255,255,255,0.04); font-family: 'JetBrains Mono', monospace; font-size: 11px;"><?= htmlspecialchars($eco['transaction_type']) ?></span>
                                    </div>
                                    
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                                        <div style="font-size: 14px;" class="<?= $amountClass ?>"><?= $prefix . number_format($eco['amount']) ?> GTLM</div>
                                        <div style="text-align:right; font-size:11px; color:var(--muted); font-family: 'JetBrains Mono', monospace;">
                                            Before: <?= number_format($eco['balance_before']) ?><br>
                                            After: <?= number_format($eco['balance_after']) ?>
                                        </div>
                                    </div>
                                    
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; border-top:1px solid rgba(255,255,255,0.02); padding-top:8px;">
                                        <div class="alert-time"><i class="fa-regular fa-clock"></i> <?= $eco['created_at'] ?></div>
                                        <div>
                                            <?php if ($eco['metadata']): ?>
                                                <button class="btn btn-primary" style="padding: 4px 8px; font-size: 11px; border-radius: 6px;" onclick="showEcoDetails(<?= htmlspecialchars(json_encode($eco)) ?>)">
                                                    <i class="fa-solid fa-code"></i> Meta
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Details Modal Popup -->
        <div id="details-modal" class="modal" onclick="closeModal(event)">
            <div class="modal-content" onclick="event.stopPropagation()">
                <div class="modal-close" onclick="closeModalDirect()"><i class="fa-solid fa-xmark"></i></div>
                <div class="modal-header" id="modal-title">Log Details</div>
                <div id="modal-body" style="display:flex; flex-direction:column; gap:16px;">
                    <!-- Filled dynamically -->
                </div>
            </div>
        </div>

        <!-- New Experiment Modal Popup -->
        <div id="new-experiment-modal" class="modal" onclick="closeNewExperimentModal(event)">
            <div class="modal-content" onclick="event.stopPropagation()">
                <div class="modal-close" onclick="closeNewExperimentModalDirect()"><i class="fa-solid fa-xmark"></i></div>
                <div class="modal-header"><i class="fa-solid fa-flask" style="color:var(--primary)"></i> Create New Experiment</div>
                <form method="POST" style="display:flex; flex-direction:column; gap:16px;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="create_experiment">
                    
                    <div>
                        <label class="sim-label" style="margin-bottom:6px;">Experiment Name</label>
                        <input type="text" name="name" required placeholder="Ví dụ: Tết Event VIP Wheel" style="width:100%; background:var(--surface-hover); border:1px solid var(--border); border-radius:8px; padding:10px 14px; color:var(--text); font-size:14px; outline:none;">
                    </div>
                    
                    <div>
                        <label class="sim-label" style="margin-bottom:6px;">Experiment Type</label>
                        <input list="experiment-types" name="type" required placeholder="Chọn hoặc tự gõ loại thử nghiệm..." style="width:100%; background:var(--surface-hover); border:1px solid var(--border); border-radius:8px; padding:10px 14px; color:var(--text); font-size:14px; outline:none;">
                        <datalist id="experiment-types">
                            <?php foreach ($allExperimentTypes as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div>
                        <label class="sim-label" style="margin-bottom:6px;">Traffic Segment (%)</label>
                        <input type="number" name="user_percent" min="1" max="100" value="10" style="width:100%; background:var(--surface-hover); border:1px solid var(--border); border-radius:8px; padding:10px 14px; color:var(--text); font-size:14px; outline:none;">
                    </div>
                    
                    <div>
                        <label class="sim-label" style="margin-bottom:6px;">Initial Status</label>
                        <select name="status" style="width:100%; background:var(--surface-hover); border:1px solid var(--border); border-radius:8px; padding:10px 14px; color:var(--text); font-size:14px; outline:none; cursor:pointer;">
                            <option value="paused">Paused (Tạm dừng)</option>
                            <option value="active">Active (Kích hoạt ngay)</option>
                        </select>
                    </div>
                    
                    <div style="margin-top:10px; display:flex; justify-content:flex-end; gap:12px;">
                        <button type="button" class="btn" style="background:var(--surface-hover); color:var(--text);" onclick="closeNewExperimentModalDirect()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Experiment</button>
                    </div>
                </form>
            </div>
        </div>

    </main>

    <script>
        // --- Navigation Logic ---
        document.querySelectorAll('.nav-item[data-tab]').forEach(item => {
            item.addEventListener('click', () => {
                document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                item.classList.add('active');
                document.getElementById(item.dataset.tab).classList.add('active');
            });
        });

        // --- Logs Filtering & Modal Logic ---
        const appSearch = document.getElementById('app-log-search');
        const appLevel = document.getElementById('app-log-level');
        const ecoSearch = document.getElementById('eco-log-search');
        const ecoType = document.getElementById('eco-log-type');

        function filterAppLogs() {
            const query = appSearch.value.toLowerCase();
            const level = appLevel.value;
            
            document.querySelectorAll('.app-log-item').forEach(item => {
                const matchQuery = item.dataset.search.includes(query);
                const matchLevel = level === "" || item.dataset.level === level;
                
                if (matchQuery && matchLevel) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function filterEcoLogs() {
            const query = ecoSearch.value.toLowerCase();
            const type = ecoType.value;
            
            document.querySelectorAll('.eco-log-item').forEach(item => {
                const matchQuery = item.dataset.search.includes(query);
                const matchType = type === "" || item.dataset.type === type;
                
                if (matchQuery && matchType) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        if (appSearch) appSearch.addEventListener('input', filterAppLogs);
        if (appLevel) appLevel.addEventListener('change', filterAppLogs);
        if (ecoSearch) ecoSearch.addEventListener('input', filterEcoLogs);
        if (ecoType) ecoType.addEventListener('change', filterEcoLogs);

        // Modal Handlers
        const modal = document.getElementById('details-modal');
        const modalTitle = document.getElementById('modal-title');
        const modalBody = document.getElementById('modal-body');

        window.showLogDetails = function(log) {
            modalTitle.innerHTML = `<i class="fa-solid fa-server" style="color:var(--primary)"></i> System Log Details - ID #${log.id}`;
            let parsedDetails = "";
            try {
                if (log.details) {
                    const obj = JSON.parse(log.details);
                    parsedDetails = JSON.stringify(obj, null, 4);
                }
            } catch(e) {
                parsedDetails = log.details;
            }

            modalBody.innerHTML = `
                <div><strong>Category:</strong> <span class="badge" style="background:rgba(255,255,255,0.06); font-family: monospace;">${log.category}</span></div>
                <div><strong>Level:</strong> <span class="log-badge log-${log.log_level}">${log.log_level}</span></div>
                <div><strong>Message:</strong> <div style="margin-top:6px; color:#f8fafc; font-size:14px; font-weight:500;">${log.message}</div></div>
                <div><strong>Actor User:</strong> ${log.actor_name ? log.actor_name + ' (ID: #' + log.user_id + ')' : '<span style="color:var(--muted)">System (Guest)</span>'}</div>
                <div><strong>IP Address:</strong> <span style="font-family: monospace;">${log.ip_address}</span></div>
                <div><strong>Page URL:</strong> <span style="font-family: monospace; color:var(--info);">${log.page_url}</span></div>
                <div><strong>Created At:</strong> ${log.created_at}</div>
                \${parsedDetails ? \`
                    <div>
                        <strong>Trace Details & Context:</strong>
                        <pre class="code-block" style="margin-top:6px;">\${escapeHtml(parsedDetails)}</pre>
                    </div>
                \` : ''}
            `;
            modal.style.display = 'flex';
        };

        window.showEcoDetails = function(eco) {
            modalTitle.innerHTML = `<i class="fa-solid fa-coins" style="color:var(--warning)"></i> Economy Audit details - ID #${eco.id}`;
            let parsedMeta = "";
            try {
                if (eco.metadata) {
                    const obj = JSON.parse(eco.metadata);
                    parsedMeta = JSON.stringify(obj, null, 4);
                }
            } catch(e) {
                parsedMeta = eco.metadata;
            }

            modalBody.innerHTML = `
                <div><strong>User:</strong> <span>\${eco.username} (ID: #\${eco.user_id})</span></div>
                <div><strong>Transaction Type:</strong> <span class="badge" style="background:rgba(255,255,255,0.06); font-family: monospace;">\${eco.transaction_type}</span></div>
                <div><strong>Amount Change:</strong> <span class="\${eco.amount >= 0 ? 'amount-positive' : 'amount-negative'}">\${eco.amount >= 0 ? '+' : ''}\${parseFloat(eco.amount).toLocaleString()} GTLM</span></div>
                <div>
                    <strong>Balance Shift:</strong>
                    <div style="font-family: 'JetBrains Mono', monospace; font-size: 13px; color: var(--muted); margin-top:4px;">
                        Before: \${parseFloat(eco.balance_before).toLocaleString()} GTLM<br>
                        After:  \${parseFloat(eco.balance_after).toLocaleString()} GTLM
                    </div>
                </div>
                <div><strong>Reference ID:</strong> <span style="font-family: monospace;">\${eco.reference_id || 'N/A'}</span></div>
                <div><strong>Created At:</strong> \${eco.created_at}</div>
                \${parsedMeta ? \`
                    <div>
                        <strong>Transaction Metadata:</strong>
                        <pre class="code-block" style="margin-top:6px;">\${escapeHtml(parsedMeta)}</pre>
                    </div>
                \` : ''}
            `;
            modal.style.display = 'flex';
        };

        window.closeModalDirect = function() {
            modal.style.display = 'none';
        };

        window.closeModal = function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        };

        // --- New Experiment Modal Logic ---
        const expModal = document.getElementById('new-experiment-modal');
        window.openNewExperimentModal = function() {
            expModal.style.display = 'flex';
        };
        window.closeNewExperimentModalDirect = function() {
            expModal.style.display = 'none';
        };
        window.closeNewExperimentModal = function(e) {
            if (e.target === expModal) {
                expModal.style.display = 'none';
            }
        };

        function escapeHtml(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }


        // --- Charts ---
        const ecoCtx = document.getElementById('ecoChart').getContext('2d');
        const ecoHistory = <?= json_encode($ecoHistory) ?>;
        const labels = ecoHistory.map(h => h.time).slice(-20);
        
        new Chart(ecoCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Human Money',
                        data: ecoHistory.map(h => h.human).slice(-20),
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Bot Money',
                        data: ecoHistory.map(h => h.bot).slice(-20),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } },
                    x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
                }
            }
        });

        const botCtx = document.getElementById('botChart').getContext('2d');
        new Chart(botCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys($botStats['games'])) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($botStats['games'])) ?>,
                    backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#0ea5e9', '#8b5cf6'],
                    borderWidth: 0
                }]
            },
            options: {
                cutout: '70%',
                plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', usePointStyle: true } } }
            }
        });

        // --- Simulator Logic ---
        const inInflow = document.getElementById('in-inflow');
        const inEdge = document.getElementById('in-edge');
        const inBurn = document.getElementById('in-burn');
        const simResult = document.getElementById('sim-result');

        function updateSim() {
            document.getElementById('val-inflow').innerText = (inInflow.value / 10).toFixed(1) + 'B';
            document.getElementById('val-edge').innerText = inEdge.value + '%';
            document.getElementById('val-burn').innerText = (inBurn.value / 10).toFixed(1) + 'B';

            const inflow = parseFloat(inInflow.value);
            const edge = parseFloat(inEdge.value);
            const burn = parseFloat(inBurn.value);
            
            // Simple model: Net = Inflow - Burn - (Volume * Edge)
            // Assuming daily volume is 5x Inflow
            const volume = inflow * 5;
            const houseProfit = volume * (edge / 100);
            const net = inflow - burn - houseProfit;
            
            const totalMoney = <?= $latestEco['human'] + $latestEco['bot'] ?>;
            const predictedRate = (net / (totalMoney / 1000000)) * 0.1; // Scaling factor
            
            simResult.innerText = (predictedRate > 0 ? '+' : '') + predictedRate.toFixed(2) + '%';
            simResult.style.color = predictedRate > 5 ? 'var(--danger)' : (predictedRate > 0 ? 'var(--warning)' : 'var(--success)');
        }

        [inInflow, inEdge, inBurn].forEach(i => i.addEventListener('input', updateSim));
        updateSim();

        function generateMockAlert() {
            const types = ['big_win', 'fraud', 'churn_spike'];
            const type = types[Math.floor(Math.random() * types.length)];
            const messages = {
                'big_win': 'User "ProGamer99" won 50,000,000 GTLM in Blackjack Royale!',
                'fraud': 'Suspicious transaction pattern detected for User ID #552 (Rapid bet spikes)',
                'churn_spike': 'DAU dropped by 15% in last 4 hours compared to yesterday'
            };
            
            const alertHtml = `
                <div class="alert-item" style="background: rgba(99, 102, 241, 0.05); animation: fadeIn 0.5s ease-out;">
                    <div class="alert-icon warning">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                    <div class="alert-body">
                        <div class="alert-title">SIMULATED ${type.toUpperCase()}</div>
                        <div class="alert-desc">${messages[type]}</div>
                        <div class="alert-time">Just now</div>
                    </div>
                </div>
            `;
            const feed = document.getElementById('alert-feed');
            feed.insertAdjacentHTML('afterbegin', alertHtml);
        }
    </script>
</body>
</html>
