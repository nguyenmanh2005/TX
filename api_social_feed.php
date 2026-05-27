<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập!']);
    exit();
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'post') {
    $content = trim($_POST['content'] ?? '');
    if (empty($content)) {
        echo json_encode(['status' => 'error', 'message' => 'Nội dung không được để trống!']);
        exit();
    }

    $sql = "INSERT INTO social_feed (user_id, activity_type, message, created_at) VALUES (?, 'custom_post', ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $userId, $content);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Đã đăng bài!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi database: ' . $conn->error]);
    }
    $stmt->close();
} elseif ($action === 'toggle_like') {
    $feedId = (int)$_POST['feed_id'];
    
    // Check if liked
    $stmt = $conn->prepare("SELECT id FROM social_feed_likes WHERE feed_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $feedId, $userId);
    $stmt->execute();
    $liked = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($liked) {
        // Delete like
        $stmt = $conn->prepare("DELETE FROM social_feed_likes WHERE feed_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $feedId, $userId);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success', 'liked' => false]);
    } else {
        // Insert like
        $stmt = $conn->prepare("INSERT INTO social_feed_likes (feed_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $feedId, $userId);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success', 'liked' => true]);
    }
} elseif ($action === 'toggle_reaction') {
    $feedId = (int)$_POST['feed_id'];
    $reactionType = $_POST['reaction_type'] ?? '';

    $allowed = ['fire', 'cry', 'money', 'like'];
    if (!in_array($reactionType, $allowed)) {
        echo json_encode(['status' => 'error', 'message' => 'Loại tương tác không hợp lệ!']);
        exit();
    }

    // Check if reaction exists
    $stmt = $conn->prepare("SELECT id FROM social_feed_reactions WHERE feed_id = ? AND user_id = ? AND reaction_type = ?");
    $stmt->bind_param("iis", $feedId, $userId, $reactionType);
    $stmt->execute();
    $react = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($react) {
        // Delete reaction
        $stmt = $conn->prepare("DELETE FROM social_feed_reactions WHERE feed_id = ? AND user_id = ? AND reaction_type = ?");
        $stmt->bind_param("iis", $feedId, $userId, $reactionType);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success', 'reacted' => false]);
    } else {
        // Insert reaction
        $stmt = $conn->prepare("INSERT INTO social_feed_reactions (feed_id, user_id, reaction_type) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $feedId, $userId, $reactionType);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success', 'reacted' => true]);
    }
} elseif ($action === 'add_comment') {
    $feedId = (int)$_POST['feed_id'];
    $commentText = trim(strip_tags($_POST['comment_text'] ?? ''));

    if (empty($commentText)) {
        echo json_encode(['status' => 'error', 'message' => 'Nội dung bình luận không được để trống!']);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO social_feed_comments (feed_id, user_id, comment_text) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $feedId, $userId, $commentText);
    if ($stmt->execute()) {
        // Update comments_count
        $stmtCount = $conn->prepare("UPDATE social_feed SET comments_count = comments_count + 1 WHERE id = ?");
        $stmtCount->bind_param("i", $feedId);
        $stmtCount->execute();
        $stmtCount->close();
        echo json_encode(['status' => 'success', 'message' => 'Đã gửi bình luận!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $conn->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ!']);
}
?>
