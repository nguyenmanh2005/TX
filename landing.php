<?php
session_start();

if (isset($_SESSION['Iduser'])) {
    header('Location: index.php');
    exit();
}

require 'db_connect.php';

$totalPlayers = 0;
$totalGamesLogged = 0;
$totalQuests = 0;
$totalGifts = 0;

if ($conn && !$conn->connect_error) {
    $playerResult = $conn->query("SELECT COUNT(*) AS total FROM users");
    if ($playerResult && $row = $playerResult->fetch_assoc()) {
        $totalPlayers = (int)($row['total'] ?? 0);
    }

    $checkGameHistory = $conn->query("SHOW TABLES LIKE 'game_history'");
    if ($checkGameHistory && $checkGameHistory->num_rows > 0) {
        $gameResult = $conn->query("SELECT COUNT(*) AS total FROM game_history");
        if ($gameResult && $row = $gameResult->fetch_assoc()) {
            $totalGamesLogged = (int)($row['total'] ?? 0);
        }
    }

    $checkQuests = $conn->query("SHOW TABLES LIKE 'quests'");
    if ($checkQuests && $checkQuests->num_rows > 0) {
        $questResult = $conn->query("SELECT COUNT(*) AS total FROM quests");
        if ($questResult && $row = $questResult->fetch_assoc()) {
            $totalQuests = (int)($row['total'] ?? 0);
        }
    }

    $checkGifts = $conn->query("SHOW TABLES LIKE 'gifts'");
    if ($checkGifts && $checkGifts->num_rows > 0) {
        $giftResult = $conn->query("SELECT COUNT(*) AS total FROM gifts");
        if ($giftResult && $row = $giftResult->fetch_assoc()) {
            $totalGifts = (int)($row['total'] ?? 0);
        }
    }
}

function formatStat(int $value): string {
    if ($value >= 1000000) {
        return number_format($value / 1000000, 1) . 'M';
    }
    if ($value >= 1000) {
        return number_format($value / 1000, 1) . 'K';
    }
    return (string)$value;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giải Trí Lành Mạnh - Trang Giới Thiệu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/animations.css">

    <style>
        :root {
            --landing-bg: linear-gradient(135deg, #101537 0%, #1f2255 40%, #3f1b5b 100%);
            --landing-accent: #ffb347;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Segoe UI', 'Poppins', 'Roboto', sans-serif;
            background: var(--landing-bg);
            color: #f4f6fb;
            min-height: 100vh;
            overflow-x: hidden;
        }

        #stars {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .landing-container {
            width: min(1200px, 92%);
            margin: 0 auto;
            padding: 30px 0 80px;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0 20px;
            position: sticky;
            top: 0;
            background: rgba(16, 21, 55, 0.9);
            backdrop-filter: blur(12px);
            z-index: 10;
        }

        .logo {
            font-weight: 800;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        nav {
            display: flex;
            gap: 20px;
        }

        nav a {
            color: #f4f6fb;
            text-decoration: none;
            font-weight: 600;
            opacity: 0.8;
            transition: opacity 0.2s ease;
        }

        nav a:hover {
            opacity: 1;
        }

        .cta-group {
            display: flex;
            gap: 10px;
        }

        .btn {
            border: none;
            padding: 12px 20px;
            border-radius: 999px;
            font-weight: 700;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-outline {
            background: transparent;
            color: #f4f6fb;
            border: 1px solid rgba(255, 255, 255, 0.4);
        }

        .btn-primary {
            background: linear-gradient(135deg, #ff9966, #ff5e62);
            color: #fff;
            box-shadow: 0 10px 25px rgba(255, 99, 72, 0.4);
        }

        .btn:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 12px 30px rgba(255, 99, 72, 0.5);
        }

        .hero {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 40px;
            align-items: center;
            margin-top: 40px;
            padding: 40px;
            border-radius: 32px;
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 25px 70px rgba(1, 14, 31, 0.6);
            position: relative;
            overflow: hidden;
        }

        .hero::after {
            content: '';
            position: absolute;
            width: 380px;
            height: 380px;
            background: radial-gradient(circle, rgba(255, 99, 72, 0.25), transparent 60%);
            top: -120px;
            right: -120px;
            z-index: 0;
        }

        .hero-copy {
            position: relative;
            z-index: 1;
        }

        .hero h1 {
            font-size: clamp(36px, 4vw, 56px);
            margin: 0 0 20px;
            line-height: 1.1;
        }

        .highlighted {
            color: var(--landing-accent);
        }

        .hero p {
            font-size: 18px;
            line-height: 1.6;
            opacity: 0.85;
        }

        .hero-cta {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .hero-stats {
            display: flex;
            gap: 20px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .hero-stat {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 16px 20px;
            min-width: 140px;
            text-align: center;
        }

        .hero-stat .value {
            font-size: 28px;
            font-weight: 800;
        }

        .hero-visual {
            position: relative;
            z-index: 1;
            border-radius: 24px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.08);
            padding: 30px;
        }

        .hero-preview {
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }

        .hero-preview img {
            width: 100%;
            display: block;
            object-fit: cover;
        }

        .chip {
            position: absolute;
            background: rgba(15, 15, 35, 0.8);
            padding: 14px 18px;
            border-radius: 16px;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.35);
        }

        .chip-quests { top: 20px; right: 20px; }
        .chip-lucky { bottom: 20px; left: 20px; }

        section {
            margin-top: 80px;
        }

        .section-title {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 15px;
        }

        .section-subtitle {
            font-size: 18px;
            opacity: 0.8;
            max-width: 640px;
        }

        .feature-grid {
            margin-top: 40px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 20px;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.06);
            border-radius: 18px;
            padding: 24px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.25);
            transition: transform 0.25s ease, border 0.25s ease;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            border-color: rgba(255, 255, 255, 0.25);
        }

        .feature-icon {
            font-size: 32px;
            margin-bottom: 12px;
            color: var(--landing-accent);
        }

        .feature-card h3 {
            margin: 0 0 10px;
        }

        .feature-card p {
            margin: 0;
            font-size: 15px;
            line-height: 1.5;
            opacity: 0.85;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            margin-top: 40px;
        }

        .step-card {
            background: rgba(19, 25, 69, 0.9);
            border-radius: 18px;
            padding: 24px;
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .step-number {
            position: absolute;
            top: -18px;
            left: 20px;
            font-size: 64px;
            font-weight: 900;
            color: rgba(255, 255, 255, 0.08);
        }

        .community {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 40px;
        }

        .community-card {
            background: rgba(255, 255, 255, 0.06);
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 25px 40px rgba(0, 0, 0, 0.25);
        }

        .community-card h4 {
            margin: 0 0 8px;
            font-size: 18px;
        }

        .community-card p {
            margin: 0;
            opacity: 0.82;
            line-height: 1.6;
        }

        .cta-final {
            text-align: center;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 193, 94, 0.15));
            padding: 50px 30px;
            border-radius: 30px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.35);
        }

        .cta-final h2 {
            font-size: 36px;
            margin-bottom: 16px;
        }

        .cta-final p {
            font-size: 18px;
            opacity: 0.85;
            margin-bottom: 30px;
        }

        footer {
            margin-top: 60px;
            text-align: center;
            opacity: 0.7;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            header {
                flex-direction: column;
                gap: 16px;
                padding: 16px;
            }

            nav {
                flex-wrap: wrap;
                justify-content: center;
            }

            .hero {
                padding: 30px;
            }
        }
    </style>
</head>
<body>
<div id="stars"></div>
<header class="landing-container">
    <div class="logo">
        <i class="fas fa-bolt"></i>
        Giải Trí Lành Mạnh
    </div>
    <nav>
        <a href="#features">Tính năng</a>
        <a href="#steps">Bắt đầu</a>
        <a href="#community">Cộng đồng</a>
        <a href="#signup">Tham gia</a>
    </nav>
    <div class="cta-group">
        <button class="btn btn-outline" onclick="window.location.href='login.php'">Đăng nhập</button>
        <button class="btn btn-primary" onclick="window.location.href='auth.php'">Đăng ký miễn phí</button>
    </div>
</header>

<main class="landing-container">
    <section class="hero">
        <div class="hero-copy">
            <p style="letter-spacing: 3px; text-transform: uppercase; font-weight: 600; color: var(--landing-accent);">Cộng đồng giải trí ảo</p>
            <h1>Trải nghiệm hơn <span class="highlighted">20 mini game</span> & hệ thống nhiệm vụ đỉnh cao.</h1>
            <p>
                Toàn bộ tiền tệ đều là ảo nhưng cảm xúc thì có thật. Hoàn thành quest, mở khóa danh hiệu, xây dựng hồ sơ cá nhân với những vật phẩm độc quyền – tất cả chỉ trong vài phút trải nghiệm.
            </p>
            <div class="hero-cta">
                <button class="btn btn-primary" onclick="window.location.href='auth.php'">
                    Bắt đầu ngay
                    <i class="fas fa-arrow-right"></i>
                </button>
                <button class="btn btn-outline" onclick="document.getElementById('features').scrollIntoView({behavior: 'smooth'});">
                    Xem tính năng
                </button>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="value"><?= formatStat($totalPlayers) ?>+</div>
                    <div>Người chơi</div>
                </div>
                <div class="hero-stat">
                    <div class="value"><?= formatStat($totalGamesLogged) ?>+</div>
                    <div>Ván game đã log</div>
                </div>
                <div class="hero-stat">
                    <div class="value"><?= formatStat($totalQuests) ?>+</div>
                    <div>Nhiệm vụ đang mở</div>
                </div>
            </div>
        </div>
        <div class="hero-visual">
            <div class="hero-preview">
                <img src="1.png" alt="Preview games">
            </div>
            <div class="chip chip-quests">
                🎯 Quest tự động cập nhật tiến độ
            </div>
            <div class="chip chip-lucky">
                🎡 Lucky Wheel & Gift System cho mọi người
            </div>
        </div>
    </section>

    <section id="features">
        <div class="section-title">Tính năng nổi bật</div>
        <div class="section-subtitle">Thiết kế riêng cho người yêu mini game và những thử thách mới mỗi ngày.</div>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon">🎮</div>
                <h3>20+ mini game</h3>
                <p>Bầu cua, Blackjack, Coin Flip, Bingo, Roulette... tất cả đều dùng chung ví tiền ảo với giao diện cao cấp.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🎯</div>
                <h3>Nhiệm vụ thông minh</h3>
                <p>Quest hàng ngày/tuần, tự ghi nhận log qua `logGameHistory()` giúp người chơi tiến bộ tự nhiên.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🎁</div>
                <h3>Gift & Friends</h3>
                <p>Trao tiền ảo, item (theme, cursor, khung) chỉ bằng vài cú click. Tương tác xã hội tăng x2.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Statistics realtime</h3>
                <p>Trang thống kê mới với API realtime (`api_statistics.php`) cho phép người chơi xem win-rate tức thì.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🏆</div>
                <h3>Tournament & Leaderboard</h3>
                <p>Hệ thống rank, giải đấu, danh hiệu tự động cập nhật, kích thích cạnh tranh lành mạnh.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🌀</div>
                <h3>Lucky Wheel hằng ngày</h3>
                <p>Giữ chân người chơi bằng những phần thưởng bất ngờ: tiền ảo, theme đặc biệt, khung avatar.</p>
            </div>
        </div>
    </section>

    <section id="steps">
        <div class="section-title">Bắt đầu cực nhanh</div>
        <div class="section-subtitle">Chỉ 3 bước để bạn thấy tiền ảo nhảy múa.</div>
        <div class="steps">
            <div class="step-card">
                <div class="step-number">01</div>
                <h3>Đăng ký & nhận bonus</h3>
                <p>Đăng ký tại <strong>auth.php</strong>, nhận ngay khoản vốn ảo đầu tiên cùng bộ nhiệm vụ onboarding.</p>
            </div>
            <div class="step-card">
                <div class="step-number">02</div>
                <h3>Hoàn thành tour nhiệm vụ</h3>
                <p>Hero UI gợi ý quest đầu tiên, hướng dẫn bạn mở lucky wheel, gift code và statistics dashboard.</p>
            </div>
            <div class="step-card">
                <div class="step-number">03</div>
                <h3>Tham gia giải đấu</h3>
                <p>Ghi log tự động vào tournament: càng chơi nhiều càng có điểm, phần thưởng danh hiệu cực chất.</p>
            </div>
        </div>
    </section>

    <section id="community">
        <div class="section-title">Cộng đồng & cảm hứng</div>
        <div class="section-subtitle">Những lời nhắn từ người chơi thật, giúp người mới tự tin hơn.</div>
        <div class="community">
            <div class="community-card">
                <h4>Thành viên #5798</h4>
                <p>“Quest tracking cực tiện, chơi bầu cua xong thấy nhiệm vụ hoàn thành liền. Giao diện mới nhìn đã phê.”</p>
            </div>
            <div class="community-card">
                <h4>Thành viên #8321</h4>
                <p>“Stat dashboard mới giúp mình biết game nào hòa vốn, game nào lỗ để điều chỉnh chiến thuật. Rất hữu ích.”</p>
            </div>
            <div class="community-card">
                <h4>Thành viên #10442</h4>
                <p>“Gift system làm tụi mình tặng quà nhau vui phết, chưa kể tournament tuần tạo cảm giác cạnh tranh nhẹ nhàng.”</p>
            </div>
        </div>
    </section>

    <section id="signup" class="cta-final">
        <h2>Chuẩn bị thăng hạng chưa?</h2>
        <p>Hãy bắt đầu với tài khoản miễn phí, tiền ảo vô hạn, nhiệm vụ mới mỗi ngày.</p>
        <div class="hero-cta" style="justify-content: center;">
            <button class="btn btn-primary" onclick="window.location.href='auth.php'">Đăng ký ngay</button>
            <button class="btn btn-outline" onclick="window.location.href='login.php'">Tôi đã có tài khoản</button>
        </div>
        <div class="hero-stats" style="justify-content: center; margin-top: 30px;">
            <div class="hero-stat">
                <div class="value"><?= formatStat($totalGifts) ?>+</div>
                <div>Quà đã trao</div>
            </div>
            <div class="hero-stat">
                <div class="value">24/7</div>
                <div>Hoạt động</div>
            </div>
        </div>
    </section>
</main>

<footer>
    © <?= date('Y') ?> Giải Trí Lành Mạnh • Tất cả tiền tệ trong web đều là ảo.
</footer>

<script>
    const starCanvas = document.getElementById('stars');
    const ctx = starCanvas.getContext('2d');
    const stars = [];

    function resizeCanvas() {
        starCanvas.width = window.innerWidth;
        starCanvas.height = window.innerHeight;
        generateStars();
    }

    function generateStars() {
        stars.length = 0;
        for (let i = 0; i < 150; i++) {
            stars.push({
                x: Math.random() * starCanvas.width,
                y: Math.random() * starCanvas.height,
                radius: Math.random() * 1.5,
                alpha: Math.random()
            });
        }
    }

    function drawStars() {
        ctx.clearRect(0, 0, starCanvas.width, starCanvas.height);
        stars.forEach(star => {
            star.alpha += (Math.random() - 0.5) * 0.05;
            star.alpha = Math.min(Math.max(star.alpha, 0.1), 0.9);
            ctx.beginPath();
            ctx.arc(star.x, star.y, star.radius, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(255,255,255,' + star.alpha + ')';
            ctx.fill();
        });
        requestAnimationFrame(drawStars);
    }

    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();
    drawStars();
</script>
</body>
</html>














