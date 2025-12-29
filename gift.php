<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

// Load theme
require_once 'load_theme.php';

// Đảm bảo $bgGradientCSS có giá trị
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

$userId = $_SESSION['Iduser'];

// Lấy thông tin người dùng
$sql = "SELECT Iduser, Name, Money FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Kiểm tra bảng gifts có tồn tại không
$checkTable = $conn->query("SHOW TABLES LIKE 'gifts'");
$giftsTableExists = $checkTable && $checkTable->num_rows > 0;

if (!$giftsTableExists) {
    die("⚠️ Hệ thống Gift chưa được kích hoạt! Vui lòng chạy file create_gift_tables.sql trước.");
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tặng Quà - Gift System</title>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            animation: fadeIn 0.6s ease;
            position: relative;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: slideUp 0.6s ease;
            position: relative;
            z-index: 1;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 24px;
            padding: 2px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.3), rgba(118, 75, 162, 0.3));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .container:hover::before {
            opacity: 1;
        }

        h1 {
            text-align: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 40px;
            font-size: 42px;
            font-weight: 800;
            letter-spacing: -1px;
            text-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .tabs {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            background: rgba(247, 247, 247, 0.8);
            padding: 8px;
            border-radius: 16px;
        }

        .tab {
            padding: 16px 32px;
            background: transparent;
            border: 2px solid transparent;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            color: #666;
            position: relative;
            overflow: hidden;
        }

        .tab::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: left 0.3s ease;
            z-index: -1;
        }

        .tab.active {
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .tab.active::before {
            left: 0;
        }

        .tab:hover:not(.active) {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            transform: translateY(-2px);
        }

        .tab-content {
            display: none;
            animation: fadeInContent 0.4s ease;
        }

        @keyframes fadeInContent {
            from {
                opacity: 0;
                transform: translateY(10px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            margin-bottom: 10px;
            font-weight: 700;
            color: #333;
            font-size: 15px;
            letter-spacing: 0.3px;
        }

        input, select, textarea {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: #fff;
            font-family: inherit;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        input:hover, select:hover, textarea:hover {
            border-color: #bbb;
        }

        textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.6;
        }

        .user-search {
            position: relative;
        }

        .user-list {
            max-height: 250px;
            overflow-y: auto;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            background: white;
            display: none;
            position: absolute;
            width: 100%;
            z-index: 100;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15),
                        0 0 0 1px rgba(102, 126, 234, 0.1);
            margin-top: 5px;
            animation: slideDown 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .user-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .user-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .user-list::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
        }
        
        .user-list::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }

        .user-item {
            padding: 14px 18px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .user-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transform: scaleY(0);
            transition: transform 0.3s ease;
            transform-origin: bottom;
        }

        .user-item:hover {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.1) 0%, rgba(102, 126, 234, 0.05) 100%);
            padding-left: 24px;
            transform: translateX(5px);
        }
        
        .user-item:hover::before {
            transform: scaleY(1);
        }

        .user-item:last-child {
            border-bottom: none;
            border-radius: 0 0 10px 10px;
        }

        .user-item:first-child {
            border-radius: 10px 10px 0 0;
        }

        .btn {
            padding: 16px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3),
                        0 0 0 0 rgba(102, 126, 234, 0.4);
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .btn:hover::after {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5),
                        0 0 20px rgba(102, 126, 234, 0.3);
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }

        .btn:active {
            transform: translateY(-1px) scale(0.98);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .alert {
            padding: 18px 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            border-left: 4px solid;
            animation: slideInRight 0.4s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-success {
            background: linear-gradient(90deg, rgba(40, 167, 69, 0.1) 0%, rgba(40, 167, 69, 0.05) 100%);
            color: #155724;
            border-left-color: #28a745;
        }

        .alert-error {
            background: linear-gradient(90deg, rgba(220, 53, 69, 0.1) 0%, rgba(220, 53, 69, 0.05) 100%);
            color: #721c24;
            border-left-color: #dc3545;
        }

        .alert-info {
            background: linear-gradient(90deg, rgba(23, 162, 184, 0.1) 0%, rgba(23, 162, 184, 0.05) 100%);
            color: #0c5460;
            border-left-color: #17a2b8;
        }

        .history-item {
            padding: 20px;
            border: 2px solid #e0e0e0;
            border-radius: 14px;
            margin-bottom: 16px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(247, 247, 247, 0.9) 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .history-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            transition: width 0.3s ease, opacity 0.3s ease;
        }
        
        .history-item::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at right, rgba(102, 126, 234, 0.05) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .history-item:hover {
            transform: translateX(8px) scale(1.01);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15),
                        0 0 0 1px rgba(102, 126, 234, 0.1);
        }

        .history-item:hover::before {
            width: 6px;
        }
        
        .history-item:hover::after {
            opacity: 1;
        }

        .history-item.sent {
            border-left-color: #667eea;
        }

        .history-item.sent::before {
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        }

        .history-item.received {
            border-left-color: #28a745;
        }

        .history-item.received::before {
            background: linear-gradient(180deg, #28a745 0%, #20c997 100%);
        }

        .item-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }

        .item-card {
            border: 2px solid #e0e0e0;
            border-radius: 14px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
            position: relative;
            overflow: hidden;
        }

        .item-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.15) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .item-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s ease;
        }

        .item-card:hover::before {
            opacity: 1;
        }
        
        .item-card:hover::after {
            left: 100%;
        }

        .item-card:hover {
            border-color: #667eea;
            transform: translateY(-5px) scale(1.03) rotate(1deg);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3),
                        0 0 20px rgba(102, 126, 234, 0.2);
        }

        .item-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.1) 100%);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4),
                        0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: scale(1.05);
            animation: pulse 2s ease-in-out infinite;
        }

        .item-card.selected::before {
            opacity: 1;
        }

        .daily-limit {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.15) 0%, rgba(255, 235, 59, 0.1) 100%);
            border: 2px solid #ffc107;
            border-radius: 14px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.2);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 4px 15px rgba(255, 193, 7, 0.2);
            }
            50% {
                box-shadow: 0 4px 25px rgba(255, 193, 7, 0.4);
            }
        }

        .money-display {
            font-size: 28px;
            font-weight: 800;
            margin: 30px 0;
            text-align: center;
            padding: 20px;
            background: rgba(40, 167, 69, 0.1);
            border-radius: 14px;
            border: 2px solid rgba(40, 167, 69, 0.2);
            color: #28a745;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);
        }

        .money-display span {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
                border-radius: 16px;
            }

            h1 {
                font-size: 32px;
            }

            .tabs {
                flex-direction: column;
            }

            .tab {
                width: 100%;
            }

            .item-grid {
                grid-template-columns: 1fr;
            }

            .money-display {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎁 Tặng Quà</h1>
        <div class="money-display">💰 Số dư: <span><?= number_format($user['Money'], 0, ',', '.') ?></span> VNĐ</div>

        <div class="tabs">
            <button class="tab active" onclick="switchTab('send')">💌 Tặng Quà</button>
            <button class="tab" onclick="switchTab('history')">📜 Lịch Sử</button>
        </div>

        <div id="send-tab" class="tab-content active">
            <div class="daily-limit">
                <strong>📊 Giới hạn tặng quà:</strong> <span id="daily-count">0</span> / 10 lần/ngày
            </div>

            <div class="tabs">
                <button class="tab active" onclick="switchGiftType('money')">💰 Tặng Tiền</button>
                <button class="tab" onclick="switchGiftType('item')">🎁 Tặng Item</button>
            </div>

            <!-- Tặng tiền -->
            <div id="money-form" class="tab-content active">
                <form id="send-money-form">
                    <div class="form-group">
                        <label>👤 Tìm người nhận:</label>
                        <div class="user-search">
                            <input type="text" id="user-search" placeholder="Nhập tên người dùng..." autocomplete="off">
                            <div id="user-list" class="user-list"></div>
                        </div>
                        <input type="hidden" id="to-user-id">
                        <div id="selected-user" style="margin-top: 10px; padding: 10px; background: #e7f3ff; border-radius: 8px; display: none;">
                            <strong>Đã chọn:</strong> <span id="selected-user-name"></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>💰 Số tiền:</label>
                        <input type="number" id="gift-amount" placeholder="Nhập số tiền (1 - 100.000.000)" min="1" max="100000000" required>
                    </div>

                    <div class="form-group">
                        <label>💬 Lời nhắn (tùy chọn):</label>
                        <textarea id="gift-message" placeholder="Nhập lời nhắn..."></textarea>
                    </div>

                    <button type="submit" class="btn">🎁 Tặng Quà</button>
                </form>
            </div>

            <!-- Tặng item -->
            <div id="item-form" class="tab-content">
                <form id="send-item-form">
                    <div class="form-group">
                        <label>👤 Tìm người nhận:</label>
                        <div class="user-search">
                            <input type="text" id="user-search-item" placeholder="Nhập tên người dùng..." autocomplete="off">
                            <div id="user-list-item" class="user-list"></div>
                        </div>
                        <input type="hidden" id="to-user-id-item">
                        <div id="selected-user-item" style="margin-top: 10px; padding: 10px; background: #e7f3ff; border-radius: 8px; display: none;">
                            <strong>Đã chọn:</strong> <span id="selected-user-name-item"></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>🎁 Loại item:</label>
                        <select id="item-type" onchange="loadUserItems()">
                            <option value="">-- Chọn loại --</option>
                            <option value="theme">Theme</option>
                            <option value="cursor">Cursor</option>
                            <option value="chat_frame">Chat Frame</option>
                            <option value="avatar_frame">Avatar Frame</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>🎁 Chọn item:</label>
                        <div id="items-container" class="item-grid"></div>
                        <input type="hidden" id="selected-item-id">
                    </div>

                    <div class="form-group">
                        <label>💬 Lời nhắn (tùy chọn):</label>
                        <textarea id="gift-message-item" placeholder="Nhập lời nhắn..."></textarea>
                    </div>

                    <button type="submit" class="btn">🎁 Tặng Quà</button>
                </form>
            </div>

            <div id="alert-container"></div>
        </div>

        <div id="history-tab" class="tab-content">
            <div class="tabs">
                <button class="tab active" onclick="switchHistoryType('all')">📜 Tất Cả</button>
                <button class="tab" onclick="switchHistoryType('sent')">📤 Đã Gửi</button>
                <button class="tab" onclick="switchHistoryType('received')">📥 Đã Nhận</button>
            </div>
            <div id="history-container"></div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="btn" style="text-decoration: none; display: inline-block;">🏠 Về Trang Chủ</a>
        </div>
    </div>

    <script>
        let selectedUserId = null;
        let selectedUserIdItem = null;
        let searchTimeout = null;

        // Switch tab
        function switchTab(tab) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.getElementById(tab + '-tab').classList.add('active');
            event.target.classList.add('active');

            if (tab === 'history') {
                loadHistory('all');
            } else {
                loadDailyCount();
            }
        }

        // Switch gift type
        function switchGiftType(type) {
            document.querySelectorAll('#send-tab .tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('#send-tab .tab').forEach(t => t.classList.remove('active'));
            document.getElementById(type + '-form').classList.add('active');
            event.target.classList.add('active');
        }

        // Switch history type
        function switchHistoryType(type) {
            document.querySelectorAll('#history-tab .tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');
            loadHistory(type);
        }

        // Load daily count
        function loadDailyCount() {
            fetch('api_gift.php?action=get_daily_count')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('daily-count').textContent = data.count;
                    }
                });
        }

        // Search users
        document.getElementById('user-search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            if (query.length < 2) {
                document.getElementById('user-list').style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(() => {
                fetch(`api_gift.php?action=get_users&search=${encodeURIComponent(query)}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const list = document.getElementById('user-list');
                            list.innerHTML = '';
                            if (data.users.length === 0) {
                                list.innerHTML = '<div class="user-item">Không tìm thấy người dùng</div>';
                            } else {
                                data.users.forEach(user => {
                                    const item = document.createElement('div');
                                    item.className = 'user-item';
                                    item.textContent = `${user.name} (${number_format(user.money)} VNĐ)`;
                                    item.onclick = () => selectUser(user.id, user.name, 'money');
                                    list.appendChild(item);
                                });
                            }
                            list.style.display = 'block';
                        }
                    });
            }, 300);
        });

        document.getElementById('user-search-item').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            if (query.length < 2) {
                document.getElementById('user-list-item').style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(() => {
                fetch(`api_gift.php?action=get_users&search=${encodeURIComponent(query)}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const list = document.getElementById('user-list-item');
                            list.innerHTML = '';
                            if (data.users.length === 0) {
                                list.innerHTML = '<div class="user-item">Không tìm thấy người dùng</div>';
                            } else {
                                data.users.forEach(user => {
                                    const item = document.createElement('div');
                                    item.className = 'user-item';
                                    item.textContent = `${user.name} (${number_format(user.money)} VNĐ)`;
                                    item.onclick = () => selectUser(user.id, user.name, 'item');
                                    list.appendChild(item);
                                });
                            }
                            list.style.display = 'block';
                        }
                    });
            }, 300);
        });

        // Select user
        function selectUser(userId, userName, type) {
            if (type === 'money') {
                selectedUserId = userId;
                document.getElementById('to-user-id').value = userId;
                document.getElementById('selected-user-name').textContent = userName;
                document.getElementById('selected-user').style.display = 'block';
                document.getElementById('user-list').style.display = 'none';
                document.getElementById('user-search').value = '';
            } else {
                selectedUserIdItem = userId;
                document.getElementById('to-user-id-item').value = userId;
                document.getElementById('selected-user-name-item').textContent = userName;
                document.getElementById('selected-user-item').style.display = 'block';
                document.getElementById('user-list-item').style.display = 'none';
                document.getElementById('user-search-item').value = '';
            }
        }

        // Load user items
        function loadUserItems() {
            const itemType = document.getElementById('item-type').value;
            if (!itemType) {
                document.getElementById('items-container').innerHTML = '';
                document.getElementById('selected-item-id').value = '';
                return;
            }

            document.getElementById('items-container').innerHTML = '<p>Đang tải items...</p>';
            document.getElementById('selected-item-id').value = '';

            fetch(`api_gift.php?action=get_user_items&item_type=${itemType}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('items-container');
                        if (data.items.length === 0) {
                            container.innerHTML = '<p style="text-align: center; padding: 20px;">Bạn không có item nào để tặng.</p>';
                            return;
                        }

                        container.innerHTML = data.items.map(item => `
                            <div class="item-card" onclick="selectItem(${item.id}, this)">
                                <strong>${item.name}</strong>
                            </div>
                        `).join('');
                    } else {
                        document.getElementById('items-container').innerHTML = `<p style="color: red;">${data.message}</p>`;
                    }
                })
                .catch(() => {
                    document.getElementById('items-container').innerHTML = '<p style="color: red;">Không thể tải items. Vui lòng thử lại.</p>';
                });
        }

        // Select item
        function selectItem(itemId, element) {
            document.querySelectorAll('.item-card').forEach(card => card.classList.remove('selected'));
            element.classList.add('selected');
            document.getElementById('selected-item-id').value = itemId;
        }

        // Send money
        document.getElementById('send-money-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'send_money');
            formData.append('to_user_id', document.getElementById('to-user-id').value);
            formData.append('amount', document.getElementById('gift-amount').value);
            formData.append('message', document.getElementById('gift-message').value);

            fetch('api_gift.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                showAlert(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    document.getElementById('send-money-form').reset();
                    document.getElementById('selected-user').style.display = 'none';
                    selectedUserId = null;
                    loadDailyCount();
                    location.reload(); // Reload để cập nhật số dư
                }
            });
        });

        // Send item
        document.getElementById('send-item-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const itemId = document.getElementById('selected-item-id').value;
            if (!itemId) {
                showAlert('Vui lòng chọn item!', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'send_item');
            formData.append('to_user_id', document.getElementById('to-user-id-item').value);
            formData.append('item_type', document.getElementById('item-type').value);
            formData.append('item_id', itemId);
            formData.append('message', document.getElementById('gift-message-item').value);

            fetch('api_gift.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                showAlert(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    document.getElementById('send-item-form').reset();
                    document.getElementById('selected-user-item').style.display = 'none';
                    document.getElementById('items-container').innerHTML = '';
                    selectedUserIdItem = null;
                    loadDailyCount();
                }
            });
        });

        // Load history
        function loadHistory(type) {
            fetch(`api_gift.php?action=get_history&type=${type}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('history-container');
                        if (data.history.length === 0) {
                            container.innerHTML = '<div class="alert alert-info">Chưa có lịch sử tặng quà.</div>';
                            return;
                        }

                        container.innerHTML = data.history.map(gift => {
                            const isSent = gift.from_user_id == <?= $userId ?>;
                            const otherUser = isSent ? gift.to_user_name : gift.from_user_name;
                            const giftDisplay = gift.gift_type === 'money' 
                                ? `${number_format(gift.gift_value)} VNĐ` 
                                : `${gift.gift_type} #${gift.item_id}`;

                            return `
                                <div class="history-item ${isSent ? 'sent' : 'received'}">
                                    <strong>${isSent ? '📤 Gửi cho' : '📥 Nhận từ'}:</strong> ${otherUser}<br>
                                    <strong>Quà:</strong> ${giftDisplay}<br>
                                    ${gift.message ? `<strong>Lời nhắn:</strong> ${gift.message}<br>` : ''}
                                    <small>${new Date(gift.created_at).toLocaleString('vi-VN')}</small>
                                </div>
                            `;
                        }).join('');
                    }
                });
        }

        // Show alert
        function showAlert(message, type) {
            const container = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            container.innerHTML = '';
            container.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }

        // Number format
        function number_format(number) {
            return new Intl.NumberFormat('vi-VN').format(number);
        }

        // Hide user list when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.user-search')) {
                document.getElementById('user-list').style.display = 'none';
                document.getElementById('user-list-item').style.display = 'none';
            }
        });

        // Load initial data
        loadDailyCount();
    </script>
</body>
</html>

