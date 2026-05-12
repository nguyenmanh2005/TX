<?php
session_start();
require_once 'db_connect.php';

$userId = $_SESSION['Iduser'] ?? 0;
if (!$userId) exit(json_encode(['success' => false, 'message' => 'Unauthorized']));

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Lấy thông tin bang hội của user
$userGuildRes = $conn->query("SELECT guild_id, Role FROM users WHERE Iduser = $userId");
$userGuildData = $userGuildRes->fetch_assoc();
$guildId = $userGuildData['guild_id'] ?? 0;
$userRole = $userGuildData['Role'] ?? ''; // Giả sử Role được lưu trực tiếp hoặc join từ bảng members

if (!$guildId) exit(json_encode(['success' => false, 'message' => 'Bạn chưa vào bang!']));

// Kiểm tra quyền (chỉ Leader/Officer mới được nâng cấp)
// Lưu ý: Cần join bảng guild_members để chính xác hơn, ở đây giả định role đơn giản
$memberRes = $conn->query("SELECT role FROM guild_members WHERE guild_id = $guildId AND user_id = $userId");
$memberData = $memberRes->fetch_assoc();
$role = $memberData['role'] ?? 'member';

switch ($action) {
    case 'get_skills':
        $skills = $conn->query("SELECT * FROM guild_skills WHERE guild_id = $guildId");
        $skillList = [];
        while ($row = $skills->fetch_assoc()) {
            $skillList[$row['skill_type']] = $row['level'];
        }
        
        // Mặc định các skill level 0
        $defaults = ['fortune' => 0, 'charisma' => 0, 'unity' => 0];
        foreach ($defaults as $type => $lvl) {
            if (!isset($skillList[$type])) $skillList[$type] = 0;
        }

        echo json_encode(['success' => true, 'skills' => $skillList]);
        break;

    case 'upgrade':
        if ($role != 'leader' && $role != 'officer') {
            exit(json_encode(['success' => false, 'message' => 'Bạn không có quyền nâng cấp!']));
        }

        $type = $_POST['type'] ?? '';
        if (!in_array($type, ['fortune', 'charisma', 'unity'])) {
            exit(json_encode(['success' => false, 'message' => 'Skill không hợp lệ']));
        }

        // Lấy level hiện tại
        $currRes = $conn->query("SELECT level FROM guild_skills WHERE guild_id = $guildId AND skill_type = '$type'");
        $currData = $currRes->fetch_assoc();
        $currLevel = $currData['level'] ?? 0;
        
        if ($currLevel >= 10) exit(json_encode(['success' => false, 'message' => 'Đã đạt level tối đa!']));

        // Tính chi phí (ví dụ: Level 1 = 1000 XP, Level 2 = 2000 XP...)
        $cost = ($currLevel + 1) * 5000;

        // Kiểm tra XP bang hội
        $guildRes = $conn->query("SELECT guild_xp FROM guilds WHERE id = $guildId");
        $guildData = $guildRes->fetch_assoc();
        if ($guildData['guild_xp'] < $cost) {
            exit(json_encode(['success' => false, 'message' => "Bang hội không đủ XP (Cần $cost XP)"]));
        }

        $conn->begin_transaction();
        try {
            // Trừ XP
            $conn->query("UPDATE guilds SET guild_xp = guild_xp - $cost WHERE id = $guildId");

            // Nâng cấp skill
            if ($currData) {
                $conn->query("UPDATE guild_skills SET level = level + 1 WHERE guild_id = $guildId AND skill_type = '$type'");
            } else {
                $conn->query("INSERT INTO guild_skills (guild_id, skill_type, level) VALUES ($guildId, '$type', 1)");
            }

            $conn->commit();
            echo json_encode(['success' => true, 'new_level' => $currLevel + 1]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;
}
