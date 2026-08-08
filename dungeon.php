<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require_once 'load_theme.php';
require_once 'dungeon_helper.php';

$userId = $_SESSION['Iduser'];
$dungeon = get_or_generate_daily_dungeon($conn);

// Get user progress
$completions = [];
$stmt = $conn->prepare("SELECT * FROM dungeon_completions WHERE user_id = ? AND dungeon_id = ?");
$stmt->bind_param("ii", $userId, $dungeon['id']);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $completions[$row['tier']] = $row;
}

// Get rewards for display
$rewards = [];
$stmt = $conn->prepare("SELECT dr.*, m.name as mat_name, m.icon as mat_icon, m.rarity 
                        FROM dungeon_rewards dr 
                        JOIN materials m ON dr.material_id = m.id 
                        WHERE dr.dungeon_id = ?");
$stmt->bind_param("i", $dungeon['id']);
$stmt->execute();
$resRewards = $stmt->get_result();
while ($row = $resRewards->fetch_assoc()) {
    $rewards[$row['tier']][] = $row;
}

// Type display config
$typeConfig = [
    'hunt'       => ['icon' => '⚔️', 'label' => 'SĂNN MỒI',     'color' => '#ef4444', 'desc' => 'Săn thắng liên tiếp'],
    'accumulate' => ['icon' => '💰', 'label' => 'TÍCH LŨY',     'color' => '#f59e0b', 'desc' => 'Cược đủ số GTLM mục tiêu'],
    'streak'     => ['icon' => '🔥', 'label' => 'CHUỖI THẮNG',  'color' => '#f97316', 'desc' => 'Thắng liên tiếp không nghỉ'],
    'specialist' => ['icon' => '🎯', 'label' => 'CHUYÊN GIA',   'color' => '#8b5cf6', 'desc' => 'Thành thạo một game cụ thể'],
    'survivor'   => ['icon' => '🛡️', 'label' => 'SINH TỒN',     'color' => '#06b6d4', 'desc' => 'Giữ số dư trước áp lực'],
    'explorer'   => ['icon' => '🗺️', 'label' => 'THÁM HIỂM',   'color' => '#10b981', 'desc' => 'Khám phá nhiều game khác nhau'],
];
$tc = $typeConfig[$dungeon['type']] ?? ['icon' => '⚡', 'label' => strtoupper($dungeon['type']), 'color' => '#6366f1', 'desc' => ''];

$tierConfig = [
    1 => ['name' => 'Đồng', 'icon' => '🥉', 'color' => '#cd7f32', 'glow' => 'rgba(205,127,50,0.4)', 'gradient' => 'linear-gradient(135deg,#7c3f00,#cd7f32)'],
    2 => ['name' => 'Bạc',  'icon' => '🥈', 'color' => '#a8a9ad', 'glow' => 'rgba(168,169,173,0.4)', 'gradient' => 'linear-gradient(135deg,#4a4a4a,#a8a9ad)'],
    3 => ['name' => 'Vàng', 'icon' => '🥇', 'color' => '#ffd700', 'glow' => 'rgba(255,215,0,0.5)',   'gradient' => 'linear-gradient(135deg,#7d5a00,#ffd700)'],
];

// Count total claimed
$totalClaimed = count(array_filter($completions, fn($c) => $c['status'] === 'claimed'));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚔️ Dungeon – Thử Thách Hàng Ngày</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700;900&family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.7.3/sweetalert2.all.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            color: white;
            overflow-x: hidden;
        }

        /* ─── Atmospheric Background ─── */
        body::before {
            content: '';
            position: fixed; inset: 0; z-index: 0;
            background: radial-gradient(ellipse 80% 50% at 50% 0%, rgba(139,92,246,0.15) 0%, transparent 60%),
                        radial-gradient(ellipse 60% 40% at 80% 80%, rgba(239,68,68,0.08) 0%, transparent 50%);
            pointer-events: none;
        }

        /* ─── Floating Particles ─── */
        .particle {
            position: fixed; width: 3px; height: 3px; border-radius: 50%;
            background: rgba(255,215,0,0.6); pointer-events: none; z-index: 1;
            animation: float-up linear infinite;
        }
        @keyframes float-up {
            0%   { transform: translateY(100vh) scale(0); opacity: 0; }
            10%  { opacity: 1; }
            90%  { opacity: 0.5; }
            100% { transform: translateY(-100px) scale(1.5); opacity: 0; }
        }

        /* ─── Layout ─── */
        .dungeon-wrap {
            position: relative; z-index: 2;
            max-width: 960px;
            margin: 0 auto;
            padding: 30px 20px 60px;
        }

        /* ─── Top Nav ─── */
        .top-nav {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px;
        }
        .top-nav a {
            display: flex; align-items: center; gap: 7px;
            color: rgba(255,255,255,0.6); text-decoration: none;
            font-size: 13px; font-weight: 600;
            padding: 8px 16px; border-radius: 20px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            transition: 0.2s;
        }
        .top-nav a:hover { background: rgba(255,255,255,0.12); color: white; }

        /* ─── Hero Header ─── */
        .dungeon-hero {
            text-align: center;
            padding: 50px 40px 40px;
            background: linear-gradient(135deg, rgba(0,0,0,0.6) 0%, rgba(20,10,40,0.8) 100%);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 28px;
            backdrop-filter: blur(20px);
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }
        .dungeon-hero::before {
            content: '';
            position: absolute; top: -1px; left: 10%; right: 10%; height: 2px;
            background: linear-gradient(to right, transparent, <?= $tc['color'] ?>, transparent);
        }
        .dungeon-type-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: <?= $tc['color'] ?>22;
            border: 1px solid <?= $tc['color'] ?>55;
            color: <?= $tc['color'] ?>;
            padding: 6px 20px;
            border-radius: 30px;
            font-size: 11px; font-weight: 800; letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 20px;
        }
        .dungeon-hero h1 {
            font-family: 'Cinzel', serif;
            font-size: clamp(28px, 5vw, 48px);
            font-weight: 900;
            background: linear-gradient(135deg, #f1c40f 0%, #e67e22 50%, #fff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 2px;
            margin-bottom: 10px;
            text-shadow: none;
        }
        .dungeon-hero p {
            color: rgba(255,255,255,0.55);
            font-size: 15px;
        }
        .hero-badges {
            display: flex; justify-content: center; gap: 12px;
            margin-top: 22px; flex-wrap: wrap;
        }
        .hero-badge {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 6px 18px; border-radius: 20px;
            font-size: 12px; color: rgba(255,255,255,0.7);
            display: flex; align-items: center; gap: 6px;
        }
        .hero-badge strong { color: white; }

        /* ─── Reset countdown ─── */
        .reset-banner {
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.2);
            border-radius: 14px;
            padding: 12px 24px;
            text-align: center;
            font-size: 13px;
            color: rgba(255,255,255,0.6);
            margin-bottom: 30px;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        #countdown { font-weight: 800; color: #ef4444; font-size: 15px; }

        /* ─── Tier Cards ─── */
        .tiers-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .tier-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.09);
            border-radius: 24px;
            padding: 28px 30px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 24px;
            align-items: center;
            transition: all 0.35s ease;
            position: relative;
            overflow: hidden;
        }
        .tier-card::before {
            content: '';
            position: absolute; inset: 0;
            border-radius: 24px;
            opacity: 0;
            transition: opacity 0.35s;
        }
        .tier-card.status-completed { border-color: var(--tier-color, rgba(255,255,255,0.09)); }
        .tier-card.status-completed::before { background: radial-gradient(ellipse at left, var(--tier-glow, transparent) 0%, transparent 60%); opacity: 1; }
        .tier-card.status-claimed { opacity: 0.6; }
        .tier-card:hover { transform: translateY(-2px); }

        /* Tier Icon Circle */
        .tier-icon-wrap {
            width: 72px; height: 72px; position: relative; flex-shrink: 0;
        }
        .tier-icon-wrap svg {
            width: 72px; height: 72px; transform: rotate(-90deg);
        }
        .tier-icon-wrap .bg-ring { fill: none; stroke: rgba(255,255,255,0.08); stroke-width: 5; }
        .tier-icon-wrap .prog-ring { fill: none; stroke-width: 5; stroke-linecap: round; transition: stroke-dashoffset 1s cubic-bezier(0.4,0,0.2,1); }
        .tier-icon-inner {
            position: absolute; inset: 0;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            font-size: 26px; line-height: 1;
        }
        .tier-pct {
            font-size: 10px; font-weight: 800; color: rgba(255,255,255,0.7); margin-top: 1px;
        }

        /* Tier Info */
        .tier-info {}
        .tier-label {
            display: flex; align-items: center; gap: 10px; margin-bottom: 6px;
        }
        .tier-name {
            font-size: 18px; font-weight: 800;
        }
        .tier-status-pill {
            font-size: 10px; font-weight: 700; letter-spacing: 1px;
            padding: 3px 10px; border-radius: 20px;
            text-transform: uppercase;
        }
        .pill-locked    { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.35); }
        .pill-progress  { background: rgba(59,130,246,0.2); color: #60a5fa; border: 1px solid rgba(59,130,246,0.3); }
        .pill-done      { background: rgba(16,185,129,0.2); color: #34d399; border: 1px solid rgba(16,185,129,0.3); animation: pulse-pill 2s infinite; }
        .pill-claimed   { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.3); }
        @keyframes pulse-pill {
            0%,100% { box-shadow: none; }
            50%      { box-shadow: 0 0 8px rgba(16,185,129,0.6); }
        }

        .tier-progress-text {
            font-size: 13px; color: rgba(255,255,255,0.5);
            margin-bottom: 10px;
        }
        .tier-progress-text strong { color: white; font-size: 15px; }

        /* thin bar */
        .thin-bar {
            height: 6px; background: rgba(255,255,255,0.08); border-radius: 8px;
            overflow: hidden; margin-bottom: 14px; width: 100%;
        }
        .thin-fill {
            height: 100%; border-radius: 8px;
            transition: width 1s cubic-bezier(0.4,0,0.2,1);
        }

        /* Rewards row */
        .reward-row {
            display: flex; flex-wrap: wrap; gap: 8px;
        }
        .reward-chip {
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 12px; font-weight: 600;
            display: flex; align-items: center; gap: 5px;
            color: rgba(255,255,255,0.75);
        }
        .reward-chip.gold { border-color: rgba(255,215,0,0.3); color: #ffd700; }
        .reward-chip span { font-size: 14px; }

        /* Claim Button */
        .btn-claim {
            flex-shrink: 0;
            min-width: 140px;
            padding: 14px 22px;
            border-radius: 16px;
            border: none;
            font-weight: 800;
            font-size: 13px;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.25s;
            white-space: nowrap;
        }
        .btn-claim.ready {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 20px rgba(16,185,129,0.45);
            animation: btn-pulse 2s infinite;
        }
        @keyframes btn-pulse {
            0%,100% { box-shadow: 0 4px 20px rgba(16,185,129,0.4); }
            50%      { box-shadow: 0 6px 30px rgba(16,185,129,0.7); }
        }
        .btn-claim.ready:hover { transform: translateY(-3px) scale(1.03); }
        .btn-claim.claimed {
            background: rgba(255,255,255,0.05);
            color: rgba(255,255,255,0.25);
            cursor: default;
            border: 1px dashed rgba(255,255,255,0.1);
        }
        .btn-claim.locked {
            background: rgba(255,255,255,0.04);
            color: rgba(255,255,255,0.2);
            cursor: not-allowed;
            border: 1px dashed rgba(255,255,255,0.08);
        }

        /* ─── All Claimed Banner ─── */
        .all-done-banner {
            text-align: center;
            padding: 30px;
            background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(5,150,105,0.05));
            border: 1px solid rgba(16,185,129,0.3);
            border-radius: 20px;
            margin-top: 25px;
            font-size: 15px;
        }
        .all-done-banner .trophy { font-size: 40px; margin-bottom: 10px; display: block; }

        @media (max-width: 600px) {
            .tier-card { grid-template-columns: 1fr; text-align: center; }
            .tier-label { justify-content: center; }
            .reward-row { justify-content: center; }
            .btn-claim { width: 100%; }
            .tier-icon-wrap { margin: 0 auto; }
        }
    </style>
</head>
<body>

    <?php
    // Generate particles
    for ($p = 0; $p < 12; $p++):
        $left  = rand(2, 98);
        $dur   = rand(8, 20);
        $delay = rand(0, 12);
        $size  = rand(2, 5);
    ?>
    <div class="particle" style="left:<?= $left ?>%;width:<?= $size ?>px;height:<?= $size ?>px;animation-duration:<?= $dur ?>s;animation-delay:-<?= $delay ?>s;opacity:<?= rand(3,8)/10 ?>;"></div>
    <?php endfor; ?>

    <div class="dungeon-wrap">

        <!-- Nav -->
        <div class="top-nav">
            <a href="index.php">← Trang Chủ</a>
            <a href="inventory.php">📦 Kho Đồ</a>
        </div>

        <!-- Hero -->
        <div class="dungeon-hero">
            <div class="dungeon-type-badge"><?= $tc['icon'] ?> <?= $tc['label'] ?></div>
            <h1><?= htmlspecialchars($dungeon['name']) ?></h1>
            <p><?= htmlspecialchars($tc['desc']) ?> · Thử thách hàng ngày</p>
            <?php if (!empty($dungeon['game_required'])): ?>
            <p style="color: <?= $tc['color'] ?>; margin-top:8px; font-weight:700; font-size:14px;">
                🎮 Game yêu cầu: <?= htmlspecialchars($dungeon['game_required']) ?>
            </p>
            <?php endif; ?>
            <div class="hero-badges">
                <div class="hero-badge">📅 <strong><?= date('d/m/Y') ?></strong></div>
                <div class="hero-badge">🏆 <strong><?= $totalClaimed ?>/3</strong> đã hoàn thành</div>
                <div class="hero-badge">⏰ Reset lúc <strong>00:00</strong></div>
            </div>
        </div>

        <!-- Countdown -->
        <div class="reset-banner">
            ⏱ Thời gian còn lại hôm nay: <span id="countdown">--:--:--</span>
        </div>

        <!-- Tier Cards -->
        <div class="tiers-grid">
        <?php for ($tier = 1; $tier <= 3; $tier++):
            $target     = $dungeon["tier{$tier}_target"];
            $comp       = $completions[$tier] ?? ['progress' => 0, 'status' => 'in_progress'];
            $progress   = $comp['progress'];
            $pct        = min(100, ($progress / $target) * 100);
            $status     = $comp['status'];
            $tc2        = $tierConfig[$tier];

            // Circumference for SVG ring: r=30
            $circum = 2 * M_PI * 30; // ~188.5
            $dashoffset = $circum * (1 - $pct / 100);

            // Status pill / button
            if ($status === 'claimed') {
                $pillClass = 'pill-claimed'; $pillText = '✓ Đã nhận';
                $btnClass  = 'claimed';      $btnText  = '✓ Đã nhận';
            } elseif ($status === 'completed') {
                $pillClass = 'pill-done';    $pillText = '✦ Sẵn sàng!';
                $btnClass  = 'ready';        $btnText  = '🎁 Nhận Thưởng';
            } else {
                $pillClass = ($pct > 0) ? 'pill-progress' : 'pill-locked';
                $pillText  = ($pct > 0) ? 'Đang tiến hành' : 'Chưa bắt đầu';
                $btnClass  = 'locked';
                $btnText   = round($pct) . '%';
            }

            $cardClass = ($status === 'completed') ? 'status-completed' : ($status === 'claimed' ? 'status-claimed' : '');
        ?>
        <div class="tier-card <?= $cardClass ?>" 
             style="--tier-color: <?= $tc2['color'] ?>; --tier-glow: <?= $tc2['glow'] ?>;">

            <!-- Ring Progress -->
            <div class="tier-icon-wrap">
                <svg viewBox="0 0 72 72">
                    <circle class="bg-ring" cx="36" cy="36" r="30"/>
                    <circle class="prog-ring" cx="36" cy="36" r="30"
                        stroke="<?= $tc2['color'] ?>"
                        stroke-dasharray="<?= $circum ?>"
                        stroke-dashoffset="<?= $dashoffset ?>"
                        data-offset="<?= $dashoffset ?>"
                        data-circum="<?= $circum ?>"
                    />
                </svg>
                <div class="tier-icon-inner">
                    <span><?= $tc2['icon'] ?></span>
                    <span class="tier-pct"><?= round($pct) ?>%</span>
                </div>
            </div>

            <!-- Info -->
            <div class="tier-info">
                <div class="tier-label">
                    <span class="tier-name" style="color: <?= $tc2['color'] ?>;">
                        Thử Thách <?= $tc2['name'] ?>
                    </span>
                    <span class="tier-status-pill <?= $pillClass ?>"><?= $pillText ?></span>
                </div>

                <div class="tier-progress-text">
                    <strong><?= number_format($progress) ?></strong> / <?= number_format($target) ?>
                    <?php if ($dungeon['type'] === 'accumulate' || $dungeon['type'] === 'survivor'): ?>
                        GTLM
                    <?php elseif ($dungeon['type'] === 'hunt' || $dungeon['type'] === 'specialist'): ?>
                        lượt thắng
                    <?php elseif ($dungeon['type'] === 'streak'): ?>
                        chuỗi thắng
                    <?php else: ?>
                        game
                    <?php endif; ?>
                </div>

                <div class="thin-bar">
                    <div class="thin-fill" style="width: <?= $pct ?>%; background: <?= $tc2['gradient'] ?>;"></div>
                </div>

                <!-- Rewards -->
                <div class="reward-row">
                    <?php if (isset($rewards[$tier])): foreach ($rewards[$tier] as $r): ?>
                    <div class="reward-chip" title="<?= htmlspecialchars($r['mat_name']) ?>">
                        <span><?= $r['mat_icon'] ?></span> ×<?= $r['quantity'] ?> <?= htmlspecialchars($r['mat_name']) ?>
                    </div>
                    <?php endforeach; endif; ?>
                    <div class="reward-chip gold">
                        <span>💰</span> <?= number_format($tier * 10000) ?> GTLM
                    </div>
                </div>
            </div>

            <!-- Claim Button -->
            <button class="btn-claim <?= $btnClass ?>"
                    data-tier="<?= $tier ?>"
                    <?= ($btnClass !== 'ready') ? 'disabled' : '' ?>>
                <?= $btnText ?>
            </button>
        </div>
        <?php endfor; ?>
        </div>

        <!-- All Done Banner -->
        <?php if ($totalClaimed === 3): ?>
        <div class="all-done-banner">
            <span class="trophy">🏆</span>
            <strong style="font-size:18px; display:block; margin-bottom:6px;">Hoàn Thành Toàn Bộ Dungeon Hôm Nay!</strong>
            <span style="color: rgba(255,255,255,0.5); font-size:13px;">Bạn đã chinh phục cả 3 tầng. Quay lại vào ngày mai để nhận thưởng mới!</span>
        </div>
        <?php endif; ?>

    </div>

    <script>
    // ─── Countdown to midnight ───
    function updateCountdown() {
        const now = new Date();
        const midnight = new Date();
        midnight.setHours(24, 0, 0, 0);
        let diff = Math.floor((midnight - now) / 1000);
        const h = String(Math.floor(diff / 3600)).padStart(2, '0');
        diff %= 3600;
        const m = String(Math.floor(diff / 60)).padStart(2, '0');
        const s = String(diff % 60).padStart(2, '0');
        document.getElementById('countdown').textContent = `${h}:${m}:${s}`;
    }
    updateCountdown();
    setInterval(updateCountdown, 1000);

    // ─── Animate SVG rings on load ───
    document.querySelectorAll('.prog-ring').forEach(ring => {
        const finalOffset = parseFloat(ring.dataset.offset);
        const circum      = parseFloat(ring.dataset.circum);
        ring.style.strokeDashoffset = circum; // start from 0%
        setTimeout(() => { ring.style.strokeDashoffset = finalOffset; }, 200);
    });

    // ─── Claim reward ───
    $('.btn-claim.ready').on('click', function() {
        const btn  = $(this);
        const tier = btn.data('tier');

        btn.text('...').prop('disabled', true);

        $.post('api_dungeon.php', { action: 'claim_tier', claim_tier: tier }, function(res) {
            if (res.success) {
                Swal.fire({
                    title: '🎉 Nhận thưởng thành công!',
                    html: `<div style="font-size:28px;margin-bottom:10px;">🎁</div>
                           <div style="color:rgba(255,255,255,0.7);font-size:14px;">Phần thưởng đã được thêm vào kho đồ của bạn!</div>`,
                    icon: 'success',
                    background: '#0f0f1a',
                    color: '#fff',
                    confirmButtonColor: '#10b981',
                    confirmButtonText: 'Tuyệt vời! ✓',
                    showClass: { popup: 'animate__animated animate__zoomIn' },
                }).then(() => location.reload());
            } else {
                Swal.fire({
                    title: '❌ Lỗi',
                    text: res.message,
                    icon: 'error',
                    background: '#0f0f1a',
                    color: '#fff',
                    confirmButtonColor: '#ef4444',
                });
                btn.text('🎁 Nhận Thưởng').prop('disabled', false);
            }
        }, 'json').fail(() => {
            Swal.fire({ title: 'Lỗi kết nối', icon: 'error', background: '#0f0f1a', color: '#fff' });
            btn.text('🎁 Nhận Thưởng').prop('disabled', false);
        });
    });
    </script>
</body>
</html>
