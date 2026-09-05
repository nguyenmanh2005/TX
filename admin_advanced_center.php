<?php
/**
 * Trang Master Trận Địa (admin_advanced_center.php) đã được xóa bỏ theo yêu cầu
 * Tự động chuyển hướng về Admin Dashboard
 */
session_start();
header("Location: admin_dashboard.php");
exit();
