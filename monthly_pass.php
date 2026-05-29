<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require_once 'load_theme.php';
$userId = $_SESSION['Iduser'];

// 2. ThÃªm dá»¯ liá»‡u máº«u náº¿u báº£ng trá»‘ng
$checkTypes = $conn->query("SELECT COUNT(*) as total FROM monthly_pass_types");
if ($checkTypes->fetch_assoc()['total'] == 0) {
    $conn->query("INSERT INTO monthly_pass_types (name, price, daily_bonus, instant_bonus, icon, color, description) VALUES 
    ('Silver Pass', 500000, 50000, 100000, 'ðŸ¥ˆ', '#c0c0c0', 'Nháº­n ngay 100k GTLM. Má»—i ngÃ y nháº­n 50k GTLM. x2 thÆ°á»Ÿng VÃ²ng Quay May Máº¯n.'),
    ('Gold Pass', 2000000, 250000, 500000, 'ðŸ¥‡', '#ffd700', 'Nháº­n ngay 500k GTLM. Má»—i ngÃ y nháº­n 250k GTLM. x2 thÆ°á»Ÿng VÃ²ng Quay May Máº¯n. Æ¯u tiÃªn hÃ ng chá» VIP.')");
}

// 3. Láº¥y thÃ´ng tin pass hiá»‡n táº¡i cá»§a user
$sql = "SELECT ump.*, mpt.name, mpt.daily_bonus, mpt.icon, mpt.color, mpt.description
        FROM user_monthly_pass ump
        JOIN monthly_pass_types mpt ON ump.pass_type_id = mpt.id
        WHERE ump.user_id = ? AND ump.expiry_date > NOW()
        ORDER BY ump.expiry_date DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$activePass = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 4. Láº¥y danh sÃ¡ch cÃ¡c gÃ³i cÃ³ sáºµn
$passTypes = $conn->query("SELECT * FROM monthly_pass_types ORDER BY price ASC")->fetch_all(MYSQLI_ASSOC);

// 5. Láº¥y  Gtlm user
$userMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Pass - GÃ³i ThuÃª Bao ThÃ¡ng</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            color: white;
            font-family: 'Inter', sans-serif;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .pass-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .pass-header h1 {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #fff 0%, #aaa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .active-pass-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 40px;
            border: 1px solid rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            gap: 30px;
            animation: fadeIn 0.6s ease;
        }

        .pass-icon {
            font-size: 80px;
            filter: drop-shadow(0 0 20px rgba(255,255,255,0.3));
        }

        .pass-info h2 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .pass-expiry {
            font-size: 14px;
            opacity: 0.7;
        }

        .claim-btn {
            margin-left: auto;
            padding: 15px 30px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
        }

        .claim-btn:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.4);
        }

        .claim-btn:disabled {
            background: #555;
            cursor: not-allowed;
        }

        .pass-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .pass-card {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 24px;
            padding: 35px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: 0.4s;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        .pass-card:hover {
            transform: translateY(-10px);
            border-color: rgba(255, 255, 255, 0.3);
            background: rgba(0, 0, 0, 0.4);
        }

        .pass-card.popular::before {
            content: 'PHá»” BIáº¾N';
            position: absolute;
            top: 20px;
            right: -30px;
            background: #ffd700;
            color: #000;
            padding: 5px 40px;
            transform: rotate(45deg);
            font-size: 12px;
            font-weight: 800;
        }

        .pass-card h3 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .pass-price {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 25px;
            color: var(--accent);
        }

        .pass-price span {
            font-size: 16px;
            opacity: 0.6;
            font-weight: 400;
        }

        .benefits-list {
            list-style: none;
            padding: 0;
            margin: 0 0 30px 0;
            flex-grow: 1;
        }

        .benefits-list li {
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            opacity: 0.9;
        }

        .benefits-list li i {
            color: #28a745;
        }

        .buy-btn {
            width: 100%;
            padding: 16px;
            border-radius: 14px;
            border: none;
            background: white;
            color: black;
            font-weight: 800;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }

        .buy-btn:hover {
            background: #eee;
            transform: scale(1.02);
        }

        .money-badge {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 12px 20px;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 100;
        }
    </style>
</head>
<body>
    <div class="money-badge">
        <i class="fas fa-wallet" style="color: #ffd700;"></i>
        <span id="userMoney"><?= number_format($userMoney) ?> GTLM</span>
    </div>

    <div class="container">
        <div class="pass-header">
            <h1>âœ¨ MONTHLY PASS</h1>
            <p>Äáº·c quyá»n VIP háº±ng thÃ¡ng - Tá»‘i Æ°u lá»£i nhuáº­n chiáº¿n Ä‘áº¥u</p>
        </div>

        <?php if ($activePass): 
            $today = date('Y-m-d');
            $canClaim = ($activePass['last_claimed_date'] != $today);
        ?>
        <div class="active-pass-card">
            <div class="pass-icon"><?= $activePass['icon'] ?></div>
            <div class="pass-info">
                <h2>Báº¡n Ä‘ang sá»Ÿ há»¯u <?= $activePass['name'] ?></h2>
                <div class="pass-expiry">Háº¿t háº¡n: <?= date('d/m/Y H:i', strtotime($activePass['expiry_date'])) ?></div>
                <div style="margin-top: 10px; color: #28a745; font-weight: 600;">
                    <i class="fas fa-check-circle"></i> Äang kÃ­ch hoáº¡t: x2 ThÆ°á»Ÿng Lucky Wheel
                </div>
            </div>
            <button class="claim-btn" id="claimBtn" onclick="claimDaily()" <?= $canClaim ? '' : 'disabled' ?>>
                <?= $canClaim ? 'ðŸŽ Nháº­n '.number_format($activePass['daily_bonus']).' GTLM' : 'âœ… ÄÃ£ nháº­n hÃ´m nay' ?>
            </button>
        </div>
        <?php endif; ?>

        <div class="pass-grid">
            <?php foreach ($passTypes as $type): ?>
            <div class="pass-card <?= $type['name'] == 'Gold Pass' ? 'popular' : '' ?>">
                <div style="font-size: 50px; margin-bottom: 15px;"><?= $type['icon'] ?></div>
                <h3><?= $type['name'] ?></h3>
                <div class="pass-price"><?= number_format($type['price'] / 1000) ?>k <span>/ 30 ngÃ y</span></div>
                
                <ul class="benefits-list">
                    <li><i class="fas fa-gift"></i> Nháº­n ngay <?= number_format($type['instant_bonus']) ?> GTLM</li>
                    <li><i class="fas fa-calendar-check"></i> +<?= number_format($type['daily_bonus']) ?> GTLM má»—i ngÃ y</li>
                    <li><i class="fas fa-star"></i> x2 ThÆ°á»Ÿng VÃ²ng Quay May Máº¯n</li>
                    <?php if($type['name'] == 'Gold Pass'): ?>
                    <li><i class="fas fa-crown"></i> Æ¯u tiÃªn hÃ ng chá» VIP</li>
                    <li><i class="fas fa-shield-alt"></i> Badge Gold Ä‘áº·c biá»‡t</li>
                    <?php endif; ?>
                </ul>

                <button class="buy-btn" onclick="buyPass(<?= $type['id'] ?>, '<?= $type['name'] ?>', <?= $type['price'] ?>)">
                    <?= $activePass && $activePass['pass_type_id'] == $type['id'] ? 'GIA Háº N GÃ“I' : 'MUA NGAY' ?>
                </button>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align: center; margin-top: 50px;">
            <a href="index.php" style="color: rgba(255,255,255,0.5); text-decoration: none;"><i class="fas fa-arrow-left"></i> Quay láº¡i trang chá»§</a>
        </div>
    </div>

    <script>
        function buyPass(id, name, price) {
            Swal.fire({
                title: 'XÃ¡c nháº­n mua?',
                text: `Báº¡n cÃ³ muá»‘n mua ${name} vá»›i giÃ¡ ${price.toLocaleString()} GTLM khÃ´ng?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Mua ngay',
                cancelButtonText: 'Há»§y'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_monthly_pass.php', { action: 'buy', id: id }, function(res) {
                        if (res.status === 'success') {
                            Swal.fire('ThÃ nh cÃ´ng!', res.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Lá»—i!', res.message, 'error');
                        }
                    }, 'json');
                }
            });
        }

        function claimDaily() {
            $.post('api_monthly_pass.php', { action: 'claim' }, function(res) {
                if (res.status === 'success') {
                    Swal.fire('ÄÃ£ nháº­n!', res.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Lá»—i!', res.message, 'error');
                }
            }, 'json');
        }
    </script>
</body>
</html>
