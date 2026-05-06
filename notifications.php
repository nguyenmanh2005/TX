<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['Iduser'];
require_once 'load_theme.php';

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
    <title>🔔 Thông Báo</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
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
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -1px;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .notification-item {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item:hover {
            background: rgba(102, 126, 234, 0.05);
        }
        
        .notification-item.unread {
            background: rgba(102, 126, 234, 0.08);
            border-left: 4px solid #667eea;
        }
        
        .notification-item.important {
            background: rgba(255, 193, 7, 0.1);
            border-left: 4px solid #ffc107;
        }
        
        .notification-icon {
            font-size: 32px;
            min-width: 40px;
            text-align: center;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            font-size: 16px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .notification-text {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .notification-time {
            color: #999;
            font-size: 12px;
            margin-top: 8px;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .badge-unread {
            background: #dc3545;
            color: white;
        }
        
        .badge-important {
            background: #ffc107;
            color: #333;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .tab {
            padding: 12px 24px;
            background: transparent;
            border: none;
            font-weight: 600;
            font-size: 15px;
            color: #666;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
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
        }
        
        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .setting-item:last-child {
            border-bottom: none;
        }
        
        .setting-label {
            font-weight: 600;
            color: #333;
        }
        
        .setting-desc {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
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
            background-color: #ccc;
            transition: .4s;
            border-radius: 26px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #667eea;
        }
        
        input:checked + .slider:before {
            transform: translateX(24px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔔 Thông Báo</h1>
            <div>
                <button class="btn btn-success" onclick="markAllRead()">
                    <i class="fas fa-check-double"></i> Đánh Dấu Tất Cả Đã Đọc
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
                <h2 style="margin-bottom: 20px;">⚙️ Cài Đặt Thông Báo</h2>
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
            $(`.tab:contains(${tab === 'all' ? 'Tất Cả' : tab === 'unread' ? 'Chưa Đọc' : 'Quan Trọng'})`).addClass('active');
            loadNotifications();
        }
        
        function loadNotifications() {
            const unreadOnly = currentTab === 'unread' ? '&unread_only=1' : '';
            $.get('api_notifications.php?action=get_list' + unreadOnly, function(response) {
                if (response.success) {
                    displayNotifications(response.notifications);
                    updateUnreadCount();
                }
            });
        }
        
        function displayNotifications(notifications) {
            const list = $('#notifications-list');
            
            if (notifications.length === 0) {
                list.html(`
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h2>Không có thông báo nào</h2>
                        <p>Bạn sẽ nhận thông báo khi có hoạt động mới!</p>
                    </div>
                `);
                return;
            }
            
            let html = '';
            notifications.forEach(notif => {
                const isUnread = notif.is_read == 0;
                const isImportant = notif.is_important == 1;
                const timeAgo = getTimeAgo(notif.created_at);
                
                html += `
                    <div class="notification-item ${isUnread ? 'unread' : ''} ${isImportant ? 'important' : ''}">
                        <div class="notification-icon">${notif.icon || '🔔'}</div>
                        <div class="notification-content">
                            <div class="notification-title">
                                ${notif.title}
                                ${isUnread ? '<span class="badge badge-unread">Mới</span>' : ''}
                                ${isImportant ? '<span class="badge badge-important">Quan Trọng</span>' : ''}
                            </div>
                            <div class="notification-text">${notif.content}</div>
                            <div class="notification-time">${timeAgo}</div>
                        </div>
                        <div class="notification-actions">
                            ${isUnread ? `<button class="btn btn-success btn-sm" onclick="markRead(${notif.id})">
                                <i class="fas fa-check"></i>
                            </button>` : ''}
                            ${notif.link ? `<a href="${notif.link}" class="btn btn-primary btn-sm">
                                <i class="fas fa-external-link-alt"></i>
                            </a>` : ''}
                            <button class="btn btn-danger btn-sm" onclick="deleteNotification(${notif.id})">
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
                            Swal.fire('Thành công', 'Đã đánh dấu tất cả đã đọc!', 'success');
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
                            Swal.fire('Thành công', 'Đã xóa thông báo!', 'success');
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
                'friend_request': { label: 'Lời Mời Kết Bạn', desc: 'Thông báo khi có người gửi lời mời kết bạn' },
                'private_message': { label: 'Tin Nhắn Riêng', desc: 'Thông báo khi nhận tin nhắn riêng' },
                'achievement': { label: 'Đạt Achievement', desc: 'Thông báo khi đạt được achievement mới' },
                'gift_received': { label: 'Nhận Quà', desc: 'Thông báo khi nhận được quà từ người khác' },
                'event_update': { label: 'Cập Nhật Sự Kiện', desc: 'Thông báo về sự kiện mới hoặc cập nhật' },
                'tournament_update': { label: 'Cập Nhật Giải Đấu', desc: 'Thông báo về giải đấu mới hoặc kết quả' },
                'guild_invite': { label: 'Mời Vào Guild', desc: 'Thông báo khi được mời vào guild' },
                'guild_message': { label: 'Tin Nhắn Guild', desc: 'Thông báo tin nhắn trong guild' },
                'sound_enabled': { label: 'Âm Thanh', desc: 'Bật/tắt âm thanh thông báo' },
                'email_notifications': { label: 'Email Thông Báo', desc: 'Gửi thông báo qua email (nếu có)' }
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
                    Swal.fire('Thành công', 'Đã lưu cài đặt!', 'success');
                } else {
                    Swal.fire('Lỗi', response.message, 'error');
                }
            });
        }
        
        // Load notifications khi trang load
        $(document).ready(function() {
            loadNotifications();
            setInterval(updateUnreadCount, 30000); // Update mỗi 30 giây
        });
    </script>
</body>
</html>

