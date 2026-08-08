<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Lấy thông tin Guild của User
$stmt = $conn->prepare("SELECT * FROM guild_members WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

$guildId = $member['guild_id'] ?? 0;

switch ($action) {
    case 'get_map':
        // Lấy tất cả lãnh địa
        $territories = $conn->query("SELECT t.*, g.name as guild_name, g.tag as guild_tag FROM guild_territories t LEFT JOIN guilds g ON t.guild_id = g.id")->fetch_all(MYSQLI_ASSOC);
        echo json_encode([
            'success' => true,
            'territories' => $territories,
            'my_guild_id' => $guildId,
            'role' => $member['role'] ?? 'member'
        ]);
        break;

    case 'capture':
        if (!$guildId) {
            echo json_encode(['success' => false, 'message' => 'Bạn chưa tham gia Bang hội!']);
            exit;
        }
        if ($member['role'] !== 'leader' && $member['role'] !== 'officer') {
            echo json_encode(['success' => false, 'message' => 'Chỉ Bang chủ hoặc Phó bang mới được chiếm lãnh địa!']);
            exit;
        }

        $territoryId = (int)($_POST['territory_id'] ?? 0);

        $conn->begin_transaction();
        try {
            // Lock guild and territory
            $stmt = $conn->prepare("SELECT * FROM guilds WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $guildId);
            $stmt->execute();
            $guild = $stmt->get_result()->fetch_assoc();

            $stmt = $conn->prepare("SELECT * FROM guild_territories WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $territoryId);
            $stmt->execute();
            $territory = $stmt->get_result()->fetch_assoc();

            if (!$territory) throw new Exception("Lãnh địa không tồn tại!");
            
            if ($territory['guild_id'] == $guildId) {
                throw new Exception("Bang của bạn đã chiếm lĩnh lãnh địa này rồi!");
            }

            $cost = $territory['required_points'];
            if ($territory['guild_id'] !== null) {
                $cost *= 2; // Gấp đôi điểm để cướp từ bang khác
            }

            if ($guild['experience'] < $cost) {
                throw new Exception("Bang hội không đủ điểm EXP (Yêu cầu: $cost EXP). Hãy chơi game để cày thêm EXP!");
            }

            // Trừ EXP bang
            $stmt = $conn->prepare("UPDATE guilds SET experience = experience - ? WHERE id = ?");
            $stmt->bind_param("ii", $cost, $guildId);
            $stmt->execute();

            // Sang tên lãnh địa
            $stmt = $conn->prepare("UPDATE guild_territories SET guild_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $guildId, $territoryId);
            $stmt->execute();

            // Log
            $logMsg = "Bang hội [{$guild['tag']}] {$guild['name']} đã xuất quân chiếm lĩnh lãnh địa {$territory['name']} thành công!";
            require_once 'vocabulary_helper.php';
            $logMsg = VocabularyHelper::mask($logMsg);
            $sysId = 0; $sysName = 'Hệ Thống Lãnh Địa'; $sysAvatar = 'https://cdn-icons-png.flaticon.com/512/1041/1041044.png';
            $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("isss", $sysId, $sysName, $logMsg, $sysAvatar);
            $stmt->execute();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Chiếm lĩnh thành công!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
}
?>
