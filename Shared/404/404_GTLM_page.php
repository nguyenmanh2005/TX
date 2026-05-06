<?php
session_start();
// Đi tìm file load_theme.php ở thư mục cha của cha
require_once '../../db_connect.php';
require_once '../../load_theme.php';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Bác Tài Lạc Đường GTLM</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.2/dist/gsap.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;700;900&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
            height: 100%;
            overflow: hidden;
            background:
                <?= $bgGradientCSS ?>
            ;
            /* Đồng bộ với theme hệ thống */
            cursor: url('../../chuot.png'), auto !important;
        }

        #scene {
            font-family: 'Be Vietnam Pro', sans-serif;
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 40px 20px;
            background: rgba(13, 27, 42, 0.8);
            backdrop-filter: blur(10px);
        }

        /* Cursor Fix */
        * {
            cursor: inherit !important;
        }

        button,
        a,
        .taxi {
            cursor: url('../../img/tay.png'), pointer !important;
        }

        .stars {
            position: absolute;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .star {
            position: absolute;
            width: 2px;
            height: 2px;
            background: white;
            border-radius: 50%;
            animation: twinkle 2s infinite alternate;
        }

        @keyframes twinkle {
            from {
                opacity: 0.2;
            }

            to {
                opacity: 1;
            }
        }

        .road {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: #1a1a2e;
            border-top: 3px solid #f4a261;
        }

        .road-line {
            position: absolute;
            bottom: 45px;
            left: 0;
            right: 0;
            height: 4px;
            background: repeating-linear-gradient(to right, #f4d03f 0px, #f4d03f 40px, transparent 40px, transparent 80px);
            animation: roadMove 0.6s linear infinite;
        }

        @keyframes roadMove {
            from {
                transform: translateX(0);
            }

            to {
                transform: translateX(-80px);
            }
        }

        .taxi {
            position: absolute;
            bottom: 95px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 64px;
            animation: taxiBounce 0.4s ease-in-out infinite alternate;
            filter: drop-shadow(0 4px 16px #f4a26188);
            user-select: none;
        }

        .taxi:active {
            transform: translateX(-50%) scale(0.92);
        }

        @keyframes taxiBounce {
            from {
                transform: translateX(-50%) translateY(0);
            }

            to {
                transform: translateX(-50%) translateY(-6px);
            }
        }

        .honk-bubble {
            position: absolute;
            bottom: 178px;
            left: calc(50% + 24px);
            background: #f4d03f;
            color: #1a1a2e;
            font-weight: 900;
            font-size: 13px;
            padding: 5px 10px;
            border-radius: 16px 16px 16px 4px;
            display: none;
            white-space: nowrap;
            box-shadow: 0 2px 8px #0005;
        }

        .honk-bubble.show {
            display: block;
            animation: popIn 0.3s;
        }

        @keyframes popIn {
            from {
                transform: scale(0.5);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .big-404 {
            font-size: clamp(80px, 18vw, 130px);
            font-weight: 900;
            color: #f4a261;
            line-height: 1;
            text-shadow: 0 0 40px #f4a26166, 4px 4px 0 #c0392b;
            letter-spacing: -4px;
            animation: glitch 3s infinite;
            position: relative;
            z-index: 2;
        }

        @keyframes glitch {

            0%,
            92%,
            100% {
                text-shadow: 0 0 40px #f4a26166, 4px 4px 0 #c0392b;
                transform: none;
            }

            93% {
                text-shadow: -3px 0 #e74c3c, 3px 0 #3498db, 4px 4px 0 #c0392b;
                transform: skewX(-2deg);
            }

            95% {
                text-shadow: 3px 0 #e74c3c, -3px 0 #2ecc71, 4px 4px 0 #c0392b;
                transform: skewX(1deg);
            }

            97% {
                text-shadow: 0 0 40px #f4a26166, 4px 4px 0 #c0392b;
                transform: none;
            }
        }

        .subtitle {
            color: #f4d03f;
            font-size: 20px;
            font-weight: 700;
            margin: 8px 0 6px;
            text-align: center;
            z-index: 2;
            position: relative;
        }

        .desc {
            color: #adb5bd;
            font-size: 14px;
            text-align: center;
            max-width: 340px;
            line-height: 1.7;
            z-index: 2;
            position: relative;
            margin-bottom: 24px;
        }

        .desc span {
            color: #f4a261;
            font-weight: 700;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
            z-index: 2;
            position: relative;
            margin-bottom: 120px;
        }

        .btn {
            display: inline-block; /* Bắt buộc để GSAP di chuyển được thẻ a */
            padding: 10px 22px;
            border-radius: 50px;
            font-family: inherit;
            font-weight: 700;
            font-size: 14px;
            border: none;
            transition: box-shadow 0.3s;
            text-decoration: none;
            position: relative;
            z-index: 100;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: #f4a261;
            color: #0d1b2a;
            box-shadow: 0 4px 16px #f4a26155;
        }

        .btn-ghost {
            background: transparent;
            color: #f4d03f;
            border: 2px solid #f4d03f55;
        }

        .tip {
            position: absolute;
            bottom: 108px;
            right: 18px;
            color: #6c757d;
            font-size: 11px;
            z-index: 3;
        }

        .passenger {
            position: absolute;
            bottom: 100px;
            left: calc(50% - 110px);
            font-size: 28px;
            animation: wait 1.2s ease-in-out infinite alternate;
        }

        @keyframes wait {
            from {
                transform: rotate(-5deg);
            }

            to {
                transform: rotate(5deg) translateY(-3px);
            }
        }

        .counter {
            position: absolute;
            top: 16px;
            right: 16px;
            background: #1a1a2e;
            border: 1px solid #f4a26144;
            border-radius: 8px;
            padding: 6px 12px;
            color: #f4a261;
            font-size: 12px;
            font-weight: 700;
            z-index: 5;
        }

        .dialogue {
            position: absolute;
            top: 14px;
            left: 16px;
            background: #1a1a2e;
            border: 1px solid #f4d03f44;
            border-radius: 8px;
            padding: 6px 12px;
            color: #f4d03f;
            font-size: 11px;
            max-width: 200px;
            z-index: 5;
        }
    </style>
</head>

<body>
    <div id="scene">
        <div class="stars" id="stars"></div>
        <div class="counter" id="counter">Bấm còi: <span id="honkCount">0</span></div>
        <div class="dialogue" id="dialogue">🎙️ "Ê bác ơi, sai đường rồi!"</div>

        <div class="big-404">404</div>
        <div class="subtitle">🚖 Bác tài lạc đường rồi bạn ơi!</div>
        <div class="desc">
            Trang này <span>không tồn tại</span> hoặc đã bị dời đi chỗ khác.<br>
            Như xe ôm đi không có Google Maps vậy đó.
        </div>

        <div class="btn-group">
            <a href="../../index.php" class="btn btn-primary" id="run-btn">🏠 Về trang chủ</a>
            <a href="javascript:history.back()" class="btn btn-ghost">↩ Quay lại</a>
        </div>

        <div class="road">
            <div class="road-line"></div>
        </div>
        <div class="passenger">🧍</div>
        <div class="taxi" id="taxi" onclick="honk()" title="Bấm còi nào!">🚖</div>
        <div class="honk-bubble" id="honkBubble">BEEEP! 📯</div>
        <div class="tip">👆 Bấm vào xe để còi</div>
    </div>

    <script>
        const btn = document.getElementById('run-btn');
        let avoidCount = 0;
        const maxAvoid = 6; // Tăng độ khó lên 6 lần
        
        btn.addEventListener('mouseover', () => {
            if (avoidCount < maxAvoid) {
                avoidCount++;
                const d = document.getElementById('dialogue');
                const trollLines = [
                    '🎙️ "Đố anh bắt được em!"',
                    '🎙️ "Lêu lêu, chậm quá bác ơi!"',
                    '🎙️ "Cút ra chỗ khác chơi!"',
                    '🎙️ "Còn lâu mới về được nhà nhé!"',
                    '🎙️ "Gần được rồi, cố lên tí nữa!"',
                    '🎙️ "Thôi được rồi, cho về đấy!"'
                ];
                d.textContent = trollLines[avoidCount - 1];
                
                // Nhảy cực xa - Toàn màn hình
                const maxX = window.innerWidth * 0.4;
                const maxY = window.innerHeight * 0.4;
                const newX = (Math.random() - 0.5) * maxX * 2;
                const newY = (Math.random() - 0.5) * maxY * 2;
                
                gsap.to(btn, {
                    x: newX,
                    y: newY,
                    rotation: Math.random() * 40 - 20,
                    duration: 0.1, // Nhảy cực nhanh
                    ease: "power2.out"
                });
            }
        });

        btn.addEventListener('click', (e) => {
            if (avoidCount < maxAvoid) {
                e.preventDefault();
            } else {
                e.preventDefault();
                const taxi = document.getElementById('taxi');
                const d = document.getElementById('dialogue');
                d.textContent = '🎙️ "VỀ NHÀ THÔI! GTLM MUÔN NĂM! 🚀"';
                
                gsap.to('#scene', { x: 8, yoyo: true, repeat: 12, duration: 0.05 });
                gsap.to(taxi, {
                    x: window.innerWidth + 500,
                    rotation: 15,
                    duration: 1,
                    ease: "power4.in",
                    onComplete: () => { window.location.href = '../../index.php'; }
                });
                gsap.to('.btn-group, .big-404, .subtitle, .desc', { opacity: 0, duration: 0.3 });
            }
        });

        const starsEl = document.getElementById('stars');
        for (let i = 0; i < 60; i++) {
            const s = document.createElement('div');
            s.className = 'star';
            s.style.left = Math.random() * 100 + '%';
            s.style.top = Math.random() * 100 + '%';
            s.style.animationDelay = (Math.random() * 2) + 's';
            starsEl.appendChild(s);
        }

        let honkCount = 0;
        const honkMessages = ['BEEEP! 📯', 'BIIIIIP! 🔔', 'TOOOOOT! 📢', 'DÍT DÍT! 🎺', 'BEEEEEP 😤', 'ÉCH ÉCH! 🚨'];
        const dialogues = [
            '🎙️ "Ê bác ơi, sai đường rồi!"',
            '🎙️ "Trang này 404 rồi bác ơi!"',
            '🎙️ "Đi đâu vậy bác?"',
            '🎙️ "Bác ơi về trang chủ đi!"',
            '🎙️ "Còi lần nữa là bác tắt máy!"',
            '🎙️ "Xin lỗi không có chỗ này!"',
            '🎙️ "Hết đường rồi bạn ơi 😅"'
        ];
        let dialogIdx = 0;

        function honk() {
            honkCount++;
            document.getElementById('honkCount').textContent = honkCount;
            const bubble = document.getElementById('honkBubble');
            bubble.textContent = honkMessages[Math.floor(Math.random() * honkMessages.length)];
            bubble.classList.remove('show');
            void bubble.offsetWidth;
            bubble.classList.add('show');

            dialogIdx = (dialogIdx + 1) % dialogues.length;
            document.getElementById('dialogue').textContent = dialogues[dialogIdx];

            if (honkCount === 5) document.getElementById('dialogue').textContent = '🎙️ "Bấm còi 5 lần... bạn rảnh thật!"';
            if (honkCount === 10) document.getElementById('dialogue').textContent = '🎙️ "Bạn bấm còi 10 lần rồi đó 😑"';

            if (honkCount >= 20) {
                document.getElementById('dialogue').textContent = '🎙️ "Thôi bác không chở nữa!!! Cút về trang cũ giùm!!!" 😤';
                setTimeout(() => {
                    if (window.history.length > 1) {
                        window.history.back();
                    } else {
                        window.location.href = '../../index.php';
                    }
                }, 1500);
            }
        }
    </script>
</body>

</html>