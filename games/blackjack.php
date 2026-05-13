<?php
session_start();
require_once '../db_connect.php';
require_once '../include_css.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['Iduser'];
require_once '../load_theme.php'; // Nạp thông số theme

/** @var int $particleCount */
/** @var float $particleSize */
/** @var string $particleColor */
/** @var float $particleOpacity */
/** @var int $shapeCount */
/** @var array $shapeColors */
/** @var float $shapeOpacity */
/** @var array $bgGradient */
/** @var string $bgGradientCSS */

// Lấy thông tin người chơi để hiển thị Gtlm
$sql = "SELECT * FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$gameTitle = "Xì Dách Royale";
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $gameTitle ?> - Sòng Bài Hoàng Gia</title>
    <link rel="stylesheet" href="../assets/css/blackjack.css">

    <!-- Theme Config for Three.js Background -->
    <script>
        window.themeConfig = {
            particleCount: <?= $particleCount ?>,
            particleSize: <?= $particleSize ?>,
            particleColor: '<?= $particleColor ?>',
            particleOpacity: <?= $particleOpacity ?>,
            shapeCount: <?= $shapeCount ?>,
            shapeColors: <?= json_encode($shapeColors) ?>,
            shapeOpacity: <?= $shapeOpacity ?>,
            bgGradient: <?= json_encode($bgGradient) ?>
        };
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.2/dist/gsap.min.js"></script>
    <style>
        html,
        body.blackjack-theme {
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            cursor: url('../chuot.png'), auto !important;
        }

        * {
            cursor: inherit !important;
        }

        button,
        a,
        .chip,
        input {
            cursor: url('../img/tay.png'), pointer !important;
        }
    </style>
</head>

<body class="game-page blackjack-theme">
    <!-- Canvas nền Three.js của hệ thống -->
    <canvas id="threejs-background"></canvas>

    <div class="game-container">
        <!-- Header -->
        <div class="game-header">
            <a href="../index.php" class="back-btn">← Trang chủ</a>
            <div class="game-info">
                <h1><?= $gameTitle ?></h1>
                <div class="balance-box">
                    <span>Ngân khố:</span>
                    <strong id="userBalance"><?= number_format($user['Money'], 0, ',', '.') ?></strong> GTLM
                </div>
            </div>
            <button id="guideBtn" class="help-btn">?</button>
        </div>

        <!-- 3D Canvas -->
        <div id="blackjack-canvas"></div>

        <!-- UI Overlays -->
        <div class="game-ui">
            <div class="score-display">
                <div class="score-badge player-score">CHALLENGER: <span id="playerScore">0</span></div>
                <div class="score-badge king-score">THE KING: <span id="kingScore">?</span></div>
            </div>

            <div id="resultAnnounce" class="result-announcement"></div>

            <!-- Controls Area -->
            <div class="controls-area" style="padding-bottom: 60px;">
                <div class="chip-selector">
                    <div class="chip" data-value="1000">1K</div>
                    <div class="chip active" data-value="5000">5K</div>
                    <div class="chip" data-value="10000">10K</div>
                    <div class="chip" data-value="50000">50K</div>
                    <div class="chip" data-value="100000">100K</div>
                    <div class="custom-bet-box">
                        <input type="number" id="customBetInput" placeholder="Cược khác..." min="1000" step="1000">
                    </div>
                </div>

                <div class="bet-info">
                    <span>Mức thách đấu: <strong id="currentBetDisplay">5.000</strong> GTLM</span>
                </div>

                <div class="action-buttons">
                    <button id="dealBtn" class="btn btn-primary">KHAI CUỘC</button>
                    <div id="gameActions" class="sub-actions" style="display: none;">
                        <button id="hitBtn" class="btn btn-hit">RÚT THÊM</button>
                        <button id="standBtn" class="btn btn-stand">DẰN BÀI</button>
                        <button id="doubleBtn" class="btn btn-double">GẤP ĐÔI</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Guide Modal -->
        <div id="guideModal" class="guide-modal">
            <div class="guide-content">
                <span class="close-guide">&times;</span>
                <h2>📖 BÍ KÍP XÌ DÁCH ROYALE</h2>
                <div class="guide-sections">
                    <section>
                        <h3>✨ Mục tiêu</h3>
                        <p>Đạt tổng điểm gần <strong>21</strong> nhất nhưng không được vượt quá. Bạn cần cao điểm hơn
                            <strong>THE KING</strong> để thắng.</p>
                    </section>
                    <section>
                        <h3>🃏 Cách tính điểm</h3>
                        <ul>
                            <li>Lá 2-10: Tính theo mặt số.</li>
                            <li>Lá J, Q, K: Tính là 10 điểm.</li>
                            <li>Lá A: Tính linh hoạt là 1 hoặc 11 điểm.</li>
                        </ul>
                    </section>
                    <section>
                        <h3>🛠️ Hành động</h3>
                        <ul>
                            <li><strong>RÚT THÊM</strong>: Nhận thêm 1 lá bài.</li>
                            <li><strong>DẰN BÀI</strong>: Giữ nguyên điểm và so bài.</li>
                            <li><strong>GẤP ĐÔI</strong>: Tăng gấp đôi Gtlm cược và chỉ được rút thêm đúng 1 lá.</li>
                        </ul>
                    </section>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/blackjack-3d.js?v=<?= time() ?>"></script>
    <script src="../assets/js/blackjack-logic.js?v=<?= time() ?>"></script>

    <!-- Premium Effects Loader -->
    <script>
        (function () {
            const prefix = '../';
            const scripts = ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'];
            scripts.forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();
    </script>
</body>

</html>