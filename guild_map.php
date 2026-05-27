<?php
require_once 'db_connect.php';
session_start();

/**
 * 🗺️ Guild World Map - Trận Địa Đại Chiến
 * Hiển thị các vùng đất và quyền chiếm đóng của các Guild.
 */

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit;
}

// Lấy danh sách vùng đất và thông tin Guild chiếm đóng
$sql = "SELECT t.*, g.Name as GuildName, g.ImageURL as GuildLogo, g.id as GuildId 
        FROM territories t 
        LEFT JOIN guilds g ON t.occupying_guild_id = g.id";
$result = $conn->query($sql);
$regions = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Lấy thông tin Guild của người dùng hiện tại
$userId = $_SESSION['Iduser'];
$userGuild = $conn->query("SELECT guild_id FROM users WHERE Iduser = $userId")->fetch_assoc();
$myGuildId = $userGuild['guild_id'] ?? null;

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đại Chiến Lục Địa GTLM | World Map</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #020617;
            --primary: #6366f1;
            --secondary: #a855f7;
            --gold: #fbbf24;
            --success: #10b981;
            --danger: #ef4444;
            --card-bg: rgba(30, 41, 59, 0.7);
        }

        * { box-sizing: border-box; }

        body {
            background: var(--bg-dark);
            color: #f8fafc;
            font-family: 'Inter', sans-serif;
            margin: 0;
            overflow: hidden;
            height: 100vh;
            width: 100vw;
        }

        /* 🌌 Map Background with Parallax effect */
        .map-wrapper {
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.15) 0%, transparent 70%),
                url('https://www.transparenttextures.com/patterns/stardust.png'),
                linear-gradient(to bottom, #020617, #0f172a);
            position: relative;
            overflow: hidden;
            cursor: grab;
        }

        .map-wrapper:active { cursor: grabbing; }

        /* 🏔️ Decorative Clouds/Mist */
        .mist {
            position: absolute;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.03) 0%, transparent 60%);
            animation: drift 60s linear infinite;
            pointer-events: none;
        }

        @keyframes drift {
            from { transform: translate(-25%, -25%) rotate(0deg); }
            to { transform: translate(-20%, -20%) rotate(360deg); }
        }

        /* 📍 Region Cards */
        .region {
            position: absolute;
            width: 240px;
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 20px;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            animation: fadeInScale 0.6s ease backwards;
        }

        @keyframes fadeInScale {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .region:hover {
            transform: scale(1.05) translateY(-10px);
            border-color: var(--primary);
            box-shadow: 0 20px 40px rgba(99, 102, 241, 0.4);
            z-index: 100;
        }

        .region.occupied {
            border-color: var(--gold);
            background: rgba(251, 191, 36, 0.05);
        }

        .region.my-territory {
            border-color: var(--success);
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.3);
        }

        .region-icon {
            font-size: 40px;
            margin-bottom: 15px;
            display: block;
            filter: drop-shadow(0 0 10px rgba(99, 102, 241, 0.5));
        }

        .region-name {
            font-size: 18px;
            font-weight: 900;
            color: #fff;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .bonus-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .guild-info {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 15px;
            margin-top: 10px;
        }

        .guild-logo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid var(--gold);
            object-fit: cover;
            margin-bottom: 8px;
            box-shadow: 0 0 15px rgba(251, 191, 36, 0.3);
        }

        .guild-name {
            font-size: 14px;
            font-weight: 700;
            color: var(--gold);
        }

        /* 🎮 UI Overlay */
        .overlay-header {
            position: fixed;
            top: 30px; left: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            z-index: 1000;
        }

        .btn-ui {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 12px 24px;
            border-radius: 16px;
            text-decoration: none;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .btn-ui:hover {
            background: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.4);
        }

        .season-info {
            position: fixed;
            top: 30px; right: 30px;
            text-align: right;
            z-index: 1000;
        }

        .season-timer {
            font-size: 24px;
            font-weight: 900;
            color: var(--gold);
            text-shadow: 0 0 15px rgba(251, 191, 36, 0.5);
        }

        .season-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.7;
        }

        /* 📊 TP Progress Bar */
        .tp-progress {
            width: 100%;
            height: 6px;
            background: rgba(0,0,0,0.3);
            border-radius: 3px;
            margin-top: 10px;
            overflow: hidden;
        }

        .tp-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            width: 0%;
            transition: width 1s ease-out;
        }

        /* 📱 Responsive */
        @media (max-width: 768px) {
            .region { width: 180px; padding: 15px; }
            .region-icon { font-size: 30px; }
        }
    </style>
</head>
<body>

    <div class="mist"></div>

    <div class="overlay-header">
        <a href="index.php" class="btn-ui"><i class="fa fa-arrow-left"></i> Về Sảnh</a>
        <div style="background: rgba(0,0,0,0.5); padding: 12px 20px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1);">
            <i class="fa fa-shield-halved" style="color: var(--primary)"></i>
            <span style="margin-left: 10px; font-weight: 700;">Đại Chiến Lãnh Thổ</span>
        </div>
    </div>

    <div class="season-info">
        <div class="season-label">Reset Mùa Giải Trong</div>
        <div class="season-timer" id="countdown">--d --h --m</div>
    </div>

    <div class="map-wrapper" id="map">
        <?php 
        // Tọa độ các vùng trên map
        $coords = [
            1 => ['top' => '20%', 'left' => '15%', 'icon' => '⛰️'],
            2 => ['top' => '15%', 'left' => '65%', 'icon' => '🐲'],
            3 => ['top' => '55%', 'left' => '25%', 'icon' => '🐾'],
            4 => ['top' => '65%', 'left' => '70%', 'icon' => '🐓'],
        ];

        foreach ($regions as $r): 
            $pos = $coords[$r['id']] ?? ['top' => '50%', 'left' => '50%', 'icon' => '🏰'];
            $isMine = ($myGuildId && $r['GuildId'] == $myGuildId);
            $tpPercent = min(100, ($r['total_tp'] / 1000) * 100); // Giả sử 1000 TP để chiếm vùng
        ?>
            <div class="region <?= $r['occupying_guild_id'] ? 'occupied' : '' ?> <?= $isMine ? 'my-territory' : '' ?>" 
                 style="top: <?= $pos['top'] ?>; left: <?= $pos['left'] ?>; animation-delay: <?= $r['id'] * 0.1 ?>s">
                
                <span class="region-icon"><?= $pos['icon'] ?></span>
                <div class="region-name"><?= htmlspecialchars($r['name']) ?></div>
                
                <div class="bonus-badge">
                    <i class="fa fa-circle-dollar-to-slot"></i> 
                    +<?= ($r['bonus_value'] * 100) ?>% <?= $r['bonus_type'] == 'tax' ? 'GTLM Thuế' : ($r['bonus_type'] == 'dungeon' ? 'Lượt Dun' : 'EXP') ?>
                </div>

                <?php if ($r['occupying_guild_id']): ?>
                    <div class="guild-info">
                        <img src="<?= htmlspecialchars($r['GuildLogo'] ?: 'img/guild_default.png') ?>" class="guild-logo" onerror="this.src='https://cdn-icons-png.flaticon.com/512/1162/1162283.png'">
                        <div class="guild-name"><?= htmlspecialchars($r['GuildName']) ?></div>
                    </div>
                <?php else: ?>
                    <div style="font-size: 11px; opacity: 0.5; margin-bottom: 5px;">Chưa có chủ sở hữu</div>
                    <div class="tp-progress">
                        <div class="tp-fill" style="width: <?= $tpPercent ?>%"></div>
                    </div>
                    <div style="font-size: 9px; margin-top: 5px; color: var(--primary);">Tiến trình: <?= $r['total_tp'] ?>/1000 TP</div>
                <?php endif; ?>

                <button onclick="challengeRegion(<?= $r['id'] ?>, '<?= addslashes($r['name']) ?>')" 
                        style="margin-top: 15px; width: 100%; padding: 8px; border-radius: 10px; border: none; background: var(--primary); color: white; font-weight: 700; cursor: pointer;">
                    <?= $isMine ? 'QUẢN LÝ' : 'TUYÊN CHIẾN' ?>
                </button>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function challengeRegion(id, name) {
            Swal.fire({
                title: 'Tuyên Chiến: ' + name,
                text: "Guild của bạn có muốn ra chiêu giành quyền chiếm đóng vùng này?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#6366f1',
                cancelButtonColor: '#ef4444',
                confirmButtonText: 'XUẤT QUÂN!',
                cancelButtonText: 'HỦY'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Thành công!', 'Đã ghi nhận lệnh tuyên chiến. Thắng Guild War để tích lũy TP!', 'success');
                }
            })
        }

        // Đếm ngược reset mùa giải (Giả lập: Cuối tháng)
        function updateCountdown() {
            const now = new Date();
            const nextMonth = new Date(now.getFullYear(), now.getMonth() + 1, 1);
            const diff = nextMonth - now;

            const d = Math.floor(diff / (1000 * 60 * 60 * 24));
            const h = Math.floor((diff / (1000 * 60 * 60)) % 24);
            const m = Math.floor((diff / (1000 * 60)) % 60);

            document.getElementById('countdown').innerHTML = `${d}d ${h}h ${m}m`;
        }

        setInterval(updateCountdown, 60000);
        updateCountdown();

        // Hiệu ứng kéo bản đồ (Kéo thả đơn giản)
        let isDown = false;
        let startX, startY, scrollLeft, scrollTop;
        const slider = document.getElementById('map');

        slider.addEventListener('mousedown', (e) => {
            isDown = true;
            startX = e.pageX - slider.offsetLeft;
            startY = e.pageY - slider.offsetTop;
        });
        slider.addEventListener('mouseleave', () => { isDown = false; });
        slider.addEventListener('mouseup', () => { isDown = false; });
        slider.addEventListener('mousemove', (e) => {
            if(!isDown) return;
            e.preventDefault();
        });
    </script>
</body>
</html>
