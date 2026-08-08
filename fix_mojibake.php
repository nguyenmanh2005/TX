<?php
$files = [
    'admin_manage_frames.php', 
    'admin_manage_items.php', 
    'admin_analytics.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        // Bước 1: Xóa chuỗi rác BOM (nếu có) do Powershell sinh ra
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        
        // Cần lấy lại dữ liệu byte gốc trước khi bị Powershell chuyển sang chuỗi ANSI
        // Trong PHP 8.2 trở xuống, utf8_decode làm việc này (UTF-8 -> ISO-8859-1)
        // Trong PHP 8.2+, dùng mb_convert_encoding($content, 'ISO-8859-1', 'UTF-8')
        $fixed = mb_convert_encoding($content, 'ISO-8859-1', 'UTF-8');
        
        file_put_contents($file, $fixed);
        echo "Fixed $file\n";
    }
}
echo "Done.";
?>
