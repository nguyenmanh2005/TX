<?php
session_start();
require 'db_connect.php';
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}
// Load theme
require_once 'load_theme.php';
$userId = $_SESSION['Iduser'];
// Kiểm tra và tạo bảng social_feed nếu chưa có
$checkTable = $conn->query("SHOW TABLES LIKE 'social_feed'");
if (!$checkTable || $checkTable->num_rows == 0) {
    $conn->query($createTable);
    $conn->query($createLikes);
    $conn->query($createComments);
}
// Lấy thông tin người dùng
$sql = "SELECT Iduser, Name, Money FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
// Lấy feed activities
$sql = "SELECT sf.*, u.Name, u.ImageURL,
        (SELECT COUNT(*) FROM social_feed_likes WHERE feed_id = sf.id) as likes_count,
        (SELECT COUNT(*) FROM social_feed_comments WHERE feed_id = sf.id) as comments_count,
        (SELECT COUNT(*) FROM social_feed_likes WHERE feed_id = sf.id AND user_id = ?) as is_liked
        FROM social_feed sf
        JOIN users u ON sf.user_id = u.Iduser
        WHERE sf.is_public = 1
        ORDER BY sf.created_at DESC
        LIMIT 50";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$feedItems = [];
while ($row = $result->fetch_assoc()) {
    $feedItems[] = $row;
}
$stmt->close();
// Lấy comments và reactions cho mỗi feed item
foreach ($feedItems as &$item) {
    // 1. Fetch Comments
    $sql = "SELECT sfc.*, u.Name, u.ImageURL
            FROM social_feed_comments sfc
            JOIN users u ON sfc.user_id = u.Iduser
            WHERE sfc.feed_id = ?
            ORDER BY sfc.created_at ASC
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $item['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $item['comments'] = [];
    while ($comment = $result->fetch_assoc()) {
        $item['comments'][] = $comment;
    }
    $stmt->close();
    // 2. Fetch Reactions
    $item['reactions'] = [
        'fire' => ['count' => 0, 'active' => false],
        'cry' => ['count' => 0, 'active' => false],
        'money' => ['count' => 0, 'active' => false],
        'like' => ['count' => 0, 'active' => false]
    ];
    $sql = "SELECT reaction_type, COUNT(*) as cnt,
                  SUM(CASE WHEN user_id = ? THEN 1 ELSE 0 END) as my_react
           FROM social_feed_reactions
           WHERE feed_id = ?
           GROUP BY reaction_type";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $item['id']);
    $stmt->execute();
    $reactRes = $stmt->get_result();
    while ($r = $reactRes->fetch_assoc()) {
        $item['reactions'][$r['reaction_type']] = [
            'count' => (int)$r['cnt'],
            'active' => $r['my_react'] > 0
        ];
    }
    $stmt->close();
}
unset($item);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Feed - Hoạt Động Cộng Đồng</title>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            animation: fadeIn 0.6s ease;
        }
        * {
            cursor: inherit;
        }
        button, a, input[type="button"], input[type="submit"], label, select, textarea {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header-feed {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 24px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
            animation: fadeInDown 0.6s ease;
            position: relative;
            overflow: hidden;
        }
        .header-feed::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.05) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }
        .header-feed h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 42px;
            font-weight: 800;
            letter-spacing: -1px;
            position: relative;
            z-index: 1;
        }
        .feed-item {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: fadeInUp 0.6s ease backwards;
            transition: all 0.3s ease;
        }
        .feed-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
        }
        .feed-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        .feed-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
        }
        .feed-user-info {
            flex: 1;
        }
        .feed-user-name {
            font-weight: 700;
            color: #333;
            font-size: 18px;
        }
        .feed-time {
            font-size: 12px;
            color: #999;
        }
        .feed-content {
            margin: 15px 0;
            color: #333;
            line-height: 1.6;
            font-size: 16px;
        }
        .feed-actions {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        .feed-action-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(102, 126, 234, 0.1);
            border: none;
            border-radius: 8px;
            color: #667eea;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .feed-action-btn:hover {
            background: rgba(102, 126, 234, 0.2);
            transform: scale(1.05);
        }
        .feed-action-btn.liked {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        .comments-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        .comment-item {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            background: rgba(247, 247, 247, 0.8);
            border-radius: 12px;
        }
        .comment-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }
        .comment-content {
            flex: 1;
        }
        .comment-author {
            font-weight: 700;
            color: #333;
            font-size: 14px;
            margin-bottom: 3px;
        }
        .comment-text {
            color: #666;
            font-size: 14px;
        }
        .comment-input {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .comment-input input {
            flex: 1;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 20px;
            font-size: 14px;
        }
        .comment-input button {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 20px;
            font-weight: 600;
            cursor: pointer;
        }
        .reaction-pill-btn {
            background: rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 50px;
            padding: 6px 15px;
            font-size: 13px;
            font-weight: 700;
            color: #4b5563;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }
        .reaction-pill-btn:hover {
            transform: translateY(-2px);
            background: rgba(102, 126, 234, 0.08);
            border-color: rgba(102, 126, 234, 0.2);
        }
        .reaction-pill-btn.active {
            background: rgba(102, 126, 234, 0.15) !important;
            border-color: rgba(102, 126, 234, 0.4) !important;
            color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }
        .quick-comment-pill {
            background: rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 600;
            color: #4b5563;
            white-space: nowrap;
            transition: all 0.2s;
            cursor: pointer;
        }
        .quick-comment-pill:hover {
            background: rgba(102, 126, 234, 0.08);
            color: #667eea;
            border-color: rgba(102, 126, 234, 0.2);
            transform: scale(1.02);
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        .empty-state-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .empty-state-text {
            font-size: 18px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-feed">
            <h1>📱 Social Feed</h1>
            <p style="color: #666; margin-top: 10px; font-size: 18px;">Xem hoạt động của cộng đồng!</p>
        </div>
        <?php if (empty($feedItems)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <div class="empty-state-text">
                    Chưa có hoạt động nào. Hãy chơi game để xuất hiện trên feed!
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($feedItems as $item): 
                $timeAgo = '';
                $created = new DateTime($item['created_at']);
                $now = new DateTime();
                $diff = $now->diff($created);
                if ($diff->days > 0) {
                    $timeAgo = $diff->days . ' ngày trước';
                } elseif ($diff->h > 0) {
                    $timeAgo = $diff->h . ' giờ trước';
                } elseif ($diff->i > 0) {
                    $timeAgo = $diff->i . ' phút trước';
                } else {
                    $timeAgo = 'Vừa xong';
                }
                $activityIcon = '🎮';
                switch ($item['activity_type']) {
                    case 'big_win':
                        $activityIcon = '🎉';
                        break;
                    case 'achievement':
                        $activityIcon = '🏆';
                        break;
                    case 'level_up':
                        $activityIcon = '⭐';
                        break;
                    case 'gift_sent':
                        $activityIcon = '🎁';
                        break;
                }
            ?>
                <div class="feed-item">
                    <div class="feed-header">
                        <img src="<?= htmlspecialchars($item['ImageURL'] ?? 'img/default-avatar.png') ?>" 
                             class="feed-avatar" alt="Avatar">
                        <div class="feed-user-info">
                            <div class="feed-user-name"><?= htmlspecialchars($item['Name']) ?></div>
                            <div class="feed-time"><?= $timeAgo ?></div>
                        </div>
                        <div style="font-size: 32px;"><?= $activityIcon ?></div>
                    </div>
                    <div class="feed-content">
                        <?= htmlspecialchars($item['message'] ?? '') ?>
                    </div>
                    <div class="feed-actions">
                        <button class="feed-action-btn <?= $item['is_liked'] > 0 ? 'liked' : '' ?>" 
                                onclick="toggleLike(<?= $item['id'] ?>)">
                            <i class="fas fa-heart"></i>
                            <span><?= $item['likes_count'] ?></span>
                        </button>
                        <button class="feed-action-btn" onclick="toggleComments(<?= $item['id'] ?>)">
                            <i class="fas fa-comment"></i>
                            <span><?= $item['comments_count'] ?></span>
                        </button>
                    </div>
                    <!-- Emoji Reactions Bar -->
                    <div class="feed-reactions-row" style="display: flex; gap: 10px; margin-top: 15px; padding-top: 15px; border-top: 1px dashed rgba(0,0,0,0.08);">
                        <button class="reaction-pill-btn fire-btn <?= $item['reactions']['fire']['active'] ? 'active' : '' ?>" onclick="toggleReaction(<?= $item['id'] ?>, 'fire')">
                            🔥 <span class="badge"><?= $item['reactions']['fire']['count'] ?></span>
                        </button>
                        <button class="reaction-pill-btn cry-btn <?= $item['reactions']['cry']['active'] ? 'active' : '' ?>" onclick="toggleReaction(<?= $item['id'] ?>, 'cry')">
                            😭 <span class="badge"><?= $item['reactions']['cry']['count'] ?></span>
                        </button>
                        <button class="reaction-pill-btn money-btn <?= $item['reactions']['money']['active'] ? 'active' : '' ?>" onclick="toggleReaction(<?= $item['id'] ?>, 'money')">
                            💰 <span class="badge"><?= $item['reactions']['money']['count'] ?></span>
                        </button>
                        <button class="reaction-pill-btn like-btn <?= $item['reactions']['like']['active'] ? 'active' : '' ?>" onclick="toggleReaction(<?= $item['id'] ?>, 'like')">
                            ❤️ <span class="badge"><?= $item['reactions']['like']['count'] ?></span>
                        </button>
                    </div>
                    <!-- Quick Comments Suggested Pills -->
                    <div class="quick-comments-row" style="display: flex; gap: 8px; margin: 15px 0 10px; overflow-x: auto; padding-bottom: 5px; scrollbar-width: none; align-items: center;">
                        <span style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-right: 5px; white-space: nowrap;">Bình luận nhanh:</span>
                        <button class="quick-comment-pill" onclick="postQuickComment(<?= $item['id'] ?>, 'Húp căng thế! 🔥')">Húp căng thế! 🔥</button>
                        <button class="quick-comment-pill" onclick="postQuickComment(<?= $item['id'] ?>, 'Chia lộc đi idol! 💰')">Chia lộc đi idol! 💰</button>
                        <button class="quick-comment-pill" onclick="postQuickComment(<?= $item['id'] ?>, 'Khóc cùng idol... 😭')">Khóc cùng idol... 😭</button>
                        <button class="quick-comment-pill" onclick="postQuickComment(<?= $item['id'] ?>, 'Đỉnh chóp! 🏆')">Đỉnh chóp! 🏆</button>
                    </div>
                    <div class="comments-section" id="comments-<?= $item['id'] ?>" style="display: none;">
                        <?php if (!empty($item['comments'])): ?>
                            <?php foreach ($item['comments'] as $comment): ?>
                                <div class="comment-item">
                                    <img src="<?= htmlspecialchars($comment['ImageURL'] ?? 'img/default-avatar.png') ?>" 
                                         class="comment-avatar" alt="Avatar">
                                    <div class="comment-content">
                                        <div class="comment-author"><?= htmlspecialchars($comment['Name']) ?></div>
                                        <div class="comment-text"><?= htmlspecialchars($comment['comment_text']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <div class="comment-input">
                            <input type="text" id="comment-input-<?= $item['id'] ?>" 
                                   placeholder="Viết bình luận...">
                            <button onclick="postComment(<?= $item['id'] ?>)">Gửi</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 12px; font-weight: 600;">
                🏠 Về Trang Chủ
            </a>
        </div>
    </div>
    <script>
        function toggleLike(feedId) {
            $.ajax({
                url: 'api_social_feed.php',
                method: 'POST',
                data: {
                    action: 'toggle_like',
                    feed_id: feedId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        location.reload();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi!',
                            text: response.message
                        });
                    }
                }
            });
        }
        function toggleComments(feedId) {
            const commentsSection = document.getElementById('comments-' + feedId);
            if (commentsSection.style.display === 'none') {
                commentsSection.style.display = 'block';
            } else {
                commentsSection.style.display = 'none';
            }
        }
        function postComment(feedId) {
            const input = document.getElementById('comment-input-' + feedId);
            const commentText = input.value.trim();
            if (!commentText) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Cảnh Báo!',
                    text: 'Vui lòng nhập nội dung bình luận!'
                });
                return;
            }
            $.ajax({
                url: 'api_social_feed.php',
                method: 'POST',
                data: {
                    action: 'add_comment',
                    feed_id: feedId,
                    comment_text: commentText
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        input.value = '';
                        location.reload();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi!',
                            text: response.message
                        });
                    }
                }
            });
        }
        function toggleReaction(feedId, reactionType) {
            $.ajax({
                url: 'api_social_feed.php',
                method: 'POST',
                data: {
                    action: 'toggle_reaction',
                    feed_id: feedId,
                    reaction_type: reactionType
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        location.reload();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi!',
                            text: response.message
                        });
                    }
                }
            });
        }
        function postQuickComment(feedId, commentText) {
            $.ajax({
                url: 'api_social_feed.php',
                method: 'POST',
                data: {
                    action: 'add_comment',
                    feed_id: feedId,
                    comment_text: commentText
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        location.reload();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi!',
                            text: response.message
                        });
                    }
                }
            });
        }
    </script>
</body>
</html>
