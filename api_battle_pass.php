<?php
$isDirectCall = (isset($_SERVER['SCRIPT_FILENAME']) && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']));

if ($isDirectCall) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    require_once 'db_connect.php';

    $userId = $_SESSION['Iduser'] ?? 0;
    if (!$userId) exit(json_encode(['success' => false]));

// Hàm kiểm tra và reset Mùa Giải Battle Pass
function checkAndResetBPSeason(mysqli $conn, int $userId) {
    $check = $conn->query("SHOW TABLES LIKE 'bp_seasons'");
    if ($check->num_rows == 0) return null;
    
    $res = $conn->query("SELECT * FROM bp_seasons WHERE is_active = 1 AND NOW() BETWEEN start_time AND end_time LIMIT 1");
    $season = $res->fetch_assoc();
    if (!$season) return null;
    
    $seasonId = (int)$season['id'];
    
    // Đảm bảo cột season_id tồn tại
    $checkCol = $conn->query("SHOW COLUMNS FROM bp_stats LIKE 'season_id'");
    if ($checkCol->num_rows == 0) {
        $conn->query("ALTER TABLE bp_stats ADD COLUMN season_id INT DEFAULT 0");
    }
    
    $stmtStats = $conn->prepare("SELECT season_id FROM bp_stats WHERE user_id = ?");
    $stmtStats->bind_param("i", $userId);
    $stmtStats->execute();
    $stats = $stmtStats->get_result()->fetch_assoc();
    $stmtStats->close();
    
    if ($stats && $stats['season_id'] != $seasonId) {
        // Chuyển mùa -> Reset
        $conn->query("UPDATE bp_stats SET level = 1, xp = 0, has_premium = 0, season_id = $seasonId WHERE user_id = $userId");
        $conn->query("DELETE FROM bp_user_missions WHERE user_id = $userId");
        $conn->query("DELETE FROM bp_claimed_rewards WHERE user_id = $userId");
    }
    return $season;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_status':
        $season = checkAndResetBPSeason($conn, $userId);
        
        // Lấy thông tin level & XP
        $stmtStats = $conn->prepare("SELECT * FROM bp_stats WHERE user_id = ?");
        $stmtStats->bind_param("i", $userId);
        $stmtStats->execute();
        $stats = $stmtStats->get_result()->fetch_assoc();
        $stmtStats->close();

        if (!$stats) {
            $stmtIns = $conn->prepare("INSERT INTO bp_stats (user_id) VALUES (?)");
            $stmtIns->bind_param("i", $userId);
            $stmtIns->execute();
            $stmtIns->close();
            $stats = ['level' => 1, 'xp' => 0, 'has_premium' => 0];
        }

        // FIX #5: Lấy TẤT CẢ nhiệm vụ (daily + weekly + lifetime), phân nhóm theo type
        $stmtMissions = $conn->prepare("
            SELECT m.*, um.progress, um.status
            FROM bp_missions m
            LEFT JOIN bp_user_missions um ON m.id = um.mission_id AND um.user_id = ?
            ORDER BY FIELD(m.type, 'daily', 'weekly', 'lifetime'), m.id ASC
        ");
        $stmtMissions->bind_param("i", $userId);
        $stmtMissions->execute();
        $missionRows = $stmtMissions->get_result();
        $stmtMissions->close();

        // Phân nhóm missions theo type
        $missions = ['daily' => [], 'weekly' => [], 'lifetime' => []];
        while ($row = $missionRows->fetch_assoc()) {
            $type = $row['type'] ?? 'daily';
            $missions[$type][] = $row;
        }

        // Lấy danh sách phần thưởng
        $rewards = $conn->query("SELECT * FROM bp_rewards ORDER BY level ASC, type ASC")->fetch_all(MYSQLI_ASSOC);

        // FIX #6: Lấy danh sách đã claim từ bảng bp_claimed_rewards (thay vì JSON trong TEXT)
        $stmtClaimed = $conn->prepare("
            SELECT reward_level, track FROM bp_claimed_rewards WHERE user_id = ?
        ");
        $stmtClaimed->bind_param("i", $userId);
        $stmtClaimed->execute();
        $claimedRows = $stmtClaimed->get_result();
        $stmtClaimed->close();

        $claimedFree = [];
        $claimedPremium = [];
        while ($cr = $claimedRows->fetch_assoc()) {
            if ($cr['track'] === 'premium') {
                $claimedPremium[] = (int)$cr['reward_level'];
            } else {
                $claimedFree[] = (int)$cr['reward_level'];
            }
        }

        echo json_encode([
            'success'         => true,
            'level'           => $stats['level'],
            'xp'              => $stats['xp'],
            'xp_max'          => $stats['level'] * 1000,
            'has_premium'     => (bool)$stats['has_premium'],
            'missions'        => $missions,
            'rewards'         => $rewards,
            'claimed'         => $claimedFree,
            'premium_claimed' => $claimedPremium,
            'season'          => $season
        ]);
        break;

    case 'buy_premium':
        $price = 200000; // Giá Premium Pass: 200k GTLM
        $stmtUser = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
        $stmtUser->bind_param("i", $userId);
        $stmtUser->execute();
        $user = $stmtUser->get_result()->fetch_assoc();
        $stmtUser->close();

        if ($user['Money'] < $price) exit(json_encode(['success' => false, 'message' => 'Không đủ GTLM!']));

        $conn->begin_transaction();
        try {
            $stmtDeduct = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
            $stmtDeduct->bind_param("di", $price, $userId);
            $stmtDeduct->execute();
            $stmtDeduct->close();

            $stmtPrem = $conn->prepare("UPDATE bp_stats SET has_premium = 1 WHERE user_id = ?");
            $stmtPrem->bind_param("i", $userId);
            $stmtPrem->execute();
            $stmtPrem->close();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Kích hoạt Premium Track thành công!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'claim_reward':
        $level = (int) $_POST['level'];
        $track = $_POST['track'] ?? 'free'; // 'free' hoặc 'premium'

        // Whitelist track
        if (!in_array($track, ['free', 'premium'])) {
            exit(json_encode(['success' => false, 'message' => 'Track không hợp lệ!']));
        }

        // Lấy thông tin level của user
        $stmtStats = $conn->prepare("SELECT level, has_premium FROM bp_stats WHERE user_id = ?");
        $stmtStats->bind_param("i", $userId);
        $stmtStats->execute();
        $stats = $stmtStats->get_result()->fetch_assoc();
        $stmtStats->close();

        if (!$stats || $level > $stats['level']) {
            exit(json_encode(['success' => false, 'message' => 'Chưa đạt level!']));
        }

        if ($track === 'premium' && !$stats['has_premium']) {
            exit(json_encode(['success' => false, 'message' => 'Cần mua Premium Pass!']));
        }

        // Lấy thông tin phần thưởng từ DB
        $stmtReward = $conn->prepare("SELECT * FROM bp_rewards WHERE level = ? AND type = ?");
        $stmtReward->bind_param("is", $level, $track);
        $stmtReward->execute();
        $reward = $stmtReward->get_result()->fetch_assoc();
        $stmtReward->close();

        if (!$reward) exit(json_encode(['success' => false, 'message' => 'Không có phần thưởng cho level này!']));

        $conn->begin_transaction();
        try {
            // FIX #6: Dùng INSERT IGNORE vào bảng riêng thay vì UPDATE JSON column
            // UNIQUE KEY (user_id, reward_level, track) đảm bảo atomic — không cần đọc-ghi-đọc
            $stmtClaim = $conn->prepare(
                "INSERT INTO bp_claimed_rewards (user_id, reward_level, track) VALUES (?, ?, ?)"
            );
            $stmtClaim->bind_param("iis", $userId, $level, $track);
            $stmtClaim->execute();

            if ($stmtClaim->affected_rows === 0) {
                // Đã nhận rồi (UNIQUE constraint chặn)
                $stmtClaim->close();
                $conn->rollback();
                exit(json_encode(['success' => false, 'message' => 'Đã nhận rồi!']));
            }
            $stmtClaim->close();

            if ($reward['reward_type'] === 'money') {
                $stmtMoney = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
                $stmtMoney->bind_param("di", $reward['reward_value'], $userId);
                $stmtMoney->execute();
                $stmtMoney->close();
            }
            // (Thêm logic tặng item/skin ở đây nếu cần)

            $conn->commit();
            echo json_encode(['success' => true, 'message' => "Bạn đã nhận: {$reward['reward_name']}"]);
        } catch (Exception $e) {
            $conn->rollback();
            // Nếu lỗi duplicate key — bắt cụ thể
            if ($conn->errno === 1062) {
                echo json_encode(['success' => false, 'message' => 'Đã nhận rồi!']);
            } else {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        }
        break;
}
} // End $isDirectCall check


