<?php
/**
 * 🖋️ Lore Helper - Người chép sử của Trận Địa
 * Giúp tự động hóa việc ghi lại các cột mốc quan trọng vào Sử Ký.
 */

function recordServerLore($conn, $type, $title, $description, $importance = 1, $era = 'Kỷ Nguyên Khai Mở') {
    $title = $conn->real_escape_string($title);
    $description = $conn->real_escape_string($description);
    $era = $conn->real_escape_string($era);
    
    try {
        $sql = "INSERT INTO server_lore (era_name, event_title, event_description, event_type, importance_level) 
                VALUES ('$era', '$title', '$description', '$type', $importance)";
        return $conn->query($sql);
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * 🤖 Tự động sinh nội dung sử thi cho Big Win
 */
function generateBigWinLore($userName, $gameName, $amount) {
    $templates = [
        "Trận địa rung chuyển khi huyền thoại @$userName ra chiêu tại $gameName, mang về kho báu trị giá " . number_format($amount) . " GTLM.",
        "Dấu chân vương giả của @$userName đã in hằn tại $gameName sau ván thắng lịch sử " . number_format($amount) . " GTLM.",
        "Người đời sẽ còn nhắc mãi về ngày hôm nay, khi @$userName chinh phục $gameName và húp trọn " . number_format($amount) . " GTLM.",
        "Linh khí của Trận Địa đã hội tụ về @$userName, biến một ván cược tại $gameName thành huyền thoại với giá trị " . number_format($amount) . " GTLM."
    ];
    return $templates[array_rand($templates)];
}

/**
 * 👹 Tự động sinh nội dung cho Boss Kill
 */
function generateBossKillLore($userName, $bossName) {
    return "Sau một cuộc vây hãm kinh thiên động địa, dũng sĩ @$userName đã giáng đòn kết liễu Ma Thần $bossName, mang lại thái bình (và hàng tấn lộc) cho toàn server.";
}
