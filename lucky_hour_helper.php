<?php
/**
 * 🕵️‍♂️ Secret Lucky Hour Helper v1.0
 * Calculates a secret deterministic hour each day for a +20% payout multiplier across all games.
 */
class LuckyHourHelper {
    /**
     * Determines today's secret lucky hour (0 - 23) deterministically from the date.
     */
    public static function getLuckyHour() {
        $dateStr = date('Y-m-d');
        $salt = "SecretLuckyHourSalt_2026";
        $hash = md5($dateStr . $salt);
        // Take 4 hex characters, convert to decimal, mod 24 to get a reliable hour of the day
        return hexdec(substr($hash, 0, 4)) % 24;
    }

    /**
     * Checks if the secret lucky hour is currently active.
     */
    public static function isLuckyHour() {
        $currentHour = (int)date('H');
        return $currentHour === self::getLuckyHour();
    }

    /**
     * Applies the +20% secret lucky hour bonus to a user.
     * Credits money and records transaction log.
     */
    public static function applyLuckyHourBonus($conn, $userId, $winAmount, $gameName) {
        if (!self::isLuckyHour() || $winAmount <= 0) {
            return 0;
        }

        $bonus = round($winAmount * 0.20);
        if ($bonus <= 0) return 0;

        // Credit user wallet
        $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
        $stmt->bind_param("di", $bonus, $userId);
        $stmt->execute();
        $stmt->close();

        // Log transaction
        $stmt = $conn->prepare("INSERT INTO bot_transactions (user_id, amount, type, reason) VALUES (?, ?, 'receive', ?)");
        $reason = "Húp Lộc Lucky Hour bí mật (+20% tại $gameName)";
        $stmt->bind_param("ids", $userId, $bonus, $reason);
        $stmt->execute();
        $stmt->close();

        return $bonus;
    }
}
?>
