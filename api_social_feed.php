<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập!']);
    exit();
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? 'get';

if ($action === 'post') {
    $content = $_POST['content'] ?? '';
    if (empty($content)) {
        echo json_encode(['status' => 'error', 'message' => 'Nội dung không được để trống!']);
        exit();
    }

    $sql = "INSERT INTO social_feed (user_id, content, created_at) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $userId, $content);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Đã đăng bài!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi database: ' . $conn->error]);
    }
} elseif ($action === 'like') {
    $feedId = (int)$_POST['feed_id'];
    $sql = "INSERT IGNORE INTO social_feed_likes (feed_id, user_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $feedId, $userId);
    $stmt->execute();
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ!']);
}
