<?php
/**
 * 🛡️ Security Gateway v1.0
 * Cho phép Admin truy cập các file tài liệu/log nhạy cảm, chặn người dùng thường.
 */
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/admin_helper.php';

$file = $_GET['file'] ?? '';
// Chuẩn hóa đường dẫn để tránh Directory Traversal
$baseDir = realpath(__DIR__ . '/bot');
$requestedPath = realpath($baseDir . '/' . ltrim($file, '/'));

// 1. Kiểm tra file có nằm trong thư mục bot không
if (!$requestedPath || strpos($requestedPath, $baseDir) !== 0) {
    header("HTTP/1.1 403 Forbidden");
    exit("🛡️ Security Alert: Bạn không có quyền truy cập khu vực này.");
}

// 2. Kiểm tra file có phải là file nhạy cảm tuyệt đối không (như .env.php hay config.php)
$basename = basename($requestedPath);
if ($basename === '.env.php' || $basename === 'config.php' || $basename === 'tester_session.txt') {
    header("HTTP/1.1 403 Forbidden");
    exit("🛡️ Critical: File này không thể truy cập trực tiếp vì lý do an toàn tuyệt đối.");
}


// 3. Kiểm tra quyền Admin
if (!isAdmin($conn, (int)($_SESSION['Iduser'] ?? 0))) {
    header("Location: login.php?error=unauthorized");
    exit();
}

// 4. Trả về nội dung file nếu là Admin
if (file_exists($requestedPath)) {
    $ext = pathinfo($requestedPath, PATHINFO_EXTENSION);
    $contentType = 'text/plain';
    
    if ($ext === 'json') $contentType = 'application/json';
    if ($ext === 'log' || $ext === 'txt') $contentType = 'text/plain';
    
    header('Content-Type: ' . $contentType . '; charset=utf-8');
    readfile($requestedPath);
} else {
    header("HTTP/1.1 404 Not Found");
    echo "File không tồn tại.";
}
