<?php
/**
 * 🎯 Daily Game Challenge Helper v1.0
 * Handles matching, evaluation, and reward crediting for game-specific daily challenges.
 */
class DailyChallengeHelper {
    /**
     * Resolves game name to standardized daily challenge game key.
     */
    public static function resolveGameKey(string $gameName): string {
        $name = strtolower($gameName);
        if (strpos($name, 'crash') !== false) return 'crash';
        if (strpos($name, 'sicbo') !== false) return 'sicbo';
        if (strpos($name, 'slot') !== false) return 'slot';
        if (strpos($name, 'baccarat') !== false) return 'baccarat';
        return '';
    }

    /**
     * Centralized evaluator called after every resolved game round.
     */
    public static function checkAndComplete(mysqli $conn, int $userId, string $gameName, float $betAmount, float $winAmount, bool $isWin) {
        $gameKey = self::resolveGameKey($gameName);
        if (empty($gameKey)) return;

        $today = date('Y-m-d');

        // Check if user has already completed today's challenge for this game
        $stmt = $conn->prepare("SELECT completed FROM user_daily_challenge_status WHERE user_id = ? AND game_key = ? AND challenge_date = ?");
        $stmt->bind_param("iss", $userId, $gameKey, $today);
        $stmt->execute();
        $statusRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($statusRow && $statusRow['completed']) {
            return; // Already completed
        }

        // Get the active challenge for this game key
        $stmt = $conn->prepare("SELECT * FROM daily_game_challenges WHERE game_key = ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("s", $gameKey);
        $stmt->execute();
        $challenge = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$challenge) return;

        $isCompleted = false;

        // Evaluate challenge criteria
        switch ($challenge['condition_type']) {
            case 'exact_multiplier':
                // For Crash: Cash out at exactly condition_value (e.g. 2.00)
                // Let's assume winAmount / betAmount is approximately the multiplier
                if ($betAmount > 0 && $winAmount > 0) {
                    $mult = round($winAmount / $betAmount, 2);
                    if (abs($mult - (float)$challenge['condition_value']) < 0.05) {
                        $isCompleted = true;
                    }
                }
                break;

            case 'single_roll_win':
                // For other games: single bet payout threshold or minimum bet
                if ($isWin && $winAmount >= (float)$challenge['condition_value']) {
                    $isCompleted = true;
                }
                break;
        }

        if ($isCompleted) {
            $reward = (float)$challenge['reward_gtlm'];

            $conn->begin_transaction();
            try {
                // Insert/Update challenge status
                $stmt = $conn->prepare("INSERT INTO user_daily_challenge_status (user_id, game_key, challenge_date, completed, completed_at) 
                                        VALUES (?, ?, ?, 1, NOW()) 
                                        ON DUPLICATE KEY UPDATE completed = 1, completed_at = NOW()");
                $stmt->bind_param("iss", $userId, $gameKey, $today);
                $stmt->execute();
                $stmt->close();

                // Award GTLM to user balance
                $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
                $stmt->bind_param("di", $reward, $userId);
                $stmt->execute();
                $stmt->close();

                // Log bot transactions
                $stmt = $conn->prepare("INSERT INTO bot_transactions (user_id, amount, type, reason) VALUES (?, ?, 'receive', ?)");
                $reason = "Hoàn thành Thử thách Ngày game " . strtoupper($gameKey) . "!";
                $stmt->bind_param("ids", $userId, $reward, $reason);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                // Store in session to alert user in frontend
                if (!isset($_SESSION['completed_challenges'])) $_SESSION['completed_challenges'] = [];
                $_SESSION['completed_challenges'][] = [
                    'game_key' => $gameKey,
                    'text' => $challenge['challenge_text'],
                    'reward' => $reward
                ];
            } catch (Exception $e) {
                $conn->rollback();
            }
        }
    }
}
?>
