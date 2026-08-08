<?php
/**
 * API Bot Smart Chat & Sound FX Coordinator
 * [NEW FILE] - Endpoint độc lập để kích hoạt quét tin nhắn AI & quản lý âm thanh global
 * Không ghi đè lên chat.php hay load_theme.php
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/bot/bot_chat_smart_ai.php';

$action = $_GET['action'] ?? 'scan';

if ($action === 'scan') {
    // 1. Chạy tiến trình rà quét và phản hồi ngữ cảnh AI Bot
    $scanResult = ['success' => false, 'message' => 'Init'];
    try {
        $smartAI = new BotChatSmartAI($conn);
        $scanResult = $smartAI->scanAndRespond();
    } catch (Throwable $e) {
        $scanResult = ['success' => false, 'message' => $e->getMessage()];
    }

    // 2. Kiểm tra xem có sự kiện âm thanh đặc biệt nào vừa xảy ra trong 30 giây qua không để trả về cho Frontend phát Sound FX
    $soundEvents = [];

    try {
        // Kiểm tra Jackpot mới nhất (trong 30 giây qua)
        $jpSql = "SELECT * FROM chat_messages WHERE message LIKE '%vừa nổ hũ tại game%' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND) ORDER BY id DESC LIMIT 1";
        $jpRes = $conn->query($jpSql);
        if ($jpRes && $jpRes->num_rows > 0) {
            $jpRow = $jpRes->fetch_assoc();
            $soundEvents[] = [
                'type' => 'jackpot',
                'id' => 'jp_' . $jpRow['id'],
                'message' => $jpRow['message']
            ];
        }

        // Kiểm tra có lời khiêu chiến PvP nào mới dành cho user hiện tại không
        if (isset($_SESSION['Iduser'])) {
            $myId = (int)$_SESSION['Iduser'];
            $pvpSql = "SELECT id, challenger_id, bet_amount FROM pvp_challenges WHERE opponent_id = ? AND status = 'pending' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND) LIMIT 1";
            $pvpStmt = $conn->prepare($pvpSql);
            if ($pvpStmt) {
                $pvpStmt->bind_param("i", $myId);
                $pvpStmt->execute();
                $pvpRes = $pvpStmt->get_result();
                if ($pvpRes && $pvpRow = $pvpRes->fetch_assoc()) {
                    $soundEvents[] = [
                        'type' => 'pvp_challenge',
                        'id' => 'pvp_' . $pvpRow['id'],
                        'bet' => $pvpRow['bet_amount']
                    ];
                }
                $pvpStmt->close();
            }
        }
    } catch (Throwable $e) {
        // Ignore sound event query errors to maintain clean JSON
    }

    echo json_encode([
        'success' => true,
        'bot_scan' => $scanResult,
        'sound_events' => $soundEvents,
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
exit;
?>
