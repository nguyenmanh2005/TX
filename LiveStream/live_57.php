<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_57', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require '../db_connect.php';

$useBotTheme = $botUserId;
require_once '../load_theme.php';

$userId = $botUserId;

// Lấy thông tin user
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$userName = $user['Name'];
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
            $response['message'] = "Số GTLM cược không hợp lệ!";
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
            $response['message'] = "Không đủ GTLM để tham chiến (cần gấp đôi cược ban đầu)!";
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
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <style>
        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Exo 2', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: -1;
            pointer-events: none;
        }

        /* ── RESULT STATUS BADGE (CHÍNH XÁC NHƯ GAME 1) ── */
        #result-status-badge {
            position: fixed;
            top: 22%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.8);
            display: none;
            align-items: center;
            gap: 12px;
            padding: 12px 28px;
            border-radius: 50px;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6), 0 0 20px rgba(255, 255, 255, 0.2);
            z-index: 9999;
            pointer-events: none;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            opacity: 0;
            backdrop-filter: blur(10px);
        }

        #result-status-badge.show {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }

        #result-status-badge.badge-win {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.95), rgba(5, 150, 105, 0.95));
            border: 2px solid #34d399;
            color: #fff;
            box-shadow: 0 0 35px rgba(16, 185, 129, 0.7);
        }

        #result-status-badge.badge-war-win {
            background: linear-gradient(135deg, rgba(234, 179, 8, 0.95), rgba(217, 119, 6, 0.95));
            border: 2px solid #fbbf24;
            color: #fff;
            box-shadow: 0 0 45px rgba(234, 179, 8, 0.9);
            animation: pulseGlow 1s infinite alternate;
        }

        #result-status-badge.badge-lose {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.9), rgba(185, 28, 28, 0.9));
            border: 2px solid #f87171;
            color: #fff;
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.6);
        }

        #result-status-badge.badge-tie {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.95), rgba(37, 99, 235, 0.95));
            border: 2px solid #60a5fa;
            color: #fff;
            box-shadow: 0 0 35px rgba(59, 130, 246, 0.7);
        }

        @keyframes pulseGlow {
            from { transform: translate(-50%, -50%) scale(1); filter: brightness(1); }
            to { transform: translate(-50%, -50%) scale(1.06); filter: brightness(1.2); }
        }

        .header-bar {
            width: 100%;
            padding: 8px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(15px);
            border-bottom: 2px solid #f1c40f;
            box-sizing: border-box;
        }

        .logo-war {
            font-size: 20px;
            font-weight: 900;
            color: #ffd700;
            letter-spacing: 2px;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
        }

        .user-money {
            background: rgba(0, 0, 0, 0.4);
            padding: 5px 18px;
            border-radius: 30px;
            border: 1px solid #ffd700;
            font-weight: 800;
            color: #ffd700;
            font-size: 15px;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.1);
        }

        .game-wrapper {
            max-width: 780px;
            margin: 0.8rem auto;
            position: relative;
            z-index: 1;
            padding: 0 10px;
            width: 100%;
            box-sizing: border-box;
        }

        .glass {
            background: rgba(18, 18, 30, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 1.6rem;
            padding: 1.2rem 1.6rem;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            box-sizing: border-box;
        }

        .card-area {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: clamp(1rem, 4vw, 2.5rem);
            margin: 1.2rem 0;
            perspective: 1000px;
        }

        .card-container {
            flex: 1;
            min-width: 110px;
            text-align: center;
        }

        .card-label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 0.6rem;
            color: #00d2ff;
            font-weight: 800;
        }

        .playing-card {
            width: clamp(75px, 12vw, 95px);
            aspect-ratio: 2/3;
            background: rgba(255, 255, 255, 0.05);
            border: 2px dashed rgba(255, 255, 255, 0.2);
            border-radius: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .playing-card.revealed {
            background: #fff;
            border: none;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }

        .playing-card.red {
            color: #e74c3c;
        }

        .playing-card.black {
            color: #2c3e50;
        }

        .card-value {
            position: absolute;
            top: 0.4rem;
            left: 0.5rem;
            font-size: 1.1rem;
            font-weight: 900;
        }

        .card-suit {
            font-size: clamp(2.2rem, 6vw, 3rem);
        }

        .vs-text {
            font-size: 1.8rem;
            font-weight: 900;
            color: #f1c40f;
            opacity: 0.85;
        }

        .btn-premium {
            padding: 0.65rem 2rem;
            border: none;
            border-radius: 40px;
            font-weight: 900;
            font-size: 0.95rem;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .btn-premium:hover:not(:disabled) {
            transform: translateY(-2px) scale(1.03);
            filter: brightness(1.1);
        }
        .btn-deal { background: linear-gradient(135deg, #f1c40f, #d4ac0d); color: #000; }
        .btn-war { background: linear-gradient(135deg, #e74c3c, #c0392b); color: #fff; }
        .btn-surrender { background: linear-gradient(135deg, #34495e, #2c3e50); color: #fff; }

        .chip-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin-bottom: 14px;
            width: 100%;
        }
        .chip {
            padding: 5px 14px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.85rem;
            color: #fff;
            transition: 0.25s;
            user-select: none;
        }
        .chip:hover, .chip.active {
            background: #00d2ff;
            color: #000;
            border-color: #00d2ff;
            transform: scale(1.08);
            box-shadow: 0 4px 12px rgba(0, 210, 255, 0.4);
        }

        .home-link {
            display: none !important;
        }
    </style>
</head>

<body>
    <!-- ThreeJS 3D Canvas Background -->
    <canvas id="threejs-background"></canvas>

    <!-- Modal Status Badge (Thắng / Thua Game 1) -->
    <div id="result-status-badge">
        <span class="badge-icon">🎉</span>
        <span class="badge-text">CHIẾN THẮNG</span>
    </div>

    <header class="header-bar">
        <div class="logo-war">⚔️ CASINO WAR</div>
        <div class="user-money">💰 <span id="balance-val"><?php echo number_format($money, 0, ',', '.'); ?></span> GTLM</div>
        <div style="font-size: 13px; color: #aaa;">STREAMER: <b style="color: #ffd700;"><?= htmlspecialchars($userName) ?></b></div>
    </header>

    <div class="game-wrapper">
        <div class="glass">
            <div style="font-size: 13px; color: rgba(255,255,255,0.6); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1.5px;">
                LỚN HƠN LÀ THẮNG — ĐƠN GIẢN & KỊCH TÍNH
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
                    <div class="card-label">Streamer (Player)</div>
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

                <div class="controls" style="display:flex; gap:15px; justify-content:center; align-items:center; flex-wrap: wrap;">
                    <div style="background: rgba(0,0,0,0.3); padding: 4px 14px; border-radius: 30px; border: 1px solid rgba(255,255,255,0.2); display: flex; align-items: center; gap: 8px;">
                        <span style="font-weight: bold; opacity: 0.8; font-size: 13px;">CƯỢC:</span>
                        <input type="number" id="bet-amt" value="10000" style="background: transparent; color:#fff; outline:none; font-size:1.1rem; font-weight:900; text-align:center; width:100px; border: none;">
                    </div>
                    
                    <button class="btn-premium btn-deal" id="deal-btn">CHIA BÀI</button>
                </div>
            </div>

            <div id="tie-controls" style="display: none; margin-top: 10px;">
                <h3 style="margin: 0 0 10px; color: #00d2ff; font-weight: bold; font-size: 15px;">HÒA BÀI! BẠN MUỐN LÀM GÌ?</h3>
                <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                    <button id="war-btn" class="btn-premium btn-war">⚔️ THAM CHIẾN (WAR)</button>
                    <button id="surrender-btn" class="btn-premium btn-surrender">🏳️ ĐẦU HÀNG (MẤT 1/2 CƯỢC)</button>
                </div>
            </div>

            <div id="result-area" style="display: none; margin-top: 12px;">
                <button id="reset-btn" class="btn-premium btn-deal">🔄 VÁN MỚI</button>
            </div>
        </div>
    </div>

    <!-- Theme Config & Effects -->
    <script>
        window.themeConfig = {
            particleCount: <?= $particleCount ?? 800 ?>,
            particleSize: <?= $particleSize ?? 0.05 ?>,
            particleColor: '<?= $particleColor ?? "#ffd700" ?>',
            particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
            shapeCount: <?= $shapeCount ?? 10 ?>,
            shapeColors: <?= json_encode($shapeColors ?? ["#ffd700", "#ff4757", "#12c2e9"]) ?>,
            shapeOpacity: <?= $shapeOpacity ?? 0.3 ?>,
            bgGradient: <?= json_encode($bgGradient ?? ["#1a0b2e", "#2a1b3d", "#000000"]) ?>
        };
    </script>
    <script src="../threejs-background.js"></script>
    <script src="../assets/js/game-effects.js"></script>
    <script src="../assets/js/game-effects-auto.js"></script>

    <!-- Game Logic -->
    <script>
        function showResultStatus(type, text, icon) {
            const badge = document.getElementById('result-status-badge');
            if (!badge) return;
            badge.className = '';
            badge.classList.add('badge-' + type);
            badge.querySelector('.badge-icon').textContent = icon || (type.includes('win') ? '🎉' : (type === 'tie' ? '🤝' : '😢'));
            badge.querySelector('.badge-text').textContent = text;
            badge.style.display = 'flex';
            void badge.offsetWidth;
            badge.classList.add('show');

            if (type === 'win' || type === 'war-win') {
                if (typeof GameEffects !== 'undefined' && GameEffects.win) {
                    GameEffects.win();
                }
                if (typeof confetti === 'function') {
                    confetti({ particleCount: 120, spread: 70, origin: { y: 0.6 }, colors: ['#ffd700', '#00d2ff', '#e74c3c'] });
                }
            } else if (type === 'lose') {
                if (typeof GameEffects !== 'undefined' && GameEffects.lose) {
                    GameEffects.lose();
                }
            }

            setTimeout(() => {
                badge.classList.remove('show');
                setTimeout(() => {
                    badge.style.display = 'none';
                }, 400);
            }, 3500);
        }

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
                    showResultStatus('tie', 'HÒA BÀI! THAM CHIẾN HAY ĐẦU HÀNG?', '🤝');
                    $('#tie-controls').show();
                } else {
                    setTimeout(() => {
                        if (res.status === 'WIN_WAR') {
                            showResultStatus('war-win', `👑 ĐẠI THẮNG CHIẾN TRANH! +${res.winAmount.toLocaleString('vi-VN')} GTLM`, '🏆');
                        } else if (res.status === 'WIN') {
                            showResultStatus('win', `🎉 CHIẾN THẮNG DEALER! +${res.winAmount.toLocaleString('vi-VN')} GTLM`, '🎉');
                        } else if (res.status === 'LOSE_WAR') {
                            showResultStatus('lose', `💀 THUA CHIẾN TRANH! ${res.winAmount.toLocaleString('vi-VN')} GTLM`, '💀');
                        } else if (res.status === 'SURRENDER') {
                            showResultStatus('lose', `🏳️ ĐẦU HÀNG (MẤT 1/2 CƯỢC) ${res.winAmount.toLocaleString('vi-VN')} GTLM`, '🏳️');
                        } else {
                            showResultStatus('lose', `😢 BAY MÀU ${res.winAmount.toLocaleString('vi-VN')} GTLM`, '😢');
                        }
                    }, 400);
                    
                    $('#result-area').show();
                }
            }

            $('#deal-btn').click(function() {
                const bet = $('#bet-amt').val();
                if (bet < 100) return;

                $.post('?action=deal', { bet: bet }, function(res) {
                    if (!res.success) return;
                    
                    $('#bet-form').hide();
                    
                    renderCard('#player-card', res.playerCard);
                    renderCard('#dealer-card', res.dealerCard);
                    
                    handleResult(res);
                });
            });

            $('#war-btn').click(function() {
                $.post('?action=war', function(res) {
                    if (!res.success) return;
                    
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
                    handleResult(res);
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

    <!-- Nạp Bot AI Chuyên Nghiệp -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="bots/bot_57.js"></script>
</body>
</html>