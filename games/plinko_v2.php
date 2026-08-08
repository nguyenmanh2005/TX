<?php
session_start();
include '../db_connect.php';
require_once '../include_css.php';
include '../load_theme.php';

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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plinko V2 - Pro Edition</title>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;500;700&display=swap" rel="stylesheet">
    
    <?php echo getCSSIncludes(['special_effects' => true]); ?>
    <link rel="stylesheet" href="../assets/css/game-plinko_v2.css?v=<?= time() ?>">
    
    <style>
        body {
            margin: 0;
            background: <?= $bgGradientCSS ?? 'linear-gradient(135deg, #0f0c29, #302b63, #24243e)' ?>;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Inter', sans-serif;
            overflow: hidden;
            cursor: url('../img/chuot.png'), auto !important;
        }
    </style>
</head>
<body>
    <div class="plinko-v2-container">
        <div class="plinko-card">
            
            <!-- Controls Sidebar -->
            <div class="plinko-controls">
                <div>
                    <h1 style="margin:0; font-family:'Orbitron'; font-size:2.5rem; color:var(--primary); font-weight:900;">PLINKO V2</h1>
                    <p style="margin:0; opacity:0.5; font-size:0.8rem; letter-spacing:1px;">ADVANCED GRAVITY ENGINE</p>
                </div>
                
                <div class="control-group">
                    <label>Mức rủi ro (Risk)</label>
                    <div class="segmented-control" id="riskControl">
                        <button class="segment-btn risk-low" data-val="low">LOW</button>
                        <button class="segment-btn risk-med active" data-val="medium">MEDIUM</button>
                        <button class="segment-btn risk-high" data-val="high">HIGH</button>
                    </div>
                </div>
                
                <div class="control-group">
                    <label>Số hàng đinh (Rows)</label>
                    <div class="segmented-control" id="rowsControl">
                        <button class="segment-btn" data-val="8">8</button>
                        <button class="segment-btn active" data-val="12">12</button>
                        <button class="segment-btn" data-val="16">16</button>
                    </div>
                </div>

                <div class="control-group">
                    <label>GTLM cược / Bóng</label>
                    <div class="control-input">
                        <input type="number" id="betAmount" value="10000" min="1000" step="1000">
                    </div>
                    <div class="quick-amounts">
                        <button class="btn-quick" onclick="setBet(10000)">10K</button>
                        <button class="btn-quick" onclick="setBet(50000)">50K</button>
                        <button class="btn-quick" onclick="setBet(100000)">100K</button>
                        <button class="btn-quick" onclick="setBet(500000)">500K</button>
                        <button class="btn-quick" onclick="setBet(1000000)">1M</button>
                        <button class="btn-quick" onclick="setBet('ALL')">ALL IN</button>
                    </div>
                </div>

                <div class="control-group">
                    <label>Số bóng thả (1-50)</label>
                    <div class="control-input">
                        <input type="number" id="ballCount" value="1" min="1" max="50">
                    </div>
                    <div class="quick-amounts">
                        <button class="btn-quick" onclick="$('#ballCount').val(1)">1</button>
                        <button class="btn-quick" onclick="$('#ballCount').val(10)">10</button>
                        <button class="btn-quick" onclick="$('#ballCount').val(50)">50</button>
                    </div>
                </div>

                <div style="background:rgba(0,0,0,0.4); padding:1rem; border-radius:1rem; border:1px solid rgba(255,255,255,0.05); margin-top: 10px;">
                    <div style="font-size:0.7rem; opacity:0.6; font-weight:700;">TỔNG CƯỢC</div>
                    <div id="totalBetDisplay" style="font-family:'Orbitron'; font-size:1.5rem; font-weight:900; color:#fff;">10.000</div>
                </div>

                <button id="btnPlay" class="btn-play">🟢 THẢ BÓNG</button>
                
                <div style="margin-top:auto; padding-top:1rem; border-top:1px dashed rgba(255,255,255,0.1);">
                    <div style="font-size:0.7rem; opacity:0.6; font-weight:700; margin-bottom:5px;">SỐ DƯ (GTLM)</div>
                    <div id="userMoney" style="font-family:'Orbitron'; font-size:1.8rem; font-weight:900; color:var(--accent);"><?= number_format($money, 0, ',', '.') ?></div>
                    <a href="../index.php" style="color: rgba(255,255,255,0.4); text-decoration: none; font-size: 0.8rem; display:block; margin-top:10px;">← Về Dashboard</a>
                </div>
            </div>

            <!-- Plinko Board -->
            <div class="plinko-board-area">
                <div class="board-stats">
                    <div class="stat-label">LỢI NHUẬN PHIÊN</div>
                    <div class="stat-value" id="sessionProfit">0</div>
                </div>
                
                <div class="canvas-container" id="scaleContainer">
                    <div id="scaleWrapper" style="position: relative; transform-origin: center center; display: flex; justify-content: center;">
                        <canvas id="plinkoCanvas"></canvas>
                        <div class="pockets-wrapper" id="pocketsWrapper" style="position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); display: flex; gap: 4px; z-index: 5;">
                            <!-- Pockets will be generated by JS -->
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <!-- JS Core -->
    <script>
        window.USER_MONEY = <?= $money ?>;
    </script>
    <script src="../assets/js/game-plinko_v2.js?v=<?= time() ?>"></script>
    
    <!-- Background Effects -->
    <canvas id="threejs-background" style="position:fixed; top:0; left:0; width:100%; height:100%; z-index:-1; pointer-events:none;"></canvas>
    <script>
        (function () {
            window.themeConfig = {
                particleCount: 500,
                particleSize: 0.05,
                particleColor: '#12c2e9',
                particleOpacity: 0.4,
                bgGradient: ["#0f0c29", "#302b63", "#24243e"]
            };
            const prefix = '../';
            ['threejs-background.js', 'assets/js/game-effects.js'].forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src; s.async = false;
                document.head.appendChild(s);
            });
        })();
    </script>
</body>
</html>
