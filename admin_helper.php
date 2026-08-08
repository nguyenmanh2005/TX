<?php
/**
 * Helper function để kiểm tra quyền admin
 * Hệ thống phân quyền 4 cấp:
 *   Role 0 = User (Dân Thường)
 *   Role 1 = Admin (Quản Trị Viên)        — Dashboard, Analytics, PvP, Tournament, quản lý User thường
 *   Role 2 = Super Admin                   — Thêm vào: Items, Frames, Economy, Crafting, Events
 *   Role 3 = Owner (Nhà Phát Triển)        — Toàn quyền: Advanced Center, cấp quyền bậc cao
 */

/**
 * Lấy Role thực tế của user từ DB.
 * Hàm nội bộ, dùng cho các hàm kiểm tra bên dưới.
 */
function _getRoleFromDB($conn, $userId) {
    if (!$conn || !$userId) return -1;
    $stmt = $conn->prepare("SELECT Role FROM users WHERE Iduser = ?");
    if (!$stmt) return -1;
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($role);
    $hasRow = $stmt->fetch();
    $stmt->close();
    return $hasRow ? (int)$role : -1;
}

/**
 * Kiểm tra quyền Admin (Role = 1 là Nhà phát triển / Cao nhất).
 * Các Role 2, 3 được dành cho mục đích khác (VIP, v.v.) và KHÔNG có quyền admin.
 */
function isAdmin($conn, $userId) {
    return _getRoleFromDB($conn, $userId) == 1;
}

function isSuperAdmin($conn, $userId) {
    return _getRoleFromDB($conn, $userId) == 1;
}

function isOwner($conn, $userId) {
    return _getRoleFromDB($conn, $userId) == 1;
}

/**
 * Lấy Role của user (trả về số nguyên).
 * @return int -1 nếu không tìm thấy, 0-3 tương ứng với cấp bậc
 */
function getUserRole($conn, $userId) {
    return _getRoleFromDB($conn, $userId);
}

/**
 * Lấy tên hiển thị của Role.
 */
function getRoleName($role) {
    switch ((int)$role) {
        case 0: return 'Dân Thường';
        case 1: return '🛡️ Admin / Nhà Phát Triển';
        case 2: return '💎 VIP 1 (Dự kiến)';
        case 3: return '👑 VIP 2 (Dự kiến)';
        default: return 'Không xác định';
    }
}

/**
 * Redirect về 403 nếu không đủ quyền Admin (Role == 1).
 */
function requireAdmin($conn, $userId) {
    if (!isAdmin($conn, $userId)) {
        header("Location: Shared/403/403.php");
        exit();
    }
}

function requireSuperAdmin($conn, $userId) {
    if (!isSuperAdmin($conn, $userId)) {
        header("Location: Shared/403/403.php");
        exit();
    }
}

function requireOwner($conn, $userId) {
    if (!isOwner($conn, $userId)) {
        header("Location: Shared/403/403.php");
        exit();
    }
}
?>
