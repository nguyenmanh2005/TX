<?php
/**
 * Chuyển hướng tương thích ngược về trang Hồ Sơ Hợp Nhất (in4.php)
 * Đã gộp toàn bộ tính năng hồ sơ mạng xã hội vào in4.php
 */
session_start();
$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: in4.php" . $queryString);
exit();