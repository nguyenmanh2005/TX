<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_58', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require '../db_connect.php';

$useBotTheme = $botUserId;
require_once '../load_theme.php';

$userId = $botUserId;
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$userName = $user['Name'];
$stmt->close();

// Auto-create history table
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false];
    if ($action === 'roll') {
        $bet = (float) ($_POST['bet'] ?? 0);
        $keep = $_POST['keep'] ?? [];
        $rollCount = (int) ($_POST['rollCount'] ?? 0);
        if ($rollCount == 1) {
            if ($bet <= 0 || $bet > $money) {
                $response['message'] = "Số GTLM cược không đủ hoặc không hợp lệ!";
                echo json_encode($response);
                exit;
            }
            // Initial roll
            $conn->begin_transaction();
            $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $stmtLock->bind_param("i", $userId);
            $stmtLock->execute();
            $lockedMoney = $stmtLock->get_result()->fetch_assoc()['Money'] ?? 0;
            $stmtLock->close();
            if ($bet > $lockedMoney) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Số dư không đủ hoặc thao tác quá nhanh!']);
                exit;
            }
            $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");
            $_SESSION['yahtzee_bet'] = $bet;
            $_SESSION['yahtzee_dice'] = [0, 0, 0, 0, 0];
        }
        $dice = $_SESSION['yahtzee_dice'] ?? [rand(1, 6), rand(1, 6), rand(1, 6), rand(1, 6), rand(1, 6)];
        for ($i = 0; $i < 5; $i++) {
            if (!in_array($i, $keep)) {
                $dice[$i] = rand(1, 6);
            }
        }
        $_SESSION['yahtzee_dice'] = $dice;
        $conn->commit();
        $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
        $response = [
            'success' => true,
            'dice' => $dice,
            'money' => number_format($newMoney, 0, ',', '.')
        ];
    } elseif ($action === 'submit') {
        $category = $_POST['category'];
        $dice = $_SESSION['yahtzee_dice'] ?? null;
        $bet = $_SESSION['yahtzee_bet'] ?? 0;
        if (!$dice || $bet <= 0) {
            $response['message'] = "Phiên chơi đã kết thúc hoặc không hợp lệ!";
        } else {
            $counts = array_count_values($dice);
            $winMult = 0;
            switch ($category) {
                case 'ones':
                    $winMult = ($counts[1] ?? 0) * 0.5;
                    break;
                case 'twos':
                    $winMult = ($counts[2] ?? 0) * 1.0;
                    break;
                case 'threes':
                    $winMult = ($counts[3] ?? 0) * 1.5;
                    break;
                case 'fours':
                    $winMult = ($counts[4] ?? 0) * 2.0;
                    break;
                case 'fives':
                    $winMult = ($counts[5] ?? 0) * 2.5;
                    break;
                case 'sixes':
                    $winMult = ($counts[6] ?? 0) * 3.0;
                    break;
                case 'threeofakind':
                    if (max($counts) >= 3)
                        $winMult = 5;
                    break;
                case 'fourofakind':
                    if (max($counts) >= 4)
                        $winMult = 10;
                    break;
                case 'fullhouse':
                    $cv = array_values($counts);
                    sort($cv);
                    if ($cv === [2, 3] || $cv === [5])
                        $winMult = 15;
                    break;
                case 'yahtzee':
                    if (max($counts) === 5)
                        $winMult = 50;
                    break;
            }
            $winAmount = round($bet * $winMult);
            $profit = $winAmount - $bet;
            $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $userId");
            $resStr = "Dice: " . implode(',', $dice) . " | Category: $category";
            $his = $conn->prepare("INSERT INTO history_yahtzee (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
            $his->bind_param("idss", $userId, $bet, $resStr, $profit);
            $his->execute();
            unset($_SESSION['yahtzee_bet']);
            unset($_SESSION['yahtzee_dice']);
            $conn->commit();
            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
            $response = [
                'success' => true,
                'winAmount' => $winAmount,
                'profit' => $profit,
                'winAmountFormatted' => number_format($winAmount, 0, ',', '.'),
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
    <title>Yahtzee Royale - Cao Cấp</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <style>
        :root {
            --primary-color: #ff4757;
            --accent-color: #ffa502;
            --glass: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }
        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #fff;
            min-height: 100vh;
            font-family: 'Exo 2', system-ui, sans-serif;
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

        #result-status-badge.badge-jackpot {
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
            border-bottom: 2px solid var(--primary-color);
            box-sizing: border-box;
        }

        .logo-yahtzee {
            font-size: 20px;
            font-weight: 900;
            color: var(--primary-color);
            letter-spacing: 2px;
            text-shadow: 0 0 10px rgba(255, 71, 87, 0.5);
        }

        .user-money {
            background: rgba(0, 0, 0, 0.4);
            padding: 5px 18px;
            border-radius: 30px;
            border: 1px solid var(--accent-color);
            font-weight: 800;
            color: var(--accent-color);
            font-size: 15px;
            box-shadow: 0 0 15px rgba(255, 165, 2, 0.1);
        }

        .main-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 820px;
            margin: 0.6rem auto;
            padding: 0 10px;
            box-sizing: border-box;
        }

        .glass-card {
            background: rgba(18, 18, 30, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 1.6rem;
            padding: 1.2rem 1.6rem;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            margin-bottom: 0.8rem;
            box-sizing: border-box;
        }

        .dice-area {
            display: flex;
            justify-content: center;
            gap: 14px;
            margin-bottom: 1.2rem;
            flex-wrap: wrap;
        }

        .die {
            width: 62px;
            height: 62px;
            background: #fff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #000;
            font-weight: 900;
            cursor: pointer;
            transition: 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
            user-select: none;
        }

        .die.held {
            background: var(--primary-color);
            color: #fff;
            transform: translateY(-6px);
            box-shadow: 0 0 16px var(--primary-color);
        }

        .die.held::after {
            content: "GIỮ";
            position: absolute;
            bottom: -18px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.7rem;
            font-weight: 900;
            color: var(--primary-color);
            letter-spacing: 1px;
        }

        .score-card {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .score-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.6rem 1rem;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: 0.25s;
            font-size: 0.85rem;
        }

        .score-row:hover {
            background: rgba(255, 71, 87, 0.2);
            border-color: var(--primary-color);
            transform: scale(1.02);
        }

        .score-label {
            font-weight: 700;
        }

        .score-mult {
            color: var(--accent-color);
            font-weight: 900;
        }

        .btn-roll {
            background: linear-gradient(135deg, var(--primary-color), #ff6b81);
            color: #fff;
            border: 2px solid var(--accent-color);
            padding: 0.8rem 1.5rem;
            border-radius: 35px;
            font-size: 1.1rem;
            font-weight: 900;
            cursor: pointer;
            width: 100%;
            transition: 0.3s;
            margin-top: 6px;
            box-shadow: 0 6px 20px rgba(255, 71, 87, 0.4);
            text-transform: uppercase;
        }

        .btn-roll:hover:not(:disabled) {
            transform: translateY(-2px) scale(1.02);
            filter: brightness(1.1);
            box-shadow: 0 10px 25px rgba(255, 71, 87, 0.6);
        }

        .btn-roll:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .quick-bet-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
            margin-bottom: 12px;
            width: 100%;
        }

        .quick-btn {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.2);
            color: #e2e8f0;
            padding: 5px 14px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .quick-btn:hover, .quick-btn.active {
            background: var(--accent-color);
            color: #000;
            border-color: var(--accent-color);
            transform: scale(1.06);
            box-shadow: 0 4px 12px rgba(255, 165, 2, 0.4);
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
        <div class="logo-yahtzee">🎲 YAHTZEE ROYALE</div>
        <div class="user-money">💰 <span id="userMoney"><?php echo number_format($money, 0, ',', '.'); ?></span> GTLM</div>
        <div style="font-size: 13px; color: #aaa;">STREAMER: <b style="color: #ffd700;"><?= htmlspecialchars($userName) ?></b></div>
    </header>

    <div class="main-container">
        <div class="glass-card">
            <div class="dice-area" id="diceArea">
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <div class="die" data-index="<?php echo $i; ?>" onclick="toggleHold(this)">?</div>
                <?php endfor; ?>
            </div>

            <div style="max-width: 580px; margin: 0 auto;">
                <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 12px; font-weight: 800; font-size: 1rem;">
                    <div style="background: rgba(0,0,0,0.3); padding: 4px 14px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.15);">
                        <span>CƯỢC: </span>
                        <input type="number" id="betAmount" value="10000"
                            style="background:none; border:none; color:var(--accent-color); width:80px; text-align:center; font-weight:900; outline:none; font-size:1.1rem;">
                        <span>GTLM</span>
                    </div>
                    <div style="background: rgba(0,0,0,0.3); padding: 4px 14px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.15);">
                        <span>LẦN LẮC: <span id="rollCount" style="color:var(--primary-color); font-size:1.2rem;">0</span>/3</span>
                    </div>
                </div>

                <div class="quick-bet-grid">
                    <button class="quick-btn active" onclick="setBet(10000, event)">10K</button>
                    <button class="quick-btn" onclick="setBet(50000, event)">50K</button>
                    <button class="quick-btn" onclick="setBet(100000, event)">100K</button>
                    <button class="quick-btn" onclick="setBet(500000, event)">500K</button>
                    <button class="quick-btn" onclick="setBet(1000000, event)">1M</button>
                    <button class="quick-btn" onclick="setBet(5000000, event)">5M</button>
                </div>

                <button class="btn-roll" id="rollBtn" onclick="rollDice()">🎲 LẮC XÚC XẮC</button>

                <div style="margin: 1rem 0 0.6rem; text-align:center; text-transform:uppercase; letter-spacing:1.5px; font-size: 12px; color: #ffd700; font-weight: 800;">
                    BẢNG ĐIỂM & TỔ HỢP TRẢ THƯỞNG
                </div>

                <div class="score-card" id="scoreCard">
                    <div class="score-row" data-cat="ones" onclick="submitScore('ones')"><span class="score-label">Bộ 1</span><span class="score-mult">x0.5</span></div>
                    <div class="score-row" data-cat="twos" onclick="submitScore('twos')"><span class="score-label">Bộ 2</span><span class="score-mult">x1.0</span></div>
                    <div class="score-row" data-cat="threes" onclick="submitScore('threes')"><span class="score-label">Bộ 3</span><span class="score-mult">x1.5</span></div>
                    <div class="score-row" data-cat="fours" onclick="submitScore('fours')"><span class="score-label">Bộ 4</span><span class="score-mult">x2.0</span></div>
                    <div class="score-row" data-cat="fives" onclick="submitScore('fives')"><span class="score-label">Bộ 5</span><span class="score-mult">x2.5</span></div>
                    <div class="score-row" data-cat="sixes" onclick="submitScore('sixes')"><span class="score-label">Bộ 6</span><span class="score-mult">x3.0</span></div>
                    <div class="score-row" data-cat="threeofakind" onclick="submitScore('threeofakind')"><span class="score-label">Bộ Ba</span><span class="score-mult">x5.0</span></div>
                    <div class="score-row" data-cat="fourofakind" onclick="submitScore('fourofakind')"><span class="score-label">Tứ Quý</span><span class="score-mult">x10.0</span></div>
                    <div class="score-row" data-cat="fullhouse" onclick="submitScore('fullhouse')"><span class="score-label">Cù Lũ</span><span class="score-mult">x15.0</span></div>
                    <div class="score-row" data-cat="yahtzee" onclick="submitScore('yahtzee')"><span class="score-label" style="color:var(--accent-color); font-weight:900;">👑 YAHTZEE</span><span class="score-mult" style="color:var(--accent-color)">x50.0</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Theme Config & Effects -->
    <script>
        window.themeConfig = {
            particleCount: <?= $particleCount ?? 800 ?>,
            particleSize: <?= $particleSize ?? 0.05 ?>,
            particleColor: '<?= $particleColor ?? "#ff4757" ?>',
            particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
            shapeCount: <?= $shapeCount ?? 10 ?>,
            shapeColors: <?= json_encode($shapeColors ?? ["#ff4757", "#ff6b81", "#70a1ff"]) ?>,
            shapeOpacity: <?= $shapeOpacity ?? 0.3 ?>,
            bgGradient: <?= json_encode($bgGradient ?? ["#000000", "#12001a", "#250033"]) ?>
        };
    </script>
    <script src="../threejs-background.js"></script>
    <script src="../assets/js/game-effects.js"></script>
    <script src="../assets/js/game-effects-auto.js"></script>

    <script>
        function showResultStatus(type, text, icon) {
            const badge = document.getElementById('result-status-badge');
            if (!badge) return;
            badge.className = '';
            badge.classList.add('badge-' + type);
            badge.querySelector('.badge-icon').textContent = icon || (type === 'jackpot' ? '👑' : (type === 'win' ? '🎉' : '😢'));
            badge.querySelector('.badge-text').textContent = text;
            badge.style.display = 'flex';
            void badge.offsetWidth;
            badge.classList.add('show');

            if (type === 'jackpot' || type === 'win') {
                if (typeof GameEffects !== 'undefined' && GameEffects.win) {
                    GameEffects.win();
                }
                if (typeof confetti === 'function') {
                    confetti({ particleCount: 140, spread: 75, origin: { y: 0.6 }, colors: ['#ff4757', '#ffa502', '#70a1ff'] });
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

        function setBet(amount, event) {
            $('#betAmount').val(amount);
            $('.quick-btn').removeClass('active');
            if (event && event.target) {
                event.target.classList.add('active');
            }
        }
        let rollCount = 0;
        let isRolling = false;
        let currentDice = [0, 0, 0, 0, 0];

        function toggleHold(el) {
            if (rollCount === 0 || isRolling) return;
            $(el).toggleClass('held');
        }

        function rollDice() {
            if (isRolling) return;
            if (rollCount >= 3) {
                return;
            }
            const bet = parseInt($('#betAmount').val());
            if (isNaN(bet) || bet <= 0) {
                return;
            }
            let keep = [];
            $('.die').each(function() {
                if ($(this).hasClass('held')) {
                    keep.push($(this).data('index'));
                }
            });
            isRolling = true;
            $('#rollBtn').prop('disabled', true);
            // Animation xóc xí ngầu GSAP
            gsap.to('.die:not(.held)', {
                y: -30,
                rotationX: 360,
                rotationY: 360,
                duration: 0.4,
                stagger: 0.08,
                yoyo: true,
                repeat: 1
            });
            $.post('?action=roll', { bet: bet, keep: keep, rollCount: rollCount + 1 }, function(res) {
                setTimeout(() => {
                    if (res.success) {
                        rollCount++;
                        $('#rollCount').text(rollCount);
                        $('#userMoney').text(res.money);
                        currentDice = res.dice;
                        // Cập nhật mặt xúc xắc
                        $('.die').each(function(i) {
                            $(this).text(res.dice[i]);
                        });
                        if (rollCount >= 3) {
                            $('#rollBtn').prop('disabled', true);
                        } else {
                            $('#rollBtn').prop('disabled', false);
                        }
                    } else {
                        $('#rollBtn').prop('disabled', false);
                    }
                    isRolling = false;
                }, 700);
            }).fail(function() {
                isRolling = false;
                $('#rollBtn').prop('disabled', false);
            });
        }

        function submitScore(category) {
            if (rollCount === 0 || isRolling) {
                return;
            }
            $.post('?action=submit', { category: category }, function(res) {
                if (res.success) {
                    const winVal = parseInt(res.winAmount);
                    if (winVal > 0) {
                        if (category === 'yahtzee' || category === 'fullhouse' || category === 'fourofakind' || winVal >= 100000) {
                            showResultStatus('jackpot', `👑 ĐẠI THẮNG ${category.toUpperCase()}! +${res.winAmountFormatted} GTLM`, '👑');
                        } else {
                            showResultStatus('win', `🎉 CHIẾN THẮNG! +${res.winAmountFormatted} GTLM`, '🎉');
                        }
                    } else {
                        showResultStatus('lose', `😢 BAY MÀU (0 GTLM THƯỞNG)`, '😢');
                    }

                    // Reset game
                    rollCount = 0;
                    $('#rollCount').text('0');
                    $('#userMoney').text(res.money);
                    $('.die').removeClass('held').text('?');
                    $('#rollBtn').prop('disabled', false);
                }
            });
        }
    </script>

    <!-- Nạp Bot AI Chuyên Nghiệp -->
    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="bots/bot_58.js"></script>
</body>
</html>
