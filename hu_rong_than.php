<?php
session_start();
require_once 'db_connect.php';
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

// Fetch user data
$userId = $_SESSION['Iduser'];
$userStmt = $conn->prepare("SELECT * FROM users WHERE Iduser = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

require_once 'admin_helper.php';
$isAdminUser = isAdmin($conn, $userId);

// Fetch jackpot data
$jackpotStmt = $conn->query("SELECT j.*, u.Name as winner_name FROM global_jackpot j LEFT JOIN users u ON j.last_winner_id = u.Iduser WHERE j.id = 1");
$jackpot = $jackpotStmt->fetch_assoc();

$currentJackpot = $jackpot['amount'] ?? 100000000;
$lastWinner = $jackpot['winner_name'] ?? 'Chưa có';
$lastWinAmount = $jackpot['last_win_amount'] ?? 0;
$lastWinAt = $jackpot['last_win_at'] ?? 'Chưa rõ';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hũ Rồng Thần | GTLM Gaming</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800;900&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --bg-color: #0f172a;
            --primary: #f59e0b;
            --primary-dark: #b45309;
            --glass-bg: rgba(30, 41, 59, 0.7);
            --glass-border: rgba(255, 255, 255, 0.1);
            --text-main: #f8fafc;
            --text-sub: #94a3b8;
        }

        body {
            margin: 0;
            padding: 0;
            background: var(--bg-color);
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(245, 158, 11, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 80% 70%, rgba(220, 38, 38, 0.1) 0%, transparent 40%);
            color: var(--text-main);
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .header-bar {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(12px);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--glass-border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-bar .btn-back {
            color: var(--text-sub);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s;
        }

        .header-bar .btn-back:hover {
            color: var(--primary);
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .dragon-hero {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }

        .dragon-icon {
            font-size: 80px;
            color: var(--primary);
            text-shadow: 0 0 40px rgba(245, 158, 11, 0.6);
            animation: float 4s ease-in-out infinite;
            margin-bottom: 20px;
        }

        .title {
            font-size: 3rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 4px;
            background: linear-gradient(135deg, #fcd34d 0%, #f59e0b 50%, #b45309 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0 0 10px 0;
            filter: drop-shadow(0 4px 10px rgba(0,0,0,0.5));
        }

        .subtitle {
            font-size: 1.1rem;
            color: var(--text-sub);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .jackpot-display {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            border: 2px solid rgba(245, 158, 11, 0.3);
            padding: 40px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5), inset 0 0 40px rgba(245, 158, 11, 0.1);
            position: relative;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .jackpot-display::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url('https://www.transparenttextures.com/patterns/stardust.png');
            opacity: 0.2;
            pointer-events: none;
        }

        .jackpot-display::after {
            content: '';
            position: absolute;
            top: -50%; left: -50%; width: 200%; height: 200%;
            background: conic-gradient(from 0deg, transparent, rgba(245, 158, 11, 0.1), transparent 40%);
            animation: rotate 10s linear infinite;
            pointer-events: none;
        }

        .jackpot-label {
            font-size: 1rem;
            font-weight: 800;
            color: #fbbf24;
            letter-spacing: 4px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .jackpot-amount {
            font-family: 'JetBrains Mono', monospace;
            font-size: clamp(3rem, 6vw, 5rem);
            font-weight: 900;
            color: #ffffff;
            text-shadow: 0 0 20px rgba(245, 158, 11, 0.8), 0 0 40px rgba(245, 158, 11, 0.4);
            margin: 0;
            line-height: 1.2;
            word-break: break-all;
        }

        .jackpot-currency {
            font-size: 1.5rem;
            color: #fbbf24;
            vertical-align: super;
        }

        .rules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .rule-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 25px;
            transition: transform 0.3s, border-color 0.3s;
        }

        .rule-card:hover {
            transform: translateY(-5px);
            border-color: rgba(245, 158, 11, 0.3);
        }

        .rule-icon {
            font-size: 30px;
            color: #fbbf24;
            margin-bottom: 15px;
        }

        .rule-card h3 {
            margin: 0 0 10px 0;
            font-size: 1.2rem;
            color: #fff;
        }

        .rule-card p {
            margin: 0;
            font-size: 0.95rem;
            color: var(--text-sub);
            line-height: 1.5;
        }

        .winner-history {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px;
        }

        .winner-history h3 {
            margin: 0 0 20px 0;
            font-size: 1.4rem;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .winner-card {
            background: rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .winner-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .winner-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            border: 2px solid #fbbf24;
        }

        .winner-details h4 {
            margin: 0 0 5px 0;
            font-size: 1.1rem;
            color: #fff;
        }

        .winner-details p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--text-sub);
        }

        .winner-prize {
            text-align: right;
        }

        .winner-prize .amount {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.5rem;
            font-weight: 800;
            color: #10b981;
        }

        .winner-prize .label {
            font-size: 0.85rem;
            color: var(--text-sub);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 5px;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .play-btn {
            display: block;
            width: fit-content;
            margin: 30px auto 0;
            padding: 15px 40px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #fff;
            text-decoration: none;
            font-size: 1.2rem;
            font-weight: 800;
            border-radius: 99px;
            text-transform: uppercase;
            letter-spacing: 2px;
            box-shadow: 0 10px 20px rgba(217, 119, 6, 0.4);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .play-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 15px 30px rgba(217, 119, 6, 0.6);
        }

        /* Thêm style cho nút Admin */
        .admin-btn {
            background: #1e293b;
            color: #f8fafc;
            border: 1px solid #334155;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            justify-content: center;
            min-width: 140px;
        }
        .admin-btn:hover {
            background: #ef4444;
            border-color: #ef4444;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

    <div class="header-bar">
        <a href="index.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Trở về Sảnh</a>
        <div style="font-weight: 600; color: #fbbf24;">
            <i class="fa-solid fa-coins"></i> <?= number_format($user['Money'], 0, ',', '.') ?> GTLM
        </div>
    </div>

    <div class="container">
        
        <div class="dragon-hero">
            <div class="dragon-icon"><i class="fa-solid fa-dragon"></i></div>
            <h1 class="title">Hũ Rồng Thần</h1>
            <p class="subtitle">Truyền thuyết kể rằng, Long Thần đang say giấc tại trung tâm Trận Địa. Hãy tham gia bất kỳ trò chơi nào để đánh thức ngài và nhận trọn kho báu siêu khổng lồ!</p>
        </div>

        <div class="jackpot-display">
            <div class="jackpot-label">🏆 TỔNG QUỸ HIỆN TẠI 🏆</div>
            <div class="jackpot-amount" id="liveJackpotAmount">
                <?= number_format($currentJackpot, 0, ',', '.') ?><span class="jackpot-currency"> GTLM</span>
            </div>
            <div style="margin-top: 15px; color: rgba(255,255,255,0.5); font-size: 0.9rem;">
                <i class="fa-solid fa-circle-dot fa-fade" style="color: #10b981; font-size: 0.7rem;"></i> Quỹ thưởng đang tăng liên tục theo thời gian thực
            </div>
            
            <?php if ($isAdminUser): ?>
            <div style="margin-top: 25px; background: rgba(0,0,0,0.4); padding: 20px; border-radius: 15px; border: 1px dashed rgba(239, 68, 68, 0.5);">
                <div style="color: #ef4444; font-weight: 900; margin-bottom: 15px; font-size: 1rem; text-transform: uppercase; letter-spacing: 2px;">
                    <i class="fa-solid fa-user-shield"></i> Quyền Lực Admin
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">
                    <button onclick="adminWithdraw('self')" class="admin-btn"><i class="fa-solid fa-hand-holding-dollar"></i> Húp Trọn</button>
                    <button onclick="adminWithdraw('individual')" class="admin-btn"><i class="fa-solid fa-user-tag"></i> Cho Cá Nhân</button>
                    <button onclick="adminWithdraw('group')" class="admin-btn"><i class="fa-solid fa-users"></i> Chia Theo Nhóm</button>
                    <button onclick="adminWithdraw('random')" class="admin-btn"><i class="fa-solid fa-dice"></i> Rải Ngẫu Nhiên</button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="rules-grid">
            <div class="rule-card">
                <div class="rule-icon"><i class="fa-solid fa-coins"></i></div>
                <h3>Tích lũy vô hạn</h3>
                <p>Mỗi khi bất kỳ ai đặt cược tại mọi trò chơi trên server, <strong>0.1%</strong> số tiền cược sẽ được tự động trích và cộng dồn vào quỹ Hũ Rồng Thần.</p>
            </div>
            <div class="rule-card">
                <div class="rule-icon"><i class="fa-solid fa-bolt"></i></div>
                <h3>Tỉ lệ cực hiếm</h3>
                <p>Hũ rớt ngẫu nhiên với tỉ lệ siêu hiếm là <strong>1/10.000</strong> mỗi khi một vé cược được xử lý. Bạn cược càng nhiều ván, cơ hội trúng càng cao!</p>
            </div>
            <div class="rule-card">
                <div class="rule-icon"><i class="fa-solid fa-gift"></i></div>
                <h3>Trúng là giàu to</h3>
                <p>Người chơi may mắn kích hoạt nổ hũ sẽ ẵm trọn 100% số dư hiện có. Sau đó, quỹ sẽ được reset về mức khởi điểm 100.000.000 GTLM.</p>
            </div>
        </div>

        <div class="winner-history">
            <h3><i class="fa-solid fa-crown" style="color: #fbbf24;"></i> Truyền Nhân Rồng Thần Gần Nhất</h3>
            
            <?php if ($lastWinner !== 'Chưa có' && $lastWinAmount > 0): ?>
            <div class="winner-card">
                <div class="winner-info">
                    <div class="winner-avatar"><i class="fa-solid fa-user-astronaut"></i></div>
                    <div class="winner-details">
                        <h4><?= htmlspecialchars($lastWinner) ?></h4>
                        <p><i class="fa-regular fa-clock"></i> Thời gian nổ hũ: <?= date('d/m/Y H:i', strtotime($lastWinAt)) ?></p>
                    </div>
                </div>
                <div class="winner-prize">
                    <div class="amount">+<?= number_format($lastWinAmount, 0, ',', '.') ?> GTLM</div>
                    <div class="label">Phần thưởng</div>
                </div>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 40px 20px; color: var(--text-sub);">
                <i class="fa-solid fa-ghost" style="font-size: 40px; opacity: 0.5; margin-bottom: 15px;"></i>
                <p>Chưa có truyền nhân nào đủ sức đánh thức Rồng Thần...</p>
                <p>Khối tài sản khổng lồ vẫn đang chờ chủ nhân đích thực!</p>
            </div>
            <?php endif; ?>
        </div>

        <a href="index.php" class="play-btn">Tham gia chơi game ngay</a>

    </div>

    <script>
        // Cập nhật Jackpot Realtime
        function fetchJackpot() {
            $.ajax({
                url: 'api_jackpot.php',
                type: 'GET',
                data: { action: 'get_status' },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        // Format số với dấu chấm
                        let formattedAmount = Number(res.amount).toLocaleString('vi-VN');
                        $('#liveJackpotAmount').html(formattedAmount + '<span class="jackpot-currency"> GTLM</span>');
                    }
                }
            });
        }

        // Cập nhật mỗi 5 giây
        setInterval(fetchJackpot, 5000);

        <?php if ($isAdminUser): ?>
        function adminWithdraw(type) {
            if (type === 'self') {
                Swal.fire({
                    title: 'Xác nhận húp Hũ?',
                    text: 'Toàn bộ GTLM trong Hũ sẽ được cộng vào tài khoản của bạn!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Húp ngay',
                    confirmButtonColor: '#ef4444'
                }).then((res) => {
                    if (res.isConfirmed) processAdminWithdraw('self', '');
                });
            } else if (type === 'individual') {
                Swal.fire({
                    title: 'Rải lộc cá nhân',
                    text: 'Nhập ID người chơi may mắn:',
                    input: 'number',
                    showCancelButton: true,
                    confirmButtonText: 'Chuyển tiền',
                    confirmButtonColor: '#10b981'
                }).then((res) => {
                    if (res.isConfirmed && res.value) processAdminWithdraw('individual', res.value);
                });
            } else if (type === 'group') {
                Swal.fire({
                    title: 'Chia cho nhóm',
                    text: 'Nhập danh sách ID, cách nhau bằng dấu phẩy (vd: 1, 5, 20):',
                    input: 'text',
                    showCancelButton: true,
                    confirmButtonText: 'Chia đều',
                    confirmButtonColor: '#f59e0b'
                }).then((res) => {
                    if (res.isConfirmed && res.value) processAdminWithdraw('group', res.value);
                });
            } else if (type === 'random') {
                Swal.fire({
                    title: 'Mưa tài lộc',
                    text: 'Nhập số lượng người chơi sẽ được nhận tiền ngẫu nhiên:',
                    input: 'number',
                    showCancelButton: true,
                    confirmButtonText: 'Bốc thăm & Chia đều',
                    confirmButtonColor: '#8b5cf6'
                }).then((res) => {
                    if (res.isConfirmed && res.value) processAdminWithdraw('random', res.value);
                });
            }
        }

        function processAdminWithdraw(type, target) {
            Swal.fire({
                title: 'Đang rải lộc...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            $.post('api_jackpot.php', { action: 'admin_withdraw', type: type, target: target }, function(res) {
                if (res.success) {
                    Swal.fire('Thành Công!', res.message, 'success');
                    fetchJackpot();
                } else {
                    Swal.fire('Thất Bại', res.message || 'Lỗi không xác định', 'error');
                }
            }, 'json').fail(function() {
                Swal.fire('Lỗi', 'Mất kết nối máy chủ', 'error');
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>
