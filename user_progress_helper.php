<?php

require_once 'db_connect.php';

/**
 * Cấu hình cơ bản cho hệ thống level/XP/streak.
 */
if (!defined('UP_LOGIN_DAILY_XP')) {
    define('UP_LOGIN_DAILY_XP', 20);
}

if (!defined('UP_STREAK_BONUS_XP')) {
    define('UP_STREAK_BONUS_XP', 5); // mỗi ngày streak cộng thêm
}

if (!defined('UP_DAILY_REWARD_BASE_COINS')) {
    define('UP_DAILY_REWARD_BASE_COINS', 50);
}

// Sự kiện theo mùa (có thể chỉnh tay ở đây hoặc override bằng include riêng)
if (!defined('UP_EVENT_ACTIVE')) {
    define('UP_EVENT_ACTIVE', false);
}

if (!defined('UP_EVENT_NAME')) {
    define('UP_EVENT_NAME', 'Sự kiện đặc biệt');
}

if (!defined('UP_EVENT_REWARD_MULTIPLIER')) {
    define('UP_EVENT_REWARD_MULTIPLIER', 2.0);
}

if (!defined('UP_EVENT_LOGIN_BONUS_TEXT')) {
    define('UP_EVENT_LOGIN_BONUS_TEXT', 'Thưởng đăng nhập được nhân đôi trong thời gian sự kiện!');
}

/**
 * Tính tổng XP cần cho 1 level.
 * Ở đây dùng công thức đơn giản: 100 * level.
 */
function up_required_xp_for_level(int $level): int
{
    return max(100, $level * 100);
}

/**
 * Đảm bảo có dòng user_progress cho user.
 */
function up_ensure_row(mysqli $conn, int $userId): void
{
    $sql = "INSERT IGNORE INTO user_progress (user_id) VALUES (?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Lấy thông tin tiến trình user (level/xp/streak...).
 */
function up_get_progress(mysqli $conn, int $userId): ?array
{
    up_ensure_row($conn, $userId);

    $sql = "SELECT * FROM user_progress WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

/**
 * Cộng XP và xử lý lên level nếu đủ.
 */
function up_add_xp(mysqli $conn, int $userId, int $xpToAdd): void
{
    if ($xpToAdd <= 0) {
        return;
    }

    up_ensure_row($conn, $userId);

    $sql = "SELECT level, xp FROM user_progress WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($levelCurrent, $xpCurrent);
    $hasRow = $stmt->fetch();
    $stmt->close();

    if (!$hasRow) {
        return;
    }

    $level = (int)$levelCurrent;
    $xp = (int)$xpCurrent + $xpToAdd;

    // Lên level nhiều lần nếu XP đủ
    $leveledUp = false;
    while (true) {
        $required = up_required_xp_for_level($level);
        if ($xp >= $required) {
            $xp -= $required;
            $level++;
            $leveledUp = true;
        } else {
            break;
        }
    }

    $updateSql = "UPDATE user_progress SET level = ?, xp = ?, updated_at = NOW() WHERE user_id = ?";
    $updateStmt = $conn->prepare($updateSql);
    if ($updateStmt) {
        $updateStmt->bind_param("iii", $level, $xp, $userId);
        $updateStmt->execute();
        $updateStmt->close();
    }
}

/**
 * Gọi khi user đăng nhập thành công: cập nhật streak + thưởng ngày + XP.
 * Trả về mảng thông tin thưởng để hiển thị.
 */
function up_handle_successful_login(mysqli $conn, int $userId): array
{
    up_ensure_row($conn, $userId);

    $today = new DateTimeImmutable('today');
    $todayStr = $today->format('Y-m-d');

    $sql = "SELECT login_streak, best_login_streak, last_login_date, last_daily_reward_date 
            FROM user_progress WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return [];
    }

    $loginStreak = (int)$row['login_streak'];
    $bestStreak = (int)$row['best_login_streak'];
    $lastLoginDate = $row['last_login_date'] ? new DateTimeImmutable($row['last_login_date']) : null;
    $lastRewardDate = $row['last_daily_reward_date'] ? new DateTimeImmutable($row['last_daily_reward_date']) : null;

    // Cập nhật streak
    if ($lastLoginDate) {
        $diffDays = (int)$lastLoginDate->diff($today)->days;
        if ($diffDays === 0) {
            // cùng ngày, không đổi streak
        } elseif ($diffDays === 1) {
            $loginStreak++;
        } else {
            $loginStreak = 1;
        }
    } else {
        $loginStreak = 1;
    }

    if ($loginStreak > $bestStreak) {
        $bestStreak = $loginStreak;
    }

    $rewardGranted = false;
    $rewardCoins = 0;
    $rewardXp = 0;

    // Thưởng ngày nếu chưa nhận hôm nay
    if (!$lastRewardDate || $lastRewardDate->format('Y-m-d') !== $todayStr) {
        $multiplier = (UP_EVENT_ACTIVE ? UP_EVENT_REWARD_MULTIPLIER : 1.0);

        $rewardCoins = (int)round(UP_DAILY_REWARD_BASE_COINS * $multiplier + ($loginStreak - 1) * 5);
        $rewardXp = (int)round(UP_LOGIN_DAILY_XP * $multiplier + $loginStreak * UP_STREAK_BONUS_XP);

        // Cộng tiền vào users.Money
        $updateMoneySql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
        $mStmt = $conn->prepare($updateMoneySql);
        if ($mStmt) {
            $mStmt->bind_param("ii", $rewardCoins, $userId);
            $mStmt->execute();
            $mStmt->close();
        }

        // Cộng XP
        up_add_xp($conn, $userId, $rewardXp);

        $rewardGranted = true;
        $lastRewardDateStr = $todayStr;
    } else {
        $lastRewardDateStr = $lastRewardDate->format('Y-m-d');
    }

    $updateSql = "UPDATE user_progress 
                  SET login_streak = ?, best_login_streak = ?, last_login_date = ?, last_daily_reward_date = ?, updated_at = NOW()
                  WHERE user_id = ?";
    $uStmt = $conn->prepare($updateSql);
    if ($uStmt) {
        $uStmt->bind_param("isssi", $loginStreak, $bestStreak, $todayStr, $lastRewardDateStr, $userId);
        $uStmt->execute();
        $uStmt->close();
    }

    return [
        'reward_granted' => $rewardGranted,
        'reward_coins' => $rewardCoins,
        'reward_xp' => $rewardXp,
        'login_streak' => $loginStreak,
        'best_login_streak' => $bestStreak,
        'event_active' => UP_EVENT_ACTIVE,
        'event_name' => UP_EVENT_NAME,
        'event_multiplier' => UP_EVENT_REWARD_MULTIPLIER,
    ];
}


