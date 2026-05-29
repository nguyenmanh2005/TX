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

$action = $_GET['action'] ?? '';
$msg = $_GET['msg'] ?? '';

// ==========================================
// ⚡ XỬ LÝ CÁC THAY ĐỔI QUA POST
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die("Lỗi: Yêu cầu không hợp lệ (CSRF Token Verification Failed).");
    }

    // 1. SAVE STORYLINE EVENT
    if (isset($_POST['save_storyline'])) {
        $id = (int)($_POST['id'] ?? 0);
        $title = $_POST['title'];
        $description = $_POST['description'];
        $totalChapters = (int)($_POST['total_chapters'] ?? 5);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($isActive) {
            // Đảm bảo chỉ 1 storyline được active
            $conn->query("UPDATE storyline_events SET is_active = 0");
        }

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE storyline_events SET title = ?, description = ?, total_chapters = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param("ssiii", $title, $description, $totalChapters, $isActive, $id);
            $stmt->execute();
            $stmt->close();
            header("Location: admin_storyline.php?msg=" . urlencode("Storyline Event đã được cập nhật!"));
        } else {
            $stmt = $conn->prepare("INSERT INTO storyline_events (title, description, total_chapters, is_active) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssii", $title, $description, $totalChapters, $isActive);
            $stmt->execute();
            $stmt->close();
            header("Location: admin_storyline.php?msg=" . urlencode("Storyline Event mới đã được tạo!"));
        }
        exit;
    }

    // 2. DELETE STORYLINE EVENT
    if (isset($_POST['delete_storyline'])) {
        $id = (int)$_POST['id'];
        
        $stmtC = $conn->prepare("DELETE FROM storyline_chapters WHERE storyline_id = ?");
        $stmtC->bind_param("i", $id); $stmtC->execute(); $stmtC->close();

        $stmtP = $conn->prepare("DELETE FROM user_storyline_progress WHERE storyline_id = ?");
        $stmtP->bind_param("i", $id); $stmtP->execute(); $stmtP->close();

        $stmt = $conn->prepare("DELETE FROM storyline_events WHERE id = ?");
        $stmt->bind_param("i", $id); $stmt->execute(); $stmt->close();

        header("Location: admin_storyline.php?msg=" . urlencode("Đã xóa Storyline Event và toàn bộ dữ liệu liên quan!"));
        exit;
    }

    // 3. SAVE CHAPTER
    if (isset($_POST['save_chapter'])) {
        $id = (int)($_POST['chapter_id'] ?? 0);
        $storylineId = (int)$_POST['storyline_id'];
        $chapterNum = (int)$_POST['chapter_number'];
        $chapterTitle = $_POST['title'];
        $storyText = $_POST['story_text'] ?? '';
        $targetBets = (int)$_POST['target_bets'];
        $rewardMoney = (float)$_POST['reward_money'];

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE storyline_chapters SET chapter_number = ?, chapter_title = ?, story_text = ?, target_bets = ?, reward_money = ? WHERE id = ?");
            $stmt->bind_param("issdii", $chapterNum, $chapterTitle, $storyText, $targetBets, $rewardMoney, $id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO storyline_chapters (storyline_id, chapter_number, chapter_title, story_text, target_bets, reward_money) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissdi", $storylineId, $chapterNum, $chapterTitle, $storyText, $targetBets, $rewardMoney);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: admin_storyline.php?manage_id=$storylineId&msg=" . urlencode("Chương cốt truyện đã được lưu!"));
        exit;
    }

    // 4. DELETE CHAPTER
    if (isset($_POST['delete_chapter'])) {
        $id = (int)$_POST['chapter_id'];
        $storylineId = (int)$_POST['storyline_id'];
        $stmt = $conn->prepare("DELETE FROM storyline_chapters WHERE id = ?");
        $stmt->bind_param("i", $id); $stmt->execute(); $stmt->close();
        header("Location: admin_storyline.php?manage_id=$storylineId&msg=" . urlencode("Đã xóa chương cốt truyện!"));
        exit;
    }
}

// Lấy danh sách storyline
$storylines = $conn->query("SELECT * FROM storyline_events ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

// Lấy chi tiết event nếu đang manage
$manageId = (int)($_GET['manage_id'] ?? 0);
$selectedStoryline = null;
$chapters = [];

if ($manageId > 0) {
    $selectedStoryline = $conn->query("SELECT * FROM storyline_events WHERE id = $manageId")->fetch_assoc();
    if ($selectedStoryline) {
        $chapters = $conn->query("SELECT * FROM storyline_chapters WHERE storyline_id = $manageId ORDER BY chapter_number ASC")->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Storyline Event Manager | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #8b5cf6;
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
            margin: 0;
            padding: 40px 20px;
            background-image: radial-gradient(at 0% 0%, rgba(139, 92, 246, 0.15) 0px, transparent 50%);
            min-height: 100vh;
        }

        .container { max-width: 1200px; margin: 0 auto; }
        
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        h1 { font-size: 2.2rem; font-weight: 900; margin: 0; background: linear-gradient(to right, #a78bfa, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .grid-2 { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 14px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.04); }
        th { color: var(--text-muted); font-size: 0.9rem; font-weight: 600; }
        tr:hover td { background: rgba(255, 255, 255, 0.02); }

        .badge { padding: 5px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 700; }
        .badge-active { background: rgba(34, 197, 94, 0.15); color: var(--success); border: 1px solid rgba(34, 197, 94, 0.3); }
        .badge-inactive { background: rgba(148, 163, 184, 0.15); color: var(--text-muted); border: 1px solid rgba(148, 163, 184, 0.3); }

        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; color: var(--text-muted); font-size: 0.9rem; }
        input, textarea, select {
            width: 100%; padding: 10px 15px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px; color: white; font-family: inherit; box-sizing: border-box;
        }

        button {
            padding: 12px 20px;
            background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%);
            border: none; border-radius: 10px; color: white; font-weight: 700; cursor: pointer; transition: 0.2s;
        }
        button:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(139, 92, 246, 0.4); }

        .btn-sm { padding: 8px 12px; font-size: 0.85rem; }
        .btn-danger { background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%); }
        .btn-outline { background: transparent; border: 1px solid rgba(255,255,255,0.2); }
        
        .alert { padding: 15px; background: rgba(34, 197, 94, 0.15); color: var(--success); border-radius: 10px; margin-bottom: 20px; font-weight: 600; border: 1px solid rgba(34, 197, 94, 0.3); }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fa fa-book-open"></i> Quản Lý Storyline Event</h1>
            <div>
                <a href="admin_Event_Manager.php"><button class="btn-outline"><i class="fa fa-arrow-left"></i> Về Event Manager</button></a>
                <?php if ($manageId > 0): ?>
                    <a href="admin_storyline.php"><button class="btn-outline"><i class="fa fa-plus"></i> Tạo Mới Storyline</button></a>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($msg): ?>
            <div class="alert"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <!-- LEFT: FORM CREATE/EDIT STORYLINE -->
            <div class="card">
                <h2><i class="fa fa-<?= $manageId > 0 ? 'edit' : 'plus' ?>"></i> <?= $manageId > 0 ? 'Sửa Storyline' : 'Tạo Storyline Mới' ?></h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <?php if ($manageId > 0): ?>
                        <input type="hidden" name="id" value="<?= $manageId ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Tiêu đề</label>
                        <input type="text" name="title" required value="<?= htmlspecialchars($selectedStoryline['title'] ?? '') ?>" placeholder="VD: Hành Trình Bất Tận">
                    </div>
                    
                    <div class="form-group">
                        <label>Mô tả cốt truyện (cốt truyện tóm tắt)</label>
                        <textarea name="description" rows="3" required><?= htmlspecialchars($selectedStoryline['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Tổng số chương</label>
                        <input type="number" name="total_chapters" min="1" required value="<?= htmlspecialchars($selectedStoryline['total_chapters'] ?? 5) ?>">
                    </div>

                    <div class="form-group" style="display:flex; align-items:center; gap:10px;">
                        <input type="checkbox" name="is_active" id="isActive" style="width:auto;" <?= ($selectedStoryline['is_active'] ?? 0) ? 'checked' : '' ?>>
                        <label for="isActive" style="margin:0;">Kích hoạt (Chỉ 1 sự kiện được chạy tại 1 thời điểm)</label>
                    </div>

                    <button type="submit" name="save_storyline" style="width:100%; margin-top:15px;"><i class="fa fa-save"></i> LƯU SỰ KIỆN</button>
                </form>
            </div>

            <!-- RIGHT: LIST STORYLINES -->
            <div class="card">
                <h2><i class="fa fa-list"></i> Danh Sách Sự Kiện Cốt Truyện</h2>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tiêu Đề</th>
                                <th>Chương</th>
                                <th>Trạng Thái</th>
                                <th style="text-align:right;">Hành Động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($storylines as $sl): ?>
                            <tr>
                                <td>#<?= $sl['id'] ?></td>
                                <td style="font-weight:700;"><?= htmlspecialchars($sl['title']) ?></td>
                                <td><?= $sl['total_chapters'] ?></td>
                                <td><span class="badge <?= $sl['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $sl['is_active'] ? 'ACTIVE' : 'INACTIVE' ?></span></td>
                                <td style="text-align:right; display:flex; gap:5px; justify-content:flex-end;">
                                    <a href="admin_storyline.php?manage_id=<?= $sl['id'] ?>">
                                        <button class="btn-sm"><i class="fa fa-cog"></i> Quản Lý</button>
                                    </a>
                                    <form method="POST" onsubmit="return confirm('Xóa sự kiện này sẽ xóa toàn bộ tiến trình người chơi và chi tiết chương! Chắc chắn xóa?');" style="margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="id" value="<?= $sl['id'] ?>">
                                        <button type="submit" name="delete_storyline" class="btn-sm btn-danger"><i class="fa fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($manageId > 0 && $selectedStoryline): ?>
        <!-- QUẢN LÝ CHƯƠNG -->
        <div class="card" style="border-color: rgba(139, 92, 246, 0.3);">
            <h2 style="color:#c084fc;"><i class="fa fa-layer-group"></i> Quản Lý Các Chương: <?= htmlspecialchars($selectedStoryline['title']) ?></h2>
            
            <div class="grid-2">
                <div>
                    <h3 style="margin-top:0; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:10px;">Thêm / Sửa Chương</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="storyline_id" value="<?= $manageId ?>">
                        <input type="hidden" name="chapter_id" id="edit_chapter_id" value="0">
                        
                        <div class="form-group">
                            <label>Số Chương (Ví dụ: 1, 2, 3...)</label>
                            <input type="number" name="chapter_number" id="edit_chapter_number" required min="1">
                        </div>
                        
                        <div class="form-group">
                            <label>Tên Chương</label>
                            <input type="text" name="title" id="edit_title" required placeholder="VD: Khởi Hành">
                        </div>

                        <div class="form-group">
                            <label>Nội dung cốt truyện</label>
                            <textarea name="story_text" id="edit_story_text" rows="4" required placeholder="VD: Khi mặt trận bắt đầu nóng lên..."></textarea>
                        </div>

                        <div class="form-group">
                            <label>Yêu Cầu (Số ván cược trong ngày)</label>
                            <input type="number" name="target_bets" id="edit_target_bets" required min="1">
                        </div>

                        <div class="form-group">
                            <label>Phần Thưởng (GTLM)</label>
                            <input type="number" name="reward_money" id="edit_reward_money" required min="0">
                        </div>

                        <button type="submit" name="save_chapter" style="width:100%;"><i class="fa fa-save"></i> LƯU CHƯƠNG NÀY</button>
                    </form>
                </div>

                <div>
                    <h3 style="margin-top:0; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:10px;">Danh Sách Chương Đã Tạo</h3>
                    <table style="margin-top:0;">
                        <thead>
                            <tr>
                                <th>Chương</th>
                                <th>Tên</th>
                                <th>Cược Bắt Buộc</th>
                                <th>Thưởng</th>
                                <th>Hành Động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($chapters as $c): ?>
                            <tr>
                                <td><strong style="color:var(--primary);">Chương <?= $c['chapter_number'] ?></strong></td>
                                <td><?= htmlspecialchars($c['chapter_title'] ?? '') ?></td>
                                <td><?= number_format($c['target_bets']) ?> ván</td>
                                <td style="color:#fbbf24; font-weight:800;"><?= number_format($c['reward_money']) ?> GTLM</td>
                                <td style="display:flex; gap:5px;">
                                    <button class="btn-sm btn-outline" type="button" onclick="editChapter(<?= $c['id'] ?>, <?= $c['chapter_number'] ?>, '<?= htmlspecialchars(addslashes($c['chapter_title'] ?? '')) ?>', <?= $c['target_bets'] ?>, <?= $c['reward_money'] ?>, '<?= htmlspecialchars(addslashes($c['story_text'] ?? '')) ?>')">
                                        <i class="fa fa-edit"></i> Sửa
                                    </button>
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Chắc chắn xóa chương này?');">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="storyline_id" value="<?= $manageId ?>">
                                        <input type="hidden" name="chapter_id" value="<?= $c['id'] ?>">
                                        <button type="submit" name="delete_chapter" class="btn-sm btn-danger"><i class="fa fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($chapters)): ?>
                                <tr><td colspan="5" style="text-align:center; opacity:0.5;">Chưa có chương nào được tạo.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <script>
            function editChapter(id, num, title, target, reward, storyText) {
                document.getElementById('edit_chapter_id').value = id;
                document.getElementById('edit_chapter_number').value = num;
                document.getElementById('edit_title').value = title;
                document.getElementById('edit_target_bets').value = target;
                document.getElementById('edit_reward_money').value = reward;
                document.getElementById('edit_story_text').value = storyText;
                window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
            }
        </script>
        <?php endif; ?>

    </div>
</body>
</html>
