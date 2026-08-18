<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_war', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require '../db_connect.php';
require_once '../load_theme.php';

// Kiểm tra đăng nhập


$userId = $botUserId;

// Lấy thông tin user
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$stmt->close();

// AJAX handler
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false, 'message' => ''];

    // Khởi tạo bộ bài
    $suits = ['♠', '♥', '♦', '♣'];
    $values = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
    $valueMap = array_flip($values); // 2=0, ..., A=12

    if ($action === 'deal') {
        $bet = (int) ($_POST['bet'] ?? 0);
        if ($bet <= 0 || $bet > $money) {
            $response['message'] = "gtlm cược không hợp lệ!";
        } else {
            // Rút 2 lá
            $pValIdx = rand(0, 12);
            $pSuitIdx = rand(0, 3);
            $dValIdx = rand(0, 12);
            $dSuitIdx = rand(0, 3);

            $playerCard = ['val' => $values[$pValIdx], 'suit' => $suits[$pSuitIdx], 'score' => $pValIdx + 2];
            $dealerCard = ['val' => $values[$dValIdx], 'suit' => $suits[$dSuitIdx], 'score' => $dValIdx + 2];

            $_SESSION['war_bet'] = $bet;
            $_SESSION['war_player_card'] = $playerCard;
            $_SESSION['war_dealer_card'] = $dealerCard;

            $status = "";
            $winAmount = 0;
            $over = false;

            if ($playerCard['score'] > $dealerCard['score']) {
                $winAmount = $bet; // Thắng ăn 1-1
                $status = "WIN";
                $over = true;
            } elseif ($playerCard['score'] < $dealerCard['score']) {
                $winAmount = -$bet;
                $status = "LOSE";
                $over = true;
            } else {
                $status = "TIE";
                $over = false;
            }

            if ($over) {
                $newMoney = $money + $winAmount;
                $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
                $stmt->bind_param("di", $newMoney, $userId);
                $stmt->execute();
                $stmt->close();
            }

            $response = [
                'success' => true,
                'playerCard' => $playerCard,
                'dealerCard' => $dealerCard,
                'status' => $status,
                'winAmount' => $winAmount,
                'money' => number_format($money + ($over ? $winAmount : 0), 0, ',', '.')
            ];
        }
    } elseif ($action === 'war') {
        $bet = $_SESSION['war_bet'];
        if ($money < $bet * 2) {
            $response['message'] = "Không đủ gtlm để tham chiến (cần gấp đôi cược ban đầu)!";
        } else {
            $pValIdx = rand(0, 12);
            $pSuitIdx = rand(0, 3);
            $dValIdx = rand(0, 12);
            $dSuitIdx = rand(0, 3);

            $playerCardNew = ['val' => $values[$pValIdx], 'suit' => $suits[$pSuitIdx], 'score' => $pValIdx + 2];
            $dealerCardNew = ['val' => $values[$dValIdx], 'suit' => $suits[$dSuitIdx], 'score' => $dValIdx + 2];

            $winAmount = 0;
            $status = "";
            if ($playerCardNew['score'] >= $dealerCardNew['score']) {
                $winAmount = $bet;
                $status = "WIN_WAR";
            } else {
                $winAmount = -($bet * 2);
                $status = "LOSE_WAR";
            }

            $newMoney = $money + $winAmount;
            $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
            $stmt->bind_param("di", $newMoney, $userId);
            $stmt->execute();
            $stmt->close();

            $response = [
                'success' => true,
                'playerCard' => $playerCardNew,
                'dealerCard' => $dealerCardNew,
                'status' => $status,
                'winAmount' => $winAmount,
                'money' => number_format($newMoney, 0, ',', '.')
            ];
        }
    } elseif ($action === 'surrender') {
        $bet = $_SESSION['war_bet'];
        $loss = ceil($bet / 2);
        $newMoney = $money - $loss;

        $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $stmt->bind_param("di", $newMoney, $userId);
        $stmt->execute();
        $stmt->close();

        $response = [
            'success' => true,
            'status' => 'SURRENDER',
            'winAmount' => -$loss,
            'money' => number_format($newMoney, 0, ',', '.')
        ];
    }

    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Casino War - Trận Chiến Bài Tây</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <style>
        body {
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Exo 2', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }

        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .glass {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 2rem;
        }

        .card-area {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: clamp(1rem, 5vw, 4rem);
            margin: 2.5rem 0;
            perspective: 1000px;
            flex-wrap: wrap;
        }

        .card-container {
            flex: 1;
            min-width: 140px;
            text-align: center;
        }

        .card-label {
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 1rem;
            color: #00d2ff;
            font-weight: 800;
        }

        .playing-card {
            width: clamp(100px, 20vw, 140px);
            aspect-ratio: 2/3;
            background: rgba(255, 255, 255, 0.05);
            border: 2px dashed rgba(255, 255, 255, 0.2);
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .playing-card.revealed {
            background: #fff;
            border: none;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
        }

        .playing-card.red {
            color: #e74c3c;
        }

        .playing-card.black {
            color: #2c3e50;
        }

        .card-value {
            position: absolute;
            top: 0.8rem;
            left: 0.8rem;
            font-size: 1.5rem;
            font-weight: 900;
        }

        .card-suit {
            font-size: clamp(3rem, 10vw, 4.5rem);
        }

        .btn-premium {
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 900;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
        }
        .btn-deal { background: #f1c40f; color: #000; }
        .btn-war { background: #e74c3c; color: #fff; }
        .btn-surrender { background: #34495e; color: #fff; }

        .chip-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-bottom: 25px;
            width: 100%;
        }
        .chip {
            padding: 8px 18px;
            background: rgba(255,255,255,0.1);
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            color: #fff;
            transition: 0.3s;
            user-select: none;
        }
        .chip:hover, .chip.active {
            background: #00d2ff;
            color: #000;
            border-color: #00d2ff;
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(0, 210, 255, 0.4);
        }
        .vs-text {
            font-size: 2.5rem;
            font-weight: 900;
            color: #f1c40f;
            opacity: 0.8;
        }
    </style>
</head>

<body>
    <div class="game-wrapper" style="max-width:800px; margin:2rem auto; position:relative; z-index:1; padding: 0 15px; width: 100%;">
        <div class="glass" style="padding: 2.5rem; text-align: center; border-radius: 2rem; width: 100%;">
            <h1 style="margin: 0 0 1rem; font-size: 2.5rem; font-weight: 900; color: #00d2ff; text-transform: uppercase; letter-spacing: 2px;">CASINO WAR</h1>
            <p style="color: rgba(255,255,255,0.6); margin-bottom: 2rem;">Lớn hơn là thắng - Đơn giản & Kịch tính</p>
            
            <div style="background: rgba(0,0,0,0.3); padding: 10px 25px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.2); display: inline-block; margin-bottom: 2rem; max-width: 100%;">
                <span style="opacity: 0.8; font-size: 0.9rem; margin-right: 5px;">SỐ GTLM:</span>
                <span id="balance-val" style="font-weight: 900; font-size: clamp(14px, 3vw, 1.5rem); color: #f1c40f; word-break: break-all;"><?php echo number_format($money, 0, ',', '.'); ?></span> <span style="font-weight: 900; font-size: clamp(14px, 3vw, 1.5rem); color: #f1c40f;">gtlm</span>
            </div>

            <div class="card-area">
                <div class="card-container">
                    <div class="card-label">Nhà Cái (Dealer)</div>
                    <div id="dealer-card" class="playing-card">
                        <div class="card-suit" style="color:rgba(255,255,255,0.2)">🂠</div>
                    </div>
                </div>
                
                <div class="vs-text">VS</div>

                <div class="card-container">
                    <div class="card-label">Bạn (Player)</div>
                    <div id="player-card" class="playing-card">
                        <div class="card-suit" style="color:rgba(255,255,255,0.2)">🂠</div>
                    </div>
                </div>
            </div>

            <div id="bet-form" class="betting-area" style="display:flex; flex-direction:column; align-items:center;">
                <div class="chip-selector" id="chipSelector">
                    <div class="chip active" data-value="10000">10K</div>
                    <div class="chip" data-value="50000">50K</div>
                    <div class="chip" data-value="100000">100K</div>
                    <div class="chip" data-value="500000">500K</div>
                    <div class="chip" data-value="1000000">1M</div>
                    <div class="chip" data-value="5000000">5M</div>
                    <div class="chip" data-value="allin">MAX</div>
                </div>

                <div class="controls" style="display:flex; gap:20px; justify-content:center; align-items:center; flex-wrap: wrap;">
                    <div style="background: rgba(0,0,0,0.3); padding: 5px 15px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.2); display: flex; align-items: center; gap: 10px;">
                        <span style="font-weight: bold; opacity: 0.8;">CƯỢC:</span>
                        <input type="number" id="bet-amt" value="10000" style="background: transparent; color:#fff; outline:none; font-size:1.3rem; font-weight:900; text-align:center; width:120px; border: none;">
                    </div>
                    
                    <button class="btn-premium btn-deal" id="deal-btn">CHIA BÀI</button>
                </div>
            </div>

            <div id="tie-controls" style="display: none;">
                <h3 style="margin-bottom: 1.5rem; color: #00d2ff; font-weight: bold;">HÒA! BẠN MUỐN LÀM GÌ?</h3>
                <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                    <button id="war-btn" class="btn-premium btn-war">THAM CHIẾN (WAR)</button>
                    <button id="surrender-btn" class="btn-premium btn-surrender">ĐẦU HÀNG (MẤT 1/2 CƯỢC)</button>
                </div>
            </div>

            <div id="result-area" style="display: none; margin-top: 2rem;">
                <button id="reset-btn" class="btn-premium btn-deal">VÁN MỚI</button>
            </div>
            
            <div style="margin-top: 3rem;">
                <a href="../index.php" style="color:#fff; text-decoration:none; border:1px solid rgba(255,255,255,0.2); padding:0.8rem 2rem; border-radius:50px; font-weight: bold; background: rgba(0,0,0,0.2); transition: 0.3s; display: inline-block;">🏠 THOÁT VỀ SẢNH</a>
            </div>
        </div>
    </div>

    <!-- Premium Effects System -->
    <canvas id="threejs-background"></canvas>
    <script>
        (function() {
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
            const prefix = window.location.pathname.includes('/games/') ? '../' : '';
            const scripts = ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'];
            
            scripts.forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();

        $(document).ready(function() {
            $('.chip').click(function() {
                if ($('#deal-btn').is(':hidden')) return;
                $('.chip').removeClass('active');
                $(this).addClass('active');
                const val = $(this).data('value');
                if (val === 'allin') {
                    $('#bet-amt').val(<?= $money ?>);
                } else {
                    $('#bet-amt').val(val);
                }
            });

            function renderCard(target, card) {
                const colorClass = (card.suit === '♥' || card.suit === '♦') ? 'red' : 'black';
                $(target).addClass('revealed ' + colorClass)
                         .html(`<div class="card-value">${card.val}</div><div class="card-suit">${card.suit}</div>`);
            }

            function handleResult(res) {
                $('#balance-val').text(res.money);

                if (res.status === 'TIE') {
                    $('#tie-controls').show();
                } else {
                    setTimeout(() => {
                        if (res.winAmount > 0) {
                            if (typeof GameEffects !== 'undefined') GameEffects.showWin(res.winAmount);
                            let title = res.status === 'WIN_WAR' ? 'Thắng Chiến Tranh' : 'Thắng';
                            Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'success', title: title, text: 'Bạn đã chiến thắng Dealer!' });
                        } else if (res.winAmount < 0) {
                            if (typeof GameEffects !== 'undefined') GameEffects.showLoss();
                            let title = res.status === 'LOSE_WAR' ? 'Thua Chiến Tranh' : 'Thua';
                            Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'error', title: title, text: 'Dealer đã chiến thắng!' });
                        }
                    }, 500);
                    
                    $('#result-area').show();
                }
            }

            $('#deal-btn').click(function() {
                const bet = $('#bet-amt').val();
                if (bet < 100) return Swal.fire('Lỗi', 'Cược tối thiểu 100 gtlm!', 'error');

                $.post('?action=deal', { bet: bet }, function(res) {
                    if (!res.success) return Swal.fire('Lỗi', res.message, 'error');
                    
                    $('#bet-form').hide();
                    
                    renderCard('#player-card', res.playerCard);
                    renderCard('#dealer-card', res.dealerCard);
                    
                    handleResult(res);
                });
            });

            $('#war-btn').click(function() {
                $.post('?action=war', function(res) {
                    if (!res.success) return Swal.fire('Lỗi', res.message, 'error');
                    
                    $('#tie-controls').hide();
                    
                    // Render new cards
                    renderCard('#player-card', res.playerCard);
                    renderCard('#dealer-card', res.dealerCard);
                    
                    handleResult(res);
                });
            });

            $('#surrender-btn').click(function() {
                $.post('?action=surrender', function(res) {
                    if (!res.success) return;
                    
                    $('#tie-controls').hide();
                    $('#balance-val').text(res.money);
                    
                    if (typeof GameEffects !== 'undefined') GameEffects.showLoss();
                    Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'info', title: 'Đầu hàng', text: 'Bạn đã mất một nửa GTLM cược.' });
                    
                    $('#result-area').show();
                });
            });

            $('#reset-btn').click(function() {
                $('#result-area').hide();
                $('#tie-controls').hide();
                $('#bet-form').show();
                
                $('#player-card').removeClass('revealed red black').html('<div class="card-suit" style="color:rgba(255,255,255,0.2)">🂠</div>');
                $('#dealer-card').removeClass('revealed red black').html('<div class="card-suit" style="color:rgba(255,255,255,0.2)">🂠</div>');
                
                $('.chip.active').click();
            });
        });
    </script>

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script>
    if (typeof BotVirtualCursor !== "undefined") {
        BotVirtualCursor.init("Bot Streamer");
        setInterval(() => {
            const allBtns = Array.from(document.querySelectorAll("button, .btn-bet, .chip, .spin-btn, #btnSpin, .bet-button, .card, .btn-primary, .btn-success, input[type='button'], input[type='submit']"));
            const btns = allBtns.filter(b => {
                if(b.offsetParent === null || b.disabled) return false;
                const txt = (b.innerText || b.value || "").toLowerCase();
                const cls = (b.className || "").toLowerCase();
                const id = (b.id || "").toLowerCase();
                
                // Exclude common navigation/help buttons
                if(txt.includes("hướng dẫn") || txt.includes("trang chủ") || txt.includes("nạp") || txt.includes("rút") || txt.includes("lịch sử") || txt.includes("quay lại") || txt.includes("thoát")) return false;
                if(cls.includes("back") || cls.includes("help") || cls.includes("guide") || cls.includes("close") || cls.includes("swal") || cls.includes("nav")) return false;
                if(id.includes("guide") || id.includes("back") || id.includes("close") || id.includes("nav")) return false;
                
                return true;
            });
            
            if(btns.length > 0) {
                const btn = btns[Math.floor(Math.random() * btns.length)];
                BotVirtualCursor.moveToElement($(btn), 1, 0, () => {
                    setTimeout(() => { 
                        BotVirtualCursor.simulateClick(() => {
                            try { btn.click(); } catch(e){}
                        });
                    }, 500);
                });
            }
        }, 3000 + Math.random() * 4000);
    }
</script>

</body>
</html>