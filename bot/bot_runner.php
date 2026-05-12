<?php
/**
 * 🚀 Bot Army Runner - Web Controller v1.0
 * Run and monitor bot cycles from the browser.
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
    <title>Bot Army Runner | Web Controller</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #020617;
            --panel: #0f172a;
            --primary: #6366f1;
            --success: #22c55e;
            --warn: #f59e0b;
            --danger: #ef4444;
            --text: #f8fafc;
            --text-dim: #94a3b8;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 900px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        h1 { font-weight: 800; text-align: center; margin-bottom: 30px; background: linear-gradient(135deg, #818cf8, #c084fc); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }

        .card {
            background: var(--panel);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }

        .form-group {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        input[type="number"] {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            width: 100px;
            font-size: 16px;
            font-family: 'Outfit';
        }

        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
            filter: brightness(1.1);
        }

        .btn:disabled {
            background: #475569;
            cursor: not-allowed;
            transform: none;
        }

        .console {
            background: #000;
            border-radius: 15px;
            padding: 20px;
            height: 500px;
            overflow-y: auto;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            line-height: 1.6;
            border: 1px solid rgba(255,255,255,0.1);
            position: relative;
        }

        .console::-webkit-scrollbar {
            width: 8px;
        }

        .console::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-idle { background: rgba(148, 163, 184, 0.1); color: #94a3b8; }
        .status-running { background: rgba(34, 197, 94, 0.1); color: #22c55e; animation: pulse 2s infinite; }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .log-entry {
            margin-bottom: 5px;
            border-left: 2px solid rgba(255,255,255,0.05);
            padding-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>🚀 Bot Army Web Runner</h1>
                <p style="color: var(--text-dim); margin: 5px 0 0 0;">Điều khiển quân đoàn bot trực tiếp từ trình duyệt</p>
            </div>
            <div id="runnerStatus" class="status-badge status-idle">Sẵn sàng</div>
        </div>

        <div class="card">
            <div class="form-group">
                <div style="flex: 1;">
                    <label style="display: block; font-size: 12px; color: var(--text-dim); margin-bottom: 8px;">Số lượng Bot (Max: <?= $totalBots ?>)</label>
                    <input type="number" id="maxBots" value="10" min="1" max="<?= $totalBots ?>">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-size: 12px; color: var(--text-dim); margin-bottom: 8px;">Nghỉ giữa chu kỳ (giây)</label>
                    <input type="number" id="cooldown" value="5" min="1" max="300">
                </div>
                <div style="display: flex; align-items: center; gap: 8px; margin-top: 20px;">
                    <input type="checkbox" id="autoRun" style="width: 20px; height: 20px; accent-color: var(--primary);">
                    <label for="autoRun" style="font-size: 14px; font-weight: 600;">Chạy liên tục</label>
                </div>
                <button id="startBtn" class="btn" onclick="startCycle()">
                    <span>▶️ BẮT ĐẦU</span>
                </button>
                <button id="stopBtn" class="btn" style="background: var(--danger);" onclick="stopCycle()" disabled>
                    <span>⏹️ DỪNG LẠI</span>
                </button>
            </div>
        </div>

        <div class="card" style="padding: 10px;">
            <div id="console" class="console">
                <div style="color: #475569;">[Hệ thống] Nhấn "Bắt đầu" để kích hoạt quân đoàn...</div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php" style="color: var(--primary); text-decoration: none; font-size: 14px; font-weight: 600;">← Quay lại Dashboard</a>
        </div>
    </div>

    <script>
        let isRunning = false;
        let controller = null;
        let autoRunTimeout = null;

        async function startCycle() {
            if (isRunning) return;
            clearTimeout(autoRunTimeout);

            const maxBots = document.getElementById('maxBots').value;
            const cooldown = document.getElementById('cooldown').value;
            const isAuto = document.getElementById('autoRun').checked;
            const consoleEl = document.getElementById('console');
            const startBtn = document.getElementById('startBtn');
            const stopBtn = document.getElementById('stopBtn');
            const statusBadge = document.getElementById('runnerStatus');

            isRunning = true;
            startBtn.disabled = true;
            stopBtn.disabled = false;
            statusBadge.className = 'status-badge status-running';
            statusBadge.innerText = 'Đang chạy';

            consoleEl.innerHTML += `<div style="color: var(--primary); margin: 15px 0 10px 0; border-top: 1px dashed rgba(99, 102, 241, 0.3); padding-top: 10px;">[${new Date().toLocaleTimeString()}] Bắt đầu chu kỳ mới (${maxBots} Bot)...</div>`;

            controller = new AbortController();
            const signal = controller.signal;

            try {
                const response = await fetch(`bot_engine.php?max_bots=${maxBots}`, { signal });
                const reader = response.body.getReader();
                const decoder = new TextDecoder();

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    
                    const chunk = decoder.decode(value, { stream: true });
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = chunk;
                    
                    while (tempDiv.firstChild) {
                        consoleEl.appendChild(tempDiv.firstChild);
                    }
                    consoleEl.scrollTop = consoleEl.scrollHeight;
                }
            } catch (err) {
                if (err.name === 'AbortError') {
                    consoleEl.innerHTML += `<div style="color: var(--warn);">[Hệ thống] Đã dừng chu kỳ.</div>`;
                    document.getElementById('autoRun').checked = false; // Tắt auto nếu bấm dừng
                } else {
                    consoleEl.innerHTML += `<div style="color: var(--danger);">[Lỗi] Engine: ${err.message}</div>`;
                }
            } finally {
                isRunning = false;
                if (!document.getElementById('autoRun').checked) {
                    startBtn.disabled = false;
                    stopBtn.disabled = true;
                    statusBadge.className = 'status-badge status-idle';
                    statusBadge.innerText = 'Sẵn sàng';
                }
                
                consoleEl.innerHTML += `<div style="color: var(--success); margin-top: 5px;">[${new Date().toLocaleTimeString()}] Chu kỳ hoàn tất.</div>`;
                consoleEl.scrollTop = consoleEl.scrollHeight;

                // Auto Run Logic
                if (document.getElementById('autoRun').checked) {
                    statusBadge.innerText = `Nghỉ (${cooldown}s)`;
                    statusBadge.className = 'status-badge status-idle';
                    autoRunTimeout = setTimeout(() => {
                        startCycle();
                    }, cooldown * 1000);
                }
            }
        }

        function stopCycle() {
            document.getElementById('autoRun').checked = false;
            clearTimeout(autoRunTimeout);
            if (controller) {
                controller.abort();
            }
        }
    </script>
</body>
</html>
