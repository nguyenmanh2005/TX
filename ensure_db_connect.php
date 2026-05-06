<?php
/**
 * Helper script để đảm bảo tất cả file đều sử dụng db_connect.php
 * Chạy script này để tự động sửa các file chưa đúng
 */

$baseDir = __DIR__;
$fixedFiles = [];
$skippedFiles = ['db_connect.php', 'db_connect_backup_local.php', 'check_db_connect_usage.php', 'ensure_db_connect.php'];

// Lấy tất cả file PHP
function getAllPhpFiles($dir, &$files, $excludeDirs = ['assets', 'uploads', 'img', 'game', 'khungchat', 'node_modules', '.git']) {
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        
        if (is_dir($path)) {
            $dirName = basename($path);
            if (!in_array($dirName, $excludeDirs)) {
                getAllPhpFiles($path, $files, $excludeDirs);
            }
        } elseif (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $files[] = $path;
        }
    }
}

$phpFiles = [];
getAllPhpFiles($baseDir, $phpFiles);

echo "Đang kiểm tra " . count($phpFiles) . " file PHP...\n\n";

foreach ($phpFiles as $file) {
    $fileName = basename($file);
    if (in_array($fileName, $skippedFiles)) {
        continue;
    }
    
    $content = file_get_contents($file);
    $originalContent = $content;
    $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file);
    $needsFix = false;
    
    // 1. Thay thế db_connect_backup_local.php
    if (preg_match('/db_connect_backup_local/i', $content)) {
        $content = preg_replace(
            [
                '/require\s+[\'"]db_connect_backup_local\.php[\'"]/i',
                '/include\s+[\'"]db_connect_backup_local\.php[\'"]/i',
                '/require_once\s+[\'"]db_connect_backup_local\.php[\'"]/i',
                '/include_once\s+[\'"]db_connect_backup_local\.php[\'"]/i'
            ],
            "require 'db_connect.php'",
            $content
        );
        $needsFix = true;
    }
    
    // 2. Thay thế các pattern require/include khác nhau thành require 'db_connect.php'
    // Chỉ sửa nếu chưa có require db_connect.php
    if (!preg_match('/require.*db_connect\.php|include.*db_connect\.php/i', $content)) {
        // Kiểm tra xem file có sử dụng database không
        if (preg_match('/\$conn|mysqli_|SELECT|INSERT|UPDATE|DELETE/i', $content)) {
            // Tìm vị trí phù hợp để thêm require (sau <?php hoặc session_start)
            $lines = explode("\n", $content);
            $insertPosition = -1;
            
            for ($i = 0; $i < count($lines); $i++) {
                // Tìm dòng <?php
                if (preg_match('/^\s*<\?php/i', $lines[$i])) {
                    $insertPosition = $i + 1;
                    break;
                }
            }
            
            // Nếu không tìm thấy <?php, tìm session_start
            if ($insertPosition == -1) {
                for ($i = 0; $i < count($lines); $i++) {
                    if (preg_match('/session_start/i', $lines[$i])) {
                        $insertPosition = $i + 1;
                        break;
                    }
                }
            }
            
            // Nếu vẫn không tìm thấy, thêm sau dòng đầu tiên
            if ($insertPosition == -1) {
                $insertPosition = 1;
            }
            
            // Kiểm tra xem đã có require db_connect chưa
            $hasRequire = false;
            for ($i = 0; $i < min($insertPosition + 5, count($lines)); $i++) {
                if (preg_match('/require.*db_connect/i', $lines[$i])) {
                    $hasRequire = true;
                    break;
                }
            }
            
            if (!$hasRequire) {
                array_splice($lines, $insertPosition, 0, "require 'db_connect.php';");
                $content = implode("\n", $lines);
                $needsFix = true;
            }
        }
    }
    
    // 3. Chuẩn hóa require 'db_connect.php' (đảm bảo dùng dấu nháy đơn)
    if (preg_match('/require\s+["\']db_connect\.php["\']/i', $content)) {
        $content = preg_replace(
            '/require\s+["\']db_connect\.php["\']/i',
            "require 'db_connect.php'",
            $content
        );
        // Chỉ đánh dấu cần fix nếu có thay đổi
        if ($content !== $originalContent) {
            $needsFix = true;
        }
    }
    
    // Lưu file nếu có thay đổi
    if ($needsFix && $content !== $originalContent) {
        file_put_contents($file, $content);
        $fixedFiles[] = $relativePath;
        echo "✅ Đã sửa: $relativePath\n";
    }
}

echo "\n";
if (count($fixedFiles) > 0) {
    echo "✅ Hoàn thành! Đã sửa " . count($fixedFiles) . " file:\n";
    foreach ($fixedFiles as $file) {
        echo "   - $file\n";
    }
} else {
    echo "✅ Tất cả file đã sử dụng db_connect.php đúng cách!\n";
}

echo "\n📝 Lưu ý: File db_connect_backup_local.php vẫn được giữ lại để backup.\n";
echo "   Tất cả file khác đã được chuyển sang sử dụng db_connect.php.\n";

?>

