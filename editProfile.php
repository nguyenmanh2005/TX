<?php
/**
 * Chuyển hướng tương thích ngược về trang Hồ Sơ Hợp Nhất (in4.php)
 * Đã gộp chức năng chỉnh sửa hồ sơ vào trực tiếp in4.php?tab=edit
 */
session_start();
header("Location: in4.php?tab=edit");
exit();
