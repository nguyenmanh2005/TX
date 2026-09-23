<?php
/**
 * ⚔️ Phân Hệ 4: Quản Trị Giải Đấu & Đấu Trường PvP
 * Hợp nhất từ: admin_tournaments.php, admin_pvp_manager.php
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

$sub = $_GET['sub'] ?? 'tournaments';

if ($sub === 'pvp') {
    require __DIR__ . '/pvp_manager.php';
} else {
    require __DIR__ . '/tournaments.php';
}
