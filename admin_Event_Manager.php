<?php
session_start();
require_once 'db_connect.php';
require_once 'api_event_helper.php';
require_once 'admin_helper.php';

// Generate CSRF token if not already exists in session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = $_SESSION['Iduser'] ?? 0;
requireAdmin($conn, $userId);

$action = $_GET['action'] ?? '';
$msg = $_GET['msg'] ?? '';

// ==========================================
// ⚡ XỬ LÝ CÁC THAY ĐỔI QUA POST
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die("Lỗi: Yêu cầu không hợp lệ (CSRF Token Verification Failed).");
    }
    
    // 1. GAME OF THE DAY
    if (isset($_POST['set_gotd'])) {
        $game = $_POST['game_name'];
        $today = date('Y-m-d');
        $stmt = $conn->prepare("INSERT INTO daily_tournament_records (game_name, event_date) VALUES (?, ?) ON DUPLICATE KEY UPDATE game_name = ?");
        $stmt->bind_param("sss", $game, $today, $game);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_Event_Manager.php?tab=gotd&msg=Game of the Day updated!");
        exit;
    }

    // 2. VIP TRIAL (24h)
    if (isset($_POST['grant_vip'])) {
        $userName = $_POST['user_name'];
        $stmt = $conn->prepare("SELECT Iduser FROM users WHERE Name = ?");
        $stmt->bind_param("s", $userName);
        $stmt->execute();
        $userRes = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($userRes) {
            $uId = $userRes['Iduser'];
            $sql = "UPDATE users SET vip_expiry = IF(vip_expiry > NOW(), DATE_ADD(vip_expiry, INTERVAL 24 HOUR), DATE_ADD(NOW(), INTERVAL 24 HOUR)) WHERE Iduser = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $uId);
            $stmt->execute();
            $stmt->close();
            header("Location: admin_Event_Manager.php?tab=gotd&msg=" . urlencode("VIP Trial (24h) granted to $userName!"));
        } else {
            header("Location: admin_Event_Manager.php?tab=gotd&msg=" . urlencode("User not found!"));
        }
        exit;
    }

    // 3. SEASONAL PASS CONFIGS (Original System)
    if (isset($_POST['create_season'])) {
        $name = $_POST['season_name'];
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];
        $color = $_POST['theme_color'];
        $boss = $_POST['boss_name'];
        $hp = $_POST['boss_hp'];

        $stmt = $conn->prepare("INSERT INTO seasonal_pass_configs (name, start_date, end_date, theme_color, boss_name, boss_hp_max) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $name, $start, $end, $color, $boss, $hp);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_Event_Manager.php?tab=pass&msg=Seasonal Pass config created!");
        exit;
    }

    // 4. SEASONAL PASS REWARDS (Original System)
    if (isset($_POST['add_reward'])) {
        $seasonId = $_POST['season_id'];
        $level = $_POST['level'];
        $type = $_POST['reward_type'];
        $val = $_POST['reward_value'];
        $premium = isset($_POST['is_premium']) ? 1 : 0;

        $stmt = $conn->prepare("INSERT INTO seasonal_pass_levels (season_id, level, reward_type, reward_value, is_premium) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iissi", $seasonId, $level, $type, $val, $premium);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_Event_Manager.php?tab=pass&msg=Reward level added for Seasonal Pass!");
        exit;
    }

    // 5. SEASONAL EVENTS (New System)
    if (isset($_POST['save_seasonal_event'])) {
        $id = (int)($_POST['event_id'] ?? 0);
        $name = $_POST['name'];
        $emoji = $_POST['theme_emoji'];
        $starts = $_POST['starts_at'];
        $ends = $_POST['ends_at'];
        $cost = (int)$_POST['spin_cost'];
        $status = $_POST['status'];
        $milestones = trim($_POST['milestone_config'] ?: '[]');
        if (json_decode($milestones) === null && json_last_error() !== JSON_ERROR_NONE) {
            die("Lỗi: Cấu hình Milestone JSON không hợp lệ. Vui lòng kiểm tra lại syntax!");
        }
        $theme = trim($_POST['theme_config'] ?: '{}');
        if (json_decode($theme) === null && json_last_error() !== JSON_ERROR_NONE) {
            die("Lỗi: Cấu hình Theme JSON không hợp lệ.");
        }
        $chains = trim($_POST['chain_config'] ?: '[]');
        if (json_decode($chains) === null && json_last_error() !== JSON_ERROR_NONE) {
            die("Lỗi: Cấu hình Chain JSON không hợp lệ. Vui lòng kiểm tra lại syntax!");
        }

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE seasonal_events SET name = ?, theme_emoji = ?, starts_at = ?, ends_at = ?, spin_cost = ?, status = ?, milestone_config = ?, theme_config = ?, chain_config = ? WHERE id = ?");
            $stmt->bind_param("ssssissssi", $name, $emoji, $starts, $ends, $cost, $status, $milestones, $theme, $chains, $id);
            $stmt->execute();
            $stmt->close();
            header("Location: admin_Event_Manager.php?tab=events&msg=Seasonal Event updated!");
        } else {
            $stmt = $conn->prepare("INSERT INTO seasonal_events (name, theme_emoji, starts_at, ends_at, spin_cost, status, milestone_config, theme_config, chain_config) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssissss", $name, $emoji, $starts, $ends, $cost, $status, $milestones, $theme, $chains);
            $stmt->execute();
            $stmt->close();
            header("Location: admin_Event_Manager.php?tab=events&msg=New Seasonal Event launched!");
        }
        exit;
    }

    // 6. DELETE SEASONAL EVENT
    if (isset($_POST['delete_seasonal_event'])) {
        $id = (int)$_POST['event_id'];
        
        $stmtD1 = $conn->prepare("DELETE FROM event_missions WHERE event_id = ?");
        $stmtD1->bind_param("i", $id); $stmtD1->execute(); $stmtD1->close();

        $stmtD2 = $conn->prepare("DELETE FROM event_rewards WHERE event_id = ?");
        $stmtD2->bind_param("i", $id); $stmtD2->execute(); $stmtD2->close();

        $stmtD3 = $conn->prepare("DELETE FROM event_exchange_shop WHERE event_id = ?");
        $stmtD3->bind_param("i", $id); $stmtD3->execute(); $stmtD3->close();

        $stmtD4 = $conn->prepare("DELETE FROM seasonal_events WHERE id = ?");
        $stmtD4->bind_param("i", $id); $stmtD4->execute(); $stmtD4->close();

        header("Location: admin_Event_Manager.php?tab=events&msg=" . urlencode("Event and associated data permanently deleted!"));
        exit;
    }

    // 7. TOGGLE STATUS
    if (isset($_POST['toggle_status'])) {
        $id = (int)$_POST['event_id'];

        $stmtCurr = $conn->prepare("SELECT status FROM seasonal_events WHERE id = ?");
        $stmtCurr->bind_param("i", $id);
        $stmtCurr->execute();
        $curr = $stmtCurr->get_result()->fetch_assoc()['status'];
        $stmtCurr->close();

        $newStatus = ($curr === 'active') ? 'inactive' : 'active';

        if ($newStatus === 'active') {
            // Đảm bảo chỉ 1 event được active tại 1 thời điểm
            $stmtResetActive = $conn->prepare("UPDATE seasonal_events SET status = 'inactive' WHERE status = 'active'");
            $stmtResetActive->execute();
            $stmtResetActive->close();
        }

        $stmt = $conn->prepare("UPDATE seasonal_events SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_Event_Manager.php?tab=events&msg=Event status toggled successfully!");
        exit;
    }

    // 8. EXTEND EVENT
    if (isset($_POST['extend_event'])) {
        $id = (int)$_POST['event_id'];
        $days = (int)$_POST['extend_days'];
        $stmt = $conn->prepare("UPDATE seasonal_events SET ends_at = DATE_ADD(ends_at, INTERVAL ? DAY) WHERE id = ?");
        $stmt->bind_param("ii", $days, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_Event_Manager.php?tab=events&msg=Event duration extended by $days days!");
        exit;
    }

    // 9. DUPLICATE EVENT (Clone Event & Child Records)
    if (isset($_POST['duplicate_event'])) {
        $id = (int)$_POST['event_id'];
        
        $stmtOrig = $conn->prepare("SELECT * FROM seasonal_events WHERE id = ?");
        $stmtOrig->bind_param("i", $id);
        $stmtOrig->execute();
        $orig = $stmtOrig->get_result()->fetch_assoc();
        $stmtOrig->close();
        
        if ($orig) {
            $newName = "Bản sao - " . $orig['name'];
            $stmt = $conn->prepare("INSERT INTO seasonal_events (name, theme_emoji, starts_at, ends_at, spin_cost, status, milestone_config, theme_config, chain_config) VALUES (?, ?, ?, ?, ?, 'inactive', ?, ?, ?)");
            $stmt->bind_param("ssssisss", $newName, $orig['theme_emoji'], $orig['starts_at'], $orig['ends_at'], $orig['spin_cost'], $orig['milestone_config'], $orig['theme_config'], $orig['chain_config']);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();

            // Clone Missions — FIX: include cycle and prerequisite_mission_id
            $stmtMis = $conn->prepare("SELECT * FROM event_missions WHERE event_id = ?");
            $stmtMis->bind_param("i", $id);
            $stmtMis->execute();
            $missions = $stmtMis->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtMis->close();
            
            // Build old_id -> new_id map to remap prerequisite references
            $missionIdMap = [];
            foreach ($missions as $m) {
                $stmtM = $conn->prepare("INSERT INTO event_missions (event_id, title, mission_type, target_value, reward_currency, reward_xp, cycle, prerequisite_mission_id) VALUES (?, ?, ?, ?, ?, ?, ?, NULL)");
                $stmtM->bind_param("issiiis", $newId, $m['title'], $m['mission_type'], $m['target_value'], $m['reward_currency'], $m['reward_xp'], $m['cycle']);
                $stmtM->execute();
                $missionIdMap[$m['id']] = $stmtM->insert_id;
                $stmtM->close();
            }
            // Second pass: update prerequisite_mission_id using the new mapped IDs
            $stmtUpdPrereq = $conn->prepare("UPDATE event_missions SET prerequisite_mission_id = ? WHERE id = ?");
            foreach ($missions as $m) {
                if (!empty($m['prerequisite_mission_id']) && isset($missionIdMap[$m['prerequisite_mission_id']])) {
                    $newMissionId  = $missionIdMap[$m['id']];
                    $newPrereqId   = $missionIdMap[$m['prerequisite_mission_id']];
                    $stmtUpdPrereq->bind_param("ii", $newPrereqId, $newMissionId);
                    $stmtUpdPrereq->execute();
                }
            }
            $stmtUpdPrereq->close();

            // Clone Rewards
            $stmtRew = $conn->prepare("SELECT * FROM event_rewards WHERE event_id = ?");
            $stmtRew->bind_param("i", $id);
            $stmtRew->execute();
            $rewards = $stmtRew->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtRew->close();
            
            foreach ($rewards as $r) {
                $stmtR = $conn->prepare("INSERT INTO event_rewards (event_id, reward_type, reward_value, reward_name, reward_icon, weight, is_limited, quantity_left) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtR->bind_param("issssiii", $newId, $r['reward_type'], $r['reward_value'], $r['reward_name'], $r['reward_icon'], $r['weight'], $r['is_limited'], $r['quantity_left']);
                $stmtR->execute();
                $stmtR->close();
            }

            // Clone Exchange Shop Items
            $stmtShp = $conn->prepare("SELECT * FROM event_exchange_shop WHERE event_id = ?");
            $stmtShp->bind_param("i", $id);
            $stmtShp->execute();
            $shop = $stmtShp->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtShp->close();
            
            foreach ($shop as $s) {
                $stmtS = $conn->prepare("INSERT INTO event_exchange_shop (event_id, item_name, item_type, item_id, cost_currency, limit_per_user, total_stock) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtS->bind_param("isssiii", $newId, $s['item_name'], $s['item_type'], $s['item_id'], $s['cost_currency'], $s['limit_per_user'], $s['total_stock']);
                $stmtS->execute();
                $stmtS->close();
            }

            header("Location: admin_Event_Manager.php?tab=events&msg=Event cloned successfully with all missions, rewards, and exchange items!");
        } else {
            header("Location: admin_Event_Manager.php?tab=events&msg=Original event not found!");
        }
        exit;
    }

    // 10. SUB-MANAGER: ADD MISSION
    if (isset($_POST['add_mission'])) {
        $eventId = (int)$_POST['event_id'];
        $title = $_POST['title'];
        $type = $_POST['mission_type'];
        $target = (int)$_POST['target_value'];
        $cur = (int)$_POST['reward_currency'];
        $xp = (int)$_POST['reward_xp'];
        $cycle = $_POST['cycle'] ?? 'permanent';
        $prereqId = !empty($_POST['prerequisite_mission_id']) ? (int)$_POST['prerequisite_mission_id'] : null;

        $stmt = $conn->prepare("INSERT INTO event_missions (event_id, title, mission_type, target_value, reward_currency, reward_xp, cycle, prerequisite_mission_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issiiisi", $eventId, $title, $type, $target, $cur, $xp, $cycle, $prereqId);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_Event_Manager.php?tab=events&manage_event_id=$eventId&msg=Mission added successfully!");
        exit;
    }

    // 11. SUB-MANAGER: DELETE MISSION
    if (isset($_POST['delete_mission'])) {
        $id = (int)$_POST['mission_id'];
        $eventId = (int)$_POST['event_id'];
        $conn->query("DELETE FROM event_missions WHERE id = $id");
        header("Location: admin_Event_Manager.php?tab=events&manage_event_id=$eventId&msg=Mission deleted!");
        exit;
    }

    // 12. SUB-MANAGER: ADD SPIN REWARD
    if (isset($_POST['add_spin_reward'])) {
        $eventId = (int)$_POST['event_id'];
        $type = $_POST['reward_type'];
        $val = $_POST['reward_value'];
        $name = $_POST['reward_name'];
        $icon = $_POST['reward_icon'];
        $weight = (int)$_POST['weight'];
        $limited = isset($_POST['is_limited']) ? 1 : 0;
        $qty = (int)$_POST['quantity_left'];

        $stmt = $conn->prepare("INSERT INTO event_rewards (event_id, reward_type, reward_value, reward_name, reward_icon, weight, is_limited, quantity_left) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssiii", $eventId, $type, $val, $name, $icon, $weight, $limited, $qty);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_Event_Manager.php?tab=events&manage_event_id=$eventId&msg=Spin reward added!");
        exit;
    }

    // 13. SUB-MANAGER: DELETE SPIN REWARD
    if (isset($_POST['delete_spin_reward'])) {
        $id = (int)$_POST['reward_id'];
        $eventId = (int)$_POST['event_id'];
        $conn->query("DELETE FROM event_rewards WHERE id = $id");
        header("Location: admin_Event_Manager.php?tab=events&manage_event_id=$eventId&msg=Spin reward deleted!");
        exit;
    }

    // 14. SUB-MANAGER: ADD EXCHANGE ITEM
    if (isset($_POST['add_shop_item'])) {
        $eventId = (int)$_POST['event_id'];
        $name = $_POST['item_name'];
        $type = $_POST['item_type'];
        $itemId = (int)$_POST['item_id'];
        $cost = (int)$_POST['cost_currency'];
        $limit = (int)$_POST['limit_per_user'];
        $stock = (int)$_POST['total_stock'];

        $stmt = $conn->prepare("INSERT INTO event_exchange_shop (event_id, item_name, item_type, item_id, cost_currency, limit_per_user, total_stock) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssiii", $eventId, $name, $type, $itemId, $cost, $limit, $stock);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_Event_Manager.php?tab=events&manage_event_id=$eventId&msg=Exchange shop item added!");
        exit;
    }

    // 15. SUB-MANAGER: DELETE EXCHANGE ITEM
    if (isset($_POST['delete_shop_item'])) {
        $id = (int)$_POST['item_id'];
        $eventId = (int)$_POST['event_id'];
        $conn->query("DELETE FROM event_exchange_shop WHERE id = $id");
        header("Location: admin_Event_Manager.php?tab=events&manage_event_id=$eventId&msg=Shop item deleted!");
        exit;
    }

    // 16. MANAGER: ADD VOTING OPTION
    if (isset($_POST['add_vote_option'])) {
        $title = $_POST['title'];
        $desc = $_POST['description'];
        $icon = $_POST['icon'];
        $stmt = $conn->prepare("INSERT INTO event_voting_options (title, description, icon) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $title, $desc, $icon);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_Event_Manager.php?tab=votes&msg=Thêm lựa chọn bình chọn thành công!");
        exit;
    }

    // 17. MANAGER: DELETE VOTING OPTION
    if (isset($_POST['delete_vote_option'])) {
        $id = (int)$_POST['option_id'];
        $conn->query("DELETE FROM event_voting_options WHERE id = $id");
        $conn->query("DELETE FROM user_event_votes WHERE option_id = $id");
        header("Location: admin_Event_Manager.php?tab=votes&msg=Đã xóa lựa chọn bình chọn!");
        exit;
    }

    // 18. MANAGER: RESET VOTES
    if (isset($_POST['reset_votes'])) {
        $conn->query("UPDATE event_voting_options SET votes = 0");
        $conn->query("TRUNCATE TABLE user_event_votes");
        header("Location: admin_Event_Manager.php?tab=votes&msg=Đã làm mới toàn bộ kết quả bình chọn!");
        exit;
    }
}

// ==========================================
// 🔍 LẤY DỮ LIỆU HIỂN THỊ
// ==========================================
$currentGotd = EventHelper::getGameOfTheDay($conn);
$seasons = $conn->query("SELECT * FROM seasonal_pass_configs ORDER BY start_date DESC")->fetch_all(MYSQLI_ASSOC);
$seasonalEvents = $conn->query("SELECT * FROM seasonal_events ORDER BY starts_at DESC")->fetch_all(MYSQLI_ASSOC);
$availableGames = ['Baccarat', 'Blackjack', 'Roulette', 'Sicbo', 'Tài Xỉu', 'RPS', 'Vietlott', 'Xóc Đĩa', 'Poker', 'Bầu Cua', 'Slot Cyber', 'Mega Spin', 'Horse Race'];

// Logic load Event phụ thuộc để quản lý chi tiết (Missions, Spin rewards, Shop)
$manageEventId = (int)($_GET['manage_event_id'] ?? 0);
$selectedEvent = null;
$eventMissions = [];
$eventRewards = [];
$eventShop = [];

if ($manageEventId > 0) {
    $selectedEvent = $conn->query("SELECT * FROM seasonal_events WHERE id = $manageEventId")->fetch_assoc();
    if ($selectedEvent) {
        $eventMissions = $conn->query("SELECT * FROM event_missions WHERE event_id = $manageEventId")->fetch_all(MYSQLI_ASSOC);
        $eventRewards = $conn->query("SELECT * FROM event_rewards WHERE event_id = $manageEventId")->fetch_all(MYSQLI_ASSOC);
        $eventShop = $conn->query("SELECT * FROM event_exchange_shop WHERE event_id = $manageEventId")->fetch_all(MYSQLI_ASSOC);
    }
}

// Fetch voting options
$votingOptions = [];
$votingOptionsCheck = $conn->query("SHOW TABLES LIKE 'event_voting_options'");
if ($votingOptionsCheck && $votingOptionsCheck->num_rows > 0) {
    $votingOptions = $conn->query("SELECT * FROM event_voting_options ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
}

// 📊 LẤY DỮ LIỆU ANALYTICS CHO SỰ KIỆN ĐANG HOẠT ĐỘNG
$activeEventIdRes = getActiveSeasonalEvent($conn, false, 'id, name');
$analyticsEventId = $activeEventIdRes['id'] ?? 0;
$analyticsName = $activeEventIdRes['name'] ?? '';

$analyticsData = [
    'total_entered' => 0,
    'total_completed_mission' => 0,
    'completion_rate' => 0,
    'abandoned_missions' => [],
    'peak_hours' => []
];

if ($analyticsEventId > 0) {
    $analyticsData['total_entered'] = (int)$conn->query("SELECT COUNT(DISTINCT user_id) as c FROM user_event_data WHERE event_id = $analyticsEventId")->fetch_assoc()['c'];
    
    $analyticsData['total_completed_mission'] = (int)$conn->query("
        SELECT COUNT(DISTINCT p.user_id) as c 
        FROM user_mission_progress p 
        JOIN event_missions m ON p.mission_id = m.id 
        WHERE m.event_id = $analyticsEventId AND p.is_completed = 1
    ")->fetch_assoc()['c'];
    
    if ($analyticsData['total_entered'] > 0) {
        $analyticsData['completion_rate'] = round(($analyticsData['total_completed_mission'] / $analyticsData['total_entered']) * 100, 1);
    }
    
    // Nhiệm vụ bị bỏ / tỷ lệ hoàn thành thấp nhất
    $analyticsData['abandoned_missions'] = $conn->query("
        SELECT m.title, 
               COUNT(p.id) as total_started, 
               SUM(CASE WHEN p.is_completed = 1 THEN 1 ELSE 0 END) as total_completed
        FROM event_missions m
        LEFT JOIN user_mission_progress p ON m.id = p.mission_id
        WHERE m.event_id = $analyticsEventId
        GROUP BY m.id
        ORDER BY total_completed ASC, total_started DESC
        LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);
    
    // Top nhiệm vụ được hoàn thành nhiều nhất
    $analyticsData['top_missions'] = $conn->query("
        SELECT m.title, COUNT(p.id) as completed_count
        FROM event_missions m
        JOIN user_mission_progress p ON m.id = p.mission_id AND p.is_completed = 1
        WHERE m.event_id = $analyticsEventId
        GROUP BY m.id
        ORDER BY completed_count DESC
        LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);

    // Top item được đổi nhiều nhất
    $analyticsData['top_shop_items'] = $conn->query("
        SELECT item_name, item_type, COUNT(*) as exchange_count
        FROM event_exchange_history
        WHERE event_id = $analyticsEventId
        GROUP BY item_name, item_type
        ORDER BY exchange_count DESC
        LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);

    // Tổng xu sự kiện đã phát
    $analyticsData['total_currency_distributed'] = (int)$conn->query("
        SELECT COALESCE(SUM(event_currency), 0) as total FROM user_event_data WHERE event_id = $analyticsEventId
    ")->fetch_assoc()['total'];

    // Tỉ lệ chuyển đổi: người vào vs hoàn thành ít nhất 1 nhiệm vụ
    $atLeastOne = (int)$conn->query("
        SELECT COUNT(DISTINCT p.user_id) as c
        FROM user_mission_progress p
        JOIN event_missions m ON p.mission_id = m.id
        WHERE m.event_id = $analyticsEventId AND p.is_completed = 1
    ")->fetch_assoc()['c'];
    $analyticsData['conversion_rate'] = $analyticsData['total_entered'] > 0
        ? round(($atLeastOne / $analyticsData['total_entered']) * 100, 1)
        : 0;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Event Manager Dashboard | Advanced Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.4);
            --bg: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #f59e0b;
            --danger: #ef4444;
            --success: #22c55e;
            --info: #3b82f6;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            margin: 0;
            padding: 40px 20px;
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(245, 158, 11, 0.1) 0px, transparent 50%);
            min-height: 100vh;
        }

        .container { max-width: 1280px; margin: 0 auto; }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        h1 { font-size: 2.5rem; font-weight: 900; margin: 0; background: linear-gradient(to right, #818cf8, #f472b6); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }

        /* Navigation Tabs */
        .tabs-header {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 15px;
        }
        
        .tab-btn {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            color: var(--text-muted);
            padding: 12px 24px;
            border-radius: 14px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn:hover {
            color: white;
            background: rgba(255,255,255,0.06);
            transform: translateY(-1px);
        }

        .tab-btn.active {
            background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%);
            color: white;
            border-color: transparent;
            box-shadow: 0 8px 20px var(--primary-glow);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.4s ease-in-out forwards;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .grid-2 { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
        .grid-half { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }

        .card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            margin-bottom: 30px;
            position: relative;
        }

        .card h2 { margin-top: 0; display: flex; align-items: center; gap: 10px; color: var(--accent); font-weight: 800; font-size: 1.6rem; }
        .card h3 { margin-top: 0; color: #fff; font-weight: 700; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 10px; }

        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-muted); font-size: 0.9rem; }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 16px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: white;
            font-family: inherit;
            box-sizing: border-box;
            font-size: 0.95rem;
            transition: all 0.2s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 8px 16px var(--primary-glow);
            font-family: inherit;
            font-size: 1rem;
        }

        button:hover { transform: translateY(-2px); box-shadow: 0 12px 24px var(--primary-glow); }
        
        .btn-outline {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: none;
        }
        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            box-shadow: none;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
            box-shadow: 0 8px 16px rgba(239, 68, 68, 0.3);
        }
        .btn-danger:hover { box-shadow: 0 12px 24px rgba(239, 68, 68, 0.4); }

        .btn-info {
            background: linear-gradient(135deg, var(--info) 0%, #2563eb 100%);
            box-shadow: 0 8px 16px rgba(59, 130, 246, 0.3);
        }
        .btn-info:hover { box-shadow: 0 12px 24px rgba(59, 130, 246, 0.4); }

        .btn-success {
            background: linear-gradient(135deg, var(--success) 0%, #16a34a 100%);
            box-shadow: 0 8px 16px rgba(34, 197, 150, 0.3);
        }
        .btn-success:hover { box-shadow: 0 12px 24px rgba(34, 197, 150, 0.4); }

        /* Badges & Tables */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
        }
        .badge-success { background: rgba(34, 197, 94, 0.15); color: var(--success); border: 1px solid rgba(34, 197, 94, 0.3); }
        .badge-inactive { background: rgba(148, 163, 184, 0.15); color: #cbd5e1; border: 1px solid rgba(148, 163, 184, 0.3); }
        .badge-upcoming { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { text-align: left; color: var(--text-muted); padding: 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); font-size: 0.9rem; font-weight: 600; }
        td { padding: 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.04); font-size: 0.95rem; }
        
        tr:hover td { background: rgba(255, 255, 255, 0.02); }

        .season-item {
            padding: 16px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.03);
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(255,255,255,0.05);
            transition: all 0.2s;
        }
        .season-item:hover {
            background: rgba(255,255,255,0.05);
        }

        .alert {
            padding: 16px 20px;
            border-radius: 16px;
            background: rgba(34, 197, 94, 0.15);
            color: #4ade80;
            margin-bottom: 25px;
            border: 1px solid rgba(34, 197, 94, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        /* Builders elements */
        .builder-container {
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .btn-danger-small {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 8px;
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-danger-small:hover {
            background: var(--danger);
            color: white;
        }

        /* Action Icon Buttons */
        .action-btns {
            display: flex;
            gap: 6px;
        }
        .action-btn {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            padding: 0;
            box-shadow: none;
        }
        .action-btn:hover {
            color: white;
            background: var(--primary);
            border-color: transparent;
            transform: translateY(-1px);
        }
        .action-btn.btn-delete:hover {
            background: var(--danger);
        }
        .action-btn.btn-activate:hover {
            background: var(--success);
        }

        /* Modal Preview */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(12px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        .modal-body {
            width: 90%;
            max-width: 900px;
            height: 85vh;
            background: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 28px;
            overflow-y: auto;
            box-shadow: 0 30px 80px rgba(0,0,0,0.6);
            display: flex;
            flex-direction: column;
        }
        .modal-header {
            padding: 20px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-content-container {
            padding: 30px;
            flex: 1;
        }
        
        /* Event Center UI Mimic inside Modal Preview */
        .preview-hero {
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
            margin-bottom: 30px;
        }
        .preview-hero-title { font-size: 32px; font-weight: 900; margin: 0; text-transform: uppercase; }
        .preview-timer { background: rgba(0,0,0,0.25); display: inline-block; padding: 8px 20px; border-radius: 50px; margin-top: 15px; font-weight: 700; font-size: 0.9rem; }
        
        .preview-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 25px; }
        .preview-card { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); padding: 15px; border-radius: 16px; text-align: center; }

        .preview-currency-bar { display: flex; justify-content: center; gap: 15px; margin-bottom: 30px; }
        .preview-currency { background: rgba(255,255,255,0.04); padding: 10px 20px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 10px; }
        
        .preview-tab-btn { background: rgba(255, 255, 255, 0.04); color: #94a3b8; border: none; padding: 10px 24px; border-radius: 12px; font-weight: 700; cursor: pointer; }
    </style>
</head>
<body>

    <div class="container">
        <header>
            <div>
                <h1>Event Manager Hub</h1>
                <p style="color: var(--text-muted); margin: 6px 0 0;">Quản lý Sự Kiện Mùa Giải, Bốc Thăm Trúng Thưởng, và Game of the Day</p>
            </div>
            <a href="index.php" style="color: white; text-decoration: none; font-weight: bold;"><i class="fa fa-arrow-left"></i> Quay lại Sảnh</a>
        </header>

        <!-- Thông báo từ hệ thống -->
        <?php if (!empty($msg)): ?>
            <div class="alert">
                <i class="fa-solid fa-circle-check"></i> 
                <span><?php echo htmlspecialchars($msg); ?></span>
            </div>
        <?php endif; ?>

        <!-- MENU TABS CHÍNH -->
        <div class="tabs-header">
            <button class="tab-btn active" id="tab-gotd-btn" onclick="switchMainTab('gotd')">
                <i class="fa fa-dice"></i> Game of the Day & VIP
            </button>
            <button class="tab-btn" id="tab-pass-btn" onclick="switchMainTab('pass')">
                <i class="fa fa-trophy"></i> Seasonal Pass Rewards
            </button>
            <button class="tab-btn" id="tab-events-btn" onclick="switchMainTab('events')">
                <i class="fa fa-magic"></i> Seasonal Events (CRUD)
            </button>
            <button class="tab-btn" id="tab-votes-btn" onclick="switchMainTab('votes')">
                <i class="fa fa-poll"></i> Bình Chọn Sự Kiện
            </button>
            <button class="tab-btn" id="tab-analytics-btn" onclick="switchMainTab('analytics')">
                <i class="fa fa-chart-line"></i> Phân Tích Dữ Liệu
            </button>
        </div>

        <!-- ====================================================================================== -->
        <!-- TAB 1: GAME OF THE DAY & VIP -->
        <!-- ====================================================================================== -->
        <div class="tab-content active" id="tab-gotd">
            <div class="grid-2">
                <!-- Game of the Day Card -->
                <div class="card">
                    <h2><i class="fa fa-calendar-day"></i> Game of the Day</h2>
                    <p style="color: var(--text-muted); font-size: 0.9rem;">Game được chỉ định sẽ tự động kích hoạt tính năng nhân đôi EXP (x2 XP) cho tất cả người chơi trong ngày.</p>
                    
                    <div style="text-align: center; margin: 25px 0; padding: 25px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 20px;">
                        <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: bold; letter-spacing: 1px;">HÔM NAY QUYẾT CHIẾN</div>
                        <div style="font-size: 2rem; font-weight: 900; color: var(--accent); margin-top: 5px;"><?php echo htmlspecialchars($currentGotd); ?></div>
                        <span class="badge badge-success" style="margin-top: 12px; font-size: 0.75rem;"><i class="fa fa-bolt"></i> x2 XP ACTIVE</span>
                    </div>

                    <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label>Chọn Game of the Day mới</label>
                            <select name="game_name">
                                <?php foreach ($availableGames as $g): ?>
                                    <option value="<?php echo $g; ?>" <?php echo $g === $currentGotd ? 'selected' : ''; ?>><?php echo $g; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="set_gotd">Cập Nhật Game of the Day</button>
                    </form>
                </div>

                <!-- VIP Trial Card -->
                <div class="card">
                    <h2><i class="fa fa-crown"></i> Cấp VIP Trial (24 Giờ)</h2>
                    <p style="color: var(--text-muted); font-size: 0.9rem;">Món quà khích lệ dành cho người chơi tích cực. Kích hoạt VIP Trial thời hạn 24 giờ cho tài khoản chỉ định. Nếu tài khoản đã là VIP, thời gian hết hạn sẽ tự động cộng dồn.</p>
                    
                    <form method="POST" style="margin-top: 25px;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label>Tên Người Dùng (Chính xác 100%)</label>
                            <input type="text" name="user_name" placeholder="Nhập username..." required>
                        </div>
                        <button type="submit" name="grant_vip" class="btn-info">
                            <i class="fa fa-magic"></i> Tặng 24h VIP Trial
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ====================================================================================== -->
        <!-- TAB 2: SEASONAL PASS REWARDS (Original Levels System) -->
        <!-- ====================================================================================== -->
        <div class="tab-content" id="tab-pass">
            <div class="grid-2">
                <!-- Add New Season Form -->
                <div class="card">
                    <h2><i class="fa fa-folder-plus"></i> Launch New Season Pass</h2>
                    <p style="color: var(--text-muted); font-size: 0.9rem;">Khởi động mùa giải level pass mới với mốc Boss khủng toàn server.</p>
                    
                    <form method="POST" style="margin-top: 20px;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label>Tên Mùa Giải</label>
                            <input type="text" name="season_name" placeholder="Ví dụ: Mùa 1: Khởi Nguyên" required>
                        </div>
                        <div class="grid-half">
                            <div class="form-group">
                                <label>Màu Chủ Đề (Theme Color)</label>
                                <input type="color" name="theme_color" value="#6366f1" style="height: 48px; padding: 2px;">
                            </div>
                            <div class="form-group">
                                <label>Tên World Boss</label>
                                <input type="text" name="boss_name" value="Hắc Long Vương" required>
                            </div>
                        </div>
                        <div class="grid-half">
                            <div class="form-group">
                                <label>Ngày Bắt Đầu</label>
                                <input type="date" name="start_date" required>
                            </div>
                            <div class="form-group">
                                <label>Ngày Kết Thúc</label>
                                <input type="date" name="end_date" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>HP World Boss Cực Đại</label>
                            <input type="number" name="boss_hp" value="5000000" required>
                        </div>
                        <button type="submit" name="create_season">Phát Hành Mùa Giải</button>
                    </form>
                </div>

                <!-- Seasons List & Reward Level Add -->
                <div class="card">
                    <h2><i class="fa fa-trophy"></i> Danh Sách Mùa Giải Đang Chạy</h2>
                    <p style="color: var(--text-muted); font-size: 0.9rem;">Danh sách các Mùa Giải Pass đã cấu hình trong hệ thống.</p>
                    
                    <div style="margin-top: 20px;">
                        <?php if (empty($seasons)): ?>
                            <div style="opacity: 0.5; padding: 20px; text-align: center;">Chưa có Season Pass nào được cấu hình.</div>
                        <?php endif; ?>
                        <?php foreach ($seasons as $s): ?>
                            <div class="season-item" style="border-left: 5px solid <?php echo $s['theme_color']; ?>">
                                <div>
                                    <strong style="font-size: 1.1rem;"><?php echo htmlspecialchars($s['name']); ?></strong>
                                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">
                                        ⏱️ <?php echo $s['start_date']; ?> đến <?php echo $s['end_date']; ?> <br>
                                        👹 Boss: <?php echo htmlspecialchars($s['boss_name']); ?> (<?php echo number_format($s['boss_hp_max']); ?> HP)
                                    </div>
                                </div>
                                <button style="width: auto; padding: 8px 15px; font-size: 0.8rem;" class="btn-outline" onclick="openRewardModal(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['name'])); ?>')">
                                    Thêm Level Reward
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Reward Management Section for Season Pass -->
            <div class="card" style="margin-top: 10px;" id="reward-section">
                <h2><i class="fa fa-gift"></i> Quản Lý Phần Thưởng Level Cho: <span id="selected-season-name" style="color: white;">(Chọn 1 Season ở trên)</span></h2>
                <form method="POST" style="margin-top: 20px;" class="grid-3" style="grid-template-columns: 1fr 1fr 1fr 1fr auto; align-items: flex-end;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="season_id" id="form-season-id">
                    <div class="form-group">
                        <label>Level Cấp Độ</label>
                        <input type="number" name="level" value="1" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Loại Phần Thưởng</label>
                        <select name="reward_type">
                            <option value="money">Money (GTLM)</option>
                            <option value="item">Item / Frame ID</option>
                            <option value="exclusive">Quà Độc Quyền</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Giá Trị Quà Tặng</label>
                        <input type="text" name="reward_value" placeholder="Ví dụ: 100000" required>
                    </div>
                    <div class="form-group" style="padding-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" name="is_premium" id="is_premium" style="width: auto; margin: 0;">
                        <label for="is_premium" style="margin: 0; font-weight: bold;">Cần Premium Pass?</label>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <button type="submit" name="add_reward">Lưu Phần Thưởng</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ====================================================================================== -->
        <!-- TAB 3: SEASONAL EVENTS (NEW CRUD SYSTEM FOR seasonal_events) -->
        <!-- ====================================================================================== -->
        <div class="tab-content" id="tab-events">
            <div class="grid-2">
                <!-- Create / Edit Event Card -->
                <div class="card" id="event-editor-card">
                    <h2 id="event-form-title"><i class="fa fa-magic"></i> Tạo Mới Sự Kiện Mùa Giải</h2>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">Điền thông tin và cấu hình các mốc quà tặng, theme sự kiện trực quan bên dưới.</p>
                    
                    <form method="POST" id="seasonal-event-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="event_id" id="event-id-field" value="0">
                        <input type="hidden" name="milestone_config" id="milestone_config_input" value="[]">
                        <input type="hidden" name="chain_config" id="chain_config_input" value="[]">
                        <input type="hidden" name="theme_config" id="theme_config_input" value="{}">

                        <div class="form-group">
                            <label>Tên Sự Kiện</label>
                            <input type="text" name="name" id="ev-name" placeholder="Ví dụ: Lễ Hội Đèn Lồng 2026" required>
                        </div>

                        <div class="grid-half">
                            <div class="form-group">
                                <label>Emoji Đại Diện</label>
                                <input type="text" name="theme_emoji" id="ev-emoji" value="🧧" placeholder="Ví dụ: 🏮" required>
                            </div>
                            <div class="form-group">
                                <label>Phí Quay (Spin Cost)</label>
                                <input type="number" name="spin_cost" id="ev-cost" value="10000" required>
                            </div>
                        </div>

                        <div class="grid-half">
                            <div class="form-group">
                                <label>Thời Gian Bắt Đầu</label>
                                <input type="datetime-local" name="starts_at" id="ev-start" required>
                            </div>
                            <div class="form-group">
                                <label>Thời Gian Kết Thúc</label>
                                <input type="datetime-local" name="ends_at" id="ev-end" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Trạng Thái Ban Đầu</label>
                            <select name="status" id="ev-status">
                                <option value="draft">📝 Nháp (Draft) — chỉ Admin thấy</option>
                                <option value="inactive">Tắt (Inactive)</option>
                                <option value="active">Bật (Active)</option>
                                <option value="upcoming">Sắp Diễn Ra (Upcoming)</option>
                            </select>
                        </div>

                        <!-- 🎨 1. Theme Configuration Visual Picker -->
                        <div class="builder-container">
                            <label style="color: white; font-weight: bold; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                                <i class="fa fa-palette"></i> Cấu Hình Giao Diện (Theme Config)
                            </label>
                            <div class="grid-half">
                                <div class="form-group">
                                    <label>Màu Chủ Đạo (Primary)</label>
                                    <input type="color" id="th-primary" value="#f43f5e" onchange="updateThemeConfig()" style="height: 40px; padding: 2px;">
                                </div>
                                <div class="form-group">
                                    <label>Màu Phụ (Secondary)</label>
                                    <input type="color" id="th-secondary" value="#fbbf24" onchange="updateThemeConfig()" style="height: 40px; padding: 2px;">
                                </div>
                            </div>
                            <div class="grid-half">
                                <div class="form-group">
                                    <label>Màu Nền (Background)</label>
                                    <input type="color" id="th-bg" value="#0f172a" onchange="updateThemeConfig()" style="height: 40px; padding: 2px;">
                                </div>
                                <div class="form-group">
                                    <label>Icon Trang Trí (Emoji)</label>
                                    <input type="text" id="th-icon" value="🧧" onchange="updateThemeConfig()" placeholder="🧧">
                                </div>
                            </div>
                        </div>

                        <!-- 🎯 2. Milestone Config Visual Builder -->
                        <div class="builder-container">
                            <label style="color: white; font-weight: bold; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                                <i class="fa fa-bullseye"></i> Quản Lý Mốc Vinh Danh (Milestone Config)
                            </label>
                            <div id="milestone-builder-list" style="margin-bottom: 12px; max-height: 150px; overflow-y: auto;">
                                <!-- Trực quan hóa danh sách ở đây -->
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 8px; align-items: flex-end;">
                                <div>
                                    <label style="font-size: 0.75rem;">Điểm Mốc</label>
                                    <input type="number" id="ms-pts" placeholder="100" style="padding: 8px 12px;">
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem;">Loại Quà</label>
                                    <select id="ms-type" style="padding: 8px 12px;">
                                        <option value="money">Money</option>
                                        <option value="item">Item</option>
                                        <option value="title">Title</option>
                                        <option value="avatar_frame">Frame</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem;">Giá Trị</label>
                                    <input type="text" id="ms-val" placeholder="50000" style="padding: 8px 12px;">
                                </div>
                                <button type="button" class="btn-success" onclick="addMilestone()" style="padding: 10px 15px; border-radius: 8px; height: 42px;">
                                    <i class="fa fa-plus"></i>
                                </button>
                            </div>
                            <div style="margin-top: 6px; display: none;">
                                <label style="font-size: 0.75rem;">Tên Mốc (Tùy chọn)</label>
                                <input type="text" id="ms-label" placeholder="Mốc 100 điểm" style="padding: 8px 12px;">
                            </div>
                        </div>

                        <!-- ⛓️ 3. Chain Config Visual Builder -->
                        <div class="builder-container">
                            <label style="color: white; font-weight: bold; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                                <i class="fa fa-link"></i> Chuỗi Nhiệm Vụ Hoàn Thành (Chain Config)
                            </label>
                            <div id="chain-builder-list" style="margin-bottom: 12px; max-height: 150px; overflow-y: auto;">
                                <!-- Trực quan hóa danh sách ở đây -->
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 8px; align-items: flex-end;">
                                <div>
                                    <label style="font-size: 0.75rem;">Số Q.Missions</label>
                                    <input type="number" id="ch-req" placeholder="5" style="padding: 8px 12px;">
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem;">Loại Quà</label>
                                    <select id="ch-type" style="padding: 8px 12px;">
                                        <option value="money">Money</option>
                                        <option value="item">Item</option>
                                        <option value="title">Title</option>
                                        <option value="avatar_frame">Frame</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem;">Giá Trị</label>
                                    <input type="text" id="ch-val" placeholder="100000" style="padding: 8px 12px;">
                                </div>
                                <button type="button" class="btn-success" onclick="addChain()" style="padding: 10px 15px; border-radius: 8px; height: 42px;">
                                    <i class="fa fa-plus"></i>
                                </button>
                            </div>
                            <div style="margin-top: 6px; display: none;">
                                <label style="font-size: 0.75rem;">Tên Chuỗi (Tùy chọn)</label>
                                <input type="text" id="ch-label" placeholder="Đạt chuỗi 5 nhiệm vụ" style="padding: 8px 12px;">
                            </div>
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <button type="submit" name="save_seasonal_event" style="flex: 2;"><i class="fa fa-save"></i> Lưu Thiết Lập</button>
                            <button type="button" class="btn-outline" onclick="previewCurrentForm()" style="flex: 1;"><i class="fa fa-eye"></i> Xem Trước</button>
                            <button type="button" id="cancel-edit-btn" class="btn-danger" style="flex: 1; display: none;" onclick="resetEventForm()"><i class="fa fa-times"></i> Hủy</button>
                        </div>
                    </form>
                </div>

                <!-- Event List Card -->
                <div class="card">
                    <h2><i class="fa fa-list-ol"></i> Danh Sách Sự Kiện Mùa Giải</h2>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 25px;">Danh sách tất cả các sự kiện mùa giải được thiết lập trong DB. Chỉ có duy nhất 1 sự kiện trạng thái Bật.</p>
                    
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Sự Kiện</th>
                                    <th>Trạng Thái</th>
                                    <th>Mốc Thời Gian</th>
                                    <th>Phí Quay</th>
                                    <th>Hành Động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($seasonalEvents)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; opacity: 0.5;">Chưa có sự kiện nào. Hãy tạo một cái!</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($seasonalEvents as $ev): ?>
                                    <tr>
                                        <td><?php echo $ev['id']; ?></td>
                                        <td>
                                            <span style="font-size: 1.2rem;"><?php echo htmlspecialchars($ev['theme_emoji']); ?></span>
                                            <strong><?php echo htmlspecialchars($ev['name']); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($ev['status'] === 'active'): ?>
                                                <span class="badge badge-success"><i class="fa fa-circle-play"></i> BẬT</span>
                                            <?php elseif ($ev['status'] === 'upcoming'): ?>
                                                <span class="badge badge-upcoming"><i class="fa fa-clock"></i> SẮP RA MẮT</span>
                                            <?php elseif ($ev['status'] === 'draft'): ?>
                                                <span class="badge" style="background:rgba(139,92,246,0.15);color:#a78bfa;border:1px solid rgba(139,92,246,0.3);"><i class="fa fa-pencil"></i> NHÁP</span>
                                            <?php else: ?>
                                                <span class="badge badge-inactive"><i class="fa fa-circle-stop"></i> TẮT</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.4;">
                                            <span>Starts: <?php echo date('d/m H:i', strtotime($ev['starts_at'])); ?></span><br>
                                            <span>Ends: <?php echo date('d/m H:i', strtotime($ev['ends_at'])); ?></span>
                                        </td>
                                        <td><strong><?php echo number_format($ev['spin_cost']); ?></strong></td>
                                        <td>
                                            <div class="action-btns">
                                                <!-- Click to quick activate/deactivate -->
                                                <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                                                    <button type="submit" name="toggle_status" class="action-btn <?php echo $ev['status'] === 'active' ? 'btn-delete' : 'btn-activate'; ?>" title="Bật/Tắt sự kiện">
                                                        <i class="fa <?php echo $ev['status'] === 'active' ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                                                    </button>
                                                </form>

                                                <!-- Click to quick extend 3 days -->
                                                <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                                                    <input type="hidden" name="extend_days" value="3">
                                                    <button type="submit" name="extend_event" class="action-btn" title="Gia hạn thêm 3 ngày">
                                                        <i class="fa fa-clock"></i>
                                                    </button>
                                                </form>

                                                <!-- Edit Event Details Form -->
                                                <button type="button" class="action-btn" title="Chỉnh sửa thông tin" onclick='editEvent(<?php echo json_encode($ev, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>)'>
                                                    <i class="fa fa-edit"></i>
                                                </button>

                                                <!-- Clone Event configuration -->
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Sao chép sự kiện này kèm toàn bộ Nhiệm Vụ, Vòng Quay, Shop?')">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                                                    <button type="submit" name="duplicate_event" class="action-btn" title="Sao chép cấu hình">
                                                        <i class="fa fa-copy"></i>
                                                    </button>
                                                </form>

                                                <!-- Preview mockup inside Admin dashboard -->
                                                <button type="button" class="action-btn" title="Xem trước giao diện" onclick='previewEvent(<?php echo json_encode($ev, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>)'>
                                                    <i class="fa fa-eye"></i>
                                                </button>
                                                <?php if ($ev['status'] === 'draft'): ?>
                                                <!-- Preview Draft as Player — mở event_center với quyền xem nháp -->
                                                <a href="event_center.php?preview_event_id=<?php echo $ev['id']; ?>" target="_blank" class="action-btn" title="Xem trước như người chơi (Draft)" style="color:#a78bfa;text-decoration:none;">
                                                    <i class="fa fa-user-check"></i>
                                                </a>
                                                <?php endif; ?>

                                                <!-- Manage Missions / Spin rewards / Shop -->
                                                <a href="admin_Event_Manager.php?tab=events&manage_event_id=<?php echo $ev['id']; ?>#details-section" class="action-btn" title="Quản lý chi tiết nhiệm vụ/quà" style="text-decoration:none;">
                                                    <i class="fa fa-gears" style="color:var(--accent);"></i>
                                                </a>

                                                <!-- Delete Event and components -->
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa vĩnh viễn sự kiện này cùng toàn bộ nhiệm vụ/quà bên trong?')">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                                                    <button type="submit" name="delete_seasonal_event" class="action-btn btn-delete" title="Xóa vĩnh viễn">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- SECTION C: SUB-MANAGER (MISSIONS, SPIN REWARDS, SHOP DETAILS) -->
            <!-- ========================================== -->
            <?php if ($manageEventId > 0 && $selectedEvent): ?>
                <div class="card" id="details-section" style="border: 2px solid var(--accent); margin-top: 30px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:15px; margin-bottom:20px;">
                        <h2 style="margin:0;"><i class="fa fa-gears"></i> QUẢN LÝ CHI TIẾT SỰ KIỆN: <span style="color: white;"><?php echo htmlspecialchars($selectedEvent['name']); ?></span></h2>
                        <a href="admin_Event_Manager.php?tab=events" class="btn-danger-small" style="width:auto; padding:5px 12px; height:auto; font-size:0.8rem; text-decoration:none;"><i class="fa fa-times"></i> Đóng Quản Lý</a>
                    </div>

                    <!-- Inner sub-tabs -->
                    <div style="display:flex; gap:10px; margin-bottom:20px; background:rgba(0,0,0,0.2); padding:6px; border-radius:12px;">
                        <button class="tab-btn active" id="sub-tab-missions-btn" onclick="switchSubTab('missions')" style="flex:1; padding:8px 15px; font-size:0.85rem;"><i class="fa fa-list-check"></i> Nhiệm Vụ (Missions)</button>
                        <button class="tab-btn" id="sub-tab-rewards-btn" onclick="switchSubTab('rewards')" style="flex:1; padding:8px 15px; font-size:0.85rem;"><i class="fa fa-dharmachakra"></i> Quà Vòng Quay (Spin)</button>
                        <button class="tab-btn" id="sub-tab-shop-btn" onclick="switchSubTab('shop')" style="flex:1; padding:8px 15px; font-size:0.85rem;"><i class="fa fa-store"></i> Cửa Hàng Đổi Quà (Shop)</button>
                    </div>

                    <!-- SUB-TAB 1: EVENT MISSIONS -->
                    <div class="sub-tab-content" id="sub-tab-missions" style="display:block;">
                        <div class="grid-2">
                            <div>
                                <h3>Thêm Nhiệm Vụ Mới</h3>
                                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="event_id" value="<?php echo $manageEventId; ?>">
                                    <div class="form-group">
                                        <label>Tiêu Đề Nhiệm Vụ</label>
                                        <input type="text" name="title" placeholder="Ví dụ: Đặt cược 50,000 GTLM ở Tài Xỉu" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Loại Nhiệm Vụ</label>
                                        <select name="mission_type">
                                            <option value="bet_amount">Tổng Cược (bet_amount)</option>
                                            <option value="win_games">Số Trận Thắng (win_games)</option>
                                            <option value="login_days">Số Ngày Đăng Nhập (login_days)</option>
                                            <option value="total_win">Tổng Thắng GTLM (total_win)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Yêu Cầu (Target Value)</label>
                                        <input type="number" name="target_value" value="50000" min="1" required>
                                    </div>
                                    <div class="grid-half">
                                        <div class="form-group">
                                            <label>Chu Kỳ Nhiệm Vụ</label>
                                            <select name="cycle">
                                                <option value="permanent">Vĩnh viễn (Permanent)</option>
                                                <option value="daily">Hằng ngày (Daily)</option>
                                                <option value="weekly">Hằng tuần (Weekly)</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Nhiệm vụ điều kiện (Prerequisite)</label>
                                            <select name="prerequisite_mission_id">
                                                <option value="">Không có (None)</option>
                                                <?php foreach ($eventMissions as $exM): ?>
                                                    <option value="<?php echo $exM['id']; ?>"><?php echo htmlspecialchars($exM['title']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="grid-half">
                                        <div class="form-group">
                                            <label>Thưởng Xu Sự Kiện</label>
                                            <input type="number" name="reward_currency" value="10" min="0" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Thưởng Điểm Vinh Danh</label>
                                            <input type="number" name="reward_xp" value="5" min="0" required>
                                        </div>
                                    </div>
                                    <button type="submit" name="add_mission" class="btn-success">Thêm Nhiệm Vụ</button>
                                </form>
                            </div>
                            <div>
                                <h3>Danh Sách Nhiệm Vụ (<?php echo count($eventMissions); ?>)</h3>
                                <table style="font-size:0.85rem;">
                                    <thead>
                                        <tr>
                                            <th>Tiêu Đề</th>
                                            <th>Chu Kỳ / Điều Kiện</th>
                                            <th>Loại</th>
                                            <th>Yêu Cầu</th>
                                            <th>Xu 🧧</th>
                                            <th>XP 🏆</th>
                                            <th>Hành Động</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($eventMissions)): ?>
                                            <tr><td colspan="7" style="text-align:center; opacity:0.5;">Chưa có nhiệm vụ nào được cấu hình cho sự kiện này.</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($eventMissions as $m): 
                                            $prereqTitle = 'Không';
                                            if ($m['prerequisite_mission_id'] > 0) {
                                                foreach ($eventMissions as $exM) {
                                                    if ($exM['id'] == $m['prerequisite_mission_id']) {
                                                        $prereqTitle = $exM['title'];
                                                        break;
                                                    }
                                                }
                                            }
                                        ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($m['title']); ?></strong></td>
                                                <td>
                                                    <span class="badge <?php echo $m['cycle'] === 'daily' ? 'badge-success' : ($m['cycle'] === 'weekly' ? 'badge-upcoming' : 'badge-inactive'); ?>" style="font-size:0.7rem; padding:2px 6px;">
                                                        <?php echo $m['cycle']; ?>
                                                    </span>
                                                    <?php if ($m['prerequisite_mission_id'] > 0): ?>
                                                        <div style="font-size:0.75rem; color:var(--accent); margin-top:4px;">
                                                            🔑 Y/C: <?php echo htmlspecialchars($prereqTitle); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><code><?php echo $m['mission_type']; ?></code></td>
                                                <td><?php echo number_format($m['target_value']); ?></td>
                                                <td><span style="color:var(--accent); font-weight:bold;">+<?php echo $m['reward_currency']; ?></span></td>
                                                <td><span style="color:var(--success); font-weight:bold;">+<?php echo $m['reward_xp']; ?></span></td>
                                                <td>
                                                    <form method="POST" onsubmit="return confirm('Xóa nhiệm vụ này?')">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="event_id" value="<?php echo $manageEventId; ?>">
                                                        <input type="hidden" name="mission_id" value="<?php echo $m['id']; ?>">
                                                        <button type="submit" name="delete_mission" class="btn-danger-small" title="Xóa nhiệm vụ"><i class="fa fa-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- SUB-TAB 2: SPIN REWARDS -->
                    <div class="sub-tab-content" id="sub-tab-rewards" style="display:none;">
                        <div class="grid-2">
                            <div>
                                <h3>Thêm Quà Vòng Quay Mới</h3>
                                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="event_id" value="<?php echo $manageEventId; ?>">
                                    <div class="form-group">
                                        <label>Tên Quà Hiển Thị</label>
                                        <input type="text" name="reward_name" placeholder="Ví dụ: Siêu Xe Cyber" required>
                                    </div>
                                    <div class="grid-half">
                                        <div class="form-group">
                                            <label>Loại Quà</label>
                                            <select name="reward_type">
                                                <option value="money">Money (GTLM)</option>
                                                <option value="item">Vật Phẩm (Item ID)</option>
                                                <option value="title">Danh Hiệu (Title ID)</option>
                                                <option value="avatar_frame">Khung Đại Diện</option>
                                                <option value="nothing">Chúc May Mắn</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Giá Trị Quà (ID hoặc Số Tiền)</label>
                                            <input type="text" name="reward_value" placeholder="100000 hoặc item_12" required>
                                        </div>
                                    </div>
                                    <div class="grid-half">
                                        <div class="form-group">
                                            <label>Icon Hiển Thị (Emoji hoặc URL)</label>
                                            <input type="text" name="reward_icon" value="🎁" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Tỷ Lệ Trúng (Trọng số Weight)</label>
                                            <input type="number" name="weight" value="10" min="1" required>
                                        </div>
                                    </div>
                                    <div class="grid-half">
                                        <div class="form-group" style="padding-top: 12px; display:flex; align-items:center; gap:8px;">
                                            <input type="checkbox" name="is_limited" id="is_limited_reward" style="width:auto; margin:0;">
                                            <label for="is_limited_reward" style="margin:0; font-weight:bold;">Giới Hạn Số Lượng?</label>
                                        </div>
                                        <div class="form-group">
                                            <label>Số Lượng Kho (Quantity, -1 là Vô Hạn)</label>
                                            <input type="number" name="quantity_left" value="-1" required>
                                        </div>
                                    </div>
                                    <button type="submit" name="add_spin_reward" class="btn-success">Thêm Quà Vòng Quay</button>
                                </form>
                            </div>
                            <div>
                                <h3>Danh Sách Quà Vòng Quay (<?php echo count($eventRewards); ?>)</h3>
                                <table style="font-size:0.85rem;">
                                    <thead>
                                        <tr>
                                            <th>Quà Tặng</th>
                                            <th>Loại</th>
                                            <th>Giá Trị</th>
                                            <th>Trọng Số</th>
                                            <th>Kho Hàng</th>
                                            <th>Hành Động</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($eventRewards)): ?>
                                            <tr><td colspan="6" style="text-align:center; opacity:0.5;">Chưa có quà vòng quay nào được cấu hình cho sự kiện này.</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($eventRewards as $r): ?>
                                            <tr>
                                                <td>
                                                    <span style="font-size:1.1rem;"><?php echo htmlspecialchars($r['reward_icon']); ?></span>
                                                    <strong><?php echo htmlspecialchars($r['reward_name']); ?></strong>
                                                </td>
                                                <td><code><?php echo $r['reward_type']; ?></code></td>
                                                <td><?php echo htmlspecialchars($r['reward_value']); ?></td>
                                                <td><?php echo $r['weight']; ?></td>
                                                <td>
                                                    <?php if ($r['is_limited']): ?>
                                                        <span style="color:var(--danger); font-weight:bold;">Còn <?php echo $r['quantity_left']; ?></span>
                                                    <?php else: ?>
                                                        <span style="color:var(--success);">Vô Hạn</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" onsubmit="return confirm('Xóa quà vòng quay này?')">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="event_id" value="<?php echo $manageEventId; ?>">
                                                        <input type="hidden" name="reward_id" value="<?php echo $r['id']; ?>">
                                                        <button type="submit" name="delete_spin_reward" class="btn-danger-small" title="Xóa quà"><i class="fa fa-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- SUB-TAB 3: EXCHANGE SHOP -->
                    <div class="sub-tab-content" id="sub-tab-shop" style="display:none;">
                        <div class="grid-2">
                            <div>
                                <h3>Thêm Vật Phẩm Cửa Hàng Mới</h3>
                                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="event_id" value="<?php echo $manageEventId; ?>">
                                    <div class="form-group">
                                        <label>Tên Vật Phẩm Hiển Thị</label>
                                        <input type="text" name="item_name" placeholder="Ví dụ: Khung Viền Neon Rực Rỡ" required>
                                    </div>
                                    <div class="grid-half">
                                        <div class="form-group">
                                            <label>Loại Vật Phẩm</label>
                                            <select name="item_type">
                                                <option value="title">Danh Hiệu (Title)</option>
                                                <option value="theme">Hình Nền Giao Diện</option>
                                                <option value="cursor">Con Trỏ Chuột</option>
                                                <option value="chat_frame">Khung Chat</option>
                                                <option value="avatar_frame">Khung Avatar</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>ID Vật Phẩm (Trong Hệ Thống)</label>
                                            <input type="number" name="item_id" placeholder="Ví dụ: 12" required>
                                        </div>
                                    </div>
                                    <div class="grid-half">
                                        <div class="form-group">
                                            <label>Giá Đổi (Xu Sự Kiện 🧧)</label>
                                            <input type="number" name="cost_currency" value="100" min="1" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Giới Hạn Đổi / Người Chơi</label>
                                            <input type="number" name="limit_per_user" value="1" min="0" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Tổng Kho Ban Đầu (-1 là Vô Hạn)</label>
                                        <input type="number" name="total_stock" value="-1" required>
                                    </div>
                                    <button type="submit" name="add_shop_item" class="btn-success">Thêm Vật Phẩm</button>
                                </form>
                            </div>
                            <div>
                                <h3>Danh Sách Vật Phẩm Đổi (<?php echo count($eventShop); ?>)</h3>
                                <table style="font-size:0.85rem;">
                                    <thead>
                                        <tr>
                                            <th>Tên Vật Phẩm</th>
                                            <th>Loại</th>
                                            <th>ID Hệ Thống</th>
                                            <th>Giá Đổi</th>
                                            <th>Kho Hàng</th>
                                            <th>Giới Hạn</th>
                                            <th>Hành Động</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($eventShop)): ?>
                                            <tr><td colspan="7" style="text-align:center; opacity:0.5;">Chưa có vật phẩm nào trong shop được cấu hình.</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($eventShop as $s): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($s['item_name']); ?></strong></td>
                                                <td><code><?php echo htmlspecialchars($s['item_type']); ?></code></td>
                                                <td><?php echo $s['item_id']; ?></td>
                                                <td><span style="color:var(--accent); font-weight:bold;"><?php echo number_format($s['cost_currency']); ?> 🧧</span></td>
                                                <td>
                                                    <?php if ($s['total_stock'] < 0): ?>
                                                        <span style="color:var(--success);">Vô Hạn</span>
                                                    <?php else: ?>
                                                        <span style="color:var(--danger); font-weight:bold;">Còn <?php echo $s['total_stock']; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $s['limit_per_user'] > 0 ? $s['limit_per_user'] . ' lần' : 'Không giới hạn'; ?></td>
                                                <td>
                                                    <form method="POST" onsubmit="return confirm('Xóa vật phẩm shop này?')">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="event_id" value="<?php echo $manageEventId; ?>">
                                                        <input type="hidden" name="item_id" value="<?php echo $s['id']; ?>">
                                                        <button type="submit" name="delete_shop_item" class="btn-danger-small" title="Xóa vật phẩm"><i class="fa fa-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ====================================================================================== -->
    <!-- 👁️ MODAL PREVIEW MOCKUP EVENT CENTER (USER INTERFACE) -->
    <!-- ====================================================================================== -->
    <div class="modal" id="previewModal">
        <div class="modal-body">
            <div class="modal-header">
                <h2 style="color: white; margin: 0; font-weight: 800; font-size: 1.4rem;">
                    <i class="fa fa-eye"></i> User Interface Live Preview
                </h2>
                <button class="btn-danger-small" onclick="closePreviewModal()"><i class="fa fa-times"></i></button>
            </div>
            <div class="modal-content-container" id="preview-mockup-wrapper">
                <!-- Mockup styled exactly like event_center.php -->
                <div id="preview-mockup-body" style="background:#0f172a; padding: 20px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.08); font-family:'Outfit', sans-serif;">
                    
                    <div class="preview-hero" id="p-hero" style="background: linear-gradient(135deg, #f43f5e, #e11d48); color: white;">
                        <h1 class="preview-hero-title" id="p-hero-title">🧧 Tên Sự Kiện Mùa Giải</h1>
                        <div class="preview-timer" id="p-timer">⏳ Sự kiện đang diễn ra trực tiếp!</div>
                    </div>

                    <div class="preview-currency-bar">
                        <div class="preview-currency">
                            <span style="font-size: 1.5rem;">🧧</span>
                            <div style="text-align: left;">
                                <div style="font-weight: 900; color: #fbbf24; font-size: 1.1rem;">150</div>
                                <div style="font-size: 9px; opacity: 0.6; text-transform: uppercase;">Xu Sự Kiện</div>
                            </div>
                        </div>
                        <div class="preview-currency">
                            <span style="font-size: 1.5rem;">🏆</span>
                            <div style="text-align: left;">
                                <div style="font-weight: 900; color: #fbbf24; font-size: 1.1rem;">350</div>
                                <div style="font-size: 9px; opacity: 0.6; text-transform: uppercase;">Điểm Vinh Danh</div>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:center; gap:10px; margin-bottom:20px;">
                        <button class="preview-tab-btn" style="background:#f43f5e; color:white;">NHIỆM VỤ SỰ KIỆN</button>
                        <button class="preview-tab-btn">CỬA HÀNG ĐỔI QUÀ</button>
                    </div>

                    <!-- Chain list preview container -->
                    <div id="p-chain-container" style="background: rgba(0,0,0,0.3); border: 1px solid #fbbf24; padding: 15px; border-radius: 16px; margin-bottom: 20px; text-align: left;">
                        <div style="font-size:13px; font-weight:800; margin-bottom:10px; color:#fbbf24;"><i class="fa fa-gift"></i> CHUỖI NHIỆM VỤ TÍCH LŨY</div>
                        <div id="p-chain-list" style="display:flex; gap:10px; flex-wrap:wrap;"></div>
                    </div>

                    <!-- Milestones preview container -->
                    <div id="p-milestones-container" style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.06); padding: 15px; border-radius: 16px; margin-bottom: 20px; text-align: left;">
                        <div style="font-size:13px; font-weight:800; margin-bottom:10px; color:#3b82f6;"><i class="fa fa-bullseye"></i> MỐC QUÀ VINH DANH</div>
                        <div id="p-milestones-list" style="display:flex; gap:10px; flex-wrap:wrap;"></div>
                    </div>

                    <!-- Standard missions mock list -->
                    <div style="text-align: left;">
                        <div style="font-weight:800; font-size:14px; margin-bottom:10px; opacity:0.8;">DANH SÁCH NHIỆM VỤ</div>
                        <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); border-radius:14px; padding:15px; display:flex; justify-content:space-between; align-items:center; border-left: 4px solid #fbbf24;">
                            <div>
                                <strong style="font-size:14px;">🎲 Đại giao lưu Tài Xỉu Đối Kháng</strong>
                                <div style="font-size:11px; color:#fbbf24; margin-top:2px;">+10 Xu 🧧 &nbsp;+5 XP</div>
                            </div>
                            <div style="width:40%; text-align:right;">
                                <div style="font-size:10px; font-weight:800; margin-bottom:4px;">15,000 / 50,000 GTLM</div>
                                <div style="height:6px; background:rgba(255,255,255,0.1); border-radius:10px; overflow:hidden;">
                                    <div style="height:100%; width:30%; background:#f43f5e;"></div>
                                </div>
                            </div>
                            <button disabled style="width:auto; padding:6px 15px; font-size:11px; margin-left:15px; background:#334155; color:#94a3b8; border:none; border-radius:8px;">CHƯA XONG</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <!-- ====================================================================================== -->
        <!-- TAB 4: BÌNH CHỌN SỰ KIỆN -->
        <!-- ====================================================================================== -->
        <div class="tab-content" id="tab-votes">
            <div class="grid-2">
                <!-- Add New Option Form -->
                <div class="card">
                    <h2><i class="fa fa-plus-circle"></i> Thêm Lựa Chọn Mới</h2>
                    <p style="color: var(--text-muted); font-size: 0.9rem;">Thêm sự kiện vào danh sách để người chơi bình chọn.</p>
                    
                    <form method="POST" style="margin-top: 20px;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label>Tên Lựa Chọn (Ví dụ: Đua Tốc Độ)</label>
                            <input type="text" name="title" required>
                        </div>
                        <div class="form-group">
                            <label>Mô Tả</label>
                            <input type="text" name="description" required>
                        </div>
                        <div class="form-group">
                            <label>Icon (Emoji)</label>
                            <input type="text" name="icon" value="🎮" required>
                        </div>
                        <button type="submit" name="add_vote_option"><i class="fa fa-plus"></i> Thêm Lựa Chọn</button>
                    </form>
                </div>

                <!-- List of Options -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2><i class="fa fa-list"></i> Danh Sách Lựa Chọn</h2>
                        <form method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn làm mới toàn bộ kết quả bình chọn (xóa toàn bộ vote)?');">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <button type="submit" name="reset_votes" class="btn-danger" style="padding: 8px 16px; width: auto;"><i class="fa fa-trash"></i> Reset Số Vote</button>
                        </form>
                    </div>

                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên Sự Kiện</th>
                                    <th style="text-align: center;">Số Vote</th>
                                    <th style="text-align: right;">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($votingOptions)): ?>
                                    <?php foreach ($votingOptions as $opt): ?>
                                    <tr>
                                        <td>#<?php echo $opt['id']; ?></td>
                                        <td>
                                            <div style="font-weight: 700;"><?php echo $opt['icon'] . ' ' . htmlspecialchars($opt['title']); ?></div>
                                            <div style="font-size: 11px; opacity: 0.7;"><?php echo htmlspecialchars($opt['description']); ?></div>
                                        </td>
                                        <td style="text-align: center;">
                                            <span style="background: rgba(34,197,94,0.2); color: #4ade80; padding: 4px 10px; border-radius: 10px; font-weight: 800;">
                                                <?php echo number_format($opt['votes']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <form method="POST" onsubmit="return confirm('Xóa lựa chọn này?');" style="display: inline-block;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="option_id" value="<?php echo $opt['id']; ?>">
                                                <button type="submit" name="delete_vote_option" class="action-btn btn-delete"><i class="fa fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align: center; opacity: 0.5;">Chưa có lựa chọn nào.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════════════ -->
            <!-- BUG FIX #4: Panel kết quả vote thời gian thực + nút áp dụng thủ công -->
            <!-- ═══════════════════════════════════════════════════════════════════════ -->
            <div class="card" style="margin-top: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 10px;">
                    <h2 style="margin: 0;"><i class="fa fa-chart-bar"></i> Kết Quả Bình Chọn Thời Gian Thực</h2>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <!-- Buff đang chạy (nếu có) -->
                        <div id="vote-buff-badge" style="display:none; background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.4); border-radius: 12px; padding: 8px 16px; font-size: 0.85rem; font-weight: 700; color: #4ade80;">
                            <i class="fa fa-bolt"></i> <span id="vote-buff-name">Buff đang chạy</span>
                        </div>
                        <!-- Nút áp dụng thủ công -->
                        <button class="btn-success" style="width: auto; padding: 10px 20px; font-size: 0.9rem;" onclick="applyVoteResultManually()" id="btn-apply-vote">
                            <i class="fa fa-play-circle"></i> Áp Dụng Lựa Chọn Thắng
                        </button>
                        <!-- Auto-refresh indicator -->
                        <div style="font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                            <i class="fa fa-circle-notch fa-spin" style="color: var(--success);"></i>
                            Tự cập nhật mỗi 20s
                        </div>
                    </div>
                </div>

                <!-- Kết quả vote resolved (nếu đã chạy cron) -->
                <div id="vote-result-resolved" style="display:none; background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.3); border-radius: 16px; padding: 20px; margin-bottom: 25px;">
                    <div style="font-weight: 800; font-size: 1.1rem; margin-bottom: 10px; color: #818cf8;">
                        <i class="fa fa-trophy"></i> Kết quả đã được xử lý
                    </div>
                    <div id="vote-result-text" style="font-size: 0.95rem;"></div>
                </div>

                <!-- Live vote bars -->
                <div id="vote-live-bars">
                    <div style="text-align: center; padding: 40px; opacity: 0.5;">
                        <i class="fa fa-spinner fa-spin" style="font-size: 2rem;"></i>
                        <p style="margin-top: 10px;">Đang tải kết quả bình chọn...</p>
                    </div>
                </div>

                <!-- Output log của trigger_result -->
                <div id="vote-trigger-log" style="display:none; margin-top:20px; background: rgba(0,0,0,0.4); border-radius: 12px; padding: 16px; font-family: monospace; font-size: 0.85rem; color: #a3e635; white-space: pre-wrap; max-height: 200px; overflow-y: auto;"></div>
            </div>

            <script>
            // ── Vote Live Results Panel ──────────────────────────────────────
            let voteRefreshInterval = null;

            async function loadVoteLiveResults() {
                try {
                    const res = await fetch('api_event_vote.php?action=get_options');
                    const data = await res.json();
                    if (!data.success || !data.options || data.options.length === 0) {
                        document.getElementById('vote-live-bars').innerHTML =
                            '<div style="text-align:center; opacity:0.5; padding:20px;">Không có lựa chọn bình chọn nào hoặc không có sự kiện đang diễn ra.</div>';
                        return;
                    }

                    const total = data.total_votes || 1;
                    const opts  = data.options;
                    const maxVotes = Math.max(...opts.map(o => parseInt(o.votes) || 0), 1);

                    const barColors = ['#6366f1','#f59e0b','#22c55e','#ef4444','#3b82f6','#ec4899'];
                    let html = '<div style="display:flex; flex-direction:column; gap:16px;">';

                    opts.sort((a,b) => (parseInt(b.votes)||0) - (parseInt(a.votes)||0)).forEach((opt, i) => {
                        const v    = parseInt(opt.votes) || 0;
                        const pct  = total > 0 ? Math.round((v / total) * 100) : 0;
                        const isWinner = (i === 0 && v > 0);
                        const color = barColors[i % barColors.length];

                        html += `
                            <div style="background: rgba(255,255,255,0.03); border: 1px solid ${isWinner ? color : 'rgba(255,255,255,0.06)'}; border-radius: 14px; padding: 16px; ${isWinner ? 'box-shadow: 0 0 20px ' + color + '33;' : ''}">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                    <span style="font-weight: 700; font-size: 15px;">
                                        ${opt.icon || '🎮'} ${opt.title}
                                        ${isWinner ? '<span style="background:' + color + '; color:white; padding:2px 8px; border-radius:8px; font-size:11px; margin-left:8px;">🏆 ĐANG DẪN</span>' : ''}
                                    </span>
                                    <span style="font-weight: 900; font-size: 18px; color: ${color};">${v.toLocaleString()} phiếu <span style="font-size:13px; opacity:0.7;">(${pct}%)</span></span>
                                </div>
                                <div style="height: 14px; background: rgba(0,0,0,0.4); border-radius: 99px; overflow:hidden; border: 1px solid rgba(255,255,255,0.05);">
                                    <div style="height:100%; width:${pct}%; background: linear-gradient(90deg, ${color}, ${color}cc); border-radius:99px; transition: width 0.8s ease;"></div>
                                </div>
                            </div>`;
                    });

                    html += `<div style="text-align:right; color: var(--text-muted); font-size:0.85rem; margin-top:5px;">Tổng số phiếu: <strong style="color:white;">${total.toLocaleString()}</strong></div>`;
                    html += '</div>';
                    document.getElementById('vote-live-bars').innerHTML = html;
                } catch(e) {
                    console.error('Vote load error:', e);
                }

                // Kiểm tra kết quả đã được xử lý chưa
                try {
                    const res2 = await fetch('api_event_vote.php?action=get_result');
                    const d2   = await res2.json();
                    if (d2.success && d2.status === 'resolved' && d2.result) {
                        const r = d2.result;
                        document.getElementById('vote-result-resolved').style.display = 'block';
                        document.getElementById('vote-result-text').innerHTML =
                            `<b>🏆 Option thắng:</b> ${r.option_title || r.option_id} (${parseInt(r.votes).toLocaleString()} phiếu)<br>
                             <b>🎁 Buff đã kích hoạt:</b> ${r.buff_type || '-'}<br>
                             <b>📅 Xử lý lúc:</b> ${r.processed_at || '-'}`;
                    }
                    if (d2.buff_active && d2.buff_event) {
                        const bf = d2.buff_event;
                        const secsLeft = parseInt(bf.secs_left) || 0;
                        const minsLeft = Math.ceil(secsLeft / 60);
                        document.getElementById('vote-buff-badge').style.display = 'flex';
                        document.getElementById('vote-buff-name').textContent = `${bf.event_name} — còn ${minsLeft} phút`;
                    }
                } catch(e) {}
            }

            async function applyVoteResultManually() {
                if (!confirm('Áp dụng kết quả vote thủ công ngay bây giờ? Hệ thống sẽ đọc option thắng và kích hoạt buff tương ứng.')) return;
                const btn = document.getElementById('btn-apply-vote');
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Đang xử lý...';

                try {
                    const form = new FormData();
                    form.append('action', 'trigger_result');
                    const res = await fetch('api_event_vote.php', { method: 'POST', body: form });
                    const data = await res.json();

                    const logEl = document.getElementById('vote-trigger-log');
                    logEl.style.display = 'block';
                    logEl.textContent = data.output || 'Không có output.';

                    if (data.success) {
                        loadVoteLiveResults();
                    } else {
                        alert('Lỗi: ' + (data.message || 'Unknown error'));
                    }
                } catch(e) {
                    alert('Lỗi kết nối: ' + e.message);
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-play-circle"></i> Áp Dụng Lựa Chọn Thắng';
                }
            }

            // Auto-refresh mỗi 20 giây khi tab votes đang mở
            function startVoteRefresh() {
                loadVoteLiveResults();
                voteRefreshInterval = setInterval(loadVoteLiveResults, 20000);
            }

            function stopVoteRefresh() {
                if (voteRefreshInterval) { clearInterval(voteRefreshInterval); voteRefreshInterval = null; }
            }

            // Load ngay khi tab được click
            document.getElementById('tab-votes-btn').addEventListener('click', function() {
                setTimeout(startVoteRefresh, 100);
            });

            // Tự load nếu tab đang active từ URL param
            if (new URLSearchParams(window.location.search).get('tab') === 'votes') {
                startVoteRefresh();
            }
            </script>
        </div>

    </div>

    <!-- ====================================================================================== -->
    <!-- SECTION E: TỔNG QUAN ANALYTICS SỰ KIỆN HOẠT ĐỘNG -->
    <!-- ====================================================================================== -->
    <div class="tab-content" id="tab-analytics">
        <div class="card" style="margin-bottom: 20px;">
            <h2><i class="fa fa-chart-pie"></i> Báo Cáo Hiệu Suất: <span style="color: var(--accent);"><?php echo htmlspecialchars($analyticsName ?: 'Không có sự kiện đang chạy'); ?></span></h2>
            
            <?php if ($analyticsEventId > 0): ?>
            <!-- KPI Cards Row 1 -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-top: 20px;">
                <div style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.3); padding: 20px; border-radius: 15px; text-align: center;">
                    <div style="font-size: 28px; margin-bottom: 5px;">👥</div>
                    <div style="font-size: 13px; opacity: 0.7; text-transform: uppercase; font-weight: 700;">Người Tham Gia</div>
                    <div style="font-size: 32px; font-weight: 900; color: #3b82f6;"><?php echo number_format($analyticsData['total_entered']); ?></div>
                </div>
                <div style="background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); padding: 20px; border-radius: 15px; text-align: center;">
                    <div style="font-size: 28px; margin-bottom: 5px;">✅</div>
                    <div style="font-size: 13px; opacity: 0.7; text-transform: uppercase; font-weight: 700;">Hoàn Thành ≥1 NV</div>
                    <div style="font-size: 32px; font-weight: 900; color: #22c55e;"><?php echo number_format($analyticsData['total_completed_mission']); ?></div>
                </div>
                <div style="background: rgba(251,191,36,0.1); border: 1px solid rgba(251,191,36,0.3); padding: 20px; border-radius: 15px; text-align: center;">
                    <div style="font-size: 28px; margin-bottom: 5px;">🔄</div>
                    <div style="font-size: 13px; opacity: 0.7; text-transform: uppercase; font-weight: 700;">Tỷ Lệ Chuyển Đổi</div>
                    <div style="font-size: 32px; font-weight: 900; color: #fbbf24;"><?php echo $analyticsData['conversion_rate']; ?>%</div>
                </div>
                <div style="background: rgba(244,63,94,0.1); border: 1px solid rgba(244,63,94,0.3); padding: 20px; border-radius: 15px; text-align: center;">
                    <div style="font-size: 28px; margin-bottom: 5px;">🧧</div>
                    <div style="font-size: 13px; opacity: 0.7; text-transform: uppercase; font-weight: 700;">Tổng Xu Đã Phát</div>
                    <div style="font-size: 32px; font-weight: 900; color: #f43f5e;"><?php echo number_format($analyticsData['total_currency_distributed']); ?></div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 20px;">
                <!-- Top Completed Missions -->
                <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 20px; border-radius: 15px;">
                    <h3 style="color: #22c55e;"><i class="fa fa-trophy"></i> NV Hoàn Thành Nhiều Nhất</h3>
                    <?php if (!empty($analyticsData['top_missions'])): ?>
                    <?php foreach ($analyticsData['top_missions'] as $i => $m): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <span style="font-size: 13px;"><?php echo htmlspecialchars($m['title']); ?></span>
                        <span style="font-weight: 900; color: #22c55e; background: rgba(34,197,94,0.1); padding: 2px 8px; border-radius: 5px; font-size: 13px;"><?php echo number_format($m['completed_count']); ?>x</span>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?><div style="opacity:0.5; text-align:center; padding:20px;">Chưa có dữ liệu</div><?php endif; ?>
                </div>

                <!-- Abandoned Missions -->
                <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 20px; border-radius: 15px;">
                    <h3 style="color: #ef4444;"><i class="fa fa-triangle-exclamation"></i> NV Bị Bỏ Nhiều Nhất</h3>
                    <?php if (!empty($analyticsData['abandoned_missions'])): ?>
                    <?php foreach ($analyticsData['abandoned_missions'] as $m):
                        $rate = $m['total_started'] > 0 ? round(($m['total_completed'] / $m['total_started']) * 100) : 0;
                    ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <span style="font-size: 12px;"><?php echo htmlspecialchars($m['title']); ?></span>
                        <span style="font-weight: 900; color: <?php echo $rate < 30 ? '#ef4444' : '#fbbf24'; ?>; background: rgba(239,68,68,0.1); padding: 2px 8px; border-radius: 5px; font-size: 13px;"><?php echo $rate; ?>%</span>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?><div style="opacity:0.5; text-align:center; padding:20px;">Chưa có dữ liệu</div><?php endif; ?>
                </div>

                <!-- Top Shop Items Exchanged -->
                <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 20px; border-radius: 15px;">
                    <h3 style="color: #fbbf24;"><i class="fa fa-store"></i> Item Đổi Nhiều Nhất</h3>
                    <?php if (!empty($analyticsData['top_shop_items'])): ?>
                    <?php foreach ($analyticsData['top_shop_items'] as $s): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <div>
                            <div style="font-size: 13px; font-weight: 700;"><?php echo htmlspecialchars($s['item_name']); ?></div>
                            <div style="font-size: 11px; opacity: 0.5; text-transform: uppercase;"><?php echo htmlspecialchars($s['item_type']); ?></div>
                        </div>
                        <span style="font-weight: 900; color: #fbbf24; background: rgba(251,191,36,0.1); padding: 2px 8px; border-radius: 5px; font-size: 13px;"><?php echo number_format($s['exchange_count']); ?>x</span>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?><div style="opacity:0.5; text-align:center; padding:20px;">Chưa có lượt đổi quà</div><?php endif; ?>
                </div>
            </div>

            <?php else: ?>
                <div style="text-align: center; padding: 60px; opacity: 0.5;"><i class="fa fa-chart-bar" style="font-size:40px; margin-bottom:15px; display:block;"></i>Chưa có sự kiện nào đang diễn ra để phân tích.</div>
            <?php endif; ?>
        </div>
    </div>


    <!-- ========================================== -->
    <!-- ⚙️ CÁC SCRIPT ĐIỀU KHIỂN & BUILDER -->
    <!-- ========================================== -->
    <script>
        // ──────────────────────────────────────────
        // 1. Quản lý Tabs chính & sub-tabs
        // ──────────────────────────────────────────
        function switchMainTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tabs-header .tab-btn').forEach(el => el.classList.remove('active'));
            
            document.getElementById('tab-' + tabName).classList.add('active');
            document.getElementById('tab-' + tabName + '-btn').classList.add('active');
            
            // Cập nhật URL hash hoặc search param để load lại đúng tab
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }

        function switchSubTab(subTabName) {
            document.querySelectorAll('.sub-tab-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('#details-section .tab-btn').forEach(el => el.classList.remove('active'));
            
            document.getElementById('sub-tab-' + subTabName).style.display = 'block';
            document.getElementById('sub-tab-' + subTabName + '-btn').classList.add('active');
        }

        // Tự động kích hoạt tab dựa trên search param của URL
        window.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            const activeTab = params.get('tab') || 'gotd';
            switchMainTab(activeTab);
            
            <?php if ($manageEventId > 0): ?>
            // Tự động cuộn xuống khu vực quản lý chi tiết
            document.getElementById('details-section').scrollIntoView({ behavior: 'smooth' });
            <?php endif; ?>
        });

        // ──────────────────────────────────────────
        // 2. Visual Theme & JSON Builders
        // ──────────────────────────────────────────
        let milestones = [];
        let chains = [];

        function updateThemeConfig() {
            let primary = document.getElementById('th-primary').value;
            let secondary = document.getElementById('th-secondary').value;
            let bg = document.getElementById('th-bg').value;
            let icon = document.getElementById('th-icon').value.trim() || '🧧';
            
            let themeObj = { primary, secondary, bg, icon };
            document.getElementById('theme_config_input').value = JSON.stringify(themeObj);
        }

        // --- Milestone Builder ---
        function renderMilestones() {
            let container = document.getElementById('milestone-builder-list');
            container.innerHTML = '';
            
            if (milestones.length === 0) {
                container.innerHTML = '<div style="opacity: 0.5; font-size: 0.8rem; padding: 5px 0;">Chưa thiết lập mốc quà nào.</div>';
            }
            
            milestones.forEach((m, idx) => {
                let div = document.createElement('div');
                div.style.cssText = 'display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.05); padding:6px 12px; border-radius:8px; margin-bottom:5px; font-size: 0.85rem;';
                div.innerHTML = `
                    <div>
                        🎯 Mốc <strong>${new Intl.NumberFormat().format(m.points)}đ</strong> - ${m.label || `Mốc ${m.points} điểm`} 
                        &nbsp;<span class="badge badge-success" style="font-size:0.7rem; padding: 2px 6px;">${m.reward_type}: ${m.reward_value}</span>
                    </div>
                    <button type="button" class="btn-danger-small" onclick="removeMilestone(${idx})" style="width:24px; height:24px;"><i class="fa fa-times" style="font-size:10px;"></i></button>
                `;
                container.appendChild(div);
            });
            document.getElementById('milestone_config_input').value = JSON.stringify(milestones);
        }

        function addMilestone() {
            let pts = parseInt(document.getElementById('ms-pts').value);
            let label = document.getElementById('ms-label').value.trim();
            let type = document.getElementById('ms-type').value;
            let val = document.getElementById('ms-val').value.trim();
            
            if (isNaN(pts) || pts <= 0 || !val) {
                alert('Vui lòng điền đầy đủ các thông tin mốc quà tặng.');
                return;
            }
            if (!label) label = `Mốc ${pts} điểm`;
            
            milestones.push({ points: pts, label: label, reward_type: type, reward_value: val });
            milestones.sort((a, b) => a.points - b.points);
            renderMilestones();
            
            document.getElementById('ms-pts').value = '';
            document.getElementById('ms-label').value = '';
            document.getElementById('ms-val').value = '';
        }

        function removeMilestone(idx) {
            milestones.splice(idx, 1);
            renderMilestones();
        }

        // --- Chain Builder ---
        function renderChains() {
            let container = document.getElementById('chain-builder-list');
            container.innerHTML = '';
            
            if (chains.length === 0) {
                container.innerHTML = '<div style="opacity: 0.5; font-size: 0.8rem; padding: 5px 0;">Chưa thiết lập chuỗi nhiệm vụ.</div>';
            }
            
            chains.forEach((c, idx) => {
                let div = document.createElement('div');
                div.style.cssText = 'display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,0.05); padding:6px 12px; border-radius:8px; margin-bottom:5px; font-size: 0.85rem;';
                div.innerHTML = `
                    <div>
                        ⛓️ Chuỗi <strong>${c.required_missions} nhiệm vụ</strong> - ${c.label || `Chuỗi ${c.required_missions} NV`}
                        &nbsp;<span class="badge badge-success" style="font-size:0.7rem; padding: 2px 6px;">${c.reward_type}: ${c.reward_value}</span>
                    </div>
                    <button type="button" class="btn-danger-small" onclick="removeChain(${idx})" style="width:24px; height:24px;"><i class="fa fa-times" style="font-size:10px;"></i></button>
                `;
                container.appendChild(div);
            });
            document.getElementById('chain_config_input').value = JSON.stringify(chains);
        }

        function addChain() {
            let req = parseInt(document.getElementById('ch-req').value);
            let label = document.getElementById('ch-label').value.trim();
            let type = document.getElementById('ch-type').value;
            let val = document.getElementById('ch-val').value.trim();
            
            if (isNaN(req) || req <= 0 || !val) {
                alert('Vui lòng nhập đầy đủ thông tin chuỗi nhiệm vụ.');
                return;
            }
            if (!label) label = `Chuỗi ${req} nhiệm vụ`;
            
            chains.push({ required_missions: req, label: label, reward_type: type, reward_value: val });
            chains.sort((a, b) => a.required_missions - b.required_missions);
            renderChains();
            
            document.getElementById('ch-req').value = '';
            document.getElementById('ch-label').value = '';
            document.getElementById('ch-val').value = '';
        }

        function removeChain(idx) {
            chains.splice(idx, 1);
            renderChains();
        }

        // Reset visual builders
        function resetEventForm() {
            document.getElementById('seasonal-event-form').reset();
            document.getElementById('event-id-field').value = '0';
            document.getElementById('event-form-title').innerHTML = '<i class="fa fa-magic"></i> Tạo Mới Sự Kiện Mùa Giải';
            document.getElementById('cancel-edit-btn').style.display = 'none';
            milestones = [];
            chains = [];
            renderMilestones();
            renderChains();
            
            document.getElementById('th-primary').value = '#f43f5e';
            document.getElementById('th-secondary').value = '#fbbf24';
            document.getElementById('th-bg').value = '#0f172a';
            document.getElementById('th-icon').value = '🧧';
            updateThemeConfig();
        }

        // Fill form when Editing Event
        function editEvent(data) {
            // Cuộn lên form
            document.getElementById('event-editor-card').scrollIntoView({ behavior: 'smooth' });
            
            document.getElementById('event-id-field').value = data.id;
            document.getElementById('event-form-title').innerHTML = `<i class="fa fa-edit"></i> Chỉnh Sửa Sự Kiện: ${data.name}`;
            document.getElementById('cancel-edit-btn').style.display = 'block';
            
            document.getElementById('ev-name').value = data.name;
            document.getElementById('ev-emoji').value = data.theme_emoji || '🧧';
            document.getElementById('ev-cost').value = data.spin_cost || 10000;
            document.getElementById('ev-start').value = data.starts_at ? data.starts_at.replace(' ', 'T') : '';
            document.getElementById('ev-end').value = data.ends_at ? data.ends_at.replace(' ', 'T') : '';
            document.getElementById('ev-status').value = data.status || 'inactive';
            
            // Load visual builders
            try { milestones = JSON.parse(data.milestone_config || '[]'); } catch(e) { milestones = []; }
            try { chains = JSON.parse(data.chain_config || '[]'); } catch(e) { chains = []; }
            renderMilestones();
            renderChains();
            
            // Load theme pickers
            let theme = { primary: '#f43f5e', secondary: '#fbbf24', bg: '#0f172a', icon: '🧧' };
            try { theme = Object.assign(theme, JSON.parse(data.theme_config || '{}')); } catch(e) {}
            document.getElementById('th-primary').value = theme.primary;
            document.getElementById('th-secondary').value = theme.secondary;
            document.getElementById('th-bg').value = theme.bg;
            document.getElementById('th-icon').value = theme.icon || '🧧';
            
            updateThemeConfig();
        }

        // ──────────────────────────────────────────
        // 3. User-facing live preview popup modal
        // ──────────────────────────────────────────
        function closePreviewModal() {
            document.getElementById('previewModal').style.display = 'none';
        }

        function showPreview(id, name, emoji, milestonesData, chainsData, themeData) {
            let modal = document.getElementById('previewModal');
            let wrapper = document.getElementById('preview-mockup-wrapper');
            
            // Đọc dữ liệu thực tế từ form (nếu là preview từ form đang nhập)
            if (!id || id === 0) {
                name = document.getElementById('ev-name')?.value.trim() || name;
                emoji = document.getElementById('ev-emoji')?.value.trim() || emoji;
                themeData = {
                    primary:   document.getElementById('th-primary')?.value   || themeData.primary,
                    secondary: document.getElementById('th-secondary')?.value || themeData.secondary,
                    bg:        document.getElementById('th-bg')?.value        || themeData.bg,
                    icon:      document.getElementById('th-icon')?.value      || themeData.icon
                };
            }

            // Build preview data, fetch real missions from DB if event id > 0
            const buildAndShowPreview = (missions, shopItems) => {
                window.currentPreviewData = {
                    success: true,
                    event: {
                        id: id || 9999,
                        name: name || 'Tên Sự Kiện',
                        theme_emoji: emoji || '🎉',
                        theme_config: JSON.stringify(themeData),
                        chain_config: JSON.stringify(chainsData),
                        milestone_config: JSON.stringify(milestonesData),
                        ends_at: new Date(Date.now() + 86400000).toISOString().slice(0, 19).replace('T', ' ')
                    },
                    user_data: { event_currency: 1000, points: 500, milestones_claimed: '[]' },
                    total_server_points: 42000,
                    missions: missions,
                    shop_items: shopItems
                };

                // Force iframe reload to pick up new data
                wrapper.innerHTML = `<iframe src="event_center.php?preview=1&ts=${Date.now()}" style="width:100%; height:750px; border:none; border-radius:12px; background:#0f172a;"></iframe>`;
                modal.style.display = 'flex';
            };

            if (id && parseInt(id) > 0) {
                // Fetch real missions and shop items from the actual event
                fetch(`api_event_engine.php?action=get_event_data&preview_id=${id}`)
                    .then(r => r.json())
                    .then(res => {
                        const missions   = res.success ? res.missions   : [];
                        const shopItems  = res.success ? res.shop_items : [];
                        buildAndShowPreview(missions, shopItems);
                    })
                    .catch(() => buildAndShowPreview([], []));
            } else {
                // Preview form in progress — use sample missions
                buildAndShowPreview([
                    { id: 1, title: '[Ảo] Đăng nhập', mission_type: 'login', target_value: 1, current_value: 0, reward_currency: 50, reward_xp: 10, is_completed: 0, is_claimed: 0, cycle: 'daily', is_locked: 0 },
                    { id: 2, title: '[Ảo] Chơi 5 ván', mission_type: 'play_game', target_value: 5, current_value: 3, reward_currency: 100, reward_xp: 20, is_completed: 0, is_claimed: 0, cycle: 'weekly', is_locked: 0 },
                    { id: 3, title: '[Ảo] Cược 100K', mission_type: 'bet', target_value: 100000, current_value: 100000, reward_currency: 500, reward_xp: 100, is_completed: 1, is_claimed: 0, cycle: 'permanent', is_locked: 0 }
                ], [
                    { id: 1, item_name: 'Khung Chat VIP', item_type: 'chat_frame', cost_currency: 500, total_stock: 10, limit_per_user: 1 }
                ]);
            }
        }

        // Getter for iframe to call
        function getPreviewData() {
            return window.currentPreviewData;
        }

        // Preview from list item
        function previewEvent(data) {
            let mData = [];
            let cData = [];
            let tData = { primary: '#f43f5e', secondary: '#fbbf24', bg: '#0f172a', icon: '🧧' };
            
            try { mData = JSON.parse(data.milestone_config || '[]'); } catch(e){}
            try { cData = JSON.parse(data.chain_config || '[]'); } catch(e){}
            try { tData = Object.assign(tData, JSON.parse(data.theme_config || '{}')); } catch(e){}
            
            showPreview(data.id, data.name, data.theme_emoji || '🧧', mData, cData, tData);
        }

        // Preview current form state (before saving)
        function previewCurrentForm() {
            let name = document.getElementById('ev-name').value.trim() || 'Tên Sự Kiện Đang Nhập';
            let emoji = document.getElementById('ev-emoji').value.trim() || '🧧';
            
            let primary = document.getElementById('th-primary').value;
            let secondary = document.getElementById('th-secondary').value;
            let bg = document.getElementById('th-bg').value;
            let icon = document.getElementById('th-icon').value.trim() || '🧧';
            let tData = { primary, secondary, bg, icon };
            
            let eventId = document.getElementById('event-id-field').value;
            showPreview(eventId, name, emoji, milestones, chains, tData);
        }

        // ──────────────────────────────────────────
        // 4. Seasonal Pass reward level modal anchor
        // ──────────────────────────────────────────
        function openRewardModal(id, name) {
            document.getElementById('selected-season-name').innerText = name;
            document.getElementById('form-season-id').value = id;
            document.getElementById('reward-section').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>
