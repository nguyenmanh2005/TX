<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

// Load theme
require_once 'load_theme.php';

// Load admin helper
require_once 'admin_helper.php';

if (!function_exists('fetchStmtRows')) {
    /**
     * Helper fetch cho mysqli_stmt khi host không bật mysqlnd.
     */
    function fetchStmtRows($stmt)
    {
        $rows = [];
        if (!$stmt) {
            return $rows;
        }

        if (function_exists('mysqli_stmt_get_result')) {
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
                $result->free();
            }
            return $rows;
        }

        $meta = $stmt->result_metadata();
        if (!$meta) {
            return $rows;
        }

        $fields = $meta->fetch_fields();
        $bindVars = [];
        $bindRefs = [];
        foreach ($fields as $field) {
            $bindVars[$field->name] = null;
            $bindRefs[] = &$bindVars[$field->name];
        }

        call_user_func_array([$stmt, 'bind_result'], $bindRefs);

        while ($stmt->fetch()) {
            $row = [];
            foreach ($bindVars as $key => $value) {
                $row[$key] = $value;
            }
            $rows[] = $row;
        }

        return $rows;
    }
}

$userId = $_SESSION['Iduser'] ?? null;

// Kiểm tra user ID
if (!$userId) {
    header("Location: login.php");
    exit();
}

// Kiểm tra bảng users tồn tại
$checkUsersTable = $conn->query("SHOW TABLES LIKE 'users'");
if (!$checkUsersTable || $checkUsersTable->num_rows == 0) {
    die("⚠️ LỖI: Bảng users không tồn tại! Vui lòng chạy file RESTORE_USERS_TABLE.sql hoặc ALL_DATABASE_TABLES.sql để tạo lại bảng users.");
}

// Kiểm tra user có tồn tại không
$checkUserSql = "SELECT Iduser, Role FROM users WHERE Iduser = ?";
$checkUserStmt = $conn->prepare($checkUserSql);
$checkUserStmt->bind_param("i", $userId);
$checkUserStmt->execute();
$userExists = $checkUserStmt->get_result()->num_rows > 0;
$checkUserStmt->close();

if (!$userExists) {
    // User không tồn tại trong database, clear session và redirect
    session_destroy();
    header("Location: login.php?error=user_not_found");
    exit();
}

// Kiểm tra quyền admin (Role = 1)
if (!isAdmin($conn, $userId)) {
    die("⚠️ Bạn không có quyền truy cập trang này! Chỉ admin (Role = 1) mới có thể truy cập. Vui lòng liên hệ admin để được cấp quyền.");
}

$message = '';
$messageType = '';

// Xử lý cập nhật Role
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_role'])) {
    $targetUserId = (int)$_POST['user_id'];
    $newRole = (int)$_POST['role'];
    
    // Không cho phép thay đổi role của chính mình
    if ($targetUserId == $userId) {
        $message = "❌ Bạn không thể thay đổi vai trò của chính mình!";
        $messageType = 'error';
    } else {
        // Validate role (0 = user, 1 = admin)
        if ($newRole != 0 && $newRole != 1) {
            $message = "❌ Vai trò không hợp lệ!";
            $messageType = 'error';
        } else {
            $updateSql = "UPDATE users SET Role = ? WHERE Iduser = ?";
            $updateStmt = $conn->prepare($updateSql);
            if ($updateStmt) {
                $updateStmt->bind_param("ii", $newRole, $targetUserId);
                if ($updateStmt->execute()) {
                    $message = "✅ Đã cập nhật vai trò thành công!";
                    $messageType = 'success';
                } else {
                    $message = "❌ Lỗi cập nhật: " . $conn->error;
                    $messageType = 'error';
                }
                $updateStmt->close();
            } else {
                $message = "❌ Lỗi prepare statement: " . $conn->error;
                $messageType = 'error';
            }
        }
    }
}

// Xử lý tìm kiếm
$search = $_GET['search'] ?? '';

// Lấy danh sách users với phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Đếm tổng số users
if (!empty($search)) {
    if (is_numeric($search)) {
        $countSql = "SELECT COUNT(*) as total FROM users u WHERE u.Name LIKE ? OR u.Iduser = ?";
        $countStmt = $conn->prepare($countSql);
        if ($countStmt) {
            $searchParam = "%$search%";
            $countStmt->bind_param("si", $searchParam, $search);
            $countStmt->execute();
        }
    } else {
        $countSql = "SELECT COUNT(*) as total FROM users u WHERE u.Name LIKE ?";
        $countStmt = $conn->prepare($countSql);
        if ($countStmt) {
            $searchParam = "%$search%";
            $countStmt->bind_param("s", $searchParam);
            $countStmt->execute();
        }
    }
} else {
    $countSql = "SELECT COUNT(*) as total FROM users";
    $countStmt = $conn->prepare($countSql);
    if ($countStmt) {
        $countStmt->execute();
    }
}

if ($countStmt) {
    $countRows = fetchStmtRows($countStmt);
    $totalUsers = isset($countRows[0]['total']) ? (int)$countRows[0]['total'] : 0;
    $countStmt->close();
} else {
    $totalUsers = 0;
}

$totalPages = ceil($totalUsers / $perPage);

// Kiểm tra bảng user_achievements có tồn tại không
$checkAchievementsTable = $conn->query("SHOW TABLES LIKE 'user_achievements'");
$hasAchievementsTable = $checkAchievementsTable && $checkAchievementsTable->num_rows > 0;

// Lấy danh sách users
if (!empty($search)) {
    if (is_numeric($search)) {
        if ($hasAchievementsTable) {
            $sql = "SELECT u.Iduser, u.Name, u.Money, u.Role, u.ImageURL, u.created_at,
                    COALESCE(COUNT(DISTINCT ua.achievement_id), 0) as achievement_count
                    FROM users u
                    LEFT JOIN user_achievements ua ON u.Iduser = ua.user_id
                    WHERE u.Name LIKE ? OR u.Iduser = ?
                    GROUP BY u.Iduser
                    ORDER BY u.Iduser DESC
                    LIMIT ? OFFSET ?";
        } else {
            $sql = "SELECT u.Iduser, u.Name, u.Money, u.Role, u.ImageURL, u.created_at, 0 as achievement_count
                    FROM users u
                    WHERE u.Name LIKE ? OR u.Iduser = ?
                    ORDER BY u.Iduser DESC
                    LIMIT ? OFFSET ?";
        }
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $searchParam = "%$search%";
            $stmt->bind_param("siii", $searchParam, $search, $perPage, $offset);
            $stmt->execute();
        }
    } else {
        if ($hasAchievementsTable) {
            $sql = "SELECT u.Iduser, u.Name, u.Money, u.Role, u.ImageURL, u.created_at,
                    COALESCE(COUNT(DISTINCT ua.achievement_id), 0) as achievement_count
                    FROM users u
                    LEFT JOIN user_achievements ua ON u.Iduser = ua.user_id
                    WHERE u.Name LIKE ?
                    GROUP BY u.Iduser
                    ORDER BY u.Iduser DESC
                    LIMIT ? OFFSET ?";
        } else {
            $sql = "SELECT u.Iduser, u.Name, u.Money, u.Role, u.ImageURL, u.created_at, 0 as achievement_count
                    FROM users u
                    WHERE u.Name LIKE ?
                    ORDER BY u.Iduser DESC
                    LIMIT ? OFFSET ?";
        }
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $searchParam = "%$search%";
            $stmt->bind_param("sii", $searchParam, $perPage, $offset);
            $stmt->execute();
        }
    }
} else {
    if ($hasAchievementsTable) {
        $sql = "SELECT u.Iduser, u.Name, u.Money, u.Role, u.ImageURL, u.created_at,
                COALESCE(COUNT(DISTINCT ua.achievement_id), 0) as achievement_count
                FROM users u
                LEFT JOIN user_achievements ua ON u.Iduser = ua.user_id
                GROUP BY u.Iduser
                ORDER BY u.Iduser DESC
                LIMIT ? OFFSET ?";
    } else {
        $sql = "SELECT u.Iduser, u.Name, u.Money, u.Role, u.ImageURL, u.created_at, 0 as achievement_count
                FROM users u
                ORDER BY u.Iduser DESC
                LIMIT ? OFFSET ?";
    }
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $perPage, $offset);
        $stmt->execute();
    }
}

$users = [];
if ($stmt) {
    $users = fetchStmtRows($stmt);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Người Dùng - Admin</title>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            padding: 20px;
            min-height: 100vh;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select, input[type="text"] {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.98);
            padding: 30px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        }
        
        h1 {
            color: var(--primary-color);
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            flex: 1;
            max-width: 400px;
        }
        
        .search-box input[type="text"] {
            flex: 1;
            padding: 12px 18px;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 16px;
            background: rgba(255, 255, 255, 0.95);
        }
        
        .search-box input[type="text"]:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.2);
        }
        
        .btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.5);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }
        
        .message {
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-weight: 600;
            animation: messageSlide 0.5s ease;
        }
        
        .message.success {
            background: rgba(40, 167, 69, 0.2);
            border: 2px solid #28a745;
            color: #155724;
        }
        
        .message.error {
            background: rgba(220, 53, 69, 0.2);
            border: 2px solid #dc3545;
            color: #721c24;
        }
        
        @keyframes messageSlide {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .users-table th {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .users-table td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .users-table tr:hover {
            background: rgba(52, 152, 219, 0.05);
        }
        
        .users-table tr:last-child td {
            border-bottom: none;
        }
        
        .avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }
        
        .role-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .role-badge.admin {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }
        
        .role-badge.user {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }
        
        .role-select {
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 14px;
            background: white;
            cursor: pointer;
        }
        
        .role-select:focus {
            outline: none;
            border-color: var(--secondary-color);
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: 14px;
            margin-left: 10px;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .pagination a,
        .pagination span {
            padding: 10px 15px;
            background: white;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .pagination a:hover {
            background: var(--secondary-color);
            color: white;
            border-color: var(--secondary-color);
        }
        
        .pagination .current {
            background: var(--secondary-color);
            color: white;
            border-color: var(--secondary-color);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        
        .stat-card .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .stat-card .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .form-inline {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1><i class="fa-solid fa-users-gear"></i> Quản Lý Người Dùng</h1>
        
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-label">Tổng số User</div>
                <div class="stat-value"><?= number_format($totalUsers) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Admin</div>
                <div class="stat-value">
                    <?php
                    $adminCountSql = "SELECT COUNT(*) as count FROM users WHERE Role = 1";
                    $adminCountResult = $conn->query($adminCountSql);
                    $adminCount = $adminCountResult ? $adminCountResult->fetch_assoc()['count'] : 0;
                    echo number_format($adminCount);
                    ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Người dùng</div>
                <div class="stat-value">
                    <?php
                    $userCountSql = "SELECT COUNT(*) as count FROM users WHERE Role = 0";
                    $userCountResult = $conn->query($userCountSql);
                    $userCount = $userCountResult ? $userCountResult->fetch_assoc()['count'] : 0;
                    echo number_format($userCount);
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Header Actions -->
        <div class="header-actions">
            <form method="GET" class="search-box">
                <input type="text" name="search" placeholder="Tìm kiếm theo tên hoặc ID..." 
                       value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn"><i class="fa-solid fa-search"></i> Tìm kiếm</button>
                <?php if (!empty($search)): ?>
                    <a href="admin_manage_users.php" class="btn btn-secondary"><i class="fa-solid fa-times"></i> Xóa</a>
                <?php endif; ?>
            </form>
            <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-home"></i> Về Trang Chủ</a>
        </div>
        
        <!-- Users Table -->
        <table class="users-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Ảnh</th>
                    <th>Tên</th>
                    <th>Số dư</th>
                    <th>Vai trò hiện tại</th>
                    <th>Danh hiệu</th>
                    <th>Ngày tạo</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                            <i class="fa-solid fa-inbox" style="font-size: 48px; margin-bottom: 10px;"></i><br>
                            Không tìm thấy người dùng nào
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><strong>#<?= htmlspecialchars($user['Iduser'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td>
                                <img src="<?= !empty($user['ImageURL']) ? htmlspecialchars($user['ImageURL'], ENT_QUOTES, 'UTF-8') : 'images.ico' ?>" 
                                     alt="Avatar" class="avatar-small"
                                     onerror="this.src='images.ico'">
                            </td>
                            <td><strong><?= htmlspecialchars($user['Name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= number_format($user['Money'], 0, ',', '.') ?> VNĐ</td>
                            <td>
                                <span class="role-badge <?= $user['Role'] == 1 ? 'admin' : 'user' ?>">
                                    <?= $user['Role'] == 1 ? '👑 Admin' : '👤 User' ?>
                                </span>
                            </td>
                            <td><?= number_format($user['achievement_count'], 0, ',', '.') ?> danh hiệu</td>
                            <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                            <td>
                                <form method="POST" class="form-inline" 
                                      onsubmit="return confirm('Bạn có chắc muốn thay đổi vai trò của <?= htmlspecialchars($user['Name'], ENT_QUOTES, 'UTF-8') ?>?')">
                                    <input type="hidden" name="user_id" value="<?= $user['Iduser'] ?>">
                                    <select name="role" class="role-select" required>
                                        <option value="0" <?= $user['Role'] == 0 ? 'selected' : '' ?>>👤 User</option>
                                        <option value="1" <?= $user['Role'] == 1 ? 'selected' : '' ?>>👑 Admin</option>
                                    </select>
                                    <?php if ($user['Iduser'] != $userId): ?>
                                        <button type="submit" name="update_role" class="btn btn-small btn-success">
                                            <i class="fa-solid fa-save"></i> Cập nhật
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 12px;">(Tài khoản của bạn)</span>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                        <i class="fa-solid fa-chevron-left"></i> Trước
                    </a>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                        Sau <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

