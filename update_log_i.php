<?php
$file = 'C:\xampp\htdocs\1\Tổng-Quan-Dự-Án\23_07.md';
if (file_exists($file)) {
    $content = file_get_contents($file);
} else {
    $content = "# Báo Cáo Công Việc - Ngày 23/07/2026\n";
}

$taskText = "\n### 4. Task I: Cải thiện Bảo mật (Economy Security)\n";
$taskText .= "- Tạo `economy_helper.php` để chuẩn hóa các giao dịch trừ/cộng GTLM an toàn.\n";
$taskText .= "- Sửa lỗi Race Condition (Spam Request / Auto-Click) tại 5 tính năng chính: Chợ trời (`api_marketplace.php`), Tặng quà (`api_gift.php`), Đấu giá (`api_auction.php`), Chế tạo (`api_crafting.php`) và Lì Xì (`api_red_envelope.php`).\n";
$taskText .= "- Sử dụng kĩ thuật Transaction và Khóa luồng (Row Lock `FOR UPDATE`) để ngăn chặn việc nhân bản vật phẩm hoặc âm số dư.\n";

file_put_contents($file, $content . $taskText);
echo "Log updated!";
?>
