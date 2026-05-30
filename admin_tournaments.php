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
$msgType = 'success';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'started') $msg = '🚀 Giải đấu đã bắt đầu!';
    if ($_GET['msg'] === 'ended') $msg = '🏁 Giải đấu đã kết thúc và trao giải!';
    if ($_GET['msg'] === 'created') $msg = '✨ Giải đấu mới đã được tạo thành công!';
    if ($_GET['msg'] === 'paused') $msg = '⏸️ Giải đấu đã được tạm dừng!';
    if ($_GET['msg'] === 'resumed') $msg = '▶️ Giải đấu đã được tiếp tục!';
    if ($_GET['msg'] === 'deleted') $msg = '🗑️ Đã xóa giải đấu thành công!';
    if ($_GET['msg'] === 'updated') $msg = '✏️ Đã cập nhật thông tin giải đấu thành công!';
}
if (isset($_GET['error'])) {
    $msgType = 'error';
    if ($_GET['error'] === 'missing') $msg = '❌ Vui lòng nhập đầy đủ thông tin giải đấu!';
    if ($_GET['error'] === 'db') $msg = '❌ Đã xảy ra lỗi cơ sở dữ liệu!';
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
    } elseif ($action === 'pause') {
        $stmt = $conn->prepare("UPDATE tournaments SET status = 'Paused' WHERE id = ?");
        $stmt->bind_param("i", $tourId);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_tournaments.php?msg=paused");
        exit;
    } elseif ($action === 'resume') {
        $stmt = $conn->prepare("UPDATE tournaments SET status = 'Ongoing' WHERE id = ?");
        $stmt->bind_param("i", $tourId);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_tournaments.php?msg=resumed");
        exit;
    } elseif ($action === 'delete') {
        $conn->begin_transaction();
        try {
            // Xóa người tham gia giải đấu
            $stmt = $conn->prepare("DELETE FROM tournament_participants WHERE tournament_id = ?");
            $stmt->bind_param("i", $tourId);
            $stmt->execute();
            $stmt->close();

            // Xóa điểm số giải đấu
            $stmt = $conn->prepare("DELETE FROM tournament_scores WHERE tournament_id = ?");
            $stmt->bind_param("i", $tourId);
            $stmt->execute();
            $stmt->close();

            // Xóa giải đấu
            $stmt = $conn->prepare("DELETE FROM tournaments WHERE id = ?");
            $stmt->bind_param("i", $tourId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            header("Location: admin_tournaments.php?msg=deleted");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            header("Location: admin_tournaments.php?error=db");
            exit;
        }
    } elseif ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $gameType = trim($_POST['game_type'] ?? 'Tài Xỉu');
        $buyIn = (float)($_POST['buy_in'] ?? 0);
        $houseFee = (int)($_POST['house_fee_percent'] ?? 10);
        $maxPlayers = (int)($_POST['max_players'] ?? 50);
        $minPlayers = (int)($_POST['min_players'] ?? 2);
        $tournamentType = $_POST['tournament_type'] ?? 'weekly';
        $startTime = $_POST['start_time'] ?? '';

        // Build reward_structure JSON from dynamic rows
        $rewardKeys = $_POST['reward_key'] ?? [];
        $rewardVals = $_POST['reward_val'] ?? [];
        $rewardData = [];
        foreach ($rewardKeys as $i => $key) {
            $key = trim($key);
            if ($key !== '' && isset($rewardVals[$i]) && is_numeric($rewardVals[$i])) {
                $rewardData[$key] = (float)$rewardVals[$i];
            }
        }
        $rewardStructure = !empty($rewardData) ? json_encode($rewardData, JSON_UNESCAPED_UNICODE) : null;

        if (!empty($name) && !empty($startTime)) {
            $startTimeFormatted = date('Y-m-d H:i:s', strtotime($startTime));
            
            $stmt = $conn->prepare("INSERT INTO tournaments (name, description, game_type, buy_in, house_fee_percent, max_players, min_players, tournament_type, start_time, reward_structure, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
            $stmt->bind_param("sssdiiisss", $name, $description, $gameType, $buyIn, $houseFee, $maxPlayers, $minPlayers, $tournamentType, $startTimeFormatted, $rewardStructure);
            
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: admin_tournaments.php?msg=created");
                exit;
            } else {
                $stmt->close();
                header("Location: admin_tournaments.php?error=db");
                exit;
            }
        } else {
            header("Location: admin_tournaments.php?error=missing");
            exit;
        }
    } elseif ($action === 'edit') {
        $tourId = (int)($_POST['tournament_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $gameType = trim($_POST['game_type'] ?? 'Tài Xỉu');
        $buyIn = (float)($_POST['buy_in'] ?? 0);
        $houseFee = (int)($_POST['house_fee_percent'] ?? 10);
        $maxPlayers = (int)($_POST['max_players'] ?? 50);
        $minPlayers = (int)($_POST['min_players'] ?? 2);
        $tournamentType = $_POST['tournament_type'] ?? 'weekly';
        $startTime = $_POST['start_time'] ?? '';

        // Build reward_structure JSON from dynamic rows
        $rewardKeys = $_POST['reward_key'] ?? [];
        $rewardVals = $_POST['reward_val'] ?? [];
        $rewardData = [];
        foreach ($rewardKeys as $i => $key) {
            $key = trim($key);
            if ($key !== '' && isset($rewardVals[$i]) && is_numeric($rewardVals[$i])) {
                $rewardData[$key] = (float)$rewardVals[$i];
            }
        }
        $rewardStructure = !empty($rewardData) ? json_encode($rewardData, JSON_UNESCAPED_UNICODE) : null;

        if ($tourId > 0 && !empty($name) && !empty($startTime)) {
            $startTimeFormatted = date('Y-m-d H:i:s', strtotime($startTime));
            
            $stmt = $conn->prepare("UPDATE tournaments SET name = ?, description = ?, game_type = ?, buy_in = ?, house_fee_percent = ?, max_players = ?, min_players = ?, tournament_type = ?, start_time = ?, reward_structure = ? WHERE id = ?");
            $stmt->bind_param("sssdiiisssi", $name, $description, $gameType, $buyIn, $houseFee, $maxPlayers, $minPlayers, $tournamentType, $startTimeFormatted, $rewardStructure, $tourId);
            
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: admin_tournaments.php?msg=updated");
                exit;
            } else {
                $stmt->close();
                header("Location: admin_tournaments.php?error=db");
                exit;
            }
        } else {
            header("Location: admin_tournaments.php?error=missing");
            exit;
        }
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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary: #fbbf24; 
            --dark: #0f172a; 
            --card: rgba(30, 41, 59, 0.7); 
            --text: #f8fafc; 
            --text-muted: #94a3b8;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        body { 
            background: var(--dark); 
            color: var(--text); 
            font-family: 'Outfit', sans-serif; 
            padding: 40px 20px; 
            background-image: radial-gradient(at 0% 0%, rgba(251, 191, 36, 0.15) 0px, transparent 50%);
            min-height: 100vh;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        h1 { font-size: 2.2rem; font-weight: 900; margin: 0; background: linear-gradient(to right, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .card { 
            background: var(--card); 
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.08);
            padding: 30px; 
            border-radius: 20px; 
            margin-bottom: 30px; 
        }
        .grid-2 { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        @media(max-width: 992px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
        th { color: var(--text-muted); font-size: 0.9rem; font-weight: 600; }
        
        .btn { padding: 8px 14px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; transition: 0.2s; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; }
        .btn:hover { transform: translateY(-1px); }
        .btn-start { background: linear-gradient(135deg, var(--success) 0%, #059669 100%); color: white; }
        .btn-pause { background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%); color: white; }
        .btn-resume { background: linear-gradient(135deg, var(--success) 0%, #059669 100%); color: white; }
        .btn-end { background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%); color: white; }
        .btn-edit { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: white; }
        .btn-edit:hover { filter: brightness(1.2); }
        .btn-delete { background: linear-gradient(135deg, #4b5563 0%, #1f2937 100%); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .btn-delete:hover { background: #ef4444; color: white; }

        /* Edit Slide Panel */
        .edit-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 999; backdrop-filter: blur(4px); }
        .edit-overlay.open { display: block; }
        .edit-panel {
            position: fixed; top: 0; right: -520px; width: 500px; height: 100%; 
            background: #0f172a; border-left: 1px solid rgba(255,255,255,0.1);
            padding: 40px 30px; overflow-y: auto; z-index: 1000;
            transition: right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: -20px 0 60px rgba(0,0,0,0.5);
        }
        .edit-panel.open { right: 0; }
        .edit-panel h2 { font-size: 1.4rem; font-weight: 800; background: linear-gradient(to right, #60a5fa, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 25px; }
        .edit-panel .close-btn { position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.1); border: none; color: white; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .edit-panel .close-btn:hover { background: rgba(255,255,255,0.2); }
        
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-Pending { background: rgba(59, 130, 246, 0.2); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
        .status-Ongoing { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); animation: pulse 1.5s infinite; }
        .status-Paused { background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
        .status-Finished { background: rgba(100, 116, 139, 0.2); color: #94a3b8; border: 1px solid rgba(100, 116, 139, 0.3); }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }
        
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: bold; display: flex; align-items: center; gap: 8px; }
        .alert-success { background: rgba(16, 185, 129, 0.15); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.3); }
        .alert-error { background: rgba(239, 68, 68, 0.15); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.3); }

        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: var(--text-muted); font-size: 0.9rem; }
        input, textarea, select {
            width: 100%; padding: 10px 15px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px; color: white; font-family: inherit; box-sizing: border-box;
            font-size: 14px;
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--primary);
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary) 0%, #f59e0b 100%);
            border: none;
            border-radius: 10px;
            color: var(--dark);
            font-weight: 800;
            font-size: 15px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(251, 191, 36, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>🏆 Tournament Control</h1>
                <p style="color: var(--text-muted);">Điều hành, tạo mới, tạm dừng và trao giải cho các giải đấu</p>
            </div>
            <a href="admin_dashboard.php" style="color: white; text-decoration: none;"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msgType ?>"><i class="fas fa-info-circle"></i> <?= $msg ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <!-- LEFT: DANH SÁCH GIẢI ĐẤU -->
            <div class="card">
                <h2>🌍 Danh sách giải đấu</h2>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Tên Giải Đấu</th>
                                <th>Trạng Thái</th>
                                <th>Game</th>
                                <th>Người Tham Gia</th>
                                <th>Prize Pool</th>
                                <th style="text-align:right;">Hành Động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tournaments as $t): ?>
                            <tr>
                                <td><b><?= htmlspecialchars($t['name']) ?></b></td>
                                <td><span class="badge status-<?= $t['status'] ?>"><?= $t['status'] ?></span></td>
                                <td><?= htmlspecialchars($t['game_type']) ?></td>
                                <td><?= $t['participants'] ?> / <?= $t['max_players'] ?></td>
                                <td style="color: #ffd700; font-weight: bold;"><?= number_format($t['prize_pool']) ?> GTLM</td>
                                <td style="text-align:right; display:flex; gap:6px; justify-content:flex-end; align-items:center;">
                                    <?php if ($t['status'] === 'Pending'): ?>
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="start">
                                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                            <button class="btn btn-start" title="Bắt đầu giải đấu"><i class="fas fa-play"></i> Bắt Đầu</button>
                                        </form>
                                    <?php elseif ($t['status'] === 'Ongoing'): ?>
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="pause">
                                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                            <button class="btn btn-pause" title="Tạm dừng giải đấu"><i class="fas fa-pause"></i> Tạm Dừng</button>
                                        </form>
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="end">
                                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                            <button class="btn btn-end" title="Kết thúc & trao giải"><i class="fas fa-stop"></i> Kết Thúc</button>
                                        </form>
                                    <?php elseif ($t['status'] === 'Paused'): ?>
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="resume">
                                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                            <button class="btn btn-resume" title="Tiếp tục giải đấu"><i class="fas fa-play"></i> Tiếp Tục</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 12px; margin-right: 10px;">Hoàn thành</span>
                                    <?php endif; ?>

                                    <!-- Nút Sửa Giải Đấu -->
                                    <button class="btn btn-edit" title="Chỉnh sửa" onclick="openEdit(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8') ?>)"><i class="fas fa-pencil-alt"></i></button>

                                    <!-- Nút Xóa Giải Đấu -->
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa giải đấu này cùng toàn bộ dữ liệu người chơi liên quan?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                        <button class="btn btn-delete" title="Xóa giải đấu"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($tournaments)): ?>
                                <tr><td colspan="6" style="text-align:center; opacity:0.5;">Chưa có giải đấu nào được tạo.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- RIGHT: TẠO GIẢI ĐẤU MỚI -->
            <div class="card">
                <h2>➕ Tạo giải đấu mới</h2>
                <form method="POST" style="margin-top: 20px;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label>Tên Giải Đấu:</label>
                        <input type="text" name="name" required placeholder="Ví dụ: Siêu Cúp Xanh Đỏ Đối Kháng Hoàng Gia">
                    </div>

                    <div class="form-group">
                        <label>Mô tả giải đấu</label>
                        <textarea name="description" rows="3" placeholder="Mô tả thể lệ, phần thưởng giải đấu..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Loại Game:</label>
                        <select name="game_type">
                            <option value="Xanh Đỏ Đối Kháng">Xanh Đỏ Đối Kháng</option>
                            <option value="Baccarat">Baccarat</option>
                            <option value="Roulette">Roulette</option>
                            <option value="Poker">Poker</option>
                            <option value="Xóc Đĩa">Xóc Đĩa</option>
                            <option value="Tất cả">Tất cả Game</option>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Phí tham gia (GTLM) *</label>
                            <input type="number" name="buy_in" required min="0" step="1000" value="10000">
                        </div>
                        <div class="form-group">
                            <label>Phí vận hành (%)</label>
                            <input type="number" name="house_fee_percent" min="0" max="100" value="10">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Số người tối đa</label>
                            <input type="number" name="max_players" min="2" value="50">
                        </div>
                        <div class="form-group">
                            <label>Số người tối thiểu</label>
                            <input type="number" name="min_players" min="2" value="2">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Chu kỳ giải đấu</label>
                        <select name="tournament_type">
                            <option value="weekly">Weekly (Hàng tuần)</option>
                            <option value="daily">Daily (Hàng ngày)</option>
                            <option value="monthly">Monthly (Hàng tháng)</option>
                            <option value="special">Special (Đặc biệt)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Thời gian bắt đầu *</label>
                        <input type="datetime-local" name="start_time" required>
                    </div>

                    <div class="form-group">
                        <label>🏆 Cơ cấu giải thưởng</label>
                        <div id="create_reward_rows" style="display:flex; flex-direction:column; gap:8px; margin-bottom:8px;"></div>
                        <button type="button" onclick="addCreateRewardRow()" style="background:rgba(251,191,36,0.15); border:1px dashed #fbbf24; color:#fbbf24; padding:8px 16px; border-radius:8px; cursor:pointer; font-size:13px; width:100%;">
                            + Thêm hạng giải
                        </button>
                        <div style="font-size:11px; color:#94a3b8; margin-top:5px;">Đặt hạng: 1, 2, 3 hoặc dải hạng: 4-10, 11-50</div>
                    </div>

                    <button type="submit" class="btn-submit"><i class="fas fa-plus-circle"></i> TẠO GIẢI ĐẤU</button>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT OVERLAY & PANEL -->
    <div class="edit-overlay" id="editOverlay" onclick="closeEdit()"></div>
    <div class="edit-panel" id="editPanel">
        <button class="close-btn" onclick="closeEdit()">✕</button>
        <h2>✏️ Chỉnh Sửa Giải Đấu</h2>
        <form method="POST" id="editForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="tournament_id" id="edit_id">

            <div class="form-group">
                <label>Tên giải đấu *</label>
                <input type="text" name="name" id="edit_name" required>
            </div>

            <div class="form-group">
                <label>Mô tả giải đấu</label>
                <textarea name="description" id="edit_description" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label>Loại Game *</label>
                <input type="text" name="game_type" id="edit_game_type" list="game_list" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Phí tham gia (GTLM)</label>
                    <input type="number" name="buy_in" id="edit_buy_in" min="0" step="1000">
                </div>
                <div class="form-group">
                    <label>Phí vận hành (%)</label>
                    <input type="number" name="house_fee_percent" id="edit_house_fee" min="0" max="100">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Số người tối đa</label>
                    <input type="number" name="max_players" id="edit_max_players" min="2">
                </div>
                <div class="form-group">
                    <label>Số người tối thiểu</label>
                    <input type="number" name="min_players" id="edit_min_players" min="2">
                </div>
            </div>

            <div class="form-group">
                <label>Chu kỳ giải đấu</label>
                <select name="tournament_type" id="edit_tournament_type">
                    <option value="weekly">Weekly (Hàng tuần)</option>
                    <option value="daily">Daily (Hàng ngày)</option>
                    <option value="monthly">Monthly (Hàng tháng)</option>
                    <option value="special">Special (Đặc biệt)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Thời gian bắt đầu *</label>
                <input type="datetime-local" name="start_time" id="edit_start_time" required>
            </div>

            <div class="form-group">
                <label>🏆 Cơ cấu giải thưởng</label>
                <div id="edit_reward_rows" style="display:flex; flex-direction:column; gap:8px; margin-bottom:8px;"></div>
                <button type="button" onclick="addEditRewardRow()" style="background:rgba(59,130,246,0.15); border:1px dashed #3b82f6; color:#60a5fa; padding:8px 16px; border-radius:8px; cursor:pointer; font-size:13px; width:100%;">
                    + Thêm hạng giải
                </button>
                <div style="font-size:11px; color:#94a3b8; margin-top:5px;">Đặt hạng: 1, 2, 3 hoặc dải hạng: 4-10, 11-50</div>
            </div>

            <button type="submit" class="btn-submit" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);"><i class="fas fa-save"></i> LƯU THAY ĐỔI</button>
        </form>
    </div>

    <script>
    // ====== REWARD ROW HELPERS ======
    function makeRewardRow(containerId, key, val) {
        const row = document.createElement('div');
        row.style.cssText = 'display:grid; grid-template-columns:1fr 1fr auto; gap:8px; align-items:center;';
        row.innerHTML = `
            <input type="text" name="reward_key[]" placeholder="Hạng (vd: 1, 4-10)" value="${key || ''}" style="margin:0;">
            <input type="number" name="reward_val[]" placeholder="Phần thưởng (GTLM)" value="${val || ''}" min="0" step="1000" style="margin:0;">
            <button type="button" onclick="this.closest('div').remove()" style="background:rgba(239,68,68,0.2); border:1px solid rgba(239,68,68,0.4); color:#ef4444; width:34px; height:34px; border-radius:8px; cursor:pointer; font-size:16px; flex-shrink:0;">×</button>
        `;
        document.getElementById(containerId).appendChild(row);
    }

    function addCreateRewardRow() { makeRewardRow('create_reward_rows', '', ''); }
    function addEditRewardRow()   { makeRewardRow('edit_reward_rows', '', ''); }

    // ====== OPEN EDIT PANEL ======
    function openEdit(t) {
        document.getElementById('edit_id').value = t.id;
        document.getElementById('edit_name').value = t.name || '';
        document.getElementById('edit_description').value = t.description || '';
        document.getElementById('edit_game_type').value = t.game_type || '';
        document.getElementById('edit_buy_in').value = t.buy_in || 0;
        document.getElementById('edit_house_fee').value = t.house_fee_percent || 10;
        document.getElementById('edit_max_players').value = t.max_players || 50;
        document.getElementById('edit_min_players').value = t.min_players || 2;
        document.getElementById('edit_tournament_type').value = t.tournament_type || 'weekly';

        // Convert MySQL datetime to datetime-local format
        if (t.start_time) {
            const dt = new Date(t.start_time.replace(' ', 'T'));
            const pad = n => String(n).padStart(2, '0');
            const formatted = dt.getFullYear() + '-' + pad(dt.getMonth()+1) + '-' + pad(dt.getDate()) + 'T' + pad(dt.getHours()) + ':' + pad(dt.getMinutes());
            document.getElementById('edit_start_time').value = formatted;
        }

        // Populate reward rows
        const container = document.getElementById('edit_reward_rows');
        container.innerHTML = '';
        try {
            const rewards = JSON.parse(t.reward_structure || '{}');
            Object.entries(rewards).forEach(([k, v]) => makeRewardRow('edit_reward_rows', k, v));
        } catch(e) {}

        document.getElementById('editOverlay').classList.add('open');
        document.getElementById('editPanel').classList.add('open');
    }

    function closeEdit() {
        document.getElementById('editOverlay').classList.remove('open');
        document.getElementById('editPanel').classList.remove('open');
    }
    </script>
</body>
</html>
