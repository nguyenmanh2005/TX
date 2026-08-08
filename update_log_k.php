<?php
$file = 'C:\xampp\htdocs\1\Tổng-Quan-Dự-Án\23_07.md';
if (file_exists($file)) {
    $content = file_get_contents($file);
} else {
    $content = "# Báo Cáo Công Việc - Ngày 23/07/2026\n";
}

$taskText = "\n### 6. Task K: Mở rộng Đại Lộ Danh Vọng lên Top 10\n";
$taskText .= "- Nâng cấp SQL Query để quét và lấy thông tin Top 10 (Thay vì Top 3) ở cả 3 hạng mục: Lợi nhuận, Đại gia, Dân cày.\n";
$taskText .= "- Giao diện: Top 1-3 vẫn nằm trên Bục Vinh Quang 3D, trong khi Top 4-10 được hiển thị ở bảng danh sách phía dưới bục.\n";
$taskText .= "- CSS: Đã áp dụng hiệu ứng `sparkle-text` (Chữ Lấp Lánh) xịn xò cho Top 1, và `sparkle-gold` cho Top 2-3.\n";
$taskText .= "- Cronjob: Bổ sung gói phần thưởng GTLM, danh hiệu và Khung Chat cho các game thủ đạt hạng 4 đến 10.\n";

file_put_contents($file, $content . $taskText);
echo "Đã ghi log thành công vào Tổng-Quan-Dự-Án/23_07.md";
?>
