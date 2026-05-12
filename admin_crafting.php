<?php
session_start();
require 'db_connect.php';
require 'admin_helper.php';

$userId = $_SESSION['Iduser'] ?? 0;
requireAdmin($conn, $userId);

$msg = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'added') $msg = '✅ Đã thêm công thức mới thành công!';
    if ($_GET['success'] === 'deleted') $msg = '🗑️ Đã xóa công thức thành công!';
}

// Xử lý hành động
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = $_POST['name'] ?? '';
        $type = $_POST['output_type'] ?? '';
        $itemId = (int)($_POST['output_item_id'] ?? 0);
        $cost = (float)($_POST['gtlm_cost'] ?? 0);
        $rate = (int)($_POST['success_rate'] ?? 100);
        
        // Build JSON requirements
        $reqTypes = $_POST['req_type'] ?? [];
        $reqAmts = $_POST['req_amount'] ?? [];
        $requirements = [];
        foreach($reqTypes as $idx => $type) {
            if (!empty($type) && isset($reqAmts[$idx]) && $reqAmts[$idx] > 0) {
                // Nếu trùng loại thì cộng dồn
                $requirements[$type] = ($requirements[$type] ?? 0) + (int)$reqAmts[$idx];
            }
        }
        $reqs = json_encode($requirements);
        
        $desc = $_POST['description'] ?? '';

        $stmt = $conn->prepare("INSERT INTO crafting_recipes (name, output_type, output_item_id, gtlm_cost, success_rate, input_requirements, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssidiss", $name, $type, $itemId, $cost, $rate, $reqs, $desc);
        $stmt->execute();
        header("Location: admin_crafting.php?success=added");
        exit;
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM crafting_recipes WHERE id = $id");
        header("Location: admin_crafting.php?success=deleted");
        exit;
    }
}

$recipes = $conn->query("SELECT * FROM crafting_recipes ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Admin - Quản Lý Crafting</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #f97316;
            --dark: #0f172a;
            --card: #1e293b;
            --text: #f8fafc;
        }
        body {
            background: var(--dark);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 40px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .card { background: var(--card); padding: 30px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 30px; }
        h1, h2 { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
        th { color: #94a3b8; font-size: 12px; text-transform: uppercase; }
        .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        input, select, textarea { 
            background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); 
            padding: 12px; border-radius: 10px; color: white; width: 100%; box-sizing: border-box;
        }
        .btn { 
            padding: 12px 25px; border-radius: 10px; border: none; font-weight: bold; cursor: pointer; transition: 0.3s;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-danger { background: #ef4444; color: white; padding: 8px 15px; font-size: 12px; }
        .badge { padding: 4px 10px; border-radius: 5px; font-size: 11px; font-weight: bold; }
        .badge-theme { background: #8b5cf6; }
        .badge-cursor { background: #ec4899; }
        .badge-frame { background: #10b981; }
        .alert { background: #10b981; color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>🛠️ Crafting Management</h1>
                <p style="color: #94a3b8;">Cấu hình công thức rèn vật phẩm cho người chơi</p>
            </div>
            <a href="admin_dashboard.php" style="color: white; text-decoration: none;"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </div>

        <?php if ($msg): ?>
            <div class="alert"><?= $msg ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>✨ Thêm Công Thức Mới</h2>
            <form action="" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div>
                        <label>Tên công thức</label>
                        <input type="text" name="name" placeholder="VD: Rèn Kiếm Rồng" required>
                    </div>
                    <div>
                        <label>Loại vật phẩm đầu ra</label>
                        <select name="output_type">
                            <option value="theme">Giao diện (Theme)</option>
                            <option value="cursor">Con trỏ (Cursor)</option>
                            <option value="avatar_frame">Khung Avatar</option>
                        </select>
                    </div>
                    <div>
                        <label>ID Vật phẩm đầu ra</label>
                        <input type="number" name="output_item_id" required>
                    </div>
                    <div>
                        <label>Phí rèn (GTLM)</label>
                        <input type="number" name="gtlm_cost" value="50000">
                    </div>
                    <div>
                        <label>Tỉ lệ thành công (%)</label>
                        <input type="number" name="success_rate" value="100" min="0" max="100">
                    </div>
                    <div style="grid-column: span 3;">
                        <label>Nguyên liệu yêu cầu</label>
                        <div id="requirements-list">
                            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <select name="req_type[]" style="width: 40%;">
                                    <option value="theme">Themes</option>
                                    <option value="cursor">Cursors</option>
                                    <option value="avatar_frame">Frames</option>
                                    <option value="chat_frame">Chat Frames</option>
                                </select>
                                <input type="number" name="req_amount[]" value="1" style="width: 20%;" placeholder="SL">
                                <button type="button" class="btn" style="background: rgba(255,255,255,0.1); padding: 5px 15px;" onclick="addRequirementRow()">+</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <label>Mô tả công thức</label>
                    <textarea name="description" rows="2" placeholder="Thông tin cho người chơi biết họ đang rèn cái gì..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-plus"></i> Tạo Công Thức
                </button>
            </form>
        </div>

        <div class="card">
            <h2>📜 Danh Sách Công Thức Hiện Có</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên</th>
                        <th>Đầu Ra</th>
                        <th>ID Item</th>
                        <th>Phí (GTLM)</th>
                        <th>Tỉ Lệ</th>
                        <th>Yêu Cầu</th>
                        <th>Hành Động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recipes as $r): ?>
                    <tr>
                        <td>#<?= $r['id'] ?></td>
                        <td><b><?= htmlspecialchars($r['name']) ?></b></td>
                        <td>
                            <span class="badge badge-<?= str_replace('_', '', $r['output_type']) ?>">
                                <?= strtoupper($r['output_type']) ?>
                            </span>
                        </td>
                        <td><?= $r['output_item_id'] ?></td>
                        <td style="color: #ffd700;"><?= number_format($r['gtlm_cost']) ?></td>
                        <td><?= $r['success_rate'] ?>%</td>
                        <td style="font-size: 11px; color: #94a3b8;">
                            <?php 
                                $reqs = json_decode($r['input_requirements'], true);
                                foreach($reqs as $k => $v) echo "$v x " . strtoupper($k);
                            ?>
                        </td>
                        <td>
                            <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Xác nhận xóa công thức này?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        function addRequirementRow() {
            const list = document.getElementById('requirements-list');
            const row = document.createElement('div');
            row.style.display = 'flex';
            row.style.display = 'flex';
            row.style.gap = '10px';
            row.style.marginBottom = '10px';
            row.innerHTML = `
                <select name="req_type[]" style="width: 40%;">
                    <option value="theme">Themes</option>
                    <option value="cursor">Cursors</option>
                    <option value="avatar_frame">Frames</option>
                    <option value="chat_frame">Chat Frames</option>
                </select>
                <input type="number" name="req_amount[]" value="1" style="width: 20%;" placeholder="SL">
                <button type="button" class="btn btn-danger" style="padding: 5px 15px;" onclick="this.parentElement.remove()">-</button>
            `;
            list.appendChild(row);
        }
    </script>
</body>
</html>
