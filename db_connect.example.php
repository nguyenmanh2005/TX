<?php
/**
 * CẤU HÌNH DATABASE MẪU (Dùng cho Git)
 * Hãy copy file này thành db_connect.php và điền thông tin của bạn.
 */

$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname      = "namedb";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    die("❌ Kết nối thất bại!");
}

$conn->set_charset("utf8mb4");

if (php_sapi_name() !== 'cli') {
    require_once 'tracking.php';
}
?>
