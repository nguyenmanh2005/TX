<?php
session_start();
$ui_version = $_GET['v'] ?? 'v1';

// Nếu người dùng chọn giao diện V2 (React/Vite)
if ($ui_version === 'v2' || $ui_version === 'new') {
    setcookie('use_new_ui', '2', time() + (86400 * 30), "/");
    header("Location: v2/index.php");
} 
// Nếu người dùng chọn giao diện V3 (Dashboard 3 Cột Sidebar gập mở)
elseif ($ui_version === 'v3') {
    setcookie('use_new_ui', '3', time() + (86400 * 30), "/");
    header("Location: v3/index.php");
} 
// Mặc định về V1 (PHP truyền thống)
else {
    setcookie('use_new_ui', '0', time() - 3600, "/");
    header("Location: index.php");
}
exit();
?>

