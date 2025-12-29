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
$checkTable = $conn->query("SHOW TABLES LIKE 'daily_login_rewards'");
$tableExists = $checkTable && $checkTable->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎁 Phần Thưởng Đăng Nhập</title>
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
            text-align: center;
        }

        .header h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 15px;
        }
        
        .stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
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
        
        .rewards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .reward-item {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            position: relative;
            transition: all 0.3s ease;
            border: 3px solid transparent;
        }
        
        .reward-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        
        .reward-item.claimed {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            border-color: #28a745;
        }
        
        .reward-item.today {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            border-color: #ffc107;
            animation: pulse 2s infinite;
        }
        
        .reward-item.locked {
            background: linear-gradient(135deg, #e0e0e0 0%, #bdbdbd 100%);
            opacity: 0.6;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .reward-day {
            font-size: 18px;
            font-weight: 800;
            color: #333;
            margin-bottom: 10px;
        }
        
        .reward-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .reward-value {
            font-size: 14px;
            font-weight: 600;
            color: #666;
        }
        
        .reward-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 800;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
            margin-top: 20px;
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
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .info-box {
            background: rgba(247, 247, 247, 0.8);
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }
        
        .info-box h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .info-box p {
            color: #666;
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎁 Phần Thưởng Đăng Nhập Hàng Ngày</h1>
            <div class="stats">
                <div class="stat-item">
                    <div class="stat-value" id="consecutive-days">0</div>
                    <div class="stat-label">Ngày Liên Tiếp</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" id="total-days">0</div>
                    <div class="stat-label">Tổng Ngày</div>
                </div>
            </div>
        </div>

        <?php if (!$tableExists): ?>
            <div class="card">
                <div style="text-align: center; padding: 40px; color: #999;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 64px; margin-bottom: 20px; opacity: 0.5;"></i>
                    <h2>Hệ thống Daily Login chưa được kích hoạt!</h2>
                    <p>Vui lòng chạy file <code>create_daily_login_tables.sql</code> trong phpMyAdmin trước.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <h2 style="margin-bottom: 20px;">📅 Phần Thưởng Hàng Tuần</h2>
                <div class="info-box">
                    <h3>💡 Cách Hoạt Động</h3>
                    <p>
                        - Đăng nhập mỗi ngày để nhận phần thưởng<br>
                        - Chuỗi đăng nhập liên tiếp sẽ tăng phần thưởng<br>
                        - Nếu bỏ lỡ 1 ngày, chuỗi sẽ reset về ngày 1<br>
                        - Phần thưởng chu kỳ 7 ngày, sau đó lặp lại
                    </p>
                </div>
                
                <div class="rewards-grid" id="rewards-grid">
                    <div style="text-align: center; padding: 40px; color: #999;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 32px;"></i>
                        <p>Đang tải...</p>
                    </div>
                </div>
                
                <div style="text-align: center;">
                    <button class="btn btn-success" id="claim-btn" onclick="claimReward()" disabled>
                        <i class="fas fa-gift"></i> Nhận Phần Thưởng Hôm Nay
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let currentStatus = null;
        
        function loadStatus() {
            $.get('api_daily_login.php?action=get_status', function(response) {
                if (response.success) {
                    currentStatus = response;
                    $('#consecutive-days').text(response.consecutive_days);
                    $('#total-days').text(response.total_days);
                    
                    if (response.can_claim) {
                        $('#claim-btn').prop('disabled', false);
                    } else {
                        $('#claim-btn').prop('disabled', true);
                    }
                }
            });
        }
        
        function loadRewards() {
            $.get('api_daily_login.php?action=get_rewards_list', function(response) {
                if (response.success) {
                    displayRewards(response.rewards);
                }
            });
        }
        
        function displayRewards(rewards) {
            const grid = $('#rewards-grid');
            let html = '';
            
            rewards.forEach((reward, index) => {
                const day = reward.day_number;
                const isClaimed = currentStatus && currentStatus.reward_day >= day && currentStatus.has_claimed_today;
                const isToday = currentStatus && currentStatus.reward_day === day;
                const isLocked = currentStatus && currentStatus.reward_day < day;
                
                let classes = 'reward-item';
                if (isClaimed) classes += ' claimed';
                if (isToday && !isClaimed) classes += ' today';
                if (isLocked) classes += ' locked';
                
                const icon = reward.reward_type === 'money' ? '💰' : '🎁';
                const value = reward.reward_type === 'money' 
                    ? number_format(reward.reward_value) + ' VNĐ'
                    : reward.description;
                
                html += `
                    <div class="${classes}">
                        ${isToday && !isClaimed ? '<div class="reward-badge">!</div>' : ''}
                        <div class="reward-day">Ngày ${day}</div>
                        <div class="reward-icon">${icon}</div>
                        <div class="reward-value">${value}</div>
                    </div>
                `;
            });
            
            grid.html(html);
        }
        
        function claimReward() {
            if (!currentStatus || !currentStatus.can_claim) {
                Swal.fire('Thông báo', 'Bạn chưa thể nhận phần thưởng!', 'info');
                return;
            }
            
            Swal.fire({
                title: 'Nhận Phần Thưởng?',
                text: 'Bạn có chắc muốn nhận phần thưởng đăng nhập hôm nay?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Nhận Ngay',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_daily_login.php', {
                        action: 'claim_reward'
                    }, function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Thành Công!',
                                html: `Bạn đã nhận được:<br><strong>${response.reward.description}</strong>`,
                                timer: 3000,
                                showConfirmButton: false
                            });
                            loadStatus();
                            loadRewards();
                        } else {
                            Swal.fire('Lỗi', response.message, 'error');
                        }
                    });
                }
            });
        }
        
        function number_format(number) {
            return new Intl.NumberFormat('vi-VN').format(number);
        }
        
        // Auto check login khi vào trang
        $(document).ready(function() {
            $.post('api_daily_login.php', {
                action: 'check_login'
            }, function(response) {
                if (response.success) {
                    loadStatus();
                    loadRewards();
                }
            });
        });
    </script>
</body>
</html>

