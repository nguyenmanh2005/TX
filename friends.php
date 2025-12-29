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

// Kiểm tra bảng friends có tồn tại không
$checkTable = $conn->query("SHOW TABLES LIKE 'friends'");
$friendsTableExists = $checkTable && $checkTable->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bạn Bè - Friends</title>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .friends-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header-friends {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 24px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
            animation: fadeInDown 0.6s ease;
            position: relative;
            overflow: hidden;
        }
        
        .header-friends::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.05) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        .header-friends h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 42px;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 15px;
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
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .tab-content.active {
            display: block;
        }
        
        .search-box {
            background: rgba(255, 255, 255, 0.98);
            padding: 25px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .search-input {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .user-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .user-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .user-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .user-card:hover::before {
            left: 100%;
        }
        
        .user-card:hover {
            transform: translateY(-8px) scale(1.03) rotate(1deg);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2),
                        0 0 0 1px rgba(102, 126, 234, 0.2);
            border-color: #667eea;
        }
        
        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 15px;
            border: 4px solid #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3),
                        0 0 0 0 rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
        }
        
        .user-card:hover .user-avatar {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5),
                        0 0 20px rgba(102, 126, 234, 0.3);
            border-color: #764ba2;
        }
        
        .user-name {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }
        
        .user-money {
            color: #28a745;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin: 5px;
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
        
        .btn:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }
        
        .btn-primary:hover {
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
        
        .btn-success:hover {
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.5);
        }
        
        .btn-danger:hover {
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.5);
        }
        
        .btn:active {
            transform: translateY(-1px) scale(1.02);
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 16px;
            color: #666;
            font-size: 18px;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }
    </style>
</head>
<body>
    <div class="friends-container">
        <div class="header-friends">
            <h1>👥 Bạn Bè</h1>
            <div style="margin-top: 15px; font-size: 18px; color: var(--success-color); font-weight: 600;">
                👤 <?= htmlspecialchars($user['Name']) ?> | 💰 <?= number_format($user['Money'], 0, ',', '.') ?> VNĐ
            </div>
        </div>
        
        <?php if (!$friendsTableExists): ?>
            <div class="no-data">
                <h2>⚠️ Hệ thống bạn bè chưa được kích hoạt</h2>
                <p>Vui lòng chạy file <strong>create_friends_tables.sql</strong> trong database.</p>
            </div>
        <?php else: ?>
            <div class="tabs">
                <button class="tab active" onclick="switchTab('friends')">👥 Bạn Bè</button>
                <button class="tab" onclick="switchTab('requests')">📨 Lời Mời</button>
                <button class="tab" onclick="switchTab('search')">🔍 Tìm Kiếm</button>
            </div>
            
            <!-- Bạn bè -->
            <div id="friends-tab" class="tab-content active">
                <div id="friends-list" class="user-list">
                    <div class="no-data">Đang tải...</div>
                </div>
            </div>
            
            <!-- Lời mời -->
            <div id="requests-tab" class="tab-content">
                <div id="requests-list" class="user-list">
                    <div class="no-data">Đang tải...</div>
                </div>
            </div>
            
            <!-- Tìm kiếm -->
            <div id="search-tab" class="tab-content">
                <div class="search-box">
                    <input type="text" id="search-input" class="search-input" placeholder="Nhập tên người dùng để tìm kiếm..." autocomplete="off">
                </div>
                <div id="search-results" class="user-list">
                    <div class="no-data">Nhập tên để tìm kiếm...</div>
                </div>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="back-link">🏠 Về Trang Chủ</a>
        </div>
    </div>
    
    <script>
        let searchTimeout = null;
        
        function switchTab(tab) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.getElementById(tab + '-tab').classList.add('active');
            event.target.classList.add('active');
            
            if (tab === 'friends') {
                loadFriends();
            } else if (tab === 'requests') {
                loadPendingRequests();
            }
        }
        
        function loadFriends() {
            fetch('api_friends.php?action=get_friends')
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('friends-list');
                    if (data.success && data.friends.length > 0) {
                        container.innerHTML = data.friends.map(friend => `
                            <div class="user-card">
                                <img src="${friend.ImageURL || 'images.ico'}" alt="${friend.Name}" class="user-avatar" onerror="this.src='images.ico'">
                                <div class="user-name">${friend.title_icon || ''} ${friend.Name}</div>
                                <div class="user-money">💰 ${number_format(friend.Money)} VNĐ</div>
                                <button class="btn btn-primary" onclick="openChat(${friend.Iduser}, '${friend.Name}')">💬 Nhắn Tin</button>
                                <button class="btn btn-danger" onclick="removeFriend(${friend.Iduser})">🗑️ Xóa Bạn</button>
                            </div>
                        `).join('');
                    } else {
                        container.innerHTML = '<div class="no-data">Chưa có bạn bè nào. Hãy tìm kiếm và kết bạn nhé!</div>';
                    }
                });
        }
        
        function loadPendingRequests() {
            fetch('api_friends.php?action=get_pending_requests')
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('requests-list');
                    if (data.success && data.requests.length > 0) {
                        container.innerHTML = data.requests.map(req => `
                            <div class="user-card">
                                <img src="${req.ImageURL || 'images.ico'}" alt="${req.Name}" class="user-avatar" onerror="this.src='images.ico'">
                                <div class="user-name">${req.title_icon || ''} ${req.Name}</div>
                                <div class="user-money">💰 ${number_format(req.Money)} VNĐ</div>
                                <button class="btn btn-success" onclick="acceptRequest(${req.Iduser})">✅ Chấp Nhận</button>
                                <button class="btn btn-danger" onclick="removeFriend(${req.Iduser})">❌ Từ Chối</button>
                            </div>
                        `).join('');
                    } else {
                        container.innerHTML = '<div class="no-data">Không có lời mời kết bạn nào.</div>';
                    }
                });
        }
        
        function sendFriendRequest(friendId) {
            const formData = new FormData();
            formData.append('action', 'send_friend_request');
            formData.append('friend_id', friendId);
            
            fetch('api_friends.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                Swal.fire({
                    icon: data.success ? 'success' : 'error',
                    title: data.success ? 'Thành công!' : 'Lỗi!',
                    text: data.message
                });
                if (data.success) {
                    loadSearchResults();
                }
            });
        }
        
        function acceptRequest(friendId) {
            const formData = new FormData();
            formData.append('action', 'accept_friend_request');
            formData.append('friend_id', friendId);
            
            fetch('api_friends.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                Swal.fire({
                    icon: data.success ? 'success' : 'error',
                    title: data.success ? 'Thành công!' : 'Lỗi!',
                    text: data.message
                });
                if (data.success) {
                    loadPendingRequests();
                    loadFriends();
                }
            });
        }
        
        function removeFriend(friendId) {
            Swal.fire({
                title: 'Xác nhận',
                text: 'Bạn có chắc muốn xóa bạn bè này?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Xóa',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'remove_friend');
                    formData.append('friend_id', friendId);
                    
                    fetch('api_friends.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        Swal.fire({
                            icon: data.success ? 'success' : 'error',
                            title: data.success ? 'Thành công!' : 'Lỗi!',
                            text: data.message
                        });
                        if (data.success) {
                            loadFriends();
                            loadPendingRequests();
                            loadSearchResults();
                        }
                    });
                }
            });
        }
        
        function openChat(friendId, friendName) {
            window.location.href = `private_message.php?friend_id=${friendId}&friend_name=${encodeURIComponent(friendName)}`;
        }
        
        function loadSearchResults() {
            const query = document.getElementById('search-input').value.trim();
            const container = document.getElementById('search-results');
            
            if (query.length < 2) {
                container.innerHTML = '<div class="no-data">Nhập ít nhất 2 ký tự để tìm kiếm...</div>';
                return;
            }
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                fetch(`api_friends.php?action=search_users&search=${encodeURIComponent(query)}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.users.length > 0) {
                            container.innerHTML = data.users.map(user => `
                                <div class="user-card">
                                    <img src="${user.ImageURL || 'images.ico'}" alt="${user.Name}" class="user-avatar" onerror="this.src='images.ico'">
                                    <div class="user-name">${user.title_icon || ''} ${user.Name}</div>
                                    <div class="user-money">💰 ${number_format(user.Money)} VNĐ</div>
                                    <button class="btn btn-primary" onclick="sendFriendRequest(${user.Iduser})">➕ Kết Bạn</button>
                                </div>
                            `).join('');
                        } else {
                            container.innerHTML = '<div class="no-data">Không tìm thấy người dùng nào.</div>';
                        }
                    });
            }, 300);
        }
        
        function number_format(number) {
            return new Intl.NumberFormat('vi-VN').format(number);
        }
        
        // Event listeners
        document.getElementById('search-input').addEventListener('input', loadSearchResults);
        
        // Load initial data
        loadFriends();
        
        // Cursor fix
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
            
            const interactiveElements = document.querySelectorAll('button, a, input, label, select');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            });
        });
    </script>
</body>
</html>

