<?php
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../admin_helper.php';

$userId = $_SESSION['Iduser'] ?? 0;
requireSuperAdmin($conn, $userId); // Chỉ Super Admin (Role >= 2) trở lên

$msg = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'added') $msg = '✅ Đã thêm công thức mới thành công!';
    if ($_GET['success'] === 'deleted') $msg = '🗑️ Đã xóa công thức thành công!';
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    session_write_close();
}

// Xử lý hành động
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF Token verification failed.");
    }
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
        header("Location: admin_dashboard.php?page=assets&sub=crafting&success=added");
        exit;
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM crafting_recipes WHERE id = $id");
        header("Location: admin_dashboard.php?page=assets&sub=crafting&success=deleted");
        exit;
    }
}

$recipes = $conn->query("SELECT * FROM crafting_recipes ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Admin - Quản Lý Rèn Vật Phẩm (Crafting)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_modules/admin_theme.css">
    <style>
        body {
            background: var(--admin-bg, #07090f);
            color: var(--admin-text, #e8eaf0);
            font-family: 'Outfit', sans-serif;
            margin: 0;
            padding: 40px 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .card { 
            background: var(--admin-surface, #0e111a); 
            padding: 30px; 
            border-radius: 20px; 
            border: 1px solid var(--admin-border, rgba(255,255,255,0.08)); 
            margin-bottom: 30px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        h1, h2 { margin-top: 0; color: #fff; font-weight: 800; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
        th { color: var(--admin-muted, #636b80); font-size: 12px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        input, select, textarea { 
            background: rgba(0,0,0,0.35); border: 1px solid rgba(255,255,255,0.12); 
            padding: 12px; border-radius: 12px; color: white; width: 100%; box-sizing: border-box;
            font-family: inherit; font-size: 14px;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--admin-primary, #4f8dff);
            box-shadow: 0 0 0 3px rgba(79, 141, 255, 0.2);
        }
        .btn { 
            padding: 12px 25px; border-radius: 12px; border: none; font-weight: bold; cursor: pointer; transition: 0.2s;
            display: inline-flex; align-items: center; gap: 8px; font-family: inherit;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn-primary { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: white; box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3); }
        .btn-danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 8px 15px; font-size: 12px; }
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge-theme { background: rgba(139, 92, 246, 0.2); color: #c084fc; border: 1px solid rgba(139, 92, 246, 0.3); }
        .badge-cursor { background: rgba(236, 72, 153, 0.2); color: #f472b6; border: 1px solid rgba(236, 72, 153, 0.3); }
        .badge-frame, .badge-avatarframe { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-chatframe { background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .alert { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <?php
        require_once __DIR__ . '/navbar.php';
        renderAdminNavbar('assets', 'crafting');
        ?>
        <div class="header">
            <div>
                <h1>🛠️ Crafting Management</h1>
                <p style="color: #94a3b8;">Cấu hình công thức rèn vật phẩm cho người chơi</p>
            </div>
            <a href="admin_dashboard.php?page=overview" style="color: white; text-decoration: none;"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </div>

        <?php if ($msg): ?>
            <div class="alert"><?= $msg ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>✨ Thêm Công Thức Mới</h2>
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
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
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
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
