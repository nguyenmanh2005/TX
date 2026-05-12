<?php
/**
 * Helper functions để tạo notifications dễ dàng
 * 
 * Cách sử dụng:
 * require_once 'notification_helper.php';
 * createNotification($conn, $userId, 'achievement', 'Đạt Achievement!', 'Bạn đã đạt được achievement mới!', '🏆', 'achievements.php');
 */

/**
 * Tạo thông báo mới
 * 
 * @param mysqli $conn Database connection
 * @param int $userId ID của user nhận thông báo
 * @param string $type Loại thông báo (friend_request, message, achievement, gift, event, tournament, guild)
 * @param string $title Tiêu đề thông báo
 * @param string $content Nội dung thông báo
 * @param string $icon Icon (emoji hoặc icon class)
 * @param string|null $link Link khi click vào thông báo
 * @param int|null $relatedId ID liên quan (user_id, achievement_id, etc.)
 * @param bool $isImportant Có quan trọng không
 * @return bool True nếu thành công
 */
function createNotification(mysqli $conn, int $userId, string $type, string $title, string $content, string $icon = '🔔', ?string $link = null, ?int $relatedId = null, bool $isImportant = false)
{
    // Kiểm tra bảng tồn tại
    $checkTable = $conn->query("SHOW TABLES LIKE 'user_notifications'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        return false; // Bảng chưa tồn tại
    }

    // Kiểm tra cài đặt thông báo
    $settingsSql = "SELECT * FROM notification_settings WHERE user_id = ?";
    $settingsStmt = $conn->prepare($settingsSql);
    $settingsStmt->bind_param("i", $userId);
    $settingsStmt->execute();
    $settings = $settingsStmt->get_result()->fetch_assoc();
    $settingsStmt->close();

    // Kiểm tra loại thông báo có được bật không
    $typeMap = [
        'friend_request' => 'friend_request',
        'private_message' => 'private_message',
        'achievement' => 'achievement',
        'gift_received' => 'gift_received',
        'event_update' => 'event_update',
        'tournament_update' => 'tournament_update',
        'guild_invite' => 'guild_invite',
        'guild_message' => 'guild_message'
    ];

    if ($settings && isset($typeMap[$type])) {
        $settingKey = $typeMap[$type];
        if (!$settings[$settingKey]) {
            return false; // Thông báo này bị tắt
        }
    }

    // Tạo thông báo
    $sql = "INSERT INTO user_notifications (user_id, type, title, content, icon, link, related_id, is_important) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $isImportantInt = $isImportant ? 1 : 0;
    $stmt->bind_param("isssssii", $userId, $type, $title, $content, $icon, $link, $relatedId, $isImportantInt);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * Tạo thông báo khi nhận lời mời kết bạn
 */
function notifyFriendRequest(mysqli $conn, int $receiverId, int $senderId, string $senderName)
{
    return createNotification(
        $conn,
        $receiverId,
        'friend_request',
        'Lời Mời Kết Bạn',
        "$senderName đã gửi lời mời kết bạn cho bạn!",
        '👋',
        'friends.php',
        $senderId
    );
}

/**
 * Tạo thông báo khi đạt achievement
 */
function notifyAchievement(mysqli $conn, int $userId, int $achievementId, string $achievementName)
{
    // Tạo notification thông thường
    $result = createNotification(
        $conn,
        $userId,
        'achievement',
        'Đạt Achievement!',
        "Chúc mừng! Bạn đã đạt được achievement: $achievementName",
        '🏆',
        'achievements.php',
        $achievementId,
        true // Quan trọng
    );

    // Tạo achievement notification riêng (nếu bảng tồn tại)
    $checkTable = $conn->query("SHOW TABLES LIKE 'achievement_notifications'");
    if ($checkTable && $checkTable->num_rows > 0) {
        // Lấy thông tin achievement
        $sql = "SELECT name, icon, rarity, reward_money, reward_xp FROM achievements WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $achievementId);
            $stmt->execute();
            $result = $stmt->get_result();
            $achievement = $result->fetch_assoc();
            $stmt->close();

            if ($achievement) {
                $message = "Bạn đã đạt được danh hiệu: " . $achievement['name'];
                if ($achievement['reward_money'] > 0 || $achievement['reward_xp'] > 0) {
                    $message .= " và nhận được phần thưởng!";
                }

                $sql = "INSERT INTO achievement_notifications 
                        (user_id, achievement_id, notification_type, message)
                        VALUES (?, ?, 'achievement_unlocked', ?)";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("iis", $userId, $achievementId, $message);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }

    return $result;
}

/**
 * Tạo feed activity khi có hoạt động lớn
 */
function createFeedActivity(mysqli $conn, int $userId, string $activityType, string $message, $activityData = null)
{
    // Kiểm tra bảng social_feed có tồn tại không
    $checkTable = $conn->query("SHOW TABLES LIKE 'social_feed'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        return false; // Bảng chưa tồn tại
    }

    $sql = "INSERT INTO social_feed (user_id, activity_type, activity_data, message, is_public)
            VALUES (?, ?, ?, ?, 1)";
    $stmt = $conn->prepare($sql);
    $activityDataJson = $activityData ? json_encode($activityData) : null;
    $stmt->bind_param("isss", $userId, $activityType, $activityDataJson, $message);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * Tạo thông báo khi nhận quà
 */
function notifyGiftReceived(mysqli $conn, int $receiverId, int $senderId, string $senderName, string $giftType, mixed $giftValue)
{
    $giftTypeText = [
        'money' => 'gtlm',
        'theme' => 'theme',
        'cursor' => 'cursor',
        'frame' => 'khung chat'
    ];

    $typeText = $giftTypeText[$giftType] ?? $giftType;
    $valueText = $giftType === 'money' ? number_format($giftValue) . ' gtlm' : '';

    return createNotification(
        $conn,
        $receiverId,
        'gift_received',
        'Nhận Quà!',
        "$senderName đã tặng bạn $typeText $valueText!",
        '🎁',
        'gift.php',
        $senderId
    );
}

/**
 * Tạo thông báo về sự kiện mới
 */
function notifyEventUpdate(mysqli $conn, int $userId, int $eventId, string $eventName, string $message)
{
    return createNotification(
        $conn,
        $userId,
        'event_update',
        'Cập Nhật Sự Kiện',
        "$eventName: $message",
        '🎉',
        'events.php',
        $eventId
    );
}

/**
 * Tạo thông báo về giải đấu
 */
function notifyTournamentUpdate(mysqli $conn, int $userId, int $tournamentId, string $tournamentName, string $message)
{
    return createNotification(
        $conn,
        $userId,
        'tournament_update',
        'Cập Nhật Giải Đấu',
        "$tournamentName: $message",
        '🎯',
        'tournament.php',
        $tournamentId
    );
}

/**
 * Tạo thông báo khi được mời vào guild
 */
function notifyGuildInvite(mysqli $conn, int $receiverId, int $guildId, string $guildName, string $inviterName)
{
    return createNotification(
        $conn,
        $receiverId,
        'guild_invite',
        'Lời Mời Vào Guild',
        "$inviterName đã mời bạn tham gia guild: $guildName",
        '🏆',
        'guilds.php',
        $guildId
    );
}

/**
 * Tạo thông báo tin nhắn guild
 */
function notifyGuildMessage(mysqli $conn, int $userId, int $guildId, string $guildName, string $senderName, string $message)
{
    return createNotification(
        $conn,
        $userId,
        'guild_message',
        "Tin Nhắn Guild: $guildName",
        "$senderName: " . substr($message, 0, 50) . (strlen($message) > 50 ? '...' : ''),
        '💬',
        'guilds.php',
        $guildId
    );
}

/**
 * Thông báo khi bị thách đấu guild
 */
function notifyChallenge(mysqli $conn, int $leaderId, string $challengerName)
{
    return createNotification(
        $conn,
        $leaderId,
        'guild',
        'Thách đấu Bang hội!',
        "Bang $challengerName đã gửi lời thách đấu 24h cho bang bạn!",
        '⚔️',
        'guild_war.php',
        null,
        true
    );
}

