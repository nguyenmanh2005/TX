<?php
/**
 * 📖 Storyline Event Backend API
 * Quản lý tiến trình cốt truyện, kiểm tra nhiệm vụ và trả thưởng.
 */
require_once 'db_connect.php';
require_once 'SystemLogger.php';
require_once 'api_event_helper.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng nhập!']);
    exit;
}

$userId = (int)$_SESSION['Iduser'];
$action = $_GET['action'] ?? '';

// Helper lấy tiến trình người dùng
function getUserProgress($conn, $userId) {
    $today = date('Y-m-d');
    
    // Lấy thông tin event đang hoạt động bằng prepared statement
    $stmt = $conn->prepare("SELECT * FROM storyline_events WHERE is_active = 1 LIMIT 1");
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$event) return null;
    
    $eventId = (int)$event['id'];
    
    // Khởi tạo tiến trình nếu chưa có bằng prepared statement
    $stmt = $conn->prepare("INSERT IGNORE INTO user_storyline_progress (user_id, storyline_id, unlocked_chapters, completed_chapters, last_active_date) VALUES (?, ?, 1, 0, ?)");
    $stmt->bind_param("iis", $userId, $eventId, $today);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT * FROM user_storyline_progress WHERE user_id = ? AND storyline_id = ?");
    $stmt->bind_param("ii", $userId, $eventId);
    $stmt->execute();
    $progress = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Cập nhật lại ngày hoạt động bằng prepared statement
    if ($progress['last_active_date'] !== $today) {
        $stmt = $conn->prepare("UPDATE user_storyline_progress SET last_active_date = ? WHERE user_id = ? AND storyline_id = ?");
        $stmt->bind_param("sii", $today, $userId, $eventId);
        $stmt->execute();
        $stmt->close();
        $progress['last_active_date'] = $today;
    }
    
    // Đếm số cược hôm nay trực tiếp từ nhật ký kinh tế tập trung bằng prepared statement
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM economy_transaction_logs WHERE user_id = ? AND amount < 0 AND created_at >= CURDATE()");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $betsRes = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $betsPlacedToday = $betsRes ? (int)$betsRes['cnt'] : 0;
    
    return [
        'event_id' => $eventId,
        'title' => $event['title'],
        'total_chapters' => (int)$event['total_chapters'],
        'unlocked_chapters' => (int)$progress['unlocked_chapters'],
        'completed_chapters' => (int)$progress['completed_chapters'],
        'bets_placed_today' => $betsPlacedToday
    ];
}

// 1. GET STATE: Lấy thông tin cốt truyện
if ($action === 'get_state') {
    $state = getUserProgress($conn, $userId);
    if (!$state) {
        echo json_encode(['success' => false, 'message' => 'Không có sự kiện cốt truyện nào đang diễn ra.']);
        exit;
    }
    
    // Lấy danh sách chương bằng prepared statement
    $eventId = (int)$state['event_id'];
    $stmt = $conn->prepare("SELECT * FROM storyline_chapters WHERE storyline_id = ? ORDER BY chapter_number ASC");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $chapters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // ⚡ ĐỒNG BỘ THEME VỚI SỰ KIỆN MÙA (SEASONAL EVENT)
    $seasonal = getActiveSeasonalEvent($conn, false, 'name, theme_emoji, theme_config');
    $seasonalTheme = null;
    if ($seasonal) {
        $seasonalTheme = json_decode($seasonal['theme_config'], true) ?: [];
        $seasonalTheme['name'] = $seasonal['name'];
        $seasonalTheme['emoji'] = $seasonal['theme_emoji'];
    }
    
    echo json_encode([
        'success' => true,
        'state' => $state,
        'chapters' => $chapters,
        'seasonal_theme' => $seasonalTheme
    ]);
    exit;
}

// 2. CLAIM REWARD: Nhận quà và mở khóa chương tiếp theo
if ($action === 'claim') {
    $chapterNum = (int)($_POST['chapter_number'] ?? 0);
    $eventId = (int)($_POST['event_id'] ?? 0); // Strictly cast incoming event ID as requested by security review
    
    if ($chapterNum <= 0) {
        echo json_encode(['success' => false, 'message' => 'Mã chương không hợp lệ!']);
        exit;
    }
    
    $conn->begin_transaction();
    try {
        $state = getUserProgress($conn, $userId);
        if (!$state) throw new Exception("Không có sự kiện hoạt động.");
        
        $currentEventId = (int)$state['event_id'];
        if ($eventId > 0 && $eventId !== $currentEventId) {
            throw new Exception("Sự kiện không đúng hoặc đã kết thúc!");
        }
        
        // Kiểm tra xem chương này đã được unlock chưa và đã hoàn thành chưa
        if ($chapterNum > $state['unlocked_chapters']) {
            throw new Exception("Chương này đang bị khóa!");
        }
        if ($chapterNum <= $state['completed_chapters']) {
            throw new Exception("Bạn đã nhận thưởng chương này rồi!");
        }
        
        // Lấy điều kiện chương bằng prepared statement
        $stmt = $conn->prepare("SELECT * FROM storyline_chapters WHERE storyline_id = ? AND chapter_number = ? FOR UPDATE");
        $stmt->bind_param("ii", $currentEventId, $chapterNum);
        $stmt->execute();
        $chapter = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$chapter) throw new Exception("Không tìm thấy chương!");
        
        // Kiểm tra xem đã đủ số lượng cược chưa
        if ($state['bets_placed_today'] < $chapter['target_bets']) {
            throw new Exception("Bạn chưa đặt đủ " . $chapter['target_bets'] . " cược hôm nay để hoàn thành chương!");
        }
        
        // Phát thưởng
        $reward = (float)$chapter['reward_money'];
        
        // Cộng GTLM và lưu log kinh tế
        SystemLogger::setEconomyContext('STORYLINE_REWARD', 'chapter-' . $chapterNum, [
            'chapter' => $chapterNum,
            'reward_amount' => $reward
        ]);
        
        $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
        $stmt->bind_param("di", $reward, $userId);
        $stmt->execute();
        $stmt->close();
        
        SystemLogger::clearEconomyContext();

        // ── BUG FIX #5: Cộng điểm vào Seasonal Event đang active ──────────────
        // Trước đây storyline hoàn toàn tách biệt, không trao điểm vào sự kiện mùa.
        // Nay: 1 điểm event / 10,000 GTLM thưởng storyline.
        $activeSeasonalEvent = getActiveSeasonalEvent($conn, false, 'id');

        if ($activeSeasonalEvent) {
            $seId          = (int)$activeSeasonalEvent['id'];
            $pointsToAdd   = max(1, (int)ceil($reward / 10000)); // tối thiểu 1 điểm/chương
            $stmtSE = $conn->prepare("
                INSERT INTO user_event_data (user_id, event_id, points, event_currency)
                VALUES (?, ?, ?, 0)
                ON DUPLICATE KEY UPDATE points = points + ?
            ");
            $stmtSE->bind_param("iiii", $userId, $seId, $pointsToAdd, $pointsToAdd);
            $stmtSE->execute();
            $stmtSE->close();
        }
        // ────────────────────────────────────────────────────────────────────────

        
        // Cập nhật tiến trình
        $newCompleted = $chapterNum;
        $newUnlocked = $state['unlocked_chapters'];
        
        // Nếu hoàn thành chương hiện tại và còn chương tiếp theo, tự động mở khóa chương mới
        if ($chapterNum == $state['unlocked_chapters'] && $chapterNum < $state['total_chapters']) {
            $newUnlocked++;
        }
        
        $stmt = $conn->prepare("UPDATE user_storyline_progress SET completed_chapters = ?, unlocked_chapters = ? WHERE user_id = ? AND storyline_id = ?");
        $stmt->bind_param("iiii", $newCompleted, $newUnlocked, $userId, $currentEventId);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => "Chúc mừng! Bạn đã húp thành công " . number_format($reward) . " GTLM từ Chương $chapterNum!",
            'reward' => $reward
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>
