<?php
// bot_gacha.php
// Tích hợp hệ thống gacha để bot tự sắm Khung Chat và Khung Avatar

function executeBotGacha(mysqli $conn, int $userId, float $money, string $botName) {
    if ($money < 2000000) return; // Chỉ quay khi có trên 2M GTLM
    
    // Tỉ lệ quay mỗi ngày: 10% cơ hội quay
    if (rand(1, 100) > 10) return;
    
    $gachaCost = 100000; // Giá mỗi lần quay khung
    
    // Lấy danh sách khung chat và avatar
    $chatFrames = $conn->query("SELECT id FROM chat_frames ORDER BY RAND() LIMIT 1")->fetch_assoc();
    $avatarFrames = $conn->query("SELECT id FROM avatar_frames ORDER BY RAND() LIMIT 1")->fetch_assoc();
    
    if (!$chatFrames && !$avatarFrames) return;
    
    // Random chọn khung chat hoặc avatar
    $type = rand(0, 1) === 0 ? 'chat' : 'avatar';
    $frameId = null;
    
    if ($type === 'chat' && $chatFrames) {
        $frameId = $chatFrames['id'];
        $check = $conn->query("SELECT id FROM user_chat_frames WHERE user_id = $userId AND frame_id = $frameId");
        if ($check->num_rows == 0) {
            $conn->query("INSERT INTO user_chat_frames (user_id, frame_id) VALUES ($userId, $frameId)");
            $conn->query("UPDATE users SET Money = Money - $gachaCost, chat_frame_id = $frameId WHERE Iduser = $userId");
            writeBotLog("SYSTEM", "INFO", "GACHA", "$botName đã quay trúng và trang bị Khung Chat #$frameId");
        }
    } else if ($type === 'avatar' && $avatarFrames) {
        $frameId = $avatarFrames['id'];
        $check = $conn->query("SELECT id FROM user_avatar_frames WHERE user_id = $userId AND frame_id = $frameId");
        if ($check->num_rows == 0) {
            $conn->query("INSERT INTO user_avatar_frames (user_id, frame_id) VALUES ($userId, $frameId)");
            $conn->query("UPDATE users SET Money = Money - $gachaCost, avatar_frame_id = $frameId WHERE Iduser = $userId");
            writeBotLog("SYSTEM", "INFO", "GACHA", "$botName đã quay trúng và trang bị Khung Avatar #$frameId");
        }
    }
}
