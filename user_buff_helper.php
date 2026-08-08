<?php
/**
 * 🛡️ User Active Buff Helper v1.0
 * Manages active spectator cheers/buffs applied to players, including luck boosts, hyper payouts, and loss protections.
 */
class UserBuffHelper {
    /**
     * Grants a new cheer buff or stacks uses on an existing buff.
     */
    public static function addBuff($conn, $userId, $buffType, $uses = 3) {
        $stmt = $conn->prepare("SELECT id FROM user_active_buffs WHERE user_id = ? AND buff_type = ? LIMIT 1");
        $stmt->bind_param("is", $userId, $buffType);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $stmt = $conn->prepare("UPDATE user_active_buffs SET uses_left = uses_left + ? WHERE id = ?");
            $stmt->bind_param("ii", $uses, $row['id']);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO user_active_buffs (user_id, buff_type, uses_left) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $userId, $buffType, $uses);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Checks if a user has a specific buff type and returns the uses_left.
     */
    public static function getBuffUses($conn, $userId, $buffType) {
        $stmt = $conn->prepare("SELECT uses_left FROM user_active_buffs WHERE user_id = ? AND buff_type = ? AND uses_left > 0 LIMIT 1");
        $stmt->bind_param("is", $userId, $buffType);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['uses_left'] : 0;
    }

    /**
     * Decrements the uses_left of a specific buff type by 1.
     */
    public static function consumeBuff($conn, $userId, $buffType) {
        $stmt = $conn->prepare("UPDATE user_active_buffs SET uses_left = GREATEST(0, uses_left - 1) WHERE user_id = ? AND buff_type = ? AND uses_left > 0");
        $stmt->bind_param("is", $userId, $buffType);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Applies the Spectator Shield Buff (Khiên Hộ Mệnh) to protect 50% of the player's losses.
     */
    public static function applyLossProtection($conn, $userId, $betAmount) {
        $shieldUses = self::getBuffUses($conn, $userId, 'shield');
        if ($shieldUses <= 0) return 0;

        $refund = round($betAmount * 0.50);
        if ($refund <= 0) return 0;

        // Consume one shield charge
        self::consumeBuff($conn, $userId, 'shield');

        // Credit 50% loss back to user wallet
        $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
        $stmt->bind_param("di", $refund, $userId);
        $stmt->execute();
        $stmt->close();

        // Log transaction
        $stmt = $conn->prepare("INSERT INTO bot_transactions (user_id, amount, type, reason) VALUES (?, ?, 'receive', 'Kích hoạt Khiên Hộ Mệnh (Bảo hiểm 50% số Chiến)')");
        $stmt->bind_param("id", $userId, $refund);
        $stmt->execute();
        $stmt->close();

        return $refund;
    }
}
?>
