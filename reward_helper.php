<?php
/**
 * reward_helper.php
 * Hàm trao phần thưởng trung tâm — dùng chung cho:
 *   api_events.php, api_world_boss.php, api_event_engine.php, cron_*.php
 *
 * Cách dùng:
 *   require_once 'reward_helper.php';
 *   require_once 'notification_helper.php';
 *   deliverReward($userId, $reward, $conn);
 *
 * Format $reward:
 *   ['reward_type' => 'money',        'reward_value' => 50000]
 *   ['reward_type' => 'avatar_frame', 'reward_value' => 3]
 *   ['reward_type' => 'title',        'reward_value' => 5]
 *   ['reward_type' => 'item',         'reward_value' => 'theme:3']
 *   ['reward_type' => 'item',         'reward_value' => 'cursor:7']
 *   ['reward_type' => 'item',         'reward_value' => 'chat_frame:2']
 *   ['reward_type' => 'item',         'reward_value' => 'xp:500']
 *   ['reward_type' => 'item',         'reward_value' => 'vip:24']
 *   ['reward_type' => 'item',         'reward_value' => 'buff:1:3600']
 */

/**
 * Trao phần thưởng cho user.
 *
 * @param int   $userId  ID user nhận thưởng
 * @param array $reward  Mảng chứa reward_type và reward_value
 * @param mysqli $conn   Database connection
 * @return bool True nếu trao thành công
 */
function deliverReward(int $userId, array $reward, mysqli $conn): bool {
    $rewardType  = $reward['reward_type']  ?? '';
    $rewardValue = $reward['reward_value'] ?? '';

    switch ($rewardType) {

        // ─── GTLM ────────────────────────────────────────────────────────────
        case 'money':
            $amount = (int)$rewardValue;
            if ($amount <= 0) return false;
            $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            $stmt->bind_param("ii", $amount, $userId);
            $result = $stmt->execute();
            $stmt->close();
            _notifyReward($conn, $userId,
                '💰 Nhận GTLM từ sự kiện!',
                'Bạn nhận được ' . number_format($amount) . ' GTLM.'
            );
            return $result;

        // ─── KHUNG AVATAR ─────────────────────────────────────────────────────
        case 'avatar_frame':
            $frameId = (int)$rewardValue;
            if ($frameId <= 0) return false;
            $check = $conn->query("SELECT id FROM user_avatar_frames WHERE user_id = $userId AND avatar_frame_id = $frameId");
            if ($check->num_rows > 0) return false; // đã có
            $stmt = $conn->prepare("INSERT INTO user_avatar_frames (user_id, avatar_frame_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $userId, $frameId);
            $result = $stmt->execute();
            $stmt->close();
            _notifyReward($conn, $userId, '🖼️ Khung Avatar Mới!', 'Bạn nhận được khung avatar từ sự kiện!');
            return $result;

        // ─── DANH HIỆU ────────────────────────────────────────────────────────
        case 'title':
            $titleId = (int)$rewardValue;
            if ($titleId <= 0) return false;
            $check = $conn->query("SELECT id FROM user_achievements WHERE user_id = $userId AND achievement_id = $titleId");
            if ($check->num_rows > 0) return false; // đã có
            $stmt = $conn->prepare("INSERT INTO user_achievements (user_id, achievement_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $userId, $titleId);
            $result = $stmt->execute();
            $stmt->close();
            _notifyReward($conn, $userId, '🏷️ Danh Hiệu Mới!', 'Bạn nhận được danh hiệu mới từ sự kiện!');
            return $result;

        // ─── ITEM (theme / cursor / chat_frame / xp / vip / buff) ────────────
        case 'item':
            return _deliverItemReward($userId, (string)$rewardValue, $conn);

        default:
            return false;
    }
}

/**
 * Xử lý các loại item: theme, cursor, chat_frame, xp, vip, buff.
 * Format reward_value: "type:id" hoặc "buff:id:seconds"
 */
function _deliverItemReward(int $userId, string $rewardValue, mysqli $conn): bool {
    $parts       = explode(':', $rewardValue);
    $itemSubType = $parts[0] ?? '';

    switch ($itemSubType) {

        // ── Theme ────────────────────────────────────────────────────────────
        case 'theme':
            $themeId = (int)($parts[1] ?? 0);
            if ($themeId <= 0) return false;
            $stmt = $conn->prepare("INSERT IGNORE INTO user_themes (user_id, theme_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $userId, $themeId);
            $result = $stmt->execute();
            $stmt->close();
            _notifyReward($conn, $userId, '🎨 Theme Mới!', 'Bạn nhận được theme mới từ sự kiện!');
            return $result;

        // ── Cursor ───────────────────────────────────────────────────────────
        case 'cursor':
            $cursorId = (int)($parts[1] ?? 0);
            if ($cursorId <= 0) return false;
            $stmt = $conn->prepare("INSERT IGNORE INTO user_cursors (user_id, cursor_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $userId, $cursorId);
            $result = $stmt->execute();
            $stmt->close();
            _notifyReward($conn, $userId, '🖱️ Con Trỏ Mới!', 'Bạn nhận được con trỏ mới từ sự kiện!');
            return $result;

        // ── Khung Chat ───────────────────────────────────────────────────────
        case 'chat_frame':
            $chatFrameId = (int)($parts[1] ?? 0);
            if ($chatFrameId <= 0) return false;
            $stmt = $conn->prepare("INSERT IGNORE INTO user_chat_frames (user_id, chat_frame_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $userId, $chatFrameId);
            $result = $stmt->execute();
            $stmt->close();
            _notifyReward($conn, $userId, '💬 Khung Chat Mới!', 'Bạn nhận được khung chat mới từ sự kiện!');
            return $result;

        // ── XP ───────────────────────────────────────────────────────────────
        case 'xp':
            $xpAmount = (int)($parts[1] ?? 0);
            if ($xpAmount <= 0) return false;
            $stmt = $conn->prepare("UPDATE users SET xp = xp + ? WHERE Iduser = ?");
            $stmt->bind_param("ii", $xpAmount, $userId);
            $result = $stmt->execute();
            $stmt->close();
            _notifyReward($conn, $userId, '⭐ Nhận XP!', "Bạn nhận được $xpAmount XP từ sự kiện!");
            return $result;

        // ── VIP (cộng dồn, không ghi đè) ─────────────────────────────────────
        case 'vip':
            $hours = (int)($parts[1] ?? 24);
            if ($hours <= 0) return false;
            // Dùng logic IF(vip_expiry > NOW(), DATE_ADD...) giống admin_Event_Manager.php dòng 69
            $stmt = $conn->prepare(
                "UPDATE users SET vip_expiry = IF(vip_expiry > NOW(),
                    DATE_ADD(vip_expiry, INTERVAL ? HOUR),
                    DATE_ADD(NOW(), INTERVAL ? HOUR))
                WHERE Iduser = ?"
            );
            $stmt->bind_param("iii", $hours, $hours, $userId);
            $result = $stmt->execute();
            $stmt->close();
            _notifyReward($conn, $userId,
                '👑 VIP Được Kích Hoạt!',
                "Bạn nhận được $hours giờ VIP từ sự kiện!",
                true
            );
            return $result;

        // ── Buff (cộng dồn thời gian nếu còn hiệu lực) ───────────────────────
        case 'buff':
            $buffId   = (int)($parts[1] ?? 0);
            $duration = (int)($parts[2] ?? 3600);
            if ($buffId <= 0) return false;
            $expiresAt = date('Y-m-d H:i:s', time() + $duration);
            $stmt = $conn->prepare(
                "INSERT INTO user_active_buffs (user_id, buff_id, expires_at)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE expires_at = IF(expires_at > NOW(),
                     DATE_ADD(expires_at, INTERVAL ? SECOND), ?)"
            );
            $stmt->bind_param("iiisi", $userId, $buffId, $expiresAt, $duration, $expiresAt);
            $result = $stmt->execute();
            $stmt->close();
            _notifyReward($conn, $userId, '✨ Buff Mới!', 'Một buff mới đã được kích hoạt từ sự kiện!');
            return $result;
    }

    return false;
}

/**
 * Gửi notification sau khi trao thưởng.
 * Tự động dùng createNotification() từ notification_helper.php nếu đã được include.
 */
function _notifyReward(mysqli $conn, int $userId, string $title, string $content, bool $isImportant = false): void {
    if (function_exists('createNotification')) {
        createNotification($conn, $userId, 'event_update', $title, $content, '🎁', 'events.php', null, $isImportant);
    }
}
