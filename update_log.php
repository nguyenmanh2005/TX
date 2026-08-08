<?php
$dir = __DIR__ . '/Tổng-Quan-Dự-Án';
if (!is_dir($dir)) mkdir($dir, 0777, true);
$file = $dir . '/23_07.md';

$content = '';
if (file_exists($file)) {
    $content = file_get_contents($file);
}

$taskNumber = 1;
// Count existing tasks to increment the number
if (preg_match_all('/^### (\d+)\./m', $content, $matches)) {
    $taskNumber = max($matches[1]) + 1;
}

$todayTasks = "\n\n### {$taskNumber}. Task F: Tối ưu UI Quản trị (Admin Panel)\n";
$todayTasks .= "- Gộp logic xử lý các trang `frame_add.php`, `frame_edit.php`, `item_add.php`, `item_edit.php` vào Modal duy nhất trên các trang chính.\n";
$todayTasks .= "- Loại bỏ triệt để lỗi Font Mojibake tiếng Việt.\n\n";

$taskNumber++;
$todayTasks .= "### {$taskNumber}. Task G: Tối ưu Dashboard Mobile\n";
$todayTasks .= "- Thêm Media Queries Responsive cho trang `admin_dashboard.php`.\n";
$todayTasks .= "- Tối ưu khoảng cách, font chữ, bố cục hiển thị trên màn hình dọc (mobile).\n";

file_put_contents($file, $content . $todayTasks);
echo "Updated $file";
?>
