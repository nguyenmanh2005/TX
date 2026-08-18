<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_baccarat', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require_once '../db_connect.php';
require_once '../include_css.php';

// Kiểm tra đăng nhập


$userId = $botUserId;
$sql = "SELECT * FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Cài đặt game
$gameTitle = "Baccarat Royale";
$gameId = "baccarat";
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $gameTitle ?> - Casino Royale</title>
    <?= getCSSIncludes(['game_effects' => true]) ?>
    <link rel="stylesheet" href="../assets/css/baccarat.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.2/dist/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="game-page baccarat-theme">
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
            <button id="guideBtn" class="help-btn" title="Hướng dẫn cách chơi">?</button>
        </div>

        <!-- Main Game Area -->
        <div class="baccarat-world">
            <div id="threejs-canvas"></div>
            
            <!-- Game UI Overlay -->
            <div class="baccarat-ui">
                <!-- Result Display -->
                <div class="result-announcement" id="resultAnnounce"></div>

                <!-- Cards Display Area -->
                <div class="cards-layout">
                    <div class="card-zone player-zone">
                        <h3>👸 QUEEN</h3>
                        <div class="score-badge" id="playerScore">0</div>
                        <div class="cards-row" id="playerCards"></div>
                    </div>
                    <div class="card-zone banker-zone">
                        <h3>👑 KING</h3>
                        <div class="score-badge" id="bankerScore">0</div>
                        <div class="cards-row" id="bankerCards"></div>
                    </div>
                </div>

                <!-- Betting Area -->
                <div class="betting-board">
                    <div class="bet-option player" id="box-player" data-type="player" onclick="selectBetBox('player')">
                        <span class="label">QUEEN</span>
                        <span class="payout">Reward 1:1</span>
                        <div class="bet-amount" id="betPlayer">0</div>
                        <input type="hidden" id="bet-player" value="0">
                    </div>
                    <div class="bet-option tie" id="box-tie" data-type="tie" onclick="selectBetBox('tie')">
                        <span class="label">DRAW</span>
                        <span class="payout">Reward 1:8</span>
                        <div class="bet-amount" id="betTie">0</div>
                        <input type="hidden" id="bet-tie" value="0">
                    </div>
                    <div class="bet-option banker" id="box-banker" data-type="banker" onclick="selectBetBox('banker')">
                        <span class="label">KING</span>
                        <span class="payout">Reward 1:1</span>
                        <div class="bet-amount" id="betBanker">0</div>
                        <input type="hidden" id="bet-banker" value="0">
                    </div>
                </div>

                <!-- Control Panel -->
                <div class="controls">
                    <p style="margin-bottom: 0; opacity: 0.8; font-size: 0.9rem;">CHỌN Ô CƯỢC SAU ĐÓ CHỌN VÀNG ĐỂ ĐẶT GTLM</p>
                    <div class="chip-selector" id="chipSelector">
                        <div class="chip" data-value="1000">1K</div>
                        <div class="chip" data-value="5000">5K</div>
                        <div class="chip" data-value="10000">10K</div>
                        <div class="chip" data-value="50000">50K</div>
                        <div class="chip" data-value="100000">100K</div>
                        <div class="chip" data-value="500000">500K</div>
                        <div class="chip" data-value="1000000">1M</div>
                        <div class="chip" data-value="5000000">5M</div>
                        <div class="chip" data-value="0" style="background: #333; font-size: 0.8rem;">XÓA</div>
                    </div>
                    <div class="action-btns" style="margin-top: 10px;">
                        <button id="dealBtn" class="btn btn-success">KHAI CUỘC</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Roadmap / History -->
        <div class="baccarat-roadmap">
            <div class="roadmap-header">LỊCH SỬ THÁCH ĐẤU</div>
            <div id="beadPlate" class="roadmap-grid"></div>
        </div>
    </div>

    <!-- Royale Guide Modal -->
    <div id="guideModal" class="guide-modal">
        <div class="guide-content">
            <span class="close-guide">&times;</span>
            <h2>📖 BÍ KÍP HOÀNG GIA (BACCARAT ROYALE)</h2>
            
            <div class="guide-sections">
                <section>
                    <h3>✨ Mục tiêu</h3>
                    <p>Dự đoán xem bên nào có tổng điểm gần 9 nhất: <strong>KING</strong>, <strong>QUEEN</strong> hoặc <strong>DRAW</strong>.</p>
                </section>

                <section>
                    <h3>🃏 Cách tính điểm</h3>
                    <ul>
                        <li><strong>Lá 2-9</strong>: Tính theo mặt số.</li>
                        <li><strong>Lá 10, J, Q, K</strong>: Tính là 0 điểm.</li>
                        <li><strong>Lá A</strong>: Tính là 1 điểm.</li>
                        <li><em>Lưu ý: Chỉ lấy số hàng đơn vị (ví dụ: 15 điểm tính là 5).</em></li>
                    </ul>
                </section>

                <section>
                    <h3>🎁 Phần thưởng (Reward)</h3>
                    <div class="reward-table">
                        <div class="reward-row"><span>Dự đoán QUEEN</span> <span>Thưởng 1 : 1</span></div>
                        <div class="reward-row"><span>Dự đoán KING</span> <span>Thưởng 1 : 1</span></div>
                        <div class="reward-row"><span>Dự đoán DRAW</span> <span>Thưởng 1 : 8</span></div>
                    </div>
                </section>

                <section>
                    <h3>🛠️ Cách thực hiện</h3>
                    <ol>
                        <li>Chọn mệnh giá <strong>Vàng (Chip)</strong>.</li>
                        <li>Đặt vào ô <strong>QUEEN</strong>, <strong>KING</strong> hoặc <strong>DRAW</strong>.</li>
                        <li>Nhấn <strong>KHAI CUỘC</strong> để bắt đầu ván đấu.</li>
                    </ol>
                </section>
            </div>
            
            <div class="guide-footer">
                <p>Hệ thống tự động rút thêm lá thứ 3 theo luật Baccarat quốc tế.</p>
            </div>
        </div>
    </div>

    <script src="../assets/js/baccarat-3d.js?v=<?= time() ?>"></script>
    <script src="../assets/js/baccarat-logic.js?v=<?= time() ?>"></script>

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
    <style>
        .bet-option.focused {
            border-color: #d4af37 !important;
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.5);
            background: rgba(255, 255, 255, 0.15) !important;
            transform: translateY(-5px);
        }
    </style>

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script src="../assets/js/bots/bot_baccarat.js?v=<?= time() ?>"></script>
</body>
</html>