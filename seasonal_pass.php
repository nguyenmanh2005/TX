<?php
session_start();
require_once 'db_connect.php';
require_once 'api_event_helper.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['Iduser'];
$activeSeason = EventHelper::getActiveSeason($conn);

if (!$activeSeason) {
    die("Hiện không có mùa giải nào đang diễn ra.");
}

$seasonId = $activeSeason['id'];

// Lấy tiến trình user
$stmt = $conn->prepare("SELECT * FROM user_seasonal_pass_progress WHERE user_id = ? AND season_id = ?");
$stmt->bind_param("ii", $userId, $seasonId);
$stmt->execute();
$progress = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$progress) {
    $conn->query("INSERT INTO user_seasonal_pass_progress (user_id, season_id) VALUES ($userId, $seasonId)");
    $progress = ['current_level' => 1, 'current_xp' => 0, 'is_premium' => 0, 'claimed_rewards' => '[]'];
}

$claimedRewards = json_decode($progress['claimed_rewards'] ?? '[]', true);

// Lấy danh sách phần thưởng
$rewards = $conn->query("SELECT * FROM seasonal_pass_levels WHERE season_id = $seasonId ORDER BY level ASC, is_premium ASC")->fetch_all(MYSQLI_ASSOC);

// Xử lý nhận thưởng
if (isset($_POST['claim_reward'])) {
    $level = (int)$_POST['level'];
    $isPremiumReward = (int)$_POST['is_premium'];
    
    if ($level > $progress['current_level']) {
        $msg = "Bạn chưa đạt level này!";
    } elseif ($isPremiumReward && !$progress['is_premium']) {
        $msg = "Phần thưởng này chỉ dành cho Premium Pass!";
    } elseif (in_array($level . ($isPremiumReward ? 'p' : 'f'), $claimedRewards)) {
        $msg = "Bạn đã nhận phần thưởng này rồi!";
    } else {
        // Thực hiện trao thưởng
        $stmt = $conn->prepare("SELECT * FROM seasonal_pass_levels WHERE season_id = ? AND level = ? AND is_premium = ?");
        $stmt->bind_param("iii", $seasonId, $level, $isPremiumReward);
        $stmt->execute();
        $rewardData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($rewardData) {
            $conn->begin_transaction();
            try {
                if ($rewardData['reward_type'] === 'money') {
                    $val = (float)$rewardData['reward_value'];
                    $conn->query("UPDATE users SET Money = Money + $val WHERE Iduser = $userId");
                }
                // (Logic cho các loại thưởng khác...)

                $claimedRewards[] = $level . ($isPremiumReward ? 'p' : 'f');
                $newClaimed = json_encode($claimedRewards);
                $conn->query("UPDATE user_seasonal_pass_progress SET claimed_rewards = '$newClaimed' WHERE user_id = $userId AND season_id = $seasonId");
                
                $conn->commit();
                $msg = "Nhận thưởng thành công!";
                header("Location: seasonal_pass.php?msg=" . urlencode($msg));
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $msg = "Lỗi: " . $e->getMessage();
            }
        }
    }
}

// Xử lý mua Premium
if (isset($_POST['buy_premium'])) {
    $price = 250000; // 250k GTLM
    $user = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc();
    if ($user['Money'] >= $price) {
        $conn->begin_transaction();
        $conn->query("UPDATE users SET Money = Money - $price WHERE Iduser = $userId");
        $conn->query("UPDATE user_seasonal_pass_progress SET is_premium = 1 WHERE user_id = $userId AND season_id = $seasonId");
        $conn->commit();
        header("Location: seasonal_pass.php?msg=Premium Activated!");
        exit;
    } else {
        $msg = "Bạn không đủ GTLM!";
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?= $activeSeason['name'] ?> | Seasonal Pass</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --season-color: <?= $activeSeason['theme_color'] ?>;
            --bg: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --text: #f8fafc;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            margin: 0;
            background-image: 
                radial-gradient(at 0% 0%, var(--season-color) 0px, transparent 40%),
                radial-gradient(at 100% 100%, #1e293b 0px, transparent 50%);
            background-attachment: fixed;
            min-height: 100vh;
        }

        .container { max-width: 1000px; margin: 0 auto; padding: 40px 20px; }

        .header {
            text-align: center;
            margin-bottom: 50px;
        }

        .header h1 { font-size: 3rem; font-weight: 800; margin: 0; text-transform: uppercase; letter-spacing: 4px; }
        .header p { color: #94a3b8; font-size: 1.2rem; margin-top: 10px; }

        .stats-bar {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 30px;
            padding: 30px;
            display: flex;
            align-items: center;
            gap: 40px;
            margin-bottom: 40px;
        }

        .lvl-circle {
            width: 100px; height: 100px;
            border-radius: 50%;
            border: 5px solid var(--season-color);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            background: rgba(0,0,0,0.3);
            box-shadow: 0 0 30px var(--season-color);
        }
        .lvl-num { font-size: 2.5rem; font-weight: 800; line-height: 1; }
        .lvl-label { font-size: 0.7rem; font-weight: 600; opacity: 0.7; }

        .xp-progress { flex: 1; }
        .xp-label { display: flex; justify-content: space-between; margin-bottom: 10px; font-weight: 600; }
        .xp-bar { height: 15px; background: rgba(0,0,0,0.3); border-radius: 10px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05); }
        .xp-fill { height: 100%; background: linear-gradient(to right, var(--season-color), #fff); box-shadow: 0 0 15px var(--season-color); transition: 1s ease; }

        .premium-status {
            padding: 20px;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(0,0,0,0.3) 100%);
            border-radius: 20px;
            border: 1px solid #f59e0b;
            text-align: center;
        }

        .btn-premium {
            background: #f59e0b;
            color: #000;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 800;
            cursor: pointer;
            margin-top: 10px;
        }

        /* Reward Track */
        .track-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .reward-row {
            display: grid;
            grid-template-columns: 80px 1fr 1fr;
            gap: 15px;
            align-items: center;
        }

        .level-marker {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 800;
            color: #475569;
        }
        .level-marker.active { color: #fff; text-shadow: 0 0 10px var(--season-color); }

        .reward-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: 0.3s;
            position: relative;
        }
        .reward-card.locked { opacity: 0.4; filter: grayscale(1); }
        .reward-card.premium { border-color: #f59e0b; background: rgba(245, 158, 11, 0.03); }

        .reward-icon { font-size: 2rem; width: 50px; text-align: center; }
        .reward-info { flex: 1; }
        .reward-name { display: block; font-weight: 700; font-size: 1.1rem; }
        .reward-type { font-size: 0.8rem; color: #94a3b8; text-transform: uppercase; }

        .btn-claim {
            background: var(--season-color);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
        }
        .claimed { color: #4ade80; font-weight: 700; }

        /* World Boss Widget */
        .boss-widget {
            margin-top: 50px;
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.1) 0%, rgba(0,0,0,0.5) 100%);
            border: 2px solid #ef4444;
            border-radius: 30px;
            padding: 30px;
            text-align: center;
        }
        .boss-hp-bar { height: 30px; background: #000; border-radius: 15px; margin: 20px 0; overflow: hidden; border: 2px solid #ef4444; }
        .boss-hp-fill { height: 100%; background: linear-gradient(to right, #ef4444, #7f1d1d); box-shadow: 0 0 20px #ef4444; width: 75%; }
    </style>
</head>
<body>

    <div class="container">
        <div class="header">
            <h1 style="color: var(--season-color);"><?= $activeSeason['name'] ?></h1>
            <p>Mùa giải kết thúc vào: <?= date('d/m/Y', strtotime($activeSeason['end_date'])) ?></p>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div style="padding: 15px; background: rgba(34, 197, 94, 0.2); border: 1px solid #4ade80; color: #4ade80; border-radius: 15px; margin-bottom: 30px; text-align: center;">
                <?= htmlspecialchars($_GET['msg']) ?>
            </div>
        <?php endif; ?>

        <div class="stats-bar">
            <div class="lvl-circle">
                <span class="lvl-label">LEVEL</span>
                <span class="lvl-num"><?= $progress['current_level'] ?></span>
            </div>
            <div class="xp-progress">
                <div class="xp-label">
                    <span>XP TIẾN TRÌNH</span>
                    <span><?= $progress['current_xp'] ?> / 1000</span>
                </div>
                <div class="xp-bar">
                    <div class="xp-fill" style="width: <?= ($progress['current_xp'] / 10) ?>%;"></div>
                </div>
            </div>
            <div class="premium-status">
                <?php if ($progress['is_premium']): ?>
                    <div style="color: #f59e0b; font-weight: 800;"><i class="fa fa-crown"></i> PREMIUM ACTIVATED</div>
                <?php else: ?>
                    <div style="font-size: 0.9rem; color: #94a3b8;">Upgrade for exclusive rewards</div>
                    <form method="POST"><button type="submit" name="buy_premium" class="btn-premium">BUY PREMIUM PASS (250k)</button></form>
                <?php endif; ?>
            </div>
        </div>

        <div class="track-container">
            <div class="reward-row" style="margin-bottom: 20px; font-weight: 800; color: #94a3b8; text-transform: uppercase; font-size: 0.8rem;">
                <div>LEVEL</div>
                <div>FREE REWARDS</div>
                <div>PREMIUM REWARDS</div>
            </div>

            <?php
            // Group rewards by level
            $levels = [];
            foreach ($rewards as $r) {
                $levels[$r['level']][$r['is_premium'] ? 'premium' : 'free'] = $r;
            }

            for ($i = 1; $i <= 10; $i++): // Show 10 levels for demo
                $free = $levels[$i]['free'] ?? null;
                $prem = $levels[$i]['premium'] ?? null;
                $isReached = $i <= $progress['current_level'];
            ?>
            <div class="reward-row">
                <div class="level-marker <?= $isReached ? 'active' : '' ?>"><?= $i ?></div>
                
                <!-- Free Reward Card -->
                <div class="reward-card <?= !$isReached ? 'locked' : '' ?>">
                    <div class="reward-icon"><?= $free['reward_type'] === 'money' ? '💰' : '🎁' ?></div>
                    <div class="reward-info">
                        <span class="reward-type">FREE</span>
                        <span class="reward-name"><?= $free ? number_format($free['reward_value']) . ' GTLM' : 'Empty' ?></span>
                    </div>
                    <?php if ($free && $isReached): ?>
                        <?php if (in_array($i . 'f', $claimedRewards)): ?>
                            <span class="claimed">CLAIMED</span>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="level" value="<?= $i ?>">
                                <input type="hidden" name="is_premium" value="0">
                                <button type="submit" name="claim_reward" class="btn-claim">CLAIM</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Premium Reward Card -->
                <div class="reward-card premium <?= (!$isReached || !$progress['is_premium']) ? 'locked' : '' ?>">
                    <div class="reward-icon">💎</div>
                    <div class="reward-info">
                        <span class="reward-type" style="color: #f59e0b;">PREMIUM</span>
                        <span class="reward-name"><?= $prem ? number_format($prem['reward_value']) . ' GTLM' : 'Exclusive Item' ?></span>
                    </div>
                    <?php if ($prem && $isReached && $progress['is_premium']): ?>
                        <?php if (in_array($i . 'p', $claimedRewards)): ?>
                            <span class="claimed">CLAIMED</span>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="level" value="<?= $i ?>">
                                <input type="hidden" name="is_premium" value="1">
                                <button type="submit" name="claim_reward" class="btn-claim" style="background: #f59e0b; color: #000;">CLAIM</button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <i class="fa fa-lock" style="color: #94a3b8;"></i>
                    <?php endif; ?>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <div class="boss-widget">
            <h2 style="margin: 0; text-transform: uppercase;">👾 WORLD BOSS: <?= $activeSeason['boss_name'] ?></h2>
            <div class="boss-hp-bar">
                <div class="boss-hp-fill"></div>
            </div>
            <div style="display: flex; justify-content: space-between; font-weight: 800; color: #ef4444;">
                <span>HEALTH: 3,750,000 / <?= number_format($activeSeason['boss_hp_max']) ?></span>
                <span>STATUS: ENRAGED</span>
            </div>
            <p style="margin-top: 20px; color: #94a3b8; font-size: 0.9rem;">Gây sát thương bằng cách chơi các game sự kiện! Boss bị hạ gục sẽ thưởng toàn server!</p>
        </div>

        <div style="text-align: center; margin-top: 50px;">
            <a href="index.php" style="color: #94a3b8; text-decoration: none; font-weight: 600;"><i class="fa fa-arrow-left"></i> QUAY LẠI SẢNH</a>
        </div>
    </div>

</body>
</html>
