<?php
/**
 * 🎯 Reusable Daily Game Challenge Widget v1.0
 * Renders a glassmorphic container detailing the active daily challenge and completion status.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../daily_challenge_helper.php';

$userId = $_SESSION['Iduser'] ?? 0;
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$gameKey = DailyChallengeHelper::resolveGameKey($currentPage);

if (!empty($gameKey) && $userId > 0) {
    $today = date('Y-m-d');

    // 1. Get challenge details
    $stmt = $conn->prepare("SELECT * FROM daily_game_challenges WHERE game_key = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $gameKey);
    $stmt->execute();
    $challenge = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($challenge) {
        // 2. Get user completion status
        $stmt = $conn->prepare("SELECT completed FROM user_daily_challenge_status WHERE user_id = ? AND game_key = ? AND challenge_date = ?");
        $stmt->bind_param("iss", $userId, $gameKey, $today);
        $stmt->execute();
        $statusRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $completed = $statusRow ? (bool)$statusRow['completed'] : false;
        ?>
        <!-- Daily Game Challenge Glassmorphic Widget -->
        <div class="daily-game-challenge-container" style="
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 1.2rem;
            margin: 1.5rem auto;
            max-width: 800px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            text-align: left;
        ">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="
                    font-size: 2.2rem;
                    background: linear-gradient(135deg, #fbbf24, #f59e0b);
                    -webkit-background-clip: text;
                    background-clip: text;
                    -webkit-text-fill-color: transparent;
                ">🎯</div>
                <div>
                    <div style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 2px; color: #fbbf24; margin-bottom: 3px;">Thử Thách Ngày</div>
                    <div style="font-size: 0.95rem; font-weight: 600; color: #efeff1;"><?= htmlspecialchars($challenge['challenge_text']) ?></div>
                </div>
            </div>
            
            <div style="display: flex; align-items: center; gap: 15px; flex-shrink: 0;">
                <div style="text-align: right;">
                    <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 600; text-transform: uppercase;">Phần Thưởng</div>
                    <div style="font-size: 1rem; font-weight: 800; color: #22c55e;">+<?= number_format($challenge['reward_gtlm']) ?> GTLM</div>
                </div>
                
                <div>
                    <?php if ($completed): ?>
                        <span style="
                            background: rgba(34, 197, 94, 0.15);
                            color: #22c55e;
                            border: 1px solid rgba(34, 197, 94, 0.3);
                            padding: 6px 12px;
                            border-radius: 50px;
                            font-size: 0.75rem;
                            font-weight: 700;
                            display: inline-flex;
                            align-items: center;
                            gap: 5px;
                        ">🎉 ĐÃ XONG</span>
                    <?php else: ?>
                        <span style="
                            background: rgba(245, 158, 11, 0.15);
                            color: #f59e0b;
                            border: 1px solid rgba(245, 158, 11, 0.3);
                            padding: 6px 12px;
                            border-radius: 50px;
                            font-size: 0.75rem;
                            font-weight: 700;
                            display: inline-flex;
                            align-items: center;
                            gap: 5px;
                            animation: pulseChallenge 2s infinite;
                        ">⏳ ĐANG LÀM</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <style>
            @keyframes pulseChallenge {
                0% { opacity: 1; transform: scale(1); }
                50% { opacity: 0.8; transform: scale(0.98); }
                100% { opacity: 1; transform: scale(1); }
            }
        </style>
        <?php
    }
}
?>
