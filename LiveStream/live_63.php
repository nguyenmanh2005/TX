<?php
session_start();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../game_history_helper.php';
require_once __DIR__ . '/bot_streamer_helper.php';

$botUser = getOrCreateBotStreamerUser($conn, 'bot_63', 50000000);
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
    if ($_GET['action'] === 'trade') {
        $profit = rand(20000, 150000);
        $conn->query("UPDATE users SET Money = Money + $profit WHERE Iduser = $userId");
        $newMoney = $money + $profit;
        echo json_encode([
            'success' => true,
            'profit' => $profit,
            'balance' => number_format($newMoney, 0, ',', '.'),
            'item' => ['Bảo Kiếm Thần Long', 'Trứng Phượng Hoàng', 'Đá Quý Hỗn Độn', 'Cổ Thư Vạn Linh'][rand(0, 3)]
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
    <title>🏪 Chợ Thần Tài LiveStream - Bàn 63</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        body {
            margin: 0; background: <?= $bgGradientCSS ?? '#0a0d1a' ?>; color: #fff; font-family: 'Outfit', sans-serif;
            min-height: 100vh; padding: 20px;
        }
        .market-card {
            max-width: 800px; margin: 0 auto; background: rgba(18, 22, 36, 0.9);
            border-radius: 24px; border: 1px solid rgba(168, 85, 247, 0.3); padding: 30px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.6);
        }
        .trade-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 25px 0; }
        .trade-item {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px; padding: 18px; display: flex; align-items: center; gap: 15px;
        }
        .trade-icon { font-size: 2.5rem; }
        .btn-trade {
            background: linear-gradient(135deg, #a855f7, #6366f1); color: #fff;
            border: none; padding: 15px 35px; border-radius: 50px; font-weight: 800; font-size: 1.1rem;
            cursor: pointer; transition: 0.2s; box-shadow: 0 0 15px rgba(168,85,247,0.4);
        }
        .btn-trade:hover { transform: scale(1.05); }
    </style>
</head>
<body>
    <div class="market-card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h1 style="color:#c084fc; margin:0; font-family:'Orbitron'; font-size:1.8rem;">🏪 CHỢ THẦN TÀI & LINH THÚ</h1>
                <p style="opacity:0.8; margin-top:5px;">Sàn đấu giá vật phẩm hiếm & bảo vật vạn năm</p>
            </div>
            <div style="background:rgba(192,132,252,0.15); border:1px solid #c084fc; padding:10px 20px; border-radius:16px; font-weight:800;">
                💰 <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span> GTLM
            </div>
        </div>

        <div class="trade-grid">
            <div class="trade-item">
                <div class="trade-icon">🗡️</div>
                <div>
                    <div style="font-weight:800; color:#ffd700;">Bảo Kiếm Thần Long</div>
                    <div style="font-size:0.85rem; opacity:0.7;">Định giá: 120.000 GTLM</div>
                </div>
            </div>
            <div class="trade-item">
                <div class="trade-icon">🥚</div>
                <div>
                    <div style="font-weight:800; color:#f472b6;">Trứng Phượng Hoàng Cổ</div>
                    <div style="font-size:0.85rem; opacity:0.7;">Định giá: 250.000 GTLM</div>
                </div>
            </div>
            <div class="trade-item">
                <div class="trade-icon">🔮</div>
                <div>
                    <div style="font-weight:800; color:#38bdf8;">Đá Quý Hỗn Độn</div>
                    <div style="font-size:0.85rem; opacity:0.7;">Định giá: 80.000 GTLM</div>
                </div>
            </div>
            <div class="trade-item">
                <div class="trade-icon">📜</div>
                <div>
                    <div style="font-weight:800; color:#4ade80;">Cổ Thư Vạn Linh</div>
                    <div style="font-size:0.85rem; opacity:0.7;">Định giá: 95.000 GTLM</div>
                </div>
            </div>
        </div>

        <div style="text-align:center; margin-top:20px;">
            <div id="tradeLog" style="margin-bottom:15px; color:#10b981; font-weight:700;">Sẵn sàng giao dịch phiên chợ mới</div>
            <button id="btnTrade" class="btn-trade">
                <i class="fa fa-handshake"></i> CHỐT GIAO DỊCH BẢO VẬT
            </button>
        </div>
    </div>

    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="../assets/js/bot_chat.js"></script>
    <script src="bots/bot_63.js?v=<?= time() ?>"></script>
</body>
</html>
