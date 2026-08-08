<?php
/**
 * 🔮 API Oracle Prophecy v1.0
 * Hệ thống Sự Kiện "Lời Tiên Tri" hàng tuần.
 * 
 * Actions:
 *  - get_current    : Lấy tuần hiện tại + 3 lời tiên tri
 *  - witness        : Đánh dấu user đã "chứng kiến" (engagement)
 *  - get_buff       : Kiểm tra community buff đang active
 *  - admin_generate : (Admin) Tạo lời tiên tri cho tuần mới
 *  - admin_evaluate : (Admin) Chấm điểm cuối tuần
 */

require_once 'db_connect.php';
require_once 'admin_helper.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

// ─── Prophecy Templates Pool ────────────────────────────────────────────────
// Mỗi template: [text_lore, condition_type, condition_value_fn]
// condition_type khớp với logic trong evaluateProphecy()
$PROPHECY_TEMPLATES = [
    [
        'text' => "Khi bóng tối chớm phủ, Rồng Thần sẽ ngã xuống — linh khí tuôn trào khắp Trận Địa.",
        'type' => 'boss_killed',
        'value' => 1,
        'label' => 'World Boss bị hạ ít nhất 1 lần',
    ],
    [
        'text' => "Vận số của tuần này nằm trong tay kẻ dám cược lớn — ai bạo dạn, người đó gặt hái.",
        'type' => 'big_win_count',
        'value' => 10,
        'label' => 'Tổng Big Win ≥ 10 lần',
    ],
    [
        'text' => "Một liên minh hùng mạnh sẽ trỗi dậy và in dấu lãnh thổ trên bản đồ quyền lực.",
        'type' => 'guild_war_conquest',
        'value' => 1,
        'label' => 'Có ít nhất 1 lãnh thổ bị chiếm',
    ],
    [
        'text' => "Trận địa sẽ đón chào 50 chiến binh mới trong 7 ngày tới.",
        'type' => 'new_users',
        'value' => 5,
        'label' => 'Có ≥ 5 thành viên mới đăng ký',
    ],
    [
        'text' => "Vàng sẽ chảy như suối — tổng lộc tuần này vượt qua ngưỡng thiên thư.",
        'type' => 'total_winnings',
        'value' => 50000000,
        'label' => 'Tổng GTLM thắng toàn server ≥ 50M GTLM',
    ],
    [
        'text' => "Một đêm đặc biệt sẽ chứng kiến kẻ giành được kho báu khổng lồ — hơn 5 triệu trong một ván.",
        'type' => 'single_mega_win',
        'value' => 5000000,
        'label' => 'Có ít nhất 1 ván thắng ≥ 5 triệu',
    ],
    [
        'text' => "Số phận ủng hộ kẻ trung thành — hơn 20 linh hồn sẽ duy trì streak trong tuần này.",
        'type' => 'streak_holders',
        'value' => 20,
        'label' => '≥ 20 người có streak ≥ 5 ngày',
    ],
    [
        'text' => "Chiếc bánh xe vận mệnh sẽ quay hơn một ngàn vòng trước khi tuần tàn.",
        'type' => 'lucky_wheel_spins',
        'value' => 100,
        'label' => 'Tổng lần quay Lucky Wheel ≥ 100',
    ],
    [
        'text' => "Một cuộc đấu kiếm giữa hai anh hùng sẽ rung chuyển Trận Địa — PVP sẽ sôi sục như chưa từng.",
        'type' => 'pvp_battles',
        'value' => 30,
        'label' => 'Số trận PVP trong tuần ≥ 30',
    ],
    [
        'text' => "Cộng đồng sẽ đoàn kết — nhiệm vụ cộng đồng tuần này sẽ được hoàn thành.",
        'type' => 'community_challenge_completed',
        'value' => 1,
        'label' => 'Có ≥ 1 nhiệm vụ cộng đồng hoàn thành',
    ],
];

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * Lấy (hoặc tạo) tuần hiện tại trong oracle_prophecy_weeks
 */
function getOrCreateCurrentWeek(mysqli $conn): ?array {
    $monday = date('Y-m-d', strtotime('monday this week'));
    $sunday = date('Y-m-d', strtotime('sunday this week'));

    $stmt = $conn->prepare("SELECT * FROM oracle_prophecy_weeks WHERE week_start = ?");
    $stmt->bind_param("s", $monday);
    $stmt->execute();
    $week = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $week ?: null;
}

/**
 * Kiểm tra giá trị thực tế cho từng condition_type trong khoảng 1 tuần
 */
function evaluateCondition(mysqli $conn, string $type, int $conditionValue, string $weekStart, string $weekEnd): array {
    $actual = 0;
    switch ($type) {
        case 'boss_killed':
            $r = $conn->query("SELECT COUNT(*) as c FROM server_lore 
                WHERE event_type='boss_kill' 
                AND created_at BETWEEN '$weekStart 00:00:00' AND '$weekEnd 23:59:59'");
            $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
            break;

        case 'big_win_count':
            $r = $conn->query("SELECT COUNT(*) as c FROM arena_memory 
                WHERE event_type='big_win' 
                AND created_at BETWEEN '$weekStart 00:00:00' AND '$weekEnd 23:59:59'");
            $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
            break;

        case 'guild_war_conquest':
            $r = $conn->query("SELECT COUNT(*) as c FROM territories 
                WHERE last_reset BETWEEN '$weekStart 00:00:00' AND '$weekEnd 23:59:59'");
            $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
            break;

        case 'new_users':
            $r = $conn->query("SELECT COUNT(*) as c FROM users 
                WHERE created_at BETWEEN '$weekStart 00:00:00' AND '$weekEnd 23:59:59'");
            $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
            break;

        case 'total_winnings':
            $r = $conn->query("SELECT COALESCE(SUM(win_amount),0) as c FROM game_history 
                WHERE is_win=1 AND played_at BETWEEN '$weekStart 00:00:00' AND '$weekEnd 23:59:59'");
            $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
            break;

        case 'single_mega_win':
            $r = $conn->query("SELECT MAX(win_amount) as c FROM game_history 
                WHERE is_win=1 AND played_at BETWEEN '$weekStart 00:00:00' AND '$weekEnd 23:59:59'");
            $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
            break;

        case 'streak_holders':
            $r = $conn->query("SELECT COUNT(*) as c FROM user_streaks WHERE current_streak >= 5");
            $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
            break;

        case 'lucky_wheel_spins':
            $r = $conn->query("SELECT COUNT(*) as c FROM game_history 
                WHERE game_name LIKE '%Lucky Wheel%' 
                AND played_at BETWEEN '$weekStart 00:00:00' AND '$weekEnd 23:59:59'");
            $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
            break;

        case 'pvp_battles':
            $r = $conn->query("SELECT COUNT(*) as c FROM pvp_challenges 
                WHERE status='completed' 
                AND created_at BETWEEN '$weekStart 00:00:00' AND '$weekEnd 23:59:59'");
            $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
            break;

        case 'community_challenge_completed':
            $r = $conn->query("SELECT COUNT(*) as c FROM community_challenges 
                WHERE status='completed' 
                AND updated_at BETWEEN '$weekStart 00:00:00' AND '$weekEnd 23:59:59'");
            $actual = (int)($r ? $r->fetch_assoc()['c'] : 0);
            break;
    }

    return [
        'actual'  => $actual,
        'correct' => $actual >= $conditionValue,
    ];
}

// ─── Router ─────────────────────────────────────────────────────────────────
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = (int)($_SESSION['Iduser'] ?? 0);

if (!$userId && !in_array($action, ['get_current', 'get_buff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

switch ($action) {

    // ── GET CURRENT WEEK + PROPHECIES ──────────────────────────────────────
    case 'get_current':
        $week = getOrCreateCurrentWeek($conn);
        if (!$week) {
            echo json_encode(['success' => true, 'week' => null, 'prophecies' => [], 'buff' => null]);
            exit;
        }

        $prophecies = $conn->query("SELECT * FROM oracle_prophecies WHERE week_id = {$week['id']} ORDER BY prophecy_index")->fetch_all(MYSQLI_ASSOC);

        // Active community buff
        $buff = $conn->query("SELECT * FROM community_buffs WHERE buff_type='oracle_blessing' AND is_active=1 AND expires_at > NOW() ORDER BY id DESC LIMIT 1")->fetch_assoc();

        // Days left
        $daysLeft = max(0, (int)ceil((strtotime($week['week_end'] . ' 23:59:59') - time()) / 86400));

        // Witness count
        $wCount = (int)$conn->query("SELECT COUNT(*) as c FROM oracle_prophecy_witnesses WHERE week_id={$week['id']}")->fetch_assoc()['c'];

        // Current user witnessed?
        $hasWitnessed = false;
        if ($userId) {
            $wStmt = $conn->prepare("SELECT id FROM oracle_prophecy_witnesses WHERE week_id=? AND user_id=?");
            $wStmt->bind_param("ii", $week['id'], $userId);
            $wStmt->execute();
            $hasWitnessed = $wStmt->get_result()->num_rows > 0;
            $wStmt->close();
        }

        echo json_encode([
            'success'      => true,
            'week'         => $week,
            'prophecies'   => $prophecies,
            'buff'         => $buff,
            'days_left'    => $daysLeft,
            'witness_count'=> $wCount,
            'has_witnessed'=> $hasWitnessed,
        ]);
        break;

    // ── WITNESS (user clicks "Tôi Chứng Kiến") ─────────────────────────────
    case 'witness':
        $week = getOrCreateCurrentWeek($conn);
        if (!$week) { echo json_encode(['success' => false, 'message' => 'Chưa có tuần sự kiện']); exit; }

        $stmt = $conn->prepare("INSERT IGNORE INTO oracle_prophecy_witnesses (week_id, user_id) VALUES (?,?)");
        $stmt->bind_param("ii", $week['id'], $userId);
        $stmt->execute();
        $inserted = $stmt->affected_rows;
        $stmt->close();

        $wCount = (int)$conn->query("SELECT COUNT(*) as c FROM oracle_prophecy_witnesses WHERE week_id={$week['id']}")->fetch_assoc()['c'];

        echo json_encode([
            'success'      => true,
            'already'      => $inserted === 0,
            'witness_count'=> $wCount,
            'message'      => $inserted > 0 ? 'Ngươi đã chứng kiến Lời Tiên Tri!' : 'Ngươi đã chứng kiến rồi, Lão nhớ mặt ngươi.',
        ]);
        break;

    // ── GET ACTIVE COMMUNITY BUFF ───────────────────────────────────────────
    case 'get_buff':
        $buff = $conn->query("SELECT * FROM community_buffs WHERE is_active=1 AND expires_at > NOW() ORDER BY id DESC LIMIT 1")->fetch_assoc();
        echo json_encode(['success' => true, 'buff' => $buff]);
        break;

    // ── ADMIN: GENERATE NEW WEEK ────────────────────────────────────────────
    case 'admin_generate':
        if (!isAdmin()) { echo json_encode(['success' => false, 'message' => 'Forbidden']); exit; }
        global $PROPHECY_TEMPLATES;

        $monday = date('Y-m-d', strtotime('monday this week'));
        $sunday = date('Y-m-d', strtotime('sunday this week'));

        // Check if week already exists
        $existing = $conn->query("SELECT id FROM oracle_prophecy_weeks WHERE week_start='$monday'")->fetch_assoc();
        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Tuần này đã có lời tiên tri rồi!', 'week_id' => $existing['id']]);
            exit;
        }

        // Pick 3 unique random prophecies
        $pool = $PROPHECY_TEMPLATES;
        shuffle($pool);
        $chosen = array_slice($pool, 0, 3);

        $conn->begin_transaction();
        try {
            // Insert week
            $stmt = $conn->prepare("INSERT INTO oracle_prophecy_weeks (week_start, week_end, status) VALUES (?,?,'active')");
            $stmt->bind_param("ss", $monday, $sunday);
            $stmt->execute();
            $weekId = $conn->insert_id;
            $stmt->close();

            // Insert 3 prophecies
            $stmt = $conn->prepare("INSERT INTO oracle_prophecies (week_id, prophecy_index, prophecy_text, condition_type, condition_value) VALUES (?,?,?,?,?)");
            foreach ($chosen as $idx => $p) {
                $i = $idx + 1;
                $stmt->bind_param("iissi", $weekId, $i, $p['text'], $p['type'], $p['value']);
                $stmt->execute();
            }
            $stmt->close();

            $conn->commit();

            // Record to server lore
            require_once __DIR__ . '/lore_helper.php';
            recordServerLore($conn, 'oracle', '🔮 Lão Tiên Tri Giáng Lời', 
                "Đầu tuần " . date('d/m/Y', strtotime($monday)) . ", Lão Tiên Tri đã công bố 3 lời tiên tri huyền bí về vận mệnh Trận Địa. Toàn server nín thở chờ đợi...",
                2);

            echo json_encode(['success' => true, 'week_id' => $weekId, 'prophecies' => $chosen]);
        } catch (\Throwable $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ── ADMIN: EVALUATE END OF WEEK ─────────────────────────────────────────
    case 'admin_evaluate':
        if (!isAdmin()) { echo json_encode(['success' => false, 'message' => 'Forbidden']); exit; }

        $weekId = (int)($_POST['week_id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM oracle_prophecy_weeks WHERE id=?");
        $stmt->bind_param("i", $weekId);
        $stmt->execute();
        $week = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$week) { echo json_encode(['success' => false, 'message' => 'Không tìm thấy tuần']); exit; }
        if ($week['buff_granted']) { echo json_encode(['success' => false, 'message' => 'Tuần này đã được chấm điểm rồi']); exit; }

        $prophecies = $conn->query("SELECT * FROM oracle_prophecies WHERE week_id=$weekId")->fetch_all(MYSQLI_ASSOC);
        $correctCount = 0;
        $results = [];

        $conn->begin_transaction();
        try {
            foreach ($prophecies as $p) {
                $eval = evaluateCondition($conn, $p['condition_type'], (int)$p['condition_value'], $week['week_start'], $week['week_end']);
                $result  = $eval['correct'] ? 'correct' : 'wrong';
                $actual  = $eval['actual'];
                if ($eval['correct']) $correctCount++;

                $now = date('Y-m-d H:i:s');
                $upStmt = $conn->prepare("UPDATE oracle_prophecies SET result=?, actual_value=?, checked_at=? WHERE id=?");
                $upStmt->bind_param("sisi", $result, $actual, $now, $p['id']);
                $upStmt->execute();
                $upStmt->close();

                $results[] = ['id' => $p['id'], 'result' => $result, 'actual' => $actual, 'text' => $p['prophecy_text']];
            }

            // Grant buff if 3/3 correct
            $buffType  = null;
            $buffMsg   = "Lão Tiên Tri đã phán: $correctCount/3 lời tiên tri thành sự thật.";
            $buffExpires = date('Y-m-d H:i:s', strtotime('+7 days'));

            if ($correctCount === 3) {
                $buffType = 'oracle_blessing';
                $buffDesc = '🔮 Phúc Lành Tiên Tri: +20% GTLM cho toàn bộ chiến thắng trong 7 ngày!';
                $mult = 1.20;

                $bStmt = $conn->prepare("INSERT INTO community_buffs (buff_type, multiplier, description, source, expires_at, is_active) VALUES (?,?,?,'oracle',?,1)");
                $bStmt->bind_param("sdss", $buffType, $mult, $buffDesc, $buffExpires);
                $bStmt->execute();
                $bStmt->close();

                $buffMsg = "Kỳ tích! 3/3 lời tiên tri đã ứng nghiệm — Phúc Lành Tiên Tri ban xuống! Toàn server nhận +20% GTLM trong 7 ngày!";

                // Notify all users
                $allUsers = $conn->query("SELECT Iduser FROM users WHERE status='active' LIMIT 500");
                require_once __DIR__ . '/notification_helper.php';
                while ($u = $allUsers->fetch_assoc()) {
                    createNotification($conn, (int)$u['Iduser'], 'event_update',
                        '🔮 Phúc Lành Tiên Tri!',
                        $buffMsg,
                        '🔮', 'oracle_prophecy.php', null, true);
                }
            }

            // Mark week completed
            $upWeek = $conn->prepare("UPDATE oracle_prophecy_weeks SET status='completed', correct_count=?, buff_granted=1, buff_type=?, buff_expires_at=? WHERE id=?");
            $upWeek->bind_param("issi", $correctCount, $buffType, $buffExpires, $weekId);
            $upWeek->execute();
            $upWeek->close();

            $conn->commit();

            // Record lore
            require_once __DIR__ . '/lore_helper.php';
            $loreImportance = $correctCount === 3 ? 3 : ($correctCount >= 1 ? 2 : 1);
            recordServerLore($conn, 'oracle', "🔮 Lời Tiên Tri Được Xét Xử ($correctCount/3 Đúng)", $buffMsg, $loreImportance);

            echo json_encode([
                'success'       => true,
                'correct_count' => $correctCount,
                'buff_granted'  => $correctCount === 3,
                'buff_type'     => $buffType,
                'message'       => $buffMsg,
                'results'       => $results,
            ]);
        } catch (\Throwable $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ── GET HISTORY ────────────────────────────────────────────────────────
    case 'get_history':
        $rows = $conn->query(
            "SELECT week_start, week_end, correct_count, buff_type, status
             FROM oracle_prophecy_weeks
             WHERE status='completed'
             ORDER BY week_start DESC
             LIMIT 8"
        )->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'history' => $rows]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
?>
