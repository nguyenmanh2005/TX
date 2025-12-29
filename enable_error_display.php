<?php
/**
 * Bật hiển thị lỗi để debug
 * Xóa file này sau khi fix xong lỗi
 */

// Tạo file .htaccess để bật error display
$htaccessContent = <<<'EOT'
# Bật hiển thị lỗi (CHỈ DÙNG KHI DEBUG)
php_flag display_errors on
php_flag display_startup_errors on
php_value error_reporting E_ALL

# Hoặc thêm vào đầu file PHP:
# error_reporting(E_ALL);
# ini_set('display_errors', 1);
EOT;

file_put_contents('.htaccess', $htaccessContent);

echo "<h1>✅ Đã bật hiển thị lỗi</h1>";
echo "<p>File .htaccess đã được tạo. Bây giờ truy cập index.php để xem lỗi cụ thể.</p>";
echo "<p><strong>⚠️ Lưu ý:</strong> Xóa file .htaccess hoặc comment các dòng trên sau khi fix xong!</p>";
echo "<p><a href='index.php'>Thử lại index.php</a></p>";
echo "<p><a href='check_error_logs.php'>Kiểm tra lỗi chi tiết</a></p>";
?>

