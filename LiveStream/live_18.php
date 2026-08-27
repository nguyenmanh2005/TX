<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_18', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require_once '../db_connect.php';

if (!isset($botUserId)) {
    header('Location: ../login.php');
    exit;
}

include '../load_theme.php';

$userId = $botUserId;
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
    <title>Tung Đồng Xu | Lật Kèo Đỉnh Cao</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { margin: 0; padding: 0; font-family: 'Outfit', sans-serif; background: transparent; color: #fff; }
        .game-container { max-width: 600px; margin: 40px auto; padding: 30px; background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(20px); border-radius: 30px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 20px 50px rgba(0,0,0,0.5); text-align: center; }
        .game-title { font-size: 2.5rem; font-weight: 800; background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 5px; }
        .game-subtitle { color: #94a3b8; font-size: 1.1rem; margin-bottom: 30px; }
        .balance-card { background: rgba(15, 23, 42, 0.6); padding: 15px 30px; border-radius: 20px; display: inline-block; margin-bottom: 30px; font-weight: 600; font-size: 1.2rem; border: 1px solid rgba(74, 222, 128, 0.2); }
        .balance-value { color: #4ade80; font-size: 1.4rem; font-weight: 800; }
        
        /* Coin Animation */
        .coin-wrapper { perspective: 1000px; margin: 0 auto 40px auto; width: 150px; height: 150px; }
        .coin { width: 100%; height: 100%; position: relative; transform-style: preserve-3d; transition: transform 3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .coin-face { position: absolute; width: 100%; height: 100%; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 3rem; font-weight: 800; backface-visibility: hidden; box-shadow: inset 0 0 20px rgba(0,0,0,0.5), 0 10px 20px rgba(0,0,0,0.5); border: 8px solid #fbbf24; }
        .coin-heads { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; }
        .coin-tails { background: linear-gradient(135deg, #94a3b8, #64748b); color: #fff; transform: rotateY(180deg); border-color: #cbd5e1; }
        
        .coin.flip-heads { transform: rotateY(1800deg); }
        .coin.flip-tails { transform: rotateY(1980deg); }

        .bet-input-container { margin-bottom: 25px; }
        .bet-input { background: rgba(15, 23, 42, 0.6); border: 2px solid rgba(255,255,255,0.1); color: #fff; padding: 15px; border-radius: 15px; font-size: 1.2rem; width: 250px; text-align: center; font-family: 'Outfit', sans-serif; font-weight: 600; outline: none; transition: all 0.3s ease; }
        .bet-input:focus { border-color: #f59e0b; box-shadow: 0 0 15px rgba(245, 158, 11, 0.3); }
        
        .quick-bet-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-bottom: 30px; }
        .quick-btn { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #e2e8f0; padding: 10px 20px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
        .quick-btn:hover { background: rgba(255,255,255,0.15); transform: translateY(-2px); }
        .quick-btn.active { background: #f59e0b; color: #fff; border-color: #f59e0b; }

        .action-btns { display: flex; justify-content: center; gap: 20px; }
        .action-btn { flex: 1; padding: 20px; border: none; border-radius: 20px; font-size: 1.3rem; font-weight: 800; color: #fff; cursor: pointer; transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; gap: 5px; box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        .action-btn:hover:not(:disabled) { transform: translateY(-5px); box-shadow: 0 15px 25px rgba(0,0,0,0.3); }
        .action-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        
        .btn-heads { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .btn-tails { background: linear-gradient(135deg, #64748b, #475569); }
        .payout-rate { font-size: 0.9rem; font-weight: 400; opacity: 0.9; }

        .back-home { display: inline-block; margin-top: 30px; color: #94a3b8; text-decoration: none; font-weight: 600; transition: color 0.2s; }
        .back-home:hover { color: #fff; }
    </style>
</head>
<body>
    <div class="game-container">
        <div class="game-title">TUNG ĐỒNG XU</div>
        <div class="game-subtitle">Tỷ lệ thắng 50/50 - GTLM thưởng x2</div>

        <div class="balance-card">
            Số dư: <span class="balance-value" id="current-balance"><?= number_format($money, 0, ',', '.') ?></span> đ
        </div>

        <div class="coin-wrapper">
            <div class="coin" id="coin">
                <div class="coin-face coin-heads">SẤP</div>
                <div class="coin-face coin-tails">NGỬA</div>
            </div>
        </div>

        <div class="bet-input-container">
            <input type="number" id="bet-amount" class="bet-input" value="10000" min="1000" placeholder="Nhập số GTLM cược...">
        </div>

        <div class="quick-bet-grid">
            <button class="quick-btn" onclick="setBet(10000)">10K</button>
            <button class="quick-btn" onclick="setBet(50000)">50K</button>
            <button class="quick-btn" onclick="setBet(100000)">100K</button>
            <button class="quick-btn" onclick="setBet(500000)">500K</button>
            <button class="quick-btn" onclick="setBet(1000000)">1M</button>
            <button class="quick-btn" onclick="setBet(5000000)">5M</button>
            <button class="quick-btn" onclick="setBet(<?= $money ?>)">ALL IN</button>
        </div>

        <div class="action-btns">
            <button class="action-btn btn-heads" onclick="playGame('sấp')" id="btn-sap">
                CHỌN SẤP
                <span class="payout-rate">Ăn x2</span>
            </button>
            <button class="action-btn btn-tails" onclick="playGame('ngửa')" id="btn-ngua">
                CHỌN NGỬA
                <span class="payout-rate">Ăn x2</span>
            </button>
        </div>

        <a href="../index.php" class="back-home">🏠 QUAY LẠI TRANG CHỦ</a>
    </div>

    <script>
        function setBet(amount) {
            $('#bet-amount').val(amount);
            $('.quick-btn').removeClass('active');
            event.target.classList.add('active');
        }

        function formatMoney(n) {
            return Number(n).toLocaleString('vi-VN');
        }

        function playGame(choice) {
            const betAmount = parseInt($('#bet-amount').val());
            if (isNaN(betAmount) || betAmount < 1000) {
                Swal.fire('Lỗi', 'Số GTLM cược tối thiểu là 1.000đ', 'error');
                return;
            }

            $('.action-btn').prop('disabled', true);
            const coin = $('#coin');
            
            // Đặt coin về mặc định trước khi quay
            coin.removeClass('flip-heads flip-tails');
            coin.css('transition', 'none');
            coin.css('transform', '');
            
            // Force reflow
            coin[0].offsetHeight; 
            coin.css('transition', '');

            $.post('../api_coinflip.php', { betAmount: betAmount, choice: choice }, function(data) {
                if (!data.success) {
                    Swal.fire('Lỗi', data.message, 'error');
                    $('.action-btn').prop('disabled', false);
                    return;
                }

                // Cập nhật số dư tạm thời (trừ GTLM cược)
                $('#current-balance').text(formatMoney(data.new_balance - (data.is_win ? data.win_amount : 0)));

                // Quay đồng xu
                if (data.result_choice === 'sấp') {
                    coin.addClass('flip-heads');
                } else {
                    coin.addClass('flip-tails');
                }

                // Đợi quay xong mới báo kết quả
                setTimeout(() => {
                    $('#current-balance').text(formatMoney(data.new_balance));
                    
                    if (data.is_win) {
                        Swal.fire({
                            title: 'THẮNG LỚN!',
                            html: `Đồng xu ra <b>${data.result_choice.toUpperCase()}</b><br>Bạn nhận được <b style="color:#4ade80;">+${formatMoney(data.win_amount)}đ</b>`,
                            icon: 'success',
                            background: '#1e293b',
                            color: '#fff'
                        });
                    } else {
                        Swal.fire({
                            title: 'THUA RỒI!',
                            html: `Đồng xu ra <b>${data.result_choice.toUpperCase()}</b><br>Bạn bị mất <b style="color:#ef4444;">-${formatMoney(betAmount)}đ</b>`,
                            icon: 'error',
                            background: '#1e293b',
                            color: '#fff'
                        });
                    }
                    $('.action-btn').prop('disabled', false);
                }, 3000); // Đợi 3s khớp với animation
            }, 'json').fail(function() {
                Swal.fire('Lỗi', 'Lỗi kết nối máy chủ', 'error');
                $('.action-btn').prop('disabled', false);
            });
        }
    </script>

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script src="bots/bot_18.js"></script>

</body>
</html>

