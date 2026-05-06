<?php
session_start();
header('Content-Type: application/json');

require 'db_connect.php';

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
$limit = max(1, min(20, $limit));

$sql = "SELECT sn.*, 
               u.ImageURL AS avatar_url,
               u.avatar_frame_id,
               af.ImageURL AS avatar_frame_url,
               ach.icon AS title_icon
        FROM server_notifications sn
        LEFT JOIN users u ON sn.user_id = u.Iduser
        LEFT JOIN avatar_frames af ON u.avatar_frame_id = af.id
        LEFT JOIN achievements ach ON u.active_title_id = ach.id
        WHERE sn.is_active = 1 
          AND (sn.expires_at IS NULL OR sn.expires_at > NOW())
        ORDER BY sn.created_at DESC 
        LIMIT ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Không thể lấy thông báo"
    ]);
    exit;
}

$stmt->bind_param("i", $limit);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'id' => (int)$row['id'],
        'message' => $row['message'],
        'amount' => (float)$row['amount'],
        'type' => $row['notification_type'],
        'created_at' => $row['created_at'],
        'time_ago' => formatTimeAgo($row['created_at']),
        'user' => [
            'id' => (int)$row['user_id'],
            'name' => $row['user_name'],
            'avatar' => $row['avatar_url'] ?? null,
            'avatar_frame' => $row['avatar_frame_url'] ?? null,
            'title_icon' => $row['title_icon'] ?? null
        ]
    ];
}
$stmt->close();

// Xóa các thông báo đã hết hạn
$deleteSql = "UPDATE server_notifications SET is_active = 0 WHERE expires_at < NOW()";
$conn->query($deleteSql);

echo json_encode([
    "status" => "success",
    "notifications" => $notifications
]);

$conn->close();

function formatTimeAgo(string $dateTime): string {
    $timestamp = strtotime($dateTime);
    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'Vừa xong';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' phút trước';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' giờ trước';
    } else {
        $days = floor($diff / 86400);
        return $days . ' ngày trước';
    }
}
?>

