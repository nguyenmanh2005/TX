<?php
session_start();
require_once 'db_connect.php';

$userId = $_SESSION['Iduser'] ?? 0;
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// 1. Tạo bảng guild_messages nếu chưa có
$sqlCreate = "CREATE TABLE IF NOT EXISTS guild_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guild_id INT,
    user_id INT,
    username VARCHAR(100),
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (guild_id)
)";
$conn->query($sqlCreate);

// 2. Lấy guild_id của user
$userRes = $conn->query("SELECT guild_id FROM users WHERE Iduser = $userId");
$userData = $userRes->fetch_assoc();
$guildId = $userData['guild_id'] ?? 0;

if (!$guildId && $_GET['action'] !== 'get_status') {
    echo json_encode(['success' => false, 'message' => 'Bạn chưa gia nhập Bang hội nào!']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_status':
        echo json_encode(['success' => true, 'in_guild' => $guildId > 0]);
        break;

    case 'load':
        $lastId = (int) ($_GET['last_id'] ?? 0);
        $stmt = $conn->prepare("SELECT gm.*, u.ImageURL as avatar 
                               FROM guild_messages gm
                               JOIN users u ON gm.user_id = u.Iduser
                               WHERE gm.guild_id = ? AND gm.id > ? 
                               ORDER BY gm.id ASC LIMIT 50");
        $stmt->bind_param("ii", $guildId, $lastId);
        $stmt->execute();
        $res = $stmt->get_result();
        $messages = [];
        while ($row = $res->fetch_assoc()) {
            $messages[] = $row;
        }
        echo json_encode(['success' => true, 'messages' => $messages]);
        break;

    case 'send':
        $message = trim($_POST['message'] ?? '');
        if (empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Nội dung trống']);
            break;
        }

        require_once 'vocabulary_helper.php';
        $message = VocabularyHelper::mask($message);

        $username = $_SESSION['Name'];
        $stmt = $conn->prepare("INSERT INTO guild_messages (guild_id, user_id, username, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $guildId, $userId, $username, $message);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi gửi tin nhắn']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action not found']);
}
