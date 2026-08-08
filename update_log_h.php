<?php
$file = 'C:\xampp\htdocs\1\Tổng-Quan-Dự-Án\23_07.md';
if (file_exists($file)) {
    $content = file_get_contents($file);
} else {
    $content = "# Báo Cáo Công Việc - Ngày 23/07/2026\n";
}

$taskText = "\n### 3. Task H: Nâng cấp hệ thống Smart AI Bot\n";
$taskText .= "- Xây dựng tính năng **Dynamic Dialogue Engine** (Máy sinh kịch bản ngôn ngữ động) dựa trên file JSON cho các Bot.\n";
$taskText .= "- Tối ưu hóa code `bot_chat_smart_ai.php`: Loại bỏ các câu thoại cứng nhắc (hardcode).\n";
$taskText .= "- Bot giờ đây có thể ráp câu chữ dựa trên trạng thái Cảm xúc (Mood), Lời chào (Greeting), và Ngữ cảnh (Context) để tạo ra hàng ngàn biến thể hội thoại tự nhiên.\n";

file_put_contents($file, $content . $taskText);
?>
