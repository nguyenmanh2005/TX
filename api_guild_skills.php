<?php
session_start();
require_once 'db_connect.php';

$userId = (int)($_SESSION['Iduser'] ?? 0);
if (!$userId) exit(json_encode(['success' => false, 'message' => 'Unauthorized']));

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Lấy thông tin bang hội của user sử dụng prepared statement
$stmt = $conn->prepare("SELECT guild_id, Role FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userGuildData = $stmt->get_result()->fetch_assoc();
$stmt->close();

$guildId = (int)($userGuildData['guild_id'] ?? 0);
if (!$guildId) exit(json_encode(['success' => false, 'message' => 'Bạn chưa vào bang!']));

// Kiểm tra quyền (chỉ Leader/Officer mới được nâng cấp)
$stmt = $conn->prepare("SELECT role FROM guild_members WHERE guild_id = ? AND user_id = ?");
$stmt->bind_param("ii", $guildId, $userId);
$stmt->execute();
$memberData = $stmt->get_result()->fetch_assoc();
$stmt->close();

$role = $memberData['role'] ?? 'member';

switch ($action) {
    case 'get_skills':
        $stmt = $conn->prepare("SELECT * FROM guild_skills WHERE guild_id = ?");
        $stmt->bind_param("i", $guildId);
        $stmt->execute();
        $skills = $stmt->get_result();
        $skillList = [];
        while ($row = $skills->fetch_assoc()) {
            $skillList[$row['skill_type']] = (int)$row['level'];
        }
        $stmt->close();
        
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
        $allowed = ['fortune', 'charisma', 'unity'];
        if (!in_array($type, $allowed)) {
            exit(json_encode(['success' => false, 'message' => 'Skill không hợp lệ']));
        }

        // Lấy level hiện tại bằng prepared statement
        $stmt = $conn->prepare("SELECT level FROM guild_skills WHERE guild_id = ? AND skill_type = ?");
        $stmt->bind_param("is", $guildId, $type);
        $stmt->execute();
        $currData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $currLevel = $currData ? (int)$currData['level'] : 0;
        if ($currLevel >= 10) exit(json_encode(['success' => false, 'message' => 'Đã đạt level tối đa!']));

        // Tính chi phí
        $cost = ($currLevel + 1) * 5000;

        // Kiểm tra XP bang hội
        $stmt = $conn->prepare("SELECT guild_xp FROM guilds WHERE id = ?");
        $stmt->bind_param("i", $guildId);
        $stmt->execute();
        $guildData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$guildData || (int)$guildData['guild_xp'] < $cost) {
            exit(json_encode(['success' => false, 'message' => "Bang hội không đủ XP (Cần $cost XP)"]));
        }

        $conn->begin_transaction();
        try {
            // Trừ XP
            $stmt = $conn->prepare("UPDATE guilds SET guild_xp = guild_xp - ? WHERE id = ?");
            $stmt->bind_param("ii", $cost, $guildId);
            $stmt->execute();
            $stmt->close();

            // Nâng cấp skill
            if ($currData) {
                $stmt = $conn->prepare("UPDATE guild_skills SET level = level + 1 WHERE guild_id = ? AND skill_type = ?");
                $stmt->bind_param("is", $guildId, $type);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO guild_skills (guild_id, skill_type, level) VALUES (?, ?, 1)");
                $stmt->bind_param("is", $guildId, $type);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            echo json_encode(['success' => true, 'new_level' => $currLevel + 1]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;
}
?>
