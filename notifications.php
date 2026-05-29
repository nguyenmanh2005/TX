<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['Iduser'];
require_once 'load_theme.php';

// Đảm bảo $bgGradientCSS có giá trị
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

// Kiểm tra bảng tồn tại
$checkTable = $conn->query("SHOW TABLES LIKE 'user_notifications'");
$tableExists = $checkTable && $checkTable->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔔 Trung Tâm Thông Báo</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
            font-family: 'Outfit', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .notif-container {
            max-width: 950px;
            margin: 40px auto;
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 24px 35px;
            border-radius: 24px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.45);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            width: 100%;
        }

        .header h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 38px;
            font-weight: 800;
            letter-spacing: -1px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 35px;
            border-radius: 24px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.45);
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .search-container {
            margin-bottom: 20px;
        }
        
        .search-input {
            width: 100%;
            padding: 14px 20px 14px 45px;
            border: 2px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            font-size: 15px;
            font-family: inherit;
            background: rgba(255, 255, 255, 0.7) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23666' class='bi bi-search' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0'/%3E%3C/svg%3E") no-repeat 18px center;
            color: #333;
            transition: all 0.3s ease;
            outline: none;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.02);
        }
        
        .search-input:focus {
            border-color: #667eea;
            background-color: rgba(255, 255, 255, 0.95);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        }
        
        .notification-item {
            padding: 20px 24px;
            border-radius: 18px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
            overflow: hidden;
            animation: fadeInUp 0.4s ease both;
        }
        
        .notification-item:last-child {
            margin-bottom: 0;
        }
        
        /* Sidebar gradient border */
        .notification-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            width: 6px;
            background: var(--card-gradient, linear-gradient(135deg, #757f9a, #d7dde8));
            border-top-left-radius: 18px;
            border-bottom-left-radius: 18px;
        }
        
        .notification-item:hover {
            transform: translateY(-3px) scale(1.005);
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border-color: rgba(102, 126, 234, 0.3);
        }
        
        .notification-item.unread {
            background: rgba(102, 126, 234, 0.05);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.04);
        }
        
        .notification-item.unread:hover {
            background: rgba(102, 126, 234, 0.08);
        }
        
        .notification-item.important {
            background: rgba(255, 193, 7, 0.03);
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.03);
        }
        
        .notification-item.important:hover {
            background: rgba(255, 193, 7, 0.07);
        }
        
        .notification-icon {
            font-size: 24px;
            min-width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .notification-content {
            flex: 1;
            padding-right: 15px;
        }
        
        .notification-title {
            font-weight: 700;
            font-size: 17px;
            color: #222;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .notification-text {
            color: #4f4f4f;
            font-size: 14.5px;
            line-height: 1.6;
        }
        
        .notification-time {
            color: #888;
            font-size: 12.5px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .notification-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-shrink: 0;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.25);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.25);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 13px;
            border-radius: 8px;
            min-height: 36px;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-unread {
            background: #dc3545;
            color: white;
        }
        
        .badge-important {
            background: #ffc107;
            color: #222;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 72px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            opacity: 0.8;
        }
        
        .tabs {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
            padding-bottom: 5px;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 12px 24px;
            background: transparent;
            border: none;
            font-weight: 700;
            font-size: 16px;
            color: #666;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab:hover {
            color: #667eea;
        }
        
        .settings-section {
            margin-top: 30px;
            animation: slideUp 0.4s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        #settings-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .setting-item {
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 18px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
        }
        
        .setting-item:hover {
            background: rgba(255, 255, 255, 0.95);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06);
            border-color: rgba(102, 126, 234, 0.3);
        }
        
        .setting-label {
            font-weight: 700;
            color: #222;
            font-size: 15.5px;
        }
        
        .setting-desc {
            font-size: 12.5px;
            color: #666;
            margin-top: 5px;
            line-height: 1.4;
        }
        
        .toggle-switch {
            position: relative;
            width: 48px;
            height: 24px;
            min-width: 48px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #d1d1d6;
            transition: .3s;
            border-radius: 24px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }
        
        input:checked + .slider {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        input:checked + .slider:before {
            transform: translateX(24px);
        }
        
        .back-link {
            display: inline-block;
            margin-top: 25px;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .back-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="notif-container">
        <div class="header">
            <h1>🔔 Thông Báo</h1>
            <div>
                <button class="btn btn-success" onclick="markAllRead()">
                    <i class="fas fa-check-double"></i> Đọc Hết
                </button>
                <button class="btn btn-danger" onclick="deleteAllNotifications()">
                    <i class="fas fa-trash-alt"></i> Xóa Hết
                </button>
                <button class="btn btn-primary" onclick="loadSettings()">
                    <i class="fas fa-cog"></i> Cài Đặt
                </button>
            </div>
        </div>

        <?php if (!$tableExists): ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h2>Hệ thống Notifications chưa được kích hoạt!</h2>
                    <p>Vui lòng chạy file <code>create_notifications_tables.sql</code> trong phpMyAdmin trước.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="search-container">
                    <input type="text" id="search-input" class="search-input" placeholder="Nhập tiêu đề hoặc nội dung thông báo để lọc nhanh..." autocomplete="off">
                </div>

                <div class="tabs">
                    <button class="tab active" onclick="switchTab('all')">Tất Cả</button>
                    <button class="tab" onclick="switchTab('unread')">Chưa Đọc <span id="unread-badge" class="badge badge-unread">0</span></button>
                    <button class="tab" onclick="switchTab('important')">Quan Trọng</button>
                </div>
                
                <div id="notifications-list">
                    <div class="empty-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Đang tải...</p>
                    </div>
                </div>
            </div>
            
            <div class="card settings-section" id="settings-section" style="display: none;">
                <h2 style="margin-bottom: 25px; background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">⚙️ Cài Đặt Thông Báo</h2>
                <div id="settings-list"></div>
                <div style="margin-top: 20px; text-align: right;">
                    <button class="btn btn-primary" onclick="saveSettings()">
                        <i class="fas fa-save"></i> Lưu Cài Đặt
                    </button>
                </div>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php" class="back-link">🏠 Về Trang Chủ</a>
        </div>
    </div>

    <script>
        let currentTab = 'all';
        
        function switchTab(tab) {
            currentTab = tab;
            $('.tab').removeClass('active');
            $(`.tab[onclick="switchTab('${tab}')"]`).addClass('active');
            
            // Xóa nội dung tìm kiếm cũ khi chuyển tab
            $('#search-input').val('');
            
            loadNotifications();
        }
        
        function loadNotifications() {
            let params = '';
            if (currentTab === 'unread') {
                params = '&unread_only=1';
            } else if (currentTab === 'important') {
                params = '&important_only=1';
            }
            
            $.get('api_notifications.php?action=get_list' + params, function(response) {
                if (response.success) {
                    displayNotifications(response.notifications);
                    updateUnreadCount();
                } else {
                    $('#notifications-list').html(`
                        <div class="empty-state">
                            <i class="fas fa-exclamation-circle" style="color: #dc3545;"></i>
                            <h2>Lỗi tải dữ liệu</h2>
                            <p>${response.message || 'Không thể kết nối đến server.'}</p>
                        </div>
                    `);
                }
            }).fail(function() {
                $('#notifications-list').html(`
                    <div class="empty-state">
                        <i class="fas fa-exclamation-circle" style="color: #dc3545;"></i>
                        <h2>Lỗi tải dữ liệu</h2>
                        <p>Không thể kết nối đến máy chủ API.</p>
                    </div>
                `);
            });
        }
        
        function displayNotifications(notifications) {
            const list = $('#notifications-list');
            
            if (!notifications || notifications.length === 0) {
                list.html(`
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h2>Không có thông báo nào</h2>
                        <p>Bạn sẽ nhận thông báo khi có hoạt động mới!</p>
                    </div>
                `);
                return;
            }
            
            const typeConfig = {
                'friend_request': { gradient: 'linear-gradient(135deg, #11998e, #38ef7d)', defaultIcon: '👋' },
                'private_message': { gradient: 'linear-gradient(135deg, #00c6ff, #0072ff)', defaultIcon: '💬' },
                'achievement': { gradient: 'linear-gradient(135deg, #f5af19, #f12711)', defaultIcon: '🏆' },
                'gift_received': { gradient: 'linear-gradient(135deg, #ff007f, #ff758c)', defaultIcon: '🎁' },
                'event_update': { gradient: 'linear-gradient(135deg, #e100ff, #7f00ff)', defaultIcon: '🎉' },
                'tournament_update': { gradient: 'linear-gradient(135deg, #ff4e50, #f9d423)', defaultIcon: '🎯' },
                'guild_invite': { gradient: 'linear-gradient(135deg, #3a7bd5, #3a6073)', defaultIcon: '🏰' },
                'guild_message': { gradient: 'linear-gradient(135deg, #4facfe, #00f2fe)', defaultIcon: '💬' },
                'system': { gradient: 'linear-gradient(135deg, #757f9a, #d7dde8)', defaultIcon: '🔔' }
            };
            
            let html = '';
            notifications.forEach((notif, index) => {
                const isUnread = notif.is_read == 0;
                const isImportant = notif.is_important == 1;
                const timeAgo = getTimeAgo(notif.created_at);
                
                const config = typeConfig[notif.type] || typeConfig['system'];
                const gradient = config.gradient;
                const icon = notif.icon || config.defaultIcon;
                
                // Add staggered animation delay
                const delay = index * 0.05;
                
                html += `
                    <div class="notification-item ${isUnread ? 'unread' : ''} ${isImportant ? 'important' : ''}" 
                         style="--card-gradient: ${gradient}; animation-delay: ${delay}s;" 
                         data-search-content="${(notif.title + ' ' + notif.content).toLowerCase()}">
                        <div class="notification-icon" style="background: ${gradient};">${icon}</div>
                        <div class="notification-content">
                            <div class="notification-title">
                                ${notif.title}
                                ${isUnread ? '<span class="badge badge-unread">Mới</span>' : ''}
                                ${isImportant ? '<span class="badge badge-important">Quan Trọng</span>' : ''}
                            </div>
                            <div class="notification-text">${notif.content}</div>
                            <div class="notification-time"><i class="far fa-clock"></i> ${timeAgo}</div>
                        </div>
                        <div class="notification-actions">
                            ${isUnread ? `<button class="btn btn-success btn-sm" onclick="markRead(${notif.id})" title="Đánh dấu đã đọc">
                                <i class="fas fa-check"></i>
                            </button>` : ''}
                            ${notif.link ? `<a href="${notif.link}" class="btn btn-primary btn-sm" title="Đi tới liên kết">
                                <i class="fas fa-external-link-alt"></i>
                            </a>` : ''}
                            <button class="btn btn-danger btn-sm" onclick="deleteNotification(${notif.id})" title="Xóa thông báo">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            
            list.html(html);
        }
        
        function getTimeAgo(dateString) {
            const now = new Date();
            const date = new Date(dateString);
            const diff = Math.floor((now - date) / 1000);
            
            if (diff < 60) return 'Vừa xong';
            if (diff < 3600) return `${Math.floor(diff / 60)} phút trước`;
            if (diff < 86400) return `${Math.floor(diff / 3600)} giờ trước`;
            if (diff < 604800) return `${Math.floor(diff / 86400)} ngày trước`;
            return date.toLocaleDateString('vi-VN');
        }
        
        function markRead(id) {
            $.post('api_notifications.php', {
                action: 'mark_read',
                notification_id: id
            }, function(response) {
                if (response.success) {
                    loadNotifications();
                } else {
                    Swal.fire('Lỗi', response.message, 'error');
                }
            });
        }
        
        function markAllRead() {
            Swal.fire({
                title: 'Xác nhận',
                text: 'Bạn có chắc muốn đánh dấu tất cả đã đọc?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Có',
                cancelButtonText: 'Không'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_notifications.php', {
                        action: 'mark_all_read'
                    }, function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Thành công',
                                text: 'Đã đánh dấu tất cả đã đọc!',
                                timer: 1500,
                                showConfirmButton: false
                            });
                            loadNotifications();
                        } else {
                            Swal.fire('Lỗi', response.message, 'error');
                        }
                    });
                }
            });
        }
        
        function deleteNotification(id) {
            Swal.fire({
                title: 'Xác nhận',
                text: 'Bạn có chắc muốn xóa thông báo này?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Xóa',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_notifications.php', {
                        action: 'delete',
                        notification_id: id
                    }, function(response) {
                        if (response.success) {
                            loadNotifications();
                        } else {
                            Swal.fire('Lỗi', response.message, 'error');
                        }
                    });
                }
            });
        }

        function deleteAllNotifications() {
            Swal.fire({
                title: 'Cảnh báo',
                text: 'Bạn có chắc chắn muốn xóa toàn bộ thông báo? Hành động này không thể hoàn tác!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Xóa tất cả',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_notifications.php', {
                        action: 'delete_all'
                    }, function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Thành công',
                                text: 'Đã xóa sạch thông báo của bạn!',
                                timer: 1500,
                                showConfirmButton: false
                            });
                            loadNotifications();
                        } else {
                            Swal.fire('Lỗi', response.message, 'error');
                        }
                    });
                }
            });
        }
        
        function updateUnreadCount() {
            $.get('api_notifications.php?action=get_unread_count', function(response) {
                if (response.success) {
                    const count = response.count;
                    if (count > 0) {
                        $('#unread-badge').text(count).show();
                    } else {
                        $('#unread-badge').hide();
                    }
                }
            });
        }
        
        function loadSettings() {
            $('#settings-section').toggle();
            if ($('#settings-section').is(':visible')) {
                $.get('api_notifications.php?action=get_settings', function(response) {
                    if (response.success) {
                        displaySettings(response.settings);
                    }
                });
            }
        }
        
        function displaySettings(settings) {
            const settingsList = $('#settings-list');
            const settingsMap = {
                'friend_request': { label: '👥 Lời Mời Kết Bạn', desc: 'Thông báo khi có người gửi lời mời kết bạn' },
                'private_message': { label: '💬 Tin Nhắn Riêng', desc: 'Thông báo khi nhận tin nhắn riêng mới' },
                'achievement': { label: '🏆 Đạt Achievement', desc: 'Thông báo khi đạt được danh hiệu mới' },
                'gift_received': { label: '🎁 Nhận Quà', desc: 'Thông báo khi nhận được quà từ người khác' },
                'event_update': { label: '🎉 Cập Nhật Sự Kiện', desc: 'Thông báo về sự kiện mới hoặc cập nhật' },
                'tournament_update': { label: '🎯 Cập Nhật Giải Đấu', desc: 'Thông báo về giải đấu mới hoặc kết quả' },
                'guild_invite': { label: '🏰 Mời Vào Guild', desc: 'Thông báo khi được mời vào guild' },
                'guild_message': { label: '💬 Tin Nhắn Guild', desc: 'Thông báo tin nhắn trong guild' },
                'sound_enabled': { label: '🔊 Âm Thanh', desc: 'Bật/tắt âm thanh khi có thông báo mới' },
                'email_notifications': { label: '📧 Email Thông Báo', desc: 'Nhận thư thông báo qua hòm thư điện tử' }
            };
            
            let html = '';
            for (const [key, info] of Object.entries(settingsMap)) {
                const value = settings[key] == 1;
                html += `
                    <div class="setting-item">
                        <div>
                            <div class="setting-label">${info.label}</div>
                            <div class="setting-desc">${info.desc}</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="setting-${key}" ${value ? 'checked' : ''}>
                            <span class="slider"></span>
                        </label>
                    </div>
                `;
            }
            
            settingsList.html(html);
        }
        
        function saveSettings() {
            const settings = {
                friend_request: $('#setting-friend_request').is(':checked') ? 1 : 0,
                private_message: $('#setting-private_message').is(':checked') ? 1 : 0,
                achievement: $('#setting-achievement').is(':checked') ? 1 : 0,
                gift_received: $('#setting-gift_received').is(':checked') ? 1 : 0,
                event_update: $('#setting-event_update').is(':checked') ? 1 : 0,
                tournament_update: $('#setting-tournament_update').is(':checked') ? 1 : 0,
                guild_invite: $('#setting-guild_invite').is(':checked') ? 1 : 0,
                guild_message: $('#setting-guild_message').is(':checked') ? 1 : 0,
                sound_enabled: $('#setting-sound_enabled').is(':checked') ? 1 : 0,
                email_notifications: $('#setting-email_notifications').is(':checked') ? 1 : 0
            };
            
            $.post('api_notifications.php', {
                action: 'update_settings',
                ...settings
            }, function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Thành công',
                        text: 'Đã lưu cấu hình cài đặt thông báo!',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire('Lỗi', response.message, 'error');
                }
            });
        }
        
        // Sự kiện lọc nhanh thông báo qua ô tìm kiếm
        $(document).on('input', '#search-input', function() {
            const val = $(this).val().toLowerCase().trim();
            if (val === '') {
                $('.notification-item').show();
            } else {
                $('.notification-item').each(function() {
                    const content = $(this).attr('data-search-content');
                    if (content.includes(val)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            }
        });
        
        // Load notifications khi trang load
        $(document).ready(function() {
            loadNotifications();
            setInterval(updateUnreadCount, 30000); // Update mỗi 30 giây
        });
    </script>
</body>
</html>
