<?php
/**
 * 🛡️ Chuyển hướng tự động về Cổng Quản Trị Trung Tâm (Master Admin Portal)
 * Đã được gộp vào: admin_modules/mod_assets.php
 */
$qs = !empty($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : '';
header("Location: admin_dashboard.php?page=assets&sub=frames" . $qs);
exit();
