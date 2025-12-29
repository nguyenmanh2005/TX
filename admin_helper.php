<?php
require 'db_connect.php';
/**
 * Helper function để kiểm tra quyền admin
 * Sử dụng trong các file admin để kiểm tra Role = 1
 */

/**
 * Kiểm tra quyền admin
 * @param mysqli $conn Database connection
 * @param int $userId ID người dùng
 * @return bool True nếu là admin, False nếu không
 */
function isAdmin($conn, $userId) {
    if (!$conn || !$userId) {
        return false;
    }
    
    $roleSql = "SELECT Role FROM users WHERE Iduser = ?";
    $roleStmt = $conn->prepare($roleSql);
    if (!$roleStmt) {
        return false;
    }
    
    $roleStmt->bind_param("i", $userId);
    $roleStmt->execute();
    $roleStmt->bind_result($role);
    $hasRow = $roleStmt->fetch();
    $roleStmt->close();
    
    return ($hasRow && (int)$role === 1);
}

/**
 * Kiểm tra và redirect nếu không phải admin
 * @param mysqli $conn Database connection
 * @param int $userId ID người dùng
 * @param string $redirectUrl URL để redirect (mặc định: index.php)
 */
function requireAdmin($conn, $userId, $redirectUrl = 'index.php') {
    if (!isAdmin($conn, $userId)) {
        header("Location: " . $redirectUrl . "?error=no_permission");
        exit();
    }
}

/**
 * Lấy Role của user
 * @param mysqli $conn Database connection
 * @param int $userId ID người dùng
 * @return int Role (0 = user, 1 = admin)
 */
function getUserRole($conn, $userId) {
    if (!$conn || !$userId) {
        return 0;
    }
    
    $roleSql = "SELECT Role FROM users WHERE Iduser = ?";
    $roleStmt = $conn->prepare($roleSql);
    if (!$roleStmt) {
        return 0;
    }
    
    $roleStmt->bind_param("i", $userId);
    $roleStmt->execute();
    $roleStmt->bind_result($role);
    $hasRow = $roleStmt->fetch();
    $roleStmt->close();
    
    return $hasRow ? (int)$role : 0;
}

?>

