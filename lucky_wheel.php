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

// Kiểm tra bảng tồn tại
$checkRewardsTable = $conn->query("SHOW TABLES LIKE 'lucky_wheel_rewards'");
$checkLogsTable = $conn->query("SHOW TABLES LIKE 'lucky_wheel_logs'");
$wheelExists = $checkRewardsTable && $checkRewardsTable->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lucky Wheel - Vòng Quay May Mắn</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        * {
            cursor: inherit;
        }

        button,
        a,
        input[type="button"],
        input[type="submit"],
        label,
        select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .wheel-container {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }

        .header-wheel {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: var(--border-radius-lg);
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .wheel-wrapper {
            position: relative;
            display: inline-block;
            margin: 40px 0;
        }

        #wheel {
            width: 500px;
            height: 500px;
            border-radius: 50%;
            border: 8px solid #2c3e50;
            box-shadow: 0 10px 50px rgba(0, 0, 0, 0.5);
            transition: transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99);
            position: relative;
            background: #ecf0f1;
        }

        .wheel-pointer {
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 30px solid transparent;
            border-right: 30px solid transparent;
            border-top: 60px solid #e74c3c;
            z-index: 10;
            filter: drop-shadow(0 5px 10px rgba(0, 0, 0, 0.5));
            pointer-events: none;
        }

        .spin-button {
            margin-top: 30px;
            padding: 20px 60px;
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius-lg);
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(243, 156, 18, 0.4);
        }

        .spin-button:hover:not(:disabled) {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 12px 35px rgba(243, 156, 18, 0.6);
        }

        .spin-button:disabled {
            opacity: 0.5;
            cursor: not-allowed !important;
        }

        .reward-popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.98);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            z-index: 1000;
            text-align: center;
            animation: popupShow 0.5s ease;
            max-width: 400px;
        }

        @keyframes popupShow {
            from {
                opacity: 0;
                transform: translate(-50%, -50%) scale(0.5);
            }

            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }

        .reward-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: bounce 1s infinite;
        }

        @keyframes bounce {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        .reward-message {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        .reward-details {
            font-size: 18px;
            color: var(--text-dark);
            margin-bottom: 30px;
        }

        .close-popup {
            padding: 12px 30px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .history-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: var(--border-radius-lg);
            margin-top: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .history-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        .history-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: rgba(0, 0, 0, 0.05);
            border-radius: var(--border-radius);
            margin-bottom: 10px;
        }

        .history-icon {
            font-size: 32px;
            margin-right: 15px;
        }

        .history-details {
            flex: 1;
            text-align: left;
        }

        .history-name {
            font-weight: 600;
            color: var(--text-dark);
        }

        .history-date {
            font-size: 12px;
            color: var(--text-light);
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
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .back-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }

        .info-message {
            background: rgba(52, 152, 219, 0.1);
            border: 2px solid var(--primary-color);
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            color: var(--primary-color);
            font-weight: 600;
        }
            \n
    </style>
</head>

<body>
    

    <div class="wheel-container">
        <div class="header-wheel">
            <h1>🎡 Lucky Wheel - Vòng Quay May Mắn</h1>
            <div style="margin-top: 15px; font-size: 18px; color: var(--success-color); font-weight: 600;">
                👤 <?= htmlspecialchars($user['Name']) ?> | 💰 <?= number_format($user['Money'], 0, ',', '.') ?> gtlm
            </div>
            <div class="info-message">
                💡 Bạn có 1 lượt quay miễn phí mỗi ngày! Hãy thử vận may của mình nhé!
            </div>
        </div>

        <?php if (!$wheelExists): ?>
            <div class="info-message" style="background: rgba(220, 53, 69, 0.1); border-color: #dc3545; color: #dc3545;">
                ⚠️ Hệ thống Lucky Wheel chưa được kích hoạt. Vui lòng chạy file
                <strong>create_lucky_wheel_tables.sql</strong> trong database.
            </div>
        <?php else: ?>
            <div class="wheel-wrapper">
                <div class="wheel-pointer"></div>
                <canvas id="wheel" width="500" height="500"></canvas>
            </div>

            <button id="spinButton" class="spin-button">🎡 Quay Ngay</button>
            <div id="spinStatus" style="margin-top: 20px; font-size: 18px; font-weight: 600; color: var(--text-dark);">
            </div>

            <!-- Reward Popup -->
            <div id="rewardPopup" class="reward-popup">
                <div class="reward-icon" id="rewardIcon">🎁</div>
                <div class="reward-message" id="rewardMessage">Chúc mừng!</div>
                <div class="reward-details" id="rewardDetails"></div>
                <button class="close-popup" onclick="closeRewardPopup()">Đóng</button>
            </div>

            <!-- History Section -->
            <div class="history-section">
                <div class="history-title">📜 Lịch Sử Quay (10 Lần Gần Nhất)</div>
                <div id="historyList">
                    <div style="text-align: center; padding: 20px; color: var(--text-light);">
                        Đang tải lịch sử...
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <a href="index.php" class="back-link">🏠 Về Trang Chủ</a>
    </div>

    <script>
        let rewards = [];
        let canSpin = false;
        let isSpinning = false;

        document.addEventListener('DOMContentLoaded', function () {
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";

            const interactiveElements = document.querySelectorAll('button, a, input, label, select');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            });

            // Kiểm tra wheel có tồn tại không
            const wheelExists = <?= $wheelExists ? 'true' : 'false' ?>;
            if (wheelExists) {
                checkSpinStatus();
                loadRewards();
                loadHistory();
            } else {
                const spinStatus = document.getElementById('spinStatus');
                if (spinStatus) {
                    spinStatus.innerHTML = '⚠️ Hệ thống Lucky Wheel chưa được kích hoạt. Vui lòng chạy file create_lucky_wheel_tables.sql trong database.';
                    spinStatus.style.color = '#dc3545';
                }
            }
        });

        function checkSpinStatus() {
            fetch('api_lucky_wheel.php?action=check_spin')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        canSpin = !data.has_spun;
                        const spinButton = document.getElementById('spinButton');
                        const spinStatus = document.getElementById('spinStatus');

                        if (data.has_spun) {
                            spinButton.disabled = true;
                            spinStatus.innerHTML = '❌ Bạn đã quay wheel hôm nay rồi! Quay lại vào ngày mai nhé.';
                            spinStatus.style.color = '#dc3545';
                        } else {
                            spinButton.disabled = false;
                            spinStatus.innerHTML = '✅ Bạn có thể quay wheel ngay bây giờ!';
                            spinStatus.style.color = '#28a745';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking spin status:', error);
                });
        }

        function loadRewards() {
            fetch('api_lucky_wheel.php?action=get_rewards')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        rewards = data.rewards || [];
                        drawWheel();
                    } else {
                        console.error('Error loading rewards:', data.message);
                        const spinStatus = document.getElementById('spinStatus');
                        if (spinStatus) {
                            spinStatus.innerHTML = '❌ ' + (data.message || 'Không thể tải phần thưởng');
                            spinStatus.style.color = '#dc3545';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading rewards:', error);
                    const spinStatus = document.getElementById('spinStatus');
                    if (spinStatus) {
                        spinStatus.innerHTML = '❌ Có lỗi xảy ra khi tải phần thưởng';
                        spinStatus.style.color = '#dc3545';
                    }
                });
        }

        function drawWheel() {
            const canvas = document.getElementById('wheel');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            if (!ctx) return;

            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;
            const radius = canvas.width / 2 - 10;

            if (!rewards || rewards.length === 0) {
                // Vẽ wheel mặc định nếu chưa có rewards
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.beginPath();
                ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
                ctx.fillStyle = '#3498db';
                ctx.fill();
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 5;
                ctx.stroke();
                ctx.fillStyle = '#fff';
                ctx.font = 'bold 20px Arial';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.strokeStyle = '#000';
                ctx.lineWidth = 3;
                ctx.strokeText('Đang tải...', centerX, centerY);
                ctx.fillText('Đang tải...', centerX, centerY);
                return;
            }

            // Clear canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            const anglePerSector = (2 * Math.PI) / rewards.length;

            rewards.forEach((reward, index) => {
                const startAngle = index * anglePerSector - Math.PI / 2;
                const endAngle = (index + 1) * anglePerSector - Math.PI / 2;

                // Vẽ sector
                ctx.beginPath();
                ctx.moveTo(centerX, centerY);
                ctx.arc(centerX, centerY, radius, startAngle, endAngle);
                ctx.closePath();
                ctx.fillStyle = reward.color || '#3498db';
                ctx.fill();
                // Vẽ border cho sector
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 2;
                ctx.stroke();

                // Vẽ đường phân cách giữa các sectors
                ctx.beginPath();
                ctx.moveTo(centerX, centerY);
                ctx.lineTo(
                    centerX + Math.cos(startAngle) * radius,
                    centerY + Math.sin(startAngle) * radius
                );
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 2;
                ctx.stroke();

                // Tính góc giữa của sector
                const middleAngle = startAngle + anglePerSector / 2;
                const angleDegrees = (middleAngle * 180 / Math.PI + 360) % 360;

                // Vị trí text - điều chỉnh để tránh bị che bởi pointer ở trên
                let textDistance = radius * 0.68;

                // Nếu sector ở phía trên (gần pointer), đẩy text vào trong hơn
                // Pointer ở góc 270 độ (trên cùng)
                if ((angleDegrees >= 255 && angleDegrees <= 285)) {
                    textDistance = radius * 0.50; // Đẩy vào trong hơn để tránh pointer
                } else if ((angleDegrees >= 240 && angleDegrees <= 300)) {
                    textDistance = radius * 0.58;
                }

                // Tính vị trí text trên wheel (theo tọa độ cực)
                const textX = centerX + Math.cos(middleAngle) * textDistance;
                const textY = centerY + Math.sin(middleAngle) * textDistance;

                // Chuẩn bị text
                let text = reward.reward_name || 'N/A';
                // Rút gọn text nếu quá dài
                const maxLength = 18;
                if (text.length > maxLength) {
                    text = text.substring(0, maxLength - 3) + '...';
                }

                // Vẽ text với góc xoay phù hợp
                ctx.save();
                ctx.translate(textX, textY);

                // Điều chỉnh góc xoay: nếu sector ở phía dưới (180-360), xoay thêm 180 độ
                let textAngle = middleAngle;
                if (angleDegrees > 90 && angleDegrees < 270) {
                    textAngle += Math.PI; // Xoay 180 độ để text không bị ngược
                }
                ctx.rotate(textAngle + Math.PI / 2); // +90 độ để text nằm ngang

                // Vẽ text với shadow và stroke để dễ đọc
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.font = 'bold 11px Arial, sans-serif';

                // Vẽ shadow (đường viền đen) để text nổi bật
                ctx.strokeStyle = '#000';
                ctx.lineWidth = 4;
                ctx.lineJoin = 'round';
                ctx.miterLimit = 2;
                ctx.strokeText(text, 0, 0);

                // Vẽ text chính (màu trắng)
                ctx.fillStyle = '#ffffff';
                ctx.fillText(text, 0, 0);

                ctx.restore();
            });
        }

        function spinWheel() {
            if (isSpinning || !canSpin) return;

            isSpinning = true;
            const spinButton = document.getElementById('spinButton');
            const spinStatus = document.getElementById('spinStatus');
            spinButton.disabled = true;
            spinStatus.innerHTML = '⏳ Đang quay...';
            spinStatus.style.color = '#f39c12';

            fetch('api_lucky_wheel.php?action=spin', {
                method: 'POST'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const wheel = document.getElementById('wheel');
                        const currentRotation = getCurrentRotation(wheel);
                        const newRotation = currentRotation + data.angle;

                        wheel.style.transform = `rotate(${newRotation}deg)`;

                        setTimeout(() => {
                            showRewardPopup(data.reward, data.message);
                            checkSpinStatus();
                            loadHistory();

                            // Reload page after 3 seconds to update balance
                            if (data.reward_given) {
                                setTimeout(() => {
                                    window.location.reload();
                                }, 3000);
                            }

                            isSpinning = false;
                        }, 4000);
                    } else {
                        alert('❌ ' + data.message);
                        isSpinning = false;
                        spinButton.disabled = false;
                        checkSpinStatus();
                    }
                })
                .catch(error => {
                    console.error('Error spinning wheel:', error);
                    alert('❌ Có lỗi xảy ra khi quay wheel!');
                    isSpinning = false;
                    spinButton.disabled = false;
                    checkSpinStatus();
                });
        }

        function getCurrentRotation(element) {
            if (!element) return 0;

            const style = window.getComputedStyle(element);
            const matrix = style.transform || style.webkitTransform || style.mozTransform;
            if (matrix && matrix !== 'none') {
                try {
                    const values = matrix.split('(')[1].split(')')[0].split(',');
                    const a = parseFloat(values[0]);
                    const b = parseFloat(values[1]);
                    const angle = Math.round(Math.atan2(b, a) * (180 / Math.PI));
                    return angle < 0 ? angle + 360 : angle;
                } catch (e) {
                    console.error('Error parsing transform:', e);
                    return 0;
                }
            }
            return 0;
        }

        function showRewardPopup(reward, message) {
            const popup = document.getElementById('rewardPopup');
            const icon = document.getElementById('rewardIcon');
            const rewardMessage = document.getElementById('rewardMessage');
            const rewardDetails = document.getElementById('rewardDetails');

            icon.textContent = reward.icon || '🎁';
            rewardMessage.textContent = reward.reward_value > 0 ? '🎉 Chúc mừng!' : '😢';
            rewardDetails.textContent = message;

            popup.style.display = 'block';
        }

        function closeRewardPopup() {
            document.getElementById('rewardPopup').style.display = 'none';
        }

        // Event listeners
        const spinButton = document.getElementById('spinButton');
        if (spinButton) {
            spinButton.addEventListener('click', spinWheel);
        }

        function loadHistory() {
            fetch('api_lucky_wheel.php?action=get_history')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const historyList = document.getElementById('historyList');

                        if (data.history.length === 0) {
                            historyList.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--text-light);">Chưa có lịch sử quay</div>';
                            return;
                        }

                        historyList.innerHTML = data.history.map(item => {
                            const date = new Date(item.spun_at);
                            const dateStr = date.toLocaleDateString('vi-VN') + ' ' + date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });

                            return `
                                <div class="history-item">
                                    <div class="history-icon">${item.icon || '🎁'}</div>
                                    <div class="history-details">
                                        <div class="history-name">${item.reward_name}</div>
                                        <div class="history-date">${dateStr}</div>
                                    </div>
                                </div>
                            `;
                        }).join('');
                    }
                })
                .catch(error => {
                    console.error('Error loading history:', error);
                });
        }
    </script>



    
    


    


    <!-- Premium Effects System -->
    <canvas id="threejs-background"></canvas>
    <script>
        (function() {
            window.themeConfig = {
                particleCount: <?= $particleCount ?? 800 ?>,
                particleSize: <?= $particleSize ?? 0.05 ?>,
                particleColor: '<?= $particleColor ?? "#ffffff" ?>',
                particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
                shapeCount: <?= $shapeCount ?? 10 ?>,
                shapeColors: <?= json_encode($shapeColors ?? ["#667eea", "#764ba2", "#4facfe", "#00f2fe"]) ?>,
                shapeOpacity: <?= $shapeOpacity ?? 0.3 ?>,
                bgGradient: <?= json_encode($bgGradient ?? ["#667eea", "#764ba2", "#4facfe"]) ?>
            };
            const prefix = window.location.pathname.includes('/games/') ? '../' : '';
            const scripts = ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'];
            
            scripts.forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();
    </script>

</body>

</html>