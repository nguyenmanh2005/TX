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

// Check for claim request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_tier'])) {
    header('Content-Type: application/json');
    $tier = (int)$_POST['claim_tier'];
    $result = claim_dungeon_reward($conn, $userId, $tier);
    echo json_encode($result);
    exit;
}

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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Dungeon - Thử Thách Hàng Ngày</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.7.3/sweetalert2.all.min.js"></script>
    <style>
        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: white;
            padding: 40px 20px;
        }
        .dungeon-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .dungeon-header {
            text-align: center;
            background: rgba(0, 0, 0, 0.4);
            padding: 40px;
            border-radius: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 40px;
        }
        .dungeon-title {
            font-size: 42px;
            font-weight: 900;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            background: linear-gradient(to right, #f1c40f, #e67e22);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .tier-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: 0.3s;
        }
        .tier-card:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: scale(1.01);
        }
        .tier-info { flex: 1; }
        .tier-name { font-size: 24px; font-weight: 800; margin-bottom: 5px; }
        .tier-target { opacity: 0.7; font-size: 16px; margin-bottom: 15px; }
        
        .progress-container {
            width: 80%;
            height: 12px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(to right, #2ecc71, #27ae60);
            transition: width 0.5s ease;
        }
        
        .reward-icons { display: flex; gap: 15px; margin-top: 15px; }
        .reward-item {
            background: rgba(0, 0, 0, 0.3);
            padding: 8px 15px;
            border-radius: 12px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .btn-claim {
            padding: 15px 30px;
            border-radius: 15px;
            border: none;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-claim.ready { background: #2ecc71; color: white; box-shadow: 0 0 20px rgba(46, 204, 113, 0.4); }
        .btn-claim.ready:hover { transform: translateY(-3px); }
        .btn-claim.claimed { background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.3); cursor: default; }
        .btn-claim.locked { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.2); cursor: not-allowed; }

        .type-badge {
            display: inline-block;
            padding: 5px 15px;
            background: #3498db;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="dungeon-container">
        <div class="dungeon-header">
            <div class="type-badge">Hôm nay: <?= strtoupper($dungeon['type']) ?></div>
            <h1 class="dungeon-title"><?= htmlspecialchars($dungeon['name']) ?></h1>
            <p style="opacity: 0.6;">Hoàn thành thử thách hàng ngày để nhận nguyên liệu hiếm!</p>
            <div style="margin-top: 20px;">
                <a href="index.php" style="color: #aaa; text-decoration: none;">🏠 Quay lại</a> |
                <a href="inventory.php" style="color: #f1c40f; text-decoration: none;">📦 Kho đồ</a>
            </div>
        </div>

        <?php for ($tier = 1; $tier <= 3; $tier++): 
            $target = $dungeon["tier{$tier}_target"];
            $comp = $completions[$tier] ?? ['progress' => 0, 'status' => 'in_progress'];
            $progPercent = min(100, ($comp['progress'] / $target) * 100);
            $tierName = ($tier == 1) ? "Thử Thách Đồng" : (($tier == 2) ? "Thử Thách Bạc" : "Thử Thách Vàng");
            $status = $comp['status'];
            
            $btnClass = "locked";
            $btnText = "CHƯA XONG";
            if ($status === 'claimed') { $btnClass = "claimed"; $btnText = "ĐÃ NHẬN"; }
            elseif ($status === 'completed') { $btnClass = "ready"; $btnText = "NHẬN THƯỞNG"; }
        ?>
        <div class="tier-card">
            <div class="tier-info">
                <div class="tier-name"><?= $tierName ?></div>
                <div class="tier-target">Mục tiêu: <?= number_format($comp['progress']) ?> / <?= number_format($target) ?></div>
                
                <div class="progress-container">
                    <div class="progress-bar" style="width: <?= $progPercent ?>%;"></div>
                </div>

                <div class="reward-icons">
                    <?php if (isset($rewards[$tier])): foreach ($rewards[$tier] as $r): ?>
                        <div class="reward-item" title="<?= htmlspecialchars($r['mat_name']) ?>">
                            <span><?= $r['mat_icon'] ?></span>
                            <span>x<?= $r['quantity'] ?></span>
                        </div>
                    <?php endforeach; endif; ?>
                    <?php if ($tier > 0): ?>
                        <div class="reward-item" style="border-color: #2ecc71;">
                            <span>💰</span>
                            <span><?= number_format($tier * 10000) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <button class="btn-claim <?= $btnClass ?>" data-tier="<?= $tier ?>" <?= ($btnClass !== 'ready') ? 'disabled' : '' ?>>
                <?= $btnText ?>
            </button>
        </div>
        <?php endfor; ?>
    </div>

    <script>
        $('.btn-claim.ready').on('click', function() {
            const btn = $(this);
            const tier = btn.data('tier');
            
            $.post('dungeon.php', { claim_tier: tier }, function(res) {
                if (res.success) {
                    Swal.fire({
                        title: 'Thành công!',
                        text: 'Bạn đã nhận phần thưởng Dungeon!',
                        icon: 'success',
                        background: '#1a1a2e',
                        color: '#fff'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Lỗi', res.message, 'error');
                }
            }, 'json');
        });
    </script>
</body>
</html>
