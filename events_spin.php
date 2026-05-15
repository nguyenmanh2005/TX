<?php
session_start();
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}
require 'db_connect.php';
require_once 'load_theme.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vòng Quay Sự Kiện</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #f59e0b;
            --secondary: #ef4444;
            --bg-glass: rgba(0, 0, 0, 0.6);
        }

        body {
            background: #0f172a;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(245, 158, 11, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(239, 68, 68, 0.15) 0%, transparent 50%);
            min-height: 100vh;
            color: #fff;
            font-family: 'Inter', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow-x: hidden;
        }

        .event-header {
            text-align: center;
            padding: 40px 0;
            width: 100%;
            background: rgba(0,0,0,0.3);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 50px;
        }

        .event-name {
            font-size: 48px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            background: linear-gradient(to right, #fcd34d, #f87171);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        /* ── Wheel Styles ── */
        .wheel-container {
            position: relative;
            width: 500px;
            height: 500px;
            margin-bottom: 50px;
        }

        .wheel-outer {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 15px solid #1e293b;
            box-shadow: 0 0 50px rgba(245, 158, 11, 0.3), inset 0 0 50px rgba(0,0,0,0.5);
            position: relative;
            overflow: hidden;
            transition: transform 5s cubic-bezier(0.1, 0, 0.1, 1);
            background: #1e293b;
        }

        .wheel-pointer {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 60px;
            background: #ef4444;
            clip-path: polygon(50% 100%, 0 0, 100% 0);
            z-index: 20;
            filter: drop-shadow(0 5px 10px rgba(0,0,0,0.5));
        }

        .wheel-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80px;
            height: 80px;
            background: #1e293b;
            border: 5px solid #fcd34d;
            border-radius: 50%;
            z-index: 15;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            box-shadow: 0 0 20px rgba(252, 211, 77, 0.5);
        }

        .wheel-segment {
            position: absolute;
            width: 50%;
            height: 50%;
            top: 0;
            left: 50%;
            transform-origin: bottom left;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-top: 40px;
        }

        .segment-content {
            transform: rotate(calc(360deg / var(--total-segments) / 2));
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .reward-icon { font-size: 32px; margin-bottom: 5px; }
        .reward-name { font-size: 10px; font-weight: 700; max-width: 60px; text-transform: uppercase; }

        /* ── Controls ── */
        .controls {
            text-align: center;
        }

        .btn-spin {
            background: linear-gradient(135deg, #f59e0b, #ef4444);
            color: white;
            padding: 18px 60px;
            border-radius: 50px;
            border: none;
            font-size: 24px;
            font-weight: 900;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.4);
            transition: all 0.3s;
            text-transform: uppercase;
        }

        .btn-spin:hover {
            transform: scale(1.05) translateY(-5px);
            box-shadow: 0 15px 40px rgba(239, 68, 68, 0.6);
        }

        .btn-spin:disabled {
            filter: grayscale(1);
            opacity: 0.5;
            cursor: not-allowed;
        }

        .spin-cost {
            margin-top: 15px;
            font-size: 18px;
            font-weight: 600;
            color: #fcd34d;
        }

        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
            background: rgba(255,255,255,0.1);
            padding: 10px 20px;
            border-radius: 50px;
            backdrop-filter: blur(10px);
            z-index: 100;
        }

        /* ── Lights ── */
        .wheel-light {
            position: absolute;
            width: 10px;
            height: 10px;
            background: #fff;
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform-origin: center;
            z-index: 10;
            animation: blink 1s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; transform: translate(-50%, -50%) scale(1); background: #fff; }
            50% { opacity: 0.5; transform: translate(-50%, -50%) scale(0.8); background: #fcd34d; }
        }

    </style>
</head>
<body>
    <a href="index.php" class="back-btn"><i class="fa fa-arrow-left"></i> Sảnh</a>

    <header class="event-header">
        <h1 class="event-name" id="event-title">Sự Kiện Đặc Biệt</h1>
        <p id="event-timer" style="opacity: 0.7;">Đang tải thông tin sự kiện...</p>
    </header>

    <div class="wheel-container">
        <div class="wheel-pointer"></div>
        <div class="wheel-center" id="event-emoji">🧧</div>
        <div class="wheel-outer" id="wheel">
            <!-- Segments injected here -->
        </div>
        <!-- Lights -->
        <div id="lights-container"></div>
    </div>

    <div class="controls">
        <button class="btn-spin" id="spin-btn" onclick="spin()">QUAY NGAY</button>
        <div class="spin-cost" id="spin-cost">Chi phí: 10,000 gtlm</div>
    </div>

    <script>
        let eventData = null;
        let rewards = [];
        let isSpinning = false;
        let currentRotation = 0;

        function loadEvent() {
            $.get('api_events.php?action=get_active_event', function(res) {
                if (res.success) {
                    eventData = res.event;
                    rewards = res.rewards;
                    $('#event-title').text(eventData.name);
                    $('#event-emoji').text(eventData.theme_emoji);
                    $('#spin-cost').text('Chi phí: ' + new Intl.NumberFormat().format(eventData.spin_cost) + ' gtlm');
                    renderWheel();
                    renderLights();
                } else {
                    Swal.fire('Thông báo', res.message, 'info').then(() => {
                        window.location.href = 'index.php';
                    });
                }
            });
        }

        function renderWheel() {
            const count = rewards.length;
            const angle = 360 / count;
            let html = '';
            
            rewards.forEach((r, i) => {
                const color = i % 2 === 0 ? '#1e293b' : '#334155';
                const rotation = i * angle;
                html += `
                    <div class="wheel-segment" style="transform: rotate(${rotation}deg); background: ${color}; clip-path: polygon(0 0, 100% 0, 100% 100%); --total-segments: ${count};">
                        <div class="segment-content" style="transform: rotate(${angle / 2}deg) translateY(-140px);">
                            <div class="reward-icon">${r.reward_icon}</div>
                            <div class="reward-name">${r.reward_name}</div>
                        </div>
                    </div>
                `;
            });
            $('#wheel').html(html);
        }

        function renderLights() {
            let html = '';
            for (let i = 0; i < 20; i++) {
                const angle = i * (360 / 20);
                html += `<div class="wheel-light" style="transform: rotate(${angle}deg) translateY(-240px); animation-delay: ${i * 0.1}s;"></div>`;
            }
            $('#lights-container').html(html);
        }

        function spin() {
            if (isSpinning) return;
            
            $('#spin-btn').prop('disabled', true);
            
            $.post('api_events.php', { action: 'spin' }, function(res) {
                if (res.success) {
                    isSpinning = true;
                    const rewardIndex = rewards.findIndex(r => r.id == res.reward.id);
                    const angle = 360 / rewards.length;
                    
                    // Tính toán vòng quay: quay ít nhất 5 vòng + tới vị trí quà
                    // Vị trí quà trên vòng tròn (theo độ) là: - (rewardIndex * angle + angle/2)
                    const extraRotation = 360 * 5 + (360 - (rewardIndex * angle + angle / 2));
                    currentRotation += extraRotation;
                    
                    $('#wheel').css('transform', `rotate(${currentRotation}deg)`);
                    
                    setTimeout(() => {
                        isSpinning = false;
                        $('#spin-btn').prop('disabled', false);
                        
                        Swal.fire({
                            title: 'Chúc Mừng!',
                            html: `
                                <div style="font-size: 60px; margin: 20px 0;">${res.reward.reward_icon}</div>
                                <p>Bạn đã nhận được: <b style="color: #f59e0b;">${res.reward.reward_name}</b></p>
                            `,
                            icon: 'success',
                            confirmButtonColor: '#f59e0b'
                        });
                    }, 5000);
                } else {
                    Swal.fire('Lỗi', res.message, 'error');
                    $('#spin-btn').prop('disabled', false);
                }
            });
        }

        $(document).ready(loadEvent);
    </script>
</body>
</html>
