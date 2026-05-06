<?php
session_start();
include '../db_connect.php';
require_once '../include_css.php';
include '../load_theme.php';
require_once '../game_history_helper.php';

if (!isset($_SESSION['Iduser'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['Iduser'];
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$userName = $user['Name'];
$stmt->close();

$conn->query("CREATE TABLE IF NOT EXISTS history_baucua (Id INT AUTO_INCREMENT PRIMARY KEY, Iduser INT NOT NULL, Bet DECIMAL(30,2) NOT NULL, Result VARCHAR(255) NOT NULL, WinAmount DECIMAL(30,2) NOT NULL, Time DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$animals = ["Chó", "Gà", "Mèo", "Cá", "Chim", "Heo"];
$emojis = ["Chó" => "🐶", "Gà" => "🐔", "Mèo" => "🐱", "Cá" => "🐟", "Chim" => "🐦", "Heo" => "🐷"];

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false];
    if ($action === 'play') {
        $bet = (float) ($_POST['bet'] ?? 0);
        $betsData = json_decode($_POST['bets'] ?? '[]', true); // Format: [{"animal": "Chó", "amount": 1000}, ...]

        $totalBet = 0;
        foreach ($betsData as $b) {
            if ($b['amount'] > 0)
                $totalBet += $b['amount'];
        }

        if ($totalBet <= 0 || $totalBet > $money) {
            $response['message'] = "Yêu cầu không hợp lệ!";
        } else {
            $conn->query("UPDATE users SET Money = Money - $totalBet WHERE Iduser = $userId");

            // Roll 3 dice
            $results = [];
            for ($i = 0; $i < 3; $i++)
                $results[] = $animals[rand(0, 5)];

            $totalWin = 0;
            $winAnimals = array_count_values($results);

            foreach ($betsData as $b) {
                $a = $b['animal'];
                $amt = $b['amount'];
                if (isset($winAnimals[$a])) {
                    // Standard Baucua: win + return bet. 
                    // 1 matching: 2x, 2 matching: 3x, 3 matching: 4x
                    $totalWin += $amt * ($winAnimals[$a] + 1);
                }
            }

            if ($totalWin > 0)
                $conn->query("UPDATE users SET Money = Money + $totalWin WHERE Iduser = $userId");

            $profit = $totalWin - $totalBet;
            $resStr = implode(', ', $results);
            $his = $conn->prepare("INSERT INTO history_baucua (Iduser,Bet,Result,WinAmount,Time) VALUES (?,?,?,?,NOW())");
            $his->bind_param("idss", $userId, $totalBet, $resStr, $profit);
            $his->execute();
            logGameHistoryWithAll($conn, $userId, 'Bầu Cua', $totalBet, $totalWin, $totalWin > 0);

            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
            $response = [
                'success' => true,
                'results' => $results,
                'winAmount' => number_format($totalWin, 0, ',', '.'),
                'money' => number_format($newMoney, 0, ',', '.'),
                'win' => ($totalWin > 0)
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
    <title>Cyber Bầu Cua - Premium</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;500;700&display=swap"
        rel="stylesheet">
    <?php echo getCSSIncludes(['special_effects' => true]); ?>
    <style>
        :root {
            --primary: #00ff88;
            --accent: #f1c40f;
            --glass: rgba(255, 255, 255, 0.06);
        }

        body {
            margin: 0;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Inter', sans-serif;
            overflow: hidden;
        }

        * {
            cursor: url('../img/tay.png'), auto !important;
        }

        .main-container {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 2rem;
            padding: 1.5rem;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.6);
            width: 95%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 1.5rem;
            max-height: 92vh;
            align-self: center;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .game-area {
            position: relative;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Betting Grid */
        .betting-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            width: 100%;
            max-width: 650px;
            margin-top: 20px;
        }

        .animal-tile {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1.5rem;
            padding: 15px;
            text-align: center;
            transition: 0.3s;
            position: relative;
            cursor: pointer;
            overflow: hidden;
        }

        .animal-tile:hover {
            background: rgba(0, 255, 136, 0.05);
            border-color: var(--primary);
            transform: translateY(-5px);
        }

        .animal-tile.active {
            border-color: var(--primary);
            background: rgba(0, 255, 136, 0.1);
            box-shadow: 0 0 20px rgba(0, 255, 136, 0.2);
        }

        .animal-emoji {
            font-size: 3.5rem;
            display: block;
            margin-bottom: 5px;
            transition: 0.3s;
            filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.5));
        }

        .animal-tile:hover .animal-emoji {
            transform: scale(1.1);
        }

        .animal-name {
            font-family: 'Orbitron';
            font-weight: 900;
            font-size: 0.8rem;
            letter-spacing: 1px;
            opacity: 0.6;
        }

        .bet-amount-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--primary);
            color: #000;
            font-weight: 900;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-family: 'Orbitron';
            display: none;
        }

        /* The Shaker (Bát) */
        .shaker-stage {
            width: 100%;
            height: 250px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            perspective: 1000px;
        }

        .bowl {
            width: 180px;
            height: 120px;
            background: radial-gradient(circle at 50% 20%, #ffffff, #888, #444);
            border-radius: 100px 100px 20px 20px;
            position: absolute;
            z-index: 20;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.8), inset 0 5px 15px rgba(255, 255, 255, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid rgba(255, 255, 255, 0.3);
            transform-origin: bottom center;
        }

        .shaking-sparks {
            position: absolute;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 25;
            display: none;
        }

        .spark {
            position: absolute;
            width: 2px;
            height: 15px;
            background: var(--primary);
            border-radius: 2px;
            box-shadow: 0 0 10px var(--primary);
        }

        .dice-result {
            display: flex;
            gap: 20px;
            z-index: 10;
        }

        .die {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
            transform: scale(0);
            opacity: 0;
        }

        .btn-action {
            padding: 1.2rem;
            border-radius: 1.5rem;
            border: none;
            font-weight: 900;
            font-size: 1.3rem;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
            background: linear-gradient(135deg, var(--primary), #00b894);
            color: #000;
            box-shadow: 0 10px 30px rgba(0, 255, 136, 0.3);
            width: 100%;
        }

        .btn-action:hover:not(:disabled) {
            transform: translateY(-3px);
            filter: brightness(1.1);
        }

        .btn-action:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .chip-selector {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .chip {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 3px dashed rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 0.7rem;
            cursor: pointer;
            transition: 0.3s;
            font-family: 'Orbitron';
        }

        .chip:hover,
        .chip.active {
            transform: scale(1.1);
            border-color: var(--primary);
            background: rgba(0, 255, 136, 0.2);
            border-style: solid;
        }

        .stat-card {
            background: rgba(0, 0, 0, 0.3);
            padding: 1rem;
            border-radius: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            text-align: center;
        }

        .stat-card span {
            display: block;
            font-size: 0.6rem;
            opacity: 0.5;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .animal-tile.winner { border-color: #fff; background: rgba(0, 255, 136, 0.4); box-shadow: 0 0 50px var(--primary); animation: animal-bounce 0.6s infinite; z-index: 5; }
        @keyframes animal-bounce { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1) translateY(-10px); } }
        
        .floating-win { position: absolute; bottom: 50%; left: 50%; transform: translateX(-50%); color: var(--accent); font-family: 'Orbitron'; font-weight: 900; font-size: 1.5rem; pointer-events: none; text-shadow: 0 0 10px #000; z-index: 100; }
        
        .game-area.lose-shake { animation: lose-shake 0.5s cubic-bezier(.36,.07,.19,.97) both; }
        @keyframes lose-shake { 10%, 90% { transform: translate3d(-1px, 0, 0); } 20%, 80% { transform: translate3d(2px, 0, 0); } 30%, 50%, 70% { transform: translate3d(-4px, 0, 0); } 40%, 60% { transform: translate3d(4px, 0, 0); } }
        
        .glitch-red { position: absolute; inset: 0; background: rgba(255, 71, 87, 0.2); mix-blend-mode: overlay; pointer-events: none; opacity: 0; }
    </style>
</head>

<body>
    <div class="main-container">
        <div class="glass-card">
            <div class="sidebar">
                <div>
                    <h1
                        style="margin:0; font-size: 2.2rem; font-weight: 900; color: var(--primary); font-family: 'Orbitron'; letter-spacing: 2px;">
                        CYBER PETS</h1>
                    <p style="margin:0; opacity:0.4; font-size: 0.7rem; letter-spacing: 1px;">Quantum Farm Protocol</p>
                </div>

                <div class="stat-card">
                    <span>Số Gtlm HIỆN TẠI</span>
                    <div style="display:flex; align-items:baseline; justify-content:center; gap:5px;">
                        <b id="userMoney"><?= number_format($money, 0, ',', '.') ?></b>
                        <small style="opacity:0.5; font-weight:900; font-size:0.6rem;">GTLM</small>
                    </div>
                </div>

                <div class="stat-card" style="background:rgba(0,255,136,0.05); border-color:rgba(0,255,136,0.1)">
                    <span>TỔNG CƯỢC</span>
                    <div style="display:flex; align-items:baseline; justify-content:center; gap:5px;">
                        <b id="totalBet" style="color:var(--primary)">0</b>
                    </div>
                </div>

                <div style="margin-top:auto;">
                    <div class="stat-card" style="margin-bottom:10px; padding:0.8rem; border-color:rgba(255,255,255,0.1)">
                        <span>CƯỢC MỖI LẦN NHẤN</span>
                        <input type="number" id="customBet" value="1000" min="1000" step="1000" 
                               style="background:none; border:none; color:var(--accent); font-family:'Orbitron'; font-size:1.2rem; font-weight:900; width:100%; text-align:center; outline:none;">
                    </div>

                    <div class="chip-selector">
                        <div class="chip active" data-val="1000">1K</div>
                        <div class="chip" data-val="5000">5K</div>
                        <div class="chip" data-val="10000">10K</div>
                        <div class="chip" data-val="50000">50K</div>
                        <div class="chip" data-val="100000">100K</div>
                    </div>
                    <button id="playBtn" class="btn-action" onclick="playGame()">⚡ XÓC NGAY</button>
                    <button class="btn-action" onclick="clearBets()"
                        style="background:rgba(255,255,255,0.1); color:#fff; font-size:0.8rem; padding:0.8rem; margin-top:10px; box-shadow:none;">XÓA
                        CƯỢC</button>
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="../index.php"
                            style="color: #fff; text-decoration: none; font-size: 0.7rem; opacity: 0.3;">← Quay về
                            Dashboard</a>
                    </div>
                </div>
            </div>

            <div class="game-area">
                <div class="shaker-stage">
                    <div class="dice-result">
                        <div class="die" id="die0">🐶</div>
                        <div class="die" id="die1">🐱</div>
                        <div class="die" id="die2">🐔</div>
                    </div>
                    <div class="bowl" id="bowl"></div>
                    <div class="shaking-sparks" id="shakingSparks"></div>
                </div>

                <div class="betting-grid">
                    <?php foreach ($animals as $a): ?>
                        <div class="animal-tile" data-animal="<?= $a ?>" onclick="placeBet('<?= $a ?>')">
                            <span class="animal-emoji"><?= $emojis[$a] ?></span>
                            <span class="animal-name"><?= strtoupper($a) ?></span>
                            <span class="bet-amount-badge" id="bet-<?= $a ?>">0</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentChip = 1000;
        let myBets = {};
        let isRolling = false;
        const animalEmojis = <?= json_encode($emojis) ?>;

        $('.chip').click(function () {
            $('.chip').removeClass('active');
            $(this).addClass('active');
            currentChip = parseInt($(this).data('val'));
            $('#customBet').val(currentChip);
        });

        $('#customBet').on('input', function() {
            currentChip = parseInt($(this).val()) || 0;
            $('.chip').removeClass('active');
            // Re-activate chip if matches
            $(`.chip[data-val="${currentChip}"]`).addClass('active');
        });

        function placeBet(animal) {
            if (isRolling) return;
            let amt = parseInt($('#customBet').val()) || 0;
            if (amt <= 0) return;
            myBets[animal] = (myBets[animal] || 0) + amt;
            updateBetUI();
        }

        function clearBets() {
            if (isRolling) return;
            myBets = {};
            updateBetUI();
        }

        function updateBetUI() {
            let total = 0;
            $('.animal-tile').removeClass('active');
            $('.bet-amount-badge').hide();

            for (let a in myBets) {
                if (myBets[a] > 0) {
                    total += myBets[a];
                    $(`.animal-tile[data-animal="${a}"]`).addClass('active');
                    $(`#bet-${a}`).text(myBets[a].toLocaleString('vi-VN')).show();
                }
            }
            $('#totalBet').text(total.toLocaleString('vi-VN'));
        }

        function playGame() {
            let total = 0;
            const betsData = [];
            for (let a in myBets) {
                if (myBets[a] > 0) {
                    total += myBets[a];
                    betsData.push({ animal: a, amount: myBets[a] });
                }
            }

            if (total === 0) { Swal.fire('Lỗi', 'Vui lòng đặt cược ít nhất một linh vật!', 'warning'); return; }
            if (isRolling) return;

            isRolling = true;
            $('#playBtn').prop('disabled', true).text('ĐANG XÓC...');

            const bowl = $('#bowl'), sparks = $('#shakingSparks');
            $('.animal-tile').removeClass('winner');
            $('.die').css('opacity', 0); // Reset dice visibility
            
            // Step 1: Slam bowl down and start loop shake
            const tl = gsap.timeline();
            tl.to(bowl, { y: 0, rotateX: 0, rotateZ: 0, opacity: 1, duration: 0.4, ease: "bounce.out" })
              .add(() => {
                  sparks.show().empty();
                  for(let i=0; i<12; i++) {
                      $('<div class="spark"></div>').css({
                          left: Math.random()*100 + '%', top: Math.random()*100 + '%',
                          transform: `rotate(${Math.random()*360}deg)`
                      }).appendTo(sparks);
                  }
                  gsap.to('.spark', { opacity: 0, scale: 2, duration: 0.2, repeat: -1, stagger: 0.1 });
                  
                  gsap.to(bowl, {
                      x: "random(-12, 12)", y: "random(-6, 6)", rotateZ: "random(-10, 10)",
                      duration: 0.1, repeat: -1, yoyo: true
                  });
              });

            $.post('baucua.php?action=play', { bets: JSON.stringify(betsData) }, function (res) {
                if (res.success) {
                    // Ensure at least 1.5s of shaking
                    setTimeout(() => {
                        gsap.killTweensOf(bowl); // Stop shaking
                        sparks.hide();
                        
                        // Step 2: Lift bowl and show results
                        gsap.to(bowl, { y: -250, x: 100, rotateZ: 45, opacity: 0, duration: 0.8, ease: "power2.inOut" });
                        
                        res.results.forEach((animal, i) => {
                            const die = $(`#die${i}`);
                            die.text(animalEmojis[animal]).css('opacity', 1);
                            gsap.fromTo(die, { scale: 0, rotate: -180 }, { scale: 1, rotate: 0, duration: 0.6, delay: i * 0.2, ease: "back.out(1.7)" });
                            $(`.animal-tile[data-animal="${animal}"]`).addClass('winner');
                        });

                        setTimeout(() => {
                            $('#userMoney').text(res.money);
                            if (res.win) {
                                if (window.GameEffects) window.GameEffects.showWin(parseInt(res.winAmount.replace(/\./g, '')));
                                $('.animal-tile.active.winner').each(function() {
                                    const float = $('<div class="floating-win">+' + res.winAmount + '</div>').appendTo($(this));
                                    gsap.to(float, { y: -100, opacity: 0, duration: 2, onComplete: () => float.remove() });
                                });
                                Swal.fire({ title: 'CHIẾN THẮNG!', html: `Tổng nhận: <b style="color:#f1c40f">${res.winAmount} gtlm</b>`, icon: 'success', background: '#1a1a1a', color: '#fff', timer: 2000, showConfirmButton: false });
                            } else {
                                $('.game-area').addClass('lose-shake');
                                if (window.GameEffects) window.GameEffects.showLoss(0);
                                setTimeout(() => $('.game-area').removeClass('lose-shake'), 500);
                            }

                            // Step 3: Reset for next round
                            setTimeout(() => {
                                gsap.to(bowl, { y: 0, x: 0, rotateZ: 0, opacity: 1, duration: 0.5 });
                                gsap.to('.die', { scale: 0, opacity: 0, duration: 0.3 });
                                $('.animal-tile').removeClass('winner');
                                $('#playBtn').prop('disabled', false).text('⚡ XÓC NGAY');
                                isRolling = false;
                                clearBets();
                            }, 2500);
                        }, 1200);
                    }, 1500);
                } else {
                    gsap.killTweensOf(bowl);
                    sparks.hide();
                    Swal.fire('Lỗi', res.message, 'error');
                    $('#playBtn').prop('disabled', false).text('⚡ XÓC NGAY');
                    isRolling = false;
                }
            });
        }
    </script>

    <canvas id="threejs-background"></canvas>
    <script>
        (function () {
            window.themeConfig = {
                particleCount: 500,
                particleSize: 0.05,
                particleColor: '#00ff88',
                particleOpacity: 0.4,
                shapeCount: 15,
                shapeColors: ["#00ff88", "#00b894", "#ffffff"],
                shapeOpacity: 0.15,
                bgGradient: ["#000000", "#001a11", "#002a1b"]
            };
            const prefix = '../';
            ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'].forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src; s.async = false;
                document.head.appendChild(s);
            });
        })();
    </script>
</body>

</html>