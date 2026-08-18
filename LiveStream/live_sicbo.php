<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_sicbo', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require '../db_connect.php';
require_once '../load_theme.php';

$userId = $botUserId;
// Auto-create history table
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$stmt->close();
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    if ($action === 'roll') {
        $bets = json_decode($_POST['bets'], true); // Array of {type: 'small', amount: 1000}
        $totalBet = 0;
        foreach ($bets as $b)
            $totalBet += (int) $b['amount'];
        if ($totalBet <= 0 || $totalBet > $money) {
            echo json_encode(['success' => false, 'message' => 'Cược không hợp lệ!']);
            exit;
        }
        $dice = [rand(1, 6), rand(1, 6), rand(1, 6)];
        $sum = array_sum($dice);
        sort($dice);
        $diceStr = implode(',', $dice);
        $counts = array_count_values($dice);
        $isTriple = (count($counts) === 1);
        $anyDouble = (count($counts) < 3);
        $winAmount = -$totalBet;
        $winLog = [];
        $totalRevenue = 0;
        foreach ($bets as $b) {
            $type = $b['type'];
            $amt = (int) $b['amount'];
            $won = false;
            $pay = 0;
            if ($type === 'small') {
                if ($sum >= 4 && $sum <= 10 && !$isTriple) {
                    $won = true;
                    $pay = 1;
                }
            } elseif ($type === 'big') {
                if ($sum >= 11 && $sum <= 17 && !$isTriple) {
                    $won = true;
                    $pay = 1;
                }
            } elseif ($type === 'odd') {
                if ($sum % 2 != 0 && !$isTriple) {
                    $won = true;
                    $pay = 1;
                }
            } elseif ($type === 'even') {
                if ($sum % 2 == 0 && !$isTriple) {
                    $won = true;
                    $pay = 1;
                }
            } elseif ($type === 'any_triple') {
                if ($isTriple) {
                    $won = true;
                    $pay = 30;
                }
            } elseif (strpos($type, 'triple_') === 0) {
                $v = (int) str_replace('triple_', '', $type);
                if ($isTriple && $dice[0] == $v) {
                    $won = true;
                    $pay = 180;
                }
            } elseif (strpos($type, 'double_') === 0) {
                $v = (int) str_replace('double_', '', $type);
                if ($counts[$v] >= 2) {
                    $won = true;
                    $pay = 10;
                }
            } elseif (strpos($type, 'total_') === 0) {
                $v = (int) str_replace('total_', '', $type);
                if ($sum == $v) {
                    $won = true;
                    $pArr = [4 => 60, 5 => 30, 6 => 18, 7 => 12, 8 => 8, 9 => 7, 10 => 6, 11 => 6, 12 => 7, 13 => 8, 14 => 12, 15 => 18, 16 => 30, 17 => 60];
                    $pay = $pArr[$v] ?? 0;
                }
            } elseif (strpos($type, 'single_') === 0) {
                $v = (int) str_replace('single_', '', $type);
                if (isset($counts[$v])) {
                    $won = true;
                    $pay = $counts[$v];
                } // 1x, 2x, 3x
            }
            if ($won) {
                $revenue = $amt * ($pay + 1);
                $winAmount += $revenue;
                $totalRevenue += $revenue;
                $typeNames = [
                    'small' => 'Xanh',
                    'big' => 'Đỏ',
                    'odd' => 'Lẻ',
                    'even' => 'Chẵn',
                    'any_triple' => 'Bất kỳ bộ ba'
                ];
                $displayName = $typeNames[$type] ?? $type;
                if (strpos($type, 'single_') === 0) {
                    $displayName = 'Số ' . str_replace('single_', '', $type);
                } elseif (strpos($type, 'total_') === 0) {
                    $displayName = 'Tổng ' . str_replace('total_', '', $type);
                } elseif (strpos($type, 'double_') === 0) {
                    $displayName = 'Cặp ' . str_replace('double_', '', $type);
                } elseif (strpos($type, 'triple_') === 0) {
                    $displayName = 'Bộ ba ' . str_replace('triple_', '', $type);
                }
                $winLog[] = "$displayName (Thắng x$pay): +" . number_format($revenue, 0, ',', '.');
            }
        }
        $newMoney = $money + $winAmount;
        $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $stmt->bind_param("di", $newMoney, $userId);
        $stmt->execute();
        $stmt->close();
        // History
        $his = $conn->prepare("INSERT INTO history_sicbo (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $his->bind_param("idss", $userId, $totalBet, $diceStr, $winAmount);
        $his->execute();
        $his->close();
        echo json_encode([
            'success' => true,
            'dice' => $dice,
            'sum' => $sum,
            'winAmount' => $winAmount,
            'totalRevenue' => $totalRevenue,
            'winLog' => $winLog,
            'money' => number_format($newMoney, 0, ',', '.'),
            'rawMoney' => $newMoney
        ]);
        exit;
    } elseif ($action === 'get_history') {
        $stmt = $conn->prepare("SELECT Bet, Result, WinAmount, Time FROM history_sicbo WHERE Iduser = ? ORDER BY Time DESC LIMIT 10");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'history' => $res]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sic Bo - Đỉnh Cao Xúc Xắc</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <style>
        :root {
            --primary: #ef4444;
            --secondary: #6ee7b7;
            --accent: #fbbf24;
            --glass: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Exo 2', system-ui, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
        }
        .main-container {
            position: relative;
            z-index: 1;
            width: 95%;
            max-width: 1100px;
            margin: 2rem auto;
            text-align: center;
        }
        .game-title {
            font-size: clamp(2.5rem, 8vw, 4rem);
            font-weight: 900;
            color: var(--primary);
            text-shadow: 0 0 30px rgba(239, 68, 68, 0.4);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 12px;
        }
        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 3rem;
            padding: clamp(1.5rem, 5vw, 3rem);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            margin-bottom: 2rem;
        }
        .balance-pill {
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid var(--accent);
            padding: 0.8rem 2rem;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 2rem;
            color: var(--accent);
            font-weight: 700;
        }
        .dice-area {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 3rem;
            min-height: 100px;
        }
        .die {
            width: clamp(60px, 12vw, 90px);
            aspect-ratio: 1;
            background: #fff;
            border-radius: 1rem;
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(2.5rem, 5vw, 4rem);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            transition: transform 0.1s;
        }
        .chip-selector {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-bottom: 2.5rem;
        }
        .btn-quick-bet {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: url('../img/tay.png'), pointer !important;
            font-weight: 600;
            transition: 0.3s;
        }
        .btn-quick-bet:hover, .btn-quick-bet.sel {
            background: var(--accent);
            color: #000;
            border-color: var(--accent);
        }
        .bet-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-bottom: 3rem;
        }
        .bet-item {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
            border-radius: 1.2rem;
            padding: 1.2rem 0.5rem;
            cursor: url('../img/tay.png'), pointer !important;
            transition: all 0.2s;
            position: relative;
        }
        .bet-item:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.2);
        }
        .bet-item.active {
            border-color: var(--accent);
            background: rgba(251, 191, 36, 0.1);
        }
        .bet-item .label {
            font-size: 0.85rem;
            font-weight: 800;
            color: #fca5a5;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .bet-item .odds {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.4);
            font-weight: 600;
        }
        .chip-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background: var(--accent);
            color: #000;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            font-size: 0.7rem;
            font-weight: 900;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
            z-index: 2;
        }
        .btn-roll {
            background: linear-gradient(135deg, var(--primary) 0%, #7f1d1d 100%);
            border: none;
            padding: 1.2rem 5rem;
            border-radius: 50px;
            color: #fff;
            font-size: 1.5rem;
            font-weight: 900;
            cursor: url('../img/tay.png'), pointer !important;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 4px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.4);
        }
        .btn-roll:hover:not(:disabled) {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(239, 68, 68, 0.6);
        }
        .btn-roll:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .history-section {
            display: none;
            background: var(--glass);
            border-radius: 2.5rem;
            padding: 2.5rem;
            border: 1px solid var(--glass-border);
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .history-table th {
            color: rgba(255, 255, 255, 0.4);
            text-transform: uppercase;
            font-size: 0.8rem;
            padding: 1.2rem;
            border-bottom: 2px solid var(--glass-border);
        }
        .history-table td {
            padding: 1.2rem;
            border-bottom: 1px solid var(--glass-border);
            font-weight: 600;
        }
        @media (max-width: 768px) {
            .bet-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .btn-roll {
                padding: 1rem 2rem;
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <h1 class="game-title">SIC BO</h1>
        <div class="balance-pill">💰 Số Gtlm: <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span>
            gtlm
        </div>
        <div class="glass-card">
            <div class="dice-area" id="dice-container">
                <div class="die">🎲</div>
                <div class="die">🎲</div>
                <div class="die">🎲</div>
            </div>
            <div class="chip-selector" style="display: flex; justify-content: center; flex-wrap: wrap; gap: 0.8rem; margin-bottom: 2.5rem;">
                <button type="button" class="btn-quick-bet sel" data-val="10000">10K</button>
                <button type="button" class="btn-quick-bet" data-val="50000">50K</button>
                <button type="button" class="btn-quick-bet" data-val="100000">100K</button>
                <button type="button" class="btn-quick-bet" data-val="500000">500K</button>
                <button type="button" class="btn-quick-bet" data-val="1000000">1M</button>
                <button type="button" class="btn-quick-bet" data-val="5000000">5M</button>
                <button type="button" class="btn-quick-bet" data-val="ALL">ALL IN</button>
                <button type="button" onclick="clearBets()" style="background: rgba(255,255,255,0.1); color: #fff; border: 1px solid var(--glass-border); border-radius: 8px; cursor: pointer; padding: 8px 15px; font-weight: 800; font-size: 0.75rem; transition: 0.3s; text-transform: uppercase;">DỌN BÀN</button>
            </div>
            <div class="bet-grid">
                <div class="bet-item" data-type="small">
                    <div class="label">Ác quỷ (4-10)</div>
                    <div class="odds">1:1</div>
                </div>
                <div class="bet-item" data-type="odd">
                    <div class="label">LẺ</div>
                    <div class="odds">1:1</div>
                </div>
                <div class="bet-item" data-type="any_triple" style="grid-column: span 2;">
                    <div class="label">BẤT KỲ BỘ BA</div>
                    <div class="odds">1:30</div>
                </div>
                <div class="bet-item" data-type="even">
                    <div class="label">CHẴN</div>
                    <div class="odds">1:1</div>
                </div>
                <div class="bet-item" data-type="big">
                    <div class="label">Thiên thần (11-17)</div>
                    <div class="odds">1:1</div>
                </div>
                <div class="bet-item" data-type="single_1">
                    <div class="label">Số 1</div>
                    <div class="odds">x1,x2,x3</div>
                </div>
                <div class="bet-item" data-type="single_2">
                    <div class="label">Số 2</div>
                    <div class="odds">x1,x2,x3</div>
                </div>
                <div class="bet-item" data-type="single_3">
                    <div class="label">Số 3</div>
                    <div class="odds">x1,x2,x3</div>
                </div>
                <div class="bet-item" data-type="single_4">
                    <div class="label">Số 4</div>
                    <div class="odds">x1,x2,x3</div>
                </div>
                <div class="bet-item" data-type="single_5">
                    <div class="label">Số 5</div>
                    <div class="odds">x1,x2,x3</div>
                </div>
                <div class="bet-item" data-type="single_6">
                    <div class="label">Số 6</div>
                    <div class="odds">x1,x2,x3</div>
                </div>
                <div class="bet-item" data-type="total_9">
                    <div class="label">Tổng 9</div>
                    <div class="odds">1:7</div>
                </div>
                <div class="bet-item" data-type="total_10">
                    <div class="label">Tổng 10</div>
                    <div class="odds">1:6</div>
                </div>
                <div class="bet-item" data-type="total_11">
                    <div class="label">Tổng 11</div>
                    <div class="odds">1:6</div>
                </div>
                <div class="bet-item" data-type="total_12">
                    <div class="label">Tổng 12</div>
                    <div class="odds">1:7</div>
                </div>
                <div class="bet-item" data-type="total_4">
                    <div class="label">Tổng 4</div>
                    <div class="odds">1:60</div>
                </div>
                <div class="bet-item" data-type="total_17">
                    <div class="label">Tổng 17</div>
                    <div class="odds">1:60</div>
                </div>
            </div>
            <button id="roll-btn" class="btn-roll">LẮC XÚC XẮC</button>
        </div>
        <div class="history-section">
            <h2 style="font-size: 1.2rem; letter-spacing: 2px; margin-bottom: 1rem;">LỊCH SỬ GẦN ĐÂY</h2>
            <div style="overflow-x: auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>gtlm cược</th>
                            <th>Kết quả</th>
                            <th>Thắng/Thua</th>
                        </tr>
                    </thead>
                    <tbody id="history-body"></tbody>
                </table>
            </div>
        </div>
        <div style="margin-top: 2.5rem; margin-bottom: 5rem;"><a href="../index.php"
                style="color: var(--primary); text-decoration: none; font-weight: 700; border: 1px solid var(--primary); padding: 0.8rem 2.5rem; border-radius: 50px; transition: 0.3s;">🏠
                QUAY LẠI SẢNH</a></div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/confetti.browser.min.js"></script>
    <?php require_once '../casino_help.php'; ?>
    <script>
        let currentChip = 10000;
        let bets = {};
        let isRolling = false;
        $('.btn-quick-bet').click(function() {
            if ($(this).data('val') === undefined) return;
            $('.btn-quick-bet').removeClass('sel');
            $(this).addClass('sel');
            currentChip = $(this).data('val');
        });
        $('.bet-item').click(function() {
            if (isRolling) return;
            let type = $(this).data('type');
            let moneyText = $('#balance-val').text().replace(/\./g, '');
            let maxMoney = parseInt(moneyText);
            let currentTotalBet = Object.values(bets).reduce((a,b) => a+b, 0);
            let addAmount = currentChip;
            if (currentChip === 'ALL') {
                addAmount = maxMoney - currentTotalBet;
            } else {
                addAmount = parseInt(currentChip);
            }
            if (addAmount <= 0 || (currentTotalBet + addAmount > maxMoney)) {
                Swal.fire('Lỗi', 'Không đủ GTLM!', 'error');
                return;
            }
            bets[type] = (bets[type] || 0) + addAmount;
            $(this).addClass('active');
            let badge = $(this).find('.bet-amount-badge');
            if (badge.length === 0) {
                $(this).append(`<div class="bet-amount-badge chip-badge"></div>`);
                badge = $(this).find('.bet-amount-badge');
            }
            let displayBet = bets[type];
            if (displayBet >= 1000) {
                displayBet = (displayBet / 1000) + 'K';
            }
            badge.text(displayBet).show();
        });
        function clearBets() {
            if (isRolling) return;
            bets = {};
            $('.bet-item').removeClass('active');
            $('.bet-amount-badge').remove();
        }
        $('#roll-btn').click(function() {
            if (isRolling || Object.keys(bets).length === 0) {
                if (Object.keys(bets).length === 0) Swal.fire('Lỗi', 'Vui lòng đặt cược trước khi lắc!', 'error');
                return;
            }
            isRolling = true;
            $('#roll-btn').prop('disabled', true);
            let betArray = [];
            for (let type in bets) {
                betArray.push({ type: type, amount: bets[type] });
            }
            $.post('?action=roll', { bets: JSON.stringify(betArray) }, function(data) {
                if (!data.success) {
                    Swal.fire('Lỗi', data.message, 'error');
                    isRolling = false;
                    $('#roll-btn').prop('disabled', false);
                    return;
                }
                let diceEls = document.querySelectorAll('.die');
                diceEls.forEach((el, idx) => {
                    el.textContent = data.dice[idx];
                    el.style.transform = 'scale(1.2)';
                    setTimeout(() => el.style.transform = 'scale(1)', 200);
                });
                setTimeout(() => {
                    isRolling = false;
                    $('#roll-btn').prop('disabled', false);
                    $('#balance-val').text(data.money);
                    clearBets();
                    if (data.winAmount > 0) {
                        try {
                            if (typeof confetti !== 'undefined') {
                                confetti({ particleCount: 100, spread: 70, origin: { y: 0.6 } });
                            }
                        } catch(e) { console.error(e); }
                        Swal.fire({
                            title: 'Thắng!',
                            html: `Tổng thu về: <b style="color:#10b981">${Number(data.totalRevenue).toLocaleString()}</b> GTLM<br>Lãi ròng: <b style="color:#fbbf24">+${Number(data.winAmount).toLocaleString()}</b> GTLM<hr style="border-color:rgba(255,255,255,0.1); margin:10px 0;"><small style="text-align:left; display:block; max-height:150px; overflow-y:auto; line-height: 1.6;">${(data.winLog || []).join('<br>')}</small>`,
                            icon: 'success'
                        });
                    } else {
                        Swal.fire({
                            title: 'Tiếc quá!',
                            text: 'Trắng tay rồi. Chúc may mắn ván sau nhé!',
                            icon: 'error'
                        });
                    }
                }, 1000);
            }, 'json').fail(function(xhr) {
                isRolling = false;
                $('#roll-btn').prop('disabled', false);
                Swal.fire('Lỗi', 'Không thể kết nối đến máy chủ', 'error');
            });
        });
    </script>
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
            const prefix = window.location.pathname.includes('/games/') ? '../' : '';
            const scripts = ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'];
            scripts.forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();
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