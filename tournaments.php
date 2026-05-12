<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require_once 'load_theme.php';
$userId = $_SESSION['Iduser'];

// Lấy danh sách giải đấu
$sql = "SELECT t.*, 
        (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id) as registered_players,
        (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id AND user_id = ?) as is_joined
        FROM tournaments t
        WHERE t.status IN ('Pending', 'Ongoing')
        ORDER BY t.start_time ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$tournaments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$userMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giải Đấu GTLM - Tournaments</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            color: white;
            font-family: 'Inter', sans-serif;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .tournament-header {
            text-align: center;
            margin-bottom: 50px;
            padding: 40px;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            border: 1px solid rgba(255, 215, 0, 0.3);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        }

        .tournament-header h1 {
            font-size: 3rem;
            font-weight: 900;
            letter-spacing: 2px;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #fff 0%, #ffd700 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .tournament-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }

        .tournament-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .tournament-card:hover {
            transform: translateY(-10px) scale(1.02);
            background: rgba(255, 255, 255, 0.08);
            border-color: #ffd700;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }

        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 6px 15px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .status-pending { background: #3498db; color: white; }
        .status-ongoing { background: #e74c3c; color: white; animation: pulse 1.5s infinite; }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }

        .prize-pool {
            font-size: 2.2rem;
            font-weight: 900;
            color: #ffd700;
            margin: 20px 0;
            text-align: center;
            text-shadow: 0 0 20px rgba(255, 215, 0, 0.4);
        }

        .prize-pool span {
            font-size: 1rem;
            color: #aaa;
            display: block;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .tour-meta {
            display: flex;
            justify-content: space-between;
            background: rgba(0, 0, 0, 0.2);
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .meta-item {
            text-align: center;
        }

        .meta-label {
            display: block;
            font-size: 11px;
            color: #888;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .join-btn {
            width: 100%;
            padding: 18px;
            border-radius: 15px;
            border: none;
            background: linear-gradient(135deg, #ffd700 0%, #b8860b 100%);
            color: #000;
            font-weight: 900;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
        }

        .join-btn:hover:not(:disabled) {
            transform: scale(1.03);
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.4);
        }

        .join-btn:disabled {
            background: #444;
            color: #888;
            cursor: not-allowed;
        }

        .already-joined {
            background: #2ecc71 !important;
            color: white !important;
        }

        .money-badge {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 12px 25px;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 100;
        }
    </style>
</head>
<body>
    <div class="money-badge">
        <i class="fas fa-coins" style="color: #ffd700;"></i>
        <span><?= number_format($userMoney) ?> GTLM</span>
    </div>

    <div class="container">
        <div class="tournament-header">
            <h1>🏆 TOURNAMENTS</h1>
            <p>Tham gia đấu trường - Chinh phục giải thưởng khổng lồ</p>
        </div>

        <div class="tournament-grid">
            <?php foreach ($tournaments as $tour): ?>
            <div class="tournament-card">
                <div class="status-badge status-<?= strtolower($tour['status']) ?>">
                    <?= $tour['status'] === 'Pending' ? 'Sắp diễn ra' : 'Đang đấu' ?>
                </div>
                
                <h3 style="font-size: 22px; margin-bottom: 5px;"><?= htmlspecialchars($tour['name']) ?></h3>
                <div style="font-size: 14px; color: #aaa;"><i class="fas fa-gamepad"></i> Game: <?= $tour['game_type'] ?></div>

                <div class="prize-pool">
                    <span>Tổng Giải Thưởng</span>
                    <?= number_format($tour['prize_pool']) ?> GTLM
                </div>

                <div class="tour-meta">
                    <div class="meta-item">
                        <span class="meta-label">Phí tham gia</span>
                        <b style="color: #ffd700;"><?= number_format($tour['buy_in']) ?></b>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Người chơi</span>
                        <b><?= $tour['registered_players'] ?> / <?= $tour['max_players'] ?></b>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Bắt đầu lúc</span>
                        <b><?= date('H:i d/m', strtotime($tour['start_time'])) ?></b>
                    </div>
                </div>

                <?php if ($tour['is_joined']): ?>
                    <?php if ($tour['status'] === 'Ongoing'): ?>
                        <a href="games/<?= strtolower(str_replace(' ', '', $tour['game_type'])) ?>.php" class="join-btn" style="text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center; background: #e74c3c; color: white;">
                            <i class="fas fa-play" style="margin-right: 8px;"></i> VÀO THI ĐẤU
                        </a>
                    <?php else: ?>
                        <button class="join-btn already-joined" disabled>
                            <i class="fas fa-check-circle"></i> ĐÃ THAM GIA
                        </button>
                    <?php endif; ?>
                <?php elseif ($tour['registered_players'] >= $tour['max_players']): ?>
                    <button class="join-btn" disabled>ĐÃ HẾT CHỖ</button>
                <?php else: ?>
                    <button class="join-btn" onclick="joinTournament(<?= $tour['id'] ?>, '<?= addslashes($tour['name']) ?>', <?= $tour['buy_in'] ?>)">
                        THAM GIA NGAY
                    </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align: center; margin-top: 50px;">
            <a href="index.php" style="color: rgba(255,255,255,0.5); text-decoration: none;"><i class="fas fa-arrow-left"></i> Quay lại Trang Chủ</a>
        </div>
    </div>

    <script>
        function joinTournament(id, name, buyIn) {
            Swal.fire({
                title: 'Xác nhận tham gia?',
                html: `Bạn sẽ tham gia <b>${name}</b><br>Phí đăng ký: <b style="color: #ffd700;">${buyIn.toLocaleString()} GTLM</b>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ffd700',
                confirmButtonText: '<span style="color: #000">Đăng ký ngay</span>',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_tournament.php', { action: 'join', tournament_id: id }, function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                title: 'Thành công!',
                                text: res.message,
                                icon: 'success'
                            }).then(() => location.reload());
                        } else {
                            Swal.fire('Lỗi!', res.message, 'error');
                        }
                    }, 'json');
                }
            });
        }
    </script>
</body>
</html>
