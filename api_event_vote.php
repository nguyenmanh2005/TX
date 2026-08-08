<?php
session_start();
require_once 'db_connect.php';
require_once 'api_event_helper.php'; // getActiveSeasonalEvent()

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Lấy event_id của sự kiện đang active — dùng helper tập trung
$activeEvent = getActiveSeasonalEvent($conn, false, 'id, ends_at');
$eventId = (int)($activeEvent['id'] ?? 0);

// NOTE: Bảng event_voting_options, user_event_votes, event_vote_results
// phải được tạo bằng SQL riêng (xem block SQL trong tài liệu) — Rule 1.1
// Migration đã hoàn thành: cột event_id đã được thêm vào user_event_votes.
// ALTER TABLE đã được xóa khỏi đây để tránh tốn chi phí schema check mỗi request.

// Khởi tạo data mẫu nếu chưa có -> ĐÃ XÓA (Admin giờ phải tự quản lý)

switch ($action) {
    case 'get_options':
        if (!$eventId) {
            echo json_encode(['success' => false, 'message' => 'Không có sự kiện nào đang diễn ra.']);
            exit;
        }
        $stmtOpt = $conn->prepare("SELECT * FROM event_voting_options WHERE event_id = ? AND status = 'active' ORDER BY id ASC");
        $stmtOpt->bind_param("i", $eventId);
        $stmtOpt->execute();
        $options = $stmtOpt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtOpt->close();

        // FIX Bug 4: Lọc theo event_id để check vote đúng sự kiện
        $stmtMyVote = $conn->prepare("SELECT option_id FROM user_event_votes WHERE user_id = ? AND event_id = ?");
        $stmtMyVote->bind_param("ii", $userId, $eventId);
        $stmtMyVote->execute();
        $myVoteRes = $stmtMyVote->get_result();
        $myVote = $myVoteRes->num_rows > 0 ? (int)$myVoteRes->fetch_assoc()['option_id'] : null;
        $stmtMyVote->close();
        
        $totalVotes = 0;
        foreach ($options as $opt) {
            $totalVotes += $opt['votes'];
        }

        echo json_encode(['success' => true, 'options' => $options, 'my_vote' => $myVote, 'total_votes' => $totalVotes, 'event_id' => $eventId]);
        break;

    case 'vote':
        if (!$eventId) {
            echo json_encode(['success' => false, 'message' => 'Không có sự kiện nào đang diễn ra.']);
            exit;
        }
        $optionId = (int)$_POST['option_id'];
        
        $conn->begin_transaction();
        try {
            if ($activeEvent && !empty($activeEvent['ends_at']) && strtotime($activeEvent['ends_at']) < time()) {
                throw new Exception("Sự kiện bình chọn đã kết thúc!");
            }

            // FIX Bug 4: Kiểm tra đã vote TRONG SỰ KIỆN NÀY chưa
            $stmtHasVoted = $conn->prepare("SELECT 1 FROM user_event_votes WHERE user_id = ? AND event_id = ?");
            $stmtHasVoted->bind_param("ii", $userId, $eventId);
            $stmtHasVoted->execute();
            $hasVoted = $stmtHasVoted->get_result()->num_rows > 0;
            $stmtHasVoted->close();

            if ($hasVoted) {
                throw new Exception("Bạn đã tham gia bình chọn sự kiện này rồi!");
            }

            // Kiểm tra option có thuộc đúng event và đang active không
            $stmtOptCheck = $conn->prepare("SELECT 1 FROM event_voting_options WHERE id = ? AND event_id = ? AND status = 'active'");
            $stmtOptCheck->bind_param("ii", $optionId, $eventId);
            $stmtOptCheck->execute();
            $optCheck = $stmtOptCheck->get_result();
            $hasOpt = $optCheck->num_rows > 0;
            $stmtOptCheck->close();

            if (!$hasOpt) {
                throw new Exception("Lựa chọn không hợp lệ hoặc không thuộc sự kiện hiện tại!");
            }

            // FIX Bug 4: Lưu vote kèm event_id
            $stmtInsVote = $conn->prepare("INSERT INTO user_event_votes (user_id, event_id, option_id) VALUES (?, ?, ?)");
            $stmtInsVote->bind_param("iii", $userId, $eventId, $optionId);
            $stmtInsVote->execute();
            $stmtInsVote->close();

            $stmtUpOpt = $conn->prepare("UPDATE event_voting_options SET votes = votes + 1 WHERE id = ? AND event_id = ?");
            $stmtUpOpt->bind_param("ii", $optionId, $eventId);
            $stmtUpOpt->execute();
            $stmtUpOpt->close();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Cảm ơn bạn đã bình chọn!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ── Xem kết quả vote đã được xử lý (option thắng + buff đã kích hoạt) ──
    case 'get_result':
        $targetEventId = (int)($_GET['event_id'] ?? $_POST['event_id'] ?? $eventId);
        if (!$targetEventId) {
            echo json_encode(['success' => false, 'message' => 'Không có event_id.']);
            exit;
        }

        $stmtRes = $conn->prepare("
            SELECT evr.*, evo.title as option_title, evo.votes as option_votes, evo.icon
            FROM event_vote_results evr
            LEFT JOIN event_voting_options evo ON evr.option_id = evo.id
            WHERE evr.event_id = ?
            LIMIT 1
        ");
        $stmtRes->bind_param("i", $targetEventId);
        $stmtRes->execute();
        $result = $stmtRes->get_result()->fetch_assoc();
        $stmtRes->close();

        if (!$result) {
            // Chưa có kết quả — trả về top options để frontend hiển thị
            $stmtOpts = $conn->prepare("
                SELECT * FROM event_voting_options 
                WHERE event_id = ? 
                ORDER BY votes DESC
            ");
            $stmtOpts->bind_param("i", $targetEventId);
            $stmtOpts->execute();
            $opts = $stmtOpts->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtOpts->close();

            echo json_encode(['success' => true, 'status' => 'pending', 'options' => $opts]);
        } else {
            // Kiểm tra xem buff đã được kích hoạt chưa
            $buffEvent = $conn->query("
                SELECT id, event_name, ends_at, TIMESTAMPDIFF(SECOND, NOW(), ends_at) as secs_left
                FROM random_events
                WHERE is_active = 1 AND ends_at > NOW()
                ORDER BY id DESC LIMIT 1
            ")->fetch_assoc();

            echo json_encode([
                'success'    => true,
                'status'     => 'resolved',
                'result'     => $result,
                'buff_active'=> $buffEvent ? true : false,
                'buff_event' => $buffEvent,
            ]);
        }
        break;

    // ── Admin: kích hoạt kết quả vote thủ công ─────────────────────────
    // Gọi: POST api_event_vote.php?action=trigger_result (admin only)
    case 'trigger_result':
        if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
            echo json_encode(['success' => false, 'message' => 'Không có quyền!']);
            exit;
        }
        if (!defined('ALLOW_EVENT_WEB')) define('ALLOW_EVENT_WEB', true);
        ob_start();
        include __DIR__ . '/cron_event_vote_result.php';
        $output = ob_get_clean();
        echo json_encode(['success' => true, 'output' => $output]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ.']);
        break;
}
?>
