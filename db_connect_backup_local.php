<?php
/**
 * BACKUP: File kết nối database local (localhost)
 * Lưu lại để sử dụng khi phát triển local
 */

// Kết nối database LOCAL (XAMPP/WAMP)
$servername = "localhost";
$dbUsername = "root";
$dbPassword = "";
$dbname = "taixiu";

$conn = new mysqli($servername, $dbUsername, $dbPassword, $dbname);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["status" => "error", "message" => "Kết nối cơ sở dữ liệu thất bại: " . $conn->connect_error]);
    exit();
}
?>

