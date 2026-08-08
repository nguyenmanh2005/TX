<?php
session_start();
if (!isset($_SESSION['Iduser'])) {
    header('Location: ../login.php');
    exit;
}

require '../db_connect.php';
require_once '../load_theme.php';
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #1a1c29 0%, #2a2d3e 100%)';
}

$userId = $_SESSION['Iduser'];

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
        
        // Random Rarity
        $rand = rand(1, 1000);
        if ($rand <= 50) {
            // SSR - 5%
            $rarity = 'SSR';
            $reward = 500000;
            $icon = 'fa-gem';
            $color = '#facc15';
        } elseif ($rand <= 400) {
            // SR - 35%
            $rarity = 'SR';
            $reward = 100000;
            $icon = 'fa-crown';
            $color = '#c084fc';
        } else {
            // R - 60%
            $rarity = 'R';
            $reward = 20000;
            $icon = 'fa-star';
            $color = '#38bdf8';
        }
        
        $conn->query("UPDATE users SET Money = Money + $reward WHERE Iduser = $userId");
        
        // Ghi log
        require_once '../game_history_helper.php';
        logGameHistory($conn, $userId, 'gacha_cards', $cost, $reward, true);
        
        $conn->commit();
        echo json_encode([
            'success' => true,
            'rarity' => $rarity,
            'reward' => $reward,
            'icon' => $icon,
            'color' => $color,
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            text-align: center;
            min-height: 100vh;
            padding-top: 50px;
        }

        .header h1 {
            font-size: 3rem;
            background: linear-gradient(to right, #facc15, #e879f9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .header p { color: #94a3b8; font-size: 1.2rem; }

        .card-container {
            perspective: 1000px;
            margin: 50px auto;
            width: 250px;
            height: 350px;
            cursor: pointer;
        }

        .card {
            width: 100%;
            height: 100%;
            position: relative;
            transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            transform-style: preserve-3d;
        }

        .card.flipped { transform: rotateY(180deg) scale(1.1); box-shadow: 0 0 50px rgba(255,255,255,0.2); }

        .card-face {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            border-radius: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            border: 2px solid rgba(255,255,255,0.1);
        }

        .card-front {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            background-image: repeating-linear-gradient(45deg, rgba(255,255,255,0.05) 0px, rgba(255,255,255,0.05) 10px, transparent 10px, transparent 20px);
        }

        .card-front i { font-size: 80px; color: #475569; }

        .card-back {
            background: linear-gradient(135deg, #334155, #1e293b);
            transform: rotateY(180deg);
        }

        .rarity-badge {
            position: absolute;
            top: -20px;
            background: #fff;
            color: #000;
            padding: 5px 20px;
            border-radius: 20px;
            font-weight: 900;
            font-size: 24px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .card-back i { font-size: 100px; margin-bottom: 20px; }
        .reward-amount { font-size: 24px; font-weight: bold; }

        .btn-pull {
            background: linear-gradient(135deg, #e879f9, #c084fc);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 10px 20px rgba(192, 132, 252, 0.4);
            margin-top: 30px;
        }

        .btn-pull:hover { transform: translateY(-5px); box-shadow: 0 15px 25px rgba(192, 132, 252, 0.6); }

        .wallet { margin-top: 20px; font-size: 18px; color: #facc15; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔮 Gacha Thẻ Nhân Phẩm</h1>
        <p>Thử thách nhân phẩm với 50,000 GTLM/lần mở</p>
    </div>

    <div class="wallet"><i class="fa fa-wallet"></i> GTLM của bạn: <span id="user-money"><?= number_format($userMoney) ?></span></div>

    <div class="card-container" id="gacha-container">
        <div class="card" id="gacha-card">
            <div class="card-face card-front">
                <i class="fa fa-question-circle"></i>
                <h3 style="margin-top: 20px; color: #94a3b8;">? ? ?</h3>
            </div>
            <div class="card-face card-back" id="card-result">
                <div class="rarity-badge" id="res-rarity">SSR</div>
                <i class="fa" id="res-icon"></i>
                <div class="reward-amount" id="res-reward">0 GTLM</div>
            </div>
        </div>
    </div>

    <button class="btn-pull" id="btn-pull" onclick="pullCard()">Triệu Hồi Thẻ (50k)</button>

    <div style="margin-top: 50px;">
        <a href="../games.php" style="color: #94a3b8; text-decoration: none;"><i class="fa fa-arrow-left"></i> Trở về Sảnh Game</a>
    </div>

    <script>
        let isPulling = false;
        function pullCard() {
            if (isPulling) return;
            isPulling = true;
            $('#btn-pull').prop('disabled', true).text('Đang triệu hồi...');
            
            // Reset card
            $('#gacha-card').removeClass('flipped');
            
            setTimeout(() => {
                $.post('gacha_cards.php', { action: 'pull' }, function(res) {
                    if (res.success) {
                        $('#res-rarity').text(res.rarity).css('color', res.color);
                        $('#res-icon').attr('class', 'fa ' + res.icon).css('color', res.color);
                        $('#res-reward').text(new Intl.NumberFormat().format(res.reward) + ' GTLM').css('color', res.color);
                        $('#card-result').css('box-shadow', `0 0 50px ${res.color}40`);
                        
                        // Flip animation
                        $('#gacha-card').addClass('flipped');
                        
                        // Update wallet (temporary UI update, will fetch exact later)
                        let currentMoney = parseInt($('#user-money').text().replace(/,/g, ''));
                        currentMoney = currentMoney - 50000 + parseInt(res.reward);
                        $('#user-money').text(new Intl.NumberFormat().format(currentMoney));

                        setTimeout(() => {
                            Swal.fire({
                                title: res.rarity + '!',
                                text: res.message,
                                icon: 'success',
                                confirmButtonColor: res.color
                            });
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
</body>
</html>
