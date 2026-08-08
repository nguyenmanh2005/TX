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
    <title>Mỏ Khoáng Tycoon & PVP</title>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <?php echo getCSSIncludes(['special_effects' => true]); ?>
    <link rel="stylesheet" href="../assets/css/game-mining.css?v=<?= time() ?>">
    
    <style>
        body {
            margin: 0;
            background: <?= $bgGradientCSS ?? 'linear-gradient(135deg, #0f0c29, #302b63, #24243e)' ?>;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Inter', sans-serif;
            cursor: url('../img/chuot.png'), auto !important;
        }
    </style>
</head>
<body>
    <div class="mining-container">
        
        <div class="mining-header">
            <div>
                <h1 class="mining-title">TYCOON MỎ KHOÁNG</h1>
                <p class="mining-subtitle">Khai thác tự động, nhặt nguyên liệu & phòng thủ Cướp mỏ!</p>
            </div>
            <div class="wallet-display">
                <span>Số dư:</span>
                <span id="userMoney" class="money-val"><?= number_format($money, 0, ',', '.') ?></span> GTLM
            </div>
        </div>

        <div class="mining-tabs">
            <button class="tab-btn active" data-target="myMine">KHU MỎ CỦA TÔI</button>
            <button class="tab-btn" data-target="raidPvp">CƯỚP MỎ (PVP)</button>
        </div>

        <!-- MỎ CỦA TÔI -->
        <div id="myMine" class="tab-content active">
            <!-- Tổng quan -->
            <div class="summary-card">
                <div class="summary-info">
                    <h2>Thu Nhập Tổng</h2>
                    <div class="total-rate">⚡ <span id="totalRate">0</span> GTLM / giờ</div>
                    <div style="font-size: 0.8rem; opacity: 0.7; margin-top: 5px;">Mẹo: Bạn có tỉ lệ nhận nguyên liệu Crafting khi thu hoạch!</div>
                </div>
                <div class="claim-action">
                    <div class="accumulated-display">
                        +<span id="totalAccumulatedVal">0</span>
                    </div>
                    <button id="btnClaimAll" class="btn-claim">THU HOẠCH TẤT CẢ</button>
                </div>
            </div>

            <!-- Bảo Vệ Mỏ -->
            <div class="guard-card">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div class="guard-icon"><i class="fas fa-dog"></i></div>
                    <div>
                        <h3 style="margin: 0; color: #f1c40f;">Chó Canh Gác</h3>
                        <p style="margin: 5px 0 0 0; font-size: 0.8rem; opacity: 0.7;" id="guardStatus">Chưa thuê. Bạn có thể bị cướp 15% quỹ nếu AFK quá 24h!</p>
                    </div>
                </div>
                <div>
                    <button id="btnBuyGuard" class="btn-guard">Thuê (500K / 24h)</button>
                </div>
            </div>

            <!-- Nâng Cấp Tính Năng -->
            <h3 class="panel-title" style="margin-top: 2rem;">Bổ Trợ & Nâng Cấp</h3>
            <div class="features-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                
                <!-- Card Kho -->
                <div class="guard-card" style="margin:0; background: rgba(52, 152, 219, 0.1); border-color: #3498db;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div class="guard-icon" style="color: #3498db;"><i class="fas fa-warehouse"></i></div>
                        <div>
                            <h3 style="margin: 0; color: #3498db;">Kho Chứa: Cấp <span id="storageLvlTxt">1</span></h3>
                            <p style="margin: 5px 0 0 0; font-size: 0.8rem; opacity: 0.7;">Sức chứa: <b id="storageMaxTxt">24 Giờ</b> AFK</p>
                        </div>
                    </div>
                    <div>
                        <button id="btnUpgradeStorage" class="btn-guard" style="background: #3498db;">Nâng Cấp</button>
                    </div>
                </div>

                <!-- Card Thuốc Tăng Lực -->
                <div class="guard-card" style="margin:0; background: rgba(155, 89, 182, 0.1); border-color: #9b59b6;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div class="guard-icon" style="color: #9b59b6;"><i class="fas fa-flask"></i></div>
                        <div>
                            <h3 style="margin: 0; color: #9b59b6;">Nước Tăng Lực X2</h3>
                            <p style="margin: 5px 0 0 0; font-size: 0.8rem; opacity: 0.7;" id="boostStatusTxt">Tốc độ x2 trong 12 Giờ</p>
                        </div>
                    </div>
                    <div>
                        <button id="btnBuyBoost" class="btn-guard" style="background: #9b59b6;">Mua (10M)</button>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--mining-border); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                <h3 class="panel-title" style="margin: 0; border: none; padding: 0;">Các Khe Khai Thác (Slots)</h3>
                <div class="upgrade-multiplier">
                    <span style="font-size: 0.9rem; margin-right: 10px;">Nâng cấp:</span>
                    <button class="btn-multi active" data-val="1">x1</button>
                    <button class="btn-multi" data-val="10">x10</button>
                    <button class="btn-multi" data-val="max">MAX</button>
                </div>
            </div>
            
            <div class="slots-grid" id="slotsGrid">
                <!-- Danh sách 5 slots sẽ được sinh bằng JS -->
            </div>
        </div>

        <!-- CƯỚP MỎ PVP -->
        <div id="raidPvp" class="tab-content">
            <div class="raid-header">
                <h2>🎯 BẢNG TRUY NÃ MỎ SƠ HỞ</h2>
                <p>Những người chơi sau đã AFK quá 24 tiếng. Hãy cướp 15% số GTLM của họ trước khi họ kịp thu hoạch!</p>
                <button class="btn-refresh" onclick="loadVulnerableList()"><i class="fas fa-sync-alt"></i> Làm Mới</button>
            </div>
            
            <div id="raidList" class="raid-list">
                <!-- Danh sách người bị cướp sẽ load từ JS -->
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 2rem;">
            <a href="../index.php" style="color: rgba(255,255,255,0.4); text-decoration: none; font-size: 0.9rem;">← Quay lại Dashboard</a>
        </div>
    </div>

    <!-- Background Effects -->
    <canvas id="threejs-background" style="position:fixed; top:0; left:0; width:100%; height:100%; z-index:-1; pointer-events:none; opacity: 0.5;"></canvas>
    
    <script>
        window.USER_NAME = "<?= $userName ?>";
    </script>
    <script src="../assets/js/game-mining.js?v=<?= time() ?>"></script>
    <script>
        (function () {
            window.themeConfig = {
                particleCount: 200,
                particleSize: 0.08,
                particleColor: '#f1c40f', // Gold particles
                particleOpacity: 0.6,
                bgGradient: ["#1a1a1d", "#4e4e50", "#1a1a1d"]
            };
            const prefix = '../';
            ['threejs-background.js'].forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src; s.async = false;
                document.head.appendChild(s);
            });
        })();
    </script>
</body>
</html>
