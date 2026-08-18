<?php
/**
 * 🚀 Bot Army Runner - Web Controller v3.0 (OmniBot Terminal)
 * Advanced Command Center with Chart.js, Audio FX, Leaderboard and Command Overrides.
 */
require_once __DIR__ . '/../db_connect.php';
$config = require __DIR__ . '/config.php';
$totalBots = count($config['bot_emails']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OmniBot Terminal | Advanced Command Center</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=JetBrains+Mono:wght@400;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg-base: #020617;
            --panel-bg: rgba(15, 23, 42, 0.7);
            --panel-border: rgba(99, 102, 241, 0.3);
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.6);
            --secondary: #8b5cf6;
            --success: #10b981;
            --danger: #ef4444;
            --warn: #f59e0b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --term-bg: rgba(0, 0, 0, 0.85);
            --font-tech: 'Orbitron', sans-serif;
            --font-code: 'JetBrains Mono', monospace;
            --font-body: 'Inter', sans-serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: var(--bg-base);
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 85% 30%, rgba(139, 92, 246, 0.15) 0%, transparent 50%);
            color: var(--text-main);
            font-family: var(--font-body);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Scanline Overlay Effect */
        body::before {
            content: " ";
            display: block;
            position: absolute;
            top: 0; left: 0; bottom: 0; right: 0;
            background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.1) 50%);
            background-size: 100% 4px;
            z-index: 9999;
            pointer-events: none;
            opacity: 0.3;
        }

        .topbar {
            padding: 15px 30px;
            background: rgba(2, 6, 23, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--panel-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 10;
            flex-shrink: 0;
        }

        .brand h1 {
            font-family: var(--font-tech);
            font-size: 1.5rem;
            font-weight: 900;
            background: linear-gradient(90deg, #818cf8, #c084fc);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 2px;
        }
        .brand p { font-size: 0.8rem; color: var(--text-muted); font-family: var(--font-code); }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 30px;
            font-family: var(--font-tech);
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }

        .status-idle { border-color: var(--text-muted); color: var(--text-muted); box-shadow: none; }
        .status-running { border-color: var(--success); color: var(--success); box-shadow: 0 0 15px rgba(16, 185, 129, 0.4); animation: pulse-green 2s infinite; }
        
        @keyframes pulse-green {
            0% { box-shadow: 0 0 10px rgba(16, 185, 129, 0.4); }
            50% { box-shadow: 0 0 25px rgba(16, 185, 129, 0.8); }
            100% { box-shadow: 0 0 10px rgba(16, 185, 129, 0.4); }
        }

        .main-layout {
            display: grid;
            grid-template-columns: 380px 1fr 320px;
            gap: 20px;
            padding: 20px 30px;
            flex: 1;
            position: relative;
            z-index: 10;
            min-height: 0;
        }

        .panel {
            background: var(--panel-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--panel-border);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            overflow-y: auto;
        }

        .panel::-webkit-scrollbar { width: 6px; }
        .panel::-webkit-scrollbar-thumb { background: rgba(99,102,241,0.5); border-radius: 10px; }

        .panel-title {
            font-family: var(--font-tech);
            font-size: 0.95rem;
            color: #fff;
            border-bottom: 1px dashed var(--panel-border);
            padding-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Form Controls */
        .control-group { display: flex; flex-direction: column; gap: 8px; }
        .control-group label { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;}
        .control-row { display: flex; gap: 10px; }
        
        input[type="number"], input[type="text"] {
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 10px 12px;
            border-radius: 8px;
            font-family: var(--font-code);
            font-size: 0.9rem;
            width: 100%;
            outline: none;
            transition: border 0.3s;
        }
        input[type="number"]:focus, input[type="text"]:focus { border-color: var(--primary); box-shadow: 0 0 10px var(--primary-glow); }

        /* Buttons */
        .btn-huge {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            font-family: var(--font-tech);
            font-size: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            border: none;
            display: flex; justify-content: center; align-items: center; gap: 10px;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }

        .btn-start { background: linear-gradient(45deg, #4f46e5, #7c3aed); color: #fff; box-shadow: 0 0 15px rgba(99, 102, 241, 0.4); }
        .btn-start:hover { transform: translateY(-2px); box-shadow: 0 0 25px rgba(139, 92, 246, 0.8); }

        .btn-stop { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid var(--danger); }
        .btn-stop:hover:not(:disabled) { background: var(--danger); color: #fff; box-shadow: 0 0 20px rgba(239, 68, 68, 0.6); }

        .btn-cmd { background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid var(--success); font-size: 0.8rem; padding: 10px;}
        .btn-cmd:hover { background: var(--success); color: #fff; box-shadow: 0 0 15px rgba(16, 185, 129, 0.5); }

        .btn-huge:disabled { filter: grayscale(100%); opacity: 0.5; cursor: not-allowed; box-shadow: none; transform: none; }

        /* Toggle Switch */
        .toggle-wrap { display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.3); padding: 10px 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); }
        .toggle-wrap span { font-weight: 600; font-size: 0.85rem; color: #fff; }
        .switch { position: relative; display: inline-block; width: 40px; height: 20px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(255,255,255,0.1); transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: var(--text-muted); transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--primary); box-shadow: 0 0 10px var(--primary-glow); }
        input:checked + .slider:before { transform: translateX(20px); background-color: #fff; }

        /* Stats Blocks */
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .stat-box { background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.05); padding: 10px; border-radius: 8px; text-align: center; }
        .stat-val { font-family: var(--font-tech); font-size: 1.3rem; color: #fff; font-weight: 700; margin-bottom: 2px; }
        .stat-lbl { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;}

        /* Filters */
        .filter-grid { display: flex; flex-wrap: wrap; gap: 6px; }
        .filter-btn {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--text-muted);
            padding: 5px 10px; border-radius: 6px; font-size: 0.7rem; font-family: var(--font-body); font-weight: 600;
            cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 5px;
        }
        .filter-btn:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .filter-btn.active { background: var(--primary); border-color: var(--primary); color: #fff; box-shadow: 0 0 10px var(--primary-glow); }
        .filter-btn.active-error { background: var(--danger); border-color: var(--danger); color: #fff; box-shadow: 0 0 10px rgba(239, 68, 68, 0.5); }

        /* Terminal Console */
        .terminal-wrapper {
            background: var(--term-bg);
            border: 1px solid var(--panel-border);
            border-radius: 16px;
            display: flex; flex-direction: column;
            box-shadow: inset 0 0 50px rgba(0,0,0,0.8);
            position: relative;
            overflow: hidden;
            min-height: 0;
        }

        .term-header {
            background: rgba(255,255,255,0.02);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding: 10px 20px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .term-actions { display: flex; gap: 10px; align-items: center; }
        .term-btn { background: transparent; border: 1px solid rgba(255,255,255,0.2); color: var(--text-muted); padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; cursor: pointer; transition: all 0.2s; }
        .term-btn:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .term-btn i { margin-right: 5px; }

        .console-container {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            font-family: var(--font-code);
            font-size: 0.85rem;
            line-height: 1.6;
            scroll-behavior: smooth;
        }
        
        .console-container::-webkit-scrollbar { width: 8px; }
        .console-container::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }

        /* Log Entry Styles */
        .console-container .log-item {
            margin-bottom: 6px;
            border-left: 2px solid rgba(255,255,255,0.1);
            padding-left: 10px;
            animation: slideIn 0.2s ease-out;
            word-break: break-word;
        }
        @keyframes slideIn { from { opacity: 0; transform: translateX(-10px); } to { opacity: 1; transform: translateX(0); } }
        
        /* VIP LOG CARD (Hologram effect) */
        .console-container .log-card-vip {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.15), rgba(6, 182, 212, 0.15));
            border: 1px solid rgba(34, 197, 94, 0.4);
            border-left: 4px solid var(--success);
            border-radius: 8px;
            padding: 12px;
            margin: 10px 0;
            box-shadow: 0 5px 15px rgba(34, 197, 94, 0.2);
            animation: hologram 3s infinite alternate;
        }
        @keyframes hologram { 0% { filter: brightness(1); } 100% { filter: brightness(1.2); } }

        .sys-log { color: #475569; font-style: italic; }
        .sys-ok { color: var(--success); font-weight: bold; }
        .sys-err { color: var(--danger); font-weight: bold; background: rgba(239, 68, 68, 0.1); padding: 2px 5px; border-radius: 4px;}

        /* CSS rules to handle filtering via JS classes */
        .console-container.filter-farm .log-item:not([data-filter="farm"]),
        .console-container.filter-pvp .log-item:not([data-filter="pvp"]),
        .console-container.filter-live .log-item:not([data-filter="live"]),
        .console-container.filter-chat .log-item:not([data-filter="chat"]),
        .console-container.filter-err .log-item:not([data-filter="err"]),
        .console-container.filter-combat .log-item:not([data-filter="combat"]) {
            display: none !important;
        }

        /* Leaderboard Items */
        .lb-item {
            display: flex; justify-content: space-between; align-items: center;
            background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.05);
            padding: 8px 12px; border-radius: 8px; font-size: 0.85rem;
        }
        .lb-name { font-weight: 600; color: #fff; display: flex; align-items: center; gap: 8px; }
        .lb-money { font-family: var(--font-code); color: var(--success); font-weight: 700; }
        .lb-rank-1 { color: #fbbf24; }
        .lb-rank-2 { color: #94a3b8; }
        .lb-rank-3 { color: #b45309; }

    </style>
</head>
<body>

    <div class="topbar">
        <div class="brand">
            <h1><i class="fa-solid fa-robot"></i> OmniBot Terminal</h1>
            <p>v3.0 • Advanced Command Center</p>
        </div>
        <div style="display:flex; gap: 20px; align-items:center;">
            <div class="toggle-wrap" style="padding: 5px 10px;">
                <span style="font-size:0.75rem; color:var(--text-muted); margin-right: 8px;"><i class="fa-solid fa-volume-high"></i> Audio FX</span>
                <label class="switch" style="width:30px; height: 16px;">
                    <input type="checkbox" id="audioToggle" checked>
                    <span class="slider" style="border-radius:20px;"></span>
                </label>
            </div>
            <a href="index.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem; font-weight: 600;"><i class="fa-solid fa-arrow-left"></i> Về Dashboard</a>
            <div id="runnerStatus" class="status-indicator status-idle">
                <i class="fa-solid fa-power-off"></i> <span id="statusText">System Idle</span>
            </div>
        </div>
    </div>

    <div class="main-layout">
        
        <!-- Left Panel: Controls & Command -->
        <div class="panel">
            <div class="panel-title"><i class="fa-solid fa-sliders"></i> Cấu Hình Core</div>
            
            <div class="control-row">
                <div class="control-group" style="flex: 1;">
                    <label>Số lượng Bot (Max: <?= $totalBots ?>)</label>
                    <input type="number" id="maxBots" value="10" min="1" max="<?= $totalBots ?>">
                </div>
                <div class="control-group" style="flex: 1;">
                    <label>Nghỉ giữa chu kỳ (s)</label>
                    <input type="number" id="cooldown" value="5" min="1" max="300">
                </div>
            </div>

            <div class="toggle-wrap">
                <span>Chạy Tự Động (Auto-Run)</span>
                <label class="switch">
                    <input type="checkbox" id="autoRun">
                    <span class="slider"></span>
                </label>
            </div>

            <!-- CỔNG RA LỆNH TRỰC TIẾP -->
            <div class="control-group" style="margin-top: 5px;">
                <label><i class="fa-solid fa-terminal"></i> Ghi Đè Lệnh (Override Command)</label>
                <div style="display:flex; gap: 5px;">
                    <input type="text" id="cmdInput" placeholder="VD: /farm, /chat OmniBot No.1" style="flex:1;">
                    <button class="btn-cmd" onclick="document.getElementById('cmdInput').value = ''"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div style="font-size: 0.7rem; color: var(--text-muted);">Bỏ trống để chạy ngẫu nhiên. Lệnh hỗ trợ: /farm, /chat [text], /pvp</div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 5px;">
                <button id="startBtn" class="btn-huge btn-start" onclick="startCycle()">
                    <i class="fa-solid fa-bolt"></i> BẮT ĐẦU CHU KỲ
                </button>
                <button id="stopBtn" class="btn-huge btn-stop" onclick="stopCycle()" disabled>
                    <i class="fa-solid fa-stop"></i> DỪNG KHẨN CẤP
                </button>
            </div>

            <div class="panel-title" style="margin-top: 5px;"><i class="fa-solid fa-filter"></i> Bộ Lọc Dữ Liệu</div>
            <div class="filter-grid" id="filterContainer">
                <button class="filter-btn active" data-filter="all"><i class="fa-solid fa-globe"></i> Tất Cả</button>
                <button class="filter-btn" data-filter="farm">⛏️ Nông Trại</button>
                <button class="filter-btn" data-filter="pvp">⚔️ PvP/Lãnh Địa</button>
                <button class="filter-btn" data-filter="live">👀 Livestream</button>
                <button class="filter-btn" data-filter="combat">🎮 Game/Ma Thần</button>
                <button class="filter-btn" data-filter="chat">📣 Chat/Khảo Thí</button>
                <button class="filter-btn" data-filter="err"><i class="fa-solid fa-triangle-exclamation"></i> Lỗi (Errors)</button>
            </div>
        </div>

        <!-- Center Panel: Terminal -->
        <div class="terminal-wrapper">
            <div class="term-header">
                <div style="font-family: var(--font-tech); font-size: 0.8rem; color: var(--text-muted);"><i class="fa-solid fa-terminal"></i> BASH // OMNIBOT / OUT</div>
                <div class="term-actions">
                    <label style="font-size: 0.75rem; color: var(--text-muted); display:flex; align-items:center; gap:5px; cursor:pointer;">
                        <input type="checkbox" id="autoScrollCheck" checked style="accent-color: var(--primary);"> Tự cuộn màn hình
                    </label>
                    <button class="term-btn" onclick="clearConsole()"><i class="fa-solid fa-broom"></i> Clear</button>
                </div>
            </div>
            <div class="console-container" id="console">
                <div class="sys-log">[Hệ thống] OmniBot Terminal v3 đã khởi động. Chờ lệnh...</div>
            </div>
        </div>

        <!-- Right Panel: Analytics & Leaderboard -->
        <div class="panel" style="gap: 15px;">
            <div class="panel-title"><i class="fa-solid fa-chart-line"></i> Phân Tích (Live)</div>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-val" id="statCycles">0</div>
                    <div class="stat-lbl">Chu kỳ</div>
                </div>
                <div class="stat-box">
                    <div class="stat-val" id="statLogs" style="color: var(--primary);">0</div>
                    <div class="stat-lbl">Sự kiện</div>
                </div>
            </div>

            <!-- Chart.js Canvas -->
            <div style="background: rgba(0,0,0,0.5); padding: 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); margin-top: 5px;">
                <canvas id="activityChart" height="180"></canvas>
            </div>

            <div class="panel-title" style="margin-top: 5px;"><i class="fa-solid fa-trophy"></i> Bảng Phong Thần Bot</div>
            <div id="leaderboard" style="display:flex; flex-direction: column; gap: 8px;">
                <div style="text-align:center; font-size: 0.8rem; color: var(--text-muted); padding: 20px 0;">Đang thu thập dữ liệu...</div>
            </div>
        </div>

    </div>

    <script>
        let isRunning = false;
        let controller = null;
        let autoRunTimeout = null;
        
        // Stats & Chart Data
        let cyclesCount = 0;
        let logsCount = 0;
        let chartData = { farm: 0, pvp: 0, live: 0, combat: 0, chat: 0 };
        let activityChart = null;

        // Audio Context Setup
        let audioCtx = null;
        function initAudio() {
            if (!audioCtx) {
                audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
        }
        function playSound(type) {
            if (!document.getElementById('audioToggle').checked) return;
            initAudio();
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.connect(gain);
            gain.connect(audioCtx.destination);

            if (type === 'blip') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(800, audioCtx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(1200, audioCtx.currentTime + 0.05);
                gain.gain.setValueAtTime(0.05, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.1);
                osc.start(); osc.stop(audioCtx.currentTime + 0.1);
            } else if (type === 'win') {
                osc.type = 'square';
                osc.frequency.setValueAtTime(440, audioCtx.currentTime);
                osc.frequency.setValueAtTime(660, audioCtx.currentTime + 0.1);
                gain.gain.setValueAtTime(0.05, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.3);
                osc.start(); osc.stop(audioCtx.currentTime + 0.3);
            } else if (type === 'err') {
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(150, audioCtx.currentTime);
                gain.gain.setValueAtTime(0.1, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.2);
                osc.start(); osc.stop(audioCtx.currentTime + 0.2);
            }
        }

        // Init Chart
        function initChart() {
            const ctx = document.getElementById('activityChart').getContext('2d');
            activityChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Farm', 'PvP', 'Live', 'Games', 'Chat'],
                    datasets: [{
                        data: [1, 1, 1, 1, 1], // Init dummy data
                        backgroundColor: ['#10b981', '#ef4444', '#f59e0b', '#8b5cf6', '#3b82f6'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    cutout: '75%',
                    animation: { animateScale: true }
                }
            });
        }
        initChart();

        function updateChart() {
            if (activityChart) {
                activityChart.data.datasets[0].data = [
                    chartData.farm, chartData.pvp, chartData.live, chartData.combat, chartData.chat
                ];
                activityChart.update();
            }
        }

        const consoleEl = document.getElementById('console');
        const startBtn = document.getElementById('startBtn');
        const stopBtn = document.getElementById('stopBtn');
        const statusBadge = document.getElementById('runnerStatus');
        const statCycles = document.getElementById('statCycles');
        const statLogs = document.getElementById('statLogs');
        const autoScrollCheck = document.getElementById('autoScrollCheck');

        // Setup Filters
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.filter-btn').forEach(b => {
                    b.classList.remove('active');
                    b.classList.remove('active-error');
                });
                
                const filter = e.target.getAttribute('data-filter') || e.target.closest('.filter-btn').getAttribute('data-filter');
                if (filter === 'err') e.target.closest('.filter-btn').classList.add('active-error');
                else e.target.closest('.filter-btn').classList.add('active');

                consoleEl.className = 'console-container';
                if (filter !== 'all') consoleEl.classList.add(`filter-${filter}`);
                autoScrollIfNeeded();
            });
        });

        function clearConsole() {
            consoleEl.innerHTML = '<div class="sys-log">[Hệ thống] Đã dọn dẹp màn hình.</div>';
            logsCount = 0; statLogs.innerText = '0';
        }

        function autoScrollIfNeeded() {
            if (autoScrollCheck.checked) consoleEl.scrollTop = consoleEl.scrollHeight;
        }

        function formatMoney(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        function renderLeaderboard(data) {
            const lbContainer = document.getElementById('leaderboard');
            lbContainer.innerHTML = '';
            if (!data || data.length === 0) {
                lbContainer.innerHTML = '<div style="text-align:center; font-size: 0.8rem; color: var(--text-muted); padding: 10px 0;">Không có dữ liệu.</div>';
                return;
            }
            data.forEach((bot, idx) => {
                let rankClass = '';
                let icon = '<i class="fa-solid fa-robot"></i>';
                if (idx === 0) { rankClass = 'lb-rank-1'; icon = '<i class="fa-solid fa-crown"></i>'; }
                else if (idx === 1) rankClass = 'lb-rank-2';
                else if (idx === 2) rankClass = 'lb-rank-3';
                
                lbContainer.innerHTML += `
                    <div class="lb-item">
                        <div class="lb-name ${rankClass}">${icon} ${bot.name}</div>
                        <div class="lb-money">${formatMoney(bot.money)}</div>
                    </div>
                `;
            });
        }

        // Logic phân tích nội dung HTML trả về từ server
        function processStreamChunk(tempDiv) {
            let soundPlayed = false;

            const children = Array.from(tempDiv.children); // Clone to array because we might modify DOM
            
            for (let i = 0; i < children.length; i++) {
                const child = children[i];
                
                // Intercept Leaderboard Data
                if (child.classList && child.classList.contains('top-bots-data')) {
                    try {
                        const lbData = JSON.parse(child.innerText);
                        renderLeaderboard(lbData);
                        child.style.display = 'none';
                    } catch (e) {}
                    continue; // Không count là log
                }

                if (child.classList && child.classList.contains('log-item')) {
                    logsCount++;
                    const html = child.innerHTML.toLowerCase();
                    
                    // Filter Assignment
                    if (html.includes('nông trại') || html.includes('⛏️') || html.includes('🎒')) {
                        child.setAttribute('data-filter', 'farm'); chartData.farm++;
                    } else if (html.includes('pvp') || html.includes('lãnh địa') || html.includes('⚔️') || html.includes('🏰')) {
                        child.setAttribute('data-filter', 'pvp'); chartData.pvp++;
                    } else if (html.includes('livestream') || html.includes('👀')) {
                        child.setAttribute('data-filter', 'live'); chartData.live++;
                    } else if (html.includes('chat') || html.includes('khảo thí') || html.includes('📣') || html.includes('🧠')) {
                        child.setAttribute('data-filter', 'chat'); chartData.chat++;
                    } else if (html.includes('lỗi') || html.includes('exception') || html.includes('alert') || html.includes('⚠️') || html.includes('🚨')) {
                        child.setAttribute('data-filter', 'err');
                        if (!soundPlayed) { playSound('err'); soundPlayed = true; }
                    } else {
                        child.setAttribute('data-filter', 'combat'); chartData.combat++;
                    }

                    // VIP Card Upgrade (Hologram)
                    if (html.includes('highlight-money') || html.includes('jackpot') || html.includes('ăn ngập mặt')) {
                        child.classList.add('log-card-vip');
                        if (!soundPlayed) { playSound('win'); soundPlayed = true; }
                    }
                }
            }
            statLogs.innerText = logsCount.toLocaleString();
            updateChart();
            
            if (!soundPlayed && tempDiv.querySelectorAll('.log-item').length > 0) {
                playSound('blip');
            }
        }

        async function startCycle() {
            if (isRunning) return;
            clearTimeout(autoRunTimeout);

            const maxBots = document.getElementById('maxBots').value;
            const cooldown = document.getElementById('cooldown').value;
            const cmdOverride = document.getElementById('cmdInput').value.trim();

            isRunning = true;
            startBtn.disabled = true;
            stopBtn.disabled = false;
            
            statusBadge.className = 'status-indicator status-running';
            statusBadge.innerHTML = '<i class="fa-solid fa-satellite-dish"></i> <span id="statusText">Transmitting...</span>';

            await fetch('bot_engine.php?action=set_status&enabled=1');

            cyclesCount++;
            statCycles.innerText = cyclesCount;

            const timeStr = new Date().toLocaleTimeString();
            consoleEl.innerHTML += `<div class="sys-log" style="color: var(--primary); margin: 15px 0 10px 0; border-top: 1px solid rgba(99,102,241,0.3); padding-top: 10px;">[${timeStr}] >> CYCLE ${cyclesCount} INITIATED (${maxBots} BOTS) <<</div>`;
            if (cmdOverride) {
                consoleEl.innerHTML += `<div class="sys-log" style="color: var(--warn); margin-bottom: 10px;">> COMMAND OVERRIDE ACTIVE: [${cmdOverride}]</div>`;
            }
            autoScrollIfNeeded();
            initAudio();

            controller = new AbortController();
            const signal = controller.signal;

            let queryUrl = `bot_engine.php?max_bots=${maxBots}`;
            if (cmdOverride) queryUrl += `&cmd=${encodeURIComponent(cmdOverride)}`;

            try {
                const response = await fetch(queryUrl, { signal });
                const reader = response.body.getReader();
                const decoder = new TextDecoder();

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    
                    const chunk = decoder.decode(value, { stream: true });
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = chunk;
                    
                    processStreamChunk(tempDiv);
                    
                    while (tempDiv.firstChild) {
                        consoleEl.appendChild(tempDiv.firstChild);
                        if (consoleEl.children.length > 800) consoleEl.removeChild(consoleEl.firstChild);
                    }
                    autoScrollIfNeeded();
                }
            } catch (err) {
                if (err.name === 'AbortError') {
                    consoleEl.innerHTML += `<div class="sys-err">[Hệ thống] ĐÃ NGẮT KẾT NỐI KHẨN CẤP.</div>`;
                    document.getElementById('autoRun').checked = false;
                } else {
                    consoleEl.innerHTML += `<div class="sys-err">[Lỗi] Engine: ${err.message}</div>`;
                }
            } finally {
                isRunning = false;
                if (!document.getElementById('autoRun').checked) {
                    startBtn.disabled = false;
                    stopBtn.disabled = true;
                    statusBadge.className = 'status-indicator status-idle';
                    document.getElementById('statusText').innerText = 'System Idle';
                }
                
                const endTimeStr = new Date().toLocaleTimeString();
                consoleEl.innerHTML += `<div class="sys-ok" style="margin-top: 5px;">[${endTimeStr}] // CYCLE ${cyclesCount} COMPLETE.</div>`;
                autoScrollIfNeeded();

                // Auto Run Logic
                if (document.getElementById('autoRun').checked) {
                    statusBadge.className = 'status-indicator status-idle';
                    statusBadge.innerHTML = `<i class="fa-solid fa-hourglass-half"></i> <span id="statusText">Cooldown (${cooldown}s)...</span>`;
                    
                    autoRunTimeout = setTimeout(() => {
                        startCycle();
                    }, cooldown * 1000);
                }
            }
        }

        function stopCycle() {
            document.getElementById('autoRun').checked = false;
            clearTimeout(autoRunTimeout);
            fetch('bot_engine.php?action=set_status&enabled=0');
            if (controller) controller.abort();
        }
    </script>
</body>
</html>
