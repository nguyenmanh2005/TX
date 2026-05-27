<?php
session_start();
require 'db_connect.php';
require 'admin_helper.php';

$userId = $_SESSION['Iduser'] ?? 0;
requireAdmin($conn, $userId);

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$msg = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'started') $msg = '🚀 Giải đấu đã bắt đầu!';
    if ($_GET['msg'] === 'ended') $msg = '🏁 Giải đấu đã kết thúc và trao giải!';
}

// Xử lý hành động
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('❌ Yêu cầu không hợp lệ (CSRF)!');
    }

    $action = $_POST['action'] ?? '';
    $tourId = (int)($_POST['id'] ?? 0);

    if ($action === 'start') {
        $stmt = $conn->prepare("UPDATE tournaments SET status = 'Ongoing', start_time = NOW() WHERE id = ?");
        $stmt->bind_param("i", $tourId);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_tournaments.php?msg=started");
        exit;
    } elseif ($action === 'end') {
        $conn->begin_transaction();
        try {
            // 1. Lấy thông tin giải đấu
            $stmtTour = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
            $stmtTour->bind_param("i", $tourId);
            $stmtTour->execute();
            $tour = $stmtTour->get_result()->fetch_assoc();
            $stmtTour->close();
            $prizePool = $tour['prize_pool'];

            // 2. Lấy Top 3 người chơi có điểm cao nhất
            $stmtScores = $conn->prepare("SELECT user_id, score FROM tournament_scores WHERE tournament_id = ? ORDER BY score DESC LIMIT 3");
            $stmtScores->bind_param("i", $tourId);
            $stmtScores->execute();
            $scores = $stmtScores->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtScores->close();

            $ratios = [0.5, 0.3, 0.2]; // 50%, 30%, 20%
            foreach ($scores as $index => $s) {
                if (!isset($ratios[$index])) break;
                $reward = $prizePool * $ratios[$index];
                $uId = (int)$s['user_id'];
                
                // Trao thưởng
                $stmtReward = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
                $stmtReward->bind_param("di", $reward, $uId);
                $stmtReward->execute();
                $stmtReward->close();
                
                // Ghi log thắng giải
                $winMsg = "Chúc mừng! Bạn đã đạt Top " . ($index + 1) . " trong giải đấu {$tour['name']} và nhận được " . number_format($reward) . " GTLM!";
                $stmtChat = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar) VALUES (0, 'Hệ Thống', ?, 'https://cdn-icons-png.flaticon.com/512/1041/1041044.png')");
                $stmtChat->bind_param("s", $winMsg);
                $stmtChat->execute();
                $stmtChat->close();
            }

            // 3. Cập nhật trạng thái
            $stmtFinish = $conn->prepare("UPDATE tournaments SET status = 'Finished', end_time = NOW() WHERE id = ?");
            $stmtFinish->bind_param("i", $tourId);
            $stmtFinish->execute();
            $stmtFinish->close();
            
            $conn->commit();
            header("Location: admin_tournaments.php?msg=ended");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            die("Lỗi: " . $e->getMessage());
        }
    }
}

$tournaments = $conn->query("SELECT t.*, (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id) as participants FROM tournaments t ORDER BY t.id DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Admin - Điều Hành Giải Đấu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #fbbf24; --dark: #0f172a; --card: #1e293b; --text: #f8fafc; }
        body { background: var(--dark); color: var(--text); font-family: 'Inter', sans-serif; padding: 40px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .card { background: var(--card); padding: 30px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .btn { padding: 8px 15px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; transition: 0.3s; font-size: 13px; }
        .btn-start { background: #10b981; color: white; }
        .btn-end { background: #ef4444; color: white; }
        .badge { padding: 4px 10px; border-radius: 5px; font-size: 11px; font-weight: bold; }
        .status-Pending { background: #3b82f6; }
        .status-Ongoing { background: #ef4444; animation: pulse 1.5s infinite; }
        .status-Finished { background: #64748b; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }
        .alert { background: #10b981; color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>🏆 Tournament Control</h1>
                <p style="color: #94a3b8;">Điều hành và trao giải cho các giải đấu đang diễn ra</p>
            </div>
            <a href="admin_dashboard.php" style="color: white; text-decoration: none;"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </div>

        <?php if ($msg): ?>
            <div class="alert"><?= $msg ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>🌍 Tất cả giải đấu</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tên Giải Đấu</th>
                        <th>Trạng Thái</th>
                        <th>Game</th>
                        <th>Người Tham Gia</th>
                        <th>Prize Pool</th>
                        <th>Hành Động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tournaments as $t): ?>
                    <tr>
                        <td><b><?= htmlspecialchars($t['name']) ?></b></td>
                        <td><span class="badge status-<?= $t['status'] ?>"><?= $t['status'] ?></span></td>
                        <td><?= $t['game_type'] ?></td>
                        <td><?= $t['participants'] ?> / <?= $t['max_players'] ?></td>
                        <td style="color: #ffd700; font-weight: bold;"><?= number_format($t['prize_pool']) ?> GTLM</td>
                        <td>
                            <?php if ($t['status'] === 'Pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="start">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button class="btn btn-start"><i class="fas fa-play"></i> Bắt Đầu</button>
                                </form>
                            <?php elseif ($t['status'] === 'Ongoing'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="end">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button class="btn btn-end"><i class="fas fa-stop"></i> Kết Thúc &amp; Trao Giải</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #64748b; font-size: 12px;">Đã hoàn thành</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
