<?php
session_start();
if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập']); exit;
}
require_once 'db_connect.php';
require_once 'user_progress_helper.php';

$userId = (int)$_SESSION['Iduser'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'get_active';

// ── Lấy event đang chạy ─────────────────────────────────────────────
if ($action === 'get_active') {
    $event = $conn->query("
        SELECT *, TIMESTAMPDIFF(SECOND, NOW(), ends_at) as seconds_left
        FROM random_events 
        WHERE is_active = 1 AND ends_at > NOW()
        LIMIT 1
    ")->fetch_assoc();

    if (!$event) {
        echo json_encode(['status' => 'none']); exit;
    }

    // Check user đã tham gia chưa
    $stmtPart = $conn->prepare("
        SELECT reward_given FROM random_event_participants 
        WHERE event_id = ? AND user_id = ?
    ");
    $stmtPart->bind_param("ii", $event['id'], $userId);
    $stmtPart->execute();
    $participated = $stmtPart->get_result()->fetch_assoc();
    $stmtPart->close();

    $event['config']       = json_decode($event['config'], true);
    $event['participated'] = $participated ? true : false;
    $event['reward_given'] = $participated['reward_given'] ?? false;

    echo json_encode(['status' => 'active', 'event' => $event]);
    exit;
}

// ── Lấy lịch sử tham gia Random Events ────────────────────────────────
if ($action === 'get_history') {
    $stmtHist = $conn->prepare("
        SELECT p.reward_amount, r.event_name, r.event_type, r.ended_real_at
        FROM random_event_participants p
        JOIN random_events r ON p.event_id = r.id
        WHERE p.user_id = ? AND p.reward_given = 1
        ORDER BY p.id DESC LIMIT 20
    ");
    $stmtHist->bind_param("i", $userId);
    $stmtHist->execute();
    $history = $stmtHist->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtHist->close();
    
    echo json_encode(['status' => 'success', 'history' => $history]);
    exit;
}

// ── Tự động tắt event hết hạn ───────────────────────────────────────
$expired = $conn->query("SELECT id FROM random_events WHERE ends_at <= NOW() AND is_active = 1")->fetch_all(MYSQLI_ASSOC);
foreach ($expired as $ex) {
    $stmtUpExp = $conn->prepare("UPDATE random_events SET is_active = 0, ended_real_at = NOW() WHERE id = ?");
    $stmtUpExp->bind_param("i", $ex['id']);
    $stmtUpExp->execute();
    $stmtUpExp->close();
}

// Lấy event hiện tại
$event = $conn->query("
    SELECT * FROM random_events 
    WHERE is_active = 1 AND ends_at > NOW() LIMIT 1
")->fetch_assoc();

if (!$event) {
    echo json_encode(['status' => 'error', 'message' => 'Không có event đang chạy']); exit;
}

$config  = json_decode($event['config'], true);
$eventId = (int)$event['id'];

// Kiểm tra đã tham gia chưa (cho các action 1-lần)
$oneTimeActions = ['claim_rain', 'open_box', 'guess_number'];
if (in_array($action, $oneTimeActions)) {
    $stmtCheck = $conn->prepare("
        SELECT id FROM random_event_participants 
        WHERE event_id = ? AND user_id = ?
    ");
    $stmtCheck->bind_param("ii", $eventId, $userId);
    $stmtCheck->execute();
    $check = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if ($check) {
        echo json_encode(['status' => 'error', 'message' => 'Bạn đã tham gia event này rồi!']); exit;
    }
}

// ── Xử lý từng loại event ───────────────────────────────────────────

// 💸 Mưa GTLM — nhận ngẫu nhiên
if ($action === 'claim_rain' && $event['event_type'] === 'money_rain') {
    $stmtClaimed = $conn->prepare("SELECT COUNT(*) as c FROM random_event_participants WHERE event_id = ?");
    $stmtClaimed->bind_param("i", $eventId);
    $stmtClaimed->execute();
    $claimed = (int)$stmtClaimed->get_result()->fetch_assoc()['c'];
    $stmtClaimed->close();

    if ($claimed >= ($config['max_claims'] ?? 50)) {
        echo json_encode(['status' => 'error', 'message' => 'Hết lượt rồi! Nhanh hơn lần sau nhé 😅']); exit;
    }

    $reward = rand($config['min_reward'], $config['max_reward']);
    $conn->begin_transaction();
    try {
        $stmtUpMoney = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
        $stmtUpMoney->bind_param("ii", $reward, $userId);
        $stmtUpMoney->execute();
        $stmtUpMoney->close();

        $ins = $conn->prepare("INSERT INTO random_event_participants (event_id, user_id, reward_given, reward_amount) VALUES (?, ?, 1, ?)");
        $ins->bind_param("iii", $eventId, $userId, $reward);
        $ins->execute();
        $ins->close();
        $conn->commit();
        echo json_encode(['status' => 'success', 'reward' => $reward, 'message' => '🎉 Bạn nhận được ' . number_format($reward) . ' GTLM!']);
    } catch (Exception $e) { $conn->rollback(); echo json_encode(['status' => 'error', 'message' => 'Lỗi server']); }
    exit;
}

// 🎁 Hộp quà bí ẩn — quay số theo weight
if ($action === 'open_box' && $event['event_type'] === 'mystery_box') {
    $prizes = $config['prizes'];
    $totalW = array_sum(array_column($prizes, 'weight'));
    $roll = rand(1, $totalW);
    $cum = 0; $prize = end($prizes);
    foreach ($prizes as $p) { $cum += $p['weight']; if ($roll <= $cum) { $prize = $p; break; } }

    $conn->begin_transaction();
    try {
        if ($prize['type'] === 'gtlm') { 
            $stmtUpG = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            $stmtUpG->bind_param("ii", $prize['amount'], $userId);
            $stmtUpG->execute();
            $stmtUpG->close();
        } 
        elseif ($prize['type'] === 'xp') { up_add_xp($conn, $userId, $prize['amount']); }

        $ins = $conn->prepare("INSERT INTO random_event_participants (event_id, user_id, reward_given, reward_amount) VALUES (?, ?, 1, ?)");
        $ins->bind_param("iii", $eventId, $userId, $prize['amount']);
        $ins->execute();
        $ins->close();
        $conn->commit();
        echo json_encode(['status' => 'success', 'prize' => $prize, 'message' => '🎁 Bạn nhận được: ' . $prize['label'] . '!']);
    } catch (Exception $e) { $conn->rollback(); echo json_encode(['status' => 'error', 'message' => 'Lỗi server']); }
    exit;
}

// 🔢 Đoán số may mắn
if ($action === 'guess_number' && $event['event_type'] === 'lucky_number') {
    $guess = (int)($_POST['guess'] ?? 0);
    $range = (int)($config['number_range'] ?? 10);
    if ($guess < 1 || $guess > $range) { echo json_encode(['status' => 'error', 'message' => "Nhập số từ 1 đến $range"]); exit; }

    // Sinh số random và lưu vào DB nếu config chưa có lucky_number
    if (!isset($config['lucky_number'])) {
        $config['lucky_number'] = rand(1, $range);
        $newConfig = json_encode($config);
        $stmtUpdateConfig = $conn->prepare("UPDATE random_events SET config = ? WHERE id = ?");
        $stmtUpdateConfig->bind_param("si", $newConfig, $eventId);
        $stmtUpdateConfig->execute();
        $stmtUpdateConfig->close();
    }
    $luckyNumber = (int)$config['lucky_number'];
    
    $stmtWinners = $conn->prepare("SELECT COUNT(*) as c FROM random_event_participants WHERE event_id = ? AND reward_given = 1");
    $stmtWinners->bind_param("i", $eventId);
    $stmtWinners->execute();
    $winners = (int)$stmtWinners->get_result()->fetch_assoc()['c'];
    $stmtWinners->close();

    $isWin = ($guess === $luckyNumber) && ($winners < ($config['max_winners'] ?? 5));
    $reward = $isWin ? (int)$config['reward'] : 0;

    $conn->begin_transaction();
    try {
        if ($isWin) { 
            $stmtUpW = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            $stmtUpW->bind_param("ii", $reward, $userId);
            $stmtUpW->execute();
            $stmtUpW->close();
        }
        $ins = $conn->prepare("INSERT INTO random_event_participants (event_id, user_id, reward_given, reward_amount) VALUES (?, ?, ?, ?)");
        $given = $isWin ? 1 : 0;
        $ins->bind_param("iiii", $eventId, $userId, $given, $reward);
        $ins->execute();
        $ins->close();
        $conn->commit();
        echo json_encode(['status' => 'success', 'correct' => $isWin, 'lucky_number' => $luckyNumber, 'reward' => $reward, 'message' => $isWin ? "🎉 ĐÚNG RỒI! Nhận " . number_format($reward) . " GTLM!" : "❌ Sai rồi! Số may mắn là $luckyNumber"]);
    } catch (Exception $e) { $conn->rollback(); echo json_encode(['status' => 'error', 'message' => 'Lỗi server']); }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ']);
?>
