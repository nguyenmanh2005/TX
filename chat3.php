<?php
session_start();
// Cho phép xem nếu là admin hoặc có thể là public nếu user muốn, nhưng thường admin bot thì nên bảo mật.
// Tuy nhiên để test dễ dàng, tôi sẽ làm nó đơn giản.

require 'db_connect.php';
require_once 'load_theme.php';

if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #1a2a6c 0%, #b21f1f 50%, #fdbb2d 100%)';
}

// Lấy tin nhắn từ "Admin Bot" hoặc các báo cáo hệ thống
if (isset($_GET['action']) && $_GET['action'] === 'load') {
    $result = $conn->query("
        SELECT id, username, message, created_at, avatar
        FROM chat_messages 
        WHERE username = 'Admin Tester Bot' OR message LIKE '[TESTER]%'
        ORDER BY id DESC LIMIT 100
    ");
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode(array_reverse($messages));
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    $conn->query("DELETE FROM chat_messages WHERE username = 'Admin Tester Bot' OR message LIKE '[TESTER]%'");
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'stop_signal') {
    $flagFile = __DIR__ . '/bot/scan_stop.flag';
    if (isset($_GET['clear'])) {
        if (file_exists($flagFile)) @unlink($flagFile);
    } else {
        file_put_contents($flagFile, 'STOP');
    }
    echo json_encode(['status' => 'success']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hệ Thống Báo Cáo Admin Bot</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <style>
        body { 
            font-family: 'Outfit', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: white;
            padding: 20px;
            margin: 0;
        }
        .report-page-container {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(15px);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .report-header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        h1 {
            margin: 0;
            color: #ff4e50;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 28px;
            text-shadow: 0 0 20px rgba(255, 78, 80, 0.3);
        }
        .controls-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        #report-box {
            height: calc(100vh - 350px);
            min-height: 500px;
            overflow-y: auto;
            padding: 30px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.1);
        }
        .report-item {
            display: flex;
            margin-bottom: 15px;
            padding: 18px;
            border-radius: 16px;
            background: #f8fafc;
            border-left: 6px solid #64748b;
            color: #1e293b;
            animation: messageSlideIn 0.3s ease-out;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        @keyframes messageSlideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .report-item.success { border-left-color: #10b981; background: #f0fdf4; }
        .report-item.warning { border-left-color: #f59e0b; background: #fffbeb; }
        .report-item.error { border-left-color: #ef4444; background: #fef2f2; }
        
        .avatar-img {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            margin-right: 20px;
            flex-shrink: 0;
            border: 2px solid rgba(0,0,0,0.05);
            object-fit: cover;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-weight: 700;
            color: #334155;
            font-size: 15px;
        }
        .report-content {
            white-space: pre-wrap;
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            line-height: 1.6;
            color: #475569;
        }
        .timestamp {
            font-size: 12px;
            color: #94a3b8;
            font-weight: 400;
        }
        
        /* Buttons */
        .btn-base {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            border: none;
            cursor: pointer;
        }
        .btn-base:hover {
            transform: translateY(-2px);
            filter: brightness(1.1);
        }
        .btn-run { background: #10b981; color: white; box-shadow: 0 4px 14px 0 rgba(16, 185, 129, 0.39); }
        .btn-stop { background: #ef4444; color: white; box-shadow: 0 4px 14px 0 rgba(239, 68, 68, 0.39); }
        .btn-refresh { background: #3b82f6; color: white; box-shadow: 0 4px 14px 0 rgba(59, 130, 246, 0.39); }
        .btn-clear { background: #64748b; color: white; }
        
        #scan-status {
            text-align: center;
            margin-bottom: 20px;
            padding: 12px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #34d399;
            font-weight: 600;
        }

        .spinner {
            display: inline-block;
            animation: spin 2s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        #report-box::-webkit-scrollbar { width: 8px; }
        #report-box::-webkit-scrollbar-track { background: transparent; }
        #report-box::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        #report-box::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
    </style>
</head>
<body>
    <div class="report-page-container">
        <div class="report-header-section">
            <h1>🛡️ Admin Bot Health Reports</h1>
            <div class="controls-row">
                <button onclick="startScan()" id="btn-run-scan" class="btn-base btn-run">🚀 Quét Hệ Thống</button>
                <button onclick="stopScan()" id="btn-stop-scan" class="btn-base btn-stop" style="display:none;">⏹️ Dừng Quét</button>
                <button onclick="loadReports()" class="btn-base btn-refresh">🔄 Làm mới</button>
                <button onclick="clearReports()" class="btn-base btn-clear">🗑️ Xóa Log</button>
                <a href="bot/index.php" class="btn-base btn-clear" style="background: rgba(255,255,255,0.05); color: #94a3b8;">← Quay lại</a>
            </div>
        </div>

        <div id="scan-status" style="display:none;">
            <span class="spinner">⏳</span> Đang quét hệ thống... <span id="scan-progress"></span>
        </div>

        <div id="report-box">
            <!-- Messages will be loaded here -->
        </div>
    </div>

    <script>
        let isScanning = false;
        let lastMessageId = 0;
        let isInitialLoad = true;

        function startScan() {
            if (isScanning) return;
            
            const btn = document.getElementById('btn-run-scan');
            const stopBtn = document.getElementById('btn-stop-scan');
            const status = document.getElementById('scan-status');
            
            // Xóa tín hiệu dừng cũ (nếu có)
            fetch('chat3.php?action=stop_signal&clear=1'); 

            isScanning = true;
            btn.style.display = 'none';
            stopBtn.style.display = 'block';
            status.style.display = 'block';
            
            // Tự động load report mỗi 3 giây trong khi quét
            const refreshInterval = setInterval(loadReports, 3000);
            
            fetch('bot/tester_bot.php')
                .then(res => res.text())
                .then(text => {
                    clearInterval(refreshInterval);
                    loadReports();
                    status.innerHTML = text.includes('DỪNG') ? '⏹️ Đã dừng quét theo yêu cầu.' : '✅ Quét hoàn tất!';
                    setTimeout(() => {
                        status.style.display = 'none';
                        status.innerHTML = '<span class="spinner">⏳</span> Đang quét hệ thống...';
                        btn.style.display = 'block';
                        stopBtn.style.display = 'none';
                        isScanning = false;
                    }, 5000);
                })
                .catch(err => {
                    clearInterval(refreshInterval);
                    isScanning = false;
                    btn.style.display = 'block';
                    stopBtn.style.display = 'none';
                });
        }

        function stopScan() {
            if (!confirm('Dừng quá trình quét ngay lập tức?')) return;
            fetch('chat3.php?action=stop_signal');
        }

        function loadReports() {
            const box = document.getElementById('report-box');
            fetch('chat3.php?action=load')
                .then(res => res.json())
                .then(data => {
                    if (!data || data.length === 0) {
                        if (isInitialLoad) box.innerHTML = '<div style="text-align:center; padding: 20px; color: #888;">Chưa có báo cáo nào.</div>';
                        return;
                    }
                    
                    let newMessages = [];
                    if (isInitialLoad) {
                        box.innerHTML = '';
                        newMessages = data;
                        isInitialLoad = false;
                    } else {
                        newMessages = data.filter(msg => parseInt(msg.id) > lastMessageId);
                    }

                    if (newMessages.length > 0) {
                        newMessages.forEach(msg => {
                            const div = document.createElement('div');
                            let type = 'info';
                            if (msg.message.includes('LỖI') || msg.message.includes('ERROR') || msg.message.includes('⚠️')) type = 'error';
                            else if (msg.message.includes('CẢNH BÁO') || msg.message.includes('WARNING')) type = 'warning';
                            else if (msg.message.includes('THÀNH CÔNG') || msg.message.includes('SUCCESS') || msg.message.includes('✅')) type = 'success';
                            
                            const avatarUrl = msg.avatar || 'https://cdn-icons-png.flaticon.com/512/2583/2583150.png';

                            div.className = `report-item ${type}`;
                            div.innerHTML = `
                                <img src="${avatarUrl}" class="avatar-img" onerror="this.src='images.ico'">
                                <div style="flex:1">
                                    <div class="report-header">
                                        <span>${msg.username}</span>
                                        <span class="timestamp">${msg.created_at}</span>
                                    </div>
                                    <div class="report-content">${msg.message}</div>
                                </div>
                            `;
                            box.appendChild(div);

                            if (parseInt(msg.id) > lastMessageId) {
                                lastMessageId = parseInt(msg.id);
                            }
                        });
                        box.scrollTop = box.scrollHeight;
                    }
                });
        }

        function clearReports() {
            if (!confirm('Bạn có chắc chắn muốn xóa tất cả báo cáo của Tester Bot?')) return;
            fetch('chat3.php?action=clear')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        const box = document.getElementById('report-box');
                        box.innerHTML = '<div style="text-align:center; padding: 20px; color: #888;">Chưa có báo cáo nào.</div>';
                        lastMessageId = 0;
                        isInitialLoad = true;
                    }
                });
        }

        loadReports();
        setInterval(loadReports, 10000);
    </script>
</body>
</html>
