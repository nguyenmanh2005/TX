<?php
/**
 * API Red Envelope Rain & Mystery Drops (4B)
 * [NEW FILE] - Endpoint xử lý tạo, giật lì xì & giả lập Bot AI tranh lộc
 * Hoạt động độc lập, không ghi đè lên các file gốc
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/red_envelope_manager.php';

$action = $_GET['action'] ?? 'list';
$manager = new RedEnvelopeManager($conn);

// Kiểm tra bảng
if (!$manager->checkTables()) {
    echo json_encode([
        'success' => false,
        'table_ready' => false,
        'message' => 'Bảng red_envelopes hoặc red_envelope_claims chưa tồn tại. Vui lòng chạy block SQL bên dưới trong phpMyAdmin!'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'list') {
    $activeList = $manager->getActiveEnvelopes();

    // Giả lập Bot AI tranh lộc (chỉ kích hoạt nếu có bao lì xì đang active)
    $botActionLog = '';
    if (!empty($activeList) && mt_rand(1, 100) <= 35) {
        $envToGrab = $activeList[array_rand($activeList)];
        $bots = [
            ['id' => 9001, 'username' => 'Cụ Giáo', 'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=cugia&style=circle'],
            ['id' => 9002, 'username' => 'Đại Gia Whale', 'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=whale&style=circle'],
            ['id' => 9003, 'username' => 'Thánh Nổ Plinko', 'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=plinko&style=circle'],
            ['id' => 9004, 'username' => 'Bé Simp Lởm', 'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=simp&style=circle']
        ];
        $picker = $bots[array_rand($bots)];

        // Cho Bot giật thử
        $botRes = $manager->claimEnvelope($envToGrab['id'], $picker['id'], $picker['username'], $picker['avatar'], true);
        if ($botRes['success']) {
            $botActionLog = "🤖 Bot <b>{$picker['username']}</b> vừa giật được <b>" . number_format($botRes['amount']) . " GTLM</b> từ lì xì của {$envToGrab['sender_name']}!";
            
            // Bot chat cảm ơn lên Kênh Chat Thế Giới
            $thanksText = "@{$envToGrab['sender_name']} Cảm ơn đạo hữu phát lộc nhé! Nick ta vừa giật húp " . number_format($botRes['amount']) . " GTLM rực rỡ!";
            $stmtChat = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar, created_at) VALUES (?, ?, ?, ?, NOW())");
            if ($stmtChat) {
                $stmtChat->bind_param("isss", $picker['id'], $picker['username'], $thanksText, $picker['avatar']);
                $stmtChat->execute();
                $stmtChat->close();
            }
        }
    }

    echo json_encode([
        'success' => true,
        'table_ready' => true,
        'envelopes' => $activeList,
        'bot_action' => $botActionLog,
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'create') {
    $senderId = isset($_SESSION['Iduser']) ? (int)$_SESSION['Iduser'] : 1;
    $senderName = isset($_SESSION['Name']) ? $_SESSION['Name'] : 'Admin Phát Lộc';
    $senderAvatar = isset($_SESSION['Avatar']) ? $_SESSION['Avatar'] : 'img/avatar_default.png';
    $isBot = isset($_POST['is_mock']) && $_POST['is_mock'] === 'true';

    if ($isBot) {
        $senderName = $_POST['mock_sender_name'] ?? 'Đại Gia Whale';
        $senderAvatar = $_POST['mock_sender_avatar'] ?? 'https://api.dicebear.com/7.x/avataaars/svg?seed=whale&style=circle';
        $senderId = mt_rand(9001, 9005);
    }

    $totalAmount = floatval($_POST['total_amount'] ?? 100000);
    $totalCount = intval($_POST['total_count'] ?? 5);
    $message = trim($_POST['message'] ?? 'Phát lộc rực rỡ, chúc đạo hữu húp đậm GTLM!');
    $type = $_POST['type'] ?? 'random';

    $res = $manager->createEnvelope($senderId, $senderName, $senderAvatar, $totalAmount, $totalCount, $message, $type, $isBot);
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'claim') {
    $envelopeId = intval($_POST['envelope_id'] ?? 0);
    if ($envelopeId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID bao lì xì không hợp lệ!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $userId = isset($_SESSION['Iduser']) ? (int)$_SESSION['Iduser'] : 1;
    $username = isset($_SESSION['Name']) ? $_SESSION['Name'] : 'Admin Tester';
    $avatar = isset($_SESSION['Avatar']) ? $_SESSION['Avatar'] : 'img/avatar_default.png';
    $isBot = isset($_POST['is_mock']) && $_POST['is_mock'] === 'true';

    if ($isBot) {
        $userId = mt_rand(100, 999);
        $username = "Đạo Hữu Khách #{$userId}";
    }

    $res = $manager->claimEnvelope($envelopeId, $userId, $username, $avatar, $isBot);
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action không hợp lệ'], JSON_UNESCAPED_UNICODE);
exit;
?>
