<?php
/**
 * 🎪 Phân Hệ 3: Quản Trị Sự Kiện & Cốt Truyện Thế Giới
 * Hợp nhất từ: admin_Event_Manager.php, admin_flash_event.php, admin_storyline.php
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

$sub = $_GET['sub'] ?? 'main';

if ($sub === 'flash') {
    require __DIR__ . '/flash_event.php';
} elseif ($sub === 'storyline') {
    require __DIR__ . '/storyline.php';
} else {
    require __DIR__ . '/event_manager.php';
}
