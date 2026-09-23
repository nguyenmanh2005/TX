<?php
/**
 * 👥 Phân Hệ 2: Quản Trị Người Dùng & Tài Sản Hệ Thống
 * Hợp nhất từ: admin_manage_users.php, admin_manage_items.php, admin_manage_frames.php, admin_crafting.php
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../admin_helper.php';
require_once __DIR__ . '/navbar.php';

$currentUserId = (int)($_SESSION['Iduser'] ?? 0);
if (!isAdmin($conn, $currentUserId)) {
    header("Location: ../Shared/403/403.php");
    exit();
}

$sub = $_GET['sub'] ?? 'users';

if ($sub === 'items') {
    require __DIR__ . '/items.php';
} elseif ($sub === 'frames') {
    require __DIR__ . '/frames.php';
} elseif ($sub === 'crafting') {
    require __DIR__ . '/crafting.php';
} else {
    require __DIR__ . '/users.php';
}
