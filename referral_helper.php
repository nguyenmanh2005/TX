<?php

require_once 'db_connect.php';

if (!defined('REF_BONUS_REFERRER')) {
    define('REF_BONUS_REFERRER', 100000); // thưởng cho người giới thiệu
}

if (!defined('REF_BONUS_REFERRED')) {
    define('REF_BONUS_REFERRED', 50000); // thưởng cho người được mời
}

/**
 * Tạo hoặc lấy mã giới thiệu của user.
 */
function ref_get_or_create_code(mysqli $conn, int $userId): ?string
{
    $sql = "SELECT code FROM referral_codes WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($code);
        if ($stmt->fetch() && !empty($code)) {
            $stmt->close();
            return $code;
        }
        $stmt->close();
    }

    // Tạo code mới
    $code = null;
    for ($i = 0; $i < 5; $i++) {
        $candidate = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $checkSql = "SELECT 1 FROM referral_codes WHERE code = ?";
        $checkStmt = $conn->prepare($checkSql);
        if ($checkStmt) {
            $checkStmt->bind_param("s", $candidate);
            $checkStmt->execute();
            $checkStmt->bind_result($dummy);
            if (!$checkStmt->fetch()) {
                $checkStmt->close();
                $code = $candidate;
                break;
            }
            $checkStmt->close();
        }
    }

    if (!$code) {
        return null;
    }

    $insertSql = "INSERT INTO referral_codes (user_id, code) VALUES (?, ?)";
    $insStmt = $conn->prepare($insertSql);
    if ($insStmt) {
        $insStmt->bind_param("is", $userId, $code);
        $insStmt->execute();
        $insStmt->close();
        return $code;
    }

    return null;
}

/**
 * Tìm user_id từ mã giới thiệu.
 */
function ref_find_user_by_code(mysqli $conn, string $code): ?int
{
    $sql = "SELECT user_id FROM referral_codes WHERE code = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $stmt->bind_result($uid);
    $has = $stmt->fetch();
    $stmt->close();
    return $has ? (int)$uid : null;
}

/**
 * Thưởng referral khi tạo tài khoản thành công.
 */
function ref_reward_on_register(mysqli $conn, int $referrerId, int $newUserId): void
{
    if ($referrerId <= 0 || $newUserId <= 0 || $referrerId === $newUserId) {
        return;
    }

    // Ghi log referral
    $sql = "INSERT INTO referrals (referrer_id, referred_id, created_at) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $referrerId, $newUserId);
        $stmt->execute();
        $stmt->close();
    }

    // Thưởng tiền cho cả hai
    $bonusRef = REF_BONUS_REFERRER;
    $bonusNew = REF_BONUS_REFERRED;

    $sqlRef = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
    $uStmt = $conn->prepare($sqlRef);
    if ($uStmt) {
        $uStmt->bind_param("ii", $bonusRef, $referrerId);
        $uStmt->execute();
        $uStmt->close();
    }

    $u2Stmt = $conn->prepare($sqlRef);
    if ($u2Stmt) {
        $u2Stmt->bind_param("ii", $bonusNew, $newUserId);
        $u2Stmt->execute();
        $u2Stmt->close();
    }
}


