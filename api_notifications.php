<?php
session_start();
require_once 'db_connect.php';

$userId = $_SESSION['Iduser'] ?? 0;
if (!$userId) exit(json_encode(['success' => false]));

// 1. Khởi tạo bảng thông báo
$setup = "
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(255),
    message TEXT,
    type VARCHAR(50), -- e.g., 'battle_pass', 'guild', 'system', 'pvp'
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (is_read)
);
";
// $conn->query($setup);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_notifications':
        $limit = (int) ($_GET['limit'] ?? 10);
        $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ?");
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $notifs = [];
        while ($row = $res->fetch_assoc()) $notifs[] = $row;
        
        // Đếm số thông báo chưa đọc
        $unread = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $userId AND is_read = 0")->fetch_assoc()['count'];
        
        echo json_encode(['success' => true, 'notifications' => $notifs, 'unread_count' => (int)$unread]);
        break;

    case 'mark_as_read':
        $notifId = (int) ($_POST['id'] ?? 0);
        if ($notifId > 0) {
            $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $notifId AND user_id = $userId");
        } else {
            $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $userId");
        }
        echo json_encode(['success' => true]);
        break;
}

// Hàm helper để gửi thông báo (có thể gọi từ bất cứ đâu)
function sendNotification(mysqli $conn, int $userId, string $title, string $message, string $type = 'system') {
    require_once 'vocabulary_helper.php';
    $title = VocabularyHelper::mask($title);
    $message = VocabularyHelper::mask($message);
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $title, $message, $type);
    $stmt->execute();
    $stmt->close();
}
