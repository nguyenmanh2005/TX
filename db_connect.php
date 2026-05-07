<?php
// Bật hiển thị lỗi để debug trên server
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * CẤU HÌNH DATABASE TỰ ĐỘNG (LOCAL & HOSTING)
 */
if (php_sapi_name() === 'cli' || 
    (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] == 'localhost') || 
    (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false) ||
    (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] == '127.0.0.1')) {
    // --- CẤU HÌNH LOCAL (XAMPP) ---
    $servername = "localhost";
    $db_username = "root";
    $db_password = "";
    $dbname      = "vdkmnuaahosting_taixiu"; // Đảm bảo bạn đã tạo DB này trong phpMyAdmin local
} else {
    // --- CẤU HÌNH HOSTING ---
    $servername = "localhost";
    $db_username = "vdkmnuaahosting_manh1";
    $db_password = "Alohaka3@";
    $dbname      = "vdkmnuaahosting_taixiu";
}

// Thực hiện kết nối
$conn = new mysqli($servername, $db_username, $db_password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("❌ Kết nối thất bại! <br>Lỗi: " . $conn->connect_error . " <br><br><b>Hướng dẫn:</b> Nếu chạy local, hãy đảm bảo bạn đã bật MySQL trong XAMPP và tạo database tên là <b>$dbname</b>.");
}

$conn->set_charset("utf8mb4");

// Website Tracking System (Chỉ chạy khi trên Web, không chạy khi dùng Bot .bat)
if (php_sapi_name() !== 'cli') {
    require_once 'tracking.php';
}
?>