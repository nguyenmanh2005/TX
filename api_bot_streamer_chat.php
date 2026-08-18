<?php
// api_bot_streamer_chat.php
require_once __DIR__ . '/db_connect.php';

$action = $_REQUEST['action'] ?? '';

if ($action === 'send_chat') {
    $gameId = (int)($_POST['game_id'] ?? 0);
    $botUserId = (int)($_POST['bot_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    
    // Đơn giản hóa bảo mật bằng secret key nội bộ
    $secret = $_POST['secret'] ?? '';
    if ($secret !== 'gtlm_bot_secret_999') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    if ($gameId > 0 && $botUserId > 0 && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO spectator_chat (game_id, user_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $gameId, $botUserId, $message);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
    }
    exit;
}
echo json_encode(['success' => false, 'message' => 'Invalid action']);
