<?php
/**
 * 🎰 Combo Bet Portal v1.0 - High-Risk Triple Threat
 * Place concurrent bets on Crash, Sicbo, and Baccarat to trigger x5 payouts on sweeps.
 */
session_start();
require_once 'db_connect.php';
require_once 'load_theme.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
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
    <title>Combo Bet Slip | Vegas Royale Premium</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/game-effects.css">
    <style>
        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .back-home-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 999;
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            text-decoration: none;
            font-family: 'Orbitron', sans-serif;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            text-transform: uppercase;
        }

        .back-home-btn:hover {
            background: rgba(99, 102, 241, 0.2);
            border-color: #6366f1;
            color: #a5b4fc;
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.4);
            transform: translateX(5px);
        }

        .combo-card {
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 30px;
            padding: 2.5rem;
            box-shadow: 0 30px 80px rgba(0,0,0,0.6);
            width: 100%;
            max-width: 1000px;
            text-align: center;
        }

        .combo-header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, #f59e0b, #ec4899, #3b82f6);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0 0 10px;
            text-shadow: 0 0 30px rgba(236, 72, 153, 0.2);
        }

        .game-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin: 2rem 0;
        }

        .game-selector {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .game-selector:hover {
            border-color: rgba(99, 102, 241, 0.4);
            background: rgba(99, 102, 241, 0.05);
            transform: translateY(-5px);
        }

        .game-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: #6366f1;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 900;
            padding: 3px 8px;
            border-radius: 50px;
            text-transform: uppercase;
        }

        .option-btn {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #fff;
            padding: 10px;
            border-radius: 10px;
            margin-top: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            font-size: 0.85rem;
        }

        .option-btn.active {
            background: linear-gradient(135deg, #6366f1, #3b82f6);
            border-color: #818cf8;
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.4);
        }

        .btn-bet-combo {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: #000;
            border: none;
            padding: 18px 40px;
            border-radius: 50px;
            font-weight: 900;
            font-size: 1.3rem;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(245, 158, 11, 0.4);
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-family: 'Orbitron', sans-serif;
            width: 100%;
            max-width: 400px;
            margin-top: 1.5rem;
        }

        .btn-bet-combo:hover {
            transform: scale(1.03);
            box-shadow: 0 15px 40px rgba(245, 158, 11, 0.6);
        }

        .bet-input-box {
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 15px;
            padding: 15px;
            max-width: 400px;
            margin: 0 auto;
        }

        .bet-input-box label {
            display: block;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin-bottom: 5px;
            font-weight: 800;
        }

        .bet-input-box input {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            font-weight: 900;
            width: 100%;
            text-align: center;
            outline: none;
            font-family: 'Orbitron';
        }
    </style>
</head>
<body>
    <a href="index.php" class="back-home-btn">
        <i class="fas fa-th-large"></i> Dashboard
    </a>

    <div class="combo-card">
        <div class="combo-header">
            <h1>COMBO BET PORTAL</h1>
            <p style="color:#adadb8; font-size:0.9rem;">Đặt cùng lúc 3 game: Crash, Sicbo & Baccarat. Cả 3 thắng <b>Nhân x5 Payout</b>! 🔥</p>
        </div>

        <div class="game-grid">
            <!-- Game 1: Crash -->
            <div class="game-selector">
                <span class="game-badge">CRASH</span>
                <i class="fa fa-rocket" style="font-size:3rem; margin: 15px 0 10px; color:#f43f5e;"></i>
                <h3 style="margin: 0 0 10px; font-family:'Orbitron';">Chọn Rút Cược</h3>
                <p style="font-size:0.75rem; color:#adadb8; margin:0 0 15px;">Chọn điểm rút cược an toàn cho chuyến bay.</p>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <button class="option-btn active" onclick="setCrashTarget(1.50)">Rút Cược x1.50</button>
                    <button class="option-btn" onclick="setCrashTarget(2.00)">Rút Cược x2.00</button>
                    <button class="option-btn" onclick="setCrashTarget(3.00)">Rút Cược x3.00</button>
                </div>
            </div>

            <!-- Game 2: Sicbo -->
            <div class="game-selector">
                <span class="game-badge" style="background:#fbbf24; color:#000;">SICBO</span>
                <i class="fa fa-dice" style="font-size:3rem; margin: 15px 0 10px; color:#fbbf24;"></i>
                <h3 style="margin: 0 0 10px; font-family:'Orbitron';">Chọn Cửa Đặt</h3>
                <p style="font-size:0.75rem; color:#adadb8; margin:0 0 15px;">Dự đoán kết quả lắc 3 viên xúc xắc cổ xưa.</p>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <button class="option-btn active" onclick="setSicboChoice('ac_quy', this)">Ác Quỷ (Xỉu)</button>
                    <button class="option-btn" onclick="setSicboChoice('thien_than', this)">Thiên Thần (Tài)</button>
                    <button class="option-btn" onclick="setSicboChoice('le', this)">Cửa Lẻ</button>
                    <button class="option-btn" onclick="setSicboChoice('chan', this)">Cửa Chẵn</button>
                </div>
            </div>

            <!-- Game 3: Baccarat -->
            <div class="game-selector">
                <span class="game-badge" style="background:#10b981;">BACCARAT</span>
                <i class="fa fa-gem" style="font-size:3rem; margin: 15px 0 10px; color:#10b981;"></i>
                <h3 style="margin: 0 0 10px; font-family:'Orbitron';">Chọn Cửa Baccarat</h3>
                <p style="font-size:0.75rem; color:#adadb8; margin:0 0 15px;">So sánh điểm bài cào Tây giữa hai cửa lớn.</p>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <button class="option-btn active" onclick="setBaccaratChoice('player', this)">Player (Con)</button>
                    <button class="option-btn" onclick="setBaccaratChoice('banker', this)">Banker (Cái)</button>
                    <button class="option-btn" onclick="setBaccaratChoice('tie', this)">Tie (Hòa)</button>
                </div>
            </div>
        </div>

        <div class="bet-input-box">
            <label>Liều lượng cược (GTLM)</label>
            <input type="number" id="comboBetAmount" value="50000" min="1000" step="any">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px; font-size:0.8rem;">
                <span style="color:#94a3b8;">Số dư ví:</span>
                <span id="userMoney" style="font-weight:800; color:#fbbf24;"><?= number_format($money) ?> GTLM</span>
            </div>
        </div>

        <button class="btn-bet-combo" onclick="submitComboBet()">ĐẶT COMBO 🔥</button>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let crashTarget = 1.50;
        let sicboChoice = 'ac_quy';
        let baccaratChoice = 'player';

        function setCrashTarget(val) {
            crashTarget = val;
            const btns = event.target.parentElement.querySelectorAll('.option-btn');
            btns.forEach(b => b.classList.remove('active'));
            event.target.classList.add('active');
        }

        function setSicboChoice(choice, btn) {
            sicboChoice = choice;
            const btns = btn.parentElement.querySelectorAll('.option-btn');
            btns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }

        function setBaccaratChoice(choice, btn) {
            baccaratChoice = choice;
            const btns = btn.parentElement.querySelectorAll('.option-btn');
            btns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }

        function submitComboBet() {
            const bet = $('#comboBetAmount').val();
            if (!bet || bet < 1000) {
                Swal.fire('Lỗi', 'Cược tối thiểu là 1,000 GTLM!', 'warning');
                return;
            }

            Swal.fire({
                title: 'Đang khóa kèo Combo...',
                html: 'Hệ thống đang xoay xúc xắc, phóng phi thuyền và chia bài cào...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.post('api_combo_bet.php', {
                bet_amount: bet,
                crash_target: crashTarget,
                sicbo_choice: sicboChoice,
                baccarat_choice: baccaratChoice
            }, function(res) {
                Swal.close();
                if (res.success) {
                    $('#userMoney').text(res.new_money + ' GTLM');
                    
                    let resultHtml = `
                        <div style="text-align:left; font-size:0.9rem; margin-top:10px; background:rgba(0,0,0,0.3); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                            <div style="margin-bottom:8px; display:flex; justify-content:space-between;">
                                <span>🚀 Crash Point: <b>x${res.crash_point}</b> (Mục tiêu x${crashTarget})</span>
                                <span>${res.game1_won ? '✅ THẮNG' : '❌ THUA'}</span>
                            </div>
                            <div style="margin-bottom:8px; display:flex; justify-content:space-between;">
                                <span>🎲 Xúc xắc: <b>${res.sicbo_dice.join(', ')}</b> (Tổng ${res.sicbo_sum})</span>
                                <span>${res.game2_won ? '✅ THẮNG' : '❌ THUA'}</span>
                            </div>
                            <div style="display:flex; justify-content:space-between;">
                                <span>🎴 Baccarat: Player <b>${res.baccarat_player}</b> vs Banker <b>${res.baccarat_banker}</b></span>
                                <span>${res.game3_won ? '✅ THẮNG' : '❌ THUA'}</span>
                            </div>
                        </div>
                    `;

                    if (res.all_won) {
                        Swal.fire({
                            title: '🎉 TRIPLE THREAT SWEEP!',
                            html: `Chúc mừng bạn húp trọn vẹn cả 3 kèo với <b>Multi x5 CỰC ĐẠI</b>!<br><br>🎁 <b>Húp được: +${res.payout_formatted} GTLM!</b>` + resultHtml,
                            icon: 'success',
                            background: '#0f172a',
                            color: '#fff',
                            confirmButtonColor: '#fbbf24'
                        });
                    } else {
                        Swal.fire({
                            title: '❌ COMBO BỊ XÉ TOẠC!',
                            html: `Rất tiếc! Một hoặc nhiều lựa chọn trong combo đã bay màu.` + resultHtml,
                            icon: 'error',
                            background: '#0f172a',
                            color: '#fff',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                } else {
                    Swal.fire('Lỗi', res.message, 'error');
                }
            }, 'json');
        }
    </script>
</body>
</html>
