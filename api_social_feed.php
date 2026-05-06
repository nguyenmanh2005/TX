<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập!']);
    exit();
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? '';

if ($action === 'toggle_like') {
    $feedId = (int)($_POST['feed_id'] ?? 0);
    
    if ($feedId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID feed không hợp lệ!']);
        exit();
    }
    
    // Kiểm tra đã like chưa
    $sql = "SELECT id FROM social_feed_likes WHERE feed_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $feedId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $isLiked = $result->num_rows > 0;
    $stmt->close();
    
    if ($isLiked) {
        // Unlike
        $sql = "DELETE FROM social_feed_likes WHERE feed_id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $feedId, $userId);
        $stmt->execute();
        $stmt->close();
        
        // Cập nhật likes_count
        $sql = "UPDATE social_feed SET likes_count = GREATEST(0, likes_count - 1) WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $feedId);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['status' => 'success', 'message' => 'Đã bỏ like!']);
    } else {
        // Like
        $sql = "INSERT INTO social_feed_likes (feed_id, user_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $feedId, $userId);
        $stmt->execute();
        $stmt->close();
        
        // Cập nhật likes_count
        $sql = "UPDATE social_feed SET likes_count = likes_count + 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $feedId);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['status' => 'success', 'message' => 'Đã like!']);
    }
    
} elseif ($action === 'add_comment') {
    $feedId = (int)($_POST['feed_id'] ?? 0);
    $commentText = trim($_POST['comment_text'] ?? '');
    
    if ($feedId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID feed không hợp lệ!']);
        exit();
    }
    
    if (empty($commentText)) {
        echo json_encode(['status' => 'error', 'message' => 'Nội dung bình luận không được để trống!']);
        exit();
    }
    
    if (strlen($commentText) > 500) {
        echo json_encode(['status' => 'error', 'message' => 'Bình luận quá dài! Tối đa 500 ký tự.']);
        exit();
    }
    
    // Thêm comment
    $sql = "INSERT INTO social_feed_comments (feed_id, user_id, comment_text) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $feedId, $userId, $commentText);
    $stmt->execute();
    $stmt->close();
    
    // Cập nhật comments_count
    $sql = "UPDATE social_feed SET comments_count = comments_count + 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $feedId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['status' => 'success', 'message' => 'Đã thêm bình luận!']);
    
} else {
    echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ!']);
}
?>

