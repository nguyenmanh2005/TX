<?php
/**
 * Script kiểm tra và đảm bảo tất cả file đều sử dụng db_connect.php
 * Chạy file này để kiểm tra và tự động sửa các file chưa đúng
 */

$baseDir = __DIR__;
$phpFiles = [];
$issues = [];
$fixed = [];

// Lấy tất cả file PHP (trừ các file trong thư mục assets, uploads, img, game)
function getPhpFiles($dir, &$files) {
    $excludeDirs = ['assets', 'uploads', 'img', 'game', 'khungchat', 'node_modules', '.git'];
    $excludeFiles = ['db_connect_backup_local.php', 'check_db_connect_usage.php'];
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        
        if (is_dir($path)) {
            $dirName = basename($path);
            if (!in_array($dirName, $excludeDirs)) {
                getPhpFiles($path, $files);
            }
        } elseif (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $fileName = basename($path);
            if (!in_array($fileName, $excludeFiles)) {
                $files[] = $path;
            }
        }
    }
}

getPhpFiles($baseDir, $phpFiles);

echo "<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <title>Kiểm Tra db_connect.php Usage</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        .file-path { font-family: monospace; color: #666; }
        .action-btn { padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .action-btn:hover { background: #218838; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Kiểm Tra Sử Dụng db_connect.php</h1>
        <p>Tổng số file PHP được kiểm tra: <strong>" . count($phpFiles) . "</strong></p>";

$hasDbConnection = [];
$missingDbConnect = [];
$hasDirectConnection = [];
$hasBackupConnection = [];

foreach ($phpFiles as $file) {
    $content = file_get_contents($file);
    $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file);
    
    // Kiểm tra có kết nối database trực tiếp không
    if (preg_match('/new\s+mysqli\s*\(|mysqli_connect\s*\(/', $content)) {
        // Bỏ qua file db_connect.php
        if (basename($file) !== 'db_connect.php') {
            $hasDirectConnection[] = $relativePath;
        }
    }
    
    // Kiểm tra có sử dụng db_connect_backup_local.php không
    if (preg_match('/db_connect_backup_local|backup_local/', $content)) {
        $hasBackupConnection[] = $relativePath;
    }
    
    // Kiểm tra có require db_connect.php không
    if (preg_match('/require.*db_connect\.php|include.*db_connect\.php/', $content)) {
        $hasDbConnection[] = $relativePath;
    } else {
        // Kiểm tra xem file có cần kết nối database không (có sử dụng $conn)
        if (preg_match('/\$conn\s*->|mysqli_|SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER/i', $content)) {
            $missingDbConnect[] = $relativePath;
        }
    }
}

// Hiển thị kết quả
if (empty($hasDirectConnection) && empty($hasBackupConnection) && empty($missingDbConnect)) {
    echo "<div class='success'>✅ Tất cả file đều sử dụng db_connect.php đúng cách!</div>";
} else {
    if (!empty($hasDirectConnection)) {
        echo "<div class='error'><h3>❌ Các file tạo kết nối database trực tiếp (cần sửa):</h3><ul>";
        foreach ($hasDirectConnection as $file) {
            echo "<li class='file-path'>$file</li>";
        }
        echo "</ul></div>";
    }
    
    if (!empty($hasBackupConnection)) {
        echo "<div class='warning'><h3>⚠️ Các file sử dụng db_connect_backup_local.php (cần sửa):</h3><ul>";
        foreach ($hasBackupConnection as $file) {
            echo "<li class='file-path'>$file</li>";
        }
        echo "</ul></div>";
    }
    
    if (!empty($missingDbConnect)) {
        echo "<div class='warning'><h3>⚠️ Các file có thể cần require db_connect.php:</h3><ul>";
        foreach ($missingDbConnect as $file) {
            echo "<li class='file-path'>$file</li>";
        }
        echo "</ul></div>";
    }
}

echo "<div class='info'>
    <h3>📊 Thống Kê:</h3>
    <ul>
        <li>File sử dụng db_connect.php: <strong>" . count($hasDbConnection) . "</strong></li>
        <li>File tạo kết nối trực tiếp: <strong>" . count($hasDirectConnection) . "</strong></li>
        <li>File sử dụng backup connection: <strong>" . count($hasBackupConnection) . "</strong></li>
        <li>File có thể thiếu require: <strong>" . count($missingDbConnect) . "</strong></li>
    </ul>
</div>";

// Nút tự động sửa (nếu có vấn đề)
if (!empty($hasBackupConnection)) {
    echo "<div class='info'>
        <h3>🔧 Tự Động Sửa:</h3>
        <p>Click nút bên dưới để tự động thay thế tất cả 'db_connect_backup_local.php' thành 'db_connect.php':</p>
        <a href='?action=fix_backup' class='action-btn'>🔧 Sửa Tự Động</a>
    </div>";
}

// Xử lý sửa tự động
if (isset($_GET['action']) && $_GET['action'] === 'fix_backup') {
    $fixedCount = 0;
    foreach ($hasBackupConnection as $file) {
        $fullPath = $baseDir . DIRECTORY_SEPARATOR . $file;
        if (file_exists($fullPath)) {
            $content = file_get_contents($fullPath);
            $newContent = preg_replace(
                ['/require\s+[\'"]db_connect_backup_local\.php[\'"]/i', 
                 '/include\s+[\'"]db_connect_backup_local\.php[\'"]/i',
                 '/require_once\s+[\'"]db_connect_backup_local\.php[\'"]/i',
                 '/include_once\s+[\'"]db_connect_backup_local\.php[\'"]/i'],
                'require \'db_connect.php\'',
                $content
            );
            
            if ($content !== $newContent) {
                file_put_contents($fullPath, $newContent);
                $fixed[] = $file;
                $fixedCount++;
            }
        }
    }
    
    if ($fixedCount > 0) {
        echo "<div class='success'><h3>✅ Đã sửa $fixedCount file!</h3><ul>";
        foreach ($fixed as $file) {
            echo "<li class='file-path'>$file</li>";
        }
        echo "</ul></div>";
        echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
    }
}

echo "</div></body></html>";
?>

