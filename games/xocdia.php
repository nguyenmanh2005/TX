<?php
session_start();
include '../load_theme.php';
require_once '../game_history_helper.php';

if (!isset($_SESSION['Iduser'])) { header('Location: ../login.php'); exit; }

$userId = $_SESSION['Iduser'];
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money']; $userName = $user['Name']; $stmt->close();

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action']; $response = ['success' => false];
    
    if ($action === 'play') {
        $betsData = json_decode($_POST['bets'] ?? '[]', true);
        $totalCharge = 0;
        foreach($betsData as $b) { if($b['amount'] > 0) $totalCharge += $b['amount']; }

        if ($totalCharge <= 0 || $totalCharge > $money) { $response['message'] = "Năng lượng không đủ!"; }
        else {
            $conn->query("UPDATE users SET Money = Money - $totalCharge WHERE Iduser = $userId");
            
            // 4 Orbs: 0=Negative (Cyan), 1=Positive (Magenta)
            $orbs = [rand(0,1), rand(0,1), rand(0,1), rand(0,1)];
            $posCount = array_sum($orbs);
            $state = ($posCount % 2 === 0) ? 'Stable' : 'Volatile';
            
            $totalReward = 0;
            foreach($betsData as $b) {
                $c = $b['choice']; $amt = $b['amount'];
                if ($c === 'Stable' && $state === 'Stable') $totalReward += $amt * 1.96;
                elseif ($c === 'Volatile' && $state === 'Volatile') $totalReward += $amt * 1.96;
                elseif ($c === 'Full Negative' && $posCount === 0) $totalReward += $amt * 12;
                elseif ($c === 'Full Positive' && $posCount === 4) $totalReward += $amt * 12;
                elseif ($c === 'Triple Negative' && $posCount === 1) $totalReward += $amt * 3.5;
                elseif ($c === 'Triple Positive' && $posCount === 3) $totalReward += $amt * 3.5;
            }
            
            if ($totalReward > 0) $conn->query("UPDATE users SET Money = Money + $totalReward WHERE Iduser = $userId");
            
            $profit = $totalReward - $totalCharge;
            $resStr = "P: $posCount, N: ".(4-$posCount);
            $his = $conn->prepare("INSERT INTO history_xocdia (Iduser,Bet,Result,WinAmount,Time) VALUES (?,?,?,?,NOW())");
            $his->bind_param("idss", $userId, $totalCharge, $resStr, $profit); $his->execute();
            logGameHistoryWithAll($conn, $userId, 'Quantum Pulse', $totalCharge, $totalReward, $totalReward > 0);
            
            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
            $response = [
                'success'=>true, 'orbs'=>$orbs, 'state'=>$state, 'posCount'=>$posCount,
                'rewardAmount'=>number_format($totalReward,0,',','.'),
                'money'=>number_format($newMoney,0,',','.'), 'win'=>($totalReward > 0)
            ];
        }
    }
    echo json_encode($response); exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quantum Pulse - Particle Synchronization</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <link rel="stylesheet" href="../assets/css/game-effects.css">
    <style>
        :root { --primary: #00f2fe; --accent: #712cf9; --glass: rgba(255,255,255,0.06); }
        body { margin:0; background: <?= $bgGradientCSS ?>; background-attachment: fixed; color:#fff; font-family:'Inter',sans-serif; overflow:hidden; }
        * { cursor: url('../img/tay.png'), auto !important; }
        
        .main-container { height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; box-sizing:border-box; }
        .glass-card { background:var(--glass); backdrop-filter:blur(30px); border:1px solid rgba(255,255,255,0.1); border-radius:2rem; padding:1.5rem; box-shadow:0 40px 100px rgba(0,0,0,0.8); width:95%; max-width:1200px; display:grid; grid-template-columns:320px 1fr; gap:1.5rem; height:90vh; }
        
        .sidebar { display:flex; flex-direction:column; gap:1rem; }
        .chamber-area { position:relative; background:rgba(0,0,0,0.5); border-radius:2rem; border:1px solid rgba(0,242,254,0.2); overflow:hidden; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:20px; box-shadow: inset 0 0 50px rgba(0,242,254,0.1); }
        
        .stat-card { background:rgba(0,0,0,0.3); padding:1rem; border-radius:1.5rem; border:1px solid rgba(255,255,255,0.05); text-align:center; }
        .stat-card span { display:block; font-size:0.6rem; opacity:0.5; font-weight:700; text-transform:uppercase; margin-bottom:5px; }
        .stat-card b { font-size:1.3rem; font-family:'Orbitron'; color:var(--primary); }
        
        .sync-board { display:grid; grid-template-columns: repeat(2, 1fr); gap:10px; width:100%; margin-top:20px; }
        .sync-option { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.1); border-radius:1.2rem; padding:15px; text-align:center; transition:0.3s; position:relative; cursor:pointer; overflow:hidden; border-left: 4px solid transparent; }
        .sync-option:hover { background:rgba(0,242,254,0.05); border-color:var(--primary); transform:translateY(-3px); }
        .sync-option.active { border-color:var(--primary); border-left-color:var(--primary); background:rgba(0,242,254,0.1); box-shadow:0 0 20px rgba(0,242,254,0.1); }
        .sync-option b { display:block; font-family:'Orbitron'; font-size:1.1rem; margin-bottom:5px; color: var(--primary); }
        .sync-option span { font-size:0.7rem; opacity:0.5; }
        .charge-amt { position:absolute; top:5px; right:10px; font-size:0.7rem; font-weight:900; color:var(--primary); display:none; }

        .quantum-chamber { width:300px; height:300px; position:relative; display:flex; align-items:center; justify-content:center; }
        .containment-field { width:220px; height:220px; background:radial-gradient(circle, rgba(0,242,254,0.1) 0%, rgba(0,0,0,0.4) 100%); border-radius:50%; border:2px solid var(--primary); box-shadow:0 0 30px rgba(0,242,254,0.2); display:grid; grid-template-columns: repeat(2, 45px); grid-template-rows: repeat(2, 45px); align-content:center; justify-content:center; gap:20px; position:relative; }
        .vacuum-gate { width:240px; height:240px; background:rgba(0,0,0,0.6); border-radius:50%; position:absolute; z-index:20; border:2px solid #333; backdrop-filter:blur(5px); display:flex; align-items:center; justify-content:center; box-shadow:0 0 50px rgba(0,0,0,0.8); }
        .vacuum-gate::after { content:'⚡'; font-size:60px; color:var(--primary); text-shadow:0 0 20px var(--primary); opacity:0.3; }
        
        .orb { width:45px; height:45px; border-radius:50%; position:relative; box-shadow:0 0 15px rgba(0,0,0,0.5); transition:0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275); flex-shrink: 0; }
        .orb.negative { background: #007bff; box-shadow: 0 0 15px #007bff; border: 3px solid #fff; }
        .orb.positive { background: #ff4757; box-shadow: 0 0 15px #ff4757; border: 3px solid #fff; }

        .data-stream { display:grid; grid-template-columns: repeat(20, 1fr); gap:4px; background:rgba(0,0,0,0.4); padding:10px; border-radius:1rem; margin-top:15px; width:100%; height:80px; align-content:start; overflow:hidden; border:2px solid rgba(255,255,255,0.1); }
        .stream-bit { width:12px; height:12px; border-radius:2px; }
        .stream-bit.stable { background: #007bff; box-shadow: 0 0 8px #007bff; }
        .stream-bit.volatile { background: #ff4757; box-shadow: 0 0 8px #ff4757; }

        .energy-chips { display:flex; justify-content:center; gap:8px; margin-bottom:15px; }
        .chip { width:40px; height:40px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); display:flex; align-items:center; justify-content:center; font-weight:900; font-size:0.6rem; cursor:pointer; transition:0.3s; font-family:'Orbitron'; background:rgba(255,255,255,0.05); color:#fff; }
        .chip:hover, .chip.active { transform:translateY(-5px); border-color:#ff4757; background:rgba(255,71,87,0.2); box-shadow:0 5px 15px rgba(255,71,87,0.3); }

        .btn-pulse { padding:1.2rem; border-radius:1.5rem; border:none; font-weight:900; font-size:1.3rem; cursor:pointer; transition:0.3s; text-transform:uppercase; background:linear-gradient(135deg, #007bff, #ff4757); color:#fff; box-shadow:0 10px 30px rgba(255,71,87,0.3); width:100%; font-family:'Orbitron'; }
        .btn-pulse:hover:not(:disabled) { transform:translateY(-3px); filter:brightness(1.1); }
        .btn-pulse:disabled { opacity:0.5; cursor:not-allowed; }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="glass-card">
            <div class="sidebar">
                <div>
                    <h1 style="margin:0; font-size: 2rem; font-weight: 900; color: var(--primary); font-family: 'Orbitron'; letter-spacing: 2px;">QUANTUM PULSE</h1>
                    <p style="margin:0; opacity:0.4; font-size: 0.7rem; letter-spacing: 1px;">Particle Synchronization Lab</p>
                </div>

                <div class="stat-card">
                    <span>NĂNG LƯỢNG KHẢ DỤNG</span>
                    <div style="display:flex; align-items:baseline; justify-content:center; gap:5px;">
                        <b id="userMoney"><?= number_format($money, 0, ',', '.') ?></b>
                        <small style="opacity:0.5; font-weight:900; font-size:0.6rem;">UNIT</small>
                    </div>
                </div>

                <div class="data-stream" id="dataStream"></div>

                <div style="margin-top:auto;">
                    <div class="stat-card" style="margin-bottom:10px; padding:0.8rem;">
                        <span>MỨC NẠP XUNG (CHARGE)</span>
                        <input type="number" id="customCharge" value="1000" min="1000" step="1000" 
                               style="background:none; border:none; color:var(--primary); font-family:'Orbitron'; font-size:1.2rem; font-weight:900; width:100%; text-align:center; outline:none;">
                    </div>
                    <div class="energy-chips">
                        <div class="chip active" data-val="1000">1K</div>
                        <div class="chip" data-val="5000">5K</div>
                        <div class="chip" data-val="10000">10K</div>
                        <div class="chip" data-val="50000">50K</div>
                        <div class="chip" data-val="100000">100K</div>
                    </div>
                    <button id="pulseBtn" class="btn-pulse" onclick="triggerPulse()">⚡ KÍCH XUNG</button>
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="../index.php" style="color: #fff; text-decoration: none; font-size: 0.7rem; opacity: 0.3;">← Quay về Dashboard</a>
                    </div>
                </div>
            </div>

            <div class="chamber-area">
                <div class="quantum-chamber">
                    <div class="containment-field" id="containmentField">
                        <div class="orb negative"></div>
                        <div class="orb positive"></div>
                        <div class="orb negative"></div>
                        <div class="orb positive"></div>
                    </div>
                    <div class="vacuum-gate" id="vacuumGate"></div>
                </div>

                <div class="sync-board">
                    <div class="sync-option" data-choice="Stable" onclick="placeCharge('Stable')">
                        <b>STABLE</b> <span>Cân bằng (x1.96)</span>
                        <div class="charge-amt" id="charge-Stable">0</div>
                    </div>
                    <div class="sync-option" data-choice="Volatile" onclick="placeCharge('Volatile')">
                        <b>VOLATILE</b> <span>Biến động (x1.96)</span>
                        <div class="charge-amt" id="charge-Volatile">0</div>
                    </div>
                    <div class="sync-option" data-choice="Full Negative" onclick="placeCharge('Full Negative')">
                        <b>MAX NEGATIVE</b> <span>4 Âm (x12)</span>
                        <div class="charge-amt" id="charge-Full-Negative">0</div>
                    </div>
                    <div class="sync-option" data-choice="Full Positive" onclick="placeCharge('Full Positive')">
                        <b>MAX POSITIVE</b> <span>4 Dương (x12)</span>
                        <div class="charge-amt" id="charge-Full-Positive">0</div>
                    </div>
                    <div class="sync-option" data-choice="Triple Negative" onclick="placeCharge('Triple Negative')">
                        <b>3 NEGATIVE</b> <span>3 Âm 1 Dương (x3.5)</span>
                        <div class="charge-amt" id="charge-Triple-Negative">0</div>
                    </div>
                    <div class="sync-option" data-choice="Triple Positive" onclick="placeCharge('Triple Positive')">
                        <b>3 POSITIVE</b> <span>3 Dương 1 Âm (x3.5)</span>
                        <div class="charge-amt" id="charge-Triple-Positive">0</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let myCharges = {};
        let isSyncing = false;
        let streamData = [];

        $('.chip').click(function() {
            $('.chip').removeClass('active');
            $(this).addClass('active');
            $('#customCharge').val($(this).data('val'));
        });

        function placeCharge(choice) {
            if (isSyncing) return;
            const amt = parseInt($('#customCharge').val()) || 0;
            if (amt <= 0) return;
            myCharges[choice] = (myCharges[choice] || 0) + amt;
            updateUI();
        }

        function updateUI() {
            $('.sync-option').removeClass('active');
            $('.charge-amt').hide();
            for (let c in myCharges) {
                if (myCharges[c] > 0) {
                    $(`.sync-option[data-choice="${c}"]`).addClass('active');
                    $(`#charge-${c.replace(/ /g, '-')}`).text(myCharges[c].toLocaleString('vi-VN')).show();
                }
            }
        }

        function triggerPulse() {
            const chargesData = [];
            for (let c in myCharges) { if (myCharges[c] > 0) chargesData.push({ choice: c, amount: myCharges[c] }); }
            if (chargesData.length === 0) return Swal.fire('Hệ thống', 'Vui lòng nạp năng lượng dự báo!', 'warning');
            if (isSyncing) return;

            isSyncing = true;
            $('#pulseBtn').prop('disabled', true).text('ĐANG ĐỒNG BỘ...');
            
            const gate = $('#vacuumGate');
            const tl = gsap.timeline();
            
            tl.to(gate, { y: 0, opacity: 1, duration: 0.3 })
              .to(gate, { x: "random(-10, 10)", y: "random(-5, 5)", duration: 0.1, repeat: 20, yoyo: true });

            $.post('xocdia.php?action=play', { bets: JSON.stringify(chargesData) }, function(res) {
                if (res.success) {
                    setTimeout(() => {
                        gsap.to(gate, { y: -300, opacity: 0, duration: 0.8, ease: "power2.inOut" });
                        
                        $('#containmentField').empty();
                        res.orbs.forEach((o, i) => {
                            const orb = $(`<div class="orb ${o === 1 ? 'positive' : 'negative'}"></div>`).appendTo('#containmentField');
                            // Hạt xuất hiện tại chỗ, nhanh và đều
                            gsap.from(orb, { 
                                scale: 0.5, 
                                opacity: 0, 
                                duration: 0.4, 
                                ease: "back.out(1.7)",
                                delay: i * 0.05 
                            });
                        });

                        setTimeout(() => {
                            $('#userMoney').text(res.money);
                            updateStream(res.state);
                            if (res.win) {
                                if (window.GameEffects) window.GameEffects.showWin(parseInt(res.rewardAmount.replace(/\./g,'')));
                                Swal.fire({ title: 'ĐỒNG BỘ THÀNH CÔNG!', html: `Năng lượng thu hồi: <b style="color:#00f2fe">${res.rewardAmount} UNIT</b>`, icon: 'success', background: '#1a1a1a', color: '#fff', timer: 2000, showConfirmButton: false });
                            } else {
                                if (window.GameEffects) window.GameEffects.showLoss(0);
                            }
                            
                            setTimeout(() => {
                                gsap.to(gate, { y: 0, opacity: 1, duration: 0.5 });
                                $('#pulseBtn').prop('disabled', false).text('⚡ KÍCH XUNG');
                                isSyncing = false;
                                myCharges = {};
                                updateUI();
                            }, 2500);
                        }, 1000);
                    }, 2000);
                } else {
                    Swal.fire('Lỗi', res.message, 'error');
                    isSyncing = false;
                    $('#pulseBtn').prop('disabled', false).text('⚡ KÍCH XUNG');
                }
            });
        }

        function updateStream(state) {
            streamData.push(state.toLowerCase());
            if (streamData.length > 20) streamData.shift();
            const stream = $('#dataStream').empty();
            streamData.forEach(type => {
                $(`<div class="stream-bit ${type}"></div>`).appendTo(stream);
            });
        }

        $(document).ready(() => {
            for(let i=0; i<15; i++) updateStream(Math.random() > 0.5 ? 'Stable' : 'Volatile');
        });
    </script>

    <canvas id="threejs-background"></canvas>
    <script>
        (function() {
            window.themeConfig = {
                particleCount: <?= $particleCount ?>,
                particleSize: <?= $particleSize ?>,
                particleColor: '<?= $particleColor ?>',
                bgGradient: <?= json_encode($bgGradient) ?>
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