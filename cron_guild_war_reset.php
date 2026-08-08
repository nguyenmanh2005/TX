<?php
require_once 'db_connect.php';
// 1. Tạo bảng lịch sử Guild War nếu chưa có (Yêu cầu phải tạo bảng từ SQL trước)
// Khuyến nghị admin chạy lệnh CREATE TABLE guild_war_history trước.
// 2. Lấy danh sách Top Guild hiện tại để lưu lịch sử và trao thưởng
$sqlTop = "SELECT guild_id, points, wins FROM guild_weekly_stats ORDER BY points DESC LIMIT 10";
$res = $conn->query($sqlTop);
$rank = 1;
while ($row = $res->fetch_assoc()) {
    $guildId = $row['guild_id'];
    $points = $row['points'];
    $wins = $row['wins'];
    // Tính thưởng (Ví dụ: Top 1: 10M, Top 2: 5M, Top 3: 2M, Top 4-10: 500k)
    $reward = 0;
    if ($rank == 1) $reward = 10000000;
    elseif ($rank == 2) $reward = 5000000;
    elseif ($rank == 3) $reward = 2000000;
    else $reward = 500000;
    // Lưu vào lịch sử
    $stmt = $conn->prepare("INSERT INTO guild_war_history (guild_id, points, wins, rank, reward_gtlm) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiii", $guildId, $points, $wins, $rank, $reward);
    $stmt->execute();
    $stmt->close();
    // Trao thưởng cho Guild Fund (giả sử có cột guild_xp trong bảng guilds)
    // Nếu không có, có thể chia đều cho các thành viên hoặc cộng vào XP bang hội
    $conn->query("UPDATE guilds SET guild_xp = guild_xp + " . ($points * 10) . " WHERE id = $guildId");
    // --- 🎭 GUILD WAR HIGHLIGHT REEL ---
    if ($rank <= 3) {
        $gData = $conn->query("SELECT Name FROM guilds WHERE id = $guildId")->fetch_assoc();
        $gName = $gData['Name'] ?? 'Bang hội ẩn danh';
        $recapMsg = "🏆 [VINH DANH BANG CHIẾN] Bang hội **$gName** đã xuất sắc đạt TOP $rank mùa giải này với $points điểm chiến công! Phần thưởng " . number_format($reward) . " GTLM đã được trao. 🔥🚀";
        // Gửi tin nhắn vinh danh vào chat tổng (Dùng user_id 0 làm hệ thống)
        $sysAvatar = 'https://cdn-icons-png.flaticon.com/512/1041/1041044.png';
        $stmtChat = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar, created_at) VALUES (0, 'Vệ Binh Trận Địa', ?, ?, NOW())");
        $stmtChat->bind_param("ss", $recapMsg, $sysAvatar);
        $stmtChat->execute();
        $stmtChat->close();
    }
    $rank++;
}
// 3. Reset bảng thống kê tuần
$conn->query("TRUNCATE TABLE guild_weekly_stats");
echo "Guild War Season Reset Successful. History saved and rewards distributed.";
?>
