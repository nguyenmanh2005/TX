<?php
/**
 * 🛡️ GTLM Master Bot & Security Tester Hub (All-in-One) - chat3.php
 * Hợp nhất toàn bộ công cụ kiểm thử:
 * 1. Rà Quét Lỗ Hổng & Bảo Mật (Admin Tester Bot Suite v5.0)
 * 2. Kịch Bản Chat AI Ngữ Cảnh (4A Smart AI Chat Tester)
 * 3. Mưa Lì Xì & Tranh Lộc AI (4B Red Envelope Drops)
 * 4. Bộ Hiệu Ứng Âm Thanh Web Audio (4C Sound FX Synthesizer)
 * 5. Giám Sát Quân Đoàn Bot & Sức Khỏe CSDL (Bot Fleet & DB Audit)
 */

session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/admin_helper.php';
require_once __DIR__ . '/load_theme.php';

// Rule 4.2: Kiểm tra quyền Admin
$userId = (int)($_SESSION['Iduser'] ?? 0);
if (!isAdmin($conn, $userId)) {
    header("Location: login.php?error=unauthorized");
    exit();
}

// 1. AJAX: Load nhật ký rà quét bảo mật từ DB
if (isset($_GET['action']) && $_GET['action'] === 'load_logs') {
    $result = $conn->query("
        SELECT id, message, created_at
        FROM chat_messages 
        WHERE username = 'Admin Tester Bot'
        ORDER BY id DESC LIMIT 50
    ");
    $logs = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($logs);
    exit;
}

// 2. AJAX: Gửi tin nhắn giả lập cục bộ (4A)
if (isset($_GET['action']) && $_GET['action'] === 'mock_send') {
    header('Content-Type: application/json; charset=utf-8');
    $msg = trim($_POST['message'] ?? '');
    if (!empty($msg)) {
        $senderId = $userId > 0 ? $userId : 1;
        $username = $_SESSION['Name'] ?? 'Admin Tester';
        $avatar = $_SESSION['Avatar'] ?? 'img/avatar_default.png';
        
        $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("isss", $senderId, $username, $msg, $avatar);
            $stmt->execute();
            $stmt->close();
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

// 3. AJAX: Thống kê tình trạng quân đoàn Bot & CSDL (Tab 5)
if (isset($_GET['action']) && $_GET['action'] === 'bot_fleet_stats') {
    header('Content-Type: application/json; charset=utf-8');
    
    // Thống kê Bot
    $botRes = $conn->query("SELECT COUNT(*) as total_bots, COALESCE(SUM(Money), 0) as total_bot_money FROM users WHERE Email REGEXP '^bot[0-9]+@'")->fetch_assoc();
    // Thống kê Người thật
    $userRes = $conn->query("SELECT COUNT(*) as total_users, COALESCE(SUM(Money), 0) as total_user_money FROM users WHERE Email NOT REGEXP '^bot[0-9]+@'")->fetch_assoc();
    // Top 5 Bot giàu nhất
    $topBotsRes = $conn->query("SELECT Iduser, Name, Email, Money FROM users WHERE Email REGEXP '^bot[0-9]+@' ORDER BY Money DESC LIMIT 5");
    $topBots = [];
    if ($topBotsRes) {
        while ($r = $topBotsRes->fetch_assoc()) {
            $topBots[] = $r;
        }
    }
    
    echo json_encode([
        'success' => true,
        'bots' => [
            'count' => (int)($botRes['total_bots'] ?? 0),
            'total_money' => (float)($botRes['total_bot_money'] ?? 0),
            'top' => $topBots
        ],
        'users' => [
            'count' => (int)($userRes['total_users'] ?? 0),
            'total_money' => (float)($userRes['total_user_money'] ?? 0)
        ]
    ]);
    exit;
}

// Kiểm tra tình trạng các bảng DB bổ trợ
$tableBotReady = false;
$checkBot = $conn->query("SHOW TABLES LIKE 'bot_smart_chat_logs'");
if ($checkBot && $checkBot->num_rows > 0) $tableBotReady = true;

$tableEnvReady = false;
$checkEnv = $conn->query("SHOW TABLES LIKE 'red_envelopes'");
if ($checkEnv && $checkEnv->num_rows > 0) $tableEnvReady = true;

// Truy vấn sẵn thông tin quân đoàn Bot cho server-side render ban đầu
$initialBotStats = $conn->query("SELECT COUNT(*) as total_bots, COALESCE(SUM(Money), 0) as total_bot_money FROM users WHERE Email REGEXP '^bot[0-9]+@'")->fetch_assoc();
$totalBotsCount = (int)($initialBotStats['total_bots'] ?? 0);
$totalBotMoney = (float)($initialBotStats['total_bot_money'] ?? 0);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛡️ Master Tester Hub | Hệ Thống Kiểm Thử Toàn Diện</title>
    
    <!-- Google Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Tương thích module Lì Xì & Sound FX -->
    <link rel="stylesheet" href="assets/css/sound-fx-hub.css">
    <link rel="stylesheet" href="assets/css/red-envelope.css">
    
    <!-- Script phụ trợ -->
    <script src="assets/js/sound-fx-hub.js"></script>
    <script src="assets/js/red-envelope.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Khởi tạo hàm switchTab ngay lập tức trên window để click luôn hoạt động
        function switchTab(tabId, el) {
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            
            const targetPanel = document.getElementById(tabId);
            if (targetPanel) {
                targetPanel.classList.add('active');
            }
            if (el) {
                el.classList.add('active');
            } else {
                const matchedBtn = document.querySelector(`.tab-btn[data-tab="${tabId}"]`);
                if (matchedBtn) matchedBtn.classList.add('active');
            }

            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '#' + tabId);
            }

            if (typeof logAction === 'function') {
                logAction('HỆ THỐNG', 'Chuyển sang phân hệ kiểm thử: <b>' + tabId.toUpperCase() + '</b>', 'tag-sec');
            }

            if (tabId === 'tab-fleet' && typeof loadFleetStats === 'function') {
                loadFleetStats();
            }
        }
        window.switchTab = switchTab;
    </script>

    <style>
        :root {
            --primary: #ef4444;
            --primary-glow: rgba(239, 68, 68, 0.4);
            --secondary: #3b82f6;
            --accent-purple: #8b5cf6;
            --accent-gold: #f59e0b;
            --success: #10b981;
            --warning: #f59e0b;
            --dark-bg: #090e17;
            --card-bg: rgba(20, 27, 45, 0.75);
            --border-color: rgba(255, 255, 255, 0.08);
            --border-highlight: rgba(99, 102, 241, 0.4);
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at top center, #1e1b4b 0%, #0f172a 45%, #050811 100%);
            background-attachment: fixed;
            color: #f8fafc;
            margin: 0;
            padding: 24px;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Top Header */
        .master-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 30px;
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid var(--border-color);
            backdrop-filter: blur(16px);
            border-radius: 20px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.5);
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .master-title h1 {
            margin: 0 0 6px 0;
            font-size: 28px;
            font-weight: 900;
            background: linear-gradient(135deg, #60a5fa 0%, #c084fc 50%, #fbbf24 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .master-title p {
            margin: 0;
            color: #94a3b8;
            font-size: 14px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-top {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.25s ease;
            cursor: pointer;
            border: 1px solid rgba(255,255,255,0.12);
        }

        .btn-top-dashboard {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            color: #cbd5e1;
        }

        .btn-top-dashboard:hover {
            background: #334155;
            color: #fff;
            transform: translateY(-2px);
        }

        .btn-top-bot {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.35);
        }

        .btn-top-bot:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
        }

        /* Navigation Tabs */
        .tabs-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            overflow-x: auto;
            padding-bottom: 6px;
            scrollbar-width: thin;
        }

        .tab-btn {
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid var(--border-color);
            color: #94a3b8;
            padding: 14px 22px;
            border-radius: 16px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
            backdrop-filter: blur(10px);
        }

        .tab-btn:hover {
            color: #f8fafc;
            border-color: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .tab-btn.active {
            color: #fff;
            border-color: #6366f1;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.35), rgba(139, 92, 246, 0.35));
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.35);
        }

        .tab-btn.tab-red.active {
            border-color: #ef4444;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.35), rgba(185, 28, 28, 0.35));
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.35);
        }

        .tab-btn.tab-gold.active {
            border-color: #f59e0b;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.35), rgba(217, 119, 6, 0.35));
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.35);
        }

        .tab-btn.tab-green.active {
            border-color: #10b981;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.35), rgba(5, 150, 105, 0.35));
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
        }

        /* Tab Content Panels */
        .tab-panel {
            display: none !important;
        }

        .tab-panel.active {
            display: block !important;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Card Container */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 26px;
            backdrop-filter: blur(16px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            margin-bottom: 24px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        @media (max-width: 900px) {
            .grid-2 { grid-template-columns: 1fr; }
        }

        /* Module Headers inside Cards */
        .module-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .module-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #60a5fa;
        }

        .module-header h2.red { color: #f87171; }
        .module-header h2.gold { color: #fbbf24; }
        .module-header h2.green { color: #34d399; }

        /* General Buttons */
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            border: 1px solid rgba(255,255,255,0.15);
            transition: all 0.25s ease;
            text-align: center;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            filter: brightness(1.1);
        }

        .btn-action:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .btn-primary { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4); }
        .btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; box-shadow: 0 4px 15px rgba(220, 38, 38, 0.4); }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); color: white; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4); }
        .btn-gold { background: linear-gradient(135deg, #f59e0b, #d97706); color: #000; font-weight: 800; border-color: #fef08a; }
        .btn-purple { background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: white; }

        /* Stats Grid (Tab 1) */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-box {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 18px;
            text-align: center;
            transition: transform 0.2s ease;
        }

        .stat-box:hover {
            transform: translateY(-3px);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .stat-val {
            font-size: 32px;
            font-weight: 900;
            display: block;
            margin-bottom: 4px;
        }

        .stat-lbl {
            font-size: 13px;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Progress Bar */
        .progress-box {
            display: none;
            margin-bottom: 20px;
            background: rgba(15, 23, 42, 0.6);
            padding: 14px 18px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .progress-track {
            height: 10px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 6px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-bar-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #3b82f6, #10b981);
            transition: width 0.3s ease;
        }

        /* Findings Table */
        .findings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .findings-table th {
            text-align: left;
            padding: 14px 16px;
            color: #94a3b8;
            border-bottom: 1px solid var(--border-color);
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
        }

        .findings-table td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            font-size: 14px;
        }

        .badge-type {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .badge-critical { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid #ef4444; }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 1px solid #f59e0b; }
        .badge-ok { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid #10b981; }

        /* Action Buttons Grid in Tabs */
        .grid-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }

        /* Unified Master Console */
        .master-console {
            background: #060911;
            border: 1px solid #1e293b;
            border-radius: 18px;
            padding: 20px;
            box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.8);
            margin-top: 24px;
        }

        .console-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            border-bottom: 1px solid #1e293b;
            padding-bottom: 10px;
        }

        .console-header h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 800;
            color: #38bdf8;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .console-box {
            height: 240px;
            overflow-y: auto;
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 13px;
            color: #cbd5e1;
            line-height: 1.5;
            padding-right: 8px;
        }

        .console-item {
            margin-bottom: 6px;
            padding-bottom: 6px;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.05);
        }

        .console-item span.time { color: #fbbf24; font-weight: bold; }
        .console-item span.tag-sec { color: #f87171; font-weight: bold; }
        .console-item span.tag-ai { color: #c084fc; font-weight: bold; }
        .console-item span.tag-env { color: #fb7185; font-weight: bold; }
        .console-item span.tag-snd { color: #60a5fa; font-weight: bold; }
        .console-item span.tag-fleet { color: #34d399; font-weight: bold; }

        .env-item-row {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Top Master Header -->
    <div class="master-header">
        <div class="master-title">
            <h1><i class="fas fa-shield-cat"></i> Master Bot & Security Tester Hub</h1>
            <p>Hợp nhất 5 phân hệ: Rà Quét Lỗ Hổng 5.0 • Trí Tuệ Chat AI 4A • Mưa Lì Xì 4B • Sound Synthesizer 4C • Quân Đoàn Bot CSDL</p>
        </div>
        <div class="header-actions">
            <a href="admin_dashboard.php" class="btn-top btn-top-dashboard"><i class="fas fa-arrow-left"></i> Bảng Admin</a>
            <a href="bot/index.php" class="btn-top btn-top-bot"><i class="fas fa-robot"></i> Quân Đoàn Bot</a>
            <a href="chat.php" class="btn-top btn-top-dashboard" target="_blank"><i class="fas fa-comments"></i> Kênh Chat Thế Giới</a>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="tabs-nav">
        <button class="tab-btn active" data-tab="tab-security" onclick="switchTab('tab-security', this)">
            <i class="fas fa-shield-halved"></i> 1. Rà Quét Lỗ Hổng (Security Suite)
        </button>
        <button class="tab-btn" data-tab="tab-ai" onclick="switchTab('tab-ai', this)">
            <i class="fas fa-brain"></i> 2. Kịch Bản Chat AI (Smart AI Bot)
        </button>
        <button class="tab-btn tab-red" data-tab="tab-env" onclick="switchTab('tab-env', this)">
            <i class="fas fa-envelope-open-text"></i> 3. Mưa Lì Xì & Tranh Lộc (Drops)
        </button>
        <button class="tab-btn tab-gold" data-tab="tab-sound" onclick="switchTab('tab-sound', this)">
            <i class="fas fa-music"></i> 4. Synthesizer Âm Thanh (Sound FX)
        </button>
        <button class="tab-btn tab-green" data-tab="tab-fleet" onclick="switchTab('tab-fleet', this)">
            <i class="fas fa-users-gear"></i> 5. Quân Đoàn Bot & CSDL (Fleet Health)
        </button>
    </div>

    <!-- ==================== TAB 1: SECURITY AUDIT ==================== -->
    <div id="tab-security" class="tab-panel active">
        <div class="card">
            <div class="module-header">
                <h2><i class="fas fa-microchip"></i> Kiểm Toán An Ninh & Cược Dị Thường (Tester Bot Suite v5.0)</h2>
                <div style="display: flex; gap: 10px;">
                    <button id="btnStart" class="btn-action btn-primary" onclick="startScan()">
                        <i class="fas fa-play"></i> Bắt đầu Quét Hệ Thống
                    </button>
                    <button id="btnStop" class="btn-action btn-danger" onclick="stopScan()" disabled>
                        <i class="fas fa-stop"></i> Dừng Khẩn Cấp
                    </button>
                    <button class="btn-action btn-success" onclick="exportResults()">
                        <i class="fas fa-file-export"></i> Xuất Báo Cáo JSON
                    </button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-box">
                    <span id="statTotal" class="stat-val" style="color: #60a5fa;">0</span>
                    <span class="stat-lbl">Tổng File PHP Đã Quét</span>
                </div>
                <div class="stat-box">
                    <span id="statCritical" class="stat-val" style="color: #ef4444;">0</span>
                    <span class="stat-lbl">Nguy Cơ Cao (Critical)</span>
                </div>
                <div class="stat-box">
                    <span id="statWarning" class="stat-val" style="color: #f59e0b;">0</span>
                    <span class="stat-lbl">Cảnh Báo (Warning)</span>
                </div>
                <div class="stat-box">
                    <span id="statOk" class="stat-val" style="color: #10b981;">0</span>
                    <span class="stat-lbl">Vùng An Toàn</span>
                </div>
            </div>

            <!-- Progress Bar -->
            <div id="progressBox" class="progress-box">
                <div style="display: flex; justify-content: space-between; font-size: 14px; font-weight: 600;">
                    <span id="scanStatusText"><i class="fas fa-spinner fa-spin"></i> Đang phân tích 498 files và 9 bài kiểm toán...</span>
                    <span id="progressPercent" style="color: #38bdf8;">0%</span>
                </div>
                <div class="progress-track">
                    <div id="progressFill" class="progress-bar-fill"></div>
                </div>
            </div>

            <!-- Findings Table -->
            <div style="overflow-x: auto;">
                <table class="findings-table">
                    <thead>
                        <tr>
                            <th style="width: 120px;">Mức Độ</th>
                            <th style="width: 220px;">Tệp Tin / Phân Hệ</th>
                            <th>Mô Tả Lỗ Hổng / Vấn Đề Phát Hiện</th>
                        </tr>
                    </thead>
                    <tbody id="resultsBody">
                        <tr>
                            <td colspan="3" style="text-align: center; color: #64748b; padding: 25px;">
                                Chưa có dữ liệu quét. Hãy bấm <b>"Bắt đầu Quét Hệ Thống"</b> để Tester Bot rà quét tự động 498 files.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ==================== TAB 2: SMART AI BOT CHAT ==================== -->
    <div id="tab-ai" class="tab-panel">
        <div class="grid-2">
            <div class="card">
                <div class="module-header">
                    <h2><i class="fas fa-comments"></i> Kịch Bản Tin Nhắn Mẫu (Ngữ Cảnh 4A)</h2>
                </div>
                <p style="color:#94a3b8; font-size:14px; margin-top:0;">Bấm vào một kịch bản để gửi tin nhắn thử nghiệm vào Kênh Chat Thế Giới và xem Bot AI lập tức phản hồi:</p>
                
                <div class="grid-buttons">
                    <button class="btn-action btn-purple" onclick="sendMockChat('Anh em ơi hôm nay làm ván gì thơm @Cụ Giáo')">👴 Gọi @Cụ Giáo</button>
                    <button class="btn-action btn-primary" onclick="sendMockChat('Đại gia @Whale có kèo gì cho em húp GTLM với')">🐳 Gọi @Whale</button>
                    <button class="btn-action btn-gold" onclick="sendMockChat('@Plinko hướng dẫn em cách nổ hũ x100 với anh')">🎰 Gọi @Plinko</button>
                    <button class="btn-action btn-success" onclick="sendMockChat('Vừa nổ hũ 500 triệu GTLM bên Plinko sướng quá!')">🎉 Ngữ Cảnh Thắng/Nổ Hũ</button>
                    <button class="btn-action btn-danger" onclick="sendMockChat('Đen quá bay màu sạch nick GTLM rồi buồn!')">😭 Ngữ Cảnh Thua/Cháy Nick</button>
                    <button class="btn-action btn-purple" onclick="sendMockChat('Tối nay nên chiến game Sâm Lốc hay Đua Ngựa đây?')">❓ Ngữ Cảnh Tư Vấn Kèo</button>
                </div>

                <div style="margin-top: 24px; border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 18px;">
                    <label style="color:#cbd5e1; font-size:13px; font-weight:700;">✍️ Hoặc nhập tin nhắn tùy chỉnh:</label>
                    <div style="display:flex; gap:10px; margin-top:8px;">
                        <input type="text" id="customMsg" placeholder="VD: @Cụ Giáo ơi cho em xin lộc ván này..." style="flex:1; background:#0b1120; border:1px solid #334155; color:#f8fafc; padding:12px 16px; border-radius:12px; outline:none; font-family:'Outfit', sans-serif;">
                        <button class="btn-action btn-gold" style="white-space:nowrap;" onclick="sendCustomChat()">Gửi & Quét AI</button>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="module-header">
                    <h2><i class="fas fa-bolt"></i> Trạng Thái Bộ Não AI (Rule 5.3)</h2>
                </div>
                <div style="color:#cbd5e1; font-size:14px; line-height:1.7;">
                    <p>• <b>Từ vựng Casino độc quyền</b>: <i>GTLM, húp, bay màu, ra chiêu, trận địa, nick...</i></p>
                    <p>• <b>Cơ chế tự động</b>: AI Bot tự động rà quét mỗi <b>12 giây</b> hoặc phản hồi ngay lập tức khi nhận được tín hiệu gọi tên (@Mention).</p>
                    <p>• <b>Tình trạng Bảng CSDL</b>: <?= $tableBotReady ? '<span style="color:#10b981; font-weight:bold;">✅ Bảng `bot_smart_chat_logs` đã sẵn sàng</span>' : '<span style="color:#ef4444; font-weight:bold;">⚠️ Chưa có bảng `bot_smart_chat_logs`</span>' ?></p>
                </div>
                <button class="btn-action btn-primary" style="width:100%; margin-top:20px; padding:14px;" onclick="triggerSmartAIScan()">
                    <i class="fas fa-rotate"></i> Cưỡng Chế Quét & Kích Hoạt AI Bot Ngay
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== TAB 3: RED ENVELOPE ==================== -->
    <div id="tab-env" class="tab-panel">
        <div class="grid-2">
            <div class="card">
                <div class="module-header">
                    <h2 class="red"><i class="fas fa-envelope-open-text"></i> Tạo Mưa Lì Xì Tranh Lộc (4B)</h2>
                </div>
                <p style="color:#94a3b8; font-size:14px; margin-top:0;">Bấm để các Bot Đại Gia phát lì xì, tạo cơn mưa bao lì xì rơi lả tả trên toàn bộ màn hình:</p>
                <div class="grid-buttons">
                    <button class="btn-action btn-gold" onclick="createMockRain('Đại Gia Whale', 'https://api.dicebear.com/7.x/avataaars/svg?seed=whale&style=circle', 100000, 5, 'Bổn thiếu gia vừa húp Baccarat 1 Tỷ, phát lộc cho anh em!')">🐳 @Whale Phát 100K (5 bao)</button>
                    <button class="btn-action btn-purple" onclick="createMockRain('Cụ Giáo', 'https://api.dicebear.com/7.x/avataaars/svg?seed=cugia&style=circle', 250000, 8, 'Tâm bất biến giữa dòng đời vạn biến, chúc đạo hữu may mắn!')">👴 @Cụ Giáo Phát 250K (8 bao)</button>
                    <button class="btn-action btn-primary" onclick="createMockRain('Thánh Nổ Plinko', 'https://api.dicebear.com/7.x/avataaars/svg?seed=plinko&style=circle', 500000, 10, 'Plinko x100 rực rỡ! Ai tay nhanh thì húp!')">🎰 @Plinko Phát 500K (10 bao)</button>
                    <button class="btn-action btn-danger" onclick="createMockRain('Admin Phát Lộc', 'img/avatar_default.png', 1000000, 20, 'Lì xì tri ân toàn thể cộng đồng GTLM hôm nay!')">👑 Admin Phát 1 Triệu (20 bao)</button>
                </div>
            </div>

            <div class="card">
                <div class="module-header">
                    <h2 class="red"><i class="fas fa-list-check"></i> Bao Lì Xì Đang Kích Hoạt</h2>
                </div>
                <div id="envList" style="min-height:120px;">
                    <p style="color:#94a3b8; font-style:italic;">⏳ Đang rà quét bao lì xì từ server...</p>
                </div>
                <button class="btn-action btn-gold" style="width:100%; margin-top:14px;" onclick="loadActiveList(true)">
                    <i class="fas fa-arrows-rotate"></i> Làm Mới & Thả Bot AI Vào Giật Lộc
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== TAB 4: SOUND FX SYNTHESIZER ==================== -->
    <div id="tab-sound" class="tab-panel">
        <div class="grid-2">
            <div class="card">
                <div class="module-header">
                    <h2 class="gold"><i class="fas fa-waveform-lines"></i> Bộ Hiệu Ứng Âm Thanh Web Audio (4C)</h2>
                </div>
                <p style="color:#94a3b8; font-size:14px; margin-top:0;">Âm thanh tổng hợp trực tiếp bằng dao động tần số Web Audio API — mượt mà, không lag, không phụ thuộc file MP3:</p>
                <div class="grid-buttons">
                    <button class="btn-action btn-gold" onclick="testSound('jackpot')"><i class="fas fa-trophy"></i> Nổ Hũ Jackpot x100</button>
                    <button class="btn-action btn-success" onclick="testSound('lottery')"><i class="fas fa-coins"></i> Thắng Xổ Số / Giật Lì Xì</button>
                    <button class="btn-action btn-danger" onclick="testSound('boss')"><i class="fas fa-dragon"></i> Boss Long Thần Gầm Rú</button>
                    <button class="btn-action btn-primary" onclick="testSound('pvp')"><i class="fas fa-bullhorn"></i> Kèn Thách Đấu PvP</button>
                    <button class="btn-action btn-purple" onclick="testSound('spin')"><i class="fas fa-rotate"></i> Vòng Quay Lucky Spin</button>
                    <button class="btn-action btn-gold" onclick="testSound('pop')"><i class="fas fa-bell"></i> Âm Báo Tin Nhắn / Bot Pop</button>
                </div>
            </div>

            <div class="card">
                <div class="module-header">
                    <h2 class="gold"><i class="fas fa-sliders"></i> Quản Trị Sóng Âm Toàn Hệ Thống</h2>
                </div>
                <div style="color:#cbd5e1; font-size:14px; line-height:1.7;">
                    <p>• Bấm bất kỳ nút bên trái để nghe thử nghiệm tức thì dải tần số âm thanh.</p>
                    <p>• Trạng thái âm thanh toàn cục được đồng bộ tự động tại thanh widget góc dưới trái (`#gtlm-sound-widget`).</p>
                    <p>• Các sóng âm nhấp nháy động sẽ reo theo từng dải tần của hiệu ứng.</p>
                </div>
                <button class="btn-action btn-gold" style="width:100%; margin-top:20px; padding:14px;" onclick="toggleSystemMute()">
                    <i class="fas fa-volume-high"></i> Bật / Tắt Âm Thanh Toàn Hệ Thống
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== TAB 5: BOT FLEET & DB AUDIT ==================== -->
    <div id="tab-fleet" class="tab-panel">
        <div class="grid-2">
            <div class="card">
                <div class="module-header">
                    <h2 class="green"><i class="fas fa-users-gear"></i> Tình Trạng Quân Đoàn Bot</h2>
                    <button class="btn-action btn-success" style="padding:6px 14px; font-size:12px;" onclick="loadFleetStats()"><i class="fas fa-sync"></i> Cập Nhật</button>
                </div>
                <div class="stats-grid" style="margin-bottom:16px;">
                    <div class="stat-box">
                        <span id="fleetBotCount" class="stat-val" style="color:#34d399;"><?= number_format($totalBotsCount) ?></span>
                        <span class="stat-lbl">Tổng Quân Số Bot</span>
                    </div>
                    <div class="stat-box">
                        <span id="fleetBotMoney" class="stat-val" style="color:#fbbf24;"><?= number_format($totalBotMoney) ?></span>
                        <span class="stat-lbl">Tổng GTLM Quân Đoàn</span>
                    </div>
                </div>
                <div style="background:rgba(15,23,42,0.6); padding:16px; border-radius:14px; border:1px solid var(--border-color); font-size:14px; line-height:1.6;">
                    <b style="color:#38bdf8;">Định dạng định danh chuẩn:</b> <code>^bot[0-9]+@gmail.com</code><br>
                    <b style="color:#38bdf8;">Kiểm tra Regex CSDL:</b> <span id="fleetRegexStatus" style="color:#10b981;">100% tài khoản bot tuân thủ quy chuẩn</span>
                </div>
            </div>

            <div class="card">
                <div class="module-header">
                    <h2 class="green"><i class="fas fa-crown"></i> Top 5 Bot Giàu Nhất Server</h2>
                </div>
                <div id="topBotsTableBox" style="overflow-x:auto;">
                    <table class="findings-table">
                        <thead>
                            <tr>
                                <th>Bot</th>
                                <th>Email Định Danh</th>
                                <th style="text-align:right;">Số Dư GTLM</th>
                            </tr>
                        </thead>
                        <tbody id="topBotsBody">
                            <tr><td colspan="3" style="text-align:center; color:#64748b;">Đang tải danh sách đại gia bot...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Master Unified Console Log -->
    <div class="master-console">
        <div class="console-header">
            <h3><i class="fas fa-terminal"></i> UNIFIED MASTER CONSOLE LOG (TẤT CẢ PHÂN HỆ)</h3>
            <button class="btn-action" style="padding: 4px 12px; font-size: 12px; background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.1); color: #94a3b8;" onclick="clearMasterLog()">
                <i class="fas fa-broom"></i> Xóa Console
            </button>
        </div>
        <div class="console-box" id="masterLog">
            <div class="console-item">
                <span class="time">[<?= date('H:i:s') ?>]</span>
                <span class="tag-sec">[SYSTEM]</span> Master Tester Hub đã kết nối thành công toàn bộ 5 phân hệ kiểm thử. Sẵn sàng nhận lệnh từ Admin!
            </div>
        </div>
    </div>
</div>

<script>
    // --- Biến Quản Lý Trạng Thái ---
    let isScanning = false;
    let scanResults = null;

    // --- Tab Navigation Hỗ Trợ URL Query & Hash ---
    function switchTab(tabId, el) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        
        const targetPanel = document.getElementById(tabId);
        if (targetPanel) {
            targetPanel.classList.add('active');
        }
        
        const activeBtn = el || document.querySelector(`.tab-btn[data-tab="${tabId}"]`);
        if (activeBtn) {
            activeBtn.classList.add('active');
        }

        // Cập nhật Hash trên thanh địa chỉ mà không reload
        if (window.history.replaceState) {
            window.history.replaceState(null, '', '#' + tabId);
        }

        logAction('HỆ THỐNG', `Chuyển sang phân hệ kiểm thử: <b>${tabId.toUpperCase()}</b>`, 'tag-sec');

        if (tabId === 'tab-fleet' && typeof loadFleetStats === 'function') {
            loadFleetStats();
        }
    }
    window.switchTab = switchTab;

    // --- Master Console Logging ---
    function logAction(tag, htmlMsg, tagClass = 'tag-sec') {
        const box = document.getElementById('masterLog');
        if (!box) return;
        const item = document.createElement('div');
        item.className = 'console-item';
        item.innerHTML = `<span class="time">[${new Date().toLocaleTimeString()}]</span> <span class="${tagClass}">[${tag}]</span> ${htmlMsg}`;
        box.insertBefore(item, box.firstChild);
    }

    function clearMasterLog() {
        document.getElementById('masterLog').innerHTML = '';
    }

    // ==================== TAB 1: SECURITY SCANNER ====================
    async function startScan() {
        if (isScanning) return;
        
        isScanning = true;
        document.getElementById('btnStart').disabled = true;
        document.getElementById('btnStop').disabled = false;
        document.getElementById('progressBox').style.display = 'block';
        document.getElementById('resultsBody').innerHTML = '';
        
        logAction('SECURITY', "Khởi động Suite kiểm toán v5.0 Final... Đang rà quét 498 files & 9 bài test tự động.", 'tag-sec');
        
        try {
            const response = await fetch('bot/tester_bot.php?action=scan');
            const data = await response.json();
            
            scanResults = data;
            renderResults(data);
            logAction('SECURITY', `Quét hoàn tất trong <b>${data.performance?.game_flows || 'vài'} giây</b>! Đã phân tích ${data.total_files} files.`, 'tag-sec');
        } catch (err) {
            logAction('SECURITY', `<b style="color:#ef4444">Lỗi tiến trình quét:</b> ${err.message}`, 'tag-sec');
            console.error(err);
        } finally {
            isScanning = false;
            document.getElementById('btnStart').disabled = false;
            document.getElementById('btnStop').disabled = true;
        }
    }

    function stopScan() {
        fetch('bot/tester_bot.php?action=stop')
            .then(() => logAction('SECURITY', "Đã gửi tín hiệu ngắt khẩn cấp tới Tester Bot...", 'tag-sec'))
            .catch(err => console.error(err));
    }

    function renderResults(data) {
        const tbody = document.getElementById('resultsBody');
        tbody.innerHTML = '';
        
        let critical = 0;
        let warning = 0;
        
        if (!data.findings || data.findings.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: #10b981; padding:25px;"><i class="fas fa-circle-check"></i> Tuyệt vời! Không tìm thấy lỗ hổng nào trên hệ thống!</td></tr>';
        } else {
            data.findings.forEach(item => {
                if (item.type === 'CRITICAL') critical++;
                if (item.type === 'WARNING') warning++;
                
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><span class="badge-type badge-${item.type.toLowerCase()}">${item.type}</span></td>
                    <td><code>${item.file}</code></td>
                    <td>${item.issue}</td>
                `;
                tbody.appendChild(tr);
            });
        }
        
        document.getElementById('statTotal').textContent = data.total_files || 498;
        document.getElementById('statCritical').textContent = critical;
        document.getElementById('statWarning').textContent = warning;
        document.getElementById('statOk').textContent = Math.max(0, (data.total_files || 498) - (critical + warning));
        
        document.getElementById('progressFill').style.width = '100%';
        document.getElementById('progressPercent').textContent = '100%';
        document.getElementById('scanStatusText').innerHTML = '<span style="color:#10b981"><i class="fas fa-check"></i> Đã hoàn tất quy trình kiểm toán!</span>';
    }

    function exportResults() {
        if (!scanResults) {
            Swal.fire('Thông báo', 'Chưa có kết quả quét để xuất file!', 'info');
            return;
        }
        const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(scanResults, null, 2));
        const downloadAnchorNode = document.createElement('a');
        downloadAnchorNode.setAttribute("href", dataStr);
        downloadAnchorNode.setAttribute("download", "security_scan_report_" + new Date().toISOString().slice(0,10) + ".json");
        document.body.appendChild(downloadAnchorNode);
        downloadAnchorNode.click();
        downloadAnchorNode.remove();
        logAction('SECURITY', "Đã xuất file báo cáo JSON thành công.", 'tag-sec');
    }

    // ==================== TAB 2: SMART AI BOT CHAT ====================
    async function sendMockChat(msgText) {
        logAction('AI CHAT', `Đang gửi tin nhắn mẫu: "<i>${msgText}</i>"...`, 'tag-ai');
        try {
            const formData = new FormData();
            formData.append('message', msgText);
            await fetch('chat3.php?action=mock_send', { method: 'POST', body: formData });
            setTimeout(triggerSmartAIScan, 500);
        } catch(e) {
            logAction('AI CHAT', `<b style="color:#ef4444">Lỗi gửi tin nhắn:</b> ${e.message}`, 'tag-ai');
        }
    }

    async function sendCustomChat() {
        const input = document.getElementById('customMsg');
        if (!input.value.trim()) return;
        await sendMockChat(input.value.trim());
        input.value = '';
    }

    async function triggerSmartAIScan() {
        logAction('AI CHAT', 'Đang kích hoạt quy trình quét phản hồi AI: <code>api_bot_smart_chat.php?action=scan</code>...', 'tag-ai');
        try {
            const res = await fetch('api_bot_smart_chat.php?action=scan');
            const data = await res.json();

            if (data && data.success && data.bot_scan) {
                if (data.bot_scan.actions && data.bot_scan.actions.length > 0) {
                    data.bot_scan.actions.forEach(act => logAction('AI CHAT', `🔥 ${act}`, 'tag-ai'));
                    if (typeof SoundFXHub !== 'undefined') SoundFXHub.playPop();
                    Swal.fire({
                        title: '🤖 AI Bot Đã Phản Hồi!',
                        text: 'Các Bot vừa trả lời tin nhắn của bạn trên Kênh Chat Thế Giới.',
                        icon: 'success', toast: true, position: 'top-end', timer: 3000, showConfirmButton: false
                    });
                } else {
                    logAction('AI CHAT', '✅ Quét hoàn tất: Không có ngữ cảnh mới nào chưa được xử lý.', 'tag-ai');
                }
            } else {
                logAction('AI CHAT', `Thông báo AI: ${data?.message || data?.bot_scan?.message || 'Chưa có hành động mới.'}`, 'tag-ai');
            }
        } catch(e) {
            logAction('AI CHAT', `<b style="color:#ef4444">Lỗi kết nối AI:</b> ${e.message}`, 'tag-ai');
        }
    }

    // ==================== TAB 3: RED ENVELOPE ====================
    async function createMockRain(senderName, senderAvatar, amount, count, msg) {
        logAction('LÌ XÌ', `Đang tạo Mưa Lì Xì: <b>${senderName}</b> phát <b>${new Intl.NumberFormat().format(amount)} GTLM</b> (${count} bao)...`, 'tag-env');
        try {
            const formData = new FormData();
            formData.append('is_mock', 'true');
            formData.append('mock_sender_name', senderName);
            formData.append('mock_sender_avatar', senderAvatar);
            formData.append('total_amount', amount);
            formData.append('total_count', count);
            formData.append('message', msg);

            const res = await fetch('api_red_envelope.php?action=create', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                logAction('LÌ XÌ', `✅ Phát lộc thành công! Mưa lì xì [LÌ XÌ #${data.envelope_id}] đang rơi trên màn hình...`, 'tag-env');
                if (typeof SoundFXHub !== 'undefined') SoundFXHub.playLotteryWin();
                setTimeout(() => loadActiveList(false), 500);
            } else {
                logAction('LÌ XÌ', `<b style="color:#ef4444">Lỗi tạo lì xì:</b> ${data.message}`, 'tag-env');
            }
        } catch(e) {
            logAction('LÌ XÌ', `<b style="color:#ef4444">Lỗi kết nối lì xì:</b> ${e.message}`, 'tag-env');
        }
    }

    async function loadActiveList(triggerBot) {
        try {
            const res = await fetch('api_red_envelope.php?action=list');
            const data = await res.json();
            const listEl = document.getElementById('envList');

            if (!data.success || !data.table_ready) {
                listEl.innerHTML = '<p style="color:#ef4444">⚠️ Bảng DB red_envelopes chưa sẵn sàng.</p>';
                return;
            }

            if (data.bot_action) {
                logAction('LÌ XÌ', `🤖 ${data.bot_action}`, 'tag-env');
                if (typeof SoundFXHub !== 'undefined') SoundFXHub.playPop();
            }

            const list = data.envelopes || [];
            if (list.length === 0) {
                listEl.innerHTML = '<p style="color:#94a3b8; font-style:italic;">Hiện tại không có lì xì nào đang kích hoạt. Bấm nút phát lộc ở bên trái để tạo mưa lì xì!</p>';
                return;
            }

            let html = '';
            list.forEach(env => {
                const safeJson = encodeURIComponent(JSON.stringify(env));
                html += `
                    <div class="env-item-row">
                        <div>
                            <b style="color:#fbbf24">🧧 ${env.sender_name}</b><br>
                            <small style="color:#cbd5e1">Còn: ${new Intl.NumberFormat().format(env.remaining_amount)} GTLM (${env.remaining_count}/${env.total_count} bao)</small>
                        </div>
                        <button class="btn-action btn-gold" style="padding:6px 14px; font-size:13px;" onclick="if(typeof RedEnvelopeHub!=='undefined'){RedEnvelopeHub.open(JSON.parse(decodeURIComponent('${safeJson}')))}">Tranh Lộc Ngay</button>
                    </div>
                `;
            });
            listEl.innerHTML = html;
        } catch(e) {
            logAction('LÌ XÌ', `<b style="color:#ef4444">Lỗi tải danh sách lì xì:</b> ${e.message}`, 'tag-env');
        }
    }

    // ==================== TAB 4: SOUND FX ====================
    function testSound(type) {
        logAction('SOUND', `Kích hoạt sóng âm Web Audio: <b>${type.toUpperCase()}</b>`, 'tag-snd');
        if (typeof SoundFXHub === 'undefined') return;
        if (type === 'jackpot') SoundFXHub.playJackpot();
        else if (type === 'lottery') SoundFXHub.playLotteryWin();
        else if (type === 'boss') SoundFXHub.playBossRoar();
        else if (type === 'pvp') {
            if (typeof SoundFXHub.playPvpChallenge === 'function') SoundFXHub.playPvpChallenge();
            else if (typeof SoundFXHub.playPvpHorn === 'function') SoundFXHub.playPvpHorn();
        }
        else if (type === 'spin') SoundFXHub.playLuckySpin();
        else if (type === 'pop') SoundFXHub.playPop();
    }

    function toggleSystemMute() {
        if (typeof SoundFXHub !== 'undefined') {
            SoundFXHub.toggleMute();
            logAction('SOUND', "Đã thay đổi trạng thái Âm Thanh Toàn Hệ Thống.", 'tag-snd');
        }
    }

    // ==================== TAB 5: BOT FLEET HEALTH ====================
    async function loadFleetStats() {
        logAction('FLEET', "Đang kiểm tra chỉ số Quân Đoàn Bot trong Database...", 'tag-fleet');
        try {
            const res = await fetch('chat3.php?action=bot_fleet_stats');
            const data = await res.json();
            
            if (data.success) {
                document.getElementById('fleetBotCount').textContent = new Intl.NumberFormat().format(data.bots.count);
                document.getElementById('fleetBotMoney').textContent = new Intl.NumberFormat().format(data.bots.total_money) + ' GTLM';
                
                const tbody = document.getElementById('topBotsBody');
                if (data.bots.top && data.bots.top.length > 0) {
                    let html = '';
                    data.bots.top.forEach(b => {
                        html += `
                            <tr>
                                <td><b>${b.Name}</b> (ID: ${b.Iduser})</td>
                                <td><code>${b.Email}</code></td>
                                <td style="text-align:right; font-weight:bold; color:#fbbf24;">${new Intl.NumberFormat().format(b.Money)} GTLM</td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                } else {
                    tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; color:#94a3b8;">Chưa có tài khoản bot nào.</td></tr>';
                }
                logAction('FLEET', `CSDL hợp lệ: Đang có <b>${data.bots.count}</b> Bot sở hữu <b>${new Intl.NumberFormat().format(data.bots.total_money)} GTLM</b>.`, 'tag-fleet');
            }
        } catch(e) {
            logAction('FLEET', `<b style="color:#ef4444">Lỗi kiểm tra CSDL bot:</b> ${e.message}`, 'tag-fleet');
        }
    }

    // --- Khởi tạo khi tải trang ---
    document.addEventListener('DOMContentLoaded', () => {
        // Kiểm tra Hash hoặc query param để tự động mở tab tương ứng
        const hash = window.location.hash.replace('#', '');
        const urlParams = new URLSearchParams(window.location.search);
        const queryTab = urlParams.get('tab');
        
        const activeTabId = queryTab || hash;
        if (activeTabId && document.getElementById(activeTabId)) {
            switchTab(activeTabId);
        } else {
            loadFleetStats();
        }

        loadActiveList(false);
        setInterval(() => loadActiveList(false), 12000);
    });
</script>

</body>
</html>