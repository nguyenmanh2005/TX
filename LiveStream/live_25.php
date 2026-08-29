<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_25', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

if (!isset($botUserId)) {
    header('Location: ../login.php');
    exit;
}

require '../db_connect.php';
require_once '../load_theme.php';
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #1a1c29 0%, #2a2d3e 100%)';
}

$userId = $botUserId;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pull') {
    header('Content-Type: application/json');
    $cost = 50000;
    
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if ($user['Money'] < $cost) {
            throw new Exception("Không đủ GTLM (Cần " . number_format($cost) . ")");
        }
        
        $conn->query("UPDATE users SET Money = Money - $cost WHERE Iduser = $userId");
        
        // 38 Card Rarities Pool
        $gachaPool = [
            ['id' => 'C', 'name' => 'Common', 'prob' => 250000, 'reward' => 10000, 'color' => '#9ca3af', 'icon' => 'fa-leaf'],
            ['id' => 'UC', 'name' => 'Uncommon', 'prob' => 200000, 'reward' => 20000, 'color' => '#a7f3d0', 'icon' => 'fa-seedling'],
            ['id' => 'R', 'name' => 'Rare', 'prob' => 150000, 'reward' => 30000, 'color' => '#38bdf8', 'icon' => 'fa-star'],
            ['id' => 'SR', 'name' => 'Super Rare', 'prob' => 100000, 'reward' => 50000, 'color' => '#818cf8', 'icon' => 'fa-meteor'],
            ['id' => 'RR', 'name' => 'Double Rare', 'prob' => 70000, 'reward' => 60000, 'color' => '#c084fc', 'icon' => 'fa-moon'],
            ['id' => 'UR', 'name' => 'Ultra Rare', 'prob' => 50000, 'reward' => 80000, 'color' => '#f472b6', 'icon' => 'fa-gem'],
            ['id' => 'S-UR', 'name' => 'Super Ultra Rare', 'prob' => 40000, 'reward' => 100000, 'color' => '#fb7185', 'icon' => 'fa-fire'],
            ['id' => 'XR', 'name' => 'Extra Rare', 'prob' => 30000, 'reward' => 120000, 'color' => '#f87171', 'icon' => 'fa-bolt'],
            ['id' => 'MR', 'name' => 'Mega Rare', 'prob' => 20000, 'reward' => 150000, 'color' => '#fb923c', 'icon' => 'fa-sun'],
            ['id' => 'LR', 'name' => 'Legendary Rare', 'prob' => 15000, 'reward' => 200000, 'color' => '#facc15', 'icon' => 'fa-crown'],
            ['id' => 'L', 'name' => 'Legendary', 'prob' => 12000, 'reward' => 250000, 'color' => '#eab308', 'icon' => 'fa-dragon'],
            ['id' => 'ML', 'name' => 'Mythic Legendary', 'prob' => 10000, 'reward' => 300000, 'color' => '#ca8a04', 'icon' => 'fa-khanda'],
            ['id' => 'M', 'name' => 'Mythic', 'prob' => 8000, 'reward' => 400000, 'color' => '#854d0e', 'icon' => 'fa-shield-alt'],
            ['id' => 'MMR', 'name' => 'Master Mythic Rare', 'prob' => 6000, 'reward' => 500000, 'color' => '#dc2626', 'icon' => 'fa-chess-knight'],
            ['id' => 'SSR', 'name' => 'Secret Rare', 'prob' => 5000, 'reward' => 600000, 'color' => '#b91c1c', 'icon' => 'fa-user-secret'],
            ['id' => 'SP', 'name' => 'Special', 'prob' => 4000, 'reward' => 800000, 'color' => '#be185d', 'icon' => 'fa-star-half-alt'],
            ['id' => 'SPR', 'name' => 'Special Rare', 'prob' => 3500, 'reward' => 1000000, 'color' => '#9d174d', 'icon' => 'fa-certificate'],
            ['id' => 'SE', 'name' => 'Special Edition', 'prob' => 3000, 'reward' => 1200000, 'color' => '#831843', 'icon' => 'fa-award'],
            ['id' => 'PR', 'name' => 'Promo Rare', 'prob' => 2500, 'reward' => 1500000, 'color' => '#4c1d95', 'icon' => 'fa-bullhorn'],
            ['id' => 'PRM', 'name' => 'Premium', 'prob' => 2000, 'reward' => 2000000, 'color' => '#5b21b6', 'icon' => 'fa-gift'],
            ['id' => 'P', 'name' => 'Premium Rare', 'prob' => 1800, 'reward' => 2500000, 'color' => '#6d28d9', 'icon' => 'fa-box-open'],
            ['id' => 'GR', 'name' => 'Gold Rare', 'prob' => 1500, 'reward' => 3000000, 'color' => '#fbbf24', 'icon' => 'fa-coins'],
            ['id' => 'CHR', 'name' => 'Character Rare', 'prob' => 1200, 'reward' => 4000000, 'color' => '#f59e0b', 'icon' => 'fa-user-astronaut'],
            ['id' => 'CR', 'name' => 'Collector Rare', 'prob' => 1000, 'reward' => 5000000, 'color' => '#d97706', 'icon' => 'fa-book'],
            ['id' => 'CSR', 'name' => 'Character Secret Rare', 'prob' => 800, 'reward' => 6000000, 'color' => '#b45309', 'icon' => 'fa-mask'],
            ['id' => 'SAR', 'name' => 'Special Art Rare', 'prob' => 700, 'reward' => 8000000, 'color' => '#ea580c', 'icon' => 'fa-palette'],
            ['id' => 'AR', 'name' => 'Art Rare', 'prob' => 600, 'reward' => 10000000, 'color' => '#c2410c', 'icon' => 'fa-paint-brush'],
            ['id' => 'HR', 'name' => 'Hyper Rare', 'prob' => 500, 'reward' => 15000000, 'color' => '#9a3412', 'icon' => 'fa-rocket'],
            ['id' => 'TR', 'name' => 'Treasure Rare', 'prob' => 400, 'reward' => 20000000, 'color' => '#fef08a', 'icon' => 'fa-gem'],
            ['id' => 'GOD', 'name' => 'God Rare', 'prob' => 300, 'reward' => 30000000, 'color' => '#fffbeb', 'icon' => 'fa-cross'],
            ['id' => 'G', 'name' => 'God', 'prob' => 200, 'reward' => 50000000, 'color' => '#ffffff', 'icon' => 'fa-church'],
            ['id' => 'GRR', 'name' => 'God Rare Rare', 'prob' => 150, 'reward' => 80000000, 'color' => '#ccfbf1', 'icon' => 'fa-pray'],
            ['id' => 'EX', 'name' => 'Exclusive', 'prob' => 100, 'reward' => 100000000, 'color' => '#5eead4', 'icon' => 'fa-fingerprint'],
            ['id' => 'EXR', 'name' => 'Exclusive Rare', 'prob' => 50, 'reward' => 200000000, 'color' => '#14b8a6', 'icon' => 'fa-id-badge'],
            ['id' => 'LE', 'name' => 'Limited Edition', 'prob' => 30, 'reward' => 300000000, 'color' => '#0f766e', 'icon' => 'fa-hourglass-half'],
            ['id' => 'LTR', 'name' => 'Limited Treasure Rare', 'prob' => 20, 'reward' => 500000000, 'color' => '#042f2e', 'icon' => 'fa-trophy'],
            ['id' => '1/1', 'name' => 'One-of-One', 'prob' => 5, 'reward' => 1000000000, 'color' => '#000000', 'icon' => 'fa-infinity'],
            ['id' => 'Unique', 'name' => 'Unique Card', 'prob' => 1, 'reward' => 5000000000, 'color' => '#111827', 'icon' => 'fa-cube']
        ];
        
        $totalProb = 0;
        foreach ($gachaPool as $card) $totalProb += $card['prob'];
        
        $rand = mt_rand(1, $totalProb);
        $currentProb = 0;
        foreach ($gachaPool as $card) {
            $currentProb += $card['prob'];
            if ($rand <= $currentProb) {
                $rarity = $card['id'] . ' (' . $card['name'] . ')';
                $reward = $card['reward'];
                $icon = $card['icon'];
                $color = $card['color'];
                break;
            }
        }
        
        $conn->query("UPDATE users SET Money = Money + $reward WHERE Iduser = $userId");
        
        // Ghi log
        require_once '../game_history_helper.php';
        logGameHistory($conn, $userId, 'gacha_cards', $cost, $reward, true);
        
        $conn->commit();
        $newBalance = $user['Money'] - $cost + $reward;
        echo json_encode([
            'success' => true,
            'rarity' => $rarity,
            'reward' => $reward,
            'icon' => $icon,
            'color' => $color,
            'balance' => $newBalance,
            'message' => "Bạn đã mở được thẻ $rarity và nhận $reward GTLM!"
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$userMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gacha Thẻ Nhân Phẩm</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: <?= $bgGradientCSS ?>;
            color: #fff;
            font-family: 'Outfit', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .main-container {
            width: 90%;
            max-width: 600px;
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.9));
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border-radius: 40px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 50px 40px;
            box-shadow: 0 0 80px rgba(0, 0, 0, 0.6), inset 0 0 30px rgba(192, 132, 252, 0.1);
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .header h1 {
            font-size: 3rem;
            background: linear-gradient(to right, #fde047, #f43f5e, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-top: 0;
            margin-bottom: 10px;
            text-shadow: 0 10px 20px rgba(244, 63, 94, 0.2);
            font-weight: 900;
        }

        .header p { color: #cbd5e1; font-size: 1.15rem; margin-bottom: 25px; letter-spacing: 0.5px; }

        /* ThreeJS Background */
        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }
        
        .header, .wallet, .card-container, .btn-pull {
            position: relative;
            z-index: 1;
        }

        /* Result banner */
        .result-banner {
            position: fixed;
            top: -100px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 15px;
            padding: 18px 26px;
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: .5px;
            display: none;
            z-index: 1000;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .result-banner.show {
            display: block;
            animation: bannerIn .5s cubic-bezier(.16, 1, .3, 1) forwards;
        }
        @keyframes bannerIn {
            from { opacity: 0; transform: translate(-50%, -20px) scale(.95); }
            to { opacity: 1; transform: translate(-50%, 20px) scale(1); }
        }
        .result-banner.win {
            background: linear-gradient(135deg, rgba(0, 230, 118, .95), rgba(0, 200, 100, .95));
            border: 1px solid rgba(0, 230, 118, .4);
            color: #fff;
            text-shadow: 0 2px 5px rgba(0,0,0,0.5);
        }
        .result-banner.lose {
            background: linear-gradient(135deg, rgba(255, 61, 87, .95), rgba(200, 40, 60, .95));
            border: 1px solid rgba(255, 61, 87, .3);
            color: #fff;
            text-shadow: 0 2px 5px rgba(0,0,0,0.5);
        }

        .magic-circle {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 350px;
            height: 350px;
            margin-top: -175px;
            margin-left: -175px;
            background: radial-gradient(circle, rgba(192, 132, 252, 0.15) 0%, transparent 70%);
            border: 2px dashed rgba(192, 132, 252, 0.4);
            border-radius: 50%;
            animation: spin 20s linear infinite;
            z-index: 0;
            box-shadow: 0 0 50px rgba(192, 132, 252, 0.2);
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }

        .card-container {
            perspective: 1000px;
            margin: 30px 0 50px;
            width: 240px;
            height: 340px;
            cursor: pointer;
            animation: float 4s ease-in-out infinite;
            position: relative;
            z-index: 2;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }

        .card {
            width: 100%;
            height: 100%;
            position: relative;
            transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            transform-style: preserve-3d;
        }

        .card.flipped { transform: rotateY(180deg) scale(1.1); box-shadow: 0 0 50px rgba(255,255,255,0.3); }

        .card-face {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
            border-radius: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            border: 2px solid rgba(255,255,255,0.1);
        }

        .card-front {
            background: linear-gradient(135deg, #0f172a, #1e1b4b);
            border: 2px solid rgba(192, 132, 252, 0.5);
            box-shadow: inset 0 0 30px rgba(192, 132, 252, 0.2);
        }
        .card-front::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: repeating-linear-gradient(45deg, rgba(255,255,255,0.03) 0px, rgba(255,255,255,0.03) 15px, transparent 15px, transparent 30px);
            border-radius: 18px;
        }

        .card-front i { font-size: 90px; color: #a78bfa; filter: drop-shadow(0 0 10px rgba(167, 139, 250, 0.5)); position: relative; z-index: 1; }
        .card-front h3 { margin-top: 20px; color: #cbd5e1; position: relative; z-index: 1; font-size: 1.5rem; letter-spacing: 2px; }

        .card-back {
            background: linear-gradient(135deg, #1e1b4b, #3b0764);
            border: 2px solid #fff;
            transform: rotateY(180deg);
        }

        .rarity-badge {
            position: absolute;
            top: -25px;
            background: linear-gradient(90deg, #facc15, #f59e0b);
            color: #000;
            padding: 8px 30px;
            border-radius: 30px;
            font-weight: 900;
            font-size: 26px;
            box-shadow: 0 10px 20px rgba(250, 204, 21, 0.4);
            border: 2px solid #fff;
        }

        .card-back i { font-size: 100px; margin-bottom: 20px; }
        .reward-amount { font-size: 24px; font-weight: bold; }

        .btn-pull {
            background: linear-gradient(135deg, #ec4899, #8b5cf6);
            color: white;
            border: none;
            padding: 18px 50px;
            border-radius: 50px;
            font-size: 1.4rem;
            font-weight: 900;
            cursor: pointer;
            transition: 0.4s;
            box-shadow: 0 0 20px rgba(236, 72, 153, 0.5), inset 0 0 10px rgba(255,255,255,0.3);
            width: 100%;
            max-width: 320px;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
            z-index: 2;
        }
        .btn-pull::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%; width: 200%; height: 200%;
            background: linear-gradient(to right, transparent, rgba(255,255,255,0.3), transparent);
            transform: rotate(45deg);
            animation: shine 3s infinite;
        }
        @keyframes shine { 0% { left: -100%; } 20%, 100% { left: 100%; } }
        .btn-pull:hover { transform: scale(1.05); box-shadow: 0 0 40px rgba(236, 72, 153, 0.8); }

        .wallet { 
            background: rgba(0, 0, 0, 0.4);
            padding: 12px 25px;
            border-radius: 50px;
            border: 1px solid rgba(250, 204, 21, 0.3);
            font-size: 1.25rem; 
            color: #facc15; 
            font-weight: bold; 
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
    <!-- Premium Effects System -->
    <canvas id="threejs-background"></canvas>
    <script>
        (function () {
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
            const prefix = (window.location.pathname.includes('/games/') || window.location.pathname.includes('/LiveStream/')) ? '../' : '';
            const scripts = ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'];

            scripts.forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();
    </script>
    <div class="main-container">
        <div class="header">
            <h1>🔮 Gacha Thẻ Nhân Phẩm</h1>
            <p>Thử thách nhân phẩm với 50,000 GTLM/lần mở</p>
        </div>

        <div class="wallet"><i class="fa fa-wallet"></i> Số dư: <span id="user-money"><?= number_format($userMoney) ?></span> GTLM</div>

        <div class="card-container" id="gacha-container">
            <div class="magic-circle"></div>
            <div class="card" id="gacha-card">
                <div class="card-face card-front">
                    <i class="fa fa-gem"></i>
                    <h3>? ? ?</h3>
                </div>
                <div class="card-face card-back" id="card-result">
                    <div class="rarity-badge" id="res-rarity">SSR</div>
                    <i class="fa" id="res-icon"></i>
                    <div class="reward-amount" id="res-reward">0 GTLM</div>
                </div>
            </div>
        </div>

        <button class="btn-pull" id="btn-pull" onclick="pullCard()">Triệu Hồi Thẻ (50k)</button>
    </div>

    <script>
        function showBanner(msg, type) {
            const b = document.getElementById('resultBanner');
            b.className = 'result-banner ' + type + ' show';
            b.innerHTML = msg;
            setTimeout(() => { b.classList.remove('show'); }, 3000);
        }

        let isPulling = false;
        function pullCard() {
            if (isPulling) return;
            isPulling = true;
            $('#btn-pull').prop('disabled', true).text('Đang triệu hồi...');
            
            // Reset card
            $('#gacha-card').removeClass('flipped');
            
            setTimeout(() => {
                $.post('live_25.php', { action: 'pull' }, function(res) {
                    if (res.success) {
                        $('#res-rarity').text(res.rarity).css('color', res.color);
                        $('#res-icon').attr('class', 'fa ' + res.icon).css('color', res.color);
                        $('#res-reward').text(new Intl.NumberFormat().format(res.reward) + ' GTLM').css('color', res.color);
                        $('#card-result').css('box-shadow', `0 0 50px ${res.color}40`);
                        
                        // Flip animation
                        $('#gacha-card').addClass('flipped');
                        
                        // Update wallet directly from server response to avoid parsing bugs
                        $('#user-money').text(new Intl.NumberFormat().format(res.balance));

                        // Hieu ung banner va pha/o hoa
                        let profit = parseInt(res.reward) - 50000;
                        if (profit > 0) {
                            showBanner('THẮNG LỚN! +' + new Intl.NumberFormat().format(profit) + ' GTLM', 'win');
                            if (window.GameEffects) window.GameEffects.showWin(profit);
                        } else {
                            showBanner('LỖ RỒI! -' + new Intl.NumberFormat().format(Math.abs(profit)) + ' GTLM', 'lose');
                            if (window.GameEffects) window.GameEffects.showLoss(Math.abs(profit));
                        }

                        setTimeout(() => {
                            isPulling = false;
                            $('#btn-pull').prop('disabled', false).text('Triệu Hồi Thẻ Lần Nữa');
                        }, 1000);
                    } else {
                        Swal.fire('Lỗi!', res.message, 'error');
                        isPulling = false;
                        $('#btn-pull').prop('disabled', false).text('Triệu Hồi Thẻ (50k)');
                    }
                }, 'json').fail(function() {
                    Swal.fire('Lỗi!', 'Lỗi kết nối mạng!', 'error');
                    isPulling = false;
                    $('#btn-pull').prop('disabled', false).text('Triệu Hồi Thẻ (50k)');
                });
            }, 500); // 0.5s delay to make it feel like "shuffling"
        }
    </script>

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script src="bots/bot_25.js"></script>

<div class="result-banner" id="resultBanner"></div>
</body>
</html>
