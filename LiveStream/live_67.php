<?php
session_start();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../game_history_helper.php';
require_once __DIR__ . '/bot_streamer_helper.php';

$botUser = getOrCreateBotStreamerUser($conn, 'bot_67', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;
$_SESSION['Iduser'] = $botUserId;

$userId = $botUserId;
require_once __DIR__ . '/../load_theme.php';

$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user ? (float)$user['Money'] : 50000000;
$stmt->close();

if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    if ($_GET['action'] === 'spin_wheel') {
        $prizes = [
            ['name' => '100.000 GTLM', 'amount' => 100000, 'icon' => '💎'],
            ['name' => '250.000 GTLM', 'amount' => 250000, 'icon' => '💰'],
            ['name' => '50.000 GTLM', 'amount' => 50000, 'icon' => '🪙'],
            ['name' => 'JACKPOT 1.000.000 GTLM', 'amount' => 1000000, 'icon' => '👑'],
            ['name' => '500.000 GTLM', 'amount' => 500000, 'icon' => '🌟']
        ];
        $selected = $prizes[rand(0, count($prizes) - 1)];
        $reward = $selected['amount'];
        $conn->query("UPDATE users SET Money = Money + $reward WHERE Iduser = $userId");
        $newMoney = $money + $reward;
        echo json_encode([
            'success' => true,
            'reward' => $reward,
            'name' => $selected['name'],
            'icon' => $selected['icon'],
            'balance' => number_format($newMoney, 0, ',', '.')
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎡 Vòng Quay May Mắn LiveStream - Bàn 67</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        body { margin: 0; background: <?= $bgGradientCSS ?? '#160a29' ?>; color: #fff; font-family: 'Outfit', sans-serif; min-height: 100vh; padding: 20px; }
        .wheel-card { max-width: 800px; margin: 0 auto; background: rgba(22, 10, 41, 0.9); border-radius: 24px; border: 1px solid rgba(234, 179, 8, 0.35); padding: 30px; box-shadow: 0 15px 50px rgba(0,0,0,0.7); }
        .wheel-container { position: relative; width: 280px; height: 280px; margin: 30px auto; display: flex; align-items: center; justify-content: center; }
        .wheel-visual { width: 100%; height: 100%; border-radius: 50%; border: 6px solid #ffd700; box-shadow: 0 0 30px rgba(255,215,0,0.4); background: conic-gradient(#ef4444 0% 20%, #f59e0b 20% 40%, #10b981 40% 60%, #3b82f6 60% 80%, #8b5cf6 80% 100%); transition: transform 2.5s cubic-bezier(0.15, 0.9, 0.3, 1); }
        .wheel-pointer { position: absolute; top: -12px; font-size: 2rem; color: #ffd700; text-shadow: 0 0 10px #000; z-index: 10; }
        .btn-spin-wheel { background: linear-gradient(135deg, #f59e0b, #eab308); color: #000; border: none; padding: 18px 45px; border-radius: 50px; font-weight: 900; font-size: 1.2rem; cursor: pointer; transition: 0.2s; box-shadow: 0 0 20px rgba(245,158,11,0.5); }
        .btn-spin-wheel:hover { transform: scale(1.06); }
    </style>
</head>
<body>
    <div class="wheel-card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h1 style="color:#fde047; margin:0; font-family:'Orbitron'; font-size:1.8rem;">🎡 VÒNG QUAY MAY MẮN HOÀNG GIA</h1>
                <p style="opacity:0.8; margin-top:5px;">Vòng quay kim cương, đón bão thưởng Jackpot ngập tràn</p>
            </div>
            <div style="background:rgba(253,224,71,0.15); border:1px solid #fde047; padding:10px 20px; border-radius:16px; font-weight:800; color:#ffd700;">
                💰 <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span> GTLM
            </div>
        </div>

        <div class="wheel-container">
            <div class="wheel-pointer">▼</div>
            <div class="wheel-visual" id="wheelCircle"></div>
        </div>

        <div style="text-align:center; margin-top:15px;">
            <div id="wheelLog" style="margin-bottom:15px; font-weight:700; color:#fde047;">Sẵn sàng quay vòng may mắn</div>
            <button id="btnSpinWheel" class="btn-spin-wheel">
                <i class="fa fa-rotate"></i> QUAY VÒNG MAY MẮN
            </button>
        </div>
    </div>

    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="../assets/js/bot_chat.js"></script>
    <script src="bots/bot_67.js?v=<?= time() ?>"></script>
</body>
</html>
