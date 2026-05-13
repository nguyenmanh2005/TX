<?php
session_start();
require_once 'db_connect.php';
require_once 'admin_helper.php';
require_once 'load_theme.php';

// Security Check
if (!isAdmin($conn, (int)($_SESSION['Iduser'] ?? 0))) {
    header("Location: login.php?error=unauthorized");
    exit();
}

if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)';
}

// AJAX: Load recent findings from DB
if (isset($_GET['action']) && $_GET['action'] === 'load_logs') {
    $result = $conn->query("
        SELECT id, message, created_at
        FROM chat_messages 
        WHERE username = 'Admin Tester Bot'
        ORDER BY id DESC LIMIT 50
    ");
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode($logs);
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛡️ Security Dashboard | Admin Tester Bot</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #ef4444;
            --secondary: #3b82f6;
            --success: #10b981;
            --warning: #f59e0b;
            --dark: #0f172a;
            --glass: rgba(30, 41, 59, 0.7);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #f8fafc;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }

        .dashboard {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
        }

        .card {
            background: var(--glass);
            backdrop-filter: blur(12px);
            border-radius: 24px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 28px;
            background: linear-gradient(to right, #ff4e50, #f9d423);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 20px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .stat-value { font-size: 32px; font-weight: 700; display: block; }
        .stat-label { font-size: 14px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; }

        .progress-container {
            margin: 20px 0;
            display: none;
        }

        .progress-bar {
            height: 12px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #10b981);
            width: 0%;
            transition: width 0.3s ease;
        }

        .scan-controls {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-primary { background: var(--secondary); color: white; }
        .btn-danger { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        
        .btn:hover { transform: translateY(-2px); filter: brightness(1.1); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .results-table th {
            text-align: left;
            padding: 15px;
            color: #94a3b8;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            font-weight: 600;
        }

        .results-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-critical { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid #ef4444; }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 1px solid #f59e0b; }
        .badge-ok { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid #10b981; }

        .log-container {
            max-height: 300px;
            overflow-y: auto;
            background: rgba(0,0,0,0.2);
            border-radius: 12px;
            padding: 15px;
            font-family: monospace;
            font-size: 13px;
            margin-top: 20px;
        }

        .log-entry { margin-bottom: 5px; color: #94a3b8; }
        .log-entry.new { color: #34d399; }

        /* Animation */
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .scanning { animation: pulse 2s infinite; }

    </style>
</head>
<body>

    <div class="dashboard">
        <div class="card">
            <div class="header">
                <h1><i class="fas fa-shield-halved"></i> Security Tester Dashboard</h1>
                <div class="scan-controls">
                    <button id="btnStart" class="btn btn-primary" onclick="startScan()">
                        <i class="fas fa-play"></i> Bắt đầu Quét
                    </button>
                    <button id="btnStop" class="btn btn-danger" onclick="stopScan()" disabled>
                        <i class="fas fa-stop"></i> Dừng Quét
                    </button>
                    <button class="btn btn-success" onclick="exportResults()">
                        <i class="fas fa-file-export"></i> Xuất JSON
                    </button>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <span id="statTotal" class="stat-value">0</span>
                    <span class="stat-label">Tổng số File</span>
                </div>
                <div class="stat-card" style="color: #ef4444;">
                    <span id="statCritical" class="stat-value">0</span>
                    <span class="stat-label">Nguy cơ Cao</span>
                </div>
                <div class="stat-card" style="color: #f59e0b;">
                    <span id="statWarning" class="stat-value">0</span>
                    <span class="stat-label">Cảnh báo</span>
                </div>
                <div class="stat-card" style="color: #10b981;">
                    <span id="statOk" class="stat-value">0</span>
                    <span class="stat-label">An toàn</span>
                </div>
            </div>

            <div id="progressBox" class="progress-container">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span id="scanStatusText">Đang quét...</span>
                    <span id="progressPercent">0%</span>
                </div>
                <div class="progress-bar">
                    <div id="progressFill" class="progress-fill"></div>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Mức độ</th>
                            <th>Vị trí</th>
                            <th>Vấn đề phát hiện</th>
                        </tr>
                    </thead>
                    <tbody id="resultsBody">
                        <tr>
                            <td colspan="3" style="text-align: center; color: #475569;">Chưa có dữ liệu quét. Nhấn "Bắt đầu Quét" để khởi động.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="log-container" id="logContainer">
                <div class="log-entry">--- Sẵn sàng quét hệ thống ---</div>
            </div>
        </div>
    </div>

    <script>
        let isScanning = false;
        let scanResults = null;

        function addLog(msg, isNew = false) {
            const container = document.getElementById('logContainer');
            const entry = document.createElement('div');
            entry.className = 'log-entry' + (isNew ? ' new' : '');
            entry.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
            container.appendChild(entry);
            container.scrollTop = container.scrollHeight;
        }

        async function startScan() {
            if (isScanning) return;
            
            isScanning = true;
            document.getElementById('btnStart').disabled = true;
            document.getElementById('btnStop').disabled = false;
            document.getElementById('progressBox').style.display = 'block';
            document.getElementById('resultsBody').innerHTML = '';
            
            addLog("Bắt đầu quá trình quét bảo mật v2.0...", true);
            
            try {
                const response = await fetch('bot/tester_bot.php?action=scan');
                const data = await response.json();
                
                scanResults = data;
                renderResults(data);
                addLog("Quét hoàn tất!");
            } catch (err) {
                addLog("Lỗi khi quét: " + err.message);
                console.error(err);
            } finally {
                isScanning = false;
                document.getElementById('btnStart').disabled = false;
                document.getElementById('btnStop').disabled = true;
            }
        }

        function stopScan() {
            fetch('bot/tester_bot.php?action=stop')
                .then(() => addLog("Đã gửi tín hiệu dừng..."))
                .catch(err => console.error(err));
        }

        function renderResults(data) {
            const tbody = document.getElementById('resultsBody');
            tbody.innerHTML = '';
            
            let critical = 0;
            let warning = 0;
            
            if (data.findings.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: #10b981;">✅ Không tìm thấy lỗ hổng nào!</td></tr>';
            } else {
                data.findings.forEach(item => {
                    if (item.type === 'CRITICAL') critical++;
                    if (item.type === 'WARNING') warning++;
                    
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><span class="badge badge-${item.type.toLowerCase()}">${item.type}</span></td>
                        <td><code>${item.file}</code></td>
                        <td>${item.issue}</td>
                    `;
                    tbody.appendChild(tr);
                });
            }
            
            document.getElementById('statTotal').textContent = data.total_files;
            document.getElementById('statCritical').textContent = critical;
            document.getElementById('statWarning').textContent = warning;
            document.getElementById('statOk').textContent = data.total_files - (critical + warning);
            
            document.getElementById('progressFill').style.width = '100%';
            document.getElementById('progressPercent').textContent = '100%';
            document.getElementById('scanStatusText').textContent = 'Hoàn tất!';
        }

        function exportResults() {
            if (!scanResults) return alert("Chưa có kết quả để xuất!");
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(scanResults, null, 2));
            const downloadAnchorNode = document.createElement('a');
            downloadAnchorNode.setAttribute("href",     dataStr);
            downloadAnchorNode.setAttribute("download", "security_scan_report.json");
            document.body.appendChild(downloadAnchorNode);
            downloadAnchorNode.click();
            downloadAnchorNode.remove();
        }

        // Poll logs from DB every 3s to show bot activity even if scan was triggered elsewhere
        async function syncLogs() {
            try {
                const res = await fetch('chat3.php?action=load_logs');
                const logs = await res.json();
                // Optional: sync UI if needed
            } catch(e) {}
        }

        setInterval(syncLogs, 3000);

    </script>
</body>
</html>