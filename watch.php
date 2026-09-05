<?php
/**
 * Watch Live Proxy - Tự động định tuyến chuẩn xác về LiveStream/watch.php
 * Tránh lỗi 404 đường dẫn tương đối khi gọi từ thư mục gốc
 */
session_start();
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}
$id = isset($_GET['id']) ? (int)$_GET['id'] : 1;
header("Location: LiveStream/watch.php?id=" . $id);
exit();
