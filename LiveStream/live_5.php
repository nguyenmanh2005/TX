<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_5', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require '../db_connect.php';
require_once '../load_theme.php';



$userId = $botUserId;

// Lấy thông tin user
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$stmt->close();

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false, 'message' => ''];

    if ($action === 'get_balance') {
        $stmtB = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
        $stmtB->bind_param("i", $userId);
        $stmtB->execute();
        $bRow = $stmtB->get_result()->fetch_assoc();
        $stmtB->close();
        $curMoney = (float)($bRow['Money'] ?? 0);
        echo json_encode([
            'success' => true,
            'money' => number_format($curMoney, 0, ',', '.'),
            'rawMoney' => $curMoney
        ]);
        exit;
    }

    $suits = ['♠', '♥', '♦', '♣'];
    $values = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];

    if ($action === 'deal') {
        $betDragon = (int) ($_POST['betDragon'] ?? 0);
        $betTiger = (int) ($_POST['betTiger'] ?? 0);
        $betTie = (int) ($_POST['betTie'] ?? 0);
        $totalBet = $betDragon + $betTiger + $betTie;

        if ($totalBet <= 0 || $totalBet > $money) {
            $response['message'] = "gtlm cược không hợp lệ hoặc không đủ Gtlm!";
        } else {
            // Deduct total bet first
            $conn->begin_transaction();
            $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $stmtLock->bind_param("i", $userId);
            $stmtLock->execute();
            $lockedMoney = $stmtLock->get_result()->fetch_assoc()['Money'] ?? 0;
            $stmtLock->close();
            if ($totalBet > $lockedMoney) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Số dư không đủ hoặc thao tác quá nhanh!']);
                exit;
            }
            $conn->query("UPDATE users SET Money = Money - $totalBet WHERE Iduser = $userId");

            $dValIdx = rand(0, 12);
            $dSuitIdx = rand(0, 3);
            $tValIdx = rand(0, 12);
            $tSuitIdx = rand(0, 3);

            $dragonCard = ['val' => $values[$dValIdx], 'suit' => $suits[$dSuitIdx], 'score' => $dValIdx + 1];
            $tigerCard = ['val' => $values[$tValIdx], 'suit' => $suits[$tSuitIdx], 'score' => $tValIdx + 1];

            $totalReturn = 0;
            $winAmount = -$totalBet;

            if ($dragonCard['score'] > $tigerCard['score']) {
                $totalReturn += ($betDragon * 2);
                $winAmount += ($betDragon * 2);
            } elseif ($dragonCard['score'] < $tigerCard['score']) {
                $totalReturn += ($betTiger * 2);
                $winAmount += ($betTiger * 2);
            } else {
                // Tie
                $totalReturn += ($betTie * 9); // Tie pays 8:1 + original bet = 9
                $totalReturn += ($betDragon * 0.5) + ($betTiger * 0.5); // Return half of dragon/tiger bets
                
                $winAmount += ($betTie * 9) + ($betDragon * 0.5) + ($betTiger * 0.5);
            }

            if ($totalReturn > 0) {
                $conn->query("UPDATE users SET Money = Money + $totalReturn WHERE Iduser = $userId");
            }

            $conn->commit();
            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];

            $resSide = ($dragonCard['score'] > $tigerCard['score']) ? 'Dragon' : ($dragonCard['score'] < $tigerCard['score'] ? 'Tiger' : 'Tie');

            // Tích hợp hệ thống Nhiệm vụ Sự kiện, Battle Pass, XP, v.v.
            require_once '../game_history_helper.php';
            logGameHistoryWithAll($conn, $userId, 'Rồng Hổ', $totalBet, $totalReturn, $totalReturn > $totalBet);

            $response = [
                'success' => true,
                'dragonCard' => $dragonCard,
                'tigerCard' => $tigerCard,
                'winSide' => $resSide,
                'winAmount' => $winAmount,
                'money' => number_format($newMoney, 0, ',', '.')
            ];
        }
    }

    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Long Hổ Tranh Đấu - Dragon Tiger</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Exo+2:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Exo 2', sans-serif;
            margin: 0;
            padding: 0;
            overflow: hidden;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        ::-webkit-scrollbar {
            display: none !important;
            width: 0px !important;
            height: 0px !important;
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
            border-radius: 1.2rem;
        }

        .table-area {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin: 0.5rem 0;
        }

        .side-box {
            flex: 1;
            max-width: 260px;
            padding: 0.6rem 1rem;
            border-radius: 1rem;
            background: rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.4s;
        }

        .side-box.dragon { border-color: #3498db; }
        .side-box.tiger { border-color: #e74c3c; }

        .side-box.winner-dragon {
            box-shadow: 0 0 30px #3498db;
            background: rgba(52, 152, 219, 0.2);
            transform: scale(1.03);
        }

        .side-box.winner-tiger {
            box-shadow: 0 0 30px #e74c3c;
            background: rgba(231, 76, 60, 0.2);
            transform: scale(1.03);
        }

        .playing-card {
            width: 80px;
            aspect-ratio: 2/3;
            background: rgba(255, 255, 255, 0.05);
            border: 2px dashed rgba(255, 255, 255, 0.2);
            border-radius: 0.6rem;
            color: rgba(255,255,255,0.2);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin: 0.4rem auto;
            position: relative;
            transition: transform 0.6s;
        }
        
        .playing-card.revealed {
            background: #fff;
            border: none;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
        }

        .playing-card.red { color: #e74c3c; }
        .playing-card.black { color: #2c3e50; }

        .card-val {
            font-size: 1.2rem;
            position: absolute;
            top: 0.3rem;
            left: 0.5rem;
            font-weight: 900;
        }

        .card-suit {
            font-size: 2.4rem;
        }

        .bet-area {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.8rem;
            margin: 0.5rem auto;
            max-width: 600px;
        }

        .bet-zone {
            padding: 0.5rem 0.8rem;
            border-radius: 0.8rem;
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.2);
            cursor: pointer;
            transition: all 0.3s;
        }

        .bet-zone.focused {
            background: rgba(255,255,255,0.1);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.3);
        }
        
        .bet-zone.dragon-zone.focused { border-color: #3498db; box-shadow: 0 0 15px rgba(52,152,219,0.4); }
        .bet-zone.tie-zone.focused { border-color: #f1c40f; box-shadow: 0 0 15px rgba(241,196,15,0.4); }
        .bet-zone.tiger-zone.focused { border-color: #e74c3c; box-shadow: 0 0 15px rgba(231,76,60,0.4); }

        .bet-label {
            font-size: 0.95rem;
            font-weight: 900;
            margin-bottom: 0.3rem;
            letter-spacing: 1px;
        }

        .dragon-label { color: #3498db; }
        .tiger-label { color: #e74c3c; }
        .tie-label { color: #f1c40f; }

        .bet-zone input {
            width: 100%;
            background: transparent;
            border: none;
            color: #fff;
            text-align: center;
            font-size: 1.1rem;
            font-weight: bold;
            outline: none;
            pointer-events: none;
        }

        .btn-premium {
            background: linear-gradient(135deg, #f1c40f 0%, #d35400 100%);
            border: none;
            padding: 0.6rem 2.5rem;
            border-radius: 30px;
            color: #fff;
            font-size: 1.1rem;
            font-weight: 900;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(211, 84, 0, 0.3);
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 2px;
            width: 100%;
            max-width: 280px;
            margin: 6px auto;
            display: block;
        }

        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(211, 84, 0, 0.5);
        }

        .chip-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: center;
            margin-bottom: 8px;
            width: 100%;
        }
        
        .chip {
            padding: 4px 12px;
            background: rgba(255,255,255,0.1);
            border: 1.5px solid rgba(255,255,255,0.3);
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.85rem;
            color: #fff;
            transition: 0.2s;
            user-select: none;
        }
        
        .chip:hover, .chip.active {
            background: #00d2ff;
            color: #000;
            border-color: #00d2ff;
            transform: scale(1.08);
            box-shadow: 0 4px 12px rgba(0, 210, 255, 0.4);
        }
        .floating-win {
            position: absolute;
            bottom: 45%;
            left: 50%;
            transform: translateX(-50%);
            color: #00ff88;
            font-family: 'Exo 2', sans-serif;
            font-weight: 900;
            font-size: 1.4rem;
            pointer-events: none;
            text-shadow: 0 0 10px rgba(0, 0, 0, 0.9), 0 0 20px rgba(0, 255, 136, 0.6);
            z-index: 1000;
            white-space: nowrap;
        }
        .lose-shake { animation: lose-shake 0.5s cubic-bezier(.36,.07,.19,.97) both; }
        @keyframes lose-shake { 10%, 90% { transform: translate3d(-2px, 0, 0); } 20%, 80% { transform: translate3d(3px, 0, 0); } 30%, 50%, 70% { transform: translate3d(-5px, 0, 0); } 40%, 60% { transform: translate3d(5px, 0, 0); } }
    </style>
</head>

<body>
    <div class="game-wrapper" style="max-width: 800px; margin: 0.4rem auto; position: relative; z-index: 1; padding: 0 10px; width: 100%;">
        <div class="glass" style="padding: 1rem 1.2rem; text-align: center; border-radius: 1.2rem; width: 100%;">
            <h1 style="margin: 0 0 0.3rem; font-size: 1.8rem; font-weight: 900; background: linear-gradient(45deg, #f1c40f, #e67e22, #f1c40f); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-transform: uppercase; letter-spacing: 2px;">DRAGON TIGER</h1>
            
            <div style="background: rgba(0,0,0,0.3); padding: 4px 15px; border-radius: 30px; border: 1px solid rgba(255,255,255,0.2); display: inline-block; margin-bottom: 0.6rem; max-width: 100%;">
                <span style="opacity: 0.8; font-size: 0.8rem; margin-right: 4px;">SỐ GTLM:</span>
                <span id="balance-val" style="font-weight: 900; font-size: 1.1rem; color: #f1c40f; word-break: break-all;"><?php echo number_format($money, 0, ',', '.'); ?></span> <span style="font-weight: 900; font-size: 1.1rem; color: #f1c40f;">gtlm</span>
            </div>

            <div class="table-area">
                <div id="dragon-box" class="side-box dragon" style="position: relative;">
                    <div class="dragon-label bet-label" style="font-size: 1.2rem;">RỒNG (DRAGON)</div>
                    <div id="dragon-card" class="playing-card">
                        <div class="card-suit" style="font-size: 2.4rem; color: rgba(255,255,255,0.2)">🐉</div>
                    </div>
                </div>

                <div style="font-size: 2rem; font-weight: 900; color: rgba(255,255,255,0.2);">VS</div>

                <div id="tiger-box" class="side-box tiger" style="position: relative;">
                    <div class="tiger-label bet-label" style="font-size: 1.2rem;">HỔ (TIGER)</div>
                    <div id="tiger-card" class="playing-card">
                        <div class="card-suit" style="font-size: 2.4rem; color: rgba(255,255,255,0.2)">🐯</div>
                    </div>
                </div>
            </div>

            <div id="betting-section">
                <p style="margin-bottom: 6px; opacity: 0.8; font-size: 0.8rem;">CHỌN Ô CƯỢC SAU ĐÓ CHỌN PHỈNH ĐỂ ĐẶT GTLM</p>
                <div class="chip-selector" id="chipSelector">
                    <div class="chip" data-value="10000">10K</div>
                    <div class="chip" data-value="50000">50K</div>
                    <div class="chip" data-value="100000">100K</div>
                    <div class="chip" data-value="500000">500K</div>
                    <div class="chip" data-value="1000000">1M</div>
                    <div class="chip" data-value="5000000">5M</div>
                    <div class="chip" data-value="0">XÓA</div>
                </div>

                <div class="bet-area">
                    <div class="bet-zone dragon-zone focused" id="box-dragon" onclick="selectBetBox('dragon')" style="position: relative;">
                        <div class="dragon-label bet-label">DRAGON (1:1)</div>
                        <input type="number" id="bet-dragon" value="0">
                    </div>
                    <div class="bet-zone tie-zone" id="box-tie" onclick="selectBetBox('tie')" style="position: relative;">
                        <div class="tie-label bet-label">TIE (1:8)</div>
                        <input type="number" id="bet-tie" value="0">
                    </div>
                    <div class="bet-zone tiger-zone" id="box-tiger" onclick="selectBetBox('tiger')" style="position: relative;">
                        <div class="tiger-label bet-label">TIGER (1:1)</div>
                        <input type="number" id="bet-tiger" value="0">
                    </div>
                </div>

                <button id="deal-btn" class="btn-premium">QUYẾT ĐẤU</button>
            </div>
            
            <div id="reset-section" style="display: none; margin-top: 0.8rem;">
                <button id="reset-btn" class="btn-premium" style="background: linear-gradient(135deg, #3498db, #2ecc71);">VÁN MỚI</button>
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

        let currentBetBox = 'dragon';

        function selectBetBox(box) {
            currentBetBox = box;
            $('.bet-zone').removeClass('focused');
            $('#box-' + box).addClass('focused');
        }

        $(document).ready(function() {
            $('.chip').click(function() {
                if ($('#deal-btn').is(':hidden')) return;
                const val = $(this).data('value');
                $('#bet-' + currentBetBox).val(val);
                
                $('#box-' + currentBetBox).css('transform', 'scale(1.05)');
                setTimeout(() => $('#box-' + currentBetBox).css('transform', 'none'), 150);
            });

            function renderCard(target, card) {
                const colorClass = (card.suit === '♥' || card.suit === '♦') ? 'red' : 'black';
                $(target).css({ transform: 'scale(1.15) rotateY(90deg)' });
                setTimeout(() => {
                    $(target).addClass('revealed ' + colorClass)
                             .html(`<div class="card-val">${card.val}</div><div class="card-suit">${card.suit}</div>`)
                             .css({ transform: 'scale(1) rotateY(0deg)' });
                }, 200);
            }

            $('#deal-btn').click(function() {
                const betDragon = parseInt($('#bet-dragon').val()) || 0;
                const betTiger = parseInt($('#bet-tiger').val()) || 0;
                const betTie = parseInt($('#bet-tie').val()) || 0;
                const totalBet = betDragon + betTiger + betTie;

                if (totalBet <= 0) return Swal.fire('Lỗi', 'Vui lòng đặt cược ít nhất một cửa!', 'error');

                $.post('?action=deal', { betDragon: betDragon, betTiger: betTiger, betTie: betTie }, function(res) {
                    if (!res.success) return Swal.fire('Lỗi', res.message, 'error');
                    
                    $('#deal-btn, #chipSelector').hide();
                    
                    // Lật bài Rồng trước (0.2s)
                    renderCard('#dragon-card', res.dragonCard);

                    // Lật bài Hổ sau (0.7s) tạo kịch tính
                    setTimeout(() => {
                        renderCard('#tiger-card', res.tigerCard);

                        // Highlight bên chiến thắng sau khi cả 2 bài đã mở
                        setTimeout(() => {
                            if (res.winSide === 'Dragon') {
                                $('#dragon-box').addClass('winner-dragon');
                            } else if (res.winSide === 'Tiger') {
                                $('#tiger-box').addClass('winner-tiger');
                            } else {
                                $('#dragon-box').addClass('winner-tie');
                                $('#tiger-box').addClass('winner-tie');
                            }
                            
                            $('#balance-val').text(res.money);

                            // Hiệu ứng thông báo + / - GTLM chuẩn 100% Thế Giới Linh Thú
                            if (res.winAmount > 0) {
                                if (window.GameEffects) window.GameEffects.showWin(res.winAmount);
                                
                                $('.bet-zone').each(function() {
                                    const input = $(this).find('input');
                                    const betVal = parseInt(input.val()) || 0;
                                    const boxId = $(this).attr('id');
                                    let isWinner = false;
                                    if (res.winSide === 'Dragon' && boxId === 'box-dragon') isWinner = true;
                                    if (res.winSide === 'Tiger' && boxId === 'box-tiger') isWinner = true;
                                    if (res.winSide === 'Tie' && boxId === 'box-tie') isWinner = true;

                                    if (isWinner && betVal > 0) {
                                        const mult = (res.winSide === 'Tie') ? 8 : 1;
                                        const winTile = betVal * mult;
                                        const float = $('<div class="floating-win">+' + winTile.toLocaleString('vi-VN') + '</div>').appendTo($(this));
                                        gsap.to(float, { y: -80, opacity: 0, duration: 2, onComplete: () => float.remove() });
                                    }
                                });
                            } else if (res.winAmount < 0) {
                                $('.game-wrapper').addClass('lose-shake');
                                if (window.GameEffects) window.GameEffects.showLoss(Math.abs(res.winAmount));
                                
                                $('.bet-zone').each(function() {
                                    const input = $(this).find('input');
                                    const betVal = parseInt(input.val()) || 0;
                                    if (betVal > 0) {
                                        const float = $('<div class="floating-win" style="color: #ff4757; text-shadow: 0 0 10px #000, 0 0 20px rgba(255,71,87,0.8);">-' + betVal.toLocaleString('vi-VN') + '</div>').appendTo($(this));
                                        gsap.to(float, { y: -80, opacity: 0, duration: 2, onComplete: () => float.remove() });
                                    }
                                });
                                setTimeout(() => $('.game-wrapper').removeClass('lose-shake'), 600);
                            }
                            
                            $('#reset-section').fadeIn(300);
                        }, 500);
                    }, 500);
                });
            });

            $('#reset-btn').click(function() {
                $('#reset-section').hide();
                $('#deal-btn, #chipSelector').show();
                
                // Reset giao diện và kết quả
                $('#dragon-box').removeClass('winner-dragon winner-tie');
                $('#tiger-box').removeClass('winner-tiger winner-tie');
                
                $('#dragon-card').removeClass('revealed red black').html('<div class="card-suit" style="font-size:2.4rem; color:rgba(255,255,255,0.2)">🐉</div>');
                $('#tiger-card').removeClass('revealed red black').html('<div class="card-suit" style="font-size:2.4rem; color:rgba(255,255,255,0.2)">🐯</div>');

                // 🔄 RESET CƯỢC LẠI SAU MỖI VÁN (Về 0)
                $('#bet-dragon').val(0);
                $('#bet-tiger').val(0);
                $('#bet-tie').val(0);
                $('.bet-zone').removeClass('focused');
                $('#box-dragon').addClass('focused');
                currentBetBox = 'dragon';
            });

            // Tự động đồng bộ số dư Bot khi nhận Tip / Quà từ người xem
            setInterval(() => {
                if ($('#deal-btn').is(':visible')) {
                    $.get('?action=get_balance', function(res) {
                        if (res && res.success && res.money) {
                            $('#balance-val').text(res.money);
                        }
                    });
                }
            }, 2000);
        });
    </script>

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script src="bots/bot_5.js"></script>

</body>
</html>
