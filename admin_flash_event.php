<?php
session_start();
require_once 'db_connect.php';
require_once 'admin_helper.php';

// Generate CSRF token if not already exists in session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = $_SESSION['Iduser'] ?? 0;
requireAdmin($conn, $userId);

$msg = $_GET['msg'] ?? '';

// ==========================================
// ⚡ XỬ LÝ POST
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die("CSRF Token Verification Failed.");
    }

    if (isset($_POST['save_flash'])) {
        $id = (int)($_POST['id'] ?? 0);
        $multiplier = (float)$_POST['multiplier'];
        $startTime = $_POST['start_time'];
        $endTime = $_POST['end_time'];
        $status = $_POST['status'];

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE flash_events SET multiplier = ?, start_time = ?, end_time = ?, status = ? WHERE id = ?");
            $stmt->bind_param("dsssi", $multiplier, $startTime, $endTime, $status, $id);
            $stmt->execute();
            $stmt->close();
            header("Location: admin_flash_event.php?msg=" . urlencode("Cập nhật Flash Event thành công!"));
        } else {
            $stmt = $conn->prepare("INSERT INTO flash_events (multiplier, start_time, end_time, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("dsss", $multiplier, $startTime, $endTime, $status);
            $stmt->execute();
            $stmt->close();
            
            // Nếu tạo active, thông báo toàn server luôn
            if ($status === 'active') {
                $duration = max(1, round((strtotime($endTime) - strtotime($startTime)) / 60));
                $announceMsg = "⚡ SỰ KIỆN CHỚP NHOÁNG (FLASH EVENT)! Cổng trời mở ra x{$multiplier} phần thưởng GTLM cho TOÀN BỘ trận địa trong {$duration} phút tiếp theo!";
                $stmtChat = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, created_at) VALUES (1, 'Hệ Thống', ?, NOW())");
                if ($stmtChat) {
                    $stmtChat->bind_param("s", $announceMsg);
                    $stmtChat->execute();
                    $stmtChat->close();
                }
            }
            
            header("Location: admin_flash_event.php?msg=" . urlencode("Tạo Flash Event thành công!"));
        }
        exit;
    }

    if (isset($_POST['delete_flash'])) {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM flash_events WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_flash_event.php?msg=" . urlencode("Đã xóa Flash Event!"));
        exit;
    }
}

// Lấy danh sách Flash Events
$events = $conn->query("SELECT * FROM flash_events ORDER BY id DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);
$now = time();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Flash Event Manager | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #f59e0b;
            --bg: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --danger: #ef4444;
            --success: #22c55e;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            margin: 0; padding: 40px 20px;
            background-image: radial-gradient(at 100% 0%, rgba(245, 158, 11, 0.15) 0px, transparent 50%);
            min-height: 100vh;
        }

        .container { max-width: 1200px; margin: 0 auto; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        h1 { font-size: 2.2rem; font-weight: 900; margin: 0; background: linear-gradient(to right, #fcd34d, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .card { background: var(--card-bg); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; padding: 30px; margin-bottom: 30px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 14px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.04); }
        th { color: var(--text-muted); font-size: 0.9rem; font-weight: 600; }
        tr:hover td { background: rgba(255, 255, 255, 0.02); }

        .badge { padding: 5px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 700; }
        .badge-active { background: rgba(34, 197, 94, 0.15); color: var(--success); border: 1px solid rgba(34, 197, 94, 0.3); }
        .badge-inactive { background: rgba(148, 163, 184, 0.15); color: var(--text-muted); border: 1px solid rgba(148, 163, 184, 0.3); }
        .badge-running { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); animation: pulse 2s infinite; }

        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }

        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; color: var(--text-muted); font-size: 0.9rem; }
        input, select { width: 100%; padding: 10px 15px; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 10px; color: white; font-family: inherit; box-sizing: border-box; }

        button { padding: 12px 20px; background: linear-gradient(135deg, var(--primary) 0%, #d97706 100%); border: none; border-radius: 10px; color: white; font-weight: 700; cursor: pointer; transition: 0.2s; }
        button:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(245, 158, 11, 0.4); }

        .btn-sm { padding: 8px 12px; font-size: 0.85rem; }
        .btn-danger { background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%); }
        .btn-outline { background: transparent; border: 1px solid rgba(255,255,255,0.2); }
        .alert { padding: 15px; background: rgba(34, 197, 94, 0.15); color: var(--success); border-radius: 10px; margin-bottom: 20px; font-weight: 600; border: 1px solid rgba(34, 197, 94, 0.3); }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fa fa-bolt"></i> Quản Lý Flash Event</h1>
            <a href="admin_Event_Manager.php"><button class="btn-outline"><i class="fa fa-arrow-left"></i> Về Event Manager</button></a>
        </header>

        <?php if ($msg): ?>
            <div class="alert"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <div class="card">
                <h2><i class="fa fa-plus"></i> Tạo / Sửa Event</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="id" id="edit_id" value="0">

                    <div class="form-group">
                        <label>Hệ số nhân (Multiplier)</label>
                        <input type="number" step="0.1" name="multiplier" id="edit_multiplier" value="2.0" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Thời gian Bắt Đầu</label>
                        <input type="datetime-local" name="start_time" id="edit_start" required>
                    </div>

                    <div class="form-group">
                        <label>Thời gian Kết Thúc</label>
                        <input type="datetime-local" name="end_time" id="edit_end" required>
                    </div>

                    <div class="form-group">
                        <label>Trạng Thái</label>
                        <select name="status" id="edit_status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <button type="submit" name="save_flash" style="width:100%;"><i class="fa fa-save"></i> LƯU FLASH EVENT</button>
                    <button type="button" class="btn-outline" style="width:100%; margin-top:10px;" onclick="resetForm()">Làm mới Form</button>
                </form>
            </div>

            <div class="card">
                <h2><i class="fa fa-list"></i> Lịch Sử Flash Event</h2>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Hệ số</th>
                                <th>Bắt Đầu</th>
                                <th>Kết Thúc</th>
                                <th>Trạng Thái</th>
                                <th style="text-align:right;">Hành Động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $ev): 
                                $startTs = strtotime($ev['start_time']);
                                $endTs = strtotime($ev['end_time']);
                                $isRunning = ($ev['status'] === 'active' && $now >= $startTs && $now <= $endTs);
                            ?>
                            <tr>
                                <td><strong style="color:var(--primary);">x<?= (float)$ev['multiplier'] ?></strong></td>
                                <td style="font-size:13px;"><?= date('d/m/Y H:i', $startTs) ?></td>
                                <td style="font-size:13px;"><?= date('d/m/Y H:i', $endTs) ?></td>
                                <td>
                                    <?php if ($isRunning): ?>
                                        <span class="badge badge-running">ĐANG CHẠY</span>
                                    <?php elseif ($ev['status'] === 'active'): ?>
                                        <span class="badge badge-active">ACTIVE</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">INACTIVE</span>
                                    <?php endif; ?>
                                </td>
                                <td style="display:flex; gap:5px; justify-content:flex-end;">
                                    <button class="btn-sm btn-outline" type="button" onclick="editEvent(<?= $ev['id'] ?>, <?= $ev['multiplier'] ?>, '<?= str_replace(' ', 'T', $ev['start_time']) ?>', '<?= str_replace(' ', 'T', $ev['end_time']) ?>', '<?= $ev['status'] ?>')">
                                        <i class="fa fa-edit"></i> Sửa
                                    </button>
                                    <form method="POST" onsubmit="return confirm('Chắc chắn xóa Flash Event này?');" style="margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                                        <button type="submit" name="delete_flash" class="btn-sm btn-danger"><i class="fa fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editEvent(id, mult, start, end, status) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_multiplier').value = mult;
            document.getElementById('edit_start').value = start.substring(0, 16);
            document.getElementById('edit_end').value = end.substring(0, 16);
            document.getElementById('edit_status').value = status;
        }

        function resetForm() {
            document.getElementById('edit_id').value = 0;
            document.getElementById('edit_multiplier').value = 2.0;
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            const nowStr = now.toISOString().substring(0,16);
            
            const end = new Date(now);
            end.setMinutes(end.getMinutes() + 30);
            const endStr = end.toISOString().substring(0,16);
            
            document.getElementById('edit_start').value = nowStr;
            document.getElementById('edit_end').value = endStr;
            document.getElementById('edit_status').value = 'active';
        }

        // Set default times on load
        if(document.getElementById('edit_id').value == "0") resetForm();
    </script>
</body>
</html>
