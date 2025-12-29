<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require_once 'load_theme.php';

if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

$userId = $_SESSION['Iduser'];

// Kiểm tra bảng events có tồn tại không
$checkTable = $conn->query("SHOW TABLES LIKE 'events'");
$eventsTableExists = $checkTable && $checkTable->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - Sự Kiện Đặc Biệt</title>
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
        
        .events-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header-events {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 24px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
        }

        .header-events h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 42px;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 15px;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .event-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .event-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .event-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            border-color: #667eea;
        }
        
        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .event-title {
            font-size: 22px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }
        
        .event-badge {
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-daily {
            background: #28a745;
            color: white;
        }
        
        .badge-weekly {
            background: #007bff;
            color: white;
        }
        
        .badge-special {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .badge-limited {
            background: #dc3545;
            color: white;
        }
        
        .event-status {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-upcoming {
            background: #6c757d;
            color: white;
        }
        
        .status-active {
            background: #28a745;
            color: white;
        }
        
        .status-ended {
            background: #dc3545;
            color: white;
        }
        
        .event-description {
            color: #666;
            margin: 15px 0;
            line-height: 1.6;
        }
        
        .event-details {
            background: rgba(247, 247, 247, 0.8);
            padding: 15px;
            border-radius: 12px;
            margin: 15px 0;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .detail-label {
            color: #999;
        }
        
        .detail-value {
            font-weight: 600;
            color: #333;
        }
        
        .progress-container {
            margin: 20px 0;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .progress-bar {
            width: 100%;
            height: 12px;
            background: #e0e0e0;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 10px;
            font-weight: 600;
        }
        
        .reward-section {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            padding: 15px;
            border-radius: 12px;
            margin: 15px 0;
        }
        
        .reward-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .reward-value {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-block;
            text-decoration: none;
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 18px;
        }
        
        .message {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .message.error {
            background: rgba(220, 53, 69, 0.2);
            border: 2px solid #dc3545;
            color: #dc3545;
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
        
        @media (max-width: 768px) {
            .events-container {
                padding: 10px;
            }
            
            .header-events {
                padding: 25px;
            }
            
            .event-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="events-container">
        <div class="header-events">
            <h1>🎉 Events - Sự Kiện Đặc Biệt</h1>
            <p style="color: #666; font-size: 18px; margin-top: 10px;">Tham gia các sự kiện đặc biệt để nhận phần thưởng độc quyền!</p>
        </div>

        <?php if (!$eventsTableExists): ?>
            <div class="card">
                <div class="message error">
                    ⚠️ Hệ thống Events chưa được kích hoạt! Vui lòng chạy file <strong>ALL_DATABASE_TABLES.sql</strong> trước.
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <h2 style="margin-bottom: 20px;">Danh Sách Sự Kiện</h2>
                <div id="events-list" class="event-list">
                    <div class="no-data">Đang tải...</div>
                </div>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php" class="back-link">🏠 Về Trang Chủ</a>
        </div>
    </div>

    <script>
        const userId = <?= $userId ?>;

        // Load events
        function loadEvents() {
            $.ajax({
                url: 'api_events.php',
                method: 'GET',
                data: { action: 'get_list', status: 'all' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let html = '';
                        if (response.events.length === 0) {
                            html = '<div class="no-data">Chưa có sự kiện nào</div>';
                        } else {
                            response.events.forEach(event => {
                                const badgeClass = 'badge-' + event.event_type;
                                const statusClass = 'status-' + event.status;
                                const statusText = {
                                    'upcoming': 'Sắp Diễn Ra',
                                    'active': 'Đang Diễn Ra',
                                    'ended': 'Đã Kết Thúc',
                                    'cancelled': 'Đã Hủy'
                                };
                                
                                const typeText = {
                                    'daily': 'Hàng Ngày',
                                    'weekly': 'Hàng Tuần',
                                    'special': 'Đặc Biệt',
                                    'limited': 'Giới Hạn'
                                };
                                
                                const startTime = new Date(event.start_time).toLocaleString('vi-VN');
                                const endTime = new Date(event.end_time).toLocaleString('vi-VN');
                                
                                // Tính phần trăm progress
                                const progress = event.user_progress || 0;
                                const percentage = event.requirement_value > 0 
                                    ? Math.min(100, Math.round((progress / event.requirement_value) * 100))
                                    : 0;
                                
                                // Requirement text
                                let requirementText = '';
                                if (event.requirement_type === 'play_games') {
                                    requirementText = `Chơi ${formatNumber(event.requirement_value)} game`;
                                } else if (event.requirement_type === 'win_games') {
                                    requirementText = `Thắng ${formatNumber(event.requirement_value)} lần`;
                                } else if (event.requirement_type === 'earn_money') {
                                    requirementText = `Kiếm ${formatMoney(event.requirement_value)}`;
                                } else if (event.requirement_type === 'big_win') {
                                    requirementText = `Thắng một lần trên ${formatMoney(event.requirement_value)}`;
                                } else {
                                    requirementText = 'Hoàn thành yêu cầu';
                                }
                                
                                html += `
                                    <div class="event-card">
                                        <div class="event-header">
                                            <div>
                                                <div class="event-title">
                                                    ${event.name}
                                                    <span class="event-badge ${badgeClass}">${typeText[event.event_type]}</span>
                                                </div>
                                                <span class="event-status ${statusClass}">${statusText[event.status]}</span>
                                            </div>
                                        </div>
                                        <div class="event-description">${event.description || 'Chưa có mô tả'}</div>
                                        <div class="event-details">
                                            <div class="detail-row">
                                                <span class="detail-label">Bắt đầu:</span>
                                                <span class="detail-value">${startTime}</span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Kết thúc:</span>
                                                <span class="detail-value">${endTime}</span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Yêu cầu:</span>
                                                <span class="detail-value">${requirementText}</span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Thành viên:</span>
                                                <span class="detail-value">${event.participant_count || 0}${event.max_participants ? '/' + event.max_participants : ''}</span>
                                            </div>
                                        </div>
                                        ${event.is_joined ? `
                                            <div class="progress-container">
                                                <div class="progress-label">
                                                    <span>Tiến độ</span>
                                                    <span>${formatNumber(progress)}/${formatNumber(event.requirement_value)}</span>
                                                </div>
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: ${percentage}%">${percentage}%</div>
                                                </div>
                                            </div>
                                        ` : ''}
                                        <div class="reward-section">
                                            <div class="reward-title">💰 Phần Thưởng:</div>
                                            <div class="reward-value">${formatMoney(event.reward_value)}</div>
                                        </div>
                                        <div class="action-buttons">
                                            ${!event.is_joined && event.status === 'active' ? 
                                                `<button class="btn btn-success" onclick="joinEvent(${event.id})">Tham Gia</button>` : ''}
                                            ${event.is_joined && !event.user_completed ? 
                                                `<button class="btn btn-primary" onclick="viewEvent(${event.id})">Xem Chi Tiết</button>` : ''}
                                            ${event.is_joined && event.user_completed && !event.user_claimed ? 
                                                `<button class="btn btn-warning" onclick="claimReward(${event.id})">Nhận Phần Thưởng</button>` : ''}
                                            ${event.is_joined && event.user_claimed ? 
                                                `<span class="btn" style="background: #6c757d; cursor: default;">Đã Nhận Phần Thưởng</span>` : ''}
                                            <button class="btn btn-primary" onclick="viewEvent(${event.id})">Chi Tiết</button>
                                        </div>
                                    </div>
                                `;
                            });
                        }
                        $('#events-list').html(html);
                    }
                }
            });
        }

        // Join event
        function joinEvent(eventId) {
            $.ajax({
                url: 'api_events.php',
                method: 'POST',
                data: {
                    action: 'join',
                    event_id: eventId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Thành công', response.message, 'success');
                        loadEvents();
                    } else {
                        Swal.fire('Lỗi', response.message, 'error');
                    }
                }
            });
        }

        // View event details
        function viewEvent(eventId) {
            $.ajax({
                url: 'api_events.php',
                method: 'GET',
                data: { action: 'get_info', event_id: eventId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const event = response.event;
                        const progress = event.user_progress || 0;
                        const percentage = event.requirement_value > 0 
                            ? Math.min(100, Math.round((progress / event.requirement_value) * 100))
                            : 0;
                        
                        Swal.fire({
                            title: event.name,
                            html: `
                                <div style="text-align: left;">
                                    <p><strong>Mô tả:</strong> ${event.description || 'Chưa có mô tả'}</p>
                                    <p><strong>Thời gian:</strong> ${new Date(event.start_time).toLocaleString('vi-VN')} - ${new Date(event.end_time).toLocaleString('vi-VN')}</p>
                                    <p><strong>Yêu cầu:</strong> ${event.requirement_type}</p>
                                    ${event.is_joined ? `
                                        <div style="margin-top: 20px;">
                                            <h3>Tiến Độ</h3>
                                            <div style="width: 100%; height: 20px; background: #e0e0e0; border-radius: 10px; overflow: hidden; margin-top: 10px;">
                                                <div style="width: ${percentage}%; height: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">${percentage}%</div>
                                            </div>
                                            <p style="margin-top: 10px;">${formatNumber(progress)}/${formatNumber(event.requirement_value)}</p>
                                        </div>
                                    ` : ''}
                                    <div style="margin-top: 20px;">
                                        <h3>Phần Thưởng</h3>
                                        <p style="font-size: 24px; font-weight: 700; color: #667eea;">${formatMoney(event.reward_value)}</p>
                                    </div>
                                </div>
                            `,
                            width: '600px',
                            showCloseButton: true,
                            showConfirmButton: false
                        });
                    }
                }
            });
        }

        // Claim reward
        function claimReward(eventId) {
            $.ajax({
                url: 'api_events.php',
                method: 'POST',
                data: {
                    action: 'claim_reward',
                    event_id: eventId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Thành công',
                            html: `Bạn đã nhận được <strong>${formatMoney(response.reward.money)}</strong>!`,
                            timer: 3000,
                            showConfirmButton: false
                        });
                        loadEvents();
                    } else {
                        Swal.fire('Lỗi', response.message, 'error');
                    }
                }
            });
        }

        // Format money
        function formatMoney(amount) {
            return new Intl.NumberFormat('vi-VN').format(amount) + ' VNĐ';
        }

        // Format number
        function formatNumber(num) {
            return new Intl.NumberFormat('vi-VN').format(num);
        }

        // Load initial data
        loadEvents();
        
        // Auto refresh every 30 seconds
        setInterval(loadEvents, 30000);
    </script>
</body>
</html>

